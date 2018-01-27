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

if(AUTOD_FB && 0)
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

if(!function_exists('mysql_pconnect')){
  $mysqli = mysqli_connect("$dbhost:$dbport", $dbuser, $dbpass, $dbname);
  function mysql_pconnect($dbhost, $dbuser, $dbpass){
    global $dbport;
    global $dbname;
    global $mysqli;
    $mysqli = mysqli_connect("$dbhost:$dbport", $dbuser, $dbpass, $dbname);
    return $mysqli;
  }
  function mysql_select_db($dbname){
    global $mysqli;
    return mysqli_select_db($mysqli,$dbname);
  }
  function mysql_fetch_array($result){
    return mysqli_fetch_array($result);
  }
  function mysql_fetch_assoc($result){
    return mysqli_fetch_assoc($result);
  }
  function mysql_fetch_row($result){
    return mysqli_fetch_row($result);
  }
  function mysql_query($query){
    global $mysqli;
    return mysqli_query($mysqli,$query);
  }
  function mysql_escape_string($data){
    global $mysqli;
    return mysqli_real_escape_string($mysqli, $data);
    //return addslashes(trim($data));
  }
  function mysql_real_escape_string($data){
    return mysql_real_escape_string($data);
  }
  function mysql_close(){
    global $mysqli;
    return mysqli_close($mysqli);
  }
}