<?php
/**
 * 分析
 * Date: 2018/10/8
 * Time: 20:18
 */

namespace model;


class bi extends model
{
    private $tb_trade='mc_trade';
    private $tb_payLog="pay_log";

    function  tjTradeCntByMin( $where ){
        $all = $this->createSql()->select( $this->tb_trade, $where, [0,100000], ['ctime'] )->getAll();

        $pn=5; //5分钟

        $re[]=[];
        for( $h=0;$h<24;$h++ ){
            for($m=0;$m<60;$m++ ){
                $re[$h*100+intval($m/$pn) *$pn ]=0;
            }
        }

        foreach($all as $v ){
            $k= intval(date("H",$v['ctime']))*100+intval(date("i",$v['ctime'])/$pn )*$pn ;
            $re[$k]++;
        }
        $re2=[];
        foreach($re as $k=>$v ){
            $k2= intval($k/100).':'. $k%100;
            $re2[$k2]= $v ;
        }
        return $re2 ;
    }

    function tjTrade( $where ,$s_time ,$e_time  ){

        $where['>=']=['ctime'=>$s_time ];
        if( $e_time>0 ) $where['<=']=['ctime'=>$e_time ];
        $where['type']=[1,11];
        //$this->drExit( $where );
        $tall = $this->createSql()->select( $this->tb_trade, $where,[0,50000], ['price','realprice','ctime','notify_time','trade_id'])->getAll();
        //$today=['cnt'=>0,'price'=>0,'ctime'=>0 ];
        $re=[];
        $tid25=[];
        foreach($tall as $v){
            $k= date("Ymd", $v['notify_time']);
            //if( $k==20190528) $tid25[$v['trade_id']]= $v['trade_id'];
            $re[$k]['cnt']++;
            $re[$k]['price']+=$v['price'];
            $re[$k]['realprice']+=$v['realprice'];
        }
        $total=[];
        foreach( $re as $v ){
            foreach( $v as $k2=>$v2) $total[$k2]+= $v2;
        }
        $re['Total']= $total ;

        $this->assign('tid25',$tid25 );
        //$this->drExit( $re );
        return $re;
    }

    function isInTime($time, $s_time ,$e_time){
        if( $s_time<=0 && $e_time<=0 ) return true;
        if( $s_time<=0 && $time<= $e_time ) return true;
        if( $e_time<=0 && $time>= $s_time ) return true;
        if( $time<= $e_time && $time>= $s_time ) return true;
        return false;
    }

    function tjPayLog( $where ,$s_time ,$e_time ){
        $where_trade = $where;
        $where['>=']=['ctime'=>$s_time ];
        $where_trade['>=']=['notify_time'=>$s_time ];
        if( $e_time>0 ) {
            $where['<=']=['ctime'=>$e_time ];
            $where_trade['<=']=['notify_time'=>$e_time ];
        }
        $where['opt_type']=10;
        $where_trade['type']=[1,11];

        $dbs=  $this->createSql()->select( $this->tb_payLog, $where,[0,50000],['id','ctime','fee','trade_id'] );

        //$this->assign('dsql', $dbs->getSQL() );
        $tall = $dbs->getAll();

        if( !$tall ) return [];


        $this->createSql()->merge( $this->tb_trade, 'trade_id',$tall,['trade_id','ctime','notify_time'] );
        //
        $pid24=[];
        $re=[];
        $trade_arr = [];
        foreach($tall as $v ){
            if($v['trade_id'] ){
                $time = $v['trade_id_merge']['ctime'];
                /*
                if( $v['trade_id_merge']['notify_time']>0 && date("Ymd",  $v['trade_id_merge']['notify_time'] ) !=  date("Ymd",  $v['ctime'] ) ){
                    $time= $v['trade_id_merge']['notify_time'];
                }
                */
                if(  $v['trade_id_merge']['notify_time']>0 && ! $this->isInTime(  $v['trade_id_merge']['notify_time'], $s_time ,$e_time ) ){
                    continue; #回调时间没在时间范围内的去掉，---
                }
                $key= date("Ymd", $time );
                $trade_arr[ $v['trade_id'] ]= $v['trade_id'];

                //if( $key==20190528) $pid24[]= $v['trade_id'];

            }elseif( $v['fee']==1 || $v['fee']==1111){
                $key='test';
            }else{
                $key='no';
            }
            $re[$key]['cnt']++;
            $re[$key]['fee']+= $v['fee'];
        }

        #回调时间在范围内但是建立时间不在范围内的加进来 +++
        $tall =  $this->createSql()->select( $this->tb_trade, $where_trade,[0,50000], ['trade_id','ctime','notify_time','type','realprice'] )->getAll();
        $no_rz =[];
        $other=[];
        foreach ( $tall as $v ){
            if(! $trade_arr[  $v['trade_id'] ] ){
                $no_rz[  $v['trade_id'] ] = $v ;
                $key= date("Ymd", $v['ctime']  );
                $re[$key]['cnt']++;
                $re[$key]['fee']+= $v['realprice'];

                //if( $key==20190528) $pid24[]= $v['trade_id'];

                $other[ $v['trade_id'] ]= $key;
            }
        }

        //if( $no_rz ) $this->drExit( $no_rz );

        $total=[];
        foreach( $re as $v ){
            foreach( $v as $k2=>$v2) $total[$k2]+= $v2;
        }
        $re['Total']= $total ;

        $this->assign('pid24', $pid24)->assign('other', $other )->assign('m28', $re['20190528']);

        //$this->drExit( $re );
        return $re ;
    }

}