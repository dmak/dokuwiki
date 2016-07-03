<?php
/**
 * Multiline List Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Adrian Sai-wah Tam <adrian.sw.tam@gmail.com>
 */
 
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_mllist extends DokuWiki_Syntax_Plugin {
 
  function getInfo(){
    return array(
      'author' => 'Adrian Sai-wah Tam',
      'email'  => 'adrian.sw.tam@gmail.com',
      'date'   => '2007-06-06',
      'name'   => 'Multiline list plugin',
      'desc'   => 'Allows a list item to break into multiple lines with indentation on non-bullet lines',
      'url'    => 'http://aipl.ie.cuhk.edu.hk/~adrian/doku.php/software/mllist'
    );
  }
 
  function getType(){ return 'container'; }
  function getPType(){ return 'block'; }
  function getSort(){ return 9; }
  
  function getAllowedTypes(){
    return array('formatting', 'substition', 'disabled', 'protected');
  }
  
  function connectTo($mode){
    $indent = '\n(?: {2,}|\t{1,})'; // spaces or tabs
    $list = '(?:-|\*(?!\*))'; // list marker begins with dash or star (which is not followed by star)
    $this->Lexer->addEntryPattern($indent . $list,$mode,'plugin_mllist');
    $this->Lexer->addPattern($indent . $list,'plugin_mllist');
    // Continuation lines need at least three spaces for indentation
    $this->Lexer->addPattern($indent . '(?=\s)','plugin_mllist');
  }
  
  function postConnect(){
    $this->Lexer->addExitPattern('\n','plugin_mllist');
  }
  
  function handle($match, $state, $pos, Doku_Handler $handler){
    switch ($state){
      case DOKU_LEXER_ENTER:
        $ReWriter = new Doku_Handler_List($handler->CallWriter);
        $handler->CallWriter = & $ReWriter;
        $handler->_addCall('list_open', array($match), $pos);
        break;
      case DOKU_LEXER_EXIT:
        $handler->_addCall('list_close', array(), $pos);
        $handler->CallWriter->process();
        $ReWriter = & $handler->CallWriter;
        $handler->CallWriter = & $ReWriter->CallWriter;
        break;
      case DOKU_LEXER_MATCHED:
        if (preg_match("/^\s+$/",$match)) break;
            // Captures the continuation case
        $handler->_addCall('list_item', array($match), $pos);
        break;
      case DOKU_LEXER_UNMATCHED:
        $handler->_addCall('cdata', array($match), $pos);
        break;
    }
    return true;
  }
  
  function render($mode, Doku_Renderer $renderer, $data){
    return true;
  }
}
