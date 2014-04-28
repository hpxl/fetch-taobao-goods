<?php
/**
 * 采集商品，暂支持淘宝、天猫商品
 *
 * @author hpxl <lvwl.cn@163.com>
 */
if (substr(php_sapi_name(), 0, 3) !== 'cli') {
    die("This Programe can only be run in CLI mode\n");
}

// 记录进程是否在运行
define("PIDFILE", '/tmp/fetchgoods.pid');
file_put_contents(PIDFILE, getmypid());
function removePidFile() {
    unlink(PIDFILE);
}
register_shutdown_function('removePidFile');

// 店铺url
$shop_url = trim($argv[1]);

// 检查店铺地址是否正确
if (empty($shop_url)) {
    echo "参数错误，请输入店铺地址\n";
    exit(-1);
}

include 'HttpFetch.class.php';
include 'FetchGoods.class.php';

// 执行商品采集
$FetchGoods = new FetchGoods();
$FetchGoods->run($shop_url);
