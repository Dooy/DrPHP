<?php
/**
 * 问答系统
 * 错误 代码从 4018xxx 开始
 */

namespace model;


class wenda extends model
{
    private $tb_wenda='wenda';
    private $tb_view_list='novel_view_list';
    private $text_type= 10 ;
    private $_file=['pid','user_id','school_id','novel_id','term_key' ,'word','ctime','re_time' ,'cat_id','re_user_id','cp_id','block_id'];
    static private $_wenda=[];

    function add($text,  &$var ){
        $this->getCat( $var['cat_id'] );
        //if( $var['cat_id'] ==4 ) $text = $var['text']['me'];
        drFun::strip( $text );

        $var['pid']= intval(  $var['pid'] );
        if( $var['cat_id'] <100 && !$var['title']){
            $var['title']= drFun::getTitleFromText( $text,30 );
            drFun::strip( $var['title'] );
        }
        $var['word'] = $var['word']>5? $var['word'] : drFun::wordCountEnAndCn( $text );
        if(  $var['cat_id'] ==10 ){ }
        elseif( ! isset($var['novel_id'] )  || $var['novel_id']<=0 ) $this->throw_exception('必须绑定小说'  ,4018003 );
        $this->checkZiShu($var['cat_id'],$var['pid'],$var['word'] )->margeBlock($var);


        $var['school_id']=  $var['school_id']>0? $var['school_id']: $this->getLogin()->getSchoolID();
        $var['user_id']=  $var['user_id']>0? $var['user_id']: $this->getLogin()->getUserId();
        $var['term_key']=  $var['term_key'] ? $var['term_key']: $this->getLogin()->getCheckTerm();

        $var['ctime']=$var['re_time']= time();
        $var['wenda_id'] =$this->insert( $this->tb_wenda, $var, $this->_file  );

        $this->encode($text, $var);

        $text_id = $var['text_id'] = $this->getLogin()->createText()->add( $var['wenda_id'] ,$this->text_type,$text, $var['title'] );
        $this->update( $this->tb_wenda,['wenda_id'=> $var['wenda_id'] ] , ['text_id'=>$text_id ]);

        if(  $var['pid']>0 ){
            $this->updatePid( $var['pid'], $var['user_id']  );
        }
        $this->upCnt(  $var['cat_id'], $var['user_id'], $var['novel_id']);
        return $this;
    }

    function margeBlock( &$var ){
        $arr = $this->getLogin()->createNovel()->getViewList(['user_id'=>$this->getLogin()->getUserId(),'novel_id'=> $var['novel_id']] );
        if( !$arr ) return $this;
        $var['block_id']= $arr[0]['block_id'];
        return $this;
    }

    function  upCnt( $cat_id,$user_id ,$novel_id ){
        //if( $cat_id>101) $cat_id=101;
        if( !(($cat_id>0& $cat_id<10)|| $cat_id==101)) return $this;
//        if( $cat_id>100){
//            $cnt = $this->createSql()->getCount( $this->tb_wenda, ['user_id'=>$user_id,'novel_id'=>$novel_id,'>='=>['cat_id'=>$cat_id ]] )->getOne();
//        }else
        if( $cat_id==2 ){ #报告是回答pid=1001
            $cnt = $this->createSql()->getCount( $this->tb_wenda, ['user_id'=>$user_id,'novel_id'=>$novel_id,'pid'=>1001] )->getOne();
        }elseif( $cat_id==7 ){
            $cnt = $this->getLogin()->createNovel()->getCommentCntByWhere( [ 'user_id'=>$user_id,'novel_id'=>$novel_id ]);
        }else{
            $cnt = $this->createSql()->getCount( $this->tb_wenda, ['user_id'=>$user_id,'novel_id'=>$novel_id,'cat_id'=>$cat_id ] )->getOne();
        }
        $this->update($this->tb_view_list,[ 'user_id'=>$user_id,'novel_id'=>$novel_id ] ,[ 'cnt_'.$cat_id=> $cnt]);
        return $this;
    }

