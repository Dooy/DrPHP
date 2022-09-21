<?php
/**
 * 入口页
 * User: Administrator
 * Date: 2017/5/8 0008
 * Time: 下午 8:24
 */
if( isset($_GET['debug'])){
    ini_set("display_errors","On");
    error_reporting(E_ALL);
}else {
    error_reporting(E_ERROR   | E_PARSE);
}
date_default_timezone_set("PRC");

#文件根目录
define('ROOT_PATH', dirname( dirname( __FILE__ )));

#app根目录
define('APP_PATH', ROOT_PATH.'/app/');

#www 目录
define('WWW_ROOT','/');

#cooke 秘钥
define('COOKIE_SECKEY','zLxWtFYqlWx6f0VKCnQBADyqELcWOvqU');

#资源根目录（js、css、jpg\png\gif 等静态文件） 布置在线上可用cdn目录
define('WWW_RES','/res/');

#date_default_timezone_set("PRC");
include(ROOT_PATH.'/app/dr.php');
include ROOT_PATH.'/lib/function.php';
use DR\DR;
$dr = new DR();
$dr->run();
