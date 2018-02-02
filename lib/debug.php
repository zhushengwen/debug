<?php

// Author: zhushengwen [zhushengwenzsw@126.com]

ini_set('display_errors', true);
ini_set('log_errors', 1);
ini_set('error_log', DEBUG_TEMP.'/!phperror.log');
//ini_set('html_errors', 0);
ini_set('date.timezone', 'Asia/Shanghai');
register_shutdown_function(function(){register_shutdown_function('data_cleanup');});
if(FB_DEBUG_ERROR)
{
	$_SERVER['FB_DEBUG_ERROR']='debug_error';
	set_error_handler($_SERVER['FB_DEBUG_ERROR']);
}
function debug_record()
{
	foreach($GLOBALS as $k => $v)
	{
	    if($k != 'GLOBALS')
	    {
	        $re['$'.$k]=$v;
	    }
	}
}
function debug_error($error_no, $error_msg, $error_file, $error_line, $error_context)
{
	// check with error_reporting, if statement is preceded with @ we have to ignore it
	if (!($error_no & error_reporting())) return;

	$trace = debug_backtrace();
	if (isset($trace[0]) && isset($trace[0]['function']) && 'debug_error' == $trace[0]['function']) {
		array_shift($trace);
	}
	if (isset($trace[0]) && isset($trace[0]['file']) && isset($trace[0]['line']) && $trace[0]['file'] == $error_file && $trace[0]['line'] == $error_line) {
		// base error_file & error_line already in backtrace
	} else {
		$trace = array_merge(array(array('file'=>$error_file, 'line'=>$error_line)), $trace);
	}

	// register_shutdown_function() bug, after exit on php5 win32, shutdown function is not called
	if (function_exists('data_cleanup')) data_cleanup();

	// Use output buffering in your controller, so we can cancel any content already generated.
	debug_ob_cleanup();

	if (!headers_sent()) {
		// Send 503 http code so search engines do not crawl that broken page
		header('HTTP/1.0 503 Service Unavailable');
	}

	$error_types = array (
		E_ERROR => 'E_ERROR',
		E_WARNING => 'E_WARNING',
		E_PARSE => 'E_PARSE',
		E_NOTICE => 'E_NOTICE',
		E_CORE_ERROR => 'E_CORE_ERROR',
		E_CORE_WARNING => 'E_CORE_WARNING',
		E_COMPILE_ERROR => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING => 'E_COMPILE_WARNING',
		E_USER_ERROR => 'E_USER_ERROR',
		E_USER_WARNING => 'E_USER_WARNING',
		E_USER_NOTICE => 'E_USER_NOTICE',
		E_STRICT => 'E_STRICT',
		4096 => 'E_RECOVERABLE_ERROR', // since PHP 5.2.0
		8192 => 'E_DEPRECATED', // since PHP 5.3.0
		16384 => 'E_USER_DEPRECATED' // since PHP 5.3.0
	);

	$error_type = isset($error_types[$error_no]) ? $error_types[$error_no] : $error_no;

	$error = array();
	$error_context = debug_error_context($error_context);

	$dbmsg = false;
	if (function_exists('fb_db_query')
		&& preg_match('/db\-(mysql|pgsql|mssql|oracle|sqlite)\.php$/', basename($trace[0]['file'])))
	{
		$trace = debug_trace($trace, __FILE__, '/db\-(mysql|pgsql|mssql|oracle|sqlite)\.php$/');
		$query = '';
		$dbmsg = true;
		if (preg_match('/\s*Query:([\s\S]+)$/', $error_msg, $match)) {
			$error_msg = str_replace($match[0], '', $error_msg);
			$query = $match[1];
			$query = preg_replace('/(\w),(\w)/', '$1, $2', $query);
			$query = preg_replace('/\s+/', ' ', $query);
			$query = trim($query);
		}
		$error['db_error'] = $error_msg;
		if ($query)	$error['fb_db_query'] = $query;
		if (isset($error_context['query_base'])) unset($error_context['query_base']);
		if (isset($error_context['query'])) unset($error_context['query']);
	} else {
		$error['msg'] = $error_msg;
		$trace = debug_trace($trace, __FILE__);
	}

	$return = '';
	$h2_context = 'Vars in current scope: '.count($error_context);
	if ($dbmsg) {
		$h2_context = 'Vars in DB scope: '.count($error_context);
		$msg = $error_msg;
		if (strlen($msg) > 100) {
			$msg = substr($msg, 0, 100).'..';
		}
		$msg = debug_escape($msg);
		$title = '<b>DB_ERROR:</b> '.$msg.' in';
		$return .= debug_header(strip_tags($title), $trace);
		$return .= debug_h1($title, $trace, 'error');
		if ($query) {
			$return .= debug_query($query);
		}
		$return .= debug_h2('Source: '.debug_path($trace[0]['file']));
	} else {
		$msg = $error['msg'];
		if (strlen($msg) > 100) {
			$msg = substr($msg, 0, 100).'..';
		}
		$msg = debug_escape($msg);
		$title = '<b>'.$error_type.':</b> '.$msg.' in';
		$return .= debug_header($error_type.': '.$msg.' in', $trace);
		$return .= debug_h1($title, $trace, 'error');
	}

	$max_line = debug_max_line($trace);
	$return .= debug_source($trace[0]['file'], $trace[0]['line'], strlen($max_line));

	$return .= debug_pre($error_context, $h2_context, $max_lines = 12);
	$return .= debug_print_trace($trace, strlen($max_line));
	if ('POST' == $_SERVER['REQUEST_METHOD'] && count($_POST)) {
		$return .= debug_h2('$_POST:');
		$return .= debug_pre(debug_error_post_vars());
	}
	$return .= debug_included_files();
	$return .= debug_footer();
	$return = str_replace('charset=utf-8">', 'charset='.debug_detect_charset($return).'">', $return);

	echo $return;

	exit();
}
function debug_error_context($context, $recursion = 1)
{
	static $ds_max_elements = 128;
	static $ds_elements = 0;
	static $ds_max_len = 128;
	static $ds_data = 0;
	static $ds_max_data = 16384;

	if (memory_get_usage() > 1024*1024*10) {
		return '[debug-stop:memory-usage>10MB]';
	}
	$return = array();
	if ($recursion > 3) {
		return $return;
	}
	if (is_array($context)) {
		foreach ($context as $k => $v) {
			if (is_array($v)) {
				if ('GLOBALS' === $k || '_' === substr($k,0,1)) {
					continue;
				}
				$ds_elements++;
				if ($ds_elements > $ds_max_elements) {
					$return[] = '[debug-stop:'.$ds_max_elements.'-elements-reached]';
					return $return;
				}
				$return[$k] = debug_error_context($context[$k], ++$recursion);
			} else if (is_string($v)) {
				$v_len = strlen($v);
				if ($v_len > $ds_max_len) {
					$v = substr($v, 0, $ds_max_len).'..[debug-stop:real-length:'.$v_len.']';
				}
				$return[$k] = $v;
				$ds_elements++;
				$ds_data += $v_len;
				if ($ds_elements > $ds_max_elements) {
					$return[] = '[debug-stop:'.$ds_max_elements.'-elements-reached]';
					return $return;
				}
				if ($ds_data > $ds_max_data) {
					$return[] = '[debug-stop:max-data-reached:'.round($ds_max_data/1024).'KB]';
					return $return;
				}
			} else {
				$return[$k] = $v;
				$ds_elements++;
				if ($ds_elements > $ds_max_elements) {
					$return[] = '[debug-stop:'.$ds_max_elements.'-elements-reached]'; return $return;
				}
			}
		}
	}
	return $return;
}
function debug_error_post_vars()
{
	$post = '';
	if (count($_POST)) {
		$passwords = array('password', 'pass', 'pwd', 'hash', 'md5');
		foreach ($passwords as $pass) {
			foreach ($_POST as $k => $v) {
				if (strstr(strtolower($k), $pass)) {
					$_POST[$k] = '****';
					$_REQUEST[$k] = '****';
				}
			}
		}
		$post = $_POST;
		foreach ($post as $k => $v) {
			if (!is_string($v)) continue;
			if (strlen($v) > 100) {
				$post[$k] = substr($v, 0, 100).'..';
			}
		}
	}
	return $post;
}

