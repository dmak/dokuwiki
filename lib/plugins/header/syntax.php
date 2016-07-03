<?php
/**
 * Plugin Header: Header Replacement Plugin - EXPERIMENTAL
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_header extends DokuWiki_Syntax_Plugin {
 
    var $idx = false;
    var $placeholder = '<h# id="@">';
    var $counters = array(NULL,0,0,0,0,0,0);
    var $in_use = false;
 
    // settings (move to plugin config) FIXME!
    var $dflt_depth = 3;      // max header depth at which counters will apply
    var $depth = 0;           
    var $dflt_pattern = '#.'; // pattern used for counter substitution
    var $pattern = '#.';
 
    function getInfo(){
      return array(
        'author' => 'Christopher Smith',
        'email'  => 'chris@jalakai.co.uk',
        'date'   => '2006-04-05',
        'name'   => 'Header Replacement Plugin',
        'desc'   => 'Replace/Upgrade Heading Syntax',
        'url'    => 'http://wiki.splitbrain.org/plugin:header',
      );
    }
 
    function getSort() { return 45; }                       /* same as header */
    function getType() { return 'baseonly'; }
    function getAllowedTypes() { return array('substition', 'formatting'); }
    function getPType(){ return 'normal';}
 
    function connectTo($mode) { 
        $this->Lexer->addSpecialPattern('~~OH[^\n]*~~',$mode,'plugin_header');
        $this->Lexer->addEntryPattern('[ \t]*={2,6}(?=[^\n]+={2,}[ \t]*\n)',$mode,'plugin_header'); 
    }
 
    function postConnect() {
      $this->Lexer->addExitPattern('={2,}[ \t]*(?=\n)', 'plugin_header');
    }
 
    function handle($match, $state, $pos, Doku_Handler $handler){
      static $level;
      switch ($state) {
          case DOKU_LEXER_ENTER:
            $level = 7 - strlen(trim($match));
            if ($handler->status['section']) {
              $handler->_addCall('section_close', array(), $pos);
            } else {
              $handler->_addCall('p_close', array(), $pos);
            }
            return array('header_open',$level,$pos);
 
//        case DOKU_LEXER_MATCHED:
 
          case DOKU_LEXER_SPECIAL:
            $data = trim(substr($match, 4, -2));
            $settings = array();
            if (preg_match('/^(?::(\d))?(?:\|(.*))?$/',$data,$settings)) { 
              array_shift($settings);
            } else {
              $settings = array($this->dflt_depth,$this->dflt_pattern);
            }
 
            $data = array('header_settings', $settings, $pos);
 
            // put the settings instruction at the front of the instruction stack
            array_unshift($handler->calls, array('plugin', array('header', $data, $pos), $pos));            
            return array('header_none',NULL,NULL);
 
 
          case DOKU_LEXER_UNMATCHED:                
            return array('header_cdata', $match, $pos);
 
          case DOKU_LEXER_EXIT:
            // hack! add our plugin call and after that a section open call
            $data = array('header_close', $level, $pos);
            $handler->_addCall('plugin', array('header', $data, $pos), $pos);
            $handler->_addCall('section_open', array($level), $pos);    
            $handler->status['section'] = true;
            return array('header_none',NULL,NULL);
 
      } 
 
      return NULL;
    }
 
    function render($mode, Doku_Renderer $renderer, $indata) {
 
      list($instr, $data, $pos) = $indata;
 
      if($mode == 'xhtml'){
        switch ($instr) {
          case 'header_settings' : 
            if (count($data) >= 2) {
              list($this->depth, $this->pattern) = $data;
            }
            $this->in_use = true;
            break;
 
          case 'header_open' :
#            $renderer->doc .= "</p>";
            $this->xhtml_header_open($renderer, $data, $pos);
            break;
 
          case 'header_cdata' :   
            $renderer->doc .= $renderer->_xmlEntities($data); 
            break;
 
          case 'header_close' : 
            $this->xhtml_header_close($renderer, $data);
#            $renderer->doc .= "<p>";
            break;
        }
 
        return true;
      }
 
      return false;
    }
 
    function xhtml_header_open(&$renderer, $level, $pos) {
        global $conf;
 
        //copied from parser/xhtml.php
        //handle section editing
        if($level <= $conf['maxseclevel']){
            // add button for last section if any
            // API has changed so that below function does not exist. html_secedit() should be called for complete page.
            // if($renderer->lastsec) $renderer->_secedit($renderer->lastsec,$pos-1);
            // remember current position
            $renderer->lastsec = $pos;
        }
 
        $this->idx = strlen($renderer->doc);
        $renderer->doc .= $this->placeholder;
    }
 
    function xhtml_header_close(&$renderer, $level) {
        global $conf;
 
        // grab header title text
        // 1: locate our header text
        //    for efficiency, look first at the idx into renderer->doc saved in xhtml_header_open
        //    if we don't find anything, then try looking from the start of the document
        if (($this->idx = strpos($renderer->doc, $this->placeholder, $this->idx)) === false) {
          $this->idx = strpos($renderer->doc, $this->placeholder);
        }
 
        // rendered header data is from located position to the end of the string
        $header = substr($renderer->doc,$this->idx+strlen($this->placeholder));
 
        // remove any xhtml markup, leaving the CDATA
        $title = preg_replace('/<.*?>/','',$header);
 
        // create an id for the header
        $id = $renderer->_headerToLink($title,'true');
 
        //handle TOC
        if ($title) {    
          // the TOC is one of our standard ul list arrays ;-)
          $renderer->toc_additem($id, trim($title), $level);
        }
 
        // rebuild the rendered doc
        // contents up to header_open + header_open + counter + rendered header data + header_close
        $renderer->doc = substr($renderer->doc,0,$this->idx);
        $renderer->doc .= '<h'.$level.' id="'.$id.'">';
 
        if ($this->in_use && ($level <= $this->depth))
          $renderer->doc .= '<span class="counter">'.$this->xhtml_header_counter($level).'</span>';
 
        $renderer->doc .= $header;
        $renderer->doc .= '</h'.$level.'>'.DOKU_LF;
 
        // reset status variables
        $this->idx = false;
    }
 
    function xhtml_header_counter($level) {
 
        // sanity check
        if ($level > $this->depth) return '';
 
        // increment the counter for this level
        $this->counter[$level]++;
 
        // reset counters at deeper levels
        for ($i=$level+1; $i<=$this->depth; $i++) $this->counter[$i] = 0;
 
        // generate the counter string, performing conversions/substitutions as requested
        $out = '';
        for ($i=1; $i<=$level; $i++) {
          $x = $this->counter[$i];
          $r = $this->roman($x);
          $values = array($x,strtolower($r),$r, chr(ord('A')+$x-1), chr(ord('a')+$x-1));
          $out .= str_replace(array('#','i','I','A','a'),$values,$this->pattern);
        }
 
        return $out;
    }
 
    // generate roman numerals
    function roman($n) {
        $ones = array('','I','II','III','IV','V','VI','VII','VIII','IX');
        $tens = array('','X','XX','XXX','XL','L','LX','LXX','LXXX','XC');
        $huns = array('','C','CC','CCC','CD','D','DC','DCC','DCCC','CM');
        $thou = array('','M','MM','MMM');
 
        $idx = str_pad(strrev((string)($n)),4,'0');
 
        $out = $thou[$idx{3}].$huns[$idx{2}].$tens[$idx{1}].$ones[$idx{0}];
 
        return $out;
    }
}
?>
