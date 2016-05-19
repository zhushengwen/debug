<?php
//require dirname(__FILE__).'/debug/auto_prepend.php';
date_default_timezone_set("Asia/Shanghai");
ob_start();
define('DEBUG_FB',1);
define('IS_GBK',0);
defined('DEBUG_CONSOLE_HIDE');
//define('DEBUG_CONSOLE_HIDE',1);
define('DEBUG_CLI',PHP_SAPI == 'cli');
define('AUTOD_FB',in_array('runkit',get_loaded_extensions()));
define('LOCAL',(isset($_SERVER["REMOTE_ADDR"]) && $_SERVER['REMOTE_ADDR']=="10.0.2.2") ||
	isset($_SERVER["HTTP_HOST"]) && (isset($_SERVER['LOCAL_ADDR'])?$_SERVER['LOCAL_ADDR']:$_SERVER['SERVER_ADDR'])==$_SERVER['REMOTE_ADDR']);
define('DEBUG_DIR', fb_debug_dir());
define('DEBUG_TEMP', (DIRECTORY_SEPARATOR === '\\')?'C:\\Windows\Temp':'/data/www/projects/root/tmp');
define('DEBUG_COOKIE',isset($_COOKIE['xdebug-trace'])?$_COOKIE['xdebug-trace']:0);
define('DEBUG_LIST_FILE',DEBUG_TEMP.'/xdebug-trace.html');
define('DEBUG_HIST_FILE',DEBUG_TEMP.'/xdebug-history.html');
define('DEBUG_FORCE_FAIL',file_exists(DEBUG_LIST_FILE) && time()-filectime(DEBUG_LIST_FILE)>1200);

define('FB_DEBUG_FORCE',1 || !DEBUG_CLI && !DEBUG_FORCE_FAIL && 0 );

define('DEBUG_SHOW_FORCE',0);
define('DEBUG_CONSOLE',LOCAL&&(DEBUG_COOKIE+1)||DEBUG_SHOW_FORCE);
define('DEBUG_FB_DIR', dirname(__FILE__));
define('XDEBUG_TRACE_SCRIPT_PATH',DEBUG_DIR.'/dev/xdebug-trace.php');
define('HTTP_HOST',isset($_SERVER['HTTP_X_FORWARDED_HOST'])? $_SERVER['HTTP_X_FORWARDED_HOST']: (isset($_SERVER["HTTP_HOST"])?$_SERVER["HTTP_HOST"]:'localhost'));
define('XDEBUG_HTTP_HOST', 'http://'.HTTP_HOST);
define('XDEBUG_TRACE_SCRIPT', XDEBUG_HTTP_HOST.XDEBUG_TRACE_SCRIPT_PATH);
define('XDEBUG_TIME',(microtime(1)*10000).rand(1000,9999));
define('XDEBUG_TIME_REAL',intval(XDEBUG_TIME/100000000));
define('XDEBUG_XT_FILE', DEBUG_TEMP.'/xdebug-trace.'.XDEBUG_TIME);

define('AUTO_EXPAND_CONTROLLER',1);
define('AUTO_FB_COOKIE',0);
define('AUTO_FB_COOKIE_ONE',AUTO_FB_COOKIE && 1);
define('DEBUG_FDB',FB_DEBUG_FORCE || (DEBUG_FB && LOCAL));
define('FB_DEBUG_ERROR', 0);
define('FB_RECOND_CONTENT',FB_DEBUG_FORCE);

