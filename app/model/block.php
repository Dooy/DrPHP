<?php
/**
 * 小说板块, 错误标志从4025xxx 开始
 * User: zahei.com
 * Date: 2018/4/25
 * Time: 20:37
 */

namespace model;


class block extends model
{
    private $tb_name='novel_block_name';
    private $tb_block ='novel_block';
    private $_file =['order','read_cnt','finish_cnt', 'xuefen_cnt','comment_cnt','dtime','word'];
    private static $blockName=[];

    /**
     * 为学校增加板块名称
     * @param $block
     * @param $school
     * @return $this
     * @throws drException
     */
    function addBlockName( $block,$school ){
        if( ! $block || !$school ) $this->throw_exception( "参数错误！",4025002 );
        $row = $this->getLogin()->createClassBook()->getBookSchoolFromDB( $school) ;
        if( !$row ) $this->throw_exception( "学校不存在！",4025001);
        $cnt = $this->createSql()->getCount( $this->tb_name,['school_id'=>$row['id'],'block'=>$block ] )->getOne();
        if( $cnt >0 )$this->throw_exception( "该模块已经存在！",4025003 );
        $this->insert( $this->tb_name, ['user_id'=>$this->getLogin()->getUserId(),'school_id'=>$row['id'],'block'=>$block ,'ctime'=>time() ]);
        return $this;
    }

    /**
     * 修改板块名称
     * @param $block_id
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function modifyBlockName( $block_id ,$opt=[] ){
        $file_name = ['block','novel_cnt','type','bg_color','is_class','icon','opt', 's_start_time','s_end_time','is_bu'
            ,'sub_end_time','slogen','hv_type','img','opt','info','info','book_limit','book_limit_min','book_max']; //'school_id',
        $block= $this->getBlockNameByID($block_id);

        if( isset( $opt['is_class']) ) $this->getTypeIsClass( $opt['is_class']);
        if( isset( $opt['hv_type']) ) $this->getTypeHv( $opt['hv_type']);

        if(isset($opt['block'])){
            if( trim($opt['block'])=='' ) $this->throw_exception( "该模块名称不允许为空！",4025004 );
            $row= $this->createSql()->select( $this->tb_name,['school_id'=>$block['school_id'],'block'=>trim($opt['block']) ] )->getRow();
            if( $row && $row['block_id']!= $block_id ) $this->throw_exception( "该模块已经存在！",4025005 );
        }
        if( isset( $opt['type'] ) ) $this->getTypeBlockName( $opt['type'] );
        $this->update($this->tb_name,['block_id'=>$block_id], $opt, $file_name);
        return $this;
    }


    /**
     * 删除板块名称
     * @param $block_id
     * @return $this
     * @throws \Exception
     */
    function delNameByID($block_id){
        $this->createSql()->delete($this->tb_name,['block_id'=>$block_id])->query();
        return $this;
    }

    /**
     * 获取板块名称
     * @param $block_id
     * @return mixed
     * @throws drException
     */
    function getBlockNameByID( $block_id ){
        if( is_array( $block_id ) )
            return $this->createSql()->select( $this->tb_name,['block_id'=>$block_id])->getAllByKey('block_id');
        if( isset( self::$blockName[$block_id ] ) ) return  self::$blockName[$block_id ] ;
        $row = $this->createSql()->select( $this->tb_name,['block_id'=>$block_id])->getRow();
        if( !$row ) $this->throw_exception("该模块不存在！", 4025007);
        $this->opt_decode($row['opt'] );
        self::$blockName[$block_id ]= $row;
        return $row;
    }

    function taskInfo_default( &$opt ){
        $task=[];
        $task[6]='任务：请在读这本的过程中撰写一篇期中总结，按照主题或章节归纳已读完部分的核心内容，培养你归纳、分析与整合信息的能力。';
        $task[4]='任务：请摘抄出书中让你印象深刻的句子或段落，并附上自己的见解与感受。';
        $task[2]='任务：请在期末时撰写一篇读书报告，在总结本书主要内容的基础上发表自己反思性的感想或见解，培养自己的思辨能力。';
        $task[5]='任务：请选择你认为比较合适的段落朗读并上传对应的文字和语音。坚持朗读能够提高发音的准确性，培养语言的韵律感，进一步完善你的各项语言技能。';
        $task[1]='任务：请针对本书内容发起'.$opt['cnt']['1'].'个主题(字数不少于'.$opt['word']['1'].'字)，如果你的主题足够有趣，一定会吸引更多同学来参与讨论。同时你也需要积极参与讨论其他同学发起的主题至少'.$opt['cnt']['101'].'次。';

        foreach( $opt['info'] as $k=>&$v){
            if( $v!='') continue;
            if( isset( $task[$k] )) $v =  $task[$k];
        }
        return $this;
    }

