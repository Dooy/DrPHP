<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/23
 * Time: 16:00
 */

namespace model;


class trade extends model
{
    private $secret;
    private $md5_str;
    function setSecret( $secret ){
        $this->secret= $secret;
        return $this;
    }

    /**
     * @param $c_user_id
     * @return bool
     */
    function isDandu( $c_user_id ){
        $dan_c_uid=[66];
        return  in_array( $c_user_id,$dan_c_uid );
    }

    function isQqBay($c_user_id){
        $dan_c_uid=[115];
        return  in_array( $c_user_id,$dan_c_uid );
    }

    function isNoinMain($c_user_id){
        return $this->isDandu( $c_user_id ) || $this->isQqBay($c_user_id );
    }

    function createSign( $data ,$opt=[] ){
        if( !$data || !is_array($data) ){
            $this->throw_exception('数据必须是有效数据',2018082301 );
        }
        ksort( $data );

        unset( $data['order_user_name'] ); //用户名有特殊字符 不参与sign的计算

        $str='';
        if($opt['decode']){
            foreach ($data as $k => $v) $str .= ($k) . '=' .urldecode($v) . '&';
        }else {
            foreach ($data as $k => $v) $str .= ($k) . '=' . ($v) . '&';
        }

        $str.= 'app_secret='.$this->secret;

        //echo  $str ;
        //$this->drExit( $str );
        $this->md5_str =  strtolower( $str);
        if( $opt['noLower2'] ){
            $this->md5_str = $str;
            return md5(  $str);;

        }
        return md5( strtolower( $str));
    }

    function getMd5Str( ){
        return  $this->md5_str;
    }

    function urlDecode(&$var){
        foreach( $var as $k=>&$v) $v= urldecode( $v );
        return $this;
    }

    function getReturnDataByHuafei( $trade_row ){
        $arr=['order_no','tel','pay_time', 'fee' ];
        $data=[];
        foreach( $arr as $k ) $data[$k]= $trade_row[$k];
        $data['fee_id']= $trade_row['hf_id'];

        $mid= $trade_row['merchant_id'];
        $r_mid= $this->getLogin()->getRealMid( $mid );
        if($r_mid>0) $mid= $r_mid;
        $mc = $this->getLogin()->createQrPay()->getMerchantByID(  $mid);

        if( $mc['pid']>0 ){ #如果有父亲节点使用父亲节点
            $mc = $this->getLogin()->createQrPay()->getMerchantByID(  $mc['pid'] );
        }
        $this->setSecret( $mc['app_secret']);

        $data['sign']= $this->createSign( $data);

        return $data;

    }

    function getReturnData( $trade_row ){
        $arr=['order_no','order_user_id','order_user_id','attach','pay_type','price','realprice','pay_time','type'];
        $data=[];
        foreach( $arr as $k ) $data[$k]= $trade_row[$k];
        $data['trade_no']= $trade_row['trade_id'];
        $mid= $trade_row['merchant_id'];
        $r_mid= $this->getLogin()->getRealMid( $mid );
        if($r_mid>0) $mid= $r_mid;
        $mc = $this->getLogin()->createQrPay()->getMerchantByID(  $mid);

        if( $mc['pid']>0 ){ #如果有父亲节点使用父亲节点
            $mc = $this->getLogin()->createQrPay()->getMerchantByID(  $mc['pid'] );
        }
        $this->setSecret( $mc['app_secret']);

        $data['sign']= $this->createSign( $data);

        return $data;
    }
    function huafei_notify( $row){
        $re='';

        $trade_row = $trade  = $this->getLogin()->createTableHfTrade()->getRowByKey( $row['hf_id']);
        if( !$trade_row) $this->throw_exception("未找到话费记录",20011401 );

        if ($trade['notify_success'] =='1'   ) {
            $re = '已经执行过！请勿重复';
            return $re ;
        }

        //if( !$trade) $this->throw_exception("未找到话费记录",20011401 );
        $data = $this->getReturnData( $trade_row );
        $url = $trade_row['notify_url'];

        $post_data = $str= drFun::http_build_query( $data);

        $this->log("====huafei>> curl -k -d " .'"'. $str.'" ' ."\t". $url );
        $this->log("====huafei>> getMd5Str:" . $this->getMd5Str()  );

        $is_ok= false ;
        $str= $post_data;
        $start= microtime( true);
        drFun::cPost($url, $str, 5 );
        $re = $str;
        $this->log("re2huafei>> " . $re . "\t" . $data['order_no'] ."\t".( microtime( true)- $start )."\t". $trade_row['merchant_id'] );
        if(  trim($str)=='ok'  ) $is_ok= true;

        $opt=['+'=>['notify_cnt'=>1] ];

        if( $is_ok  ) {
            $opt['notify_success'] = 1;
            $opt['notify_time'] = time();
        }elseif(   (time()-$trade['pay_time'])<7200  && $trade['notify_cnt']<=20  ){
            $row['cmd']='cn.huafei.trade';
            $this->getLogin()->createQrPay()->toMqTrade($row, 'qf_fail');
        }
        $this->getLogin()->createTableHfTrade()->updateByKey( $row['hf_id'] ,$opt );
        return $re ;

    }
    /**
     * 异步通知 订单
     * @param $trade_row
     * @param string $re
     * @return $this
     * @throws drException
     */
    function notify( $trade_row ,&$re='' ){

        if( isset( $trade_row['cmd'] ) && $trade_row['cmd']=='cn.huafei.trade' ){
            $re= $this->huafei_notify( $trade_row );
            return $this;
        }
        $cnt=1;

        $m_arr=[]; #大面积延迟 , 8099 8111

        try {
            $trade = $this->getLogin()->createQrPay()->getTradeByID($trade_row['trade_id']);
            //if ($trade['notify_success'] == '1') {
            if( in_array( $trade['merchant_id'], $m_arr ) ) $cnt=3;

            if ($trade['notify_success'] =='1' && $trade['notify_cnt']>=$cnt ) {
                $re = '已经执行过！请勿重复';
                return $this;
            }
        }catch ( drException $ex ){
            $trade= $trade_row;
            $this->log("DB Error [".$ex->getCode()."]\t".  $ex->getMessage() );
        }
        $data = $this->getReturnData( $trade_row );
        $url = $trade_row['notify_url'];

        #$url = strtr( $url,[ 'pay.sjzditie.com'=>'pay.fieldbaby.com'] );
        #$url = strtr( $url,[ 'pay.fieldbaby.com'=>'47.244.12.58'] );
        $url = strtr( $url,[ 'pay.yxeplus.com'=>'pay.job36l.cn'] );


        //$this->drExit( $url );
        $str= drFun::http_build_query( $data);

        if( $this->getLogin()->isKC( $trade['merchant_id'] ) ){
            $this->getLogin()->createQrPay()->searchBuyerFromTrade( $trade );
            if(isset( $trade['pay_log']['buyer'] )) $str.="&order_user_name=". urlencode($trade['pay_log']['buyer'] ) ;
        }



        $this->log("====\nPOST>> curl -k -d " .'"'. $str.'" ' ."\t". $url );
        $this->log("====\ngetMd5Str>> " . $this->getMd5Str()  );
        $post_data= $str;
        $max= in_array( $trade['merchant_id'], $m_arr )? 10: 1;

        $is_ok= false ;

        for($i=0; $i<$max;$i++) {
            $str= $post_data;
            //drFun::cPost($url, $str, 10 );
            $start= microtime( true);
            //drFun::cPost($url, $str, 5 , [],['proxy'=>['ip'=>'193.112.201.59','port'=>8088] ]);
            drFun::cPost($url, $str, 5 ); //, [],['proxy'=>['ip'=>'193.112.201.59','port'=>8088] ]
            $re = $str;
            $this->log("re2 [".(1+$i)."] :" . $re . "\t" . $data['order_no'] ."\t".( microtime( true)- $start )."\t". $trade['merchant_id'] );
            if(  trim($str)=='ok'  ) $is_ok= true;

            //
            if( trim($str)=='已经执行过！请勿重复'  ) $is_ok= true;
            if( trim($str)=='success'  ) $is_ok= true;
            if( trim($str)=='notify timeout'  ) $is_ok= true;

            if( $trade['merchant_id']==8341 &&  trim($str)=='no'  )  $is_ok= true;
        }

        $opt=['+'=>['notify_cnt'=>1] ];

        if( $trade['notify_cnt']<$cnt &&  in_array( $trade['merchant_id'], $m_arr )  ){
            $this->getLogin()->createQrPay()->toMqTrade($trade, 'qf_fail');
        }
        //if( trim($str)=='ok'   ) {
        if( $is_ok  ) {
            $opt['notify_success'] = 1;
            $opt['notify_time'] = time();
            $merchant= $this->getLogin()->createQrPay()->getMerchantByID( $trade['merchant_id'] );
            $rate = round( $merchant['rate']*$trade['realprice']/10000);
            $this->log("good_rate>>". $trade_row['trade_id'] ."\t". $rate. "\t". $merchant['rate']. "\t". $trade['realprice'] );
            $opt['rate']= $rate;
        }elseif( (time()-$trade['ctime'])>100 && ( $trade['notify_cnt']<=20 || (time()-$trade['ctime'])<7200 ) ){
            $this->getLogin()->createQrPay()->toMqTrade($trade, 'qf_fail');
        }






        if(  $trade_row['trade_id'] ) $this->getLogin()->createQrPay()->upTradeByID( $trade_row['trade_id'] ,$opt );


        //$this->getLogin()->createQrPay()->toDayByTrade($trade_row );

        //统计地区
        //$this->getLogin()->createQrPay()->getAccLoByTrade( $trade_row['account_id'] ,[ 'isUp'=>1 ] );
        if( $trade_row['lo'] ){
            $acc= $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id'] );
            if( $acc['lo']=='') $this->getLogin()->createQrPay()->upAccountByID( $trade_row['account_id'] ,[ 'lo'=>$trade_row['lo'] ] );
        }

        return $this;
    }

