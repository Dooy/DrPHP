<?php
/**
 * 缓存处理
 * User: Administrator
 * Date: 2017/9/9
 * Time: 14:59
 */

namespace model\lib;


use model\model;

class cache extends model
{

    static $cacheClass;
    /**
     * 获取cache的class
     * @return cacheInterface
     */
    function getClass(){
        if( self::$cacheClass) return self::$cacheClass;

        if( extension_loaded('Redis')){
            self::$cacheClass= new cacheRedis( );

            return  self::$cacheClass;
        }
        $this->log( 'Redis plus is not exit 插件不存在 无法使用redis 缓存！' );
        self::$cacheClass= new cacheFile( );
        return self::$cacheClass;
    }

    /**
     *
     * @return DrRedis
     */
    function getRedis(){
        if($this->getClass()->getType()=='redis' ) return $this->getClass()->getRedis();
        $this->throw_exception("目前未使用REDIS", 1042);
    }
}

/**
 * 文件缓存
 * Class cacheFile
 *
 */
class cacheFile extends cacheInterface{

    function get($key)
    {
        // TODO: Implement get() method.
    }
    function getObjNotByFun($key, $fun, $timeout)
    {
        // TODO: Implement getObjNotByFun() method.
    }
    function set($key, $value, $timeout)
    {
        // TODO: Implement set() method.
    }

    function del($key)
    {
        // TODO: Implement del() method.
    }
    function getType()
    {
        return 'file';
    }

}

/**
 * redis
 * Class cacheRedis
 *
 */
class cacheRedis extends cacheInterface{
    static $drRedis;

    /**
     * 获取redis
     * @return DrRedis
     */
    function getRedis(){
        if(! self::$drRedis ){
            self::$drRedis= new DrRedis('redis.server.haoce.com',6379);
        }
        return  self::$drRedis;
    }

    function get($key)
    {
        return $this->getRedis()->get( $key );
    }
    function getObjNotByFun($key, $fun, $timeout=600)
    {
        // TODO: Implement getObjNotByFun() method.
    }
    function set($key, $value, $timeout=600 )
    {
        $rf= $this->getRedis()->set( $key,$value,10 );
        if( $rf && $timeout>0 ) $this->getRedis()->expire( $key, $timeout);
        return $rf;
    }

    function del($key)
    {
        return $this->getRedis()->del( $key );
    }
    function getType()
    {
        return 'redis';
    }
}

/**
 * 缓存
 * Class cacheInterface
 *
 */
abstract class cacheInterface extends model{
    abstract function get( $key);
    abstract function set( $key,$value,$timeout);
    abstract function getObjNotByFun( $key,$fun,$timeout );
    abstract function del( $key);
    abstract function getType(  );
}