<?php
/**
 * Plugin google_maps: Generates embedded Google Maps frame or link to Google Maps.
 * 
 * @license    GPLv2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Dmitry Katsubo <dma_k@mail.ru>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_google_maps extends DokuWiki_Syntax_Plugin
{
	private $RE_NON_SYNTAX_SEQ = '[^\[\]{}|]+';
	private $RE_PLUGIN_BODY;

	function syntax_plugin_google_maps()
	{
		$this->RE_PLUGIN_BODY = $this->RE_NON_SYNTAX_SEQ . '(?:\\[' . $this->RE_NON_SYNTAX_SEQ . '\\])?';
	}

	function getInfo()
	{
		return array(
			'author'	=> 'Dmitry Katsubo',
			'email'		=> 'dma_k@mail.ru',
			'date'		=> '2010-10-15',
			'name'		=> 'Google Maps Plugin',
			'desc'		=> 'Adds a Google Maps frame 
				 syntax: {{googlemaps>address1;address2;address3[zoom=16,size=small,control=hierarchical,overviewmap=true,width=800,height=600,type=embedded]|alternative text}}',
			'url'    => 'http://centurion.dynalias.com/wiki/plugin/google_maps',
		);
	}

	function getAllowedTypes()
	{
		return array('formatting');
	}

	function getType()
	{
		return 'substition';
	}

	function getSort()
	{
		return 159;
	}

	function connectTo($mode)
	{
		$this->Lexer->addSpecialPattern('{{googlemaps>' . $this->RE_PLUGIN_BODY . '}}', $mode, 'plugin_google_maps'); 
		$this->Lexer->addEntryPattern('{{googlemaps>' . $this->RE_PLUGIN_BODY . '\|(?=' . $this->RE_NON_SYNTAX_SEQ . '}})', $mode, 'plugin_google_maps');
	}

	function postConnect()
	{
		$this->Lexer->addExitPattern('}}', 'plugin_google_maps');
	}

	private function getConfigValue($options, $option_name, $config_prefix = null)
	{
		// Also escape HTML to protect the page:
		return(htmlspecialchars(
			isset($options[$option_name]) ?
				$options[$option_name] :
				$this->getConf($config_prefix . $option_name)
		));
	}

	function handle($match, $state, $pos, &$handler)
	{
		switch ($state)
		{
			case DOKU_LEXER_SPECIAL:
			case DOKU_LEXER_ENTER:
				$matches = array();

				if (!preg_match('/{{googlemaps>(' . $this->RE_NON_SYNTAX_SEQ . ')(?:\\[(' . $this->RE_NON_SYNTAX_SEQ . ')\\])?/', $match, $matches))
				{
					return array('');  // this is an error
				}

				$options = array();

				if (isset($matches[2]))
				{
					$entries = explode(',', $matches[2]);

					foreach ($entries as $entry)
					{
						$key_value = explode('=', $entry);

						$options[trim($key_value[0])] = trim($key_value[1]);
					}
				}

				return array($state, array($matches[1], &$options));
		}
		
		return array($state, $match);
	}

	function render($mode, &$renderer, $data)
	{
		if ($mode == 'xhtml')
		{
			list($state, $match) = $data;

			switch($state)
			{
				case DOKU_LEXER_SPECIAL:
				case DOKU_LEXER_ENTER:
					list($text, $options) = $match;

					// All locations are in this array:
					$locations = array();
					$i = 0;

					foreach (explode(";", $text) as $q)
					{
						$q = trim($q);
						if (strlen($q))
						{
							$locations[$i++] = htmlspecialchars(html_entity_decode($q));
						}
					}

					// This type is available only in DOKU_LEXER_SPECIAL state:
					if ($state == DOKU_LEXER_SPECIAL && $options['type'] == 'embedded')
					{
						// Dynamic injection of this script via JS causes FF to hang, so we have to include it for each map:
						$renderer->doc .= "\n<script type='text/javascript' src='http://maps.google.com/maps?file=api&v=2.x&key=" . $this->getConf('google_api_key') . "'></script>";

						// Default values:
						$size			= $this->getConfigValue($options, 'size');
						$width  		= $this->getConfigValue($options, 'width', $size . '_') . "px";
						$height 		= $this->getConfigValue($options, 'height', $size . '_') . "px";

						// Embedded div:
						$renderer->doc .= "\n<div class='gmaps_frame' style='width: $width; height: $height'";

						foreach ($locations as $i => $q)
						{
							$renderer->doc .= " location$i='$q'";
						}

						// Copy values into attributes:
						foreach (array('size', 'control', 'overviewmap', 'zoom') as $attr_name)
						{
							$attr_value = $this->getConfigValue($options, $attr_name);

							if (strlen($attr_value))
							{
								$renderer->doc .= ' ' . $attr_name . '="' . $attr_value . '"';
							}
						}

						// Important to leave one hanging node inside <div>, otherwise maps start overlappig.
						$renderer->doc .= '></div>';

						return true;
					}

					// If we are here it means:
					// * state == DOKU_LEXER_SPECIAL and type != embedded ==> we render a link with a text equal to address, as there is no alternative text in this state
					// * state == DOKU_LEXER_ENTER   and type != embedded ==> we start rendering a link; the alternative text will be rendered by dokuwiki renderer and may include any formatting
					// * state == DOKU_LEXER_ENTER   and type == embedded ==> the is unsupported combination, but we render a link the same as with type != embedded

					// Concat params:
					$params = '&';
					// If not defined, Google Maps engine will automatically select the best zoom:
					if ($options['zoom'])
					{
						$params .= "z=" . $options['zoom'];
					}

					// Query is already escaped, params are taken from options:
					$url = "http://maps.google.com/maps?q=$locations[0]$params";

					// External link:
					$renderer->doc .= "<a href='$url' class='gmaps_link'>";

					if ($state == DOKU_LEXER_SPECIAL)
					{
						 $renderer->doc .= "$text</a>";
					}

					return true;

				case DOKU_LEXER_UNMATCHED:
					$renderer->doc .= $renderer->_xmlEntities($match);
					return true;
					
				case DOKU_LEXER_EXIT:
					$renderer->doc .= '</a>';
					return true;
				
				default:
					//$renderer->doc .= "<div class='error'>Cannot handle mode $style</div>";
			}
		}

		return false;
	}
}
?>
