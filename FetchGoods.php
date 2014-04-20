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

// 运行此文件
include 'HttpFetch.class.php';
$FetchGoods = new FetchGoods();
$FetchGoods->run($argv);

/** 
 * 采集商品类
 */
class FetchGoods
{
    // 采集类
    private $_httpFetch = null;

    // 数据存放地址
    public $data_dir = '';

    // 站点名称
    private $_sitename = '';

    // 店铺名称
    private $_shopname = '';

    // 商品总数
    private $_goods_total = 1;

    public function __construct() 
    {
        // 数据存放目录
        if (empty($this->data_dir)) {
            $this->data_dir = dirname(__FILE__). '/data';
        }
    }

    /**
     * 运行
     */
    public function run($argv)
    {
        // 检查店铺地址是否正确
        $shop_url = rtrim(trim($argv[1]), '/');
        if (empty($shop_url)) {
            echo "参数错误，请输入店铺地址\n";
            return false;
        }

        // 匹配站点、店铺名称
        $preg_name = '/(http:\/\/)?(\w+)\.(\w+)\./';
        preg_match($preg_name, $shop_url, $matches);
        $this->_shopname = $matches[2];
        $this->_sitename = $matches[3];

        // 检查站点名称
        if (!in_array($this->_sitename, array('taobao', 'tmall'))) {
            echo "暂不支持 '{$this->_sitename}' 站点采集，只支持淘宝、天猫\n";
            return false;
        }

        // 开始执行采集店铺商品
        $this->fetchOneShop($shop_url);
    }

    /**
     * 采集某个店铺商品
     */
    public function fetchOneShop($shop_url)
    {
        $url_arr = parse_url($shop_url);
        $shop_host = "{$url_arr['scheme']}://{$url_arr['host']}";
        $search_url = "{$shop_host}/search.htm?&mid=w-6029587454-0&search=y";

        $time_format = "m-d H:i:s";
        $start_time = date($time_format);
        echo "shop_url:{$shop_url} ... start_time:{$start_time} ... start!\n";

        // 实例化http采集类
        $this->_httpFetch = new HttpFetch();

        // 淘宝店铺所有宝贝列表需要登录更新cookie才可以抓取
        // 为了避免登录，采集店铺分类列表
        if ($this->_sitename == 'taobao') {
            // 获取店铺分类采集规则
            $rule_shop_category = $this->_getRules($this->_sitename, 'shop_category');
            $search_source = $this->_httpFetch->get($search_url);
            $category_urls = $this->collectAll($search_source, $rule_shop_category);
            $category_urls && $category_urls = array_flip(array_flip($category_urls));
            $search_source = null;
        } else {
            $category_urls = array($search_url);
        }

        // 执行页面商品采集
        foreach ($category_urls as $category_url) {
            echo "category_url:{$category_url}\n";
            $this->_fetchPageGoods($category_url);
        }

        $end_time = date($time_format);
        echo "shop_url:{$shop_url} ... end_time:{$end_time} ... finish!\n";
        return true;
    }

    /**
     * 采集某个分类商品
     * 
     * @param string $category_url 分类url
     */
    private function _fetchPageGoods($category_url)
    {
        // 暂停5s，尽量避免淘宝防采集功能
        $sleep = 5;

        // 获取商品ID 采集规则
        $rule_goods_id = $this->_getRules($this->_sitename, 'goods_id');

        // 最多采集20页
        for ($page_num = 1; $page_num < 20; $page_num++) {
            $page_url = "{$category_url}&pageNo={$page_num}";
            $page_source = $this->_httpFetch->proxyGet($page_url);

            // 该页没有找到商品，采集完成
            if (false !== strpos($page_source, '<p class="item-not-found">')) {
                break;
            }
            $goods_ids = $this->collectAll($page_source, $rule_goods_id);
            $goods_ids = array_flip(array_flip($goods_ids));
            $goods_num = count($goods_ids);
            echo "page_url:{$page_url} ... page_goods_num:{$goods_num}\n";
            foreach ($goods_ids as $k => $good_id) {
                $num = $k + 1;
                echo "goods_total:{$this->_goods_total} ... {$num}/{$goods_num} ... goods_id:{$good_id}\n";
                $this->_getGoodsData($good_id);
            }

            echo "sleep {$sleep}s\n";
            sleep($sleep);
        }
        return true;
    }