    function upCntByViewListID( $id ){
        $row= $this->createSql()->select( $this->tb_view_list,['id'=>$id] )->getRow();
        if(! $row ) $this->throw_exception("该ID不存在",4018010);
        $cat=[101,1,2,4,5,6,7];
        foreach ( $cat as $cat_id){
            $this->upCnt($cat_id,$row['user_id'],$row['novel_id'] );
        }
        return $this;
    }

    function encode( &$text ,  $var ){
        #随想要将ying入的段落带进来
        if( $var['cat_id'] ==4  )$text =drFun::json_encode(['text'=>$text,'ying'=>$var['ying']] );
        if( $var['cat_id'] ==5  ){
            //print_r( $var);  $this->drExit($_FILES);
            $p_file= drFun::upload( $_FILES['file'] ,['dir'=>'yin','ext'=> ['mp3'=>1,'mp4'=>1,'amr'=>1]]  );
            $p_file['time']= $_POST['time'];
            $p_file['text']= $text;
            $text =drFun::json_encode( $p_file );
        }
        return $this;
    }

    function decode( &$var_list ){
        if( ! is_array($var_list )) return $this;

        if( isset($var_list['cat_id']) && isset( $var_list['text']) ) $this->decode_item($var_list );
        foreach( $var_list as &$v) $this->decode_item( $v );
        return $this;
    }

    function decode_item( &$var ){
        if( !isset( $var['cat_id']) ||  ! isset( $var['text']) ) return $this;
        if(  $var['cat_id'] ==4  ||  $var['cat_id'] ==5 ){
            $arr = drFun::json_decode(  $var['text']['text'] );
            if( $arr ){
                $var['text']['text']= $arr['text'];
                if( $var['cat_id'] ==5   ){
                    //$arr['file'] =  $_SERVER['SERVER_ONLINE'] !='debug'?
                    drFun::cdnImg($arr,['file'] );
                }
                $var['text']['text_arr']= $arr ;
            }
        }
        return $this ;
    }

    function checkZiShu( $cat_id,$pid,$word, $opt=[] ){
        if(  $pid >0 ) $p_wenda = $this->getOne( $pid );
        if( $cat_id >=100 && ($pid<=0 ||  !$p_wenda   )  ){
            $this->throw_exception('您回复的主题删除或者不存在！'  ,4018004 );
        }elseif( $cat_id >=100 && ($p_wenda['cat_id']==2 ||  $p_wenda['cat_id']==3 ) &&  $word<80 ){
            $this->throw_exception('亲，老师要求 80 字以上！'  ,4018006 );
        }
        $word_limit = 10;
        if(  $word <$word_limit && !$this->getLogin()->isTeacher(3 ) ) $this->throw_exception( '亲，能多写几个字么？',4018002 ); //'字数必须大于' .$word_limit
        return $this;
    }

    /**
     * 更新回复的主题
     * @param $pid
     * @param $user_id
     * @return $this
     */
    function updatePid( $pid , $user_id ){
        $cnt = $this->createSql()->getCount( $this->tb_wenda,['pid'=>$pid])->getOne();
        $var=['re_time'=>time(),'re_user_id'=>$user_id,'cnt'=>$cnt ] ;
        if( $user_id<=0 ){
            unset( $var['re_time'] );
            unset( $var['re_user_id'] );
        }
        $this->update($this->tb_wenda,['wenda_id'=>$pid ], $var);
        return $this;
    }

    /**
     * 删除
     * @param $wenda_id
     * @return $this
     * @throws \Exception
     */
    function del($wenda_id ){
        //$this->drExit( $wenda_id );
        $wenda= $this->getOne( $wenda_id);
        $this->check( $wenda );
        $this->getLogin()->createLogRecycle()->append( $wenda['user_id'],207, $wenda );
        $this->createSql()->delete( $this->tb_wenda,['wenda_id'=>$wenda_id])->query();
        $this->updatePid( $wenda['pid'],-1 );
        return $this;
    }

