<?php
/**
 * 长文本 错误提示用4019xxx
 */

namespace model;


class text extends model
{
    private $tb_text='text';
    function getType( $type='all'){
        $arr=[10=>['n'=>'问答'] ];
        if( 'all'== $type) return $arr;
        if( isset( $arr[$type] ) ) return $arr[$type];
        $this->throw_exception("op_type 类型不存在",4019001);
    }

    /**
     * @param int $op_id
     * @param int $op_type
     * @param string $text
     * @param string $title
     * @return int
     */
    function add( $op_id, $op_type,$text ,$title=''){
        $this->getType($op_type );
        $var=['op_id'=>$op_id, 'op_type'=>$op_type,'text'=>$text,'title'=> $title  ];
        return $this->insert( $this->tb_text, $var);
    }

    function modify( $text_id, $text,$opt=[]){
        $var= ['text'=>$text];
        if( isset($opt['title'])   ) $var['title'] =trim($opt['title']);
        $this->update($this->tb_text,['text_id'=>$text_id], $var);
        return $this;
    }

    /**
     * @param array|int $id
     * @param array $opt
     * @return array
     */
    function getByID( $id,$opt=[] ){
        $file= isset( $opt['file'])?$opt['file']:[] ;
        if( $file && !in_array('text_id',$file )) $file[]='text_id';
        if(is_array($id)) return $this->createSql()->select( $this->tb_text,['text_id'=> $id ],[],$file)->getAllByKey( 'text_id');
        return $this->createSql()->select( $this->tb_text,['text_id'=> $id ],[],$file)->getRow();
    }

    function marge( &$list , $opt=[] ){
        if( !$list ) return $this;
        $id = [];
        drFun::searchFromArray( $list,['text_id'],$id  );
        $text = $this->getByID( $id );
        foreach ( $list as &$v){
            $v['text']= $text[ $v['text_id']];
        }
        //$this->drExit( $list );
        return $this;
    }
}