<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/1/6
 * Time: 10:27
 */

namespace model;


class mserver extends model
{
    private $host = 'http://v1.atbaidu.com';
    private $type='10086';
    function setHost( $host ){
        $this->host= $host; //;
        return $this;
    }

    function getHost(){
        return $this->host;
    }


    function post(  $data ){
        // ,$url='test/mq/'

        if( $data['cmd']=='') $this->throw_exception("请先定义CMD",20010701);

        $url= 'mclient/mq' ;
        switch ( $this->type){
            case '10086':
                $url=  'mclient/mq/10086' ;
                break;
        }

        $str= is_array( $data)? drFun::http_build_query($data): $data ;
        //$this->log( $this->host.'/'. trim($url ,'/') ,'debug.log');
        $this->log('toMq>>'. $str );
        drFun::cPost( $this->host.'/'. trim($url ,'/'),$str ,10);
        return $str;
    }

    function sendOnline320(){

        //$sql="s";
        $where=['type'=>320,'online'=>[1,11] ];
        $where['>']['clienttime'] = time()-20*60;
        $file= ['account_id','zhifu_account', 'user_id'];
        $accounts= $this->getLogin()->createTablePayAccount()->getAllByKey('account_id',$where ,[],[0,1000], $file);
        //$this->drExit( $accounts );
        if( !$accounts) return $this;

        $where= ['account_id'=> array_keys($accounts), 'type'=>320 ];
        $attr= $this->getLogin()->createTablePayAccountAttr()->getColByWhere( $where,['account_id','attr'] );

        foreach( $accounts as $k=>$v){
            $cookie= $attr[$k];
            if( !$cookie) continue;
            $v['cookie']=$cookie;
            $v['cmd']='cn.10086.order';
            //$this->drExit( $v );
            $this->post( $v );
        }
        return $this;
    }

    /*
    function sendCreateBill($fee_type ){
        $where=['type'=>[1,4],'fee_type'=>$fee_type ];
        $where['>']['endtime']= time();
        $hf_trade = $this->getLogin()->createTableHfTrade()->getAll($where );
        if( !$hf_trade ) return $this;

    }
    */

    function createBillByHfID( $hf_id, $opt=[]){
        $huafei = $this->getLogin()->createTableHfTrade()->getRowByKey($hf_id);
        //if( !$huafei) $this->
        if( $opt['user_id'] && $huafei['user_id']!= $opt['user_id']) $this->throw_exception('非法构建!', 20010820);
        if( $huafei['endtime']<time() ) $this->throw_exception('已过截止时间!', 20010817);
        if( !in_array( $huafei['type'], $this->getCanCreateType() )) $this->throw_exception('状态不可构建', 20010818);

        $acc_id= $this->getPayAccountIdByHuafei($huafei );

        $data=['hf_id'=>$huafei['hf_id'],'fee'=>$huafei['fee'],'tel'=>$huafei['tel'],'user_id'=>$huafei['user_id'],'account_id'=>$acc_id  ];

        $data['cmd']='cn.10086.createBill.'. $huafei['fee_type'];
        $row =  $this->getLogin()->createTablePayAccountAttr()->getRowByWhere(['account_id'=>$acc_id,'type'=> $huafei['fee_type']]  );
        if( !$row) $this->throw_exception( '哎呀未登录！', 20010822 );
        $data['cookie']=$row['attr'];

        $this->getLogin()->createTableHfTrade()->updateByKey($hf_id,['type'=>3 ] );
        $this->post($data);

        return $this;


        //$data=['cmd'=>];

        //$acc =
    }
    function getPayAccountIdByHuafei( $huafei){
        //,4 作为不能生成订单存在！
        $where=['user_id'=> $huafei['user_id'],'online'=>[1,11],'type'=> $huafei['fee_type'] ];
        $where['>']['clienttime']= time()-5*60;
        $acc= $this->getLogin()->createQrPay()->getAccountIDByWhere( $where );
        if( !$acc) $this->throw_exception("无可用账号",20010819 );
        //if( !$acc) $this->throw_exception("无可用账号",20010819 );
        return $acc[ rand(0, count($acc)-1)];
    }

    function getCanCreateType(){

        return [1,4];
    }
    function getTimeOutType(){
        return [1,2,3,4,5,6];
    }

    /**
     *
     * 1下单  2构建超时 3构建中 4构建失败 5构建成功 6不可构建  10支付中 11支付成功 20取消 21超时
     * 下单->建单中->建单成功->支付中->支付成功
     *
     * @param string $type
     * @return array|mixed
     * @throws drException
     */
    function getType( $type='all' ){

        $tarr=[1=>'下单',2=>'构建过期',3=>'构建中' ,4=>'构建失败' ,5=>'构建成功',6=>'不可构建' ,10=>'支付中', 11=>'支付成功',20=>'取消',21=>'过期' ];

        if( $type=='all') return $tarr;
        if( !isset( $tarr[$type] )) $this->throw_exception("此种状态不能存在", 20010803 );
        return  $tarr[$type];
    }

    function getHuafeType($type='all'){
        $tarr= [320=>'移动', 325=>'联通',329=>'电信'];
        return $tarr;
    }

    function huafei( $var, $mc){
        drFun::checkTel( $var['tel']);
        if( !$var['order_no']  ) $this->throw_exception( "请填写 order_no ",20010806);
        if( !$var['notify_url']  ) $this->throw_exception( "请填写 notify_url ",20010806);
        if( !in_array( $var['fee_type'],[320] )  ) $this->throw_exception( "目前仅支持移动号码",20010807);
        if( $var['fee']<=0 || $var['fee']%100>0 ) $this->throw_exception( "话费必须为大于0的整数",20010812);
        if( $var['endtime']<time() ) $this->throw_exception( "充值截止时间必须大于当前时间",20010813);

        $where=['merchant_id'=>$mc['merchant_id'], 'order_no'=>$var['order_no'] ];
        $cnt = $this->getLogin()->createTableHfTrade()->getCount( $where );
        if( $cnt>0) $this->throw_exception( "请勿重复下单",  20010808);

        $var['merchant_id']= $mc['merchant_id'];
        $var['ctime']=  time();
        $var['type']=  1 ;
        $var['user_id']=  $this->getLogin()->getHuaFeiMcCuser( $mc['merchant_id'] ) ;

        $this->getLogin()->createTableHfTrade()->append($var);
        $lastID= $this->getLogin()->createSql()->lastID();

        $re['fee_id']= $lastID;
        return $re;
    }


}