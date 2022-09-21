<?php


namespace ctrl;




use ipip\db\City;
use model\drException;
use model\drFun;
use model\drTpl;
use model\lib\cache;
use model\lib\mq;
use model\okex;
use model\trade;
use model\y10086;

class test extends drTpl
{
    function init()
    {
        parent::init();

        $this->htmlFile="hcadmin.phtml";
    }
    function act_huafeiV2( $p ){
        $this->tplFile='debug_hf';
    }
    function act_debug($p){
        $fx= $p[0];
        $this->tplFile='debug';

        switch ($p[0]){
            case 'hf':
                break;
            case 'yue':
            case 'orderCheck':
                $fx ='ex/'.$p[0] ;
            case 'pay':
            case 'ex': #汇出
            case 'exConfirm': #汇出
            case 'huafei': #话费下单
            case 'huafeiCheck': #话费查询
                if( in_array( $p[0],[ 'huafei','huafeiCheck' ] )) $fx= 'huafei/'. $p[0];

                $trade= new trade();
                $app_secret= $_POST['app_secret'];
                unset( $_POST['app_secret'] );

                $var= $_POST;
                foreach( $var as $k=>$v )  $var[$k]= urldecode( $v );
                ksort($var);

                $re['sign']= $var['sign']= $trade->setSecret( $app_secret )->createSign( $var );

                $post='';
                foreach( $var as $k=>$v ) $post.= $k."=".urlencode($v).'&';
                $post = trim( $post,'&') ;


                $curl ='curl -k -d "' . $post . '"  https://api.biqiug.com/api/'. $fx;
                $re['post'] =$post;
                $re['curl'] =$curl;
                $re['md5'] = $trade->getMd5Str();

                $this->assign('rz', $re );

                break;
        }

        //$this->htmlFile="app/debug.phtml";

    }
    function act_oos( $p ){

        switch ($p[0]){
            case 'callBack':
                header("Content-Type: application/json");
                $data = array("Status"=>"Ok");
                //echo json_encode($data);
                $this->drExit( json_encode($data) );
                break;
            case 'start':
            case 'start2':
            case 'start3':

                $id= 'LTAI4FveS863umu6JLUiKQvT';          // 请填写您的AccessKeyId。 用户登录名称 okqr@1789832679691247.onaliyun.com

                $key= 'C6jZCQICrPWQLiKKTRYQH0d6bdq6ol';     // 请填写您的AccessKeySecret。
                // $host的格式为 bucketname.endpoint，请替换为您的真实信息。
                #$host = 'http://bucket-name.oss-cn-hangzhou.aliyuncs.com';
                $host = 'https://okqr.oss-cn-shenzhen.aliyuncs.com';
                // $callbackUrl为上传回调服务器的URL，请将下面的IP和Port配置为您自己的真实URL信息。
                $callbackUrl = 'http://88.88.88.88:8888/aliyun-oss-appserver-php/php/callback.php';
                $callbackUrl = 'https://qq.atbaidu.com/test/oos/callBack';
                $dir = 'alioss/qr/'.date("Ym")."/". date("d").'/';          // 用户上传文件时指定的前缀。

                if( $p[0]=='start3') $dir = 'alioss/pz/'.date("Ym")."/". date("d").'/';          // 用户上传文件时指定的前缀。

                $callback_param = array('callbackUrl'=>$callbackUrl,
                    'callbackBody'=>'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
                    'callbackBodyType'=>"application/x-www-form-urlencoded");
                $callback_string = json_encode($callback_param);

                $base64_callback_body = base64_encode($callback_string);
                $now = time();
                $expire = 30;  //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问。
                $end = $now + $expire;
                $expiration = $this->gmt_iso8601($end);


                //最大文件大小.用户可以自己设置
                $condition = array(0=>'content-length-range', 1=>0, 2=>1048576000);
                $conditions[] = $condition;

                // 表示用户上传的数据，必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
                $start = array(0=>'starts-with', 1=>'$key', 2=>$dir);
                $conditions[] = $start;


                $arr = array('expiration'=>$expiration,'conditions'=>$conditions);
                $policy = json_encode($arr);
                $base64_policy = base64_encode($policy);
                $string_to_sign = $base64_policy;
                $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));

                $response = array();
                $response['accessid'] = $id;
                $response['host'] = $host;
                $response['policy'] = $base64_policy;
                $response['signature'] = $signature;
                $response['expire'] = $end;
                $response['callback'] = $base64_callback_body;
                $response['dir'] = $dir;
                if($p[0]=='start'){
                    header("Content-Type: application/json");
                    $this->drExit(json_encode($response));
                }else {
                    $this->assign('dp', $response );
                }
                break;
        }

    }
    function gmt_iso8601($time) {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration."Z";
    }

    function getIP2($ip){
        $tarr= explode('.' ,$ip );
        unset( $tarr[ count($tarr)-1]);
        unset( $tarr[ count($tarr)-1]);
        $ip2= implode('.', $tarr);
        return $ip2;
    }

    function act_huafei($p){
        switch ($p[0]){
            case 'debug':
                $this->tplFile="debug_hf";
                break;
            case '10086':
                $this->tplFile="10086";
                break;
            case '10086LoginSms':
                $this->getLogin()->createY10086()->sendSms( $p[1],'login');
                $this->redirect('','短信发送成功');
                break;
            case 'login':
                $var= $_POST;
                $y10086= new y10086();

                //$y10086->setCookie('CITY_INFO=100|100; cmccssotoken=1daf5266ad5a4f098e77e28052f7a7ac@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7ciMaCnvRGGKQ5C9HR/8Dc7ufDnEseMbY9i4cAM0dx4I3HJijpPO2TeIbk2iaOlHOM0GGAja0rCx6G9VFz8VIQcw8nUTGxohmRS8MKjblhsnXQ==; c=1daf5266ad5a4f098e77e28052f7a7ac; verifyCode=5b530a29be4ea8122754df2d46828527e638e90d; CITY_INFO=100|100; jsessionid-echd-cpt-cmcc-jt=134B18CDE7F7A0940E03B0E6B965938C');
                //$y10086->setCookie('CITY_INFO=100|100; cmccssotoken=f81d14b9987a47cba513507240acef12@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7ciMaCnvRGGKQ5C9HR/8Dc7ufDnEseMbY9i4cAM0dx4I3HJijpPO2TeIbk2iaOlHOM0Bfq8lE5N7u680XeUEvo1J8nUTGxohmRS8MKjblhsnXQ==; c=f81d14b9987a47cba513507240acef12; verifyCode=54e8a6c368984f8b31ce329c0e62fa5da602cc61; CITY_INFO=100|100');
                //$y10086->test();

                $y10086->login($var['telA'] , $var['yzm']);
                $this->assign('cookie', $y10086->getCookie() ); //获取到的cookie

                //$cookie='CITY_INFO=100|100; cmccssotoken=018cb8e32b1b4cbb94d8b835cb810031@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7ciMaCnvRGGKQ5C9HR/8Dc7ufDnEseMbY9i4cAM0dx4I3HJijpPO2TeIbk2iaOlHOM2FA2r+zpK4Ch5TIaCJJBcg8nUTGxohmRS8MKjblhsnXQ==; c=018cb8e32b1b4cbb94d8b835cb810031; verifyCode=46107d022e121ed198c4bd96e83dc50a7cc9438e; CITY_INFO=100|100';
                //$this->assign('cookie', $cookie );
                $this->redirect('','登录成功');
                break;
            case 'order':
                $y10086= new y10086();
                $y10086->setCookie($_POST['cookie']);
                //$this->drExit($_POST);
                $list= $y10086->getOrderList( $p[1]);
                $this->assign('list', $list );
                $this->redirect('','获取成功');
                break;
            case 'getBill':
                $var= $_POST;
                $y10086= new y10086();
                $cookie= trim($_POST['cookie']); //'channel=0705; ssologinprovince=null; CmLocation=100|100; CmProvid=bj; cart_code_key=pau7jv2sl5upjhi2jkoo5hiuj4; cmcc_guide=20151117; collect_id=oh8n0s2pn5cch53n6vwf3b29ph8urbau; WT_FPC=id=28f14d126683ea62caf1577701341218:lv=1578214067910:ss=1578213992569; CaptchaCode=YYrJWV; rdmdmd5=72A1004281B8254BC7B230BE69198451; sendflag=20200105203828818341; cmccssotoken=1431d883434748a5b3017e84a21cd273@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7ciMaCnvRGGKQ5C9HR/8Dc7ufDnEseMbY9i4cAM0dx4I3HJijpPO2TeIbk2iaOlHOM0TvggTRVjjmy1Cd/uaXfS48nUTGxohmRS8MKjblhsnXQ==; c=1431d883434748a5b3017e84a21cd273; verifyCode=e533c73e9dd7208faeabdcfeff614a8473c1a4d1; jsessionid-echd-cpt-cmcc-jt=90B090CB3F63AE39741362DF29B750CC';
                $y10086->setCookie($cookie);
                $re= $y10086->getBill($var['telB'], $var['money']);
                $url= $re['data']['payUrl'];
                //$url= 'https://pay.shop.10086.cn/paygw/470956990178369161-1578230932329-cec440eeafd2a2a5a2573ae3a8632f89-20.html';
                //$url= 'https://pay.shop.10086.cn/paygw/470957269178230501-1578231354993-0708b85cc9b34335325b747dd1c50113-20.html';

                $this->assign('link', $url);
                $re=$y10086->getPayLink($url ,['debug'=>1] );

                $this->assign('pay', $re );
                $this->redirect('','获取成功');
                break;
        }
    }

    function act_t40( $p ){
        /**
         * var bankName =  "工商银行";;
        var bankMark = "ICBC";
        var amount = "199.98";
        var cardIndex ="2002201801058271951";
        var bankAccount ="黄承贺";
         */

        $hb=['money'=>1,'bankMark'=>'ICBC','bankName'=>'工商银行','bankAccount'=>'黄承贺','cardId'=>'2002201801058271951'];
        $this->assign('hb', $hb);
        $this->htmlFile='app/v40v212.phtml';
        switch ($p[0]){
            case '123':
                $url='https://qz.atbaidu.com/test/t40';
                $url2= 'alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. urlencode($url );

                $this->assign('url', $url2);
                $this->htmlFile='app/t123.phtml';
                break;
            case 'b1':
                $this->htmlFile='app/t40_b1.phtml';
                break;
            case 'b2':
                $this->htmlFile='app/t40_b2.phtml';
                break;
            case 'b2zz':
                $url='alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&userId=2088232932547186';
                $url.='&amount=1.00&money=1.00'    ;
                $this->assign('url', $url );
                $this->htmlFile='app/t40_b2zz.phtml';
                break;
            case 'b2sao':
                $this->assign('url', 'alipays://platformapi/startapp?appId=20000123&actionType=scan&goBack=NO&biz_data={"s": "money","u": "2088232932547186","a": "1.00","m": "80086"}' );
                $this->htmlFile='app/t40_b2zz.phtml';
                break;
        }

    }

    function act_dw( $p ){
        if( $p[0]=='dl'&&in_array( $p[1],['VirtualXposed_0.16.1.apk','alipay_v10.1.38.apk','qfZhuV2.2.9_3.apk','wo_akds_v2.apk'] ) ){
            $p[0]='dl2';
        }
        //$url= 'http://cdn.becunion.com/'. implode('/',$p );
        $url= 'https://cdn.nekoraw.com/'. implode('/',$p );
        $this->redirect( $url);
    }

    function act_info(){
        phpinfo();
        $this->drExit();
    }

    function apiError( $ex ){
        $this->logErr(  $ex->getCode()."\n" .$ex->getMessage()."\n". $ex->getTraceAsString() );
        $html='<!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
</head><body><div style="text-align:center;padding-top: 50px;">'. $ex->getMessage() .'</div></body></html>';
        $this->drExit( $html );
    }

    function act_webHook( $p ){
        switch ($p[0]){
            case 'potato':
                $update = file_get_contents('php://input');
                $update = json_decode($update, TRUE);
                $this->log("potatoWebHook>>".json_encode( $update) );
                break;
            default:
                $update = file_get_contents('php://input');
                $update = json_decode($update, TRUE);
                $this->log("telegramWebHook>>".json_encode( $update) );
        }

        //$this->log("myTest>>"  );
        $this->drExit("ok");
    }


    function act_tool($p){
        switch ($p[0]){
            case 'okex2':
                $test = new \model\test();
                $test->okexJD2Telegram();
                break;

            case 'okex':

                $test = new okex();
                if($p[1]=='aze'){
                    $test->aZeHeader();
                }
                else{
                    $test->realHeader();
                }
                //$test->yueSellOut(1);
                $yuebao= $test->getYueBao();

                //$this->drExit($yuebao);
                $re = $test->okexJiedai();
                $yue = $test->getYue();



                echo '<pre>';
                echo "余额宝=".$yuebao."\n";
                print_r( $yue);

                echo "<br>-----".$test->getCanAmount($yue,$re )."----<br>";
                echo '<a href="/test/tool/okbuy/'.$re['investBound'].'/'.$re['borrowOrderId'].'/'. $re['period'].'?display=json">buy</a>
            <br>
';
                echo "<br>--变动---".$test->_change."次----<br>";
                $this->drExit($re);
                break;
            case 'okbuy':

                $ok=new okex();
                //$ok->realHeader();


                //print_r()
                $re = $ok->buy( intval($p[1]), $p[2], $p[3]);
                $this->drExit($re);
                break;
            case 'yue':
                $this->assign('yue', $this->getLogin()->redisGet('mYue'. $p[1]));

                //$this->drExit('ddd');
                break;
            case 'dx':

                $tall= $this->getLogin()->createTablePayLog()->getAll(['user_id'=>'4761'],['id'=>'desc'],[0,3000],['ctime','trade_id','opt_value'] );
                $tel=[];
                foreach ($tall as $v){
                    $opt= drFun::json_decode($v['opt_value']);
                    $key= $opt['title'];
                    if( strlen($key )<=5) continue;
                    $tel[ $opt['title'] ]++;
                    if( in_array( $key,['+8613165600411']) ){
                        echo "[".date("Y-m-d H:i:s", $v['ctime'])."] " .$v['trade_id']." ".$key ." ". $opt['text']. "\n<br>\n";
                    }
                }
                asort($tel);
                $r2=[];
                foreach($tel as $k=>$v){
                    if(intval( substr($k,0,1)) <=0 || in_array(substr($k,0,2),['86','13','14','15','16','17','18','19'] )) continue;

                    if($v>2) $r2[]=$k;
                }

                //var_export($r2);
                echo "'".implode("','",$r2)."' \n<br>";

                $re2=['106905695528','106903409551186','106380096599','106350196368','10692799551186','1069295561','106980095533','106905961896518','106980096518','10691995558','106905695559','10692955611','10698000962999','1069095599','1069199596599','10698000096558','106575580180','106980096328','1069070996599','1065905596588','10690329296368','106927995511','1069800096368','955581101','1069800096511','9555801' ];

                print_r( array_merge($re2,['ab','abc']));
                $this->drExit($tel);
                break;
            case 'dt':
                $str='您8585账户于8月21日21:28:01在支付宝快捷支付消费11.10元，详查 pingan.com/foMI 【平安银行】';
                $str='您尾号1417的人民币活期账户，于2020年08月21日23:59:42支付宝付款存入600.00元，余额1,660.00元';
                //$str='您尾号1417的人民币活期账户，于2020年08月21日07支付宝付款存入600.00元，余额1,660.00元';
                $str='您尾号5232的储蓄卡8月21日0时9分支付宝提现收入人民币0.01元,活期余额1472.22元。[建设银行]';
                $trade = new trade();
                echo $trade->getTimeFromStr($str)."<br>\n";
                $this->drExit( 'mt='. $trade->timeDtMinute($str, strtotime('2020-08-21 00:01:09') ) );
                break;
            case 'tg':
                $test= new \model\test();
                $re = $test->telegramSendMessage( -344503692,'我们就搞搞！');
                $this->drExit($re);
                break;
            case 'potato':
                $test= new \model\test();
                $re = $test->potatoSendMessage(12381385,'你好 Potato！');
                $this->drExit($re);
                break;
            case 'exorder':
                $where=['type'=>[1,11],'user_id'=>'4335','lo'=>'湖南'];
                $this->getLogin()->createExport()->exOrderByWhere( $where );

                break;
            case 'sms':
                $pay['text']='您尾号4279卡6月14日10:48网上银行支出(跨行汇款)1,800元，余额0.05元。【工商银行】';
                //if( strpos($pay['text'],'尾号7467')!==false) echo "ok\n\n<br>";
                $re=[];
                //preg_match_all( '/([\d\.,]+)元/i', $pay['text'],$re);
                preg_match_all( '/([\d\.,]+)元/i', $pay['text'],$re);
                echo drFun::yuan2fen($re[1][0]);
                $this->drExit($re );
                break;
            case 'sms2':
                $pay['text']='【招商银行】您账户8407于06月17日他行实时转入人民币11.11，付方廖海云。尊享购物优惠速领 cmbt.cn/ijBeFY 。';
                //if( strpos($pay['text'],'尾号7467')!==false) echo "ok\n\n<br>";
                $re=[];
                //preg_match_all( '/([\d\.,]+)元/i', $pay['text'],$re);
                preg_match_all( '/(\d+\.\d+)/i', $pay['text'],$re);
                echo drFun::yuan2fen($re[1][0]);
                $this->drExit($re );

                break;
            case 't223':
                //$this->drExit($_GET);
                $hongbao=['price'=> '0.01' ,'b_name'=> trim($_GET['n']) ];
                $hongbao['b_account']= trim($_GET['no']);
                $hongbao['b_add']= '';
                $hongbao['b_bank']= trim($_GET['bn']);
                $hongbao['time']= 600;

                $this->assign('hongbao' ,$hongbao );

                $this->htmlFile='app/ali145.phtml';

                break;
            case 't222':
                try{
                    $account_id = intval($p[1]);
                    if( $account_id<=0 ) $this->throw_exception('参数缺失');
                    $account =$acc= $this->getLogin()->createTablePayAccount()->getRowByKey( $account_id);

                    if(! in_array( $this->getLogin()->getUserId(), [$acc['user_id'], $acc['ma_user_id'] ] )){
                        $this->throw_exception('非法访问！');
                    }

                    $bankType = $this->getLogin()->createQrPay()->getBankType();

                    $hongbao=['price'=> '0.01' ,'b_name'=> $account['zhifu_name']];
                    $hongbao['b_account']= $account['zhifu_account'];
                    $hongbao['b_add']= $account['zhifu_realname'];
                    $hongbao['b_bank']=$bankType[ $account['bank_id'] ]['n'];
                    $hongbao['time']= 600;

                    $this->htmlFile='app/ali145.phtml';

                }catch (\Exception $e){
                    //$this->drExit($e->getMessage() );
                    $this->apiError( $e);
                }
                break;
            case 'rate':

                $merchant = $this->getLogin()->createTableMerchant()->getColByWhere('1',['merchant_id','rate'] );

                foreach($merchant as $mid=>$rate){
                    if( $rate<=0) continue;
                    $sql="update mc_trade set rate=ROUND( `realprice`*".$rate."/10000)  where merchant_id='".$mid."' and type in(1,11)";
                    //echo $sql.";\n<br>";
                }

                /*
                $trade = $this->getLogin()->createTableTrade()->getAll( ['type'=>[1,11]],['trade_id'=>'desc'],[0,50000],['trade_id','merchant_id','realprice']);

                foreach( $trade as $k=> $v){

                    $rate= round( $merchant[$v['merchant_id']]*$v['realprice'] /10000);
                    $trade[$k]['rate']= $rate;
                    if( $rate==0) continue;

                    $this->getLogin()->createTableTrade()->updateByKey($v['trade_id'],['rate'=>$rate] );
                    //print_r( $v );

                    //$this->drExit( $v['trade_id'].'='. $rate );
                }
                */

                //$this->assign('merchant', $merchant )->assign('trade', $trade );
                break;
            case 'g4':
                $hi= intval(date('i'))+11;
                $hh= intval(date('H'))+11;
                //$f5= $hi%3;
                $acode = substr($hi,1,1).  substr($hh,1,1). '39'.  substr($hi,0,1).substr($hh,0,1)  ;
                $acode = substr($hh,1,1).substr($hi,0,1). '39' . substr($hi,1,1).    substr($hh,0,1)  ;
                //$this->drExit( 'code='. $acode );
                break;
            case 'testPayMatch':
            case 'payMatch':
                $this->getLogin()->createQrPay()->payMatchByLogID( $p[1] );
                break;
            case 'mabu':

                $re=$this->getLogin()->createVip()->maTrdeBu( '126' );
                $this->assign('mabu', $re );
                break;
            case '10086':
                $y10086= new y10086();

                $y10086->login($p[1] ,$p[2]);
                //$cookie='CITY_INFO=100|100; cmccssotoken=510f707c510143d39bb7d6d192b8f618@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5widFM35Cm3y3C+ek+3KUzXWSMTsRVyT13VInOal6sQlEY+dvBVErR/ksPv5W6XILGzNIChi3gihwmhVzzoGOae/Wf05PMmV+8xg3RxlK4W1OmNwSyqpJkEqg/LuT1QHsyO; c=510f707c510143d39bb7d6d192b8f618; verifyCode=1f3d53e9d368cd39fc297e1c5881bcefbf8998de; CITY_INFO=100|100';
                $cookie='CITY_INFO=100|100; cmccssotoken=3485bcaf1c194c1291f558044272341d@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5wiDSOPqpAhg4a3OUkDSMhMpCMTsRVyT13VInOal6sQlEY+dvBVErR/ksPv5W6XILGzNIChi3gihwmhVzzoGOae/YzbFyH/FDCfoob256Swz+SNwSyqpJkEqg/LuT1QHsyO; c=3485bcaf1c194c1291f558044272341d; verifyCode=e02a80584848bea8c3cb44482ffe22eaa7a3b03f; CITY_INFO=100|100; channel=0705; jsessionid-echd-cpt-cmcc-jt=6D8FFA1B32A7F31CE4CDA9A54E515516';

                $cookie='channel=0705; login=true; channel=0705; ssologinprovince=100; CmLocation=100|100; CmProvid=bj; cart_code_key=pau7jv2sl5upjhi2jkoo5hiuj4; cmcc_guide=20151117; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5widFM35Cm3y3C+ek+3KUzXWSMTsRVyT13VInOal6sQlEY+dvBVErR/ksPv5W6XILGzNIChi3gihwmhVzzoGOae/Wf05PMmV+8xg3RxlK4W1OmNwSyqpJkEqg/LuT1QHsyO; verifyCode=1f3d53e9d368cd39fc297e1c5881bcefbf8998de; collect_id=oh8n0s2pn5cch53n6vwf3b29ph8urbau; jsessionid-echd-cpt-cmcc-jt=A2D1221B2E0118B37EC24D6CB2B16F54; chargeresource=s%3D~e%3D~c%3D~taskId%3D~tag%3D; CaptchaCode=wuBYAX; rdmdmd5=F77DE57BF658D0CDE713F4F8931B4D31; sendflag=20200104234121531663; cmccssotoken=0bb814d1f6ff41fb917d7992cebf0f29@.10086.cn; is_login=true; c=0bb814d1f6ff41fb917d7992cebf0f29; WT_FPC=id=28f14d126683ea62caf1577701341218:lv=1578152409839:ss=1578148342572';

                $re = $y10086->setCookie($cookie)->getBill('15010133879', 10);
                //$this->drExit($re );

                $url='https://pay.shop.10086.cn/paygw/470880484178202341-1578154085462-eb482e494f5ecf93a95b4103913771a0-20.html';
                $url='https://pay.shop.10086.cn/paygw/470880484178202341-1578155596622-bb2ad731e0fdcbdf715fce4558a54ca3-20.html';
                $url= $re['data']['payUrl'];
                $re= $y10086->getPayLink($url ,['debug'=>1] );

                break;
            case 'en':
                $key='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsgDq4OqxuEisnk2F0EJFmw4xKa5IrcqEYHvqxPs2CHEg2kolhfWA2SjNuGAHxyDDE5MLtOvzuXjBx/5YJtc9zj2xR/0moesS+Vi/xtG1tkVaTCba+TV+Y5C61iyr3FGqr+KOD4/XECu0Xky1W9ZmmaFADmZi7+6gO9wjgVpU9aLcBcw/loHOeJrCqjp7pA98hRJRY+MML8MK15mnC4ebooOva+mJlstW6t/1lghR8WNV8cocxgcHHuXBxgns2MlACQbSdJ8c6Z3RQeRZBzyjfey6JCCfbEKouVrWIUuPphBL3OANfgp0B+QG31bapvePTfXU48TYK0M5kE+8LgbbWQIDAQAB';

                $this->drExit( urlencode( drFun::publicEncrypt( '424323',$key ) ) );
                break;
            case 'ip2':
                $sign='449b479f3be0d1cc07633ed07d176b81';
                //115.194.7.250
                $ip='42.120.102.31';
                $ip='123.119.232.51';
                //62cf6db39fba0e4da31dbb87409f0aeb
                $this->drExit($this->getIP2($ip) .'='. md5( $sign .'qfs'. $this->getIP2($ip) ));
                break;
            case 'safe':
                $this->getLogin()->checkSafeFromExt();
                #$this->drExit( ($_SERVER ) );
                break;
            case 'server':

                echo'<pre>';
                echo "\n\n";
                /*
                foreach (getallheaders() as $name => $value) {
                    echo $name.": ".$value."\n";
                }
                */
                $this->drExit( ($_SERVER ) );
                break;
            case 'ipclear':
                try {
                    $redis= $this->getLogin()->createRedis();
                    $redis->del('LOIP');
                    $redis->close();
                    $this->drExit('ip is clear' );

                }catch (drException $ex ){

                }
                break;
            case 'ipall':
                try {
                    $redis= $this->getLogin()->createRedis();
                    if( $p[1]=='clear'){
                        $redis->del('LOIP');
                    }
                    $loip=$redis->hGetAll('LOIP' );

                    $redis->close();
                    echo '<pre>';
                    $this->drExit($loip );

                }catch (drException $ex ){

                }
                break;
            case 'ip':
                //getIpAll
                //$this->getLogin()->checkIpSafe();
                $http_host = strtolower( $_SERVER['HTTP_HOST'] );
                echo $http_host."\n<br>";
                $realIp= drFun::getRealIP();
                #if( !$realIp ) $realIp= drFun::getIP();
                echo 'realIP='.$realIp."\n<br>";
                echo 'date='.date('H:i:s')."\n<br>";

                //$this->getLogin()->redisSet( ,);


                try {
                    $redis= $this->getLogin()->createRedis();
                    $redis->hIncrBy('LOIP', $realIp, 1);
                    if($_GET['del']==1 || $p[1]=='clear'){
                        $redis->del('LOIP');
                        //$redis->setTimeout( 'LOIP',30 );
                    }
                    //$redis->hDel('LOIP',);
                    //$redis->setex
                    $redis->close();
                }catch (drException $ex ){

                }


                //print_r($_SERVER );

                $this->drExit( drFun::getIpAll() );
                break;
            case 'where':
                $where = $this->getLogin()->createSql()->arr2where( ['ali_trade_no' => $re['chatroom'],'!='=>['ali_beizhu'=>''] ] );
                $this->drExit( $where );
                break;
            case 'redis2':
                $cache = new cache();
                $result= $cache->getRedis()->set('test456','455' );
                var_dump($result);
                $user_name = $cache->getRedis()->get('test456');
                $this->drExit( $user_name );
                break;
            case 'redis':
                $redis = new \redis();
                //$redis->connect('127.0.0.1', 6379);
                $redis->pconnect('redis.server.haoce.com', 6379);
                $result = $redis->set('test_123',"11111111111");
                var_dump($result);

                $rz= $redis->get('test_123');

                echo "\n\n";
                print_r($rz);

                $this->drExit();
                break;
            case 'psms':
                $this->getLogin()->createTablePaySms()->append([ 'account_id'=>123 , 'ctime'=>time(), 'text'=> 'good' ]);

                break;
            case 'yj':
                $pid = $p[1]>0 ?$p[1] :12160;
                $re= $this->getLogin()->createTest()->cAccount($pid );

                $this->drExit('aid='. $pid."\t total=". ($re['total']/100));
                break;
            case 'yj2':
                $where=['user_id'=> 1187,'type'=>3];
                $acc = $this->getLogin()->createQrPay()->getAccountIDByWhere($where,['all'=>1 ]);
                $total=0;
                foreach( $acc as $v){
                    $re= $this->getLogin()->createTest()->cAccount( $v['account_id'] );
                    echo  $v['account_id']."\t". $v['zhifu_name']." total=". ($re['total']/100) ."<br>\n";
                    $total+= ($re['total']/100);
                }
                $this->drExit("<br>\ntotal=". ($total));
                break;
            case 'yj3':
                $where=['user_id'=> 1187,'type'=>3];
                $acc = $this->getLogin()->createQrPay()->getAccountIDByWhere($where,['all'=>1 ]);
                $total=0;
                $trade=[];
                foreach( $acc as $v){
                    $re= $this->getLogin()->createTest()->cAccount( $v['account_id'] );
                    //echo  $v['account_id']."\t". $v['zhifu_name']." total=". ($re['total']/100) ."<br>\n";
                    $total+= ($re['total']/100);
                    foreach( $re['detail'] as $v2 ){
                        //$this->drExit( $v2 );
                        $tid = $v2[1]['trade_id'];
                        $trade[$tid]= $v2[1]['trade_id'];
                    }
                }
                $wh= ['trade_id'=> array_values( $trade) ,'type'=>1  ];
                $merchant =  $this->getLogin()->createQrPay()->tjTradeGroup('merchant_id', $wh );

                $mc_name= $this->getLogin()->createQrPay()->getMerchantByID( array_keys( $merchant ) );
                $fun= function ( $a,$b){
                    return $a['realprice']<$b['realprice'];
                };
                usort( $merchant, $fun );
                //print_r( $merchant);
                $total=0;
                foreach($merchant as $v){
                    echo $v['merchant_id']."\t".$mc_name[$v['merchant_id']]['merchant']." cnt=".$v['cnt']." 金额=".($v['realprice']/100)."<br>\n";
                    $total+= ($v['realprice']/100);
                }
                $this->drExit( "<br>\n合计 cnt=". count( $trade )."\t金额=". ($total) );
                break;
            case 'px':
                $acc= $this->getLogin()->createQrPay()->accountPX( '356',($p[1]>0?$p[1]:300 )*100 ,['clear_account'=>1]);
                $this->drExit( $acc );
                break;
            case 'bang':
                $bang= $this->getLogin()->createVip()->phBang( 356 );
                $this->drExit( $bang );
                break;
            case 'cookie':
                $this->getLogin()->setCookieOther( 'b',1 );
                break;
            case 'real':

                $this->drExit('mid='. $this->getLogin()->getRealMid($p[1]));
                break;
            case 'hx':
                $account_id= '10795';
                $account= $this->getLogin()->createQrPay()->getAccountByID( $account_id );
                $api= $this->getLogin()->createTaoboApi($account_id,['account'=> $account]);

                $nick= $account['zhifu_name'];
                $nomber='2827483986';
                $arr = $api->taobao_daogoubao_eticket_query($nick,$nomber );
                $this->drExit( $arr );
                break;
            case 'today': //  tool/today/2019-09-12

                    $u_arr=[ 989];
                    $time= strtotime( $p[1]);

                    foreach($u_arr as $uid ) {
                        $m_arr = $this->getLogin()->getMidFromConsole($uid);
                        foreach ($m_arr as $mid) {
                            $trade_row = ['merchant_id' => $mid, 'ctime' =>  $time ];
                            $this->getLogin()->createQrPay()->toDayByTrade($trade_row);
                        }
                        //$this->drExit($m_arr);
                    }

                    $this->drExit('ok');
                break;
            case 't80':
                $this->assign('url','https://qr.alipay.com/_d?_b=peerpay&enableWK=YES&biz_no=2019082804200381811042845669_aa26f1932c916428865bf3b8ae60b574&app_name=tb&sc=qr_code&v=20190904&sign=f72435&__webview_options__=pd%3dNO');
                $this->htmlFile="app/tool_80.phtml";
                break;
            case 'openall':
                $account_all = $this->getLogin()->createQrPay()->getAccountIDByWhere(['type'=>80] ,['all'=>1] );
                foreach ( $account_all as  $account){
                    $this->getLogin()->createTaoboApi( $account['account_id'],['account'=>$account] )->taobao_tmc_user_permit() ;
                }
                $this->drExit($account_all );
                break;
            case 'log':
                $this->log("dd",'test.log');
                break;
            case 'tb_trade':
                //taobao_trade_fullinfo_get
                $re = $this->getLogin()->createTaoboApi( 9824)->taobao_trade_fullinfo_get($p[1]);
                $this->drExit($re);

                break;
            case 'load':
                $this->htmlFile="app/ali23loadV2.phtml";
                //$this->htmlFile='app/ali23load.phtml';
                break;
            case 'toMain':
                $re= $this->getLogin()->createTaobao()->toDoMain('9824');
                $this->drExit( $re );
                break;
            case 'permit':

                $this->getLogin()->createTaoboApi( 9824)->taobao_tmc_user_permit();
                $this->drExit();
                break;
            case '20190804':
                $file= dirname(dirname(dirname(__FILE__))).'/webroot/lab/20190804.log';
                echo $file."\n\n\n";
                $handle  = fopen ($file, "r");
                $str_start= false;
                $str='';

                while (!feof ($handle))
                {
                    $buffer  = fgets($handle, 4096);
                    $line = trim($buffer);

                    if( strpos( $line,'content] => <msg>')){
                        $str= $line;
                        $str_start= true;
                    }elseif ( $str_start  ) {
                        $str = $str. $line;
                        if( strpos( $line,'</appinfo></msg>') ){
                            $str_start= false;

                            $str= strtr( $str,['[content] =>'=>'']);

                            $c_arr =  drFun::xml_to_array( $str )  ;
                            if( !$c_arr  ) $this->drExit($str );
                            $re=[];
                            $re['fee']=   drFun::yuan2fen( $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['value']['word'] ); ;
                            $re['ctime']=   $c_arr['appmsg']['mmreader']['template_header']['pub_time'];
                            $des  =  $c_arr['appmsg']['mmreader']['category']['item']['digest'];;//appmsg.mmreader.category.item.digest

                            $dy = drFun::cut($des,'存入店长' ,'(*');
                            $qm = drFun::cut($des,'存入店长' ,'的零钱');

                            if(  $re['fee'] >=10000 && strpos($des,'款金额') ) echo "\n[". date('m-d H:i:s',$re['ctime'])."]\t".$des ;

                            //$this->drExit($str);
                        }
                    }


                 }

                fclose ($handle);

                $this->drExit();
                break;
            case 'fail':
                $acc = $this->getLogin()->createTest()->countFailCntByWhere( ['account_id'=>[5836,5837,5835] ]);

                $this->drExit( $acc );

                break;
            case 'lopl':
                $this->drExit( "exit");
                $acc= $this->getLogin()->createQrPay()->getAccountIDByWhere(['type'=>211 ,'user_id'=>324 ] ); //, 'lo'=>''
                foreach( $acc as $account_id ){
                    $this->getLogin()->createQrPay()->getAccLoByTrade( $account_id ,[ 'isUp'=>1 ] );
                }
                $this->drExit( $acc );
                break;
            case 'lo':
                $account_id = $p[1];
                $row = $this->getLogin()->createQrPay()->getAccLoByTrade( $account_id ,[ 'isUp'=>1 ] );
                //if( $row ) $this->getLogin()->createQrPay()->up( $account_id, ['lo'=>$row[0]['lo'] ]);
                $this->drExit( $row );
                break;
            case 'mather':
                $re=[];
                $this->getLogin()->createVip()->treeMather( 537, $re,$opt=[]);
                $this->drExit( $re );
                break;
            case 'godSql':
                $sql ="SELECT `ali_trade_no`, count(*) as cnt FROM `pay_log_tem` WHERE type in(60,61,62) GROUP by `ali_trade_no` HAVING cnt>1";
                $col= $this->getLogin() ->createSql($sql)->getCol2();
                $this->drExit($col);
                break;
            case '201':
                $this->getLogin()->createQrPay()->timeOut()->timeOut201();
                break;
            case 'fh':
                $this->getLogin()->createVip()->fenLun( '20181010620' );
                break;
            case 'ip':
                $sign=  $this->getLogin()->getCookUser('sign');
                 // echo 'sign='.$sign."<br>ipSign=".$this->getLogin()->getIPSign($sign).'<br>';
                $this->drExit( drFun::getIP() );
                break;
            case 'dd':
                $this->getLogin()->createQrPay()->timeOut78()->timeOut78(62,61 );
                break;
                /*
            case 't222':
                $this->drExit( drFun::yuan2fen("299.91"));
                break;
                */
            case 'g1':
                $url2= 'https://'.drFun::getHttpHost().'/test/tool/g2/' ;
                $url='alipays://platformapi/startapp?appId=20000067&__open_alipay__=YES&url=';//.urlencode( $url2 );
                $arr=[];
                $arr[]=['id'=>'2088332164229802','name'=>'WX017' ];
                $arr[]=['id'=>'2088332277316402','name'=>'WX015' ];


                $this->htmlFile='app/tool_g1.phtml';
                $this->assign('arr', $arr )->assign('url2', $url2);
                break;
            case 'g2':
                $this->assign('p',$p )->assign('biz_data',['u'=>$p[1],'m'=>$p[2],'a'=>'19.99' ] );
                //$this->htmlFile='app/tool_g2.phtml';
                $url2= 'https://'.drFun::getHttpHost().'/test/tool/g4/' ;
                $this->assign('url2',$url2);
                $this->htmlFile='app/tool_g2test.phtml';
                break;
            case 'g3':
                $mq= new mq();
                $mq->rabbit_publish('ali_data',$_POST );
                $this->drExit('good');
                break;
            case 'g4':

                $this->htmlFile='app/tool_g4.phtml';
                break;
            case 'cookie':
                $tr_id= intval($p[1] );
                $this->getLogin()->createQrPay()->tradePaylogCookieNameByTradeID( $tr_id );
//                $trade = $this->getLogin()->createQrPay()->getTradeByID( $tr_id);
//                $this->getLogin()->createQrPay()->searchBuyerFromTrade( $trade )->addCookieName($trade['cookie'], $trade['pay_log']['buyer'] );
//                $this->assign( 'trade', $trade );

                break;
            case 'cookiePL':
                $tr_id= intval($p[1] );
                $every= intval($p[2] );
                if($every<=0) $every=10000;
                $test = new \model\test();
                $test->cookieNamePl($tr_id, $every);
                break;
            case 'proxy':
                $url ='http://www.pigai.org/guest2016.html';
                $data='';
                drFun::cPost( $url, $data,10,[],['proxy'=>['ip'=>'193.112.201.59','port'=>8088] ] );
                $this->drExit( $data );
                break;

            case 'proxy2':
                $url ='https://www.baidu.com/';
                $data='';
                echo $url. "<br>";
                drFun::cPost( $url, $data,10,[],['proxy'=>['ip'=>'193.112.201.59','port'=>8433] ] );
                $this->drExit( $data );
                break;

            case 'tr':
                //ini_set( 'display_errors', '1' ) ;
                //error_reporting( E_ALL );

                $key='TR'.  $p[1];
                $cache = new cache();
                $user_name = $cache->getRedis()->get($key);
                //$this->drExit( $user_name );
                $this->assign('uname',$user_name );
                break;
            case 'ck':
                $this->getLogin()->createQrPay()->addCookieNamePL('m1234567',['god','news'] );
                break;
            case 'cName':
                //$trade= $this->getLogin()->createQrPay()->getTradeByID('20181735608');
                $tr_id= '20181010469';
                if($p[1]) $tr_id=  $p[1] ;
                $trade= $this->getLogin()->createQrPay()->getTradeByID($tr_id);
                $trade['ctime']= time();
                $this->getLogin()->createQrPay()->searchBuyerFromTrade( $trade );
                $url3= 'https://qr.alipay.com/fkx0131319fneqomfhr5ac9?t='.time().'&abc=1';
                $url4='alipays://platformapi/startApp?appId=10000011&url=alipayqr%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007%26clientVersion%3D3.7.0.0718%26qrcode%3Dhttps%253A%252F%252Fqz.atbaidu.com%252Fapi%252Fv40open%252Fc3c1d17e34d7bfd1ad27e26e6a57a099%252F20181866864%252F99978';
                $this->assign('trade', $trade)->assign('url',$url3)->assign('url4', $url4);
                //$this->htmlFile='app/alishowV2.phtml';
                $this->htmlFile='app/alishowV3.phtml';
                if($_GET['v']=='old'){
                    $this->htmlFile='app/alishowV4ios.phtml';
                }else {
                    $this->htmlFile = 'app/alishowV4_8277.phtml';
                }
                //$this->htmlFile='app/alishowV50wx.phtml';
                $this->htmlFile='app/alishowV4s.phtml';
                $this->htmlFile='app/alishowV35.phtml';
                $this->htmlFile='app/alishowV35t2.phtml';


                $client= drFun::getClientV2();
                $this->assign('client', $client);
                break;
            case '1990':
                $fee= '19.90';
                echo (  intval( floatval( $fee )*100 ) );
                $this->drExit("<br>".  intval( 1990 ) );
                break;
            case 'goto':
                $url3= 'https://qr.alipay.com/fkx0131319fneqomfhr5ac9?t='.time().'&abc=1';
                $url= 'alipays://platformapi/startApp?appId=10000011&url='. urlencode($url3);
                //$url= 'alipays://platformapi/startApp?appId=20000067&url='. urlencode($url3);
                //$url= 'alipays://platformapi/startApp?appId=10000007&qrcode='. urlencode($url3);
                $this->drExit('<a href="'.$url.'">' .$url.'</a>' );
                $this->redirect( $url3 );
                break;
            case 'ali':
                $is_ali= strpos( $_SERVER['HTTP_USER_AGENT'], 'AlipayClient');
                // alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=YES
                if( !$is_ali ) $this->drExit("请使用支付宝扫一扫");

                $url='alipays://platformapi/startapp?appId=09999988&actionType=toCard&goBack=NO&amount='.(rand(100,300)/100).'&cardNo=6210810940014537761&bankAccount='.urlencode("夏树刚")."&bankMark=CCB&bankName=".urlencode('中国建设银行');
                //header("Location: ", $url );
                //https://ds.alipay.com/?from=pc&appId=09999988&actionType=toCard&sourceId=bill&cardNo=6230521360013633370&bankAccount=%E5%B4%94%E7%8E%89%E9%BE%99&money=100&amount=&bankMark=ABC&bankName=%E5%86%9C%E4%B8%9A%E9%93%B6%E8%A1%8C
                //$this->redirect($url,"");
                $str='<meta http-equiv="Refresh" content="1;url=\''.$url.'\'" />';
                $this->drExit($str);

                $this->assign('url', $url);
                $this->htmlFile='app/v40open.phtml';

                // alipays://platformapi/startapp?appId=09999988&&actionType=toFastTransfer&&goBack=YES

                //$this->drExit("is_ali=". $is_ali );
                //$this->htmlFile='app/alishowV2.phtml';
                break;
            case 'copy':
                $this->htmlFile='app/ali_copy.phtml';
                break;
            case 'copy2':
                $this->htmlFile='app/ali_copy2.phtml';
                break;
            case 'ts2':
                $id='20181010598';
                $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $id );
                $acc_id = $this->getLogin()->createQrPay()->getAccountIDByMerchantId($trade_row['merchant_id'] ,4 );
                $account =  $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id']  );
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

                $this->drExit( $url);

                break;
            case 'taobao':
                $msg=[];

                $msg['message'] = '{"Id":8529364893180959,"Topic":"taobao_trade_TradeAlipayCreate","PubAppKey":"12497914","PubTime":"2019-09-07 13:45:36","OutgoingTime":"2019-09-07 13:45:36","UserId":2481419844,"UserNick":"果真v美味","Content":"{\"buyer_nick\":\"penglee0208\",\"payment\":\"0.10\",\"status\":\"WAIT_BUYER_PAY\",\"iid\":601994032577,\"oid\":612055618715866263,\"tid\":612055618715866263,\"type\":\"guarantee_trade\",\"post_fee\":\"0.00\",\"seller_nick\":\"果真v美味\"}","Dataid":612055618715866263}';
                $msg['sign'] = 'OGJiODAxNGI4OTc1Mjc3OThjNjFmMzUwNjYxMzBkMTg=';
                $this->getLogin()->createTaobao()->doMessage( $msg );
                break;
            case 'test':
                $data ='{"cls":"com.alipay.mobile.payee.ui.PayeeQRActivity","data":"SyncMessage [userId=2088232932547186, biz=COLLECT-R, msgData=[{\"mk\":181271162805200004,\"st\":1,\"isSc\":0,\"mct\":1546244885296,\"pl\":\"{\\\"payerHeadUrl\\\":\\\"http:\\\/\\\/tfs.alipayobjects.com\\\/images\\\/partner\\\/T13vtwXcxaXXXXXXXX_160X160\\\",\\\"payerLoginId\\\":\\\"dooy520@qq.com\\\",\\\"payerSessionId\\\":\\\"COLLECT_MONEY_PAY_20880021222503361546244884343\\\",\\\"payerUserId\\\":\\\"2088002122250336\\\",\\\"payerUserName\\\":\\\"\u5c0f\u9ed1\\\",\\\"sessionId\\\":\\\"COLLECT_MONEY_RECEIVER_2088232932547186\\\",\\\"state\\\":\\\"0\\\",\\\"userId\\\":\\\"2088232932547186\\\"}\"}], pushData=null, id=181271162805200004, hasMore=false], sOpcode=0]"}' ;
                $data ='{"cls":"com.alipay.mobile.payee.ui.PayeeQRActivity","data":"SyncMessage [userId=2088232932547186, biz=UCHAT"}';


                //$re = drFun::json_decode($data ) ;
                $re = ['cls'=>'com.alipay.mobile.payee.ui.PayeeQRActivity','data'=>'SyncMessage [userId=2088232932547186, biz=COLLECT-R, msgData=[{"mk":181271162823200001,"st":1,"isSc":0,"mct":1546244903171,"pl":"{\"amount\":\"0.02\",\"payerHeadUrl\":\"http:\/\/tfs.alipayobjects.com\/images\/partner\/T13vtwXcxaXXXXXXXX_160X160\",\"payerLoginId\":\"dooy520@qq.com\",\"payerSessionId\":\"COLLECT_MONEY_PAY_20880021222503361546244884343\",\"payerUserId\":\"2088002122250336\",\"payerUserName\":\"小黑\",\"sessionId\":\"COLLECT_MONEY_RECEIVER_2088232932547186\",\"state\":\"1\",\"transferNo\":\"2018123122001450330563061354\",\"userId\":\"2088232932547186\"}"}], pushData=null, id=181271162823200001, hasMore=false], sOpcode=0]'] ;
                $re = ['cls'=>'com.alipay.mobile.payee.ui.PayeeQRActivity','data'=>'SyncMessage [userId=2088432019144040, biz=COLLECT-R, msgData=[{"mk":190142120517200001,"st":1,"isSc":0,"mct":1546401917525,"pl":"{\"amount\":\"499.99\",\"payerLoginId\":\"13580044412\",\"transferNo\":\"20190102200040011100460054916074\",\"payerUserName\":\"彬\",\"sessionId\":\"COLLECT_MONEY_RECEIVER_2088432019144040\",\"state\":\"2\",\"payerSessionId\":\"COLLECT_MONEY_PAY_2088022908478460_1546401904144\",\"payerUserId\":\"2088022908478460\",\"userId\":\"2088432019144040\"}"}], pushData=null, id=190142120517200001, hasMore=false], sOpcode=0]'] ;
                $str = <<<EOF
SyncMessage [userId=2088432093628593, biz=MSG-BILL, msgData=[{"mk":190142120246200001,"st":1,"isSc":0,"appId":"","mct":1546401766000,"pl":"{\"templateType\":\"BN\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/rmsportal\/EMWIWDsKUkuXYdvKDdaZ.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190102200040011100160058191041&bizType=D_TRANSFER?tagid=MB_SEND_PH\",\"businessId\":\"PAY_HELPER_CARD_2088432093628593\",\"msgId\":\"eaf28e46f0b31f7547ba354a26f65ced5659\",\"templateCode\":\"00059_00094_zfzs001\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"title\":\"收到一笔转账\",\"gmtCreate\":1546401766390,\"content\":\"{\\\"status\\\":\\\"收到>一笔转账\\\",\\\"date\\\":\\\"01月02日\\\",\\\"amountTip\\\":\\\"\\\",\\\"money\\\":\\\"99.99\\\",\\\"unit\\\":\\\"元\\\",\\\"infoTip\\\":\\\"\\\",\\\"failTip\\\":\\\"\\\",\\\"goto\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190102200040011100160058191041&bizType=D_TRANSFER\\\",\\\"content\\\":[{\\\"title\\\":\\\"付款方：\\\",\\\"content\\\":\\\"贝        才 177******83\\\"},{\\\"title\\\":\\\"转账备注：\\\",\\\"content\\\":\\\"转账\\\"},{\\\"title\\\":\\\"到账时间：\\\",\\\"content\\\":\\\"2019-01-02 12:02\\\"}],\\\"ad\\\":[],\\\"actions\\\":[{\\\"name\\\":\\\"\\\",\\\"url\\\":\\\"\\\"},{\\\"name\\\":\\\"查看详情\\\",\\\"url\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190102200040011100160058191041&bizType=D_TRANSFER\\\"}]}\",\"linkName\":\"\",\"gmtValid\":1548993766375,\"operate\":\"SEND\",\"bizName\":\"支付助手\",\"templateName\":\"支付助手\",\"homePageTitle\":\"支付助手: ￥99.99 收到一笔转账\",\"status\":\"\",\"extraInfo\":\"{\\\"content\\\":\\\"￥99.99\\\",\\\"assistMsg1\\\":\\\"收到一笔转账\\\",\\\"assistMsg2\\\":\\\"转账\\\",\\\"linkName\\\":\\\"\\\",\\\"buttonLink\\\":\\\"\\\",\\\"templateId\\\":\\\"WALLET-FWC@remindDefaultText\\\"}\"}"}], pushData=, id=77,190142120246200001,1, hasMore=false], sOpcode=0]
EOF;
                $str= <<<EOF
