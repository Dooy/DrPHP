<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/28 0028
 * Time: 下午 5:39
 */

namespace model;


class know extends model
{
     private $user_id=0;
    private $tb_cat='know_cat' ;
    private $file_cat = ['pid'=>1,'user_id'=>1,'cat'=>1,'info'=>1,'code'=>1];

    function setUserId( $user_id){
        $this->user_id= $user_id;
        return $this;
    }

    public function imKnowsFromExcel( $ex_file){
        $data = drFun::excelReadToArray( $ex_file);
        $re[]=[];
        foreach( $data as $k=>$var ){
            $re[$k]= ['title'=>$var['title']];
            $re[$k]['rz']= $this->imKnowFromArray( $var['data']);
        }
        return $re ;
    }
    public function imKnowFromArray( $array ){
        $key='';
        #先走出表头的code
        foreach( $array[1] as $k=>$v ){
            if( strtolower(trim($v))=='code'){
                $key= $k;
            }
        }
        if( !$key) return ['error'=>614,'error_des'=>'表头不存在code字样'];

        #获取数据
        # 1.找到尾巴在哪里
        # 2.尾巴前面的空都用上一行的数组补
        # 3.插入数据
        $ord= ord( $key );

        $re=[]; $old=[];
        for($i=2, $cnt=count($array); $i<=$cnt;$i++){
            $now =$array[$i];
            $code = trim( $now[ $key]);

            $krord= $ord ;
            $endkey='A';
            while (1){
                $krord--;
                $k2= chr( $krord);
                if( trim( $now[$k2] )!='' ){
                    $endkey= $k2; break;
                }
                if( $k2=='A') break;
            }
            if( $endkey=='A' ){
                $re[ $i ]=['error'=> 123,'error_des'=> "本行无内容" ];
                continue;
            }

           $var = [];
           foreach( $now as $k3=>$v3 ){
               if( ! trim( $now[$k3] ))$now[$k3] = $old[$k3];
               $var[]= $now[$k3];//($now[$k3])? $now[$k3]: $old[$k3];
               if($k3==$endkey) break;
           }

            $rvar = [];
            for($j=0,$jcnt=count($var); $j<$jcnt;$j++){
                $cat = $var[$j];++$j;
                $info =  isset($var[$j])?$var[$j]:'' ;
                $rvar[]= ['cat'=>$cat,'info'=> $info ];
            }
            $old= $now;
            //$re[$i ]= $rvar;  //$code $rvar;

            try{
               $this->addKnowsCat( $rvar, $code);
               $re[$i ]=  $code.'   success!';
            }catch (  drException $ex){
               $re[ $i ]=['error'=> $ex->getCode(),'error_des'=>  $ex->getMessage() ];
            }

        }
        return $re ;
    }


    function imKnows( $knows ){
        $re=[];
        $k=0;
        foreach( $knows as $v ){
            $k++;
            $code = $v[count($v)-1]['code'];

            try{
                $this->addKnowsCat( $v, $code);
                $re[$k]=  $code.'   success!';
            }catch ( drException $ex ){
                $re[ $k]=['error'=> $ex->getCode(),'error_des'=>$ex->getMessage() ];
            }
        }
        return $re ;
    }

    /**
     * 批量导入知识点
     *
     * addKnowsCat([ ['cat'=>'词汇','info'=>'词汇说明'],['cat'=>'动词','info'=>'动词说明'],['cat'=>'情态','info'=>'情态说明']] ,'C401' )
     *
     * @param $knows
     * @param $code
     * @return $this
     * @throws drException
     */
    function addKnowsCat( $knows,$code){
        $cnt =  count( $knows);
        if(! is_array($knows )|| $cnt<=1 ) $this->throw_exception("知识点类别必须要2个值以上的数组",601 );
        if( $code=='') $this->throw_exception("code 不能为空！",603 );

        if( $this->getKnowCatByCode( $code))  $this->throw_exception("code ".$code." 已经存在！",604 );

        foreach( $knows as $v ) $this->checkKnowCat( $v );
        $pid=0;
        $k=1;
        foreach( $knows as $k=> $v ){
            $k++;
            $drow = $this->getKnowCatByCat( $v['cat'], $pid);
            if( $drow){
                $opt_diff = drFun::arrDiffByKey( $drow,$v, $this->file_cat);
                $pid = $drow['cat_id'];
                unset( $opt_diff['pid']);unset($opt_diff['cat']); unset( $opt_diff['code']);
                if( $opt_diff) $this->createSql()->update( $this->tb_cat,$opt_diff,['id'=>$drow['id']])->query();
            }else{
                $opt = ['user_id'=>$this->user_id,'ctime'=>time() ,'pid'=> $pid ];
                drFun::arrExtentByKey($opt, $v,$this->file_cat);
                unset( $opt['code']);
                $pid = $this->createSql()->insert($this->tb_cat,$opt)->query()->lastID();
            }
            if( $k==$cnt ){
                $this->createSql()->update( $this->tb_cat,['code'=>$code],['cat_id'=>$pid])->query();
            }
        }
        return $this;

    }
    function checkKnowCat( $know_one ){
        if( !isset($know_one['cat']) || $know_one['cat']=='' ) $this->throw_exception("知识点类别包含不为空的类别名称",602 );
    }

