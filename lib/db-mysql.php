<?php
/*
	Database abstraction library for mysql.
	Author: zhushengwen [zhushengwenzsw@126.com]
*/

$_SERVER['FB_DATA'] = array(
	'debug_file' => '',
	'debug_queries' => array(),
	'debug_count' => 0,
	'debug_time' => 0,
);
if (!defined('DB_DETECT_MISSING_WHERE')) define('DB_DETECT_MISSING_WHERE',0);
if (!defined('DB_DETECT_INJECTION')) define('DB_DETECT_INJECTION',0);
if (!defined('DEBUG_FDB')) define('DEBUG_FDB',0);
if (!defined('DEBUG_FDB_FILE')) define('DEBUG_FDB_FILE',0);

if (!extension_loaded('mysql')) {
	trigger_error('mysql extension not loaded', E_USER_ERROR);
}

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
function db_add_sql($sql,$time,$data){
	
	if (DEBUG_FDB) {
		//$microstart = microtime(true);if(function_exists('fb_sql'))fb_sql($sql,microtime(true)-$microstart,$result);
			$rows = array();
			$fids = array();
			if($data) 
			{
				$fids = array_keys($data[0]);
				foreach ($data as $key => $value) {
					$d=array();
					foreach ($value as $k => $v) {
						$d[] = $v;
					}
					$rows[] = $d;
				}
				$data = array('fids'=>$fids,'rows'=>$rows);
			}

		$_SERVER['FB_DATA']['record']['debug_queries'][] = array('query'=>$sql, 'time'=>$time ,'seq'=>$_SERVER['FB_DATA']['debug_count']+1,'data' => $data?json_encode($data):'');
		$_SERVER['FB_DATA']['debug_count']++; $_SERVER['FB_DATA']['debug_time'] += $time;
	}
}
function db_query($query,$con,$mysqli=false)
{
	

	if (DEBUG_FDB) $microstart = microtime(true);
	if($mysqli)	$result = $_SERVER['mysqli_query']($con,$query);
    else $result = $con?$_SERVER['mysql_query']($query,$con):$_SERVER['mysql_query']($query);
    
	if (DEBUG_FDB) {
		$time = microtime(true)-$microstart;
		$_SERVER['FB_DATA']['record']['debug_queries'][] = array('query'=>$query, 'time'=>$time ,'seq'=>$_SERVER['FB_DATA']['debug_count']+1,'data' => json_encode((is_resource($result)||is_object($result))?db_result($result,$mysqli):''));
		$_SERVER['FB_DATA']['debug_count']++; $_SERVER['FB_DATA']['debug_time'] += $time;
	}
	return $result;
}

function db_result($result,$mysqli=false)
{
	$fids = array();
	$rows = array();
	while ($property = $mysqli?mysqli_fetch_field($result):mysql_fetch_field($result)) $fids[] = $property->name;
	while ($row = $mysqli?mysqli_fetch_row($result):mysql_fetch_row($result)) $rows[] = $row;
	if($fids)@$mysqli?mysqli_field_seek($result, 0):mysql_field_seek($result, 0);
	if($rows)@$mysqli?mysqli_data_seek($result, 0):mysql_data_seek($result, 0);
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




?>