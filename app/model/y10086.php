<?php
/**
 * 移动10086
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/1/4
 * Time: 21:55
 */

namespace model;


class y10086 extends cpost
{

    //CITY_INFO=100|100; cmccssotoken=510f707c510143d39bb7d6d192b8f618@.10086.cn; is_login=true; defaultloginuser_p=izr73fwOUuimT7R+YElqbvQdIEKrmWCpu49KY4pe7cglQnOlbxDN0nqcpR0yt5widFM35Cm3y3C+ek+3KUzXWSMTsRVyT13VInOal6sQlEY+dvBVErR/ksPv5W6XILGzNIChi3gihwmhVzzoGOae/Wf05PMmV+8xg3RxlK4W1OmNwSyqpJkEqg/LuT1QHsyO; c=510f707c510143d39bb7d6d192b8f618; verifyCode=1f3d53e9d368cd39fc297e1c5881bcefbf8998de; CITY_INFO=100|100

    function __construct($debug = false)
    {
        parent::__construct($debug);
        $this->setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1');
    }



    function login( $tel,$code){
        $this->clearCookie();
        $mtime= time().rand(100,999);
        $url='https://login.10086.cn/needVerifyCode.htm?account='.$tel.'&pwdType=02&timestamp='.$mtime;
        $re= $this->headerPost($url,'' );
        //$this->drExit($this->getCookie() );

        $url='https://login.10086.cn/login.htm?accountType=01&pwdType=02&account='.$tel.'&password='.urlencode( $this->encrypt($code)).'&inputCode=&backUrl=https%3A%2F%2Ftouch.10086.cn%2Fi%2Fmobile%2Frechargecredit.html&rememberMe=1&channelID=12014&loginMode=03&protocol=https%3A&timestamp='.$mtime ;

        //$header=[''];
        //$this->drExit( $url );
        $re= $this->headerPost($url,'' );
        //echo $this->getCookie() ."\n\n<br><br>";
        //$this->drExit($re );

        $json= json_decode( $re['body'],true);
        if($json['code']!='0000'){
            $this->throw_exception("登录错误：". $json['desc'], 20010505);
        }

        $url='https://touch.10086.cn/i/v1/auth/getArtifact2?backUrl=https%3A%2F%2Ftouch.10086.cn%2Fi%2Fmobile%2Frechargecredit.html&artifact=3ceb4190ecf0442fad5e3cb918687b24&type=01';
        $this->headerPost($url);
        $url='https://login.10086.cn/SSOCheck.action?channelID=12014&backUrl=https://touch.10086.cn/i/mobile/rechargecredit.html?welcome=1578238826160';
        $this->headerPost($url);
        return $this;
    }



    function encrypt($psw){
        $key='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsgDq4OqxuEisnk2F0EJFmw4xKa5IrcqEYHvqxPs2CHEg2kolhfWA2SjNuGAHxyDDE5MLtOvzuXjBx/5YJtc9zj2xR/0moesS+Vi/xtG1tkVaTCba+TV+Y5C61iyr3FGqr+KOD4/XECu0Xky1W9ZmmaFADmZi7+6gO9wjgVpU9aLcBcw/loHOeJrCqjp7pA98hRJRY+MML8MK15mnC4ebooOva+mJlstW6t/1lghR8WNV8cocxgcHHuXBxgns2MlACQbSdJ8c6Z3RQeRZBzyjfey6JCCfbEKouVrWIUuPphBL3OANfgp0B+QG31bapvePTfXU48TYK0M5kE+8LgbbWQIDAQAB';
        return drFun::publicEncrypt($psw, $key );
    }

    function test(){
        $tel='17392282809';
        $re = $this->headerPost('https://login.10086.cn/sendflag.htm?timestamp=1578232018949');
        //$re = $this->headerPost('https://login.10086.cn/needVerifyCode.htm?account='.$tel.'&pwdType=02&timestamp=1578236999677');
        $re = $this->headerPost('https://touch.10086.cn/i/v1/auth/getArtifact2?backUrl=https%3A%2F%2Ftouch.10086.cn%2Fi%2Fmobile%2Fhome.html&artifact=4d1d1c23a668460ebf44278e2cf70bc3&type=01');

        //$re= $this->headerPost('https://touch.10086.cn/ajax/user/userinfo.json?province_id=100&city_id=100&nalogin=0&update=1');
        $re= $this->headerPost('https://login.10086.cn/SSOCheck.action?channelID=12012&backUrl=https%3A%2F%2Ftouch.10086.cn%2Fsso%2Fminilogincallback.php');

        //$this->drExit( $re );

    }

    function getBill( $tel, $money){
        drFun::checkTel( $tel );
        //$re= $this->headerPost('https://touch.10086.cn/i/mobile/home.html');
        //$this->drExit($re );
        //$this->headerPost('https://touch.10086.cn/i/mobile/rechargecredit.html');
        $str='{"channel":"0003","payWay":"WAP","amount":'.(intval($money*100*0.998)/100).',"chargeMoney":'.$money.',"choseMoney":'.$money.',"activityNO":"","operateId":3215,"homeProv":"100","numFlag":"0","source":""}';
        //$this->drExit($str);
        $url='https://touch.10086.cn/i/v1/pay/saveorder/'.$tel.'?provinceId=100';
        $head=['Content-Type'=>'application/json; charset=UTF-8'];
        $this->setReferer(' https://touch.10086.cn/i/mobile/rechargecredit.html');
        $re=$this->headerPost( $url, $str, $head );

        //echo $this->getCookie()."\n\n";
        //$this->drExit( $re );
        $re = json_decode( $re['body'],true);
        if( !$re['data']['payUrl']){
            $this->throw_exception("发生错误： ". $re['retMsg'],20010401 );
        }
        return $re ;
    }

