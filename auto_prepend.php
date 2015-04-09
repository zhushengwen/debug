<?php
//require dirname(__FILE__).'/debug/auto_prepend.php';
date_default_timezone_set("Asia/Shanghai");
ob_start();
define('DEBUG_FB',1);
define('IS_GBK',0);
defined('DEBUG_CONSOLE_HIDE');
define('AUTOD_FB',in_array('runkit',get_loaded_extensions()));
define('DEBUG_CONSOLE',isset($_SERVER["HTTP_HOST"]) && isset($_SERVER['LOCAL_ADDR'])?$_SERVER['LOCAL_ADDR']:$_SERVER['SERVER_ADDR']==$_SERVER['REMOTE_ADDR']);
define('DEBUG_DIR', debug_dir());
define('DEBUG_TEMP', ini_get('upload_tmp_dir'));
define('DEBUG_FB_DIR', dirname(__FILE__));
define('XDEBUG_TRACE_SCRIPT_PATH',DEBUG_DIR.'/dev/xdebug-trace.php');
define('HTTP_HOST',isset($_SERVER["HTTP_HOST"])?$_SERVER["HTTP_HOST"]:'localhost');
define('XDEBUG_HTTP_HOST', 'http://'.HTTP_HOST);
define('XDEBUG_TRACE_SCRIPT', XDEBUG_HTTP_HOST.XDEBUG_TRACE_SCRIPT_PATH);
define('XDEBUG_TIME',time());
define('XDEBUG_XT_FILE', DEBUG_TEMP.'/xdebug-trace.'.XDEBUG_TIME);



define('AUTO_FB_COOKIE',DEBUG_FB && 1);
define('AUTO_FB_COOKIE_ONE',DEBUG_FB && 0);
define('DB_DEBUG',DEBUG_FB && 1);
define('FB_DEBUG_ERROR', 0);

