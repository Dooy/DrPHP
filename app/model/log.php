<?php
/**
 * 日志操作
 *
 * User: zahei.com
 * Date: 2017/8/26
 * Time: 16:33
 */

namespace model;


use model\lib\cache;

class log extends model
{
    private $table= 'user_gt_log'; //book_log
    private $user_id = 0;

    function __construct( $table ,$user_id){
        $this->setTable( $table )->setUserId( $user_id );
    }

    function setUserId( $user_id ){
        $this->user_id = $user_id;
        return $this;
    }
    function setTable( $table ){
        $this->table= $table;
        $table_arr=['user_gt_log'=>'user_gt_log','book_log'=>'book_log' ,'user_recycle_log'=>'user_recycle_log','chat_log'=>'chat_log'];
        $table_arr['pad_log']='pad_log';
        $table_arr['wenda_log']='wenda_log';
        $table_arr['set_log']='set_log'; #放一些一般设置
        $table_arr['pay_log']='pay_log'; #师傅内容
        $table_arr['pay_account_log']='pay_account_log'; #备注账号备注

        if( ! isset($table_arr[ $table ])) $this->throw_exception( "表不存在!",6401 );
        return $this;
    }

    function getTable(){
        return $this->table;
    }

    /**
     * 获取user_id 如果未登录 则抛出异常
     * @return int
     * @throws drException
     */
    function getUserId(){
        if( $this->user_id<=0) $this->throw_exception( "用户未登录",6402);
        return $this->user_id;
    }

    /**
     * 获取字段
     * @return array
     */
    function getFile(){
        if( $this->table=='book_log' ) return ['opt_id','opt_type','user_id','ctime'];
        if( $this->table=='pad_log' ) return ['opt_id','opt_type','user_id','ctime','opt_value','name','school_id','school'];
        if( $this->table=='pay_account_log' ) return ['opt_id','opt_type','user_id','ctime','opt_value','yuer','process'];
        if( $this->table=='pay_log' )
            return ['md5','opt_id','opt_type','user_id','ctime','opt_value','ltime','fee','account_id','pay_type','qr_id','trade_id'
                ,'ip','ali_uid','buyer','ali_trade_no','ali_beizhu','ali_account','ma_user_id' ];
        return  ['opt_id','opt_type','user_id','ctime','opt_value'];
    }

