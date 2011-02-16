<?php

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();


// Supplementary setting classes
plugin_load('admin', 'config');
if (!class_exists('setting_publish_textarea')) {
  class setting_publish_textarea extends setting {
      // Used to get textarea provided by default setting class
      // without Dokuwiki complaining.
  }
}


class helper_plugin_publish extends DokuWiki_Action_Plugin {

    function getMethods() {
        $result = array();
        $result[] = array(
                'name'   => 'publishing',
                'desc'   => 'determine if a given page uses publishing',
                'params' => array('page (optional, default current)' => 'string'),
                'return' => array('authorized' => 'boolean')
                );
        $result[] = array(
                'name'   => 'authorized',
                'desc'   => 'determine if current user can publish a given page',
                'params' => array('page (optional, default current)' => 'string'),
                'return' => array('authorized' => 'boolean')
                );
        $result[] = array(
                'name'   => 'button',
                'desc'   => 'returns a button to (un)publish the current page, if possible',
                'params' => array('print (optional, default true)' => 'boolean'),
                'return' => array('html' => 'string')
                );
        $result[] = array(
                'name'   => 'actionlink',
                'desc'   => 'returns an action link to (un)publish the current page, if possible',
                'params' => array(
                    'prefix (optional, default empty)' => 'string',
                    'suffix (optional, default empty)' => 'string',
                    'inner (optional, default empty)' => 'string',
                    'print (optional, default true)' => 'boolean'),
                'return' => array('html' => 'string')
                );
        $result[] = array(
                'name'   => 'publish',
                'desc'   => 'publishes the current page',
                'params' => array(),
                'return' => array('result' => 'array')
                );
        $result[] = array(
                'name'   => 'unpublish',
                'desc'   => 'unpublish the current page',
                'params' => array(),
                'return' => array('result' => 'array')
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
        switch($this->getConf('auth')) {
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
        $type = $this->_action_type();
        if(!$type) return '';
        global $ID;
        $out = html_btn($type, $ID, substr($type, 0, 1),
                        array('do' => $type), // params
                        'get', //method
                        '', // tooltip
                        $this->getLang('do_' . $type));
        if ($print) print $out;
        return $out;
    }

    function actionlink($pre='', $suf='', $inner='', $print=true) {
        $type = $this->_action_type();
        if(!$type) return '';
        global $ID;
        $accesskey = substr($type, 0, 1);
        $caption = $this->getLang('do_' . $type);
        $out = tpl_link('?do=' . $type, $pre.(($inner)?$inner:$caption).$suf,
                        'class="action ' . $type . '" ' .
                        'accesskey="' . $accesskey . '" rel="nofollow" ' .
                        'title="' . hsc($caption) . '"', true);
        if ($print) print $out;
        return $out;
    }

    function publish() {
        global $ID;
        global $INFO;
        $publish = $INFO['meta']['publish'];

        if ($result = $this->_operate_check()) return $result;

        if($publish['cur']['rev'] == $INFO['meta']['last_change']['date'])
            return array('msg' => 'warn_pub', 'code' => 0);

        unset($publish['unpub']);
        $publish['prev'] = $publish['cur'];
        $publish['cur'] = array(
            'rev' => $INFO['meta']['last_change']['date'], 
            'client' => $INFO['client'], 
            'date' => $_SERVER['REQUEST_TIME']);
        p_set_metadata($ID, array('publish' => $publish));
        return array('msg' => 'ok_pub', 'code' => 1);
    }

    function unpublish() {
        global $ID;
        global $INFO;
        $publish = $INFO['meta']['publish'];
        
        if ($result = $this->_operate_check()) return $result;

        if($publish['cur']['rev'] != $INFO['meta']['last_change']['date'])
            return array('msg' => 'warn_unpub', 'code' => 0);

        $publish['unpub'] = array(
            'rev' => $INFO['meta']['last_change']['date'], 
            'client' => $INFO['client'], 
            'date' => $_SERVER['REQUEST_TIME']);
        $publish['cur'] = $publish['prev'];
        unset($publish['prev']);
        p_set_metadata($ID, array('publish' => $publish));
        return array('msg' => 'ok_unpub', 'code' => 1);
    }

    function _action_type() {
        if(!$this->authorized()) { return ''; }
        if(!$this->publishing()) { return ''; }
        global $INFO;
        $rev = $INFO['rev'];
        $cur = $INFO['meta']['last_change']['date'];
        $publish_cur = $INFO['meta']['publish']['cur']['rev'];
        if(!$rev || $rev == $cur) {
            if($publish_cur != $cur) { return 'publish'; }
            else { return 'unpublish'; }
        }
        return '';
    }

    function _operate_check() {
        global $REV;
        if(!$this->authorized()) {
            return array('msg' => 'bad_perm', 'code' => -1);
        }
        if(!$this->publishing()) {
            return array('msg' => 'bad_page', 'code' => -1);
        }
        if($REV) {
            return array('msg' => 'bad_rev', 'code' => -1);
        }
        return array();
    }
}
