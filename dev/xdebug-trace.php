<?php
// PHP debug tools - http://www.gosu.pl/debug/
// Author: Cezary Tomczak [cagret at gmail.com]
error_reporting(0);
ini_set("display_errors", 1);
ini_set('log_errors', 1);
ini_set('error_log', DEBUG_TEMP.'/!phperror.log');
ini_set('date.timezone', 'Asia/Shanghai');
ini_set('xdebug.overload_var_dump', false);
set_time_limit(0);
ini_set('memory_limit', -1);
$microstart = microtime(1);
$memorystart = memory_get_usage();

$show_headers = false;
$show_debug = false;
$included_files = array();

$controller_max_line = 0;
$action_not_found = TRUE;
function pf($file)
{
	if(strpos($file, 'auto_prepend.php')!==false||strpos($file, 'lib/db-mysql.php')!==false)return;
	global $included_files;
	foreach ($included_files as $value) {
		if(strtolower($file)==strtolower($value))
		{
			return;
		}
	}
	$included_files[]=$file;
}
function pt($file,$line=1,$title='')
{
  pf($file);
  return sprintf('%s:<a style="display:inline;text-decoration: none;" title="%s" href="javascript:location.href=\'notepad2://\'+((\'%s\'.indexOf(\':\')==-1)?\'http://\'+location.host:\'\')+\'%s/?%s\'"><b>%s</b></a>',
  $file,$title,$file,$file,$line,$line);
}
function ps($file)
{
  pf($file);
  return sprintf('<a style="display:inline;text-decoration: none;" href="javascript:location.href=\'notepad2://\'+((\'%s\'.indexOf(\':\')==-1)?\'http://\'+location.host:\'\')+\'%s/?1\'"><b>%s</b></a>',
  $file,$file,$file);
}
function fb_link_db($func,$params,$cid){
	if($func=='db_empty'){
		$temp = $params;
		if(preg_match('#(?:\$id = )?(\d+)\r\n(?:\$sql = )?&apos;(.+)&apos;#', $params, $match)){
		$id = $match[1];
		$sql = stripslashes($match[2]);
		return "<a id=\"sql_$id\" cid=\"$cid\" title=\"$sql\" href=\"javascript:open_db($id);\">$func</a>($id)";
		}
	}
	return $func;
}
function _debug()
{
	global $microstart, $memorystart;
	echo '<div>Memory: '.(round((memory_get_usage()-$memorystart)/1024)).' KB</div>';
	echo '<div>Time: '.number_format(microtime(1)-$microstart,2).'</div>';
	exit();
}
$gtime =$_GET['time'];
$trace_xt = DEBUG_TEMP."/xdebug-trace.$gtime.xt";
$data_xt = DEBUG_TEMP."/db-debug.dat.$gtime";
$group_nested = get('group-nested', 'bool');

$total_calls = 0;
$parsed_trace = array();
$final_trace = array();
if ($show_headers) {
	$rowtpl = array('call_id'=>null,'depth'=>0,'time'=>0,'time_nested'=>null,'is_nested'=>null,'memory'=>0,'memory_nested'=>0,'func'=>'','include'=>'','file'=>'','line'=>null, 'func_count'=>1,'nested_calls'=>null,'back_in'=>null,'parent_func'=>null);
} else {
	$rowtpl = array('call_id'=>null,'depth'=>0,'time'=>0,'memory'=>0,'func'=>'','include'=>'','file'=>'','line'=>null, 'func_count'=>1);
}
/*
$rowtpl = array('call_id'=>null,'depth'=>0,'time'=>0,'time_nested'=>null,'is_nested'=>null,'memory'=>0,'memory_nested'=>0,'func'=>'','include'=>'','file'=>'','line'=>null, 'func_count'=>1,'nested_calls'=>null,'back_in'=>null,'parent_func'=>null);
*/
$rowtpl_nonfunc = array('func'=>'','time'=>0, 'memory'=>0);
$prev = null;
$prev_func = array('depth'=>0,'func'=>'','include'=>'','file'=>'');
$row = null;
$next = null;
$start_depth = null;
$last_time = null;
$last_memory = null;
$first_row = true;
$parent_func = array(0=>''); // musi byc '', nie null, sprawdzanie back_in
$summary = array();
$real_total_time = 0;
$real_total_memory = 0;
$max_depth = 0;

$xthandle = fopen($trace_xt, 'r');
if (!$xthandle&&!file_exists($data_xt)) exit('Debug File No Found: '.'<br/>'.$trace_xt.'<br/>'.$data_xt);

