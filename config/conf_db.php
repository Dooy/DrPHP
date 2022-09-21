<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/10
 * Time: 23:39
 */
if( $_SERVER['SERVER_ONLINE']=='ON'){
    $conf_db = array(
        'dbhost' => '192.168.1.22',    //主机地址
        'dbuser' => 'cikuu',        //用户名
        'dbpass' => 'cikuutest!',    //密码
        'dbname' => 'haoce_org',    //数据库名
        'dbport' => '3306',
    );
}elseif( $_SERVER['SERVER_ONLINE']=='aliyun'  ) {

    $conf_db = array(
        'dbhost' => 'rm-j6cy3hzo646144q1833150.mysql.rds.aliyuncs.com',    //主机地址
        'dbuser' => 'qunfu',        //用户名
        'dbpass' => 'Qunfu201908',    //密码
        'dbname' => 'qunfu',    //数据库名
        'dbport' => '3306',
    );

}elseif( $_SERVER['SERVER_ONLINE']=='qqyun' || _SERVER_ONLINE =='qqyun' ) {
    $conf_db = array(
        //'dbhost' => '10.66.91.55',    //主机地址
        'dbhost' => 'db.qqyun.com',    //主机地址
        'dbuser' => 'qunfu',        //用户名
        'dbpass' => 'qunfu201908',    //密码
        'dbname' => 'qunfu',    //数据库名
        'dbport' => '3306',
    );
}else {
    $conf_db = array(
        //'dbhost'	=> 'game.db',	//主机地址
        'dbhost' => 'localhost',    //主机地址
        'dbuser' => 'root',        //用户名
        'dbpass' => 'cikuutest!',    //密码
        'dbname' => 'qunfu',    //数据库名
        'dbport' => '3306',
    );
}