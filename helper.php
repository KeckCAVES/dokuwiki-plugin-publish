<?php

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'publish/shared.php');

class helper_plugin_publish extends DokuWiki_Action_Plugin {

    function getMethods() {
        $result = array();
        $result[] = array(
                'name'   => 'publishing',
                'desc'   => 'determine if a given page uses publishing',
                'params' => array('page (optional, default current)' => 'string'),
                'return' => array('authorized' => 'boolean'),
                );
        $result[] = array(
                'name'   => 'authorized',
                'desc'   => 'determine if current user can publish a given page',
                'params' => array('page (optional, default current)' => 'string'),
                'return' => array('authorized' => 'boolean'),
                );
        $result[] = array(
                'name'   => 'button',
                'desc'   => 'returns a button to publish the current page, if possible',
                'params' => array('print (optional, default true)' => 'boolean'),
                'return' => array('html' => 'string'),
                );
        $result[] = array(
                'name'   => 'actionlink',
                'desc'   => 'returns an action link to publish the current page, if possible',
                'params' => array(
                    'prefix (optional, default empty)' => 'string',
                    'suffix (optional, default empty)' => 'string',
                    'inner (optional, default empty)' => 'string',
                    'print (optional, default true)' => 'boolean'),
                'return' => array('html' => 'string'),
                );
        return $result;
    }

    function publishing($page=false) {
        if(!$page) {
            global $ID;
            $page = $ID;
        }
        $patterns = $this->getConf('patterns');
        $patterns = preg_split('/\s+/', $patterns, -1, PREG_SPLIT_NO_EMPTY);
        $page = str_replace(':', '/', $page); // to use fnmatch()
        $accept = false;
        foreach($patterns as $p) { // Check against namespace wildcards
            $p = str_replace(':', '/', $p); // to use fnmatch()
            $include = true;
            if (substr($p,0,1) == '-') { $include=false; $p = substr($p,1); }
            else if (substr($p,0,1) == '+') { $p = substr($p,1); }
            if (fnmatch($p, $page)) { $accept = $include; break; }
        }
        return $accept;
    }

    function authorized($page=false) {
        global $INFO;
        if($page) {
            $perm = auth_quickaclcheck($page);
        } else {
            $perm = $INFO['perm'];
        }
        $auth = $this->getConf('auth');
        switch($auth) {
            case 'Edit': { return $perm >= AUTH_EDIT; }
            case 'Create': { return $perm >= AUTH_CREATE; }
            case 'Upload': { return $perm >= AUTH_UPLOAD; }
            case 'Delete': { return $perm >= AUTH_DELETE; }
            case 'Manager': { return $INFO['ismanager']; }
            case 'Admin': { return $INFO['isadmin']; }
            default: { return false; }
        }
    }

    function button($print=true) {
        if(!$this->authorized()) { return ''; }
        global $ID;
        $out = html_btn('publish', $ID, 'p',
                        array('do' => 'publish'), // params
                        'get', //method
                        '', // tooltip
                        $this->getLang('do_publish'));
        if ($print) print $out;
        return $out;
    }

    function actionlink($pre='', $suf='', $inner='', $print=true) {
        if(!$this->authorized()) { return ''; }
        global $ID;
        $accesskey = 'p';
        $caption = $this->getLang('do_publish');
        $type = 'publish';
        $out = tpl_link('?do=publish', $pre.(($inner)?$inner:$caption).$suf,
                        'class="action ' . $type . '" ' .
                        'accesskey="' . $accesskey . '" rel="nofollow" ' .
                        'title="' . hsc($caption) . '"', true);
        if ($print) print $out;
        return $out;
    }
}
