<?php
// dump_tofile()
//
// This function is useful while debugging when:
// - you don't want to stop the script execution.
// - you can't display data, because for example it is an ajax request.
// - you want to debug in more than one place through the request.

include_once './lib/dump.php';


function test5()
{
	include './testdata/test0.php';
	$test = array('int'=>1, 'float'=>2.0, 'float2'=>2.1);
	dump_tofile('test7.html', $test, $_SERVER);
	echo 'Dump saved to file: <a href="test7.html">test7.html</a>';
	$lines = file(__FILE__);
	echo '<br><br>';
	for ($i = 4; $i <= 7; $i++) {
		$line = $lines[$i - 1];
		printf('<div>%s</div>', $line);
	}
}
function test1() { test2(); }
function test2() { test3(); }
function test3() { test4(); }
function test4() { test5(); }

test1();

?>