    function blockDecode( &$block ){
        $block['s_start_time']= $block['s_start_time']>0?date("Y-m-d H:i:s", $block['s_start_time']):'';
        $block['s_end_time']= $block['s_end_time']>0?date("Y-m-d H:i:s", $block['s_end_time']):'';
        $block['sub_end_time']= $block['sub_end_time']>0?date("Y-m-d H:i:s", $block['sub_end_time']):'';
        return $this;
    }

    function blockEncode( &$block ){
        if( isset( $block['s_start_time']) ) $block['s_start_time']= $block['s_start_time']?strtotime( $block['s_start_time']):0;
        if( isset( $block['s_end_time']) ) $block['s_end_time']= $block['s_end_time']?strtotime(  $block['s_end_time']): 0 ;
        if( isset( $block['sub_end_time']) )  $block['sub_end_time']= $block['sub_end_time']?strtotime(  $block['sub_end_time']): 0;
        if( isset( $block['opt']) )  $this->opt_encode( $block['opt']);
        return $this;
    }

    /**
     * @param $opt
     * @return $this
     */
    function opt_decode( &$opt ){
        $arr = drFun::json_decode( $opt );
        if( !$arr ) {
            $opt= $this->opt_config_default();
            return $this;
        }
        $default=$this->opt_config_default();
        foreach( $default  as $k=>$v ){
            if( !isset( $arr[$k]) ) $arr[$k]= $v;
        }
        $opt = $arr;
        return $this ;
    }

    /**
     * @param $opt
     * @return $this
     */
    function opt_encode( &$opt ){
        if( !is_array($opt ) )    return $this;
        $opt = drFun::json_encode($opt );
        return $this ;
    }

    function getTypeBlockName( $type='all'){
        $arr=[1=>['n'=>'启用'],-1=>['n'=>'隐藏' ] ,10=>['n'=>'仅老师可见']  ];
        if('all'==$type ) return $arr;
        if( isset( $arr[$type] ) ) return $arr[$type];
        $this->throw_exception('该类型不支持！' ,4025010);
    }

    /**
     * 任务
     * @param string $type
     * @return array
     * @throws drException
     */
    function getTypeTask( $type='all'){
        $tall = $this->getLogin()->createWenda()->getCat();
        $tall['progress']= ['n'=>'进度','cl'=>'icon-tb-sort'];
        if('all'==$type )  return $tall;
        if( isset( $tall[$type] ) ) return $tall[$type];
        $this->throw_exception('该类型不支持！' ,4025012);
    }

    function getTypeHv( $type='all'){
        $tall['1']= ['n'=>'教学模式'];
        $tall['100']= ['n'=>'读书工程'];
        if('all'==$type )  return $tall;
        if( isset( $tall[$type] ) ) return $tall[$type];
        $this->throw_exception('该类型不支持！' ,4025016);
    }

    /**
     * 获取板块列表带分页
     * @param $where
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getNameListWithPage( $where, $opt=[]){
        $order = $opt['order'] ? $opt['order'] : ['block_id'=>'desc'];
        return $this->createSql()->selectWithPage( $this->tb_name, $where,30,[],$order );
    }

    /**
     * 获取板块列表
     * @param $where
     * @return mixed
     */
    function getNameList( $where ){
        return $this->createSql()->select($this->tb_name ,$where)->getAll();
    }
    function getBlock( $where ){
        return $this->getNameList( $where );
    }

    function delBlockByID( $id ){
        $this->createSql()->delete( $this->tb_block,['id'=>$id])->query();
        return $this;
    }

    /**
     * 为block增加小说ID
     * @param $novel_id
     * @param $block_id
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function addNovel( $novel_id,$block_id ,&$opt=[] ){
        if( !is_array($novel_id )) $this->throw_exception("小说ID必须为数组",4025006);
        $block = $this->getBlockNameByID( $block_id );
        $tem =[];
        foreach( $novel_id as $k=> $v ){ $tem[$v]= $v ; }
        $novel_id= $tem;

        $old = $this->createSql()->select($this->tb_block,['block_id'=>$block_id,'novel_id'=>$novel_id])->getAllByKey('novel_id');
        $re=['old'=>0,'new'=>0,'no'=>0  ];
        foreach( $novel_id as $k=> $v ){
            if( isset( $old[ $v ]) ){
                $re['old']++;
                unset($novel_id[$k]);
            }
        }
        $opt['re']=$re ;
        if( !$novel_id) return $this;
        $novel= $this->getLogin()->createNovel()->getNovelById( $novel_id );

        foreach($novel_id as $k=> $v ){
            if( !isset($novel[$v] )){
                unset($novel_id[$k]);
                $opt['re']['no']++;
            }else{
                $opt['re']['new']++;
            }
        }
        if( !$novel_id) return $this;

        $max = $this->createSql("select max(`order`) from ".$this->tb_block." where  block_id='".$block_id."' " )->getOne();
        $novel_id = array_reverse( $novel_id );
        $arr=[];        $max++;
        foreach( $novel_id as $k=>$v ){
            $v =['order'=> $max+$k,'block_id'=>$block_id,'school_id'=>$block['school_id'],'novel_id'=>$v ,'user_id'=>$this->getLogin()->getUserId(),'ctime'=>time() ] ;
            $arr[]=$v;
        }
        $sql = $this->createSql()->insertPL( $this->tb_block,$arr )->query() ;
        //$this->drExit( $sql );

        return $this;
    }

    /**
     * 修改板块名称选书
     * @param $block_id
     * @return $this
     * @throws drException
     */
    function updateNameNovelCnt( $block_id ){
        $where = ['block_id'=>$block_id];
        $cnt = $this->createSql()->getCount(  $this->tb_block, $where)->getOne();
        $this->update( $this->tb_name,$where ,['novel_cnt'=>$cnt ]);
        return $this;
    }



