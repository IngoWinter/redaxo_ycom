<?php

/**
 * Plugin Media-Access - rex_com_auth_media class
 * @author m.lorch[at]it-kult[dot]de Markus Lorch
 * @author <a href="http://www.it-kult.de">www.it-kult.de</a>
 */

class rex_com_auth_media
{
  var $filename;
  var $filepath;
  var $fullpath;
  var $xsendfile = false;
  var $MEDIA;

  // this is the new style constructor used by newer php versions.
  // important: if you change the signatur of this method, change also the signature of rex_com_auth_media()
  function __construct()
  {
    $this->rex_com_auth_media();
  }

  // this is the deprecated old style constructor kept for compat reasons. 
  // important: if you change the signatur of this method, change also the signature of __construct()
  function rex_com_auth_media()
  {
  }

  static function send($media)
  {
    global $REX;

    if ($REX['ADDON']['community']['plugin_auth_media']['xsendfile']) {
      header('Content-type: ' . $media->getType());
      header('Content-disposition: attachment; filename="' . $media->getFileName() . '"');
      header('X-SendFile: ' . $media->getFullPath());

    } else {

      header('Pragma: public');
      header('Expires: 0');
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Content-Type: ' . $media->getType());
      header('Content-Transfer-Encoding: binary');
      header('Content-Length: ' . $media->getSize());
      if (rex_request('media_download', 'int') == 1) {
        header('Content-Type: application/force-download');
        header('Content-Type: application/download');
        header('Content-Disposition: attachment; filename=' . $media->getFileName() . ';');
      }
      ob_clean();
      flush();
      @readfile($media->getFullPath());
    }

    exit;
  }

  static function checkPerm($media)
  {
    global $REX;

    ## starts session if required
    if (session_id() == '')
      session_start();

    if (isset($_SESSION[$REX['INSTNAME']]['UID']) && $_SESSION[$REX['INSTNAME']]['UID'] > 0)
      return true;

    ## if no access rule - grant access
    if ($media->getValue('med_com_auth_media_comusers') == '' || $media->getValue('med_com_auth_media_comusers') == '||')
      if ($media->getValue('med_com_groups') == '' || $media->getValue('med_com_groups') == '||')
        return true;

    ## true if user is in one or more required groups
    $me = rex_com_auth::getUser();
    if ($me) {

      if ($media->getValue('med_com_auth_media_comusers') != '' && $media->getValue('med_com_auth_media_comusers') != '||')
        return true;

      $media_groups = explode('|', $media->getValue('med_com_groups'));
      $user_groups = explode(',', $me->getValue('rex_com_group'));

      foreach ($media_groups as $group)
        if ($group != '' && in_array($group, $user_groups))
          return true;
    }

    return false;
  }

  static function getMedia()
  {
    global $REX;

    $filename = rex_request('rex_com_auth_media_filename', 'string');
    if ($filename) {
      if ( ($media = OOMedia::getMediaByFileName($filename)) && self::checkPerm($media) ) {
        self::send($media);
      } else {
        self::forwardErrorPage();
      }
      exit;
    }
  }

  static function forwardErrorPage()
  {
    global $REX;

    header('Location: ' . rex_getUrl($REX['ADDON']['community']['plugin_auth_media']['error_article_id'], '', array($REX['ADDON']['community']['plugin_auth']['request']['ref'] => urlencode($_SERVER['REQUEST_URI'])), '&'));

    exit;
  }

  static function createHtaccess($create = true, $unsecure_fileext = '')
  {
    global $REX;

    $path = $REX['HTDOCS_PATH'] . 'files/.htaccess';

    if ($create) {
      $unsecure_fileext = implode('|', explode(',', $unsecure_fileext));

      ## build new content
      $new_content = 'RewriteEngine On' . PHP_EOL;
      $new_content .= 'RewriteBase /' . PHP_EOL . PHP_EOL;
      $new_content .= 'RewriteCond %{REQUEST_URI} !files/.*/.*' . PHP_EOL;
      $new_content .= 'RewriteCond %{REQUEST_URI} !files/(.*).(' . $unsecure_fileext . ')$' . PHP_EOL . PHP_EOL;
      $new_content .= 'RewriteRule ^(.*)$ /?rex_com_auth_media_filename=$1&%{QUERY_STRING}' . PHP_EOL;

      return rex_put_file_contents($path, $new_content);
    } else {
      @unlink($path);
      return true;
    }

  }

}
