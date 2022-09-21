<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/29
 * Time: 14:04
 */

namespace model;


class taobaoapi extends model
{

    private $sessionKey='';
    private $methodName='';
    private $requestBody=[];
    private $rf_token='';
    private $nick='';

    public function setSession( $sessionKey ){
        $this->sessionKey= $sessionKey;
        return $this;
    }
    public function setRfToken($rf_token ){
        $this->rf_token= $rf_token;
        return $this;
    }

    public function setNick( $nike ){
        $this->nick= $nike;
        return $this;
    }


    function taobao_daogoubao_eticket_query($operator_name, $eticket_code){
        //
        $this->clear();
        $methodName='taobao.daogoubao.eticket.query';
        $methodName='taobao.trade.amount.get';
        $methodName='taobao.daogoubao.eticket.consume';
        $methodName='alibaba.omni.eticket.consume';

        $rq=['operator_name'=>$operator_name ,'total_amount'=>500 ,'eticket_code'=>$eticket_code ];
        return  $this->setRequestBody($rq)->setMethod($methodName)->todo();

    }

    /**
     * 获取支付信息 主要去支付宝alipay_no
     * @param $tid
     * @return mixed
     */
    function taobao_trade_amount_get($tid){
        $this->clear();
        $methodName='taobao.trade.amount.get';
        $rq=['fields'=>'tid,oid,alipay_no,total_fee,post_fee,created,pay_time,end_time' ,'tid'=>$tid ];
        return $this->setRequestBody($rq)->setMethod($methodName)->todo();
    }

    /**
     * 获取订单详情 含有 核销的手机号码 order_attr.mobile
     * @param $tid
     * @return mixed
     * @throws
     */
    function taobao_trade_fullinfo_get( $tid ){
        //
        $this->clear();
        $methodName='taobao.trade.fullinfo.get';
        //$methodName='taobao.trade.get';
        $rq=['fields'=>'tid,created,pay_time,end_time,type,status,payment,orders,promotion_details,receiver_mobile,receiver_phone,seller_nick,buyer_nick,buyer_message,trade_attr' ,'tid'=>$tid ];
        $trade= $this->setRequestBody($rq)->setMethod($methodName)->todo();
        $trade= $trade['trade'];
        if( !$trade) $this->throw_exception( "获取商品失败", 19090705);
        return $trade;
    }

    /**
     * 以创建上时间排序 获得订单列表
     * @param array $rq
     * @return mixed
     */
    function taobao_trades_sold_get( $rq=[]){
        $this->clear();
        $methodName='taobao.trades.sold.get';
        if( !isset($rq['fields']) ) $rq['fields']= 'tid,type,status,payment,orders,rx_audit_status,pay_time,buyer_nick';
        if( !isset($rq['page_size']) ) $rq['page_size']= 50 ;
        return $this->setRequestBody($rq)->setMethod($methodName)->todo();
    }

    /**
     * 以修复时间为排序活动订单列表
     * @param array $rq
     * @return mixed
     */
    function taobao_trades_sold_increment_get($rq=[ ]){
        $this->clear();
        $methodName='taobao.trades.sold.increment.get';

        if( !isset($rq['fields']) ) $rq['fields']= 'tid,type,status,payment,orders,rx_audit_status,pay_time,buyer_nick,created';
        if( !isset($rq['page_size']) ) $rq['page_size']= 50 ;
        if( !isset($rq['start_modified']) ) $rq['start_modified']= date("Y-m-d H:i:s", time()-3600*20 ) ;
        if( !isset($rq['end_modified']) ) $rq['end_modified']= date("Y-m-d H:i:s", time() ) ;
        if( !isset($rq['status']) )  $rq['status']= 'WAIT_SELLER_SEND_GOODS';
        if( !isset($rq['page_no']) )  $rq['page_no']= 1;

        if( $rq['status']=='all' ){
            unset(  $rq['status'] );
        }
        return $this->setRequestBody($rq)->setMethod($methodName)->todo();
    }

