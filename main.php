<?php
//require dirname(__FILE__).'/debug/auto_prepend.php';
date_default_timezone_set("Asia/Shanghai");

define('DEBUG_FB', 1);
define('IS_GBK', 0);
defined('DEBUG_CONSOLE_HIDE');
//define('DEBUG_CONSOLE_HIDE',0);
define('DEBUG_CLI', PHP_SAPI == 'cli');

define('DEBUG_FB_DIR', dirname(__FILE__));
define('DEBUG_DIR', fb_debug_dir());
define('DEBUG_TEMP', DEBUG_FB_DIR.'/tmp/xtrace');
define('DEBUG_COOKIE_EXIST',isset($_COOKIE['xdebug-trace']));
define('DEBUG_COOKIE',  DEBUG_COOKIE_EXIST? $_COOKIE['xdebug-trace'] : 0);
define('DEBUG_LIST_FILE', DEBUG_TEMP.'/xdebug-trace.html');
define('DEBUG_HIST_FILE', DEBUG_TEMP.'/xdebug-history.html');
//auto disable debug when last debug time over 20 mins
define('DEBUG_FORCE_FAIL', file_exists(DEBUG_LIST_FILE) && time() - filectime(DEBUG_LIST_FILE) > 1200);

define('FB_DEBUG_FORCE',! DEBUG_CLI && ! DEBUG_FORCE_FAIL);

define('DEBUG_SHOW_FORCE',0);
define('LOCAL', TRUE || SGS('REMOTE_ADDR') == "10.0.2.2"
		|| (SGS('LOCAL_ADDR') ?: SGS('SERVER_ADDR')) === (SGS('REMOTE_ADDR') ?: NULL));
define('DEBUG_CONSOLE', LOCAL && (DEBUG_COOKIE + 1) || DEBUG_SHOW_FORCE);

define('XDEBUG_TIME', (microtime(1) * 10000).rand(1000, 9999));
define('XDEBUG_TIME_REAL', intval(XDEBUG_TIME / 100000000));
define('XDEBUG_XT_FILE', DEBUG_TEMP.'/xdebug-trace.'.XDEBUG_TIME);

define('HTTP_HOST', isset($_SERVER['HTTP_X_FORWARDED_HOST'])
	? $_SERVER['HTTP_X_FORWARDED_HOST']
	:
	(isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : 'localhost'));
define('XDEBUG_HTTP_TYPE', ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
	|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://'
	: 'http://');
define('XDEBUG_HTTP_HOST', XDEBUG_HTTP_TYPE.HTTP_HOST);
define('XDEBUG_WEB_BASE', XDEBUG_HTTP_TYPE.(defined('DEBUG_HOST') ? DEBUG_HOST : HTTP_HOST.DEBUG_DIR));
define('DEBUG_FB_TRACE', '/dev/xdebug-trace.php');
define('XDEBUG_TRACE_SCRIPT', XDEBUG_WEB_BASE.DEBUG_FB_TRACE);