define('DEBUG_FDB_SCRIPT', 'http://'.HTTP_HOST.DEBUG_DIR.'/dev/db-debug.php');
define('DEBUG_FDB_SCRIPT_TIME', DEBUG_FDB_SCRIPT.'?time='.XDEBUG_TIME);
define('DEBUG_FDB_ORG', DEBUG_TEMP.'/db-debug.dat');
define('DEBUG_FDB_FILE', DEBUG_FDB_ORG.'.'.XDEBUG_TIME);
define('DEBUG_AJAX',isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
define('dg','fb(get_defined_vars());');
define('DEBUG_REPLAY',isset($_COOKIE['xdebug-replay']));
define('FB_DEBUG_INDEX',debug_index());

if(DEBUG_FB) require dirname(__FILE__).'/phpBugLost.0.2.php';
if(DEBUG_REPLAY)setcookie("xdebug-replay",null,null,'/');
function fb_debug_dir()
{
	$ret = '/debug';
	$root = $_SERVER['DOCUMENT_ROOT'];
	$dir = dirname(__FILE__);
	$root = str_replace('\\', '/', $root);
	$dir = str_replace('\\', '/', $dir);
	if ('/' == substr($root, -1)) { $root = substr($root, 0, -1); }
	$root = @preg_replace('#^([a-z])(:[\s\S]+)$#ie', "strtolower('\\1').'\\2'", $root);
	$dir = @preg_replace('#^([a-z])(:[\s\S]+)$#ie', "strtolower('\\1').'\\2'", $dir);
	if ($root == substr($dir, 0, strlen($root))) {
		$ret = substr($dir, strlen($root));
	}
	return $ret;
}

function fa($var){echo "<script>console.log(".json_encode($var).")</script>";}


function fc($var){
//.mt_rand(1000,9999)
	setcookie(999+(9000 - (XDEBUG_TIME_REAL%9000)).date('.H:i:s'),json_encode($var),/*XDEBUG_TIME + 60*5*/ null,'/');
}
function c_c($key){
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


function fb_error_handler($errno, $errstr, $file, $line){
	$map = array(
		'/data/www/projects/'=>'E:/work/'
	);
	$file = str_replace(array_keys($map),array_values($map),$file);
	static $ec=0;$ec++;
	$html="<strong>$ec</strong>:$errstr::$file:<a href='notepad2://".$file."/?$line'>$line</a><br/>";
	$call = 0;
	if(isset($_SERVER['set_error_function'])&&isset($_SERVER['set_error_no']))
	{
		$fun = $_SERVER['set_error_function'];
		$types = $_SERVER['set_error_no'];
		if($types&$errno)
		{
			$param_arr = func_get_args();// array($errno, $errstr, $file, $line);
			if(is_array($fun))$call=1;
			elseif(is_string($fun) && ($pos=strpos($fun, '::'))!==false)
			{
				$fun =array(substr($fun, 0,$pos),substr($fun, $pos+2));
				$call=2;
			}elseif(is_string($fun))
			{
				$call=3;
			}
		}
	}
	file_put_contents(DEBUG_TEMP.'/xdebug-error.'.XDEBUG_TIME.'.html', $html,FILE_APPEND);
	if($call)call_user_func_array($fun, $param_arr);
	return true;
}
if(!FB_DEBUG_INDEX)set_error_handler('fb_error_handler');

function fd($var,$dlog = false)
{
	if($dlog)file_put_contents(DEBUG_TEMP.'/xdebug-trace.'.XDEBUG_TIME.'.log',$var);
	else file_put_contents(DEBUG_TEMP.'/'.date('Y-m-d',time()).'.log',trim(var_export($var,true),"''")."\r\n",FILE_APPEND);
}

function ff()
{
	echo fb_debug();
}
function fl()
{
	echo bl_debug();
}

function frecord()
{
	$path = DEBUG_FB_DIR .'/fb.php';
	if(file_exists($path) && isset($_SERVER['HTTP_USER_AGENT']) && !(strpos($_SERVER['HTTP_USER_AGENT'],'FirePHP') === false)){
		require_once $path;
	}
	else {

		eval('function fb($var){fa($var);}');
	}
	$fb_data = array('method'=>(DEBUG_AJAX?'ajax_':'').SGS('REQUEST_METHOD'),
	                 'uri'=>XDEBUG_HTTP_HOST.SGS('REQUEST_URI'),
	                 'url'=>"debug_popup('".XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME."')");
	define('FB_HIST_LOG',date('Y-m-d H:i:s',XDEBUG_TIME_REAL).'-'.XDEBUG_TIME_REAL.':<a target="_blank" title="'.SGS('REMOTE_ADDR').'" href="'.XDEBUG_HTTP_HOST.'/debug/dev/xdebug-trace.php?time='.XDEBUG_TIME.'">'.SGS('REQUEST_METHOD').':'.SGS('REQUEST_URI').'</a>('.SGS("HTTP_HOST").'-<a target="_blank" href="https://www.baidu.com/s?wd='.SGS('REMOTE_ADDR').'" ><font color="darkblue">'.SGS('REMOTE_ADDR').'</font></a>)<br/>');
	if(!FB_DEBUG_INDEX)file_put_contents(DEBUG_HIST_FILE,file_exists(DEBUG_HIST_FILE)?FB_HIST_LOG.file_get_contents(DEBUG_HIST_FILE):'');
	if(DEBUG_FB && (DEBUG_COOKIE||FB_DEBUG_FORCE) && !debug_dev_dir() && !debug_index())
	{
		file_put_contents(DEBUG_LIST_FILE,FB_HIST_LOG,FILE_APPEND);
		if(AUTO_FB_COOKIE)fc($fb_data);
	}
	$fb_data['url']=XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME;
	foreach($GLOBALS as $k => $v)
	{
		if(!in_array($k, array('GLOBALS','_db','_COOKIE')))
		{
			$re['$'.$k]=$v;
		}
	}
	$fb_data['GLOBALS'] = $re;
	$_SERVER['FB_GLO_DATA']=$fb_data;

	$_SERVER['FB_COOKIE_DC'] = count($_COOKIE) - 20;
	array_walk($_COOKIE,function($val,$key){
		if(preg_match( "/^\d{4}_\d{2}:\d{2}:\d{2}$/", $key, $matches))
			if($matches && (AUTO_FB_COOKIE_ONE || $_SERVER['FB_COOKIE_DC']-- >0))
				c_c(str_replace('_','.',$matches[0]));
	});
	if(DEBUG_FB)
	{
		if(AUTOD_FB){
			$_SERVER['set_error_handler']='set_error_handler_back';
			runkit_function_copy('set_error_handler',$_SERVER['set_error_handler']);
			runkit_function_redefine('set_error_handler','$fun,$types=E_ALL','$_SERVER["set_error_function"]=$fun;$_SERVER["set_error_no"]=$types;return $_SERVER["set_error_handler"]("fb_error_handler");');
		}
		$path = dirname(__FILE__).'/lib/debug.php';
		if(file_exists($path))require_once $path;
		if(DEBUG_FDB)
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

					$_SERVER['mysqli_query']='mysqli_query_back';
					if(!function_exists($_SERVER['mysqli_query']))
						runkit_function_copy('mysqli_query',$_SERVER['mysqli_query']);
					runkit_function_redefine('mysqli_query','$con,$sql','return fb_query($sql,$con,true);');

					$_SERVER['json_encode'] = 'json_encode_back';
					if(!function_exists($_SERVER['json_encode']))
					{
						runkit_function_copy('json_encode',$_SERVER['json_encode']);
						//runkit_function_redefine('json_encode','$data',"return @preg_replace(\"#\\\\\\u([0-9a-f]+)#ie\", \"iconv('UCS-2', 'UTF-8', pack('H4', '\\\\1'))\", \$_SERVER['json_encode'](\$data));");
						if (version_compare("5.3", PHP_VERSION, "<")) {
							runkit_function_redefine('json_encode','$data,$parm=null','return $_SERVER[\'json_encode\']($data, $parm?:JSON_UNESCAPED_UNICODE);');
						}else
						{
							runkit_function_redefine('json_encode','$data',"return preg_replace_callback(\"#\\\\\\u([0-9a-f]{4})#i\", function (\$matches){return iconv('UCS-2', 'UTF-8', pack('H4', \$matches[1]));}, \$_SERVER['json_encode'](\$data));");
						}
					}
				}else {
					$_SERVER['mysql_query']='mysql_query';
					$_SERVER['mysqli_query']='mysqli_query';
				}
			}

		}
		fb_debug_start();
	}
	return $fb_data;
}