    function getKnowCatByCode( $code ){
        return  $this->createSql()->select( $this->tb_cat,[ 'code'=>$code] )->getRow();
    }

    function getKnowCatByCat( $cat, $pid ){
        return $this->createSql()->select( $this->tb_cat,['pid'=>$pid,'cat'=>$cat] )->getRow();
    }
    function getKnowCatByCatId( $cat_id ){
        if( $cat_id<=0) $this->throw_exception( "ID必须大于0", 606 );
        return $this->createSql()->select($this->tb_cat,['cat_id'=>$cat_id ] )->getRow();
    }

    public function getKnowCatsByPid( $pid ){
        $sort = $this->getAllKnowCatsSort();
        return $sort[ $pid ];
    }

    /**
     * 按父亲节点获取知识分类
     * @return array
     */
    public   function getAllKnowCatsSort()
    {
        $tall = $this->createSql()->select( $this->tb_cat,'1',[0,10000],['cat_id','pid','cat','code'])->getAllByKey('cat_id');
        $sort = [];
        foreach( $tall as $v ){
            $sort[ $v['pid']][]= $v ;
        }
        return $sort;
    }
    /**
     * @param int $from 母节点
     * @return mixed
     */
    function  getAllKnowsToTree( $from =0 ){
        $sort = $this->getAllKnowCatsSort();
        $re=  $sort[ $from ]; // $sort[0]

        //print_r( $sort );
        $this->getTreeMap( $re , $sort );

        if( !isset($tall[ $from ]) ){
            $re[0]= ['name'=>'总的','children'=>$re  ] ;
        }else{
            $v = $tall[ $from ];
            $v['name']=$v['cat'];
            $v['children']=$re;
            $re[0]= $v;
        }
        //$this->drExit( $re );
        return $re ;
    }

    function getTreeMap( &$re,$sort ){
        foreach($re as $k=>$v ){
            $pid = $v['cat_id'];
            $re[$k]['name']= $v['cat'] .($v['code']?'('.$v['code'].')':'');
            unset(  $re[$k]['cat']);
            if(isset($sort[$pid]) ){
                $this->getTreeMap( $sort[$pid],$sort );
                $re[$k]['children'] = $sort[$pid] ;
            }
        }

    }

    /**
     * @param $cat_id
     * @param $var
     * @return $this
     * @throws drException
     */
    function modifyCat( $cat_id, $var){
        unset( $var['cat_id']);
        //if( $var['code']=='') unset( $var['code'] );
        $cat_id = intval($cat_id);
        if( $cat_id<=0) $this->throw_exception( "参数错误，cat_id 必须大于0",613);
        $crow = $this->getKnowCatByCatId( $cat_id);
        if($crow['cat']!=$var['cat'] ){
            $crow2 = $this->getKnowCatByCat($var['cat'] ,$crow['pid'] );
            if( $crow2 ) $this->throw_exception( "同节点 " . $var['cat'].' 名称已经存在！',616 );
        }

        $opt=[];
        drFun::arrExtentByKey( $opt, $var,$this->file_cat );
        if( !$opt || !is_array( $opt)) $this->throw_exception( "修改参数 必须要数组",614);
        if(isset( $opt['code']) and $opt['code']!='' ){
            $row = $this->createSql()->select($this->tb_cat, ['code' => $opt['code']])->getRow();
            if ($row && $row['cat_id'] != $cat_id) $this->throw_exception("该code '".$opt['code'] ."'已经存在", 615);
        }
        $this->createSql()->update( $this->tb_cat, $opt,['cat_id'=>$cat_id ])->query();

        return $this;
    }

}