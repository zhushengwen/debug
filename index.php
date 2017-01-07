<?php
header("Content-type: text/html; charset=utf-8");
define('DEBUG_CONSOLE_MEMORY', memory_get_usage());
define('DEBUG_CONSOLE_TIME', microtime(1));
if (isset($_GET['timelog']))
{
	$log = DEBUG_TEMP.'/'.date('Y-m-d', $_GET['timelog'] / 100000000).'.log';
	if (file_exists($log))
	{
		echo str_replace("\r\n", '<br/>', file_get_contents($log));
		unlink($log);
	}
	exit;
}
if (isset($_GET['timeoutlog']))
{
	$log = DEBUG_TEMP.'/xdebug-trace.'.$_GET['timeoutlog'].'.log';
	if (file_exists($log))
	{
		echo file_get_contents($log);
	}
	exit;
}
if (isset($_GET['timeouterr']))
{
	$err = DEBUG_TEMP.'/xdebug-error.'.$_GET['timeouterr'].'.html';
	if (file_exists($err))
	{
		echo file_get_contents($err);
	}
	exit;
}
if ($_SERVER['QUERY_STRING'] == 'hist')
{
	if (is_file(DEBUG_HIST_FILE))
	{
		echo file_get_contents(DEBUG_HIST_FILE);
		exit;
	}
}
if (isset($_GET['page']))
{
	$page     = intval($_GET['page']);
	$time_org = time();

	$file_path = DEBUG_TEMP.'/files.txt';
	if (is_file($file_path))
	{
		$files = unserialize(file_get_contents($file_path));
		//unlink($file_path);
	}
	else
	{
		$files = glob(DEBUG_TEMP.'/db-debug.dat.*');
		sort($files);
		$files = array_reverse($files);
		file_put_contents(DEBUG_TEMP.'/files.txt', serialize($files));
	}

	//$page_data = DEBUG_TEMP.'/page_dat.'.$page;
	////if (is_file($page_data))
	//{
	////	$pages = unserialize(file_get_contents($page_data));
	////	unlink($page_data);
	//}
	////else
	//{
	//	$page_max = $page + 10;
	//	$pages = [];
	//	for (; $page_max >= $page; -- $page_max)
	//	{
	//		$pages = array_reverse(array_slice($files, $page_max, 2000));
	//		if ($page_max > $page)
	//		{
	//			$pages_serial = serialize($pages);
	//			file_put_contents(DEBUG_TEMP.'/page_dat.'.$page_max, $pages_serial);
	//		}
	//	}
	//}
	$count = 5000;
	$pages = array_slice($files, $page * $count, $count);
	//fe($files);
	foreach ($pages as $file)
	{
		$content        = file_get_contents($file);
		$data           = unserialize($content);
		$user_agent     = isset($data['data']['GLOBALS']['$_SERVER']['HTTP_USER_AGENT'])
			? $data['data']['GLOBALS']['$_SERVER']['HTTP_USER_AGENT'] : '';
		$remote_addr    = isset($data['data']['GLOBALS']['$_SERVER']['REMOTE_ADDR'])
			? $data['data']['GLOBALS']['$_SERVER']['REMOTE_ADDR'] : '';
		$request_method = isset($data['data']['method']) ? $data['data']['method'] : '';
		$request_uri    = isset($data['data']['GLOBALS']['$_SERVER']['REQUEST_URI'])
			? $data['data']['GLOBALS']['$_SERVER']['REQUEST_URI'] : '';
		$http_host      = isset($data['data']['GLOBALS']['$_SERVER']['HTTP_HOST'])
			? $data['data']['GLOBALS']['$_SERVER']['HTTP_HOST'] : '';

		if (in_array($remote_addr, [
			'47.90.9.64', '175.188.159.61', '10.0.2.2', '106.127.151.43', '	116.252.211.147',
		]))
		{
			continue;
		}
		if ( ! in_array($remote_addr, [
			'113.103.251.46',
		])
		)
		{
			continue;
		}
		if (strpos($request_uri, '/demoshow') !== FALSE)
		{
			continue;
		}
		if (strpos($request_uri, '.asp') !== FALSE)
		{
			continue;
		}
		if (strpos($user_agent, 'Microsoft URL Control') !== FALSE)
		{
			continue;
		}
		if ($request_method == 'HEAD')
		{
			continue;
		}

		$long_time = substr($file, - 18);
		$time      = substr($file, - 18, 10);
		$log       = date('Y-m-d H:i:s', $time).
			'-'.$time.
			':<a target="_blank" title="'.$remote_addr.'" href="'
			.XDEBUG_HTTP_HOST.'/dev/xdebug-trace.php?time='
			.$long_time.'">'.$request_method.':'
			.$request_uri.'</a>('.
			$http_host.'-<a target="_blank" href="https://www.baidu.com/s?wd='.
			$remote_addr.'" ><font color="darkblue">'.
			$remote_addr.'</font></a>)<br/>';

		echo $log;
	}
	echo '<a href="?page='.($page + 1).'">Next</a><br/>'.(time() - $time_org);
	exit;
}
define('DEBUG_FB_SHOW', TRUE);
if (is_file(DEBUG_LIST_FILE))
{
	echo file_get_contents(DEBUG_LIST_FILE);
	unlink(DEBUG_LIST_FILE);
}