    function check($wenda ){
        if($wenda['cat_id']==2  )  $this->throw_exception("来至系统不可以操作！", 4018009);

        if( !( $wenda['user_id']==$this->getLogin()->getUserId() || $this->getLogin()->isAdmin()  )){
            $this->throw_exception("仅管理员和自己可操作！", 4018007);
        }
        return $this;
    }

    /**
     * 修改
     * @param $wenda_id
     * @param $var
     * @return $this
     * @throws drException
     */
    function modify($wenda_id,$var ){
        $wenda= $this->getOne( $wenda_id);
        $this->check( $wenda );
        if( isset($var['text']) ){
          $var['word']= drFun::wordCountEnAndCn( $var['text'] );
          $this->checkZiShu($wenda['cat_id'],$wenda['pid'] ,$var['word'] );
          $opt=   isset($var['title'])? ['title'=> $var['title']]:[] ;
          $this->getLogin()->createText()->modify($wenda['text_id'],  $var['text'] ,$opt );
        }
        if( !$var  )$this->throw_exception("没想要修改的东西？", 4018008);
        $this->update( $this->tb_wenda,['wenda_id'=>$wenda_id ], $var,$this->_file );
        return $this;
    }

    /**
     * 问答类型 小于100是问题 大于100是回答
     * @param string $cat_id
     * @return array
     * @throws drException
     */
    function getCat( $cat_id='all'){
        $cat=[ 1=>['n'=>'讨论','cl'=>'icon-tb-message' ]
            ,2=>['n'=>'报告','cl'=>'icon-tb-roundcheck'] //期末报告
            ,3=>['n'=>'老师设置提问'] //不怎么使用
            ,4=>['n'=>'随想','cl'=>'icon-tb-footprint'] //摘抄
            ,5=>['n'=>'朗读','cl'=>'icon-tb-we']
            ,6=>['n'=>'概要','cl'=>'icon-tb-edit'] //期中概要
            ,7=>['n'=>'笔记','cl'=>'icon-tb-edit'] //未启用 先占坑

            ,10=>['n'=>'FQA']
            ,101=>['n'=>'一般回答'] ,102=>['n'=>'系统提问回答'],103=>['n'=>'老师提问回答']];
        if( $cat_id =='all') return $cat;
        if( isset( $cat[$cat_id ] ) ) return  $cat[$cat_id ] ;
        $this->throw_exception("类型不支持", 4018001);
    }

    function getListWithPage( $where,$opt=[]){
        $order=isset($opt['order'])? $opt['order'] :['cnt'=>'desc'];
        return $this->createSql()->selectWithPage( $this->tb_wenda, $where,30,[],$order);
    }

    /**
     * 某本书必须回答的任务问题
     * @param int $novel_id
     * @param array $opt
     * @return array
     */
    function getMyRequestByNovelId(  $novel_id,$opt=[]){
        $where =" (cat_id=2 ) or ( novel_id='".$novel_id."' and cat_id=3 )";
        return $this->createSql(" select * from ". $this->tb_wenda. " where " . $where ." order by cnt desc")->getAll();
    }

    function margeFinish( &$list, $novel_id){
        if( !$list ) return $this;
        $id =[];
        drFun::searchFromArray( $list,['wenda_id'],$id );
        $finish = $this->checkFinish( $id, $this->getLogin()->getUserId(), $novel_id );
        foreach( $list as &$v){
            $v['finish']= isset($finish[ $v['wenda_id'] ])? $finish[ $v['wenda_id'] ]  : false  ;
        }
        return $this;
    }


