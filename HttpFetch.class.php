<?php
/**
 * http fetch class
 *
 * @author hpxl <lvwl.cn@163.com>
 */
class HttpFetch
{
    // 代理IP列表
    private $_proxylist = array();

    // 记录失败代理IP
    private $_failure_proxy = array();

    // debug
    public $debug = false;

    public function __construct() 
    {
        // 获取代理IP
        $this->_proxylist = $this->proxyList();
    }

    /**
     * 获取远程数据
     */ 
    public function get($url)
    {
        return $this->disguise_curl($url);
    }

    /**
     * 通过代理方式获取页面内容
     *
     * @param string $url 链接地址
     * @return string $html 远程数据内容
     */
    public function proxyGet($url)
    {
        // 尝试多次使用代理获取数据
        for ($i = 0; $i < 3; $i++) {

            // 获取随机代理IP
            $proxy_ip = $this->_getRandProxy();

            if ($this->debug) {
                $start = microtime(true);
            }

            // 获取远程内容
            $html = $this->disguise_curl($url, $proxy_ip);

            if ($this->debug) {
                $len = strlen($html);
                $used = microtime(true) - $start;
                echo "proxy_ip:{$proxy_ip} ... used:{$used} ... len:{$len}\n";
            }

            if (!empty($html)) {
                break;
            }

            // 记录失败IP信息
            if (!isset($this->_failure_proxy[$proxy_ip])) {
                $this->_failure_proxy[$proxy_ip] = 0;
            }
            $this->_failure_proxy[$proxy_ip] += 1;
        }

        // 代理获取失败，尝试本机获取
        if (empty($html)) {
            $html = $this->disguise_curl($url);
        }

        // 重置资源
        //$this->_failure_proxy = array();

        return $html;
    }

    /**
     * 获取一个随机代理IP
     */
    private function _getRandProxy()
    {
        $proxy_count = count($this->_proxylist);
        // 尝试多次获取随机代理IP，如果ip有失败记录，换其他ip
        for (;;) {
            // 获取随机代理IP
            $rand_key = mt_rand(0, $proxy_count - 1);
            $proxy_ip = $this->_proxylist[$rand_key];
            if (!isset($this->_failure_proxy[$proxy_ip])) {
                break;
            }
        }

        return $proxy_ip;
    }

    /**
     * 代理IP列表
     */
    public function proxyList()
    {
        $proxy_arr = array(
            '58.20.127.100:3128', // 湖南省长沙市 联通
            '218.108.168.69:82', // 浙江省杭州市
            '218.108.170.166:80', // 浙江省杭州市
            '139.210.98.86:8080', // 吉林省长春市联通
            '218.108.168.68:82', // 浙江省杭州市
            '111.161.126.84:80', // 天津市联通
        );

        return $proxy_arr;
    }

    /**
     * 模拟google抓取内容
     *
     * @link http://cn2.php.net/manual/en/function.curl-setopt.php#78046
     * @param string $url 链接地址
     * @param string $proxy 代理Ip信息，如：127.0.0.1:81
     * @return string $html 页面内容
     */
    public function disguise_curl($url, $proxy=null) 
    { 
        $curl = curl_init();

        // Setup headers - I used the same headers from Firefox version 2.0.0.6 
        // below was split up because php.net said the line was too long. :/ 
        $header = array();
        $header[0]  = "Accept: text/xml,application/xml,application/xhtml+xml,"; 
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5"; 
        $header[] = "Cache-Control: max-age=0"; 
        $header[] = "Connection: keep-alive"; 
        $header[] = "Keep-Alive: 300"; 
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7"; 
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Cookie: cna=l+pgC/cDdWcCAWVF+/5q+UVs; miid=3317872020099775032; __utma=6906807.434603219.1390489590.1390489590.1390489590.1; __utmz=6906807.1390489590.1.1.utmcsr=fuwu.taobao.com|utmccn=(referral)|utmcmd=referral|utmcct=/ser/detail.htm; x=e%3D1%26p%3D*%26s%3D0%26c%3D0%26f%3D0%26g%3D0%26t%3D0%26__ll%3D-1%26_ato%3D0; lzstat_uv=24182050841715729711|2581762@3201199@2945730@2948565@2798379@2043323@3045821@2805963@2738597@878758@2208862@3027305@3284827@2581759@2581747@2938535@2938538@2879138@3010391; ali_ab=60.186.203.48.1390489531936.4; l=asd890241::1397182032328::11; v=0; uc3=nk2=AmJam4TqFyWJ&id2=W8ncTY3m5dw%3D&vt3=F8dATHtpuP76kDFmmBg%3D&lg2=V32FPkk%2Fw0dUvg%3D%3D; existShop=MTM5NzYxMTM1NA%3D%3D; lgc=asd890241; tracknick=asd890241; sg=10d; cookie2=6b8940c7741e940084cc149db11f8b7c; mt=cp=0&np=&ci=1_1&cyk=0_0; cookie1=B0EwsR%2FpnjjSjqpUTsxO8woKrOXzJMXtv9vP4SUJA5I%3D; unb=80893640; t=43525df761cffb84c1297592f666dd75; publishItemObj=Ng%3D%3D; _cc_=UIHiLt3xSw%3D%3D; tg=0; _l_g_=Ug%3D%3D; _nk_=asd890241; cookie17=W8ncTY3m5dw%3D; pnm_cku822=117fCJmZk4PGRVHHxtNZngkZ3k%2BaC52PmgTKQ%3D%3D%7CfyJ6Zyd9OGcmY3YkZHYibx4%3D%7CfiB4D15%2BZH9geTp%2FJyN8PDJtLBMbCF4lHw%3D%3D%7CeSRiYjNhIHA3dWI0c2A4eGwmfz16PnhrNHJlMH1kJnc8bS1hfzoT%7CeCVoaEATTRBWFx1IEBRReHZYZg%3D%3D%7CeyR8C0obRRhYABdDABNTFAFGEU8XUxMFTgMVSREMSxxeG1MWCTMa%7CeiJmeiV2KHMvangudmM6eXk%2BAA%3D%3D; _tb_token_=3ee5e3b8e7690; uc1=lltime=1397569800&cookie14=UoLVYuvN1ebuMw%3D%3D&existShop=true&cookie16=Vq8l%2BKCLySLZMFWHxqs8fwqnEw%3D%3D&cym=1&cookie21=URm48syIZJTgtchfymSXVA%3D%3D&tag=3&cookie15=VFC%2FuZ9ayeYq2g%3D%3D";
        $header[] = "Pragma: "; // browsers keep this blank. 

        if (!is_null($proxy)) {
            curl_setopt ($curl, CURLOPT_PROXY, $proxy); 
        }

        curl_setopt($curl, CURLOPT_URL, $url); 
        curl_setopt($curl, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)'); 
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header); 
        curl_setopt($curl, CURLOPT_REFERER, 'http://www.google.com'); 
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate'); 
        curl_setopt($curl, CURLOPT_AUTOREFERER, true); 
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        $html = curl_exec($curl); // execute the curl command 
        curl_close($curl); // close the connection 

        return $html; // and finally, return $html 
    }
}
