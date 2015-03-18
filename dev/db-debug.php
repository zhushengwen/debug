<?php

error_reporting(-1);
ini_set('display_errors', true);
ini_set('log_errors', 1);
ini_set('error_log', DEBUG_TEMP.'/!phperror.log');
ini_set('html_errors', 0);
ini_set('date.timezone', 'Asia/Shanghai');



$get = get(array(
	'id' => 'string',
	'ord' => 'string',
	'table' => 'string'
));

/*
if (!preg_match('#^\d+(\.\d+)?$#', $get['id'])) {
	trigger_error('Invalid dbg id', E_USER_ERROR);
}
*/
$debug_time = isset($_GET['time'])?$_GET['time']:$_SERVER['QUERY_STRING'];
$data_file = DB_DEBUG_ORG.'.'.$debug_time;

/*
preg_match('#^\d+#', $get['id'], $match);
$date = date_by_time($match[0]);
*/

if (!file_exists($data_file)) {
	trigger_error($data_file.' not found', E_USER_ERROR);
}

function query_cut($query)
{
	// removes sub-queries and string values from query
	$brace_start = '(';
	$brace_end = ')';
	$quote = "'";
	$inside_brace = false;
	$inside_quote = false;
	$depth = 0;
	$ret = '';
	$query = str_replace('\\\\', '', $query);

	for ($i = 0; $i < strlen($query); $i++)
	{
		$prev_char = isset($query{$i-1}) ? $query{$i-1} : null;
		$char = $query{$i};
		if ($char == $brace_start) {
			if (!$inside_quote) {
				$depth++;
			}
		}
		if ($char == $brace_end) {
			if (!$inside_quote) {
				$depth--;
				if ($depth == 0) {
					$ret .= '(...)';
				}
				continue;
			}
		}
		if ($char == $quote) {
			if ($inside_quote) {
				if ($prev_char != '\\') {
					$inside_quote = false;
					if (!$depth) {
						$ret .= "'...'";
					}
					continue;
				}
			} else {
				$inside_quote = true;
			}
		}
		if (!$depth && !$inside_quote) {
			$ret .= $char;
		}
	}
	return $ret;
}
function table_from_query($query)
{
	if (preg_match('#\sFROM\s+`?(\w+)`?#i', $query, $match)) {
		$cut = query_cut($query);
		if (preg_match('#\sFROM\s+`?(\w+)`?#i', $cut, $match2)) {
			$table = $match2[1];
		} else {
			$table = $match[1];
		}
	} else if (preg_match('#UPDATE\s+`?(\w+)`?#i', $query, $match)) {
		$table = $match[1];
	} else if (preg_match('#INSERT\s+INTO\s+`?(\w+)`?#', $query, $match)) {
		$table = $match[1];
	} else {
		$table = false;
	}
	if($table) $table = trim($table,'`');
	return $table;
}

$data = unserialize(file_get_contents($data_file));

$queries = array();
foreach ($data as $k => $row)
{
	$query = array();

	if (isset($row['Query'])) $row['query'] = $row['Query'];
	if (isset($row['Time'])) $row['time'] = $row['Time'];

	$table = table_from_query($row['query']);
	if (!$table) { $table = 'mysql'; }

	if (preg_match('#^\s*SELECT\s+#i', $row['query'])) {
		$type = 'SELECT';
	} else if (preg_match('#^\s*DELETE\s+#i', $row['query'])) {
		$type = 'DELETE';
	} else if (preg_match('#^\s*UPDATE\s+#i', $row['query'])) {
		$type = 'UPDATE';
	} else if (preg_match('#^\s*INSERT\s+INTO\s+#', $row['query'])) {
		$type = 'INSERT';
	} else {
		$type = '-';
	}

	$query['id'] = $k+1;
	$query['table'] = $table;
	$query['type'] = $type;
	$query['time'] = $row['time'];
	$query['query'] = $row['query'];
	$query['data'] = $row['data']?(array)json_decode($row['data']):'';
	$queries[] = $query;
}

// tables count befor where
$tables = array_col_values_count($queries, 'table');
foreach ($tables as $k => $v) {
	$tables[$k] = $k.' ('.$v.')';
}

// where condition
if ($get['table']) {
	$queries = array_find_rows($queries, array('table'=>$get['table']));
}

// total time after where
$total_time = 0;
foreach ($queries as $query) {
	$total_time += $query['time'];
}

