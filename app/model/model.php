<?php

namespace model;


use DR\DR;
use model\user\login;

/**
 * model类的初始 很多mobel都继承本类
 * User: Administrator
 * Date: 2017/5/11 0011
 * Time: 下午 7:39
 */

class model extends DR
{
    static  private $_db= false ;
    private $cl_login = null;

    public function createSql(){

        $arr = func_get_args();
        $arr_cnt = count( $arr );
        if($arr_cnt>1 ) return new sql( $arr[0], $arr[1]);
        if($arr_cnt>0 ) return new sql( $arr[0] );

        return new sql();
    }

    /**
     * 设置login class
     * @param $login
     * @return $this
     */
    function setLogin( $login ){
        $this->cl_login = $login;
        return $this;
    }

    /**
     * 获取类Login
     * @return login
     */
    function getLogin(){
        if(  !$this->cl_login ) return new login();
        return $this->cl_login;
    }

    /**
     * @return DR_DB
     */
    public function db(){
        if( self::$_db != false ) return self::$_db;
        self::$_db= new DR_DB();
        return self::$_db;
    }

    public function createClassPage( $total, $opt=[] ){
        $page = new page( $total );
    }

    /**
     * 插入表格 返回lastID 带筛选+检查空
     * @param string $table
     * @param array $kv 必须为 [k=>v,k2=>v2]
     * @param array $file_kv [file1,file2] [file1=>1,file2=>[n=>'电话']] 当file2=>[n=>'电话'] 表示电话不为空
     * @return int
     * @throws  drException
     */
    public function insert( $table, $kv,$file_kv=[]){
        if($file_kv){
            foreach( $kv as $k=>$v ){
                if(!( isset( $file_kv[$k])|| in_array( $k,$file_kv))) unset( $kv[$k]);
            }
            foreach( $file_kv as $k=>$v  ){
                if( isset($v['n']) and  (!isset($kv[$k]) || !$kv[$k]  )){
                    $this->throw_exception( $v['n'].' 不允许为空',414  );
                }
            }
        }

        return $this->createSql()->insert( $table, $kv)->query()->lastID();
    }

    /**
     * 更新数据
     * @param string $table
     * @param array $where
     * @param array $kv 必须为 [k=>v,k2=>v2]
     * @param array $file_kv [file1,file2] or [file1=>1,file2=>n]
     * @return $this
     * @throws \Exception
     */
    public function update( $table, $where,$kv,$file_kv=[]){
        if($file_kv){
            foreach( $kv as $k=>$v ){
                if($k=='+' || $k=='-') continue;
                if(!( isset( $file_kv[$k])|| in_array( $k,$file_kv))) unset( $kv[$k]);
            }
        }
        $this->createSql()->update( $table, $kv,$where )->query();
        return $this;
    }



}