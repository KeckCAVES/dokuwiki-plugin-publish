<?php


if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

plugin_load('admin', 'config');
if (!class_exists('setting_textarea')) {
  class setting_textarea extends setting {
      // Used to get textarea provided by default setting class
      // without Dokuwiki complaining.
  }
}

function publish_pageIncluded($page, $patterns) {
    $patterns = preg_split('/\s+/', $patterns, -1, PREG_SPLIT_NO_EMPTY);
    $page = str_replace(':', '/', $page);
    $accept = false;
    foreach($patterns as $p) { // Check against namespace wildcards
        $p = str_replace(':', '/', $p);
        $include = true;
        if (substr($p,0,1) == '-') { $include=false; $p = substr($p,1); }
        else if (substr($p,0,1) == '+') { $p = substr($p,1); }
        if (fnmatch($p, $page)) { $accept = $include; break; }
    }
    return $accept;
}

function publish_getInfo() {
    return array(
        'author' => 'Jarrod Lowe',
        'email' => 'dokuwiki@rrod.net',
        'date' => '2009-08-26',
        'name' => 'Publishing Process',
        'desc' => 'Publishing Process',
        'url' => 'http://www.dokuwiki.org/plugin:publish',
    );
}