    /**
     * 增加记录
     * @param int $opt_id 如果是user_recycle_log opt_id经量使用user_id
     * @param int $opt_type
     * @param mixed $opt_value
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function append( $opt_id, $opt_type,$opt_value='', $opt=[] ){
        $type = $this->getType( $opt_type );

        if( is_array( $opt_value )){
            $opt_value = drFun::json_encode( $opt_value );
        }


        $var= $opt;
        $var[ 'opt_id']= $opt_id;
        $var[ 'opt_type']= $opt_type;
        $var[ 'opt_value']= $opt_value;
        $var['ctime'] = $opt['ctime']? $opt['ctime'] :time();
        $var['user_id']=  $opt['user_id']? $opt['user_id'] : $this->getUserId();
        if( !isset($type['op'] )){
            $this->insert( $this->table, $var, $this->getFile() );
            return $this;
        }

        $where=[ 'opt_id'=>$opt_id,'user_id'=>  $var['user_id'] ,'opt_type'=> $opt_type  ];
        switch ( $type['op'] ){
            case 'one':
                $row = $this->createSql()->select( $this->table, $where)->getRow();
                if( $row ) $this->update(  $this->table,$where,$var,  $this->getFile() );
                else   $this->insert( $this->table, $var, $this->getFile() );
                break;
            case 'onedel':
                $row = $this->createSql()->select( $this->table, $where)->getRow();
                if( $row ) $this->createSql()->delete( $this->table,['id'=> $row['id'] ] )->query();//$this->update(  $this->table,$where,$var,  $this->getFile() );
                else $this->insert( $this->table, $var, $this->getFile() );
                break;
            case 'pay_check':

                if( in_array($var['pay_type'],[31,32,33,35,36,37,38,39,351,80,90,91,92,93,94,96 ,120,130,320,139,150,239 ] )){
                    $where2=['ali_trade_no'=> $var['ali_trade_no'] ];
                    $cnt = $this->createSql()->getCount($this->table, $where2)->getOne();
                    if ($cnt > 0) $this->throw_exception('重复上传', 6409);
                }else{ //if(  $var['pay_type']!=21 ) { #账单上传由md5来检查了
                    $where2=[ 'opt_id'=>$opt_id,'ltime'=>  $var['ltime'] ,'fee'=>$var['fee'] ] ; //,'account_id'=> $var['account_id']

                    if( $var['account_id'] ) $where2['account_id']=$var['account_id'];

                    $cnt = $this->createSql()->getCount($this->table, $where2)->getOne();

                    if($_GET['dds']){
                        echo "cnt=". $cnt."\n\n" ;
                        print_r($var);
                        $this->drExit($where2);
                    }

                    if ($cnt > 0){
                        //$this->assign('wh', $where2 );

                        $this->throw_exception('重复上传', 6408);
                    }
                }
                $this->insert( $this->table, $var, $this->getFile() );
                break;
            default:
                $this->insert( $this->table, $var, $this->getFile() );
                break;
        }

        return $this;

    }

    function getById( $id ){
        return $this->createSql()->select($this->table,['id'=>$id])->getRow();
    }

    function getByOptID($opt_id,$type='all' ){
        $where=['opt_id'=>$opt_id];
        if( $type!='all' ){
            $this->getType($type );
            $where['type']= $type;
        }
        $list = $this->createSql()->select($this->getTable(), $where)->getAll();
        $this->decodeList($list);
        return $list;
    }

    function getType( $type='all'){
        $type_arr=[];
        switch ($this->table){
            case 'user_recycle_log':
                # 201~300 删除
                $type_arr =[201=>['n'=>'主题','t'=>'book_topic'],202=>['n'=>'班级','t'=>'class'] ,203=>['n'=>'选书','t'=>'book_user']
                    ,204=>['n'=>'天天朗读','t'=>'du_daily']
                    ,205=>['n'=>'句子翻译','t'=>'snt']
                    ,206=>['n'=>'笔记','t'=>'novel_comment']
                    ,207=>['n'=>'问答','t'=>'wenda']
                    ,220=>['n'=>'财务打款','t'=>'mc_finance']
                    ,221=>['n'=>'日志','t'=>'pay_log']
                    ,222=>['n'=>'收款账号','t'=>'pay_account']
                    ,501=>['n'=>'码商账单多扣','t'=>'ma_bill']
                    ,502=>['n'=>'商户','t'=>'merchant']
                ];
                break;
            case 'user_gt_log':
                #0-99 单人用户
                $type_arr =[1=>['n'=>'导入'],2=>['n'=>'单人添加'],3=>['n'=>'沟通记录'],4=>['n'=>'修改学校'] ];



                #101~200 关于学校
                $type_arr[101]= ['n'=>'(校)纪要'];
                $type_arr[102]= ['n'=>'(校)批量短信'];

                $type_arr[10]= ['n'=>'补单申请'];
                $type_arr[11]= ['n'=>'补单成功'];
                $type_arr[12]= ['n'=>'补单驳回'];
                $type_arr[103]= ['n'=>'清账'];
                break;
            case 'chat_log':
                #401~449
                $type_arr[401]= ['n'=>'群内消息'];
                break;

            case 'pad_log':
                #450~479跟主题任务相关  480~499其他
                $type_arr=[ 450=>['n'=>'发布主题'],  453=>['n'=>'发布朗读'],  454=>['n'=>'期中概要'] ,  455=>['n'=>'期末报告'],  456=>['n'=>'发布摘抄']
                    ,480=>['n'=>'回复主题'],481=>['n'=>'开始阅读'] //,482=>['n'=>'阅读笔记'],483=>['n'=>'阅读查词']
                    ,484=>['n'=>'天天朗读']   ,485=>['n'=>'完成阅读'],486=>['n'=>'阅读查词'],487=>['n'=>'读书笔记']
                ];
                break;
            case 'wenda_log':
                $type_arr=[];
                $type_arr[10]=['n'=>'老师评分','op'=>'one'];
                $type_arr[11]=['n'=>'学生评分','op'=>'one'];
                break;
            case 'set_log':
                $type_arr=[];
                $type_arr[10]=['n'=>'设置','op'=>'one','code'=>1 ];
                $type_arr[100]=['n'=>'学校首页区块','op'=>'one'  ];
                break;
            case 'pay_log':
                $type_arr[10]=['n'=>'记录','op'=>'pay_check' ];
                $type_arr[401]=['n'=>'非法忽略','op'=>'pay_check' ];
                $type_arr[402]=['n'=>'重复忽略','op'=>'pay_check' ];
                $type_arr[403]=['n'=>'重复忽略','op'=>'pay_check' ];
                break;
            case 'pay_account_log':
                $type_arr = $this->getLogin()->createQrPay()->getTypeOnlineV2();
                unset( $type_arr[0] );
                break;
        }


        if( isset( $type_arr[ $type] )) return $type_arr[ $type] ;
        if( $type==='all' )return $type_arr;
        $this->throw_exception( "opt_type=".$type.' 不存在！'  ,6403);
    }

    /**
     * @param int $opt_id
     * @param array $opt
     * @return array
     */
    function getListWithPage( $opt_id, $opt=[]){
        $where= [];

        if( isset($opt['where'] ) ){
            $where = $opt['where'] ;
        } elseif( isset($opt['type'] )) $where['opt_type'] = $opt['type'];
        $where['opt_id']=  $opt_id ;

        $re = $this->createSql()->selectWithPage( $this->table, $where, 10 );
        $re['type']= $this->getType( );
        return $re;
    }