define('DB_DEBUG_SCRIPT', 'http://'.HTTP_HOST.DEBUG_DIR.'/dev/db-debug.php');
define('DB_DEBUG_SCRIPT_TIME', DB_DEBUG_SCRIPT.'?time='.XDEBUG_TIME);
define('DB_DEBUG_ORG', DEBUG_TEMP.'/db-debug.dat');
define('DB_DEBUG_FILE', DB_DEBUG_ORG.'.'.XDEBUG_TIME);
define('DEBUG_AJAX',isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');




if(DEBUG_FB) require dirname(__FILE__).'/phpBugLost.0.2.php';

function debug_dir()
{
	$ret = '/debug';
	$root = $_SERVER['DOCUMENT_ROOT'];
	$dir = dirname(__FILE__);
	$root = str_replace('\\', '/', $root);
	$dir = str_replace('\\', '/', $dir);
	if ('/' == substr($root, -1)) { $root = substr($root, 0, -1); }
	$root = preg_replace('#^([a-z])(:[\s\S]+)$#ie', "strtolower('\\1').'\\2'", $root);
	$dir = preg_replace('#^([a-z])(:[\s\S]+)$#ie', "strtolower('\\1').'\\2'", $dir);
	if ($root == substr($dir, 0, strlen($root))) {
		$ret = substr($dir, strlen($root));
	}
	return $ret;
}

function fa($var){echo "<script>console.log(".json_encode($var).")</script>";}

$path = DEBUG_FB_DIR .'/fb.php';
if(file_exists($path) && isset($_SERVER['HTTP_USER_AGENT']) && !(strpos($_SERVER['HTTP_USER_AGENT'],'FirePHP') === false)){
    require_once $path;
    }
 else {
  
     eval('function fb($var){fa($var);}');
 }


define('dg','fb(get_defined_vars());');
function fc($var){
//.mt_rand(1000,9999)
setcookie(999+(9000 - (XDEBUG_TIME%9000)).date('.H:i:s'),json_encode($var),/*XDEBUG_TIME + 60*5*/ null,'/');
}
function cc($key){
setcookie($key,null,null,'/');
}
function stack()
{
   $html="";$array =debug_backtrace();unset($array[0]);
   header('Content-type: text/html; charset=utf-8'); 
   foreach($array as $row)
    {
       $url="<a href='notepad2://{$row['file']}/?{$row['line']}' title='GoTo{$row['function']}'>{$row['function']}</a>";
       $html .=$row['file'].':'.$row['line'].'GoTo:'.$url."<p>";
    }
    $line=__LINE__+3;
  $html=__FILE__.":{$line}GoTo<a href='notepad2://".__FILE__."/?$line'>$line</a><p>".$html;return $html;
}

function pt($file,$line=1,$title='')
{
  return sprintf('%s:<a style="display:inline;text-decoration: none;" title="%s" href="javascript:location.href=\'notepad2://\'+((\'%s\'.indexOf(\':\')==-1)?\'http://\'+location.host:\'\')+\'%s/?%s\'"><b>%s</b></a>',
  $file,$title,$file,$file,$line,$line);
}
function ps($file)
{
  return sprintf('<a style="display:inline;text-decoration: none;" href="javascript:location.href=\'notepad2://\'+((\'%s\'.indexOf(\':\')==-1)?\'http://\'+location.host:\'\')+\'%s/?1\'"><b>%s</b></a>',
  $file,$file,$file);
}

function myErrorHandler123($errno, $errstr, $file, $line){
if(!(error_reporting() &$errno)){return;}
$url="[$errno]:$errstr in $file on line $line\r\n";
file_put_contents(DEBUG_TEMP.'/error.log', $url,FILE_APPEND);
return true;
}
function fd($var)
{
 file_put_contents(DEBUG_TEMP.'/xdebug-trace.'.XDEBUG_TIME.'.log',var_export($var,true)."\r\n",FILE_APPEND);
 file_put_contents(DEBUG_TEMP.'/'.date('Y-m-d',XDEBUG_TIME).'.log',var_export($var,true)."\r\n",FILE_APPEND);
}
function fe($a){var_dump($a);exit;}
#set_error_handler('myErrorHandler123',E_ALL);

 function ff()
 {
    echo fb_debug();
 }
  function fl()
 {
    echo bl_debug();
 }
if(DEBUG_FB && isset($_COOKIE['xdebug-trace']) && $_SERVER['SCRIPT_NAME']!=XDEBUG_TRACE_SCRIPT_PATH)
{
 file_put_contents(DEBUG_TEMP.'/xdebug-trace.html',date('Y-m-d H:i:s',XDEBUG_TIME).'-'.XDEBUG_TIME.':<a target="_blank" href="'.XDEBUG_HTTP_HOST.'/debug/dev/xdebug-trace.php?time='.XDEBUG_TIME.'">'.$_SERVER['REQUEST_METHOD'].':'.$_SERVER['REQUEST_URI'].'</a><br/>',FILE_APPEND);
 if(AUTO_FB_COOKIE)
 fc(array('method'=>(DEBUG_AJAX?'ajax_':'').$_SERVER['REQUEST_METHOD'],'uri'=>XDEBUG_HTTP_HOST.$_SERVER['REQUEST_URI'],'url'=>"debug_popup('".XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME."')"));
}
$_SERVER['FB_COOKIE_DC'] = count($_COOKIE) - 20;
array_walk($_COOKIE,function($val,$key){
if(preg_match( "/^\d{4}_\d{2}:\d{2}:\d{2}$/", $key, $matches))
if($matches && (AUTO_FB_COOKIE_ONE || $_SERVER['FB_COOKIE_DC']-- >0))
    cc(str_replace('_','.',$matches[0]));
});


if(DEBUG_FB)
{
    $path = dirname(__FILE__).'/lib/debug.php';
    if(file_exists($path))require_once $path; 
    if(DB_DEBUG)
    {
        $path = dirname(__FILE__).'/lib/db-mysql.php';
        if(file_exists($path))
        {
            require_once $path; 
            if(AUTOD_FB){
            $_SERVER['mysql_query']='mysql_query_back';
            if(!function_exists($_SERVER['mysql_query']))
            runkit_function_copy('mysql_query',$_SERVER['mysql_query']);
            runkit_function_redefine('mysql_query','$sql,$con=null','return fb_query($sql,$con);');
            }
        }
  
    }
    if ( DEBUG_AJAX ) {
    }
    else
    {
     register_shutdown_function('debug_console', 1);
    }
    fb_debug_start();
}
function fb_query($sql,$con = null){
  global $_db;
  if (DB_DEBUG) {
    db_empty($_db['debug_count']+1,$sql);
  }
  if(function_exists('db_query'))return db_query($sql,$con);
  else if($con)return mysql_query($sql,$con);
  else return mysql_query($sql);
}

//https://github.com/Crack/runkit-windows/archive/master.zip
//runkit.internal_override = On