    function margeScoreFromWendaLog( &$view_list ){
        $uid=[]; $novel=[];
        drFun::searchFromArray($view_list,['user_id'] ,$uid);
        drFun::searchFromArray($view_list,['novel_id'] ,$novel);
        //print_r($uid );
        //print_r($novel );
        $wenda= $this->createSql()->select( $this->tb_wenda,['user_id'=>array_keys($uid),'novel_id'=>array_keys($novel)] )->getAllByKey('wenda_id');
        $wenda_log = $this->getLogin()->createLogWenda()->getList( array_keys($wenda ),['type'=>10, 'limit'=>[] ]);
        //print_r($wenda );
        foreach( $wenda_log  as $v)   $wenda[$v['opt_id']]['ren_score'][]=$v['opt_value'];
        //print_r($wenda_log );
        unset($wenda_log );
        $user_novel=[]; //人工分
        $u_n=[];//机器分
        foreach( $wenda as $v ){
            $cat_id= $v['cat_id'];
            if( $v['pid']==1001 ) $cat_id=2;
            if( isset($v['ren_score']) ) {
                $user_novel[$v['user_id']][$v['novel_id']][$cat_id] =
                    isset($user_novel[$v['user_id']][$v['novel_id']][$cat_id]) ? max($user_novel[$v['user_id']][$v['novel_id']][$cat_id], max($v['ren_score'])) : max($v['ren_score']);
            }
            $u_n[$v['user_id']][$v['novel_id']][$cat_id] =  isset($u_n[$v['user_id']][$v['novel_id']][$cat_id])? max( $v['score'],$u_n[$v['user_id']][$v['novel_id']][$cat_id] ) :  $v['score'];
        }
        unset($wenda );

        foreach($view_list as &$v){
            if( isset($user_novel[$v['user_id']][$v['novel_id']]) ) $v['ren_score']= $user_novel[$v['user_id']][$v['novel_id']];
            if( isset($u_n[$v['user_id']][$v['novel_id']]) ) $v['score']= $u_n[$v['user_id']][$v['novel_id']];
        }
        //$this->drExit( $view_list );
        return $this;
    }

    function reCnt(  &$list,$where  ){
        $pid=[];
        drFun::searchFromArray( $list, ['wenda_id'], $pid);
        if( !$pid ) return $this;
        $where['pid']= $pid;
        $pCnt = $this->createSql()->group( $this->tb_wenda,['pid'], $where,['pid','count(*) as cnt'] )->getAllByKey('pid');
        //$this->drExit( $pCnt );
        foreach( $list as &$v){
            $v['cnt']= isset( $pCnt[ $v['wenda_id'] ] ) ?  $pCnt[ $v['wenda_id'] ]['cnt'] :0 ;
        }
        return $this;
    }

    function checkFinish( $wenda_id, $user_id ,$novel_id ){
        return $this->createSql()->select( $this->tb_wenda,['user_id'=>$user_id,'novel_id'=>$novel_id ,'pid'=>$wenda_id],[],['pid','wenda_id','text_id'] )->getAllByKey( 'pid');
    }
    function checkTask( $task, $where ){
        switch ($task){
            case 'suiXiang':
            default:
                return $this->createSql()->getCount( $this->tb_wenda, $where )->getOne();
        }
    }
    function getOne( $wenda_id ){
        if( isset(   self::$_wenda[ $wenda_id]  ) ) return  self::$_wenda[ $wenda_id]  ;

        $wenda= $this->createSql()->select( $this->tb_wenda, ['wenda_id'=>$wenda_id] )->getRow();
        if( !$wenda )  $this->throw_exception("主题不存在", 4018005);
        self::$_wenda[ $wenda_id] = $wenda;
        return $wenda;
    }
    function upView( $wenda_id ){
        $this->update( $this->tb_wenda,['wenda_id'=>$wenda_id] ,['+'=>['view'=>1] ]);
        return $this;
    }

    /**
     * @param $where
     * @param array $opt
     * @return array
     */
    function tjWenda( $where,$opt=[]){
        $re=['cnt'=>0,'word'=>0 ];
        $tb= $this->tb_wenda;
        $where =   $this->createSql()->arr2where( $where);
        $tem = $this->createSql(  "select COUNT( * ) AS cnt, SUM( word ) AS word from ".$tb ." where " . $where  )
            ->getRow();
        foreach( $tem as $k=>$v ) $re[$k]= $v ;
        return $re ;
    }

}