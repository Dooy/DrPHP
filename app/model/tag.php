<?php
/**
 * Tag 需求
 * Date: 2018/6/22
 * Time: 20:56
 */

namespace model;


use model\lib\excel;

class tag extends model
{
    private $table='tag';
    private $type;
    function __construct($type, $table='tag')
    {
        $this->getType( $type );
        $this->type= $type;
        $this->setTable( $table);
    }

    function setTable( $table ){
        $arr=['tag'=>1 ];
        if(! isset( $arr[$table]) ) $this->throw_exception( "数据表不存在！", 6022001);
        $this->table = $table ;
        return $this;
    }

    function getTable(){
        return $this->table;
    }

    function getType( $type='all'){
        $tarr =[];
        $tarr[1]=['n'=>'书单','t'=>'novel'];
        if( $type=='all') return $tarr;
        if( ! isset( $tarr[$type]) ) $this->throw_exception( "该类别不存在！",6022002);
        return $tarr[$type];
    }

    private  function add( $tag_id ,$tag ){
        $var=['tag'=>$tag,'tag_id'=>$tag_id,'tag_type' =>$this->type ];
        $cnt = $this->createSql()->getCount( $this->getTable(),  $var )->getOne();
        if( $cnt>0 ) $this->throw_exception( $tag.'已经存在！',6022003);
        $var['ctime']= time();
        $var['user_id']=  $this->getLogin()->getUserId();
        $this->createSql()->insert( $this->getTable(), $var)->query();
        return $this;
    }

    function delByID( $id ){
        $this->createSql()->delete( $this->getTable(), ['id'=>$id])->query();
        return $this ;
    }

    function addByText(  $tag_id , $text){
        if( trim($text) =='') $this->throw_exception( "请输入Tag",6022004 );
        if( intval($tag_id) <=0 ) $this->throw_exception( "tag ID  必须大于0",6022005 );

        $text = strtr( $text,['，'=>',','，'=>','] );
        $arr= preg_split("/[ ,]+/",$text );
        $re=['success'=>[], 'fail'=>[] ];
        foreach( $arr as $tag ){
            $tag = trim( $tag);
            if( !$tag ) continue;
            try{
                $this->add( $tag_id, $tag );
                $re['success'][]= $tag;
            }catch ( drException $ex ){
                $re['fail'][]= $tag;
            }
        }
        return $re ;
    }
    function getTagByTagID( $tag_id ){
        $where= [  'tag_id'=> $tag_id, 'tag_type'=> $this->type ];
        $tall = $this->createSql()->select( $this->getTable(), $where)->getAll();
        return $tall ;
    }

    function imFromExcel( $excel_file ){
        $arr = drFun::excelReadToArray( $excel_file);
        //$this->drExit( $arr[0]['data'] );
        $re=['f'=>0,'success'=>0,'fail'=>0 ];
        foreach( $arr[0]['data'] as $v){
            if( intval( $v['A'])<=0 || trim( $v['C'])=='' ) continue;
            $re['f']++;
            $tem = $this->addByText( intval( $v['A']), trim( $v['C']) );
            $re['success']+=count($tem['success'] );
            $re['fail']+=count($tem['fail'] );
            //return $re ;
            //$this->drExit( $re );
        }
        return $re;
    }

    function getIDByTagWithStartEvery( $tag ){
        return $this->createSql()->setStartEvery()->select( $this->getTable(), ['tag'=>$tag,'tag_type'=>$this->type ] ,[],['tag_id'])->getCol();
    }

    /**
     * 通tag取得id 最多取500条
     * @param $tag
     * @return array
     */
    function getIDByTag( $tag ){
        $list = $this->createSql()->select(  $this->getTable(), ['tag'=>$tag,'tag_type'=>$this->type ] ,[0,500],['tag_id'] )->getCol();
        if( ! $list ) $this->throw_exception( "哎呦！ ".$tag." 未找到相关ID",6022006);
        return $list;
    }

}