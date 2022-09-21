<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/11
 * Time: 11:13
 */

namespace ctrl;


use model\drException;
use model\drFun;
use model\drTpl;
use model\lib\mq;
use model\log;
use model\trade;

class client extends drTpl
{
    public function init()
    {
        parent::init();
        //$this->htmlFile="nologin.phtml";
    }

    function act_payLogV2(){
        $mq_data= $_POST;

        /*
        $pay = json_decode( $mq_data['pay'] ,true);
        $fee= 100* floatval( trim(  strtr( $pay['billAmount'],['元'=>''] )) );
        if( $fee==1 ){
            $account  = json_decode( $mq_data['account'] ,true);
            $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
        }
        */

        $this->getLogin()->createQrPay()->toMqTrade($mq_data,'client_pay_log'  );
        //$data['w_uid']=
    }

    function act_payLogV3(){
        //$this->log( "\n=====payLogV3= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));
        $this->log( "\n=====payLogV3= ".date("Y-m-d H:i:s")."===== ". json_encode( $_POST ) );
        //
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );

        $this->drExit($_POST);
    }

    function act_payLogV3wx(){
        $this->log( "\n=====payLogV3wx= ".date("Y-m-d H:i:s")."===== ". json_encode( $_POST));
        //$this->log( "\n=====payLogV3wx= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit($_POST);
    }

    function act_payLogV3ding(){
        $this->log( "\n=====payLogV3ding= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit($_POST);
    }

    function act_payLogV3tao( $p ){
        $this->log( "\n=====payLogV3tao= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit($_POST);
    }


    function act_payLogV3Weibo( $p ){
        $this->log( "\n=====act_payLogV3Weibo= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit($_POST);
    }

    function act_payLogV3Client( $p ){
        $this->log( "=====payLogV3Client= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit('ok');
    }

    function act_hxsms( $p ){
        $this->log( "=====act_hxsms= ".date("Y-m-d H:i:s")."===== ". json_encode($_POST));
        //$this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        //$this->drExit($_POST);
        $this->assign('ok', 1 );
    }
    function act_payLogV3PingAn( $p ){
        $this->log( "\n=====payLogV3PingAn= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        //$this->drExit($_POST);
        $this->assign('ok',1 );
    }

    function act_payLogV3B2Alipay(){
        //$this->log( "\n=====payLogV3B2Alipay= ".date("Y-m-d H:i:s")."=====\n" . print_r( $_POST,true), 'payLogV3B2Alipay.log');
        $this->log( "\n=====payLogV3B2Alipay= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        if($_POST['cls']=='com.b2alipay.bill.qy'){
            if ( !$this->getLogin()->isLogin()) $this->throw_exception("请先登录");
            //$this->getLogin()->getUserId();
        }
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->assign('ok',1 );
    }
    function act_payLogV3B2JD( $p ){

        $this->log( "\n=====payLogV3B2JD= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        if($_POST['cls']=='com.b2jd.bill'){
            if ( !$this->getLogin()->isLogin()) $this->throw_exception("请先登录");
            //$this->getLogin()->getUserId();
        }
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->assign('ok',1 );
    }

    function act_payLogV3uni( $p ){
        $this->log( "\n=====payLogV3uni= ".date("Y-m-d H:i:s")."=====\n". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit($_POST);
    }
    function act_payLogV3laka( $p ){
        $this->log( "\n=====payLogV3laka= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit($_POST);
    }

    function act_payLogV3wang( $p ){
        $this->log( "\n=====payLogV3wang= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'client_pay_log_v3'  );
        $this->drExit('ok');
    }

    function act_smsLog($p){
        $this->log( "\n=====smsLog= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));
        //print_r($_POST);
        if($p[0]=='gbk') $data =  mb_convert_encoding( $_POST['data'] , "UTF-8", "GBK");
        else $data =    $_POST['data'];
        $clinet_uid= intval($_POST['client_uid']);
        try {
            //$this->drExit( $clinet_uid );
            $this->getSmsLog($data,$clinet_uid);
        }catch (\Exception $ex ){
             if($p[0]=='gbk' ) $this->drExit("error:(".$ex->getCode().")" . mb_convert_encoding( $ex->getMessage() , "GBK","UTF-8" ));
             else $this->drExit("error:(".$ex->getCode().")" .   $ex->getMessage() );
        }




        $this->drExit("ok");
    }

    function getSmsLog( $str,$clinet_uid ){
        //echo  $str ;
        $arr = explode(',' , $str, 5);
        if( count( $arr)!=5) $this->throw_exception("格式不对！");
        $ltime=  strtotime( $arr[1] );
        if( $ltime<=0 ) $this->throw_exception("时间非法");

        $var= ['ctime'=>$ltime,'user_id'=> $clinet_uid ];
        $this->getSmsAccount( $arr ,$var )->getSmsVarFromText(  $arr[4] , $var)->sms460( $var );


        $log =  new log('pay_log', $var['user_id'] );
        $log->append( $ltime ,$var['opt_type'] ,['text'=>$str,'t'=>date("Y-m-d H:i:s") ] ,['ltime'=> $ltime,'fee'=>$var['fee']
                    ,'account_id'=>$var['account_id'],'pay_type'=> $var['pay_type']  ,'ip'=>drFun::getIP() ,'buyer'=>$var['buyer'],'ctime'=>$ltime    ]);

        $pay_log_id = $this->getLogin()->createSql()->lastID();
        //if( $var['opt_type'] ==10 ) $this->getLogin()->createQrPay()->payMatchByLogID($pay_log_id);

        return $this;
    }
    function getSmsAccount( $arr , &$var){
        if( strpos($arr[4],'群富' )!==false ){
            $this->bindAccount( $arr ,  $var );
        }

        $account= $this->getLogin()->createQrPay()->getAccountIDByWhere( ['user_id'=> $var['user_id'],'card_index'=> $arr[0] ]  ,['dan_row'=>1]);
        if( !$account ) $this->throw_exception($arr[0]." 未绑定！");
        if( $account['zhifu_realname']=='' ) $this->throw_exception($arr[0]." 未绑定 手机号不存在！");
        //$this->drExit( $account );
        /*
        $arr = explode('|' , $text );
        $account_id = intval($arr[0]);
        $account= $this->getLogin()->createQrPay()->getAccountByID( $account_id );
        if( !$account ) $this->throw_exception( "账号非法1！" );
        //print_r( $account );
        if( $account['account']!=  trim($arr[1]) ) $this->throw_exception( "账号非法2" );
        */
        $var['account_id']= $account['account_id'];
        $var['user_id']= $account['user_id'];
        $account_type=['60'=>460 ];
        if( isset( $account_type[  $account['type']  ] ) ) $var['pay_type']= $account_type[  $account['type']  ] ;
        else $this->throw_exception( "账号非法3" );


        return $this;
    }

    function sms460( &$var ){
        if( $var['pay_type']!=460 ) return $this;
        if( $var['opt_type']!=10 ) return $this;
        $where=['account_id'=> $var['account_id'],'type'=>[3,4],'realprice'=> $var['fee'] ]; //,2
        //$where['<']['ctime']= $var['ctime'];
        //$where['>']['ctime']= $var['ctime']-10*60;
        $tall = $this->getLogin()->createQrPay()->getTradeByWhere( $where  );
        if(! $tall ) $var['opt_type'] = 401; //
        return $this;
    }
    function bindAccount( $arr ,  $var ){
        $re=[];
        preg_match_all( '/(\d+)/i', $arr[4],$re);
        $account_id= intval($re[1][0]);
        $account= $this->getLogin()->createQrPay()->getAccountByID( $account_id);
        if( $account['zhifu_realname']=='')  $this->throw_exception("手机号不存在！" );
        if( $account['user_id']!= $var['user_id'] ) $this->throw_exception("绑定账号有误，请重新发送短信" );
        $card_index= trim($arr[0] );
        $this->getLogin()->createQrPay()->upAccountByWhere( ['user_id'=> $account['user_id'],'card_index'=>$card_index],['card_index'=>'']);
        $this->getLogin()->createQrPay()->upAccountByWhere( ['account_id'=>   $account_id],['card_index'=> $card_index]);

        $this->drExit('tel bind Ok   '.$card_index.'=>'. $account['zhifu_realname'] );
        return $this;
        //print_r( $account );
    }
    function getSmsVarFromText($text , &$revar){
        //$var=['fee'=>0 ,'opt_type'=>401 ,'buyer'=>'' ];
        $re=[];
        preg_match_all( '/(\d+\.\d+)/i', $text,$re);
        //print_r( $re );
        $fee= drFun::yuan2fen($re[0][0]);

        $pay['text']= $text; $opt_type = 401; $buyer='';

        if(strpos($pay['text'],'农业银行') &&  ( strpos($pay['text'],'代付') || strpos($pay['text'],'转账') || strpos($pay['text'],'入账'))  && strpos( $pay['text'],'-' )===false  ){
            $opt_type = 10;
        }elseif(  strpos($pay['text'],'网商银行') &&  ( strpos($pay['text'],'转入')     ) ){
            $opt_type = 10;

        }elseif( strpos($pay['text'],'工商银行') &&  strpos($pay['text'],'收入') ){

            $re=[];
            preg_match_all( '/([\d\.,]+)元/i', $pay['text'],$re);
            //$fee= ceil(strtr($re[1][0],[','=>''])*100);
            $int= strtr($re[1][0],[','=>'']);
            $fee=  drFun::yuan2fen($int) ;//floor(($int+0.001)*100);

            $c_str = drFun::cut( $pay['text'] ,'(',')');
            $arr= explode("支付宝", $c_str);
            $buyer = trim($arr[0] );

            $opt_type = 10;

        }elseif(  strpos($pay['text'],'徽商银行') &&  ( strpos($pay['text'],'增加')     ) ){
            $opt_type = 10;
        }elseif(  strpos($pay['text'],'湖南农信') &&  ( strpos($pay['text'],'转入')     ) ){
            $opt_type = 10;
        }elseif(  strpos($pay['text'],'交通银行') &&  ( strpos($pay['text'],'转入')     ) ){
            $opt_type = 10;
        }elseif(  strpos($pay['text'],'招商银行') &&  ( strpos($pay['text'],'收款人民币')  ||  strpos($pay['text'],'入账人民币')  ) ){
            $opt_type = 10;
        }elseif(  strpos($pay['text'],'中信银行') &&  ( strpos($pay['text'],'存入')     ) ){
            $opt_type = 10;
        }elseif(  strpos($pay['text'],'光大银行') &&  ( strpos($pay['text'],'存入')     ) ){
            $opt_type = 10;
            $buyer=  drFun::cut( $pay['text'] ,'摘要:','支付宝');
        }elseif(  strpos($pay['text'],'张家口银行') &&  ( strpos($pay['text'],'转入')     ) ){
            $opt_type = 10;
            $re=[];
            preg_match_all( '/金额([\d\., ]+)元/i', $pay['text'],$re);
            if(  trim($re[1][0]) )   $fee = drFun::yuan2fen( trim($re[1][0]));
            else{
                preg_match_all( '/([\d\.,]+)元/i', $pay['text'] ,$re);
                $fee = drFun::yuan2fen( trim($re[1][0]));
            }

        }elseif(  strpos($pay['text'],'浦发银行') &&  ( strpos($pay['text'],'存入')     ) ){
            $opt_type = 10;
            $re=[];
            preg_match_all( '/([\d\.,]+)\[/i', $pay['text'],$re);
            $fee = drFun::yuan2fen($re[1][0]);
            //[王浩支付宝转账
            $buyer =  drFun::cut( $pay['text'] ,'[','支付宝');

        }elseif(  strpos($pay['text'],'平安银行') &&  ( strpos($pay['text'],'转入')     ) ){
            $opt_type = 10;
        }elseif( strpos($pay['text'],'福建农信') &&  strpos($pay['text'],'转入') ){
            $opt_type = 10;
        }elseif( strpos($pay['text'],'邮储银行') &&  strpos($pay['text'],'提现') ){
            $opt_type = 10;
        }elseif( strpos($pay['text'],'银行') &&  strpos($pay['text'],'收入') ){
            $opt_type = 10;
        }



        if( $opt_type!=10 &&  strpos($pay['text'],'银行')===false ) $this->throw_exception("该短信模板未匹配");

        //return ['fee'=>$fee,'opt_type'=>$opt_type,'buyer'=>$buyer];
        $revar['fee']= $fee;
        $revar['opt_type']= $opt_type;
        $revar['buyer']= $buyer;
        return $this;
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
    function getWeiHaoAcc(  $accList ,$sms ){
        if( strpos($sms,'中国银行') || strpos($sms,'邮储银行') ){
            foreach($accList as $acc ){
                if(200104==$acc['bank_id'] && strpos($sms,'中国银行') ){
                    //return $acc;
                    $this->log( "200104=>china_bank=>".$sms."=>". json_encode( $acc));
                    if(  strpos($sms,$acc['zhifu_name']) )return $acc;
                    //您的借记卡账户长城电子借记卡
                    if( in_array( trim( $acc['zhifu_name']),['夏善伟'] )  ){
                        return $acc;
                    }
                }
                if(200105==$acc['bank_id'] && strpos($sms,'邮储银行') ){
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

    function act_tts( $p){

        $str='【北京银行】您尾号为1493的京卡于20年6月20日08:35银联入账收入1000.00元。活期余额11636.49元。对方账号尾号:8465。对方户名:旦增曲登。详询我行';
        $zhifu_account= '622908363031194117';
        $weiHao5 = substr($zhifu_account,-5 );
        $weiHao54 = substr($weiHao5,0,4 );

        $this->drExit($weiHao54);

        $this->drExit( $this->isYes10( $str) );
    }

    function act_payLog(){
        $this->log( "\n=====payLogV3SMS= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));
        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'sms'  );
    }

    /**
     * 上传支付记录
     * @throws \model\drException
     */
    function act_payLog_old(){

        //$this->log( "\n=====payLogV3= ".date("Y-m-d H:i:s")."=====\n". print_r( $_POST,true));
        $this->log( "\n=====payLogV3SMS= ".date("Y-m-d H:i:s")."=====". json_encode( $_POST));

        $this->getLogin()->createQrPay()->toMqTrade( $_POST,'sms'  );

        $uid = intval($_POST['uid']);
        $pay = json_decode( $_POST['pay'] ,true);
        $account  = json_decode( $_POST['account'] ,true);

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
            }elseif(  strpos($pay['text'],'湖南农信') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'交通银行') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'招商银行') &&  ( strpos($pay['text'],'收款人民币')  ||  strpos($pay['text'],'转入')||  strpos($pay['text'],'入账人民币')   ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'中信银行') &&  ( strpos($pay['text'],'存入')     ) ){
                $opt_type = 10;
            }elseif(  strpos($pay['text'],'光大银行') &&  ( strpos($pay['text'],'存入')     ) ){
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

            }elseif(  strpos($pay['text'],'平安银行') &&  ( strpos($pay['text'],'转入')     ) ){
                $opt_type = 10;
            }elseif( strpos($pay['text'],'福建农信') &&  strpos($pay['text'],'转入') ){
                $opt_type = 10;
            }elseif( strpos($pay['text'],'邮储银行') &&  (strpos($pay['text'],'提现') || strpos($pay['text'],'入账') ||  strpos($pay['text'],'来账')||  strpos($pay['text'],'汇入') ) ){
                $opt_type = 10;
            //}elseif( strpos($pay['text'],'银行') && (strpos($pay['text'],'收入') || strpos($pay['text'],'收款') ||  strpos($pay['text'],'存入')|| strpos($pay['text'],'转入') ) ){
            }elseif( strpos($pay['text'],'银行') &&  $this->isYes10( $pay['text']) ){
                $opt_type = 10;
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

        $buyer=$pay['buyer']='';
        unset($pay['buyer']);

        $test_txt = preg_replace("/\[[0-9 ]+条\]/","", $pay['text'] );

        if( $test_txt  && $opt_type==10 ){
            $whSms= ['account_id'=> $no_acc_id   , 'text'=> $test_txt  ];
            $cnt= $this->getLogin()->createTablePaySms()->getCount( $whSms );
            if( $cnt>0 ) {
                $this->log("sms402>>". $test_txt );
                $opt_type= 402;
            }#重复忽略
            else {
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

        if( $account['ma_user_id']>0 ){
            $log->append( $pay['id'] ,$opt_type ,$pay,['ltime'=>$pay['postTime'],'fee'=>$fee
                ,'account_id'=>$account['account_id'],'ma_user_id'=>$account['ma_user_id'],'pay_type'=> $pay_type  ,'ip'=>drFun::getIP() ,'buyer'=>$buyer   ]);
        }else{
            $log->append( $pay['id'] ,$opt_type ,$pay,['ltime'=>$pay['postTime'],'fee'=>$fee
                ,'account_id'=>$account['account_id'],'pay_type'=> $pay_type  ,'ip'=>drFun::getIP() ,'buyer'=>$buyer   ]);
        }



        if($opt_type!=10 ) $this->throw_exception("忽略");

        $pay_log_id = $this->getLogin()->createSql()->lastID();

        #正式环境请移到队列中
        try {
            //$fee==1  || $fee==1111 || $fee==11||  $fee==5011 ||  $fee==2111 || $fee==10011|| $fee==10011
            if( in_array( $fee,[1,11,1111,2011,5011,10011,30011]) ) $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );

            if( $fee==20100 &&  strpos($pay['text'],'张家口银行') ) {
                $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
            }

            $this->getLogin()->createQrPay()->payMatchByLogID($pay_log_id);
        }catch ( drException $ex ){

        }

//        try{
//            $mq = new mq();
//            $mq->rabbit_publish('qf_pay_log', ['pay_log_id'=> $pay_log_id]);
//        }catch ( \Exception $ex ){
//
//        }


        //$this->assign('post',$_POST);

        //$this->drExit();
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

    function act_time( $p ){
        //if( $this->is)
        if( $p[0] ){
            $this->getLogin()->createQrPay()->updateClientTime( $p[0] ,$_GET );
        }

        $this->assign('time',['s'=>date('Y-m-d H:i:s'),'i'=>time() ] );
    }
    function act_timeV3(  $p ){
        if(  $p[0] ){

            /*
            $account = $this->getLogin()->createQrPay()->getAccountByAliUid(  $p[0] );
            if( $account['type']==90 ){
                $this->log("time90: ". $p[0]  ."\t" .  $account['account_id']);
                $acc_online= $this->getLogin()->createTableAccountOnline()->getRowByWhere(['account_id'=> $account['account_id'] ,'type'=>90]  );
                if( !$acc_online ) return ;
                $this->log("time90: ".date('Y-m-d H:i:s'). json_encode( $acc_online ) ."\t". date('Y-m-d H:i:s',$acc_online['clienttime']) );
                if( time()> ($acc_online['clienttime']+20) ) return ;
            }
            $this->getLogin()->createQrPay()->updateClientTime( $account['account_id']  );
            */


            $trade = new trade();
            $trade->timeV3($p);

            /*
            try{
                $var=['p'=>$p,'t'=>time() ];
                $this->getLogin()->createQrPay()->toMqTradeV2( $var,'timeV3' ,['host'=>2] );

            }catch ( drException $ex ){
                $trade = new trade();
                $trade->timeV3($p);
            }
            */
        }
    }
    function act_timeV3wx($p){
        $this->act_timeV3($p);
        if(  $p[0] ){
            $this->getLogin()->createQrPay()->updateClientTimeByCardIndex( $p[0] ,[24,211]  );
        }
    }

    function act_timeV3ding($p){
        $this->act_timeV3($p);
    }
    function act_timeV3tao($p){
        $this->act_timeV3($p);
    }
    function act_timeV3uni($p){
        $this->act_timeV3($p);
    }
    function act_timeV3laka($p){
        $this->act_timeV3($p);
    }
    function act_timeV3wang($p){
        $this->act_timeV3($p);
    }

    function act_timePingAn( $p ){
        $this->log( "===timePingAn==".json_encode( $_POST ));
        if(  $p[0] ){
            $account = $this->getLogin()->createQrPay()->getAccountByAliUid(  $p[0] );
            if ( $account ) $this->getLogin()->createQrPay()->updateClientTime( $account['account_id']  );
            else{
                $this->getLogin()->createQrPay()->addAccountFromPingAn( $_POST);
            }
        }

        $this->assign('time',['s'=>date('Y-m-d H:i:s'),'i'=>time() ] );
    }

    function act_timeB2Alipay( $p ){
        $this->log( "===timeB2Alipay==".json_encode( $_POST ));
        if(  $p[0] ){
            $account = $this->getLogin()->createQrPay()->getAccountByAliUid(  $p[0] );
            //if ( $account ) $this->getLogin()->createQrPay()->updateClientTime( $account['account_id']  );

            if ( $account ){
                $this->getLogin()->createQrPay()->updateClientTimeV2( $account  );
                $this->getLogin()->createQrPay()->updateClientTime( $account['account_id']  );
            }
            else{
                $this->getLogin()->createQrPay()->addAccountFromB2Alipay( $_POST);
            }

        }

        $this->assign('time',['s'=>date('Y-m-d H:i:s'),'i'=>time() ] )->assign('acc',$account);
    }



    function act_timeB2JD( $p){
        $this->log( "===timeB2JD==".json_encode( $_POST ));
        if( $p[0]  ){
            $account = $this->getLogin()->createQrPay()->getAccountByAliUid(  $p[0] );
            if($account){
                $this->getLogin()->createQrPay()->updateClientTime( $account['account_id']  );
            }else{
                $this->getLogin()->createQrPay()->addAccountFromB2JD( $_POST);
            }
        }
    }

    function act_login(){

        $this->assign('post', $_POST);
        $duser = $this->getLogin()->loginByPsw( $_POST['openid'], $_POST['psw'], ['channel'=>'client' ]);
        $this->assign('duser', $duser );
        $qr = $this->getLogin()->createQrPay()->getAccountByUid($_POST['pay'] ,$duser['user_id'] );
        $qr['is_upload']= true;
        $this->assign('qr', $qr );
        //全码
        $this->assign('qrFee',$this->getLogin()->createQrPay()->getMoneyConfig('all',['display'=>'array']) );
        //$this->assign('qrFee',  $this->getLogin()->createQrPay()->getMoneyConfigV2(  ) );
        //余码
        //$this->assign('qrFee',$this->getLogin()->createQrPay()->getMoneyConfigYuMa( $qr['account_id']) );
        //$this->assign('qrFee', [10086,1999 ]);
    }
    function act_reg(){
        $user = [openid=>'G',name=>'G','ts'=>'1',school=>'西南','password'=>'456789'];
        $uid = $this->getLogin()->reg( $user );
        $this->assign('uid',$uid );
    }

    function act_logout(){
        $this->getLogin()->logout();
    }

    function act_config( $p){
        switch ($p[0]){
            case 'fee':
                $this->assign('qrFee', $this->getLogin()->createQrPay()->getMoneyConfig() );
                break;
        }
    }

    function act_upload(){
        if( !$this->getLogin()->isLogin() ) $this->throw_exception( "请先登录", 463 );
        //$this->getLogin()->checkLogin();
        $file = $_FILES['file'];
        $opt =[];
        $opt['dir']='qr';
        $opt['ext']= ['jpg'=> 1,'png'=>1,'gif'=>1] ;
        $r= drFun::upload( $file ,$opt); //

        $var['file']= $r['file'];

        $this->getLogin()->createQrPay()
            ->appendQr( intval($_POST['account_id']),  intval($_POST['uid']),intval($_POST['fee']),$r['file'] ,$_POST );


        //等用户弄好了 之后 upload 得添加到 upload中去
        //存储到个人doc当中


        $this->assign('file', $r['file'] );
        //$this->assign('p', $p );
    }

}