[{"billByMonthJumpUrl":"alipays://platformapi/startapp?appId=66666798&url=%2Fwww%2FrealtimeBill%2Findex.html","billListItems":[{"canDelete":false,"contentRender":0,"gmtCreate":0,"isAggregatedRec":false,"month":"本月","recordType":"MONTH","serializedSize":60,"statistics":"支出 ￥49.97    收入 ￥23.73","tagStatus":0,"unknownFieldsSerializedSize":0},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330054018081%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330054018081","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"哈哈哈-小黑","contentRender":1,"createDesc":"今天","createTime":"17:06","gmtCreate":1546592816000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":430,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330054232269%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330054232269","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"转账-小黑","contentRender":1,"createDesc":"今天","createTime":"16:58","gmtCreate":1546592307000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330054248470%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330054248470","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"拼好-小黑","contentRender":1,"createDesc":"今天","createTime":"16:49","gmtCreate":1546591743000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330053984151%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330053984151","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"黄家驹-小黑","contentRender":1,"createDesc":"今天","createTime":"11:59","gmtCreate":1546574380000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":430,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330053893684%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330053893684","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"回来-小黑","contentRender":1,"createDesc":"今天","createTime":"11:53","gmtCreate":1546574014000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330053951264%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330053951264","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"转账-小黑","contentRender":1,"createDesc":"今天","createTime":"11:34","gmtCreate":1546572892000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104305218623181%26bizType%3DMINITRANS%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":190,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104305218623181","bizSubType":"8041","bizType":"MINITRANS","canDelete":true,"categoryName":"理财","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"余额宝-2019.01.03-收益发放","contentRender":1,"createDesc":"今天","createTime":"07:45","gmtCreate":1546559113000,"isAggregatedRec":false,"oppositeLogo":"https://gw.alipayobjects.com/zos/mwalletmng/HMrWjUrCzaboAbeczVqY.png","recordType":"CONSUME","serializedSize":410,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100370052756343%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100370052756343","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"转账-安达市文勇文化传媒有限公司","contentRender":1,"createDesc":"今天","createTime":"01:24","gmtCreate":1546536259000,"isAggregatedRec":false,"oppositeLogo":"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png","recordType":"CONSUME","serializedSize":454,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330053247815%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330053247815","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"转账-小黑","contentRender":1,"createDesc":"今天","createTime":"01:22","gmtCreate":1546536125000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100370053116970%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100370053116970","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.10","consumeStatus":"2","consumeTitle":"转账-安达市文勇文化传媒有限公司","contentRender":1,"createDesc":"今天","createTime":"01:22","gmtCreate":1546536120000,"isAggregatedRec":false,"oppositeLogo":"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png","recordType":"CONSUME","serializedSize":454,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100370053118778%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100370053118778","bizSubType":"1135","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"收款-安达市文勇文化传媒有限公司","contentRender":1,"createDesc":"今天","createTime":"01:19","gmtCreate":1546535966000,"isAggregatedRec":false,"oppositeLogo":"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png","recordType":"CONSUME","serializedSize":454,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100460056548404%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100460056548404","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.10","consumeStatus":"2","consumeTitle":"转账-大庆市凯升谦经贸有限公司","contentRender":1,"createDesc":"今天","createTime":"00:43","gmtCreate":1546533835000,"isAggregatedRec":false,"oppositeLogo":"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png","recordType":"CONSUME","serializedSize":451,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100460056527363%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100460056527363","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.10","consumeStatus":"2","consumeTitle":"转账-大庆市凯升谦经贸有限公司","contentRender":1,"createDesc":"今天","createTime":"00:37","gmtCreate":1546533427000,"isAggregatedRec":false,"oppositeLogo":"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png","recordType":"CONSUME","serializedSize":451,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330053730032%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330053730032","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.01","consumeStatus":"2","consumeTitle":"转账-小黑","contentRender":1,"createDesc":"今天","createTime":"00:30","gmtCreate":1546533059000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190104200040011100330053362819%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190104200040011100330053362819","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.03","consumeStatus":"2","consumeTitle":"回来-小黑","contentRender":1,"createDesc":"今天","createTime":"00:00","gmtCreate":1546531247000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D2019010322001450330584762665%26bizType%3DTRADE%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":194,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"2019010322001450330584762665","bizSubType":"1041","bizType":"TRADE","canDelete":true,"categoryName":"小买卖","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"收钱码收款-来自*道荣","contentRender":1,"createDesc":"昨天","createTime":"23:49","gmtCreate":1546530585000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":428,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D2019010322001450330583801076%26bizType%3DTRADE%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":194,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"2019010322001450330583801076","bizSubType":"1041","bizType":"TRADE","canDelete":true,"categoryName":"小买卖","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"收钱码收款-来自*道荣","contentRender":1,"createDesc":"昨天","createTime":"23:46","gmtCreate":1546530388000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":428,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190103200040011100330053758122%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190103200040011100330053758122","bizSubType":"1106","bizType":"D_TRANSFER","canDelete":true,"categoryName":"其他","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"GG-小黑","contentRender":1,"createDesc":"昨天","createTime":"23:30","gmtCreate":1546529451000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":423,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20190103300924056181%26bizType%3DMINITRANS%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":190,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20190103300924056181","bizSubType":"8041","bizType":"MINITRANS","canDelete":true,"categoryName":"理财","consumeFee":"+0.02","consumeStatus":"2","consumeTitle":"余额宝-2019.01.02-收益发放","contentRender":1,"createDesc":"昨天","createTime":"06:35","gmtCreate":1546468529000,"isAggregatedRec":false,"oppositeLogo":"https://gw.alipayobjects.com/zos/mwalletmng/HMrWjUrCzaboAbeczVqY.png","recordType":"CONSUME","serializedSize":410,"tagStatus":0,"unknownFieldsSerializedSize":18},{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D2019010222001147180500247260%26bizType%3DTRADE%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":194,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"2019010222001147180500247260","bizSubType":"92","bizType":"TRADE","canDelete":true,"categoryName":"消费","consumeFee":"-49.97","consumeStatus":"2","consumeTitle":"手机充值","contentRender":0,"createDesc":"01-02","createTime":"20:12","gmtCreate":1546431172000,"isAggregatedRec":false,"oppositeLogo":"https://gw.alipayobjects.com/zos/mwalletmng/ulqLcciAckMtQxSvvjNT.png_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":439,"subCategoryName":"通讯物流","tagStatus":0,"unknownFieldsSerializedSize":29}],"code":0,"consumeVersion":"standard","hasMore":true,"nextQueryPageType":"All","paging":{"defQueryEndTime":"20190104222134","hasNextPage":true,"listQueryTime":1,"nextPageMonth":201901,"nextPageNum":2,"pageSize":20,"serializedSize":28,"unknownFieldsSerializedSize":0},"serializedSize":8913,"succ":true,"timeRangeTip":"1","unknownFieldsSerializedSize":0},false]
EOF;

                $re = ['cls'=>'com.alipay.android.phone.messageboxstatic.biz.sync.d','data'=> $str] ;
                $re = ['cls'=>'com.alipay.mobile.bill.list.ui.BillListActivity_','data'=> $str,'userId'=>'2088232932547186'] ;

                $str= <<<EOF
SyncMessage [userId=2088432038710352, biz=MSG-BOX, msgData=[{"mk":190157113505200004,"st":1,"isSc":0,"mct":1547696105867,"pl":"{\"templateType\":\"S\",\"commandType\":\"SEND\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/zos.alipayobjects.com\/rmsportal\/SdJCSvgNRubKgXw.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=09999988&tagid=TRANSFERPROD_TO_CARD_SUCCESS_PUSH\",\"msgId\":\"4a9deb15805cf29a9db46e10502e2f820035\",\"templateCode\":\"TRANSFERPROD_TO_CARD_SUCCESS_PUSH\",\"title\":\"银行卡收款通知\",\"gmtCreate\":1547696105855,\"content\":\"夏晓淇通过支付宝向你的银行卡（尾号3301）转账0.02元已到账，\\b如有疑问请咨询银行确认。\",\"linkName\":\"我也要转账\",\"gmtValid\":1548300905847,\"operate\":\"SEND\",\"bizName\":\"支付宝\",\"templateName\":\"收款提醒\",\"homePageTitle\":\"银行卡收款通知\",\"status\":\"\",\"extraInfo\":\"{\\\"expireLink\\\":\\\"https:\/\/render.alipay.com\/p\/f\/fd-jblxfp45\/pages\/home\/index.html\\\",\\\"templateId\\\":\\\"WALLET-FWC@remindMultiLine\\\",\\\"content\\\":\\\"银行卡收款通知\\\",\\\"linkName\\\":\\\"我也要转账\\\",\\\"assistMsg2\\\":\\\"\\\",\\\"gmtValid\\\":1548300905847,\\\"assistMsg1\\\":\\\"夏晓淇通过支付宝向你的银行卡（尾号3301）转账0.02元已到账，\\\\b如有疑问请咨询银行确认。\\\",\\\"sceneExt\\\":{\\\"sceneTemplateId\\\":\\\"birdNest:\/\/WALLET-FWC@lifeTemplateMultiLine\\\",\\\"sceneUrl\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000042&publicBizType=LIFE_APP&sourceId=notifications&publicId=2013121700002381\\\",\\\"sceneType\\\":\\\"lifePublic\\\",\\\"forwardLifePublicMsg\\\":\\\"Y\\\",\\\"sceneName\\\":\\\"支付宝服务中心\\\",\\\"sceneId\\\":\\\"2013121700002381\\\",\\\"sceneIcon\\\":\\\"https:\/\/mdn.alipay.com\/wsdk\/img?fileid=A*Kh7cTaAVtZMAAAAAAAAAAABjAfYuAQ&bz=life_app&zoom=2048w_80q_1l\\\",\\\"sceneExtInfo\\\":\\\"{\\\\\\\"vipMsgNoteType\\\\\\\":\\\\\\\"dot\\\\\\\",\\\\\\\"isProhibited\\\\\\\":false,\\\\\\\"vip\\\\\\\":\\\\\\\"0\\\\\\\"}\\\",\\\"sceneTitle\\\":\\\"进入生活号\\\"},\\\"imageUrl\\\":\\\"\\\"}\"}"}], pushData=, id=36,ID:190157113505200004,1, hasMore=false], sOpcode=0]
EOF;

                $re=['cls'=>'com.alipay.mobile.rome.longlinkservice.syncmodel.SyncMessage' ,'data'=> $str ];

                $str= <<<EOF
{"asyncRec":false,"crowdDuration":1440,"extInfo":{},"giftCrowdFlowInfo":{"best":false,"crowdNo":"201902150206302200000000330036809593","id":0,"memo":"红包金额将打入你的支付宝账户","ownFlag":true,"receiveAmount":"0.01","receiveCount":0,"receiveDateDesc":"今天 16:00:20","receiver":{"alipayAccount":"135***5443","imgUrl":"http://tfs.alipayobjects.com/images/partner/TB1kDK_X7VDDuNkUuGVXXX_sXXa_160X160","realFriend":true,"userId":"2088232932547186","userName":"大桥"},"returnCount":0,"scratchCount":0,"state":"RECEIVE_SUC","stateDesc":"","win":false},"giftCrowdInfo":{"amount":"0.01","canResend":false,"count":0,"creator":{"alipayAccount":"doo***@qq.com","imgUrl":"http://tfs.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_160X160","realFriend":true,"userId":"2088002122250336","userName":"小黑"},"crowdDuration":24,"crowdNo":"201902150206302200000000330036809593","gcashUseAvg":true,"gmtCreateDesc":"今天 16:00","id":0,"prodCode":"CROWD_COMMON_CASH","prodName":"普通红包","remark":"恭喜发财，万事如意！","totalNumber":0,"withStars":false},"guessResult":false,"hasNextPage":false,"messageCardInfo":{"bizMemo":"你领取了<o name=\"小黑\" id=\"2088002122250336\" />的红包","bizType":"GIFTSHARE","clientMsgId":"3adb24bfe12aa94a2572a33e19ac4dce","fromUser":"2088232932547186","receiverUserType":"1","templateCode":"8003","templateData":"{\"userIdSet\":[\"2088002122250336\"],\"icon\":\"gift\",\"m\":\"你领取了<o name=\\\"小黑\\\" id=\\\"2088002122250336\\\" />的<a href=\\\"alipays://platformapi/startapp?appId=88886666&appClearTop=false&target=groupPre&bizType=CROWD_COMMON_CASH&crowdNo=201902150206302200000000330036809593&universalDetail=true&clientVersion=10.0.0-5&schemeMode=portalInside&prevBiz=chat\\\"> 红包 </a>\"}","toLogonId":"","toUser":"2088002122250336"},"mockMessage":"[\"{\\\"toUId\\\":\\\"2088232932547186\\\",\\\"toType\\\":\\\"1\\\",\\\"bizType\\\":\\\"GIFTSHARE\\\",\\\"fromUId\\\":\\\"2088002122250336\\\",\\\"createTime\\\":1550217620956,\\\"clientMsgId\\\":\\\"66ffdc4bcae28f005b250cee49216808\\\",\\\"bizMemo\\\":\\\"恭喜发财，万事如意！\\\",\\\"templateData\\\":\\\"{\\\\\\\"adapterCode\\\\\\\":\\\\\\\"1001\\\\\\\",\\\\\\\"adapterData\\\\\\\":\\\\\\\"{\\\\\\\\\\\\\\\"desc\\\\\\\\\\\\\\\":\\\\\\\\\\\\\\\"恭喜发财，万事如意！\\\\\\\\\\\\\\\",\\\\\\\\\\\\\\\"thumb\\\\\\\\\\\\\\\":\\\\\\\\\\\\\\\"\\\\\\\\\\\\\\\",\\\\\\\\\\\\\\\"title\\\\\\\\\\\\\\\":\\\\\\\\\\\\\\\"领取普通红包\\\\\\\\\\\\\\\"}\\\\\\\",\\\\\\\"appName\\\\\\\":\\\\\\\"红包\\\\\\\",\\\\\\\"bgColor\\\\\\\":\\\\\\\"#F2A4A6\\\\\\\",\\\\\\\"bizImage\\\\\\\":\\\\\\\"https://oalipay-dl-django.alicdn.com/rest/1.0/image?fileIds=w7RFEZFpR7CVJt05ldEywQAAACMAAQED&zoom=original\\\\\\\",\\\\\\\"m\\\\\\\":\\\\\\\"恭喜发财，万事如意！\\\\\\\",\\\\\\\"title\\\\\\\":\\\\\\\"红包已领取\\\\\\\"}\\\",\\\"link\\\":\\\"alipays://platformapi/startapp?appId=88886666&appClearTop=false&target=groupPre&bizType=CROWD_COMMON_CASH&crowdNo=201902150206302200000000330036809593&universalDetail=true&clientVersion=10.0.0-5&schemeMode=portalInside&prevBiz=chat&sign=ce9a619c3b3676d31b9330ac33cf860b35755cc63f2080aeff3174d2a38d7c74\\\",\\\"templateCode\\\":\\\"107\\\",\\\"bizRemind\\\":\\\"[红包]\\\"}\"]","needCertify":false,"needRealName":false,"needWriteMessage":true,"received":false,"resultCode":"1000","resultDesc":"处理成功","success":true}
EOF;


                $re=['cls'=>'myapp.v13.ReceiveCrowdTask' ,'data'=> $str  ];

                $re=['cls'=>'myapp.v13.createBill' ,'data'=> '{"success":true,"transferNo":"20190225200040011100330094134733"}'
                    ,'transferNo'=>'20190227200040011100330095687714','remark'=>'T0227093937655' ];
                $re[money] = "0.01";
                $re[tUid] ='2088002122250336';
                $re[dt] =  '1551064771621';
                $re[userId] =  '2088232932547186';

/*
                $str= <<<EOF
SyncMessage [userId=2088232932547186, biz=MSG-BILL, msgData=[{"mk":190265223438200002,"st":1,"isSc":0,"mct":1551105278942,"pl":"{\"templateType\":\"BN\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/rmsportal\/EMWIWDsKUkuXYdvKDdaZ.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190225200040011100330094718352&bizType=D_TRANSFER?tagid=MB_SEND_PH\",\"businessId\":\"PAY_HELPER_CARD_2088232932547186\",\"msgId\":\"435fed456d5e9295cdcc721b16134a430018\",\"templateCode\":\"00059_00094_zfzs001\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"title\":\"收款到账成功\",\"gmtCreate\":1551105278937,\"content\":\"{\\\"status\\\":\\\"收款到账成功\\\",\\\"date\\\":\\\"02月25日\\\",\\\"amountTip\\\":\\\"\\\",\\\"money\\\":\\\"0.06\\\",\\\"unit\\\":\\\"元\\\",\\\"infoTip\\\":\\\"\\\",\\\"failTip\\\":\\\"\\\",\\\"goto\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190225200040011100330094718352&bizType=D_TRANSFER\\\",\\\"content\\\":[{\\\"title\\\":\\\"付款方：\\\",\\\"content\\\":\\\"小黑 doo***@qq.com\\\"},{\\\"title\\\":\\\"收款理由：\\\",\\\"content\\\":\\\"20182084909\\\"}],\\\"ad\\\":[],\\\"actions\\\":[{\\\"name\\\":\\\"\\\",\\\"url\\\":\\\"\\\"},{\\\"name\\\":\\\"查看详情\\\",\\\"url\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190225200040011100330094718352&bizType=D_TRANSFER\\\"}]}\",\"linkName\":\"\",\"gmtValid\":1553697278932,\"operate\":\"SEND\",\"bizName\":\"支付助手\",\"templateName\":\"支付助手\",\"homePageTitle\":\"支付助手: ￥0.06 收款到账成功\",\"status\":\"\",\"extraInfo\":\"{\\\"content\\\":\\\"￥0.06\\\",\\\"assistMsg1\\\":\\\"收款到账成功\\\",\\\"assistMsg2\\\":\\\"20182084909\\\",\\\"linkName\\\":\\\"\\\",\\\"buttonLink\\\":\\\"\\\",\\\"templateId\\\":\\\"WALLET-FWC@remindDefaultText\\\"}\"}"}], pushData=, id=763,ID:190265223438200002,1, hasMore=false], sOpcode=0]
EOF;

                $re=['cls'=>'com.alipay.android.phone.messageboxstatic.biz.sync.d' ,'data'=> $str ];
*/

//
//                $re=['cls'=>'com.alipay.$bank2Typemobile.socialchatsdk.chat.data.ChatDataSyncCallback','data'=>'SyncMessage [userId=2088232932547186, biz=UCHAT, msgData=[{"mk":0,"st":1,"isSc":0,"mct":1546257587005,"pl":"ChAyMDg4MDAyMTIyMjUwMzM2Eg1kb28qKipAcXEuY29tGhAyMDg4MjMyOTMyNTQ3MTg2KgExMO7R0oSAmvfBAjorVFJBTlNGRVIyMDE4MTIzMTIwMDA0MDAxMTEwMDMzMDA1MDgwMjk5Ml9QMUIDMTA5SjN7ImFwcE5hbWUiOiLovazotKYiLCJtIjoiMC4wM+WFgyIsInRpdGxlIjoi6L2n56m6In1aE+WQkeS9oOi9rOi0pjAuMDPlhYNiCFRSQU5TRkVSaghUUkFOU0ZFUnJvYWxpcGF5czovL3BsYXRmb3JtYXBpL3N0YXJ0YXBwP2FwcElkPTIwMDAwMDkwJmFjdGlvblR5cGU9dG9CaWxsRGV0YWlscyZ0cmFkZU5PPTIwMTgxMjMxMjAwMDQwMDExMTAwMzMwMDUwODAyOTkyeKm2iaGALZABA5oBCFvovazotKZdugEzOThlZjNiZmJjODhiMmY2YTllNTRmZWZjMmNiNjA2ODNfMTgxMjMxMTk1OTQ2MDAyNjcw","isB":"1"}], pushData=[{"badge":1,"bizType":"0","content":"小黑已成功向你转了1笔钱","displayOffset":-1,"displayTimeout":-1,"idenOfUser":"2088232932547186","k":"_01e3df13da2e8a92","noticeExt":"{\"flag\":\"0\",\"bizId\":\"social\"}","notificationId":5700067931939248021,"showOffset":-1,"showTimeout":-1,"snd":"file=diaoluo_da.mp3","style":"2","title":"支付宝消息","uri":"{\"type\":\"startApp\", \"params\":{\"appId\":\"20000167\",\"tUserId\":\"2088002122250336\",\"tUserType\":\"1\",\"tLoginId\":\"doo***@qq.com\"}}"}], id=69,d34cfb07424acd33,1, hasMore=false], sOpcode=0]' ];
//                $re=['cls'=>'com.alipay.mobile.payee.ui.PayeeQRActivity','data'=>'SyncMessage [userId=2088232932547186, biz=COLLECT-R, msgData=[{"mk":190141160508200001,"st":1,"isSc":0,"mct":1546329908911,"pl":"{\"payerLoginId\":\"dooy520@qq.com\",\"payerHeadUrl\":\"http:\/\/tfs.alipayobjects.com\/images\/partner\/T13vtwXcxaXXXXXXXX_160X160\",\"payerUserName\":\"小黑\",\"sessionId\":\"COLLECT_MONEY_RECEIVER_2088232932547186\",\"state\":\"0\",\"payerSessionId\":\"COLLECT_MONEY_PAY_2088002122250336_1546329908899\",\"payerUserId\":\"2088002122250336\",\"userId\":\"2088232932547186\"}"}], pushData=null, id=190141160508200001, hasMore=false], sOpcode=0]' ];

                $re=[];
                $re=['cls'=>'v3.dingding.CreateCny',
                    'orderId'=>'order123',
                    'dingdingOrderId'=>'3ho2lvA2_623105921100',
                    'redId'=>'3ho2lvA2',
                    'money'=>'0.01',
                    'orderStr'=>'service="alipay.fund.stdtrustee.order.create.pay"&partner="2088801132166875"&_input_charset="utf-8"&notify_url="https://repay.dingtalk.com/RENotify/alipay_fund_stdtrustee_order_create_pay"&out_order_no="3ho2lvA2_623105921100"&out_request_no="3ho2lvA2_623105921100_s"&product_code="SOCIAL_RED_PACKETS"&scene_code="MERCHANT_COUPON"&amount="0.01"&pay_strategy="CASHIER_PAYMENT"&receipt_strategy="INNER_ACCOUNT_RECEIPTS"&platform="DEFAULT"&channel="APP"&order_title="发送钉钉红包"&master_order_no="2019031310002001380273835546"&order_type="DEDUCT_ORDER"&extra_param="{"payeeShowName":"钉钉红包"}"&pay_timeout="30m"&order_expired_time="60d"&out_context="{"dingtalk_biz_tag":"red_envelop"}"&sign="ZslYOck36JNLEjY%2BG0tu8UwHrSNopDMZ06%2B5wI9ydQDWykVh%2BN%2FajLcTommV9cAdVRxQeAcZO3Eerve67BeD5VLZT2mdPwH7D3DyRQKw82NRSk5cYST%2FXwew2eNOl66oMbYl1YDP4EcEiuHcOGnA9mCpO%2B1z2ShTi0Ms1bAC7SJ%2FPF57rhnPLahz%2Bj4CewrL3E2%2B%2BGwzdPrxKt%2BTb1bXeWKVZtOnUn4pMuTZfcOx9h5sdVvv8fY4LDB2I7KiHWynH4Q0PAviu%2FEp8puD4G6qL9ipRL%2F37Hc%2BipDU77Gr03%2FXjFXRsiXFdEMPT8Lt%2BrRYEL9IGKl81qFg1pa0TMbWmw%3D%3D"&sign_type="RSA"',
                    'remark'=>'G2019',
                    'data'=>'{"alipayOrderString":"service=\"alipay.fund.stdtrustee.order.create.pay\"&partner=\"2088801132166875\"&_input_charset=\"utf-8\"&notify_url=\"https://repay.dingtalk.com/RENotify/alipay_fund_stdtrustee_order_create_pay\"&out_order_no=\"3ho2lvA2_623105921100\"&out_request_no=\"3ho2lvA2_623105921100_s\"&product_code=\"SOCIAL_RED_PACKETS\"&scene_code=\"MERCHANT_COUPON\"&amount=\"0.01\"&pay_strategy=\"CASHIER_PAYMENT\"&receipt_strategy=\"INNER_ACCOUNT_RECEIPTS\"&platform=\"DEFAULT\"&channel=\"APP\"&order_title=\"发送钉钉红包\"&master_order_no=\"2019031310002001380273835546\"&order_type=\"DEDUCT_ORDER\"&extra_param=\"{\"payeeShowName\":\"钉钉红包\"}\"&pay_timeout=\"30m\"&order_expired_time=\"60d\"&out_context=\"{\"dingtalk_biz_tag\":\"red_envelop\"}\"&sign=\"ZslYOck36JNLEjY%2BG0tu8UwHrSNopDMZ06%2B5wI9ydQDWykVh%2BN%2FajLcTommV9cAdVRxQeAcZO3Eerve67BeD5VLZT2mdPwH7D3DyRQKw82NRSk5cYST%2FXwew2eNOl66oMbYl1YDP4EcEiuHcOGnA9mCpO%2B1z2ShTi0Ms1bAC7SJ%2FPF57rhnPLahz%2Bj4CewrL3E2%2B%2BGwzdPrxKt%2BTb1bXeWKVZtOnUn4pMuTZfcOx9h5sdVvv8fY4LDB2I7KiHWynH4Q0PAviu%2FEp8puD4G6qL9ipRL%2F37Hc%2BipDU77Gr03%2FXjFXRsiXFdEMPT8Lt%2BrRYEL9IGKl81qFg1pa0TMbWmw%3D%3D\"&sign_type=\"RSA\"","alipayStatus":0,"amount":"0.01","businessId":"3ho2lvA2_623105921100","cid":"4208934203","clusterId":"3ho2lvA2","congratulations":"G2019","count":0,"createTime":1552469691000,"ext":{},"modifyTime":1552469691000,"oid":0,"pickDoneTime":0,"pickPlanTime":0,"pickTime":0,"sender":623105921,"senderPayType":0,"size":1,"status":1,"type":0}',
                    'dingID'=>'623105921'];
                $re=['cls'=>'v3.dingding.OpenCny',
                    'money'=>'0.01',
                    'remark'=>'T0313082753312',
                    'tradeNo'=>'1hWT8toaO_623105921100',
                    'redId'=>'1hWT8toaO',
                    'data'=>'{"alipayStatus":0,"amount":"0.01","businessId":"1hWT8toaO_623105921100","cid":"4208934203","clusterId":"1hWT8toaO","congratulations":"T0313082753312","count":0,"createTime":1552480076000,"ext":{},"modifyTime":1552480076000,"oid":0,"pickDoneTime":0,"pickPlanTime":0,"pickTime":1552480400000,"sender":623105921,"senderPayType":0,"size":1,"status":2,"type":0}',
                    'dingID'=>'623105921'];


                $re=['cls'=>'com.alipay.android.phone.messageboxstatic.biz.dao.TradeDao','userId'=>'2088232932547186'];
                $re['data']= <<<EOF
 ServiceReminderRecord{msgId='afb49ed2b0230624877eae47a860581800718', operate='UPDATE', templateType='BN', templateId='WALLET-BILL@BLPaymentHelper', msgType='NOTICE', title='支付助手', content='{"content":"￥0.01","assistMsg1":"二维码收款到账通知","assistMsg2":"GB011","linkName":"","buttonLink":"","templateId":"WALLET-FWC@remindDefaultText"}', icon='https://gw.alipayobjects.com/zos/rmsportal/EMWIWDsKUkuXYdvKDdaZ.png', link='alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190327200040011100330018597125&bizType=D_TRANSFER?tagid=MB_SEND_PH', linkName='', templateCode='00059_00094_zfzs001', gmtCreate=1553662968943, gmtValid=1556254968938, homePageTitle='支付助手: ￥0.01 二维码收款到账通知', statusFlag='null', status='', businessId='PAY_HELPER_CARD_2088232932547186', expireLink='', templateName='支付助手', menus='null', extraInfo='{"actions":[{"name":"","url":""},{"name":"查看详情","url":"alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190327200040011100330018597125&bizType=D_TRANSFER"}],"ad":[],"amountTip":"","bizMonitor":"{\"businessId\":\"PAY_HELPER_CARD_2088232932547186\",\"expireLink\":\"\",\"gmtCreate\":1553662968943,\"gmtValid\":1556254968938,\"hiddenSum\":\"0\",\"homePageTitle\":\"支付助手: ￥0.01 二维码
收款到账通知\",\"icon\":\"https://gw.alipayobjects.com/zos/rmsportal/EMWIWDsKUkuXYdvKDdaZ.png\",\"id\":\"afb49ed2b0230624877eae47a86058180071800059_00094_zfzs0012088232932547186\",\"link\":\"alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190327200040011100330018597125&bizType=D_TRANSFER?tagid=MB_SEND_PH\",\"linkName\":\"\",\"msgId\":\"afb49ed2b0230624877eae47a860581800718\",\"msgType\":\"NOTICE\",\"operate\":\"UPDATE\",\"status\":\"\",\"templateCode\":\"00059_00094_zfzs001\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"templateName\":\"支付助手\",\"templateType\":\"BN\",\"title\":\"支付助手\",\"userId\":\"2088232932547186\"}","content":[{"content":"累计收款金额0.01元，累计收款1笔","title":"今日汇总："},{"content":"小黑 doo***@qq.com","title":"付款人："},{"content":"GB011","title":"收款理由："},{"content":"2019-03-27 13:02","title":"到账时间："}],"date":"03月27日","failTip":"","goto":"alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20190327200040011100330018597125&bizType=D_TRANSFER","infoTip":"","money":"0.01","status":"二维码收款到账通知","unit":"元"}', msgState='null', userId='2088232932547186'}
EOF;

                $re=['cls'=>'com.alipay.mobile.payee.ui.PayeeQRActivity','userId'=>'2088232932547186'];
                $re['data']= <<<EOF
SyncMessage [userId=2088432785646889, biz=COLLECT-R, msgData=[{"mk":190367210923200005,"st":1,"isSc":0,"mct":1553692163839,"pl":"{\"amount\":\"600.00\",\"payerLoginId\":\"13402020360\",\"payerHeadUrl\":\"http:\/\/tfs.alipayobjects.com\/images\/partner\/TB1VqQEae5GDuNkUQcKXXbDdVXa_160X160\",\"transferNo\":\"20190327200040011100980027083186\",\"payerUserName\":\"7ã<80><82>\",\"sessionId\":\"COLLECT_MONEY_RECEIVER_2088432785646889\",\"state\":\"2\",\"payerSessionId\":\"COLLECT_MONEY_PAY_2088622425810985_1553692157221\",\"payerUserId\":\"2088622425810985\",\"userId\":\"2088432785646889\"}"}], pushData=null, id=190367210923200005, hasMore=false], sOpcode=0]
EOF;

                $re=['cls'=>'org.myapp.wx.bill.receive','wxID'=>'made2099god' ];
                $re['content']= <<<EOF
<msg> <appmsg appid="" sdkver="0"> 	<title><![CDATA[微信支付收款0.01元(朋友到店)]]></title> 	<des><![CDATA[收款金额￥0.01收款方备注good汇总今日第1笔收款，共计￥0.01备注收款成功，已存入零钱。点击可查看详情]]></des> 	<action></action> 	<type>5</type> 	<showtype>1</showtype>     <soundtype>0</soundtype> 	<content><![CDATA[]]></content> 	<contentattr>0</contentattr> 	<url><![CDATA[https://payapp.weixin.qq.com/payf2f/jumpf2fbill?timestamp=1553737605&openid=fGgj35ZNk3Jkg_PGoJLWewfeBKYhV7K1LPayDOTpI5Y=]]></url> 	<lowurl><![CDATA[]]></lowurl> 	<appattach> 		<totallen>0</totallen> 		<attachid></attachid> 		<fileext></fileext> 		<cdnthumburl><![CDATA[]]></cdnthumburl> 		<cdnthumbaeskey><![CDATA[]]></cdnthumbaeskey> 		<aeskey><![CDATA[]]></aeskey> 	</appattach> 	<extinfo></extinfo> 	<sourceusername><![CDATA[]]></sourceusername> 	<sourcedisplayname><![CDATA[]]></sourcedisplayname> 	<mmreader> 		<category type="0" count="1"> 			<name><![CDATA[微信支付]]></name> 			<topnew> 				<cover><![CDATA[]]></cover> 				<width>0</width> 				<height>0</height> 				<digest><![CDATA[收款金额￥0.01收款方备注good汇总今日第1笔收款，共计￥0.01备注收款成功，已存入零钱。点击可查看详情]]></digest> 			</topnew> 				<item> 	<itemshowtype>4</itemshowtype> 	<title><![CDATA[收款到账通知]]></title> 	<url><![CDATA[https://payapp.weixin.qq.com/payf2f/jumpf2fbill?timestamp=1553737605&openid=fGgj35ZNk3Jkg_PGoJLWewfeBKYhV7K1LPayDOTpI5Y=]]></url> 	<shorturl><![CDATA[]]></shorturl> 	<longurl><![CDATA[]]></longurl> 	<pub_time>1553737605</pub_time> 	<cover><![CDATA[]]></cover> 	<tweetid></tweetid> 	<digest><![CDATA[收款金额￥0.01收款方备注good汇总今日第1笔收款，共计￥0.01备注收款成功，已存入零钱。点击可查看详情]]></digest> 	<fileid>0</fileid> 	<sources> 	<source> 	<name><![CDATA[微信支付]]></name> 	</source> 	</sources> 	<styles><topColor><![CDATA[]]></topColor><style><range><![CDATA[{4,5}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{15,4}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{22,15}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{40,18}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style></styles>	<native_url></native_url>    <del_flag>0</del_flag>     <contentattr>0</contentattr>     <play_length>0</play_length> 	<play_url><![CDATA[]]></play_url> 	<player><![CDATA[]]></player> 	<template_op_type>1</template_op_type> 	<weapp_username><![CDATA[gh_fac0ad4c321d@app]]></weapp_username> 	<weapp_path><![CDATA[pages/index/index.html]]></weapp_path> 	<weapp_version>157</weapp_version> 	<weapp_state>0</weapp_state>     <music_source>0</music_source>     <pic_num>0</pic_num> 	<show_complaint_button>0</show_complaint_button> 	<vid><![CDATA[]]></vid> 	<recommendation><![CDATA[]]></recommendation> 	<pic_urls></pic_urls>	<comment_topic_id>0</comment_topic_id>	<cover_235_1><![CDATA[]]></cover_235_1> 	<cover_1_1><![CDATA[]]></cover_1_1>     <appmsg_like_type>0</appmsg_like_type> 	</item> 		</category> 		<publisher> 			<username><![CDATA[wxzhifu]]></username> 			<nickname><![CDATA[微信支付]]></nickname> 		</publisher> 		<template_header><title><![CDATA[收款到账通知]]></title><title_color><![CDATA[]]></title_color><pub_time>1553737605</pub_time><first_data><![CDATA[]]></first_data><first_color><![CDATA[]]></first_color><hide_title_and_time>0</hide_title_and_time><show_icon_and_display_name>0</show_icon_and_display_name><display_name><![CDATA[]]></display_name><icon_url><![CDATA[]]></icon_url><hide_icon_and_display_name_line>1</hide_icon_and_display_name_line><header_jump_url><![CDATA[]]></header_jump_url><shortcut_icon_url><![CDATA[]]></shortcut_icon_url><ignore_hide_title_and_time>1</ignore_hide_title_and_time><hide_title>0</hide_title><hide_time>1</hide_time><pay_style>1</pay_style></template_header> 		<template_detail><template_show_type>1</template_show_type><text_content><cover><![CDATA[]]></cover><text><![CDATA[]]></text><color><![CDATA[]]></color></text_content><line_content><topline><key><word><![CDATA[收款金额]]></word><color><![CDATA[#888888]]></color><hide_dash_line>1</hide_dash_line></key><value><word><![CDATA[￥0.01]]></word><color><![CDATA[#000000]]></color><small_text_count>1</small_text_count></value></topline><lines><line><key><word><![CDATA[收款方备注]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[good]]></word><color><![CDATA[#000000]]></color></value></line><line><key><word><![CDATA[汇总]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[今日第1笔收款，共计￥0.01]]></word><color><![CDATA[#000000]]></color></value></line><line><key><word><![CDATA[备注]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[收款成功，已存入零钱。点击可查看详情]]></word><color><![CDATA[#000000]]></color></value></line></lines></line_content><opitems><opitem><word><![CDATA[收款小账本]]></word><url><![CDATA[]]></url><icon><![CDATA[]]></icon><color><![CDATA[#000000]]></color><weapp_username><![CDATA[gh_fac0ad4c321d@app]]></weapp_username><weapp_path><![CDATA[pages/index/index.html]]></weapp_path><op_type>1</op_type><weapp_version>157</weapp_version><weapp_state>0</weapp_state><hint_word><![CDATA[]]></hint_word><is_rich_text>0</is_rich_text><display_line_number>0</display_line_number></opitem><show_type>1</show_type></opitems></template_detail> 	    <forbid_forward>0</forbid_forward> 	</mmreader> 	<thumburl><![CDATA[]]></thumburl> 	     <template_id><![CDATA[ey45ZWkUmYUBk_fMgxBLvyaFqVop1rmoWLFd62OXGiU]]></template_id>                          	 </appmsg><fromusername><![CDATA[gh_3dfda90e39d6]]></fromusername><appinfo><version>0</version><appname><![CDATA[微信支付]]></appname><isforceupdate>1</isforceupdate></appinfo></msg>    
EOF;
                $re['content']= <<<EOF
<msg> <appmsg appid="" sdkver="0">     <title><![CDATA[[店员消息]收款到账0.01元]]></title>     <des><![CDATA[收款金额￥0.01汇总今日第2笔收款, 共计￥0.02说明已存入店长宏基(**鑫)的零钱]]></des>         <action></action>       <type>5</type>  <showtype>1</showtype>     <soundtype>0</soundtype>     <content><![CDATA[]]></content>         <contentattr>0</contentattr>    <url><![CDATA[]]></url>         <lowurl><![CDATA[]]></lowurl>   <appattach>             <totallen>0</totallen>          <attachid></attachid>           <fileext></fileext>             <cdnthumburl><![CDATA[]]></cdnthumburl>                 <cdnthumbaeskey><![CDATA[]]></cdnthumbaeskey>           <aeskey><![CDATA[]]></aeskey>   </appattach>    <extinfo></extinfo>     <sourceusername><![CDATA[]]></sourceusername>   <sourcedisplayname><![CDATA[]]></sourcedisplayname>     <mmreader>              <category type="0" count="1">                   <name><![CDATA[微信收款助手]]></name>                   <topnew>                                <cover><![CDATA[]]></cover>                             <width>0</width>                                <height>0</height>                              <digest><![CDATA[收款金额￥0.01汇总今日第2笔收款, 共计￥0.02说明已存入店长宏基(**鑫)的零钱]]></digest>                      </topnew>                               <item>  <itemshowtype>4</itemshowtype>  <title><![CDATA[[店员消息]收款到账0.01元]]></title>     <url><![CDATA[]]></url>         <shorturl><![CDATA[]]></shorturl>       <longurl><![CDATA[]]></longurl>         <pub_time>1555054161</pub_time>         <cover><![CDATA[]]></cover>     <tweetid></tweetid>     <digest><![CDATA[收款金额￥0.01汇总今日第2笔收款, 共计￥0.02说明已存入店长宏基(**鑫)的零钱]]></digest>      <fileid>0</fileid>      <sources>       <source>        <name><![CDATA[微信收款助手]]></name>   </source>       </sources>      <styles><topColor><![CDATA[]]></topColor><style><range><![CDATA[{4,5}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{12,16}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{31,15}]]></range><font><![CDATA[s]]></font><color><![CDATA[#FA962A]]></color></style></styles>       <native_url></native_url>    <del_flag>0</del_flag>     <contentattr>0</contentattr>     <play_length>0</play_length>   <play_url><![CDATA[]]></play_url>       <player><![CDATA[]]></player>   <template_op_type>1</template_op_type>  <weapp_username><![CDATA[gh_27a64db998c4@app]]></weapp_username>        <weapp_path><![CDATA[pages/home/index.html?payee_openid=owQcD0fWA_kuaazkfX4FetpRXvnY&timestamp=1555054161]]></weapp_path>       <weapp_version>33</weapp_version>       <weapp_state>0</weapp_state>     <music_source>0</music_source>     <pic_num>0</pic_num>        <show_complaint_button>0</show_complaint_button>        <vid><![CDATA[]]></vid>         <recommendation><![CDATA[]]></recommendation>   <pic_urls></pic_urls>   <comment_topic_id>0</comment_topic_id>  <cover_235_1><![CDATA[]]></cover_235_1>         <cover_1_1><![CDATA[]]></cover_1_1>     <appmsg_like_type>0</appmsg_like_type>     <video_width>0</video_width>     <video_height>0</video_height>      </item>                 </category>             <publisher>                     <username><![CDATA[gh_f0a92aa7146c]]></username>                        <nickname><![CDATA[微信收款助手]]></nickname>           </publisher>            <template_header><title><![CDATA[收款到账通知]]></title><title_color><![CDATA[]]></title_color><pub_time>1555054161</pub_time><first_data><![CDATA[04月12日 15:29]]></first_data><first_color><![CDATA[#888888]]></first_color><hide_title_and_time>1</hide_title_and_time><show_icon_and_display_name>0</show_icon_and_display_name><display_name><![CDATA[]]></display_name><icon_url><![CDATA[]]></icon_url><hide_icon_and_display_name_line>1</hide_icon_and_display_name_line><header_jump_url><![CDATA[]]></header_jump_url><shortcut_icon_url><![CDATA[]]></shortcut_icon_url><ignore_hide_title_and_time>1</ignore_hide_title_and_time><hide_time>1</hide_time><pay_style>1</pay_style></template_header>              <template_detail><template_show_type>1</template_show_type><text_content><cover><![CDATA[]]></cover><text><![CDATA[]]></text><color><![CDATA[]]></color></text_content><line_content><topline><key><word><![CDATA[收款金额]]></word><color><![CDATA[#888888]]></color><hide_dash_line>1</hide_dash_line></key><value><word><![CDATA[￥0.01]]></word><color><![CDATA[#000000]]></color><small_text_count>1</small_text_count></value></topline><lines><line><key><word><![CDATA[汇总]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[今日第2笔收款, 共计￥0.02]]></word><color><![CDATA[#000000]]></color></value></line><line><key><word><![CDATA[说明]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[已存入店长宏基(**鑫)的零钱]]></word><color><![CDATA[#FA962A]]></color></value></line></lines></line_content><opitems><opitem><word><![CDATA[查看收款记录]]></word><url><![CDATA[]]></url><icon><![CDATA[]]></icon><color><![CDATA[#000000]]></color><weapp_username><![CDATA[gh_27a64db998c4@app]]></weapp_username><weapp_path><![CDATA[pages/home/index.html?payee_openid=owQcD0fWA_kuaazkfX4FetpRXvnY&timestamp=1555054161&report=record_1]]></weapp_path><op_type>1</op_type><weapp_version>33</weapp_version><weapp_state>0</weapp_state><hint_word><![CDATA[]]></hint_word><is_rich_text>0</is_rich_text><display_line_number>0</display_line_number></opitem><opitem><word><![CDATA[设置收款通知]]></word><url><![CDATA[]]></url><icon><![CDATA[]]></icon><color><![CDATA[#000000]]></color><weapp_username><![CDATA[gh_27a64db998c4@app]]></weapp_username><weapp_path><![CDATA[pages/home/options.html?payee_openid=owQcD0fWA_kuaazkfX4FetpRXvnY&timestamp=1555054161&report=setting_1]]></weapp_path><op_type>1</op_type><weapp_version>33</weapp_version><weapp_state>0</weapp_state><hint_word><![CDATA[]]></hint_word><is_rich_text>0</is_rich_text><display_line_number>0</display_line_number></opitem><show_type>1</show_type></opitems></template_detail>          <forbid_forward>0</forbid_forward>  </mmreader>     <thumburl><![CDATA[]]></thumburl>            <template_id><![CDATA[hhBEwxiQAAY8HBHor5osXOl4zvANfBpjRKy_EkV__pg]]></template_id>                                  </appmsg><fromusername><![CDATA[gh_f0a92aa7146c]]></fromusername><appinfo><version>0</version><appname><![CDATA[微信收款助手]]></appname><isforceupdate>1</isforceupdate></appinfo></msg>
EOF;


                //$re =['cls'=>'org.myapp.wx.qrcode','money'=>'99.45','desc'=>'G2017','qrcode'=>'wxp://f2f1fA9EWLFzJmssZlnZnz9Mmy_2NuH5FQU_','wxID'=>'made2099god'];

            /*
                $re=[];
                $re['cls']='v3.taobao.create';
                $re['arg']='{"note":"190401150309001","id":"190401150309004","m":"1"}';
                $re['gid']='0_G_4175470692_1553944591327';
                $re['data']='{"ret":["SUCCESS::调用成功"],"data":{"result":"{\"result\":{\"alipay_param\":{\"url\":\"service=\\\"alipay.fund.stdtrustee.order.create.pay\\\"&partner=\\\"2088401309894080\\\"&_input_charset=\\\"utf-8\\\"&notify_url=\\\"https://wwhongbao.taobao.com/callback/alipay/notifyPaySuccess.do\\\"&out_order_no=\\\"190401150309004_7f5d4d6bc8ce0233361f60124a952cf7_2\\\"&out_request_no=\\\"190401150309004_7f5d4d6bc8ce0233361f60124a952cf7_2_p\\\"&product_code=\\\"SOCIAL_RED_PACKETS\\\"&scene_code=\\\"MERCHANT_COUPON\\\"&amount=\\\"0.01\\\"&pay_strategy=\\\"CASHIER_PAYMENT\\\"&receipt_strategy=\\\"INNER_ACCOUNT_RECEIPTS\\\"&platform=\\\"DEFAULT\\\"&channel=\\\"APP\\\"&order_title=\\\"淘宝现金红包\\\"&master_order_no=\\\"2019040110002001180264605511\\\"&order_type=\\\"DEDUCT_ORDER\\\"&extra_param=\\\"{\\\"payeeShowName\\\":\\\"淘宝现金红包\\\"}\\\"&pay_timeout=\\\"30m\\\"&order_expired_time=\\\"360d\\\"&sign=\\\"aKBFC9D0yPEXKbG%2Fv3y%2BsbMDHlOr4LHDADejjqC2K2AXJMvu%2FRfr23tFSrSjrsrNxWyY%2BEe5EfsU1J4as0dHr2G6Mkn7G8X5sOzmblhRFLprY1yLpU6efcGmXlU05rOd85OyH8cL%2BqUxlF9Rf7i7G1n%2B%2B83lEHBP%2BIQ%2BQKv3grlMmHGmR9DK1VReKfuyWosgEnT8qxI%2BZ2palemBRuT0vuUB4sJzdOcesDlOv3JLwWsABFYTtaqet7XpUd5rQtvS8jglfaCUS8McMqFG6f3k8Ihu%2FAxBxGsypH94rDSpaijz6rz8e%2F5pHYJyU%2BepMXI3ZHJnl0cktxbtG2qempWQxg%3D%3D\\\"&sign_type=\\\"RSA\\\"\"}},\"msg\":\"success\",\"code\":0}"},"v":"1.0","api":"mtop.taobao.wangwang.whongbao.shoutao.create"}';
                $re['alipay']='service="alipay.fund.stdtrustee.order.create.pay"&partner="2088401309894080"&_input_charset="utf-8"&notify_url="https://wwhongbao.taobao.com/callback/alipay/notifyPaySuccess.do"&out_order_no="190401150309004_7f5d4d6bc8ce0233361f60124a952cf7_2"&out_request_no="190401150309004_7f5d4d6bc8ce0233361f60124a952cf7_2_p"&product_code="SOCIAL_RED_PACKETS"&scene_code="MERCHANT_COUPON"&amount="0.01"&pay_strategy="CASHIER_PAYMENT"&receipt_strategy="INNER_ACCOUNT_RECEIPTS"&platform="DEFAULT"&channel="APP"&order_title="淘宝现金红包"&master_order_no="2019040110002001180264605511"&order_type="DEDUCT_ORDER"&extra_param="{"payeeShowName":"淘宝现金红包"}"&pay_timeout="30m"&order_expired_time="360d"&sign="aKBFC9D0yPEXKbG%2Fv3y%2BsbMDHlOr4LHDADejjqC2K2AXJMvu%2FRfr23tFSrSjrsrNxWyY%2BEe5EfsU1J4as0dHr2G6Mkn7G8X5sOzmblhRFLprY1yLpU6efcGmXlU05rOd85OyH8cL%2BqUxlF9Rf7i7G1n%2B%2B83lEHBP%2BIQ%2BQKv3grlMmHGmR9DK1VReKfuyWosgEnT8qxI%2BZ2palemBRuT0vuUB4sJzdOcesDlOv3JLwWsABFYTtaqet7XpUd5rQtvS8jglfaCUS8McMqFG6f3k8Ihu%2FAxBxGsypH94rDSpaijz6rz8e%2F5pHYJyU%2BepMXI3ZHJnl0cktxbtG2qempWQxg%3D%3D"&sign_type="RSA"';
                $re['taoID']='4175470692';

                $re=[];
                $re['cls']='v3.taobao.pickHongBao';
                $re['arg']='{"asac":"1A181318463JDPC78Q0FUB","ccode":"0_G_4175470692_1553962811130","hongbao_id":"117291554052486000","sender":"cntaobaotb778947520"}';
                $re['data']='{"ret":["SUCCESS::调用成功"],"data":{"result":"{\"result\":{\"amount\":0,\"flow_id\":0,\"details\":[{\"is_most\":1,\"amount\":1,\"receiver\":\"cntaobaotb778947520\",\"timestamp\":1554053344000}],\"status\":2},\"msg\":\"success\",\"code\":0}"},"v":"1.0","api":"mtop.taobao.wangwang.whongbao.shoutao.pick"}';
                $re['taoID']='4175470692';
                */

                $re=['cls'=>'com.alipay.android.phone.messageboxstatic.biz.sync.d','userId'=>'2088232932547186' ];
                $re['data']= <<<EOF
SyncMessage [userId=2088232932547186, biz=MSG-BOX, msgData=[{"mk":190455135727200003,"st":1,"isSc":0,"appId":"","mct":1555307847000,"pl":"{\"templateType\":\"S\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/mwalletmng\/XYNZOTVpZxkryjGaSsoi.png\",\"link\":\"https:\/\/render.alipay.com\/p\/z\/merchant-mgnt\/simple-order.html?source=mdb_new_sqm_card\",\"msgId\":\"9cc4acab0dd0e3240bbd72c556e7d9e600718\",\"templateCode\":\"pep_MDailyBill_new_Push\",\"title\":\"商家服务·收款到账\",\"gmtCreate\":1555307847463,\"content\":\"{\\\"templateId\\\": \\\"WALLET-BILL@mbill-pay-fwc-dynamic-v2\\\", \\\"mainTitle\\\": \\\"收款金额\\\", \\\"mainAmount\\\": \\\"0.03\\\", \\\"contentList\\\": [{\\\"label\\\": \\\"汇总\\\", \\\"content\\\": \\\"今日第2笔收款，共计￥0.05\\\", \\\"isHighlight\\\": false}, {\\\"label\\\": \\\"备注\\\", \\\"content\\\": \\\"用花呗收钱，可支持顾>客使用花呗红包\\\", \\\"isHighlight\\\": true}], \\\"actionTitle\\\": \\\"商家服务\\\", \\\"actionName\\\": \\\"收款记录\\\", \\\"actionLink\\\": \\\"alipays:\/\/platformapi\/startapp?appId=60000081\\\", \\\"cardLink\\\": \\\"https:\/\/render.alipay.com\/p\/z\/merchant-mgnt\/simple-order.html?source=mdb_new_sqm_card\\\", \\\"content\\\": \\\"收款金额￥0.03\\\", \\\"link\\\": \\\"https:\/\/render.alipay.com\/p\/z\/merchant-mgnt\/simple-order.html?source=mdb_new_sqm_card\\\", \\\"assistMsg1\\\": \\\"今日第2笔收款，共计￥0.05\\\", \\\"assistMsg2\\\": \\\"用花呗收钱，可支持顾客使用花呗红包\\\"}\",\"linkName\":\"\",\"gmtValid\":1557899847455,\"operate\":\"SEND\",\"bizName\":\"商家服务·收款到账\",\"templateName\":\"商家服务收款到账\",\"homePageTitle\":\"商家服务: ￥0.03 收款到账通知\",\"status\":\"\",\"extraInfo\":\"{\\\"expireLink\\\":\\\"https:\/\/render.alipay.com\/p\/f\/fd-jblxfp45\/pages\/home\/index.html\\\",\\\"actionTitle\\\":\\\"商家服务\\\",\\\"link\\\":\\\"https:\/\/render.alipay.com\/p\/z\/merchant-mgnt\/simple-order.html?source=mdb_new_sqm_card\\\",\\\"mainAmount\\\":\\\"0.03\\\",\\\"templateId\\\":\\\"WALLET-BILL@mbill-pay-fwc-dynamic-v2\\\",\\\"actionLink\\\":\\\"alipays:\/\/platformapi\/startapp?appId=60000081\\\",\\\"content\\\":\\\"收款金额￥0.03\\\",\\\"assistMsg2\\\":\\\"用花呗收钱，可支持顾客使用花呗红包\\\",\\\"assistMsg1\\\":\\\"今日第2笔收款，共计￥0.05\\\",\\\"gmtValid\\\":1557899847455,\\\"cardLink\\\":\\\"https:\/\/render.alipay.com\/p\/z\/merchant-mgnt\/simple-order.html?source=mdb_new_sqm_card\\\",\\\"mainTitle\\\":\\\"收款金额\\\",\\\"contentList\\\":[{\\\"isHighlight\\\":false,\\\"label\\\":\\\"汇总\\\",\\\"content\\\":\\\"今日第2笔收款，共计￥0.05\\\"},{\\\"isHighlight\\\":true,\\\"label\\\":\\\"备注\\\",\\\"content\\\":\\\"用花呗收钱，可支持顾客使用花呗红包\\\"}],\\\"actionName\\\":\\\"收款记录\\\"}\"}"}], pushData=, id=2379,190455135727200003,1, hasMore=false], sOpcode=0]
EOF;

                $re= ['cls'=>'v3.dingding.DDHelper'];
                $re['data']='{"payUrl":"alipay_sdk=alipay-sdk-java-3.5.35.DEV&app_id=2018101061618854&biz_content=7UyROpIwNDbETBJrNdfXuutjfKDfqQVc5wAB1wHdeQWttRMj8EnIXx74dRUO7eE1jDoQARR9Br4VkWJRygQRUrHAeCMazMTCv8D7BRla69J3NlvzNR7vkbcdP0DuXH9T15Jv%2BCIm1Z97ehw7p7iX2To7WbH60mCKtD2c%2FvUkr0zDA69zZGHNtbEfCfnuwEKJ7DLY2y0beTzB0lKFLgePGf04yt9bjsadXWLqb1Spl8cmg5W43BZ%2BT6JbS3WOzck9EIMcZR8PjXqEw7M8CriTmREX0DkoHHr453R7gOBYimNdpmntZ5IJOzDtmEXjl8QZNkOAJyKKCtGnX5SeJ73z%2BEf6G4XKsAR8xuj1Q4dfuogPUd4E1Q%2F8qXc2mFOrFcjSed5veIpIkPLDgUTNZibERUsYD7OImpxa2YrsrS2g96ieAWlVEv0D4ull5gvmyCSoTM66h1sc0wcuzyOeZIunpZ89PnJQBEnKUkq%2BgcnMSZmpsnRR54tkIuglRDqHeYRlUX%2Fz63p6flacDDBVsGjPR%2BdXXeAFmv1b29wsavH9siFBY2srlh2SUDGFOSukj1TwUV%2BiNnE%2BiUkR%2BmQLNhYdJeZaagmUqXny8KbrZAbBngemZLCScLUT%2BFPm%2FzeCNoSTt6AxIttJmXiGH7iLbTny84GbocX2XVI43aNBoPKtYtrao6vYQ78okq%2F7P4TDeJhd1xr6UKPE47W7v9zu%2BsnCYJNG5WYrvUyIggUQmUX5zQrqXU6fAuI0%2Fjo0gaxsgqsqyf71soeF%2By0hxMD71UZzsxYFwKgTzIkLju2tTuj4TUKW1SUQtVAykxVDFmmLzeyB9ruOskl6G%2FwvuhW3XwuPgGjkilPP3KiGUD50ySsZUcSnxcYQv9nDXbDQRPsTeph2t4419fT5V2ER8ZxE%2BB7i7qUwAruR2tAFlDuuR8j43dAb1uE72pdgCGoM4%2Bxevwk%2FmxTdza2aqOeeuPvscgoslm6AfOuF1nl2yeDYEiEbwCh5fx%2F91j0UYaP9v6mWpp%2FUNEtilO%2FuuGEbUrsInTrEssUQriARc15xU%2FaHJbfWKfxZh%2B6uLbMQC08ADtlvRL0ufyXGhlbhGownneLR22oJmN7yR6JRH4hq559HSsOtw9R%2BQWHsE4UXcPu900VgcP4XH8%2FiHqn90lmLoXmL6IDDB7ecw7oRtjPyC2GOFweG0HAnwQvgRZGEpqUm3rxgdfF9cp1mrOP3EQfO%2F2Vwd9ALaIZPfNnA1Gylvpv2k81hju%2FV8X4LtHtQm8UJk9HRY12xZuclbA%2BaOpWyuRGH2n3tQjd855QvWHimf4FQjFtPxB9yWzXo8Yh3mBJzY5DBtekENjh7BLb7Sfq%2BAInSP5jc7SQu3TElIXnZkDTDsy%2B0SzXjqRMqkomhVLvKl6AbLf3Vokr%2FKsy4P0ouz0ohrP1bc0LMI3i7dq8R0%2B8COGgDyZ0%3D&charset=UTF-8&encrypt_type=AES&format=json&method=alipay.fund.trans.app.pay&sign=G24PgoV8KB2YwM%2BFN2Px3yEZR9LQk3qs%2BdY73sY0JULQpGxDTXUGStwl0e5Y7sEAp%2FUPRoDOODUy07PzOm0J1PkRQpN5Kh%2BFyR%2BCZ3G0l3LvtqHCcU4qoGVu0TEdgrr19M3HJDuad%2FcuMNyAF%2BxQetEnKyKawxmec4YwYptu%2FBrW9bJDxpBxwlavPehVD3vI96JBv4eEnL4umw%2BaeiBeBOXCLsubYhbd27Nk5GzI07MucBMDwBeaq55ft8QCT4RwmJk6yXLoh33ursXABD1RNGIBxLhOMZUkI9bF0dIkgwmTGhd2x8nU6MTaGhu2s9pdUORGSTb74FvRxFWDMDl%2FGw%3D%3D&sign_type=RSA2&timestamp=2019-04-16+11%3A51%3A30&version=1.0"}';
                $re['arg']='{"mBody":"[623105921,\"3hrdKWhG\"]","creatorUid":"623105921","groupBillId":"3hrdKWhG","linkUrl":"dingtalk://dingtalkclient/action/open_mini_app?miniAppId=2018092561515364&ddMode=push&keepAlive=false&mainTask=true&page=pages%2Fdetail%2Fhome%3FgroupBillId%3D3hrdKWhG%26creatorUid%3D623105921%26from%3Dconv","bill":"[{\"uid\":78198577,\"amount\":\"0.05\"}]","groupBillRealAmount":"0.05","groupBillTotalAmount":"0.05","groupBillName":"godnew","mUri":"/r/Adaptor/IDLGroupBill/payGroupBillV2"}';
                $re['dingID']='78198577';
                /*
                           $re=['cls'=>'v3.dingding.DDHelper',
                'data'=>'{"targetName":"聊了","amount":"+0.01","finishTime":1557020516000,"orderNo":"20190505094138127001000031332022","billCategory":"INCOME","bizCode":"GROUPBILL","outBizNo":"623105921_67ws58cN_652438767","title":"Tr1rzyu24sfg","alipayBillId":"2019050509415601120190505110075001502410008318736S","targetAlipayAccountName":"*道荣","bizName":"群收款","alipayOrderId":"20190505110075001502410008318736","targetAlipayAccountLogonId":"doo***@qq.com","targetProfile":"%40kgDOJuNs7w","targetUid":652438767}',
                'arg'=>'{"mBody":"[\"20190505110075000002410008296209\"]","from":"queryBill_num","mUri":"/r/Adaptor/WalletBill/queryBillDetail"}',
                'dingID'=>'623105921'];
                */


                /*

                $re=[];
                $re['cls']='v3.uniPay.qr';
                $re['data']= '{"msg":"交易超时或此卡不支持当前业务WL10086","resp":"WL10086","cmd":"p2pPay/applyQrCode","params":{}}';
                $re['arg']='{"money":"0.01","remark":"Tfscd2itvhz8","id":"Tfscd2itvhz8"}';
                $re['uniID']='141921667446637';


                $re=['cls'=>'myapp.v13.getAliBillList',
                    'data'=>'[{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D2019051622001450331034707959%26bizType%3DTRADE%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":194,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"2019051622001450331034707959","bizSubType":"104","bizType":"TRADE","canDelete":true,"categoryName":"小买卖","consumeFee":"+0.08","consumeStatus":"2","consumeTitle":"收钱码收款-来自*道荣","contentRender":1,"createDesc":"今天","createTime":"11:56","gmtCreate":1557978966000,"isAggregatedRec":false,"oppositeLogo":"https://t.alipayobjects.com/images/partner/T13vtwXcxaXXXXXXXX_[pixelWidth]x.png","recordType":"CONSUME","serializedSize":427,"tagStatus":0,"unknownFieldsSerializedSize":18}]',
                    'arg'=>'{"from":"商家服务"}',
                    'dt'=>'1557978973815',
                    'userId'=>'2088232932547186'];

                $re= ['cls'=>'com.alipay.android.phone.messageboxstatic.biz.sync.d',
                    'data'=>'SyncMessage [userId=2088232932547186, biz=MSG-BOX, msgData=[{"mk":190556223554200002,"st":1,"isSc":0,"mct":1558017354418,"pl":"{\"templateType\":\"S\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/mwalletmng\/XYNZOTVpZxkryjGaSsoi.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=activeTab%3DBILL%26channel%3Dcard&page=pages%2Fclerk%2Fhome%2Findex&chInfo=ch_clerktone_card_money_notice\",\"msgId\":\"172c9e3ccc1f56b3f9f71dbcc982c1da00499\",\"templateCode\":\"pep_MDailyBill_assistant_Push\",\"title\":\"商家服务·收款到账(店员)\",\"gmtCreate\":1558017354411,\"content\":\"{\\\"templateId\\\": \\\"WALLET-BILL@mbill-pay-fwc-dynamic-v2\\\", \\\"mainTitle\\\": \\\"收款金额\\\", \\\"mainAmount\\\": \\\"200.00\\\", \\\"contentList\\\": [{\\\"label\\\": \\\"汇总\\\", \\\"content\\\": \\\"今日第26笔收款，共计￥4651.00\\\", \\\"isHighlight\\\": false}, {\\\"label\\\": \\\"备注\\\", \\\"content\\\": \\\"点此查看收款明细\\\", \\\"isHighlight\\\": true}], \\\"actionTitle\\\": \\\"商家服务·店员通\\\", \\\"actionName\\\": \\\"收款记录\\\", \\\"actionLink\\\": \\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=channel%3Dcard\\\", \\\"cardLink\\\": \\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=activeTab%3DBILL%26channel%3Dcard&page=pages%2Fclerk%2Fhome%2Findex&chInfo=ch_clerktone_card_money_notice\\\", \\\"content\\\": \\\"收款金额￥200.00\\\", \\\"link\\\": \\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=activeTab%3DBILL%26channel%3Dcard&page=pages%2Fclerk%2Fhome%2Findex&chInfo=ch_clerktone_card_money_notice\\\", \\\"assistMsg1\\\": \\\"今日第26笔收款，共计￥4651.00\\\", \\\"assistMsg2\\\": \\\"点此查看收款明细\\\"}\",\"linkName\":\"\",\"gmtValid\":1560609354404,\"operate\":\"SEND\",\"bizName\":\"商家服务·收款到账(店员)\",\"templateName\":\"商家服务收款到账(店员)\",\"homePageTitle\":\"商家服务: ￥200.00 收款到账通知\",\"attributes\":\"0000000000000001\",\"status\":\"\",\"extraInfo\":\"{\\\"expireLink\\\":\\\"https:\/\/render.alipay.com\/p\/f\/fd-jblxfp45\/pages\/home\/index.html\\\",\\\"actionTitle\\\":\\\"商家服务·店员通\\\",\\\"link\\\":\\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=activeTab%3DBILL%26channel%3Dcard&page=pages%2Fclerk%2Fhome%2Findex&chInfo=ch_clerktone_card_money_notice\\\",\\\"mainAmount\\\":\\\"200.00\\\",\\\"templateId\\\":\\\"WALLET-BILL@mbill-pay-fwc-dynamic-v2\\\",\\\"actionLink\\\":\\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=channel%3Dcard\\\",\\\"content\\\":\\\"收款金额￥200.00\\\",\\\"assistMsg2\\\":\\\"点此查看收款明细\\\",\\\"assistMsg1\\\":\\\"今日第26笔收款，共计￥4651.00\\\",\\\"gmtValid\\\":1560609354404,\\\"sceneExt\\\":{\\\"sceneTemplateId\\\":\\\"USER_DEFINED\\\",\\\"sceneUrl\\\":\\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=activeTab%3DBILL%26channel%3Dcard&page=pages%2Fclerk%2Fhome%2Findex&chInfo=ch_clerktone_card_money_notice\\\",\\\"sceneType\\\":\\\"nativeApp\\\",\\\"sceneTitle\\\":\\\"进入应用\\\"},\\\"cardLink\\\":\\\"alipays:\/\/platformapi\/startapp?appId=2018030502317554&query=activeTab%3DBILL%26channel%3Dcard&page=pages%2Fclerk%2Fhome%2Findex&chInfo=ch_clerktone_card_money_notice\\\",\\\"mainTitle\\\":\\\"收款金额\\\",\\\"contentList\\\":[{\\\"isHighlight\\\":false,\\\"label\\\":\\\"汇总\\\",\\\"content\\\":\\\"今日第26笔收款，共计￥4651.00\\\"},{\\\"isHighlight\\\":true,\\\"label\\\":\\\"备注\\\",\\\"content\\\":\\\"点此查看收款明细\\\"}],\\\"actionName\\\":\\\"收款记录\\\"}\"}"}], pushData=, id=3581,ID:190556223554200002,1, hasMore=false], sOpcode=0]',
                    'userId'=>'2088232932547186'];

                $re=['cls'=>'myapp.v13.ReceiveCrowdTask',
                    'orderNo'=>'201908180206302100000000180045462099',
                    'tuid'=>'2088632061595964',
                    'remark'=>'恭喜发财，万事如意！',
                    'money'=>'0.01',
                    'data'=>'{"asyncRec":false,"crowdDuration":0,"extInfo":{},"giftCrowdFlowInfo":{"best":true,"crowdNo":"201908180206302100000000180045462099","id":12902611,"memo":"红包金额将打入你的支付宝账户","ownFlag":true,"receiveAmount":"0.01","receiveCount":0,"receiveDateDesc":"今天 12:52:05","receiver":{"alipayAccount":"150***3879","imgUrl":"","realFriend":true,"userId":"2088632061595964","userName":"吓搞"},"returnCount":0,"scratchCount":0,"state":"RECEIVE_SUC","stateDesc":"","win":false},"giftCrowdFlowInfoList":[{"best":true,"crowdNo":"201908180206302100000000180045462099","id":12902611,"memo":"红包金额将打入你的支付宝账户","ownFlag":true,"receiveAmount":"0.01","receiveCount":0,"receiveDateDesc":"今天 12:52:05","receiver":{"alipayAccount":"150***3879","imgUrl":"","realFriend":true,"userId":"2088632061595964","userName":"吓搞"},"returnCount":0,"scratchCount":0,"state":"RECEIVE_SUC","stateDesc":"","win":false}],"giftCrowdInfo":{"amount":"0.01","canResend":false,"count":0,"creator":{"alipayAccount":"qqm***@163.com","imgUrl":"http://tfs.alipayobjects.com/images/partner/TB1kDK_X7VDDuNkUuGVXXX_sXXa_160X160","realFriend":false,"userId":"2088232932547186","userName":"大桥"},"crowdDuration":24,"crowdNo":"201908180206302100000000180045462099","gcashUseAvg":false,"gender":"all","gmtCreateDesc":"今天 12:49","id":0,"prodCode":"CROWD_CODE_CASH","prodName":"口令红包","remark":"恭喜发财，万事如意！","subTitle1Txt":"1个红包共0.01元，3分钟内被抢光","totalNumber":0,"withStars":false},"guessResult":false,"hasNextPage":false,"needCertify":false,"needRealName":false,"needWriteMessage":false,"received":false,"resultCode":"1321","resultDesc":"已领过","resultView":"已领过","snsShareInfo":{"success":false},"success":true}',
                    'dt'=>'1566111933724',
                    'arg'=>'{"scode":"56383218","id":"801890405"}',
                    'userId'=>'2088632061595964'];

                $re= ['cls'=>'myapp.error.klcheck',
'data'=>'ReceiveCrowdTask',
'arg'=>'{"scode":"72988918","sign":"521513f84f226e4a7d7110751f233a27723d57633ba1d1fdd985352b9df13ec6","id":"8018904089","crowdNo":"201908180206302100000000180045463319"}',
'userId'=>'2088632061595964'];


                $re= ['cls'=>'v3.uniPay.order.list' ];
                $re['data']='{"cmd":"order/list","msg":"","params":{"currentPage":"1","pageSize":"50","totalPage":"73","uporders":[{"amount":"30.97","currencyUnit":"元","orderId":"2019082922043170F751A3CE2B7D77EBB6C7D88722143F-79809170779568-03080000   00049992   4673350829220431_270","orderStatus":"02","orderTime":"20190829 22:04:31","orderType":"A2000021","title":"向*宇飞-收款","tn":"03080000   00049992   4673350829220431"},{"amount":"49.96","currencyUnit":"元","orderId":"2019082922035770F751A3CE2B7D77EBB6C7D88722143F-79809170779642-00250001   00049992   4695690829220357_270","orderStatus":"02","orderTime":"20190829 22:03:57","orderType":"A2000021","title":"向*炜峰-收款","tn":"00250001   00049992   4695690829220357"},{"amount":"39.97","currencyUnit":"元","orderId":"2019082922033370F751A3CE2B7D77EBB6C7D88722143F-79809170779666-03080000   00049992   3069570829220333_270","orderStatus":"02","orderTime":"20190829 22:03:33","orderType":"A2000021","title":"向*雪-收款","tn":"03080000   00049992   3069570829220333"},{"amount":"99.95","currencyUnit":"元","orderId":"2019082922030570F751A3CE2B7D77EBB6C7D88722143F-79809170779694-00250001   00049992   3413190829220304_270","orderStatus":"02","orderTime":"20190829 22:03:05","orderType":"A2000021","title":"向*苑芳-收款","tn":"00250001   00049992   3413190829220304"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082922030070F751A3CE2B7D77EBB6C7D88722143F-79809170779699-00250001   00049992   2387080829220259_270","orderStatus":"02","orderTime":"20190829 22:03:00","orderType":"A2000021","title":"向*隆隆-收款","tn":"00250001   00049992   2387080829220259"},{"amount":"49.96","currencyUnit":"元","orderId":"2019082922025570F751A3CE2B7D77EBB6C7D88722143F-79809170779744-00250001   00049992   1948360829220255_270","orderStatus":"02","orderTime":"20190829 22:02:55","orderType":"A2000021","title":"向*江-收款","tn":"00250001   00049992   1948360829220255"},{"amount":"149.95","currencyUnit":"元","orderId":"2019082922020570F751A3CE2B7D77EBB6C7D88722143F-79809170779794-00250001   00049992   2035710829220204_270","orderStatus":"02","orderTime":"20190829 22:02:05","orderType":"A2000021","title":"向*娟-收款","tn":"00250001   00049992   2035710829220204"},{"amount":"199.92","currencyUnit":"元","orderId":"2019082922020370F751A3CE2B7D77EBB6C7D88722143F-79809170779796-00250001   00049992   0959570829220202_270","orderStatus":"02","orderTime":"20190829 22:02:03","orderType":"A2000021","title":"向*启国-收款","tn":"00250001   00049992   0959570829220202"},{"amount":"119.98","currencyUnit":"元","orderId":"2019082922013270F751A3CE2B7D77EBB6C7D88722143F-79809170779867-00250001   00049992   9998360829220132_270","orderStatus":"02","orderTime":"20190829 22:01:32","orderType":"A2000021","title":"向*泽-收款","tn":"00250001   00049992   9998360829220132"},{"amount":"30.97","currencyUnit":"元","orderId":"2019082922011070F751A3CE2B7D77EBB6C7D88722143F-79809170779889-00250001   00049992   1100740829220109_270","orderStatus":"02","orderTime":"20190829 22:01:10","orderType":"A2000021","title":"向*文叶-收款","tn":"00250001   00049992   1100740829220109"},{"amount":"39.97","currencyUnit":"元","orderId":"2019082921594170F751A3CE2B7D77EBB6C7D88722143F-79809170784058-00250001   00049992   7665820829215940_270","orderStatus":"02","orderTime":"20190829 21:59:41","orderType":"A2000021","title":"向*俊-收款","tn":"00250001   00049992   7665820829215940"},{"amount":"299.96","currencyUnit":"元","orderId":"2019082921593370F751A3CE2B7D77EBB6C7D88722143F-79809170784066-00250001   00049992   7464570829215932_270","orderStatus":"02","orderTime":"20190829 21:59:33","orderType":"A2000021","title":"向*亮-收款","tn":"00250001   00049992   7464570829215932"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082921592770F751A3CE2B7D77EBB6C7D88722143F-79809170784072-00250001   00049992   7427080829215927_270","orderStatus":"02","orderTime":"20190829 21:59:27","orderType":"A2000021","title":"向*磊-收款","tn":"00250001   00049992   7427080829215927"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921582770F751A3CE2B7D77EBB6C7D88722143F-79809170784172-00250001   00049992   7245740829215826_270","orderStatus":"02","orderTime":"20190829 21:58:27","orderType":"A2000021","title":"向*君勇-收款","tn":"00250001   00049992   7245740829215826"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921574070F751A3CE2B7D77EBB6C7D88722143F-79809170784259-00250001   00049992   5255780829215739_270","orderStatus":"02","orderTime":"20190829 21:57:40","orderType":"A2000021","title":"向*艳-收款","tn":"00250001   00049992   5255780829215739"},{"amount":"100.93","currencyUnit":"元","orderId":"2019082921571670F751A3CE2B7D77EBB6C7D88722143F-79809170784283-00250001   00049992   6663370829215715_270","orderStatus":"02","orderTime":"20190829 21:57:16","orderType":"A2000021","title":"向*超群-收款","tn":"00250001   00049992   6663370829215715"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921564870F751A3CE2B7D77EBB6C7D88722143F-79809170784351-00250001   00049992   4044450829215647_270","orderStatus":"02","orderTime":"20190829 21:56:48","orderType":"A2000021","title":"向*武兴-收款","tn":"00250001   00049992   4044450829215647"},{"amount":"199.98","currencyUnit":"元","orderId":"2019082921564870F751A3CE2B7D77EBB6C7D88722143F-79809170784351-00250001   00049992   4104540829215647_270","orderStatus":"02","orderTime":"20190829 21:56:48","orderType":"A2000021","title":"向*丽杰-收款","tn":"00250001   00049992   4104540829215647"},{"amount":"339.86","currencyUnit":"元","orderId":"2019082921551870F751A3CE2B7D77EBB6C7D88722143F-79809170784481-00250001   00049992   2333210829215518_270","orderStatus":"02","orderTime":"20190829 21:55:18","orderType":"A2000021","title":"向*乐-收款","tn":"00250001   00049992   2333210829215518"},{"amount":"99.95","currencyUnit":"元","orderId":"2019082921545570F751A3CE2B7D77EBB6C7D88722143F-79809170784544-00250001   00049992   1759470829215454_270","orderStatus":"02","orderTime":"20190829 21:54:55","orderType":"A2000021","title":"向*丹丹-收款","tn":"00250001   00049992   1759470829215454"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921545170F751A3CE2B7D77EBB6C7D88722143F-79809170784548-00250001   00049992   1510810829215450_270","orderStatus":"02","orderTime":"20190829 21:54:51","orderType":"A2000021","title":"向*朝华-收款","tn":"00250001   00049992   1510810829215450"},{"amount":"99.98","currencyUnit":"元","orderId":"2019082921542970F751A3CE2B7D77EBB6C7D88722143F-79809170784570-00250001   00049992   1471990829215429_270","orderStatus":"02","orderTime":"20190829 21:54:29","orderType":"A2000021","title":"向*沛-收款","tn":"00250001   00049992   1471990829215429"},{"amount":"99.97","currencyUnit":"元","orderId":"2019082921541770F751A3CE2B7D77EBB6C7D88722143F-79809170784582-00250001   00049992   0723230829215416_270","orderStatus":"02","orderTime":"20190829 21:54:17","orderType":"A2000021","title":"向*宏焕-收款","tn":"00250001   00049992   0723230829215416"},{"amount":"129.94","currencyUnit":"元","orderId":"2019082921540070F751A3CE2B7D77EBB6C7D88722143F-79809170784599-00250001   00049992   9399610829215359_270","orderStatus":"02","orderTime":"20190829 21:54:00","orderType":"A2000021","title":"向*涛-收款","tn":"00250001   00049992   9399610829215359"},{"amount":"99.98","currencyUnit":"元","orderId":"2019082921530970F751A3CE2B7D77EBB6C7D88722143F-79809170784690-00250001   00049992   8910790829215309_270","orderStatus":"02","orderTime":"20190829 21:53:09","orderType":"A2000021","title":"向*梦庆-收款","tn":"00250001   00049992   8910790829215309"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921520570F751A3CE2B7D77EBB6C7D88722143F-79809170784794-00250001   00049992   7891990829215204_270","orderStatus":"02","orderTime":"20190829 21:52:05","orderType":"A2000021","title":"向*春刚-收款","tn":"00250001   00049992   7891990829215204"},{"amount":"44.96","currencyUnit":"元","orderId":"2019082921512670F751A3CE2B7D77EBB6C7D88722143F-79809170784873-03080000   00049992   6508310829215126_270","orderStatus":"02","orderTime":"20190829 21:51:26","orderType":"A2000021","title":"向*龙-收款","tn":"03080000   00049992   6508310829215126"},{"amount":"129.95","currencyUnit":"元","orderId":"2019082921505570F751A3CE2B7D77EBB6C7D88722143F-79809170784944-00250001   00049992   5686980829215054_270","orderStatus":"02","orderTime":"20190829 21:50:55","orderType":"A2000021","title":"向*洪波-收款","tn":"00250001   00049992   5686980829215054"},{"amount":"34.98","currencyUnit":"元","orderId":"2019082921504070F751A3CE2B7D77EBB6C7D88722143F-79809170784959-00250001   00049992   4438360829215040_270","orderStatus":"02","orderTime":"20190829 21:50:40","orderType":"A2000021","title":"向*振超-收款","tn":"00250001   00049992   4438360829215040"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921503070F751A3CE2B7D77EBB6C7D88722143F-79809170784969-00250001   00049992   4543330829215029_270","orderStatus":"02","orderTime":"20190829 21:50:30","orderType":"A2000021","title":"向*路路-收款","tn":"00250001   00049992   4543330829215029"},{"amount":"99.95","currencyUnit":"元","orderId":"2019082921491570F751A3CE2B7D77EBB6C7D88722143F-79809170785084-00250001   00049992   2637080829214914_270","orderStatus":"02","orderTime":"20190829 21:49:15","orderType":"A2000021","title":"向*东庭-收款","tn":"00250001   00049992   2637080829214914"},{"amount":"99.94","currencyUnit":"元","orderId":"2019082921483270F751A3CE2B7D77EBB6C7D88722143F-79809170785167-00250001   00049992   2200710829214831_270","orderStatus":"02","orderTime":"20190829 21:48:32","orderType":"A2000021","title":"向*超-收款","tn":"00250001   00049992   2200710829214831"},{"amount":"199.96","currencyUnit":"元","orderId":"2019082921481870F751A3CE2B7D77EBB6C7D88722143F-79809170785181-00250001   00049992   2030750829214817_270","orderStatus":"02","orderTime":"20190829 21:48:18","orderType":"A2000021","title":"向*迎良-收款","tn":"00250001   00049992   2030750829214817"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082921475170F751A3CE2B7D77EBB6C7D88722143F-79809170785248-00250001   00049992   0179610829214750_270","orderStatus":"02","orderTime":"20190829 21:47:51","orderType":"A2000021","title":"向*涛-收款","tn":"00250001   00049992   0179610829214750"},{"amount":"99.96","currencyUnit":"元","orderId":"2019082921473870F751A3CE2B7D77EBB6C7D88722143F-79809170785261-00250001   00049992   1164490829214737_270","orderStatus":"02","orderTime":"20190829 21:47:38","orderType":"A2000021","title":"向*顺豪-收款","tn":"00250001   00049992   1164490829214737"},{"amount":"79.98","currencyUnit":"元","orderId":"2019082921471070F751A3CE2B7D77EBB6C7D88722143F-79809170785289-00250001   00049992   9823280829214709_270","orderStatus":"02","orderTime":"20190829 21:47:10","orderType":"A2000021","title":"向*磊-收款","tn":"00250001   00049992   9823280829214709"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082921470070F751A3CE2B7D77EBB6C7D88722143F-79809170785299-00250001   00049992   9388200829214700_270","orderStatus":"02","orderTime":"20190829 21:47:00","orderType":"A2000021","title":"向*黎明-收款","tn":"00250001   00049992   9388200829214700"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921460170F751A3CE2B7D77EBB6C7D88722143F-79809170785398-00250001   00049992   8658240829214600_270","orderStatus":"02","orderTime":"20190829 21:46:01","orderType":"A2000021","title":"向*全燕-收款","tn":"00250001   00049992   8658240829214600"},{"amount":"199.97","currencyUnit":"元","orderId":"2019082921454370F751A3CE2B7D77EBB6C7D88722143F-79809170785456-00250001   00049992   8190740829214542_270","orderStatus":"02","orderTime":"20190829 21:45:43","orderType":"A2000021","title":"向*春发-收款","tn":"00250001   00049992   8190740829214542"},{"amount":"164.92","currencyUnit":"元","orderId":"2019082921445470F751A3CE2B7D77EBB6C7D88722143F-79809170785545-00250001   00049992   6669510829214453_270","orderStatus":"02","orderTime":"20190829 21:44:54","orderType":"A2000021","title":"向*桂-收款","tn":"00250001   00049992   6669510829214453"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921445470F751A3CE2B7D77EBB6C7D88722143F-79809170785545-00250001   00049992   6677010829214453_270","orderStatus":"02","orderTime":"20190829 21:44:54","orderType":"A2000021","title":"向*兵-收款","tn":"00250001   00049992   6677010829214453"},{"amount":"44.97","currencyUnit":"元","orderId":"2019082921443270F751A3CE2B7D77EBB6C7D88722143F-79809170785567-00250001   00049992   5993310829214431_270","orderStatus":"02","orderTime":"20190829 21:44:32","orderType":"A2000021","title":"向*寒文-收款","tn":"00250001   00049992   5993310829214431"},{"amount":"57.98","currencyUnit":"元","orderId":"2019082921431870F751A3CE2B7D77EBB6C7D88722143F-79809170785681-00250001   00049992   4143260829214317_270","orderStatus":"02","orderTime":"20190829 21:43:18","orderType":"A2000021","title":"向*芳-收款","tn":"00250001   00049992   4143260829214317"},{"amount":"499.92","currencyUnit":"元","orderId":"2019082921423970F751A3CE2B7D77EBB6C7D88722143F-79809170785760-00250001   00049992   2954430829214238_270","orderStatus":"02","orderTime":"20190829 21:42:39","orderType":"A2000021","title":"向*新-收款","tn":"00250001   00049992   2954430829214238"},{"amount":"35.97","currencyUnit":"元","orderId":"2019082921420570F751A3CE2B7D77EBB6C7D88722143F-79809170785794-00250001   00049992   2160800829214204_270","orderStatus":"02","orderTime":"20190829 21:42:05","orderType":"A2000021","title":"向*舜光-收款","tn":"00250001   00049992   2160800829214204"},{"amount":"30.97","currencyUnit":"元","orderId":"2019082921414770F751A3CE2B7D77EBB6C7D88722143F-79809170785852-00250001   00049992   1543280829214147_270","orderStatus":"02","orderTime":"20190829 21:41:47","orderType":"A2000021","title":"向*强-收款","tn":"00250001   00049992   1543280829214147"},{"amount":"39.98","currencyUnit":"元","orderId":"2019082921411370F751A3CE2B7D77EBB6C7D88722143F-79809170785886-00250001   00049992   9952110829214112_270","orderStatus":"02","orderTime":"20190829 21:41:13","orderType":"A2000021","title":"向*江华-收款","tn":"00250001   00049992   9952110829214112"},{"amount":"199.95","currencyUnit":"元","orderId":"2019082921394770F751A3CE2B7D77EBB6C7D88722143F-79809170786052-00250001   00049992   8324430829213946_270","orderStatus":"02","orderTime":"20190829 21:39:47","orderType":"A2000021","title":"向*国举-收款","tn":"00250001   00049992   8324430829213946"},{"amount":"49.96","currencyUnit":"元","orderId":"2019082921394470F751A3CE2B7D77EBB6C7D88722143F-79809170786055-00250001   00049992   0305870829213943_270","orderStatus":"02","orderTime":"20190829 21:39:44","orderType":"A2000021","title":"向*银-收款","tn":"00250001   00049992   0305870829213943"},{"amount":"999.96","currencyUnit":"元","orderId":"2019082921393370F751A3CE2B7D77EBB6C7D88722143F-79809170786066-00250001   00049992   7762090829213932_270","orderStatus":"02","orderTime":"20190829 21:39:33","orderType":"A2000021","title":"向*玉娇-收款","tn":"00250001   00049992   7762090829213932"}]},"resp":"00"}';
                $re['arg']='{"queryNum":10}';
                $re['uniID']='141921667446637';
                $rvar = $this->getLogin()->createPayLog()->V3Parse( $re);

                 */


                $re= ['cls'=>'v3.uniPay.order.list' ];
                $re['data']='{"cmd":"order/list","msg":"","params":{"currentPage":"1","pageSize":"50","totalPage":"73","uporders":[{"amount":"30.97","currencyUnit":"元","orderId":"2019082922043170F751A3CE2B7D77EBB6C7D88722143F-79809170779568-03080000   00049992   4673350829220431_270","orderStatus":"02","orderTime":"20190829 22:04:31","orderType":"A2000021","title":"向*宇飞-收款","tn":"03080000   00049992   4673350829220431"},{"amount":"49.96","currencyUnit":"元","orderId":"2019082922035770F751A3CE2B7D77EBB6C7D88722143F-79809170779642-00250001   00049992   4695690829220357_270","orderStatus":"02","orderTime":"20190829 22:03:57","orderType":"A2000021","title":"向*炜峰-收款","tn":"00250001   00049992   4695690829220357"},{"amount":"39.97","currencyUnit":"元","orderId":"2019082922033370F751A3CE2B7D77EBB6C7D88722143F-79809170779666-03080000   00049992   3069570829220333_270","orderStatus":"02","orderTime":"20190829 22:03:33","orderType":"A2000021","title":"向*雪-收款","tn":"03080000   00049992   3069570829220333"},{"amount":"99.95","currencyUnit":"元","orderId":"2019082922030570F751A3CE2B7D77EBB6C7D88722143F-79809170779694-00250001   00049992   3413190829220304_270","orderStatus":"02","orderTime":"20190829 22:03:05","orderType":"A2000021","title":"向*苑芳-收款","tn":"00250001   00049992   3413190829220304"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082922030070F751A3CE2B7D77EBB6C7D88722143F-79809170779699-00250001   00049992   2387080829220259_270","orderStatus":"02","orderTime":"20190829 22:03:00","orderType":"A2000021","title":"向*隆隆-收款","tn":"00250001   00049992   2387080829220259"},{"amount":"49.96","currencyUnit":"元","orderId":"2019082922025570F751A3CE2B7D77EBB6C7D88722143F-79809170779744-00250001   00049992   1948360829220255_270","orderStatus":"02","orderTime":"20190829 22:02:55","orderType":"A2000021","title":"向*江-收款","tn":"00250001   00049992   1948360829220255"},{"amount":"149.95","currencyUnit":"元","orderId":"2019082922020570F751A3CE2B7D77EBB6C7D88722143F-79809170779794-00250001   00049992   2035710829220204_270","orderStatus":"02","orderTime":"20190829 22:02:05","orderType":"A2000021","title":"向*娟-收款","tn":"00250001   00049992   2035710829220204"},{"amount":"199.92","currencyUnit":"元","orderId":"2019082922020370F751A3CE2B7D77EBB6C7D88722143F-79809170779796-00250001   00049992   0959570829220202_270","orderStatus":"02","orderTime":"20190829 22:02:03","orderType":"A2000021","title":"向*启国-收款","tn":"00250001   00049992   0959570829220202"},{"amount":"119.98","currencyUnit":"元","orderId":"2019082922013270F751A3CE2B7D77EBB6C7D88722143F-79809170779867-00250001   00049992   9998360829220132_270","orderStatus":"02","orderTime":"20190829 22:01:32","orderType":"A2000021","title":"向*泽-收款","tn":"00250001   00049992   9998360829220132"},{"amount":"30.97","currencyUnit":"元","orderId":"2019082922011070F751A3CE2B7D77EBB6C7D88722143F-79809170779889-00250001   00049992   1100740829220109_270","orderStatus":"02","orderTime":"20190829 22:01:10","orderType":"A2000021","title":"向*文叶-收款","tn":"00250001   00049992   1100740829220109"},{"amount":"39.97","currencyUnit":"元","orderId":"2019082921594170F751A3CE2B7D77EBB6C7D88722143F-79809170784058-00250001   00049992   7665820829215940_270","orderStatus":"02","orderTime":"20190829 21:59:41","orderType":"A2000021","title":"向*俊-收款","tn":"00250001   00049992   7665820829215940"},{"amount":"299.96","currencyUnit":"元","orderId":"2019082921593370F751A3CE2B7D77EBB6C7D88722143F-79809170784066-00250001   00049992   7464570829215932_270","orderStatus":"02","orderTime":"20190829 21:59:33","orderType":"A2000021","title":"向*亮-收款","tn":"00250001   00049992   7464570829215932"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082921592770F751A3CE2B7D77EBB6C7D88722143F-79809170784072-00250001   00049992   7427080829215927_270","orderStatus":"02","orderTime":"20190829 21:59:27","orderType":"A2000021","title":"向*磊-收款","tn":"00250001   00049992   7427080829215927"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921582770F751A3CE2B7D77EBB6C7D88722143F-79809170784172-00250001   00049992   7245740829215826_270","orderStatus":"02","orderTime":"20190829 21:58:27","orderType":"A2000021","title":"向*君勇-收款","tn":"00250001   00049992   7245740829215826"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921574070F751A3CE2B7D77EBB6C7D88722143F-79809170784259-00250001   00049992   5255780829215739_270","orderStatus":"02","orderTime":"20190829 21:57:40","orderType":"A2000021","title":"向*艳-收款","tn":"00250001   00049992   5255780829215739"},{"amount":"100.93","currencyUnit":"元","orderId":"2019082921571670F751A3CE2B7D77EBB6C7D88722143F-79809170784283-00250001   00049992   6663370829215715_270","orderStatus":"02","orderTime":"20190829 21:57:16","orderType":"A2000021","title":"向*超群-收款","tn":"00250001   00049992   6663370829215715"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921564870F751A3CE2B7D77EBB6C7D88722143F-79809170784351-00250001   00049992   4044450829215647_270","orderStatus":"02","orderTime":"20190829 21:56:48","orderType":"A2000021","title":"向*武兴-收款","tn":"00250001   00049992   4044450829215647"},{"amount":"199.98","currencyUnit":"元","orderId":"2019082921564870F751A3CE2B7D77EBB6C7D88722143F-79809170784351-00250001   00049992   4104540829215647_270","orderStatus":"02","orderTime":"20190829 21:56:48","orderType":"A2000021","title":"向*丽杰-收款","tn":"00250001   00049992   4104540829215647"},{"amount":"339.86","currencyUnit":"元","orderId":"2019082921551870F751A3CE2B7D77EBB6C7D88722143F-79809170784481-00250001   00049992   2333210829215518_270","orderStatus":"02","orderTime":"20190829 21:55:18","orderType":"A2000021","title":"向*乐-收款","tn":"00250001   00049992   2333210829215518"},{"amount":"99.95","currencyUnit":"元","orderId":"2019082921545570F751A3CE2B7D77EBB6C7D88722143F-79809170784544-00250001   00049992   1759470829215454_270","orderStatus":"02","orderTime":"20190829 21:54:55","orderType":"A2000021","title":"向*丹丹-收款","tn":"00250001   00049992   1759470829215454"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921545170F751A3CE2B7D77EBB6C7D88722143F-79809170784548-00250001   00049992   1510810829215450_270","orderStatus":"02","orderTime":"20190829 21:54:51","orderType":"A2000021","title":"向*朝华-收款","tn":"00250001   00049992   1510810829215450"},{"amount":"99.98","currencyUnit":"元","orderId":"2019082921542970F751A3CE2B7D77EBB6C7D88722143F-79809170784570-00250001   00049992   1471990829215429_270","orderStatus":"02","orderTime":"20190829 21:54:29","orderType":"A2000021","title":"向*沛-收款","tn":"00250001   00049992   1471990829215429"},{"amount":"99.97","currencyUnit":"元","orderId":"2019082921541770F751A3CE2B7D77EBB6C7D88722143F-79809170784582-00250001   00049992   0723230829215416_270","orderStatus":"02","orderTime":"20190829 21:54:17","orderType":"A2000021","title":"向*宏焕-收款","tn":"00250001   00049992   0723230829215416"},{"amount":"129.94","currencyUnit":"元","orderId":"2019082921540070F751A3CE2B7D77EBB6C7D88722143F-79809170784599-00250001   00049992   9399610829215359_270","orderStatus":"02","orderTime":"20190829 21:54:00","orderType":"A2000021","title":"向*涛-收款","tn":"00250001   00049992   9399610829215359"},{"amount":"99.98","currencyUnit":"元","orderId":"2019082921530970F751A3CE2B7D77EBB6C7D88722143F-79809170784690-00250001   00049992   8910790829215309_270","orderStatus":"02","orderTime":"20190829 21:53:09","orderType":"A2000021","title":"向*梦庆-收款","tn":"00250001   00049992   8910790829215309"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921520570F751A3CE2B7D77EBB6C7D88722143F-79809170784794-00250001   00049992   7891990829215204_270","orderStatus":"02","orderTime":"20190829 21:52:05","orderType":"A2000021","title":"向*春刚-收款","tn":"00250001   00049992   7891990829215204"},{"amount":"44.96","currencyUnit":"元","orderId":"2019082921512670F751A3CE2B7D77EBB6C7D88722143F-79809170784873-03080000   00049992   6508310829215126_270","orderStatus":"02","orderTime":"20190829 21:51:26","orderType":"A2000021","title":"向*龙-收款","tn":"03080000   00049992   6508310829215126"},{"amount":"129.95","currencyUnit":"元","orderId":"2019082921505570F751A3CE2B7D77EBB6C7D88722143F-79809170784944-00250001   00049992   5686980829215054_270","orderStatus":"02","orderTime":"20190829 21:50:55","orderType":"A2000021","title":"向*洪波-收款","tn":"00250001   00049992   5686980829215054"},{"amount":"34.98","currencyUnit":"元","orderId":"2019082921504070F751A3CE2B7D77EBB6C7D88722143F-79809170784959-00250001   00049992   4438360829215040_270","orderStatus":"02","orderTime":"20190829 21:50:40","orderType":"A2000021","title":"向*振超-收款","tn":"00250001   00049992   4438360829215040"},{"amount":"30.98","currencyUnit":"元","orderId":"2019082921503070F751A3CE2B7D77EBB6C7D88722143F-79809170784969-00250001   00049992   4543330829215029_270","orderStatus":"02","orderTime":"20190829 21:50:30","orderType":"A2000021","title":"向*路路-收款","tn":"00250001   00049992   4543330829215029"},{"amount":"99.95","currencyUnit":"元","orderId":"2019082921491570F751A3CE2B7D77EBB6C7D88722143F-79809170785084-00250001   00049992   2637080829214914_270","orderStatus":"02","orderTime":"20190829 21:49:15","orderType":"A2000021","title":"向*东庭-收款","tn":"00250001   00049992   2637080829214914"},{"amount":"99.94","currencyUnit":"元","orderId":"2019082921483270F751A3CE2B7D77EBB6C7D88722143F-79809170785167-00250001   00049992   2200710829214831_270","orderStatus":"02","orderTime":"20190829 21:48:32","orderType":"A2000021","title":"向*超-收款","tn":"00250001   00049992   2200710829214831"},{"amount":"199.96","currencyUnit":"元","orderId":"2019082921481870F751A3CE2B7D77EBB6C7D88722143F-79809170785181-00250001   00049992   2030750829214817_270","orderStatus":"02","orderTime":"20190829 21:48:18","orderType":"A2000021","title":"向*迎良-收款","tn":"00250001   00049992   2030750829214817"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082921475170F751A3CE2B7D77EBB6C7D88722143F-79809170785248-00250001   00049992   0179610829214750_270","orderStatus":"02","orderTime":"20190829 21:47:51","orderType":"A2000021","title":"向*涛-收款","tn":"00250001   00049992   0179610829214750"},{"amount":"99.96","currencyUnit":"元","orderId":"2019082921473870F751A3CE2B7D77EBB6C7D88722143F-79809170785261-00250001   00049992   1164490829214737_270","orderStatus":"02","orderTime":"20190829 21:47:38","orderType":"A2000021","title":"向*顺豪-收款","tn":"00250001   00049992   1164490829214737"},{"amount":"79.98","currencyUnit":"元","orderId":"2019082921471070F751A3CE2B7D77EBB6C7D88722143F-79809170785289-00250001   00049992   9823280829214709_270","orderStatus":"02","orderTime":"20190829 21:47:10","orderType":"A2000021","title":"向*磊-收款","tn":"00250001   00049992   9823280829214709"},{"amount":"49.98","currencyUnit":"元","orderId":"2019082921470070F751A3CE2B7D77EBB6C7D88722143F-79809170785299-00250001   00049992   9388200829214700_270","orderStatus":"02","orderTime":"20190829 21:47:00","orderType":"A2000021","title":"向*黎明-收款","tn":"00250001   00049992   9388200829214700"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921460170F751A3CE2B7D77EBB6C7D88722143F-79809170785398-00250001   00049992   8658240829214600_270","orderStatus":"02","orderTime":"20190829 21:46:01","orderType":"A2000021","title":"向*全燕-收款","tn":"00250001   00049992   8658240829214600"},{"amount":"199.97","currencyUnit":"元","orderId":"2019082921454370F751A3CE2B7D77EBB6C7D88722143F-79809170785456-00250001   00049992   8190740829214542_270","orderStatus":"02","orderTime":"20190829 21:45:43","orderType":"A2000021","title":"向*春发-收款","tn":"00250001   00049992   8190740829214542"},{"amount":"164.92","currencyUnit":"元","orderId":"2019082921445470F751A3CE2B7D77EBB6C7D88722143F-79809170785545-00250001   00049992   6669510829214453_270","orderStatus":"02","orderTime":"20190829 21:44:54","orderType":"A2000021","title":"向*桂-收款","tn":"00250001   00049992   6669510829214453"},{"amount":"49.97","currencyUnit":"元","orderId":"2019082921445470F751A3CE2B7D77EBB6C7D88722143F-79809170785545-00250001   00049992   6677010829214453_270","orderStatus":"02","orderTime":"20190829 21:44:54","orderType":"A2000021","title":"向*兵-收款","tn":"00250001   00049992   6677010829214453"},{"amount":"44.97","currencyUnit":"元","orderId":"2019082921443270F751A3CE2B7D77EBB6C7D88722143F-79809170785567-00250001   00049992   5993310829214431_270","orderStatus":"02","orderTime":"20190829 21:44:32","orderType":"A2000021","title":"向*寒文-收款","tn":"00250001   00049992   5993310829214431"},{"amount":"57.98","currencyUnit":"元","orderId":"2019082921431870F751A3CE2B7D77EBB6C7D88722143F-79809170785681-00250001   00049992   4143260829214317_270","orderStatus":"02","orderTime":"20190829 21:43:18","orderType":"A2000021","title":"向*芳-收款","tn":"00250001   00049992   4143260829214317"},{"amount":"499.92","currencyUnit":"元","orderId":"2019082921423970F751A3CE2B7D77EBB6C7D88722143F-79809170785760-00250001   00049992   2954430829214238_270","orderStatus":"02","orderTime":"20190829 21:42:39","orderType":"A2000021","title":"向*新-收款","tn":"00250001   00049992   2954430829214238"},{"amount":"35.97","currencyUnit":"元","orderId":"2019082921420570F751A3CE2B7D77EBB6C7D88722143F-79809170785794-00250001   00049992   2160800829214204_270","orderStatus":"02","orderTime":"20190829 21:42:05","orderType":"A2000021","title":"向*舜光-收款","tn":"00250001   00049992   2160800829214204"},{"amount":"30.97","currencyUnit":"元","orderId":"2019082921414770F751A3CE2B7D77EBB6C7D88722143F-79809170785852-00250001   00049992   1543280829214147_270","orderStatus":"02","orderTime":"20190829 21:41:47","orderType":"A2000021","title":"向*强-收款","tn":"00250001   00049992   1543280829214147"},{"amount":"39.98","currencyUnit":"元","orderId":"2019082921411370F751A3CE2B7D77EBB6C7D88722143F-79809170785886-00250001   00049992   9952110829214112_270","orderStatus":"02","orderTime":"20190829 21:41:13","orderType":"A2000021","title":"向*江华-收款","tn":"00250001   00049992   9952110829214112"},{"amount":"199.95","currencyUnit":"元","orderId":"2019082921394770F751A3CE2B7D77EBB6C7D88722143F-79809170786052-00250001   00049992   8324430829213946_270","orderStatus":"02","orderTime":"20190829 21:39:47","orderType":"A2000021","title":"向*国举-收款","tn":"00250001   00049992   8324430829213946"},{"amount":"49.96","currencyUnit":"元","orderId":"2019082921394470F751A3CE2B7D77EBB6C7D88722143F-79809170786055-00250001   00049992   0305870829213943_270","orderStatus":"02","orderTime":"20190829 21:39:44","orderType":"A2000021","title":"向*银-收款","tn":"00250001   00049992   0305870829213943"},{"amount":"999.96","currencyUnit":"元","orderId":"2019082921393370F751A3CE2B7D77EBB6C7D88722143F-79809170786066-00250001   00049992   7762090829213932_270","orderStatus":"02","orderTime":"20190829 21:39:33","orderType":"A2000021","title":"向*玉娇-收款","tn":"00250001   00049992   7762090829213932"}]},"resp":"00"}';
                $re['arg']='{"queryNum":10}';
                $re['uniID']='141921667446637';

                $re=['cls'=>'v3.uniPay.tongzhi' ];
                $re['data']='{"body":{"title":"动账通知","alert":"您尾号为1452的银行卡于26日15时21分入账0.01元"},"info":{"rk":"2541940001091302226-79809073-79809073847956-0926152116-606325-48020000-00049992-0-01$","tp":"001"}}';
                $re['uniID']= '126810127506758';

                $re=['cls'=>'com.pingan.bill','pingAnID'=>'51bedfb69068eb0dd7da259416282663'];
                $re['data']='{"responseCode":"000000","totalNum":"1","transRecordsList":[{"cardMask":"6230***********0567","cardNo":"","cardType":"01","compsiteTime":"2019-09-29 00:22:04","mercode":"001980099990002","mernamec":"银联扫码转账","searialNo":"2810351909291451271188","source":"3","transAmount":"1.0","transCcy":"RMB","transNo":"9693011909290022039333","transResult":"0","transType":"C","vouchernum":"18190929012977390782"}]}';

                $re=['cls'=>'com.pingan.qr','pingAnID'=>'2976faf9f01fdbf4e134686d4e17a000'];
                $re['data']='{"bankCardSign":"2976faf9f01fdbf4e134686d4e17a000","ccy":"RMB","currencyCode":"156","orderNo":"PA20190929310020528071","qrCode":"https://qr.95516.com/00010000/01111634261790929397272399034761","responseCode":"000000","txnAmt":"199","txnAmtSum":"199"}';
                $re['arg']='{"money":"199","remark":"goodnew122","id":"goodnew122"}';


                $re =['cls'=>'v3.uniPay.order.detail'];
                $re['data']= '{"cmd":"order/detail","msg":"","params":{"mchntNm":"","orderDesc":"向*小名-收款","orderDetail":"{\"payUserName\":\"*道荣\",\"payCardInfo\":\"招商银行(0678)\",\"bill_tp\":\"21\",\"collectionCardInfo\":\"农业银行(3373)\",\"postScript\":\"8018456123\",\"walletOrderId\":\"20190511140344291866\",\"voucherNum\":\"67190511506008661961\",\"collectionCard\":\"\",\"business_tp\":\"收款\"}","orderId":"00250001   00049992   0074500511140343","orderStatus":"A000","orderTime":"2019-05-11 14:03:44","pointsAt":"0","totalAmount":"1","transAt":"1"},"resp":"00"}';
                $re['arg']= '{"orderId":"20190511140344B26773F031D8638C1ED1ABB511369085-79809488859655-00250001   00049992   0074500511140343_270","queryNum":10,"order":{"orderType":"A2000021","amount":"0.01","orderTime":"20190511 14:03:44","orderId":"20190511140344B26773F031D8638C1ED1ABB511369085-79809488859655-00250001   00049992   0074500511140343_270","orderStatus":"02","tn":"00250001   00049992   0074500511140343","title":"向*道荣-收款","currencyUnit":"元"}}';
                $re['uniID']='109089136860951';


                $re=["cls"=> "com.b2alipay.qr",
                    "data"=> "{\"orderId\":\"101777e8d7caa2306c48014033NN5814\",\"alipayNo\":\"20191017200040011100810071220700\",\"securityId\":\"web|cashier_ebank_3|1c957b12-d199-4eb2-9f0e-70adef0fde5aRZ24\",\"form_token\":\"a11bfb7820e35216027261864be4aec198e6d8da7246466c9dbc5a5a2f5cd607RZ24\",\"arg\":{\"amount\":\"0.01\",\"remark\":\"Ttud4jowhsed\",\"id\":\"Ttud4jowhsed\",\"bank\":\"HXBANKhxbanknucc103_DEPOSIT_DEBIT_EBANK_XBOX_MODEL\",\"type\":\"B2C_EBANK\"},\"url\":\"https://wlpay.hxb.com.cn:443/epgate/epccDirect\",\"epccGwMsg\":\"PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48cm9vdCB4bWxucz0ibmFtZXNwYWNlX3N0cmluZyI+PE1zZ0hlYWRlcj48U25kRHQ+MjAxOS0xMC0xN1QwMjozNToyMjwvU25kRHQ+PE1zZ1RwPmVwY2MuMjQyLjAwMS4wMTwvTXNnVHA+PElzc3JJZD5HNDAwMDMxMTAwMDAxODwvSXNzcklkPjxEcmN0bj4yMTwvRHJjdG4+PFNpZ25TTj40MDA5NzI1NDA4PC9TaWduU04+PE5jcnB0blNOPjQxODU1NDY5OTc8L05jcnB0blNOPjxEZ3RsRW52bHA+TUlHTUFpQmZwSEZRK0lta2xzNit1QWZ4RTBTUFQ1MmxrNzBRamhsaHFQc0tWWHFjOWdJaEFLR0FmaW1FcU1SblZqMTJVMGtOdktBT1hTNkZ6L3JyNy9KU25TRml6RjQxQkNBUURQVVNzbkwzd0xwNGVQTFNzeStmQWN6WXR1R0YzZTA2dSt1cDBNOHVpd1FqbGZBVUJMR1RYNC9hRE9UUTZXTStHSmRmQVp4L3RqRGcraUl0KzVWd1pVYlhHYU09PC9EZ3RsRW52bHA+PC9Nc2dIZWFkZXI+PE1zZ0JvZHk+PFN5c1J0bkluZj48U3lzUnRuQ2Q+MDAwMDAwMDA8L1N5c1J0bkNkPjxTeXNSdG5EZXNjPjAwMDAwMDAwPC9TeXNSdG5EZXNjPjxTeXNSdG5UbT4yMDE5LTEwLTE3VDAyOjM1OjIyPC9TeXNSdG5UbT48L1N5c1J0bkluZj48Qml6SW5mPjxUcnhDdGd5PjAxMTI8L1RyeEN0Z3k+PFRyeElkPjIwMTkxMDE3MTI0NzI3Nzk4MDAyODExNDAzMDIwMDM8L1RyeElkPjxUcnhBbXQ+Q05ZMC4wMTwvVHJ4QW10PjxUcnhTdGF0dXM+MDI8L1RyeFN0YXR1cz48Qml6U3RzQ2Q+MDAwMDAwMDA8L0JpelN0c0NkPjxCaXpTdHNEZXNjPuaIkOWKnzwvQml6U3RzRGVzYz48UmRyY3RVcmw+aHR0cHM6Ly93bHBheS5oeGIuY29tLmNuOjQ0My9lcGdhdGUvZXBjY0RpcmVjdDwvUmRyY3RVcmw+PC9CaXpJbmY+PFNlYz5kVFVyTHEyRFJCYi9uMFFiMFJLTVM3U21nckhCZ2hqT3RqeGdBNUZmK05LQS9KL3VjZk5ORlYvOWNFYkhCUVoxL1lkOGExTC9ZQlU3U1kxY010SXduYzlpaWRsN3g2MTZzRXZVc3YxUTJqWWtsVXgvUHBYOFE0M3dxaWUxS3ZieTJRcWVjMFNWZmREUVQxNG5KWVpGcUh5M2NaSi85MkR1SHhRdm56TFNnU2VTQnNnVmhYZ0JlVis4RmJGZWRPaWkwMy9QeElIVWdNVUg1bHJvQnVpcEJRZTVFWkxXemNXTUs3M2pad3Fhc1lFcUQzcyswUnIyb0lqM1JXZkhiVUZaV01SeDEzWm1BQk14UWtrbDNLbG9yNllSSDMybVRnbURRWlFaaE9sSzY0Zk9zelJDbFgvTm56VHNtV1ZTc1RoRHk5RDVzUTRmLzZMRG9OamxlN01vYWxDSURPcU5oVkZpbHpjVCtMam02YWcyR1Iyb25PL012UzhXbjdITjVPUDJWem42bUJhdklNN2wrdUhpRHlXVHNzbXZZR2krSjAxRHc1cUxkdU1sM1R6bFg2OTNDYUlhQVNrL21jL0NYakFPZ0h4VWROUlYrNUtUWFZjWUtEdWZzdGJDK3BHeDRXTC9VZjNtNWRDNGppVGJqSHdjQkJOMThJU0ZzYitiV0VmMHh5VjF4RlEvV1NMUVN0SlVRSjAvNmJLL2dybExLZGFTNmpVWHU4QVJUcGhZR0NPdERLZ25nSU1xNm8wK1ptNU11azRrUTgwczZLLzZXY3g3TVI3K3pMRDA2Qk1JaW1PMW1sLzBvQWNrZDFMa00vUnRYMWRIcExUOUFWODd1d3g3SnFROWxIQ3gwak1sRTZDTVEzRVdiU3BBSzVKekV6Q0RaQWYrcWlyU2V1TEVxWUZXV2Q2U0xxYUpBdzdVSS9lVm4rdVBtQmFBWHFWWHF4YjhWbnYvTG1oeHg2VE0rMUwxZXdYWE9sQlVuWkM0alpGUHhEV0pQdjJBVTNJdm9BdFBCemJXVGV6Z09Ba3k1bjh4YThrOFEzSjdhQmtNSGlKeVo3WFZWMkJjU1puemovV3doYnNrRS9DelFwV2lySjFJTWJTWDJmbDR5dDFlaWNNZFRRRVloTUY5Z21LL01UZHlUczVaRWNxOWFsUEpSZHlpWVoxYWRPa2tYR3drTVd0VzRGS2VRbGo0ZEpMQ0RGMCs0bjdLUGp4WjdLRmZ1ZHJWcDBtMmVZTG5QZE1RcmszZ1Y5UEJGWlV1QVdhZGZjN1pzSU9MaWJ1RkNYcit3L1B6blc4aloxNzNhRlR3SXlONUZuV3VJaXRKQ3ZWcU5UQ0lvdWdCOU9aNXM2WEVsMlVoYVdNbmdnMjNaYUVWSTErdnJ3QURpcmM1Y0x1NTJDKzg3SW9nN1Qyb2tSTTE5d2JOcDFPVWdXQVFzRDROc1V6RXhZMXdmVWQyRVZwMVRlT1NmdkVudmRPbFBjYmIrWVpYWXZIWlJSZWl5aXhnVVYyOHZxbkdDVGZyOWdvMVBGbXFrdG9PY0xDT1cxb1B3S2xZOSthcmJkM3pnOU9GSG81UXFjOU15eHhRc2g3Yk5XTmRCeks3NmR1cFlaWXFsSlc1Y1ROeExYZ2tLMGdQZk9VOWpITTR1RTdHSG94cW5mN3o2cFFpZHZCdzlCR3RHbzhFNk44bmhWYXFPNHdTdUVuZzlUbGpUZWZzeTRRK0IycHZhWUUxM2VwbzRLTndORER4WjZQUFdveExiOGRpRytIanFlVk16dnRIRVFCTkVnMHpzTnBPQTFCc3U4emdOb1JxREdHaHo1ZnpIRm1sSkNRVU1KVk9LY3NQWFBVeGVpYTQyKzljaURLT0NtRFdWMU5XVGNpYWVRUVFkMGxqcitIemZmVmNJWVFJZmJObkZzVDFqMEFXVWdHR1VzeDl6RDZDYXhKa2NPb2JjQkVLWFRaa2l1ZkZ3U1pzRE9qQ3dnamtoWmxaNjhPME93eTlDL3NGWmF0eTBYUmZBN3U4a040OGxoTEd1NjE3NVJCaWp1SEI5SHh3SVhxcU05K1FuMHMvV2RlNWpja1VqVDlhcFJWdzRUQXBPWkNZWTV6VXY1RmZ6NllTUzdLbHpZakZLM0s3bStQZmUwVHNNcisxaTdqNWpqUnJDT0VvQ25EekVxK2ptYW1pVG8zdUZsclg2Wm0ycHFGNElDdVJSVDFHVUtOdzwvU2VjPjwvTXNnQm9keT48L3Jvb3Q+DQp7UzpFc3RIM3oyeVVNN1FrWHAxeGJ4WVBYNUUzNHZ6NDUwVk1BMHhPd2hZV1VhUC91M0RERXp1aTRUQjE4cWpJOGY3eXRONHFjeUtmNTltcjQwMERYbE5oN2pjTUNzL1QxZkFROW9GQUhyL21WRHFNTHZXdlZONGZhMlpUb1IxTGpFd1lRU1hOSmZkRmt0cTlvL0NtVzBETFgvQ285UWRQOHlUeGgzRE1lN2djakFuSk84N3M2VDZXdVJSTnlOczMvK2ZxY1h6eXRQYlMycjl2LzNIN0U0YmE5R0xocXlMTGlSTUpOVzJVVTRyWWRWbUU0TDJPdnlGM01PKzkwZkp4YXg4Vld1VjB5NVlBVzUvQkRBRUI0VUhOQUY0amx0OEFuWGJwUUd0VC9Jc2JaTzdmQXFFM1IyYldrTzVOeEtua1FSQyswZ2ZHQ214bWROTUl0c3ZOdDFqb3c9PX0=\"}",
                    "account"=> "17570753971" ];

                $re=["cls"=>"com.b2alipay.bill","account"=> "17570753971",
                    "data"=>"{\"alipayNo\":\"20191016200040011100810070874494\",\"time\":\"2019-10-16 17:00\",\"amount\":\"2.00\",\"buyer\":\"蒋映红\",\"bank\":\"<a href=\\\"https://consumeweb.alipay.com/record/bank/index.htm?cardType=ICBC&cardNo=6457\\\">中国工商银行 (6457)</a>\"}",
                    "arg"=>"{\"alipayNo\":\"20191016200040011100810070874494\",\"stime\":1571250213,\"cnt\":1}"
                ];

                $str= <<<EOF
SyncMessage [userId=2088532035673525, biz=MSG-BILL, msgData=[{"mk":191064170234200001,"st":1,"isSc":0,"appId":"","mct":1571907754000,"pl":"{\"templateType\":\"BN\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/rmsportal\/EMWIWDsKUkuXYdvKDdaZ.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068020463&bizType=D_TRANSFER?tagid=MB_SEND_PUSH_WALLET_NEW\",\"businessId\":\"PAY_HELPER_CARD_2088532035673525\",\"msgId\":\"eaf85efda2bb0bc0a82113154ef44fef56352\",\"templateCode\":\"00059_00094_zfzs001\",\"subscribeConfig\":\"0\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"title\":\"充值-余额充值\",\"gmtCreate\":1571907754994,\"content\":\"{\\\"status\\\":\\\"付款成功\\\",\\\"date\\\":\\\"10月24日\\\",\\\"amountTip\\\":\\\"\\\",\\\"money\\\":\\\"200.00\\\",\\\"unit\\\":\\\"元\\\",\\\"infoTip\\\":\\\"\\\",\\\"failTip\\\":\\\"\\\",\\\"goto\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068020463&bizType=D_TRANSFER\\\",\\\"content\\\":[{\\\"title\\\":\\\"交易对象：\\\",\\\"content\\\":\\\"熊祖勇\\\"},{\\\"title\\\":\\\"商品说明：\\\",\\\"content\\\":\\\"余额充值\\\"}],\\\"ad\\\":[],\\\"actions\\\":[{\\\"name\\\":\\\"\\\",\\\"url\\\":\\\"\\\"},{\\\"name\\\":\\\"查看详情\\\",\\\"url\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068020463&bizType=D_TRANSFER\\\"}]}\",\"linkName\":\"\",\"gmtValid\":1887267754990,\"operate\":\"SEND\",\"bizName\":\"支付助手\",\"templateName\":\"支付助手\",\"homePageTitle\":\"充值-余额充值￥200.00\",\"status\":\"\",\"extraInfo\":\"{     \\\"templateId\\\":\\\"WALLET-FWC@remindDefaultText\\\",     \\\"content\\\":\\\"充值-余额充值\\\",     \\\"assistMsg1\\\":\\\"￥200.00\\\",     \\\"assistName1\\\":\\\"转入金额\\\",     \\\"linkName\\\":\\\"\\\",     \\\"buttonLink\\\":\\\"\\\", }\"}"},{"mk":191064170705200001,"st":1,"isSc":0,"appId":"","mct":1571908025000,"pl":"{\"templateType\":\"BN\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/rmsportal\/EMWIWDsKUkuXYdvKDdaZ.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068038343&bizType=D_TRANSFER?tagid=MB_SEND_PUSH_WALLET_NEW\",\"businessId\":\"PAY_HELPER_CARD_2088532035673525\",\"msgId\":\"8e604e432138c67d6ad8b53fb429ef7556352\",\"templateCode\":\"00059_00094_zfzs001\",\"subscribeConfig\":\"0\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"title\":\"充值-余额充值\",\"gmtCreate\":1571908025106,\"content\":\"{\\\"status\\\":\\\"付款成功\\\",\\\"date\\\":\\\"10月24日\\\",\\\"amountTip\\\":\\\"\\\",\\\"money\\\":\\\"100.00\\\",\\\"unit\\\":\\\"元\\\",\\\"infoTip\\\":\\\"\\\",\\\"failTip\\\":\\\"\\\",\\\"goto\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068038343&bizType=D_TRANSFER\\\",\\\"content\\\":[{\\\"title\\\":\\\"交易对象：\\\",\\\"content\\\":\\\"熊祖勇\\\"},{\\\"title\\\":\\\"商品说明：\\\",\\\"content\\\":\\\"余额充值\\\"}],\\\"ad\\\":[],\\\"actions\\\":[{\\\"name\\\":\\\"\\\",\\\"url\\\":\\\"\\\"},{\\\"name\\\":\\\"查看详情\\\",\\\"url\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068038343&bizType=D_TRANSFER\\\"}]}\",\"linkName\":\"\",\"gmtValid\":1887268025102,\"operate\":\"SEND\",\"bizName\":\"支付助手\",\"templateName\":\"支付助手\",\"homePageTitle\":\"充值-余额充值￥100.00\",\"status\":\"\",\"extraInfo\":\"{     \\\"templateId\\\":\\\"WALLET-FWC@remindDefaultText\\\",     \\\"content\\\":\\\"充值-余额充值\\\",     \\\"assistMsg1\\\":\\\"￥100.00\\\",     \\\"assistName1\\\":\\\"转入金额\\\",     \\\"linkName\\\":\\\"\\\",     \\\"buttonLink\\\":\\\"\\\", }\"}"},{"mk":191064172002200001,"st":1,"isSc":0,"appId":"","mct":1571908802000,"pl":"{\"templateType\":\"BN\",\"commandType\":\"UPDATE\",\"expireLink\":\"\",\"msgType\":\"NOTICE\",\"icon\":\"https:\/\/gw.alipayobjects.com\/zos\/rmsportal\/EMWIWDsKUkuXYdvKDdaZ.png\",\"link\":\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068067602&bizType=D_TRANSFER?tagid=MB_SEND_PUSH_WALLET_NEW\",\"businessId\":\"PAY_HELPER_CARD_2088532035673525\",\"msgId\":\"b61efb56c67d79720bc387a10248975856352\",\"templateCode\":\"00059_00094_zfzs001\",\"subscribeConfig\":\"0\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"title\":\"充值-余额充值\",\"gmtCreate\":1571908802018,\"content\":\"{\\\"status\\\":\\\"付款成功\\\",\\\"date\\\":\\\"10月24日\\\",\\\"amountTip\\\":\\\"\\\",\\\"money\\\":\\\"100.00\\\",\\\"unit\\\":\\\"元\\\",\\\"infoTip\\\":\\\"\\\",\\\"failTip\\\":\\\"\\\",\\\"goto\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068067602&bizType=D_TRANSFER\\\",\\\"content\\\":[{\\\"title\\\":\\\"交易对象：\\\",\\\"content\\\":\\\"熊祖勇\\\"},{\\\"title\\\":\\\"商品说明：\\\",\\\"content\\\":\\\"余额充值\\\"}],\\\"ad\\\":[],\\\"actions\\\":[{\\\"name\\\":\\\"\\\",\\\"url\\\":\\\"\\\"},{\\\"name\\\":\\\"查看详情\\\",\\\"url\\\":\\\"alipays:\/\/platformapi\/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191024200040011100520068067602&bizType=D_TRANSFER\\\"}]}\",\"linkName\":\"\",\"gmtValid\":1887268802013,\"operate\":\"SEND\",\"bizName\":\"支付助手\",\"templateName\":\"支付助手\",\"homePageTitle\":\"充值-余额充值￥100.00\",\"status\":\"\",\"extraInfo\":\"{     \\\"templateId\\\":\\\"WALLET-FWC@remindDefaultText\\\",     \\\"content\\\":\\\"充值-余额充值\\\",     \\\"assistMsg1\\\":\\\"￥100.00\\\",     \\\"assistName1\\\":\\\"转入金额\\\",     \\\"linkName\\\":\\\"\\\",     \\\"buttonLink\\\":\\\"\\\", }\"}"}], pushData=, id=49,191064172002200001,1, hasMore=false], sOpcode=0]
EOF;

                $re = ['cls'=>'com.alipay.android.phone.messageboxstatic.biz.sync.d','data'=> $str ,'userId'=>'2088532035673525'] ;



                $str= <<<EOF
ServiceReminderRecord{msgId='d39bac5a49ba77f865050c14eb2af08756352', operate='UPDATE', templateType='BN', templateId='WALLET-BILL@BLPaymentHelper', msgType='NOTICE', title='支付助手', content='{     "templateId":"WALLET-FWC@remindDefaultText",     "content":"充值-余额充值",     "assistMsg1":"￥50.00",     "assistName1":"转入金额",     "linkName":"",     "buttonLink":"", }', icon='https://gw.alipayobjects.com/zos/rmsportal/EMWIWDsKUkuXYdvKDdaZ.png', link='alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191025200040011100520068746601&bizType=D_TRANSFER?tagid=MB_SEND_PUSH_WALLET_NEW', linkName='', templateCode='00059_00094_zfzs001', gmtCreate=1571997882278, gmtValid=1887357882273, homePageTitle='充值-余额充值￥50.00', statusFlag='null', status='', businessId='PAY_HELPER_CARD_2088532035673525', expireLink='', templateName='支付助手', menus='null', extraInfo='{"actions":[{"name":"","url":""},{"name":"查看详情","url":"alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191025200040011100520068746601&bizType=D_TRANSFER"}],"ad":[],"amountTip":"","bizMonitor":"{\"businessId\":\"PAY_HELPER_CARD_2088532035673525\",\"expireLink\":\"\",\"gmtCreate\":1571997882278,\"gmtValid\":1887357882273,\"hiddenSum\":\"0\",\"homePageTitle\":\"充值-余额充值￥50.00\",\"icon\":\"https://gw.alipayobjects.com/zos/rmsportal/EMWIWDsKUkuXYdvKDdaZ.png\",\"id\":\"d39bac5a49ba77f865050c14eb2af0875635200059_00094_zfzs0012088532035673525\",\"link\":\"alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191025200040011100520068746601&bizType=D_TRANSFER?tagid=MB_SEND_PUSH_WALLET_NEW\",\"linkName\":\"\",\"msgId\":\"d39bac5a49ba77f865050c14eb2af08756352\",\"msgType\":\"NOTICE\",\"operate\":\"UPDATE\",\"status\":\"\",\"templateCode\":\"00059_00094_zfzs001\",\"templateId\":\"WALLET-BILL@BLPaymentHelper\",\"templateName\":\"支付助手\",\"templateType\":\"BN\",\"title\":\"支付助手\",\"userId\":\"2088532035673525\"}","content":[{"content":"熊祖勇","title":"交易对象："},{"content":"余额充值","title":"商品说明："}],"date":"10月25日","failTip":"","goto":"alipays://platformapi/startapp?appId=20000003&actionType=toBillDetails&tradeNO=20191025200040011100520068746601&bizType=D_TRANSFER","infoTip":"","money":"50.00","status":"付款成功","unit":"元"}', msgState='null', userId='2088532035673525'}
EOF;

                $re = ['cls'=>'com.alipay.android.phone.messageboxstatic.biz.dao.TradeDao','data'=> $str ,'userId'=>'2088532035673525'] ;

                $re=['cls'=>'org.myapp.wx.bill.receive','wxID'=>'l635241m' ];
                $re['content']= <<<EOF
<msg> <appmsg appid="" sdkver="0"> 	<title><![CDATA[手机号收款到账通知]]></title> 	<des><![CDATA[收款金额￥0.11付款方兔兔收款方式手机号（181****1999）备注收款成功，已存入零钱。点击可查看详情]]></des> 	<action></action> 	<type>5</type> 	<showtype>1</showtype>     <soundtype>0</soundtype> 	<content><![CDATA[]]></content> 	<contentattr>0</contentattr> 	<url><![CDATA[https://wx.tenpay.com/userroll/readtemplate?t=userroll/index_tmpl&type=userrolldetail&trans_id=18000082622019102801010011105024&create_time=1572266219]]></url> 	<lowurl><![CDATA[]]></lowurl> 	<appattach> 		<totallen>0</totallen> 		<attachid></attachid> 		<fileext></fileext> 		<cdnthumburl><![CDATA[]]></cdnthumburl> 		<cdnthumbaeskey><![CDATA[]]></cdnthumbaeskey> 		<aeskey><![CDATA[]]></aeskey> 	</appattach> 	<extinfo></extinfo> 	<sourceusername><![CDATA[]]></sourceusername> 	<sourcedisplayname><![CDATA[]]></sourcedisplayname> 	<mmreader> 		<category type="0" count="1"> 			<name><![CDATA[微信支付]]></name> 			<topnew> 				<cover><![CDATA[]]></cover> 				<width>0</width> 				<height>0</height> 				<digest><![CDATA[收款金额￥0.11付款方兔兔收款方式手机号（181****1999）备注收款成功，已存入零钱。点击可查看详情]]></digest> 			</topnew> 				<item> 	<itemshowtype>4</itemshowtype> 	<title><![CDATA[手机号收款到账通知]]></title> 	<url><![CDATA[https://wx.tenpay.com/userroll/readtemplate?t=userroll/index_tmpl&type=userrolldetail&trans_id=18000082622019102801010011105024&create_time=1572266219]]></url> 	<shorturl><![CDATA[]]></shorturl> 	<longurl><![CDATA[]]></longurl> 	<pub_time>1572266220</pub_time> 	<cover><![CDATA[]]></cover> 	<tweetid></tweetid> 	<digest><![CDATA[收款金额￥0.11付款方兔兔收款方式手机号（181****1999）备注收款成功，已存入零钱。点击可查看详情]]></digest> 	<fileid>0</fileid> 	<sources> 	<source> 	<name><![CDATA[微信支付]]></name> 	</source> 	</sources> 	<styles><topColor><![CDATA[]]></topColor><style><range><![CDATA[{4,5}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{13,2}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{20,16}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style><style><range><![CDATA[{39,18}]]></range><font><![CDATA[s]]></font><color><![CDATA[#000000]]></color></style></styles>	<native_url></native_url>    <del_flag>0</del_flag>     <contentattr>0</contentattr>     <play_length>0</play_length> 	<play_url><![CDATA[]]></play_url> 	<player><![CDATA[]]></player> 	<template_op_type>0</template_op_type> 	<weapp_username><![CDATA[]]></weapp_username> 	<weapp_path><![CDATA[]]></weapp_path> 	<weapp_version>0</weapp_version> 	<weapp_state>0</weapp_state>     <music_source>0</music_source>     <pic_num>0</pic_num> 	<show_complaint_button>0</show_complaint_button> 	<vid><![CDATA[]]></vid> 	<recommendation><![CDATA[]]></recommendation> 	<pic_urls></pic_urls>	<comment_topic_id>0</comment_topic_id>	<cover_235_1><![CDATA[]]></cover_235_1> 	<cover_1_1><![CDATA[]]></cover_1_1>     <appmsg_like_type>0</appmsg_like_type>     <video_width>0</video_width>     <video_height>0</video_height>     <is_pay_subscribe>0</is_pay_subscribe> 	</item> 		</category> 		<publisher> 			<username><![CDATA[wxzhifu]]></username> 			<nickname><![CDATA[微信支付]]></nickname> 		</publisher> 		<template_header><title><![CDATA[手机号收款到账通知]]></title><title_color><![CDATA[]]></title_color><pub_time>1572266220</pub_time><first_data><![CDATA[]]></first_data><first_color><![CDATA[]]></first_color><hide_title_and_time>1</hide_title_and_time><show_icon_and_display_name>0</show_icon_and_display_name><display_name><![CDATA[]]></display_name><icon_url><![CDATA[]]></icon_url><hide_icon_and_display_name_line>1</hide_icon_and_display_name_line><header_jump_url><![CDATA[]]></header_jump_url><shortcut_icon_url><![CDATA[]]></shortcut_icon_url><ignore_hide_title_and_time>1</ignore_hide_title_and_time><hide_time>1</hide_time><pay_style>1</pay_style></template_header> 		<template_detail><template_show_type>1</template_show_type><text_content><cover><![CDATA[]]></cover><text><![CDATA[]]></text><color><![CDATA[]]></color></text_content><line_content><topline><key><word><![CDATA[收款金额]]></word><color><![CDATA[#888888]]></color><hide_dash_line>1</hide_dash_line></key><value><word><![CDATA[￥0.11]]></word><color><![CDATA[#000000]]></color><small_text_count>1</small_text_count></value></topline><lines><line><key><word><![CDATA[付款方]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[兔兔]]></word><color><![CDATA[#000000]]></color></value></line><line><key><word><![CDATA[收款方式]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[手机号（181****1999）]]></word><color><![CDATA[#000000]]></color></value></line><line><key><word><![CDATA[备注]]></word><color><![CDATA[#888888]]></color></key><value><word><![CDATA[收款成功，已存入零钱。点击可查看详情]]></word><color><![CDATA[#000000]]></color></value></line></lines></line_content><opitems><opitem><word><![CDATA[查看账单详情]]></word><url><![CDATA[https://wx.tenpay.com/userroll/readtemplate?t=userroll/index_tmpl&type=userrolldetail&trans_id=18000082622019102801010011105024&create_time=1572266219]]></url><icon><![CDATA[]]></icon><color><![CDATA[#000000]]></color><weapp_username><![CDATA[]]></weapp_username><weapp_path><![CDATA[]]></weapp_path><op_type>0</op_type><weapp_version>0</weapp_version><weapp_state>0</weapp_state><hint_word><![CDATA[]]></hint_word><is_rich_text>0</is_rich_text><display_line_number>0</display_line_number></opitem><show_type>1</show_type></opitems></template_detail> 	    <forbid_forward>0</forbid_forward> 	</mmreader> 	<thumburl><![CDATA[]]></thumburl> 	     <template_id><![CDATA[OqDMc7J-BuyL3PptuTuGTHEAAYOZT8_6y-hkAP-e6XM]]></template_id>                          	 </appmsg><fromusername><![CDATA[gh_3dfda90e39d6]]></fromusername><appinfo><version>0</version><appname><![CDATA[微信支付]]></appname><isforceupdate>1</isforceupdate></appinfo></msg>   
EOF;

                $re=['cls'=>'myapp.v13.getAliBillList',
                    'data'=>'[{"actionParam":{"autoJumpUrl":"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20191111200040011100960084467163%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES","serializedSize":203,"type":"APP","unknownFieldsSerializedSize":0},"bizInNo":"20191111200040011100960084467163","bizSubType":"1100","bizType":"D_TRANSFER","canDelete":true,"categoryName":"转账充值","consumeFee":"1.00","consumeStatus":"2","consumeTitle":"余额充值","contentRender":0,"createDesc":"今天","createTime":"21:29","gmtCreate":1573478977000,"isAggregatedRec":false,"oppositeLogo":"https://gw.alipayobjects.com/zos/mwalletmng/fCzAdjpWtkcHUpBKrhlJ.png","recordType":"CONSUME","serializedSize":422,"tagStatus":0,"unknownFieldsSerializedSize":20}]',
                    'arg'=>'{"from":""}', //bu90
                    'dt'=>'1573480082117',
                    'userId'=>'2088632061595964'];

                $re =['cls'=>'v3.uniPay.order.detail'];
                $re['data']= '{"cmd":"order/detail","msg":"","params":{"mchntNm":"","orderDesc":"向*道荣-收款","orderDetail":"{\"payUserName\":\"*道荣\",\"payCardInfo\":\"招商银行(0678)\",\"bill_tp\":\"21\",\"collectionCardInfo\":\"中国银行(3182)\",\"postScript\":\"收款\",\"walletOrderId\":\"20191114210541151716\",\"voucherNum\":\"85191114759149103302\",\"collectionCard\":\"\",\"business_tp\":\"收款\"}","orderId":"03080000   00049992   0004561114210541","orderStatus":"A000","orderTime":"2019-11-14 21:05:41","pointsAt":"0","totalAmount":"50","transAt":"50"},"resp":"00"}';
                $re['arg']= '{"orderId":"20191114210541194825191-4011-79808885789458-03080000   00049992   0004561114210541_274","queryNum":10,"order":{"orderType":"A2000021","amount":"0.50","orderTime":"20191114 21:05:41","orderId":"20191114210541194825191-4011-79808885789458-03080000   00049992   0004561114210541_274","orderStatus":"02","tn":"03080000   00049992   0004561114210541","title":"向*道荣-收款","currencyUnit":"元"}}';
                $re['uniID']='151041756261913';


                $re =['cls'=>'v3.uniPay.bigData.detail'];
                //$re['data']= '{"cmd":"bigData/detail","msg":"","params":{"acquirer":"招商银行","appleOrderIdFlag":"","authNo":"","batchNo":"914910","cardAss":"","comment":"","deductAt":"","drawIcon":"dztz1107.png","drawList":[{"drawURL":"https://wallet.95516.com/s/wl/webV3/activity/fifteenctoc/html/inviteIndex2.html?code=ctoc00000000082","iconURL":"dztz1107.png"}],"drawUrl":"https://wallet.95516.com/s/wl/webV3/activity/fifteenctoc/html/inviteIndex2.html?code=ctoc00000000082","faceRecognitionPayFlag":"","faceRecognitionPayOrderId":"","imgPath":"","inOutType":"02","issuer":"中行北京分行","mchntAddr":"","mchntCd":"001980099990002","mchntClass":"020301","mchntClassNm":"其他收入-转账存款","mchntNm":"银联扫码转账","mchntNo":"001980099990002","orderAt":"","payFlag":"","payerCardNo":"招商银行 6214****0678","payerNm":"杨道荣                        ","postscript":"","reference":"210541000456","remark":"","rowKey":"2813551000010097126-79808885-79808885789485-1114210541-000456-03080000-00049992-0-01$","symbol":"01","terminalNo":"10000001","transAcc":"d851c2b61206026e056bb13b6dfd64282a5d1729d632ffaf","transAmount":"50","transAtForCurrCd":"","transChannelName":"网上","transCurrCdNm":"","transCurrCode":"156","transTime":"2019-11-14 21:05:14","transType":"入账","voucher":"330220","x":"","y":""},"resp":"00"}';
                $re['data']= '{"cmd":"bigData/detail","msg":"","params":{"acquirer":"","appleOrderIdFlag":"","authNo":"","batchNo":"753343","cardAss":"","comment":"","deductAt":"","drawIcon":"dztz1107.png","drawList":[{"drawURL":"https://wallet.95516.com/s/wl/webV3/activity/fifteenctoc/html/inviteIndex2.html?code=ctoc00000000082","iconURL":"dztz1107.png"}],"drawUrl":"https://wallet.95516.com/s/wl/webV3/activity/fifteenctoc/html/inviteIndex2.html?code=ctoc00000000082","faceRecognitionPayFlag":"","faceRecognitionPayOrderId":"","imgPath":"","inOutType":"02","issuer":"工商银行","mchntAddr":"","mchntCd":"001980099990002","mchntClass":"020301","mchntClassNm":"其他收入-转账存款","mchntNm":"银联扫码转账","mchntNo":"001980099990002","orderAt":"","payFlag":"","payerCardNo":"招商银行 6214****1629","payerNm":"田雷                          ","postscript":"cXEzMDQ1Njc=","reference":"205541402574","remark":"","rowKey":"7546171001091955126-79808883-79808883794465-1116205541-402574-88020005-00049992-0-01$","symbol":"01","terminalNo":"10000001","transAcc":"e8ad372c308dfea3acaa3c7a7a91e429176a52da60b32c25","transAmount":"3","transAtForCurrCd":"","transChannelName":"网上","transCurrCdNm":"","transCurrCode":"156","transTime":"2019-11-16 20:55:34","transType":"入账","voucher":"989431","x":"","y":""},"resp":"00"}';
                $re['uniID']='151041756261913';

                /*
                $re =['cls'=>'v3.uniPay.bigData.list'];
                $re['data']= '{"cmd":"bigData/records","msg":"","params":{"currentTime":"20191115","hasNext":"0","hasNextDate":"201911","transRecordsList":[{"canDraw":"","cloudPay":"1","inOutType":"02","key":"2813551000010097126-79808884-79808884844294-1115155716-641945-03080000-00049992-0-01$","mchntAdd":"","mchntClass":"020301","mchntClassNm":"转账存款","mchntNm":"银联扫码转账","notesClassNm":"资金往来","recordType":"01","symbol":"01","transAcc":"d851c2b61206026e056bb13b6dfd64282a5d1729d632ffaf","transAmount":"1","transSt":"00","transTime":"20191115","virtualTp":"00"},{"canDraw":"","cloudPay":"1","inOutType":"02","key":"2813551000010097126-79808884-79808884895497-1115104605-836198-03080000-00049992-0-01$","mchntAdd":"","mchntClass":"020301","mchntClassNm":"转账存款","mchntNm":"银联扫码转账","notesClassNm":"资金往来","recordType":"01","symbol":"01","transAcc":"d851c2b61206026e056bb13b6dfd64282a5d1729d632ffaf","transAmount":"1","transSt":"00","transTime":"20191115","virtualTp":"00"},{"canDraw":"","cloudPay":"0","inOutType":"02","key":"2813551000010097126-79808885-79808885789485-1114210541-000456-03080000-00049992-0-01$","mchntAdd":"","mchntClass":"020301","mchntClassNm":"转账存款","mchntNm":"银联扫码转账","notesClassNm":"资金往来","recordType":"01","symbol":"01","transAcc":"d851c2b61206026e056bb13b6dfd64282a5d1729d632ffaf","transAmount":"50","transSt":"00","transTime":"20191114","virtualTp":"00"}]},"resp":"00"}';
                $re['uniID']='151041756261913';
                */

                $re=[ "cls"=> "org.myapp.wx.qun.qr", "qr"=> "https://weixin.qq.com/g/AzMyToln9yPt3Uip", "chatroom"=> "24097739083@chatroom", "wxID"=> "asw1608" ];

                $re= [
                    "cls"=>"org.myapp.wx.hongbao.pick",
                    //"data"=>"{\"retcode\":0,\"retmsg\":\"ok\",\"sendId\":\"1000039401201912087008901267827\",\"amount\":1,\"recNum\":1,\"recAmount\":1,\"totalNum\":1,\"totalAmount\":1,\"hasWriteAnswer\":0,\"hbType\":1,\"isSender\":0,\"isContinue\":0,\"receiveStatus\":2,\"hbStatus\":4,\"statusMess\":\"\",\"wishing\":\"恭喜发财，大吉大利\",\"receiveId\":\"1000039401000912087008901267045\",\"headTitle\":\"1个红包，2秒被抢光\",\"canShare\":0,\"operationHeader\":[],\"record\":[{\"receiveAmount\":1,\"receiveTime\":\"1575793118\",\"answer\":\"\",\"receiveId\":\"1000039401000912087008901267045\",\"state\":1,\"gameTips\":\"手气最佳\",\"receiveOpenId\":\"wxid_h3gbqpkswn5d22\",\"userName\":\"wxid_h3gbqpkswn5d22\"}],\"watermark\":\"\",\"jumpChange\":1,\"changeWording\":\"已存入零钱，可用于发红包\",\"sendUserName\":\"dooy520\",\"changeUrl\":\"weixin:\\/\\/wxpay\\/change\",\"real_name_info\":{\"guide_flag\":0},\"SystemMsgContext\":\"<img src=\\\"SystemMessages_HongbaoIcon.png\\\"\\/>  你领取了$dooy520$的<_wc_custom_link_ color=\\\"#FD9931\\\" href=\\\"weixin:\\/\\/weixinhongbao\\/opendetail?sendid=1000039401201912087008901267827&sign=b58d4aecbdf5842df2a88166294591e0a32834a58296857e811072ca95f49fbbe5de0d43f61821ddd57c5032ca46f2be17578ab45781c61819a5d992f7b67c65d8c42aa4bfdfac7789dc1176e23cf353&ver=6\\\">红包<\\/_wc_custom_link_>\",\"sessionUserName\":\"22192561379@chatroom\",\"jumpChangeType\":1,\"changeIconUrl\":\"\",\"expression_md5\":\"\",\"expression_type\":0,\"showYearExpression\":1,\"showOpenNormalExpression\":1,\"enableAnswerByExpression\":1,\"enableAnswerBySelfie\":0}",
                    "data"=>"{\"retcode\":0,\"retmsg\":\"ok\",\"sendId\":\"1000039401201912086017358837652\",\"amount\":0,\"recNum\":1,\"recAmount\":1000,\"totalNum\":1,\"totalAmount\":1000,\"hasWriteAnswer\":0,\"hbType\":1,\"isSender\":0,\"isContinue\":0,\"receiveStatus\":0,\"hbStatus\":4,\"statusMess\":\"手慢了，红包派完了\",\"wishing\":\"恭喜发财，大吉大利\",\"receiveId\":\"\",\"headTitle\":\"1个红包，1秒被抢光\",\"canShare\":0,\"operationHeader\":[],\"record\":[],\"watermark\":\"\",\"jumpChange\":1,\"changeWording\":\"已存入零钱，可直接转账\",\"externMess\":\"\",\"sendUserName\":\"wxid_gxl22c9hf5dv22\",\"changeUrl\":\"weixin:\/\/wxpay\/change\",\"real_name_info\":{\"guide_flag\":0},\"sessionUserName\":\"19337677371@chatroom\",\"jumpChangeType\":1,\"changeIconUrl\":\"\",\"expression_md5\":\"\",\"expression_type\":0,\"showYearExpression\":1,\"showOpenNormalExpression\":1,\"enableAnswerByExpression\":1,\"enableAnswerBySelfie\":0}",
                    "arg"=>"{\"msg\":{\"appmsg\":{\"des\":\"我给你发了一个红包，赶紧去拆!\",\"wcpayinfo\":{\"receivertitle\":\"恭喜发财，大吉大利\",\"paymsgid\":\"1000039401201912087008901267827\",\"sendertitle\":\"恭喜发财，大吉大利\",\"innertype\":\"0\",\"templateid\":\"7a2a165d31da7fce6dd77e05c300028a\",\"content\":\"\\n\\t\\t\",\"url\":\"https://wxapp.tenpay.com/mmpayhb/wxhb_personalreceive?showwxpaytitle=1&msgtype=1&channelid=1&sendid=1000039401201912087008901267827&ver=6&sign=b58d4aecbdf5842df2a88166294591e0a32834a58296857e811072ca95f49fbbe5de0d43f61821ddd57c5032ca46f2be17578ab45781c61819a5d992f7b67c65d8c42aa4bfdfac7789dc1176e23cf353\",\"receiverdes\":\"领取红包\",\"senderdes\":\"查看红包\",\"iconurl\":\"https://wx.gtimg.com/hongbao/1800/hb.png\",\"sceneid\":\"1002\",\"invalidtime\":\"1575879516\",\"scenetext\":[{\"content\":\"微信红包\"},{\"content\":\"微信红包\"}],\"locallogoicon\":\"c2c_hongbao_icon_cn\",\"nativeurl\":\"wxpay://c2cbizmessagehandler/hongbao/receivehongbao?msgtype=1&channelid=1&sendid=1000039401201912087008901267827&sendusername=dooy520&ver=6&sign=b58d4aecbdf5842df2a88166294591e0a32834a58296857e811072ca95f49fbbe5de0d43f61821ddd57c5032ca46f2be17578ab45781c61819a5d992f7b67c65d8c42aa4bfdfac7789dc1176e23cf353\",\"broaden\":\"\"},\"appid\":\"\",\"sdkver\":\"\",\"type\":\"2001\",\"title\":\"微信红包\",\"thumburl\":\"https://wx.gtimg.com/hongbao/1800/hb.png\",\"content\":\"\\n\\t\",\"url\":\"https://wxapp.tenpay.com/mmpayhb/wxhb_personalreceive?showwxpaytitle=1&msgtype=1&channelid=1&sendid=1000039401201912087008901267827&ver=6&sign=b58d4aecbdf5842df2a88166294591e0a32834a58296857e811072ca95f49fbbe5de0d43f61821ddd57c5032ca46f2be17578ab45781c61819a5d992f7b67c65d8c42aa4bfdfac7789dc1176e23cf353\"},\"fromusername\":\"dooy520\",\"content\":\"\\n\\t\"},\"talker\":\"22192561379@chatroom\",\"timingIdentifier\":\"B762D563E4371B17F1E66A24F5A884A0\"}",
                    "version"=>"V2.2.2",
                    "wxID"=>"as123654777"
                ];

                $re=[
                    "cls"=> "org.myapp.wx.qun.memberlist",
                    "memberlist"=> "",
                    "chatroom"=> "19321594878@chatroom",
                    "version"=> "V2.2.2",
                    "wxID"=> "zenghang584520"
                ];


                /*
                $re=["cls"=>"org.myapp.wx.qun.join",
                    "guid"=>"24097739083@chatroom:",
                    "content"=>"{\"sysmsg\":{\"sysmsgtemplate\":{\"content_template\":{\"template\":\"\\\"$adder$\\\"通过扫描你分享的二维码加入群聊  $revoke$\",\"plain\":\"\",\"link_list\":{\"link\":[{\"memberlist\":{\"member\":{\"nickname\":\"HiGo\",\"content\":\"\\n\\t\\t\\t\\t\\t\\t\",\"username\":\"dooy520\"},\"content\":\"\\n\\t\\t\\t\\t\\t\"},\"name\":\"adder\",\"type\":\"link_profile\",\"content\":\"\\n\\t\\t\\t\\t\"},{\"hidden\":\"1\",\"qrcode\":\"http://weixin.qq.com/g/AzMyToln9yPt3Uip\",\"name\":\"revoke\",\"type\":\"link_revoke_qrcode\",\"title\":\"撤销\",\"content\":\"\\n\\t\\t\\t\\t\",\"username\":\"dooy520\"}],\"content\":\"\\n\\t\\t\\t\"},\"type\":\"tmpl_type_profilewithrevokeqrcode\",\"content\":\"\\n\\t\\t\"},\"content\":\"\\n\\t\"},\"type\":\"sysmsgtemplate\",\"content\":\"\\n\\t\"}}",
                    "wxID"=>"asw1608"];
                 */

                $re=[
                    "cmd"=>"wb.bill",
                    "h5"=> "_h5_from=102003; _T_WM=51783772242; from=102003; HTTP_USER_AGENT_WEIBO=Meizu-M1813__weibo__9.12.0__android__android8.1.0; M_WEIBOCN_PARAMS=from%3D102003%26lfid%3D102803%26luicode%3D20000174%26uicode%3D20000174; MLOGIN=1; SCF=Aolq86djioX566BnojEhx-4cjy-hxEUNNVmjrS36tN-9Lx7tlUqawAjUUjOPdHd3IU63B16En1smyPBImMWXfig.; SSOLoginState=1575908793; SUB=_2A25w6gXpDeRhGedI71YR9CjJyD6IHXVQFKuhrDV6PUJbkdANLVD4kW1NV3AdCGYjliew4S5fqdoyGVn2If2p7CdZ; SUHB=0HTgz7eGeePJJM; WEIBOCN_FROM=1110003030; CONTENT-HONGBAO-G0=dc13680feed1518dda4bcc695a1fa9c9",
                    "app"=> "_s_tentry=-; ALF=1578500793; Apache=6719515797316.056.1575873591492; SCF=AmDOx4KwN-l7-sqJFrb35gZUA74w_ONHeyKGtm9Giob_GvjAgGoboFU6k7yYCCCSC4Kp712ShRPb8Ubifoq9q70.; SINAGLOBAL=3973782236673.7886.1533543040699; SSOLoginState=1575896593; SUB=_2A25w6gXpDeRhGedI71YR9CjJyD6IHXVQFKuhrDV8PUJbkNBeLVbwkW1NV3AdCFGK9TT9tenFs13gw7LJP2tkSHgT; SUBP=0033WrSXqPxfM725Ws9jqgMF55529P9D9WF0dOaNDJpAjOy53j3UE7Z55JpX5oz75NHD95QpSoBXehBcSKeEWs4DqcjCi--4iKnpiK.0i--RiKy2i-20; SUHB=0jBIAdGAyBjD9P; ULV=1575873591600:54:8:1:6719515797316.056.1575873591492:1575689723480; UOR=ent.ifeng.com,widget.weibo.com,login.sina.com.cn; webim_unReadCount=%7B%22time%22%3A1575893437846%2C%22dm_pub_total%22%3A0%2C%22chat_group_client%22%3A0%2C%22allcountNum%22%3A9%2C%22msgbox%22%3A0%7D; wvr=6; SC-EVENT-G0=17736b3ddf92e2cc96c6f394b8063789",
                    "data"=> "{\"amount\":\"1.11\",\"beizhu\":\"T1211095431500\",\"gid\":\"4446667619035347\",\"count\":1}",
                    "aid"=> "37",
                    "rz"=> "{\"url\":\"https:\\/\\/pay.sc.weibo.com\\/api\\/merchant\\/pay\\/cashier?sign_type=RSA&sign=SrXyoAHoilLWUdk9JMQ8xVA2P788QxNa%2F10hNtItRll4jfFs4Bx4Rs3ewMf5Q7dMH9D0rVSnKaHRYygi8pBogL%2BHZdAaGS5DEfjXV1iRn%2FYS4YO0xeLUHBH9JsPcF7RVhyeiii7mNkzUksa996I%2BbRC%2BWMOJu430wYEWhmPbgyw%3D&seller_id=5136362277&appkey=743219212&out_pay_id=6000062426967&notify_url=https%3A%2F%2Fhb.e.weibo.com%2Fv2%2Fbonus%2Fpay%2Fwnotify&return_url=https%3A%2F%2Fhb.e.weibo.com%2Fv2%2Fbonus%2Fpay%2Fwreturn%3Fsinainternalbrowser%3Dtopnav&subject=%E5%BE%AE%E5%8D%9A%E7%BA%A2%E5%8C%85&body=&total_amount=111&cfg_follow_uid=5136362277&cfg_share_opt=0&cfg_follow_opt=0\",\"body\":\"Cgk8Zm9ybSBhY3Rpb249Imh0dHBzOi8vb3BlbmFwaS5hbGlwYXkuY29tL2dhdGV3YXkuZG8\\/Y2hhcnNldD1VVEYtOCIgbWV0aG9kPSJQT1NUIiBpZD0iYWxpcGF5Rm9ybSI+Cgk8aW5wdXQgbmFtZT0iYXBwX2lkIiB0eXBlPSJoaWRkZW4iIHZhbHVlPSIyMDE2MDEwNDAxMDYyNjE0Ii8+Cgk8aW5wdXQgbmFtZT0ibWV0aG9kIiB0eXBlPSJoaWRkZW4iIHZhbHVlPSJhbGlwYXkudHJhZGUud2FwLnBheSIvPgoJPGlucHV0IG5hbWU9ImZvcm1hdCIgdHlwZT0iaGlkZGVuIiB2YWx1ZT0iSlNPTiIvPgoJPGlucHV0IG5hbWU9InJldHVybl91cmwiIHR5cGU9ImhpZGRlbiIgdmFsdWU9Imh0dHBzOi8vcGF5LnNjLndlaWJvLmNvbS9jaGFyZ2Uvd2FwL3JldHVybjIiLz4KCTxpbnB1dCBuYW1lPSJjaGFyc2V0IiB0eXBlPSJoaWRkZW4iIHZhbHVlPSJVVEYtOCIvPgoJPGlucHV0IG5hbWU9InNpZ25fdHlwZSIgdHlwZT0iaGlkZGVuIiB2YWx1ZT0iUlNBMiIvPgoJPGlucHV0IG5hbWU9InRpbWVzdGFtcCIgdHlwZT0iaGlkZGVuIiB2YWx1ZT0iMjAxOS0xMi0xMSAxMDo1OTo1MyIvPgoJPGlucHV0IG5hbWU9InZlcnNpb24iIHR5cGU9ImhpZGRlbiIgdmFsdWU9IjEuMCIvPgoJPGlucHV0IG5hbWU9Im5vdGlmeV91cmwiIHR5cGU9ImhpZGRlbiIgdmFsdWU9Imh0dHBzOi8vcGF5LnNjLndlaWJvLmNvbS9hcGkvYWxpcGF5L3dhcC9ub3RpZnkyIi8+Cgk8aW5wdXQgbmFtZT0iYml6X2NvbnRlbnQiIHR5cGU9ImhpZGRlbiIgdmFsdWU9InsmcXVvdDtzdWJqZWN0JnF1b3Q7OiZxdW90O+W+ruWNmue6ouWMhSZxdW90OywmcXVvdDtvdXRfdHJhZGVfbm8mcXVvdDs6JnF1b3Q7MTA0NDQ4MjY3OTc1MTA0MzYyJnF1b3Q7LCZxdW90O3RpbWVvdXRfZXhwcmVzcyZxdW90OzoxLCZxdW90O3RvdGFsX2Ftb3VudCZxdW90OzomcXVvdDsxLjExJnF1b3Q7LCZxdW90O3Byb2R1Y3RfY29kZSZxdW90OzomcXVvdDtRVUlDS19XQVBfV0FZJnF1b3Q7LCZxdW90O3F1aXRfdXJsJnF1b3Q7OiZxdW90O2h0dHBzOlwvXC9wYXkuc2Mud2VpYm8uY29tXC9wYXlcL3dhcFwvZmFpbD9tc2c9JUU2JTk0JUFGJUU0JUJCJTk4JUU1JUJDJTgyJUU1JUI4JUI4JnF1b3Q7fSIvPgoJPGlucHV0IG5hbWU9InNpZ24iIHR5cGU9ImhpZGRlbiIgdmFsdWU9Im1tc2xxT1JrY0hDditYaDU4YlVYamdvUkRGUTdxY0dFOFhzOEZiTWxQQklhcGk4eEhqS1h4TWtNUlNkZXAzV29Uc1lPNVRLKzllV0ZXVjhDS2NNVndxK3JHVFlTZlNXRytydHNEb2V6UDd5RjIrSzlQMzc2R3h3LzA0WW5CZ1J0N3BLb3hvajhDV3gyT0Y2ZlZvVzc5M01ZOUY2bGpndDlKN0VoY2EvTjdRbjZxdCtNNXFPVlV4dlRiVkcxYjB3M0ZIeUFjZU9zL3RzbUF4QURzQnhXR3h6c2E0c0dRTThsYzlkM2FFQjlYRTA5ZFdDVmpmeWducWNra0xDKytnRGJ6b04xTmlLRm84R1NXTDdPM0FuTlB3eG1WU0VJbDBVSFBVZEZpOWpNUGpvbW9JcWJIcW82eTE3WDkwQXg3a25QazVoTTBTRVhEUHFiT09jMXBzNFZlUT09Ii8+Cgk8L2Zvcm0+Cg==\"}"
                ];

                $re=[
                    "cmd"=>"weibo.refresh.bill",
                    "h5"=>"_h5_from=102003; _T_WM=51783772242; from=102003; HTTP_USER_AGENT_WEIBO=Meizu-M1813__weibo__9.12.0__android__android8.1.0; M_WEIBOCN_PARAMS=from%3D102003%26lfid%3D102803%26luicode%3D20000174%26uicode%3D20000174; MLOGIN=1; SCF=Aolq86djioX566BnojEhx-4cjy-hxEUNNVmjrS36tN-9Lx7tlUqawAjUUjOPdHd3IU63B16En1smyPBImMWXfig.; SSOLoginState=1575908793; SUB=_2A25w6gXpDeRhGedI71YR9CjJyD6IHXVQFKuhrDV6PUJbkdANLVD4kW1NV3AdCGYjliew4S5fqdoyGVn2If2p7CdZ; SUHB=0HTgz7eGeePJJM; WEIBOCN_FROM=1110003030; CONTENT-HONGBAO-G0=dc13680feed1518dda4bcc695a1fa9c9",
                    "app"=>"_s_tentry=-; ALF=1578500793; Apache=6719515797316.056.1575873591492; SCF=AmDOx4KwN-l7-sqJFrb35gZUA74w_ONHeyKGtm9Giob_GvjAgGoboFU6k7yYCCCSC4Kp712ShRPb8Ubifoq9q70.; SINAGLOBAL=3973782236673.7886.1533543040699; SSOLoginState=1575896593; SUB=_2A25w6gXpDeRhGedI71YR9CjJyD6IHXVQFKuhrDV8PUJbkNBeLVbwkW1NV3AdCFGK9TT9tenFs13gw7LJP2tkSHgT; SUBP=0033WrSXqPxfM725Ws9jqgMF55529P9D9WF0dOaNDJpAjOy53j3UE7Z55JpX5oz75NHD95QpSoBXehBcSKeEWs4DqcjCi--4iKnpiK.0i--RiKy2i-20; SUHB=0jBIAdGAyBjD9P; ULV=1575873591600:54:8:1:6719515797316.056.1575873591492:1575689723480; UOR=ent.ifeng.com,widget.weibo.com,login.sina.com.cn; webim_unReadCount=%7B%22time%22%3A1575893437846%2C%22dm_pub_total%22%3A0%2C%22chat_group_client%22%3A0%2C%22allcountNum%22%3A9%2C%22msgbox%22%3A0%7D; wvr=6; SC-EVENT-G0=17736b3ddf92e2cc96c6f394b8063789",
                    "data"=> "{\"amount\":\"1\",\"beizhu\":\"T1211032528111\",\"gid\":\"4446667619035347\",\"count\":1}",
                    "aid"=> "37",
                    "oid"=> "6000062464970",
                    "rBill"=> "{\"fa\":{\"id\":[\"6000062443255\",\"6000060373145\",\"6000060271777\",\"6000060265527\"],\"time\":[\"2019-12-11 13:22:36\",\"2019-12-07 11:44:50\",\"2019-12-07 01:02:52\",\"2019-12-07 00:45:05\"],\"amount\":[\"1.00\",\"1.00\",\"0.20\",\"0.20\"],\"status\":[\"0\\/1\",\"1\\/1\",\"1\\/2\",\"1\\/2\"]},\"sh\":{\"r\":[\"receivedetail\",\"receivedetail\",\"receivedetail\",\"receivedetail\",\"receivedetail\"],\"id\":[\"6000061660532\",\"6000061632167\",\"6000061469338\",\"6000061356040\",\"6000060308637\"],\"amount\":[\"1.00\",\"0.50\",\"0.50\",\"0.50\",\"0.70\"],\"time\":[\"2019-12-10 01:31:17\",\"2019-12-10 01:21:50\",\"2019-12-09 20:34:54\",\"2019-12-09 15:35:45\",\"2019-12-07 12:05:45\"]}}"
                ];

                $str='{"cmd":"cn.10086.online","cookie":"login=true; channel=0705; ssologinprovince=100; CmLocation=100|100; CmProvid=bj; cmcc_guide=20151117; collect_id=oh8n0s2pn5cch53n6vwf3b29ph8urbau; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5widFM35Cm3y3C+ek+3KUzXWSMTsRVyT13VInOal6sQlEY+dvBVErR/ksPv5W6XILGzNIChi3gihwmhVzzoGOae/Wf05PMmV+8xg3RxlK4W1OmNwSyqpJkEqg/LuT1QHsyO; verifyCode=1f3d53e9d368cd39fc297e1c5881bcefbf8998de; cart_code_key=i2snm7op83etdbh3460nq1s7f1; jsessionid-echd-cpt-cmcc-jt=3F1893A01DD3801639678067BD11BAF5; chargeresource=s%3D~e%3D~c%3D~taskId%3D~tag%3D; CaptchaCode=uAqOpw; rdmdmd5=A38FBAC2B21C14AD514374C09171EDF2; sendflag=20200107231434814138; cmccssotoken=f5529e33a8e64536b109df7947031d93@.10086.cn; is_login=true; c=f5529e33a8e64536b109df7947031d93; ssoFailFlag=1; WT_FPC=id=28f14d126683ea62caf1577701341218:lv=1578410011597:ss=1578409979839; sendflag=20200107234150848086; CITY_INFO=100|100; ssologinprovince=100; channel=0705","account_id":"48","user_id":"4","tel":"15010133879","back":{"data":{"totalCount":"18","pageSize":"20","orderInfo":[{"orderId":"471047988178127441","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200106223948","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882851178496511","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004732","closeTime":"202001","payType":"1","busiStatus":"4","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882809178300221","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004649","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882767178236351","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004607","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882422178483771","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004023","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882415178187831","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004015","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882296178232911","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105003816","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470878926178360781","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104234206","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470876957178328761","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104230918","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470876827178487091","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104230708","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]}]},"retCode":"000000","retMsg":"充值订单查询成功"}}';
                $str='{"account_id":"18026","zhifu_account":"15010133879","user_id":"4","cookie":"CITY_INFO=100|10; cmccssotoken=990f2aede15542a09c26491d2fc66832@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5widFM35Cm3y3C+ek+3KUzXWSMTsRVyT13VInOal6sQlEY+dvBVErR\/ksPv5W6XILGzNIChi3gihwmhVzzoGOae\/Wf05PMmV+8xg3RxlK4W1OmNwSyqpJkEqg\/LuT1QHsyO; c=990f2aede15542a09c26491d2fc66832; verifyCode=1f3d53e9d368cd39fc297e1c5881bcefbf8998de; CITY_INFO=100|10; jsessionid-echd-cpt-cmcc-jt=1C02A5FD853B740AA315B44D72FAC810; channel=0705; CITY_INFO=100|10; ssologinprovince=100; channel=0705","cmd":"cn.10086.order","back":{"data":{"totalCount":"18","pageSize":"20","orderInfo":[{"orderId":"471047988178127441","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200106223948","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882851178496511","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004732","closeTime":"202001","payType":"1","busiStatus":"4","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882809178300221","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004649","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882767178236351","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004607","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882422178483771","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004023","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882415178187831","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004015","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882296178232911","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105003816","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470878926178360781","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104234206","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470876957178328761","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104230918","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470876827178487091","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104230708","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]}]},"retCode":"000000","retMsg":"\u5145\u503c\u8ba2\u5355\u67e5\u8be2\u6210\u529f"}}';
                $str='{"account_id":"18915","zhifu_account":"15010133879","user_id":"4","cookie":"CITY_INFO=100|10; cmccssotoken=f42b73d3016a411b8112f67720fedc90@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5widFM35Cm3y3C+ek+3KUzXWSMTsRVyT13VInOal6sQlEY+dvBVErR\/ksPv5W6XILGzNIChi3gihwmhVzzoGOae\/Wf05PMmV+8xg3RxlK4W1OmNwSyqpJkEqg\/LuT1QHsyO; c=f42b73d3016a411b8112f67720fedc90; verifyCode=1f3d53e9d368cd39fc297e1c5881bcefbf8998de; CITY_INFO=100|10; jsessionid-echd-cpt-cmcc-jt=E979C232A23D09D84C5DEED6B0A0FD7B; channel=0705; CITY_INFO=100|10; ssologinprovince=100; channel=0705","cmd":"cn.10086.order","back":{"data":{"totalCount":"19","pageSize":"20","orderInfo":[{"orderId":"471720784178075611","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200114173304","closeTime":"202001","payType":"1","busiStatus":"4","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"471047988178127441","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200106223948","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882851178496511","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004732","closeTime":"202001","payType":"1","busiStatus":"4","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882809178300221","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004649","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882767178236351","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004607","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882422178483771","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004023","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882415178187831","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105004015","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470882296178232911","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200105003816","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"15010133879","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470878926178360781","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104234206","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]},{"orderId":"470876957178328761","billCharge":"9.98","usePoint":"null","orderType":"004","orderSubType":"","cancelFlag":"0","customerId":"15010133879","loginType":"1","createTime":"20200104230918","closeTime":"202001","payType":"1","busiStatus":"1","orderListInfoItems":[{"rechargeNum":"13910144759","chargeFee":"10","queryType":"2","operateId":"3215"}]}]},"retCode":"000000","retMsg":"\u5145\u503c\u8ba2\u5355\u67e5\u8be2\u6210\u529f"}}';


                $str='{"cls":"com.ali.wang.pick","data":"{\"money\":1,\"id\":\"tribe50864299\",\"hongbaoId\":\"23504001581166297\"}","version":"V2.2.4","wangID":"tb778947520"}';

                $str= '{"cls":"myapp.v13.qrGroup","data":"{\"expireDate\":1582473599887,\"imgUrl\":\"https:\/\/mobilecodec.alipay.com\/show.htm?code=cgx18300olwuham2josi18a&picSize=256\",\"qrcode\":\"https:\/\/qr.alipay.com\/cgx18300olwuham2josi18a\",\"qrcodeMemo\":\"\u8be5\u652f\u4ed8\u5b9d\u4e8c\u7ef4\u7801\u5c06\u57282020\u5e7402\u670823\u65e5\u5931\u6548\",\"resultCode\":100,\"success\":true}","arg":"{\"gid\":\"0067890000020200215223650181\",\"fr\":\"local\"}","dt":"1581856111870","version":"V2.2.4","userId":"2088232932547186"}';

                $str='{"cls":"myapp.v13.group.join","gid":"0067890000020200215223650181","muid":"2088422429385814","dt":"1581864144479","version":"V2.2.5","userId":"2088232932547186"}';
                $str=<<<EOF
{"cls":"com.ali.wang.create","s":"2","data":"{\"url\":\"c2VydmljZT0iYWxpcGF5LmZ1bmQuc3RkdHJ1c3RlZS5vcmRlci5jcmVhdGUucGF5IiZwYXJ0bmVyPSIyMDg4NDAxMzA5ODk0MDgwIiZfaW5wdXRfY2hhcnNldD0idXRmLTgiJm5vdGlmeV91cmw9Imh0dHBzOi8vd3dob25nYmFvLnRhb2Jhby5jb20vY2FsbGJhY2svYWxpcGF5L25vdGlmeVBheVN1Y2Nlc3MuZG8iJm91dF9vcmRlcl9ubz0iMTI2MjExODZfMjU2XzE1ODMzNDExMDJfN2Y1ZDRkNmJjOGNlMDIzMzM2MWY2MDEyNGE5NTJjZjdfMSImb3V0X3JlcXVlc3Rfbm89IjEyNjIxMTg2XzI1Nl8xNTgzMzQxMTAyXzdmNWQ0ZDZiYzhjZTAyMzMzNjFmNjAxMjRhOTUyY2Y3XzFfcCImcHJvZHVjdF9jb2RlPSJTT0NJQUxfUkVEX1BBQ0tFVFMiJnNjZW5lX2NvZGU9Ik1FUkNIQU5UX0NPVVBPTiImYW1vdW50PSIwLjAxIiZwYXlfc3RyYXRlZ3k9IkNBU0hJRVJfUEFZTUVOVCImcmVjZWlwdF9zdHJhdGVneT0iSU5ORVJfQUNDT1VOVF9SRUNFSVBUUyImcGxhdGZvcm09IkRFRkFVTFQiJmNoYW5uZWw9IkFQUCImb3JkZXJfdGl0bGU9Iua3mOWuneeOsOmHkee6ouWMhSImbWFzdGVyX29yZGVyX25vPSIyMDIwMDMwNTEwMDAyMDAxOTQwNTIyMjQzOTgzIiZvcmRlcl90eXBlPSJERURVQ1RfT1JERVIiJmV4dHJhX3BhcmFtPSJ7InBheWVlU2hvd05hbWUiOiLmt5jlrp3njrDph5HnuqLljIUifSImcGF5X3RpbWVvdXQ9IjMwbSImb3JkZXJfZXhwaXJlZF90aW1lPSIzNjBkIiZzaWduPSJSJTJCMTNpMDUlMkJic2xKNnpvUGxWNTVwMmhFYXpjJTJGR25RUmpORjBCa2RlQWZCJTJCVFQwczVtanhCODRmWEdTakUxdzhLc1RuQVBzREJiZ296V1dQTnFyZVk0RUlENW83SklibGpOSjlIUFNhaFUlMkZjbXpQblJ3YVRVNGs2JTJCZSUyRmhORFZyOUwlMkI2a1VzZDlrRWJMek43UTlvTUdvJTJGamR2ZUdoeW4lMkZ6dnlOazJrTiUyRlRQaDVoR2dkWTdXbUwwTkNMajZBVlBvWjB0YXBLY2ZPV0ZNaXlObEZncFJSVUtHMGZ6aUhLSXJxRFpYRXQ4akZPdzhkJTJCWFNVZ2VYc0JvTmpqVDkySGEyWSUyQjRUOGl5Mkc3U3VvczR4RFJHSTNNSXp0bTBvcHJ5WkZUQWVUSUFVa0R2c3BBd0t3dWZ4bm56WDcxbjFoZCUyQmNFbzBNVVRNNFhEdTlpR0JjV2pWU1B3JTNEJTNEIiZzaWduX3R5cGU9IlJTQSI=\",\"money\":\"1.0\",\"id\":\"Tzegway11te\",\"hongbaoId\":\"12621186_256_1583341102\",\"qunid\":\"50864299\"}","version":"V2.2.6","wangID":"tb778947520"}                
EOF;

                $str='{"cls":"myapp.v13.qrMoney","money":"69","remark":"80187306569","data":"{\"codeId\":\"2003171847433860\",\"printQrCodeUrl\":\"https:\/\/qr.alipay.com\/fkx102711kstxcm8jwmd919\",\"qrCodeUrl\":\"https:\/\/qr.alipay.com\/fkx13925vlucqsofoneth0b?t=1584442933858\",\"success\":true}","dt":"1584442932498","version":"V2.2.6","userId":"2088232932547186"}';

                $str= <<<EOF
{"cls":"com.alipay.mobile.bill.list.ui.BillListActivity_","data":"[{\"billByMonthJumpUrl\":\"alipays://platformapi/startapp?appId=66666798&url=%2Fwww%2FrealtimeBill%2Findex.html\",\"billListItems\":[{\"canDelete\":false,\"contentRender\":0,\"gmtCreate\":0,\"isAggregatedRec\":false,\"month\":\"本月\",\"recordType\":\"MONTH\",\"serializedSize\":68,\"statistics\":\"支出 ￥26,945.97    收入 ￥27,500.02\",\"tagStatus\":0,\"unknownFieldsSerializedSize\":0},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100610096397028%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100610096397028\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+500.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-永旺\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:40\",\"gmtCreate\":1587642012000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/T1rn0fXjhrXXXXXXXX_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":435,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100190098495006%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100190098495006\",\"bizSubType\":\"1102\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"-4,900.10\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账到银行卡-转账-梁铭新\",\"contentRender\":0,\"createDesc\":\"今天\",\"createTime\":\"19:30\",\"gmtCreate\":1587641432000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://pic.alipayobjects.com/oss-fix/i/mobileapp/png/201410/3jTuQvQCCT.png_fix.png_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":486,\"tagStatus\":0,\"unknownFieldsSerializedSize\":23},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100240095354182%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100240095354182\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+0.01\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-黄光2\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:29\",\"gmtCreate\":1587641374000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png\",\"recordType\":\"CONSUME\",\"serializedSize\":428,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100030099648268%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100030099648268\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+2,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-秀运\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:27\",\"gmtCreate\":1587641224000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/TB1t2B8aj9EDuNjmgXUXXbCKXXa_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":446,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100270094399913%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100270094399913\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+1,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-谢佩\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:18\",\"gmtCreate\":1587640702000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/TB1Ny0PXxsJluNjmeUvXXcAiVXa_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":446,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100660097712462%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100660097712462\",\"bizSubType\":\"1135\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+500.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"80187667739-开心的过好每一天\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:11\",\"gmtCreate\":1587640291000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/TB1_9qBXRVrDuNjm2G1XXcA3XXa_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":467,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100480093722876%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100480093722876\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+1,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-有文\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:06\",\"gmtCreate\":1587639996000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png\",\"recordType\":\"CONSUME\",\"serializedSize\":431,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100190098443152%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100190098443152\",\"bizSubType\":\"1102\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"-4,500.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账到银行卡-转账-梁铭新\",\"contentRender\":0,\"createDesc\":\"今天\",\"createTime\":\"19:02\",\"gmtCreate\":1587639768000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://pic.alipayobjects.com/oss-fix/i/mobileapp/png/201410/3jTuQvQCCT.png_fix.png_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":486,\"tagStatus\":0,\"unknownFieldsSerializedSize\":23},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100110001804014%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100110001804014\",\"bizSubType\":\"1135\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+1,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"80187667916-敏\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"19:01\",\"gmtCreate\":1587639677000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png\",\"recordType\":\"CONSUME\",\"serializedSize\":433,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100920003667075%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100920003667075\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+1,500.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-执丶念\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"18:57\",\"gmtCreate\":1587639461000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/TB1o20wb6uEDuNjmf_lXXcSrXXa_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":449,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100190098409707%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100190098409707\",\"bizSubType\":\"1102\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"-15,100.12\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账到银行卡-转账-梁铭新\",\"contentRender\":0,\"createDesc\":\"今天\",\"createTime\":\"18:56\",\"gmtCreate\":1587639372000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://pic.alipayobjects.com/oss-fix/i/mobileapp/png/201410/3jTuQvQCCT.png_fix.png_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":487,\"tagStatus\":0,\"unknownFieldsSerializedSize\":23},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100080004519941%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100080004519941\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+2,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-满\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"18:54\",\"gmtCreate\":1587639249000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/TB1DW4JXpeb81Jjme7sXXa.OXXa_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":443,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100550094840170%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100550094840170\",\"bizSubType\":\"1135\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+10,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"80187667819-艺\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"18:53\",\"gmtCreate\":1587639218000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://os.alipayobjects.com/static/i/mobileapp/png/201509/5rNQKGl6CL.png\",\"recordType\":\"CONSUME\",\"serializedSize\":434,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100190098409668%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100190098409668\",\"bizSubType\":\"1102\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"-1,995.63\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账到银行卡-转账-梁铭新\",\"contentRender\":0,\"createDesc\":\"今天\",\"createTime\":\"18:53\",\"gmtCreate\":1587639195000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://pic.alipayobjects.com/oss-fix/i/mobileapp/png/201410/3jTuQvQCCT.png_fix.png_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":486,\"tagStatus\":0,\"unknownFieldsSerializedSize\":23},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100120004258275%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100120004258275\",\"bizSubType\":\"1135\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+4,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"80187667755-庞开文\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"18:52\",\"gmtCreate\":1587639164000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/T13HJpXlxeXXXXXXXX_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":445,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100190098382287%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100190098382287\",\"bizSubType\":\"1102\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"-450.12\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账到银行卡-转账-梁铭新\",\"contentRender\":0,\"createDesc\":\"今天\",\"createTime\":\"18:51\",\"gmtCreate\":1587639088000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://pic.alipayobjects.com/oss-fix/i/mobileapp/png/201410/3jTuQvQCCT.png_fix.png_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":484,\"tagStatus\":0,\"unknownFieldsSerializedSize\":23},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100990003243520%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100990003243520\",\"bizSubType\":\"1135\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+4,000.00\",\"consumeStatus\":\"2\",\"consumeTitle\":\"80187667755-小扣\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"18:50\",\"gmtCreate\":1587639026000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/T1NN4eXepgXXXXXXXX_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":442,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18},{\"actionParam\":{\"autoJumpUrl\":\"alipays://platformapi/startapp?appId=66666676&appClearTop=false&url=%2Fwww%2Findex.html%3FtradeNo%3D20200423200040011100120004134998%26bizType%3DD_TRANSFER%26appClearTop%3Dfalse%26startMultApp%3DYES\",\"serializedSize\":203,\"type\":\"APP\",\"unknownFieldsSerializedSize\":0},\"bizInNo\":\"20200423200040011100120004134998\",\"bizSubType\":\"1106\",\"bizType\":\"D_TRANSFER\",\"canDelete\":true,\"categoryName\":\"转账充值\",\"consumeFee\":\"+0.01\",\"consumeStatus\":\"2\",\"consumeTitle\":\"转账-Ady\",\"contentRender\":1,\"createDesc\":\"今天\",\"createTime\":\"18:46\",\"gmtCreate\":1587638812000,\"isAggregatedRec\":false,\"oppositeLogo\":\"https://t.alipayobjects.com/images/partner/TB1lb0JbX1LDuNjme7sXXbmYpXa_[pixelWidth]x.png\",\"recordType\":\"CONSUME\",\"serializedSize\":439,\"tagStatus\":0,\"unknownFieldsSerializedSize\":18}],\"code\":0,\"consumeVersion\":\"standard\",\"hasMore\":false,\"nextQueryPageType\":\"All\",\"serializedSize\":8401,\"succ\":true,\"timeRangeTip\":\"1\",\"unknownFieldsSerializedSize\":0},false]","userId":"2088832283450191"}
EOF;

                /*
                $str= <<<EOF
{"cls":"v3.taobao.msg.system","data":"eyJleHQiOnsibXNnX2ZlYXR1cmUiOjksInJ0IjoiQSIsInNlbmRlclJvbGVUeXBlIjoiMSIsImJ0IjoiMTQiLCJiaXpUeXBlIjoiMTQiLCJyZW1pbmRlciI6eyJpc1JlbWluZGVyIjpmYWxzZSwicmVtaW5kZXIiOmZhbHNlfSwiYml6Q2hhaW5JRCI6IjBiMDE3Y2NkMTU5Mjc2MTMwMTI5NDYzNzNkMDhkMy1pbU1zZyIsInNydF8wIjoiMSJ9LCJzdW1tYXJ5IjoidGI1ODI1OTczNzgg55Sz6K+35YWl576kIOWOu+WkhOeQhiIsImNvZGUiOnsiY2xpZW50SWQiOiIwX0dfMzkxNDU2MDU3M18xNTkxNDMzNTQ2NDk5XzM0MjM4OTg2NjJfMTU5Mjc2MTMwMTI4MV84MjE5NTI0MDM2MTg2MTAwODc3IiwibnVsbCI6ZmFsc2UsIm1lc3NhZ2VJZCI6IjBfR18zOTE0NTYwNTczXzE1OTE0MzM1NDY0OTlfMzQyMzg5ODY2Ml8xNTkyNzYxMzAxMjgxXzgyMTk1MjQwMzYxODYxMDA4NzcifSwibXNnVHlwZSI6MTA2LCJyZWNlaXZlciI6eyJ2YWxpZCI6dHJ1ZSwidGFyZ2V0SWQiOiIzOTE0NTYwNTczIiwidGFyZ2V0VHlwZSI6IjMifSwicmVtaW5kZXIiOnsicmVtaW5kQmVoYXZpb3IiOjAsInJlbWluZFR5cGUiOjF9LCJ2aWV3TWFwIjp7fSwic2VsZlN0YXRlIjowLCJzZW5kVGltZSI6MTU5Mjc2MTMwMTI4MywibW9kaWZ5VGltZSI6MTU5Mjc2MTMwMTI4Mywic2VuZGVyIjp7InZhbGlkIjp0cnVlLCJ0YXJnZXRJZCI6IjM0MjM4OTg2NjIiLCJ0YXJnZXRUeXBlIjoiMyJ9LCJjb252ZXJzYXRpb25Db2RlIjoiMF9HXzM5MTQ1NjA1NzMjM18xNTkxNDMzNTQ2NDk5XzBfMzkxNDU2MDU3MyMzIiwicmVjZWl2ZXJTdGF0ZSI6eyJyZWFkIjp7ImFsbCI6dHJ1ZSwiY291bnQiOjF9LCJ1bnJlYWQiOnsiYWxsIjpmYWxzZSwiY291bnQiOjB9fSwidGFnIjoiIiwib3JpZ2luYWxEYXRhIjp7ImFjdGl2ZUNvbnRlbnQiOiJbe1wiaW5kZXhcIjoxNyxcImxlbmd0aFwiOjMsXCJ0ZXh0XCI6XCLljrvlpITnkIZcIixcInVybFwiOlwiLy9tYXJrZXQubS50YW9iYW8uY29tL2FwcC90Yi1jaGF0dGluZy9jaGF0dGluZy1ndWlkZS9wYWdlcy9hcHByb3ZlP3doX3dlZXg9dHJ1ZSZncm91cElkPTBfR18zOTE0NTYwNTczXzE1OTE0MzM1NDY0OTkmdGFza0lkPWZkYzI5MjIwZjcxODQ4ZDRiZDA2MjI0NzIwZTM1M2E0XCJ9XSIsImNvbnRlbnQiOiJ0YjU4MjU5NzM3OCDnlLPor7flhaXnvqQg5Y675aSE55CGIn0sImxvY2FsRXh0Ijp7fSwic29ydFRpbWVNaWNyb3NlY29uZCI6MTU5Mjc2MTMwMTI4MzA3MSwic3RhdHVzIjowfQ==","version":"V2.3.0","taoID":"3914560573"}
EOF;
                */

                $str= <<<EOF
{"cls":"v3.taobao.url","arg":"{\"ic\":\"9y72pn80\",\"url\":\"http:\/\/d.m.taobao.com\/goAlipay.htm?service=%22alipay.fund.stdtrustee.order.create.pay%22&partner=%222088401309894080%22&_input_charset=%22utf-8%22&notify_url=%22https:\/\/wwhongbao.taobao.com\/callback\/alipay\/notifyPaySuccess.do%22&out_order_no=%2212621186_5859_1593132251_9b2817306a9e3df9805e4c4847738e45_1%22&out_request_no=%2212621186_5859_1593132251_9b2817306a9e3df9805e4c4847738e45_1_p%22&product_code=%22SOCIAL_RED_PACKETS%22&scene_code=%22MERCHANT_COUPON%22&amount=%22495.00%22&pay_strategy=%22CASHIER_PAYMENT%22&receipt_strategy=%22INNER_ACCOUNT_RECEIPTS%22&platform=%22DEFAULT%22&channel=%22APP%22&order_title=%22%E6%B7%98%E5%AE%9D%E7%8E%B0%E9%87%91%E7%BA%A2%E5%8C%85%22&master_order_no=%222020062610002001960535211136%22&order_type=%22DEDUCT_ORDER%22&extra_param=%22{%22payeeShowName%22:%22%E6%B7%98%E5%AE%9D%E7%8E%B0%E9%87%91%E7%BA%A2%E5%8C%85%22}%22&pay_timeout=%2230m%22&order_expired_time=%22360d%22&sign=%22UVNPAkrLBqLqaOpDgsliaW8Wh0zKkfxVkCgGG8mRLvvpQjJhuvKxEjKaRmbBzIINeNIzwTvV%2FsEBKMdRlcRoVausVdDU4Rcq00328dWgUNMxWV9iOW9FUM6QsNn%2Bxs12plWSTbmMIpubARCk4cbkwhsgCcZ%2Fm482XqeYUPoCd5m4nZm9%2BchtXp1%2BH3W6BVv67wDlnd%2B5f%2BvoKE%2FyhZLiYeWzMgeJwud7XTrwXQbS9%2BtZmSgLi9PS9SGIXi97tBq7SN4CHb84166v5Ur8mVs2cWKG9U02CUiVaSD4nR%2FtcYCu2c%2FPh09HsW%2B8ihSD3dfr9Fom2JjNugQjXPKb49VP5g%3D%3D%22&sign_type=%22RSA%22&taobaoCheckPayPasswordAction=true\"}","data":"eyJyZXQiOlsiU1VDQ0VTUzo66LCD55So5oiQ5YqfIl0sImRhdGEiOnsic2hvcnRVcmwiOiJodHRwczovL20udGIuY24vaC5WS0dpdjZSIiwibG9uZ1VybCI6Imh0dHA6Ly9kLm0udGFvYmFvLmNvbS9nb0FsaXBheS5odG0\/c2VydmljZT0lMjJhbGlwYXkuZnVuZC5zdGR0cnVzdGVlLm9yZGVyLmNyZWF0ZS5wYXklMjImcGFydG5lcj0lMjIyMDg4NDAxMzA5ODk0MDgwJTIyJl9pbnB1dF9jaGFyc2V0PSUyMnV0Zi04JTIyJm5vdGlmeV91cmw9JTIyaHR0cHM6Ly93d2hvbmdiYW8udGFvYmFvLmNvbS9jYWxsYmFjay9hbGlwYXkvbm90aWZ5UGF5U3VjY2Vzcy5kbyUyMiZvdXRfb3JkZXJfbm89JTIyMTI2MjExODZfNTg1OV8xNTkzMTMyMjUxXzliMjgxNzMwNmE5ZTNkZjk4MDVlNGM0ODQ3NzM4ZTQ1XzElMjImb3V0X3JlcXVlc3Rfbm89JTIyMTI2MjExODZfNTg1OV8xNTkzMTMyMjUxXzliMjgxNzMwNmE5ZTNkZjk4MDVlNGM0ODQ3NzM4ZTQ1XzFfcCUyMiZwcm9kdWN0X2NvZGU9JTIyU09DSUFMX1JFRF9QQUNLRVRTJTIyJnNjZW5lX2NvZGU9JTIyTUVSQ0hBTlRfQ09VUE9OJTIyJmFtb3VudD0lMjI0OTUuMDAlMjImcGF5X3N0cmF0ZWd5PSUyMkNBU0hJRVJfUEFZTUVOVCUyMiZyZWNlaXB0X3N0cmF0ZWd5PSUyMklOTkVSX0FDQ09VTlRfUkVDRUlQVFMlMjImcGxhdGZvcm09JTIyREVGQVVMVCUyMiZjaGFubmVsPSUyMkFQUCUyMiZvcmRlcl90aXRsZT0lMjIlRTYlQjclOTglRTUlQUUlOUQlRTclOEUlQjAlRTklODclOTElRTclQkElQTIlRTUlOEMlODUlMjImbWFzdGVyX29yZGVyX25vPSUyMjIwMjAwNjI2MTAwMDIwMDE5NjA1MzUyMTExMzYlMjImb3JkZXJfdHlwZT0lMjJERURVQ1RfT1JERVIlMjImZXh0cmFfcGFyYW09JTIyeyUyMnBheWVlU2hvd05hbWUlMjI6JTIyJUU2JUI3JTk4JUU1JUFFJTlEJUU3JThFJUIwJUU5JTg3JTkxJUU3JUJBJUEyJUU1JThDJTg1JTIyfSUyMiZwYXlfdGltZW91dD0lMjIzMG0lMjImb3JkZXJfZXhwaXJlZF90aW1lPSUyMjM2MGQlMjImc2lnbj0lMjJVVk5QQWtyTEJxTHFhT3BEZ3NsaWFXOFdoMHpLa2Z4VmtDZ0dHOG1STHZ2cFFqSmh1dkt4RWpLYVJtYkJ6SUlOZU5JendUdlYlMkZzRUJLTWRSbGNSb1ZhdXNWZERVNFJjcTAwMzI4ZFdnVU5NeFdWOWlPVzlGVU02UXNObiUyQnhzMTJwbFdTVGJtTUlwdWJBUkNrNGNia3doc2dDY1olMkZtNDgyWHFlWVVQb0NkNW00blptOSUyQmNodFhwMSUyQkgzVzZCVnY2N3dEbG5kJTJCNWYlMkJ2b0tFJTJGeWhaTGlZZVd6TWdlSnd1ZDdYVHJ3WFFiUzklMkJ0Wm1TZ0xpOVBTOVNHSVhpOTd0QnE3U040Q0hiODQxNjZ2NVVyOG1WczJjV0tHOVUwMkNVaVZhU0Q0blIlMkZ0Y1lDdTJjJTJGUGgwOUhzVyUyQjhpaFNEM2RmcjlGb20ySmpOdWdRalhQS2I0OVZQNWclM0QlM0QlMjImc2lnbl90eXBlPSUyMlJTQSUyMiZ0YW9iYW9DaGVja1BheVBhc3N3b3JkQWN0aW9uPXRydWUmdW49ODFlNGZiODM5ZGMxYWY1YjZiNmMwOTg2YzFhNjU4OTEmc2hhcmVfY3J0X3Y9MSJ9LCJ2IjoiMS4wIiwiYXBpIjoibXRvcC50YW9iYW8uc2hhcmVwYXNzd29yZC5nZW5lcmF0ZXNob3J0dXJsIn0=","version":"V2.3.1","taoID":"3914560573"}
EOF;

                $str=<<<EOF
{"cls":"v3.taobao.create","arg":"{\"note\":\"190701030128\",\"id\":\"190145190701030128\",\"m\":\"1\"}","gid":"0_G_3914560573_1591352975564","data":"","alipay":"service=\"alipay.fund.stdtrustee.order.create.pay\"&partner=\"2088401309894080\"&_input_charset=\"utf-8\"&notify_url=\"https://wwhongbao.taobao.com/callback/alipay/notifyPaySuccess.do\"&out_order_no=\"190145190701030128_56f5f96054764b8bf9559fd1b3d747fc_2\"&out_request_no=\"190145190701030128_56f5f96054764b8bf9559fd1b3d747fc_2_p\"&product_code=\"SOCIAL_RED_PACKETS\"&scene_code=\"MERCHANT_COUPON\"&amount=\"0.01\"&pay_strategy=\"CASHIER_PAYMENT\"&receipt_strategy=\"INNER_ACCOUNT_RECEIPTS\"&platform=\"DEFAULT\"&channel=\"APP\"&order_title=\"淘宝现金红包\"&master_order_no=\"2020070110002001840577634746\"&order_type=\"DEDUCT_ORDER\"&extra_param=\"{\"payeeShowName\":\"淘宝现金红包\"}\"&pay_timeout=\"30m\"&order_expired_time=\"360d\"&sign=\"X1qrBaUI0n1mUNwknesaF6Z4s9mHOShxakA%2Bf5QpSO9PdvTDFS5HfD5Qg5VXEkd8WkH9XbD7ctBbxnahNnMrhKN%2Bp9K9dBf1hZZa4y6lERQIcLGyGlIbvJs5e3unXmFDm5NIf7U1dAmbNGxpIptO9Er0PCFn3cKLSKTtqwxCeTOk%2B7JsypTRjCEmzs0F5OFpdtYYUv7xR0XDSIMjWhNk8IPte36Ed%2Fdq23Xu0pav%2FebPeWjjzIdl2cJFGWXL0QGiOhtLSGXrmC9N39l0TkKLVao60i00lWn8G7zUJUyS6Euzo%2BS5kEIOQpzS6EEhWBbcSgRx6TaMWFAPphGPr4mZgw%3D%3D\"&sign_type=\"RSA\"","surl":"eyJyZXQiOlsiU1VDQ0VTUzo66LCD55So5oiQ5YqfIl0sImRhdGEiOnsic2hvcnRVcmwiOiJodHRwczovL20udGIuY24vaC5WS0FYczV1IiwibG9uZ1VybCI6Imh0dHBzOi8vbG9naW4ubS50YW9iYW8uY29tL2xvZ2luLmh0bT90cGxfcmVkaXJlY3RfdXJsPWh0dHAlM0ElMkYlMkZkLm0udGFvYmFvLmNvbSUyRmdvQWxpcGF5Lmh0bSUzRnNlcnZpY2UlM0QlMjJhbGlwYXkuZnVuZC5zdGR0cnVzdGVlLm9yZGVyLmNyZWF0ZS5wYXklMjIlMjZwYXJ0bmVyJTNEJTIyMjA4ODQwMTMwOTg5NDA4MCUyMiUyNl9pbnB1dF9jaGFyc2V0JTNEJTIydXRmLTglMjIlMjZub3RpZnlfdXJsJTNEJTIyaHR0cHMlM0ElMkYlMkZ3d2hvbmdiYW8udGFvYmFvLmNvbSUyRmNhbGxiYWNrJTJGYWxpcGF5JTJGbm90aWZ5UGF5U3VjY2Vzcy5kbyUyMiUyNm91dF9vcmRlcl9ubyUzRCUyMjE5MDE0NTE5MDcwMTAzMDEyOF81NmY1Zjk2MDU0NzY0YjhiZjk1NTlmZDFiM2Q3NDdmY18yJTIyJTI2b3V0X3JlcXVlc3Rfbm8lM0QlMjIxOTAxNDUxOTA3MDEwMzAxMjhfNTZmNWY5NjA1NDc2NGI4YmY5NTU5ZmQxYjNkNzQ3ZmNfMl9wJTIyJTI2cHJvZHVjdF9jb2RlJTNEJTIyU09DSUFMX1JFRF9QQUNLRVRTJTIyJTI2c2NlbmVfY29kZSUzRCUyMk1FUkNIQU5UX0NPVVBPTiUyMiUyNmFtb3VudCUzRCUyMjAuMDElMjIlMjZwYXlfc3RyYXRlZ3klM0QlMjJDQVNISUVSX1BBWU1FTlQlMjIlMjZyZWNlaXB0X3N0cmF0ZWd5JTNEJTIySU5ORVJfQUNDT1VOVF9SRUNFSVBUUyUyMiUyNnBsYXRmb3JtJTNEJTIyREVGQVVMVCUyMiUyNmNoYW5uZWwlM0QlMjJBUFAlMjIlMjZvcmRlcl90aXRsZSUzRCUyMiVFNiVCNyU5OCVFNSVBRSU5RCVFNyU4RSVCMCVFOSU4NyU5MSVFNyVCQSVBMiVFNSU4QyU4NSUyMiUyNm1hc3Rlcl9vcmRlcl9ubyUzRCUyMjIwMjAwNzAxMTAwMDIwMDE4NDA1Nzc2MzQ3NDYlMjIlMjZvcmRlcl90eXBlJTNEJTIyREVEVUNUX09SREVSJTIyJTI2ZXh0cmFfcGFyYW0lM0QlMjIlN0IlMjJwYXllZVNob3dOYW1lJTIyJTNBJTIyJUU2JUI3JTk4JUU1JUFFJTlEJUU3JThFJUIwJUU5JTg3JTkxJUU3JUJBJUEyJUU1JThDJTg1JTIyJTdEJTIyJTI2cGF5X3RpbWVvdXQlM0QlMjIzMG0lMjIlMjZvcmRlcl9leHBpcmVkX3RpbWUlM0QlMjIzNjBkJTIyJTI2c2lnbiUzRCUyMlgxcXJCYVVJMG4xbVVOd2tuZXNhRjZaNHM5bUhPU2h4YWtBJTI1MkJmNVFwU085UGR2VERGUzVIZkQ1UWc1VlhFa2Q4V2tIOVhiRDdjdEJieG5haE5uTXJoS04lMjUyQnA5SzlkQmYxaFpaYTR5NmxFUlFJY0xHeUdsSWJ2SnM1ZTN1blhtRkRtNU5JZjdVMWRBbWJOR3hwSXB0TzlFcjBQQ0ZuM2NLTFNLVHRxd3hDZVRPayUyNTJCN0pzeXBUUmpDRW16czBGNU9GcGR0WVlVdjd4UjBYRFNJTWpXaE5rOElQdGUzNkVkJTI1MkZkcTIzWHUwcGF2JTI1MkZlYlBlV2pqeklkbDJjSkZHV1hMMFFHaU9odExTR1hybUM5TjM5bDBUa0tMVmFvNjBpMDBsV244Rzd6VUpVeVM2RXV6byUyNTJCUzVrRUlPUXB6UzZFRWhXQmJjU2dSeDZUYU1XRkFQcGhHUHI0bVpndyUyNTNEJTI1M0QlMjIlMjZzaWduX3R5cGUlM0QlMjJSU0ElMjIlMjZ0YW9iYW9DaGVja1BheVBhc3N3b3JkQWN0aW9uJTNEdHJ1ZSUyNnRhb2Jhb0NoZWNrUGF5UGFzc3dvcmRBY3Rpb24lM0R0cnVlJnVuPTgxZTRmYjgzOWRjMWFmNWI2YjZjMDk4NmMxYTY1ODkxJnNoYXJlX2NydF92PTEifSwidiI6IjEuMCIsImFwaSI6Im10b3AudGFvYmFvLnNoYXJlcGFzc3dvcmQuZ2VuZXJhdGVzaG9ydHVybCJ9","version":"V2.3.1","taoID":"3914560573"}
EOF;

                $str= <<<EOF
{"data":"{\"accountDetailForm\":{\"accountType\":\"\",\"billUserId\":\"2088831220486165\",\"bizTypeList\":[],\"endAmount\":\"\",\"endDateInput\":\"2020-09-30 17:55:59\",\"forceAync\":\"\",\"goodsTitle\":\"\",\"pageNum\":\"1\",\"pageSize\":\"20\",\"precisionQueryKey\":\"tradeNo\",\"precisionQueryValue\":\"\",\"queryEntrance\":\"1\",\"reqUserId\":\"\",\"searchType\":\"\",\"searchableCardListJson\":\"\",\"securityBizType\":\"\",\"securityId\":\"\",\"shopId\":\"\",\"showType\":\"1\",\"sortTarget\":\"tradeTime\",\"sortType\":\"0\",\"startAmount\":\"\",\"startDateInput\":\"2020-09-29 17:55:59\",\"targetMainAccount\":\"\",\"type\":\"\",\"ua\":\"\"},\"queryForm\":{\"accountType\":\"\",\"billUserId\":\"2088831220486165\",\"bizTypeList\":[],\"endAmount\":\"\",\"endDateInput\":\"2020-09-30 17:55:59\",\"forceAync\":\"\",\"goodsTitle\":\"\",\"pageNum\":\"1\",\"pageSize\":\"20\",\"precisionQueryKey\":\"tradeNo\",\"precisionQueryValue\":\"\",\"queryEntrance\":\"1\",\"reqUserId\":\"\",\"searchType\":\"\",\"searchableCardListJson\":\"\",\"securityBizType\":\"\",\"securityId\":\"\",\"shopId\":\"\",\"showType\":\"1\",\"sortTarget\":\"tradeTime\",\"sortType\":\"0\",\"startAmount\":\"\",\"startDateInput\":\"2020-09-29 17:55:59\",\"targetMainAccount\":\"\",\"type\":\"\",\"ua\":\"\"},\"status\":\"succeed\",\"success\":\"true\",\"result\":{\"summary\":{\"showSummary\":true,\"expendSum\":{\"amount\":\"0.00\",\"count\":0},\"showBegin\":false,\"incomeSum\":{\"amount\":\"0.01\",\"count\":1},\"endBalance\":\"0.00\",\"showEnd\":false,\"expendDetails\":[],\"showClassify\":true,\"incomeDetails\":[{\"amount\":\"0.01\",\"counts\":1,\"title\":\"\u5145\u503c\"}],\"beginBalance\":\"0.00\"},\"paging\":{\"totalItems\":1,\"current\":1,\"sizePerPage\":20},\"showBillInfo\":true,\"detail\":[{\"bizNos\":\"\",\"billSource\":\"\",\"otherBizFullName\":\"\",\"balance\":\"0.64\",\"transDate\":\"2020-09-30\",\"action\":{\"needDetail\":false},\"bizOrigNo\":\"\",\"storeName\":\"\",\"depositBankNo\":\"\",\"cashierChannels\":\"\",\"signProduct\":\"\",\"orderNo\":\"20200930110074011506160079644515\",\"bizDesc\":\"\",\"tradeNo\":\"2088831220486165-f8e847025204475081b03c8b450fa750\",\"accountType\":\"\u5145\u503c\",\"otherAccountFullname\":\"\u6e56\u5357\u822a\u5409\u822a\u7a7a\u670d\u52a1\u6709\u9650\u516c\u53f8\",\"accountLogId\":\"324448709792161\",\"transMemo\":\"\",\"tradeTime\":\"2020-09-30 17:28:18\",\"tradeAmount\":\"0.01\",\"chargeRate\":\"\",\"otherAccount\":\"dummy\",\"actualChargeAmount\":\"0.00\",\"goodsTitle\":\"\",\"otherAccountEmail\":\"jw258369@qq.com\"}]},\"isEntOperator\":false}","t":"1601459759","i":"2088831220486165","cls":"com.b2alipay.bill.qy","s":"C8F62B24915B02B6C28B9CC1F8BD2F1B","account":"2088831220486165","MB_time":"1601459759","MB_os":{"android":"true","version":"8.1.0","isBadAndroid":"false"}}
EOF;


                $str= <<<EOF
{"data":"                                <table>                                    <colgroup>                                        <col width=147>                                        <col width=205>                                        <col width=76>                                        <col width=>                                    <\/colgroup>                                    <tbody>                                    <tr>                                        <th>\u65f6\u95f4<\/th>                                        <th>\u91d1\u989d<\/th>                                        <th>\u64cd\u4f5c<\/th>                                        <th>\u5907\u6ce8<\/th>                                    <\/tr>                                                                                <tr>                                            <td>2020-10-12 23:46:16<\/td>                                            <td><em class=td-num>+10.00<\/em>                                            <\/td>                                            <td>\u8f6c\u5165<\/td>                                            <td>                                                <div class=td-msg>\u4f59\u989d\u5145\u503c\u5355:5094731,\u5145\u503c\u91d1\u989d\uff1a10<\/div>                                            <\/td>                                        <\/tr>                                                                            <\/tbody>                                <\/table>                                ","t":"1602638737","i":"wdlzqwxodohklg","cls":"com.b2jd.bill","s":"EDB6696545CE93C945903E62FBC82E77","account":"wdlzqwxodohklg","MB_time":"1602638736","MB_os":{"android":"true","version":"8.1.0","isBadAndroid":"false"}}
EOF;
                $str= <<<EOF
{"data":"{\"orderId\":\"\",\"alipayNo\":\"5097082\",\"arg\":{\"amount\":\"11\",\"id\":\"Tbn2izhb2dvf\",\"bn\":\"\u5efa\u8bbe\u94f6\u884c\"},\"url\":\"https:\/\/ibsbjstar.ccb.com.cn\/CCBIS\/CCBWLReqServlet\",\"epccGwMsg\":\"PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48cm9vdCB4bWxucz0ibmFtZXNwYWNlX3N0cmluZyI+PE1zZ0hlYWRlcj48U25kRHQ+MjAyMC0xMC0xNFQxMDoxOTozMzwvU25kRHQ+PE1zZ1RwPmVwY2MuMjQyLjAwMS4wMTwvTXNnVHA+PElzc3JJZD5HNDAwMDMxMTAwMDAxODwvSXNzcklkPjxEcmN0bj4yMTwvRHJjdG4+PFNpZ25TTj40MDA5NzI1NDA4PC9TaWduU04+PE5jcnB0blNOPjQwMDk4MzU1MjU8L05jcnB0blNOPjxEZ3RsRW52bHA+T2oyNFMraTNNYmlsWjNyZURmM3hIWU1UQ3R5TWxqUmlUV2pjVGlDRnpkMTVEU2x4Qy9YMHhuWFVtYU9saGFmc1NvSU5IMHJnems4d0JhL2pSTFA1UGhKZzhlM3hyMC9pRWp3NS9xc3E5aStGU1RVa3l3UmFTTWZPOVprZE83NGx4RkRUTFJPNGRQaFpUOTJOWkNJTGFkczQ4QXZjYmg1eTVRWEZSdkpWUk9tZEI5ZTZYSE1kTFNSVEFrMTJGNWxQTHlIUUtoU3M5QW5pK1pnU25OS3BCd2NmcmZOL3JDRC9lRzBtcS9WU09NN2xwazA2NklSOUp1d3lmb3IwZk82SGgxSUhFcURHWTFFZ0tLOWVyZlQzb0RibFRlaGhqdjR0T2I5WW9rS1hZbUIzSC9TdnIxamhLZXA3OE9DTUMybmlmb1QvTGlsVjZhRm50cWJTTnh3bmF3PT08L0RndGxFbnZscD48L01zZ0hlYWRlcj48TXNnQm9keT48U3lzUnRuSW5mPjxTeXNSdG5DZD4wMDAwMDAwMDwvU3lzUnRuQ2Q+PFN5c1J0bkRlc2M+MDAwMDAwMDA8L1N5c1J0bkRlc2M+PFN5c1J0blRtPjIwMjAtMTAtMTRUMTA6MTk6MzM8L1N5c1J0blRtPjwvU3lzUnRuSW5mPjxCaXpJbmY+PFRyeEN0Z3k+MDExMjwvVHJ4Q3RneT48VHJ4SWQ+MjAyMDEwMTQwMjg1NjAxMDMxMDg1NDU4MDExMjkwNjwvVHJ4SWQ+PFRyeEFtdD5DTlkyMC4wMDwvVHJ4QW10PjxUcnhTdGF0dXM+MDI8L1RyeFN0YXR1cz48Qml6U3RzQ2Q+MDAwMDAwMDA8L0JpelN0c0NkPjxCaXpTdHNEZXNjPuaIkOWKnzwvQml6U3RzRGVzYz48UmRyY3RVcmw+aHR0cHM6Ly9pYnNianN0YXIuY2NiLmNvbS5jbi9DQ0JJUy9DQ0JXTFJlcVNlcnZsZXQ8L1JkcmN0VXJsPjwvQml6SW5mPjxTZWM+ZSs4K2dRT2YrdGt3ZVFQTVlaT3FBVUtFR3pObmxLbDRoQXNEVThWN0tIVGNDUVZwcjIwNG1sMlNoejhPYllIV2duL1NRNjQrTkNxQ2NscFZqajNqVnRjYjJiRDRvRmZ3ekNZWU94aEFqYmgwQnl5QlhyZU1LMFV3dU04Nzg4Z0g0VndGY2RkVEtNcXdTNXpWenFyc3NwMVkxamRZTXpOcXlXV2RyNDlKRTVIb0M0cDd2OGlRK0VVNngrRDRubnQ3Rk14RmIvTnNsZzJWRjYwb1VGZnUxYlByM1YraFdkQklSNm5Xb3RSWWFPeFBlelR6ZmdKS01YUEMwa0poblR4dlVkb29wdFlSem1sL0lSOExDZ1JRSGZnT3U3NndpaFdxdU9CWFVKMnlnTVJGODZVZlRSVm9KcVEzQnhackhXZVJLcWxFQzN2dUN6d2YzZzhrOUJydXRpSUhJeG9ubjlzYUxZcmpacmVSSzBGT0xySEgrTGpac2xNaWdpMTBzZUMyaHhEV1d3cXpzeU9pQlJTejFYRmFzRGdicEd1THV0U3lQVzBlei92bTdNWGthbzhCck5ueW1jSDF6MUxWK000OEFTVi9GN2RZaldnZk5pdlJZNFUvbU9mNFlldWp0R2owRWd5Sjk5SnArMHZtazVzK1Fwak9jZUk1UmkrSGhtRWpKbFBZK2toWUsvK3RHWkJwNkFRNDUzNkpyaExBWlVWbER4eFZ6V0Nia1lUdmtiZW1raWZLMnZtR2p4UmJlc3IxdUdvVk5IS2xpZzdtclJQM1cyMU05MTNHRXlYZC9oWnlsWm5NMmpQNm00MjVqOFR6OFVkRzFsTUFUc0IwQXpGRUZuZ2VFUE9PTHV0YjFvdFdHSVFITlc1R1NUL3ROb05UL3JGRTNJdTczMGNTZlJuZ2tEcWZpVjdaUm1GMXpVK1JUU2pVbUNjK1NqKytMUDdkUFRCZ2tQL1BnYlhOQnVqSWhNVUo0MGNXWVVhbjVyVUtHS2JDSUVydDBRdGJLRTk3dU1CRWdKWFpUY0pTdDhYaWVnZ1RvMjJmdEc4dTZ1bktlME45T1l1MUYwV1NnbVZxSmprRXpvTHNLcEhxWExVTUtyaTRySEFTWDBGZWlyZjc4SGU1Q0h6OFZjQzZJMkFneURDTWMrUGdKUWhXcTFLMll3OEJMUTBDL09DVU5TNHkxNnU5dDRSZzl0OVRIR3VDM2UrMG5Jb29qbUpFcnhROHl2Q0dZa0NkQTZYaWlWcGFYRVcvUldlWUVZeFl2c1lVQ0t6aHY5Rk43SSt5eTY1em5ha05FT0M5dUQwWFIvNzJmK29XQVJaVnRabVFDb3ZzUXkxNEtSUThIMEErMktuSHVlVjdIT1AwR0ZsNFV5ZjVQSFlHclM4MEJkL3JRaUpmQWxSV1FxZ0lyQlE3QTdEbHdmWXZTV3VrNWl1eHRSTlZZUkN6OEw0TWlQQXRJdHFEU3p4c1dKYUk0dk5PZ0Y5a0VDMHpyZ0Q5OXFBTDZFWjMrWmhhSitkelpldXp0T2pZVWlZZUx0RzVhRUFuRW5MWnVyUjJtODhVTitIdHliOHRqUTg5Rm56VEZvQ0wxYlM1TUJEVnVTUy9NamRpZkEyRFBLMWhMWUtOSmFobE84eE1acXhGaStOTk9Zdmd2Q3V5VHpRT0hMTjdiZHd2RWNQZVVOMDNLenJ4a25uM2hOY1FnQUZiMDZKajVkRHFMZHR0M3JIQXNBaFBwYzdLY2N0Slcrd1Mrb1M2SHJOenVzZ1hnQmhqT0pRT2pJa0Q5K2FxcHFIUzc4b0k0d0EvSWhwbEZEV01BVCtCOEFUcUk2UEhXY3Z1SVZ2MWF3R3hsQ0hMVUE1ZWZnN3ZrTllWNk54eUhLVmdPUHdIc2EwLzgydUZWc1ZNYUl5dHl0VEdRdmFMMm93bjU4dk9ia1BJeDNVZHR4UXhSZ2t5LzRZNmlWZ201dk1LVmZQUythN3JwRGdGMWdpbDJKcnpNZ2wwS1Iyc0MxVjBwcUdqajJIejNxanhSQVdySktSV0poUjV2NlJuNUgyaUhaYm4wbzZkTTdNTFVQaUtHWVF3Qndyd0UvYzJ2ZlAxandRRUp6eEFaOWYrb0JlYjh6cS8zdTQ0TjY4akhpNU43R3hOL2U1UTQ4NUlwMW1idlpJV1BmaHRlc3phOFA2WTFyNUE2TkY5Si96VVZhNkxUcTNCT3l1Z2I1ajBCa2MvSDhRL2hLRWhSV3hUNFlPRE9SamUxTnJTSXc2SWdteDluR2Z5YmhNPTwvU2VjPjwvTXNnQm9keT48L3Jvb3Q+DQp7UzpqbGlteDBmRG82S0ZkcnBWamlKZWVWbFBPemF1QWJRT09iU2g3Z01LTWpLMisyckJrKzJnYWlPVDVQRWJRNUhNZEdwRHMrckhwTHBaajFZcWNqZG1mdzByQlQ0N1pJc3k2ZU9xL09VUmhkaHJvb0tvV3hEdGR3RlM4RWtiankwemdCQkM4cDRpZXZXSnA1SkNRRDRzOWhyRytRR0hyV3ZYTVQwbm5SaWhwTzJsN3M3eUtQVmdrOG1pWjhLTVhtYWs3U2ZXaU9pRitOTWlwZHZvMUZMZlFhc1lOUXVvZTVIdWMzcjlWU3VMN2lqVWVNMExFbWF0VmlIaFkwc2tDMWtYMkl3WUk1QmpwMnFjVjROaUtwTEdKQlF0M3pWaU1xR01XcllQVUVOUGNYdEtmVDdlbTV3ZHh0RDNWeE1WU0dHZnlkRDJNei9nTkFTdWl1SmJJRmRONGc9PX0=\"}","cls":"com.b2jd.qr","account":"wdlzqwxodohklg","MB_time":"1602641973","MB_os":{"android":"true","version":"8.1.0","isBadAndroid":"false"}}
EOF;







                $re= json_decode($str,true );
                //$this->drExit( $str );
                //$this->drExit( $re );
                $rvar = $this->getLogin()->createPayLog()->V3Parse( $re);

                print_r( $rvar );
                print_r( $re );
                $this->drExit( "<br><br>=====完结=====" );
                //$this->drExit( drFun::cut($data, 'userId=',',' ) );
                break;
            case 'smslog':

                $str= <<<EOF
{"uid":"4473","pay":"{\"strbody\":\"\u60a8\u5c3e\u53f71619\u7684\u50a8\u84c4\u53617\u67088\u65e512\u65f655\u5206\u5411\u5362\u6d77\u8de8\u884c\u8f6c\u51fa\u652f\u51fa\u4eba\u6c11\u5e015088.00\u5143,\u6d3b\u671f\u4f59\u989d14.87\u5143\u3002[\u5efa\u8bbe\u94f6\u884c]\",\"strAddress\":\"95533\",\"strDate\":\"2020-07-08 12:55:59\",\"strType\":\"\u63a5\u6536\",\"type\":42,\"title\":\"95533\",\"text\":\"\u60a8\u5c3e\u53f71619\u7684\u50a8\u84c4\u53617\u67088\u65e512\u65f655\u5206\u5411\u5362\u6d77\u8de8\u884c\u8f6c\u51fa\u652f\u51fa\u4eba\u6c11\u5e05088.00\u5143,\u6d3b\u671f\u4f59\u989d14.87\u5143\u3002[\u5efa\u8bbe\u94f6\u884c]\",\"money\":\"5088.00\",\"is_up\":false}","account":"{\"account_id\":\"56024\",\"account\":\"M56024\",\"user_id\":\"4467\",\"ctime\":\"1594105163\",\"type\":\"147\",\"zhifu_name\":\"\u8c2d\u6c38\u5f3a\",\"zhifu_realname\":\"\u6e56\u5357\u957f\u6c99\",\"zhifu_account\":\"6221805510001054122\",\"bank_id\":\"200105\",\"clienttime\":\"0\",\"online\":\"2\",\"yuer\":\"0.00\",\"process\":\"\",\"ali_uid\":\"\",\"utime\":\"0\",\"card_index\":\"\",\"ma_user_id\":\"4473\",\"price_max\":\"1000000\",\"fail_cnt\":\"0\",\"lo\":\"\",\"fail_cnt_all\":\"0\",\"fail_cnt_day\":\"0\",\"is_upload\":true}"}
EOF;
                $str= <<<EOF
{"uid":"4459","pay":"{\"strbody\":\"\u60a8\u5c3e\u53f7*1478\u7684\u5361\u4e8e07\u670807\u65e500:00\u5728\u652f\u4ed8\u5b9d\u8f6c\u5165300.00\u5143\uff0c\u4ea4\u6613\u540e\u4f59\u989d\u4e3a3310.00\u5143\u3002\u3010\u4ea4\u901a\u94f6\u884c\u3011\",\"strAddress\":\"95559\",\"strDate\":\"2020-07-08 12:00:21\",\"strType\":\"\u63a5\u6536\",\"type\":42,\"title\":\"95559\",\"text\":\"\u60a8\u5c3e\u53f7*1478\u7684\u5361\u4e8e07\u670807\u65e500:00\u5728\u652f\u4ed8\u5b9d\u8f6c\u5165300.00\u5143\uff0c\u4ea4\u6613\u540e\u4f59\u989d\u4e3a3310.00\u5143\u3002\u3010\u4ea4\u901a\u94f6\u884c\u3011\",\"money\":\"300.00\",\"is_up\":false}","account":"{\"account_id\":\"53892\",\"account\":\"M53892\",\"user_id\":\"4335\",\"ctime\":\"1593336569\",\"type\":\"147\",\"zhifu_name\":\"\u9ec4\u79cb\u534e\",\"zhifu_realname\":\"\u5149\u5927\u94f6\u884c\u785a\u53e3\u652f\u884c\",\"zhifu_account\":\"6214911400584733\",\"bank_id\":\"200108\",\"clienttime\":\"1594114361\",\"online\":\"1\",\"yuer\":\"0.00\",\"process\":\"\",\"ali_uid\":\"\",\"utime\":\"1594100043\",\"card_index\":\"\",\"ma_user_id\":\"4459\",\"price_max\":\"1000000\",\"fail_cnt\":\"0\",\"lo\":\"\u5e7f\u4e1c\",\"fail_cnt_all\":\"0\",\"fail_cnt_day\":\"0\",\"is_upload\":true}"}
EOF;

                $re= json_decode($str,true );

                //$this->drExit($re );

                print_r($re);

                //$this->getLogin()->createPayLog()->smsPayLog( $re );

                $trade = new trade();
                $trade->smsPayLog( $re );
                break;
            case '64':
                $str='ChAyMDg4NjIxNTYyODQ3NjU0EhBwYXkqKipAcGlnYWkub3JnGhAyMDg4MjMyOTMyNTQ3MTg2KgExMKfz0YH\/mvfBAjorVFJBTlNGRVIyMDE4MTIzMTIwMDA0MDAxMTEwMDY1MDA1NDQ4MzczOF9QMUIDMTA5SjZ7ImFwcE5hbWUiOiLovazotKYiLCJtIjoiMC4wMeWFgyIsInRpdGxlIjoi5ZOI5ZOI5ZOIIn1aE+WQkeS9oOi9rOi0pjAuMDHlhYNiCFRSQU5TRkVSaghUUkFOU0ZFUnJvYWxpcGF5czovL3BsYXRmb3JtYXBpL3N0YXJ0YXBwP2FwcElkPTIwMDAwMDkwJmFjdGlvblR5cGU9dG9CaWxsRGV0YWlscyZ0cmFkZU5PPTIwMTgxMjMxMjAwMDQwMDExMTAwNjUwMDU0NDgzNzM4eNysn6aALZABA5oBCFvovazotKZdugEzYzBkNTQxZTdjZjUzY2YzMzYyYjE2MzIzY2E5MTQxZWZfMTgxMjMxMjMwMDMxMDAyMDIz';
                echo base64_decode( $str);
                echo "\n\n<br><br>\n";
                $str='ChAyMDg4NjIxNTYyODQ3NjU0EhBwYXkqKipAcGlnYWkub3JnGhAyMDg4MjMyOTMyNTQ3MTg2KgExMAA6PVNUVFJBTlNGRVIyMDE4MTIzMTIwMDA0MDAxMTEwMDY1MDA1NDQ4MzczOF9QMVRQXzE1NDYyNjg0MzE5NzFCBDgwMDNK\/QJ7Im0iOiLnu5nlr7nmlrnlj5HkuKo8YSBocmVmPVwiYWxpcGF5czovL3BsYXRmb3JtYXBpL3N0YXJ0YXBwP2FwcElkPTA5OTk5OTg4JmJpelR5cGU9VFJBTlNGRVImYWN0aW9uVHlwZT1zZW5kUmVjZWlwdCZ0aXRsZT0lRTUlOEYlOTElRTklODAlODElRTUlOUIlOUUlRTYlODklQTcmbWVzc2FnZT0lRTUlQjclQjIlRTYlOTQlQjYlRTUlODglQjAlRTglQkQlQUMlRTglQjQlQTYwLjAxJUU1JTg1JTgzJnRyYWRlTm89MjAxODEyMzEyMDAwNDAwMTExMDA2NTAwNTQ0ODM3MzgmY2xpZW50TXNnSWQ9U1RUUkFOU0ZFUjIwMTgxMjMxMjAwMDQwMDExMTAwNjUwMDU0NDgzNzM4X1AxVFBfMTU0NjI2ODQzMTk3MVwiID7ovazotKblm57miac8L2E+77yM6K6p5a+55pa55pS+5b+DIn1aE+WQkeS9oOi9rOi0pjAuMDHlhYNiCFRSQU5TRkVSaghUUkFOU0ZFUnjjrJ+mgC2KAQExkAEEmgEIW+i9rOi0pl26ASJjMGQ1NDFlN2NmNTNjZjMzNjJiMTYzMjNjYTkxNDFlZl8w';
                $str =base64_decode( $str);
                $this->drExit( $str );
                break;
            case 'ac':
                $account = $this->getLogin()->createQrPay()->getAccountByID($p[1] );//getCardNo
                $cardNo = $this->getCardNo( $account );
                $this->drExit( $cardNo );
                break;

            case 'h5':
                $trade_id = trim($p[1]);
                //$this->drExit( $p );
                $this->getLogin()->createCaibao()->h5pNNost( $trade_id );
                break;
            case 'h6':
                $str='amount=100&app=zyptestapp&barcode=123123123123&local_order_no=localorderno123123123123&operator_id=axgdfdafd34124&subject=这是一笔支付订单&timestamp=1460512556270&key=thisistestkey';
                $str='amount=9&app=M81604320000001&command=open.api.h5&confirm_way=AUTO_PAY&local_order_no=20181010469&operator_id=21c4a94c9b83917408088440d6d3ec39&remark=VIP&request_id=20181010469&request_time=20190123103004&sign_type=MD5&subject=VIP&version=2.0&key=37c30299e1a700aba4e982708838e272';
                echo md5($str);
                break;

            case 'v45':
                $pay=['text'=>'【中国农业银行】董微于01月28日14:31向您尾号6670账户完成网银转账交易人民币11.11，余额22.23。'];
                $this->payLog45( $pay );
                $this->drExit( $pay );
                break;
            case 't38':
                $this->htmlFile='app/tool_t38.phtml';
                break;
            case 'int':

                $this->drExit(drFun::yuan2fen('150.11') );
                break;

            case 'dm':
                $this->drExit( "dm=". $this->getLogin()->getDomainByUid(4));
                break;

            case 'qr2':
                $trade_row= $this->getLogin()->createQrPay()->getTradeByID( $p[1] );
                $qf_ck = $_COOKIE['qf'];
                $this->getLogin()->createQrPay()->changQr( $trade_row,$qf_ck );

                break;

            case 'ip2':

                //$ip= APP_PATH.'ipip/ipipfree.ipdb' ;
                //$this->drExit( "ddd=".$ip );
                //$city= new City($ip);
                $this->drExit( $this->getLogin()->createIpCity()->find('123.116.133.212', 'CN') );
                break;
            case 'hic':
                //$this->getLogin()->createTest()->countFailCntByCUserID( $p[1] );
                $this->getLogin()->createTest()->countFailCntByCUserAll(   );
                $this->drExit();
                break;
            case 'ali351':
                $ali_id= trim($p[1]); //http://qz.atbaidu.com/test/tool/ali351/228146045
                $url=['code'=>'/api/q351/codeTest/'.$ali_id,'query'=> '/api/q351/queryTest'  ];
                $this->assign('trade',['realprice'=>1] )->assign('url', $url);
                $this->htmlFile='app/ali351.phtml';
                break;
            case 'google':
                $google = $this->getLogin()->createGoogleAuthenticator()->getAll();
                $this->drExit( $google );
                break;

            case 'taobao':
                ini_set('memory_limit', '4024M');
                //$re  = $tao->setSession($sessionKey)->taobao_trades_sold_increment_get( ['page_size'=>5,'status'=>'WAIT_BUYER_PAY' ]);
                $re= $this->getLogin()->createTaoboApi(1)->taobao_trades_sold_increment_get(  ['page_size'=>5,'status'=>'WAIT_BUYER_PAY' ] );
                //$this->drExit( $re );
                $this->getLogin()->createTaobao()->toPayLogTemByList($re ,1);//->toPayLogTem( $re );
                break;

            case 'taobaoMc':
                $re= $this->getLogin()->createTaoboApi(1)->taobao_user_seller_get(   );
                $this->drExit( $re );
                break;

            case 'mcyue':
                $mc= $this->getLogin()->createQrPay()->getMerchantYue(8522);
                $this->drExit($mc);
                break;

            case 'timeout80':
                //timeOut80
                $this->getLogin()->createQrPay()->timeOut80();
                break;
            case 'ic':
                $url='http://imsg.zahei.com/icomet/push?cname=TB3914560573&content=%7B%22cmd%22%3A%22createBill%22%2C%22data%22%3A%7B%22m%22%3A%221%22%2C%22id%22%3A%22193748190630013613%22%2C%22note%22%3A%22190630013613%22%7D%7D';
                $str= file_get_contents($url);
                $this->drExit( $str);
                break;
            case 'no':

                $test= new  \model\test();
                $test->payNoMatch( );
                break;
            case 'smhy':
                $trade= new trade();
                $str='陈速7月18日15时20分向您尾号0585的储蓄卡支付宝提现收入人民币5000元，存入人民币0.10元,活期余额2.21元。[建设银行]';
                // $str='【招商银行】巫孝慧于2020-07-18 16:30:54向您账户9641发起500.00元的汇款。本短信不作入账凭证，请查询您的银行';
                //$str='李浩7月18日15时34分向您尾号0585的储蓄卡电子汇入存入人民币3000.00元,活期余额3002.21元。[建设银行]';
                $str='[20条]尾号9234账户00:34存入201元，余额304.11元，摘要:元海亮支付宝转账 元海亮支付宝转账。[光大银行]';
                $str='[21条]温坤贤9月6日2时25分向您尾号6019的储蓄卡存入人民币4999.56存入人民币0.10元,活期余额2350.75元。[建设银行]';

                $tf= $trade->smsHealth($str, 499956);
                $this->assign('tf', $tf);
                break;
        }
    }

    function act_jsgo($p){
        $file=  dirname( dirname(dirname(__FILE__)) ).'/webroot/res/js/alipayUid.js';
        //$this->drExit( $file );
        header('Content-type: text/javascript');
        $str ='';
        $str.=";". file_get_contents( $file );
        $this->drExit( $str );
    }


    function act_taobaoCK( $p ){
        switch ($p[0]){
            case 'start':
                $where=['type'=>9];
                $where['>']['ctime']= time()-24*3600;
                $this->getLogin()->createTableTaobaoQr()->updateByWhere( $where,['type'=>0]);
                $this->redirect("",'可以开始了！');
                break;
            case 'load':
                $list = $this->getLogin()->createTableTaobaoQr()->selectWithPage( ['type'=>0 ] );
                $this->getLogin()->createTaobao()->listQr($list['list']);
                $server['qrType']= $this->getLogin()->createTaobao()->getQrType();
                $this->assign('sv',$server )->assign('list', $list );;
                break;
            case 'match':
                //$this->drExit($_POST );
                $qr_id = intval(  $p[1]);
                $this->getLogin()->createTaobao()->qrAnly($_POST['d'], $qr_id)->matchFromQr( $qr_id );
                $this->redirect("",'审核成功！');
                break;
        }
    }

    function act_demo($p){
        $app_secret='t2k36lywpmgvulxebuff3clelwrygkkd'; #商户秘钥
        $app_id='ac6pfuvs'; #商户秘钥

        $data['app_secret']= $app_secret; #商户秘钥 $app_secret
        $data['app_id']= $app_id;#商户ID

        //$data['price']= intval(100*$_POST['price']); #价格：单位分
        $data['goods_name']= 'hello';#商品名称
        $data['order_no']= "test".date("Ymd").rand(1000,9999); #商品订单号
        $data['notify_url']= 'https://'.drFun::getHost().'/phpdemo/notify.php'; #不超过255
        $data['return_url']= 'https://'.drFun::getHost().'/phpdemo/return.php';
        $data['pay_type']='1'; #1为支付宝 2为微信
        //$data['format']= trim($_POST['format']); #默认h5显示H5扫描支付; 还有app 格式，这种格式没有 return_url 需要自行根据notify_url通知充值用户



//可选
        $data['order_user_id']='123'; #商品平台的用户UID
        $data['attach']='id=789&arg=2'; #往会提交时会 原封不动提交返回

        $price=[100,300,500,1000,2000,3000,5000,0.01,1,2, 19.99, 49.99,99.99,199.99,499.99,999.99,1999.99,2999.99,345];

        switch ($p[0]){
            case 'post':
                $var = $_POST;
                $app_secret= $var['app_secret'];
                unset($var['app_secret']);
                foreach( $var as $k=>$v )  $var[$k]= urldecode( $v );
                ksort($var);

                //include "pay.class.php";
                $pay = new trade();
                $var['sign']= $pay->setSecret( $app_secret)->createSign( $var);


                $post='';
                foreach( $var as $k=>$v ) $post.= $k."=".urlencode($v).'&';
                $post = trim( $post,'&') ;

                $curl ='curl -k -d "' . $post . '"  https://'.drFun::getHost().'/api/pay';

                $result = array();
                $result['error'] = 0 ;  //成功返回
                $result['html'] = $curl ;  //表单
                $result['sign'] = $var['sign'] ;  //表单
                $result['post'] = $post ;  //表单
                $result['md5'] = $pay->getMd5Str();  //md5
                //echo json_encode($result);
                $this->drExit( json_encode($result) );

                break;
        }

        $this->assign('data', $data )->assign( 'price',$price);
        $this->htmlFile="test/demo.phtml";
    }

    function act_weibo( $p ){
        $this->setDisplay("json");
        //$this->log()
        if( !$_POST) $this->throw_exception( "未提交内容");
        $mq= new mq();

        //$mq->rabbit_publish( 'hc_test' , '11222=>'. date("Y-m-d") );
        $mq->rabbit_publish( 'weibo' ,  drFun::json_encode($_POST) );
        //$this->assign('ok', 1 );
        $this->drExit('ok');
    }


    function act_pay( $p ){
        $mid=8080;
        if( isset($_REQUEST['mid'])) $mid= trim( $_REQUEST['mid']);
        if( is_numeric( $p[0] )) $mid= $p[0] ;
        $md = $this->getLogin()->createQrPay()->getMerchantByID( $mid );

        switch ($p[0]){
            case 'notify':
                //$this->drExit('ok');
                $this->drExit('ok');
                break;
            case 'call':
                $trade= new trade();
                $data=[];
                $app_secret= $md['app_secret'];
                $app_id= $md['app_id'];
                //$data['app_secret']= $app_secret; #商户秘钥 $app_secret
                $data['app_id']= $app_id;#商户ID

                $data['price']=  drFun::yuan2fen($_POST['money']); #价格：单位分

                $data['goods_name']= 'hello';#商品名称
                $data['order_no']= "test".date("Ymd").rand(1000,9999); #商品订单号
                $data['notify_url']= 'http://q1.atbaidu.com/test/pay/notify'; #不超过255
                $data['return_url']= 'http://q1.atbaidu.com/test/pay/return';
                $data['pay_type']= isset($_POST['pay_type'])? trim( $_POST['pay_type'] ): '1'; #1为支付宝 2为微信
                $data['format']= 'json'; #默认h5显示H5扫描支付; 还有app 格式，这种格式没有 return_url 需要自行根据notify_url通知充值用户



//可选
                $data['order_user_id']='123'; #商品平台的用户UID
                $data['attach']='id=789&arg=2'; #往会提交时会 原封不动提交返回
                $data['sign']=  $trade->setSecret( $app_secret )->createSign( $data );

                $re= drFun::curlPost('https://qf.zahei.com/api/pay',$data);
                $this->assign('post',$_POST )->assign('re', json_decode($re, true)  )->assign('ds', $data );
                break;
            default:
                unset($md['app_secret']);
                unset($md['app_id']);
                $this->assign('merchant', $md);
                $this->htmlFile='app/test_pay.phtml';
                if($p[1]=='v2')   $this->htmlFile='app/test_pay_v2.phtml';

        }
    }


    function payLog45( &$pay ){

        if(  strpos($pay['text'],'北京银行') &&  ( strpos($pay['text'],'收入')     ) ) {
            //$opt_type = 10;
            $pay['buyer']= drFun::cut( $pay['text'],'对方户名:','。' );
            if( ! trim($pay['buyer'])  ) return false;
            $pay['ali_uid']= drFun::cut( $pay['text'],'对方尾号:','。' );
            return true;
        }
        if( strpos($pay['text'],'中国农业银行')  &&  ( strpos($pay['text'],'转存')|| strpos($pay['text'],'转账'))  ){

            $pay['buyer']= drFun::cut( $pay['text'],'】','于'.date("m") );
            if( ! trim($pay['buyer'])  ) return false;
            return true;
        }

        return false ;

    }


    function getCardNo($account){
        $card_index = trim( $account['card_index'] );
        if(! $card_index) return  urlencode( trim( $account['zhifu_account']));
        $card= trim( $account['zhifu_account']);
        $cardNo= substr( $card,0,8);
        $cardNo.='****';
        $cardNo.= substr($card,-4 );
        $cardNo.='&cardChannel=HISTORY_CARD&cardNoHidden=true&cardIndex='. $card_index ;
        return $cardNo;
    }

    function act_tburl( $p ){

        $qf_ck= $_COOKIE['qf'];
        if( !$qf_ck )       $qf_ck= drFun::rankStr(8);
        drFun::setcookie('qf',$qf_ck, time()+ 3600*24*365 );

        $sv=['ck'=>$qf_ck];

        switch ($p[0]){
            case 'send':
                $url= trim($_POST['url']);
                $this->sendUrl2Taobao( $url, $qf_ck);
                break;
            default:
                $this->assign('sv',$sv );
                $this->htmlFile="test/tburl.phtml";

        }
    }

    function sendUrl2Taobao( $url ,$ck ){
        drFun::checkUrl( $url );
        $arr=[];
        $arr['cmd']='tao.url';
        $arr['data']=["url"=>$url, 'ic'=>$ck ];
        $tao_id='3914560573';
        $url = 'http://imsg.zahei.com/icomet/push?cname=TB'.$tao_id.'&content='.urlencode( json_encode( $arr ) );

        //{"cmd":"tao.url","data":{"url":""}}
        file_get_contents( $url );

    }
}