$next_line = rtrim(fgets($xthandle,512));
$chunked_trace = array();
if($xthandle)
{
	while (!feof($xthandle))
	{
		$line = $next_line;
		$next_line = rtrim(fgets($xthandle));

		if (isset($next)) {
			$row = $next;
		} else {
			$row = parse_line($line);
		}
		if (!$row) continue;
		if ($first_row) { $first_row = false; $prev = $row; continue; }


		if(isset($row['file'])&&strpos($row['file'],'/debug/lib/debug.php'))continue;
		//if ('function_exists' == $row['func'] && $row['param'] == "&apos;data_cleanup&apos;")break;
		if ('data_cleanup' == $row['func']) break;
		if ($row['func'])
		{
			$total_calls++;
			$next = parse_line($next_line);

			$func = $row;
			$func['time'] = bcsub($row['time'], $prev['time'], 6);
			$func['memory'] = $row['memory'] - $prev['memory'];
			if ($func['memory'] < 0) $func['memory'] = 0;
			$func['is_nested'] = $next['depth'] > $func['depth'];

			if ($func['depth'] > $max_depth) $max_depth = $func['depth'];

			//show_headers:
			//$func['prev_depth'] = $prev_func['depth'];
			//$func['prev_func'] = $prev_func['func'];
			//$func['prev_include'] = $prev_func['include'];
			//$func['prev_file'] = $prev_func['file'];
			//$func['parent_func'] = $parent_func[$func['depth']];

			if ($prev_func['func'] && ($prev_func['depth'] != $row['depth'])) {
				foreach ($chunked_trace as $tmp_func) {
					$parsed_trace[] = $tmp_func;
				}
				$chunked_trace = array();
			}

			if ($func['is_nested'] || $func['include'] || true) {
				$chunked_trace[] = $func;
			} else if (isset($chunked_trace[$func['func']])) {
				$chunked_trace[$func['func']]['func_count']++;
				$chunked_trace[$func['func']]['time'] = bcadd($chunked_trace[$func['func']]['time'], $func['time'], 6);
				$chunked_trace[$func['func']]['memory'] += $func['memory'];
			} else {
				$chunked_trace[$func['func']] = $func;
			}

			//show_headers:
			//if ($func['is_nested']) $parent_func[$next['depth']] = $func['func'];

			$prev_func = $func;
		} else {
			$next = null;
		}
		if (strpos($row['func'],'->'))
		{
			list($class,$method) = explode('->',$row['func']);
			if($action_not_found && strpos(strtoupper($class),'CONTROLLER') !== FALSE && !in_array($method,array('before','after')))
			{
				$controller_max_line = $total_calls;
				$action_not_found = !(strpos($method,'action_')===0);
			}

		}
		$prev = $row;
	}

	foreach ($chunked_trace as $tmp_func) {
		$parsed_trace[] = $tmp_func;
	}
	fclose($xthandle);

	//_debug();

	// summary & real total time
	foreach ($parsed_trace as $func)
	{
		$func_name = $func['func'];
		if ($func['include']) $func_name = 'include';

		if (isset($summary[$func_name])) {
			$summary[$func_name]['count'] += $func['func_count'];
			$summary[$func_name]['time'] = bcadd($summary[$func_name]['time'], $func['time'], 6);
		} else {
			$summary[$func_name] = array(
				'func'=>$func_name, 'count' => $func['func_count'], 'time' => $func['time']
			);
		}

		$real_total_time = bcadd($real_total_time, $func['time'], 6);
		$real_total_memory += $func['memory'];
	}

	//ob_start();

	if ($show_debug) echo '<div>Time: '.number_format(microtime(1)-$microstart,3).'</div>';

	$parsed_count = count($parsed_trace);
	// liczymy time_nested
	$f1 = 0;
	$f2 = 0;
	for ($i = 0; $i < $parsed_count; $i++)
	{
		$f1++;
		if (!$parsed_trace[$i]['is_nested']) continue;
		$parent =& $parsed_trace[$i];
		$parent['time_nested'] = $parent['time'];
		$parent['memory_nested'] = $parent['memory'];
		$parent['nested_calls'] = 0;
		for ($k = $i+1; $k < $parsed_count; $k++) {
			$f2++;
			$parent['time_nested'] = bcadd($parent['time_nested'], $parsed_trace[$k]['time'], 6);
			$parent['memory_nested'] += $parsed_trace[$k]['memory'];
			$parent['nested_calls'] += $parsed_trace[$k]['func_count'];
			$is_last = (($k + 1) == $parsed_count);
			if ($is_last) { break; }
			if ($parsed_trace[$k+1]['depth'] <= $parent['depth']) break;
			if ($parsed_trace[$k+1]['depth'] == $parent['depth']) {
				$parsed_trace[$k+1]['back_in'] = $parent['parent_func'];
				break;
			}
		}
	}
}

function formate_class($str)
{
	$str = str_replace("\t","\\\\//",$str);
	$splish = explode('; ',$str);
	$retstr = '';
	$stack = 0;
	foreach($splish as $key => $val)
	{
		if($key&&$stack>=0) $retstr .= ";\n".str_repeat("\t",$stack);
		$lsplish = explode(' { ',$val);
		foreach($lsplish as $lkey => $lval)
		{
			if($lkey){$retstr .= " { \n".str_repeat("\t",$stack+1);$stack++;}
			$rsplish = explode(' }',$lval);
			foreach($rsplish as $rkey => $rval)
			{
				if($rkey){$stack--;if($stack>=0)$retstr .= "\n".str_repeat("\t",$stack)." }";}
				$rval = str_replace("\n","\n".str_repeat("\t",$stack+2),$rval);
				$retstr .= $rval;
			}
		}
	}
	$retstr = str_replace("\\\\//","\n",$retstr);
	$retstr = preg_replace("/{[ \t\n\.]+}/",'{ ... }',$retstr);
	return $retstr;
}
// wykorzystana zmienna $row z petli, nie przenosic tych linijek
//$total_memory = $row['memory'];

