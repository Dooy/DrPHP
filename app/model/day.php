<?php
/**
 * 日统计
 * User: Administrator
 * Date: 2018/9/8
 * Time: 10:08
 */

namespace model;


class day extends model
{
    private $mc_id;
    private $day;
    private $time_s;
    private $time_e;

    private $tb_trade="mc_trade";
    private $tb_finance='mc_finance';
    private $tb_day='mc_day';

    private $file_day=['merchant_id','day','trade_total_cnt','trade_total_realprice','trade_1_cnt','trade_1_realprice'
        ,'trade_11_cnt','trade_11_realprice','finance_cnt','finance_fee','utime','notify','notify_cnt','notify_all','notify_all_cnt','fee'];

//    public function day($mc_id='',$day=''){
//        //$this->setMcID( $mc_id)->setDay( $day);
//        $this->mc_id= $mc_id;
//        $this->day= $day;
//    }
    public function __construct( $mc_id='',$day='')
    {
        parent::__construct(false);
    }

    public function setMcID($mc_id){
        $this->mc_id= $mc_id;
        return $this;
    }
    function getMcID(){
        if(  !$this->mc_id ||$this->mc_id<=0 ) $this->throw_exception( "先设置商户ID",8909001);
        return $this->mc_id;
    }

    function setDay( $day){
        $time = strtotime( $day);
        if(  $time <=0) $this->throw_exception( "日期格式错误 2018-08-08",8909002);
        $this->day = date("Ymd",$time );
        $this->time_s= strtotime( date("Y-m-d",$time ) );
        $this->time_e = $this->time_s+ 86400-1;
        return $this;
    }
    function getDay(){
        if(! $this->day )$this->throw_exception( "先设置日期",8909003);
        return  $this->day;
    }

    /**
     * @param $var
     * @return $this
     * @throws drException
     */
    private  function append( $var ){
        $where = ['merchant_id'=> $this->getMcID(),'day'=>$this->getDay() ];
        $row = $this->createSql()->select( $this->tb_day, $where )->getRow();
        $var['utime']= time();
        if( $row ){
            unset( $var['merchant_id'] );
            unset( $var['day'] );
            $this->update( $this->tb_day, ['md_id'=>$row['md_id']], $var, $this->file_day );
        }else{
            $var['merchant_id']= $this->getMcID();
            $var['day']= $this->getDay();
            $this->insert( $this->tb_day, $var ,$this->file_day );
        }
        return $this;
    }

    function appendByTrade( ){
        $this->getDay();
        $where = ['merchant_id'=> $this->getMcID(),'>='=>['ctime'=>$this->time_s ] ,'<='=>['ctime'=>$this->time_e] ];
        $group  = $this->getLogin()->createQrPay()->tjTradeGroup('type', $where );

        $var['trade_1_cnt']= $group[1]['cnt'];
        $var['trade_1_realprice']= $group[1]['realprice'];

        $var['trade_11_cnt']= $group[11]['cnt'];
        $var['trade_11_realprice']= $group[11]['realprice'];

        $total_cnt=0;
        $total_realprice=0;
        foreach( $group as $k=>$v ){
            $total_cnt+= $v['cnt'];
            $total_realprice+= $v['realprice'];
        }
        if( $total_cnt<=0  ){
            $this->log("appendByTrade no\n",'debug.log');
            return $this;
        }
        $var['trade_total_cnt'] = $total_cnt;
        $var['trade_total_realprice'] = $total_realprice;

        $this->append( $var );
        return $this;
    }

    function appendByTradeNotify(){
        $this->getDay();
        $where = ['merchant_id'=> $this->getMcID(),'>='=>['notify_time'=>$this->time_s ] ,'<='=>['notify_time'=>$this->time_e] ];
        $row_all =  $this->getLogin()->createQrPay()->tjTrade(  $where );
        $where['>=']['ctime']= $this->time_s;
        $where['<=']['ctime']= $this->time_e;
        $row= $this->getLogin()->createQrPay()->tjTrade(  $where );

        if( $row_all['cnt']<=0 && $row['cnt']<=0 ) {
            $this->log("appendByTradeNotify no\t".$this->getMcID() ,'debug.log');
            return $this;
        }

        $mc= $this->getLogin()->createQrPay()->getMerchantByID( $this->getMcID() );

        $var=[];
        $var['notify']= $row['price'];//$row['realprice'];
        $var['notify_cnt']=$row['cnt'];
        $var['notify_all']=$row_all['price'] ;// $row_all['realprice'];
        $var['notify_all_cnt']= $row_all['cnt'];
        $var['fee']= intval($mc['rate']*$var['notify_all']/10000 );
        //$this->drExit( $var );
        $this->append( $var );
        return $this;
    }

