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
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_dlcount extends DokuWiki_Action_Plugin {
    
    const DATADIR = '_media';   // below $conf['metadir'], no trailing slash
    const SUFFIX  = '.meta';

    /**
     * return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/INFO.txt');
    }

    /*
     * plugin should use this method to register its handlers with the dokuwiki's event controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('MEDIA_SENDFILE', 'BEFORE', $this, '_countdl');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, '_showcount');
        $controller->register_hook('MEDIA_DELETE_FILE', 'AFTER', $this, '_delcounter');
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'BEFORE', $this, '_delcounter2');
    }

    function _delcounter(&$event, $param) {
        $metafn = $this->metaFnFromFullPath($event->data['path']);
        if (file_exists($metafn)) @unlink($metafn);
    }

    function _delcounter2(&$event, $param) {
        $metafn = $this->metaFnFromFullPath($event->data[1]);
        // TODO: Maybe keep counter if updating file ($event->data[4] == 1, i.e. overwrite)
        if (file_exists($metafn)) @unlink($metafn);
    }

    function _showcount(&$event, $param) {
        global $conf;
        if (!$this->getConf('show_dlcount') && !$this->getConf('show_filesize')) return;   // return if there's nothing to do
        $html = &$event->data;
        $matchct = preg_match_all('/\<a href="([^"]*)" class="[^"]*mediafile[^"]*"[^\>]*>[^\<]*\<\/a\>/', $html, $matches, PREG_OFFSET_CAPTURE|PREG_PATTERN_ORDER);
        if ($matchct == 0) return;   // do nothing
        $newhtml = '';
        $lastoffset = 0;
        //print_r($matches); die();
        foreach ($matches[0] as $i=>$match) {
            $href = $matches[1][$i][0];
            $fn = false;
            if (strpos($href, 'fetch.php?') !== false) {
                // no rewrite (http://wiki.birth-online.de/lib/exe/fetch.php?media=software:php:dlcount.tar.gz)
                $fn = '/' . substr($href, strpos($href, '?media=')+strlen('?media='));
                $fn = str_replace(':', '/', $fn);
            } elseif (strpos($href, 'fetch.php/') !== false) {
                // no rewrite with useslash (http://wiki.birth-online.de/lib/exe/fetch.php/software/php/dlcount.tar.gz)
                // THANKS TO: TMH 2009-02-22 via dokuwiki.org
                $fn = '/' . substr($href, strpos($href, 'fetch.php/')+strlen('fetch.php/'));
                $fn = str_replace(':', '/', $fn);
            } else {
                // rewrite (http://wiki.birth-online.de/_media/software/php/dlcount.tar.gz)
                $fn = substr($href, strpos($href, '/_media/')+strlen('/_media/')-1);
            }
            if ($conf['fnencode'] == 'utf-8') {
                $fn = urldecode($fn);
            }
            $metafn = $conf['metadir'] . '/' . self::DATADIR . $fn . self::SUFFIX;
            $meta = array('dlcount' => 0);
            $txt = array();
            if (file_exists($metafn)) $meta = unserialize(io_readFile($metafn, false));
            if ($this->getConf('show_filesize')) $txt['filesize'] = $this->size_translate(filesize($conf['mediadir'] . '/' . $fn));
            if ($this->getConf('show_lastmod')) {
                $fmod = filemtime($conf['mediadir'] . '/' . $fn);
                $txt['lastmod'] = '<acronym title="Modified: ' . date('Y-m-d H:i.s', $fmod) . '">' . reset(explode(' ', $this->time_translate(time()-$fmod))) . ' ago</acronym>';
            }
            if ($this->getConf('show_dlcount')) {
                if ($meta['dlcount'] != 1) $s = 's'; else $s = '';
                $txt['dlcount'] = $meta['dlcount'] . ' download' . $s;
                if (isset($meta['lastdl'])) $txt['dlcount'] = '<acronym title="Last download: ' . $this->time_translate(time()-$meta['lastdl']). ' ago.">' . $txt['dlcount'] . '</acronym>';
            }
            $txt = ' (' . implode(', ', $txt) . ')';
            $afteroffset = $match[1] + strlen($match[0]);
            $newhtml .= substr($html, $lastoffset, $afteroffset-$lastoffset) . $txt;
            $lastoffset = $afteroffset;
        }
        $newhtml .= substr($html, $lastoffset);
        $html = $newhtml;
    }

    function _countdl(&$event, $param) {
        if ($event->data['download'] != 1) return;   // skip embedded images (we don't want to count these)
        $metafn = $this->metaFnFromFullPath($event->data['file']);

        // read metafile
        $ctr = 0;
        $meta = array();
        if (file_exists($metafn)) {
            $metastring = io_readFile($metafn, false);
            $meta = unserialize($metastring);
            if (isset($meta['dlcount'])) {
                $ctr = $meta['dlcount'];
            }
        }

        // advance counter
        $ctr++;

        // more statistics
        $meta['lastdl'] = time();
        $meta['dlusers'][] = array(
            'Time' => time(),
            'IP' => $this->getClientIP(),
            'Referer' => $_SERVER['HTTP_REFERER'],
            'UserAgent' => $_SERVER['HTTP_USER_AGENT'],
        );
        $meta['dlusers'] = array_slice($meta['dlusers'], -50);   // only keep last 50 downloaders

        // output to metafile
        io_makeFileDir($metafn);
        $meta['dlcount'] = $ctr;
        io_saveFile($metafn, serialize($meta));
    }

    /**
     * Returns the filename of the META file for specified MEDIA file
     * @global array $conf Global Configuration
     * @param string $fullpath Path to MEDIA file
     * @return string Path to META file
     */
    function metaFnFromFullPath($fullpath) {
        global $conf;
        $mediadir = realpath($conf['mediadir']);
        $fn = str_replace($mediadir, '', $fullpath);
        return $conf['metadir'] . '/' . self::DATADIR . $fn . self::SUFFIX;
    }

    /**
     * Checks for a valid non-127-IP
     * @param string $ip IP-Address
     * @return bool TRUE if IP is valid and not localnet, FALSE if invalid or local
     */
    function isValidIP($ip) {
        $ip = trim($ip);
        $isvalid = true;
        $i = explode('.', $ip, 4);
        $iv = array();

        foreach ($i as $key=>$val) {
            $iv[$key] = intval($val);
            if (strlen($val)>3 || strlen($val)<=0 || $iv[$key]<0 || $iv[$key]>255) {
                $isvalid = false;
            }
        }
        if ($iv[0]==0) {
            $isvalid = false;
        }
        if ($iv[0]==127 && $iv[1]==0 && $iv[2]==0 && $iv[3]==1) {
            $isvalid = false;
        }
        return $isvalid;
    }

    /**
     * Returns the IP of the accessing client PC, if possible
     * @return string|bool The client IP or FALSE on error.
     */
    function getClientIP() {
        $addr = $_SERVER['HTTP_X_FORWARDED_FOR'];
        if (!empty($addr) && $this->isValidIP($addr)) return $addr;
        $addr = $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($addr) && $this->isValidIP($addr)) return $addr;
        $addr = $_SERVER['REMOTE_ADDR'];
        if (!empty($addr) && $this->isValidIP($addr)) return $addr;
        return false;
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