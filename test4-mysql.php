<?php
#include_once './auto_prepend.php';

#include './lib/db-mysql.php';
db_connect(array(
	'host' => 'localhost',
	'user' => 'root',
	'pass' => 'admin',
	'dbname' => 'test',
	'charset' => 'utf8'
));
db_query('CREATE TABLE IF NOT EXISTS testdebug (id int PRIMARY KEY)');
db_query('DELETE FROM testdebug WHERE 1=1');
db_insert('testdebug', array('id'=>1));
db_insert('testdebug', array('id'=>2));
db_insert('testdebug', array('id'=>3));
$id1 = db_one('SELECT id FROM testdebug WHERE id = %id', array('id'=>1));
$row_id2 = db_row('SELECT * FROM testdebug WHERE id = %0', array(1));
$assoc = db_assoc('SELECT id, id FROM testdebug ORDER BY id');
#debug($assoc);
fb($assoc);
#include_once './auto_append.php';
?>