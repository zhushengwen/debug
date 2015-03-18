<?php
/*
	Database abstraction library for mysql.
	Author: Cezary Tomczak [www.gosu.pl]
*/

global $_db;
$_db = array(
	'link' => null,
	'dbname' => null,
	'transaction_level' => 0,
	'debug_file' => '',
	'debug_queries' => array(),
	'debug_count' => null,
	'debug_time' => null,
	'mysql_query'=>'mysql_query',
);

if (!defined('DB_DETECT_MISSING_WHERE')) define('DB_DETECT_MISSING_WHERE',0);
if (!defined('DB_DETECT_INJECTION')) define('DB_DETECT_INJECTION',0);
if (!defined('DB_DEBUG')) define('DB_DEBUG',0);
if (!defined('DB_DEBUG_FILE')) define('DB_DEBUG_FILE',0);

if (!extension_loaded('mysql')) {
	trigger_error('mysql extension not loaded', E_USER_ERROR);
}
register_shutdown_function('db_cleanup');

if (ini_get('magic_quotes_gpc')) {
	ini_set('magic_quotes_runtime', 0);
	array_walk_recursive($_GET, 'db_magic_quotes_gpc');
	array_walk_recursive($_POST, 'db_magic_quotes_gpc');
	array_walk_recursive($_COOKIE, 'db_magic_quotes_gpc');
}
if (DB_DETECT_INJECTION) {
	array_walk_recursive($_GET, 'db_detect_injection_gpc');
	array_walk_recursive($_POST, 'db_detect_injection_gpc');
	array_walk_recursive($_COOKIE, 'db_detect_injection_gpc');
}

// -------- polaczenie + wybor bazy


// -------- podstawowe funkcje: db_query() db_one() db_row() db_list() db_assoc()
function db_empty($id,$sql){}
function db_query($query,$con)
{
	global $_db;

	if (DB_DETECT_MISSING_WHERE) db_detect_missing_where($query);
	if (DB_DETECT_INJECTION) db_detect_injection($query);
	if (DB_DEBUG) $microstart = microtime(true);
    $result = $con?$_db['mysql_query']($query, $con):$_db['mysql_query']($query);
	if (DB_DEBUG) {
		$time = microtime(true)-$microstart;
		$_db['debug_queries'][] = array('query'=>$query, 'time'=>$time ,'seq'=>$_db['debug_count']+1,'data' => json_encode(is_resource($result)?db_result($result):''));
		$_db['debug_count']++; $_db['debug_time'] += $time;
	}
	return $result;
}

function db_result($result)
{
	$rows = array();
	$fids = array();
	while ($property = mysql_fetch_field($result)) $fids[] = $property->name;
	while ($row = mysql_fetch_row($result)) $rows[] = $row;
	if($rows)@mysql_data_seek($result, 0);
	return array('fids'=>$fids,'rows'=>$rows);
}


// -------- wykrywanie: brakujacego where w update/delete, sql injection

function db_detect_missing_where($query)
{
	if (preg_match('/^\s*(update|delete)/i',$query) && !stristr($query,'where')) {
		trigger_error("Detected missing 'where' condition. Query: $query", E_USER_ERROR);
	}
}
function db_detect_injection($query)
{
	$inside_quote = false;
	$query = '';
	$query = str_replace('\\\\', '', $query);
	$query_len = strlen($query);
	for ($i = 0; $i < $query_len; $i++) {
		$prev_char = isset($query{$i-1}) ? $query{$i-1} : null;
		$char = $query{$i};
		if ($char == "'") {
			if ($inside_quote) {
				if ($prev_char != '\\') {
					$inside_quote = false;
					continue;
				}
			} else {
				$inside_quote = true;
			}
		}
		if (!$inside_quote) $query .= $char;
	}
	if (strstr($query, '--') || strstr($query, '#')
		|| strstr($query, '/*') || strstr($query, '0x')) {
		trigger_error("Detected sql injection. Query: $query", E_USER_ERROR);
	}
}
function db_detect_injection_gpc($val)
{
	if (stristr($val,'union') && stristr($val,'select')) {
		trigger_error("Detected sql injection. GPC: $val", E_USER_ERROR);
	}
}

// -------- magic quotes

function db_magic_quotes_gpc(&$val)
{
	$val = stripslashes($val);
}

// -------- inicjalizacja / porzadki po zakonczeniu wykonywania skryptu


function db_cleanup()
{
	static $called = false;
	if ($called) return;
	else $called = true;
	global $_db;
	if (DB_DEBUG && DB_DEBUG_FILE) {
		file_put_contents(DB_DEBUG_FILE, serialize($_db['debug_queries']));
	}
}

?>