<?php


if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

function in_namespace($valid, $invalid, $check) {
    // PHP apparantly does not have closures -
    // so we will parse $valid ourselves. Wasteful.
    $valid = preg_split('/\s+/', $valid, -1, PREG_SPLIT_NO_EMPTY);
    $invalid = preg_split('/\s+/', $invalid, -1, PREG_SPLIT_NO_EMPTY);
    $check = explode(':', trim($check, ':'));

    $accept = (count($valid) == 0);
    foreach($valid as $v) { // Check against valid namespaces
        if (explode(':', $v) == array_slice($check, 0, count($v))) { $accept=true; break; }
    }
    foreach($invalid as $v) { // Check against invalid namespaces
        if (explode(':', $v) == array_slice($check, 0, count($v))) { $accept=false; break; }
    }
    return $accept;
}

function in_sub_namespace($valid, $invalid, $check) {
    // is check a dir which contains any valid?
    // PHP apparantly does not have closures -
    // so we will parse $valid ourselves. Wasteful.
    $valid = preg_split('/\s+/', $valid, -1, PREG_SPLIT_NO_EMPTY);
    $invalid = preg_split('/\s+/', $invalid, -1, PREG_SPLIT_NO_EMPTY);
    $check = explode(':', trim($check, ':'));

    $accept = (count($valid) == 0);
    foreach($valid as $v) { // Check against valid namespaces
        if (explode(':', $v) == $check) { $accept=true; break; }
    }
    foreach($invalid as $v) { // Check against invalid namespaces
        if (explode(':', $v) == $check) { $accept=false; break; }
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
