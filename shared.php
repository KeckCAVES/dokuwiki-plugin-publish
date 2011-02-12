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
