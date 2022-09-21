<?php
/**
 * 这个是支付接口，面对的是机器
 *
 * User: Administrator
 * Date: 2018/8/22
 * Time: 20:24
 */

namespace ctrl;


use model\drException;
use model\drFun;
use model\drTpl;
use model\lib\cache;
use model\lib\mq;
use model\log;
use model\table;
use model\trade;
use mysql_xdevapi\Exception;

class api extends drTpl
{
    private $cl_trade;

    /**
     * @return trade
     */
    function getTrade(){
        if( !$this->cl_trade )        $this->cl_trade= new trade();

        return  $this->cl_trade;

    }

    function act_huafei( $p ){
        $this->setDisplay('json');
        $this->log( "=====huafei>".$p[0]."= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));

        $mc = $this->getLogin()->createQrPay()->getMerchantByAppID($_POST['app_id']);
        $var = $_POST;
        $this->signConfirm($mc, $var);

        switch ($p[0]){
            case 'huafei':
                $re=$this->getLogin()->createMServer()->huafei($var, $mc);
                $this->assign('fee', $re );
                if( $re['fee_id']>0 ){
                    $re['cmd']='cn.huafei.create';
                    $this->getLogin()->createQrPay()->toMqTrade( $re,'client_pay_log_v3'  );
                }
                break;

        }
    }

    /**
     * 支付宝支付宝
     */
    function act_pay(){
        if(  1 ){ //$_POST['format']=='json'
            $this->setDisplay('json');
            $this->pay();
        }else {
            /*
            $error = ['error' => 0, 'error_des' => ''];
            try {
                $this->pay();
            } catch (drException $ex) {
                //$this->drExit( $ex );
                $error = ['error' => $ex->getCode(), 'error_des' => $ex->getMessage()];
                //$this->assign('error',$ex->getCode());
                //$this->assign('error_des', $ex->getMessage() );
            }
            $this->assign('error', $error);
            $this->htmlFile = "pay.phtml";
            */
            $this->setDisplay('json');
            $this->pay();
        }
    }

    function act_unionPay(){
        if(  $_POST['format']=='html' ){
            $error = ['error' => 0, 'error_des' => ''];
            try {
                $this->unionPay();
            } catch (drException $ex) {
                //$this->drExit( $ex );
                $error = ['error' => $ex->getCode(), 'error_des' => $ex->getMessage()];
                //$this->assign('error',$ex->getCode());
                //$this->assign('error_des', $ex->getMessage() );
            }
            $this->assign('error', $error);
            $this->htmlFile = "pay.phtml";

        }else {
            $this->setDisplay('json');
            $this->unionPay();
        }
    }

    function unionPay(){

        $min = intval( date("Hi"));
        if( $min>2350 || $min<10 ) $this->throw_exception( "隔日结算中",1151);


        $this->log( "\n=====POST= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));

        $pay_type=3;

        //$this->drExit( $_POST);

        //if($_POST['format']=='app' && !drFun::isMobile() ) $this->throw_exception("请在手机上使用！",201808009);
        //if($_POST['format']=='app' && $_POST['pay_type']==1 && drFun::isWeixin() ) $this->throw_exception("请在微信外使用",201808011);

        $mc=$this->getLogin()->createQrPay()->getMerchantByAppID( $_POST['app_id']);
        $var = $_POST;   unset( $var['sign']);
        //print_r($mc );print_r( $var );
        $this->getTrade()->urlDecode( $var );
        $sign = $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $var );
        //$sign = $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $var ,['decode'=>1 ] );

        //$this->drExit( $sign );


        if( $_POST['sign']!=$sign ) $this->throw_exception("错误签名错误！"  ,20180801);

        $trade_row = $this->getLogin()->createQrPay()->getTradeByOrderNo($mc['merchant_id'], $var['order_no']);

        #新的方式测试
        $is_debug_version = in_array($mc['merchant_id'],[  8080,8088 , 8133 ,8100 ,8099, 8111 ] ); //

        $version=  $this->getLogin()->getVersionByMid( $mc['merchant_id'] ,$pay_type );
        $is_debug_version = ( $version==2 ) ;

        if( in_array($mc['merchant_id'], [ 123 ] )    ){ // 8088  8166,8168,8177,8188
            $this->throw_exception("公测未开放！"  ,2019);
        }
        if( in_array($mc['merchant_id'], [ 8133 ] ) &&  $var['price']<=2000    ){
            //$this->throw_exception("请试一试其他金额！"  ,2019);
        }

        $mc['is_debug_version'] = $is_debug_version;
        $mc['version'] = $version;

        $mc['account_type'] = $pay_type; //账号属性 3银联支付




        if(!$trade_row) {
            if( $version==3 ) $qr = $this->getLogin()->createQrPay()->getLiveQrV3($var['price'] , $mc );
            //elseif( $mc['merchant_id']==8080  ||  $mc['merchant_id']==8088 ){ //
            elseif( $version==2  || $version==4  ){ //
                $qr = $this->getLogin()->createQrPay()->getLiveQrV2( $var['price'] , $mc );
            }elseif( $version==5 ){
                $qr = $this->getLogin()->createQrPay()->getLiveQrV5( $var['price'] , $mc );
            }
            else
                $qr = $this->getLogin()->createQrPay()->getLiveQr( $var['price'] , $mc );

            $var['merchant_id'] = $mc['merchant_id'];
            $var['realprice'] = $qr['fee'];
            $var['qr_id'] = $qr['qr_id'];
            $var['account_id'] = $qr['account_id'];
            $var['pay_type'] = $pay_type;

            $this->getLogin()->createQrPay()->createTrade($var);

            $trade_row['trade_id']= $this->getLogin()->createSql()->lastID();
            $trade_row['ctime']= time();
            $trade_row['realprice'] = $var['realprice'] ;
            $trade_row['price'] = $var['price'] ;

        }else{
            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );

        }

        //$this->log( "create trade_id:" .  $trade_row['trade_id']  );
        //$this->log( $qr );

        if( $_POST['format']!='html' ){
            $act = 'union';
            //$this->drExit( $act );
//            if($is_debug_version ) $act = 'ali2';
//            elseif( $version==3 ) $act = 'ali3';
//            elseif( $version==4 ) $act = 'ali4';
//            elseif( $version==5 ) $act = 'ali4';


            $md5= $this->getPaySign( $trade_row['trade_id'] );//md5($trade_row['trade_id'].'adf888');

            $pay=['url'=>'https://qz.atbaidu.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;




            $pay['realprice'] = $trade_row['realprice'];
            $pay['price'] = $trade_row['price'];
            $pay['sign'] =   $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $pay );
            $this->assign('pay_data', $pay);
            return ;
        }

        //$this->drExit( $qr );
        //$this->drExit($_POST);
        $timeLimit = $trade_row['ctime']+180-   time();
        $qr_var=['price'=>number_format($qr['fee']/100, 2)  ,'url'=>$this->getQrUrlSign($qr)
            ,'timeLimit'=> $timeLimit ,'trade_no'=> $trade_row['trade_id'],'mc_id'=>$mc['merchant_id'],'qr_url'=>$qr['qr_text'] ];
        $this->assign('qr_var', $qr_var );
        $this->assign('post', $_POST );
    }

