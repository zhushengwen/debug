<?php
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
if(is_file(DEBUG_TEMP.'/xdebug-trace.html')){
echo file_get_contents(DEBUG_TEMP.'/xdebug-trace.html');
unlink(DEBUG_TEMP.'/xdebug-trace.html');
}