define('DEBUG_FDB_SCRIPT', XDEBUG_WEB_BASE.'/dev/db-debug.php');
define('DEBUG_FDB_SCRIPT_TIME', DEBUG_FDB_SCRIPT.'?time='.XDEBUG_TIME);
define('DEBUG_FDB_ORG', DEBUG_TEMP.'/db-debug.dat');
define('DEBUG_FDB_FILE', DEBUG_FDB_ORG.'.'.XDEBUG_TIME);
define('DEBUG_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
define('dg', 'fb(get_defined_vars());');
define('DEBUG_REPLAY', strpos(SGS('HTTP_REFERER'), DEBUG_FB_TRACE) && isset($_COOKIE['xdebug-redo']));

define('AUTO_EXPAND_CONTROLLER', 1);
define('AUTO_FB_COOKIE_COUNT', AUTO_FB_COOKIE?10:0);
define('DEBUG_FDB', FB_DEBUG_FORCE || (DEBUG_FB && LOCAL));
define('FB_DEBUG_ERROR', 0);
define('FB_RECOND_CONTENT', FB_DEBUG_FORCE);

if (DEBUG_REPLAY)
{
	header('Access-Control-Allow-Origin:*');
	header('Access-Control-Allow-Headers: X-Requested-With');
	if (SGS('REQUEST_METHOD') === 'OPTIONS')
	{
		exit;
	}
	c_c('xdebug-redo');
}

if ( ! FB_DEBUG_INDEX)
{
	ob_start();
}

//if(DEBUG_FB) require dirname(__FILE__).'/phpBugLost.0.2.php';
function fb_debug_dir()
{
	$ret  = '/debug';
	$root = $_SERVER['DOCUMENT_ROOT'];
	$dir  = dirname(__FILE__);
	$root = str_replace('\\', '/', $root);
	$dir  = str_replace('\\', '/', $dir);
	if ('/' == substr($root, - 1))
	{
		$root = substr($root, 0, - 1);
	}
	//$root = @preg_replace('#^([a-z])(:[\s\S]+)$#ie', "strtolower('\\1').'\\2'", $root);
	//$dir = @preg_replace('#^([a-z])(:[\s\S]+)$#ie', "strtolower('\\1').'\\2'", $dir);
	if ($root == substr($dir, 0, strlen($root)))
	{
		$ret = substr($dir, strlen($root));
	}

	return $ret;
}

function fa($var) { echo "<script>console.log(".json_encode($var).")</script>"; }

function fc($var)
{
//.mt_rand(1000,9999)
	setcookie(999 + (9000 - (XDEBUG_TIME_REAL % 9000)).date('.H:i:s'), json_encode($var),/*XDEBUG_TIME + 60*5*/
		NULL, '/');
}

function c_c($key)
{
	setcookie($key, NULL, NULL, '/');
}

function stack()
{
	$html  = "";
	$array = debug_backtrace();
	unset($array[0]);
	header('Content-type: text/html; charset=utf-8');
	foreach ($array as $row)
	{
		$url = "<a href='notepad2://{$row['file']}/?{$row['line']}' title='GoTo{$row['function']}'>{$row['function']}</a>";
		$html .= $row['file'].':'.$row['line'].'GoTo:'.$url."<p>";
	}
	$line = __LINE__ + 3;
	$html = __FILE__.":{$line}GoTo<a href='notepad2://".__FILE__."/?$line'>$line</a><p>".$html;

	return $html;
}

function fb_error_handler($errno, $errstr, $file, $line)
{
	if ($errstr == 'fsockopen(): unable to connect to '.FB_XDEBUG_HOST.':-1 (Connection refused)')
	{
		return;
	}
	$map  = array(
		//'/data/www/projects/' => 'E:/work/',
	);
	$file = str_replace(array_keys($map), array_values($map), $file);
	static $ec = 0;
	$ec ++;
	$html = "<strong>$ec</strong>:$errstr::$file:<a href='notepad2://".$file."/?$line'>$line</a><br/>";
	$call = 0;
	if (isset($_SERVER['set_error_function']) && isset($_SERVER['set_error_no']))
	{
		$fun   = $_SERVER['set_error_function'];
		$types = $_SERVER['set_error_no'];
		if ($types & $errno)
		{
			$param_arr = func_get_args();// array($errno, $errstr, $file, $line);
			if (is_array($fun))
			{
				$call = 1;
			}
			elseif (is_string($fun) && ($pos = strpos($fun, '::')) !== FALSE)
			{
				$fun  = array(substr($fun, 0, $pos), substr($fun, $pos + 2));
				$call = 2;
			}
			elseif (is_string($fun))
			{
				$call = 3;
			}
		}
	}
	file_put_contents(DEBUG_TEMP.'/xdebug-error.'.XDEBUG_TIME.'.html', $html, FILE_APPEND);
	if ($call)
	{
		call_user_func_array($fun, $param_arr);
	}

	return TRUE;
}

if ( ! FB_DEBUG_INDEX)
{
	set_error_handler('fb_error_handler');
}

function fd($var, $dlog = FALSE)
{
	if ($dlog)
	{
		file_put_contents(DEBUG_TEMP.'/xdebug-trace.'.XDEBUG_TIME.'.log', $var);
	}
	else
	{
		file_put_contents(DEBUG_TEMP.'/'.date('Y-m-d', time()).'.log', trim(var_export($var, TRUE), "''")."\r\n",
			FILE_APPEND);
	}
}

function ff()
{
	//echo fb_debug();
}

function fl()
{
	echo bl_debug();
}

function frecord()
{

	$path = DEBUG_FB_DIR.'/fb.php';
	if (file_exists($path) && isset($_SERVER['HTTP_USER_AGENT'])
		&& ! (strpos($_SERVER['HTTP_USER_AGENT'], 'FirePHP') === FALSE)
	)
	{
		require_once $path;
	}
	else
	{

		eval('function fb($var){fa($var);}');
	}
	$fb_data     = array(
		'method' => (DEBUG_AJAX ? 'ajax_' : '').SGS('REQUEST_METHOD'),
		'uri'    => XDEBUG_HTTP_HOST.SGS('REQUEST_URI'),
		'url'    => "debug_popup('".XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME."')",
	);
	$show_server = (defined('FB_DEBUG_SERVER') && FB_DEBUG_SERVER) ? FB_DEBUG_SERVER : XDEBUG_HTTP_HOST.'/debug';
	define('FB_HIST_LOG', date('Y-m-d H:i:s', XDEBUG_TIME_REAL).'-'.XDEBUG_TIME_REAL.':<a target="_blank" title="'
		.SGS('REMOTE_ADDR').'" href="'.$show_server.'/dev/xdebug-trace.php?time='.XDEBUG_TIME.'">'.SGS('REQUEST_METHOD')
		.':'.SGS('REQUEST_URI').'</a>('.SGS("HTTP_HOST").'-<a target="_blank" href="https://www.baidu.com/s?wd='
		.SGS('REMOTE_ADDR').'" ><font color="darkblue">'.SGS('REMOTE_ADDR').'</font></a>)<br/>');
	if ( ! FB_DEBUG_INDEX)
	{
		file_put_contents(DEBUG_HIST_FILE,
			file_exists(DEBUG_HIST_FILE) ? FB_HIST_LOG.file_get_contents(DEBUG_HIST_FILE) : '');
	}
	if (DEBUG_FB && (DEBUG_COOKIE || FB_DEBUG_FORCE) && ! debug_dev_dir() && ! debug_index())
	{
		file_put_contents(DEBUG_LIST_FILE, FB_HIST_LOG, FILE_APPEND);
		if (AUTO_FB_COOKIE)
		{
			fc($fb_data);
		}
	}
	$fb_data['url'] = XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME;
	foreach ($GLOBALS as $k => $v)
	{
		if ( ! in_array($k, array('GLOBALS', '_db', '_COOKIE')))
		{
			$re['$'.$k] = $v;
		}
	}
	$fb_data['GLOBALS']     = $re;
	$_SERVER['FB_GLO_DATA'] = $fb_data;

	$_SERVER['FB_COOKIE_DC'] = AUTO_FB_COOKIE_COUNT;
	array_walk($_COOKIE, function ($val, $key)
	{
		if (preg_match("/^\d{4}_\d{2}:\d{2}:\d{2}$/", $key, $matches))
		{
			if ($matches && ($_SERVER['FB_COOKIE_DC'] -- <= 0))
			{
				c_c(str_replace('_', '.', $matches[0]));
			}
		}
	});
	if (DEBUG_FB)
	{
		if (AUTOD_FB)
		{
			if ( ! isset($_SERVER['set_error_handler']))
			{
				$_SERVER['set_error_handler'] = 'set_error_handler_back';
				runkit_function_copy('set_error_handler', $_SERVER['set_error_handler']);
				runkit_function_redefine('set_error_handler', '$fun,$types=E_ALL',
					'$_SERVER["set_error_function"]=$fun;$_SERVER["set_error_no"]=$types;return $_SERVER["set_error_handler"]("fb_error_handler");');
			}
		}
		$path = dirname(__FILE__).'/lib/debug.php';
		if (file_exists($path))
		{
			require_once $path;
		}
		if (DEBUG_FDB)
		{
			$path = dirname(__FILE__).'/lib/db-mysql.php';
			if (file_exists($path))
			{
				require_once $path;
				if (AUTOD_FB)
				{
					$_SERVER['mysql_query'] = 'mysql_query_back';
					if ( ! function_exists($_SERVER['mysql_query']))
					{
						runkit_function_copy('mysql_query', $_SERVER['mysql_query']);
					}
					runkit_function_redefine('mysql_query', '$sql,$con=null', 'return fb_query($sql,$con);');

					$_SERVER['mysqli_query'] = 'mysqli_query_back';
					if ( ! function_exists($_SERVER['mysqli_query']))
					{
						runkit_function_copy('mysqli_query', $_SERVER['mysqli_query']);
					}
					runkit_function_redefine('mysqli_query', '$con,$sql', 'return fb_query($sql,$con,true);');

					$_SERVER['json_encode'] = 'json_encode_back';
					if ( ! function_exists($_SERVER['json_encode']))
					{
						runkit_function_copy('json_encode', $_SERVER['json_encode']);
						//runkit_function_redefine('json_encode','$data',"return @preg_replace(\"#\\\\\\u([0-9a-f]+)#ie\", \"iconv('UCS-2', 'UTF-8', pack('H4', '\\\\1'))\", \$_SERVER['json_encode'](\$data));");
						if (version_compare("5.3", PHP_VERSION, "<"))
						{
							runkit_function_redefine('json_encode', '$data,$parm=null',
								'return $_SERVER[\'json_encode\']($data, $parm?:JSON_UNESCAPED_UNICODE);');
						}
						else
						{
							runkit_function_redefine('json_encode', '$data',
								"return preg_replace_callback(\"#\\\\\\u([0-9a-f]{4})#i\", function (\$matches){return iconv('UCS-2', 'UTF-8', pack('H4', \$matches[1]));}, \$_SERVER['json_encode'](\$data));");
						}
					}
				}
				else
				{
					$_SERVER['mysql_query']  = 'mysql_query';
					$_SERVER['mysqli_query'] = 'mysqli_query';
				}
			}
		}
		if(DEBUG_COOKIE != 2)fb_debug_start();
	}

	return $fb_data;
}


function fb_sql($sql, $time = 0, $data = '')
{
	if (DEBUG_FDB)
	{
		db_empty($_SERVER['FB_DATA']['debug_count'] + 1, $sql);
		db_add_sql($sql, $time, $data);
	}
}

function fb_query($sql, $con = NULL, $mysqli = FALSE)
{

	if (DEBUG_FDB)
	{
		db_empty($_SERVER['FB_DATA']['debug_count'] + 1, $sql);
	}
	if (function_exists('fb_db_query'))
	{
		return fb_db_query($sql, $con, $mysqli);
	}
	else
	{
		if ($mysqli)
		{
			return mysqli_query($con, $sql);
		}
		else
		{
			if ($con)
			{
				return mysql_query($sql, $con);
			}
			else
			{
				return mysql_query($sql);
			}
		}
	}
}

function debug_dev_dir()
{
	return FB_DEBUG_INDEX || strpos(SGS('REQUEST_URI'), '/debug/') !== FALSE;
}

function data_cleanup()
{
	static $called = FALSE;
	if ($called)
	{
		return;
	}
	else
	{
		$called = TRUE;
	}
	$dev = debug_dev_dir();
	if (DEBUG_FDB_FILE && ! $dev)
	{
		$_SERVER['FB_DATA']['record']['data'] = $_SERVER['FB_GLO_DATA'];
		file_put_contents(DEBUG_FDB_FILE, serialize($_SERVER['FB_DATA']['record']));
	}
	$content = ob_get_contents();
	if (FB_RECOND_CONTENT && ! $dev)
	{
		fd($content, TRUE);
	}
	if (DEBUG_REPLAY && ! FB_DEBUG_INDEX)
	{
		ob_end_clean();
		header('HTTP/1.1 200 OK');
		header('Content-Type:text/html;');
		exit(DEBUG_AJAX
			? ('<url>'.XDEBUG_TRACE_SCRIPT.'?time='.XDEBUG_TIME.'</url>')
			:
			('<script>var x = (screen.width/2-400);var y = (screen.height/2-325);window.open("'.XDEBUG_TRACE_SCRIPT.'?time='
				.XDEBUG_TIME
				.'", "", "scrollbars=yes,resizable=yes,width=800,height=650,screenX="+(x)+",screenY="+y+",left="+x+",top="+y);</script>'));
	}
	if ( ! DEBUG_AJAX && DEBUG_FB)
	{
		fb_debug_stop();
		$content = trim($content);

		if ($content != '')
		{
			if ($content{0} !== '<' && substr($content, - 1) !== '>')
			{
				return;
			}
			if (strpos($content, '<?xml') === 0 || stristr($content, '</') === FALSE)
			{
				return;
			}
		}
		if ($content != '' || FB_DEBUG_INDEX)
		{
			echo debug_console();
		}
	}
}

frecord();
//define('APP_DEBUG',1);
//https://github.com/Crack/runkit-windows/archive/master.zip
//runkit.internal_override = On