    function act_union( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        try{

            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );

            if( $trade_row['type'] >0  && !isset($_GET['no'])) $this->throw_exception( "请勿重复支付！", 456 );

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck ) {
                $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*60 );
            }
            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP(),'client'=>drFun::getClientV2() ] );

            $url2= 'https://'.drFun::getHttpHost().'/api/url4/'. implode('/',$p ); //https://qz.q41n.com
            //$url2= 'https://qz.q41n.com/api/un/'. implode('/',$p );
            //$url2= 'https://qz.yagdsj.com/api/url4/'. implode('/',$p );
            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(!$qr) $this->throw_exception("二维码有错误！");
            $url = $qr['qr_text'];
            //$url = 'https://qr.95516.com/00010000/01169441514889612153670046215983';
            $this->assign('url',$url )->assign( 'trade', $trade_row);
            $this->htmlFile='app/union_show.phtml';
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }
    function act_un( $p ){

    }

    //

    /**
     * 通过order no查询
     * http://qunfu.zahei.com/api/checkPay/order?app_id=q1234567&order_no=m201808252459
     *
     * 通过trade_id 查询
     * http://qunfu.zahei.com/api/checkPay/1/20181010464
     * @param $p
     */
    function act_checkPay( $p ){
        $this->setDisplay('json');
        switch ($p[0]){
            case 'order':
                $this->checkPayByOrder($_REQUEST['order_no'],$_REQUEST['app_id']);
                break;
            default:
                $this->checkPayByTradeID( $p[1], $p[0]);
        }
        //$this->display();
    }

    function act_paylink($p ){
        //if( ! drFun::isMobile() )  $this->drExit('请在手机上使用' );
        //
        $this->act_ali( $p );
        /*

        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );

            if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");
            //$this->redirect( $qr['qr_text'] );
            header('Location: '.$qr['qr_text']);

            //$this->log( "paylink trade_id:" .  $trade_row['trade_id']  );
            //$this->log( $qr );
            parent::drExit();//释放资源
        }catch (drException $ex ){
            $this->apiError( $ex );
        }
        */

    }

    function apiError( $ex ){
        $this->logErr(  $ex->getCode()."\n" .$ex->getMessage()."\n". $ex->getTraceAsString() );
        $html='<!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
</head><body><div style="text-align:center;padding-top: 50px;">'. $ex->getMessage() .'</div></body></html>';
        $this->drExit( $html );
    }

    function act_url( $p ){
        $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
        if( !$is_ali ){
            $p['error']='no in alipay';
            $p['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ;
            $this->logErr(   $p  );
            $this->redirect( '/api/no','' );
        }
        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
            //$this->drExit( $trade_row ); //|| trade_row['type'] <=0 || $trade_row['type']==3
            if(! ($trade_row['type']==4  ) ){
                $this->throw_exception( "请勿重复支付！" , 888 );
            }
            //$this->drExit( $trade_row );
            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>3] );
            //if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");
            //$this->redirect( $qr['qr_text'] );

            header('Location: '.$qr['qr_text']);

            //$this->log( "paylink trade_id:" .  $trade_row['trade_id']  );
            //$this->log( $qr );
            parent::drExit();//释放资源
        }catch (drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_no(){
        $this->logErr(  "不在支付宝内：". $_SERVER['HTTP_USER_AGENT']  );
        $html='<!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
</head><body><div style="text-align:center;padding-top: 50px;">错误！</div></body></html>';
        $this->drExit( $html );
    }

    function act_url3( $p ){
        $this->drExit('hello');
    }

    function getCardNo( & $account){
        $card_index = trim( $account['card_index'] );
        $account['cardShow']= trim( $account['zhifu_account']);
        if(! $card_index) return  urlencode( trim( $account['zhifu_account']));
        $card= trim( $account['zhifu_account']);
        $cardNo= substr( $card,0,3);
        $cardNo.='****';
        $cardNo.= substr($card,-3 );
        $account['cardShow']= $cardNo;
        $cardNo.='&cardChannel=HISTORY_CARD&cardNoHidden=true&cardIndex='. $card_index ;
        return $cardNo;
    }



    function act_v40open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            #if( !$is_ali ) $this->throw_exception("请在支付宝内使用",460 );

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $this->test1wan( $trade_row );

            $usIng= $this->getLogin()->createQrPay()->getTypeTradeUsing();
            $usIng[]=0;

            $limit_time= ($trade_row['ctime']+300)-time() ;

            //(time()- $trade_row['ctime']) >300 ||
            if(  !in_array(  $trade_row['type'],$usIng ) && !isset($_GET['no'])  ) $this->throw_exception( "支付成功或已过期！", 459 );
            // if(  !in_array(  $trade_row['type'],[ 4 ] )  ) $this->throw_exception( "请勿重复支付,如已支付稍等1-3分钟到账！", 1459 );



            //if ($_GET['u'] && $_GET['s']) {
            if ( 1 ) {
                $ali_uid='';
                $this->assign('limit_time',$limit_time );
                $up2= ['ali_uid'=>$ali_uid ,'type'=>3] ;
                if( $trade_row['cookie']=='' ) {
                    $qf_ck = $_COOKIE['qf'];
                    if (!$qf_ck) $qf_ck = drFun::rankStr(8);
                    drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
                    $client = drFun::getClientV2();
                    if ($client <= 0) $client = 1;
                    $var = ['cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client];
                    $this->checkHei($trade_row, $qf_ck, ['up' => $var]);
                    $up2=$var;
                    $up2['type']=3;
                }

                $this->getLogin()->createQrPay()->upTradeByID($id,$up2 );

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $bank2Type = $this->getLogin()->createQrPay()->getBankType();
                $bank= $bank2Type[ $account['bank_id'] ];





                if( $this->isIphone() ){
                    //$url='https://www.alipay.com/?appId=09999988&actionType=toCard&sourceId=bill&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.urlencode( trim( $account['zhifu_account'])).'&bankAccount='.urlencode( trim( $account['zhifu_name']))."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']) ;
                    $url='https://www.alipay.com/?appId=%30%39%39%39%39%39%38%38&clientVersion=10.1.18.708&actionType=toCard&sourceId=ZHUANZHUANG&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.$this->getCardNo( $account).'&bankAccount='.urlencode( trim( $account['zhifu_name']))."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']) ;
                }else{
                    //$url='alipays://platformapi/startapp?appId=09999988&actionType=toCard&goBack=NO&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.urlencode( trim( $account['zhifu_account'])).'&bankAccount='.urlencode( trim( $account['zhifu_name']))."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n'])
                    //
              $url='alipays://platformapi/startapp?appId=%30%39%39%39%39%39%38%38&clientVersion=10.1.18.708&actionType=toCard&sourceId=ZHUANZHUANG&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.$this->getCardNo( $account) .'&bankAccount='.urlencode( trim( $account['zhifu_name']))."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']);
                }

                $url6='alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$this->getCardNo( $account) .'&bankAccount='. trim( $account['zhifu_name']).'&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&bankMark='.urlencode( $bank['c']).'&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from';
                $url6= 'taobao://render.alipay.com/p/s/i?scheme='. urlencode($url6 );

                $url7='alipays://platformapi/startapp?appId=20000200&actionType=toCard&sourceId=bill&cardNo='.$this->getCardNo( $account) .'&bankAccount='. trim( $account['zhifu_name']).'&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&bankMark='.urlencode( $bank['c']).'&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from';


                $url8='https://www.alipay.com/?appId=09999988&actionType=toCard&goBack=NO&sourceId=bill&cardNo=****************&bankAccount='
                    .urlencode(trim( $account['zhifu_name'])).'&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&bankMark='.urlencode( $bank['c']).'&bankName='.urlencode($bank['n']).'&cardIndex='. trim( $account['card_index'] ).'&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from';
                $this->assign('url7', $url7 )->assign('url8', $url8);

                if($_GET['ds3']){

                }
                //$this->redirect( $url8 );

                //$url='alipays://platformapi/startapp?appId=%30%39%39%39%39%39%38%38&actionType=toCard&goBack=NO&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.$this->getCardNo( $account) .'&bankAccount='.urlencode( trim( $account['zhifu_name']))."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']);

                $this->assign('trade',$trade_row)->assign('account', $account)->assign('bank', $bank )->assign('taobao_url', $url6 );



                //header('Location: '. $url );
                $this->htmlFile='app/v40open.phtml';
                //if($trade_row['merchant_id'] ==8080 || 8222== $trade_row['merchant_id']){
                if( in_array( $trade_row['merchant_id'], $this->tsArr()  )){
                    $this->htmlFile='app/v40open_reopen.phtml';
                }

                if($_GET['new']==123 ){
                    $this->htmlFile='app/v40open_123.phtml';
                }
                $this->assign('url',$url )->assign('isIOS', $this->isIphone() );

                $card_index = trim( $account['card_index'] );
                #$card_index='';

                $hb=[];
                $hb['cardNo']= ($card_index)? '请打开网络*****' : $account['zhifu_account'] ;

                if( in_array( $trade_row['merchant_id'],[8389] )  )  $hb['cardNo']=   $account['zhifu_account'] ;

                $hb['bankAccount']= trim( $account['zhifu_name']);
                $hb['money']= ($trade_row['realprice']/100) ;
                $hb['bankMark']= $bank['c'] ;
                $hb['bankName']= $bank['n'] ;
                $hb['cardId']= $card_index;
                $this->assign('hb',$hb );


                $this->htmlFile='app/tool_t22s.phtml';

                $this->htmlFile='app/tool_t22.phtml';

                if($_GET['ds2']==2 || 'wait'==$p[3] ) {
                    $this->htmlFile='app/v40wait.phtml';
                }elseif( '3'==$p[3] ){
                    $this->htmlFile='app/v40v3.phtml';
                }elseif($_GET['ds2']){
                    $this->htmlFile='app/tool_t22.phtml';
                }

                $this->htmlFile='app/v40v3.phtml';

                $this->htmlFile='app/v40v4.phtml';

                if( $_GET['p']=='wifi'){
                    $this->htmlFile='app/v40v4_wifi.phtml';
                }else { // ( $_GET['p']=='start' )
                    $this->htmlFile='app/v40v4_start.phtml';
                }


                $this->htmlFile='app/v40v212.phtml';
                $this->htmlFile='app/v40ali.phtml';

                if(  $_GET['ds3'] ){ #C212
                    $this->htmlFile='app/v40v212V2.phtml';
                    //$this->htmlFile='app/v40v4.phtml';


                    if( $_GET['p']=='577' ){
                        $this->htmlFile='app/v40v577.phtml';
                    }elseif ($_GET['p']=='212'){
                        $this->htmlFile='app/v40v212.phtml';
                    }elseif( drFun::getClientV2()===1 ){
                        $this->htmlFile='app/v40v577Start.phtml';
                    }

                }

                if($_GET['tb']=='plus'){
                    $this->htmlFile='app/v40v4plus.phtml';
                }

                /*
                if( $_GET['p']=='577' ){
                    $this->htmlFile='app/v40v577.phtml';
                }elseif ($_GET['p']=='212'){
                    $this->htmlFile='app/v40v212.phtml';
                }elseif( drFun::getClientV2()===1 ){
                    $this->htmlFile='app/v40v577Start.phtml';
                }
                */




                //if(  in_array( $trade_row['merchant_id'],[8389] )  ) $this->htmlFile='app/v40v6.phtml';

                //parent::drExit();//释放资源

            } elseif ($_GET['auth_code']) {
                $redirect_uri = '/api/v40open/' . implode('/', $p);
                $url = 'https://qz.becunion.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                $this->redirect($url);
            } else {
                $c_file = dirname(dirname(dirname(__FILE__))) . "/webroot/lab/alipay/config.php";
                require_once $c_file;
                $redirect_uri = 'https://qf.zahei.com/api/v40open/' . implode('/', $p);
                $url = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=' . $config['app_id'] . '&scope=auth_base&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $p[1];
                //die($url);
                //header('Location: '. $url );
                $this->redirect($url);
            }
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function tsArr(){
        return [];//,8222 //8225 //8080
    }

    function isWx(){
        $is_wx= strpos( $_SERVER['HTTP_USER_AGENT'], 'MicroMessenger');
        return $is_wx ;
    }

    function act_v30open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if( !$is_ali ) $this->throw_exception("请在支付宝内使用",460 );

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng= $this->getLogin()->createQrPay()->getTypeTradeUsing();
            // (time()- $trade_row['ctime']) >180 ||
            if( !in_array(  $trade_row['type'],$usIng )  ) $this->throw_exception( "支付成功或已过期！", 459 );



            if ($_GET['u'] && $_GET['s']) {


                $ali_uid = intval($_GET['u']);
                $checkSign = md5($ali_uid . "dmd@dd454A%.dada" . $p[count($p) - 2]);

                if ($checkSign != $_GET['s']) $this->throw_exception("认证失败！");
                if( $trade_row['ali_uid'] && $trade_row['ali_uid']!= $ali_uid){
                    $this->throw_exception( "请勿重复支付！", 459 );
                }


                $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$ali_uid ,'type'=>3] );





                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

                $ali_user = $this->getLogin()->createPayLog()->getAliUserByUid( $ali_uid );

                #转账
                $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&userId='.$account['ali_uid'];
                $url.='&amount='.($trade_row['realprice']/100) .'&money='.($trade_row['realprice']/100)    ;

                if( $trade_row['merchant_id'] == 8080 ||  $trade_row['merchant_id'] >=8255 ){
                    $qr =$this->getLogin()->createQrPay()->getQrRowByWhere(['account_id'=>$trade_row['account_id'] ,'fee'=>10086 ] );
                    if($qr['qr_text']){
                        $arr = explode('?',$qr['qr_text'] );
                        //$url= $arr[0].'?t='.(1000*time());
                    }
                }elseif( !$ali_user && $trade_row['merchant_id'] != 8222 ) {

                    /*
                    $acc_id = $this->getLogin()->createQrPay()->getAccountIDByMerchantId($trade_row['merchant_id'] ,4 );
                    if($acc_id ){
                        $account2=  $this->getLogin()->createQrPay()->getAccountByID( $acc_id[0]  );
                        if($account2 ) {
                            $account= $account2;
                            $this->getLogin()->createQrPay()->upTradeByID($id, ['account_id' => $account['account_id']]);
                            #扫码
                            //$bizData = ['a'=> ($trade_row['realprice']/100).'','c'=>'','s'=>'online','u'=>$account['ali_uid'],'m'=> ''   ];
                            $bizData = ['a' => '', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
                            $url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode(json_encode($bizData)) . '&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
                        }else{
                            $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&userId='.$account['ali_uid'];
                        }

                    }else {
                        $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&userId='.$account['ali_uid'];
                    }
                    */

                    #扫码
                    $bizData = ['a' => '', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
                    $bizData = ['a' => ($trade_row['realprice']/100).'', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
                    $bizData = ['a' => ($trade_row['realprice']/100).'', 'c' => '', 's' => 'online', 'u' => $account['ali_uid']  ]; //, 'm' => ''
                    //$url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode(json_encode($bizData)) . '&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';

                }

                #扫码
                $bizData = ['a' => '', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
                $bizData = ['a' => ($trade_row['realprice']/100).'', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
                $bizData = ['a' => ($trade_row['realprice']/100).'' , 's' => 'online', 'u' => $account['ali_uid']  ]; //, 'm' => ''
                //$url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode(json_encode($bizData)) ;// . '&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';

                //$url='alipayqr://platformapi/startapp?appId=20000123&actionType=scan&biz_data={\"s\": \"money\",\"u\": \"'. $account['ali_uid'].'\",\"a\": \"'.($trade_row['realprice']/100).'\"}';

                $hongbao= [ ];
                $hongbao['i'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['a'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                $hongbao['url']= $url;

                $this->assign('hongbao',$hongbao);
                $this->assign('url',$url );
                $this->htmlFile='app/v30open.phtml';
                //$this->htmlFile='app/v30open2.phtml';

                //parent::drExit();//释放资源

            } elseif ($_GET['auth_code']) {
                $redirect_uri = '/api/v30open/' . implode('/', $p);
                $url = 'https://qz.becunion.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                $this->redirect($url);
            } else {
                $c_file = dirname(dirname(dirname(__FILE__))) . "/webroot/lab/alipay/config.php";
                require_once $c_file;
                $redirect_uri = 'https://qf.zahei.com/api/v30open/' . implode('/', $p);
                $url = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=' . $config['app_id'] . '&scope=auth_base&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $p[1];
                //die($url);
                //header('Location: '. $url );
                $this->redirect($url);
            }
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_v120open( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            if(  !$this->isWx()  ) $this->throw_exception( "请在微信客户端内使用");



            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
            // (time()- $trade_row['ctime']) >180 ||
            if (!in_array($trade_row['type'], [4] )) $this->throw_exception("请勿重复扫码！", 459);


            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );


            $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);

            $qr['data']= drFun::json_decode( $qr['data'] );
            //$this->drExit( $qr );

            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            $hongbao['uid'] = $account['ali_uid'];//自己的UID
            //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            $hongbao['url'] =$qr['data']['qr'] ;//'/api/getWxqr/trade/'. $hongbao['remark'].'/'. $account['ali_uid'];//自己的登录账号
            $hongbao['ctime']= time();

            //清空群
            drFun::clearQunMember( $qr['account_ali_uid'] , $qr['ali_trade_no']);


            $this->assign('hongbao', $hongbao)->assign('trade',$trade_row);
            //$this->htmlFile='app/v20open.phtml';
            $this->htmlFile='app/v120open.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }


    function act_v22open( $p ){ // /api/v22open/test/made2099god
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            if(  !$this->isWx()  ) $this->throw_exception( "请在微信客户端内使用");

            if( $sign=='test' ) {
                $hongbao['remark'] = 'T' . date("mdhis").rand(100,999);
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                //$hongbao['account'] = $p[2];//自己的登录账号
                $hongbao['url'] = '/api/getWxqr/test/'. $hongbao['remark'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['ctime']= time();
            }else{

                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);


                $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );



                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                $hongbao['url'] = '/api/getWxqr/trade/'. $hongbao['remark'].'/'. $account['ali_uid'];//自己的登录账号
                $hongbao['ctime']= time();
            }

            $this->assign('hongbao', $hongbao);
            $this->htmlFile='app/v20open.phtml';
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }



    function act_v23open( $p ){ // /api/v22open/test/made2099god
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            if(  !$this->isWx()  ) $this->throw_exception( "请在微信客户端内使用");

            if( $sign=='test' ) {
                $hongbao['remark'] = 'T' . date("mdhis").rand(100,999);
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                //$hongbao['account'] = $p[2];//自己的登录账号
                $hongbao['url'] = '/api/getWxqr/test/'. $hongbao['remark'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['ctime']= time();
            }else{

                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);


                $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

                $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
                if(  !$qr ) $this->throw_exception( "非法支付！");


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                $hongbao['url'] =  $qr['qr_text'];//支付二维码
                $hongbao['ctime']= time();
                $this->assign('trade', $trade_row );
            }

            $this->assign('hongbao', $hongbao);
            //$this->htmlFile='app/v23open.phtml';
            $this->htmlFile='app/v23openV2.phtml';
            $this->htmlFile='app/v23openV4.phtml';
            if($_GET['ds']==1  ){
                $this->htmlFile='app/v23openV4.phtml';
            }
        }catch ( drException $ex ){
            $this->logErr("v23open ID:". $id );
            $this->apiError( $ex );
        }

    }


    function act_v36open( $p){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) {
                $url= 'https://'.drFun::getHttpHost().'/api/v36open/'. implode('/',$p );
                $this->redirect( $this->getAliUrl( $url) );
                //$this->throw_exception("请在支付宝内使用", 460);
            }

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            }




            if ($_GET['u'] && $_GET['s']) {


                $ali_uid = intval($_GET['u']);
                $checkSign = md5($ali_uid . "dmd@dd454A%.dada" . $p[count($p) - 2]);

                if ($checkSign != $_GET['s']) $this->throw_exception("认证失败！");

                if( $sign=='test' ) {
                    $hongbao['i'] = $p[3]?  $p[3] :'T' . date("mdhis").rand(100,999);
                    $hongbao['amount'] = '0.01';
                    $hongbao['a'] = $p[1];//自己的UID
                    $hongbao['j'] = $p[2];//自己的登录账号
                    $hongbao['url'] = '/api/getBill/test/'. $hongbao['i'].'/'. $hongbao['a'].'/'.$_GET['u'].'/0.01';//自己的登录账号
                    $hongbao['ctime']= time();
                }else{

                    $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$ali_uid ,'type'=>3] );

                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );


                    $hongbao['i'] = $trade_row['trade_id'];
                    $hongbao['amount'] = $trade_row['realprice']/100 ;
                    $hongbao['a'] = $account['ali_uid'];//自己的UID
                    $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                    $hongbao['url'] = '/api/getBill/trade/'. $hongbao['i'].'/'. $ali_uid;//自己的登录账号
                    $hongbao['ctime']= time();

                }

            /*if ($_GET['u'] && $_GET['s']) {*/
                //$this->drExit($_GET);


                //header('Location: '. $url );
                $this->assign('url',$url )->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  );
                $this->htmlFile='app/v36open.phtml';
                $this->htmlFile='app/v36openV2.phtml';

                $this->htmlFile='app/v36openV2b.phtml';

                if(  $hongbao['a']=='2088232932547186' ){
                    //$this->htmlFile='app/v36openV2s.phtml';
                    $this->htmlFile='app/v36openV2b.phtml';
                }

                $this->htmlFile='app/v36openV3.phtml';
                //parent::drExit();//释放资源

            } elseif ($_GET['auth_code']) {
                $redirect_uri = '/api/v36open/' . implode('/', $p);
                $url = 'https://qz.becunion.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                if( $trade_row['merchant_id']==8080){ }
                $url = 'https://qz.atbaidu.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                $this->redirect($url);
            } else {

                $c_file = dirname(dirname(dirname(__FILE__))) . "/webroot/lab/alipay/config.php";
                require_once $c_file;
                $redirect_uri = 'https://qf.zahei.com/api/v36open/' . implode('/', $p);
                $url = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=' . $config['app_id'] . '&scope=auth_base&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $p[1];
                //die($url);
                //header('Location: '. $url );
                $this->redirect($url);
            }
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_aliID( $p ){
        // https://qz.atbaidu.com/api/aliID/m5/2/3
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) {
                 $this->throw_exception("请使用支付宝扫一扫", 460);
            }

            if ($_GET['u'] && $_GET['s']) {


                $ali_uid = intval($_GET['u']);
                $checkSign = md5($ali_uid . "dmd@dd454A%.dada" . $p[count($p) - 2]);

                if ($checkSign != $_GET['s']) $this->throw_exception("认证失败！");

                $this->throw_exception("支ID: ". $ali_uid );

            } elseif ($_GET['auth_code']) {
                //真正请求在这里
                $redirect_uri = '/api/aliID/' . implode('/', $p);
                //$url = 'https://qz.becunion.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                //if( $trade_row['merchant_id']==8080){ }
                $url = 'http://q1.atbaidu.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                $this->redirect($url);
            } else {

                $c_file = dirname(dirname(dirname(__FILE__))) . "/webroot/lab/alipay/config.php";
                require_once $c_file;
                $redirect_uri = 'https://qf.zahei.com/api/aliID/' . implode('/', $p);
                $url = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=' . $config['app_id'] . '&scope=auth_base&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $p[1];
                //die($url);
                //header('Location: '. $url );
                $this->redirect($url);
            }


        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }



    function act_v78open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            }


            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
            $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>3] );


            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            $hongbao['uid'] = $account['ali_uid'];//自己的UID
            $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            $hongbao['url'] = '/api/dingBillV2/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
            $hongbao['ctime']= time();
            $hongbao['auto']= 1;//intval($p[3]);
            //$hongbao['payUrl']='';


            if( $sign =='test'){
                $hongbao['payUrl']='alipay_sdk=alipay-sdk-java-3.5.35.DEV&app_id=2018101061618854&biz_content=7UyROpIwNDbETBJrNdfXuutjfKDfqQVc5wAB1wHdeQWttRMj8EnIXx74dRUO7eE1jDoQARR9Br4VkWJRygQRUrHAeCMazMTCv8D7BRla69J3NlvzNR7vkbcdP0DuXH9T15Jv%2BCIm1Z97ehw7p7iX2To7WbH60mCKtD2c%2FvUkr0zDA69zZGHNtbEfCfnuwEKJpn%2BJ0hNtSOMoWn9Nzs1PCVtPtzaS%2F7ZYwcGRYkZFEz63oj3D%2BjpoIKN7JuNPDenJWEYVRbNdW6%2F8iPnQZEywRSFqZKayVfSGPoNZcGVFAXL0%2Fc0RzBnhb7KUfD2zjoubqJBkASqH4V7DuVEs0rY7ciV9uvA1Cm0tGfaxDlMwPzuWjDiQq6C0E%2BNbM8EOJzo7escO0iZ95Djd0LRTA8MyOQJTmoQbfpMtek2OMQKUEovde3Vd%2FgBRQujgT%2BpNZYIR2AsSXDjDn5rdAwq48tvqyrZLYRor9LjZuGEla7HhQ5ABHRoca%2Fsm1kPAD1%2B2YQq0cVzvzQA7x%2BsLf2BS18DSQ%2FVYD2pQyiSPeFXq2WXNC9%2FgpCQvHtkNhFuzcjDT%2BoHma%2FparmjFt%2FdlsVJ%2FDhtQ14ArhCcO%2B2vYkC5gsjEZKJW%2Foz2hCRucxkxbL2Gzp5Z8Jzwjy1SgHLWZrjY0a4rlGKlHYsM4IS%2FDlBw%2Bb5LK8JweSqDNhmGcuLpPQG1k2uyYJKbpMXZzBGaQfHFO5Hh%2BrGgbfIMcQ09pKnMqfkRWHqpdScyxLR%2FBlvCy6zmV7faVQvpjeXIKYOhNtiuGLB1mv1aJ5HkuXKRX3QvnyLTC7uWYZaTizrSy3zC2kaYDo6rtUNrhs72RA5yAQDl%2FHA%2FgZlZkadqD%2BRrVcNUQBt1zTn%2BKH0yVTYkh5B7AH9DVLBKi6ck13d7IPkdVdqtOap5tlJC0jihVa71IscY205qvMAc5bFSWTQhelHEctO%2Bs5l8qJF7143gCJS3b9LrFsRwEoS16EUlprK19hJzjJnGxfH8URD8mzb%2B4KxJyhGBkQZQxPzpxsM6r1btjStLEbmuwjxHWvnuwwWMRmovI6aje3pYmx%2B0FMuAYZSMoxZdVLxMBZw0dgn6AQd95FCtkbPiOipozChh2eewgV8N3QDfRHjrOShAl%2BIU8o%2Bg7RH8bgpplk%2Fpsxo80Vs4iQyTGp%2FwexYTjuNSkPwh6MtfoC%2FKDE8hB%2FbXh8ABvV%2B%2FFR1WWnK%2Bx8gGE%2B7reK4SI%2B%2BSBbdIAZ7QxatE%2FQMOQze34VaCYkaN4I%2F5kQYcsGDiNinVlM3oKwa0OVkECtGk7gVDIGMXEFg8p1Qcscl0IN1JzvLs3JnRjhDHLam7Ox%2BPJm45AxpIgRDcC%2FCXGiBgMj6R1AE5uzTj820bxmr98iS1v%2B7XKN%2FD72IQdZGEkR5cBCkOuy08B8HOJDD6C%2F0hR9dpKqXS7ZgATsnVMFsH3MT9mQRzd3d0jGL4JLnbnvw06FKegALfm1EaUSeVR%2FylzaRw%2BeI7rwQ%3D%3D&charset=UTF-8&encrypt_type=AES&format=json&method=alipay.fund.trans.app.pay&sign=O6a0wdcawm%2BG5T9L6KngJAC7CsbQFhZLqsyfMpdMLGJtpB5WowglYn%2F3KtMcOZZdd12wxH5liY3p%2FU4qwWoWVoItuB2eRFazL%2FP2l10GugMFX5kC97JsePOSr89xPTtVB2niwh9FdgRVVTRlnI%2FwElaHolLY2COTqwcg%2Bju5nbvK7%2BKdgr%2FS0uQNzyl0g301Zpx%2BfjHFzKPvxlFm%2Bhw15qBlAqpm4rcLyhWXZWxcZDKcYZ3JIwSXEp9i8eTUaODL9RPzQETvGuPwagh3tDkCAB32vP1M9IdLBW5IGLtTJ6e%2B7oonapGRcfGANn9mouuyVVVg5GVL11pIeIyrJs3Hgg%3D%3D&sign_type=RSA2&timestamp=2019-05-04+22%3A21%3A31&version=1.0';
            }else{
                $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);//getr(  $trade_row['qr_id'] );
                if(  !$qr ) $this->throw_exception( "非法支付！");
                $data= drFun::json_decode($qr['data'] );
                $hongbao['payUrl']= $data['alipayOrderString'];
            }


            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  );
            $this->htmlFile='app/v78open.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }


    }

    function act_v139open( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                $usIng[]=0;
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng) && !isset($_GET['no']) ) $this->throw_exception("支付成功或已过期！", 459);
            }

            if( $sign=='test' ) {
                $hongbao['remark'] = $p[2]? $p[2] : ('19' . date("mdhis"));//'19' . date("mdhis") ;
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                //$hongbao['account'] = $p[2];//自己的登录账号
                $hongbao['url'] = '/api/wangBill/test/'. $hongbao['remark'].'/'. $hongbao['uid'].'/1';//自己的登录账号
                $hongbao['ctime']= time();
                $hongbao['auto']= 1;//intval($_GET['auto']);

            }else{
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                //$upData = ['ali_uid'=>$account['ali_uid'] ,'type'=>3] ;
                $qf_ck = $_COOKIE['qf'];
                if (!$qf_ck) $qf_ck = drFun::rankStr(8);
                drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

                $client = drFun::getClientV2();
                $upData = ['ali_uid'=>$account['ali_uid'] ,'type'=>4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client] ;
                if( $trade_row['type']==3 ){
                    unset( $upData['type']);
                    //$upData['ip']= drFun::getIP();
                    //$upData['client']=  drFun::getClientV2()  ;
                }

                $this->getLogin()->createQrPay()->upTradeByID($id, $upData);
                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                $hongbao['url'] = '/api/wangBill/trade/'. $hongbao['remark'].'/'.  $account['account_id'];//自己的登录账号
                $hongbao['ctime']= time();
                $hongbao['auto']= 1;//intval($p[3]);
            }

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  );


            $this->htmlFile='app/v39open.phtml';
            $this->htmlFile='app/open138.phtml';
            //$this->htmlFile='app/v139open.phtml';



        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }


    }

    function act_ali139V2($p){
        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                $usIng[]=0;
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng) && !isset($_GET['no']) ) $this->throw_exception("支付成功或已过期！", 459);
            }

            if( $sign=='test' ) {
                $hongbao['remark'] = $p[2]? $p[2] : ('19' . date("mdhis"));//'19' . date("mdhis") ;
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                //$hongbao['account'] = $p[2];//自己的登录账号
                $hongbao['url'] = '/api/wangBill/test/'. $hongbao['remark'].'/'. $hongbao['uid'].'/1';//自己的登录账号
                $hongbao['ctime']= time();
                $hongbao['auto']= 1;//intval($_GET['auto']);

            }else{
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                //$upData = ['ali_uid'=>$account['ali_uid'] ,'type'=>3] ;
                $qf_ck = $_COOKIE['qf'];
                if (!$qf_ck) $qf_ck = drFun::rankStr(8);
                drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

                $client = drFun::getClientV2();
                $upData = ['ali_uid'=>$account['ali_uid'] ,'type'=>3, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client] ;
                if( !$is_ali ){
                    //$upData['ip']= drFun::getIP();
                    //$upData['client']=  drFun::getClientV2()  ;
                }

                $this->getLogin()->createQrPay()->upTradeByID($id, $upData);
                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                $hongbao['url'] = '/api/wangBill/trade/'. $hongbao['remark'].'/'.  $account['account_id'];//自己的登录账号
                $hongbao['ctime']= time();
                $hongbao['auto']= 1;//intval($p[3]);

                $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);//getr(  $trade_row['qr_id'] );
                if(  !$qr ) $this->throw_exception( "非法支付！");
                $data= drFun::json_decode($qr['data'] );

                $data['alipayOrderString']= base64_decode( $data['alipayOrderString']);

                $hongbao['payUrl']= $data['alipayOrderString'];

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

                $hongbao['payUrlV2']=  $appv2 ;

                if(  $data['session'] &&  $data['url'] &&( time()-$qr['ctime'])<1800 ) {

                    $hongbao['sms'] = 'https://mclient.alipay.com/h5/cashierSwitchAccountSel.htm?session=' . $data['session'] . '&cc=y&logonId=otherAccount&userIdLdc=';
                }

                $this->assign('tem',$qr);
            }

            $client= drFun::getClientV2();

            if( $client<=0) $client=1;

            $this->assign('hongbao',$hongbao )->assign( 'client', $client  )->assign('url4', $hongbao['sms']);

            $this->assign('trade', $trade_row );


            $this->htmlFile='app/v39open_t2.phtml';

                $this->htmlFile='app/open139v2.phtml';

            //$this->htmlFile='app/v139open.phtml';



        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }


    function act_v38open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            }

                if( $sign=='test' ) {
                    $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                    if( $p[3] )   $hongbao['remarki'] = $p[3];
                    $hongbao['amount'] = '0.01';
                    $hongbao['uid'] = $p[1];//自己的UID
                    $hongbao['j'] = $p[2];//自己的登录账号
                    //$hongbao['url'] = '/api/dingBill/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                    $hongbao['url'] = '/api/dingBillV2/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                    $hongbao['ctime']= time();
                }else{

                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>3] );


                    $hongbao['remark'] = $trade_row['trade_id'];
                    $hongbao['amount'] = $trade_row['realprice']/100 ;
                    $hongbao['uid'] = $account['ali_uid'];//自己的UID
                    $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                    //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                    $hongbao['url'] = '/api/dingBillV2/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                    $hongbao['ctime']= time();

                }
                $hongbao['auto']= 1 ;

                $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  );

                $this->htmlFile='app/v38open.phtml';
                $this->htmlFile='app/v38open_.phtml';



        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }


    }

    function act_ali63( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);


            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            $account = $this->getLogin()->createQrPay()->getAccountByID($trade_row['account_id']);


            $this->getLogin()->createQrPay()->upTradeByID($id, ['ali_uid' => $account['ali_uid'], 'type' => 3, 'ip' => drFun::getIP(), 'cookie' => $qf_ck, 'client' => drFun::getClientV2()]);


            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice'] / 100;
            $hongbao['uid'] = $account['ali_uid'];//自己的UID
            $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
            $hongbao['url'] = '/api/uniPayQr/trade/' . $hongbao['remark'] . '/' . $account['ali_uid'];//自己的登录账号
            $hongbao['ctime'] = time();

            $qr= $this->getLogin()->createQrPay()->getQrRowByWhere( ['account_id'=> $account['account_id' ], 'fee'=> 10086 ] );
            if( !$qr) $this->throw_exception("二维码不存在！");

            $hongbao['auto']= 1 ;

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );
            $this->assign('url',   $qr['qr_text'] );

            $this->htmlFile='app/ali63.phtml';
            if(  in_array( $trade_row['merchant_id'] ,[8573] )){
                $this->htmlFile='app/ali63_jd.phtml';
            }
            //8573


        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali61($p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
                if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            }

            if( $sign=='test' ) {
                $account= $this->getLogin()->createQrPay()->getAccountByAliUid(  $p[1] );


                $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                if( $p[3] )   $hongbao['remarki'] = $p[3];
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                $hongbao['j'] = $p[2];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['ctime']= time();

                $trade_row['realprice']='1';
                $trade_row['order_no']=  "[测试]".$hongbao['remarki'] ;
                //print_r( $account );

                //print_r( $qr );
                //$this->drExit($p);
            }else {

                $qr_row = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);
                if (!$qr_row) $this->throw_exception("请勿重复支付！", 459);
                $qr_row['data'] = drFun::json_decode($qr_row['data']);

                $this->assign('qr_row', $qr_row);

                $qf_ck = $_COOKIE['qf'];
                if (!$qf_ck) $qf_ck = drFun::rankStr(8);
                drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

                $account = $this->getLogin()->createQrPay()->getAccountByID($trade_row['account_id']);


                $this->getLogin()->createQrPay()->upTradeByID($id, ['ali_uid' => $account['ali_uid'], 'type' => 4, 'ip' => drFun::getIP(), 'cookie' => $qf_ck, 'client' => drFun::getClientV2()]);


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice'] / 100;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/trade/' . $hongbao['remark'] . '/' . $account['ali_uid'];//自己的登录账号
                $hongbao['ctime'] = time();
            }


            $qr= $this->getLogin()->createQrPay()->getQrRowByWhere( ['account_id'=> $account['account_id' ], 'fee'=> 10086 ] );
            if( !$qr) $this->throw_exception("二维码不存在！");

            $hongbao['auto']= 1 ;

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );
            $this->assign('url',   $qr['qr_text'] );



            $this->htmlFile='app/ali61.phtml';


            if(  in_array( $trade_row['merchant_id'] ,[8399] )){
                $this->htmlFile='app/ali61_jd.phtml';
            }


        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali65($p){
        //$this->drExit( $p );
        $sign= trim($p[0] );
        $id = $p[1];
        try{
            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);



                $this->test1wan( $trade_row );
            }
            if( $sign=='test' ) {

                if( $this->getLogin()->isUnipayYue() ){
                    //
                    $url= '/api/ali61/'. implode('/',$p );
                    $this->redirect( $url);
                }
                $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                if( $p[3] )   $hongbao['remarki'] = $p[3];
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                $hongbao['j'] = $p[2];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/test65/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['ctime']= time();

                $trade_row['realprice']='1';
                $trade_row['order_no']=  "[测试]".$hongbao['remarki'] ;
            }else{

                $qf_ck= $_COOKIE['qf'];
                if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );



                $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/trade65/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['ctime']= time();

                $this->assign('god','god');

            }
            $hongbao['auto']= 1 ;

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );


            //$this->htmlFile='app/v38open_.phtml';
            $this->htmlFile='app/ali60.phtml';
            if( in_array( $trade_row['merchant_id'] ,[8201] )){
                $this->htmlFile='app/ali60v2.phtml';
            }
            if(  in_array( $trade_row['merchant_id'] ,[8399] )){
                $this->htmlFile='app/ali60_jd.phtml';
            }



        } catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali28( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try{
            //$this->drExit( $p );

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            $client= drFun::getClientV2();

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
            $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>3 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );
            //$this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );

            $this->assign( 'client', $client  )->assign('trade', $trade_row );
            $account= $this->getLogin()->createQrPay()->getAccountByID($trade_row['account_id']  );
            $this->assign('account',$account);

            $this->htmlFile='app/ali28.phtml';


        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali96( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if ($sign != 'test') {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

                //$this->test1wan($trade_row);
            }

            if( $sign=='test' ) {


                $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                if( $p[3] )   $hongbao['remarki'] = $p[3];

                $hongbao['amount'] = '11';//$p[3] ? '0.01':'1';
                $hongbao['uid'] = $p[1];//自己的UID
                $hongbao['j'] = $p[2];//自己的登录账号
                $hongbao['url'] = '/api/B2JD/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/'. $hongbao['amount'];//自己的登录账号
                $hongbao['ctime']= time();

                $trade_row['realprice']=  $hongbao['amount'] *100;
                $trade_row['order_no']=  "[测试]".$hongbao['remarki'] ;

            }else{

                $qf_ck= $_COOKIE['qf'];
                if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

                $client= drFun::getClientV2();

                $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
                $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);


                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['url'] = '/api/B2JD/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['ctime']= time();
            }

            $hongbao['auto']= 1 ;
            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );
            $this->htmlFile='app/ali96.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }


    function act_ali90( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if ($sign != 'test') {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

                //$this->test1wan($trade_row);
            }

            if( $sign=='test' ) {


                $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                if( $p[3] )   $hongbao['remarki'] = $p[3];

                $hongbao['amount'] = $p[3] ? '0.01':'1';
                $hongbao['uid'] = $p[1];//自己的UID
                $hongbao['j'] = $p[2];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['url'] = '/api/B2Qr/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/'. $hongbao['amount'];//自己的登录账号
                $hongbao['ctime']= time();

                $trade_row['realprice']=  $hongbao['amount'] *100;
                $trade_row['order_no']=  "[测试]".$hongbao['remarki'] ;

            }else{

                $qf_ck= $_COOKIE['qf'];
                if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

                $client= drFun::getClientV2();

                $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
                $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);


                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['url'] = '/api/B2Qr/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['ctime']= time();
            }

            $hongbao['auto']= 1 ;
            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );
            $this->htmlFile='app/ali90.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali60( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);



            if( $sign=='test' ) {

                if( $this->getLogin()->isUnipayYue() ){
                    //
                    $url= '/api/ali61/'. implode('/',$p );
                    $this->redirect( $url);
                }

                $price = isset($p[4])?$p[4]:'0.01';
                $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                if( $p[3] )   $hongbao['remarki'] = $p[3];
                $hongbao['amount'] =$price;// '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                $hongbao['j'] = $p[2];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/'.$price;//自己的登录账号
                $hongbao['ctime']= time();

                $trade_row['realprice']= $price*100;
                $trade_row['order_no']=  "[测试]".$hongbao['remarki'] ;
            }else{

                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

                $this->test1wan( $trade_row );

                $qf_ck= $_COOKIE['qf'];
                if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

                $client= drFun::getClientV2();

                $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];

                $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );



                $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['ctime']= time();

                $this->assign('god','god');

            }
            $hongbao['auto']= 1 ;

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );


            //$this->htmlFile='app/v38open_.phtml';
            $this->htmlFile='app/ali60.phtml';
            if( in_array( $trade_row['merchant_id'] ,[8201] )){
                $this->htmlFile='app/ali60v2.phtml';
            }
            if(  in_array( $trade_row['merchant_id'] ,[8399] )){
                $this->htmlFile='app/ali60_jd.phtml';
            }


        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }


    }


    function act_ali130( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);



                //$this->test1wan( $trade_row );
            }

            if( $sign=='test' ) {
                $qun= $this->getLogin()->createTableQun()->getRowByWhere( ['account_id'=> $p[1]] );
                $hongbao['remarki'] = 'T' . date("mdhis").rand(100,999);
                if( $p[3] )   $hongbao['remarki'] = $p[3];
                $hongbao['amount'] = '1.00';
                $hongbao['uid'] = $p[1];//自己的UID
                $hongbao['j'] = $p[2];//群ID
                //$hongbao['url'] = '/api/dingBill/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/0.01';//自己的登录账号
                $hongbao['url'] = '/api/weiboQr/test/'. $hongbao['remarki'].'/'. $hongbao['uid'].'/'. $qun['qid'];//自己的登录账号
                $hongbao['ctime']= time();

                $trade_row['realprice']='100';
                $trade_row['order_no']=  "[测试]".$hongbao['remarki'] ;
            }else{

                $qf_ck= $_COOKIE['qf'];
                if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

                $client= drFun::getClientV2();

                $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
                $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );



                $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=>  $client ] );


                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                //$hongbao['url'] = '/api/dingBill/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['url'] = '/api/uniPayQr/trade/'. $hongbao['remark'].'/'.  $account['ali_uid'];//自己的登录账号
                $hongbao['ctime']= time();

                $this->assign('god','god');

            }
            $hongbao['auto']= 1 ;

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  )->assign('trade', $trade_row );


            //$this->htmlFile='app/v38open_.phtml';
            $this->htmlFile='app/ali130.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }


    }

    function act_v39open( $p ){ // /api/v39open/test/4175470692

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsingLimit();

                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            }

            if( $sign=='test' ) {
                $hongbao['remark'] = '19' . date("mdhis") ;
                $hongbao['amount'] = '0.01';
                $hongbao['uid'] = $p[1];//自己的UID
                //$hongbao['account'] = $p[2];//自己的登录账号
                $hongbao['url'] = '/api/taoBill/test/'. $hongbao['remark'].'/'. $hongbao['uid'].'/1';//自己的登录账号
                $hongbao['ctime']= time();
                $hongbao['auto']= intval($_GET['auto']);

            }else{
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

                $client = drFun::getClientV2();

                $qf_ck= $_COOKIE['qf'];
                if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
                drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

                $var_b=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
                $this->checkHei( $trade_row, $qf_ck ,['up'=>$var_b ]);

                //$upData = ['ali_uid'=>$account['ali_uid'] ,'type'=>4] ;
                $upData = ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client];
                if( !$is_ali ){
                    //$upData['ip']= drFun::getIP();
                    //$upData['client']=  drFun::getClientV2()  ;
                }

                $this->getLogin()->createQrPay()->upTradeByID($id, $upData);
                $hongbao['remark'] = $trade_row['trade_id'];
                $hongbao['amount'] = $trade_row['realprice']/100 ;
                $hongbao['uid'] = $account['ali_uid'];//自己的UID
                $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                $hongbao['url'] = '/api/taoBill/trade/'. $hongbao['remark'].'/'.  $account['uid'];//自己的登录账号
                $hongbao['ctime']= time();
                $hongbao['auto']= intval($p[3]);
            }

            $this->assign('hongbao',$hongbao )->assign( 'client',drFun::getClientV2()  );


            $this->htmlFile='app/v39open.phtml';
            if($_GET['v']==2  || !$is_ali ){
                $this->htmlFile='app/v39open_v2.phtml';
            }

            $this->htmlFile='app/v39open_v3.phtml';

            //$this->drExit("good news" );



        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }


    }

    function redirectV2( $url ){

        $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
        if( !$is_ali ||  $this->isIphone()){
            $this->redirect( $url );
            return ;
        }

        $str='<script>alert("good news"); ap.redirectTo({url: "'.$url.'",data: {} });</script>';
        echo $str;
        parent::drExit();

    }

    function act_v37open( $p){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            if(  $sign !='test' ) {
                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            }




            if ($_GET['u'] && $_GET['s']) {


                $ali_uid = intval($_GET['u']);
                $checkSign = md5($ali_uid . "dmd@dd454A%.dada" . $p[count($p) - 2]);

                if ($checkSign != $_GET['s']) $this->throw_exception("认证失败！");

                if( $sign=='test' ) {
                    $hongbao['i'] = 'T' . date("mdhis").rand(100,999);
                    $hongbao['amount'] = '0.01';
                    $hongbao['a'] = $p[1];//自己的UID
                    $hongbao['j'] = $p[2];//自己的登录账号
                    $groupId = '0315290000020190301002610334';
                    $hongbao['url'] = '/api/getBill/37test/'. $hongbao['i'].'/'.  $hongbao['a'] .'/'.$groupId.'/0.01';//自己的登录账号
                    $hongbao['ctime']= time();
                }else{

                    $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$ali_uid ,'type'=>3] );
                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    $hongbao['i'] = $trade_row['trade_id'];
                    $hongbao['amount'] = $trade_row['realprice']/100 ;
                    $hongbao['a'] = $account['ali_uid'];//自己的UID
                    $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
                    //$groupId = '0315290000020190301002610334';
                    $hongbao['url'] = '/api/getBill/37trade/'. $hongbao['i'];//.'/'. $groupId ;//自己的登录账号
                    $hongbao['ctime']= time();

                }

                /*if ($_GET['u'] && $_GET['s']) {*/
                //$this->drExit($_GET);


                //header('Location: '. $url );
                $this->assign('url',$url )->assign('hongbao',$hongbao )->assign('isNoAddF', 1 );
                $this->htmlFile='app/v36open.phtml';
                //parent::drExit();//释放资源

            } elseif ($_GET['auth_code']) {
                $redirect_uri = '/api/v37open/' . implode('/', $p);
                $url = 'https://qz.becunion.com/lab/alipay/oauth/?auth_code=' . $_GET['auth_code'] . '&redirect_uri=' . urlencode($redirect_uri);
                $this->redirect($url);
            } else {
                $c_file = dirname(dirname(dirname(__FILE__))) . "/webroot/lab/alipay/config.php";
                require_once $c_file;
                $redirect_uri = 'https://qf.zahei.com/api/v37open/' . implode('/', $p);
                $url = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=' . $config['app_id'] . '&scope=auth_base&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $p[1];
                //die($url);
                //header('Location: '. $url );
                $this->redirect($url);
            }
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }
    function act_dingBill( $p ){
        $this->setDisplay('json');
        $trade = new trade();
        $re=['url'=>'error','msg'=>'参数错误！'];
        switch ($p[0]){
            case 'test': #/dingBill/test/remark/收款人uid/钱
                $re=  $trade->getDingBillByMark($p[1] );
                if(!$re['url'] ){
                    drFun::createDingBill( $p[2] ,$p[3],$p[1]);
                }
                break;
            case 'trade': #/getBill/trade/trade_id
                $re = $trade->getDingBillByMark($p[1] );
                if(!$re['url'] ){
                    $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    drFun::createDingBill($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1]);
                }
                break;

        }
        $this->assign('re', $re );
    }

    function act_dingBillV2( $p ){

        $this->setDisplay('json');
        $trade = new trade();
        $re=['url'=>'error','msg'=>'参数错误！'];
        switch ($p[0]){
            case 'test': #/dingBill/test/remark/收款人uid/钱
                $re=  $trade->getDingBillByMark($p[1] );
                if(!$re['url'] ){
                    $this->createDingBillV2( $p[2] ,$p[3],$p[1]);
                }
                break;
            case 'trade': #/getBill/trade/trade_id
                $re = $trade->getDingBillByMark($p[1] );
                if(!$re['url'] ){
                    $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    $this->createDingBillV2($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1]);
                }
                break;

        }
        $this->assign('re', $re );
    }

    function act_B2JD( $p ){
        $this->setDisplay('json');
        $re=['url'=>'error','msg'=>'参数错误！'];
        $trade = new trade();
        switch ($p[0]) {
            case 'test': #/uniPayQr/test/remark/收款人uid/钱
                $re = $trade->getDingBillByMark($p[1]);
                $ali_uid = $p[2];
                if (!$re['url']) {
                    drFun::createB2JDQr($p[2], $p[3], $p[1], trim($_POST['bn'])  );
                } else {
                    //drFun::updateB2Alipay( $ali_uid,   $re['tradeNo']  );
                }
                break;
            case 'trade':
                $re=  $trade->getDingBillByMark($p[1] );
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $ali_uid =  $account['ali_uid'] ;
                if( !$re['url']  ){
                    drFun::createB2JDQr($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1], trim($_POST['bn'])   );
                    if(  in_array($trade_row['type'],[4] ) ) $this->getLogin()->createQrPay()->upTradeByID(   $p[1] ,['type'=>5]);
                }else{
                    if( 'error'!= $re['url'] &&  $re['url']!='' && in_array($trade_row['type'],[4,5] ) ) $this->getLogin()->createQrPay()->upTradeByID(   $p[1] ,['type'=>3]);
                }
                break;

        }
        $this->assign('re', $re );
    }

    function act_B2Qr($p){
        $this->setDisplay('json');
        $this->assign('post',$_POST);
        $re=['url'=>'error','msg'=>'参数错误！'];
        $trade = new trade();
        switch ($p[0]){
            case 'test': #/uniPayQr/test/remark/收款人uid/钱
                $re=  $trade->getDingBillByMark($p[1] );
                $ali_uid = $p[2] ;
                if( !$re['url']   ){
                    drFun::createB2AlipayQr( $p[2],$p[3],  $p[1] , trim($_POST['bank']) , trim( $_POST['type']));
                }else{
                    //drFun::updateB2Alipay( $ali_uid,   $re['tradeNo']  );
                }
                break;
            case 'trade':
                $re=  $trade->getDingBillByMark($p[1] );
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $ali_uid =  $account['ali_uid'] ;
                if( !$re['url']  ){
                    drFun::createB2AlipayQr($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1], trim($_POST['bank']) , trim( $_POST['type']) );
                    if(  in_array($trade_row['type'],[4] ) ) $this->getLogin()->createQrPay()->upTradeByID(   $p[1] ,['type'=>5]);
                }else{
                    if( 'error'!= $re['url'] &&  $re['url']!='' && in_array($trade_row['type'],[4,5] ) ) $this->getLogin()->createQrPay()->upTradeByID(   $p[1] ,['type'=>3]);
                }
                break;
        }
        $this->assign('re', $re );
    }

    function act_weiboQr( $p ){
        $this->setDisplay('json');
        $trade = new trade();
        switch ($p[0]) {
            case 'test':
                $re=  $trade->getDingBillByMark($p[1] );

                if( !$re['url']   ){
                    $account_id = $p[2];
                    $qun= $this->getLogin()->createTableQun()->getRowByKey( $p[3] );
                    $this->getLogin()->createWeiboServer( $account_id)->createBill( '1', $p[1],$qun['chatroom']);
                }

                break;
        }
        $this->assign('re', $re );
    }

    function act_uniPayQr( $p ){

        $this->setDisplay('json');
        $trade = new trade();
        $re=['url'=>'error','msg'=>'参数错误！'];
        $dt = intval($_GET['dt']);
        $is_go=  1 ;//$dt==0|| $dt%2==1;
        $ali_uid='';

        switch ($p[0]){
            case 'test65':
                $re=  $trade->getDingBillByMark($p[1] );
                $ali_uid = $p[2] ;
                if( !$re['url'] && $is_go ){
                    //$this->createDingBillV2( $p[2] ,$p[3],$p[1]);
                    drFun::createPingAnQr( $p[2],$p[3],  $p[1]);
                }
                break;
            case 'test': #/uniPayQr/test/remark/收款人uid/钱
                $re=  $trade->getDingBillByMark($p[1] );
                $ali_uid = $p[2] ;
                if( !$re['url'] && $is_go ){
                    //$this->createDingBillV2( $p[2] ,$p[3],$p[1]);
                   drFun::createUniQr( $p[2],$p[3],  $p[1]);
                }
                break;

            case 'trade65':

                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                $where=['fee'=>$trade_row['realprice'],'account_id'=>$trade_row['account_id']   ];
                $re = $trade->getPayLogTemByWhere( $where ,['remark'=> $p[1] ] ); //$opt['remark']

                $this->test1wan( $trade_row );

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $ali_uid =  $account['ali_uid'] ;
                if( !$re['url'] && $is_go ){
                    drFun::createPingAnQr($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1]);
                }else{
                    // $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$ali_uid ,'type'=>3] );
                    if( 'error'!= $re['url'] &&  $re['url']!='' && $trade_row['type']==4  ) $this->getLogin()->createQrPay()->upTradeByID(   $p[1] ,['type'=>3]);
                }
                break;

            case 'trade': #/uniPayQr/trade/trade_id
                $re = $trade->getDingBillByMark($p[1] );
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );

                $this->test1wan( $trade_row );

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $ali_uid =  $account['ali_uid'] ;
                if( !$re['url'] && $is_go ){
                    if($p[0]=='trade65' ) drFun::createPingAnQr($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1]);
                    else drFun::createUniQr($account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1]);
                }else{
                    // $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$ali_uid ,'type'=>3] );
                    if( 'error'!= $re['url'] &&  $re['url']!='' && $trade_row['type']==4  ) $this->getLogin()->createQrPay()->upTradeByID(   $p[1] ,['type'=>3]);
                }
                break;

        }
        //updateUniQr
        if( $re['url'] &&  $ali_uid ){
            drFun::updateUniQr( $ali_uid );
        }
        $this->assign('re', $re );
    }

    function createDingBillV2( $ali_uid, $realprice, $remark){
        $where=['account_ali_uid'=>$ali_uid,'ali_beizhu'=>$remark ,'fee'=>drFun::yuan2fen($realprice)];
        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere($where );
        if( !$row ){
            $where['type']= -78;
            $this->getLogin()->createTablePayLogTem()->append($where);
        }

        $account= $this->getLogin()->createQrPay()->getAccountByAliUid( $ali_uid );
        $where = ['user_id'=>$account['user_id'] ,'type'=>38];
        $wh['>=']= ['clienttime'=> ( time()- 1800 ) ];
        $acc = $this->getLogin()->createQrPay()->getAccountIDByWhere($where ,['all'=>1]);
        //unset( $acc[ $ali_uid ] );
        //$this->drExit(  $acc );
        if( count($acc)<=1) $this->throw_exception("至少使用2个钉钉号！");
        $f_ali_uid=[];
        foreach($acc as $v ) {
            if( $v['ali_uid']== $ali_uid ) continue;
            $f_ali_uid[]= $v['ali_uid'];
        }
        $acc_ali_uid= $f_ali_uid[ rand(0, count($f_ali_uid)-1)];
        drFun::createDingBillV2($account['ali_uid'] ,$realprice.'',$remark, $acc_ali_uid);
    }

    function act_taoBill( $p ){
        $this->setDisplay('json');
        $trade = new trade();
        $re=['url'=>'error','msg'=>'参数错误！'];
        switch ($p[0]) {
            case 'test': #/dingBill/test/remark/收款人uid/钱
                $re = $trade->getDingBillByMark($p[1]);
                if (!$re['url']) {
                    drFun::createTaoBill($p[2], $p[3], $p[1]);
                }
                break;
            case 'trade':
                $re = $trade->getDingBillByMark($p[1] );
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                if(!$re['url'] ){
                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    drFun::createTaoBill( $account['ali_uid'] ,($trade_row['realprice']).'',$p[1]);
                }elseif( $re['url']!='error'){
                   if(  in_array( $trade_row['type'],[0,4]) ) {
                       $this->getLogin()->createQrPay()->upTradeByID($p[1], ['type' => 3]);
                   }
                }
                break;
        }
        $this->assign('re', $re );

    }

    function act_aliBill( $p ){
        $this->setDisplay('json');
        $trade = new trade();
        $re=['url'=>'error','msg'=>'参数错误！'];
        switch ($p[0]){
            case 'trade':
                $re = $trade->getDingBillByMark($p[1] );
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                $key= 'qr_'.$trade_row['account_id'];
                $re2= false;
                if( $trade_row['user_id']==4){}

                $re2= $trade->getAliBillFromCache( $trade_row['account_id'],  $trade_row['realprice']);

                if( $re2){
                    $re= $re2;
                    if( $trade_row['type']==4){
                        $this->getLogin()->createQrPay()->upTradeByID($p[1], ['type' =>3]);
                    }
                }elseif( $this->getLogin()->redisGet( $key)){
                    $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
                    $re['url']=$qr['qr_text'];
                    $re['tradeNo']='yes';
                    if( $trade_row['type']==4){
                        $this->getLogin()->createQrPay()->upTradeByID($p[1], ['type' =>3]);
                    }
                }elseif( isset($re['data']['success']) && $re['data']['success']===false ){
                    $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
                    $re['url']=$qr['qr_text'];
                    $this->getLogin()->redisSet( $key, time(),600 );
                }elseif(!$re['url'] && intval($_GET['cnt'])%10==1){
                    //$trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    drFun::createAliQr( $account['ali_uid'] ,($trade_row['realprice']/100).'',$p[1]);
                }

                break;
        }
        $this->assign('re', $re );
    }

    function act_wangBill( $p ){
        $this->setDisplay('json');
        $trade = new trade();
        $re=['url'=>'error','msg'=>'参数错误！'];

        switch ($p[0]) {
            case 'test': #/dingBill/test/remark/收款人uid/钱
                $re = $trade->getDingBillByMark($p[1]);
                if (!$re['url']) {
                    drFun::createWangBill($p[2], $p[3], $p[1]);
                }
                break;
            case 'trade':
                $re = $trade->getDingBillByMark($p[1] );
                if(!$re['url'] ){
                    $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                    $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                    drFun::createWangBill( $account['ali_uid'] ,($trade_row['realprice']).'',$p[1]);
                }
                break;
        }
        $this->assign('re', $re );

    }
    function act_q351($p ){
        $this->setDisplay('json');
        switch ($p[0]){
            case 'code':
                $trade_id = trim($p[1]);
                $code = trim($p[2]);
                $trade = $this->getLogin()->createQrPay()->getTradeByID( $trade_id );
                if( $trade['type']!=4) $this->throw_exception("正在验证或者已验证成功！");
                $account= $this->getLogin()->createQrPay()->getAccountByID( $trade['account_id'] );
                //$this->assign('acc',$account );
                $this->getLogin()->createQrPay()->upTradeByID($trade_id, ['type' => 3 ]);
                drFun::createKoulin( $account['ali_uid'] , $code, $trade_id  );
                break;
            case 'query':
                $trade_id = trim($p[1]);
                $trade = $this->getLogin()->createQrPay()->getTradeByID( $trade_id );
                $this->assign('trade', $trade );

                $payLog = $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_beizhu'=>$trade_id ] );
                if( $payLog ){
                    $data= drFun::json_decode( $payLog['data'] );
                    if( strpos($data['data'], '输入错误') ) {
                        $key= 'ks_'.$trade['cookie'];
                        $this->getLogin()->createCache()->getRedis()->set($key, $trade_id,300);
                        $this->throw_exception("口令错误，请5分钟后再下单",119 );
                    }
                    else $this->throw_exception("支付发生异常请重新下单！",119 );
                }

                break;
            case 'codeTest':
                $ali_uid = trim($p[1]);
                $code = trim($p[2]);
                drFun::createKoulin(  $ali_uid , $code, "online"  );
                break;

            case 'queryTest':
                $this->throw_exception("支付后再关闭过1分钟后会自动上线！",119  );
                break;
        }
    }
    function act_dingQuery($p){
        $this->setDisplay('json');
        if(  $p[0] )  drFun::queryDing($p[0]);
    }
    function act_getWxqr($p ){
        $this->setDisplay('json');
        $trade = new trade();

        switch ($p[0]){
            case 'test': #/getWxqr/test/remark/收款人uid/钱
                $re = $trade->getBillByMark($p[1] );
                if($re['url']==''){
                    drFun::createWxqr( $p[2], $p[3], $p[1]);
                    //$this->createBill($p[2],$p[3],$p[4],$p[1] );
                }
                break;
            case 'trade':
                $re = $trade->getBillByMark($p[1] );
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                if($re['url']==''){
                    drFun::createWxqr($account['ali_uid'],  ($trade_row['realprice']/100).'',  $trade_row['trade_id']);
                }
                break;
        }
        $this->assign('re', $re );
    }

    function act_getBill($p){
        $this->setDisplay('json');
        $trade = new trade();
        switch ($p[0]){
            case '37test':#/getBill/test/remark/收款人uid/groupid/钱
                $re = $trade->getBillByMark($p[1] );
                if($re['url']==''){
                    $this->activeShou( $p[2] ,$p[3],$p[4],$p[1]  );
                }
                break;
            case 'test': #/getBill/test/remark/收款人uid/付款人uid/钱
                $re = $trade->getBillByMark($p[1] );
                if($re['url']==''){
                    //drFun::createBill($p[2],$p[3],$p[4],$p[1] );
                    $this->createBill($p[2],$p[3],$p[4],$p[1] );
                }else{
                    drFun::delFriend($p[2], $p[3]);
                }
                break;
            case 'trade': #/getBill/trade/trade_id
                $re = $trade->getBillByMark($p[1] );

                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                if($trade_row['ali_uid']!=$p[2]  ) $this->throw_exception("这似乎不是你的订单！");
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                if($re['url']==''){
                    //drFun::createBill( $account['ali_uid'],$trade_row['ali_uid'] , ($trade_row['realprice']/100).'',$trade_row['trade_id']  );
                    $this->createBill( $account['ali_uid'],$trade_row['ali_uid'] , ($trade_row['realprice']/100).'',$trade_row['trade_id']  );
                }else{
                    drFun::delFriend(  $account['ali_uid'],$trade_row['ali_uid']);
                }

                break;
        }

        $this->assign('re', $re );
    }

    function createBill( $account_ali_id,$ali_uid, $money,$remark ){

        $msg='账单 '.$remark.' 点不开，可以向我转账'.$money."元" ;
        //
        //drFun::sendMsgAli($account_ali_id, $ali_uid,$msg);

        drFun::createBill(  $account_ali_id,$ali_uid, $money,$remark );
        return ;
        $key="MK_".$remark;
        $cache = new cache();
        $time =  $cache->getRedis()->get($key );
        if( $time ) return ;

        $cache->getRedis()->set($key , time(), 1 );
    }

    function activeShou( $account_ali_id,$group_id , $money,$remark ){
        $key="MK_".$remark;
        $cache = new cache();
        $time =  $cache->getRedis()->get($key );
        if( $time ) return ;
        drFun::activeShou(  $account_ali_id,$group_id, $money,$remark );
        $cache->getRedis()->set($key , time(), 1 );
    }

    function act_ali30( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            if( $trade_row['type'] >0 && !isset($_GET['no']) ) $this->throw_exception( "请勿重复支付！", 456 );
            $client= drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP(),'client'=>$client ] );

            $url= 'https://'.drFun::getHttpHost().'/api/v30open/'. implode('/',$p );

            $this->assign('url',$url )->assign('trade',$trade_row )->assign('qf', $qf_ck );

            $this->htmlFile='app/alishowV3.phtml';

            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url  ) ;
            //$url4= 'alipays://platformapi/startapp?appId=60000154&t='.time().'&url='. urlencode($url  ) ;

            $this->assign('url4', $url4 )->assign('trade',$trade_row )->assign('url',$url )->assign( 'client', $client  );
            $this->htmlFile='app/alishowV35t2.phtml';

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $this->assign('zhifu_name',$account['zhifu_name']  );
            $this->assign('auto',1);

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_v31open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if( !$is_ali ) $this->throw_exception("请在支付宝内使用",460 );

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng= $this->getLogin()->createQrPay()->getTypeTradeUsing();
            // (time()- $trade_row['ctime']) >180 ||
            if( !in_array(  $trade_row['type'],$usIng )  ) $this->throw_exception( "支付成功或已过期！", 459 );



            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] ); //'ali_uid'=>31 ,
            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            #转账
            $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&userId='.$account['ali_uid'];
            $url.='&amount='.($trade_row['realprice']/100) .'&money='.($trade_row['realprice']/100)    ;


            #扫码
            $bizData = ['a' => '', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
            $bizData = ['a' => ($trade_row['realprice']/100).'', 'c' => '', 's' => 'online', 'u' => $account['ali_uid'], 'm' => ''];
            $bizData = ['a' => ($trade_row['realprice']/100).'' , 's' => 'online', 'u' => $account['ali_uid']  ]; //, 'm' => ''
            //$url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode(json_encode($bizData)) ;// . '&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
            $url='alipayqr://platformapi/startapp?appId=20000123&actionType=scan&biz_data={\"s\": \"money\",\"u\": \"'. $account['ali_uid'].'\",\"a\": \"'.($trade_row['realprice']/100).'\"}';
               //'alipayqr://platformapi/startapp?appId=20000123&actionType=scan&biz_data={\"s\": \"money\",\"u\": \"2088432873395680\",\"a\": \"99.94\"}';
            $hongbao= [ ];
            $hongbao['i'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            $hongbao['a'] = $account['ali_uid'];//自己的UID
            $hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            $hongbao['url']= $url;

            $this->assign('hongbao',$hongbao);
            $this->assign('url',$url );
            $this->htmlFile='app/v30open.phtml';
            //parent::drExit();//释放资源

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali31( $p ){

        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            if( $trade_row['type'] >0 && !isset($_GET['no']) ) $this->throw_exception( "请勿重复支付！", 456 );
            $client= drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP(),'client'=>$client ] );

            $url= 'https://'.drFun::getHttpHost().'/api/v31open/'. implode('/',$p );

            $this->assign('url',$url )->assign('trade',$trade_row )->assign('qf', $qf_ck );

            $this->htmlFile='app/alishowV3.phtml';

            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url  ) ;
            $url4= 'alipays://platformapi/startapp?appId=20000067&t='.time().'&url='. urlencode($url  ) ;
            //alipays://platformapi/startapp?appId=20000067&url=
            //$url4= 'alipays://platformapi/startapp?appId=66666722&appClearTop=false&startMultApp=YES&url='. urlencode($url  ) ;
            //$url4= 'alipays://platformapi/startapp?appId=60000154&t='.time().'&url='. urlencode($url  ) ;
            $tbUrl=  'taobao://www.alipay.com/?appId=10000007&qrcode='. urlencode($url4 );


            $this->assign('url4', $url4 )->assign('trade',$trade_row )->assign('url',$url )->assign( 'client', 0  );
            //if( in_array( $trade_row['merchant_id'],[ 8080 ]  ) ) {
                //$this->assign('tb_url', $tbUrl);
           // }
            $this->htmlFile='app/alishowV35t2.phtml';

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $this->assign('zhifu_name',$account['zhifu_name']  );
            $this->assign('auto',1)->assign('is_notabo',1 );

            $this->htmlFile='app/alishowV4s31.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_v45open($p){
        $this->setDisplay('json');
        $sign= trim($p[0] );
        $id = $p[1];
        $md5 = $this->getPaySign($id);//md5($id.'adf888');
        if ($md5 != $sign) $this->throw_exception("非法支付！", 457);

        $buyer = trim($_GET['buyer']);
        if( !$buyer ) $this->throw_exception( "请填写存款人！", 459 );


        $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
        if( in_array( $trade_row['type'] , $this->getLogin()->createQrPay()->getTypeTradeSuccess() ) ){
            $this->assign('msg','已经到账')->assign('stats','11');
            return ;
            //$this->throw_exception("请勿重复确认！", 457);
        }
        //$this->assign('gt',$_GET);
        if(  $trade_row['buyer'] ){
            $this->getLogin()->createQrPay()->payMatchByTradeV45( $trade_row );
            $this->assign('msg', $this->matchTrade($trade_row)?'已经到账，请查收':'请等待1-3分钟到账！' )->assign('stats','2');
            return ;
        }

        $this->getLogin()->createQrPay()->upTradeByID( $id,['buyer'=>$buyer ]);
        $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
        $this->assign('msg',$this->matchTrade($trade_row)?'已经到账，请查收':'请等待1-3分钟到账！')->assign('stats','1');
    }

    function matchTrade( $trade_row ){
        try{
            $this->getLogin()->createQrPay()->payMatchByTradeV45( $trade_row );
        }catch (drException $ex ){
            return false;
        }
        return true;

    }

    function act_ali45( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            $usIng= $this->getLogin()->createQrPay()->getTypeTradeUsingLimit();
            // (time()- $trade_row['ctime']) >180 ||
            if( !in_array(  $trade_row['type'],$usIng )  ) $this->throw_exception("已经支付成功或者已经超时！", 456);

            // if ($trade_row['type'] > 0 && !isset($_GET['no']))

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $account= $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id'] );

            $bank2Type = $this->getLogin()->createQrPay()->getBankType();
            $bank= $bank2Type[ $account['bank_id'] ];

            $this->assign('trade', $trade_row)->assign('account', $account)
                ->assign('bank', $bank )->assign('url',  '/api/v45open/'. implode('/',$p ));

            $this->htmlFile='app/alishowV45.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }


    function getRankHost(){
        return 'https://qz.atbaidu.com';
        //return 'http://'.substr(md5(uniqid()),0,4).'.q41n.com';
        //return 'http://'.substr(md5(uniqid()),0,4).'.sllzs.cn';
        //return 'http://'.substr(md5(uniqid()),0,4).'.fusocq.cn';
        return 'http://'.substr(md5(uniqid()),0,4).'.crosscase.cn';
        return 'http://'.substr(md5(uniqid()),0,4).'.atbaidu.com';
    }

    function getHostByUid( $uid ){
        // substr( $this->getLogin()->getDomainByUid($account['user_id']) ,['*'=>])
        $hosts= $this->getLogin()->getDomainByUid( $uid ) ;//$account['user_id']

        return strtr( $hosts,['*'=> substr(md5(uniqid()),0,4) ]);

    }

    function test1wan( &$trade ){
        if( in_array( $trade['merchant_id'],[8371,8372] )){
            $trade['realprice']= $trade['price'];
        }
    }

    function act_ali40s($p){

        $sign= trim($p[0] );
        $id = $p[1];

        $this->htmlFile= 'app/ali40.phtml';
        try{


            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            if ( ! in_array( $trade_row['type'] , $this->getLogin()->createQrPay()->getTypeTradeUsingLimit() )  && !isset($_GET['no'])) $this->throw_exception("支付成功或者已超时！", 456);

            $limitTime = $trade_row['ctime']+300- time();
            if( $_GET['no'] ){
                $limitTime=200;
            }
            if( $limitTime <5) $this->throw_exception("已经超时",502);

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            $client= drFun::getClientV2();

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);



            if($trade_row['type']==0) {
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $this->getLogin()->createQrPay()->upTradeByID($id, ['ali_uid' => $account['ali_uid'], 'type' => 4, 'ip' => drFun::getIP(), 'cookie' => $qf_ck, 'client' => drFun::getClientV2()]);
            }

            if( in_array( $trade_row['merchant_id'],[8824, 8880,8974,8982] )){
                //$url='';
                $self_url = 'https://'.drFun::getHttpHost().'/api/ali40/'. implode('/',$p ).'?s=alipay';

                $this->redirect($self_url);
            }



        }catch (drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }



    function act_ali40( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');


        if($is_ali) {

            $client = drFun::getClientV2();
            if ($client != 0) {
                return $this->act_v40open($p);
            }
        }else{
            return $this->act_ali145($p);
        }


        try {
            $this->assign('_cdn', drFun::getCdn() );

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
            $usIng[]=0;

            $limit_time= ($trade_row['ctime']+300)-time() ;

            if ( ( !in_array($trade_row['type'], $usIng) || $limit_time<=10 )  && !isset($_GET['no']) )  $this->throw_exception( "支付成功或者已经过期！", 456 );
            //if( $trade_row['type']>0 && !isset($_GET['no'])  )    $this->throw_exception( "支付成功或者已经过期！", 456 );


            $self_url = 'https://'.drFun::getHttpHost().'/api/v40open/'. implode('/',$p );

            $this->test1wan( $trade_row );


            #if( $client<=0) $client=1;


            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            $upVar = ['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP(),'client'=> $client ];
            if( $trade_row['type']>0 ) unset( $upVar['type']);
            $this->getLogin()->createQrPay()->upTradeByID($id, $upVar);





            /*

            $url= 'https://'.drFun::getHttpHost().'/api/v40open/'. implode('/',$p );
            $url_tb= 'https://'.drFun::getHttpHost().'/api/v40open/'. implode('/',$p ).'?tb=1';
*/

            $account= $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id'] );


            $url=  $this->getHostByUid( $account['user_id'] ) .'/api/v40open/'. implode('/',$p );
            $url_tb=   $this->getHostByUid( $account['user_id'] ) .'/api/v40open/'. implode('/',$p ).'?tb=1';
            $url_plus=   $this->getHostByUid( $account['user_id'] ) .'/api/v40open/'. implode('/',$p ).'?tb=plus';

            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4='alipays://platformapi/startApp?appId=10000011&url='.urlencode($url2 );

            //$url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url .'/1') ;
            $url4= 'alipays://platformapi/startapp?appId=20000067&url='. urlencode($url .'/3') ;
            //ali40
            $url4= 'alipays://platformapi/startapp?saId=20000199&url='. urlencode($url .'/3') ;
            //$url4= 'alipays://platformapi/startapp?appId=60000029&showLoading=YES&url='. urlencode($url .'/3') ;

            //alipays://platformapi/startapp?appId=20000067&url=
            //alipays://platformapi/startapp?appId=20000067&url=http://api.jinnpay188.com/koudpay/rest/zzkSweepbank?orderNo=20190729153709101872&t=20190729153709
            
	    $this->assign('url',$url )->assign('trade',$trade_row )->assign('qf', $qf_ck )
                ->assign('tb_url',"alipays://platformapi/startapp?appId=20000067&url=". urlencode($url_tb) );

            $bank2Type = $this->getLogin()->createQrPay()->getBankType();
            $bank= $bank2Type[ $account['bank_id'] ];
            $url3='alipays://platformapi/startapp?appId=09999988&actionType=toCard&goBack=NO&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.urlencode( $account['zhifu_account']).'&bankAccount='.urlencode( $account['zhifu_name'])."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']);
            //
            $url2= 'https://ds.alipay.com/?from=pc&appId=09999988&actionType=toCard&sourceId=bill&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100);
            $url2.= '&cardNo='.urlencode(  $account['zhifu_account']).'&bankAccount='.urlencode( $account['zhifu_name'])."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']);

            //$this->assign('url2', $url3 )->assign('url3',$url3 )->assign('url', $url3 );
            if( $trade_row['user_id']==1185 ){ #C212
                if( $client>0  ) $client= - $client;
            }
            $this->assign('url2', $url )->assign('url3',$url3 )->assign('url', $url )
                ->assign('url4',$url4 )->assign('client',$client )->assign('account',$account);
            //$this->assign('hostsss',  $this->getHostByUid( $account['user_id'] ));



            //$account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
            $bank2Type = $this->getLogin()->createQrPay()->getBankType();
            $bank= $bank2Type[ $account['bank_id'] ];

            $url5='alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=ZHUANZHUANG&money='.($trade_row['realprice']/100).'&amount='.($trade_row['realprice']/100).'&cardNo='.$this->getCardNo( $account) .'&bankAccount='.urlencode( trim( $account['zhifu_name']))."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']);
            $this->assign('url5', $url5 );

            //if( 8080== $trade_row['merchant_id']){
            if( in_array( $trade_row['merchant_id'],[ 8080 ]  ) ){ //8080,8223,8222
                if( $this->isIphone()){
                    $url='alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=ZHUANZHUANG&cardNo='.trim($account['zhifu_account']).'&bankMark='.urlencode( $bank['c']).'&bankAccount='.urlencode( trim( $account['zhifu_name'])).'&amount='.($trade_row['realprice']/100);
                }else{
                    $url = 'alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=ZHUANZHUANG&cardNo='.trim($account['zhifu_account']).'&bankMark='.urlencode( $bank['c']).'&receiverName='.urlencode( trim( $account['zhifu_name'])).'&money='.($trade_row['realprice']/100);
                }
                $this->assign('url', $url );
            }
            //

            //$this->htmlFile='app/alishowV4.phtml';


            if( $this->isIphone() ){
                //$this->assign('ios',1 );

            }
            //$this->htmlFile='app/alishowV4ios.phtml';
            $this->htmlFile='app/alishowV4s.phtml';

            $qr_url=$url;
            $this->assign('qr_url',$qr_url);

            $bank2Type = $this->getLogin()->createQrPay()->getBankType();
            $bank= $bank2Type[ $account['bank_id'] ];

            $this->assign('bank', $bank);
            if( 8256 == $trade_row['merchant_id']  ){
                $this->htmlFile='app/alishowV4s2.phtml';
                //if( $this->isIphone() ) $this->htmlFile='app/alishowV4s3.phtml';

            }else{

                /*
                if( $client && 8252 == $trade_row['merchant_id'] ) {
                    $qr_url.='/wait';
                    $client=0;
                    $this->assign('wait',1);
                }
                */

                $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url )->assign( 'client', $client  );
                //$this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url4 )->assign( 'client', $client  );
                $this->assign( 'qr_url',$qr_url );
                $this->htmlFile='app/alishowV35t2.phtml';
                $this->htmlFile='app/alishowV4s.phtml';
                if( 8252 == $trade_row['merchant_id'] ) {
                    // $this->htmlFile='app/alishowV32.phtml';
                }
                //if( $this->isIphone() ) $this->htmlFile='app/alishowV4s3.phtml';
            }


            $this->htmlFile='app/alishowV4s7.phtml';

            $this->htmlFile='app/alishowV4s8.phtml';

            $this->htmlFile='app/alishowV4s9.phtml';//支付宝转卡 复制 copy

            if( $_GET['ds']==2 ){
                //微信
                $this->htmlFile='app/alishowV4s9wx.phtml';
            }
            //if($_GET['ds']==3 || in_array( $trade_row['user_id'],[4,3125,3190] )){
                //插件
                $this->htmlFile='app/alishowV4s9plus.phtml';
            //}

            //$this->htmlFile='app/alishowV4s9.phtml';


            $this->assign('limit_time',$limit_time )->assign('url_plus', $url_plus );


            $this->assign('url4',$self_url );
            $this->htmlFile='app/alishowV4s8.phtml';



        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali3( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        try{

            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
            //if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );
            //$usIng= $this->getLogin()->createQrPay()->getTypeTradeUsing();

            if( $trade_row['type'] >0 && !isset($_GET['no']) ) $this->throw_exception( "请勿重复支付！", 456 );

            //if( (time()- $trade_row['ctime']) >90 ||  !in_array(  $trade_row['type'],$usIng )  ) $this->throw_exception( "支付成功或已过期！", 459 );

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            //$this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4 ] );
            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP(),'client'=>drFun::getClientV2() ] );

            $url2= 'https://'.drFun::getHttpHost().'/api/url/'. implode('/',$p );
            //$url2= 'http://'.drFun::getHttpHost().'/api/url/'. implode('/',$p );

            //$url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode($qr['qr_text'] );
            $url='alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($url2 );


            if($_GET['abc']) die( $url );
            #header('Location: '.$qr['qr_text']);
            #有些手机浏览器 不支持 header location
            $is_show= strpos( $_SERVER['HTTP_USER_AGENT'], 'OppoBrowser') ||  strpos( $_SERVER['HTTP_USER_AGENT'], 'VivoBrowser');

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");

            $url = $qr['qr_text'];
            $arr2 = explode('?', $qr['qr_text'] );
            //$url="https://p.ancall.cn/api/goto?url=". urlencode( $qr['qr_text']  );
            //$url="https://p.ancall.cn/api/goto?url=". urlencode( $arr2[0].'?t='. time()  );
            //$url="https://tongji.zahei.com/api/goto?url=". urlencode( $arr2[0].'?t='. time()  );
            $url="https://qz.q41n.com/api/goto?url=". urlencode( $arr2[0].'?t='. time()  );
            //$url="https://qz.q41n.com/api/goto?url=". urlencode( $arr2[0].'?t='. time()  );

            //$url= 'alipays://platformapi/startApp?appId=20000067&url='. urlencode(  $arr2[0].'?t='. time() );
            $url= $arr2[0].'?t='. time();
            $this->assign('url',$url )->assign('trade',$trade_row )->assign('qf', $qf_ck );
            //$this->drExit( $url );
            //$this->htmlFile='app/alishowV2.phtml';
            $this->htmlFile='app/alishowV3.phtml';

            //if( in_array( $trade_row['merchant_id'],[8201,8200]) ){
            if( $this->getLogin()->isKC( $trade_row['merchant_id'] ) ){

                $key='TR'.  $trade_row['trade_id'];
                $cache = new cache();
                $user_name = $cache->getRedis()->get($key);
                if( $user_name ){
                    $this->getLogin()->createQrPay()->addCookieNamePL( $qf_ck, explode("\n", $user_name));
                }
            }

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali2( $p ){


        $sign= trim($p[0] );
        $id = $p[1];

        try{

            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
            //if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );
            //$usIng= $this->getLogin()->createQrPay()->getTypeTradeUsing();

            if( $trade_row['type'] >0 && !isset($_GET['no']) ) $this->throw_exception( "请勿重复支付！", 456 );

            //if( (time()- $trade_row['ctime']) >90 ||  !in_array(  $trade_row['type'],$usIng )  ) $this->throw_exception( "支付成功或已过期！", 459 );

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            //$this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4 ] );
            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP() ] );

            $url2= 'https://'.drFun::getHttpHost().'/api/url2/'. implode('/',$p );
            //$url2= 'http://'.drFun::getHttpHost().'/api/url/'. implode('/',$p );

            //$url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode($qr['qr_text'] );
            $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode( $url2  );
            //$url='alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($url2 );

            //$qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );

            //if( in_array( $qr['user_id']  ,[123, 12 ]  ) || $_GET['no']==1 ) {
                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.($trade_row['realprice']/100).'&userId='.$account['ali_uid'];
                $url.='&memo='. urlencode( $id. '-' .$account['account'] .'-'.$account['zhifu_name'] ) ;//$account['account'] ;
            //}


            if($_GET['abc']) die( $url );
            #header('Location: '.$qr['qr_text']);
            #有些手机浏览器 不支持 header location
            $is_show= strpos( $_SERVER['HTTP_USER_AGENT'], 'OppoBrowser') ||  strpos( $_SERVER['HTTP_USER_AGENT'], 'VivoBrowser');



            $this->assign('url',$url )->assign('trade',$trade_row );
            //$this->drExit( $url );
            //$this->htmlFile='app/alishowV2.phtml';
            $this->htmlFile='app/alishow.phtml';




        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali4( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        try{

            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );

            if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP(),'client'=>drFun::getClientV2() ] );

            //$url2= 'https://'.drFun::getHttpHost().'/api/url4/'. implode('/',$p ); //https://qz.q41n.com
            $url2= 'https://qz.q41n.com/api/url4/'. implode('/',$p );
            //$url2= 'https://qz.yagdsj.com/api/url4/'. implode('/',$p );
            //$qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            //$url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode($qr['qr_text'] );
            $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode( $url2 );
            #$url='alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($url2 );
            //$url='alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode( $qr['qr_text'] .'?_s=web-other' );


            /*
            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  ); // ($id. '-' .$account['account'])
            $arr= ['a'=> ($trade_row['realprice']/100).'','c'=>'','s'=>'online','u'=>$account['ali_uid'],'m'=> ($id. '-' .$account['account'])   ];//'{"a":"0.01","c":"","s":"online","u":"2088232932547186","m":"10137463410072"}';
            $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='.urlencode(json_encode($arr) ) .'&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
*/

            $this->assign('url',$url );
            $this->htmlFile='app/alishow.phtml';
        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        try{

            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );

            if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            //$this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4 ] );
            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>4,'cookie'=>$qf_ck,'ip'=>drFun::getIP() ] );

            $url2= 'https://'.drFun::getHttpHost().'/api/url/'. implode('/',$p );
            //$url2= 'http://'.drFun::getHttpHost().'/api/url/'. implode('/',$p );
            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode($qr['qr_text'] );
            #$url='alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($url2 );
            $url='alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode( $qr['qr_text'] .'?_s=web-other' );


	     if($_GET['abc']) die( $url );
            #header('Location: '.$qr['qr_text']);
            #有些手机浏览器 不支持 header location
            $is_show= strpos( $_SERVER['HTTP_USER_AGENT'], 'OppoBrowser') ||  strpos( $_SERVER['HTTP_USER_AGENT'], 'VivoBrowser');

