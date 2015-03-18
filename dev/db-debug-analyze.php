<?php

/*
	Debugging sql queries.
	(c) Cezary Tomczak [www.gosu.pl]
*/

//exit('ANALYZE todo');

if (0 == 1) {
	trigger_error('permission denied', E_USER_ERROR);
}

$get = (array(
	'id' => $_GET['id']
));
$post = (array(
	'query' => $_POST['query']
));

if (!preg_match('#^\d+\.\d+$#', $get['id'])) {
//	trigger_error('Invalid dbg id', E_USER_ERROR);
}
$file = sprintf(DEBUG_TEMP.'/%s.ser', $get['id']);

if (!file_exists($file)) {
	//trigger_error('Dbg file not found', E_USER_ERROR);
}

$post['query'] = ($post['query']);

if (!preg_match('#^\s*SELECT#i', $post['query'])) {
	trigger_error('Invalid query', E_USER_ERROR);
}
db_connect(array(
	'host' => 'localhost',
	'user' => 'root',
	'pass' => 'admin',
	'dbname' => 'test',
	'charset' => 'utf8'
));

$rows = db_list('EXPLAIN '.$post['query']);

function query_color($query)
{
	$words = array('SELECT', 'UPDATE', 'DELETE', 'FROM', 'LIMIT', 'OFFSET', 'AND', 'LEFT JOIN', 'WHERE', 'SET',
		'ORDER BY', 'GROUP BY', 'GROUP', 'DISTINCT', 'COUNT', 'COUNT\(\*\)', 'IS', 'NULL', 'IS NULL', 'AS', 'ON', 'INSERT INTO', 'VALUES', 'BEGIN', 'COMMIT', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'IN', 'NOT');
	$words = implode('|', $words);

	$query = preg_replace("#^({$words})(\s)#i", '<font color="blue">$1</font>$2', $query);
	$query = preg_replace("#(\s)({$words})$#i", '$1<font color="blue">$2</font>', $query);
	// replace twice, some words when preceding other are not replaced
	$query = preg_replace("#(\s)({$words})(\s)#i", '$1<font color="blue">$2</font>$3', $query);
	$query = preg_replace("#(\s)({$words})(\s)#i", '$1<font color="blue">$2</font>$3', $query);
	$query = preg_replace("#^($words)$#i", '<font color="blue">$1</font>', $query);

	return $query;
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
	</style>
</head>
<body>
<h1>Analiza zapytania</h1>

<div class="row1" style="padding: 0.5em;"><?=nl2br(query_color(($post['query'])));?></div>

<h2>Wynik</h2>

<table class="ls2">
<?
	if (true) {
		$rows = $rows[0];
	}
?>
<? foreach ($rows as $k => $v):
	if (is_array($v)) {
		if (count($v)>1) {
			trigger_error('count(v)>1', E_USER_ERROR);
		}
		foreach ($v as $k2 => $v2) {
			$k = $k2;
			$v = $v2;
		}
	}
?>
	<tr>
		<th><?=($k);?></th>
		<td><?=($v);?></td>
	</tr>
<? endforeach; ?>
</table>

<p style="margin-bottom: 1em;">
	<a href="javascript:history.go(-1)">&lt;&lt; goback</a>
</p>
</body>
</html>