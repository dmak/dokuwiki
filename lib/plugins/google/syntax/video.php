<?php
/**
 * Syntax Plugin Prototype
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');


/**
* All DokuWiki plugins to extend the parser/rendering mechanism
* need to inherit from this class
*/
class syntax_plugin_google_video extends DokuWiki_Syntax_Plugin {
	/**
	* return some info
	*/
	function getInfo(){
		return array(
		'author' => 'Fran&ccedil;ois Laperruque',
		'email'  => 'francois.laperruque@toulouse.inra.fr',
		'date'   => '2007-08-02',
		'name'   => 'Google Video Plugin',
		'desc'   => 'Google Video link and object
					 syntax: {{googlevideo>type:ID}}',
		'url'    => 'http://flap.mynetmemo.com/?p=15',
		);
	}
	function getType(){ return 'substition'; }
	function getSort(){ return 159; }
	function connectTo($mode) 
	{ 
		$this->Lexer->addSpecialPattern('\{\{googlevideo>[^}]*\}\}',$mode,'plugin_google_video'); 
	}
	/**
	* Handle the match
	*/
	function handle($match, $state, $pos, &$handler){
		$match = substr($match,14,-2); // Strip markup
		return array($state,explode(':',$match));
	}	
	/**
	* Create output
	*/
	function render($mode, &$renderer, $data) {
		if($mode == 'xhtml')
		{
			list($state, $match) = $data;
			list($disptype,$id) = $match;
			
			$href_start = '<a href="http://video.google.com/googleplayer.swf?docId='.$id.'">';			
			
			$LOGO_URL = '/_media/icon/googlevideo.png';
			if ($disptype=='link'){
				$renderer->doc .= $href_start;
				$renderer->doc .= '<img src="'.$LOGO_URL.'" alt="movie-'.$id.'"/></a>';
			}
			elseif ($disptype=='large')
			{
				$obj  = '<object width="425" height="350" type="application/x-shockwave-flash"';
				$obj .= ' data="http://video.google.com/googleplayer.swf?docId='.$id.'">';
				$obj .= '<param name="movie" value="http://video.google.com/googleplayer.swf?docId='.$id.'"></object>';
				$renderer->doc .= $obj;
			}
			elseif($disptype=='small'){
	                        $obj  = '<object width="255" height="210" type="application/x-shockwave-flash"';
				$obj .= ' data="http://video.google.com/googleplayer.swf?docId='.$id.'">';
				$obj .= '<param name="movie" value="http://video.google.com/googleplayer.swf?docId='.$id.'"></object>';
				$renderer->doc .= $obj;
			}
			else
			{
				$renderer->doc .="??";
			}
			return true;
		}
		return false;
	}
}

?>
