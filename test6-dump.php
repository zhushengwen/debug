<?php

include_once './lib/dump.php';

function test5()
{
	include './testdata/test0.php';
	$test = array('int'=>1, 'float'=>2.0, 'float2'=>2.1);
	dump($test, $_SERVER);
}
function test1() { test2(); }
function test2() { test3(); }
function test3() { test4(); }
function test4() { test5(); }

test1();

?>