// -------- debug

function fb_debug($var = null)
{
	$system = array('memory_usage'=>debug_format_bytes(memory_get_peak_usage()));
	$trace = debug_backtrace();
	$trace = debug_trace($trace);
	if (func_num_args() > 1) $var = func_get_args();

	debug_display($var, $trace, $system);
}
function debug_raw($var)
{
	if (func_num_args() > 1) $var = func_get_args();
	echo '<pre>';
	var_dump($var);
	echo '</pre>';
	exit;
}
function debug_ob_cleanup()
{
	if (headers_sent()) { error_log('ob_cleanup() failed: headers already sent'); return; }
	while (ob_get_level()) { ob_end_clean(); }
	foreach (headers_list() as $header) {
		if (preg_match('/Content-Encoding:/i', $header)) {
			header('Content-encoding: none');
			return;
		}
	}
}
function debug_display($main_var, $trace, $system)
{
	$main_string = debug_print_var($main_var);
	debug_ob_cleanup();
	if (!headers_sent()) {
		// Send 503 http code so search engines do not crawl that broken page
		header('HTTP/1.0 503 Service Unavailable');
	}

	$return = '';
	$return .= debug_header('Debug:', $trace);
	$return .= debug_h1('Debug:', $trace);
	$max_line = debug_max_line($trace);
	$return .= debug_source($trace[0]['file'], $trace[0]['line'], strlen($max_line));
	$return .= debug_pre($main_string, 'Vardump: '.(count($main_var) == 1 ? '': count($main_var)), 36);
	$return .= debug_print_trace($trace, strlen($max_line));
	$return .= debug_included_files();
	$return .= debug_pre($system, 'System', 12);
	$return .= debug_footer();
	$charset = debug_detect_charset($return);
	$return = str_replace('charset=utf-8">', ('charset='.$charset.'">'), $return);
	if (!headers_sent()) {
		header('Content-type: text/html; charset='.$charset);
	}
	if (!headers_sent()) ob_start('ob_gzhandler');
	echo $return;
	exit;
}
function debug_included_files($files=array())
{
	if(!$files){
		$files = get_included_files();
		$files = array_reverse($files, true);
	}
	$included = array();
	foreach ($files as $k => $file) {
		$nk = $k+1;
		$nk = str_pad($nk, strlen(count($files)), '0', STR_PAD_LEFT);
		$included[$nk] = debug_path($file);
	}
	return debug_pre($included, 'Included files: '.count($included), 12);
}
function debug_format_bytes($bytes)
{
	$base = 1024;
	$suffixes = array(' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB');
	$usesuf = 0;
	$n = (float) $bytes;
	while ($n >= $base) {
		$n /= (float) $base;
		++$usesuf;
	}
	$places = 2 - floor(log10($n));
	$places = max($places, 0);
	return number_format($n, $places, '.', '') . $suffixes[$usesuf];
}
function debug_reflection($trow)
{
	// reflection fatal error when zend.ze1_compatibility_mode is On
	ini_set('zend.ze1_compatibility_mode', 0);

	$name = '';
	if (isset($trow['class'])) {
		if (isset($trow['func'])) {
			try {
				$reflect = new ReflectionMethod($trow['class'], $trow['func']);
			} catch (Exception $e) {
				return 'Reflection failed';
			}
			$name = 'Method';
		} else {
			try {
				$reflect = new ReflectionClass($trow['class']);
			} catch (Exception $e) {
				return 'Reflection failed';
			}
			$name = 'Class';
		}
	} else {
		if (isset($trow['func'])) {
			try {
				$reflect = new ReflectionFunction($trow['func']);
			} catch (Exception $e) {
				return 'Reflection failed';
			}
			$name = 'Function';
		} else {
			return '';
		}
	}
	if ($reflect->isInternal()) {
		if (isset($trow['class'])) return 'Internal class';
		else return 'Internal function';
	} else {
		$return = $name.' ';
		$return .= 'defined in ';
		$return .= debug_path($reflect->getFileName());
		$return .= ' on line ';
		$return .= $reflect->getStartLine();
		return $return;
	}
}
function debug_header($title, $trace)
{
	$title = strip_tags($title);
	$return = '';
	$return .= '<html><head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>'.$title.' '.strip_tags(debug_shortfile($trace[0])).'</title>
	<style type="text/css">
	body { margin:0em; background: #f0f0f0; }
	body,h1,h2 {}
	.font { font-size: 12px; font-family: Consolas, Courier New; }
	pre { background:#f0f0f0;margin:0em;padding:0.5em; }
	a,a:visited { color:#000099;text-decoration:none; }
	a:hover { text-decoration:underline; }
	h1, h2, h3 { margin: 0em; padding: 0em; font-weight: normal; }
	h3 { background: #EEEEFF; border-left: #808080 1px solid; border-bottom: #808080 1px solid; padding: 0.5em; }
	h1 { background: #666699; border-bottom: #333366 1px solid; border-top: #333366 1px solid; color: #fff; padding: 0.5em 0.5em; }
	h1.error { background: #E0006D; }
	h1 b { font-weight: normal; color: #FFFF00; }
	h2 b { font-weight: normal; }
	h2 a, h2 a:visited { color: #fff; display: block; }
	h2 a:hover { text-decoration: none; }
	.trace_call a, .trace_call a:visited { color: #000; display: block; }
	.trace_call a:hover { text-decoration: none; }
	.trace_call b { font-weight: normal; color: #0000ff; }
	.trace_call u { text-decoration: none; color: #0000ff; cursor: help; }
	.trace_content_last { border-bottom: #808080 1px solid; }
	.line-dblclicked { background: #ddd; }
	.line-even {}
	/* .line-odd { background: #f5f5f5; } */
	.line-over { background: #ddd; }
	.line-out {}
	.popmsg { border: #333366 1px solid; background: #EEEEFF; padding: 0.2em 0.5em; }
	</style>';

	$trace_count = count($trace)-1;
	$return .= <<<SCRIPT
		<script>
		function $(id) { return document.getElementById(id); }
		function line_over(div)
		{
			if (!/ line-over/.test(div.className)) div.className += ' line-over';
			if (/ line-out/.test(div.className)) div.className = div.className.replace(/ line-out/, '');
		}
		function line_out(div)
		{
			if (!/ line-out/.test(div.className)) div.className += ' line-out';
			if (/ line-over/.test(div.className)) div.className = div.className.replace(/ line-over/, '');
		}
		function line_dblclick(div)
		{
			div.className= (/ line-dblclicked/.test(div.className)
				? div.className.replace(/ line-dblclicked/, '')
				: div.className+' line-dblclicked')
		}
		window.onload = function()
		{
			var els = document.getElementsByTagName('div');
			var length = els.length;
			for (var i = 0; i < length; i++) {
				if (els[i].className == 'line-even' || els[i].className == 'line-odd') {
					els[i].onmouseover = function() { line_over(this); }
					els[i].onmouseout = function() { line_out(this); }
					els[i].ondblclick = function() { line_dblclick(this); }
				}
			}
		}
		function toggle(id, from)
		{
			$(id).style.display = ($(id).style.display == 'none' ? '' : 'none');
			if (from) {
				from.blur();
			}
		}
		function toggle_pre(id, pre_id)
		{
			$('h2_'+id).blur();
			if (/\d+px/.test($(id).style.height)) {
				$(id).style.height = 'auto';
				$(id).style.overflow = 'visible';
			} else {
				var height;
				eval('height=max_lines_'+pre_id);
				$(id).style.height = height;
				$(id).style.overflow = 'auto';
			}
		}
		var trace_count = $trace_count;
		function toggle_trace_all()
		{
			var opened = false;
			for (var i = 0; i < trace_count; i++) {
				if ($('trace_content_'+i).style.display != 'none') {
					opened = true;
				}
			}
			for (var i = 0; i < trace_count; i++) {
				if ($('trace_content_'+i).innerHTML.length) {
					$('trace_content_'+i).style.display = opened ? 'none' : '';
				}
			}
			$('trace_h2').blur();
		}
		var popmsg_id = 0;
		var popmsg_map = {};
		function popmsg(msg, from_el)
		{
			var id, div_id;
			if (typeof popmsg_map[msg] != 'undefined') {
				id = popmsg_map[msg];
				div_id = 'popmsg_'+id;
			} else {
				id = popmsg_id++;
				popmsg_map[msg] = id;
				div_id = 'popmsg_'+id;
				var div = '<div id="'+div_id+'" class="popmsg font" style="display:none; position: absolute; z-index: 50000;">'+msg+'</div>';
				$('popmsg_container').innerHTML += div;
			}
			if (typeof from_el.onmouseout == 'undefined' || null === from_el.onmouseout) {
				from_el.onmouseout = function() { popmsg_out(div_id); };
			}
			var pos = popmsg_offset(from_el);
			var mpos = mousepos;
			var x = mpos[0] - pos[0];
			$(div_id).style.left = pos[0]+x+25+'px';
			$(div_id).style.top = pos[1]+from_el.offsetHeight+'px';
			$(div_id).style.display = 'block';
		}
		function popmsg_out(div_id)
		{
			$(div_id).style.display = 'none';
		}
		function popmsg_offset(el)
		{
			var x = 0, y = 0;
			do { if (el.nodeName.toLowerCase != 'td') { x += el.offsetLeft; y += el.offsetTop; }} while ((el = el.offsetParent) && el.nodeName.toLowerCase() != 'body');
			return [x,y];
		}
		var mousepos = [0,0];
		document.onmousemove = mousepos_listener;
		function mousepos_listener(e)
		{
			var posx = 0;
			var posy = 0;
			if (!e) e = window.event;
			if (e.pageX || e.pageY) 	{
				posx = e.pageX;
				posy = e.pageY;
			}
			else if (e.clientX || e.clientY) 	{
				posx = e.clientX + document.body.scrollLeft
					+ document.documentElement.scrollLeft;
				posy = e.clientY + document.body.scrollTop
					+ document.documentElement.scrollTop;
			}
			mousepos = [posx,posy];
		}

		</script>
SCRIPT;

	$return .= '</head><body><div id="popmsg_container"></div>';

	// do not use font detection right now, my eyes hurt from ProFontWindows and ProggyTiny fonts, they are way too small for coding in the middle of the night, Courier New might just work
	$font_detection_off = <<<SCRIPT
	<script>
	// must be inside BODY

	// taken from: http://www.lalit.org/lab/javascript-css-font-detect (Author: Lalit Patel)
	// comments removed & modified a little to make it work with all browsers
	var FontDetector = new function()
	{
		var h = document.getElementsByTagName("BODY")[0];
		var d = document.createElement("DIV");
		var s = document.createElement("SPAN");
		d.appendChild(s);
		d.style.fontFamily = "sans-serif";
		s.style.fontFamily = "sans-serif";
		s.style.fontSize   = "72px";
		s.innerHTML = "mmmmmmmmmml";
		h.appendChild(d);
		var defaultWidth = s.offsetWidth;
		var defaultHeight = s.offsetHeight;
		h.removeChild(d);
		this.detectionBroken = false;
		this.test = function(font)
		{
			if (this.detectionBroken) return false;
			h.appendChild(d);
			var f = [];
			f[0] = s.style.fontFamily = font+", sans-serif";
			f[1] = s.offsetWidth;
			f[2] = s.offsetHeight;
			h.removeChild(d);
			font = font.toLowerCase();
			if (font == "arial" || font == "sans-serif") {
				f[3] = true;
			} else {
				f[3] = (f[1] != defaultWidth || f[2] != defaultHeight);
			}
			return f[3];
		}
	}
	</script>
	<script>
	if (FontDetector.test('NoSuchFont') || !FontDetector.test('Courier New')) {
		FontDetector.detectionBroken = true;
	}
	if (FontDetector.test('ProFontWindows')) {
		//document.write('<style type="text/css">.font { font: 9px ProFontWindows; }</style>');
	} else if (FontDetector.test('ProggyTiny')) {
		//document.write('<style type="text/css">.font { font: 10px ProggyTiny; }</style>');
	}
	</script>
SCRIPT;

	return $return;
}
function debug_footer()
{
	return '</body></html>';
}
function debug_h1($title, $trace, $cssclass='', $more='')
{
	$title = strip_tags($title, '<b>');
	$return = '<h1 class="font '.$cssclass.'">'.$title.' '.debug_shortfile($trace[0]).($more ? '<br>'.$more : '').'</h1>';
	return $return;
}
function debug_h2($name)
{
	$return = '<h2 class="font" style="background:#9999CC;color:#fff;padding:0.5em 0.5em;border-bottom: #333366 1px solid;border-top: #333366 1px solid;">'.$name.'</h2>';
	return $return;
}
function debug_h2_toggle($name, $pre_id)
{
	// toggle for context vars
	$return = '<h2 class="font" style="background:#9999CC;color:#fff;padding:0.5em 0.5em;border-bottom: #333366 1px solid;border-top: #333366 1px solid;"><a href="javascript:void(0)" id="h2_pre_'.$pre_id.'" onclick="toggle_pre(\'pre_'.$pre_id.'\', '.$pre_id.')"><span style="float:right;" style="cursor:pointer;">[toggle]</span>'.$name.'</a></h2>';
	return $return;
}
function debug_h2_trace($name)
{
	$name = '<a style="display:inline;" href="javascript:void(0)" onclick="toggle_trace_all()" id="trace_h2"><span style="float:right;" style="cursor:pointer;">[toggle]</span>'.$name.'</a>';
	return debug_h2($name);
}
function debug_shortfile($tracerow)
{
	$file = isset($tracerow['file']) ? $tracerow['file'] : null;
	$line = isset($tracerow['line']) ? $tracerow['line'] : null;
	if (!$file) $file = 'unknown file';
	if (!$line) $line = 0;
	$file = debug_path($file);
	return sprintf('<b>%s</b> on line <a style="display:inline;" href="javascript:location.href=\'notepad2://http://\'+location.host+\'%s/?%s\'"><b>%s</b></a>', $file,$file,$line,$line);
}
function debug_path($file)
{
	$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
	$file = str_replace('\\', '/', $file);
	if ('/' == substr($root,-1) && strlen($root) > 1) $root = substr($root,0,-1);
	// case insensitive replace d:/dev.lc/ .. D:/dev.lc/
	$file = str_ireplace($root, '', $file);
	$file = preg_replace('/[a-z]:/i', '', $file);
	return $file;
}
function debug_pre($string, $h2_title = null, $max_lines = null, $style='')
{
	if (!is_string($string)) {
		$string = debug_print_var($string);
	}
	$string = str_replace('&lt;pre <a','<a',$string);
	$string = str_replace('&lt;pre','<pre',$string);
	$string = str_replace('&lt;/pre&gt;','</pre>',$string);
	$string = str_replace('&lt;a','<a',$string);
	$string = str_replace('&lt;/a&gt;','</a>',$string);
    $string = str_replace('&lt;b','<b',$string);
	$string = str_replace('&lt;/b&gt;','</b>',$string);
	$string = str_replace('&lt;i','<i',$string);
	$string = str_replace('&lt;/i&gt;','</i>',$string);
	$string = str_replace('&lt;font','<font',$string);
	$string = str_replace('&lt;/font&gt;','</font>',$string);
	$string = str_replace('&lt;small&gt;','<small></small>',$string);
	$string = str_replace('&lt;/small&gt;','</small>',$string);
	$string = str_replace("#cc0000'&gt;","#cc0000'>",$string);
	$string = str_replace("#3465a4'&gt;","#3465a4'>",$string);
	//if (strlen($string) > 50000) $string = substr($string,0,50000).'[debug: more than 50K chars]';
	static $pre_id = 0;
	$pre_id++;
	$count_lines = 0;
	// there are no new lines, instead we count <div> elements
	if (strstr($string, '<div')) {
		$count_lines += substr_count($string, '<div');
	}
	if (preg_match('/<div[^<>]+class="line-[^<>]+>[^<>]+\n/', $string)) {
		$count_lines += substr_count($string, "\n");
	}
	if ($count_lines < $max_lines) {
		$max_lines = null;
	}
	$return = '';
	if ($max_lines) {
		$return .= '<pre class="font" id="max_lines_'.$pre_id.'">' .implode("\n",range(1,$max_lines)). '</pre>';
		$return .= '<script>var max_lines_'.$pre_id.' = $(\'max_lines_'.$pre_id.'\').offsetHeight; if (document.body.currentStyle && !window.opera) max_lines_'.$pre_id.' -= 3; /* ie fix */ if (navigator.userAgent.indexOf(\'Firefox\') != -1) max_lines_'.$pre_id.' -= 14; /* firefox fix 1 line too much */ $(\'max_lines_'.$pre_id.'\').style.display=\'none\';</script>';
	}
	if ($max_lines) {
		$return .= debug_h2_toggle($h2_title, $pre_id);
	} else {
		if ($h2_title) {
			$return .= debug_h2($h2_title);
		}
	}
	$style .= 'background: #f0f0f0;';
	if ($max_lines) $style .= 'overflow: auto;';
	$return .= '<pre class="font" style="'.$style.'" id="pre_'.$pre_id.'">'.$string.'</pre>';
	if ($max_lines) {
		$return .= '<script>$(\'pre_'.$pre_id.'\').style.height=(max_lines_'.$pre_id.')+\'px\';$(\'pre_'.$pre_id.'\').style.overflow=\'auto\';</script>';
	}
	return $return;
}
function debug_pre_trace($string, $t_k, $trow)
{
	if (isset($trow['file']) && file_exists($trow['file'])) {
		return '<pre class="trace_call font" style="background: #CFCFE5; border: #808080 1px solid; border-style: none none solid none;"><a style="display:inline;" href="javascript:void(0)" onclick="toggle(\'trace_content_'.$t_k.'\', this);"><span style="float:right;" style="cursor:pointer;">[toggle]</span>'.$string.'</a></pre>';
	} else {
		return '<pre class="trace_call font" style="background: #CFCFE5; border: #808080 1px solid; border-style: none none solid none;">'.$string.'</pre>';
	}
}
function debug_max_line($trace)
{
	$max_line = null;
	foreach ($trace as $v) {
		if ($v['line'] > $max_line) $max_line = $v['line'];
	}
	return $max_line+5;
}
function debug_print_trace($trace, $line_width = null)
{
	array_shift($trace);
	$return = '';
	$h2name = 'Backtrace: '.(count($trace));
	if (count($trace))
	{
		$return .= debug_h2_trace($h2name);
		$max_string_length = 0;
		foreach ($trace as $k => $trow) {
			$string = str_pad($k+1, $line_width, '0', STR_PAD_LEFT).': '.debug_shortfile($trow).' invokes:';
			if (strlen(strip_tags($string)) > $max_string_length) $max_string_length = strlen(strip_tags($string));
		}
		$max_string_length++;

		$trace_string = '';
		$numrev = count($trace); // trace numeration reversed
		foreach ($trace as $t_k => $trow) {
			$string = str_pad($numrev--, $line_width, '0', STR_PAD_LEFT).': '.debug_shortfile($trow).' invokes:';
			$string .= str_repeat(' ', $max_string_length - strlen(strip_tags($string)) + 1);
			$string .= '<u onmouseover="popmsg(\''.debug_reflection($trow).'\', this)">';
			$string .= (isset($trow['class']) ? $trow['class'].'.' : '');
			$string .= (isset($trow['func']) ? $trow['func'] : '');
			$string .= '</u>';
			$call_full = false;
			if (isset($trow['args'])) {
				list($args_short, $call_full) = debug_print_args_short($trow['args']);
				if (strlen($args_short) > 80) {
					list($args_short, $call_full) = debug_print_args_short($trow['args'], true);
					$call_full = true;
				}
				$string .= $args_short;
			}
			$trace_string .= debug_pre_trace($string, $t_k, $trow);
			$trace_string .= '<div style="border-bottom: #808080 1px solid;" id="trace_content_'.$t_k.'" '.($t_k==count($trace)-1 ? 'class="trace_content_last"':'').'>';
			if ($call_full) {
				$trace_string .= '<table cellspacing="0" cellpadding="0" width="100%" id="trace'.$t_k.'_source_container"><tr>';
				$trace_string .= '<td width="50%" valign="top">';
				$trace_string .= '<div id="trace'.$t_k.'_source">';
			}
			$trace_string .= debug_source($trow['file'], $trow['line'], $line_width);
			if ($call_full) {
				$trace_string .= '</div>';
				$trace_string .= '</td>';
				$trace_string .= '<td width="50%" valign="top">';
				$trace_string .= '<h3 class="font" id="trace'.$t_k.'_args_full_head">Arguments full list: '.count($trow['args']).'</h3>';
				$trace_string .= '<div id="trace'.$t_k.'_args_full" style="overflow: auto;">';
				$trace_string .= debug_pre($trow['args']);
				$trace_string .= '</div>';
				$trace_string .= '</td></tr></table>';
				$trace_string .= '<script>';
				$trace_string .= '$(\'trace'.$t_k.'_args_full\').style.height=($(\'trace'.$t_k.'_source\').offsetHeight-$(\'trace'.$t_k.'_args_full_head\').offsetHeight)+\'px\';';
				$trace_string .= '$(\'trace'.$t_k.'_source\').style.width=(($(\'trace'.$t_k.'_source_container\').offsetWidth/2)-10)+\'px\';';
				$trace_string .= '</script>';
			}
			$trace_string .= '</div>';
			$trace_string .= '<script>$(\'trace_content_'.$t_k.'\').style.display=\'none\';</script>';
		}

		$return .= $trace_string;
	}
	else
	{
		$return .= debug_h2($h2name);
	}
	return $return;
}
function debug_print_args_short($args, $cut = false)
{
	$call_full = false;
	$ret = '(';
	foreach ($args as $k => $arg) {
		if ($k) $ret .= ', ';
		switch (true) {
			case is_string($arg):
				$arg = debug_escape($arg);
				$ret .= "'";
				if (substr($arg,-4) == '.php' || substr($arg,-4) == '.tpl') {
					$arg = debug_path($arg);
				}
				if ($cut && strlen($arg) > 20) {
					$ret .= substr($arg,0,20).'..';
					$call_full = true;
				} else {
					$ret .= $arg;
				}
				$ret .= "'";
				break;
			case is_array($arg):
				$ret .= 'array('.count($arg).')';
				$call_full = true;
				break;
			case is_object($arg):
				$ret .= 'object('.get_class($arg).')';
				$call_full = true;
				break;
			case is_int($arg):
			case is_float($arg):
				$ret .= $arg;
				break;
			case is_null($arg):
				$ret .= 'null';
				break;
			case is_bool($arg):
				$ret .= ($arg ? 'true':'false');
				break;
			case is_resource($arg):
				$ret .= 'resource('.get_resource_type($arg).')';
				$call_full = true;
				break;
			default:
				$ret .= gettype($arg);
				$call_full = true;
				break;
		}
	}
	$ret .= ')';
	return array($ret, $call_full);
}
function debug_source($file, $line, $line_width = null, $chunk_lines = 5)
{
	$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
	if (file_exists($root.$file)) $file = $root.$file;
	if (file_exists($file) && is_file($file) && is_readable($file))
	{
		$lines = file($file);
		if (!isset($lines[$line-1])) return null;

		$start = $line-1-$chunk_lines;
		if ($start < 0) $start = 0;
		$end = $line-1+$chunk_lines;
		if ($end > count($lines)-1) $end = count($lines)-1;

		$chunk = array();
		for ($i = $start; $i <= $end; $i++) {
			$chunk[$i+1] = rtrim($lines[$i]);
		}

		// blank lines at start or end won't be highlighted so remove them
		foreach ($chunk as $k => $v) {
			if (trim($v)) break;
			else unset($chunk[$k]);
		}
		unset($v);
		// blank lines search from the end
		if (count($chunk)) {
			// chunk[0] does not exist
			for ($k = $end+1; $k > $start; $k--) {
				if (trim($chunk[$k])) break;
				else unset($chunk[$k]);
			}
		}

		$new_start = null;
		$new_end = null;
		foreach ($chunk as $l => $v) {
			$new_start = $l;
			break;
		}
		$chunk2 = $chunk;
		$chunk2 = array_reverse($chunk2, true);
		foreach ($chunk2 as $l => $v) {
			$new_end = $l;
			break;
		}

		$source = implode("\r\n", $chunk);
		$source = trim($source);

		$count = 0;
		if (preg_match_all("#\r\n#", $source, $match)) {
			$count = count($match[0]);
		}

		$range = range($new_start,$new_end);
		$line_str = $line;
		if ($line_width) {
			foreach ($range as $k => $v) {
				$range[$k] = str_pad($v, $line_width, '0', STR_PAD_LEFT);
				if ($line == $v) $line_str = $range[$k];
			}
		} else {
			foreach ($range as $k => $v) {
				if (strlen($v) < strlen($new_end)) {
					$range[$k] = '0'.$v;
					if ($line == $v) $line_str = $range[$k];
				}
			}
		}

		$num = '<code class="font" style="float:left; color: #808080; text-align: right; border-right: 1px solid #808080; background:#fff; padding:0.5em; padding-right:0.6em;">'.implode($range,'<br>').'</code>';

		$highlight_line = '<span style="background:yellow;margin-right:-0.6em;margin-left:-0.5em;padding-right:0.6em;padding-left:0.5em;border-top: 1px solid #ccc;border-bottom: 1px solid #808080;">%s</span>';

		if (1 == count($range)) {
			$num = str_replace('>'.$line_str.'</code>', '>'.sprintf($highlight_line, $line_str).'</code>', $num);
		} else {
			$num = str_replace($line_str.'<br>',sprintf($highlight_line, $line_str).'<br>', $num);
		}

		$remove_phptag = false;
		if (!preg_match('#^\s*<'.'\?#', $source)) {
			$source = '<'."?php\r\n".$source;
			$remove_phptag = true;
		}
		$source = preg_replace('#([\'"][^\'"]*(password|pass|passwd|pwd|pw|hash|md5)[^\'"]*[\'"]\s*=>\s*[\'"])[^\'"]*([\'"])#i', '$1****$3', $source);
		$source = highlight_string($source, true);

		if ($remove_phptag) {
			$source = preg_replace('#<code[^<>]*>\s*<span[^<>]*>\s*<span[^<>]*>&lt;\?php\s*<br[^<>]*>(\s*</span>)?#i', '<code>', $source);
		}

		$source = preg_replace('#<code#i', '<code class="font" style="background:#f0f0f0; display:block; padding:0.5em; overflow: auto;"', $source);

		return $num.$source;
	}
}
function debug_trace_args($context, $recursion = 1)
{
	static $ds_max_elements = 32;
	static $ds_elements = 0;
	static $ds_max_len = 128;
	static $ds_data = 0;
	static $ds_max_data = 4096;

	if (memory_get_usage() > 1024*1024*10) {
		return '[debug-stop:memory-usage>10MB]';
	}
	$return = array();
	if ($recursion > 3) {
		return $return;
	}
	if (is_array($context)) {
		foreach ($context as $k => $v) {
			if (is_array($v)) {
				if ('GLOBALS' === $k || '_' === substr($k,0,1)) {
					continue;
				}
				$ds_elements++;
				if ($ds_elements > $ds_max_elements) {
					$return[] = '[debug-stop:'.$ds_max_elements.'-elements-reached]';
					return $return;
				}
				$return[$k] = debug_error_context($context[$k], ++$recursion);
			} else if (is_string($v)) {
				$v_len = strlen($v);
				if ($v_len > $ds_max_len) {
					$v = substr($v, 0, $ds_max_len).'..[debug-stop:real-length:'.$v_len.']';
				}
				$return[$k] = $v;
				$ds_elements++;
				$ds_data += $v_len;
				if ($ds_elements > $ds_max_elements) {
					$return[] = '[debug-stop:'.$ds_max_elements.'-elements-reached]';
					return $return;
				}
				if ($ds_data > $ds_max_data) {
					$return[] = '[debug-stop:max-data-reached:'.round($ds_max_data/1024).'KB]';
					return $return;
				}
			} else {
				$return[$k] = $v;
				$ds_elements++;
				if ($ds_elements > $ds_max_elements) {
					$return[] = '[debug-stop:'.$ds_max_elements.'-elements-reached]'; return $return;
				}
			}
		}
	}
	return $return;
}
function debug_trace($trace, $ignore_file = null, $ignore_pattern = null)
{
	// ignore_file - required if called from error_debug()
	$ret = array();
	foreach ($trace as $k => $v) {
		$current = array();
		$func = isset($v['function']) ? $v['function'] : '';
		$file = isset($v['file']) && strlen($v['file']) ? $v['file'] : '';
		if (isset($v['file']) && strlen($v['file'])) {
			$file = $v['file'];
			$__file = __FILE__;
			$file = str_replace('\\', '/', $file);
			$__file = str_replace('\\', '/', $__file);
			$ignore_file = str_replace('\\', '/', $ignore_file);
			if ($file == $__file) {
				continue;
			}
			if ($file == $ignore_file) {
				continue;
			}
			if ($ignore_pattern && preg_match($ignore_pattern, $file)) {
				continue;
			}
			$line = isset($v['line']) ? $v['line'] : 0;
			$current['file'] = $file;
			$current['line'] = $line;
		} else {
			$current['file'] = 'unknown file';
			$current['line'] = '0';
		}
		if (isset($v['function'])) {
			$current['func'] = $v['function'];
		}
		if (isset($v['class'])) $current['class'] = $v['class'];
		if (isset($v['args'])) {
			$current['args'] = debug_trace_args($v['args']);
		} else {
			$current['args'] = array();
		}
		if (count($current)) $ret[] = $current;
	}
	if (!count($ret)) {
		$ret[] = array(
			'file' => 'unknown file',
			'line' => 0
		);
	}
	return $ret;
}
function debug_detect_charset($string)
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
function debug_print_var($var)
{
	$max_line_length = 2005;

	// returns html escaped
	if (func_num_args() > 1) $var = func_get_args();
	debug_print_var_striplines($var);
	$string = debug_var_dump($var);
	$string = preg_replace('#("]=>)\s+#', '$1', $string);
	$string = preg_replace('#(\[\d+\]=>)\s+#', '$1', $string);
	$string = preg_replace('#(\]=>)([^\s]+)\s("[^\r\n]*")([\r\n])#', '$1$3 $2$4', $string);
	$string = preg_replace('#\["([^"]*)"\]=>#', '$1: ', $string);
	$string = preg_replace('#\[(\d+)\]=>#', '$1: ', $string);
	$string = preg_replace('#[\r\n][ ]*}([\r\n])#', '$1', $string);
	$string = preg_replace('#([\r\n])([ ]+)(\S)#e', "'\\1'.debug_print_var_doubleindent('\\2').'\\3'", $string);
	$string = preg_replace('#\s+{([\r\n])#', '$1', $string);
	$string = preg_replace('#([\s])array\(0\)([\r\n])#', '$1array[0]$2', $string);
	$string = preg_replace('#[\s]array\(\d+\)([\r\n])#', '$1', $string);
	$string = preg_replace('#NULL([\r\n])#', 'null null$1', $string);
	$string = preg_replace('#(\&?int)\((\-?[\d]+)\)([\r\n])#', '$2 $1$3', $string);
	$string = preg_replace('#(\&?float)\((\-?[\d\.]+)\)([\r\n])#', '$2 $1$3', $string);
	$string = preg_replace('#(\&?bool)\((true|false)\)([\r\n])#', '$2 $1$3', $string);
	$string = preg_replace('#^array\(\d+\)[\r\n]+#', '', $string);
	$string = preg_replace('#\s+}\s*$#', '', $string);
	$string = preg_replace('#\t#', str_repeat(' ',4), $string);
	// var is only 1 line string
	$string = preg_replace("#^\"([^\r\n]*)\"[ ]([^\r\n]*)$#", '\\2 \\1', $string);
	$string = preg_replace("#^([^\r\n]+)[ ]\"([^\r\n]*)\"$#", '\\2 \\1', $string);
	$string = preg_replace("#^\"([^\r\n]*)\"([ ][^\r\n]+)$#", '\\1\\2', $string);
	if (preg_match('#^null null\s*$#', $string)) return 'null';

	$string = trim($string);
	$lines = preg_split('#[\r\n]#', $string);
	$current_indent = 0;
	$last_indent = 0;
	$tomodify = array();
	$current_max2 = null;
	$current_max3 = null;

	foreach ($lines as $k => $line) {
		$line_pattern = "#^(\s*)([^:]+:[ ]*)[\"]([^\r\n]*)[\"]([ ][^\r\n]+)$#";
		$line_pattern2 = "#^(\s*)([^:]+:[ ]*)([^\r\n]*)([ ][^\r\n]+)$#";
		if (preg_match($line_pattern, $line, $match) || preg_match($line_pattern2, $line, $match)) {
			$current_indent = strlen($match[1]);
			// if last line we need to do the modification now
			$last_line = ($k == (count($lines)-1));
			//if (defined('AAA')) {
				//echo '<pre>';
				//echo htmlspecialchars(print_r($lines,true));
				//exit;
			//}
			if (($current_indent != $last_indent && $k) || ($last_line)) {
				if ($last_line && $current_indent == $last_indent) {
					// @@@current_max@@@ : 1/2
					if (strlen($match[2]) > $current_max2) $current_max2 = strlen($match[2]);
					if (strlen($match[3]) > $current_max3) $current_max3 = strlen($match[3]);
					$tomodify[$k] = $line;
				}
				// @@@tomodify@@@ : 1/3
				foreach ($tomodify as $k2 => $line2) {
					preg_match($line_pattern, $line2, $match2)
						|| preg_match($line_pattern2, $line2, $match2);
					$match2[4] = str_replace('array[0]', 'array(0)', $match2[4]);
					if (strstr($match2[4],'(')) {
						if (preg_match('#^\s*\(\d+\)$#', $match2[4])) {
							// ex. object(ReflectionFunction)#1 (1)
						} else {
							$match2[4] = ' ('. trim(str_replace(array('(',')'),array(':',''), $match2[4])).')';
						}
					} else {
						$match2[4] = ' ('.trim($match2[4]).')';
					}
					if ($current_max3 > $max_line_length) $current_max3 = $max_line_length;
					if (strlen($match2[3]) > $max_line_length) {
						$match2[3] = urldecode($match2[3]);
						$match2[3] = preg_replace("#([^\r\n]{".$max_line_length.",".$max_line_length."})#", "\\1\r\n".str_repeat(' ',$current_max2+strlen($match2[1])), $match2[3]);
					}
					$lines[$k2] = $match2[1].str_pad($match2[2], $current_max2).str_pad($match2[3], $current_max3).$match2[4];
				}
				$tomodify = array();
				$current_max2 = null;
				$current_max3 = null;
			}

			if ($last_line && $current_indent != $last_indent) {
				// exception for last line with different indent
				$tomodify = array();
				$current_max2 = null;
				$current_max3 = null;
			}

			// @@@current_max@@@ : 2/2
			if (strlen($match[2]) > $current_max2) $current_max2 = strlen($match[2]);
			if (strlen($match[3]) > $current_max3) $current_max3 = strlen($match[3]);
			$tomodify[$k] = $line;

			if ($last_line && $current_indent != $last_indent) {
				// exception for last line with different indent
				// @@@tomodify@@@ : 2/3
				foreach ($tomodify as $k2 => $line2) {
					preg_match($line_pattern, $line2, $match2)
						|| preg_match($line_pattern2, $line2, $match2);
					$match2[4] = str_replace('array[0]', 'array(0)', $match2[4]);
					if (strstr($match2[4],'(')) {
						if (preg_match('#^\s*\(\d+\)$#', $match2[4])) {
							// ex. object(ReflectionFunction)#1 (1)
						} else {
							$match2[4] = ' ('. trim(str_replace(array('(',')'),array(':',''), $match2[4])).')';
						}
					} else {
						$match2[4] = ' ('.trim($match2[4]).')';
					}
					if ($current_max3 > $max_line_length) $current_max3 = $max_line_length;
					if (strlen($match2[3]) > $max_line_length) {
						$match2[3] = urldecode($match2[3]);
						$match2[3] = preg_replace("#([^\r\n]{".$max_line_length.",".$max_line_length."})#", "\\1\r\n".str_repeat(' ',$current_max2+strlen($match2[1])), $match2[3]);
					}
					$lines[$k2] = $match2[1].str_pad($match2[2], $current_max2).str_pad($match2[3], $current_max3).$match2[4];
				}
			}

			$last_indent = $current_indent;
		} else {
			$last_indent = -1;
			$last_line = ($k == (count($lines)-1));
			if ($last_line) {
				// If last line was empty, preg_match(line_pattern) failed
				// @@@tomodify@@@ : 3/3
				if (count($tomodify) > 1) {
					foreach ($tomodify as $k2 => $line2) {
						preg_match($line_pattern, $line2, $match2)
							|| preg_match($line_pattern2, $line2, $match2);
						$match2[4] = str_replace('array[0]', 'array(0)', $match2[4]);
						if (strstr($match2[4],'(')) {
							if (preg_match('#^\s*\(\d+\)$#', $match2[4])) {
								// ex. object(ReflectionFunction)#1 (1)
							} else {
								$match2[4] = ' ('. trim(str_replace(array('(',')'),array(':',''), $match2[4])).')';
							}
						} else {
							$match2[4] = ' ('.trim($match2[4]).')';
						}
						if ($current_max3 > $max_line_length) $current_max3 = $max_line_length;
						if (strlen($match2[3]) > $max_line_length) {
							$match2[3] = urldecode($match2[3]);
							$match2[3] = preg_replace("#([^\r\n]{".$max_line_length.",".$max_line_length."})#", "\\1\r\n".str_repeat(' ',$current_max2+strlen($match2[1])), $match2[3]);
						}
						$lines[$k2] = $match2[1].str_pad($match2[2], $current_max2).str_pad($match2[3], $current_max3).$match2[4];
					}
				}
			}
		}
	}
	foreach ($lines as $k => $line) {
		$cssclass = '';
		$cssclass = $k % 2 == 0 ? 'line-even' : 'line-odd';
		// onmouseover="if (!this.className.length) this.style.backgroundColor=\'#ddd\';"
		// onmouseout="if (!this.className.length) this.style.backgroundColor=\'#f0f0f0\'"
		// ondblclick =this.style.backgroundColor=\'#c5c5c5\';
		$lines[$k] = '<div class="'.$cssclass.'">'.debug_ehtml($line).'</div>';
	}
	$string = implode($lines, "");
	$string = preg_replace(
		'#(password|pass|passwd|pwd|pw|hash|md5):\s+[^\r\n]{0,50}\s+\(string:\d+\)#Ui',
		'$1: ****',
		$string
	);
	return $string;
}
function debug_print_var_doubleindent($s)
{
	$s = preg_replace('#^[ ]{2,2}#', '', $s);
	return str_repeat($s, 2);
}
function debug_print_var_striplines(&$var, $recursion = 1)
{
	// change also recursion at debug_trace_args()
	if ($recursion > 3) return;
	if (is_array($var)) {
		foreach ($var as $k => $v) {
			if (is_array($v)) {
				debug_print_var_striplines($var[$k], ++$recursion);
			} else if (is_string($v)) {
				$var[$k] = str_replace(array("\r\n", "\n", "\r"), array('\r\n', '\n', '\r'), $var[$k]);
			}
		}
	} else if (is_string($var)) {
		$var = str_replace(array("\r\n", "\n", "\r"), array('\r\n', '\n', '\r'), $var);
	}
}
function debug_escape_once($s)
{
	$s = str_replace(array('&lt;','&gt;','&amp;lt;','&amp;gt;'),array('<','>','&lt;','&gt;'),$s);
	return str_replace(array('&lt;','&gt;','<','>'),array('&amp;lt;','&amp;gt;','&lt;','&gt;'),$s);
}
function debug_escape($html)
{
	// escape new lines & html special chars
	$html = str_replace(array("\r\n", "\n", "\r"), array('\r\n', '\n', '\r'), $html);
	$html = debug_ehtml($html);
	return $html;
}
function debug_ehtml($html)
{
	$undo = array(
		'&amp;' => '&',
		'&lt;' => '<',
		'&gt;' => '>',
		'&quot;' => '"',
		'&#039;' => "'"
	);
	// first undo escaped tags, then htmlspecialchars
	$html = str_ireplace(array_keys($undo), array_values($undo), $html);
	$html = htmlspecialchars($html);
	$arr = explode(' ',$html);
    $html = isset($arr[1])?str_replace($arr[1],sprintf('<a style="display:inline;text-decoration: none;" href="javascript:location.href=\'notepad2://\'+((\'%s\'.indexOf(\':\')==-1)?\'http://\'+location.host:\'\')+\'%s/?1\'"><b>%s</b></a>',$arr[1],$arr[1],$arr[1]),$html):$html;
	return $html;
}
function debug_print_r($var)
{
	if (func_num_args() > 1) $var = func_get_args();
	return print_r($var, true);
}
function debug_var_export($var)
{
	// Php parsable representation.
	if (func_num_args() > 1) $var = func_get_args();
	return var_export($var, true);
}
function debug_var_dump($var)
{
	// Detailed information including var type (string, int, null), string length, array length.
	if (func_num_args() > 1) $var = func_get_args();
	ob_start();
	var_dump($var);
	return ob_get_clean();
}
function debug_query_color($query)
{
	$query = preg_replace('/(\w),(\w)/', '$1, $2', $query);
	$query = preg_replace('/\s+/', ' ', $query);
	$query = trim($query);

	$query = debug_escape_once($query);

	$color = 'blue';

	$words = array('SELECT', 'UPDATE', 'DELETE', 'FROM', 'LIMIT', 'OFFSET', 'AND', 'INNER JOIN', 'OUTER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'JOIN', 'WHERE', 'SET', 'NAMES', 'ORDER BY', 'GROUP BY', 'GROUP', 'DISTINCT', 'COUNT', 'COUNT\(\*\)', 'IS', 'NULL', 'IS NULL', 'AS', 'ON', 'INSERT INTO', 'VALUES', 'BEGIN', 'COMMIT', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'IN', 'NOT', 'LIKE', 'ILIKE', 'ASC', 'DESC', 'LOWER', 'UPPER');

	$words = implode('|', $words);

	$nlwords = array('SELECT', 'UPDATE', 'DELETE', 'FROM', 'LIMIT', 'OFFSET', 'INNER JOIN', 'OUTER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'JOIN', 'WHERE', 'ORDER BY', 'GROUP_BY', 'GROUP', 'INSERT INTO', 'VALUES');
	$nlwords = implode('|', $nlwords);

	$query = preg_replace("#\s+({$nlwords})#", "\n".'$1', $query);

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

	$query = nl2br($query);

	return $query;
}
function debug_query($query)
{
	$query = debug_query_color($query);
	return debug_pre($query, '', 12, 'white-space: normal; max-height: 178px; overflow: auto;');
}


// -------- debug console


function debug_console($display=false)
{

	if (defined('DEBUG_CONSOLE_HIDE') && DEBUG_CONSOLE_HIDE) {
		return;
	}
	if (debug_dev_dir()&&!defined('DEBUG_FB_SHOW')) {
		return;
	}
    if(defined('DEBUG_CONSOLE') && !DEBUG_CONSOLE) return;
    if(DEBUG_AJAX)return;

	global  $Db;

	$db_time = null;
	if (isset($_SERVER['FB_DATA']['debug_time'])) {
		$db_time = $_SERVER['FB_DATA']['debug_time'];
	} else if (isset($Db['DebugTime'])) {
		$db_time = $Db['DebugTime'];
	}

	$db_cqueries = null;
	if (isset($_SERVER['FB_DATA']['record']['debug_queries'])) {
		$db_cqueries = count($_SERVER['FB_DATA']['record']['debug_queries']);
	} else if (isset($Db['DebugQueries'])) {
		$db_cqueries = count($Db['DebugQueries']);
	}

	$db_enabled = false;
	if (isset($db_time) && isset($db_cqueries)) {
		$db_enabled = true;
	}

	static $called = false;
	if ($called) {
		return;
	} else {
		$called = true;
	}

	$memory = memory_get_peak_usage() - DEBUG_CONSOLE_MEMORY;

	if ($memory < 1024) $memory = number_format(($memory)/1024,2).' KB';
	else if ($memory < 1024*1024) $memory = round(($memory)/1024).' KB';
	else $memory = number_format(($memory)/(1024*1024),2).' MB';

	$mysql_time = isset($db_time) ? $db_time : 0;

	$php_time = number_format(microtime(true) - DEBUG_CONSOLE_TIME - $mysql_time, 2);
	$mysql_time = $mysql_time ? number_format($mysql_time, 2) : 'none';
	$hide = DEBUG_COOKIE == -1?'show':'hide';
	$ret = "<div class='debug_console' id='DebugConsole'>";
	$ret .= "<div>php: $php_time";
	if ($_SERVER['FB_DATA'] || $Db) $ret .= " - mysql: $mysql_time ";
	$ret .= " - mem: $memory</div>";
	$ret .= '<div>';
	//if(FB_DEBUG_MIAN)
	{
		$err = DEBUG_TEMP.'/xdebug-error.'.XDEBUG_TIME.'.html';
		if(file_exists($err)){
			$ret .= '<a target="_blank" href="'.XDEBUG_HTTP_HOST.DEBUG_DIR.'/?timeouterr='.XDEBUG_TIME.'">Error</a> - ';
		}
		$ret .= '<a title="look history" target="_blank" href="'.XDEBUG_HTTP_HOST.DEBUG_DIR.'/?hist">hist</a> - ';
		$log = DEBUG_TEMP.'/'.XDEBUG_TIME_REAL.'.log';
		if(file_exists($log)){
			$ret .= '<a target = "_blank" href = "'.XDEBUG_HTTP_HOST.DEBUG_DIR.'/?timelog='.XDEBUG_TIME_REAL.'" > Log</a> - ';
		}
	}


	$ret .= '<a href="javascript:debug_cookie_toggle();">'.$hide.'</a> - <a href="javascript:debug_cookie_clear();">clear</a>';
	if (((defined('DEBUG_FDB') && DEBUG_FDB) || (defined('DbDebug') && DbDebug)) && defined('DEBUG_FDB_SCRIPT_TIME') && $db_enabled) {
		$ret .= ' - <a href="javascript:void(0)" id="fa_db_'.XDEBUG_TIME.'" onclick="debug_popup(\''.DEBUG_FDB_SCRIPT_TIME.'\',800,500)">db ('.$db_cqueries.')</a>';
		$xdebug_sep = true;
	}
	if (defined('XDEBUG_TRACE_SCRIPT')) {
		if (isset($xdebug_sep)) $ret .= ' - ';
		if (defined('XDEBUG_STARTED')) {
			$ret .= ' <a href="javascript:void(0)" id="fa_trace_'.XDEBUG_TIME.'" onclick="debug_popup(\''.XDEBUG_TRACE_SCRIPT.'?time='./*filemtime(XDEBUG_XT_FILE.'.xt')*/XDEBUG_TIME.'\', 800,650);">trace</a> ';
		} else {
			$ret .= ' trace ';
		}

		$ret .= '<a class="'.(defined('XDEBUG_STARTED')?'stop':'start').'" href="javascript:void(0)" onclick="if('.(defined('XDEBUG_STARTED')?1:0).') {this.innerHTML=\'[stop]\'; this.className=\'stop\'; debug_cookie_set(\'xdebug-trace\',2);;debug_cookie_clear(); location.reload();} else {this.innerHTML=\'[start]\'; this.className=\'start\'; debug_cookie_set(\'xdebug-trace\',1);location.reload();}">['.(defined('XDEBUG_STARTED')?'stop':'start').']</a>';
	}
	$ret .= "</div>";
	$ret .= '</div>';
$debug_time = XDEBUG_TIME;
$script = <<<SCRIPT
<style type="text/css">
.debug_console { position: fixed; bottom: 0.5em; right: 0.5em; color: #666; z-index: 50000; text-align: right; background: #f5f5f5; padding: 0.25em 0.5em; border: #ccc 1px solid; border-style: solid none none solid; font: normal 11px/16px "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif; text-decoration: none; padding: 2px 8px !important; border: 1px solid #bbb;	-moz-border-radius: 11px; -khtml-border-radius: 11px; -webkit-border-radius: 11px; border-radius: 11px; -moz-box-sizing: content-box; -webkit-box-sizing: content-box; -khtml-box-sizing: content-box; box-sizing: content-box; color: #464646;}
.debug_console div { margin: 0.1em 0em; }
.debug_console a { display:inline; text-decoration: none; color: #21759b; }
.debug_console a.start { }
.debug_console a.stop { color: #d54e21; }
</style>
<script>
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
var debug_cookie = {
    'time' : 0,
    'path' : '/'
};
function debug_cookie_toggle()
{
	var v = debug_cookie_get('xdebug-trace');
	if(v==-1)debug_cookie_set('xdebug-trace',0)
		else debug_cookie_set('xdebug-trace',-1);
	window.top.location.reload();

}
function debug_cookie_get(name)
{
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; ++i) {
        var a = cookies[i].split('=');
        if (a.length == 2) {
            a[0] = a[0].replace(/^\s*|\s*$/g, '');
            a[1] = a[1].replace(/^\s*|\s*$/g, '');
            if(name){
            if (a[0] == name) return unescape(a[1])
            }else{
            if(/^\d{4}\.\d{2}:\d{2}:\d{2}$/.test(a[0])) debug_cookie_del(a[0]);
            };
        }
    }
    return '';
}
function debug_cookie_set(name, value, time, path)
{
    if (typeof time == 'undefined') time = debug_cookie.time;
    var cookie = (name + '=' + escape(value));
    if (time) {
        var date = new Date(new Date().getTime()+time*1000);
        cookie += ('; expires='+date.toGMTString());
    }
    path = path ? path : debug_cookie.path;
    if (path) cookie += '; path='+path;
    document.cookie = cookie;
}
function debug_cookie_del(name, path)
{
    var cookie = (name + '=');
    path = path ? path : debug_cookie.path;
    if (path) cookie += '; path='+path;
    cookie += '; expires=Thu, 01-Jan-70 00:00:01 GMT';
    document.cookie = cookie;
}
function debug_cookie_clear()
{
    debug_cookie_get();
}
function open_trace(id){
	if(this.fb_trace && !this.fb_trace.closed){
		this.fb_trace.open_trace(id);
	}else{
		fa_trace_$debug_time.click();
		this.fb_trace.onload = function (){
			this.open_trace(id);
		}
	}
}
function open_db(id){
	if(this.fb_db && !this.fb_db.closed){
		this.fb_db.open_db(id);
	}else{
		fa_db_$debug_time.click();
		this.fb_db.onload = function (){
			this.open_db(id);
		}
	}
}
</script>
SCRIPT;

	$ret = $script . $ret;

	if ($display) echo $ret;
	else return $ret;
}
function fb_debug_start()
{
	if (debug_dev_dir()) {
		return;
	}

	static $called;
	if ($called) return;
	else $called = true;

	if (function_exists('xdebug_start_trace') && FB_DEBUG_FORCE) {
		ini_set('xdebug.collect_includes', 1);
		xdebug_start_trace(XDEBUG_XT_FILE, 2);
		define('XDEBUG_STARTED', 1);
	}
	define('DEBUG_CONSOLE_MEMORY', memory_get_usage());
	define('DEBUG_CONSOLE_TIME', microtime(1));
}
function fb_debug_stop()
{
	if (debug_dev_dir()) {
		return;
	}
	static $called;
	if ($called) return;
	else $called = true;

	if (defined('XDEBUG_STARTED')) {
		xdebug_stop_trace();
	}
}

?>
