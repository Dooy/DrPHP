<?php
/**
 * 处理crontab事物
 *
 * User: Administrator
 * Date: 2017/8/29
 * Time: 14:00
 */

namespace model;


use DR\DR;

class crontab extends DR
{
    private $handle = false ;
    function __construct( $file="" )
    {
        //parent::autoload_register();
        if( is_file($file )) {
            if (!is_writable($file)) $this->throw_exception("文件不可写", 2001);
            if (!$handle = fopen($file, 'w'))   $this->throw_exception("不能打开文件", 2002);
            $this->handle= $handle;
        }
    }
    function __destruct()
    {
       if(  $this->handle ){
           fclose( $this->handle);
       }
    }

    /**
     * 载入DB SQL类
     * @return sql
     */
    function createSql(){
        $arr = func_get_args();
        $arr_cnt = count( $arr );
        if($arr_cnt>1 ) return new sql( $arr[0], $arr[1]);
        if($arr_cnt>0 ) return new sql( $arr[0] );
        return new sql();
    }
    function writeLine( $arr ){
        //fwrite($this->handle, drFun::line( $arr ));
        $this->writeLine( drFun::line( $arr ) );
    }
    function write( $str ){
        if(! $this->handle ) $this->throw_exception("请先打开一个可写文件夹",2003);
        fwrite($this->handle, $str);
    }

    /**
     * 批量生成Es格式
     */
    function es_topic(   ){
        $that= $this;
        $cl_book = new book();
        $fun= function ( $var ) use ($that,$cl_book ){
            //print_r( $var );
            if( $var['tag_id']!=3){
                $cl_book->topic_info_decode( $var ) ;
                $str = $cl_book->formatTopic2Es( $var);
                $code = drFun::cPost('http://es54.jukuu.com/haoce/topic/_bulk',$str ,10,['Authorization: Basic Y2lrdXU6ODI2MDA4MTg=']);
                echo  $var['topic_id']."\t code=".$code." \t".$str ."\n";
                //$that->write( );
            }
            /*
            $that->writeLine(  ['index'=>['_id'=>$var['topic_id']]] );
            $var['topic_id'] = intval(  $var['topic_id'] );
            $var['book_id'] = intval(  $var['book_id'] );
            $var['user_id'] = intval(  $var['user_id'] );
            $var['tag_id'] = intval(  $var['tag_id'] );
            $var['ctime'] = date("c", $var['ctime']);
            $that->writeLine( $var );
            */
        };
        $sql ="select topic_id, book_id,user_id,tag_id,  ctime ,topic as title,topic_info as content from book_topic where topic_id>27 limit 20 ";
        //$sql ="select * from book_topic  where topic_id>27 limit 2 ";
        $sql ="select * from book_topic  where topic_id>27 ";
        $tall = $this->createSql( $sql )->getWithFun( $fun );
    }

    function tjSchool(){
        $school = $this->createSql()->select('book_school',"1",[],['school','now_term_key'])->getAll();
        $cl_book = new book();
        $cl_term = new term();
        $term_key = $cl_term->getNow(0);

        foreach( $school as $v ){
            $term = $v['now_term_key']? $v['now_term_key']:  $term_key;
            $re = $cl_book->tjSchool( $v['school'],true ,['term'=> $term] );
            echo  $v['school']."\t".$term."\t".$re['books_cnt']."\n";
            $cl_book->saveTjSchool2Redis($v['school'],$re );
        }
    }

    function clearPadLog(){
        $row = $this->createSql()->select('pad_log','1',[1000,1],[],['id'=>'desc'] )->getRow();//'SELECT * FROM  `pad_log` ORDER BY  `pad_log`.`id` DESC LIMIT 1000 , 1 '
        //print_r($row );
        if( !$row  ) return $this;
        $this->createSql('delete  FROM pad_log  where id<'.$row['id'])->query();
        return $this;
    }


}