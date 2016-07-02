<?php
/**
 * Download Counter Action Plugin
 *
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl.html)
 * @link       http://www.dokuwiki.org/plugin:dlcount
 * @author     Markus Birth <markus@birth-online.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');

class admin_plugin_dlcount extends DokuWiki_Admin_Plugin {
    
    const DATADIR = '_media';   // below $conf['metadir'], no trailing slash
    const SUFFIX  = '.meta';

    /**
     * return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/INFO.txt');
    }

    /**
     * return true if the plugin should only be accessed by wiki admins (default: true)
     */
//    function forAdminOnly() {
//        return true;
//    }

    /**
     * return the menu string to be displayed in the main admin menu
     */
//    function getMenuText($language) {
//        return 'dlcount';
//    }

    /**
     * return sort order for position in admin menu
     */
//    function getMenuSort() {
//        return 999;
//    }

    function glob_recursive($mask) {
        $result = glob($mask);
        if ($result === false) return array();
        $dirs = explode(DIRECTORY_SEPARATOR, $mask);
        $dirmask = array_pop($dirs);
        $dirs  = glob(implode(DIRECTORY_SEPARATOR, $dirs) . '/*', GLOB_ONLYDIR);
        if ($dirs === false) return $result;
        foreach ($dirs as $dir) {
            $partresult = $this->glob_recursive($dir . '/' . $dirmask);
            $result = array_merge($result, $partresult);
        }
        return $result;
    }

    /**
     * handle user request
     */
    function handle() {
        global $conf;
        $mediametadir = $conf['metadir'] . '/' . self::DATADIR;
        $this->mediafiles = $this->glob_recursive($conf['mediadir'] . '/*.*');
        $this->dlcount = array();
        $this->lastdl  = array();
        foreach ($this->mediafiles as $idx=>$mediafile) {
            list ($ext, $mime, $dl) = mimetype($mediafile);
            // skip images as their downloads are NOT counted
            if (substr($mime, 0, 5) == 'image') {
                unset($this->mediafiles[$idx]);
                continue;
            }
            $mediaWN = $this->getWNfromMediaFN($mediafile);
            $metaFN = $mediametadir . '/' . str_replace(':', DIRECTORY_SEPARATOR, $mediaWN) . self::SUFFIX;
            if (!file_exists($metaFN)) {
                $this->dlcount[$mediaWN] = 0;
                $this->lastdl[$mediaWN] = -1;
            } else {
                $meta = unserialize(io_readFile($metaFN, false));
                $this->dlcount[$mediaWN] = $meta['dlcount'];
                $this->lastdl[$mediaWN]  = $meta['lastdl'];
            }
        }
        arsort($this->dlcount);
        arsort($this->lastdl);
    }

    /**
     * output appropriate html
     */
    function html() {
        ptln(sprintf($this->getLang('mediafiles_found'), count($this->mediafiles)) . '<br /><br />');
        ptln('<strong>' . $this->getLang('top_dl_files') . ':</strong><br />');
        ptln('<table class="inline"><tr><th>#</th><th>' . $this->getLang('filename') . '</th><th>' . $this->getLang('downloads') . '</th><th>' . $this->getLang('last_download') . '</th></tr>');
        $top = 1;
        foreach ($this->dlcount as $fn=>$dlc) {
            $lastdl = $this->getLang('never');
            $lastdltime = $this->time_translate(time() - $this->lastdl[$fn]);
            if ($this->lastdl[$fn] > 0) $lastdl = sprintf($this->getLang('ago'), $lastdltime);
            ptln('<tr><th class="rightalign">' . $top++ . '</th><td>' . $fn . '</td><td class="rightalign">' . $dlc . '</td><td class="rightalign">' . $lastdl . '</td></tr>');
            if ($top > $this->getConf('top_n_statistics')) break;
        }
        ptln('</table><br /><br />');

        ptln('<strong>' . $this->getLang('most_recent_dl_files') . ':</strong><br />');
        ptln('<table class="inline"><tr><th>#</th><th>' . $this->getLang('filename') . '</th><th>' . $this->getLang('downloads') . '</th><th>' . $this->getLang('last_download') . '</th></tr>');
        $top = 1;
        foreach ($this->lastdl as $fn=>$dlt) {
            $lastdl = $this->getLang('never');
            $lastdltime = $this->time_translate(time() - $dlt);
            if ($dlt > 0) $lastdl = sprintf($this->getLang('ago'), $lastdltime);
            ptln('<tr><th class="rightalign">' . $top++ . '</th><td>' . $fn . '</td><td class="rightalign">' . $this->dlcount[$fn] . '</td><td class="rightalign">' . $lastdl . '</td></tr>');
            if ($top > $this->getConf('top_n_statistics')) break;
        }
        ptln('</table><br /><br />');

        ptln('<strong>' . $this->getLang('least_recent_dl_files') . ':</strong><br />');
        ptln('<table class="inline"><tr><th>#</th><th>' . $this->getLang('filename') . '</th><th>' . $this->getLang('downloads') . '</th><th>' . $this->getLang('last_download') . '</th></tr>');
        $top = count($this->lastdl);
        foreach (array_reverse($this->lastdl) as $fn=>$dlt) {
            $lastdl = $this->getLang('never');
            $lastdltime = $this->time_translate(time() - $dlt);
            if ($dlt > 0) $lastdl = sprintf($this->getLang('ago'), $lastdltime);
            ptln('<tr><th class="rightalign">' . $top-- . '</th><td>' . $fn . '</td><td class="rightalign">' . $this->dlcount[$fn] . '</td><td class="rightalign">' . $lastdl . '</td></tr>');
            if ($top <= count($this->lastdl)-$this->getConf('top_n_statistics')) break;
        }
        ptln('</table><br /><br />');
    }

    function getWNfromMetaFN($metafn) {
        $fixedpos = strpos($metafn, '/'.self::DATADIR.'/')+strlen('/'.self::DATADIR);
        $wn = substr($metafn, $fixedpos);
        $wn = str_replace(DIRECTORY_SEPARATOR, ':', $wn);
        return $wn;
    }

    function getWNfromMediaFN($mediafn) {
        global $conf;
        $fixedpos = strpos($mediafn, '/'.$conf['mediadir'].'/') + strlen('/'.$conf['mediadir']);
        $wn = substr($mediafn, $fixedpos);
        $wn = str_replace(DIRECTORY_SEPARATOR, ':', $wn);
        return $wn;
    }

    // BEGIN: borrowed and modified from http://de3.php.net/manual/en/function.filesize.php
    function size_translate($filesize) {
        $array = array(
            'TiB' => 1024 * 1024 * 1024 * 1024,
            'GiB' => 1024 * 1024 * 1024,
            'MiB' => 1024 * 1024,
            'KiB' => 1024,
        );
        if($filesize <= 1024) {
            return $filesize . ' B';
        }
        foreach ($array as $name=>$size) {
            if($filesize >= $size) {
                return round((round($filesize / $size * 100) / 100), 2) . ' ' . $name;
            }
        }
        return $filesize;
    }
    // END: borrowed and modified from http://de3.php.net/manual/en/function.filesize.php


    // BEGIN: borrowed and modified from http://de3.php.net/manual/en/function.filesize.php
    function time_translate($seconds) {
        $array = array(
            'y' => 60 * 60 * 24 * 365.25,
            'M' => 60 * 60 * 24 * 30.5,
            'w' => 60 * 60 * 24 * 7,
            'd' => 60 * 60 * 24,
            'h' => 60 * 60,
            'm' => 60,
            's' => 1,
        );
        foreach ($array as $name=>$secs) {
            if ($seconds < $secs && $secs != end($array)) continue;
            $resv = floor($seconds / $secs);
            $res .= ' ' . $resv . $name;
            $seconds -= $resv*$secs;
        }
        return trim($res);
    }
    // END: borrowed and modified from http://de3.php.net/manual/en/function.filesize.php

}