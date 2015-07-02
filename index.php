<?php
header("Content-type: text/html; charset=utf-8");
if(isset($_GET['timelog']))
{
	$log = DEBUG_TEMP.'/'.date('Y-m-d',$_GET['timelog']/100000000).'.log';
	if(file_exists($log)){ 
	   echo file_get_contents($log);
	   unlink($log);
	}
	exit;
}
if(isset($_GET['timeoutlog']))
{
	$log = DEBUG_TEMP.'/xdebug-trace.'.$_GET['timeoutlog'].'.log';
	if(file_exists($log)){ 
		echo file_get_contents($log);
	}
	exit;
}
if(isset($_GET['timeouterr']))
{
	$err = DEBUG_TEMP.'/xdebug-error.'.$_GET['timeouterr'].'.html';
	if(file_exists($err)){ 
		echo file_get_contents($err);
	}
	exit;
}
if($_SERVER['QUERY_STRING']=='hist')
{
	if(is_file(DEBUG_HIST_FILE)){
	echo file_get_contents(DEBUG_HIST_FILE);
	exit;
	}
}

if(is_file(DEBUG_LIST_FILE)){
echo file_get_contents(DEBUG_LIST_FILE);
unlink(DEBUG_LIST_FILE);
}