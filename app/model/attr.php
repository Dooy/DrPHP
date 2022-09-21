<?php
/**
 * Created by zahei.
 * User: Administrator
 * Date: 2018/5/15
 * Time: 14:41
 */

namespace model;


class attr extends model
{
    private $table= 'user_gt_log'; //book_log
    private $user_id = 0;

    /**
     * attr constructor.
     * @param string $table
     */
    function __construct( $table  ){
        $this->setTable( $table );// ->setUserId( $user_id );
    }

    function setTable( $table ){
        $this->table= $table;
        $table_arr=['wenda_attr'=>'wenda_attr','class_attr'=>'class_attr' ];
        //$table_arr['pad_log']='pad_log';
        if( ! isset($table_arr[ $table ])) $this->throw_exception( "表不存在!",5015002 );
        return $this;
    }

    function getTable(){
        return $this->table;
    }


    /**
     * 获取字段，必须注意 第一个字段为 key
     * @return array
     */
    function getFile(){
        if( $this->table=='class_attr' ) return ['class_id','key','value'];
        if( $this->table=='user_attr' ) return ['user_id','key','value'];
        if( $this->table=='wenda_attr' ) return ['op_id','key','value','ctime'];
        return  ['op_id','key','value'];
    }


    function getKey( $key='all'){
        $type=[];
        switch ( $this->table){
            case 'user_attr':
                $type=['p1'=>['n'=>'超级管理员','op'=>'onedel']];
                $type['p2']= ['n'=>'好策管理员','op'=>'onedel'] ;
                $type['p3']= ['n'=>'学校管理员','op'=>'onedel'] ;
                $type['p4']= ['n'=>'编辑组','op'=>'onedel'] ;
                break;
            case 'wenda_attr':
                $type=[];
                $type[10]=['n'=>'老师评分','op'=>'one'];
                $type[11]=['n'=>'学生评分','op'=>'one'];
                break;
        }
        if( $key=='all' ) return $type;
        if(! isset( $type[$key] ) ) $this->throw_exception( "为定义操作" ,5015003);
        return $type[$key];
    }
    /**
     * 添加用户属性  'one': #增加或者修改 "onedel":#增加或者删除  default:#一直增加 默认append是一直增加
     * @param $op_id 操作ID
     * @param $key_val
     * @param $opt
     * @return $this
     */
    public function opAttr( $op_id, $key_val,$opt=[] ){
        if(!is_array($key_val)) $this->throw_exception("必须是key-value数组",369 );

        $file= $this->getFile();
        $op_file = $file[0];

        foreach( $key_val as $k=>$v ){
            if( is_array($v)) $this->opAttr( $v );
            else{
                $type= $this->getKey( $k );
                $kv= $opt;
                $kv['key']= $k; $kv['value'] = $v  ;$kv[ $op_file ] = $op_id ; $kv[ 'ctime' ] = time() ;
                $where= [ $op_file=> $op_id ,'key'=>$k ];
                switch ($type['op']){
                    case 'one': #增加或者修改
                        $row = $this->createSql()->select( $this->getTable(), $where )->getRow();
                        if( $row  ) $this->update($this->getTable(), $where  , $kv ,$this->getFile()  );
                        else $this->insert($this->getTable(), $kv, $this->getFile()  );
                        break;
                    case "onedel":#增加或者删除
                        $row = $this->createSql()->select( $this->getTable(), $where )->getRow();
                        if( $row  ) $this->del( $where ) ;
                        else $this->insert($this->getTable(), $kv, $this->getFile()  );
                        break;
                    case 'append':
                    default:#一直增加
                         $this->insert($this->getTable(), $kv, $this->getFile()  );
                }
            }
        }
        return $this;
    }

    function del( $where ){
        $this->createSql()->delete($this->getTable(), $where ,100  );
        return $this;
    }

    /**
     * 纯获取属性
     * @param $op_id
     * @return mixed
     */
    function getAttrByOPID( $op_id ){
        $file= $this->getFile();
        return $this->createSql()->select( $this->getTable(), [ $file[0]=>$op_id ])->getAllByKeyArr( [  $file[0],'key']);
    }

    /**
     * 获取的属性
     * @param $list
     * @return $this
     */
    function marge( & $list ){
        if(! $list  || !is_array($list )) return $this;
        $file= $this->getFile(); $key = $file[0];
        $a_id = [];
        drFun::searchFromArray(  $list , [$key ] ,$a_id );
        if(! $a_id ) return $this;
        $op_var = $this->getAttrByOPID($a_id );
        if( isset($list[$key ] ) ){
            $list['haoce_attr']= $op_var[  $list[$key ] ];
            return $this;
        }
        foreach( $list as &$v){
            $v['haoce_attr'] = $op_var[ $v[$key] ];
        }
        return $this ;
    }

}