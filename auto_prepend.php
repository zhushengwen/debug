<?php
define('DEBUG_FB',0);
function fe($a){echo '<pre>';var_dump($a);echo '</pre>';exit;}
function debug_index()
{
  if(!defined('FB_DEBUG_MIAN'))define('FB_DEBUG_MIAN',strpos($_SERVER['HTTP_HOST'], 'debug.')===0 || (isset($_SERVER["REQUEST_URI"]) && in_array($_SERVER['REQUEST_URI'],array('/debug/','/debug/index.php'))));
  return  FB_DEBUG_MIAN ||
  in_array($_SERVER['SCRIPT_NAME'],array('/debug/index.php','/debug/dev/xdebug-trace.php','/debug/dev/db-debug.php',
                                         '/dev/xdebug-trace.php','/dev/db-debug.php'));
}
define('FB_DEBUG_INDEX',debug_index());
if(DEBUG_FB || isset($_REQUEST['debug']) || FB_DEBUG_INDEX)
{
  if(!isset($_SERVER['HTTP_HOST']) || !in_array($_SERVER['HTTP_HOST'],['phptest.ya0.cn','gocode.ya0.cn','uc.ya0.cn','42.228.4.166']))
  include "main.php";
}
