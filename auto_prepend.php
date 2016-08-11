<?php
function fe($a){echo '<pre>';var_dump($a);echo '</pre>';exit;}
if(0 || isset($_REQUEST['debug']))
{
  if(!isset($_SERVER['HTTP_HOST']) || !in_array($_SERVER['HTTP_HOST'],['phptest.ya0.cn','gocode.ya0.cn','uc.ya0.cn','42.228.4.166']))
  include "main.php";
}