    function getPayLink($url ,$opt=[]){
        //$out=[];
        //        preg_match_all('|recvdetailowner\?set_id=([0-9]+)&|U',  $str,   $out, PREG_PATTERN_ORDER);


        $str= '';
        $this->setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1');
        $head=[];//['Content-Type'=>'application/json; charset=UTF-8'];
        $re =$this->headerPost( $url, $str, $head );

        $str= drFun::cut( $re['body'],'<form','/form>');
        //die( $str );
        $a= $this->getVarFromHidden($str);
        if( !$a )$this->throw_exception("订单超时" ,20010402 );

        $re=['url'=>'https://pay.shop.10086.cn/paygw/mobileAndBankPayH5' ];
        $re['data']= $a;
        return $re ;
        //print_r( $a );

        $re=[];
        //$re['alipay'] =$this->getPayLinkOne( $a,'ALIPAY');
        $re['wxpay'] =$this->getPayLinkOne( $a,'WXPAY',$opt);
        $this->drExit($re);
    }

    function getPayLinkOne($a,$bank='ALIPAY', $opt=[]){
        $a['bankAbbr']= $bank ; //WXPAY
        //$a['bankAbbr']='WXPAY'; //WXPAY
        $url='https://pay.shop.10086.cn/paygw/mobileAndBankPayH5';

        $head= ['Content-Type'=>'application/x-www-form-urlencoded'];
        $re=$this->headerPost( $url, $a, $head );

        if($opt['debug']){
            $this->drExit($re['body'] );
        }

        $url='https://pay.it.10086.cn/payprod-format/h5/dup_submit';

        $var = $this->getVarFromHidden($re['body'], ['tr'=>['&quot;'=>'"'] ]);
        if( !$var) $this->throw_exception("订单超时" ,20010403 );

        $rz['url']= $url;
        $rz['data']= $var;
        return $rz ;
        /*
        $this->drExit( $re );
        $re=$this->headerPost( $url, $var , $head );

        switch ($bank){
            case 'WXPAY':
                return  drFun::cut( $re['body'],'action="','"');
                break;
            case 'default':
            default:
                return drFun::cut( $re['body'],'window.location.href = "','"');

        }
        */
    }

    function getVarFromHidden($str ,$opt=[]){
        $out=[];
        preg_match_all('|type="hidden" name="([^"]+)" value="([^"]+)"|U',  $str,   $out, PREG_PATTERN_ORDER);
        $a=[];
        foreach( $out[1] as $k=>$v ){
            if( $opt['tr'] )$a[$v]= strtr($out[2][$k],$opt['tr'] );
            else $a[$v]= $out[2][$k];
        }

        return $a;
    }

    function sendSms($tel, $type=''){
        drFun::checkTel( $tel );

        $ref='https://login.10086.cn/html/login/touch.html?channelID=12014&backUrl=https%3A%2F%2Ftouch.10086.cn%2Fi%2Fmobile%2Frechargecredit.html';

        $url='https://login.10086.cn/html/login/touch.html?channelID=12014&backUrl=https%3A%2F%2Ftouch.10086.cn%2Fi%2Fmobile%2Frechargecredit.html';
        //$re= $this->setReferer($ref)->headerPost($url);

        $url='https://login.10086.cn/loadSendflag.htm?timestamp=';
        $re= $this->setReferer($ref)->headerPost($url);

        $header=['X-Requested-With'=>'XMLHttpRequest'];
        $header['Content-Type']= 'application/x-www-form-urlencoded; charset=UTF-8';

        $url='https://login.10086.cn/chkNumberAction.action';
        $data=['userName'=>$tel,'type'=>'03','channelID'=>'12014' ];


        //$re= $this->setReferer($ref)->headerPost($url,$data ,$header);

        $url='https://login.10086.cn/loadToken.action';
        $data=['userName'=>$tel  ];
        $re= $this->setReferer($ref)->headerPost($url,$data ,$header);

        $json= json_decode($re['body'],true);
        if( !$json['result']) $this->throw_exception("获取失败:". $json['desc'], 20010501);
        //$this->drExit($re );


        $url='https://login.10086.cn/sendRandomCodeAction.action';
        $data=['userName'=>$tel,'type'=>'01','channelID'=>'12014' ];
        $header['Xa-before']= $json['result'];

        //$this->drExit( $header);
        $re= $this->setReferer($ref)->headerPost($url,$data ,$header);

        $msg=[];
        $msg[0]='已将短信随机码发送至手机，请查收!';
        $msg[1]='对不起，短信随机码暂时不能发送，请一分钟以后再试！';
        $msg[2]='短信下发数已达上限，您可以使用服务密码方式登录！';
        $msg[3]='对不起，短信发送次数过于频繁！';
        $msg[4]='对不起，渠道编码不能为空！';
        $msg[5]='对不起，渠道编码异常！';
        $msg[6]='发送短信验证码失败,请检查!';
        $msg['4005']='手机号码有误，请重新输入!';

        if( $re['body']!=='0'){
            $this->throw_exception("失败:" . $msg[$re['body'] ], 20011504);
        }

        //$this->drExit($re);

        return $this;
    }

    function getOrderList( $tel){
        $month =date("Ym");
        $url='https://touch.10086.cn/i/v1/cust/orderlistqry/'.$tel.'?loginNo='.$tel.'&orderType=004&status=6&startTime=201912&endTime='.$month.'&currentPage=1&channelId=00&pageSize=20'; //&time=20201693413373

        $re= $this->headerPost($url);

        //$this->drExit( $re );
        return json_decode( $re['body'], true );
    }

}