    function addLogV3FromMq( $var , &$re_arr ){
        try{
            $re_arr= $this->getLogin()->createPayLog()->V3Parse( $var );
        }catch (\Exception $ex){
            $var['ex_cnt']++;
            $var['ex_no']= $ex->getCode();
            $var['ex_msg']= $ex->getMessage();

            //$this->getLogin()->createQrPay()->toMqTrade( $var,'fail_pay_log_v3'  );
        }

        if($re_arr['ali_uid'] && $re_arr['pay_log_id'] ){
            $this->getLogin()->createQrPay()->payMatchByLogID(  $re_arr['pay_log_id']  );
        }

        return $this;
    }

    function addLogV2FromMq( $mq_data ){
        $pay = json_decode( $mq_data['pay'] ,true);
        if( !$pay['md5'] ) $this->throw_exception("md5不存在！",4080);
        if( $pay['timeInfo1']!='今天' ) $this->throw_exception("仅处理今天的订单！",4082);

        $uid = intval($mq_data['uid']);
        $log =  new log('pay_log', $uid );
        $account  = json_decode( $mq_data['account'] ,true);
        $fee= 100* floatval( trim(  strtr( $pay['billAmount'],['元'=>'',','=>''] )) );

        if( $fee==0 ) $this->throw_exception( '获得的钱有问题！');
        if( $fee<0 ) $this->throw_exception( '支出不做记录！');
        if( $pay['billName']=='余额提现' ) $this->throw_exception( '余额提现 不入库！');

        if( $account['account_id'] <=0 ) $this->throw_exception( '收款支付账号有问题！');
        $packageName= trim( $pay['packageName'] );

        $opt_type = 10;

        if( $fee<=0 ){
            $opt_type = 401;
        }
        $billName = trim( $pay['billName']);
        $arr= explode('-',$billName );
        $trade_id= $pay_id = intval( $arr[0] );
        //if( $pay_id<=0 && count($arr)>0 ) $pay_id = intval( $arr[1] );

        $cnt= $this->createSql()->getCount('pay_log',['md5'=>$pay['md5'] ] )->getOne();
        if( $cnt>0 ) $this->throw_exception( '重复上传！',4081);

        $ltime = intval(  strtr($pay['timeInfo2'],[':'=>''] ) )+10000*date("Ymd") ;

        if($pay_id >0) {
            $pay_trade = $this->createSql()->select('pay_log', ['trade_id' =>$pay_id ])->getAll() ;
            if( $pay_trade ){
                $this->throw_exception( "交易号： ".$pay_id.' 已经在数据库当中了！');
            }
        }

        $log->append( $pay_id ,$opt_type ,$pay,['md5'=>$pay['md5'],'fee'=>$fee
            ,'account_id'=>$account['account_id'],'pay_type'=> 21 ,'ltime'=>$ltime ]);

        if( $fee==1 ){ #如果是1分钱改变下状态
            $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] ,['user_id'=> $uid]);
        }

        if( $opt_type!=10 && $pay_id>0 ) $this->throw_exception("忽略");

        //撮合交易
        $pay_log_id = $this->getLogin()->createSql()->lastID();
        $this->getLogin()->createQrPay()->payMatchByLogID($pay_log_id);
        return $this;

    }

    function timeDtMinute($str,$ctime=''){
        $re =[];

        $t1=$this->getTimeFromStr($str);
        if(!$t1) return 0;

        $m1= $this->time2m( $t1 );
        $mctime= $this->time2m( date("H:i", $ctime?$ctime:time() ));
        $dt= abs($mctime-$m1);
        if($dt>1400) $dt=1440-$dt%1440;
        return $dt;
        //$this->drExit( $this->time2m($re[0][0]) );
    }

    function getTimeFromStr($str){
        preg_match_all( "/\d{2}:\d{2}/",$str,$re  );
        if($re[0][0] ) return $re[0][0];
       //22时28分
        preg_match_all( "/\d{1,2}时\d{1,2}分/",$str,$re  );
        if($re[0][0] ) return strtr( $re[0][0],[ '时'=>':','分'=>''] );
        return 0;
    }

    function time2m($st){
        $arr= explode(':',$st);
        return( intval($arr[0])*60+intval( $arr[1]));
    }

    function doTransferByConsole( $ex_id, $console_uid, $type=11, $opt=[]){

        //$this->drExit( $type );
        $row = $this->getLogin()->createTableExport()->getRowByKey(  $ex_id );
        if( !$row ) $this->throw_exception("该提现不存在",4084);
        //if( !in_array( $row['type'],[1,11]) ) $this->throw_exception("该提现已被处理过",4085);
        if( in_array( $row['type'],[21,12]) ) $this->throw_exception("该提现已被处理过",4085);

        if( in_array($row['type'],[31] ) && !in_array( $type,[1]) ){
            if( !($opt['fb']=='doXia') )  $this->throw_exception("请先取消服务商再操作",20061803);
        }
        drFun::decodeOptValue($row);

        if( $type==1 && !in_array($opt['fb'],['doXia'] )){
            $last=  count($row['opt_value']['log'])>0 ? $row['opt_value']['log'][ count($row['opt_value']['log'])-1]:[];
            if(  ($last['type']==66 && ($last['t']+300-time())<=0) || $last['type']==31 ){

            }else $this->throw_exception('请过5分钟后再取消',20062101);
        }

        $mid = $this->getLogin()->getMidFromConsole(  $console_uid );
        if(! in_array( $row['merchant_id'], $mid )) $this->throw_exception("该商户与操作员不符合",4083);

        $url= $row['notify_url'];
        if( $url =='')  $this->throw_exception("客户回调地址为空！",4087);

        if( $type==12 &&  $row['type']!=1  && (time()-$row['ctime'])>2*24*3600 ){
            $this->throw_exception( "超过48小时的申请 无法驳回！",4095);
        }

        $oldType= $this->getLogin()->createQrPay()->getTypeMcExport2( $row['type'] );
        //$this->drExit( $oldType );
        if( !in_array($type ,$oldType['can']) )  $this->throw_exception("非法操作！",4095);

        $mc = $this->getLogin()->createQrPay()->getMerchantByID($row['merchant_id']);
        if( ( in_array($row['type'],[31] ) &&  in_array( $type,[1])) || $type==31 || $url=='10086' || strpos($url,'127.0.0.1' ) || in_array( $ex_id,[82188] ) ) {
            $str='ok';
        }else{
            $data = ['type' => $type, 'order_no' => $row['order_no'], 'ex_id' => $row['export_id'], 'ctime' => time()];
            $this->setSecret($mc['app_secret']);
            $data['sign'] = $this->createSign($data);

            $str = drFun::http_build_query($data);

            $this->log("====\nPOST:\n curl -k -d " . '"' . $str . '" ' . "\t" . $url);


            #drFun::cPost($url, $str, 10, [], ['proxy' => ['ip' => '193.112.201.59', 'port' => 8088]]);
            drFun::cPost($url, $str, 5, [] );
            $re = $str;
            $this->log("re:" . $re . "\t" . $data['order_no']);
        }
        if(  trim($str)=='ok'  || $type==12){
            $row['opt_value']['log'][]= $this->getCzVar($type, $row['type'] );
            $uVar=['cz_time'=>time(),'type'=>$type ,'real_money'=>$this->getExRealMoney($row,$type ,$mc ) ] ;
            //'cz_user_id'=> $console_uid,
            if( !$row['cz_user_id'] ) $uVar['cz_user_id']= $console_uid;
            $uVar['cz_ip']= drFun::getIP();
            $uVar['opt_value']= drFun::json_encode(  $row['opt_value'] );
            if( $type==1){
                $uVar['ma_user_id']=0;
            }
            $this->getLogin()->createTableExport()->updateByKey($ex_id, $uVar);
        }else{
            $this->throw_exception("请求返回：".$str, 4086);
        }
        return $this;
    }

    function exNotify( $ex_id ){
        $row = $this->getLogin()->createTableExport()->getRowByKey(  $ex_id );
        if( !$row ) $this->throw_exception("该提现不存在",4084);
        if( $row['type']!=21) $this->throw_exception("仅支持已转账",20031502);
        $mc = $this->getLogin()->createQrPay()->getMerchantByID($row['merchant_id']);

        $url= $row['notify_url'];
        if( $url =='')  $this->throw_exception("客户回调地址为空！",4087);

        if($url=='10086' || strpos($url,'127.0.0.1' ) ) {
            $str='未提供回调地址';
        }else{
            $data = ['type' => $row['type'], 'order_no' => $row['order_no'], 'ex_id' => $row['export_id'], 'ctime' => time()];
            $this->setSecret($mc['app_secret']);
            $data['sign'] = $this->createSign($data);

            $str = drFun::http_build_query($data);

            $this->log("====\nPOST:\n curl -k -d " . '"' . $str . '" ' . "\t" . $url);
            drFun::cPost($url, $str, 10, [], ['proxy' => ['ip' => '193.112.201.59', 'port' => 8088]]);
            $re = $str;

        }
        $this->log("exNotify :" . $str . "\t" . $row['order_no'].'=>'. $row['type'] );

        /*
        if(  trim($str)=='ok'  ){
            $row['opt_value']['log'][]= $this->getCzVar($type, $row['type'] );
            $uVar=['cz_user_id'=> $console_uid,'cz_time'=>time(),'type'=>$type ,'real_money'=>$this->getExRealMoney($row,$type ,$mc ) ] ;
            $uVar['cz_ip']= drFun::getIP();
            $uVar['opt_value']= drFun::json_encode(  $row['opt_value'] );
            $this->getLogin()->createTableExport()->updateByKey($ex_id, $uVar);
        }else{
            $this->throw_exception("请求返回：".$str, 4086);
        }
        */
        return $str;

    }

    function getCzVar($type,$oldType){
       $var=['t'=>time(),'ip'=>drFun::getIP(),'uid'=>$this->getLogin()->getUserId(),'type'=> $type,'otype'=> $oldType ];
       $var['sg']= substr( md5($var['t'].$var['uid'].$type.'207845add'),4,8);
       return $var;
    }

    function getExRealMoney( $ex ,$type ,$mc){

        $money = abs($ex['money'])+ abs($mc['fa_fee']);
        $opt= $this->getLogin()->createQrPay()->getTypeMcExport2( $type) ;
        if( !isset( $opt['op'])) $this->throw_exception("没存在 op" ,190831001);
        return  $money*$opt['op'] ;
    }

    function getBillByMark( $remark ){
        $remakr = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_beizhu'=>$remark] , ['order'=>['pt_id'=>'asc'] ]  );
        $re=['url'=>'','tradeNo'=>''];
        if($remakr['type']==2){
            $re['url']='error';
            $re['msg']= $remakr['data'];
            if( strpos($re['msg'],'限制'))  $re['msg']="亲，你已超当日加好友限制！请明日再来，或换支付宝进行充值！";

        }elseif( $remakr['type']==37 ) {
            $data= drFun::json_decode( $remark['data']);
            $this->drExit( $data );
            $re['url']= 'alipays://platformapi/startapp?appId=20000215&sourceId=alipay&actionType=detail&batchNo='.$remakr['ali_trade_no'].'&';
            $re['url'] .='token='.$data['data']['token'].'&shareObjType=group&shareObjId='.$data['arg']['g'] ;
            $re['tradeNo']= $remakr['ali_trade_no'] ;
        }elseif( $remakr) {
            $re['url']= 'alipays://platformapi/startapp?appId=20000090&actionType=toBillDetails&tradeNO='.  $remakr['ali_trade_no'];
            $re['tradeNo']= $remakr['ali_trade_no'] ;
        }
        return $re ;
    }

    function getAliBillFromCache( $account_id, $realprice){

        $where=['account_id'=> $account_id,'fee'=> $realprice ,'type'=>15];
        $remakr = $this->getLogin()->createTablePayLogTem()->getRowByWhere($where , ['order'=>['pt_id'=>'asc'] ]  );
        if( !$remakr ) return false;
        $data = drFun::json_decode($remakr['data']);
        if( !$data['alipayOrderString'] ){
            $this->getLogin()->createTablePayLogTem()->updateByKey( $remakr['pt_id'],['type'=>-15]);
            return false;
        }

        $re=['url'=> $data['alipayOrderString'] ,'tradeNo'=>'cache','data'=> $data ];

        return $re ;
    }

    function getDingBillByMark($remark){
        $remakr = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_beizhu'=>$remark] , ['order'=>['pt_id'=>'asc'] ]  );
        $re=['url'=>'','tradeNo'=>''];
        if($remakr['type']==2){
            $re['url']='error';
            $re['msg']= $remakr['data'];
            if( $re['msg']=='手机旺信请回到群界面') $re['msg']='手机回到正确的界面';
            $re['v2']='';
            return $re ;
        }elseif($remakr) {
            $data = drFun::json_decode($remakr['data']);
            if( in_array($remakr['type'],[-78]  ) ){
                return $re ;
            }



            if( $remakr['type']==130 )  $data['alipayOrderString']= base64_decode( $data['alipayOrderString']);

            if( in_array( $remakr['type'],[138,139,137,130]) ) {
                $re = [];
                $data['alipayOrderString'] = base64_decode($data['alipayOrderString']);
                $tem = explode('&bizcontext', $data['alipayOrderString']);
                $re['url'] = $tem[0];
            }else{
                $re['url']  = $data['alipayOrderString'];
            }
            $re['tradeNo'] = $remakr['ali_trade_no'];



            /*
            $v2=['fromAppUrlScheme'=>'alipays', 'requestType'=>'SafePay','dataString'=>  $data['alipayOrderString'] ];
            $appv2= 'alipaymatrixbwf0cml3://alipayclient/?'. urlencode( json_encode($v2 ));

            $biz=[];
            $biz['appkey']='2014052600006128';
            $biz['ty']='ios_lite';
            $biz['sv']='h.a.3.6.5';
            $biz['an']='com.alibaba.mobileim';
            $biz['av']='1.0';
            $biz['sdk_start_time']= intval( time() .'213' );
            $v2=['fromAppUrlScheme'=>'alipays', 'requestType'=>'SafePay','dataString'=>  $data['alipayOrderString'].'&bizcontext='.json_encode($biz ) ];
            //$appv2= 'alipay://alipayclient/?'. urlencode( json_encode($v2 ));
            $appv2= 'alipaymatrixbwf0cml3://alipayclient/?'. urlencode( json_encode($v2 ));
            */
            $v2=['fromAppUrlScheme'=>'alipays', 'requestType'=>'SafePay','dataString'=> strtr( $data['alipayOrderString'],['and_lite'=>'ios_lite'] ) ];
            $biz=[];
            $biz['appkey']='2014052600006128';
            $biz['ty']='ios_lite';
            $biz['sv']='h.a.3.6.5';
            $biz['an']='com.alibaba.mobileim';
            $biz['av']='1.0';
            $biz['sdk_start_time']= intval( time() .'213' );

            if( strpos($data['alipayOrderString'],'sdk_start_time')==false ) {
                $v2=['fromAppUrlScheme'=>'alipays', 'requestType'=>'SafePay','dataString'=>  $data['alipayOrderString'].'&bizcontext='.json_encode($biz ) ];
            }
            $appv2= 'alipaymatrixbwf0cml3://alipayclient/?'. urlencode( json_encode($v2 ));



            if($remakr['type']==139  ){



                //$re['v2url']= 'https://mclient.alipay.com/h5/cashierSwitchAccountSel.htm?session=' . $data['session'] . '&cc=y&logonId=otherAccount&userIdLdc=';
                //$biz['ty']='and_lite';
                //$re['url'] = urlencode(  $re['url'] .'&bizcontext='.json_encode($biz ) );
            }
            unset( $data['alipayOrderString']);
            if( $data['surl']){
                $surl = json_decode( base64_decode( $data['surl'] ) , true );
                //data.shortUrl
                $data['surl']= $surl['data']['shortUrl'];
            }
            $re['data']= $data;


            $re['v2']= $appv2;
        }
        return $re ;

    }

    function getPayLogTemByWhere( $where ,$opt=[] ){
        $remakr = $this->getLogin()->createTablePayLogTem()->getRowByWhere( $where , ['order'=>['pt_id'=>'asc'] ]  );
        $re=['url'=>'','tradeNo'=>''];
        if( !$remakr  && $opt['remark'] ){
            $remakr = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_beizhu'=> $opt['remark']] , ['order'=>['pt_id'=>'asc'] ]  );
        }
        if($remakr['type']==2){
            $re['url']='error';
            $re['msg']= $remakr['data'];
            $re['v2']='';
            return $re ;
        }elseif($remakr) {
            $data = drFun::json_decode($remakr['data']);
            if( in_array($remakr['type'],[-78]  ) ){
                return $re ;
            }
            $re = [];
            $re['url'] = $data['alipayOrderString'];
            $re['tradeNo'] = $remakr['ali_trade_no'];

            $v2=['fromAppUrlScheme'=>'alipays', 'requestType'=>'SafePay','dataString'=>  $data['alipayOrderString'] ];
            $appv2= 'alipaymatrixbwf0cml3://alipayclient/?'. urlencode( json_encode($v2 ));
            $re['v2']= $appv2;
        }
        return $re ;
    }

    function timeV3( $p ){
        $account = $this->getLogin()->createQrPay()->getAccountByAliUid(  $p[0] );
        if( $account['type']==90 ){
            $this->log("time90: ". $p[0]  ."\t" .  $account['account_id']);
            $acc_online= $this->getLogin()->createTableAccountOnline()->getRowByWhere(['account_id'=> $account['account_id'] ,'type'=>90]  );
            if( !$acc_online ) return ;
            $this->log("time90: ".date('Y-m-d H:i:s'). json_encode( $acc_online ) ."\t". date('Y-m-d H:i:s',$acc_online['clienttime']) );
            if( time()> ($acc_online['clienttime']+20) ) return ;
        }
        $this->getLogin()->createQrPay()->updateClientTime( $account['account_id']  );
        return $this;
    }




    function smsPayLog( $post ){

        $uid = intval($post['uid']);
        $pay = json_decode( $post['pay'] ,true);
        $account  = json_decode( $post['account'] ,true);

        if( $account['account_id'] <=0 ) $this->throw_exception( '收款支付账号有问题！');

        $account= $this->getLogin()->createQrPay()->getAccountByID( $account['account_id'] );

        if( $account['user_id']>0 && $account['ma_user_id']>0 ){
            $uid= $account['user_id'];
        }


        $log =  new log('pay_log', $uid );

        //$fee= floor( 100* floatval( trim(  strtr( $pay['money'],['元'=>''] ))+0.001 ) );
        //$fee= floor( 100* floatval( trim(  strtr( $pay['money'],['元'=>''] ))+0.001 ) );
        $fee=  drFun::yuan2fen( $pay['money']);

        $packageName= trim( $pay['packageName'] );

        $opt_type = 10;

        if( '语音提醒服务 运行中'== $pay['title'] )      $this->throw_exception("忽略V2");


        if( $pay['title']!='支付宝通知' || !(strpos($pay['text'],'付款') || strpos($pay['text'],'收款')) ){
            $opt_type = 401;
        }

        $pay_type=  $this->getPayType($packageName);
        if($pay['title']=='动账通知')  $pay_type=3;

        if($pay_type==2){ #微信
            if( strpos($pay['text'],'收款'))    $opt_type = 10;
        }
        $ctime=0;

        if( $pay['type']==42 ){
            $pay_type= $pay['type'];
            $pay['text']= $pay['strbody'];
            unset( $pay['strbody'] );
            $pay['id']= strtotime( $pay['strDate'] );
            if($fee==0){
                $re=[];
                preg_match_all( '/(\d+\.\d+)/i', $pay['text'],$re);
                $fee= drFun::yuan2fen($re[1][0]);
            }
            $pay['postTime']= $pay['id'].$fee;

            $ctime= $this->strDate2Ctime( $pay['strDate'], time() );

            $this->log("cctime>>". $pay['strDate']." ". $ctime." ". date("Y-m-d H:i:s",$ctime ));

            //echo date("Y-m-d H:i:s", $ctime);
            //$this->drExit($pay);

            //if( $ctime>0 ) $pay['t']= date("Y-m-d H:i:s");

        }
        $buyer='';
        if( $pay_type==4 && $account['type']==45 ) {
            $pay_type=45;
            if($this->payLog45( $pay )) $opt_type = 10;
            $buyer= $pay['buyer'];

        }elseif( $pay_type==4 || $pay_type==42 ){

            //$acc= $this->getLogin()->createQrPay()->getAccountByID( $acc );
            $no_acc_id= $account['account_id'] ;

            $this->smsSub( $account,$pay_type , $pay); //子账号处理

            if(strpos($pay['text'],'农业银行') &&  ( strpos($pay['text'],'代付') || strpos($pay['text'],'转账') || strpos($pay['text'],'转存')  || strpos($pay['text'],'入账'))  && strpos( $pay['text'],'-' )===false  ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'网商银行') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;

            }elseif( strpos($pay['text'],'工商银行') &&  strpos($pay['text'],'收入') ){

                $re=[];
                preg_match_all( '/([\d\.,]+)元/i', $pay['text'],$re);
                //$fee= ceil(strtr($re[1][0],[','=>''])*100);
                $int= strtr($re[1][0],[','=>'']);
                $fee=  floor(($int+0.001)*100);

                $c_str = drFun::cut( $pay['text'] ,'(',')');
                $arr= explode("支付宝", $c_str);
                $buyer = trim($arr[0] );

                $opt_type = 10;
            }elseif(  strpos($pay['text'],'徽商银行') &&  ( strpos($pay['text'],'增加')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'湖南农信') &&  ( strpos($pay['text'],'转入') ||   strpos($pay['text'],'入账')  ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'交通银行') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'招商银行') &&  ( strpos($pay['text'],'收款人民币')  ||  strpos($pay['text'],'转入')||  strpos($pay['text'],'入账人民币')   ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'中信银行') &&  ( strpos($pay['text'],'存入')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'光大银行') &&  ( strpos($pay['text'],'存入') ||  strpos($pay['text'],'转入')  ) ){
                $re=[];
                preg_match_all( '/([\d\.,]+)元/i', $pay['text'],$re);
                $fee=drFun::yuan2fen( trim($re[1][0]));
                $opt_type = 10;
                $buyer=  drFun::cut( $pay['text'] ,'摘要:','支付宝');
            }elseif(  strpos($pay['text'],'张家口银行') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;
                $re=[];
                preg_match_all( '/金额([\d\., ]+)元/i', $pay['text'],$re);
                //$fee = drFun::yuan2fen( trim($re[1][0]));
                if(  trim($re[1][0]) )   $fee = drFun::yuan2fen( trim($re[1][0]));
                else{
                    preg_match_all( '/([\d\.,]+)元/i', $pay['text'] ,$re);
                    $fee = drFun::yuan2fen( trim($re[1][0]));
                }
                $buyer='';

            }elseif(  strpos($pay['text'],'浦发银行') &&  ( strpos($pay['text'],'存入')     ) ){
                $opt_type = 10;
                $re=[];
                preg_match_all( '/([\d\.,]+)\[/i', $pay['text'],$re);
                $fee = drFun::yuan2fen($re[1][0]);
                //[王浩支付宝转账
                $buyer =  drFun::cut( $pay['text'] ,'[','支付宝');

            }elseif(  strpos($pay['text'],'九江银行') &&  ( strpos($pay['text'],'(收)')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'平安银行') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;
            }elseif( strpos($pay['text'],'河北银行') &&  strpos($pay['text'],'来账') ){
                $opt_type=10;
            }elseif( strpos($pay['text'],'福建农信') &&  strpos($pay['text'],'转入') ){
                $opt_type = 10;
            }elseif( strpos($pay['text'],'邮储银行') &&  (strpos($pay['text'],'提现') || strpos($pay['text'],'入账') ||  strpos($pay['text'],'来账')||  strpos($pay['text'],'汇入') ) ){
                $opt_type = 10;
                //}elseif( strpos($pay['text'],'银行') && (strpos($pay['text'],'收入') || strpos($pay['text'],'收款') ||  strpos($pay['text'],'存入')|| strpos($pay['text'],'转入') ) ){
            }elseif( (strpos($pay['text'],'银行') || strpos($pay['text'],'农信') ) &&  $this->isYes10( $pay['text']) ){
                $opt_type = 10;

                if($fee<=0 ||  $this->sPos( $pay['text'],['长沙银行','浦发银行'])  ) {
                    $re = [];
                    preg_match_all('/([\d\.,]+)元/i', $pay['text'], $re);
                    $fee = drFun::yuan2fen(trim($re[1][0]));
                }

            }

            /**
             *
             *
             */
            //}elseif( $pay_type==3 &&  strpos($pay['text'],'付款') &&  strpos($pay['text'],'扫码') ){
        }elseif( $pay_type==3 &&  strpos($pay['text'],'闪付收款')  ){
            $opt_type = 10;
        }elseif(  $pay_type==3 &&  strpos($pay['text'],'入账')   ){
            //$this->throw_exception("忽略V3");
            //parent::display();
            $opt_type = 401;
        }

        if($opt_type==10  && isset($pay['strType'])  ){ //&& !in_array($uid, [2333])
            //if( ! isset($pay['strType']) )  $opt_type=401;//如果不是最新版本都忽略
            $yhTel= $this->yhTel();
            $pay['title'] = trim($pay['title']).'';
            $len= strlen($pay['title']);
            $is95= substr($pay['title'],0,1 )=='9' && $len==5;

            try{
                //$bank= $this->getLogin()->createQrPay()->getBankType( $account['bank_id']);
                //if( $bank['is95'] && !$is95) $opt_type=401;
            }catch (drException $ex){

            }

            if(! in_array( trim($pay['title']), $yhTel) && !$is95) { //不在银行白名单
                $opt_type=401;
            }

            if($opt_type!=10 ) {
                $logStr = 'luangao error>>[' . $uid . '][' . $pay['title'] . "]\t" . $pay['text'];
                $this->getLogin()->createQrPay()->toTelegram(4, $logStr);
                $this->log($logStr);
            }
        }

        $buyer=$pay['buyer']='';
        unset($pay['buyer']);

        $test_txt = preg_replace("/\[[0-9 ]+条\]/","", $pay['text'] );


        if($opt_type==10 && !$this->smsHealth($pay['text'], $fee) ){
            $opt_type= 402;
        }



        if(  $ctime>0 && ( time()-$ctime ) >300 && $opt_type==10 ){
            $opt_type= 402;
            //$pay['t']= date("Y-m-d H:i:s"). " 误差：" . ( time()-$ctime  )."S";
            $pay['t']= $pay['strDate'] . " 误差：" . ( time()-$ctime  )."s";
        }

        if( $test_txt  && $opt_type==10 ){
            $whSms= ['account_id'=> $no_acc_id   , 'text'=> $test_txt  ];
            $cnt= $this->getLogin()->createTablePaySms()->getCount( $whSms );
            if( $cnt>0 ) {
                $this->log("sms402>>". $test_txt );
                $opt_type= 402;
            }#重复忽略
            else {
                if( strpos( $test_txt,'湖北农信') && strpos( $test_txt,'支付宝') ){
                    $test_txt= trim( strtr( $test_txt,['【湖北农信】'=>''] ) );
                }
                $test_txt = mb_substr($test_txt, 0, 56, 'utf-8'); #一条长短信截断前56个字
                $whSms['text'] = $test_txt;
                $cnt= $this->getLogin()->createTablePaySms()->getCount( $whSms );
                if ($cnt > 0) {
                    $opt_type = 403;
                    $this->log("sms403>>". $test_txt );
                }#重复忽略
                #$this->log('sms56>>'.$test_txt );
            }

            $this->getLogin()->createTablePaySms()->append([ 'account_id'=> $no_acc_id , 'ctime'=>time(), 'text'=>  $test_txt  ]);
        }


        if( $opt_type==10){
            $dt= $this->timeDtMinute($test_txt,$ctime );
            if( $dt>5){
                $opt_type= 402;
                $logStr= 'timeDtMinute>>['.date("m-d H:i",$ctime ? $ctime:time() ).']'.$dt."\t". $test_txt;
                //$this->getLogin()->createQrPay()->toTelegram(4, $logStr);
                $this->log($logStr);
                $pay['t']= $pay['strDate'] . " 误差：" . $dt."分钟";
            }elseif ( $dt>0 ){
                //$logStr= 'timeDtMinute>>['.date("Y-m-d H:i",$ctime).']'.$dt."\t". $test_txt;
                //$this->log($logStr);
            }
        }

        if( $opt_type==10 ){
            $is_sj= $this->isYHSJ($pay);
            if($is_sj){
                $opt_type=402;
                $logStr= 'luangao>>'.$pay['title'] ."\t". $pay['text'];
                $this->getLogin()->createQrPay()->toTelegram(4, $logStr);
                $this->log($logStr);
                $pay['t']= $pay['strDate'] . " 收号：" . $pay['title'];
            }else{
                //$logStr= 'luangao>>'.$pay['title'] ."\t". $pay['text'];
                //$this->log($logStr);
            }
        }

        $pay_opt= ['ltime'=>$pay['postTime'],'fee'=>$fee
            ,'account_id'=>$account['account_id'],'pay_type'=> $pay_type  ,'ip'=>drFun::getIP() ,'buyer'=>$buyer   ];

        if( $account['ma_user_id']>0 ){
            /*
            $pay_opt= ['ltime'=>$pay['postTime'],'fee'=>$fee
                ,'account_id'=>$account['account_id'],'ma_user_id'=>$account['ma_user_id'],'pay_type'=> $pay_type  ,'ip'=>drFun::getIP() ,'buyer'=>$buyer   ];

            */
            $pay_opt['ma_user_id']=$account['ma_user_id'];


        }
        //if( $ctime>0) $pay_opt['ctime']= $ctime;

        $log->append( $pay['id'] ,$opt_type ,$pay, $pay_opt);



        if($opt_type!=10 ) $this->throw_exception("忽略");

        $pay_log_id = $this->getLogin()->createSql()->lastID();

        #正式环境请移到队列中
        try {

            //$fee==1  || $fee==1111 || $fee==11||  $fee==5011 ||  $fee==2111 || $fee==10011|| $fee==10011
            $online_price=[1,11,1111,2011,5011,10011,30011,500,600,700,1500,1600,1700,1400];
            if( in_array( $fee,$online_price) && !in_array($account['online'] ,[1,11,4]) ) $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );

            if( $fee==20100 &&  strpos($pay['text'],'张家口银行') ) {
                $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
            }

            $this->getLogin()->createQrPay()->payMatchByLogID($pay_log_id);

        }catch ( drException $ex ){
            if( !in_array( $ex->getCode(),[20061801]) ) $this->log("trade_pay_matche>>".$pay_log_id." ". $ex->getCode()."". $ex->getMessage() ."\t".$pay['text'] );
        }

    }

    function isYHSJ( $pay,$opt=[]){
        //if(!isset($pay['strType']))          return false;
        $mobile =  trim($pay['title']).'';
        if( strpos($mobile,'+')!==false ) return true;
        $sub= substr( trim($mobile),0,2);
        if( in_array( $sub,['86','13','14','15','16','17','18','19'] )) return true ;
        if( !isset($pay['strType']) ){
            /*
            if( strpos( $mobile,'银行')!==false) return false;
            if( strpos( $mobile,'农信')!==false) return false;
            if( strpos( $mobile,'中国平安')!==false) return false;
            return true;
            */
            return $this->isV1Yh($mobile);
        }
        return false;
    }

    function isV1Yh( $mobile ){
        if( strpos( $mobile,'银行')!==false) return false;
        if( strpos( $mobile,'农信')!==false) return false;
        if( strpos( $mobile,'中国平安')!==false) return false;
        if( strpos( $mobile,'邮政储蓄')!==false) return false;
        //if( strpos( $mobile,'106980096138')!==false) return false;
        if(  in_array($mobile,['106927995511','96268','106980096138']) ) return false;
        return true;
    }

    function sPos($str,$arr){
        foreach( $arr as $v ){
            if( strpos( $str, $v)!==false ) return true;
        }
        return false;
    }

    function strDate2Ctime( $strDate, $now){

        $strtotime= strtotime( $strDate);
        if($strtotime<=0) return 0;

        $dt1= abs( $strtotime-$now);

        /*
        if( ($strtotime-time() )>60 ){ ##0点的时候 会显示 7.7 0点会显示为 7.7 12点
            return $strtotime-12*3600;
        }
        */
        $ctimeF12=  $strtotime-12*3600;

        $dt2= abs( $ctimeF12 -$now );

        $ctime12= $strtotime+12*3600;
        //if( $ctime12> time() ) return $strtotime;

        $dt3 = abs($ctime12 - $now );

        $arr = [ $dt1=>$strtotime, $dt2=>$ctimeF12, $dt3=>$ctime12 ];

        $min=  min(  array_keys( $arr) );

        if( $min>2*3600) return 0;

        if( isset( $arr[$min])) return $arr[$min];

        return 0;


        //return $ctime12;
        //$this->drExit( $strDate);
    }


    function isYes10( $str ){

        //}elseif( strpos($pay['text'],'银行') && (strpos($pay['text'],'收入') || strpos($pay['text'],'') ||  strpos($pay['text'],'存入')|| strpos($pay['text'],'转入') ) ){
        if(strpos($str,'转出')) return false;
        if(strpos($str,'冲正')) return false;

        if( strpos($str,'收入')) return true;

        #if( strpos($str,'收款')) return true; //会出现收款人 转出 收款人为

        if( strpos($str,'二维码收款')) return true;
        if( strpos($str,'存入')) return true;
        if( strpos($str,'转入')) return true;
        if( strpos($str,'入账')) return true;
        return false;

    }

    function payLog45( &$pay ){

        if(  strpos($pay['text'],'北京银行') &&  ( strpos($pay['text'],'收入')     ) ) {
            //$opt_type = 10;
            $pay['buyer']= drFun::cut( $pay['text'],'对方户名:','。' );
            if( ! trim($pay['buyer'])  ) return false;
            $pay['ali_uid']= drFun::cut( $pay['text'],'对方尾号:','。' );
            return true;
        }
        if( strpos($pay['text'],'中国农业银行')  ){ // &&    ( strpos($pay['text'],'转存')|| strpos($pay['text'],'转账'))
            $pay['buyer']= drFun::cut( $pay['text'],'】','于'.date("m") );
            if( ! trim($pay['buyer'])  ) return false;
            return true;
        }
        if(  strpos($pay['text'],'邮储银行')   ){
            $str=$pay['text'] ;//'【邮储银行】19年06月19日21:26陈子平账户6712向您尾号759账户他行来账金额0.01元，余额14.00元';
            preg_match_all( '/金额([\d\., ]+)元/i', $str,$re);
            preg_match_all( '/([^0-9]+)账户/i', $str,$re2 );
            $pay['buyer']= trim($re2[1][0])  ;
            if( ! trim($pay['buyer'])  ) return false;
            return true;
        }
        if(  strpos($pay['text'],'建设银行')   &&  strpos($pay['text'],'存入')  ){
            $str=$pay['text'] ;//陈子平12月19日22时23分向您尾号6278的储蓄卡账户转账存入收入人民币11.00元,活期余额42.60元。[建设银行]
            preg_match_all( '/^([^0-9]+)[\d]+/i', $str,$re2 );
            $pay['buyer']= trim($re2[1][0])  ;
            if( ! trim($pay['buyer'])  ) return false;
            return true;
        }

        return false ;

    }

    function getPayType($packageName){
        switch ( $packageName ){
            case 'com.tencent.mm':
                return 2;
                break;
            case 'com.eg.android.AlipayGphone':
                return 1;
                break;
            case 'com.unionpay':
            case 'com.xiaomi.xmsf':
                return 3;
                break;
            case 'com.android.mms':
                return 4;
                break;
        }
        return 0;

    }



    function smsSub( &$account ,&$pay_type, $pay){
        if( !in_array($pay_type,[4,42])  ) return $this;
        $acc= $this->getLogin()->createQrPay()->getAccountByID( $account['account_id'] );
        if(! in_array($acc['type'] ,[47,147])  ) return $this;
        $accList= $this->getLogin()->createTablePayAccount()->getAll( ['ali_uid'=>$account['account_id'] ] );
        $accList[]= $acc;
        $a2= $this->getWeiHaoAcc($accList , $pay['text']);
        //$this->drExit( $a2 );
        if( $a2 ){
            $pay_type=$a2['type'];
            $account= $a2;
        }
        return $this;
    }


    function yhTel(){
        $re=['95568','95566','95555','95559','10698000096558','95588','1069070996599','95599','95528'];
        $re[]='95508';
        $re[]='95561';
        $re[]='95558';
        $re[]='95577';
        $re[]='95580';
        $re[]='95594';
        $re[]='95580';
        $re[]='96523';
        $re[]='95533';
        $re[]='95511';//中国平安
        $re[]='1069800096511';//长沙银行
        $re[]='1069800096368';//河北银行
        $re[]='106980096518';//湖南
        $re[]='106980096328';//沧州银行
        $re[]='1069199596599';//湖北银行
        $re[]='9555801';//中信银行
        $re[]='106575580180';// 【广东华兴银行】
        $re[]='95516';// 【广东华兴银行】
        $re[]='106910096888';// 【吉林农信】
        $re[]='106980096518';// 【湖南农信】
        $re[]='106380096518';// 【湖南农信】
        $re[]='106927995511';// 【平安银行】
        $re[]='1069095599';// 【平安银行】
        $re[]='1065752521332199';// 【浙信村镇银行】
        $re[]='106575296588';// 【徽商银行】
        $re[]='1069071995105588';// 【晋商银行】
        $re[]='106909071496588';// 【大同银行】
        $re[]='1062895596518';// 【山西农信】
        $re[]='106980096138';// 【广东农信】
        $re[]='1069800096699';// 【广州银行】
        $re[]='106575371777';// 【广州银行】
        $re[]='10628841';// 【贵州农信】
        $re[]='1069100996518';// 【湖南农信】
        $re[]='106912095568';// 【民生银行】
        $re[]='10690546033936';// 【大方农商银行】
        $re[]='1069032996599';// 【华融湘江银行】
        $re[]='106980096599';// 【华融湘江银行】
        $re[]='1065752581917283';// 【网商银行】
        $re[]='106905097283';// 【网商银行】
        $re[]='1065752060997283';// 【网商银行】
        $re[]='106980006266';// 【江西银行】
        $re[]='1069033396599';// 【华融湘江银行】
        $re[]='1069088596511';// 【长沙银行】
        $re[]='106907996296';// 【长沙银行】
        $re[]='10691200796655';// 【贵州银行】
        $re[]='106930096518';// 【贵州银行】
        $re[]='1069017496268';// 【江西农商银行】
        $re[]='1065755553961122';// 【东莞农商银行】
        $re[]='956033';// 【东莞农商银行】
        $re[]='1065755553961122';// 【东莞农商银行】

        $re2=['106905695528','106903409551186','106380096599','106350196368','10692799551186','1069295561','106980095533','106905961896518','106980096518','10691995558','106905695559','10692955611','10698000962999','1069095599','1069199596599','10698000096558','106575580180','106980096328','1069070996599','1065905596588','10690329296368','106927995511','1069800096368','955581101','1069800096511','9555801' ];

        $re= array_merge($re,$re2);

        return $re;
    }

    function getWeiHaoAcc(  $accList ,$sms ){
        if( strpos($sms,'中国银行') || strpos($sms,'邮储银行') || strpos($sms,'东莞银行')  ){
            foreach($accList as $acc ){
                if(200104==$acc['bank_id'] && strpos($sms,'中国银行') ){
                    //return $acc;
                    $this->log( "200104=>china_bank=>".$sms."=>". json_encode( $acc));
                    if(  strpos($sms,$acc['zhifu_name']) )return $acc;
                    //您的借记卡账户长城电子借记卡
                    if( in_array( trim( $acc['zhifu_name']),['夏善伟','王燕琴'] )  ){
                        return $acc;
                    }
                    if( strpos($sms,'借记卡账户长城电子借记卡') ){
                        return $acc;
                    }
                }
                if(200105==$acc['bank_id'] && strpos($sms,'邮储银行') ){
                    //return $acc;
                    $this->log( "200105=>china_bank=>".$sms."=>". json_encode( $acc));
                    if(  strpos($sms,$acc['zhifu_name']) )return $acc;
                }
                if(200134==$acc['bank_id'] && strpos($sms,'东莞银行') ){
                    //return $acc;
                    $this->log( "200105=>china_bank=>".$sms."=>". json_encode( $acc));
                    if(  strpos($sms,$acc['zhifu_name']) )return $acc;
                }

            }
        }
        foreach($accList as $acc ){
            //$this->drExit($acc );
            $zhifu_account =  trim( $acc['zhifu_account']);
            if( $zhifu_account=='') continue;
            $weiHao4 = substr($zhifu_account,-4 );
            $weiHao3 = substr($zhifu_account,-3 );



            $key41='尾号'.$weiHao4 ;
            $key31='尾号'.$weiHao3 ;

            $key42='账户'.$weiHao4 ;

            if( strpos($sms,$key41 )!==false ||  strpos($sms,$key31 )!==false ||  strpos($sms,$key42 )!==false ) return $acc;

            if( strpos($sms,'尾号*'.$weiHao4 )!==false ) return $acc;
            if( strpos($sms,'尾数'.$weiHao4 )!==false ) return $acc;
            if( strpos($sms,'尾数*'.$weiHao4 )!==false ) return $acc;
            if( strpos($sms,'账户*'.$weiHao4 )!==false ) return $acc;
            if( strpos($sms,'尾号为'.$weiHao4 )!==false ) return $acc;
            if( strpos($sms,'*'.$weiHao4 )!==false ) return $acc;
            if( strpos($sms,'账户['.$weiHao4 )!==false ) return $acc;

            $weiHao5 = substr($zhifu_account,-5 );
            $weiHao54 = substr($weiHao5,0,4 );
            if( strpos($sms,'*'.$weiHao54.'*' )!==false ) return $acc;

            //if( strpos( ))
        }

        return [];


    }

    function smsHealth( $sms ,$fee ){
        preg_match_all( '/([\d\., ]+)元/i', $sms,$re);

        $cnt=2;
        if( strpos($sms,'余额')===false ){
            $cnt=1;
        }

        preg_match_all( '/(\d+\.\d)/i', $sms,$re2);

        if( count( $re2[1])>count($re[1]) ){
            $re=$re2;
        }

        if( count($re[1])>$cnt){
            //print_r($re);
            $logStr= "smsHealth [". count($re[1])."][".$cnt."]>>". $sms;
            $this->log($logStr);
            $this->getLogin()->createQrPay()->toTelegram(4, $logStr);
            return false;
        }
        $money= drFun::yuan2fen($re[1][0]);
        $money= $fee;//drFun::yuan2fen($re[1][0]);
        if( count($re[1])>1 ){
            preg_match_all( '/余额([\d\., ]+)元/i', $sms,$re2);

            if(count( $re2[1] )>0 ) {
                $yue = drFun::yuan2fen($re2[1][0]);

                //echo $yue.'>>>'.$money;

                //$this->drExit($re2);

                if ($money > $yue) {
                    $logStr= "smsHealth yue[" .$yue."] money[".$money."]>>" . $sms;
                    $this->log( $logStr );
                    $this->getLogin()->createQrPay()->toTelegram(4, $logStr);
                    return false;
                }
            }


        }
        //$this->drExit($re);

        return true;
    }


}