function parse_line($line)
{
	global $rowtpl;

	$tabs = explode("\t", $line);
	$count_tabs = count($tabs);

	if ($count_tabs < 5) return false;

	static $start_depth;
	if (null === $start_depth) {
		if (5 == $count_tabs) return array('func'=>false, 'time'=>$tabs[3], 'memory'=>$tabs[4]);
		if ($count_tabs > 8 && 'debug.php' == basename($tabs[8])) return false;
		if ('{main}' == $tabs[5]) return false;
		$start_depth = $tabs[0];
	}

	static $debug_console;
	if ($count_tabs > 5 && 'debug_console' == $tabs[5]) {
		$debug_console = $tabs[0];
		return false;
	}

	if (isset($debug_console) && $tabs[0] > $debug_console) {
		return false;
	} else {
		$debug_console = null;
	}

	if (5 == $count_tabs) return array('func'=>false, 'depth'=>$tabs[0]-$start_depth, 'time'=>$tabs[3], 'memory'=>$tabs[4]);

	if ('{main}' == $tabs[5]) return false;

	static $finished = false;
	if ($finished) return false;
	if ('debug_stop' == $tabs[5]) {
		$finished = true;
		return false;
	}

	$row = $rowtpl;
	//static $call_skip = false;
	//if($tabs[5] == 'fb_error_handler'){$call_skip=true;return;}
   // if($call_skip){if($tabs[5] ==  'file_put_contents')$call_skip = false;return;}
	static $call_id = 0;
	$call_id++;

	$row['call_id'] = $call_id;
	$row['depth'] = $tabs[0] - $start_depth;
	$row['time'] = $tabs[3];
	$row['memory'] = $tabs[4];
	$row['func'] = $tabs[5];
	if($count_tabs>7)$row['include'] = $tabs[7];
	if($count_tabs>8)$row['file'] = $tabs[8];
	if($count_tabs>9)$row['line'] = $tabs[9];
	if($count_tabs>10)
	{
		$row['pcount'] = $tabs[10];
		$arr = array_slice($tabs,11,$row['pcount']);
		foreach($arr as &$val)
		{
			if (preg_match ("#\\\\\\\\u([0-9a-f]{4})#ie", $val))
			{
				$val = @preg_replace("#\\\\\\\\u([0-9a-f]{4})#ie", "iconv('UCS-2', 'UTF-8', pack('H4', '\\1'))", $val);
			}
			$val = str_replace("\\n","\n",$val);

			if(strpos($val,'class') === 0)
			{
				$val = formate_class($val);
			}
			else
			{
				$val = str_replace('array (...)',"'[...]'",$val);

				if(strpos($line,"5710968"))
				{
					//exit($val);
				}
				 $i = stripos($val,' = array ');
				 if(stripos($val,'array ')===0){
					 $temp_val = preg_replace('/ \${[^}]+}:/',' $',$val);
					 $temp_val = str_replace('$','\$',$temp_val);
					 $temp_val = preg_replace('/ => class ([^}]*)}/',' => "class ${1}}"',$temp_val);
					 try
					 {
						 $val = @var_export(eval('return '.$temp_val.';'),true);
					 }catch (Exception $e)
					 {

					 }

				}elseif($i!==false)
				{
					$val = substr($val,0,$i).' = '.var_export(eval('return '.$val.';'),true);
				}
				$val = str_replace("'[...]'",'[...]',$val);
			}

	    $val = str_replace("'",'&apos;',$val);
	    $val = str_replace('"','&quot;',$val);
	    $val = str_replace('<','&lt;',$val);
	    $val = str_replace('>','&gt;',$val);
		}
	$row['param'] = implode("\r\n",$arr);
	}
	else $row['param'] = 'void';


	return $row;
}

// sumujemy wywolania na tym samym poziomie zagniezdzenia
/*
$summed_trace = array(); // func[func_count]
$prev_depth = 0;
$chunked_trace = array();
for ($i = 0; $i < $total_calls; $i++)
{
	if ($prev_depth != $parsed_trace[$i]['depth']) {
		$summed_trace = array_merge($summed_trace, sum_chunked_trace($chunked_trace));
		$chunked_trace = array();
	}

	$chunked_trace[] = $parsed_trace[$i];
	$prev_depth = $parsed_trace[$i]['depth'];
}
$summed_trace = array_merge($summed_trace, sum_chunked_trace($chunked_trace));
$parsed_trace = $summed_trace;

function sum_chunked_trace($chunked)
{
	$ret = array();
	foreach ($chunked as $func) {
		if ($func['is_nested']) {
			$ret[] = $func;
			continue;
		}
		if (isset($ret[$func['func']])) {
			$ret[$func['func']]['func_count'] += 1;
		} else {
			$ret[$func['func']] = $func;
		}
	}
	return array_values($ret);
}
*/



if ($show_debug) printf('<div>f1: %d , f2: %d</div>',$f1,$f2);

// wyswietlaj: grupuj po zagniezdzeniach
if ($group_nested) {
	$nested_trace = array();
}

function func_time($time)
{
	if (!isset($time)) return;
	//return $time;
	if (preg_match('/0\.00(0+)/', $time, $match)) {
		return '';
	} else {
		return number_format($time, 3);
	}
}
function func_memory($memory)
{
	if ($memory > 1024) {
		return round($memory / 1024).' KB';
	}
}

$internal = get_defined_functions();
$internal = $internal['internal'];

