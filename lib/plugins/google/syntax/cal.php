<?php
/**
 * Plugin google_cal: Inserts an Google Calendar iframe
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Kite <Kite@puzzlers.org>,  Christopher Smith <chris@jalakai.co.uk> 
 * @seealso    (http://wiki.splitbrain.org/plugin:iframe)
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_google_cal extends DokuWiki_Syntax_Plugin {

	const DEFAULT_HEIGHT = "600";
	const DEFAULT_WIDTH = "98%";

	function getInfo() {
		return array(
			'author1' => 'Kite',
			'email1'  => 'kite@puzzlers.org',
			'author2' => 'Christopher Smith',
			'email2'  => 'chris@jalakai.co.uk',
			'date'   => '2007-05-01',
			'name'   => 'Google Calendar Plugin',
			'desc'   => 'Adds a Google Calendar iframe syntax: {{googlecal>name@address[width=NN,height=NN,date=YYYY-MM-DD]|alternate text}}',
			'url'    => 'http://wiki.splitbrain.org/plugin:google_cal',
		);
	}

	function getType() { return 'substition'; }
	function getPType(){ return 'block'; }
	function getSort() { return 305; }
	function connectTo($mode) { 
		$this->Lexer->addSpecialPattern('{{googlecal>[^}]+?}}', $mode, 'plugin_google_cal'); 
	}

	function handle($match, $state, $pos, &$handler) {
		$matches=array();

		if(preg_match('/{{googlecal>([^}]+?)}}/', $match, $matches)) { // Hook for future features
			$options = array();

			// Handle the simplified style of calendar tag
			@list($opt, $alt) = explode('|', html_entity_decode($matches[1]), 2);

			$options['alt'] = isset($alt) ? trim($alt) : '';

			if (preg_match('/^(.+)\[(.*)\]$/', trim($opt), $matches)) {
				$options['url'] = htmlspecialchars($matches[1]);

				$entries = explode(',', $matches[2]);

				foreach ($entries as $entry) {
					$key_value = explode('=', $entry);

					$options[trim($key_value[0])] = trim($key_value[1]);
				}

				if (!preg_match('/^\d+/', $options['width'])) { // A number followed by something
					$options['width'] = syntax_plugin_google_cal::DEFAULT_WIDTH;
				}

				if (!preg_match('/^\d+$/', $options['height'])) { // Only numbers (in px) are supported
					$options['height'] = syntax_plugin_google_cal::DEFAULT_HEIGHT;
				}

				if (preg_match('/^(\d{4,4})-(\d{1,2})-(\d{1,2})$/', $options['date'], $matches)) { // Should be in format yyyy-mm-dd
					$options['date'] = sprintf('%04d%02d%02d', $matches[1], $matches[2], $matches[3]);
					$options['date'] = htmlspecialchars($options['date'] . '/' . $options['date']);
				} else {
					$options['date'] = null;
				}
			} else { // default settings
				$options['url'] = htmlspecialchars($opt);
				$options['width'] = syntax_plugin_google_cal::DEFAULT_WIDTH;
				$options['height'] = syntax_plugin_google_cal::DEFAULT_HEIGHT;
			}

			if (!$this->getConf('js_ok') && substr($url,0,11) == 'javascript:') {
				return array('error', $this->getLang('gcal_No_JS'));
			}

			return array('wiki', &$options); 
		} else {
			return array('error', $this->getLang('gcal_Bad_iFrame'));  // this is an error
		} // matched {{googlecal>...
	}

	function render($mode, &$renderer, $data) {
		list($style, $options) = $data;
		if($mode == 'xhtml') {
			// Two styles: wiki and error
			switch($style) {
			case 'wiki':
				$options['frameborder'] = 0;

				$renderer->doc .=
					'<iframe src="http://www.google.com/calendar/embed?src=' . $options['url'] .
					'&height=' . $options['height'] .
					'&title=' . $options['alt'];

				if (isset($options['date'])) {
					$renderer->doc .= '&dates=' . $options['date'];
				}

				$renderer->doc .= '"';

				foreach (array('alt', 'width', 'height', 'frameborder') as $attr_name) {
					$renderer->doc .= ' ' . $attr_name . '="' . $options[$attr_name] . '"';
				}

				$renderer->doc .= "></iframe>\n";
				break;
			case 'error':
				$renderer->doc .= '<div class="error">' . $options['url'] . '</div>';
				break;
			default:
				$renderer->doc .= '<div class="error">' . $this->getLang('gcal_Invalid_mode') . '</div>';
				break;
			}
			return true;
		}
		return false;
	}
}
?>
