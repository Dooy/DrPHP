<?php

namespace model;


class snt extends model
{
    private $_file=['book_isbn'=>1,'en'=>1,'cn'=>1,'ctime','user_id'];
    private $table='snt';
    private $tb_isbn= 'book_isbn';
    private $isbn=[];

    /**
     * 从excel文件里批量导入
     * @param $file
     * @param $re
     * @param array $opt
     * @return $this
     */
    function implodeFromExcel( $file ,&$re ,$opt=[] ){
        $arr = drFun::excelReadToArray( $file );
        //$this->drExit( $arr );
        $re = ['error'=>0,'cnt'=>0 ];
        foreach ($arr[0]['data'] as $k=>$v ){
            if( $k<2) continue;
            $opt['book_isbn']= trim( $v['A']);
            $opt['en']= $v['B'];
            $opt['cn']= $v['C'];
            try{
                $this->add( $opt);
                $re['cnt']++;
            }catch ( drException $ex ){
                $re['error']++;
            }

        }
        foreach ( $this->isbn as $k=>$v ){
            $this->upIsbnSntCnt( $k);
        }
        return $this;
    }

    /**
     * 增加或者修改
     * @param $opt book_isbn en cn
     * @param bool $isUp 当为true为可修改
     * @return $this
     */
    function add(   $opt ,$isUp=false  ){
        $this->checkIsbn($opt['book_isbn'] );
        $id = $this->createSql()->select( $this->table,['book_isbn'=>$opt['book_isbn'],'en'=>$opt['en']],[],['snt_id'])->getOne();
        if( $id>0 ) {
            if(! $isUp ) $this->throw_exception( "已经存在！", 6203 );
            $this->update($this->table,['snt_id'=>$id ] ,$opt, $this->_file );
        }else{
            $opt['ctime']= time();
            $this->insert($this->table, $opt,$this->_file );
        }

        return $this;
    }

    /**
     * 检查 isbn号是否存在
     * @param $isbn
     * @return $this
     */
    function checkIsbn( $isbn ){
        if( $isbn =='' ) $this->throw_exception("不允许为空ISBN",6201);
        if(! isset( $this->isbn[ $isbn ]) ){
            $this->isbn[ $isbn ] = $this->createSql()->select($this->tb_isbn, [ 'book_isbn'=>$isbn ])->getRow();
        }
        if( ! $this->isbn[ $isbn ] ) $this->throw_exception("ISBN号不存在！",6202);
        return $this;
    }

    /**
     * 更新bookIsbn中的 book_isbn号
     * @param $isbn
     * @return $this
     */
    function upIsbnSntCnt( $isbn ){
        $where= ['book_isbn'=>$isbn];
        $cnt = $this->createSql()->getCount($this->table, $where)->getOne();//select( $this->table,)
        $this->update( $this->tb_isbn,$where,['snt_cnt'=>intval($cnt)] );
        return $this;
    }

    /**
     * 得到分页
     * @param array $opt
     * @return array
     */
    function selectWithPage( $opt=[]){
        $where= isset( $opt['where'])? $opt['where'] : '1' ;
        $order= isset( $opt['order'])? $opt['order']:['snt_id'=>'desc'];
        return $this->createSql()->selectWithPage( $this->table, $where ,20,[],$order);
    }

    /**
     * 删除
     * @param int $id
     * @param array $opt
     * @return $this
     */
    function delByID( $id ,$opt=[]){
        $where= ['snt_id'=> $id ];
        $row = $this->createSql()->select( $this->table, $where)->getRow();
        if( $opt['user_id']>0 &&  $row['user_id']!=$opt['user_id'] ) $this->throw_exception("仅自己可删除！",6205);
        drFun::recycleLog( $row['user_id'],205, $row  );
        $this->createSql()->delete(  $this->table,$where )->query();
        return $this ;
    }
}