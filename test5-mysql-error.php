<?php
//include_once './auto_prepend.php';

include './lib/db-mysql.php';
db_connect(array(
	'host' => 'localhost',
	'user' => 'root',
	'pass' => 'admin',
	'dbname' => 'test',
	'charset' => 'latin2'
));
function test()
{
	db_query('CREATE TABLE asd (id int PRIMARY KEY)');
}
test();

//include_once './auto_append.php';
?>