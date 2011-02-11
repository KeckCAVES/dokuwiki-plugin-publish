<?php

// TODO:
// Old Revisions Display   X
// Recent Changes Display X
// Redirection X
// List of Unpublished Documents user has permission to publish X
// Namespace restrictions + admin X
// Diff Links in banner on Prev published X
// List of Recent publications X
// Subscriptions should show publications - hard (MAIL_MESSAGE_SEND is the only appropriate hook)
// Allow submits of docs with no changes for publication, with autocomment X
// RSS Info -- hard (no hooks in feed.php)
// Internationalisation (or not) X

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_PLUGIN.'publish/shared.php');



class action_plugin_publish extends DokuWiki_Action_Plugin {

    function getInfo() { return publish_getInfo(); }

    function pageUsesPublish($page) {
        return publish_pageIncluded($page, $this->getConf('patterns'));
    }

    function authorized() {
        global $INFO;
        $auth = $this->getConf('auth');
        if($auth == 'Edit') { return $INFO['perm'] >= AUTH_EDIT; }
        else if($auth == 'Create') { return $INFO['perm'] >= AUTH_CREATE; }
        else if($auth == 'Upload') { return $INFO['perm'] >= AUTH_UPLOAD; }
        else if($auth == 'Delete') { return $INFO['perm'] >= AUTH_DELETE; }
        else if($auth == 'Manager') { return $INFO['ismanager']; }
        else if($auth == 'Admin') { return $INFO['isadmin']; }
        else { return false; }
    }