    function appendByFinance(){
        $this->getDay();

        $where = ['merchant_id'=> $this->getMcID() ,'>='=>['run_time'=>$this->time_s ] ,'<='=>['run_time'=>$this->time_e] ];

        $row = $this->getLogin()->createQrPay()->tjFinance(  $where );

        if( $row['cnt']<=0 ) {
            $this->log("appendByFinance no",'debug.log');
            return $this;
        }

        $var['finance_cnt'] = $row['cnt'];
        $var['finance_fee'] = $row['fee'];
        $this->append( $var );
        return $this;
    }

    function getDayListWithPage( $where ,$opt=[] ){
        $order = ['day'=>'desc'] ;//$opt['order']?   $opt['order']:['day'=>'desc'];
        $every = 1000 ;
        if(  $opt['every']>0 ) $every=  $opt['every'];
        $tall= $this->createSql()->selectWithPage( $this->tb_day, $where ,$every ,[], $order);
        $this->count( $tall['list'] );
        return $tall ;
    }

    function dayExList( $dList, $where){
        $re=[];
        $mc_day=[];
        $where['type']=[1,21];

        $tall= $this->createSql()->select('mc_export', $where,[0,20000],['money','real_money','ctime'],['export_id'=>'asc'])->getAll();
        //return 2;
        $total=0;
        foreach( $tall as $v ){
            $total+= $v['real_money'] ;
            $dtime = date("Ymd", $v['ctime']);
            $mc_day[ $dtime]['cnt']++;
            $mc_day[ $dtime]['money']+= $v['money'];
            $mc_day[ $dtime]['fee']+= -($v['real_money']+$v['money']);
            $mc_day[ $dtime]['real_money']+= $v['real_money'] ;
            //$mc_day[ $dtime]['total'] = $total ;
        }
        unset( $tall);
        $re=[];
        foreach( $dList['list'] as $v ){
            $dtime= $v['day'];
            $re[  $dtime]['in']= $v;
            if( isset( $mc_day[$dtime])){
                $re[$dtime]['out']= $mc_day[$dtime];
                unset( $mc_day[$dtime] );
            }
        }
        if( $mc_day ){
            foreach( $mc_day as $k=>$v){
                $re[$k]['out']= $v ;
            }
        }
        ksort($re);

        $in_total= $out_total=0;
        $in_fee= $out_fee= 0;
        foreach( $re as $k=>$v){
            if($v['in']) {
                $in_total += $v['in']['notify_all'];
                $in_fee += $v['in']['fee'];
            }
            if( $v['out']) {
                $out_total += $v['out']['money'];
                $out_fee += $v['out']['fee'];


            }
            $re[$k]['in_total']= $in_total;
            $re[$k]['in_fee']= $in_fee;
            $re[$k]['out_total']= $out_total;
            $re[$k]['out_fee']= $out_fee;

            $re[$k]['yu']= $in_total-$in_fee-$out_total-$out_fee;
            $re[$k]['k']=$k;
            $re[$k]['today']= intval($v['in']['notify_all'])- intval($v['in']['fee'])- intval( $v['out']['money'])-intval($v['out']['fee']);
        }
        //$this->drExit( $mc_day );
        #$this->assign('mc_day', $re);
        //krsort( $re );
        $re=  array_reverse(array_values( $re ) );

        return $re ;
    }

    function count( &$list ){
        $total  = 0;
        $list = array_reverse( $list );
        foreach( $list as &$v){
            $v['d_totay']=  $v['finance_fee'] - $v['trade_1_realprice']- $v['trade_11_realprice'];
            $total+=  $v['d_totay'];
            $v['d_total'] = $total;
        }
        $list = array_reverse( $list );
        return $this;
    }



}