    /**
     * 处理商品数据
     *
     * @param int $good_id 商品ID
     */
    public function _getGoodsData($good_id)
    {
        $shop_dir = "{$this->data_dir}/{$this->_shopname}";
        $good_info_file = "{$shop_dir}/{$good_id}.php";

        // 站点名称
        if ($this->_sitename == 'tmall') {
            $good_url = "http://detail.tmall.com/item.htm?id={$good_id}";
        } else {
            $good_url = "http://item.taobao.com/item.htm?id={$good_id}";
        }
        echo "good_url:{$good_url} ... ";

        // 检查商品数据文件是否存在，存在不再重复采集
        if (file_exists($good_info_file)) {
            echo "exists!\n";
            return;
        }

        $good_source = $this->_httpFetch->proxyGet($good_url);
        $good_source = mb_convert_encoding($good_source, "UTF-8", "GBK");

        // 获取站点采集规则
        $rules = $this->_getRules($this->_sitename);

        // 数据数组
        $data = array();
        $params = array('name', 'intro', 'shop_intro', 'price');
        foreach ($params as $v) {
            $data[$v] = $this->collectOne($good_source, $rules[$v]);
        }

        // 检查采集是否成功
        if (empty($data['name'])) {
            echo "failure!\n";
            return;
        }

        // 获取商品描述
        $data['desc'] = '';
        $goods_desc_url = $this->collectOne($good_source, $rules['desc_url']); 
        if ($goods_desc_url) {
            $goods_desc = $this->_httpFetch->get($goods_desc_url);
            $goods_desc = ltrim($goods_desc, "var desc='");
            $goods_desc = rtrim($goods_desc, "';");
            $data['desc'] = mb_convert_encoding(stripcslashes($goods_desc), 'UTF-8', 'GBK');
        }

        // 检查目录是否存在
        $good_image_dir = "{$shop_dir}/img/{$good_id}";
        $this->checkDir($good_image_dir, true);

        // 匹配商品图片
        $data['product_img'] = array();
        $data['goods_img'] = $this->collectAll($good_source, $rules['product_img']);
        if ($data['goods_img']) {
            $goods_img_num = count($data['goods_img']);
            echo "img_num:{$goods_img_num} ... ";
            foreach ($data['goods_img'] as $img_url) {
                // 获取图片名称
                $img_name = end(explode('/', $img_url));
                $good_image = "img/{$good_id}/{$img_name}";
                $data['product_img'][] = $good_image;

                // 保存图片
                $img_file = "{$shop_dir}/{$good_image}";
                file_put_contents($img_file, $this->_httpFetch->get($img_url));
            }
        }

        echo "ok\n";

        // save info
        file_put_contents($good_info_file, "<?php\nreturn ". var_export($data, true). ";\n");

        // 已采集商品总数
        $this->_goods_total++;
        return true;
    }

