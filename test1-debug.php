<?php
include_once './lib/debug.php';

function test($args)
{
	test_nested($args);
}
function test_nested($args)
{
	debug($args);
	// or: debug(get_defined_vars());
	// or: debug();
}
test(array('id'=>123, 'str'=>'test'));

?>