//            if( $is_show) {
//
//            }else{
//                header('Location: ' . $url);
//                parent::drExit();//释放资源
//            }

            $this->assign('url',$url );
            $this->htmlFile='app/alishow.phtml';




        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }
    function act_url2( $p ){

        $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
        if( !$is_ali ){
            $p['error']='no in alipay';
            $p['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ;
            $this->logErr(   $p  );
            $this->redirect( '/api/no','' );
        }
        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
            //$this->drExit( $trade_row ); //|| trade_row['type'] <=0 || $trade_row['type']==3
            if(! ($trade_row['type']==4  ) ){
                $this->throw_exception( "请勿重复支付！" , 888 );
            }
            //$this->drExit( $trade_row );
            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>3] );
            //if( $trade_row['type'] >0 ) $this->throw_exception( "请勿重复支付！", 456 );

            //$qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            //if(  !$qr ) $this->throw_exception( "非法支付！");

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
            $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.($trade_row['realprice']/100).'&userId='.$account['ali_uid'];
            //$url.='&memo='. urldecode( $id. '-' .$account['account']  ) ;//$account['account'] ;
            $url.='&memo='. urlencode( $id. '-' .$account['account'] .'-'.$account['zhifu_name'] ) ;
            header('Location: '. $url );

            parent::drExit();//释放资源
        }catch (drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
        parent::drExit();//释放资源
    }

    function act_url4( $p ){
        //$this->drExit('god' );;

        $sign= trim($p[0] );
        $id = $p[1];
        try{





            $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if( !$is_ali ){
                $p['error']='no in alipay';
                $p['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ;
                $this->logErr(   $p  );
                //$this->redirect( '/api/no','' );
                $this->throw_exception( "请勿重复支付！" , 4444 );
            }


            $md5= $this->getPaySign($id);//md5($id.'adf888');
            if($md5!=$sign ) $this->throw_exception( "非法支付！", 457 );

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );



            if(! in_array($trade_row['type'], $this->getLogin()->createQrPay()->getTypeTradeUsing() )   ){
                $this->throw_exception( "请勿重复支付！" , 888 );
            }

            $this->getLogin()->createQrPay()->upTradeByID($id,['type'=>3, 'client'=>drFun::getClientV2() ] );

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
            $arr= ['a'=> ($trade_row['realprice']/100).'','c'=>'','s'=>'online','u'=>$account['ali_uid'],'m'=> ($id. '-' .$account['account'])    ]; //

            //$url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.($trade_row['realprice']/100).'&userId='.$account['ali_uid'];
            //$url.='&memo='. urlencode( $id. '-' .$account['account'] .'-'.$account['zhifu_name'] ) ;

            /*
            $arr= ['a'=> ($trade_row['realprice']/100).'','c'=>'','s'=>'online','u'=>$account['ali_uid'],'m'=> ($id. '-' .$account['account'])    ];//'{"a":"0.01","c":"","s":"online","u":"2088232932547186","m":"10137463410072"}';

            $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='.urlencode(json_encode($arr) ) .'&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
            //$this->drExit($url);
            //$url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=%7B%22a%22%3A%220.01%22%2C%22c%22%3A%22%22%2C%22s%22%3A%22online%22%2C%22u%22%3A%222088232932547186%22%2C%22m%22%3A%2210137463410072%22%7D&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
            header('Location: '. $url );

            parent::drExit();//释放资源
            */
            //$this->drExit('ddd2d');
            $this->assign('biz_data',$arr);

            if( $this->isIphone() ){
                $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='.urlencode(json_encode($arr) ) .'&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
                header('Location: '. $url);
                $this->drExit();
            }

            $url3= 'https://'.drFun::getHttpHost(). $_SERVER['REQUEST_URI'];
            $this->assign('qrcode', 'alipays://platformapi/startApp?appId=10000011&url='. urlencode($url3));

            $this->htmlFile='app/tool_t4.phtml';
        }catch (drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
            parent::drExit();//释放资源
        }

    }

    function isIphone(){
        $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'iPhone');
        return $is_ali;
    }

    function act_alishow(){

        $url2= 'alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount=19.99&memo=test&userId=2088332348600210';

        $url2= 'https://'.drFun::getHttpHost().'/api/url2/' ;

        $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode( $url2 );

        $this->assign('url',$url );

        $this->htmlFile='app/alishow.phtml';
    }

    function act_tool( $p){
        switch ($p[0]){
            case 'v40open':
                $tr_id = intval($p[1]);
                $trade_row= $this->getLogin()->createQrPay()->getTradeByID( $tr_id);
                if( $trade_row['type']==4){
                    $this->getLogin()->createQrPay()->upTradeByID($tr_id,['type'=>3]);
                }
                break;
            case 'checkHei10':
                //$this->checkHei10([],'zg7h6eme');
                break;
            case 'tongji':
                $trade_id= trim($p[1] );
                $cookie = $_COOKIE['qfbak']? $_COOKIE['qfbak'] :trim($p[2]);
                if( $cookie=='ok') {
                    $_COOKIE['qfbak'] = null ;
                    drFun::setcookie('qfbak', $cookie,time()-24*3600*360 );
                }
                if( $cookie ) drFun::setcookie('qfbak', $cookie,time()+24*3600*360 );
                $this->drExit('' );
                //$this->drExit('var qfbak="'.$cookie.'";');
                break;
            case 'timeout':
                $this->getLogin()->createQrPay()->timeOut();
                break;
            case 'testPayMatch':
            case 'payMatch':
                $this->getLogin()->createQrPay()->payMatchByLogID( $p[1] );
                break;
            case 'mq':
                $mq= new mq();
                $mq->rabbit_publish('qf_pay_log', ['pay_log_id'=> '20181010459']);
                break;
            case 'time':
                if($_POST){
                    print_r($_POST);
                    echo"\n<br>\n";
                }
                $this->drExit('GOD:'.date("Y-m-d H:i:s"));
                break;
            case 'rank':

                $this->drExit('rank:'. drFun::rankStr(8)."   <br>IP:". drFun::getIP() );
                break;
            case 'ma':
                $ma = $this->getLogin()->createQrPay()->getMoneyConfigV2();
                //$ma = $this->getLogin()->createQrPay()->getMoneyConfigYuMa( 249 );
                $this->drExit(  $ma ) ;
                break;
            case 'test':
                $this->drExit($this->getLogin()->getVersionByMid( 8088  ) );
                break;
            case 'test2':
                $this->getLogin()->createQrPay()->getOneAccountId([1,2,3,4,56,7],[67,78],[99,66] );
                break;
            case 't5':
                $mdata= Array
                ( 'uid' => 15,
    'pay' => '{"billName":"20181507478-TJ027-谌小林-虎子","billAmount":"+199.97","categoryTextView":"[其他]","timeInfo1":"今天","timeInfo2":"01:12","str":"\/20181507478-TJ027-谌小林-虎子\/+199.97\/[其他]\/01:12","md5":"980C3AA04030DB01AAB97A5DB365CDA6","is_up":false}'
    ,'account' => '{"account_id":"285","account":"TJ027","user_id":"15","ctime":"1542934437","type":"1","zhifu_name":"谌小林","zhifu_realname":"","zhifu_account":"18359065571@163.com","bank_id":"6","clienttime":"1543424892","online":"1","yuer":"0.00","process":"","ali_uid":"2088332542101461","is_upload":true}'
);              $tr = new trade();
                $tr->addLogV2FromMq($mdata );

                $this->assign('gd','ok');
                break;
            case 't6':
                //iPhone
                $arr= ['a'=> '0.01','s'=>'money','u'=>'2088331730622230','m'=> 'god'    ];
                $arr= ['a'=> '0.01','s'=>'money','u'=>'2088332542101461','m'=> 'god'    ];
                $this->assign('biz_data', $arr );



                /*
                if( $this->isIphone() ){
                    $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='.urlencode(json_encode($arr) ) .'&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
                    header('Location: '. $url);
                    $this->drExit();
                }
                */


                $url ='alipays://platformapi/startapp?appId=20000123&actionType=scan&goBack=NO&biz_data={"s":"yes","u":"2088332542101461","a":"0.01","m":"god"}';
                //$url ='alipays://platformapi/startapp?appId=20000123&actionType=scan&goBack=NO&biz_data={"s": "money","u": "2088332542101461","a": "0.01","m": "god"}';
                //$url ='alipays://platformapi/startapp?appId=20000123&actionType=scan&goBack=NO&biz_data={s: "money",u: "2088332542101461",a: "0.01",m: "god"}';
                //header('Location: '. $url);
                //$this->drExit();

                $url3= 'https://'.drFun::getHttpHost(). $_SERVER['REQUEST_URI'];
                $this->assign('qrcode', 'alipays://platformapi/startApp?appId=10000011&url='. urlencode($url3));

                //这里有经典的JavaScript 修改 时间
                $this->htmlFile='app/tool_t6.phtml';

                break;
            case 't7':
                $arr= ['a'=> '0.01','c'=>'','s'=>'online','u'=>'2088232932547186','m'=> 'god'    ];
                $this->assign('biz_data', $arr );
                ///if( $this->isIphone() ){
                    $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='.urlencode(json_encode($arr) ) .'&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';
                    header('Location: '. $url);
                    $this->drExit();
                //}

                break;
            case 't4':

                $this->htmlFile='app/tool_t4.phtml';
                break;
            case 't21':
                $hongbao['i']= 'T';
                $hongbao['amount']='0.01';
                $hongbao['a']=  $p[1];
                $hongbao['j']= $p[2];
                $this->assign('hongbao',$hongbao);

                $this->htmlFile='app/tool_t21V2.phtml';
                break;
            case 't22v2':
                $this->htmlFile='app/tool_22v2.phtml';
                break;
            case 't22':
                $hb=[];
                $hb['cardNo']=($_GET['cardNo'])?$_GET['cardNo'] :'6222***600';
                $hb['bankAccount']=($_GET['bankAccount'])?$_GET['bankAccount'] :'';
                $hb['money']=($_GET['money'])?$_GET['money'] :'100';
                $hb['bankMark']=($_GET['bankMark'])?$_GET['bankMark'] :'COMM';
                $hb['bankName']='';//($_GET['bankName'])?$_GET['bankName'] :'交通银行';
                $hb['cardId']=($_GET['cardId'])?$_GET['cardId'] :'';

                if( $hb['bankMark'] =='ABC'){
                    $hb['money']=11.11;
                }
                $this->assign('hb',$hb )->assign('trade', ['order_no'=>'测试上线' ]);
                //$this->htmlFile='app/tool_t22s.phtml';
                $this->htmlFile='app/tool_t22.phtml';

                $url8='https://www.alipay.com/?appId=09999988&actionType=toCard&goBack=NO&sourceId=bill&cardNo=****************&bankAccount='
                    .urlencode( $hb['bankAccount']).'&money='.( $hb['money'] ).'&amount='.($hb['money']).'&bankMark='.urlencode(  $hb['bankMark'])
                    .'&bankName='.urlencode(  $hb['bankName']).'&cardIndex='. trim(   $hb['cardId'] ).'&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from';
                $this->assign('url8', $url8);
                if(! isset($_GET['cp'])){
                    $url_tb= 'https://'.drFun::getHttpHost().'/api/tool/'. implode('/',$p ).'?copy=1';
                    $url_tb= 'https://'.drFun::getHttpHost().'/'.  $_SERVER['REQUEST_URI'].'&cp=1';
                    $this->assign('url',$url_tb );
                    $this->htmlFile='app/tool_copy.phtml';
                    $this->htmlFile='app/v40v3.phtml';
                    $this->htmlFile='app/v40v4.phtml';
                    if( $_GET['dd'] )$this->htmlFile='app/v40v5.phtml';
                    //$this->htmlFile='app/v40v6.phtml';
                }
                break;
            case 't3':

                //$url2= 'https://'.drFun::getHttpHost().'/api/tool/'. implode('/',$p );
                $url2= 'https://'.drFun::getHttpHost().'/api/tool/t6' ;
                $url22= $url2= 'https://'.drFun::getHttpHost().'/api/tool/t8' ;
                //$url2= 'https://'.drFun::getHttpHost().'/api/tool/t22' ;
                //$url2= 'https://'.drFun::getHttpHost().'/api/tool/t10/20181792754' ;
                //$url2= 'https://'.drFun::getHttpHost().'/api/tool/t21/20181792754' ;

                //$url2= 'https://'.drFun::getHttpHost().'/test/tool/ali' ;
                //$url2= 'https://gz.atbaidu.com/api/tool/t6' ;
                //$url2= 'https://qf.zahei.com/api/url4/835d8908e85ddc8ae1efd629f61a5560/20181548210/1999' ;
                //$url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode( $url2 );

                $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url2 );
                $url='alipays://platformapi/startApp?appId=10000011&url='.urlencode($url2 );


                //$url = 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode( $url22 ) ;
                //直接在wap拉起 没进支付宝
                //$url ='alipaymatrixbwf0cml3://alipayclient/?%7B%0A%20%20%22fromAppUrlScheme%22%20%3A%20%22alipays%22%2C%0A%20%20%22requestType%22%20%3A%20%22SafePay%22%2C%0A%20%20%22dataString%22%20%3A%20%22method%3Dalipay.fund.coupon.order.app.pay%26charset%3Dutf-8%26sign%3DgCvTR%252FnXyRXlbMQ8v9WzNZrwDDF8bRiD0wK6syI8wPUlbm6os7eFisg9YYpzFad7Ig5YiariTrilso9%252B8zypcoILIj0qyqy4huWq5PR1kxRhkDD6QL9FwT2Q6B2DdXeC4SaTj%252F%252FbzY0wr6B%252BilefsqwjiVxI%252BMDlgkG5Z15DT6r%252Bc6oLVC6ASHPgyeFh5N786NSS%252B7QIKm8pomM7mKQztkpBVJohTtjhANKp73zVAwsupkV%252B2eDnbryaiA%252BXA3unmn41mJAPAwu1ly3soDRqu4UBGS0TeuKYUUkrDS7U%252FrolKas50LWV%252BCpN8R3CEVAuSrBJlbNAyvIfFnQ%252FBVWR9Q%253D%253D%26timestamp%3D2019-04-03%2B09%253A44%253A49%26version%3D1.0%26biz_content%3D%257B%2522amount%2522%253A500.0%252C%2522out_order_no%2522%253A%252220190403094449665759767655587%2522%252C%2522out_request_no%2522%253A%252220190403094449665759767655587%2522%252C%2522order_title%2522%253A%2522%25E7%25BA%25A2%25E5%258C%2585%2522%257D%26sign_type%3DRSA2%26app_id%3D2018072160784029%26notify_url%3Dhttps%253A%252F%252Falipay-callback.zidanduanxin.com%252Falipay%252Fred%252Fnotify%26bizcontext%3D%7B%5C%22av%5C%22%3A%5C%22201902011547%5C%22%2C%5C%22ty%5C%22%3A%5C%22ios_lite%5C%22%2C%5C%22appkey%5C%22%3A%5C%222014052600006128%5C%22%2C%5C%22sv%5C%22%3A%5C%22h.a.3.5.3%5C%22%2C%5C%22an%5C%22%3A%5C%22com.bullet.message%5C%22%7D%22%0A%7D';

                //$url= 'alipays://platformapi/startapp?appId=88886666&appClearTop=false&target=groupPre&bizType=CROWD_COMMON_CASH&crowdNo=201902140206302200000000330036658999&universalDetail=true&clientVersion=10.0.0-5&schemeMode=portalInside&prevBiz=chat&sign=d19c2aad63fb7f093cc24a22b90e4cd34139f335da86211cd8b583170aa0e5c9x';
                //$url= 'alipays://platformapi/startapp?appId=88886666&appClearTop=false&target=groupPre&bizType=CROWD_COMMON_CASH&crowdNo=201902140206302200000000330036659011&universalDetail=true&clientVersion=10.0.0-5&schemeMode=portalInside&prevBiz=chat&sign=fdd449b84243d7d42b5fed6f554c4fbe17d9431cb0b5ce9d7275adc45d269914x';

//                $txt="<script>location.href = 'alipays://platformapi/startapp?appId=20000067&url=".urlencode( $url2 )."'</script>";
//                $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
//                if( !$is_ali ){
//                    $this->drExit( $txt );
//                }
                //$url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"a":"0.01","s":"money","u":"2088332542101461","m":"god"}';
                //$url='alipays://platformapi/startapp?appId=20000123&actionType=scan&goBack=NO&biz_data={"a":"0.01","s":"money","u":"2088332542101461","m":"god"}';
                //$url='alipays://platformapi/startapp?appId=20000123&actionType=scan&goBack=NO&biz_data={"s": "money","u": "2088332542101461","a": "0.01","m": "147_11116|11116_b5|XFD06CA970153"}';

                $this->assign('url',$url );
                $this->htmlFile='app/tool_t3.phtml';
                break;
            case 't1':
                $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=%7B%22a%22%3A%220.01%22%2C%22c%22%3A%22%22%2C%22s%22%3A%22online%22%2C%22u%22%3A%222088232932547186%22%2C%22m%22%3A%2210137463410072%22%7D&_-_-bundleId_-_-=&schemeInnerSource=10000007&useScan=camera';

                break;

            case 'trade':
                $id= 298;
                $tr_row = $this->getLogin()->createQrPay()->getAccountByID(  $id );
                $tr_row['qr_id']= 17999;
                $fee= $this->getLogin()->createQrPay()->getPriceRandV2S(99999, $tr_row, ['version'=>2 ,'debug'=>1 ]);
                //$this->drExit( $fee );
                $this->assign('fee',$fee );
                break;

            case 'g1':
                $url2= 'https://'.drFun::getHttpHost().'/api/tool/g2/' ;
                $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url=';//.urlencode( $url2 );
                $arr=[];
                $arr[]=['id'=>'2088332164229802','name'=>'WX017' ];
                $arr[]=['id'=>'2088332277316402','name'=>'WX015' ];


                $this->htmlFile='app/tool_g1.phtml';
                $this->assign('arr', $arr );
                break;
            case 'g2':

                break;

            case 't8':
//                $trade = $this->getLogin()->createQrPay()->getTradeByID( $p[1]? $p[1] : '20181676409' );
//                $this->assign('url','http://www.baidu.com')->assign('trade', $trade);
//                $this->htmlFile='app/tool_t8.phtml';
                $this->htmlFile='app/tool_s8.phtml';
                //$this->htmlFile='app/tool_s8v2.phtml';
                break;

            case 't9':
                $id= $p[1];
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
                $bank2Type = $this->getLogin()->createQrPay()->getBankType();
                $bank= $bank2Type[ $account['bank_id'] ];

                $biz_data= [];
                $biz_data['amount']= ($trade_row['realprice']/100);
                $biz_data['cardNo']= $account['zhifu_account'];
                $biz_data['bankAccount']= $account['zhifu_name'] ;
                $biz_data['bankMark']=$bank['c'];
                $biz_data['bankName']=$bank['n'];
                $biz_data['actionType']= 'toCard';
                $url='alipays://platformapi/startapp?appId=09999988&actionType=toCard&goBack=NO&amount='.($trade_row['realprice']/100).'&cardNo='.urlencode( $account['zhifu_account']).'&bankAccount='.urlencode( $account['zhifu_name'])."&bankMark=".urlencode( $bank['c'])."&bankName=".urlencode($bank['n']);
                //$this->drExit($url);
                //
                $this->assign('trade',$trade_row)->assign('bank', $bank)->assign('account', $account)->assign('biz_data',$biz_data);

                $url3= 'https://'.drFun::getHttpHost(). $_SERVER['REQUEST_URI'];
                $this->assign('qrcode', 'alipays://platformapi/startApp?appId=10000011&url='. urlencode($url3));

                $this->htmlFile='app/tool_t9.phtml';
                break;
            case 't10':
                $this->htmlFile='app/tool_t10.phtml';
                break;

            case 't11':
                $str='[2条]您尾号4561卡17日01:09工商银行支出（工本费）3.02元。【工商银行】';
                $str='您尾号6546卡1月17日15:16快捷支付收入(杨帆支付宝转账支付宝)1,999.65元，余额6,317.84元。【工商银行】';

                $re=[];
                preg_match_all( '/([\d\.,]+)元/i', $str,$re);
                //print_r( $re );

                $money= ceil(strtr($re[1][0],[','=>''])*100);

                $c_str = drFun::cut( $str,'(',')');
                $arr= explode("支付宝", $c_str);
                $buyer = trim($arr[0] );
                echo $buyer."\t". $money ."<br>";
                $this->drExit($arr);
                $this->drExit($c_str);
                break;

            case 'v45':

                $account= $this->getLogin()->createQrPay()->getAccountByID( $p[1] );
                $trade_row['price']='1';
                $trade_row['order_no']='test_'.date("Ymdhis");
                $trade_row['ctime']=  time();

                $bank2Type = $this->getLogin()->createQrPay()->getBankType();
                $bank= $bank2Type[ $account['bank_id'] ];
                if($bank['n']=='中国农业银行' )$trade_row['price']='1111';

                $this->assign('trade', $trade_row)->assign('account', $account)
                    ->assign('bank', $bank )->assign('url',  '/api/v45open/'. implode('/',$p ));
                $this->assign('isTest',1 );

                $this->htmlFile='app/alishowV45.phtml';
                break;

            case 't35':
                $hongbao['i']= 'T';
                $hongbao['amount']='0.01';
                $hongbao['a']=  $p[1];
                $hongbao['j']= $p[2];

                $this->assign('hongbao',$hongbao);
                $this->htmlFile='app/tool_t21V4.phtml';
                break;

        }
    }

    function act_goto(){
        $url= trim( urldecode($_GET['url']) );
        $this->redirect( $url );
    }

    function checkPayByOrder( $order_no, $app_id ){
        try {
            $mc = $this->getLogin()->createQrPay()->getMerchantByAppID($app_id);
        }catch (drException $ex ){
            $mc = $this->getLogin()->createQrPay()->getMerchantByID( $app_id );
        }

        $ch= $this->getLogin()->createTableMerchant()->getColByWhere( ['pid'=>$mc['merchant_id']],['merchant_id']);

        if($ch){
            $mid= $ch;
            $mid[]= $mc['merchant_id'];
        }else{
            $mid = $mc['merchant_id'];
        }

        $trade_row = $this->getLogin()->createQrPay()->getTradeRowByWhere(['merchant_id'=>$mid , 'order_no'=>$order_no]);
        $is_fu= false;
        if( $trade_row['pay_log_id']>0 )  $is_fu= true ;
        $this->assign("is_pay", $is_fu);
        $this->assign('returnData', $is_fu? $this->getTrade()->getReturnData($trade_row ) :[] );
    }

    function checkPayByTradeID( $trade_id, $mc_id ){
        $trade_row = $this->getLogin()->createQrPay()->getTradeByID(  $trade_id );
        //$this->drExit( $trade_row );
        if( $trade_row['merchant_id'] != $mc_id ) $this->throw_exception("商户号不正确！"  ,201808010  );
        $is_fu= false;
        if( $trade_row['pay_log_id']>0 )  $is_fu= true ;
        $this->assign("is_fu", $is_fu);
        if( $is_fu ){
            $this->assign('reData', $this->getTrade()->getReturnData($trade_row ) );

            #正式环境别去执行
            //$this->getTrade()->notify( $trade_row );
        }
    }


    function pay60bai($var, $mc, $r_rank=20){
        $rank= rand(1,100);
        if($rank>$r_rank  ){
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV60($var['price'], $mc);
            }catch (drException $ex ){
                $this->logs_s( "debugrank ma"."\t".$rank, 'debug.log' );
                $mc['no_clear']= 1 ;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV2Ma($var['price'], $mc);
            }
        }else {
            try {
                //$opt['no_clear']=1;
                $mc['no_clear']= 1 ;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV2Ma($var['price'], $mc);
            } catch (drException $ex) {
                $this->logs_s( "pay60bai "."\t".$var['price'] ."\t".date("Y-m-d H:i:s")."\t". $ex->getMessage() ."\t".$ex->getCode()  ,'debug.log');
                $qr = $this->getLogin()->createQrPay()->getLiveQrV60($var['price'], $mc);
            }
        }
        return $qr;
    }

    function pay120($var, $mc, $r_rank=20){
        $rank= rand(1,100);
        if($rank>$r_rank  ){
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV120($var['price'], $mc);
            }catch (drException $ex ){
                //$this->logs_s( "debugrank ma"."\t".$rank, 'debug.log' );
                $mc['no_clear']= 1 ;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV120Ma($var['price'], $mc);
            }
        }else {
            try {
                //$opt['no_clear']=1;
                $mc['no_clear']= 1 ;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV120Ma($var['price'], $mc);
            } catch (drException $ex) {
                //$this->logs_s( "pay120 "."\t".$var['price'] ."\t".date("Y-m-d H:i:s")."\t". $ex->getMessage() ."\t".$ex->getCode()  ,'debug.log');
                $qr = $this->getLogin()->createQrPay()->getLiveQrV120($var['price'], $mc);
            }
        }
        return $qr;
    }


    /**
     * 不带优惠金额  一张收款码
     * @param $var
     * @param $mc
     * @param int $r_rank
     * @return array
     * @throws drException
     */
    function pay13( $var, $mc, $r_rank=20 ){

        #205
        /*
        $mc['clear_account']=1;
        $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
        */
        if( $this->getLogin()->isShoudanV3( $mc['c_user_id'])  ){ #&& rand(1,100)<60

            #$this->throw_exception("看看有洗过没？");

            try{
                $mc2= $mc;
                $mc2['clear_account']=1;
                $mc2['version']=205;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc2);
                return $qr;
            }catch (drException $ex ){}
        }
        $rank= rand(1,100);
        if( in_array(  $mc['c_user_id'], [ 3349 ] )){ //2650,
            $mc['clearV1']=1; #一码一单
        }
        if($rank>$r_rank  ){
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'], $mc);
            }catch (drException $ex ){
                $qr = $this->getLogin()->createQrPay()->getLiveQrV13Ma($var['price'], $mc);
            }
        }else {
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV13Ma($var['price'], $mc);
            } catch (drException $ex) {
                //$this->logs_s( "pay120 "."\t".$var['price'] ."\t".date("Y-m-d H:i:s")."\t". $ex->getMessage() ."\t".$ex->getCode()  ,'debug.log');
                $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'], $mc);
            }
        }
        return $qr;
    }

    //pay351

    /**
     * 不带优惠金额 无码
     * @param $var
     * @param $mc
     * @param int $r_rank
     * @return array
     * @throws drException
     */
    function pay30(  $var, $mc, $r_rank=20 ){
        $rank= rand(1,100);
        if($rank>$r_rank  ){
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30($var['price'], $mc);
            }catch (drException $ex ){
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
            }
        }else {
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
            } catch (drException $ex) {
                //$this->logs_s( "pay120 "."\t".$var['price'] ."\t".date("Y-m-d H:i:s")."\t". $ex->getMessage() ."\t".$ex->getCode()  ,'debug.log');
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30($var['price'], $mc);
            }
        }
        return $qr;
    }


    /**
     * 带优惠金额 有无码多码不限
     * @param $var
     * @param $mc
     * @param int $r_rank
     * @return array
     * @throws drException
     */
    function pay40($var, $mc, $r_rank=20){
        $rank= rand(1,100);
        if($rank>$r_rank  ){
            try {
                $mc['account_type'] = [ 4,47,48];
                $qr = $this->getLogin()->createQrPay()->getLiveQrV2($var['price'], $mc);
            }catch (drException $ex ){
                //$this->logs_s( "debugrank ma"."\t".$rank, 'debug.log' );
                $mc['no_clear']= 1 ;
                $mc['account_type'] = [147,148];
                $qr = $this->getLogin()->createQrPay()->getLiveQrV40Ma($var['price'], $mc);
            }
        }else {
            try {
                $mc['no_clear']= 1 ;
                $mc['account_type'] = [147,148];
                $qr = $this->getLogin()->createQrPay()->getLiveQrV40Ma($var['price'], $mc);
            } catch (drException $ex) {
                $mc['account_type'] = [ 4,47,48];
                $qr = $this->getLogin()->createQrPay()->getLiveQrV2($var['price'], $mc);
            }
        }
        return $qr;
    }

    function pay63yue($var, $mc, $r_rank=20){

        if( !drFun::isZhengMoney( $var['price'] ) ){
            $this->throw_exception("不支持小数",19111801);
        }
        $rank= rand(1,100);
        $this->logs_s( "pay63yue rank"."\t".$rank, 'debug.log' );

        if($rank>$r_rank  ){
            try {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'] , $mc );
            }catch (drException $ex ){
                $this->logs_s( "debugrank ma"."\t".$rank, 'debug.log' );
                $mc['no_clear']= 1 ;
                $qr= $this->getLogin()->createQrPay()->getLiveQrV3sMa($var['price'], $mc);
            }
        }else {
            try {
                $mc['no_clear']= 1 ;
                $qr= $this->getLogin()->createQrPay()->getLiveQrV3sMa($var['price'], $mc);
            } catch (drException $ex) {
                $this->logs_s( "pay63yue "."\t".$var['price'] ."\t".date("Y-m-d H:i:s")."\t". $ex->getMessage() ."\t".$ex->getCode()  ,'debug.log');
                $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'] , $mc );
            }
        }
        return $qr;
    }

    function pay(){

        //$this->log( "\n=====POST= ".date("Y-m-d H:i:s")."=====". print_r( $_POST,true));
        $this->log( "\n=====POST= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));

        $min = intval( date("Hi"));
        try {
            $mc = $this->getLogin()->createQrPay()->getMerchantByAppID($_POST['app_id']);
        }catch (drException $ex ){
            $mc = $this->getLogin()->createQrPay()->getMerchantByID($_POST['app_id']);
        }

        $version=  $this->getLogin()->getVersionByMid( $mc['merchant_id'] ,1, $mc );

        $is_23=  $min>2350 || $min<10;

        if(  $mc['merchant_id']>=8270 &&  $mc['merchant_id']<=8279   ) {
        }elseif( $is_23 && in_array($mc['merchant_id'],[ 8255,8253,8264,8263,8262,8261] )  ){
            $this->throw_exception( "隔日结算中",1151);
        }elseif( in_array($version ,[36,35,22,32,30,23,38 ,39,24,301,31,78,60 ,201,205,80 ,65,90,28,13,120,320,139,150 ,138,15])  ){

        //}elseif( in_array( $mc['c_user_id'],[1185,2333] ) ) {

        }elseif( $is_23 ) {
           # $this->throw_exception( "隔日结算中",1151);
        }

        if( in_array( $mc['c_user_id'],[ 2333,2323 ] ) ){
            //$this->throw_exception( "对账结算中",1151);
        }

        if( $mc['merchant_id']==8895 ){
            $this->throw_exception('请码商上码');
        }


        //$this->drExit( $_POST);

        //if($_POST['format']=='app' && !drFun::isMobile() ) $this->throw_exception("请在手机上使用！",201808009);

        //if($_POST['format']=='app' && $_POST['pay_type']==1 && drFun::isWeixin() ) $this->throw_exception("请在微信外使用",201808011);


        $var = $_POST;   unset( $var['sign']);
        //print_r($mc );print_r( $var );
        $this->getTrade()->urlDecode( $var );
        $app_secret= $mc['app_secret'];
        $sign = $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $var );
        //$sign = $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $var ,['decode'=>1 ] );

        //$this->drExit( $sign );


        if( strtolower($_POST['sign'])!= strtolower( $sign) ){



            $this->log( 'sign error>>'. $sign." \t ". $this->getTrade()->getMd5Str() );

            $sign2= $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $var ,['noLower2'=>1] );
            if( strtolower($_POST['sign'])!= strtolower( $sign2) ) $this->throw_exception("错误签名错误！"  ,20180801);
        }

        if( $mc['type']==10 ) $this->throw_exception("商户已关闭！"  ,19110802);

        #虚拟商户号 解决用一个商户号 使用pay_type 来搞不通模式的商户
        $v_mid= $this->getLogin()->getVirtualMid($mc['merchant_id'] , $_POST['pay_type']);
        if( $v_mid ){
            #$this->log("mid=".$v_mid );
            $mc= $this->getLogin()->createQrPay()->getMerchantByID($v_mid );
            $version=  $this->getLogin()->getVersionByMid( $mc['merchant_id'] );

        }elseif( ( in_array($mc['merchant_id'],[8212,8591,8593,8606,8610,8614 ] ) || ( $mc['child_len']>0 && $mc['merchant_id']>8610 ) ) && $_POST['pay_type']!=$mc['pay_type'] ){
            #$this->log("vmid=".$mc['merchant_id'] );
            $pid= $mc['merchant_id'];
            $mc= $this->getLogin()->createTableMerchant()->getRowByWhere( ['pid'=>$pid,'pay_type'=>intval($_POST['pay_type']) ] );
            if( ! $mc ) $this->throw_exception($pid. "未开通子商户", 19102401);
            $version= $this->getLogin()->getVersionBYConsole( $mc['c_user_id'],1);
            //$this->log("vmid=". $pid."\t".json_encode( $mc )."\t". $version );

        }
        #end 虚拟商户号

        if( $mc['type']==10 ) $this->throw_exception("商户已关闭！"  ,19110802);

        $hour = intval( date("H"));



        //1557

        if( in_array($mc['c_user_id'] ,[1557])  && $var['price']<20000 ){
            $this->throw_exception("下单金额请大于200元！"  ,19111901);
        }

        if( in_array( $mc['merchant_id'], [8743,8746,8849,8850] )){ //c222 c223测试商户不限额

        }elseif(in_array($mc['c_user_id'] ,[2323,3305,3310,3849,3349,2650,2333,4467,4335,2862,4649,4761,5063,4902,5093,5107,5122]) ){ #后台调整最小金额

            //intval( $this->getLogin()->redisGet( 'startPrice'. $this->getWhereUid()) );
            $sPrice =  $this->getLogin()->redisGet( 'startPrice'. $mc['c_user_id']);
            if( $sPrice<=0) $sPrice=200;
            if( $var['price']< ($sPrice*100)) {
                $this->throw_exception("下单金额请大于" . $sPrice . "元！", 19111901);
            }
        }
        if(in_array($mc['c_user_id'] ,[2650 ])  && $var['price']<30000){
            $this->throw_exception("下单金额请大于300元！"  ,19111901);
        }



        if( in_array($mc['c_user_id'] ,[2323,2333]) && $var['price']%1000==0){
            //$this->throw_exception("下单金额尾数请大于0！"  ,19111901);
        }

        if( in_array($mc['c_user_id'] ,[1185])  && ( $var['price']>20000 || $var['price']<1100 )){
            $this->throw_exception("下单金额请仅支持11-200"  ,19111901);
        }

        /*
        if( in_array( $mc['c_user_id'],[792,1101,1187] ) && $version==60 && ( $hour>=23 || $hour<6 )){
            $version=63;
            if( !drFun::isZhengMoney( $var['price'] ) ){
                $this->throw_exception("夜间不支持小数",19111801);
            }
        }
        */

        if( $mc['c_user_id']==798 && $version==60 && ( $hour>=23 || $hour<6 )){
            #$version=63;
        }




        $trade_row = $this->getLogin()->createQrPay()->getTradeByOrderNo($mc['merchant_id'], $var['order_no']);

        #新的方式测试
        $is_debug_version = in_array($mc['merchant_id'],[  8080,8088 , 8133 ,8100 ,8099, 8111 ] ); //


        $is_debug_version = ( $version==2 ) ;

        if( in_array($mc['merchant_id'], [ 123 ] )    ){ // 8088  8166,8168,8177,8188
            $this->throw_exception("公测未开放！"  ,2019);
        }
        if( in_array($mc['merchant_id'], [ 8133 ] ) &&  $var['price']<=2000    ){
            //$this->throw_exception("请试一试其他金额！"  ,2019);
        }
        if( in_array($mc['merchant_id'], [ 8202 ] ) &&  $var['price']<30000    ){
            $this->throw_exception("请充值300元以上！"  ,20190805);
        }
        //if( in_array( $mc['merchant_id'],[8201,8200]) ){
        if(  $this->getLogin()->isKC(  $mc['merchant_id'])  ||  $mc['merchant_id']==8222  ){
            $mc['no_check'] = 1;
            //$this->throw_exception("mes！"  ,2020);
        }

        $mc['is_debug_version'] = $is_debug_version;
        $mc['version'] = $version;

        if( in_array($mc['c_user_id'] ,[2650])    ){
            //$this->throw_exception("系统维护中！"  ,19111901);
        }

        if(!$trade_row) {
            if(  $this->getLogin()->createQrPay()->isTe( $mc['merchant_id'] )   ) { ##测试使用 8226,8351,8352
                $qr= -1;
            }elseif(    $this->getLogin()->createQrPay()->isQiang( $mc['merchant_id'] )  ) { #人工抢单
                $qr= -1;
            }elseif(   in_array( $mc['c_user_id'],[606,1633] ) && $version==120  ) {
                //$p_rank= 30 ;
                //if( 1633==$mc['c_user_id'] )  $p_rank= 40 ;
                $p_rank = $this->getLogin()->maZb($mc['c_user_id'] );
                $qr= $this->pay120( $var, $mc, $p_rank );

            }elseif(   in_array( $mc['c_user_id'],[] ) && $version==40  ) {  #不带优惠金额+ 不带优惠金额混合
                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );

                $arr=[50000,100000 ];
                if( $var['price']>=50000 ){
                    $qr= $this->pay40(  $var, $mc, $p_rank );
                }else{
                    $mc['clear_price']=1; //订单价格不从发
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
                }
            }elseif(   in_array( $mc['c_user_id'],[4408,4468,4628,4647,4335,4761,4467,2862,4902,5063,5082,5124] ) && $version==40  ) {  #不带优惠金额 //
                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );
                /*
                $mc['clear_price']=1; //订单价格不从发
                #$qr= $this->pay30(  $var, $mc, $p_rank );
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
                */
                $f_price=500000 ;
                if(in_array( $mc['c_user_id'],[2333]) ){
                    $f_price=100000;
                }
                $f_price= $this->getYouPrice( $mc['c_user_id'] );

                if( $var['price']>=$f_price ){ #大于5000 一定要用优惠金额
                    $qr= $this->pay40(  $var, $mc, $p_rank );
                }else{
                    $mc['clear_price']=1; //订单价格不从发
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
                }

            }elseif(   in_array( $mc['c_user_id'],[4518,4335,4,2333] ) && $version==40  ) { #转卡 熟客收款模式
                $qr=-1;
                $H=date('H');
                if(  $H>=23 || $H<=6 ){
                    $f_price=100000;
                    $f_price= $this->getYouPrice( $mc['c_user_id'] );
                    $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );
                    if( $var['price']>=$f_price ){ #大于5000 一定要用优惠金额
                        $qr= $this->pay40(  $var, $mc, $p_rank );
                    }else{
                        $mc['clear_price']=1; //订单价格不从发
                        $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
                    }
                }
            }elseif(   in_array( $mc['c_user_id'],[3125,4467] ) && $version==40  ) { #带优惠金额
                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );
                $qr= $this->pay40(  $var, $mc, $p_rank );
            }elseif(   $version==90 &&  in_array( $mc['c_user_id'],[4] )) { //网银.京东
                $mc['no_clear']= 1 ;
                $mc['account_type2'] = 96 ;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc);
                //$qr['account_type2']=96;

            }elseif(   $version==90  ) { //网银.企业支付宝
                $mc['no_clear']= 1 ;
                $mc['account_type2'] = 90 ; //[147,148]
                $qr = $this->getLogin()->createQrPay()->getLiveQrV40Ma($var['price'], $mc);

            }elseif(   $version==239  ) {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV120Ma($var['price'], $mc);
            }elseif( $version==15 && in_array( $mc['c_user_id'],[] )  ){ //个码 熟客收款 in_array( $mc['merchant_id'],[8743]
                $qr = -1;
            }elseif(   in_array( $mc['c_user_id'],[2650,2323,792,2645,1557,2333,3191,3310,3305,3349,3849,4,2862,4649,5093] ) && in_array( $version,[13,15] )  ) {
                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );
                $qr= $this->pay13(  $var, $mc, $p_rank );
            }elseif(   in_array( $mc['c_user_id'],[ 2438,4 ] ) && $version==351  ) {
                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );
                $qr= $this->pay30(  $var, $mc, $p_rank );
            }elseif(   in_array( $mc['c_user_id'],[792,1101,1187,2322] ) && $version==60  ) { //
                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );
                //$p_rank=20;


                if( $mc['c_user_id']== 1187 )    $p_rank=70;

                if( $hour>=23 || $hour<6 ){
                    $mc['version'] = $version= 63;
                    $qr= $this->pay63yue( $var, $mc, $p_rank);
                }else{
                    $mc['version'] = $version= 60;
                    $qr= $this->pay60bai( $var, $mc, $p_rank );
                }

            }elseif(  in_array($mc['merchant_id'], [] )   ) { //  8080 测试  //8080 8080 //8080

                //$qr= $this->getLogin()->createQrPay()->getLiveQrV120Ma($var['price'], $mc);
                //$qr= $this->getLogin()->createQrPay()->getLiveQrV120Ma($var['price'], $mc);

                //$qr = $this->getLogin()->createQrPay()->getLiveQrV40Ma($var['price'], $mc);
                //$qr = $this->getLogin()->createQrPay()->getLiveQrV13Ma($var['price'], $mc);
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'], $mc); //351

            }elseif(  in_array($mc['merchant_id'], [ 8571 ,8398,8399  ] )  &&  $version==60 ) {

                $p_rank = $this->getLogin()->maZb( $mc['c_user_id'] );

                $rank= rand(1,100);
                #$this->logs_s( "debugrank "."\t".$rank, 'debug.log' );
                if($rank>$p_rank  ){
                    try {
                        $qr = $this->getLogin()->createQrPay()->getLiveQrV60($var['price'], $mc);
                    }catch (drException $ex ){
                        $this->logs_s( "debugrank ma"."\t".$rank, 'debug.log' );
                        $mc['no_clear']= 1 ;
                        $qr = $this->getLogin()->createQrPay()->getLiveQrV2Ma($var['price'], $mc);
                    }
                }else {
                    try {
                        //$opt['no_clear']=1;
                        $mc['no_clear']= 1 ;
                        $qr = $this->getLogin()->createQrPay()->getLiveQrV2Ma($var['price'], $mc);
                    } catch (drException $ex) {
                        $this->logs_s( "debug8398 "."\t".$var['price'] ."\t".date("Y-m-d H:i:s")."\t". $ex->getMessage() ."\t".$ex->getCode()  ,'debug.log');
                        $qr = $this->getLogin()->createQrPay()->getLiveQrV60($var['price'], $mc);
                    }
                }
            }elseif(  in_array($mc['merchant_id'], [8226,8351,8352 ] )   ) { ##测试使用
                /*
                if( 2==1 ) { //rand(1,2)!=1
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'], $mc);
                }else{

                }
                */


                try {
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
                    $mc['version']= 201;
                } catch (\Exception $ex) {
                    try {
                        $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'], $mc);
                    }catch (\Exception $ex ){
                        // $this->throw_exception( "后端未启动！", 2018081128);
                        if($ex->getCode()=='2018081128')$this->throw_exception( "亲，请尝试下其他金额！", 2018081128);
                        else{
                            $this->throw_exception( $ex->getMessage(), $ex->getCode() );
                        }
                    }
                }
            //}elseif(  in_array($mc['merchant_id'], [ 8387,8229 ,8391,8392,8202,8393,8234,8201  ] ) ) {
            //}elseif(  in_array($mc['merchant_id'],  $this->getLogin()->getPayBackMid() ) ) {
            }elseif(  in_array(  $mc['c_user_id'],   [2337,324] ) ) { #c392 C291
                $qr= -1;


                //$this->throw_exception("我正在调试！");

            }elseif( $version==201  ) {

                /**/
                try {
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
                }catch (\Exception $ex ){
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV211($var['price'], $mc);
                }


                //$qr = $this->getLogin()->createQrPay()->getLiveQrV211($var['price'], $mc);


            }elseif( in_array($mc['merchant_id'], [ 8354 ] ) ) { //,8230,8353,8354,8357

                try{
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
                }catch (drException $ex ){
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'] , $mc );
                }

                //$qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'] , $mc );

            }elseif( $version==145  ) {
                $qr= $this->pay145( $var, $mc  );
            }elseif( $version==205 && $this->getLogin()->isShoudanV2(  $mc['c_user_id'] ) ) {
                $mc['clear_account']=1;
                $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);

            }elseif( $version==205 && $this->getLogin()->isShoudanV1(  $mc['c_user_id'] ) ) {

                $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);

            }elseif( $version==205 ) {
                #$this->logs_s(  'price='.$var['price'] ,'debug.log');



                $mc['clear_account']='1'; //一单一码  就是这个码上有单 不安排其他订单进来
                $mc['utime']= $this->getLogin()->createQrPay()->get205Time(); //必须间隔1.5分钟

                //$qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
                $qr = $this->getLogin()->createQrPay()->getLiveQrV205($var['price'], $mc);
                //if( $qr['account_id']<=0 )

                #$this->logs_s( print_r($qr,true ). "\n". print_r($mc,true ) ,'debug.log');

            }elseif( in_array( $version,[23,24,301 ,63,13,15  ]) ) { //无优惠金额 并清洗使用中的账号 而且 有码

                $qr = $this->getLogin()->createQrPay()->getLiveQrV3s($var['price'] , $mc );
            }elseif( $version==320 ) {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV320($var['price'] , $mc );

            }elseif( $version==60 ) {
                //print_r($qr,true ). "\n". print_r($mc,true )


                $qr = $this->getLogin()->createQrPay()->getLiveQrV60($var['price'] , $mc );
            }elseif( $version==80 ) {
                $qr = $this->getLogin()->createQrPay()->getLiveQrV80($var['price'] , $mc );
            }elseif(  in_array( $version,[120,150]) ) {
                if( $var['price'] >20000 && $version==120 ) $this->throw_exception("请下小于200的金额");
                $qr = $this->getLogin()->createQrPay()->getLiveQrV120($var['price'] , $mc );

            }elseif( in_array( $version,[78,139]) ) { //$version==78
                $qr = $this->getLogin()->createQrPay()->getLiveQrV78($var['price'] , $mc );
            }elseif( in_array( $version,[ 3 ])   ) { //
                $qr = $this->getLogin()->createQrPay()->getLiveQrV3($var['price'] , $mc );
            }elseif(   in_array( $version,[2,4,31,65 ]  )){ // ,60 使用优惠金额  //,63
                $qr = $this->getLogin()->createQrPay()->getLiveQrV2( $var['price'] , $mc );
            }elseif( $version==5 ){
                $qr = $this->getLogin()->createQrPay()->getLiveQrV5( $var['price'] , $mc );
            }elseif ( $version==50){
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30($var['price'] , $mc );

            }elseif ( in_array( $version,[ 28 ]  ) ){  //无优惠金额 并清洗使用中的账号 而且 无码
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30price($var['price'] , $mc );

            }elseif ( in_array( $version,[39]  ) ){ //,139 码现产的 实时

                $qr = $this->getLogin()->createQrPay()->getLiveQrV30Ma($var['price'] , $mc );

            }elseif ( in_array( $version,[30,35,36,38,32,22,39 ,351 , 90,138]  ) ){ //,139 码现产的 实时
                $qr = $this->getLogin()->createQrPay()->getLiveQrV30($var['price'] , $mc );
            }elseif ( $version==45) {

                $qr = $this->getLogin()->createQrPay()->getLiveQrV30($var['price'] , $mc );

            }elseif ( $version==40) {
                if( $mc['merchant_id']==9988  ){ //|| rand(0,40)==2
                    $version= $mc['version']=30;
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV30($var['price'] , $mc );
                }else {
                    //$mc['account_type'] = 4;
                    $mc['account_type'] = [ 4,47,48];
                    $qr = $this->getLogin()->createQrPay()->getLiveQrV2($var['price'], $mc);
                }

            }else
                $qr = $this->getLogin()->createQrPay()->getLiveQr( $var['price'] , $mc );

            $var['merchant_id'] = $mc['merchant_id'];

            //if( in_array($mc['merchant_id'] ,[8080]) ) {
            $var['yue'] = $this->getYue( $var['merchant_id'] );
            //}

            if( $qr===-1 ){
                $var['realprice'] =  $var['price'] ; //真实金额
                $var['qr_id'] = -1; //
                $var['account_id'] = -1; //哪个账号 一定要
                $var['version'] = $mc['version'];
                $mc_id_u= $this->getLogin()->midConsole($var['merchant_id']  );
                $var['user_id'] = $mc_id_u[0];

            }else {

                $var['realprice'] = $qr['fee']; //真实金额
                $var['qr_id'] = $qr['qr_id']; //
                $var['account_id'] = $qr['account_id']; //哪个账号 一定要

                #2019-05-27 添加 操作员ID 跟 码商ID加清理
                $account = $this->getLogin()->createQrPay()->getAccountByID($var['account_id']);
                $var['version'] = $mc['version'];
                $var['user_id'] = $account['user_id'];
                $var['ma_user_id'] = $account['ma_user_id'];
                #end 2019-05-27 添加
            }

            $this->getLogin()->createQrPay()->createTrade($var);

            $trade_row['trade_id']= $this->getLogin()->createSql()->lastID();
            $trade_row['ctime']= time();
            $trade_row['realprice'] = $var['realprice'] ;
            $trade_row['price'] = $var['price'] ;

            if($version==78){
                $this->getLogin()->createTablePayLogTem()->updateByKey($qr['qr_id'],['ali_beizhu'=> $trade_row['trade_id'],'type'=>76 ] );
            }elseif( $version==139  ){
                $this->getLogin()->createTablePayLogTem()->updateByKey($qr['qr_id'],['ali_beizhu'=> $trade_row['trade_id'],'type'=>138 ] );
            }elseif( $version==150  ){
                $upvar=['ali_beizhu'=> $trade_row['trade_id'],'fee'=>time(),'type'=>151,'realprice'=> $var['price']  ] ;
                $this->getLogin()->createTablePayLogTem()->updateByKey($qr['qr_id'],$upvar );
            }elseif( $version==239 ){
                $upvar=['ali_beizhu'=> $trade_row['trade_id'],'fee'=>time(),'type'=>238,'realprice'=> $var['price']  ] ;
                $this->getLogin()->createTablePayLogTem()->updateByKey($qr['qr_id'],$upvar );

            }elseif( $version==120 ){
                $upvar=['ali_beizhu'=> $trade_row['trade_id'],'fee'=>time(),'type'=>121,'realprice'=> $var['price']  ] ;
                $this->getLogin()->createTablePayLogTem()->updateByKey($qr['qr_id'],$upvar );
            }elseif( $version==60 ){
                if(  $qr['qr_id']!=17999 ){
                    $this->getLogin()->createTablePayLogTem()->updateByKey($qr['qr_id'],['ali_beizhu'=> $trade_row['trade_id'],'type'=>62 ] );
                }
            }
            if(  in_array($mc['version'] , [201,211,205,60,63,120,40,13,351,15,145,239,39,90]) &&  $account['ma_user_id']>0 ){
                $this->getLogin()->createVip()->maBillCreate(11,  $account['ma_user_id'],  $trade_row['realprice'] , $trade_row['trade_id'] );
            }
            if( $version==80 ){
                $this->getLogin()->createTableTaobaoQr()->updateByKey($qr['qr_id'],['type'=> 11 ,'trade_id'=> $trade_row['trade_id'] ]);
            }
            if(in_array($version,[320] ) ){
                $this->getLogin()->createTableHfTrade()->updateByKey( $qr['qr_id'], ['type'=>10, 'trade_id'=> $trade_row['trade_id'] ] );
            }

            if(39==$version){
                //开启老码重复利用
                /*
                $oldUpVar= ['ali_beizhu'=> $trade_row['trade_id'], 'type'=>42];
                $oldWhere=['account_id'=>$qr['account_id'],'fee'=> $var['price'],'type'=>41,'ali_beizhu'=>''  ];
                $oldWhere['>']['ctime']= time()-2*3600;
                $this->getLogin()->createQrPay()->payTemOldQrSet( $oldWhere, $oldUpVar );
                */
            }

        }else{
            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );

        }

        if(  $version==50 ){ #订单采宝 去采宝处理下
            $this->getLogin()->createCaibao()->h5post( $trade_row['trade_id'] );
        }

        //$this->log( "create trade_id:" .  $trade_row['trade_id']  );
        //$this->log( $qr );
        if( $_POST['order_user_name'] ){
            $cache = new cache();
            $cache->getRedis()->set( 'TR'. $trade_row['trade_id'], $_POST['order_user_name'] , 300 );
            //$this->getLogin()->createCache()->getRedis()->set( );
        }




        if( 1 ){ //$_POST['format']=='json'

            //if( $version==40 ) $this->log("ali40account>>". $account['user_id'] );
            $act = 'ali';
            if($is_debug_version ) $act = 'ali2';
            //elseif( $qr===-1 )  $act = 'ali23open2';
            elseif( $qr===-1 )  $act = 'ali23load';
            elseif( $version==3 ) $act = 'ali3';
            elseif( $version==4 ) $act = 'ali4';
            elseif( $version==5 ) $act = 'ali4';
            elseif( $version==30 ) $act = 'ali30';
            elseif( $version==32 ) $act = 'ali32';
            elseif( $version==40 && in_array($account['user_id'],[4,4335,4647,5082,5124])) $act = 'ali40s';
            elseif( $version==40 ) $act = 'ali40';
            elseif( $version==45 ) $act = 'ali45';
            elseif( $version==50 ) $act = 'ali50';
            elseif( $version==35 ) $act = 'ali35';
            elseif( $version==351 ) $act = 'ali351';
            elseif( $version==36 ) $act = 'ali36';
            elseif( $version==38 ) $act = 'ali38';
            elseif( $version==22 ) $act = 'ali22';
            //elseif( $version==23 ||  $version==24 ) $act = 'ali23';
            elseif( $version==23 ||  $version==24 ||  $version==201) $act = 'ali23open';
            elseif( $version==39 ) $act = 'ali39';
            elseif( $version==301 ) $act = 'ali301';
            elseif( $version==31 ) $act = 'ali31';
            elseif( $version==78 ) $act = 'ali78';
            elseif( $version==80 ) $act = 'ali80';
            elseif( $version==63 ) $act = 'ali63';
            elseif( $version==65 ) $act = 'ali65';
            elseif( $version==90 ){
                $act = 'ali90';
                if($account['type']==96) $act = 'ali96';
            }
            elseif( $version==28 ) $act = 'ali28';
            elseif( $version==13 ) $act = 'ali13';
            elseif( $version==15 ) $act = 'ali15';
            elseif( $version==120 ) $act = 'ali120';
            elseif( $version==320 ) $act = 'ali320';
            //elseif( $version==139 ) $act = 'ali139';
            elseif( $version==139 ) $act = 'ali139V2';
            elseif( $version==138 ) $act = 'ali138';
            elseif( $version==150 ) $act = 'ali150';
            elseif( $version==145 ) $act = 'ali145';
            elseif( $version==239 ) $act = 'ali239';
            elseif( $version==60 ) {
                $act = 'ali60';
                if( $this->getLogin()->isUnipayYue() )  $act = 'ali61';
            }
            elseif( $version==205 ) $act = 'ali205';

            $act2="v".$version."open";


            $md5= $this->getPaySign( $trade_row['trade_id'] );//md5($trade_row['trade_id'].'adf888');
            //$pay=['url'=>'https://qunfu.readface.cn/api/paylink/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            //$pay=['url'=>'https://'.drFun::getHttpHost().'/api/paylink/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;

            //$pay=['url'=>'https://'.drFun::getHttpHost().'/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            //$pay=['url'=>'https://pay.readface.cn/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            //$pay=['url'=>'https://qf.zahei.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            //$pay=['url'=>'http://pay.fusocq.cn/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;

            //$pay=['url'=>'https://qun.zahei.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            #$pay=['url'=>'http://p.atbaidu.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            #$pay=['url'=>'https://gz.atbaidu.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;

            #腾讯云
            //$pay=['url'=>'https://aw.atbaidu.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;

            #$pay=['url'=>'https://qz.atbaidu.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;


            #$pay=['url'=>'https://qz.atbaidu.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;

            #$pay=['url'=>'https://pz.easepm.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;

            #cloudflare 防止dns
            $pay=['url'=>'https://qz.pteclub.com.cn/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;

            #$pay=['url'=>'https://qd.atbaidu.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;
            $pay=['url'=>'https://qz.becunion.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;
            $pay=['url'=>'https://qz.nekoraw.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;

            if(  $version==40 ){
                //$pay=['url'=>'http://'.substr(md5(uniqid()),0,4).'.atbaidu.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'] ]  ;
            }

            /*
            if( $mc['merchant_id'] == 8111 ){
                //$url2= 'https://'.drFun::getHttpHost().'/api/url/'. implode('/',$p );
                $url2= 'https://qz.atbaidu.com/api/url/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ;
                $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url='.urlencode($url2 );
                //$pay['url']= $url;
            }
            */

            #$pay=['url'=>'https://z.atbaidu.com/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;
            //$pay=['url'=>'https://p.ancall.cn/api/ali/'.$md5.'/'. $trade_row['trade_id'].'/'.$qr['fee'] ]  ;


            $pay['realprice'] = $trade_row['realprice'];
            $pay['price'] = $trade_row['price'];

            if( in_array( $mc['merchant_id'],[8301,8302,8303,8304,8305,8306] ) ){
                $pay['ali_url']= 'https://qz.atbaidu.com/api/'.$act2.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'];
                //$pay['ali_url']= 'https://qz.qmailq.com/api/'.$act2.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice'];
            }
            if(  in_array( $mc['merchant_id'],[8395 ] ) ){
                //$pay['url'] ='https://qz.qmailq.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice']  ;
                $pay['url'] ='https://pz.easepm.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice']  ;
            }
            if(  in_array( $mc['c_user_id'],[2323 ] ) ){
                //$pay['url'] ='https://qz.qmailq.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice']  ;
                //$pay['url'] ='https://qz.qmailq.com/api/'.$act.'/'.$md5.'/'. $trade_row['trade_id'].'/'. $trade_row['realprice']  ;
            }

            $pay['sign'] =   $this->getTrade()->setSecret( $app_secret  )->createSign( $pay );
            //$this->log("pay_data>>".  $pay['sign']."\t". $this->getTrade()->getMd5Str() );
            $this->assign('pay_data', $pay);

            if(in_array( $mc['merchant_id'],[ 8201,8202,8080] ) ){
                $this->assign('pay_attr',['account_no'=> $account['account'] ] );
            }
            if( $qr['account_id'] && in_array( $mc['merchant_id'], [8840,8855,8080,8874] )){
                try {
                    $account = $this->getLogin()->createTablePayAccount()->getRowByKey($qr['account_id']);
                    $bank= $this->getLogin()->createQrPay()->getBankType($account['bank_id']);
                    $vb=['name'=>$account['zhifu_name'],'card'=>substr( $account['zhifu_account'],-4),'bank'=>$bank['n'] ];
                    //$this->log("api_pay_account_id>>" . $qr['account_id'] . " " . $mc['merchant_id']. " ".json_encode( $vb) );
                    $this->assign('pay_bank', $vb );
                }catch (drException $ex ){
                    $this->log("api_pay_account_id error>>".$qr['account_id']."\t" .  $account['zhifu_account'] ."\t". $ex->getMessage() );
                }
            }
            return ;
        }

        //$this->drExit( $qr );
        //$this->drExit($_POST);
        $timeLimit = $trade_row['ctime']+180-   time();
        $qr_var=['price'=>number_format($qr['fee']/100, 2)  ,'url'=>$this->getQrUrlSign($qr)
            ,'timeLimit'=> $timeLimit ,'trade_no'=> $trade_row['trade_id'],'mc_id'=>$mc['merchant_id'],'qr_url'=>$qr['qr_text'] ];
        $this->assign('qr_var', $qr_var );
        $this->assign('post', $_POST );
    }

    function getYouPrice( $user_id){

        try {

            $sv['youPrice'] = intval($this->getLogin()->redisGet('youPrice' . $user_id));
            if ($sv['youPrice'] <= 0) $sv['youPrice'] = 5000;

            return $sv['youPrice'] * 100;
        }catch (drException $ex){
            return 5000*100;
        }
    }


    function getYue($mid){
        try{
            $str = $this->getLogin()->redisGet('mYue'. $mid );
            if(!$str) return 0;
            $yue = json_decode($str ,true);

            //$this->log("yue>>[".$yue['yue']."]".$str );

            return intval($yue['yue']);

        }catch (drException $ex ){

        }

        return 0;
    }



    function act_ali145( $p ){
        ///$this->drExit($p);

        //$this->htmlFile="ali145.phtml";
        $sign= trim($p[0] );
        $id = $p[1];
        try{
            //$this->drExit( $p );
            $this->assign('_cdn', drFun::getCdn() );
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            if ( ! in_array( $trade_row['type'] , $this->getLogin()->createQrPay()->getTypeTradeUsingLimit() )  && !isset($_GET['no'])) $this->throw_exception("支付成功或者已超时！", 456);

            $limitTime = $trade_row['ctime']+300- time();
            if( $_GET['no'] ){
                $limitTime=200;
            }
            if( $limitTime <5) $this->throw_exception("已经超时",502);

            $qf_ck= $_COOKIE['qf'];
            if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
            drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

            $client= drFun::getClientV2();

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
            $this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>3 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );
            //$this->getLogin()->createQrPay()->upTradeByID($id,['ali_uid'=>$account['ali_uid'] ,'type'=>4 , 'ip' => drFun::getIP() ,'cookie'=>$qf_ck  ,'client'=> drFun::getClientV2()] );

            $this->assign( 'client', $client  )->assign('trade', $trade_row );
            $account= $this->getLogin()->createQrPay()->getAccountByID($trade_row['account_id']  );
            $this->assign('account',$account);
            $bankType = $this->getLogin()->createQrPay()->getBankType();

            $hongbao=['price'=> $trade_row['realprice']/100 ,'b_name'=> $account['zhifu_name']];
            $hongbao['b_account']= $account['zhifu_account'];
            $hongbao['b_add']= $account['zhifu_realname'];
            $hongbao['b_bank']=$bankType[ $account['bank_id'] ]['n'];
            $hongbao['time']= $limitTime;
            $hongbao['you']= ($trade_row['price']-$trade_row['realprice'])/100 ;

            $this->assign('hongbao',$hongbao);

            $this->htmlFile='app/ali145.phtml';
            if($_GET['ds']==2 || in_array($trade_row['user_id'],[] )){ //2333
                $self_url = 'https://'.drFun::getHttpHost().'/api/v40open/'. implode('/',$p );
                $this->assign('copy_url', '付款 '.($trade_row['realprice']/100).'元 '.$self_url);
                $this->htmlFile='app/ali145v2.phtml';
            }
            if(3== $_GET['ds']  || in_array($trade_row['user_id'],[4335,4467,4468 ,4902,5063 ]) ){
                $this->htmlFile='app/ali145v3.phtml';
            }

            $url=  'https://'.drFun::getHttpHost().'/api/v40open/'. implode('/',$p );
            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url  ) ;;
            $this->assign('url4', $url4);

            if($_GET['s']=='wechat') $this->htmlFile='app/ali40_wechat.phtml';
            if($_GET['s']=='alipay' || in_array($trade_row['merchant_id'],[8824,8880,8974]) ) $this->htmlFile='app/ali40_alipay.phtml';
            if($_GET['s']=='bank') $this->htmlFile='app/ali40_bank.phtml';

            if( in_array($trade_row['user_id'],[2333] ) ){
                $this->htmlFile='app/ali40_alipay_v2.phtml';
            }



        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function pay145( $var , $mc ){



        if(  $this->getLogin()->isShoudanV2(  $mc['c_user_id'] ) ) {
            $mc['clear_account']=1;
            $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
            return $qr;
        }elseif( $this->getLogin()->isShoudanV1(  $mc['c_user_id'] ) ) {
            $qr = $this->getLogin()->createQrPay()->getLiveQrV201($var['price'], $mc);
            return $qr;
        }else{
            $this->throw_exception("请先联系管理员设置模式",20060801);
        }


        return [];
    }



    function act_dtest(){
        $id= intval($_GET['id']);
        if( ! $id ) $id = '20181010478';
        $this->drExit(  );
        $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
        $cl_trade= new trade();
        $str='';
        $cl_trade->notify($trade_row,$str);
        $this->drExit( $str );
    }

    function act_me($p){
        $qf_ck= $_COOKIE['qf'];
        switch ($p[0]){
            case 'cid':
                if($p[1] )$qf_ck= trim( $p[1] );
                $this->assign('cookie', $qf_ck );
                $where['cookie']= trim( $qf_ck );
                $mlist =  $this->getLogin()->createQrPay()->getTradeWithPage( $where  ) ;

                $acc_id=[];
                drFun::searchFromArray($mlist['list'], ['account_id'],$acc_id  );

                $account=  $account_all = $this->getLogin()->createQrPay()->getAccountIDByWhere(['account_id'=> array_keys($acc_id) ] ,['all'=>1]  );

                $this->assign('list', $mlist )->assign('acc', $acc_id)->assign('account',$account );

            break;
            default:
        }

        $this->assign('cook_uid', $qf_ck);
        $this->htmlFile="app/qf_me.phtml";
    }

    function act_ddtest(){

        /*
        $feee= $this->getLogin()->createQrPay()->getLiveQr(9999 );
        $this->drExit(  $feee );
        */


    }

    function getPaySign( $tr_id ,$mc='adf888' ){
        $md5= md5($tr_id . $mc);
        return $md5;
    }




    function getQrUrlSign( $qr ){
        $time= time();
        $id = $qr['qr_id'] ;
        $url='/api/qr/'.$id.'/'.$time.'/'. $this->getSign($id,$time ) ;
        return $url;
    }
    function getSign( $id,$time){
        return  substr( md5( $id.'adsf45566'.$time),2,6 );
    }



    function act_ex( $p ){


        $this->setDisplay('json');

        $mc = $this->getLogin()->createQrPay()->getMerchantByAppID($_POST['app_id']);
        $var = $_POST;
        $this->signConfirm($mc, $var);


        switch ($p[0]) {
            case 'orderCheck':
                if( $var['order_no']=='' )  $this->throw_exception("提现号不应该不应该为空"  ,19202);
                $row = $this->getLogin()->createTableMcExport()->getRowByWhere( ['merchant_id' => $mc['merchant_id'], 'order_no' => $var['order_no']] );
                if( !$row ) $this->throw_exception("该订单不存在！", 19092408);
                $this->assign('order', $row);
                break;
            case 'yue':
                if( abs($var['time']-time() )>60 ) $this->throw_exception("请控制时间误差60s内",19092407);
                $yue = $this->getLogin()->createQrPay()->getMerchantYue($mc['merchant_id'], [] );
                unset($yue['user_id'] );
                unset($yue['ctime'] );
                $this->assign('yue', $yue );
                break;
            default:
                if( $var['order_no']=='' )  $this->throw_exception("提现号不应该不应该为空"  ,19202);
                $yue = $this->getLogin()->createQrPay()->getMerchantYue($mc['merchant_id'], [] );

                $is_check = ! in_array($mc['merchant_id'],[8080]  ); #不需要 余额验证的搞进来

                if( $is_check && $var['money'] > $yue['yue']['yue']  )  $this->throw_exception("提现金额请勿超过余额！", 19092409);

                $mc_export = new table();
                $mc_export->setTable('mc_export');//->setKeyFile('export_id');
                $cnt = $mc_export->getCount(['merchant_id' => $mc['merchant_id'], 'order_no' => $var['order_no']]);
                if ($cnt>0) $this->throw_exception("提现号已经存在,请勿重复提交！", 19201);
                $var['ctime'] = time();
                $var['merchant_id'] = $mc['merchant_id'];
                $var['merchant_user_id'] = $mc['user_id'];
                $var['type'] = 1;
                $trade = new trade();
                $var['real_money']= $trade->getExRealMoney($var,  $var['type'] ,$yue);

                if( $is_check &&  abs( $var['real_money'])> $yue['yue']['yue'] ) $this->throw_exception( "下发金额大于余额！",19092409  );

                $c_user_id= $this->getLogin()->midConsole( $mc['merchant_id'] );
                $var['cz_user_id']=  $c_user_id[0] ;
                $lastid =  $this->getLogin()->createTableMcExport()->append( $var)->lastID(); ;//$mc_export->append($var)->lastID();
                $rz = ['ex_id' => $lastid];
                $rz['sign'] = $this->getTrade()->createSign($rz);
                $this->assign('ex', $rz);
        }
    }

    function act_exConfirm(){

        $this->throw_exception("已经弃用");

        $mc=$this->getLogin()->createQrPay()->getMerchantByAppID( $_POST['app_id']);
        $var = $_POST;
        $this->signConfirm( $mc, $var );

        $mc_export= new table();
        $mc_export->setTable('mc_export');
        $row = $mc_export->getRowByKey( $var['ex_id'] );
        if( !$row ) $this->throw_exception( "该提现记录不存在！" , 19203);
        if($row['merchant_id']!=$mc['merchant_id'] ||  $row['order_no']!=$var['order_no'] ){
            $this->throw_exception( "该记录与商户不匹配！" , 19204);
        }

        if( !in_array( $var['type'],[21,22]))     $this->throw_exception("状态重置错误"  ,19205);

        $mc_export->updateByKey( $var['ex_id'],['type'=> $var['type']] );
        $this->setDisplay('json');
        $this->assign('ex', ['stats'=>'ok']  );
    }

    function signConfirm( $mc, $var ){
        $old_sign= $var['sign'];
        unset($var['sign'] );
        $this->getTrade()->urlDecode( $var );
        $sign = $this->getTrade()->setSecret( $mc['app_secret'] )->createSign( $var );
        //$this->drExit(  $_POST['sign']."\n\n". $sign. "\n\n<br>\n\n". $this->getTrade()->getMd5Str() );
        if( $old_sign !=$sign ){
            $this->log("[ex]post>>right=". json_encode( $var ) );
            $this->log("[ex]sign_error>>right=".$sign."\tp=".$old_sign. "\t".$this->getTrade()->getMd5Str() );
            $this->throw_exception("错误签名错误！"  ,20180801);
        }

        return $this;
    }

    function act_caibao( $p){
        $this->log( "\n=====caibao= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true)
            ."\n-------\n".print_r( $_GET,true) );

        $pay= $_POST;

        /*
        $pay=[
            'totalAmount' => '10',
            'receiveAmount' => '10',
            'appOrderNo' => '20181010609',
            'payTime' => '1548244499000',
            'subject' => 'VIP',
            'sign' => 'cTnj+MTJEyJiSU+N2Bard/FULulmRp8QSSMPgG7jdLi+70wf5gF4P9vw9csLEPm1l/qRrmRD6ETGvLBOCTzidL9wXKVlR8akWF5oVfM4HG5xnue8qLjdAvuQ8ZfHAZdqIq2t7ZItrlqEn3nxz8t5zxljhTYC7nhcu3wTEMofMCq+CjMNJdlo3dJOORGyN0r5s2Oc9krFztsADSoeYaaBDpxz2Vv6TefY5XY07RRdPKvQ+LiLDGkkC1+XXP7JYFQX6HaB8MHqiOaURxJfAj8Hy/0Fl45CgF+DH56zKQVnDdtydwfAomqF3hn8TaWaMpV0Vl1wvXZHyTvX6rSBNZslZA==',
            'orderStatus' => 'PAY_SUC',
            'discountAmount' => '0',
            'paymentWay' => 'QRCODE',
            'outOrderNo' => '562019012322001447181012511153',
            'cbOrderNo' => 'TCAP1901231954476922950027',
            'paymentChannel' => 'ALIPAY'
        ];
        */

        //$this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );


        $payLog=[];
        //$payLog['opt_id'] =
        $trade_id = $pay['appOrderNo'];
        $payLog['ltime'] = $pay['payTime'];
        $payLog['ctime'] = $pay['payTime']/1000;
        $payLog['fee'] = $pay['receiveAmount'];
        $payLog['ali_trade_no'] = $pay['cbOrderNo'];
        $payLog['ip'] = drFun::getIP() ;
        $payLog['pay_type'] = ($pay['paymentChannel']=='WECHAT'?52: 51 ); //[paymentChannel] => ALIPAY
        unset( $pay['sign']);




        try{

            if( strpos($pay['appOrderNo'],'S_' )){
                $arr = explode('_', $pay['appOrderNo'] );
                $trade_id=  $arr[2] ;
                $account = $this->getLogin()->createQrPay()->getAccountByID( $arr[1] );
                $payLog['account_id']= $arr[1];
                $log =  new log('pay_log', $account['user_id'] );
                $log->append($trade_id ,10 ,$pay, $payLog);
                if(! in_array( $account['online'],[1,11,4]) ){
                    $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ,'clienttime'=>time() ] );
                }
                $this->drExit('ok');
            }


            $trade = $this->getLogin()->createQrPay()->getTradeByID( $trade_id );
            if( $trade['realprice']==$payLog['fee'] ){
                $payLog['trade_id']= $trade_id;
            }
            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade['account_id'] );

            $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , [ 'clienttime'=>time() ] );

            $payLog['account_id']=$trade['account_id'];
            $log =  new log('pay_log', $account['user_id'] );
            $log->append($trade_id ,10 ,$pay, $payLog);
            $pay_log_id= $log->createSql()->lastID();
            if( $trade['realprice']==$payLog['fee'] ){
                $this->getLogin()->createQrPay()->upTradeByID( $trade_id, ['pay_time'=> $payLog['ctime'], 'type'=>1,'pay_log_id'=>$pay_log_id ]);
                $this->getLogin()->createQrPay()->toMqTrade( $trade );
            }
        }catch ( drException $ex ){
            $str = "error[".$ex->getCode()."]". $ex->getMessage() ;
            $this->log( "\n=====caibao===error=== ".date("Y-m-d H:i:s")."=====\n".  $str );
            $this->drExit( $str );
        }
        $this->drExit('ok');
    }

    function act_hx($p){
        $var = $_POST;
        $this->log( "=====hx=== ".date("Y-m-d H:i:s").'===\t'.json_encode( $var )."\n",'hx.log' );
        switch ($var['act']){
            case 'tbcode':
                $arr=['code'=>1,'data'=>['2827483986','2676784761'] ];

                $this->drExit( json_encode( $arr));
                break;
            case 'tbdata':
                $arr=['code'=>1,'msg'=>'ok' ];
                $this->drExit( json_encode( $arr));
                break;

        }
        $arr=['code'=>-1,'msg'=>'好像没获取到数据' ];
        $this->drExit(json_encode( $arr));
    }

    function act_ali50( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v50open/'. implode('/',$p );
            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4='alipays://platformapi/startApp?appId=10000011&url='.urlencode($url2 );
            $this->htmlFile='app/alishowV50.phtml';
            //8287
            if( in_array($trade_row['merchant_id'],[8287,8080] ) ){
                $this->htmlFile='app/alishowV50wx.phtml';
            }
            $this->assign('url4',$url4)->assign('url',$url )->assign('client', $client)->assign('trade', $trade_row );
        }catch ( drException $ex ){
            $this->logErr("错误50ID:". $id );
            $this->apiError( $ex );
        }

    }
    function act_v32open( $p ){

            $sign= trim($p[0] );
            $id = $p[1];

            try {

                $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
                if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

                $md5 = $this->getPaySign($id);//md5($id.'adf888');
                if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

                $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
                // (time()- $trade_row['ctime']) >180 ||
                if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);


                $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 3]); //'ali_uid'=>31 ,
                $account = $this->getLogin()->createQrPay()->getAccountByID($trade_row['account_id']);
                // $bizData = ['a' => ($trade_row['realprice']/100).'' , 's' => 'online', 'u' => $account['ali_uid']  ];

                $url='alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={\"s\": \"money\", \"u\": \"'.$account['ali_uid'].'\", \"a\": \"'.($trade_row['realprice']/100).'\", \"m\": \"='.$id.'=\"}';

                $this->htmlFile='app/v32open.phtml';
                $this->assign('url', $url);
            }catch (\Exception $ex ){
                $this->logErr("错误ID:". $id );
                $this->apiError( $ex );
            }

    }
    function act_ali32($p){
        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v32open/'. implode('/',$p );
            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4='alipays://platformapi/startApp?appId=10000011&url='.urlencode($url2 );
            //$url4='alipays://platformapi/startapp?appId=20000067&url='.urlencode($url );
            //
            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id'] );


            $sch= 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$account['ali_uid'].'","a":"'.( $trade_row['realprice']/100).'","m":"'.($trade_row['trade_id']).'"}';
            $url4='https://render.alipay.com/p/s/i/?scheme='. urlencode( $sch );
            $url4= $url ;
            //$this->redirect($url4 );
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url4 )->assign( 'client', $client  );
            $this->htmlFile='app/alishowV32.phtml';
            $this->htmlFile='app/alishowV32v2.phtml';
            if($_GET['ds']){
                $this->htmlFile='app/alishowV32v2.phtml';
            }

            //$this->htmlFile='app/alishowV35t2.phtml';

            //$url2='https://ds.alipay.com/?from=mobilecodec&scheme=alipays%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007%26clientVersion%3D3.7.0.0718%26qrcode%3Dhttp%3A%2F%2Fhdy.e535.com%2Fwap%2FhbJump%2F743aff0aa5fd4d33829be9ffc9ec53c3';

        }catch ( drException $ex ){
            $this->logErr("错误32ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali35( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v35open/'. implode('/',$p );
            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4='alipays://platformapi/startApp?appId=10000011&url='.urlencode($url2 );
            //$url4='alipays://platformapi/startapp?appId=20000067&url='.urlencode($url );

            //$this->redirect($url4 );
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url )->assign( 'client', $client  );
            $this->htmlFile='app/alishowV35.phtml';
            $this->htmlFile='app/alishowV35t2.phtml';

            //$url2='https://ds.alipay.com/?from=mobilecodec&scheme=alipays%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007%26clientVersion%3D3.7.0.0718%26qrcode%3Dhttp%3A%2F%2Fhdy.e535.com%2Fwap%2FhbJump%2F743aff0aa5fd4d33829be9ffc9ec53c3';

        }catch ( drException $ex ){
            $this->logErr("错误50ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali351( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {


            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            $client = drFun::getClientV2();
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            else{
                $key= 'ks_'.$qf_ck;
                if( $this->getLogin()->createCache()->getRedis()->get($key )){

                    $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 2, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
                    $this->throw_exception("你输错口令，请5分钟后再来下单");
                }
            }
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ( $trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $this->assign('trade',$trade_row )->assign( 'client', $client  );

            $this->assign('url', ['code'=>'/api/q351/code/'.$trade_row['trade_id'],'query'=> '/api/q351/query/'.$trade_row['trade_id']  ]);

            $this->htmlFile='app/ali351.phtml';


            //$url2='https://ds.alipay.com/?from=mobilecodec&scheme=alipays%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007%26clientVersion%3D3.7.0.0718%26qrcode%3Dhttp%3A%2F%2Fhdy.e535.com%2Fwap%2FhbJump%2F743aff0aa5fd4d33829be9ffc9ec53c3';

        }catch ( drException $ex ){
            $this->logErr("错误50ID:". $id );
            $this->apiError( $ex );
        }
    }

    function getAliUrl( $url ){
        $url4= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
        $ali_url = $url5="https://ds.alipay.com/?from=mobilecodec&scheme=".urlencode( $url4);
        return  $ali_url;
    }

    function act_ali320( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try{

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ( !in_array($trade_row['type'] ,[3,4,0]) && !isset($_GET['no'])) $this->throw_exception("超时或者已经成功！", 456);
            $client = drFun::getClientV2();

            $qr= $this->getLogin()->createTableHfTrade()->getRowByKey( $trade_row['qr_id'] );
            $qr['opt_value']= drFun::json_decode( $qr['opt_value'] );


            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 3, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/ali320/'. implode('/',$p );

            $this->htmlFile='app/ali320.phtml';

            $this->assign('qr', $qr['opt_value'])->assign('trade', $trade_row)->assign('url', $url );
            $this->assign('client', $client);

            //$this->drExit( $qr );

        }catch ( drException $ex ){
            $this->logErr("错误320 ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali150( $p ){

        //$this->drExit( $p );
        $this->htmlFile='app/alishowV35t2.phtml';
        $sign= trim($p[0] );
        $id = $p[1];
        try{


            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();

            $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);
            $qr['data']= drFun::json_decode( $qr['data'] );
            //清空群
            drFun::aliClearQunMember( $qr['account_ali_uid'] , $qr['ali_trade_no']);


            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            //$this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            $url =$qr['data']['qr'];

            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url  ) ;
            //$url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url  ) ;
            //$ali_url = $url5="https://ds.alipay.com/?from=mobilecodec&scheme=".urlencode( $url4);


            //if( in_array( $trade_row['merchant_id'],[8201,8202,8200, 8501 ] )  ){
                //$ali_url= $url;

            //}
            $this->assign('auto',0);

            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url );//->assign('auto',1 );
            $this->assign( 'client', $client  );

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $this->assign('msgTips', "加群发红包");
            $this->htmlFile='app/alishowV35qun.phtml';



        }catch (\Exception $ex ){
            $this->logErr("错误120 ID:". $id );
            $this->apiError( $ex );
        }


    }

    function act_ali239($p){
        $sign= trim($p[0] );
        $id = $p[1];
        try{

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();

            $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);
            $qr['data']= drFun::json_decode( $qr['data'] );
            //清空群
            drFun::taoQunClear( $qr['account_ali_uid'] , $qr['ali_trade_no']);

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 3, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url =$qr['data']['qr'];
            $url4=$tb_url= $url= $url;// strtr( $url,['https'=>'taobao']);

            $this->assign('auto',0);

            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url );//->assign('auto',1 );
            $this->assign( 'client', $client  )->assign('tb_url',$tb_url);

            $this->assign('msgTips', "加群发红包");
            $this->htmlFile='app/taobaoqun.phtml';
            drFun::taoQunClear( $qr['account_ali_uid'] , $qr['ali_trade_no']);


        }catch ( drException $ex ){
            $this->logErr("错误239 ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali120( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            //$this->drExit( $p );

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();

            $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);
            $qr['data']= drFun::json_decode( $qr['data'] );
            //清空群
            drFun::clearQunMember( $qr['account_ali_uid'] , $qr['ali_trade_no']);


            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/v120open/'. implode('/',$p );

            if( $this->isWx() ) $this->redirect(  $url );

            #$qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);
            #$this->drExit( $qr );

            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url );

            $this->htmlFile='app/alishowV35wx.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误120 ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali22( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();
            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/v22open/'. implode('/',$p );

            if( $this->isWx() ) $this->redirect(  $url );

            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url );
            $this->htmlFile='app/alishowV35wx.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali23( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();
            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/v23open/'. implode('/',$p );

            if( $this->isWx() ) $this->redirect(  $url );

            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url );
            $this->htmlFile='app/alishowV35wx.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_trade( $p ){
        $this->setDisplay('json');
        $sign= trim($p[0] );
        $id = $p[1];

        if( $sign!= $this->getLogin()->createVip()->getSign( $id  )  ) $this->throw_exception("非法访问");

        $trade = $this->getLogin()->createQrPay()->getTradeByID( $id );
        $this->assign('trade', $trade );

    }

    function act_ali23qiang( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $url= 'https://'.drFun::getHttpHost().'/api/ali23open2/'. implode('/',$p );
            if( $trade_row['qr_id']>0) $this->redirect( $url  );

            /*
            if(  in_array($trade_row['merchant_id'],  [8393] )  ){
                if( ! in_array( $trade_row['lo'],['广东','湖南'] ))  $this->redirect( $url  ); // ,'北京'
            }
            */

            if ( !in_array($trade_row['type'], [0,4])  && !isset($_GET['no'])  ) $this->throw_exception("支付成功或已过期！", 456);

            $trade_row['sign']= $this->getLogin()->createVip()->getSign( $id  );

            $this->pushTradeQiang( $trade_row );

            $this->assign('trade', $trade_row )->assign('url', $url );

            //$this->drExit( $trade_row  );
            $this->htmlFile='app/ali23qiang.phtml';
            if($_GET['ds']){

            }
            $this->htmlFile='app/ali23qiangV2.phtml';

        }catch ( drException $ex ){
            $this->logErr("ali23qiang ID:". $id );
            $this->apiError( $ex );
        }


    }

    function pushTradeQiang( $trade , $opt=[]){
        $cudi = $this->getLogin()->midConsole( $trade['merchant_id'] );

        drFun::icometTrade( 'ali23qiang'.$cudi[0] ,$trade );
    }

    function act_ali23load( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            //drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();
            $this->getLogin()->createQrPay()->upTradeByID($id, [  'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/ali23open2/'. implode('/',$p );

            $version = $this->getLogin()->getVersionBYConsole(  $trade_row['user_id'] );

            if( $version==40)  $url= 'https://'.drFun::getHttpHost().'/api/ali40open2/'. implode('/',$p );
            elseif( $version==15)  $url= 'https://'.drFun::getHttpHost().'/api/ali15open2/'. implode('/',$p );

            //是否支持人工抢单
            if(   $this->getLogin()->createQrPay()->isQiang(  $trade_row['merchant_id'] )   ){ #抢单

                $version= $this->getLogin()->createVip()->getQiangAccountTypeByCuid( $trade_row['user_id']);
                //205支付宝 201微信
                $url= 'https://'.drFun::getHttpHost().'/api/ali23qiang/'. implode('/',$p );
            }

            //if( $this->isWx() ) $this->redirect(  $url );

            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url );
            $this->htmlFile='app/ali23load.phtml';

        }catch ( drException $ex ){
            $this->logErr("ali23load ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali15open2( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try{

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);

            //$this->drExit($p );

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            if ( !in_array($trade_row['type'], [0,3,4]) ) $this->throw_exception("支付成功或已过期！", 456);
            $opt=['type'=>14 ,'version'=>15,'trade_type'=>0 ];
            $trade_row = $this->payBack( $trade_row ,$opt  );

            if( $trade_row['account_id']<=0 ) $this->throw_exception("无足够的码！");

            //$qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            //if(  !$qr ) $this->throw_exception( "非法支付！");

            //$this->drExit( $trade_row );

            //$this->act_ali40( $p );

            $this->act_ali15( $p );



        }catch (drException $ex ){
            $this->logErr("ali40open2 ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali40open2( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try{

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);

            //$this->drExit($p );

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            if ( !in_array($trade_row['type'], [0,3,4]) ) $this->throw_exception("支付成功或已过期！", 456);
            $opt=['type'=>[147, 148] ,'version'=>40 ];
            $trade_row = $this->payBack( $trade_row ,$opt  );

            if( $trade_row['account_id']<=0 ) $this->throw_exception("无足够的码！");

            //$qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            //if(  !$qr ) $this->throw_exception( "非法支付！");

            //$this->drExit( $trade_row );

            $this->act_ali40( $p );



        }catch (drException $ex ){
            $this->logErr("ali40open2 ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali23open2( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {
            //$this->drExit($p);
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);

            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $opt=[];
            //if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            // if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            if ( !in_array($trade_row['type'], [0,4]) ) $this->throw_exception("支付成功或已过期！", 456);

            $is_cc=  in_array(  $trade_row['merchant_id'],[83911] );
            $opt['trade_type']= $is_cc?3:4;
            $trade_row = $this->payBack( $trade_row ,$opt  );
            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");

            $url = 'https://' . drFun::getHttpHost() . '/api/v23open/' . implode('/', $p);
            //$url = 'https://qz.becunion.com/api/v23open/' . implode('/', $p);
            if( $is_cc ) {
                $url=  $qr['qr_text'];
            }



            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            //$hongbao['uid'] = $account['ali_uid'];//自己的UID
            //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            $hongbao['url'] = $url ;//支付二维码 //=  $qr['qr_text']


            $hongbao['ctime']= time();
            $hongbao['no_ip']= 1 ;
            $this->assign('trade', $trade_row )->assign("hongbao" ,$hongbao);
            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url );

            $this->htmlFile='app/v23openV3.phtml';
        }catch ( drException $ex ){
            $this->logErr("ali23open2 ID:". $id );
            $this->apiError( $ex );
        }
    }

    function checkHei($trade_row, $qf_ck, $opt=[]){
        $cnt = $this->getLogin()->createTableHei()->getCount( ['user_id'=>$trade_row['user_id'], 'cookie'=>$qf_ck] );
        if( $cnt>0) {

            if($opt['up']) $var=$opt['up'];
            $var['type']=2;
            $this->getLogin()->createQrPay()->upTradeByID($trade_row['trade_id'] ,$var );

            $this->throw_exception( "订单：".$trade_row['order_no']." <br> 已被加入黑名单，解除请联系客服！" );
        }

        return $this ;
    }
    //连续5单失败，限制10分钟。   连续10单失败限制2小时。
    function checkHei10( $trade_row , $qf_ck, $opt=[] ){
        $where=['cookie'=>$qf_ck ];
        $tall = $this->getLogin()->createQrPay()->getTradeByWhere( $where ,['limit'=>[0,30] ,'order'=>['trade_id'=>'desc'] ] );
        //$re=['cnt'=>0,'5time'=>0, ];
        $re=[];
        foreach( $tall as $v ){
            if( in_array($v['type']  ,[1,11])) break;
            $re[]=['t'=> $v['ctime']];
        }
        if( !$re ) return $this;

        $config=[['k'=>9,'t'=>60*60,'e'=>'请1小时之后再尝试'] , ['k'=>4,'t'=>10*60 ,'e'=>'请10分钟之后再尝试'] ];
        $error=[];
        foreach( $config as $v ){
            $k= $v['k'];
            if( isset($re[$k]) &&  $re[ $k]['t']>(time()-$v['t'])  ){
                $error= $v;
                break;
            }
        }
        if( $error ) {
            if($opt['up']) $var=$opt['up'];
            $var['type']=2;
            $this->getLogin()->createQrPay()->upTradeByID($trade_row['trade_id'] ,$var );
            $this->throw_exception(  " 下单过于频繁,".$error['e'] );
        }
        return $this;
    }

    /**
     * 支付后分配
     * @param $trade_row
     * @return array
     * @throws drException
     */
    function payBack( $trade_row ,$opt=[] ){

        if(  $trade_row['qr_id']!=-1 ) return $trade_row;

        $qf_ck = $_COOKIE['qf'];
        if (!$qf_ck) $qf_ck = drFun::rankStr(8);
        drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

        $ip= drFun::getIP();
        $client = drFun::getClientV2();
        $var=[ 'cookie' => $qf_ck, 'ip' =>  $ip, 'client' => $client  ];
        $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

        if( $trade_row['lo']=='' ){
            $ip_lo= $this->getLogin()->createIpCity()->find( $ip , 'CN');
            $var['lo']= strtr($ip_lo[1],['省'=>'','市'=>'' ] );
            $var['lc']=  $ip_lo[2];
        }

        #优惠金额
        $H= date("H");//H:i:s
        $f_price= $this->getYouPrice( $trade_row['user_id'] );

        $isYou= $trade_row['price']>=$f_price || $H>=23 || $H<=6 ;
        if( $trade_row['realprice']==$trade_row['price'] && in_array($trade_row['user_id'],[4,2333]) && $isYou ){
            $price= $this->getLogin()->createQrPay()->getRealPrice( $trade_row['price'] );
            $this->log("realPrice>>". $price."\t". $trade_row['trade_id']);
            $var['realprice']=$price;
        }
        foreach( $var as $k=>$v ) $trade_row[$k]= $v ;


        $qr= $this->getLogin()->createQrPay()->getBackQRV201( $trade_row ,$opt );

        if( !$qr ) {
            $var['type']=2 ; //超时
            $this->getLogin()->createQrPay()->upTradeByID( $trade_row['trade_id'] ,$var);
            $this->throw_exception("抱歉！充值<b>".($trade_row['realprice']/100)."元</b>，没有足够的收款账号！<p >请<b  style='color: red'>尝试其他金额</b>，重新下单</p>", 90613003);
        }

        $var['account_id']= $qr['account_id'];
        $var['qr_id']= $qr['qr_id'];
        $var['ma_user_id']= $qr['ma_user_id'];
        $var['type']=3;
        if( isset( $opt['trade_type']) ) $var['type']= intval( $opt['trade_type'] );




        if(  $var['ma_user_id']>0 ){
            $wh=['beizhu'=>$trade_row['trade_id'] ];
            $cnt=  $this->getLogin()->createTableMaBill()->getCount( $wh );
            if( $cnt>0 ) {
                $this->log($trade_row['trade_id']. " 请勿重复付款，请重新下单！ ");
                $this->throw_exception( "请勿重复付款，请重新下单！", 20073101 );
            }else{
                $this->getLogin()->createVip()->maBillCreate(11, $var['ma_user_id'], $trade_row['realprice'], $trade_row['trade_id']);
            }
        }
        $trade_row = $this->getLogin()->createQrPay()->upTradeByID( $trade_row['trade_id'] ,$var)->getTradeByID( $trade_row['trade_id'] );
        return $trade_row;
    }

    function act_ali23open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $client = drFun::getClientV2();
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];
            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]);

            //修改付款码 奖励的让同一用户分配在 同一个码上
            //if( isset($_GET['no']) ) $trade_row= $this->getLogin()->createQrPay()->changQr($trade_row, $qf_ck  );
            if(  in_array($trade_row['merchant_id'], [8229,8387 ]) ) $trade_row= $this->getLogin()->createQrPay()->changQr($trade_row, $qf_ck  );


            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");

            $url = 'https://' . drFun::getHttpHost() . '/api/v23open/' . implode('/', $p);
            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            $hongbao['uid'] = $account['ali_uid'];//自己的UID
            //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            $hongbao['url'] = $url= $url;  //支付二维码 $qr['qr_text'];
            $hongbao['ctime']= time();
            $this->assign('trade', $trade_row )->assign("hongbao" ,$hongbao);
            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url );

            $this->htmlFile='app/v23openV3.phtml';


        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_v205open( $p ){


        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) $this->throw_exception("请使用支付宝扫一扫", 460);


            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
            // (time()- $trade_row['ctime']) >180 ||
            if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

            //$account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");

            header('Location: '.$qr['qr_text']);
            $this->drExit();

        }catch (drException $ex){

            $this->logErr("错误205ID:". $id );
            $this->apiError( $ex );
        }


    }



    function act_ali13($p ){
        $this->act_ali205($p );
    }

    function act_ali15( $p ){

        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            //if ( ! (in_array( $trade_row['type'],[0,3,4] ) || isset($_GET['no'])) ) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];

            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]); //->checkHei10( $trade_row, $qf_ck ,['up'=>$var ] );

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' =>4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            //if(  !$qr ) $this->throw_exception( "非法支付！");

            $url= 'https://'.drFun::getHttpHost().'/api/v205open/'. implode('/',$p );

            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            $hongbao['uid'] = $account['ali_uid'];//自己的UID
            //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            $hongbao['url'] = $url  =  '/api/aliBill/trade/'. $trade_row['trade_id'];// $qr['qr_text'];//支付二维码
            $hongbao['ctime']= time();
            $hongbao['qr_url']= $qr['qr_text'];


            $hongbao['card_index']=  $account['card_index'];//

            $this->assign('trade', $trade_row )->assign("hongbao" ,$hongbao);
            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url ) ;

            $this->assign('account', $account );

            //$this->htmlFile='app/v205openV6.phtml';
            $this->htmlFile='app/ali15.phtml';

            if( $this->isAli()){
                $this->log("toAliPay>>". $id );
                $this->getLogin()->createQrPay()->upTradeByID($id, ['type' =>3]);
                $this->redirect(  $qr['qr_text'] ,"");
            }

            if($_GET['ds']==1 || in_array($trade_row['user_id'],[2650])){
                //$this->htmlFile='app/ali15v2.phtml';
            }
            if( $_GET['ds']==2 || in_array( $trade_row['merchant_id'],[ ] )){
                $this->htmlFile='app/ali15v3.phtml';
            }

            try {
                $v15 = $this->getLogin()->redisGet('v15u' . $trade_row['user_id']);
                if ($v15 == '1' ) { #|| $trade_row['realprice']<30000
                    $this->htmlFile = 'app/v205openV8.phtml';
                    $this->htmlFile='app/ali15v4.phtml';
                }


            }catch (Exception $ex ){}

        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
        //$this->drExit( $p );
    }

    function act_ali205( $p ){

        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();

            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];

            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ]); //->checkHei10( $trade_row, $qf_ck ,['up'=>$var ] );

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' =>3, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");

            $url= 'https://'.drFun::getHttpHost().'/api/v205open/'. implode('/',$p );

            $hongbao['remark'] = $trade_row['trade_id'];
            $hongbao['amount'] = $trade_row['realprice']/100 ;
            $hongbao['uid'] = $account['ali_uid'];//自己的UID
            //$hongbao['j'] = $account['zhifu_account'];//自己的登录账号
            if(in_array( $trade_row['user_id'],['2650']) && $account['card_index']){
                //$qr['qr_text']= $url_kl='https://ds.alipay.com/?requestType=hotword_b&appId=20001003&keyword='.trim( $account['card_index'] );
                //$this->log('card_index>> '.  json_encode( $account) );
                //$this->log('card_index>> '.  json_encode( $account) );
            }
            $hongbao['url'] = $url  =  $qr['qr_text'];//支付二维码
            $hongbao['ctime']= time();

            $hongbao['card_index']=  $account['card_index'];//

            $this->assign('trade', $trade_row )->assign("hongbao" ,$hongbao);
            $this->assign('url4',$url)->assign('trade',$trade_row )->assign('url',$url )->assign('qr', $qr );

            $this->assign('account', $account );

            $this->htmlFile='app/v23openV3.phtml';
            $this->htmlFile='app/v205open.phtml';
            $this->htmlFile='app/v205openV2.phtml';
            $this->htmlFile='app/v205openV4.phtml';
            $this->htmlFile='app/v205openV6.phtml';

            if( $_GET['ds']==3   ){
                $this->htmlFile='app/v205openV7.phtml';
            }




            if($_GET['ds']==1 || in_array( $trade_row['merchant_id'] ,[8277]) ){
                //$this->htmlFile='app/v205openV4.phtml';
            }
            if($_GET['ds']==2 ||  in_array( $trade_row['merchant_id'] ,[8395, 8713])|| in_array( $trade_row['user_id'],[356] )){
                $this->htmlFile='app/v205openV5.phtml';
            }

            //if( $this->getLogin()->isUserKouLing( $trade_row['user_id'] )){
            if( in_array( $trade_row['user_id'],[2333] )){
                if(  !$account['card_index'] ) {
                    //$this->getLogin()->createQrPay()->upTradeByID($id, ['type' =>2, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
                    //$this->throw_exception( "非法支付！");
                }

                $url_kl='https://ds.alipay.com/?requestType=hotword_b&appId=20001003&keyword='.trim( $account['card_index'] );
                $this->assign('url_kl',$url_kl);
                $this->htmlFile='app/v205openV8.phtml';
            }

            if( $_GET['ds']==9  || in_array( $trade_row['user_id'],[2323] ) ){
                $url_plus= $this->getHostByUid( $account['user_id'] ) .'/api/v205plus/'. implode('/',$p );
                $this->assign('url_plus', $url_plus );
                $this->htmlFile='app/v205openV9.phtml';
            }







        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
    }


    function act_v205plus( $p ){


        $sign= trim($p[0] );
        $id = $p[1];
        try{
            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) $this->throw_exception("请使用支付宝扫一扫", 460);


            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
            // (time()- $trade_row['ctime']) >180 ||
            if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            //$qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            //if(  !$qr ) $this->throw_exception( "非法支付！");

            $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&userId='.$account['ali_uid'];
            $url.='&amount='.($trade_row['realprice']/100).'&money='.($trade_row['realprice']/100)    ;
            $this->assign('url', $url );
            $this->htmlFile='app/t40_b2zz.phtml';


            header('Location: '. $url );
            $this->drExit();

        }catch (drException $ex){

            $this->logErr("错误v205plus:". $id );
            $this->apiError( $ex );
        }




        //$this->drExit($p );
    }




    function act_v301open($p){
        $sign= trim($p[0] );
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);


            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
            // (time()- $trade_row['ctime']) >180 ||
            if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);
            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

            //$account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );

            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");

            //$url= $qr['qr_text'];
            $url= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode(  $qr['qr_text'] );

            $hongbao['url']= $url;
            $hongbao['amount']= $trade_row['realprice']/100 ;;

            $this->assign('hongbao',$hongbao);
            $this->assign('url',$url );
            $this->htmlFile='app/v301open.phtml';


        }catch (drException $ex){

            $this->logErr("错误301ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ali301( $p ){
        $sign= trim($p[0] );
        $id = $p[1];

        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();
            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v301open/'. implode('/',$p );
            //if( $this->isAli() ) $this->redirect(  $url );

            /*
            $qr = $this->getLogin()->createQrPay()->getQrByID( $trade_row['qr_id'] );
            if(  !$qr ) $this->throw_exception( "非法支付！");
            $url= $qr['qr_text'];
            */
            //$url4= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url  ) ;
            $ali_url = $url5="https://ds.alipay.com/?from=mobilecodec&scheme=".urlencode( $url4);


            if( in_array( $trade_row['merchant_id'],[8201,8202,8200, 8501 ] )  ){
                $ali_url= $url;

            }
            $this->assign('auto',1);

            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url4 );//->assign('auto',1 );
            $this->assign( 'client', $client  );

            if( $client  && $trade_row['merchant_id']==8501  ) $this->redirect( $url );

            $this->htmlFile='app/alishowV35t2.phtml';


        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }

    }



    function act_ali39( $p ){

        $this->act_v39open( $p );
        return;

        $sign= trim($p[0] );
        $id = $p[1];
        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();
            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/v39open/'. implode('/',$p );

            if( $this->isAli() ) $this->redirect(  $url );

            //$url4= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url .'/1') ;
            $ali_url = $url5="https://ds.alipay.com/?from=mobilecodec&scheme=".urlencode( $url4);


            if( in_array( $trade_row['merchant_id'],[8201,8202,8200, 8501 ] )  ){
                $ali_url= $url;

            }
            $this->assign('auto',1);

            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$ali_url );//->assign('auto',1 );
            $this->assign( 'client', $client  );

            if( $client  && $trade_row['merchant_id']==8501  ) $this->redirect( $url );

            $this->htmlFile='app/alishowV35t2.phtml';


        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
    }


    function act_ali138( $p ){
        $this->act_v139open( $p );
    }
    function act_ali139( $p ){

        $this->act_v139open( $p );
    }
    function act_ali139old( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {
            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);
            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            $client = drFun::getClientV2();
            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);
            $url= 'https://'.drFun::getHttpHost().'/api/v139open/'. implode('/',$p );
            $url= 'https://qz.atbaidu.com/api/v139open/'. implode('/',$p );

            if( $this->isAli() ) $this->redirect(  $url );

            //$url4= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url .'/1') ;
            $ali_url = $url5="https://ds.alipay.com/?from=mobilecodec&scheme=".urlencode( $url4);


            if( in_array( $trade_row['merchant_id'],[8201,8202,8200, 8501 ] )  ){
                $ali_url= $url;

            }
            $this->assign('auto',1);

            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$ali_url )->assign('auto',1 );
            $this->assign( 'client', $client  );

            if( $client  && $trade_row['merchant_id']==8501  ) $this->redirect( $url );

            $this->htmlFile='app/alishowV35t2.phtml';


        }catch ( drException $ex ){
            $this->logErr("错误20ID:". $id );
            $this->apiError( $ex );
        }
    }

    function getOpenUrl($p,$act='v36open'){

        $c_file = dirname(dirname(dirname(__FILE__))) . "/webroot/lab/alipay/config.php";
        require_once $c_file;
        $redirect_uri = 'https://qf.zahei.com/api/'.$act.'/' . implode('/', $p);
        $url = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=' . $config['app_id'] . '&scope=auth_base&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $p[1];
        return $url;

    }

    function act_ali36( $p ){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v36open/'. implode('/',$p );
            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );

            $open_url = $this->getOpenUrl($p);

            $url4= 'alipays://platformapi/startapp?appId=20000691&url='. urlencode( $open_url );

            $ali_url = $url5="https://ds.alipay.com/?from=mobilecodec&scheme=".urlencode( $url4);

            if( in_array( $trade_row['merchant_id'],[8201,8202,8200] )  ){
                $ali_url= $url;
            }

            //$this->redirect($url4 );
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$ali_url )->assign('auto',1 );
            $this->assign( 'client', $client  )->assign('tips_pay',1);
            $this->htmlFile='app/alishowV35.phtml';
            $this->htmlFile='app/alishowV35t2.phtml';

            if($trade_row['merchant_id'] == 8080 ){
                //$this->htmlFile='app/alishowV35t3.phtml';
            }

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if( $is_ali ) $this->redirect( $ali_url );

        }catch ( drException $ex ){
            $this->logErr("错误50ID:". $id );
            $this->apiError( $ex );
        }
    }

    function isAli(){
        $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
        return $is_ali;
    }

    function act_v80open( $p )
    {

        $sign = trim($p[0]);
        $id = $p[1];

        try {

            $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $this->test1wan($trade_row);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();
            //(time()- $trade_row['ctime']) >300 ||
            if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("支付成功或已过期！", 459);

            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

            $qr = $this->getLogin()->createTableTaobaoQr()->getRowByKey($trade_row['qr_id']);
            $url4= $url=  $qr['qr_text'] ;//strtr($qr['qr_text'] , ['https://qr.alipay.com/_d?_b=peerpay&enableWK=YES&'=>'https://mclient.alipay.com/h5/peerpay.htm?']) ;//$qr['qr_text'];
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url );

            $this->htmlFile='app/ali80.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误38ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_ali80( $p ){

        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            //if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);
            if ( !in_array($trade_row['type'], $this->getLogin()->createQrPay()->getTypeTradeUsingLimit()  )) $this->throw_exception("支付成功或超时！", 456);
            $client = drFun::getClientV2();


            $var=[ 'cookie' => $qf_ck, 'ip' =>  drFun::getIP(), 'client' => $client  ];

            $this->checkHei( $trade_row, $qf_ck ,['up'=>$var ])->checkHei10( $trade_row, $qf_ck ,['up'=>$var ] );

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $qr = $this->getLogin()->createTableTaobaoQr()->getRowByKey($trade_row['qr_id']);
            $url4= $url=$qr['qr_text'];
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url )->assign('auto', 0 ); //$client?1:0
            $this->assign( 'client', $client  );
            $this->htmlFile='app/alishowV38.phtml';


            //if($_GET['no']==1){ }
                $url4= 'https://'.drFun::getHttpHost() .'/api/v80open/'. implode('/',$p );
                $url3= 'alipays://platformapi/startapp?appId=66666675&url='.$url4 ;
                $url4= 'alipays://platformapi/startApp?appId=60000050&showToolBar=NO&showTitleBar=YES&waitRender=150&showLoading=NO&url='.urlencode($url4 ) ;

                $qr_url= strtr($qr['qr_text'] , ['https://qr.alipay.com/_d?_b=peerpay&enableWK=YES&'=>'https://mclient.alipay.com/h5/peerpay.htm?']) ;//$qr['qr_text'];
                $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url', $url3 )->assign('auto',  $client?1:0 ); //


        }catch ( drException $ex ){
            $this->logErr("错误38ID:". $id );
            $this->apiError( $ex );
        }


    }

    function act_ali78($p){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v78open/'. implode('/',$p );
            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url .'/1') ;
            $url4= 'alipays://platformapi/startapp?appId=20000067&url='. urlencode($url .'/1') ;
            //alipays://platformapi/startapp?appId=20000067&url=http%3A%2F%2F47.107.14.69%3A8081%2FA%2Falipay89c8c280927ad7a8458808a97a94e921
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url )->assign('auto',1 );
            $this->assign( 'client', $client  );
            $this->htmlFile='app/alishowV38.phtml';

            $qr = $this->getLogin()->createTablePayLogTem()->getRowByKey($trade_row['qr_id']);//getr(  $trade_row['qr_id'] );
            $data= drFun::json_decode($qr['data'] );
            $hongbao['payUrl']= $data['alipayOrderString'];

            $v2=['fromAppUrlScheme'=>'alipays', 'requestType'=>'SafePay','dataString'=>  $data['alipayOrderString'] ];
            $appv2= 'alipaymatrixbwf0cml3://alipayclient/?'. urlencode( json_encode($v2 ));
            //$this->assign('url_client',$appv2);

        }catch ( drException $ex ){
            $this->logErr("错误38ID:". $id );
            $this->apiError( $ex );
        }

    }


    function act_ali38($p){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);
            $qf_ck = $_COOKIE['qf'];
            if (!$qf_ck) $qf_ck = drFun::rankStr(8);
            drFun::setcookie('qf', $qf_ck, time() + 3600 * 24 * 365);

            if ($trade_row['type'] > 0 && !isset($_GET['no'])) $this->throw_exception("请勿重复支付！", 456);

            $client = drFun::getClientV2();

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 4, 'cookie' => $qf_ck, 'ip' => drFun::getIP(), 'client' => $client]);

            $url= 'https://'.drFun::getHttpHost().'/api/v38open/'. implode('/',$p );
            $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            //$url4= 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );
            //alipays://platformapi/startapp?saId=10000007&qrcode=http%3A%2F%2Fgw.sdk.568067.com%2Fzf%2Findex%2F%3Ft%3D2002b3870c0e-5c31-5b1a-24b6-91854e5ec3e8%26n%3D1551320698
            $url4='alipays://platformapi/startApp?appId=10000011&url='.urlencode($url2 );
            //$url4='alipays://platformapi/startapp?appId=20000067&url='.urlencode($url );


            $url4= 'alipays://platformapi/startapp?appId=20000691&t='.time().'&url='. urlencode($url .'/1') ;
            //$this->redirect($url4 );
            $this->assign('url4',$url4)->assign('trade',$trade_row )->assign('url',$url )->assign('auto',1 );
            $this->assign( 'client', $client  );

            $this->htmlFile='app/alishowV38.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误38ID:". $id );
            $this->apiError( $ex );
        }

    }




    function act_v35open($p){
        $sign= trim($p[0] );
        $id = $p[1];
        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();

            if (!in_array($trade_row['type'], $usIng)) $this->throw_exception("已经支付或者超时", 1459);

            $this->getLogin()->createQrPay()->upTradeByID($id, ['type' => 3]);
            $account = $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id'] );

            //$this->drExit( $trade_row );
            $hongbao['i']= $trade_row['trade_id'];
            $hongbao['amount']= $trade_row['realprice']/100;
            $hongbao['a']=  $account['ali_uid'];
            //$hongbao['a']=  $account['ali_uid'];
            $hongbao['j']= $account['zhifu_account'];
            $this->assign('hongbao',$hongbao)->assign( 'client',drFun::getClientV2()  );;
            //$this->htmlFile='app/tool_t21.phtml';
            $this->htmlFile='app/tool_t21V2.phtml';
            $this->htmlFile='app/tool_t21V4.phtml';

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }
    }

    function act_v50open( $p ){

        $sign= trim($p[0] );
        $id = $p[1];

        try {

            //$is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
            //if (!$is_ali) $this->throw_exception("请在支付宝内使用", 460);

            $md5 = $this->getPaySign($id);//md5($id.'adf888');
            if ($md5 != $sign) $this->throw_exception("非法支付！", 457);
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID($id);

            $usIng = $this->getLogin()->createQrPay()->getTypeTradeUsing();

            if (!in_array($trade_row['type'],  $usIng )) $this->throw_exception("已经支付或者超时", 1459);
            $this->getLogin()->createQrPay()->upTradeByID($id,[ 'type'=>3] );

            $trade_kv = $this->getLogin()->createTableTradeKV()->getRowByKey( $trade_row['trade_id'] );

            $trade_data= json_decode( $trade_kv['value'],true );
            //$this->drExit( $trade_data );
            $this->redirect( $trade_data['data']['url'] );

        }catch ( drException $ex ){
            $this->logErr("错误ID:". $id );
            $this->apiError( $ex );
        }

    }

    function act_ip($p){
        $this->setDisplay('json');

        switch ($p[0]){
            case 'v40open':
                $tr_id = intval($p[1]);
                $trade_row= $this->getLogin()->createQrPay()->getTradeByID( $tr_id);
                if( $trade_row['type']==4){
                    $this->getLogin()->createQrPay()->upTradeByID($tr_id,['type'=>3]);
                }
                break;
            case 'ip':
            default:
                $var=['lo'=> strtr( trim($_GET['lo']) ,['省'=>'','市'=>'' ]),'lc'=> trim($_GET['lc'])];
                $trade_id= trim($p[1]);
                if(  $p[2]!= $this->getPaySign($trade_id) )  $this->throw_exception("非法支付！", 457);
                $this->getLogin()->createQrPay()->upTradeByID($trade_id, $var);
                //$this->assign('var', $var )->assign('trade', $trade_id );
                break;
        }

    }
}
