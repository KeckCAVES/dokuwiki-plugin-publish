You can allow publishers to publish with no changes by editing
inc/common.php:933 to remove:
  // ignore if no changes were made
  if($text == rawWiki($id,'')){
    return;
  }

Or, you can change it to:
  global $_POST;
  // ignore if no changes were made
  if(!$POST['published'] && $text == rawWiki($id,'')){
    return;
  }
