<?php
require_once '../src/Ip2Region.class.php';
//$ip2region = new \Ip2Region();//适用于命名空间
$ip2region = new Ip2Region();
$ip = '113.205.63.37';
$info = $ip2region->btreeSearch($ip);
header('Content-Type: text/html; charset=utf8');
print_r($info);