<?php
include $_SERVER['DOCUMENT_ROOT'] .'/fb.php';
echo '<h1>test3: trace</h1>';
function test1()
{
	test2();
}
function test2()
{
	for ($i = 0; $i < 100; $i++) {
		$s = substr('', 0, 1);
	}
}
fb(array('hhhh'=>'123456'));
?>