<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/10
 * Time: 17:34
 */

namespace model;


use model\lib\mq;

class weiboHelp extends model
{
    function updateWeiboQun($chatRoom){
        $where=['chatroom'=> $chatRoom,'type'=>[130 ,131]];
        $cnt=  $this->getLogin()->createTableQun()->getCount($where);
        $this->getLogin()->createTableQun()->updateByWhere($where ,['member_count'=>$cnt ]);
        return $this;
    }
    function addWeiboQun($chatRoom ,$acc, $re , $opt=[]  ){

        $wbuid =  drFun::getWeiboUid($acc['ali_uid']);
        if( !in_array( $wbuid, $re['members'])) $this->throw_exception( '你未加入群',19121014);


        if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权添加");

        $q_row = $this->getLogin()->createTableQun()->getRowByWhere(['chatroom'=> $chatRoom,'type'=>130] );
        if($q_row && $q_row['ma_user_id']!=$this->getLogin()->getUserId() ) $this->throw_exception("这个群已经被其他人拥有 请使用自建群");


        $cnt=  $this->getLogin()->createTableQun()->getCount(['chatroom'=> $chatRoom ,'chat_uid'=>$wbuid] );
        if( $cnt>0) $this->throw_exception("这个群 您之前加过！",  19121015 );
        $var=['name'=>$re['system_name'],'owner'=>$re['owner'],'chat_uid'=>$wbuid,'chatroom'=> $chatRoom,'type'=>$opt['qr_text']?130:131 ,'account_id'=> $acc['account_id'] ];
        $var['opt_value']= drFun::json_encode( ['url'=>$opt['qr_text']?$opt['qr_text']:$opt['url'],'members'=>$re['members']  ] );
        $var['user_id']= $acc['user_id'];
        $var['ma_user_id']= $acc['ma_user_id']>0? $acc['ma_user_id']:$acc['user_id'];
        $var['ctime']= time();
        $this->getLogin()->createTableQun()->append( $var );
        return $this;
    }

    function getQunMember( $where  ){
        $chat= $this->getLogin()->createTableQun()->getAll($where);
        return $chat;
    }

    function delQunByAccountID( $account_id ){
        $qunList = $this->getLogin()->createTableQun()->getAll( ['account_id'=>$account_id ] );
        $this->getLogin()->createTableQun()->delByWhere(['account_id'=>$account_id ] );
        foreach( $qunList as $v){
            $this->updateWeiboQun( $v['chatroom']);
        }
        return $this;
    }



    function updateLiveCntByAccountID(  $account_id ){
        $chatroom = $this->getLogin()->createTableQun()->getColByWhere( ['account_id'=>$account_id] ,['chatroom'] );
        //$acc= $this->getLogin()->createTablePayAccount()->
        $chatroom_acc= $this->getLogin()->createTableQun()->getAllByKeyArr(['chatroom'] ,['chatroom'=>$chatroom],[],[0,3000],['chatroom','account_id']);

        $acc_id=[];
        drFun::searchFromArray( $chatroom_acc,['account_id'],$acc_id );
        $acclist= $this->getLogin()->createTablePayAccount()->getColByWhere(['account_id'=> array_values($acc_id)],['account_id','online'] );

        $chat_cnt=[];
        $online=[1,11];
        foreach( $chatroom_acc as $k=> $v1){
            $chat_cnt[$k]=0;
            foreach( $v1 as $v){
                $gid= $v['chatroom'];
                $acc_id= $v['account_id'];

                if( in_array( $acclist[$acc_id] , $online) )  $chat_cnt[ $gid ]++;
            }
        }
        foreach( $chat_cnt as $k=>$cnt){
            $this->getLogin()->createTableQun()->updateByWhere( ['chatroom'=>$k], ['live_count'=>$cnt ]);
        }
        return $this;

        //$this->drExit( $chat_cnt );
    }

    function back( $arg){
        //$re=['re'=> drFun::json_encode($re)];
        $str= drFun::http_build_query($arg);
        drFun::cPost( 'http://qf3.zahei.com/client/payLogV3Weibo', $str, 10);
        $this->log( date("Y-m-d H:i:s"). " back>>". json_encode( $arg) );
    }
    public function mq( $arg ){
        //print_r( $arg );
        switch ($arg['cmd']){
            case 'wb.bill':
                $this->wb_bill($arg);
                break;
            case 'weibo.refresh.bill':
                $this->refreshBill($arg);
                break;
        }
    }

    private function refreshBill( $arg ){

        //print_r( $arg);
        $key= $arg['id'];
        $arg = $this->getRedis($key);
        //print_r( $arg);
        if( $arg['rBill'] && in_array($arg['oid'], $arg['rBill']['fa']['id']  ) ) return [];
        $wb = $this->createWb($arg);
        $arg['cmd']='weibo.refresh.bill';
        $arg['rBill']= $wb->setClient('weibo')->myHongBao();
        print_r( $arg);
        //
        if( $arg['rBill'] && in_array($arg['oid'], $arg['rBill']['fa']['id']  ) ) {
            $this->setRedis($key, $arg );
        }
        unset( $arg['rz']);
        $arg['rBill']= drFun::json_encode( $arg['rBill'] );
        $this->back( $arg);
    }

    /**
     * @param $arg
     * @return weibo
     */
    private function createWb($arg){
        $wb = new weibo();
        $wb->setH5Cookie( $arg['h5'])->setWeiboAppCookie($arg['app']);
        return $wb;
    }
    private function wb_bill( $arg ){
        $data= drFun::json_decode($arg['data']);
        $beizhu_key ='bill_'.$data['beizhu'] ;

        $arg2 = $this->getRedis( $beizhu_key);
        //print_r($arg);
        if( $arg2 ) return $this ;


        $wb = $this->createWb($arg);
        $re =$wb->setClient('weibo')->createQunHongbao($data['gid'],$data['amount'],$data['count'],$data['beizhu'] );

        $arg['rz']= drFun::json_encode( $re);
        $arg['oid']= drFun::cut($re['url'],'out_pay_id=','&') ;
        $this->setRedis($beizhu_key , $arg );
        $this->back($arg);
        $this->setRedis('weibo_'.$arg['aid'], $arg, 10*60 );
        return $this;
    }

    private function getRedis( $key ) {
        $arg = $this->getLogin()->createRedis()->get( $key);
        if( $arg )  return drFun::json_decode($arg );
        return false;
    }

    private function setRedis( $key,  $arg, $timeOut=180){
        return $this->getLogin()->createRedis()->set( $key, drFun::json_encode($arg ), $timeOut );
    }

    public function refreshReis2MQ(){
        $redis= $this->getLogin()->createRedis();
        $arr = $redis->keys("weibo_*");

        //$this->drExit( $arr );
        $mq= new mq();
        foreach( $arr as $v){
            $data=['cmd'=>'weibo.refresh.bill', 'id'=>$v ];
            $mq->rabbit_publish( 'weibo_redis', $data );
        }
        return $this;
    }


}