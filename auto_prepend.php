<?php
function fe($a){echo '<pre>';var_dump($a);echo '</pre>';exit;}
function debug_index()
{

  if(!defined('FB_DEBUG_MIAN'))define('FB_DEBUG_MIAN',isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'debug.')===0 || (isset($_SERVER["REQUEST_URI"]) && in_array($_SERVER['REQUEST_URI'],array('/debug/','/debug/index.php'))));
  return  FB_DEBUG_MIAN ||
  in_array($_SERVER['SCRIPT_NAME'],array('/debug/index.php','/debug/dev/xdebug-trace.php','/debug/dev/db-debug.php',
                                         '/dev/xdebug-trace.php','/dev/db-debug.php'));
}
define('FB_DEBUG_INDEX',debug_index());
define('AUTO_FB_COOKIE', 1);
define('DEBUG_FB_ST',1);
define('AUTOD_FB',in_array('runkit',get_loaded_extensions()));
define('FB_XDEBUG_HOST','10.0.2.2:9001');
//define('FB_DEBUG_SERVER','http://debug.xxxx.com');

if(AUTOD_FB)
{
  $_SERVER['set_error_handler']='set_error_handler_back';
  if(!function_exists($_SERVER['set_error_handler']))
    runkit_function_copy('set_error_handler',$_SERVER['set_error_handler']);
  runkit_function_redefine('set_error_handler','$error_handler, $error_types=32767','if(!@fsockopen(\''.FB_XDEBUG_HOST.'\'))return $_SERVER[\'set_error_handler\']($error_handler, $error_types);');
}

if(DEBUG_FB_ST || isset($_REQUEST['debug']) || FB_DEBUG_INDEX)
{
# define('DEBUG_HOST','debug.***.com');
  if(!isset($_SERVER['HTTP_HOST']) || !in_array($_SERVER['HTTP_HOST'],['phptest.ya0.cn','gocode.ya0.cn','uc.ya0.cn','42.228.4.166']))
  include "main.php";
}

function SGS($key) { return isset($_SERVER[$key]) ? $_SERVER[$key] : ''; }