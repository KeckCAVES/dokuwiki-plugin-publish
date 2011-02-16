<?php

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');


class syntax_plugin_publish extends DokuWiki_Syntax_Plugin {
 
    var $helper = null;

    function __construct() {
        $this->helper =& plugin_load('helper', 'publish');
    }

    function pattern() { return '\[UNPUBLISHED.*?\]'; }
    function getType() { return 'substition'; }
    function getSort() { return 20; }
    function PType() { return 'block'; }
    function connectTo($mode) { $this->Lexer->addSpecialPattern($this->pattern(),$mode,'plugin_publish'); }
    function handle($match, $state, $pos, &$handler){
        $namespace = substr($match, 13, -1);
        return compact('match', 'state', 'pos', 'namespace');
    }

    function render($mode, &$renderer, $data) {
      global $conf;
      extract($data); // match, state, pos, namespace

      if($mode == 'xhtml'){
          $namespace = cleanID(getNS($namespace . ':'));
          $pages = array();
          search($pages, $conf['datadir'], array($this, '_search_helper'), array($namespace));
          if(count($pages) == 0) {
              $renderer->doc .= '<p class="pub_none">' . $this->getLang('p_none') . '</p>';
              return true;
          }
          usort($pages, array($this, '_pagesorter'));

          # Output Table
          $renderer->doc .= '<table class="pub_table"><tr class="pub_head">';
          $renderer->doc .= '<th class="pub_page">' . $this->getLang('p_hdr_page') . '</th>';
          $renderer->doc .= '<th class="pub_prev">' . $this->getLang('p_hdr_previous') . '</th>';
          $renderer->doc .= '<th class="pub_upd">' . $this->getLang('p_hdr_updated') . '</th>';
          $renderer->doc .= '</tr>';
          $working_ns = null;
          foreach($pages as $page) {
            # $page: 0 -> pagename, 1 -> publish metadata, 2 -> last changed date
            $this_ns = getNS($page[0]);
            if($this_ns != $working_ns) {
                $name_ns = $this_ns;
                if($this_ns == '') { $name_ns = 'root'; }
                $renderer->doc .= '<tr class="pub_ns"><td colspan="3"><a href="';
                $renderer->doc .= wl($this_ns . ':' . $this->getConf('start'));
                $renderer->doc .= '">';
                $renderer->doc .= $name_ns;
                $renderer->doc .= '</a></td></tr>';
                $working_ns = $this_ns;
            }
            $updated = '<a href="' . wl($page[0]) . '">' . date('d/m/Y H:i', $page[2]) . '</a>';
            $published = '';
            if($page[1]['cur']) { // Has has published version
                $published = sprintf($this->getLang('p_published'), 
                                     editorinfo($page[1]['cur']['client']),
                                     wl($page[0], 'rev=' . $last),
                                     date('d/m/Y H:i', $page[1]['cur']['rev']));
                if($page[1]['cur']['rev'] == $page[2])
                    { $updated = 'Unchanged'; } //shouldn't be possible:
                                                //the search_helper should have
                                                //excluded this
            }

            $renderer->doc .= '<tr class="pub_table';
            if($published == '') { $renderer->doc .= ' pub_never'; }
            $renderer->doc .= '"><td class="pub_page"><a href="';
            $renderer->doc .= wl($page[0]);
            $renderer->doc .= '">';
            $renderer->doc .= $page[0];
            $renderer->doc .= '</a></td><td class="pub_prev">';
            $renderer->doc .= $published;
            $renderer->doc .= '</td><td class="pub_upd">';
            $renderer->doc .= $updated;
            $renderer->doc .= '</td></tr>';

            #$renderer->doc .= '<tr><td colspan="3">' . print_r($page, true) . '</td></tr>';
          }
          $renderer->doc .= '</table>';
          return true;
      }
      return false;
    }

    // filter out pages which can't be published by the current user
    // then check if they need publishing
    function _search_helper(&$data, $base, $file, $type, $lvl, $opts) {
      $id = pathID($file);
      $ns = $opts[0];
      if($type == 'd') { return !$ns || ($ns == $id); }
      if($ns && ($ns != getNS($id))) { return false; }
      if(!preg_match('#\.txt$#', $file)) { return false; }
      if(!$this->helper->publishing($id)) { return false; }
      $meta = p_get_metadata($id);
      if($meta['publish']['cur']['rev'] == $meta['last_change']['date']) { return false; }
      $data[] = array($id, $meta['publish'], $meta['last_change']['date']);
      return false;
    }

    function _pagesorter($a, $b){
        $ac = explode(':',$a[0]);
        $bc = explode(':',$b[0]);
        $an = count($ac);
        $bn = count($bc);
    
        # Same number of elements, can just string sort
        if($an == $bn) { return strcmp($a[0], $b[0]); }
    
        # For each level:
        # If this is not the last element in either list:
        #   same -> continue
        #   otherwise strcmp
        # If this is the last element in either list, it wins
        $n = 0;
        while(true) {
            if($n + 1 == $an) { return -1; }
            if($n + 1 == $bn) { return 1; }
            $s = strcmp($ac[$n], $bc[$n]);
            if($s != 0) { return $s; }
            $n += 1;
        }
    }

}