    /**
     * 获取站点采集规则
     *
     * @param string $site_name 站点名称
     * @param string $name 某个站点规则名称
     * @return string|array 指定规则名称返回规则字符串，否返回某个站点规则
     */
    private function _getRules($site_name='taobao', $name='')
    {
        static $rules;

        if (empty($rules)) {
            // 淘宝
            $rules['taobao'] = array(
                'page_total' => '/class="page-info"\>\d+\/(\d+)\<\/span\>/', // 单页商品数目
                //'search-result' => '/class="search-result"\>\s+共搜索到\<span\>\s+(\d+)\s+\<\/span\>/', // 店铺总商品数
                'shop_category' => '/(http:\/\/.+?\/category-\d+\.htm\?)search=y&catName/',
                'goods_id' => '/class="item-name"\shref="http:\/\/item\.taobao\.com\/item\.htm\?id=(\d+)"/', // 商品ID
                'name' => '/\<h3 class="tb-item-title"\>(.+?)\<\/h3\>/is', // 商品名称
                'price' => '/\<em\sclass="tb-rmb-num"\>(.+?)\<\/em\>/', // 商品价格
                'intro' => '/\<ul\sclass="attributes-list"\>(.+?)\<\/ul\>/is', // 商品简介
                'product_img' => '/src="(http:\/\/.+?)_60x60\.jpg/', // 商品图片
                'desc_url' => '/"(http:\/\/dsc\.taobaocdn\.com\/.+?)"/', // 商品描述url
                'shop_intro' => '/\<p\sclass="base-info"\>(.+?)\<\/p\>/is', // 店铺简介
            );

            // 天猫
            $rules['tmall'] = array(
                'page_total' => '/class="ui-page-s-len"\>\d+\/(\d+)\<\/b\>/', // 总页数
                'goods_id' => '/class="item.+?data-id="(\d+)"/is', // 商品ID
                'name' => '/\<title\>(.+?)-tmall.+?\<\/title\>/is', // 商品名称
                'price' => ' /class="J_originalPrice"\>(.+?)\<\/strong\>/', // 商品价格
                'intro' => '/\<div\sclass="attributes-list"\sid="J_AttrList"\>(.+?)\<\/div\>\s+\<\/div\>/is', // 简介
                'product_img' => '/(http:\/\/.+?)_60x60q90\.jpg/',
                'desc_url' => '/"(http:\/\/dsc\.taobaocdn\.com\/.+?)"/', // 商品描述
                'shop_intro' => '/class="extend"\>(.+?)\<\/div\>/is', // 店铺简介
            );
        }

        $site_rules = $rules[$site_name];
        return ($name) ? $site_rules[$name] : $site_rules;
    }

    /**
     * 匹配一条数据
     *
     * @param string $source 网页源码
     * @param string $preg 采集规则
     * @return string|bool 成功返回结果，失败返回false
     */
    public function collectOne(&$source, $preg) 
    {
        if (preg_match($preg, $source, $matches)) {
            return trim($matches[1]);
        }
        return false;
    }

    /**
     * 匹配多条数据
     *
     * @param string $source 页面源码
     * @param string $preg 采集规则
     * @return array 匹配成功返回数据，否返回空数组
     */
    public function collectAll(&$source, $preg)
    {
        if (preg_match_all($preg, $source, $matches)) {
            return $matches[1];
        }
        return array();
    }

    /**
     * 建立目录
     * 
     * @param      string     $dirname 目录名
     * @param      int        $mode 建立后的目录权限
     * @param      bool       $recursive 是否支持多级目录建立，默认否
     * @access     public
     * @return     bool       成功返回true，失败返回false
     */
    public function createDir($dirname, $mode=0777, $recursive = false)
    {
        if (!$recursive) {
            $ret = @mkdir($dirname, $mode);
            if($ret) @chmod($dirname, $mode);
            return $ret;
        }
        if (is_dir($dirname)) {
            return true;
        } elseif ($this->createDir(dirname($dirname), $mode, true)) {
            $ret = @mkdir($dirname, $mode);
            if($ret) @chmod($dirname, $mode);
            return $ret;
        } else {
            return false;
        }
    }

    /**
     * 检查目录是否存在，不存在尝试自动建立
     * 
     * @param      string     $dirname 目录名
     * @param      bool       $autocreate 目录不存在是否尝试自动建立，默认否
     * @access     public
     * @return     bool       成功返回true，失败返回false
     */
    public function checkDir($dirname, $autocreate=false)
    {
        if (is_dir($dirname)) {
            return true;
        } else {
            if(empty($autocreate)) return false;
            else return $this->createDir($dirname, 0777, true);
        }
    }
}