    /**
     * 获取板块小说ID并带分页
     * @param $where
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getListWithPage( $where, $opt=[]){
        $order=['order'=>'desc'];
        $every = 30;
        if( isset( $opt['every'] ) && $opt['every']>0 ) $every = $opt['every'] ;
        return $this->createSql()->selectWithPage( $this->tb_block, $where,$every ,[],$order);
    }

    function getList($where, $opt=[]){
        $order=['order'=>'desc'];
        $file = isset( $opt['file'])? $opt['file']:[] ;
        return $this->createSql()->select( $this->tb_block, $where,[], $file ,$order )->getAll();
    }

    function getNovelIdByBlockID($block_id ){
       return  $this->createSql()->select( $this->tb_block,['block_id'=>$block_id ], [],['novel_id'])->getCol() ;
    }

    /**
     * 修改单个板块小说属性
     * @param $id
     * @param $opt
     * @return $this
     * @throws \Exception
     */
    function modifyByID( $id, $opt ){
        $this->update( $this->tb_block, ['id'=>$id],$opt,$this->_file );
        return $this;
    }
    function getBlockByID($id){
        $row = $this->createSql()->select( $this->tb_block, ['id'=>$id] )->getRow();
        if( !$row ) $this->throw_exception( "该记录不存在", 4025008);
        return $row;
    }

    function tjBlockByID( $id ,&$opt=[]){

        $row = $this->getBlockByID( $id );
//        if($row['last_time'] >(time()-300) ){
//            $opt['re']= $row;
//            return $this;
//        }
        $where=['novel_id'=>$row['novel_id'],'school_id'=> $row['school_id'] ];
        $novel= $this->getLogin()->createNovel()->tjViewV2( $where ,['finish'=>1 ] );
        $var['read_cnt']= $novel['novel_cnt'];
        $var['word']= $novel['word'];
        $var['dtime']= $novel['time'];
        $var['finish_cnt']= $novel['finish'];
        $var['xuefen_cnt']= $novel['xuefen'];
        $comment= $this->getLogin()->createNovel()->tjComment( $where) ;
        $var['comment_cnt']= $comment['cnt'];
        $wenda = $this->getLogin()->createWenda()->tjWenda($where );
        $var['wenda']= $wenda['cnt'];
        $var['last_time']= time();

        $var['cnt_think']= $this->getLogin()->createWenda()->checkTask( 'suixiang',['novel_id'=>$row['novel_id'],'school_id'=> $row['school_id'] ,'cat_id'=>4 ] );
        $var['cnt_report']= $this->getLogin()->createWenda()->checkTask( 'report',['novel_id'=>$row['novel_id'],'school_id'=> $row['school_id'] , 'pid'=>1001 ] );
        $var['cnt_discuss']= $this->getLogin()->createWenda()->checkTask( 'discuss',['novel_id'=>$row['novel_id'],'school_id'=> $row['school_id'] ,'cat_id'=>1 ] );

        $this->update( $this->tb_block,$where,$var);
        $opt['re']= $var;
        return $this;

    }

    function getTypeIcon(){
        $type=['hao-icon-dushugongcheng','hao-icon-xialingying','hao-icon-dushushow'];
        return $type;
    }

    function getTypeBgColor(){
        $color=['#55B8F7','#FAE44C','#96E663'];
        return $color;
    }
    function getTypeIsClass($is_class='all'){
        //$this->drExit( $is_class );
        $type=[0=>'不需班级',1=>'班级白名单',10=>'自选班级'];
        if( $is_class=='all' ) return $type;
        if( !$is_class)   $is_class=0;
        if(! isset($type[$is_class]) ) $this->throw_exception("加入班级方式错误！",4025009);
        return $type[$is_class];
    }