function query_type_color($type)
{
	$color = null;
	switch ($type) {
		case 'SELECT':
			$color = 'green';
			break;
		case 'UPDATE':
			$color = '#ff00ff';
			break;
		case 'DELETE':
			$color = 'red';
			break;
		case 'INSERT':
			$color = '#8000FF';
			break;
	}
	if ($color) {
		$type = sprintf('<font color="%s">%s</font>', $color, $type);
	}
	return $type;
}
function query_color($query)
{
	$query = trim($query);
	$color = 'blue';
	$words = array('SELECT', 'UPDATE', 'DELETE', 'FROM', 'LIMIT', 'OFFSET', 'AND', 'INNER JOIN', 'OUTER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'JOIN', 'WHERE', 'SET', 'NAMES', 'ORDER BY', 'GROUP BY', 'GROUP', 'DISTINCT', 'COUNT', 'COUNT\(\*\)', 'IS', 'NULL', 'IS NULL', 'AS', 'ON', 'INSERT INTO', 'VALUES', 'BEGIN', 'COMMIT', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'IN', 'NOT', 'LIKE', 'ILIKE', 'ASC', 'DESC', 'LOWER', 'UPPER');
	$words = implode('|', $words);

	$query = preg_replace("#^({$words})(\s)#i", '<font color="'.$color.'">$1</font>$2', $query);
	$query = preg_replace("#(\s)({$words})$#i", '$1<font color="'.$color.'">$2</font>', $query);
	// replace twice, some words when preceding other are not replaced
	$query = preg_replace("#([\s\(\),])({$words})([\s\(\),])#i", '$1<font color="'.$color.'">$2</font>$3', $query);
	$query = preg_replace("#([\s\(\),])({$words})([\s\(\),])#i", '$1<font color="'.$color.'">$2</font>$3', $query);
	$query = preg_replace("#^($words)$#i", '<font color="'.$color.'">$1</font>', $query);

	preg_match_all('#<font[^>]+>('.$words.')</font>#i', $query, $matches);
	foreach ($matches[0] as $k => $font) {
		$font2 = str_replace($matches[1][$k], strtoupper($matches[1][$k]), $font);
		$query = str_replace($font, $font2, $query);
	}

	return $query;
}

$cols = array(
	'id' => 'Id',
	'table' => 'Table',
	'time' => 'Time',
	'query' => 'Query',
	'type' => 'Type',
	'A'
);

if (!$get['ord']) {
	//$get['ord'] = 'time_desc';
}
$get['ord'] = ord_filter($get['ord'], 'ord', $cols, 'id');

if ($get['ord']) {
	if (str_ends_with($get['ord'], '_desc')) {
		$col = str_cut_end($get['ord'], '_desc');
		$queries = array_sort_desc_col($queries, $col);
	} else {
		$queries = array_sort_col($queries, $get['ord']);
	}
}

ob_start();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>db-debug</title>
	<style type="text/css">
	body,table { font-family: tahoma; font-size: 11px; }
    body { margin: 1em; padding: 0; margin-top: 0.7em; }
    .hide{display: none;}
    h1, h2, h3, h4 { margin: 0.5em 0; margin-bottom: 0.3em; }
    h1 { font-size: 150%; }
    h2 { font-size: 130%; }
    h3 { font-size: 115%; }
    h4 { font-size: 105%; }
    a { text-decoration: none; }
    a:hover { text-decoration: underline; }
    form { margin: 0; }
    input, select { font-size: 11px; }

	.listing tr.row1 td, .listing tr.row2 td { padding: 0.15em 0.5em; }
	th { background: #bbb; color: #fff; }
	.row1 { background: #f0f0f0; }
    .row2 { background: #f0f0f0; }

    th.listing_th { line-height: 1.7em; padding: 0; }
    th.listing_thi {  padding: 0;color: #000; }
    th.listing_th div { padding-left: 1.2em; padding-right: 1.2em; }
    th.listing_th a, th.listing_th a:visited, th.listing_th a:visited, th.listing_th a:hover { display: block; color: #fff; text-decoration: none; padding-left: 1.6em; padding-right: 1.6em; }
    th.listing_th a.asc { background: url("img/order-asc.gif") no-repeat; background-position: 0.5em 0.2em; }
    th.listing_th a.desc { background: url("img/order-desc.gif") no-repeat; background-position: 0.5em 0.2em; }
    th.listing_th a:hover { background-color: #999; }
    th.listing_a, th.listing_a a, th.listing_a a:hover, th.listing_a a:visited { padding: 0; }

    .listing .row1 td, .listing .row2 td,.listingi td { border: 1px solid; border-style: solid none solid none; padding-bottom: 1px; }
    .listing .row1 td { border-color: #e0e0e0; }
    .listing .row2 td { border-color: #f0f0f0; }

    .listing a, .ls a, .listing a:visited, .ls a:visited { text-decoration: none; }
    .listing a:hover, .ls a:hover { text-decoration: underline; }

    .pager { padding: 0.5em 0em; padding-top: 0.25em; }

    .nopadding{padding: 0;border-collapse: collapse;}
    .listingi{ background:#C5D3E1}
	table table.listingi td{ background:#fff;border-width: 0;padding: 1px;}
	</style>
</head>
<body>

<h1>db-debug</h1>

<script>
function toggle(id)
{
	var truncate = document.getElementById('drow'+id);
	if (truncate.className) {
		truncate.className = '';
	} else {
		truncate.className = 'hide';
	}
}
function collapse_all(){
	var Things = document.getElementsByTagName('div');
	for (var i = 1; i < Things.length; i++) {
		Things[i].className="hide";
	};
}
function expand_all(){
	var Things = document.getElementsByTagName('div');
	for (var i = 1; i < Things.length; i++) {
		Things[i].className="";
	};
}
function open_db(id){
	scroll("sql_"+id);
}
function scroll(dest)
{
	dest = document.getElementById(dest);
	var desty = dest.offsetTop;
	var thisNode = dest;
	while (thisNode.offsetParent &&	(thisNode.offsetParent != document.body)) {
		thisNode = thisNode.offsetParent;
		desty += thisNode.offsetTop;
	}
	window.scrollTo(0,desty);
}
</script>

<form action="<?php echo $_SERVER['REQUEST_URI'];?>" method="get">
<input type="hidden" name="time" value="<?php echo $debug_time;?>"/>
<table cellspacing="0" cellpadding="1">
<tr>
	<td>Table:</td>
	<td><select name="table"><option value=""></option><?php echo options($tables, $get['table']);?></select></td>
	<td class="none"><input type="submit" wait="1" class="button" value="Filter"></td>
</tr>
</table>
</form>

<?php

	function listing_query($query)
	{
		global $get;
		?>
			<td valign="top" align="center"><a id="sql_<?php echo $query['id'];?>" href="javascript:opener.open_trace(<?php echo $query['id'];?>)"><?php echo $query['id'];?></a></td>
			<td valign="top"><?php echo $query['table'];?></td>
			<td valign="top" align="center"><?php echo number_format($query['time'],3);?></td>
			<?php if (strlen($query['query']) > 60): ?>
				<td>
					<?php
						$q = $query['query'];
						$q = preg_replace('#(\w),(\w)#', '$1, $2', $q);
						$q_full = $q;
						$q_full = preg_replace('#[ ]+#', ' ', $q_full);
						$q_full = preg_replace('#([\r\n]+)[ ]+#', '$1', $q_full);
						$q_full = preg_replace("#[\r\n]+#", "\r\n", $q_full);
						$q_full = query_color(escape_once($q_full));
						//$q_full = nl2br($q_full);
					?>
					<?php echo $q_full;?>
				</td>
			<?php else: ?>
				<td><?php echo query_color(escape_once($query['query']));?></td>
			<?php endif; ?>
			<td valign="top"><?php echo query_type_color($query['type']);?></td>
			<td align="center">
				<?php if (preg_match('#^\s*SELECT#i', $query['query'])): ?>
					<a style="color: #000;" href="javascript:void(0)" onclick="toggle(<?php echo $query['id'];?>)">&gt;&gt;</a>
				<?php else: ?>
					-
				<?php endif; ?>
			</td>
		<?php
	}
	function listing_query_head($config)
	{
		global $total_time;
		?>
			<td colspan="<?php echo $config['colspan'];?>" class="none pager">
				Found: <b><?php echo $config['count'];?></b>
				&nbsp;-&nbsp;
				Time: <b><?php echo number_format($total_time,3);?></b> sek
				&nbsp;-&nbsp;
				<a id="collapse-all" href="javascript:collapse_all()">Collapse all</a>
				&nbsp;-&nbsp;
				<a id="expand-all" href="javascript:expand_all()">Expand all</a>
			</td>
		<?php
	}

	echo listing(array(
		'cols' => $cols,
		'rows' => $queries,
		'row_func' => 'listing_query',
		'head_func' => 'listing_query_head',
		'pk' => 'id',
		'ord_key' => 'ord',
		'ord_fields' => $cols,
		'lang_found' => 'Zapyta'
	));

?>
</body>
</html>
<?php ob_end_flush(); ?>
<?php
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
function options($options, $selected = null, $ignore_type =	false)
{
	$ret = '';
	foreach	($options as $k	=> $v) {
		// old:	str_replace('"', '\"', $k)
		$ret .=	'<option value="'.escape_once($k).'"';
		if ((is_array($selected) &&	in_array($k, $selected)) ||	(!is_array($selected) && $k	== $selected && $selected !== '' && $selected !== null)) {
			if ($ignore_type) {
				$ret .=	' selected="selected"';
			} else {
				if (!(is_numeric($k) xor is_numeric($selected))) {
					$ret .=	' selected="selected"';
				}
			}
		}
		$ret .=	'>'.htmlspecialchars(strip_tags($v)).' </option>';
	}
	return $ret;
}
function escape_once($s)
{
	$s = str_replace(array('&lt;','&gt;','&amp;lt;','&amp;gt;'),array('<','>','&lt;','&gt;'),$s);
	return str_replace(array('&lt;','&gt;','<','>'),array('&amp;lt;','&amp;gt;','&lt;','&gt;'),$s);
}
function url($url, $params = array())
{
	if (null === $url) $url = $_ENV['URL'];
	// Eaxmple 1: url('test.php',array('id'=>1)
	// == test.php?id=1
	// Example 2: url('test.php?id=12&cat=miau', array('cat'=>'','newcat'=>'ding'));
	// == test.php?id=12&newcat=ding
	// Example 3: url('test.php',array('arr'=>array(1=>'a',2=>'b')))
	// == test.php?arr[1]=a&arr[2]=b
	$query = '';
	// remove existing params in script url
	foreach	($params as	$k => $v) {
		$exp = sprintf('#(\?|&)%s=[^&]*#i',	$k);
		if (preg_match($exp, $url)) {
			$url = preg_replace($exp, '', $url);
		}
	}
	// repair url like 'script.php&id=12&asd=133'
	$exp = '#\?[a-z0-9_\-\[\]%]+=[^&]*#i';
	$exp2 =	'#&(\w+=[^&]*)#i';
	if (!preg_match($exp, $url) && preg_match($exp2, $url)) {
		$url = preg_replace($exp2, '?$1', $url, 1);
	}
	// passing array via url
	foreach ($params as $k => $v) {
		if (is_array($v)) {
			unset($params[$k]);
			foreach ($v as $k2 => $v2) {
				$params[$k.'['.$k2.']'] = $v2;
			}
		}
	}
	// build url
	foreach	($params as	$k => $v) {
		if (!strlen($v)) continue;
		if ($query)	{ $query .=	'&'; }
		else { $query .= (strpos($url,	'?') === false) ? '?' : '&'; }
		$query .= $k.'='.urlencode($v);
	}
	return $url.$query;
}

function get_table_data($config){
	if(!isset($config['fids']))return '';
	$listing = '';
	$listing .= '<table class="listingi"  border="0" cellspacing="1" cellpadding="0" style="width: 100%;">'; 
	$listing .= '<tr>';
	
	if(is_array($config['fids']))
	foreach ($config['fids'] as  $val) {
		$listing .= '<th class="listing_thi">';
		$listing .= $val;
		$listing .= '</th>';
	}
	$listing .= '</tr>';


	foreach ($config['rows'] as $val) {
	$listing .= '<tr>';
	foreach ($val as $v) {
		$listing .= '<td align="center">';
		$listing .= IS_GBK?iconv("gbk","utf-8",$v):$v;
		$listing .= '</td>';
	}
	$listing .= '</tr>';		
	}

	$listing .= '</table>';
	return $listing;
}
?>
<?php 
function ord_filter($ord, $ord_key, $fields, $default_field = null) {
 if (!$ord) { 
 	if ($default_field) { $ord = $default_field; } else { if (array_is_numeric($fields)) { $ord = array_first_value($fields); } else { $ord = array_first_key($fields); } } 
 }
 if (array_is_numeric($fields)) { foreach ($fields as $k => $v) { $fields[] = $v.'_desc'; } } else { foreach ($fields as $k => $v) { $fields[$k.'_desc'] = $v.' malej眂o'; } } if (array_is_numeric($fields)) { if (in_array($ord, $fields)) { $_GET[$ord_key] = $ord; return $ord; } else { $_GET[$ord_key] = null; return null; } } else { if (array_key_exists($ord, $fields)) { $_GET[$ord_key] = $ord; return $ord; } else { $_GET[$ord_key] = null; return null; } } 
}
 function str_starts_with($str, $start, $ignore_case = false) {
  if ($ignore_case) { $str = str_upper($str); $start = str_upper($start); } if (!strlen($str) && !strlen($start)) { return true; } if (!strlen($start)) { trigger_error('str_starts_with() failed, start arg cannot be empty', E_USER_ERROR); } if (strlen($start) > strlen($str)) { return false; } for ($i = 0; $i < strlen($start); $i++) { if ($start{$i} != $str{$i}) { return false; } } return true; 
} 
function str_ends_with($str, $end, $ignore_case = false) { if ($ignore_case) { $str = str_upper($str); $end = str_upper($end); } if (!strlen($str) && !strlen($end)) { return true; } if (!strlen($end)) { trigger_error('str_ends_with() failed, end arg cannot be empty', E_USER_ERROR); } if (strlen($end) > strlen($str)) { return false; } return str_starts_with(strrev($str), strrev($end)); return true; 
} 
function str_cut_start($str, $start) { 
if (str_starts_with($str, $start)) { $str = substr($str, strlen($start)); } return $str; }
function str_cut_end($str, $end) {
  if (str_ends_with($str, $end)) { $str = substr($str, 0, -strlen($end)); } return $str; }
function str_lower($str) { $lower = str_array(iso_chars_lower()); $upper = str_array(iso_chars_upper()); $str = str_replace($upper, $lower, $str); $str = strtolower($str); return $str; }
function str_upper($str) { $lower = str_array(iso_chars_lower()); $upper = str_array(iso_chars_upper()); $str = str_replace($lower, $upper, $str); $str = strtoupper($str); return $str; }
function str_array($str) { $arr = array(); for ($i = 0; $i < strlen($str); $i++) { $arr[$i] = $str{$i}; } return $arr; }
function iso_chars() { return iso_chars_lower().iso_chars_upper(); }
function iso_chars_lower() { return '辨瓿耋都?'; } function iso_chars_upper() { return '∑剩延Μ?'; } 
function array_sort($arr, $col_key) { if (is_array($col_key)) { foreach ($arr as $k => $v) { $arr[$k]['__array_sort'] = ''; foreach ($col_key as $col) { $arr[$k]['__array_sort'] .= $arr[$k][$col].'_'; } } $col_key = '__array_sort'; } uasort($arr, create_function('$a,$b', 'if (is_null($a["'.$col_key.'"]) && !is_null($b["'.$col_key.'"]))	return 1; if (!is_null($a["'.$col_key.'"]) && is_null($b["'.$col_key.'"])) return -1; return	strnatcasecmp($a["'.$col_key.'"], $b["'.$col_key.'"]);')); if ('__array_sort' == $col_key) { foreach ($arr as $k => $v) { unset($arr[$k]['__array_sort']); } } return $arr; }
function array_sort_desc($arr, $col_key) { if (is_array($col_key)) { foreach ($arr as $k => $v) { $arr[$k]['__array_sort'] = ''; foreach ($col_key as $col) { $arr[$k]['__array_sort'] .= $arr[$k][$col].'_'; } } $col_key = '__array_sort'; } uasort($arr, create_function('$a,$b', 'return strnatcasecmp($b["'.$col_key.'"],	$a["'.$col_key.'"]);')); if ('__array_sort' == $col_key) { foreach ($arr as $k => $v) { unset($arr[$k]['__array_sort']); } } return $arr; }
function array_sort_col($arr, $col_key) { uasort($arr, create_function('$a,$b', 'if (is_null($a["'.$col_key.'"]) && !is_null($b["'.$col_key.'"]))	return 1; if (!is_null($a["'.$col_key.'"]) && is_null($b["'.$col_key.'"])) return -1; return	strnatcasecmp($a["'.$col_key.'"], $b["'.$col_key.'"]);')); return $arr; }
function array_sort_desc_col($arr, $col_key) { uasort($arr, create_function('$a,$b', 'return strnatcasecmp($b["'.$col_key.'"],	$a["'.$col_key.'"]);')); return $arr; }
function array_is_numeric($arr) { foreach ($arr as $k => $v) { if (!preg_match('#^\d+$#', $k) || (strlen($k)>1 && str_starts_with($k,'0'))) { return false; } } return true; }
function array_first_value($arr) { $arr2 = $arr; return array_shift($arr2); } 
function array_first_key($arr) { $arr2 = $arr; reset($arr); list($key, $val) = each($arr); return $key; } 
function array_col_values_count($arr, $col) { $ret = array(); foreach ($arr as $k => $row) { $val = $row[$col]; if (isset($ret[$val])) { $ret[$val]++; } else { $ret[$val] = 1; } } ksort($ret); return $ret; }
function str_bind($s, $dat = array(), $strict = false, $recur = 0) { if (!is_array($dat)) { return trigger_error('str_bind() failed. Second	argument expects to	be an array.', E_USER_ERROR); } if ($strict) { foreach ($dat as $k => $v) { if (strpos($s, "%$k%") === false) { return trigger_error(sprintf('str_bind() failed. Strict	mode On. Key not found = %s. String	= %s. Data = %s.', $k, $s, print_r($dat, 1)), E_USER_ERROR); } $s = str_replace("%$k%", $v, $s); } if (preg_match('#%\w+%#', $s, $match)) { return trigger_error(sprintf('str_bind() failed. Unassigned	data for = %s. String =	%s.', $match[0], $sBase), E_USER_ERROR); } return $s; } $sBase = $s; preg_match_all('#%\w+%#', $s, $match); $keys = $match[0]; $num = array(); foreach ($keys as $key) { $key2 = str_replace('%', '', $key); if (is_numeric($key2)) $num[$key] = true; $val = $dat[$key2]; $s = str_replace($key, $val, $s); } if (count($num)) { if (count($dat) != count($num)) { return trigger_error('str_bind() failed. When using	numeric	data binding you need to use all data passed to	the	string.	You	also cannot	mix	numeric	and	name binding.', E_USER_ERROR); } } if (preg_match('#%\w+%#', $s, $match)) { } return $s; }
function str_has($str, $needle, $ignore_case = false) { if (is_array($str)) { foreach ($str as $s) { if (str_has($s, $needle, $ignore_case)) { return true; } } return false; } if (is_array($needle)) { foreach ($needle as $n) { if (!str_has($str, $n, $ignore_case)) { return false; } } return true; } if ($ignore_case) { $str = str_lower($str); $needle = str_lower($needle); } return strpos($str, $needle) !== false; }
function array_find_rows($arr, $row, $fetch_arr_col = false) { $ret = array(); foreach ($arr as $arr_k => $row2) { $has = true; foreach ($row as $k => $v) { if ((string)$row2[$k] != (string)$v) { $has = false; } } if ($has) { if ($fetch_arr_col) { if (array_is_numeric($row2[$fetch_arr_col])) { $ret = array_merge_omit($ret, $row2[$fetch_arr_col]); } else { $ret[$arr_k] = $row2; } } else { $ret[$arr_k] = $row2; } } } return $ret; }
function array_merge_omit($a1, $a2) { foreach ($a2 as $k => $v) { if (!array_key_exists($k, $a1)) { $a1[$k] = $v; } } return $a1; }
function str_truncate($string, $length, $etc = ' ..', $break_words = true) { if ($length == 0) { return ''; } if (strlen($string) > $length + strlen($etc)) { if (!$break_words) { $string = preg_replace('/\s+?(\S+)?$/', '', substr($string, 0, $length+1)); } return substr($string, 0, $length) . $etc; } return $string; }
function str_truncate_words($string, $length, $etc = ' ..') { return str_truncate($string, $length, $etc, $break_words = false); }


function listing($config) { global $_listing; global $_listing;
 if (!isset($_listing)) { $_listing = array( 'ofs_key' => 'ofs', 'ord_key' => 'ord', 'lang_found' => 'Found', 'lang_page' => 'Strona', 'lang_not_found' => 'No results found.', 'lang_prev' => '&lt;&lt; <span class="prev">Previous</span>', 'lang_next' => '<span class="next">Next</span> &gt;&gt;', 'lang_edit_th' => 'Edycja', 'lang_edit' => 'Edytuj', 'lang_del' => '', 'lang_del_confirm' => '', 'edit_width' => '' ); }
  $config = array_merge($_listing, $config);
  $config_example = array( 'cols' => array('Column 1', 'Column 2'), 'rows' => array(array('col1'=>'asd', 'col2'=>'asd')), 'row_func' => 'listing_news', 'pk' => 'id_col', 'edit' => 'edit.php?id=%id_col%', 'del' => 'del.php?id=%id_col%', 'count' => 123, 'limit' => 20, 'ofs' => 10, 'ofs_key' => 'ofs', 'ord_key' => 'ord', 'ord_fields' => array('field1', 'field2'), 'width' => '100%', 'edit_func' => '', 'head_func' => '' );
if (!isset($config['cols'])) { return trigger_error('listing() failed. config[cols] invalid.', E_USER_ERROR); }
if (!isset($config['rows']) || !is_array($config['rows'])) { return trigger_error('listing() failed. config[rows] invalid.', E_USER_ERROR); }
if (isset($config['row_func']) && !function_exists($config['row_func'])) { return trigger_error('listing() failed. config[row_func] invalid.', E_USER_ERROR); }
if ((isset($config['edit']) || isset($config['delete'])) && !isset($config['pk'])) { return trigger_error('listing failed(). config[pk] required.', E_USER_ERROR); }
if (isset($config['limit']) && !isset($config['count'])) { return trigger_error('listing() failed. config[limit] & config[count] both required.', E_USER_ERROR); }
if (isset($config['pk'])) { if (isset($config['edit'])) { if (strpos($config['edit'], '%'.$config['pk'].'%') === false) { return trigger_error("listing() failed. %{$config['pk']}% is missing from config[edit] url.", E_USER_ERROR); } } if (isset($config['del'])) { if (strpos($config['del'], '%'.$config['pk'].'%') === false) { return trigger_error("listing() failed. %{$config['pk']}% is missing from config[del] url.", E_USER_ERROR); } } }
if (!isset($config['count']) && !isset($config['limit'])) { $config['count'] = count($config['rows']); }
if (!isset($config['width'])) $config['width'] = ''; 
if (!isset($config['count'])) $config['count'] = null;
if (is_array($config['cols'])) { $config['colspan'] = count($config['cols']); } else { $config['colspan'] = preg_match_all('#<\s*th#i', $config['cols'], $match); }
if (isset($config['edit']) || isset($config['del']) || isset($config['edit_func'])) { $config['colspan'] += 1; }
foreach ($config['rows'] as $k => $row) { foreach ($row as $k2 => $v2) { if (!isset($config['row_func']) && date_valid($v2)) { $config['rows'][$k][$k2] = timestamp($v2); } } }
$listing = ''; 
if (isset($config['cmd'])) { ob_start(); listing_cmd_inc($config); $listing .= ob_get_contents(); ob_end_clean(); $config['head_func'] = 'listing_cmd_head'; $config['cols'] = array_merge(array(''=>'<a href="javascript:listing_cmd_select_all();" title="Zaznacz/Odznacz wszystkie">#</a>'), $config['cols']); }
if (!isset($config['head_func']) && !count($config['rows']) && !$config['count'])
return str_bind('<table class="listing" cellspacing="1"><tr><td class="none pager">%lang_not_found%</td></tr></table>', $config);
$listing .= str_bind('<table class="listing" width="%width%" cellspacing="1">', $config); 
if (isset($config['count'])) { if (isset($config['head_func'])) { ob_start(); call_user_func($config['head_func'], $config); $listing .= ob_get_contents(); ob_end_clean(); } else { $listing .= str_bind('<tr><td colspan="%colspan%" class="none pager">%lang_found%: <b>%count%</b></td></tr>', $config); } }
if (count($config['rows'])) { $listing .= '<tr>'; 
if (is_array($config['cols'])) {
 foreach ($config['cols'] as $k => $v)
  { 
  	if (isset($config['ord_fields'])) {
  	 if (array_is_numeric($config['ord_fields'])) {
  	  $field_exists = !is_numeric($k) && in_array($k, $config['ord_fields'], true); 
  	} else {
  	 $field_exists = array_key_exists($k, $config['ord_fields']); }
  	} $width = ''; 
  	if (preg_match('#<(\d+[%]?)>#', $v, $match)) {
  	 $v = str_replace($match[0], '', $v); 
  	 $v = trim($v); $width = $match[1]; } 
  	 if (isset($config['ord_fields']) && $field_exists && !str_has($v, '#') && $k) {
  	  $class = ''; $ord = @$_GET[$config['ord_key']];
  	   if ($ord == $k) $class = 'asc'; 
  	   else if ($ord == $k.'_desc') $class = 'desc'; $ord_key = $k; 
  	   if ($class == 'asc') $ord_key .= '_desc';
  	    $url = url($_SERVER['REQUEST_URI'], array( $config['ord_key'] => $ord_key, $config['ofs_key'] => '0' )); 
  	    $listing .= sprintf('<th class="listing_th" nowrap="nowrap" width="%s"><a class="%s" href="%s" title="排序: %s">%s</a></th>', $width, $class, $url, $v, $v); }
  	     else { if (preg_match('#<\s*a#i', $v)) { if (preg_match('#\w+:#', $k)) { $style = $k; } else { $style = ''; } 
  	     $listing .= sprintf('<th class="listing_th listing_a" width="%s" style="%s">%s</th>', $width, $style, $v); }
  	      else { $listing .= sprintf('<th class="listing_th" width="%s"><div>%s</div></th>', $width, $v); } } }
  }
 else { $listing .= $config['cols']; } 
 if (isset($config['edit']) || isset($config['del'])) { $listing .= str_bind('<th class="listing_th" style="width: %edit_width%;"><div>%lang_edit_th%</div></th>', $config); }
  $listing .= '</tr>'; }
ob_start(); $row_i = 0; 
foreach ($config['rows'] as $row) {
 $row_i++; if ($row_i > 2) $row_i = 1; 
 echo str_bind('<tr class="row%i%" onmouseover="this.className=\'row%i% over\';" onmouseout="this.className=\'row%i%\'">', array('i'=>$row_i));
  if (isset($config['cmd'])) { listing_cmd_checkbox($row, $config); }
   if (isset($config['row_func'])) {
    call_user_func($config['row_func'], $row, $config);
     }
    else {
     listing_row_func($row, $config); 
 } 
    if (isset($config['edit']) || isset($config['del'])) { 
    	if (!isset($row[$config['pk']])) {
    	 return trigger_error(sprintf('listing() failed. pk is missing from rows. pk = %s', $config['pk']), E_USER_ERROR); 
    	} 
    	echo '<td align="center">'; 
    	if (isset($config['edit_func'])) { 
    		call_user_func($config['edit_func'], $row, $config);
    		 } else {
    		  listing_edit_func($row, $config); 
    		} echo '</td>'; 
    	}
     echo '</tr>'; 
     if(isset($row['data']) && is_array($row['data']))
     {
     	
     	echo '<tr>';
     	echo '<td valign="top" colspan="6" class="nopadding"><div id="drow'.$row[$config['pk']].'" class="hide">'; 
     	echo get_table_data($row['data']);
     	echo '</div></td>'; 
     	echo '</tr>'; 
     }
 }
$listing .= ob_get_contents();
$listing = str_replace('<td></td>', '<td>&nbsp;</td>', $listing);
ob_end_clean();
if (isset($config['count']) && isset($config['limit'])) { $config['bottom'] = true; $pager = listing_pager($config); if ($pager) { $listing .= str_bind('<tr><td colspan="%colspan%" class="none pager">', $config); $listing .= $pager; $listing .= '</td></tr>'; } }
$listing .= sprintf('</table>'); return $listing; }
function listing_cmd_inc(&$config) {
 $cmd =& $config['cmd']; 
 $cmd_defaults = array( 'do' => array('action_1'=>'Akcja 1', 'action_2'=>'Akcja 2'), 'do_group' => null, 'do_name' => 'do', 'rows_name' => 'cmd_rows', 'form_action' => null, 'lang_no_cmd_selected' => '', 'lang_no_rows_selected' => 'Nie', 'lang_cmd' => 'Wykonaj polecenie', 'lang_cmd_button' => 'wykonaj' );
  foreach ($cmd_defaults as $k => $v) 
  	{ 
  		if (!isset($cmd[$k])) { $cmd[$k] = $v; } 
  	}
   foreach ($cmd as $k => $v) 
   	{ 
   		if (str_starts_with($k, 'lang')) { $cmd[$k] = str_replace("'", '"', $v); }
    } 
?>

    <script>
    function listing_cmd_submit(form)
    {
        if (listing_cmd_checked_count() && form['<?php echo $cmd['do_name'];?>'].value.length) {
            return true;
        } else {
            if (!form['<?php echo $cmd['do_name'];?>'].value.length) {
                alert('<?php echo $cmd['lang_no_cmd_selected'];?>');
            } else {
                alert('<?php echo $cmd['lang_no_rows_selected'];?>');
            }
            button_clear(form);
            return false;
        }
    }
    function listing_cmd_checked_count()
    {
        var count = 0;
        <?php foreach ($config['rows'] as $row): ?>
            if (document.forms['listing_cmd']['<?php echo $cmd['rows_name'];?>[<?php echo $row[$config['pk']];?>]'].value != '0') {
                count++;
            }
        <?php endforeach; ?>
        return count;
    }
    function listing_cmd_checkbox(input)
    {
        if (input.checked) {
            document.forms['listing_cmd']['<?php echo $cmd['rows_name'];?>['+input.value+']'].value = input.value;
        } else {
            document.forms['listing_cmd']['<?php echo $cmd['rows_name'];?>['+input.value+']'].value = '0';
        }
    }
    function listing_cmd_select_all()
    {
        <?php foreach ($config['rows'] as $row): ?>
            $('<?php echo $cmd['rows_name'];?>_emu_<?php echo $row[$config['pk']];?>').click();
        <?php endforeach; ?>
    }
    function listing_cmd_clear()
    {
        <?php foreach ($config['rows'] as $row): ?>
            $('<?php echo $cmd['rows_name'];?>_emu_<?php echo $row[$config['pk']];?>').checked = false;
        <?php endforeach; ?>
    }
    on_load(listing_cmd_clear);

    </script>

    <?php
} 
function listing_cmd_head($config) { $cmd =& $config['cmd']; ?>
        <tr><td colspan="<?php echo $config['colspan'];?>" class="none pager" style="padding: 0; padding-top: 0.5em; padding-bottom: 0.2em;">
            <form action="<?php echo $cmd['form_action']?$cmd['form_action']:self();?>" method="post" onsubmit="return listing_cmd_submit(this);" name="listing_cmd">
                <?php foreach ($config['rows'] as $row): ?>
                    <input type="hidden" name="<?php echo $cmd['rows_name'];?>[<?php echo $row[$config['pk']];?>]" value="0">
                <?php endforeach; ?>
                <table cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <?php echo $config['lang_found'];?>: <b><?php echo $config['count'];?></b>
                    </td>
                    <td nowrap>
                        &nbsp;&nbsp;/&nbsp;&nbsp; <?php echo $cmd['lang_cmd'];?>:&nbsp;
                    </td>
                    <td style="padding-bottom: 0.2em;">
                        <select name="<?php echo $cmd['do_name'];?>" style="">
                            <option value=""></option>
                            <?php if ($cmd['do_group']): ?>
                                <?php foreach ($cmd['do_group'] as $name => $do): ?>
                                    <optgroup label="<?php echo html_once($name);?>">
                                        <?php echo options($do);?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php echo options($cmd['do']);?>
                            <?php endif; ?>
                        </select>
                        <input wait="1" class="button_long" type="submit" value="<?php echo $cmd['lang_cmd_button'];?>">
                    </td>

                </tr>
                </table>
            </form>
        </td></tr>
    <?php
} 
function listing_cmd_checkbox($row, $config) { $cmd = $config['cmd']; ?>
    <td style="padding: 0 0.1em; width: 0;" align="center">
        <input type="checkbox" id="<?php echo $cmd['rows_name'];?>_emu_<?php echo $row[$config['pk']];?>" name="<?php echo $cmd['rows_name'];?>_emu_<?php echo $row[$config['pk']];?>" value="<?php echo $row[$config['pk']];?>" onclick="listing_cmd_checkbox(this);">
    </td>
    <?php
} 
function listing_head_func($config) { } 
function listing_row_func($row, $config) {
	 foreach ($config['cols'] as $k => $v) {
			  if (!array_key_exists($k, $row)) {
			   return trigger_error(sprintf('listing_row_func() failed. row[%s] is missing.', $k), E_USER_ERROR); 
			}
			    if (preg_match('#\d{4,4}-\d{2,2}-\d{2,2} \d{2,2}:\d{2,2}#', $row[$k])) {
			     printf('<td align="center">%s</td>', $row[$k]); 
			 } else {
			      printf('<td>%s</td>', $row[$k] ? $row[$k] : '&nbsp;'); 
			  } 
	} 
}
function listing_edit_func($row, $config) { if (isset($config['edit'])) { if (isset($config['edit_check_col']) && !$row[$config['edit_check_col']]) { echo '&nbsp;'; } else { $config['tmp_id'] = $row[$config['pk']]; $config['tmp_edit'] = str_replace('%'.$config['pk'].'%', $config['tmp_id'], $config['edit']); echo str_bind('<a href="%tmp_edit%">%lang_edit%</a>', $config); if (isset($config['del'])) echo '&nbsp;|&nbsp;'; } } if (isset($config['del'])) { $config['tmp_id'] = $row[$config['pk']]; $config['tmp_del'] = str_replace('%'.$config['pk'].'%', $config['tmp_id'], $config['del']); echo str_bind('<form action="%tmp_del%" method="post" style="display: inline;" name="del_%pk%_%tmp_id%">', $config); $data = array_merge($row, $config); $config['tmp_lang_del_confirm'] = str_bind($config['lang_del_confirm'], $data); $config['tmp_lang_del_confirm'] = str_replace(array("'", '"'), array("\'", "\'"), $config['tmp_lang_del_confirm']); echo str_bind('<a href="javascript:document.forms[\'del_%pk%_%tmp_id%\'].submit();" onclick="return confirm(\'%tmp_lang_del_confirm%\');">%lang_del%</a>', $config); echo '</form>'; } }
function listing_pager($config) { global $_listing; $config_example = array( 'count' => 123, 'limit' => 20, 'ofs' => 10, 'ofs_key' => 'ofs' ); $config = array_merge($_listing, $config); if (!isset($config['count'])) { return trigger_error('listing_pager() failed. config[count] required.', E_USER_ERROR); } if (!isset($config['limit'])) { return trigger_error('listing_pager() failed. config[limit] required.', E_USER_ERROR); } $ofs = (int) @$_GET[$config['ofs_key']]; if ($ofs < 0) return trigger_error('listing_pager() failed. ofs < 0', E_USER_ERROR); $page = floor($ofs / $config['limit'] + 1); $pages = ceil($config['count'] / $config['limit']); $url = $_SERVER['REQUEST_URI']; if ($ofs && $ofs >= $config['count']) { $tmp_ofs = $config['count']-$config['limit']; if ($tmp_ofs < 0) $tmp_ofs = 0; redirect(url(url_current(), array($config['ofs_key']=>$tmp_ofs))); } else if ($ofs && $ofs % $config['limit'] != 0) { $tmp_ofs = $ofs - ($ofs % $config['limit']); if ($tmp_ofs < 0) $tmp_ofs = 0; redirect(url(url_current(), array($config['ofs_key']=>$tmp_ofs))); } if ($ofs && $ofs == $config['count']) { $page++; } if ($pages <= 1) { if (isset($config['bottom'])) { if ($ofs && $ofs >= $config['count']) { } else { return ''; } } else return str_bind('%lang_found%: <b>%count%</b>', $config); } $pager = str_bind('<div style="float: left; white-space: nowrap;">'); if ($page > 1) { $tmp_ofs = $ofs - $config['limit']; if ($tmp_ofs < 0) $tmp_ofs = 0; $config['tmp_url'] = url($url, array($config['ofs_key'] => $tmp_ofs)); $pager .= str_bind('<a href="%tmp_url%">%lang_prev%</a>', $config); } else { $pager .= $config['lang_prev']; } $pager .= " &nbsp;[Strona $page z $pages]&nbsp; "; if ($page < $pages) { $tmp_ofs = $ofs + $config['limit']; if ($tmp_ofs < 0) $tmp_ofs = 0; $config['tmp_url'] = url($url,array($config['ofs_key']=>$tmp_ofs)); $pager .= str_bind('<a href="%tmp_url%">%lang_next%</a>', $config); } else { $pager .= $config['lang_next']; } $pager .= '</div><br style="clear: both;">'; return $pager; }
function listing_google() { } 
function listing_ofs($key) { if (isset($_GET[$key])) { $_GET[$key] = (int) $_GET[$key]; if ($_GET[$key] < 0) $_GET[$key] = 0; } else { $_GET[$key] = 0; } }
   ?>