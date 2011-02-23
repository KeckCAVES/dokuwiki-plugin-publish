<?php

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');


class action_plugin_publish extends DokuWiki_Action_Plugin {

    var $helper = null;

    function __construct() {
        $this->helper =& plugin_load('helper', 'publish');
    }

    function register(&$controller) {
        #$controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, debug, array());
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, handle_action, array());
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, handle_display_banner, array());
        $controller->register_hook('HTML_REVISIONSFORM_OUTPUT', 'BEFORE', $this, handle_revisions, array());
        //$controller->register_hook('HTML_RECENTFORM_OUTPUT', 'BEFORE', $this, handle_recent, array()); //BROKEN
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, handle_start, array());
    }
    
    function handle_action(&$event, $param) {
        global $ACT;
        $status = null;
        switch($ACT) {
            case 'publish': { $status = $this->helper->publish(); break; }
            case 'unpublish': { $status = $this->helper->unpublish(); break; }
        }
        if($status) {
            $event->preventDefault(); // Don't worry, I got this.
            msg($this->getLang($status['msg']), $status['code']);
            $preact = $ACT;
            $ACT = 'show';
            global $ID;
            act_redirect($ID, $preact);
        }
    }

    function debug(&$event, $param) {
        global $ID;
        ptln('<pre>');
        ptln(print_r(p_get_metadata($ID), true));
        ptln(print_r(pageinfo(), true));
        ptln('</pre>');
    }

    function handle_display_banner(&$event, $param) {
        if($event->data != 'show') { return; }
        if(!$this->helper->publishing()) { return; }

        global $ID;
        if(!page_exists($ID)) { return; }

        global $INFO;
        if($INFO['perm'] < AUTH_EDIT) { return; }
        $meta =& $INFO['meta'];
        $publish =& $meta['publish'];

        global $REV;
        $rev = $REV;
        if(!$rev) { $rev = $meta['last_change']['date']; }

        # Published
        $published = null;
        if($publish['cur']) {
            $published = $publish['cur']['rev'];
        }
        
        # Unpublished draft
        $draft = null;
        if($publish['cur']['rev'] != $meta['last_change']['date']) {
            $draft = $meta['last_change']['date'];
        }

        # Previously published
        $previous_published = null;
        if($publish['prev']) {
            $previous_published = $publish['prev']['rev'];
        }

        $strings = array();
        $strings[] = '<div class="publish published_';
        if($rev == $published) { $strings[] = 'yes'; } else { $strings[] = 'no'; }
        $strings[] = '">';

        $strings[] = $this->helper->button(false);

        if($draft) {
            if($rev == $draft) {
                $strings[] = '<span class="publish_draft">';
                $strings[] = sprintf($this->getLang('draft'), 
                                     '<span class="publish_date">' . dformat($draft) . '</span>');
                $strings[] = '</span>';
            } else {
                $strings[] = '<span class="publish_latest_draft">';
                $strings[] = sprintf($this->getLang('recent_draft'), wl($ID));
                $strings[] = $this->difflink($ID, null, $REV) . '</span>';
            }
        }

        if($published) {
            if($rev == $published) {
                $strings[] = '<span class="publish_published">';
                $strings[] = sprintf($this->getLang('published'),
                                     '<span class="publish_date">' . dformat($published) . '</span>',
                                     editorinfo($publish['cur']['client']));
                $strings[] = '</span>';
                if($previous_published) {
                    $strings[] = '<span class="publish_previous">';
                    $strings[] = sprintf($this->getLang('previous'),
                                    wl($ID, 'rev=' . $previous_published),
                                    dformat($previous_published));
                    $strings[] = $this->difflink($ID, $previous_published, $REV) . '</span>';
                }
            } else {
                $strings[] = '<span class="publish_exists">';
                $strings[] = sprintf($this->getLang('has_published'), wl($ID, 'rev=' . $published));
                $strings[] = $this->difflink($ID, $published, $REV);
                $strings[] = '</span>';
            }
        } else {
            $strings[] = '<span class="publish_none">';
            $strings[] = $this->getLang('no_published');
            $strings[] = '</span>';
        }

        $strings[] = '</div>';

        ptln(implode($strings));
        return true;
    }

    function handle_revisions(&$event, $param) {
        if(!$this->helper->publishing()) { return; }
        global $INFO;
        $meta =& $INFO['meta'];

        $open_div = null;
        foreach($event->data->_content as $key => $ref) {
            if($ref['_elem'] == 'opentag' && $ref['_tag'] == 'div' && $ref['class'] == 'li') {
                $open_div = $key;
            }
            
            if($ref['value'] == 'current') $ref['value'] = $meta['last_change']['date'];
            if($open_div && $ref['_elem'] == 'tag' && $ref['_tag'] == 'input' && $ref['name'] == 'rev2[]') {
                if($ref['value'] == $meta['publish']['cur']['rev']) {
                    $event->data->_content[$open_div]['class'] .= ' published_revision';
                }
                $member = null;
            }
        }

        return true;
    }

    // BROKEN
    function handle_recent(&$event, $param) {
        #$meta = p_get_metadata($ID);
        #$latest_rev = $meta['last_change']['date'];

        $member = null;
        foreach($event->data->_content as $key => $ref) {
            if($ref['_elem'] == 'opentag' && $ref['_tag'] == 'div' && $ref['class'] == 'li') {
                $member = $key;
            }

            if($member && $ref['_elem'] == 'opentag' &&
               $ref['_tag'] == 'a' && $ref['class'] == 'diff_link'){
                $name = $ref['href'];
                $name = explode('?', $name);
                $name = explode('&', $name[1]);
                $usename = null;
                foreach($name as $n) {
                    $fields = explode('=', $n);
                    if($fields[0] == 'id') {
                        $usename = $fields[1];
                        break;
                    }
                }
                if($usename) {
                  if($this->helper->publishing($usename)) {
                      $meta = p_get_metadata($usename);

                      if($meta['publish'][$meta['last_change']['date']]) {
                        $event->data->_content[$member]['class'] = 'li published_revision';
                      }else{
                        $event->data->_content[$member]['class'] = 'li unpublished_revision';
                      }
                  }
                }
                $member = null;
            }
        }
        return true;
    }

    function difflink($id, $rev1, $rev2) {
        if($rev1 == $rev2) { return ''; }
        return '<a href="' . wl($id, 'rev2[]=' . $rev1 . '&rev2[]=' . $rev2 . '&do[diff]=1') .
          '" class="published_diff_link">' .
          '<img src="'.DOKU_BASE.'lib/images/diff.png" class="published_diff_link" alt="Diff" />' .
          '</a>';
    }

    function handle_start(&$event, $param) {
        # only apply to show action
        global $ACT;
        if($ACT != 'show') { return; }

        # only apply to latest rev
        global $REV;
        if($REV) { return; }

        # only apply to non-editors
        global $INFO;
        if($INFO['perm'] > AUTH_READ) { return; }

        # only apply to existing pages
        if(!$INFO['exists']) { return; }

        # Check for override token
        global $_GET;
        if($_GET['force_rev']) { return; }

        # Only apply to pages that use publishing
        if(!$this->helper->publishing()) { return; }

        # If latest revision is published, then we're done
        if($INFO['meta']['publish']['cur']['rev'] == $INFO['meta']['last_change']['date']) { return; }

        # If no publications, point to invalid revision
        if(!$INFO['meta']['publish']['cur']) {
            $REV = -1;
            return;
        }

        # Point to most recent published revision
        $REV = $INFO['meta']['publish']['cur']['rev'];
    }
}