    function opt_config_default(){
        /**
         *  1=>['n'=>'讨论']
        ,2=>['n'=>'报告'] //期末报告
        ,3=>['n'=>'老师设置提问'] //不怎么使用
        ,4=>['n'=>'随想'] //摘抄
        ,5=>['n'=>'朗读']
        ,6=>['n'=>'概要'] //期中概要
        ,7=>['n'=>'笔记'] //未启用 先占坑
         */
        return [
            'task'=>[  #参考问答中的cat getCat 0标示不要要求 需要必须占有权重
                'progress'=>5,
                '1'=>1 //讨论
                ,'2'=>2 //报告（期末）
                ,'4'=>0 //随想摘抄
                ,'5'=>1 //朗读
                ,'6'=>0 //概要（其中）
            ],
            //'discuss'=>['cnt'=>1,'re_cnt'=>5,'word'=>80,'re_word'=>30 ]
            'cnt'=>['1'=>1,'101'=>5],
            'word'=>['1'=>80,'101'=>30,'2'=>120,'4'=>0,'5'=>0,'6'=>0 ]
            ,'info'=>['1'=>'','2'=>'','4'=>'','5'=>'','6'=>'' ]
        ];
    }

    /**
     * 是不是block的学院
     * @param $term_key
     * @return bool
     * @throws drException
     */
    function isBlockTerm( $term_key){
        if( $term_key=='1001'  ){
            $block_id= intval($_GET['block_id']);
            if($block_id<=0  ) $this->throw_exception("课程ID必须带上！",4025011);
            return $block_id;
        }
        return false;
    }

    function addToBlock($block_id, $novel_id ){
        //$this->drExit( $block_id );
        $blockName = $this->getBlockNameByID($block_id);

        $time= time();
        if( $blockName['s_start_time']>0 && $time<$blockName['s_start_time']  )
            $this->throw_exception("还未到选书未开始！(".date("Y-m-d H:i",$blockName['s_start_time']).")"  ,4025015);
        if( $blockName['s_end_time']>0 && $time>$blockName['s_end_time']  )
            $this->throw_exception("选书已截止！(".date("Y-m-d H:i",$blockName['s_end_time']).")" ,4025016);

        if( $blockName['book_limit']>0   ) {
            $block_novel= $this->getLogin()->createNovel()->getMyNovel( ['where'=>['block_id'=>$block_id ],'file'=>['block_id','novel_id']]);
            if( count($block_novel)>=$blockName['book_limit'])    $this->throw_exception("您已经超过选书" .$blockName['book_limit'] .'本限制！',4025014);
        }

        if( $blockName['is_class']=='1' && !$this->getLogin()->isTeacher() ){
            $tall = $this->getLogin()->createTerm()->setTableSchoolUserByBlockID($block_id )->getBindSchoolUser( ['user_id'=>$this->getLogin()->getUserId()] );
            if( ! $tall) $this->throw_exception('请先绑定信息！' ,4025016);
        }


        $block_novel= $this->getLogin()->createNovel()->getMyNovel( ['where'=>['novel_id'=>$novel_id ]]);
        if( $block_novel[$novel_id]  ){
            if($block_novel[$novel_id]['block_id']==$block_id  )  return $this;
            if($block_novel[$novel_id]['block_id']<=0 ){
                //$this->update( $this->tb)
                $this->getLogin()->createNovel()->modifyNovelViewList( $block_novel[$novel_id]['id'] , ['block_id'=>$block_id ] );
                return $this;
            }
            $this->throw_exception( "本书已在其他课程中添加过！",4025013);
            return $this;
        }
        $this->getLogin()->createNovel()->addNovelViewList( $novel_id,$this->getLogin()->getUserId(), ['block_id'=>$block_id  ] );
        return $this;
    }

    function getBlockTj( $block_id ){
        $re= ['novel_cnt'=>0,'view_list_cnt'=>0,'task_cnt'=>0 ];
        $re['novel_cnt'] = $this->createSql()->getCount( $this->tb_block,['block_id'=>$block_id ])->getOne();
        $var = $this->getLogin()->createNovel()->getTjCntFromNovelViewList(['block_id'=>$block_id ],['view_list_cnt'=>1]);
        $re['view_list_cnt']= $var['view_list_cnt'];
        $blockName= $this->getBlockNameByID($block_id );
        foreach ($blockName['opt']['task'] as $k=>$v  ){
            if($v<=0 ||$k=='progress' ) continue;
            if( isset($var['cnt_'. $k ] )){
                $re['task_cnt']+= $var['cnt_'. $k ];
                $re['cnt_'.$k]=$var['cnt_'. $k ];
            }
        }
        return $re;
        //$this->drExit($re );

    }

}