function get($key, $type=null)
{
	if (is_string($key)) {
		$_GET[$key] = isset($_GET[$key]) ? $_GET[$key] : null;
		if ('float' == $type) $_GET[$key] = str_replace(',','.',$_GET[$key]);
		settype($_GET[$key], $type);
		if ('string' == $type) $_GET[$key] = trim($_GET[$key]);
		return $_GET[$key];
	}
	$vars = $key;
	foreach ($vars as $key => $type) {
		$_GET[$key] = isset($_GET[$key]) ? $_GET[$key] : null;
		if ('float' == $type) $_GET[$key] = str_replace(',','.',$_GET[$key]);
		settype($_GET[$key], $type);
		if ('string' == $type) $_GET[$key] = trim($_GET[$key]);
		$vars[$key] = $_GET[$key];
	}
	return $vars;
}
function fsize($bytes)
{
	// bytes|filename, human readable file size
	if (!is_numeric($bytes)) $bytes = filesize($bytes);
	if ($bytes < 1024) return number_format($bytes/1024, 2).' KB';
	if ($bytes < 1024 * 1024) return round($bytes / 1024).' KB';
	return round($bytes / (1024*1024)).' MB';
}
function fpath($file)
{
	global $common_path;
	// human readable file path, cutting document_root prefix
	$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
	$file = str_replace('\\', '/', $file);
	if ($file == $common_path) {
		return '/'.basename($file);
	}
	if ('/' == substr($root,-1) && strlen($root) > 1) $root = substr($root,0,-1);
	$file = str_replace($root, '', $file);
	if ($common_path) $file = str_replace($common_path, '', $file);
	$file = preg_replace('/[a-z]:/i', '', $file);
	return $file;
}
function fdate($file)
{
	static $timezone;
	if (!isset($timezone)) $timezone = ini_get('date.timezone');
	$time = filemtime($file);
	return $timezone ? date('Y-m-d H:i:s', $time) : gmdate('Y-m-d H:i:s', $time);
}
function ftime($file)
{
	return filemtime($file);
}
function common_path($parsed_trace)
{
	$common = '';
	$i = 0;
	if($parsed_trace)
		while (true) {
		$prev_char = '';
		foreach ($parsed_trace as $trow) {
			if (!isset($trow['file'][$i])) return $common;
			$char = $trow['file'][$i];
			if ($prev_char && $char != $prev_char) return $common;
			$prev_char = $char;
		}
		$common .= $char;
		$i++;
	}
	return $common;
}
global $common_path;
$common_path = common_path($parsed_trace);
$common_path = str_replace('\\', '/', $common_path);
if ('/' == substr($common_path,-1) && strlen($common_path) > 1) {
	$common_path = substr($common_path,0,-1);
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf8">
    <title>xdebug-trace</title>
	<style type="text/css">
	body,table { font: 13px arial; }
	h1 { font-size: 125%; margin: 0.5em 0em; }
	h2 { font-size: 110%; margin: 0.5em 0em; }
	p { margin: 0.5em 0em; }
	th { background: #ddd; padding: 0.1em 0.5em; }
	td { padding: 0.1em 0.5em; }
	tr { background: #f5f5f5; }
	tr:hover{background: #ccc;}
	a.internal { color:#000; }
	a.include { color:blue; }
	a.user-defined { color:blue; }
	a.expanded { text-decoration: none; }
	a.collapsed { text-decoration: underline; }
	span.user-defined { color:#2B2B99; }
	.debug_console { position: fixed; bottom: 0.5em; right: 0.5em; color: #666; z-index: 50000; text-align: left; background: #f5f5f5; padding: 0.25em 0.5em; border: #ccc 1px solid; border-style: solid none none solid; font: normal 11px/16px "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif; text-decoration: none; padding: 2px 8px !important; border: 1px solid #bbb;	-moz-border-radius: 11px; -khtml-border-radius: 11px; -webkit-border-radius: 11px; border-radius: 11px; -moz-box-sizing: content-box; -webkit-box-sizing: content-box; -khtml-box-sizing: content-box; box-sizing: content-box; color: #464646;}
	.debug_console div { margin: 0.1em 0em; }
	.debug_console a { display: inline; text-decoration: none; color: #21759b; }
	.debug_console a.start { }
	.debug_console a.stop { color: #d54e21; }
	</style>
</head>
<body>

<?php if ($show_debug): ?>
	<div>Time: <?php echo number_format(microtime(1)-$microstart,3);?></div>
<?php endif; ?>

<?php if (!extension_loaded('xdebug')): ?>
	<div style="background: rgb(255,255,200); padding: 0.25em 0.5em; border: #ccc 1px solid; margin-bottom: 0.5em;">
		ERROR: xdebug php extension is not installed. The data being displayed is only a cache, it was generated on: <?php echo fdate($trace_xt);?>
	</div>
<?php endif; ?>
<?php $ftime = ftime($trace_xt);  ?>

<div id="help" style="display:none;">
# - call no
{} - nesting level
() - func calls sum
</div>

<h1>xdebug-trace</h1>

<p>
	<?php if ($xthandle): ?>
	Func calls: <b><?php echo $total_calls;?></b>
	-
	Time: <b><?php echo number_format($real_total_time,3);?></b>
	-
	Memory: <b><?php echo fsize($real_total_memory);?></b>
	-
	<a href="javascript:scroll('summary')">Summary</a>
	-
	<a href="javascript:void(0)" onclick="alert($('help').innerHTML.trim());">Help</a>
	-
	<?php endif; ?>
	<a href="javascript:open_db(0);">DB</a>
	<?php $log = DEBUG_TEMP.'/'.date('Y-m-d',$_GET['time']/100000000).'.log';
	if(file_exists($log)){ ?>
	-
	<a target="_blank" href="../index.php?timelog=<?php echo $_GET['time'];?>">Log</a>
	<?php }?>
	<?php $log = DEBUG_TEMP.'/xdebug-trace.'.$_GET['time'].'.log';
	if(file_exists($log)){ ?>
	-
	<a target="_blank" href="../index.php?timeoutlog=<?php echo $_GET['time'];?>">OutLog</a>
	<?php }?>
	<?php $err = DEBUG_TEMP.'/xdebug-error.'.$_GET['time'].'.html';
	if(file_exists($err)){ ?>
	-
	<a target="_blank" href="../index.php?timeouterr=<?php echo $_GET['time'];?>">Error</a>
	<?php }?>
</p>
<?php if ($xthandle): ?>
<p>
	<a href="javascript:collapse_all()" id="collapse-all">Collapse all</a>
	-
	<a href="javascript:expand_all()" id="expand-all">Expand all</a>
	-
	Expand to level:
	<?php for ($i = 1; $i <= $max_depth; $i++): ?>
		<a href="javascript:expand_depth(<?php echo $i;?>)" id="expand-depth-<?php echo $i;?>">[<?php echo $i;?>]</a>
	<?php endfor; ?>
</p>
<?php endif; ?>
<script>
function $(id) { return document.getElementById(id); }
String.prototype.trim = function() { return this.replace(/^\s*|\s*$/g, ""); };
var reg_expanded = new RegExp('expanded$');
var reg_collapsed = new RegExp('collapsed$');
function collapse_all()
{
	var table = document.getElementById('mtable');
	for (var r = 1; r < table.rows.length; r++)
	{
		if (table.rows[r].getAttribute('depth') > 0) {
			table.rows[r].style.display = 'none';
		} else {
			table.rows[r].style.display = '';
			var call_id = table.rows[r].getAttribute('call_id');
			var a = $('a_'+call_id);
			if (a) a.className = a.className.replace(/expanded$/, 'collapsed');
		}
	}
	$('collapse-all').blur();
}
function expand_all(link)
{
	var table = document.getElementById('mtable');
	for (var r = 1; r < table.rows.length; r++)
	{
		table.rows[r].style.display = '';
		var a = $('a_'+parseInt(table.rows[r].getAttribute('call_id')));
		if (a) a.className = a.className.replace(reg_collapsed, 'expanded');
	}
	$('expand-all').blur();
}
function expand_depth(base_depth)
{
	var table = document.getElementById('mtable');
	for (var r = 1; r < table.rows.length; r++)
	{
		var depth = parseInt(table.rows[r].getAttribute('depth'));
		if (depth > base_depth) table.rows[r].style.display = 'none';
		else {
			table.rows[r].style.display = '';
			var a = $('a_'+parseInt(table.rows[r].getAttribute('call_id')));
			if (depth < base_depth) {
				if (a) a.className = a.className.replace(reg_collapsed, 'expanded');
			}
			else {
				if (a) a.className = a.className.replace(reg_expanded, 'collapsed');
			}
		}
	}
	$('expand-depth-'+base_depth).blur();
}
function expand_func(base_call_id)
{
	var base_tr = $('tr_'+base_call_id);
	var base_a = $('a_'+base_call_id);
	var is_expanded = reg_expanded.test(base_a.className);
	if (is_expanded) base_a.className = base_a.className.replace(reg_expanded, 'collapsed');
	else base_a.className = base_a.className.replace(reg_collapsed, 'expanded');
	var base_depth = parseInt(base_tr.getAttribute('depth'));
	var tr = base_tr.nextSibling.nextSibling;
	while (tr) {
		var depth = parseInt(tr.getAttribute('depth'));
		if (is_expanded && depth > base_depth+1) {
			tr.style.display = 'none';
		}
		else if (depth == base_depth+1) {
			tr.style.display = is_expanded ? 'none' : '';
			var a = $('a_'+parseInt(tr.getAttribute('call_id')));
			if (a) {
				if (!is_expanded) a.className = a.className.replace(reg_expanded, 'collapsed');
			}
		}
		else if (depth <= base_depth) break;
		tr = tr.nextSibling.nextSibling;
	}
	base_a.blur();
}
function debug_popup(url, width, height, more)
{
    if (!width) width = 800;
    if (!height) height = 650;
    var x = (screen.width/2-width/2);
    var y = (screen.height/2-height/2);
    var r=window.open(url, "", "scrollbars=yes,resizable=yes,width="+width+",height="+height+",screenX="+(x)+",screenY="+y+",left="+x+",top="+y+(more ? ","+more : ""));
   	if(height==650)
   		window.fb_trace = r;
   	else window.fb_db = r;
    return r;
}
function open_db(id){
	try{
		opener.open_db(id);
	}
	catch(e)
	{
		if(this.fb_db && !this.fb_db.closed){
			this.fb_db.open_db(id);
		}else{
			var regr = /^.*time=(\d{18}).*$/.exec(location.href);
			if(regr){
				this.fb_db = debug_popup("<?php echo DEBUG_FDB_SCRIPT.'?time=';?>"+regr[1],800,500);
				this.fb_db.onload = function (){
								this.open_db(id);
				}
			}
		}
	}
}
function open_trace(id){
	this.focus();
	with(document.body.style){var k=0.01;s=3000;e=4000;p=e;t=20;n=1;i=3040;(function(){if(!(i>s&&i<e)){t=-t;n--;}i+=t;backgroundColor="#"+i.toString(16);if(i-p&&n>0)setTimeout(arguments.callee,1);else backgroundColor="#FFF";})();}
	if(!id)return;
	var dba = $('sql_'+id);
	if(dba){
		var call_id = parseInt(dba.getAttribute('cid'));
		if(call_id) open_tr(call_id);
	}
}
<?php
if(AUTO_EXPAND_CONTROLLER && $controller_max_line)echo "if(!this.onload)this.onload = function(){open_tr($controller_max_line);};";
?>

function open_tr(call_id){
		var dest = 'tr_'+call_id;
		var base_tr = $(dest);
		var first = true;
		if(base_tr){
			var base_depth = parseInt(base_tr.getAttribute('depth'));
			while(base_depth && call_id){
				if(base_tr && parseInt(base_tr.getAttribute('depth'))<=base_depth)
				{
					base_tr.style.display = '';
					var base_a = $('a_'+call_id);
					if(base_a){
						var is_expanded = reg_expanded.test(base_a.className);
						if (!is_expanded && first) expand_func(call_id);
						first = false;
					}

					base_depth--;
				}
				call_id--;
				base_tr = $('tr_'+call_id);
			}
			scroll(dest);
		}

}
function scroll(dest, ignore_when_top)
{
	dest = $(dest);
	var desty = dest.offsetTop;
	var thisNode = dest;
	while (thisNode.offsetParent &&	(thisNode.offsetParent != document.body)) {
		thisNode = thisNode.offsetParent;
		desty += thisNode.offsetTop;
	}
	if (ignore_when_top) {
		var y = scroll_current_pos();
		if (y < desty) {
			return;
		}
	}
	window.scrollTo(0,desty);
}
function scroll_current_pos()
{
	if (document.body && document.body.scrollTop)
		return document.body.scrollTop;
	if (document.documentElement && document.documentElement.scrollTop)
		return document.documentElement.scrollTop;
	if (window.pageYOffset)
		return window.pageYOffset;
	return 0;
}
</script>
<?php if ($xthandle): ?>
<table cellspacing="1" id="mtable">
<tr>
	<th>#</th>
	<th>{}</th>
	<th>Time</th>
	<th>Function</th>
	<th>()</th>
	<th>File</th>
	<th>Memory</th>
</tr>
<?php $last_depth = null; $last_file = null; $indent = array(); $last_include = ''; $tr_style = ''; $last_tr = ''; $last_tr_depth = 0; $tr_count = 0; $prev_row = null; ?>
<?php foreach ($parsed_trace as $k => $trace): ?>
<?php
	$func_class = '';
	if ($trace['include']) $func_class = 'include internal';
	else {
		if (in_array($trace['func'], $internal)) $func_class='internal';
		else $func_class='user-defined';
	}
	$tr_style = '';

	if ($show_headers){
		$back_in = false;
		if ($trace['file'] == $trace['prev_file'] && $trace['depth'] < $trace['prev_depth'] && isset($trace['back_in']) && $trace['depth'] < $last_tr_depth) {
			$back_in = $trace['back_in'];
			$tr_style = 'background:#eee;';
			$tr_count++;
		}

		if (!$tr_style && $k < $parsed_count && ($trace['file'] != $trace['prev_file'])) {
			$tr_style = 'background:#eee;';
			$tr_count++;
		}

		$last_tr = $tr_style;
		$last_file = $trace['file'];
	}
?>
<?php if ($show_headers && $tr_style): ?>
<tr>
	<th><?php echo $tr_count;?></th>
	<th><?php echo $trace['depth'];?></th>
	<th></th>
	<th colspan="6" style="text-align: left;">
		<?php
			$close_include = false;
			if ($back_in) echo 'back in: '.$back_in.'()';
			else if ($k && $trace['prev_include']) { echo $trace['prev_func'].': '.fpath($trace['file']); $close_include = true; }
			else if ($k && !$trace['prev_include'] && $trace['prev_depth'] < $trace['depth']) echo $trace['prev_func'].'() in '.fpath($trace['file']);
			else if ($k && !$trace['prev_include'] && $trace['prev_depth'] > $trace['depth'] && $trace['parent_func'] && !in_array($trace['parent_func'],array('include','include_once','require','require_once'))) echo 'back in: '.$trace['parent_func'].'()';
			else if ($k) echo 'back in: '.fpath($trace['file']);
			else echo fpath($trace['file']);
		?>
	</th>
</tr>
<?php $last_tr_depth = $trace['depth']; ?>
<?php endif; ?>
<?php
	$depth = $trace['depth'] - $last_tr_depth;
	if ($depth < 0) $depth = 0;
	if (!isset($indent[$depth])) $indent[$depth] = str_repeat('&nbsp;',$depth*4);
?>
<tr call_id="<?php echo $trace['call_id'];?>" depth="<?php echo $trace['depth'];?>" id="tr_<?php echo $trace['call_id'];?>" style="<?php echo $trace['depth']>0?'display:none;':'';?>">
	<td nowrap align="center"><?php echo $trace['call_id'];?></td>
	<td nowrap align="center"><?php echo $trace['depth'];?></td>
	<td nowrap><?php echo func_time(isset($trace['time_nested']) ? $trace['time_nested'] : $trace['time']);?></td>
	<td nowrap title="<?php echo $trace['param'];?>" ondblclick="l(this)"><?php echo $indent[$depth];?><?php if ($trace['is_nested']):?><a id="a_<?php echo $trace['call_id'];?>" class="<?php echo $func_class;?> collapsed" href="javascript:expand_func(<?php echo $trace['call_id'];?>)"><?php else:?><span class="<?php echo $func_class;?>"><?php endif;?><?php echo fb_link_db($trace['func'],$trace['param'],$trace['call_id']);?><?php echo $trace['is_nested']?'</a>':'</span>';?><?php if ($trace['include']): ?>: <?php echo ps(fpath($trace['include']));?><?php endif; ?>
	</td>
	<td nowrap align="center"><?php if (isset($trace['nested_calls'])):?><?php echo $trace['nested_calls']+$trace['func_count'];?><?php else:?><?php echo $trace['func_count']>1?$trace['func_count']:'';?><?php endif;?></td>
	<td nowrap><?php echo pt(fpath($trace['file']),$trace['line'],$trace['param']);?></td>
	<td nowrap align="right"><?php echo func_memory(isset($trace['memory_nested']) ? $trace['memory_nested'] : $trace['memory']);?></td>
</tr>
<?php $prev_row = $trace; ?>
<?php endforeach; ?>
</table>


	<div>Time: <?php echo number_format(microtime(1)-$microstart,3);?></div>
<?php endif; ?>

<?php
	foreach ($summary as $k => $func) {
		$summary[$k]['time'] = func_time($func['time']);
	}
	$summary = array_msort($summary, array('count'=>SORT_DESC, 'func'=>SORT_ASC));
?>
<?php

$debug_time = isset($_GET['time'])?$_GET['time']:$_SERVER['QUERY_STRING'];
$data_file = DEBUG_FDB_ORG.'.'.$debug_time;
if (file_exists($data_file)) {
	include_once './krumo/class.krumo.php';
	$data = unserialize(file_get_contents($data_file));
	$method = $data['data']['method'];
	$uri = $data['data']['uri'];
	$xmlr_data = isset($data['data']['GLOBALS']['$HTTP_RAW_POST_DATA'])?$data['data']['GLOBALS']['$HTTP_RAW_POST_DATA']:'';
	$ir_ajax = strpos($method, 'ajax')!==false?1:0;
	$is_ajax = ($xmlr_data || $ir_ajax)?1:0;
	if($xmlr_data)$xmlr_data = '"'.str_replace(array("\"","\r","\n"), array("\\\"","\\r","\\n"), $xmlr_data).'"';
	$is_post = strpos($method, 'POST')!==false?1:0;
	$re_method = $is_ajax?substr($method,5):$method;
	$get_data =  $data['data']['GLOBALS']['$_GET'];
	$post_data =  $data['data']['GLOBALS']['$_POST'];

	$req_data = http_build_query($is_post?$post_data:$get_data);
 ?>
<a id="DataSumary" name="DataSumary"></a>
<h2 style="float:left;"><?php echo $method.' : <a target="_blank" style="color:black;" href="'.$uri.'">'.$uri.'</a>';?></h2>
<a style="float:left;margin-left: 0.5em;margin-top:0.5em;" href="javascript:void(0)" onclick="redo();">Replay</a>
<script>

function redo(){
var l = '<?php echo $uri;?>';
var rjax = <?php echo $ir_ajax;?>;
var ajax = <?php echo $is_ajax;?>;
var method = '<?php echo $re_method;?>';
var d = '<?php echo $req_data;?>';
function getUrl(str){
		var s= str.indexOf('<url>')+5;
		var e= str.indexOf('</url>');
		return str.substring(s,e);
}
debug_cookie_set('xdebug-redo',1);
if(!ajax){
	if(!window.replay_form)
	{
		window.replay_form = document.createElement("form");
		replay_form.action = l;
	    replay_form.target = "replay_frm";
	    replay_form.method = method;
	    replay_form.style.display="none";
		document.body.appendChild(replay_form);

		var ifm=document.createElement("iframe");
		ifm.name="replay_frm";
		ifm.style.display="none";
		document.body.appendChild(ifm);
		var load=function(){
		 //debug_popup(getUrl(ifm.contentWindow.document.body.innerHTML));
		}
		if(ifm.attachEvent){
		ifm.attachEvent("onload", load);
		}else{
		ifm.onload = load;
	   	}


	    var ar=d.split('&');
	    for(var a in ar)
	    {
		    	var br=ar[a].split('=');
		        if(br[0])
	            {
	            var input = document.createElement("input");
		        input.name = br[0];
		        input.value = br[1]||'';
		        input.type = 'hidden';
		        replay_form.appendChild(input);
		    	}
	    }
	}
	replay_form.submit();
	}
	else {
	var p = <?php echo $is_post;?>;
	var x = '<?php echo $xmlr_data;?>';
	var r = new(self.XMLHttpRequest||ActiveXObject)("Microsoft.XMLHTTP");
	r.onreadystatechange = function() {
		if (r.readyState == 4 && r.status == 200){
			debug_popup(getUrl(r.responseText));
		}
	}
	if(p){
		r.open('POST', l, true);
		if(x){
			if(rjax)r.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			r.setRequestHeader("Content-Type","application/text/xml; charset=UTF-8");
			r.send(x);
		}else{
			r.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			r.setRequestHeader('Content-type', 'application/x-www-form-urlencoded; charset=UTF-8');
			r.send(d);
		}

	}
	else{
		r.open('GET', l + (d&&l.indexOf('?')==-1?'?':'') + (d&&l.lastIndexOf('&')!=l.length-1?'&':'') + d, true);
		r.setRequestHeader("X-Requested-With", "XMLHttpRequest");
		r.setRequestHeader('Content-type', 'application/x-www-form-urlencoded; charset=UTF-8');
		r.send();
	}
	debug_cookie_del('xdebug-redo');
}


}
</script>
<div style="clear:both;"></div>
<?php krumo($data['data']['GLOBALS'],'GLOBALS');?>
<?php  }?>
<?php if ($xthandle): ?>
<a id="summary" name="summary"></a>
<h2 style="float:left;">Summary</h2>
<a style="float:left;margin-left: 0.5em;margin-top:0.5em;" href="javascript:void(0)" onclick="history.go(-1);window.scrollTo(0,0);">Up</a>

<div style="clear:both;"></div>
<table cellspacing="1">
<tr>
	<th>#</th>
	<th>Function</th>
	<th>Calls</th>
	<th>Time</th>
</tr>
<?php $test_count=0; $k=0; foreach ($summary as $func): $test_count+=$func['count']; $k++; ?>
<?php $func_style=''; if (!in_array($func['func'], $internal) && $func['func'] != 'include') $func_style='color:#2B2B99'; else $func_style='color:#000;'; ?>
<tr>
	<td><?php echo $k;?></td>
	<td style="<?php echo $func_style;?>"><?php echo $func['func'];?></td>
	<td><?php echo $func['count'];?></td>
	<td><?php echo $func['time'];?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php if ($show_debug): ?>
	<div>Time: <?php echo number_format(microtime(1)-$microstart,3);?></div>
<?php endif; ?>
<?php if($included_files) echo debug_included_files($included_files);?>
</body>
<script>
function l(td){console.log(td.title);}
function debug_popup(url, width, height, more)
{
    if (!width) width = 800;
    if (!height) height = 650;
    var x = (screen.width/2-width/2);
    var y = (screen.height/2-height/2);
    var r=window.open(url, "", "scrollbars=yes,resizable=yes,width="+width+",height="+height+",screenX="+(x)+",screenY="+y+",left="+x+",top="+y+(more ? ","+more : ""));
   	if(height==650)
   		window.fb_trace = r;
   	else window.fb_db = r;
    return r;
}
function debug_cookie_set(name, value)
{
	var cookie = name + '=' + escape(value) + '; path=/';
	document.cookie = cookie;
}
function debug_cookie_del(name, path)
{
    var cookie = (name + '=');
    path = path ? path : '/';
    if (path) cookie += '; path='+path;
    cookie += '; expires=Thu, 01-Jan-70 00:00:01 GMT';
    document.cookie = cookie;
}
function debug_cookie_clear()
{
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; ++i) {
        var a = cookies[i].split('=');
        if (a.length == 2) {
            a[0] = a[0].replace(/^\s*|\s*$/g, '');
            a[1] = a[1].replace(/^\s*|\s*$/g, '');
            if(/^\d{4}\.\d{2}:\d{2}:\d{2}$/.test(a[0])) debug_cookie_del(a[0]);
        }
    }
    debug_list();
}
function debug_list()
{
	var uri = "<?php echo isset($data['data']['uri'])?$data['data']['uri']:'';?>";
	var list = [];
	var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; ++i) {
        var a = cookies[i].split('=');
        if (a.length == 2) {
            a[0] = a[0].replace(/^\s*|\s*$/g, '');
            a[1] = a[1].replace(/^\s*|\s*$/g, '');
            if(/^\d{4}\.\d{2}:\d{2}:\d{2}$/.test(a[0]))
            	list.push(JSON.parse(unescape(a[1])));
        }
    }

	var html = '<div>DebugList -<a href="javascript:debug_cookie_clear();">clear</a>- Count('+list.length+')</div>';
	for(var i=list.length-1;i>=0;i--)
	{
		var s = list[i].uri;
		if(s){
			var r = s.indexOf('://');
			var t = s.indexOf('/',r+3);
			if(s.length-t>40)
			{
				s = s.substr(t,10)+'...'+s.substr(-30);
			}else s=s.substr(t);
			html += '<div><a title="'+list[i].uri+'" style="color:'+ (uri==list[i].uri?'blue':'black')+';" href="'+list[i].uri+'" target="_blank">'+list[i].method+'</a>:<a'+ (uri==list[i].uri?' style="color:blue;"':'')+' title="'+list[i].uri+'" href="javascript:'+ list[i].url  +';void(0);">'+s+'</a></div>';
		}
	}


	var newNode = document.createElement("div");

	newNode.innerHTML = html;
	newNode.className = "debug_console";
	document.body.appendChild(newNode);

	newNode.children[0].onclick = function(){
		var nds = newNode.children;
		for (var i = 1; i < nds.length; i++) {
			with(nds[i].style){
				if(display=="")display="none";else display = "";
			}
		};
	}
}
debug_list();
function toggle_pre(id, pre_id)
{
	$('h2_'+id).blur();
	if ($(id).style.height == '0px') {
		$(id).style.height = 'auto';
		$(id).style.overflow = 'visible';
	} else {
		$(id).style.height = 0 + 'px';
		$(id).style.overflow = 'auto';
	}
}
</script>
</html>
<?php ob_end_flush(); ?>
<?php
function array_msort($array, $cols)
{
    $colarr = array();
    foreach ($cols as $col => $order) {
        $colarr[$col] = array();
        foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
    }
    $params = array();
    foreach ($cols as $col => $order) {
        $params[] =& $colarr[$col];
        $params = array_merge($params, (array)$order);
    }
    call_user_func_array('array_multisort', $params);
    $ret = array();
    $keys = array();
    $first = true;
    foreach ($colarr as $col => $arr) {
        foreach ($arr as $k => $v) {
            if ($first) { $keys[$k] = substr($k,1); }
            $k = $keys[$k];
            if (!isset($ret[$k])) $ret[$k] = $array[$k];
            $ret[$k][$col] = $array[$k][$col];
        }
        $first = false;
    }
    return $ret;

}
?>