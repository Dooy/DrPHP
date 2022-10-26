<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/10
 * Time: 23:39
 */
if( $_SERVER['SERVER_ONLINE']=='ON'){
    $conf_db = array(
        'dbhost' => '192.168.1.88',    //主机地址
        'dbuser' => 'test',        //用户名
        'dbpass' => '',    //密码
        'dbname' => 'test',    //数据库名
        'dbport' => '3306',
    );
}elseif( $_SERVER['SERVER_ONLINE']=='aliyun'  ) {

    $conf_db = array(
         'dbhost' => '192.168.1.88',    //主机地址
        'dbuser' => 'test',        //用户名
        'dbpass' => '',    //密码
        'dbname' => 'test',    //数据库名
        'dbport' => '3306',
    );

}elseif( $_SERVER['SERVER_ONLINE']=='qqyun' || _SERVER_ONLINE =='qqyun' ) {
    $conf_db = array(
        'dbhost' => '192.168.1.88',    //主机地址
        'dbuser' => 'test',        //用户名
        'dbpass' => '',    //密码
        'dbname' => 'test',    //数据库名
        'dbport' => '3306',
    );
}else {
    $conf_db = array(
         'dbhost' => '192.168.1.88',    //主机地址
        'dbuser' => 'test',        //用户名
        'dbpass' => '',    //密码
        'dbname' => 'test',    //数据库名
        'dbport' => '3306',
    );
}