    function register(&$controller) {
        #$controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, debug, array());
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, handle_action, array());
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, handle_display_banner, array());
        $controller->register_hook('HTML_REVISIONSFORM_OUTPUT', 'BEFORE', $this, handle_revisions, array());
        $controller->register_hook('HTML_RECENTFORM_OUTPUT', 'BEFORE', $this, handle_recent, array());
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, handle_start, array());
    }
    
    function handle_action(&$event, $param) {
        global $ACT;
        if(!in_array($ACT, array('publish'))) { return; }

        $event->preventDefault(); // Don't worry, I've got this.
        $this->publish();
        $preact = $ACT;
        $ACT = 'show';
        global $ID;
        act_redirect($ID, $preact);
    }

    function publish() {
        global $ID;
        global $REV;
        global $INFO;
        global $USERINFO;

        if(!$this->authorized()) {
            msg('You do not have permission to publish.', -1);
            return;
        }
        if(!$this->pageUsesPublish($ID)) {
            msg('This page does not require publishing.', -1);
            return;
        }
        if($REV) {
            msg('Only most recent revision may be published.', -1);
            return;
        }

        $publish = $INFO['meta']['publish'];
        if(!$publish) { $publish = array(); }

        $rev = $INFO['lastmod']; // Revision to publish

        if(is_array($publish[$rev])) {
            msg('This revision is already published.', -1);
            return;
        }

        $publish[$rev] = array($INFO['client'], $USERINFO['name'], $USERINFO['mail']);
        p_set_metadata($ID, array('publish' => $publish));
        msg('Published page', 1);
    }

    function debug(&$event, $param) {
        global $ID;
        ptln('<pre>');
        ptln(print_r(p_get_metadata($ID), true));
        ptln(print_r(pageinfo(), true));
        ptln('</pre>');
    }

    function handle_display_banner(&$event, $param) {
        $strings = array();
        global $ID;
        if(!$this->pageUsesPublish($ID)) { return; }
        global $REV;
        if($event->data != 'show') { return true; }
        if(!page_exists($ID)) { return; }
        global $INFO;
        if($INFO['perm'] < AUTH_EDIT) { return; }
        $meta = p_get_metadata($ID);
        $rev = $REV;
        if(!$rev) { $rev = $meta['last_change']['date']; }
        if(!$meta['publish']) { $meta['publish'] = array(); }
        $allpublished = array_keys($meta['publish']);
        sort($allpublished);
        $latest_rev = $meta['last_change']['date'];
        #$strings[] = '<!-- ' . print_r($meta, true) . '-->';

        $longdate = date('d/m/y H:i', $rev);


        # Is this document published?
        $publisher = null;
        $date = null;
        if($meta['publish'][$rev]) {
            # Published
            if(is_array($meta['publish'][$rev])) {
              $publisher = $meta['publish'][$rev][1]; // Try full name
              if(!$publisher) { $publisher = $meta['publish'][$rev][2]; } // Try email address
              if(!$publisher) { $publisher = $meta['publish'][$rev][0]; } // Try login name
              $publisher = '<a href="mailto:' . 
                  $meta['publish'][$rev][2] .
                  '">' .
                  $publisher .
                  '</a>';
            }else{
              $publisher = $meta['publish'][$rev];
            }

            $date = date('d/m/Y', $rev);
        }

        # What is the most recent published version?
        $most_recent_published = null;
        $id = count($allpublished)-1;
        if($id >= 0) {
            if($allpublished[$id] > $rev) {
                $most_recent_published = $allpublished[$id];
            }
        }
        
        # Latest, if draft
        $most_recent_draft = null;
        #$strings[] = '<!-- lr='.$latest_rev.', r='.$rev.', mra='.$most_recently_published.', d='.($latest_rev != $rev).','.($latest_rev != $most_recently_published).' -->';
        if($latest_rev != $rev && $latest_rev != $most_recent_published) {
            $most_recent_draft = $latest_rev;
        }

        # Published *before* this one
        $previous_published = null;
        foreach($allpublished as $arev) {
            if($arev >= $rev) { break; }
            $previous_published = $arev;
        }

        $strings[] = '<div class="publish published_';
        if($publisher && !$most_recent_published) { $strings[] = 'yes'; } else { $strings[] = 'no'; }
        $strings[] = '">';

        if($most_recent_draft) {
            $strings[] = '<span class="publish_latest_draft">';
            $strings[] = sprintf($this->getLang('recent_draft'), wl($ID, 'force_rev=1'));
            $strings[] = $this->difflink($ID, null, $REV) . '</span>';
        }

        if($most_recent_published) {
            # Published, but there is a more recent version
            $userrev = $most_recent_published;
            if($userrev == $latest_rev) { $userrev = ''; }
            $strings[] = '<span class="publish_outdated">';
            $strings[] = sprintf($this->getLang('outdated'), wl($ID, 'rev=' . $userrev));
            $strings[] = $this->difflink($ID, $userrev, $REV) . '</span>';
        }

        if(!$publisher) {
            # Draft
            $strings[] = '<span class="publish_draft">';
            $strings[] = sprintf($this->getLang('draft'), 
                            '<span class="publish_date">' . $longdate . '</span>');
            if(!$most_recent_draft && $this->authorized()) {
                $strings[] = '  ' . tpl_link('?do=publish', 'You can publish it.', '', true);
            }
            $strings[] = '</span>';
        }

        if($publisher) {
            # Published
            $strings[] = '<span class="publish_published">';
            $strings[] = sprintf($this->getLang('published'),
                            '<span class="publish_date">' . $longdate . '</span>',
                            $publisher);
            $strings[] = '</span>';
        }

        if($previous_published) {
            $strings[] = '<span class="publish_previous">';
            $strings[] = sprintf($this->getLang('previous'),
                            wl($ID, 'rev=' . $previous_published),
                            date('d/m/y H:i', $previous_published));
            $strings[] = $this->difflink($ID, $previous_published, $REV) . '</span>';
        }

        $strings[] = '</div>';

        ptln(implode($strings));
        return true;
    }

    function handle_revisions(&$event, $param) {
        global $ID;
        if(!$this->pageUsesPublish($ID)) { return; }
        global $REV;
        $meta = p_get_metadata($ID);
        $latest_rev = $meta['last_change']['date'];

        $member = null;
        foreach($event->data->_content as $key => $ref) {
            if($ref['_elem'] == 'opentag' && $ref['_tag'] == 'div' && $ref['class'] == 'li') {
                $member = $key;
            }

            if($member && $ref['_elem'] == 'tag' &&
                $ref['_tag'] == 'input' && $ref['name'] == 'rev2[]'){
                if($meta['publish'][$ref['value']] ||
                        ($ref['value'] == 'current' && $meta['publish'][$latest_rev])) {
                  $event->data->_content[$member]['class'] = 'li published_revision';
                }else{
                  $event->data->_content[$member]['class'] = 'li unpublished_revision';
                }
                $member = null;
            }
        }


        return true;
    }

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
                  if($this->pageUsesPublish($usename)) {
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
        # show only
        global $ACT;
        if($ACT != 'show') { return; }

        # only apply to latest rev
        global $REV;
        if($REV != '') { return; }

        # apply to readers only
        global $INFO;
        if($INFO['perm'] != AUTH_READ) { return; }

        # Check for override token
        global $_GET;
        if($_GET['force_rev']) { return; }

        # Only apply to appropriate patterns
        global $ID;
        if(!$this->pageUsesPublish($ID)) { return; }

        # Get page metadata
        $meta = p_get_metadata($ID);

        # If latest revision is published, then we're done
        if($meta['publish'][$meta['last_change']['date']]) { return; }

        # If no publications of existing page, point to invalid revision
        if(!$meta['publish']) {
            if($INFO['exists']) { $REV = -1; }
            return;
        }

        # Point to most recent published revision
        $all = array_keys($meta['publish']);
        $REV = $all[count($all)-1];
    }
}