    /**
     * 无需物流（虚拟）发货处理
     * @param $tid
     * @return mixed
     */
    function taobao_logistics_dummy_send( $tid ){
        $this->clear();
        $methodName='taobao.logistics.dummy.send';
        $rq=['tid'=>$tid ];
        return $this->setRequestBody($rq)->setMethod($methodName)->todo();
    }



    /**
     * 自己联系物流（线下物流）发货
     *
     * @param $tid
     * @param $out_sid
     * @param $company_code
     * @return mixed
     */
    function taobao_logistics_offline_send( $tid, $out_sid,$company_code ){
        $this->clear();
        $methodName='taobao.logistics.offline.send';
        $rq=['tid'=>$tid ,'out_sid'=> $out_sid,'company_code'=>$company_code ];
        return $this->setRequestBody($rq)->setMethod($methodName)->todo();
    }

    /**
     * 获取商户信息
     * @return mixed
     */
    function taobao_user_seller_get(   ){
        $this->clear();
        $methodName='taobao.user.seller.get';
        $rq=[ 'fields'=>'nick,sex,user_id' ];
        return $this->setRequestBody($rq)->setMethod($methodName)->todo();
    }

    /**
     * 开通商户 消息通知
     * @return $this
     */
    function taobao_tmc_user_permit(){
        //taobao.tmc.user.permit
        $url='http://api.taoesou.com/api.aspx?action=openTmc&';
        $url.='sign='.$this->getSign().'&SessionKey='. $this->sessionKey .'&Refresh_Token='. $this->rf_token."&groupName=zhifutu";
        $url.='&nick='.urlencode( $this->nick ); //

        $top=['taobao_trade_TradeBuyerPay','taobao_trade_TradeAlipayCreate' ,'taobao_rdcaligenius_OrderMsgSend','taobao_trade_TradeTimeoutRemind'];
        $top[]='taobao_trade_TradeClose';
        //$top[]='taobao_trade_TradeClose';
        $url.='&topics='.urlencode(implode(',', $top) );

        $str= $this->curlPost( $url );

        $this->log("taobao_tmc_user_permit= ". $this->nick."\t". $str);
        return $this;
        //echo $str."\n\n".'<br><br>';

        //$this->drExit( $url );
    }


    function setMethod( $methodName ){
        $this->methodName= $methodName;
        return $this;
    }
    function setRequestBody( $requestBody ){
        $this->requestBody = $requestBody;
        return $this;
    }

    function clear(){
        $this->methodName='';
        $this->requestBody=[];
        return $this;
    }

    function getReqUrl(){
        $url='http://api.taoesou.com/Api.aspx?action=executeTopApi&methodName='.$this->methodName;
        $url.='&requestObjectJson='.urlencode(json_encode( $this->requestBody)).'&sign='.$this->getSign() .'&SessionKey='. $this->sessionKey;
        //die($url );
        return $url;
    }

    function getSign(){
        $time = intval(time()/60);
        $key = '1b46af52a8dcaa822b98b1be9d447bb2';
        return base64_encode(md5(md5($time.$key)));

    }

    function todo(){
        $str= $this->curlPost( $this->getReqUrl() );
        //$this->log($str );
        $re= json_decode( $str,true);

        if($re) {
            $this->log( "-==TaobaoTODO==--".$this->methodName."---" .  json_encode( $this->requestBody ) ."\n" .  $re['Body'] ); // var_export($this->requestBody, true )
            $re= json_decode( $re['Body'],true);
            if(!$re) return $str;
            foreach ( $re as $v ) return $v ;
        }
        return $str;
    }
    /*
    function log( $str ){

        $str="\n". $this->methodName."\n". var_export($this->requestBody, true )."\n". $str;
        ( $str );

    }
    */



    function curlPost($url,$post_data = ""){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);


        curl_setopt($ch, CURLOPT_TIMEOUT,10);
        if( strpos($url,'https')!== false  ){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        }
        if(!empty($post_data)){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post_data)? $this->http_build_query($post_data): $post_data );
        }
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
    function http_build_query( $arr){
        return  strtr( http_build_query($arr) ,  array('&amp;'=>'&') );
    }

}