    function getList( $opt_id, $opt=[]){
        $where= [];
        if( isset($opt['where'] ) ){
            $where = $opt['where'] ;
        } elseif( isset($opt['type'] )) $where['opt_type'] = $opt['type'];
        if( $opt_id ) $where['opt_id']=  $opt_id ;
        $limit = isset( $opt['limit'] )? $opt['limit']: [0,10];

        $list  = $this->createSql()->select( $this->table, $where,$limit ,[],['id'=>'desc'])->getAll();
        if($opt['decode']){
//            foreach ( $list as $k=>&$v ){
//                //$list[$k]['opt_value']= drFun::json_decode( $v['opt_value']);
//                $this->decode($v );
//            }
            $this->decodeList( $list );
        }
        return $list;
    }

    private function decodeList( &$list){
        foreach ( $list as $k=>&$v ){
            //$list[$k]['opt_value']= drFun::json_decode( $v['opt_value']);
            $this->decode($v );
        }
        return $this;
    }

    private function decode( &$v ){
        if(! isset($v['opt_value'] ))       return $this;
        $tArr =  drFun::json_decode( $v['opt_value']);
        if( $tArr  ) $v['opt_value'] =$tArr;
        return $this;
    }

    function getAllListWithPage( $opt=[] ){
        $where= '1'; $order= [];
        if( isset($opt['where'] ) ) $where = $opt['where'] ;
        if( isset($opt['order'] ) ) $order = $opt['order'] ;

        $re = $this->createSql()->selectWithPage( $this->table, $where,30,[],$order  );
        $re['type']= $this->getType( );
        if($opt['decode']) {
            foreach ($re['list'] as $k => &$v) {
                //$re['list'][$k]['opt_value'] = drFun::json_decode($v['opt_value']);
                $this->decode( $v );
            }
        }
        foreach( $re['list'] as &$v){
            unset($v['ali_account']);
        }
       // $re['list'] = 'good';
        return $re ;
    }

    function marge( & $list, $key, $opt=[]  ){
        $opt_id=[];
        drFun::searchFromArray( $list, [ $key], $opt_id );
        if( !$opt_id ) return $this;

        $where= ['opt_id'=> $opt_id ];
        $op_var = $this->createSql()->select($this->table,$where )->getAllByKeyArr( ['opt_id','opt_type','user_id' ]);
        //$this->drExit( $op_var);
        if( isset($list[$key] )){
            $list['haoce_log']= $op_var[ $list[$key] ];
            return $this;
        }
        foreach ( $list as &$v ){
            $v['haoce_log']= $op_var[ $v[$key] ];
        }
        return $this;
    }

    function updateByWhere( $where, $file ){
        $this->update( $this->getTable(), $where, $file, $this->getFile() );
    }

    function getCountByWhere( $where ){
        return $this->createSql()->getCount($this->getTable(), $where )->getOne() ;
    }

}