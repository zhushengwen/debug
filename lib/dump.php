<?php
// PHP debug tools - http://www.gosu.pl/debug/
// Author: Cezary Tomczak [cagret at gmail.com]

function dump()
{
	global $__dump_tofile;

	if ($__dump_tofile) {
		$trace =& $__dump_tofile;
	} else {
		$trace = debug_backtrace();
		if (!defined('DEBUG_CONSOLE_HIDE')) {
			define('DEBUG_CONSOLE_HIDE', 1);
		}
	}

	$args = func_get_args();
	if (1 == count($args)) {
		$args = $args[0];
	}

	dump_ob_cleanup();

	if ($__dump_tofile) {
		ob_start();
	}

	foreach ($trace as $k => $trv) {
		if (!isset($trv['file'])) {
			$trace[$k]['file'] = 'unknown';
		}
		if (!isset($trv['line'])) {
			$trace[$k]['line'] = '0';
		}
	}

	$srcargs = '()';
	foreach ($trace as $k => $trv) {
		$file = $trv['file'];
		$line = $trv['line'];
		if (is_file($file) && $line) {
			$lines = file($file);
			if (!isset($lines[$line-1])) {
				break;
			}
			$line = $lines[$line-1];
			unset($lines);
			if (preg_match('#\bdump(\_tofile)?\s*\(([\s\S]+)\)\s*;#i', $line, $pmatch)) {
				$srcargs = sprintf($pmatch[1].'(%s)', dump_ehtml($pmatch[2]));
			}
		}
		break;
	}

	$trfiles = array();
	foreach ($trace as $k => $trv) {
		$trfiles[$k] = dump_shortpath($trv['file']);
	}
	$cpath = dump_commonpath($trfiles);
	foreach ($trfiles as $k => $file) {
		$file = preg_replace('#^'.preg_quote($cpath).'#', '', $file);
		if (isset($file[0]) && $file[0] != '/') {
			$file = '/'.$file;
		}
		$trfiles[$k] = $file;
	}
	foreach ($trace as $k => $trv) {
		$trace[$k]['file'] = $trfiles[$k];
	}

	$count = count($trace);
	$no = $count;
	$trfirst = '';
	$trhtml = '';
	foreach ($trace as $trv) {
		$fnc = '<b>'.$trv['function'].'</b>';
		$cls = isset($trv['class']) ? $trv['class'] : '';
		if ($cls) {
			$cls = '<b>'.$cls.'</b>';
			$cls .= '.';
		}
		$file = $trv['file'];
		$tmpfile = basename($file);
		$tmpdir = str_replace($tmpfile, '', $file);
		$file = '<span class="dir">'.$tmpdir.'</span><span class="file">'.$tmpfile.'</span>';
		$line = $trv['line'];
		if ($trfirst) {
			$tmpno = str_repeat('0', strlen($count)-strlen($no)) . $no;
			$trhtml .= sprintf('<div>%s. %s%s() <u>in</u> %s <u>on line</u> <i>%s</i></div>', $tmpno, $cls, $fnc, $file, $line);
		} else {
			$trfirst = sprintf('<div><u>in</u> %s <u>on line</u> <i>%s</i></div>', $file, $line);
		}		
		$no--;
	}

	$title = sprintf('dump%s %s', $srcargs, $trfirst);

	$pre = '';
	if (count($args)) {
		$pre .= '<pre>';
		ob_start();
		var_dump($args);
		$cont = ob_get_clean();
		$replace = array(
			'#([\r\n][ \t]+\[[^\[\]]+\])=>\s+#' => '\\1 => ',
			'#\{\s+\}#' => '{}',
			'#\["([^"]*)"]#' => '[\\1]',
			'#(\&?)string\(\d+\)\s+"([^"]*)"#' => '"\\2" (\\1string)',
			'#(\&?)int\((-?\d+)\)#' => '"\\2" (\\1int)',
			'#(\&?)float\((-?\d+(\.\d+)?)\)#' => '"\\2" (\\1float)',
			'#(\&?)bool\(true\)#' => '"1" (\\1bool)',
			'#(\&?)bool\(false\)#' => '"0" (\\1bool)',
			'#(\s+=>\s+)(\&?)NULL(\s+)#'=> '\\1"" (\\2null)\\3',
		);
		$cont = preg_replace(array_keys($replace), array_values($replace), $cont);
		$cont = preg_replace('#([\r\n][^\r\n]{145})[^\r\n]+#', '\\1...', $cont);
		$cont =  dump_ehtml($cont);
		$replace = array(
			'&int' => '&amp;int',
		);
		$cont = str_replace(array_keys($replace), array_values($replace), $cont);
		$pre .= $cont;
		$pre .= '</pre>';
	} else {
		$pre .= 'No arguments.';
	}

	echo '<html><head>';
	printf('<title>%s</title>', strip_tags($title));
	printf('<meta http-equiv="Content-Type" content="text/html;charset=%s">', dump_charset($pre));
	echo '<style type="text/css">';
	echo 'body, pre, h1, h2 { font-family: Consolas, Courier New; font-size: 12px; margin: 0.5em; padding: 0em; } ';
	echo 'h1 { font-size: 18px; } h2 { font-size: 16px; } h1, h2 { margin: 0.5em 0em; }';
	echo 'h1 div { display: inline; font-size: 12px; font-weight: normal; margin-left: -0.5em; }';
	echo 'div { }';
	echo '.backtrace, .includes { max-height: 138px; overflow: auto; }';
	echo 'pre { max-height: 334px; overflow: auto; margin: 0em; }';
	echo 'pre, .backtrace, .includes { padding: 0.25em 0.5em; background: #f3f3f3; border: #ddd 1px solid; }';
	echo 'b { color: #0000BB; font-weight: normal; }';
	echo 'u { color: #007700; text-decoration: none; }';
	echo 'i { color: #000; font-weight: bold; font-style: normal; }';
	echo 'span.dir {} span.file { color: #000; font-weight: bold; }';
	echo 'span.includes-dir {} span.includes-file {}';
	echo '</style>';
	echo '</head><body>';

	echo sprintf('<h1>%s</h1>', $title);
	echo $pre;	
	
	echo '<h2>Backtrace</h2>';
	if (count($trace) > 1) {
		echo '<div class="backtrace">';
		echo $trhtml;
		echo '</div>';
	} else {
		echo 'No backtrace.';
	}	

	echo '<h2>Includes</h2>';
	$includes = get_included_files();
	foreach ($includes as $k => $file) {
		$includes[$k] = dump_shortpath($file);		
	}

	// remove debug.php & auto_prepend.php
	$includes2 = array();
	foreach ($includes as $k => $file) {
		$arr = array('/debug/lib/debug.php', '/debug/lib/dump.php', '/debug/auto_prepend.php', '/services/auto_prepend.php');
		$found = false;
		foreach ($arr as $f) {
			if (strstr($file, $f)) {
				$found = true;
				break;
			}
		}
		if ($found) {
			$includes2[] = $file;
			unset($includes[$k]);
		}
	}

	if (!count($includes)) {
		echo 'No includes.';
	}

	$cpath = dump_commonpath($includes);
	foreach ($includes as $k => $file) {
		$file = preg_replace('#^'.preg_quote($cpath).'#', '', $file);
		if ($file[0] != '/') {
			$file = '/'.$file;
		}
		$includes[$k] = $file;
	}
	$includes = array_reverse($includes);

	$count = count($includes);
	$no = $count;
	if ($no) {
		echo '<div class="includes">';
	}
	
	foreach ($includes as $file) {
		$tmpno = str_repeat('0', strlen($count)-strlen($no)) . $no;
		$tmpfile = basename($file);
		$tmpdir = str_replace($tmpfile, '', $file);
		$file = '<span class="includes-dir">'.$tmpdir.'</span><span class="includes-file">'.$tmpfile.'</span>';		
		printf('<div>%s. %s', $tmpno, $file);
		$no--;
	}
	if ($no) {
		echo '</div>';
	}	

	echo '</body></html>';

	if ($__dump_tofile) {
		return ob_get_clean();
	} else {
		exit();
	}	
}
function dump_tofile($file)
{
	global $__dump_tofile;
	$__dump_tofile = debug_backtrace();
	$args = func_get_args();
	unset($args[0]);
	if (1 == count($args)) {
		$args = $args[1];
	}
	$dumphtml = dump($args);
	$__dump_tofile = null; // unset() won't work! it just deletes the reference.
	file_put_contents($file, $dumphtml);
}
function dump_shortpath($file)
{
	$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
	$file = str_replace('\\', '/', $file);
	if ('/' == substr($root,-1) && strlen($root) > 1) {
		$root = substr($root,0,-1);
	}
	$file = str_replace($root, '', $file);
	$file = preg_replace('/[a-z]:/i', '', $file);
	return $file; // file short path
}
function dump_commonpath($files)
{
	if (!count($files)) {
		return '';
	}
	$cpath = '';
	$i = 0;
	while (true) {
		$char = null;
		foreach ($files as $file) {
			if (!isset($file[$i])) {
				break 2;
			}
			if (isset($char)) {
				if ($file[$i] != $char) {
					break 2;
				}
			} else {
				$char = $file[$i];
			}			
		}
		$cpath .= $char;
		$i++;
	}
	if ('/' != substr($cpath, -1)) {
		$tmpfile = basename($cpath);
		$cpath = preg_replace('#'.preg_quote($tmpfile).'$#', '', $cpath);
	}
	return $cpath;
}
function dump_ehtml($s)
{
	// Succession of str_replace array is important - double escape bug.
	static $replace = array(
		'&amp;' => '&amp;amp;',
		'&lt;' => '&amp;lt;',
		'&gt;' => '&amp;gt;',
		'&quot;' => '&amp;quot;',
		'&#34;' => '&amp;#34;',
		'&#034;' => '&amp;#034',
		'&#39;' => '&amp;#39;',
		'&#039' => '&amp;#039;',
		'<' => '&lt;',
		'>' => '&gt;',
		'"' => '&quot;',
		'\'' => '&#39;',
	);
	static $keys;
	static $values;
	if (!isset($keys)) {
		$keys = array_keys($replace);
	}
	static $values;
	if (!isset($values)) {
		$values = array_values($replace);	
	}
	return str_replace($keys, $values, $s);
}
function dump_ob_cleanup()
{
	if (headers_sent()) { return; }
	while (ob_get_level()) { ob_end_clean(); }
	foreach (headers_list() as $header) {
		if (preg_match('/Content-Encoding:/i', $header)) {
			header('Content-encoding: none');
			return;
		}
	}
}
function dump_charset($string)
{
	$iso88592_pattern = "#[\xb1\xe6\xea\xb3\xf1\xf3\xb6\xbc\xbf\xa1\xc6\xca\xa3\xd1\xd3\xa6\xac\xaf]#";
	$win1250_pattern = "#[\xb9\xe6\xea\xb3\xf1\xf3\x9c\x9f\xbf\xa5\xc6\xca\xa3\xd1\xd3\x8c\x8f\xaf]#";
	$charset = 'utf-8';
	if (preg_match($iso88592_pattern, $string)) {
		#$charset = 'iso-8859-2';
	} else if  (preg_match($win1250_pattern, $string)) {
		#$charset = 'windows-1250';
	}
	return $charset;
}
function dump_test()
{
	$int = 1;
	$intb = -1;
	$float = 2.0;
	$floatb = -2.1;
	$string = "asd";
	$true = true;
	$false = false;
	$null = null;
	$test = array();
	$test['int'] = $int;
	$test['int2'] =& $int;
	$test['intb'] = $intb;
	$test['intb2'] =& $intb;
	$test['float'] = $float;
	$test['float2'] =& $float;
	$test['floatb'] = $floatb;
	$test['floatb2'] =& $floatb;
	$test['string'] = $string;
	$test['string2'] =& $string;
	$test['true'] = $true;
	$test['true2'] =& $true;
	$test['false'] = $false;
	$test['false2'] =& $false;
	$test['null'] = $null;
	$test['null2'] =& $null;
	dump($test);
}

?>