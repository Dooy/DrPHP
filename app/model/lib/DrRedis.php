<?php
/**
 * redis的处理
 * User: Administrator
 * Date: 2017/9/9
 * Time: 14:40
 */

namespace model\lib;


class DrRedis extends \Redis
{
    private $is_content=false ;
    private static $log=[];
    function __construct( $server , $port )
    {
        $this->is_content = $this->pconnect(  $server , $port ,2 );
    }
    function setLog( $log ){
        self::$log[]=$log;
        return $this;
    }
    function getLog(){
        return self::$log;
    }
    function __destruct()
    {
        // TODO: Implement __destruct() method.
        //parent::__destruct();
        if( $this->is_content ) $this->close();
    }
    function set($key, $value, $timeout = 0)
    {
        return parent::set($key, is_array($value)?json_encode($value): $value , $timeout);
    }

    function hSet( $key,$hashKey, $value ){
        return parent::hSet($key,$hashKey, is_array($value)?json_encode($value): $value );
    }
    function hGet($key, $hashKey)
    {
       $str= parent::hGet($key, $hashKey);
       $arr= json_decode($str,true );
       return $arr?$arr:$str;
    }
}