function SGS($key){return isset($_SERVER[$key])?$_SERVER[$key]:'';}

function fb_sql($sql,$time=0,$data='')
{
	if (DEBUG_FDB) {
		db_empty($_SERVER['FB_DATA']['debug_count']+1,$sql);
		db_add_sql($sql,$time,$data);
	}
}


function fb_query($sql,$con = null,$mysqli = false){

	if (DEBUG_FDB) {
		db_empty($_SERVER['FB_DATA']['debug_count']+1,$sql);
	}
	if(function_exists('fb_db_query'))return fb_db_query($sql,$con,$mysqli);
	else if($mysqli)return mysqli_query($con,$sql);
	else if($con)return mysql_query($sql,$con);
	else return mysql_query($sql);
}

function debug_dev_dir()
{
	return FB_DEBUG_INDEX || strpos(SGS('REQUEST_URI'), '/debug/')!==false;
}

function debug_index()
{
	if(!defined('FB_DEBUG_MIAN'))define('FB_DEBUG_MIAN',strpos(SGS('HTTP_HOST'), 'debug.')===0 || (isset($_SERVER["REQUEST_URI"]) && in_array($_SERVER['REQUEST_URI'],array('/debug/','/debug/index.php'))));
	return  FB_DEBUG_MIAN ||
	in_array($_SERVER['SCRIPT_NAME'],array('/debug/index.php','/debug/dev/xdebug-trace.php','/debug/dev/db-debug.php',
	                                       '/dev/xdebug-trace.php','/dev/db-debug.php'));
}


function data_cleanup()
{

	static $called = false;
	if ($called) return;
	else $called = true;
	$dev = debug_dev_dir();
	if (DEBUG_FDB_FILE && !$dev) {
		$_SERVER['FB_DATA']['record']['data'] = $_SERVER['FB_GLO_DATA'];
		file_put_contents(DEBUG_FDB_FILE, serialize($_SERVER['FB_DATA']['record']));
	}
	$content = ob_get_contents();
	if(FB_RECOND_CONTENT && !$dev)fd($content,true);
	if(DEBUG_REPLAY)
	{
		ob_end_clean();
		echo '<url>'.XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME.'</url>';exit;
	}
	if(!DEBUG_AJAX && DEBUG_FB)
	{
		fb_debug_stop();
		$content=trim($content);

		if($content!='')
		{
			if($content{0}!=='<'&&substr($content, -1)!=='>')return;
			if(strpos($content,'<?xml')===0||stristr($content,'</')===false)return;
		}
		if($content!='' || FB_DEBUG_INDEX)echo debug_console();
	}
}

frecord();
//define('APP_DEBUG',1);
//https://github.com/Crack/runkit-windows/archive/master.zip
//runkit.internal_override = On