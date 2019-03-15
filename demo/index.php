<?php
//require_once '../src/Ip2Region.class.php';//使用composer无需手动引入
//$ip2region = new \Ip2Region();//适用于命名空间
$ip2region = new Ip2Region();//适用于没有使用命名空间
$ip = '113.205.63.37';
$info = $ip2region->btreeSearch($ip);
//测试输出
header('Content-Type: text/html; charset=utf8');
print_r($info);