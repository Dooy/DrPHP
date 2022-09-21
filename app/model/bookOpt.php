<?php
/**
 * 书的配置清单
 * User: Administrator
 * Date: 2017/10/2
 * Time: 14:51
 */

namespace model;



/**
 * Class bookOpt
 * @package model
 */
class bookOpt extends model {

    private  $book;
    private $tb_opt='book_opt';
    private  static $cache=[];
    /**
     * bookOpt 初始化.
     * @param book $book
     */
    function __construct( $book )
    {
        $this->book= $book;
    }

    /**
     * 获取
     * @return book;
     */
    function getBook(){
        return $this->book;
    }

    /**
     * 初始化opt配置
     * @return array
     */
    function getOptConfig( $key='ALL'){

        $tags = $this->getBook()->getTagId( );
        $opt = [];
        $opt['s_start_time']= ['n'=>'选书开始时间','t'=>'datatime'];
        $opt['s_end_time']= ['n'=>'选书截止时间','t'=>'datatime'];
        $opt['end']= ['n'=>'答题截止时间','t'=>'datatime'];
        foreach ( $tags as $k=>$v  ) {
            $opt['tag_'.$k ]= ['n'=>$v['n'].'说明','t'=>'text'];
            $opt['word_'.$k ]= ['n'=>$v['n'].'字数','t'=>'int'];
        }

        if( $key=='ALL')     return $opt;
        if(! isset($opt[ $key ])) $this->throw_exception($key. " 未考虑类型",7201 );
        return $opt[ $key ];
    }

    /**
     * 检查核对
     * @param $optVar
     * @return $this
     */
    function check( $optVar ){
        if( $optVar['s_start_time'] &&   $optVar['s_end_time']  && strtotime($optVar['s_start_time']) >=strtotime($optVar['s_end_time']) ){
            $this->throw_exception( "选课开始时间必须小于截止时间" ,7202 );
        }
        if( $optVar['s_end_time'] &&   $optVar['end']  && strtotime($optVar['s_end_time']) >=strtotime($optVar['end']) ){
            $this->throw_exception( "选课截止时间必须小于答题截止时间" ,7203 );
        }
        if( $optVar['s_start_time'] &&   $optVar['end']  && strtotime($optVar['s_start_time']) >=strtotime($optVar['end']) ){
            $this->throw_exception( "选课开始时间必须小于答题截止时间" ,7204 );
        }

        return $this;
    }

    /**
     * 保存全部
     * @param $book_id
     * @param $optVar
     * @return $this
     */
    function saveAll( $book_id, $optVar ){

        $this->clear($optVar)->check( $optVar )->isNull($optVar ,$book_id)->save( $book_id, $optVar );;
        //$this->drExit( $optVar );
        return $this;
    }

    function isNull(&$optVar,$book_id){
        $old = $this->getOpt( $book_id ) ;
        foreach( $old as $k=>$v ){
            if( !isset($optVar[$k])) $optVar[$k]='';
        }
        return $this;
    }

    /**
     * 数据清洗
     * @param $optVar
     * @return $this
     */
    function clear( &$optVar){
        $config = $this->getOptConfig();
        foreach( $optVar as $k=>$v ){
            if( !isset( $config[$k] ) || !$optVar[$k] ){
                unset($optVar[$k] );
                continue;
            }

            $type= $config[$k]['t'];
            if($type =='int'){
                $optVar[ $k]= intval( $v );
            }
        }
        return $this;
    }

    /**
     * 按 key value 保存
     * @param $book_id
     * @param $k
     * @param $v
     * @return $this
     */
    function saveByKV( $book_id ,$k,$v ){
        $c_one= $this->getOptConfig( $k );
        $opt= $this->getOpt( $book_id );
        $opt[ $k]= $v ;
        $this->saveAll( $book_id, $opt );
        return $this;
    }

    /**
     * 直接获取解码后的
     * @param $book_id
     * @return array
     */
    function getOpt( $book_id ){
        if(! self::$cache[$book_id] ) {
            $row = $this->createSql()->select($this->tb_opt, ['id' => $book_id])->getRow();
            if (!$row) return [];
            self::$cache[$book_id] = $this->decode($row['obj']);
        }
        return self::$cache[$book_id] ;
    }

    /**
     * @param $book_id
     * @param $key
     * @param $chang_value
     * @return $this
     */
    function changOptByKey( $book_id ,$key, &$chang_value ,$opt=[] ){
        $this->getOpt( $book_id );
        if( isset( self::$cache[$book_id][$key])) {
            $chang_value  = self::$cache[$book_id][$key];
            if( $opt['t']=='datetime' ) $chang_value= strtotime( $chang_value );
            return  $this;
        }
        return $this;
    }

    /**
     * 保存
     * @param $book_id
     * @param $optVar
     * @return $this
     */
    private function save( $book_id, $optVar ){
        $where = ['id'=>$book_id];
        $id  = $this->createSql()->select( $this->tb_opt,$where,[],['id'] )->getOne();
        if( !$optVar && $id ){
            $this->createSql()->delete( $this->tb_opt, $where );
            return $this;
        }
        if( $id ){
            $this->createSql()->update(  $this->tb_opt,['obj'=> $this->encode($optVar)],$where )->query();
        }else{
            $this->createSql()->insert(  $this->tb_opt ,['id'=>$book_id, 'obj'=> $this->encode($optVar) ] )->query();
        }
        return $this;
    }

    private  function encode( $arr ){
        return drFun::json_encode( $arr );
    }


    private function decode( $str ){
        return drFun::json_decode( $str);
    }
}