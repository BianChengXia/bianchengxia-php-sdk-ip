# 编程侠IP地址库PHP版

# 1. 99.9%准确率，定时更新：
数据聚合了一些知名ip到地名查询提供商的数据，这些是他们官方的的准确率，经测试着实比纯真啥的准确多了。
每次聚合一下数据需要1-2天，会不定时更新。

# 2. 标准化的数据格式：
每条ip数据段都固定了格式：城市ID|国家|区域|省份|城市|ISP

只有中国的数据精确到了城市，其他国家只能定位到国家，后前的选项全部是0，已经包含了全部你能查到的大大小小的国家。 （请忽略前面的城市Id，个人项目需求）

# 3. Composer 安装
<pre>
composer require bianchengxia/ip
</pre>
# 4. 使用方法
<pre>
require_once '../src/Ip2Region.class.php';//使用composer无需手动引入
//$ip2region = new \Ip2Region();//适用于命名空间
$ip2region = new Ip2Region();//适用于没有使用命名空间
$ip = '113.205.63.37';
$info = $ip2region->btreeSearch($ip);
if (!empty($info)) {
    $info = explode('|', $info);
}
-------------------------------------
Array
(
    [0] => 中国
    [1] => 0
    [2] => 重庆
    [3] => 重庆市
    [4] => 联通
)
</pre>
