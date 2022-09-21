<?php
/**
 *
 * 这个理放登录处理流程、登录信息、cookeie相关等
 *
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/12 0012
 * Time: 下午 9:07
 */

namespace model\user;


use model\lib\DrRedis;
use model\mserver;
use model\taobao;
use ipip\db\City;
use model\attr;
use model\block;
use model\book;
use model\caibao;
use model\cls;
use model\daily;
use model\drException;
use model\drFun;
use model\export;
use model\google_authenticator;
use model\know;
use model\lib\cache;
use model\lib\excel;
use model\lib\sms;
use model\lib\TimRestAPI;
use model\log;
use model\logPay;
use model\model;
use model\novel;
use model\qrpay;
use model\snt;
use model\table;
use model\tag;
use model\taobaoapi;
use model\task;
use model\term;
use model\test;
use model\text;
use model\user;
use model\vip;
use model\weiboHelp;
use model\weiboserver;
use model\wenda;
use model\y10086;

class login extends model
{
    private static $_one= [];
    private static $cookevar =null ;
    private static $is_reg_term= false ;
    private $user_id;
    private static $_saveCls=[];
    private $tb_user='user';
    private $schoolVar=[];
    private static $_uhao='';
    private $school= null;

    function __construct( )   {
        $this->initLoad();

    }

    private  function initLoad(){
        $cvar = self::getCookieUser();
        if( $cvar ) $this->user_id= $cvar['uid'] ;
    }

    /**
     * 情况所有缓存，实例过的类
     * @return $this
     */
    function clear(){
        self::$_one=[];
        self::$cookevar= null ;
        self::$_saveCls= [];
        return $this;
    }

    /**
     * 检查是否登录状态
     * @return bool
     */
    public function isLogin(){
        return (  $this->user_id>0 );
    }

    /**
     * 获取user_id
     * @return mixed
     * @throws drException
     */
    public  function getUserId(){
        if( $this->user_id<=0 ) $this->throw_exception("请先登录",317 );
        return  $this->user_id;
    }

    public function checkIpSafe(){
        $ip= drFun::getIpAll();
        $arr = explode(',', $ip);
        $http_host= $_SERVER['HTTP_HOST'];
        $cnt=1;
        if(   in_array($http_host, [ 'qz.becunion.com:443','qz.becunion.com' ]) )  return $this;
        if( $arr[0]=='47.244.73.47') return $this;

        if( count($arr)>$cnt ) $this->throw_exception("你处在不安全的网络",110);

        return $this;
    }

    function baopoLImit( $opt=[]){
        $key = 'ip_' . drFun::getIP() ;

        try {
            $v=0;
            if( $opt['clear'] ) {
                $v=0;
            }else{
                $v = $this->getLogin()->createCache()->getRedis()->get($key);
                $v++;
            }
            $this->getLogin()->createCache()->getRedis()->set($key,$v ,300);



        } catch (\Exception $ex) {
            $v = 0;
        }

        if( $v>10 ) $this->throw_exception("请过5分钟后尝试，请勿爆破", 19110501);

        return $v;
    }

    /**
     * @param string $email_tel_id 只能是电话或者邮箱
     * @param string $psw
     * @param array $opt
     * @return array
     * @throws drException
     */
    function loginByPsw( $email_tel_id, $psw,$opt=[] ){

        $this->checkHeiIp()->baopoLImit( );

        $user= new user( );
        $email_tel_id = trim( $email_tel_id );
        $o_from = $user->getOpenidOauthfrom( $email_tel_id );

        $oauth = $user->getUserOauth($email_tel_id,$o_from );
        if( ! $oauth ){
            $this->appendUserLoginLog(-1, 3, $email_tel_id.' 账号不存在！ '. drFun::getIpAll() );
            throw new drException("账号或者密码错误",361 );
        }

        $this->user_id = $oauth['user_id'];

        $this->log( "[".date("Y-m-d H:i:s")."] login ".drFun::getIP()."\t".$this->user_id."\t".$psw,"debug.log");

        $userone= $this->createUserOne($oauth['user_id'] );
        $is_google= false;
        $duser   = $userone->getUser();


        $msg2='';
        if( $opt['channel'] == 'client'){
            $msg2=' 来自监控['.$_POST['pay'].']';
        }

        try {
            if ($opt['channel'] != 'client') {

                if ($duser['google']) {
                    if (!$_POST['google']) $this->throw_exception("请填写谷歌验证动态码！");
                    $userone->checkGoogle($_POST['google']);
                    $is_google = true;
                }

            }
            $userone->checkPsw($psw);

            if (isset($_POST['merchant_id'])) {
                $mc = $this->getLogin()->createQrPay()->getMerchantByID($_POST['merchant_id']);
                if ($duser['user_id'] != $mc['user_id']) $this->redirect('', "非法进入");
                if( $mc['type']==10 ) $this->throw_exception('商户被禁用或者不存在！');
                $opt['mc_id'] = $mc['merchant_id'];
            }
            if ($_POST['sq']) {
                $sq = $this->shouQuan($oauth['user_id']);
                $me = $this->getLogin()->createUserOne($oauth['user_id'])->getALl();

                $me_old = $me['attr']['zw'][0]['value'];
                if ($me_old) $sq = $me_old;

                if ($sq != $_POST['sq']) {
                    $this->throw_exception("授权码不正确");
                } else {
                    $opt['isSq'] = 1;
                }
            }

            $this->createSql()->update('user', ['+' => ['login_cnt' => 1], 'last_time' => time()], ['user_id' => $oauth['user_id']])->query();
            if ($is_google) drFun::setSession('google', $this->user_id);

            $this->appendUserLoginLog( $this->user_id, 1,$msg2 );
            if( $_POST['fr']=='cha'){ #来自查单员
                $c_uid = $this->cha2Cuser($oauth['user_id']  );
                $opt['u2']= $oauth['user_id'];
                $opt['pk']='7'; //查单元
                $this->regCookie( $c_uid , $opt);
            }else {
                $this->regCookie($oauth['user_id'], $opt);
            }

        }catch (drException $ex ){
            $msg= $ex->getMessage();
            $this->appendUserLoginLog( $this->user_id, 2, $msg . $msg2 );
            $this->throw_exception( $ex->getMessage(),$ex->getCode() );
        }

        $this->baopoLImit( ['clear'=>1 ]);


        return $duser ;

        //$this->createUserOne($oauth['user_id'] )->checkPsw( $psw );
    }

    function getRealUid(){
        $user_id= $this->getLogin()->getUserId();
        if( $this->getLogin()->getCookUser('u2') )  $user_id =$this->getLogin()->getCookUser('u2');
        return $user_id;
    }

    function appendUserLoginLog($user_id, $type,$msg=''){
        $ip= drFun::getIP();
        $var=['ctime'=>time(),'ip'=>$ip,'type'=>$type ,'error_des'=>$msg,'user_id'=>$user_id  ];

        $row = $this->createTableUserLoginLog()->getRowByWhere( ['ip'=>$ip,'!='=>['lo'=>''] ]);
        if($row['lo'] ){
            $var['lo']= $row['lo'];
        }else{
            //$this->createIpCity()->find( $ip );
            $ip_lo= $this->getLogin()->createIpCity()->find( $ip , 'CN');
            $var['lo']= $ip_lo[1]. $ip_lo[2] ;//strtr($ip_lo[1],['省'=>'','市'=>'' ] );

        }
        //$this->drExit( $var['lo'] );


        $this->createTableUserLoginLog()->append( $var );

        if(  $var['lo'] =='局域网') $this->throw_exception("非法登录", 19122105);


        if($msg=='') $msg='登录成功！';
        $this->getLogin()->createQrPay()->toTelegram( $user_id, "【登录操作】\n地区:". $var['lo']."\n".$msg );

        return $this;
        //?$row['lo']:drFun::getIP()
    }

    function loginByUid( $user_id ,$opt=[]  ){
        $this->regCookie( $user_id , $opt);
        return $this;
    }

    function doLogin( $email_tel_id, $psw ){

    }

    /**
     * 验证是否登录，如果登录获取登录
     * @return mixed
     * @throws drException
     */
    public static  function checkCookieUser(){
        //echo "1\n";
        if( ! isset( $_COOKIE['_UHAO']) && !self::$_uhao ) throw new drException( "未登录",362);
        $cvar =  json_decode( $_COOKIE['_UHAO']?$_COOKIE['_UHAO']:self::$_uhao  ,true );
        if(! $cvar ) throw new drException( "未登录",363);
        $sign= $cvar['sign'];
        unset( $cvar['sign']);
        $ch_sign = drFun::sign( $cvar,COOKIE_SECKEY );
        if( $sign != $ch_sign ) throw new drException( "非法登录" ,364); //.$ch_sign.'!='.$sign

        //$term = new term();
        if( !isset( self::$_saveCls['term']) and   !self::$_saveCls['term'] ){
            self::$_saveCls['term'] =  new term( );
        }
        //return self::$_saveCls['term']

        //if(( $cvar['ctime']< 1502341578|| $cvar['term'] !=self::$_saveCls['term']->getNow() ) &&  ! self::$is_reg_term ){
        if((  $cvar['term'] !=self::$_saveCls['term']->getNow() ) &&  ! self::$is_reg_term ){
            self::$is_reg_term =true;
            if( !isset( self::$_saveCls['login']) and  ! self::$_saveCls['login'] ){
                self::$_saveCls['login'] =  new login( );
            }
            //echo "dddd<br>";
            self::$_saveCls['login']->regCookie( $cvar['uid'] );

        }
        $cvar['sign']= $sign;
        return $cvar;
    }

    /**
     * 获取cookie信息 已经try过的
     * @return bool|mixed|null
     */
    public static function getCookieUser(){
        if(  self::$cookevar===null ){
            try{
                self::$cookevar = self::checkCookieUser();
            }catch ( drException $e ){
                self::$cookevar= false ;
            }

        }
        return self::$cookevar;
    }
    function setSchool( $school ){
        $this->school= $school;
        return $this;
    }
    function getSchool(){
        $shcool= $this->school===null ? $this->getCookUser('school'):  $this->school ;
        if( !$shcool )  return  false ;//$this->throw_exception( "该学校不存在！" );
        return $shcool;
    }

    function getSchoolID(   ){
        $school = $this->getSchool();
        if(!$school ) return false ;
        if( $this->schoolVar===[]    ){
            $this->schoolVar= $this->createClassBook()->getBookSchoolFromDB( $school  );
        }
        if( !isset($this->schoolVar['id']) ) return false ;//$this->throw_exception( "该学校不存在！" );
        return $this->schoolVar['id'];
    }


    /**
     * 获取cookie中的形象
     * @param string $tkey
     * @return bool|mixed|null
     */
    public function getCookUser( $tkey='all'){
        $re= self::getCookieUser();
        if( $tkey=='all')        return $re;
        if( isset( $re[$tkey])) return  $re[$tkey];
        return null;
        //$this->throw_exception( $tkey ."不存在！",371 );
    }

    /**
     * @param $user_id
     * @return one
     */
    public function createUserOne( $user_id=0 ) {
        if( $user_id==0 ) $user_id= $this->user_id;
        if( $user_id==0 ) $this->throw_exception( "user_id 参数错误！",365 );
        if( ! isset( self::$_one[$user_id]))          self::$_one[$user_id] = new one( $user_id);
        return  self::$_one[$user_id] ;
    }

    /**
     * 随机获取系统头像
     * @return string
     */
    public function getRandHead( $ts=1 ){
        $rand=['person','2ciyuan','classics','2ciyuan'];
        if( $this->isTeacher( $ts)){
            $rand=['classics', 'party'];
        }
        $all_img = [];
        foreach ($rand as $dir){
            $res = '/res/head/'.$dir;
            for($i=1;$i<=30; $i++ ){
                $all_img[]= $res. '/'.$i.".jpg";
            }
        }
        $cnt = count($all_img );
        $rk = rand( 0,$cnt-1);
        return $all_img[ $rk ];
    }

    /**
     * 注册cookie 一般在web登录之后 登录的凭证
     * @param int $user_id 用户uid
     * @param array $opt
     * @return $this
     * @throws
     */
    public function regCookie( $user_id,$opt=[] ){
        $userOne =$this->createUserOne( $user_id  )->clear();
        $duser = $userOne->getUser();
        $attr= $userOne->getAttr();

        $cvar =['uid'=>$duser['user_id'],'school'=> $duser['school'],'time'=>time(),'ts'=> $duser['ts'],'name'=>$duser['name']];
        foreach( $opt as $k=>$v )  $cvar[$k]=$v ;

        if( $duser['head']) $cvar['head']=  $duser['head'];
        else{
            $cvar['head']= $this->getRandHead( $duser['ts'] );
            $this->createSql()->update($this->tb_user,['head'=>$cvar['head'] ],['user_id'=>$user_id])->query();
        }
        if( $duser['number']) $cvar['number']=  $duser['number'];

        if( $attr ) $cvar['attr']= array_keys( $attr );

        #检查是否在当前学期用户表内，如果没有则重新添加下
        if(! $this->createTerm()->getUserTermByUid( $duser['user_id'] )){
            $termUser = $cvar;
            $this->createTerm()->addUserTerm($duser['user_id'],$termUser  );
        }
        $cvar['term']= $this->createTerm()->getNow();

        $old_cook= $this->getCookUser();
        if( $old_cook['uid']==$cvar['uid'] && isset( $old_cook['other'] ) ){
            $cvar['other']=  $old_cook['other'];
        }

        //$cvar['sign']= drFun::sign( $cvar, COOKIE_SECKEY );
        //drFun::setcookie('_UHAO', json_encode( $cvar),time()+30*24*3600 );
        $this->regCookieReal($cvar );
        return $this;

    }

    function regCookieReal( $cook ){
        $cook['sign']= drFun::sign( $cook, COOKIE_SECKEY );
        self::$_uhao =  json_encode( $cook);
        $time= time()+5*24*3600 ;
        drFun::setcookie('_UHAO',  self::$_uhao  ,$time );
        drFun::setcookie('_UIP',  $this->getIPSign(  $cook['sign'] )  ,$time ); //
        //drFun::setcookie('_UHAO',  self::$_uhao  ,time()+24*3600  );
        $this->initLoad();
        self::$cookevar = null;
        return $this;
    }

    function getIP2(){
        $ip= drFun::getIP();
        $tarr= explode('.' ,$ip );
        unset( $tarr[ count($tarr)-1]);
        unset( $tarr[ count($tarr)-1]);
        $ip2= implode('.', $tarr);
        return $ip2;
    }

    function getIPSign($sign){

        return md5( $sign .'qfs'. $this->getIP2() );
    }
    function checkIpSign(){

        $sign= $this->getIPSign( $this->getCookUser('sign') ) ;
        //if($sign!=$_COOKIE['_UIP'] ) $this->throw_exception("您的IP有变更，请重新登录", 996 );
        return $sign==$_COOKIE['_UIP'];
        //return true;
    }

    function checkGoogleLogin(){
        session_start();
        //print_r($_SESSION);
        return $this->getUserId()==$_SESSION['google'];
    }

    /**
     * 往cookie加入 other 的词汇
     * @param $arr
     * @return $this
     */
    function regCookieOther( $arr ){
        $cook = $this->getCookUser();
        if(! $arr || !is_array($arr) || !$cook ) return $this;
        foreach( $arr as $k3=>$v3 ){
            if( $v3===null){
                unset(  $cook['other'][ $k3 ] );
                continue;
            }
            $cook['other'][ $k3 ]= $v3 ;
        }
        $this->regCookieReal( $cook );
        return $this;
    }

    function getCookieOther( $k3 ){
        $cook= $this->getCookUser();
        if( isset(  $cook['other'][ $k3 ] )) return  $cook['other'][ $k3 ];
        return null;
    }
    function setCookieOther($k3,$v3){
        //$this->getLogin()->checkCookieUser();
        $cook = $this->getCookUser();
        //$this->assign('ck',$cook);
        if( !$cook ) return $this;
        if( $v3===null){
            unset(  $cook['other'][ $k3 ] );
        }else  $cook['other'][ $k3 ]= $v3 ;
        //$this->drExit( $cook );
        $this->regCookieReal( $cook );
        return $this;
    }



    public function loginByQQ(){

    }
    public function loginByWeixin(){

    }

    /**
     * 登出、注销cookie
     * @return $this
     */
    public function logout(){
        session_start();
        drFun::setcookie('_UHAO', '',time()-30*24*3600 );
        $_SESSION['google']='';
        unset(  $_SESSION['google'] );
        return $this;
    }

    /**
     * 添加注册用户返回user_id $var 必须包含 [openid=>'电话、邮箱、第三方唯一id',name=>'姓名','ts'=>'1为老师2为学生',school=>'学校',password=>'密码'] 选填['from_id'=>'来源']
     * @param array $var
     * @param array $opt
     * @return user_id
     * @throws drException
     */
    public function reg( $var ,$opt=[] ){
        $openid = trim( $var['openid']);
        $user = new user( );
        $from_id = $user->getOpenidOauthfrom( $openid  );
        if( ($from_id==1 || $from_id==2) && !isset($opt['noYzm']) ) {
            $this->checkYzm( $openid, $var['yzm']);
        }
        //$this->drExit( $var );
        $user_id = $user->add( $openid, $from_id , $var['name'],$var['ts'],[ 'school'=> $var['school'],'psw'=>$var['password']] );
        return $user_id;
    }

    /**
     * 验证（短信、邮件）验证码
     * @param string $openid 手机、邮箱
     * @param string $check_yzm
     * @return $this
     */
    public function checkYzm( $openid , $check_yzm ){
        $yzm = $this->createSql("select * from check_yzm where openid= :openid", [':openid' => $openid])->getRow();
        if( !$yzm ){
            $this->throw_exception( "请先获取验证码", 126 );
        }
        if( $check_yzm=='' ||  $yzm['yzm']!=$check_yzm ){
            $this->createSql()->update('check_yzm',['+'=>['cnt'=>1 ]],['check_id'=> $yzm['check_id']] )->query();
            //$this->log( 'yzm: ' . $check_yzm  );
            $this->throw_exception( "验证码错误", 106 );
        }
        $this->createSql()->delete('check_yzm',['check_id'=> $yzm['check_id']])->query();
        return $this;

    }

    /**
     * 验证码类型
     * @param string $type
     * @return array
     */
    function getTypeZym( $type='all' ){
        $typeArr = ['reg' => '注册','passwd'=>'重置密码','bind'=>'绑定','unbind'=>'解除绑定'];
        if( $type=='all') return $typeArr;
        if (!isset($typeArr[$type])) $this->throw_exception("发送验证码用途错误！请核实", 117);
        return $typeArr[$type];
    }

    /**
     * 通过电话或者邮箱 修改密码
     * @param string $openid 电话或者邮箱
     * @param string $password
     * @return $this
     * @throws drException
     */
    function changPasswordByOpenId( $openid, $password ){
        $user = new user();
        $from_id = $user->getOpenidOauthfrom($openid);
        $duser = $user->getUserOauth( $openid, $from_id );
        if( $duser['user_id']<= 0 )$this->throw_exception( $openid. "账号不存在！",128 );
        $userOne = new one( $duser['user_id']);
        $userOne->up_psw( $password );
        return $this;
    }

    /**
     * 发送验证码
     * @param string $openid 电话或者邮箱
     * @return array
     * @throws drException
     */
    public function sendyzm( $openid ,$type='reg' )
    {
        $this->getTypeZym( $type );
        $user = new user();
        $from_id = $user->getOpenidOauthfrom($openid);

        $duser = $user->getUserOauth( $openid, $from_id );
        if ($type == 'reg'&& $duser ) {
             $this->throw_exception( $openid. "已经注册过了！",118 );
        }elseif( $type == 'bind' && $duser){
            $this->throw_exception( $openid. "已经被绑定！",138 );
        }elseif( $type == 'passwd' && !$duser){
            //$duser = $user->getUserOauth( $openid, $from_id );
            $this->throw_exception( $openid. "账号不存在，可先注册！",128 );
        }



        $table = 'check_yzm';
        //$this->createSql()->delete( $table, wh)
        $row = $this->createSql("select * from check_yzm where openid= :openid",[':openid'=>$openid ])->getRow();

        if($row){
            $check_id= $row['check_id'];
            $yzm= $row['yzm'];

//            if( $row['send_cnt']>=5 ){
//                $this->createSql()->delete($table,['check_id'=> $check_id] )->query();
//                $row= false;
//
////                if( $row['ctime']> (time()-24*3600) ){
////                    $this->throw_exception("24小时之内仅能发5条短信",105 );
////                }
////                else{
////                }
//            }
        }
        if( !$row){
            $yzm = rand(100001,999999);
            //$yzm =123456; //测试阶段 验证码都为 123456
            $check_id = $this->createSql()->insert('check_yzm',['openid'=>$openid,'ctime'=>time(),'yzm'=>$yzm  ] )->query()->lastID();
            //$check_id = $this->createSql()->lastID();
        }
        $this->createSql()->update($table,['+'=>['send_cnt'=>1]],['check_id'=> $check_id] )->query();
        $re = ['from_id'=> $from_id ];

        if( $from_id==1) $re['mail']= $this->sendyzmByMail( $openid,$yzm,$type);

        if( $from_id==2) $re['tel']= $this->sendyzmByTel( $openid,$yzm,$type);


        return $re ;
    }



    /**
     * 发送邮件验证码
     * @param string $email
     * @param string $yzm
     * @param int $type
     * @param array $opt
     * @return bool
     */
    public function sendyzmByMail( $email,$yzm,$type,$opt=[] ){
        $title="好策".$this->getTypeZym($type)."验证码";
        $body = "好策".$this->getTypeZym($type) .'验证码为：'. $yzm ;
        //$this->drExit( 'debug');
        return drFun::sendMail( $email, $title, $body );
    }

    /**
     * 发送短信验证码
     *
     * 模板
     * - 您在好策（haoce.com）上注册的验证码为：{s}【好策】
     * - 您在好策（haoce.com）上重置密码的验证码为：{s}【好策】
     * - 您在好策（haoce.com）上绑定的验证码为：{s}【好策】
     * - 您在好策（haoce.com）上解除绑定的验证码为：{s}【好策】
     * - 您在好策（haoce.com）上获取的验证码为：{s}【好策】
     * @param string $tel 手机号码
     * @param string $yzm 验证码
     * @param int $type 方式方法
     * @return array
     */
    public function sendyzmByTel( $tel,$yzm,$type){
        $sms = new sms();
        //$content= "您在好策网（haoce.com）上".$this->getTypeZym($type) ."的验证码为：".$yzm."【好策】";
        $content= "您在好策（haoce.com）上".$this->getTypeZym($type) ."的验证码为：".$yzm."【好策】";
        $re=[];
        $sms->sendSms( $tel,$content,$re);
        return $re ;

    }

    /**
     * 验证图片验证码
     * @param string $imgyzm
     * @throws drException
     * @return $this
     */
    public function checkImgYzm( $imgyzm ){
        session_start();
        //if(  $_SESSION['hd_code']==''|| $_POST['imgyzm']=='' ||   $_SESSION['hd_code']!=$_POST['imgyzm']){
        if(  $_SESSION['hd_code']==''||$imgyzm=='' ||   $_SESSION['hd_code']!= $imgyzm ){
            $this->clearImgYzm();
            $this->throw_exception( '验证码错误，请点击图片重新获取！' );
        }
        $this->clearImgYzm();
        return $this;
    }

    public function checkVCodeYzm( $imgyzm){
        session_start();

        $arr= explode(',',$imgyzm);
        if( count($arr)!=4 ) $this->throw_exception("提交坐标有错误！");
        $v_code =  json_decode( $_SESSION['v_code']  ,true);
        if( !$v_code )$this->throw_exception("已失效,请点击图片重新获取");
        $this->checkVCodeYzmItem($arr[0],$arr[1] ,$v_code['text']);
        $this->checkVCodeYzmItem($arr[2],$arr[3] ,$v_code['text']);
        //$this->drExit($v_code);
        return $this;
    }

    private  function  checkVCodeYzmItem( $x,$y ,&$code){
        foreach($code as $k=>$v ){
            if( $x>=$v['min_x'] &&  $x<=$v['max_x'] && $y>=$v['min_y'] &&  $y<=$v['max_y'] ){
                unset( $code[$k]);
                return $this;
            }
        }
        $this->clearImgYzm('v_code');
        $this->throw_exception( '验证错误,请点击图片重新获取 ' );
        return $this;
    }


    public function checkSignYzm( $openid, $time,$sign ){
        $m_sign = md5($openid.'Hc333@3ao'. $time  );
        if($sign!= $m_sign)$this->throw_exception( '验证码错误，非法请求！' );
        return $this;
    }

    /**
     * 清除 session
     * @return $this
     */
    public function clearImgYzm( $key='hd_code'){
        session_start();
        $_SESSION[ $key ]='';
        unset(  $_SESSION[ $key] );
        return $this;
    }

    /**
     * @return cls
     * @throws drException
     */
    public function createClassCls(){
        if( $this->user_id<=0 ) $this->throw_exception("请先登录",119 );
        if( !isset( self::$_saveCls['cls'])) self::$_saveCls['cls'] = new cls(  $this->user_id );
        return self::$_saveCls['cls'];
    }


    public function checkTeacher(){
        //$cu = self::getCookieUser();
        if( !$this->isTeacher() ) $this->throw_exception( "该功能仅支持教师", 461 );
    }

    public function checkLogin(){
        if( !self::getCookieUser() ) {
            drFun::setcookie('loginback', $_SERVER['REQUEST_URI']);
            $this->redirect('login','请先登录','error');
        }
    }
    /**
     * @return qrpay
     * @throws drException
     */
    function createQrPay(){
        if( !isset( self::$_saveCls['qrpay'])){
            self::$_saveCls['qrpay'] = new qrpay( $this->isLogin()? $this->getUserId(): 2  );
        }

        return self::$_saveCls['qrpay'] ;
    }

    /**
     *
     * @param $account_id
     * @param $opt
     * @return taobaoapi
     * @throws drException
     */
    function createTaoboApi( $account_id ,$opt=[]  ){
        //$seesionKey
        //$sessionKey ='6102414e3bfaeb6ec448921e92c2215958c7b6f1ac6d4a73193337870';

        $account = $opt['account']? $opt['account']:  $this->createQrPay()->getAccountByID(  $account_id );
        //$this->drExit( $account );

        $arr = explode('|', $account['process'] );
        if( !$arr[0]  ||  !$arr[1]) $this->throw_exception( "账号为授权！"  );
        $sessionKey= $arr[0];
        $tb = new taobaoapi();
        $tb->setSession(  $sessionKey )->setRfToken($arr[1] )->setNick( $account['zhifu_name'] ); //zhifu_name
        return $tb;
    }

    /**
     * @return mixed|taobao
     */
    function createTaobao(){
        if( self::$_saveCls['taobao'] ) return  self::$_saveCls['taobao'];
        $taobao = new taobao();
        self::$_saveCls['taobao'] = $taobao;
        return $taobao;
    }

    /**
     * @return caibao
     */
    function createCaibao(){
        if( self::$_saveCls['caibao'] ) return  self::$_saveCls['caibao'];

        $caibao = new caibao();
        self::$_saveCls['caibao'] = $caibao ;

        return $caibao;
    }

    /**
     * @return logPay
     * @throws drException
     */
    function createPayLog(){

        if( !isset( self::$_saveCls['logPay'])){
            self::$_saveCls['logPay'] = new logPay('pay_log' , $this->isLogin()? $this->getUserId(): 2 );
        }
        return self::$_saveCls['logPay'] ;
    }

    /**
     * @return log
     * @throws drException
     */
    function createPayAccountLog(){
        if( !isset( self::$_saveCls['pay_account_log'])){
            self::$_saveCls['pay_account_log'] =  new log('pay_account_log', $this->getUserId() );
        }
        return self::$_saveCls['pay_account_log'] ;
    }

    /**
     * @return table
     */
    function createTablePayAccountAttr(){
        if(  isset( self::$_saveCls['pay_account_attr'] )) return  self::$_saveCls['pay_account_attr'] ;
        $tb = new table();
        $file=['attr_id','account_id','type','ctime','attr'];
        $tb->setFile($file)->setTable('pay_account_attr')->setKeyFile('attr_id');
        self::$_saveCls['pay_account_attr'] = $tb  ;
        return $tb;
    }

    function createTablePayAccount(){
        if(  isset( self::$_saveCls['table_pay_account'] )) return  self::$_saveCls['table_pay_account'] ;
        $tb = new table();
        $file_account=['fail_cnt_all','fail_cnt_day','fail_cnt','card_index','bank_id','account','zhifu_name','zhifu_account' ,'ctime','user_id','type','online','yuer','process','zhifu_realname','ali_uid','clienttime','ma_user_id','price_max','lo'];
        $tb->setFile($file_account)->setTable('pay_account')->setKeyFile('account_id');
        self::$_saveCls['table_pay_account'] = $tb  ;
        return $tb;
    }


    /**
     * @return know
     * @throws drException
     */
    public function createKnow(){
        if( $this->user_id<=0 ) $this->throw_exception("请先登录",119 );
        if( !isset( self::$_saveCls['know'])){
            self::$_saveCls['know'] = new know( );
            self::$_saveCls['know']->setUserId(   $this->user_id );
        }
        return self::$_saveCls['know'] ;
    }

    /**
     * @return book
     */
    public function createClassBook(){
        if( !isset( self::$_saveCls['book'])){
            $tem = new book( );
            $tem->setLogin( $this );
            //$school='广东金融学院';
            $school= $this->getCookUser('school');
            $tem->setSchool( $school )->setUserId( $this->user_id );
            self::$_saveCls['book'] = $tem;
        }
        return self::$_saveCls['book'] ;
    }

    /**
     * @return user
     */
    public function createUser( )
    {
        if( !isset( self::$_saveCls['user'])){
            $user = new user( );
            self::$_saveCls['user'] = $user;
        }
        return self::$_saveCls['user'] ;
    }

    /**
     * 实例一个 daily 类
     * @return daily
     */
    public function createDaily( )
    {
        if( !isset( self::$_saveCls['daily'])){
            $cls = new daily( );
            $cls->setLogin( $this );
            $cls->setUserID( $this->getUserId() );
            self::$_saveCls['daily'] = $cls;
        }
        return self::$_saveCls['daily'] ;
    }

    /**
     * 实例一个 Term类
     * @return term
     */
    public function createTerm(){
        if( !isset( self::$_saveCls['term'])){
            //$this->drExit( $this->getSchoolID() );
            self::$_saveCls['term'] =  new term( $this->getSchoolID() );
        }
        self::$_saveCls['term']->setSchoolID( $this->getSchoolID() );
        return self::$_saveCls['term'] ;
    }

    /**
     *
     * @return City
     */
    public function createIpCity(){
        if( !isset( self::$_saveCls['ip_city']) ){
            $ip_file= APP_PATH.'ipip/ipipfree.ipdb' ;
            self::$_saveCls['ip_city'] =  new City($ip_file );
            //$city=
        }

        return  self::$_saveCls['ip_city'] ;
    }

    /**
     * 实例一个 task 类
     * @return task
     */
    public function createTask(){
        if( !isset( self::$_saveCls['task'])){
            self::$_saveCls['task'] =  new task();//new term( );
        }
        return self::$_saveCls['task'] ;
    }

    /**
     * 实例一个 user_gt_log 类
     * @return log
     */
    public function createLogGt(){

        if( !isset( self::$_saveCls['gt_log'])){
            self::$_saveCls['gt_log'] =  new log('user_gt_log', $this->getUserId() );
        }
        return self::$_saveCls['gt_log'] ;
    }

    /**
     *
     * @return table
     */
    public function createTableTransfer(){
        if(   isset( self::$_saveCls['table_transfer'])) return self::$_saveCls['table_transfer'];

        $tb = new table();
        $file= ['fee','type','ctime','etime','account_id','user_id'] ;
        $tb->setFile($file )->setTable( 'transfer')->setKeyFile( 'tf_id');
        self::$_saveCls['table_transfer'] = $tb ;
        return $tb ;
    }

    /**
     * @param $account_id
     * @return weiboserver
     */
    public function createWeiboServer($account_id ){

        if( self::$_saveCls['weibo_server'][ $account_id ] ) return self::$_saveCls['weibo_server'][ $account_id ];

        $tb= new weiboserver( $account_id );
        self::$_saveCls['weibo_server'][ $account_id ] = $tb ;
        return $tb;
    }


    /**
     *
     * @return table
     */
    public function createTablePayLogTem( ){
        if(   isset( self::$_saveCls['TablePayLogTem'])) return self::$_saveCls['TablePayLogTem'];

        $tb = new table();
        $file= ['ali_trade_no', 'ali_uid','ali_beizhu','account_ali_uid','fee','account_id','type','ctime','data','realprice'] ;
        $tb->setFile($file )->setTable( 'pay_log_tem')->setKeyFile( 'pt_id');
        self::$_saveCls['TablePayLogTem'] = $tb ;
        return $tb ;
    }

    /**
     * @return table
     */
    public function createTableQun(){
        if(   isset( self::$_saveCls['TableQun'])) return self::$_saveCls['TableQun'];
        $tb = new table();
        $file=['qid','account_id','chat_uid','chatroom','ctime','type','user_id','ma_user_id','name','opt_value','owner','member_count','live_count'];
        $tb->setFile($file )->setTable( 'qun')->setKeyFile( 'qid');
        self::$_saveCls['TableQun'] = $tb ;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableQunHongBao(){
        if(   isset( self::$_saveCls['table_qun_hong_bao'])) return self::$_saveCls['table_qun_hong_bao'];
        $tb= new table();
        $file=['hid','chatroom' ,'account_id' ,'user_id','type','hb_id','fee','beizhu','r_cnt','a_cnt','ctime','opt_value'];
        $tb->setKeyFile($file)->setKeyFile('hid')->setTable('qun_hongbao');
        self::$_saveCls['table_qun_hong_bao'] = $tb ;
        return $tb;
    }

    /**
     * @return weiboHelp
     */
    public function createWeiboHelp(){
        if(   isset( self::$_saveCls['weiboHelp'])) return self::$_saveCls['weiboHelp'];
        $obj= new weiboHelp();
        self::$_saveCls['weiboHelp'] = $obj ;
        return $obj;
    }

    /**
     * @return y10086
     */
    public function createY10086(){

        $obj= new y10086();
        return $obj;
    }

    /**
     * @return mserver
     */
    public function createMServer(){

        $mserver = new mserver();
        return $mserver;
    }

    /**
     * @return table
     */
    public function createTablePayLog(){

        if(   isset( self::$_saveCls['TablePayLogTem2'])) return self::$_saveCls['TablePayLogTem2'];

        $tb = new table();
        $file = ['id','opt_id','opt_type','user_id','ctime','opt_value','ltime','fee','account_id','pay_type','qr_id','trade_id','ip','md5','ali_uid','buyer','ali_trade_no','ali_beizhu','ali_account','ma_user_id'];
        $tb->setFile($file)->setTable('pay_log')->setKeyFile('id');
        self::$_saveCls['TablePayLogTem2'] = $tb ;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTablePaySms(){
        if(   isset( self::$_saveCls['TablePayLogSms'])) return self::$_saveCls['TablePayLogSms'];
        $tb= new table();
        $file=['sms_id','account_id','text','ctime'];
        $tb->setTable('pay_sms')->setKeyFile($file)->setKeyFile('sms_id');
        self::$_saveCls['TablePayLogSms'] = $tb ;
        return $tb ;
    }

    /**
     * @return table
     */
    public function createTableHfTrade(){
        if(   isset( self::$_saveCls['TableHfTrade'])) return self::$_saveCls['TableHfTrade'];

        $tb= new table();
        $file=['hf_id','tel','merchant_id','user_id','ctime','ma_user_id','fee_type','type','ali_trade_no','account_id','trade_id','pay_log_id','order_no','notify_url','notify_success','notify_cnt','notify_time','ali_trade_ctime','pay_time','fee','opt_value','endtime'];
        $tb->setTable('hf_trade')->setKeyFile('hf_id')->setFile($file );
        self::$_saveCls['TableHfTrade'] = $tb ;
        return $tb;
    }

    public function getHuaFeiMcCuser( $mc_id='all'){
        $mc= [8080=>4];
        if($mc_id=='all') return $mc;
        if( !isset($mc[ $mc_id ]) ) $this->throw_exception( "该商务非话费订单商户" ,20010809);

        return $mc[ $mc_id ];
    }

    /**
     * @return table
     */
    public function createTableExport(){

        /*
        if(   isset( self::$_saveCls['table_export'])) return self::$_saveCls['table_export'];
        $tb = new table();
        $tb->setTable('mc_export');
        self::$_saveCls['table_export'] = $tb ;
        return $tb;
        */
        return $this->createTableMcExport();
    }

    /**
     * @return table
     */
    public function createTableTrade( $type=1){
        if(  $type==1 &&  isset( self::$_saveCls['table_trade'])) return self::$_saveCls['table_trade'];

        $tb = new table(); //mc_trade
        $file= [ 'yue', 'rate','merchant_id','pay_type','price','goods_name','notify_url','order_no','order_user_id','attach','realprice','ctime','qr_id','account_id'];
        if( $type==2 ){
            $file= ['yue','rate','type','user_id','ma_user_id','pay_log_id','pay_time','notify_time','notify_cnt','notify_success' ,'merchant_id','pay_type','price','goods_name','notify_url','order_no','order_user_id','attach','realprice','ctime','qr_id','account_id'];
        }
        $tb->setFile($file)->setTable( 'mc_trade')->setKeyFile( 'trade_id');

        if(  $type==1 )self::$_saveCls['table_trade'] = $tb ;
        return $tb ;
    }

    /**
     * @return table
     */
    public function createTableTradeKV(){
        if(   isset( self::$_saveCls['table_trade_kv'])) return self::$_saveCls['table_trade_kv'];
        $tb = new table();
        $file= ['trade_id','value'];
        $tb->setTable('mc_trade_kv')->setFile( $file)->setKeyFile('trade_id' );
        self::$_saveCls['table_trade_kv'] = $tb ;
        return $tb ;
    }


    /**
     * 是否开通服务商
     * 1.开通服务商 服务商!=码商 费率是按占比算
     * 2.开通服务商 服务商=码商  费率是固定的3元
     * @param $c_user_id
     * @return int
     */
    public function isKaiTongFWS( $c_user_id){
        $arr=[];
        $arr=[4335=>2,4468=>2,4467=>2,4902=>2,4761=>2]; //'4'=>2,
        if( isset( $arr[$c_user_id])) return $arr[$c_user_id];
        return 0;
    }

    /**
     * 是否开通商户代理
     * @param $c_user_id
     * @return int
     */
    public function isSWDL( $c_user_id){
        $arr=[4=>1,4373=>1,7=>1,1619=>1,2321=>1]; //4373,7,1619
        if( isset( $arr[$c_user_id])) return $arr[$c_user_id];
        return 0;
    }

    /**
     * 充值之前是否询问 是否合格
     * 1.48小时内金额不重用
     * 2.3分钟不能重复提交
     * @param $c_user_id
     * @return bool
     */
    public function isCZCheckBefore($c_user_id){

        //$arr=[];
        $arr=[2650,3310]; //4
        return in_array( $c_user_id, $arr );

    }

    /**
     *
     * @return table
     */
    public function createTableUserMa(){

        if(   isset( self::$_saveCls['table_user_ma'])) return self::$_saveCls['table_user_ma'];
        $tb = new table();
        $file= ['user_id','m_user_id','role','c_user_id', 'fee','realname','card_id','card_bank','card_address','type','tel', 'qq','sfz','amount','amount_success','live_time','psw'];
        $tb->setTable("user_ma")->setFile($file)->setKeyFile('user_id');
        self::$_saveCls['table_user_ma']= $tb;
        return $tb;
    }
    /**
     *
     * @return table
     */
    public function createTableMaBill(){

        if(   isset( self::$_saveCls['table_ma_bill'])) return self::$_saveCls['table_ma_bill'];

        $tb = new table();
        $file= ['mb_id','ma_user_id','c_user_id','ctime','dtime','type','price','realprice','amount','beizhu','opt_value','ip'];
        $tb->setTable("ma_bill")->setFile($file)->setKeyFile('mb_id' );
        self::$_saveCls['table_ma_bill']= $tb;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableHei(){

        if(   isset( self::$_saveCls['table_hei'])) return self::$_saveCls['table_hei'];

        $tb= new table();
        $file= ['hid','user_id','cookie'];
        $tb->setTable("mc_hei")->setFile($file)->setKeyFile('hid');
        self::$_saveCls['table_hei']= $tb;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableUserLoginLog(){

        if( isset( self::$_saveCls['table_user_login_log'] )) return self::$_saveCls['table_user_login_log'];
        $tb= new table();
        $file=['login_id','user_id','ctime','lo','ip','type','error_des'];
        $tb->setTable('user_login_log')->setFile($file)->setKeyFile('login_id');
        self::$_saveCls['table_user_login_log']= $tb;
        return $tb;
    }

    /**
     *
     * @return table
     */
    public function createTableMaBillLog(){
        if(   isset( self::$_saveCls['table_ma_bill_log'])) return self::$_saveCls['table_ma_bill_log'];
        $tb = new table();
        $file= ['id','mb_id','ma_user_id','c_user_id','ctime' ,'type' ,'realprice','amount','beizhu' ];
        $tb->setTable("ma_bill_log")->setFile($file)->setKeyFile('mb_id' );
        self::$_saveCls['table_ma_bill_log']= $tb;
        return $tb;
    }
    /**
     *
     * @return table
     */
    public function createTableMcExport(){
        if(   isset( self::$_saveCls['table_mx_export'])) return self::$_saveCls['table_mx_export'];
        $tb = new table();
        $file= ['ma_user_id','money','real_money','export_id' ,'merchant_id','merchant_user_id','ctime','cz_user_id','cz_time','type','card_id','card_name','card_bank','card_address','notify_url','order_no','mc_ip','cz_ip','mc_lo','opt_value'];
        $tb->setTable("mc_export")->setFile($file)->setKeyFile('export_id' );
        self::$_saveCls['table_mx_export']= $tb;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableMaCard(){
        if(   isset( self::$_saveCls['table_ma_card'])) return self::$_saveCls['table_ma_card'];
        $tb= new table();
        $file=['c_user_id','ctime','c_name','c_id','c_bank','c_add','ma_user_id'];
        $tb->setFile($file)->setTable('ma_card')->setKeyFile('id');
        self::$_saveCls['table_ma_card']= $tb;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableSearchLog(){
        if(   isset( self::$_saveCls['table_search_log'])) return self::$_saveCls['table_search_log'];
        $tb= new table();
        $file=['user_id','ctime','q','type' ];
        $tb->setFile($file)->setTable('search_log')->setKeyFile('id');
        self::$_saveCls['table_search_log']= $tb;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableMaFuwu(){
        if(   isset( self::$_saveCls['table_ma_fuwu'])) return self::$_saveCls['table_ma_fuwu'];
        $tb= new table();
        $file = ['ctime','c_id','c_name','c_add','c_bank','user_id','c_user_id','ma_user_id','ma_bill_id','opt_id','type','opt_type','utime','realprice','opt_value'];
        $tb->setKeyFile($file)->setTable('ma_fuwu')->setKeyFile('fw_id');
        self::$_saveCls['table_ma_fuwu']= $tb;
        return $tb ;
    }

    /**
     * @return table
     */
    public function createTableMerchant(){
        if(   isset( self::$_saveCls['table_merchant'])) return self::$_saveCls['table_merchant'];
        $tb = new table();
        $file= ['type','merchant_id','merchant','user_id','ctime','app_id','app_secret','rate', 'last_day','last_export_id','clear_time','fa_fee','pay_type' ];
        $file[]='pid';
        $file[]='child_len';
        $file[]='c_user_id';
        $file[]='da_user_id';
        $file[]='da_fee';
        $tb->setTable("merchant")->setFile($file)->setKeyFile('merchant_id' );
        self::$_saveCls['table_merchant']= $tb;

        return $tb;
    }

    /**
     * @return table
     */
    public function createTableUserWx(){
        if(   isset( self::$_saveCls['table_user_wx'])) return self::$_saveCls['table_user_wx'];
        $tb= new table();
        $file=['id','me_id','friend_id','chatroom','ctime','utime','friend_name'];
        $tb->setTable("user_wx")->setFile($file)->setKeyFile('id' );
        self::$_saveCls['table_user_wx']= $tb;
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableAccountOnline(){
        if(   isset( self::$_saveCls['table_account_online'])) return self::$_saveCls['table_account_online'];
        $tb = new table();
        $file= ['account_id','type','clienttime'];
        //pay_account_online
        $tb->setTable( 'pay_account_online')->setFile( $file)->setKeyFile('account_id');
        return $tb;
    }

    /**
     * @return table
     */
    public function createTableTaobaoQr(){
        if(   isset( self::$_saveCls['table_tb_qr'])) return self::$_saveCls['table_tb_qr'];
        $tb= new table();
        $file=['qr_id','created','buyer','biz_no','alipay_no','tid','ctime','type','trade_id','pay_time','ma_user_id','user_id','account_id','qr_text','opt_value','fee','mobile'];
        $tb->setTable('tb_qr')->setFile($file)->setKeyFile( 'qr_id');
        self::$_saveCls['table_tb_qr']= $tb;
        return $tb;
    }

    /**
     * c_user_id 订单排队
     * c_user_id+20000000 会员对服务商排队充值 服务商排队
     * @return table
     */
    public function createTablePayRank(){
        if(   isset( self::$_saveCls['table_pay_rank'])) return self::$_saveCls['table_pay_rank'];
        $tb = new table();
        $file=['id','c_user_id','ma_user_id','utime','account_id'];
        $tb->setTable('pay_rank')->setFile($file)->setKeyFile( 'id');
        self::$_saveCls['table_pay_rank']= $tb;
        return $tb;
    }

    /**
     * 判断是否系统管理员
     * @return bool|int
     */
    function isAdmin(){
        $attr = $this->getCookUser('attr');
        if( !$attr ) return false ;
        if( in_array('p1', $attr)) return 1;
        if( in_array('p2', $attr)) return 2;
        return false ;
    }

    /**
     * 是否学校管理员
     * @return bool|int
     */
    function isSchoolAdmin(){
        $attr = $this->getCookUser('attr');
        if( !$attr ) return false ;
        if( in_array('p3', $attr)) return 3;
        return false ;
    }

    /**
     * 判断身份
     * @param $p
     * @return bool
     */
    function isShenfen( $p ){
        $attr = $this->getCookUser('attr');
        if( !$attr ) return false ;
        if( in_array($p , $attr)) return  $p ;
        return false ;
    }

    /**
     * 实例一个 user_recycle_log 类
     * @return log
     */
    public function createLogRecycle(){
        if( !isset( self::$_saveCls['recycle'])){
            self::$_saveCls['recycle'] =  new log('user_recycle_log', $this->getUserId() );
        }
        return self::$_saveCls['recycle'] ;
    }
    /**
     * 实例一个 pad_log 类
     * @return log
     * @throws drException
     */
    public function createLogPad(){
        if( !isset( self::$_saveCls['pad_log'])){
            self::$_saveCls['pad_log'] =  new log('pad_log', $this->getUserId() );
        }
        return self::$_saveCls['pad_log'] ;
    }

    /**
     * 实例一个 set_log 类
     * @return log
     */
    public function createLogSetting(){
        if( !isset( self::$_saveCls['set_log'])){
            self::$_saveCls['set_log'] =  new log('set_log', $this->getUserId() );
        }
        return self::$_saveCls['set_log'] ;
    }

    /**
     * 实例一个 wenda_attr 类
     * @return attr
     */
    function createWendaAttr(){
        if( !isset( self::$_saveCls['wenda_attr'])){
            self::$_saveCls['wenda_attr'] = $attr= new attr('wenda_attr');
        }
        return  self::$_saveCls['wenda_attr'] ;
    }
    /**
     * 实例一个 chat_log 类
     * @return log
     */
    public function createLogChat(){
        if( !isset( self::$_saveCls['chat'])){
            self::$_saveCls['chat'] =  new log('chat_log', $this->getUserId() );
        }
        return self::$_saveCls['chat'] ;
    }

    /**
     * 实例一个 wenda_log 类
     * @return log
     */
    public function createLogWenda(){
        if( !isset( self::$_saveCls['wenda_log'])){
            self::$_saveCls['wenda_log'] =  new log('wenda_log', $this->getUserId() );
        }
        return self::$_saveCls['wenda_log'] ;
    }

    /**
     * 发送注册密码
     * @param $openid
     * @param $psw
     * @return array
     */
    function sendRegPsw($openid, $psw ,$opt=[]){
        $from_id = $this->createUser()->getOpenidOauthfrom( $openid );
        $re=['from_id'=> $from_id ];
        switch ($from_id ){
            case 1: #mail

                break;
            case 2:# tel
                if( $opt['ts']==2 ){ #是学生
                    $title= ( isset($opt['name'])&&$opt['name']) ? mb_substr($opt['name'],0,1,'utf-8' ).'同学您好': '同学您好';
                }else {
                    $title= ( isset($opt['name'])&&$opt['name'])? mb_substr($opt['name'],0,1,'utf-8' ).'老师您好': '老师好';
                }
                if ($openid == $psw) {
                    $body = $title.'，您的好策读书账户已开通，账户名和初始密码均为' . $psw . '，请登录haoce.com体验【好策】';
                } else {
                    $body = $title.'，您的好策读书账户已开通，账户名为您的手机号码'.$openid.'、初始密码为' . $psw . '，请登录haoce.com体验【好策】';
                }
                $sms = new sms();
                $sms->sendSms( $openid, $body ,$re );
                break;
        }
        return $re ;
    }

    /**
     * 新建一个云通信 TimRestAPI
     * @return TimRestAPI
     */
    function createTim(){
        if( !isset( self::$_saveCls['tim'])){
            $tim  =  new TimRestAPI();
            $tim->set_file_private_key( ROOT_PATH.'/config/keys/private_key' , ROOT_PATH.'/config/keys/linux-signature64' )->init( 1400038428,'haoceAdmin');
            //haoceAdmin sign
            $sign= 'eJxNzVtPwjAYgOH-sluN*dq1ZHo3DsMBCxtogldNaTuoQLesnTIN-91mmdHb9-kO38HLavvAhaha45jrahU8BRDc91lLZZwutWp8PPJKqFhetBmU17WWjDsWNvLfkpUn1pNviABAGBEcDaiutW4U46XrbyJKKfYjg36oxurKeMCAKMIhwB86fVH9ChCKIiD4958**JzN3iZpMV2GPG7H8xnarQHmC-r8lS-dhGcbe5fQHBWg6OM5OfGOFukxzolKxGcis82C797Lqxih1O5bs35t93JMusqWWzM9rEbxObj9AMy8WOE_';
            $tim->set_user_sig( $sign);
            self::$_saveCls['tim']= $tim;
        }
        return self::$_saveCls['tim'] ;
    }

    /**
     * 判断是不是老师 目前 1一般老师，3是认证老师
     * @param int $ts
     * @return bool|int
     */
    function isTeacher( $ts=null ){
        if( $ts===null ) $ts = $this->getCookUser('ts');
        $ts_arr =[1=>'一般老师',3=>'认证老师'];
        if( isset( $ts_arr[ $ts] )) return $ts;
        return false ;
    }

    /**
     * 获取缓存
     * @return cache
     */
    function createCache(){
        if( !isset( self::$_saveCls['cache'])){
            $cache = new cache();
            self::$_saveCls['cache']= $cache;
        }
        return self::$_saveCls['cache'];
    }

    /**
     * @return novel
     */
    function createNovel(){
        if( !isset( self::$_saveCls['novel'])){
            $nc = new novel( );
            $nc->setLogin( $this);
            $nc->setUserID( $this->getUserId() );
            self::$_saveCls['novel']= $nc;
        }
        return self::$_saveCls['novel'] ;
    }

    /**
     * @return export
     */
    function createExport(){
        if( !isset( self::$_saveCls['export'])){
            self::$_saveCls['export']= new export( );
        }
        return self::$_saveCls['export'] ;
    }

    /**
     * @return snt
     */
    function createSnt(){
        if( !isset( self::$_saveCls['snt'])){
            self::$_saveCls['snt']=  new snt();
        }
        return self::$_saveCls['snt'] ;
    }

    /**
     * @return tag
     */
    function createTagNovel( ){
        if( !isset( self::$_saveCls['tagNovel'])){
            self::$_saveCls['tagNovel']  = new tag(1);
        }
        return self::$_saveCls['tagNovel'];
    }

    /**
     *
     * @return text
     */
    function createText(){
        if( !isset( self::$_saveCls['text'])){
            $text = new  text();
            $text->setLogin($this);
            self::$_saveCls['text'] = $text;
        }
        return  self::$_saveCls['text'];
    }

    /**
     * @return wenda
     */
    function createWenda(){
        if( !isset( self::$_saveCls['wenda'])) {
            $wenda = new wenda();
            $wenda->setLogin($this);
            self::$_saveCls['wenda'] = $wenda;
        }
        return  self::$_saveCls['wenda'] ;
    }
    /**
     * @return block
     */
    function createBlock(){
        if( !isset( self::$_saveCls['block'] ) ){
            $tem= new block();
            $tem->setLogin( $this );
            self::$_saveCls['block'] = $tem;
        }
        return  self::$_saveCls['block'] ;
    }

    /**
     * @return vip
     */
    function createVip(){

        if( !isset( self::$_saveCls['cls_vip'] ) ){
            $tem= new vip();;
            $tem->setLogin( $this );
            self::$_saveCls['cls_vip'] = $tem;
        }
        return  self::$_saveCls['cls_vip'];
    }



    /**
     * @return test
     */
    function createTest(){
        if( !isset( self::$_saveCls['test'] ) ){
            $tem=new test();
            $tem->setLogin( $this );
            self::$_saveCls['test'] = $tem;
        }
        return  self::$_saveCls['test'] ;
    }

    /**
     * @return google_authenticator
     */
    public function createGoogleAuthenticator(){
        //google_authenticator
        self::$_saveCls['google_authenticator'] = new google_authenticator();
        return  self::$_saveCls['google_authenticator'];
    }

    /**
     * 获取学期的key并check
     * @param string $term
     * @return string
     */
    function getCheckTerm( $term='now' ){
        if( $term=='now' || trim( $term )=='' ) return $this->createTerm()->getNow( $this->getSchoolID() );
        $this->createTerm()->getConfig(  $term );
        return $term ;
    }

    /**
     * pad日志情况增加
     * @param $opt_id
     * @param $opt_type
     * @param $opt_value
     * @return $this
     */
    function padLogAdd( $opt_id, $opt_type,$opt_value){
        try{
            $this->createLogPad()->append($opt_id, $opt_type,$opt_value,['school_id'=>$this->getSchoolID(),'school'=>$this->getSchool(),'name'=> $this->getCookUser('name') ] );
        }catch (drException $ex ){      }

        return $this;
    }

    /**
     * 是否全校开通
     * - 0:全校未开通
     * - 1:全校开通并使用白名单
     * @return int
     */
    function isSchoolAll(){
        $school = $this->getSchool();
        if( !$school ) return 0;
        $arr=['好策'=>1001,'安徽建筑大学城市建设学院'=>1001 ];
        if( isset( $arr[ $school]) ) return $arr[ $school];
        return 0;
    }

    /**
     * 财务对应操作员表
     * @param int $user_id
     * @return mixed
     * @throws drException
     */
    public function financeUser( $user_id=0){
        $mc[9]=[8,12,18,15,21,23,25 ]; //各财务权限表格 操作员 ,13 ,19
        $mc[5]=[8,12,18,15,21,23,25 ]; //各财务权限表格 操作员 ,13 ,19
        $mc[7]=[4]; //各财务权限表格
        $mc[17]=[15]; // 各财务权限表格 操作员
        $mc[1172]=  [606,792]; // 西安 操作员
        $mc[1221]=  [1186, 1187, 1185,1210,1557,1630]; // 南哥 操作员
        //$mc[1574]=  [97,98,111,1088,1037,1038,1193,1211,1040,1959]; // 98K 操作员
        $mc[1574]=  [3190,3191]; // 98K 操作员

        $mc[1619]=  [ 2650,4335,4467,4468,4647,4649,4761,4902,5063,5082,5073,5124]; // 老周 操作员 //798 ,324,356,555,1633,1949,2337,2650,3125,3310,3349,4335,4467,4468

        $mc[1039]= $mc[1056] =[606,989,784,792,1079,1102,1101,1197]; // 西安 操作员 KY852 FW852
        $mc[1072]=  [1062,1099,1108]; // 老板 操作员 caiwu2019
        $mc[2002]=  [1943,1062,1099,1108]; // 老板 操作员 cw2020

        $mc[2321]=  [2322,2323,2333,2438,2695,2808,2910]; // 南哥2020 nan2020

        $mc[2692]=  [2691,2645,2862,4518,5084,5093]; // ping 平 船长
        $mc[3251]=  [3207,4408,5107,5122]; // laowang 老王
        $mc[3306]=  [3305,3849,4865]; // kong 虚空
        $mc[4373]=  [4368,4628]; //wuge 五哥

        if( $user_id==5){
            return 'all';
        }
        if( $user_id==='all' )     return $mc ;
        if( $user_id<=0 ) $user_id = $this->getUserId();
        if( !isset($mc[$user_id] ) ) $this->throw_exception( "用户未有财务权限",78);
        return $mc[$user_id] ;
    }


    /**
     * 财务对于商户表
     * @param int $user_id
     * @return mixed
     * @throws drException
     */
    public function financeMid(  $user_id=0 ){
        $mc[9]=[ ]; //各财务权限表格 商户
        $mc[5]=[]; //各财务权限表格 商户
        $mc[17]=[8111];
        $mc[9]=[8133,8266,8088 ];

        if( $user_id==='all' )     return $mc ;


        if( $user_id<=0 ) $user_id = $this->getUserId();
        if( isset($mc[$user_id] ) )  return $mc[$user_id] ;

        $c_user_id_arr = $this->financeUser( $user_id );

        $mc_arr=  $this->midConsole();
        $remc=[];
        foreach( $mc_arr as $mid=>$user_arr ){
            if( in_array($user_arr[0], $c_user_id_arr ) )  $remc[]=$mid;
        }
        if( $c_user_id_arr ) {
            $mid2 = $this->getLogin()->createTableMerchant()->getColByWhere(['c_user_id' => $c_user_id_arr], ['merchant_id']);
            $remc = array_merge($remc, $mid2);
        }
        if( $remc) return $remc;


        $this->throw_exception( "用户未有财务权限",780 );
        return $mc[$user_id] ;
    }

    function mergeMerchantIdByCUser( &$mid, $c_user_id){
        if( !$c_user_id) return $this;
        $mid2 = $this->getLogin()->createTableMerchant()->getColByWhere(['c_user_id' => $c_user_id], ['merchant_id']);
        $mid = array_merge($mid, $mid2);
        return $this;
    }

    /**
     * 商户对应操作员表
     * @param string $mid
     * @return array|mixed
     * @throws drException
     */
    public function midConsole( $mid ='all' ){
        $mc_id_u= [];
        $mc_id_u['7080']=[4];

        $mc_id_u['8080']=[4]; //测试
        //$mc_id_u['8080']=[598]; //测试

        $mc_id_u['8223']=[48];  //厦门天才 C093

        /*
        $mc_id_u['8222']=[37];  //厦门天才 C118
        //$mc_id_u['8225']=[47];  //厦门天才 银河
        $mc_id_u['8233']=[38];  //厦门天才 998


        //$mc_id_u['8223']=[84];  //厦门天才 T093
       // $mc_id_u['8223']=[79];  //厦门天才 C501

        $mc_id_u['8225']=[47];  //厦门天才 银河 038
        //$mc_id_u['8225']=[86];  //厦门天才 银河 T038

        //$mc_id_u['8223']=[79];  //厦门天才 093
        $mc_id_u['8226']=[48];  //厦门天才 CMX MXCP
        //$mc_id_u['8226']=[85];  //厦门天才 TMX MXCP
        $mc_id_u['8226']=[79];  //厦门天才 T093 MXCP
        //$mc_id_u['8226']=[64];  //厦门天才 CMX MXCP

        $mc_id_u['8226']=[47];  //厦门天才 CMX MXCP
        $mc_id_u['8226']=[74];  //厦门天才 CMX MXCP


        $mc_id_u['8227']=[65];  //厦门天才 CQY QYCP
        $mc_id_u['8227']=[188];  //厦门天才 C503 QYCP
        $mc_id_u['8228']=[82];  //厦门天才 CJX jiaxiang
        $mc_id_u['8228']=[104];  //厦门天才 CJX C502
        $mc_id_u['8229']=[87];  //厦门天才 CDC DDC
        $mc_id_u['8229']=[200];  //厦门天才 CDC C387
        $mc_id_u['8230']=[88];  //厦门天才 CXP XPJ
        //$mc_id_u['8230']=[79];  //厦门天才 CXP 501
        $mc_id_u['8230']=[105];  //厦门天才 CXP C354

        $mc_id_u['8231']=[217];  //厦门天才 C231
        $mc_id_u['8232']=[267];  //厦门天才 C232 8232 267
        $mc_id_u['8234']=[269];  //厦门天才 C231 8234
        */

        //$mc_id_u['8200']=[ 32 ]; //KCTest
        //$mc_id_u['8201']=[ 324 ]; //CQQ
        $mc_id_u['8201']=[ 34 ]; //CQQ

        /*
        //$mc_id_u['8088']=[8]; //wx
        //$mc_id_u['8088']=[15]; //
        //$mc_id_u['8100']=[13]; //九九

        $mc_id_u['8100']=[18]; //九九 18

        //$mc_id_u['8155']=[18]; //星河娱乐
        //$mc_id_u['8199']=[18]; //大玩家
        //$mc_id_u['8211']=[18]; //777电玩城

        $mc_id_u['8111']=[15]; //淘金
        // //太阳城
        //$mc_id_u['8133']=[21]; //开心娱乐
        $mc_id_u['8133']=[15]; //开心娱乐

        //$mc_id_u['8155']=[23]; //星河娱乐
        //$mc_id_u['8199']=[23]; //大玩家


        //$mc_id_u['8099']=[12]; //UU
        $mc_id_u['8099']=[25]; //UU
        //$mc_id_u['8166']=[25]; //凤凰娱乐
        $mc_id_u['8177']=[25]; //封侯娱乐
        $mc_id_u['8168']=[25]; //168娱乐
        $mc_id_u['8188']=[25]; //乐高娱乐
        $mc_id_u['8088']=[15]; //五星
        */

        #$mc_id_u['8111']=[104]; //淘金 尊享娱乐 C502
        //$mc_id_u['8111']=[111]; //淘金 尊享娱乐 C353


        /*
        $mc_id_u['8255']=[40]; //凯优光实业1
        $mc_id_u['8256']=[59]; //凯优光实业6 cyg2
        $mc_id_u['8257']=[93]; //凯优光实业7 cyg3
        $mc_id_u['8258']=[40]; //凯优光实业8
        */
        //$mc_id_u['8122']=[4];

        $mc_id_u['8251']=[97]; //98k C251
        $mc_id_u['8252']=[98]; //98k C252
        $mc_id_u['8253']=[111]; //98k C253


        //$mc_id_u['8562']=[98]; //老板.支付宝

        $mc_id_u['8262']=[1037]; //98k C262
        $mc_id_u['8263']=[1038]; //98k C263
        $mc_id_u['8261']=[1088]; //98k C261
        $mc_id_u['8264']=[1193]; //98k C264
        $mc_id_u['8265']=[1211]; //98k C265

        $mc_id_u['8267']=[1040]; //98k C267
        $mc_id_u['8268']=[1041]; //98k C268
        /*
        $mc_id_u['8371']=[112]; //JJF C371
        $mc_id_u['8372']=[113]; //JJF C372
        $mc_id_u['8373']=[114]; //JJF C373
        */

        $mc_id_u['8371']=[317]; //平 支转卡 C371
        $mc_id_u['8372']=[115]; //平 云闪付 C372
        $mc_id_u['8372']=[798]; //周 云闪付 C372


        $mc_id_u['8388']=[115]; //湖南 C388 平测试
        $mc_id_u['8387']=[200]; //湖南 C387 平
        $mc_id_u['8389']=[317]; //湖南 C389
        $mc_id_u['8386']=[1091]; //湖南 C386
        $mc_id_u['8385']=[1103]; //湖南 C385

        $mc_id_u['8383']=[1219]; //湖南 C383

        $mc_id_u['8609']=[1219]; //湖南 C383
        //$mc_id_u['8386']=[115]; //湖南 C386

        $mc_id_u['8391']=[324]; //湖南 C391 周
        $mc_id_u['8391']=[2337]; //湖南 C291 周
        
        $mc_id_u['8393']=[324]; //湖南 C393 周 伊万
        $mc_id_u['8393']=[2337]; //湖南 C393 周 伊万
        $mc_id_u['8392']=[324]; //湖南 C391
        $mc_id_u['8392']=[2337]; //湖南 C291 2020.03.07
        $mc_id_u['8395']=[356]; //湖南 C395

        /*
        $mc_id_u['8398']=[798]; //湖南 M398  周云闪付
        $mc_id_u['8399']=[798]; //湖南 JD399 周云闪付包装为京东
        $mc_id_u['8571']=[798]; //湖南 周云闪付包装为京东  夜里
        */

        $mc_id_u['8398']=[1949]; //湖南 M398  周云闪付
        $mc_id_u['8399']=[1949]; //湖南 JD399 周云闪付包装为京东
        $mc_id_u['8571']=[1949]; //湖南 周云闪付包装为京东  夜里


        $mc_id_u['8396']=[1065]; //湖南 JD399 C396
        $mc_id_u['8397']=[1092]; //湖南   C397
        $mc_id_u['8573']=[1092]; //湖南   C397 京东云

        $mc_id_u['8594']=[555]; //湖南 M393=>C393 周 网银
        $mc_id_u['8613']=[555]; //湖南 C383

        //$mc_id_u['8266']=[49]; //步步高

        //$mc_id_u['8277']=[105]; //Cq2

        /*
        $mc_id_u['8278']=[53]; //JYT888
        $mc_id_u['8279']=[55]; //JYT999
        $mc_id_u['8276']=[57]; //JYT666
        $mc_id_u['8275']=[60]; //JYT555
        $mc_id_u['8273']=[61]; //JYT333

        $mc_id_u['8288']=[62]; //CGH 聚合
        $mc_id_u['8287']=[62]; //CGH 聚合 微信
        $mc_id_u['8311']=[63]; //CGH 聚合 微信
        */
        $mc_id_u['8321']=[66]; //C321 先锋
        /*
        $mc_id_u['8323']=[69]; //C323 先锋
        $mc_id_u['8322']=[71]; //C322 先锋

        $mc_id_u['8324']=[75]; //C324 先锋
        $mc_id_u['8325']=[76]; //C325 先锋
        $mc_id_u['8326']=[77]; //C326 先锋
        $mc_id_u['8327']=[78]; //C327 先锋
        $mc_id_u['8328']=[89]; //C328 先锋
        $mc_id_u['8329']=[96]; //C329 先锋
        $mc_id_u['8330']=[103]; //C330 先锋
        */
        /*
        $mc_id_u['8303']=[67]; //C303
        $mc_id_u['8302']=[68]; //C302
        $mc_id_u['8301']=[70]; //C301
        $mc_id_u['8305']=[72]; //C305
        $mc_id_u['8306']=[73]; //C306
        $mc_id_u['8307']=[80]; //C307
        $mc_id_u['8308']=[81]; //C308
        */

        /*
        $mc_id_u['8351']=[74]; //C351路人
        //$mc_id_u['8352']=[90]; //C352
        $mc_id_u['8352']=[74]; //C352
        $mc_id_u['8353']=[92]; //C353
        $mc_id_u['8353']=[74]; //C351
        $mc_id_u['8353']=[105]; //C351
        //$mc_id_u['8353']=[74]; //C353
        $mc_id_u['8354']=[105]; //C354
        $mc_id_u['8355']=[107]; //C355
        $mc_id_u['8356']=[109]; //C356
        // $mc_id_u['8357']=[117]; //C357
        $mc_id_u['8357']=[105]; //C354
        */

        $mc_id_u['8358']=[119]; //C358
        //$mc_id_u['8358']=[74]; //C351

        $mc_id_u['8341']=[83]; //C341 巴西
        $mc_id_u['8343']=[83]; //C341 巴西
        $mc_id_u['8342']=[100]; //C342 巴西
        $mc_id_u['8225']=[100]; //C342 巴西

        $mc_id_u['8344']=[116]; //C345 勤学精干
        $mc_id_u['8345']=[116]; //C345 勤学精干


        //$mc_id_u['8201']=[79]; //CQQ
        $mc_id_u['8202']=[104]; //NB
        //$mc_id_u['8202']=[105]; //NB C354
        //$mc_id_u['8202']=[34]; //NB C88
        $mc_id_u['8202']=[200]; //NB C387
        $mc_id_u['8202']=[324]; //NB C391

        /*
        $mc_id_u['8502']=[104]; //C502 me
        $mc_id_u['8501']=[79];  //C501 me
        $mc_id_u['8503']=[188]; //C503 me


        $mc_id_u['8361']=[99]; //五五开 C361 0.4%
        $mc_id_u['8362']=[101]; //五五开 C362
        $mc_id_u['8363']=[102]; //五五开 C363
        */

        $mc_id_u['8511']=[598]; //C511
        //$mc_id_u['8512']=[659]; //C512
        $mc_id_u['8512']=[98]; //C512 支转卡
        //$mc_id_u['8515']=[599]; //C515
        $mc_id_u['8515']=[111]; //C515 云闪付

        $mc_id_u['8521']=[606]; //C521
        $mc_id_u['8522']=[606]; //M522
        $mc_id_u['8541']=[606]; //M541 大发.微信
        $mc_id_u['8548']=[606]; //M548 PCDD.微信
        $mc_id_u['8545']=[606]; //M548 雀神.微信
        $mc_id_u['8551']=[606]; //AG.微信
        $mc_id_u['8582']=[606]; //满堂彩.微信
        $mc_id_u['8591']=[606]; //58彩.微信
        $mc_id_u['8592']=[606]; //黑桃K.微信

        $mc_id_u['8549']=[989]; //PCDD.支付宝
        $mc_id_u['8552']=[989]; //AG.支付宝
        $mc_id_u['8526']=[989]; //M526
        $mc_id_u['8560']=[989]; //大嘴.支付宝
        $mc_id_u['8564']=[989]; //土块.支付宝
        $mc_id_u['8569']=[989]; //八戒.支付宝

        $mc_id_u['8598']=[1197]; //W526 AC.网银

        $mc_id_u['8525']=[784]; //M525
        $mc_id_u['8542']=[784]; //大发.支付宝
        $mc_id_u['8546']=[784]; //雀神.支付宝
        $mc_id_u['8554']=[784]; //大发.支付宝

        $mc_id_u['8557']=[784]; //百万.支付宝
        $mc_id_u['8561']=[784]; //银河888.支付宝
        $mc_id_u['8567']=[784]; //曼.支付宝
        //$mc_id_u['8568']=[784]; //盛大.支付宝

        #$mc_id_u['8530']=[792]; //M530
        /*
        $mc_id_u['8543']=[792]; //M530 大发.云闪付
        $mc_id_u['8547']=[792]; //M530 雀神.云闪付
        $mc_id_u['8550']=[792]; // PCDD.云闪付
        $mc_id_u['8553']=[792]; // AG.云闪付
        $mc_id_u['8597']=[792]; // 荣誉.云闪付
        */


        $mc_id_u['8543']=[1101]; //M530 大发.云闪付
        $mc_id_u['8547']=[1101]; //M530 雀神.云闪付
        $mc_id_u['8550']=[1101]; // PCDD.云闪付
        $mc_id_u['8553']=[1101]; // AG.云闪付
        $mc_id_u['8597']=[1101]; // 荣誉.云闪付

        $mc_id_u['8566']=[1101]; // 土块.云闪付 Y526
        $mc_id_u['8570']=[1101]; // 八戒.云闪付 Y526
        $mc_id_u['8530']=[1101]; //M530  Y526
        $mc_id_u['8558']=[1101]; // 大嘴.云闪付 Y526
        $mc_id_u['8602']=[1101]; // 大一.云闪付 Y526
        $mc_id_u['8603']=[1101]; // 大二.云闪付 Y526


        $mc_id_u['8579']=[792]; // 银河.云闪付
        $mc_id_u['8590']=[792]; // 银河.云闪付

        #$mc_id_u['8572']=[792]; // 好彩.云闪付
        #$mc_id_u['8575']=[792]; // .云闪付
        #$mc_id_u['8578']=[792]; // .云闪付

        /*
        $mc_id_u['8576']=[1102]; // .云闪付 Y527
        $mc_id_u['8580']=[1102]; // 银河.云闪付 Y527
        $mc_id_u['8579']=[1102]; // 银河.云闪付 Y527
        $mc_id_u['8578']=[1102]; //  .云闪付 Y527
        $mc_id_u['8575']=[1102]; //  .云闪付 Y527
        $mc_id_u['8572']=[1102]; //  .云闪付 Y527
        $mc_id_u['8586']=[1102]; //  .云闪付 Y527
        $mc_id_u['8585']=[1102]; //  .云闪付 Y527
        $mc_id_u['8587']=[1102]; //  qp679.云闪付 Y527
        $mc_id_u['8590']=[1102]; //  银河999.云闪付 Y527

        $mc_id_u['8559']=[1079]; //银河888.支付宝
        $mc_id_u['8527']=[1079]; // 支付宝
        $mc_id_u['8563']=[1079]; //M563.支付宝
        $mc_id_u['8565']=[1079]; //星星娱乐.支付宝
        $mc_id_u['8555']=[1079]; //风雀.支付宝
        $mc_id_u['8556']=[1079]; //88棋牌
        */


        $mc_id_u['8577']=[1099]; //老板2.支付宝
        $mc_id_u['8562']=[1099]; //老板.支付宝
        $mc_id_u['8100']=[1099]; //九九.支付宝
        $mc_id_u['8588']=[1099]; //王牌.支付宝
        $mc_id_u['8589']=[1099]; //不凡.支付宝

        $mc_id_u['8581']=[1108]; //老板.支付宝

        //$mc_id_u['8562']=[1099]; //老板.支付宝


        $mc_id_u['8505']=[1089]; //C505.支付宝
        $mc_id_u['8583']=[1089]; //C505.支付宝

        $mc_id_u['8506']=[1090]; //C506.支付宝


        #$mc_id_u['8505']=[4]; //C505.支付宝
        $mc_id_u['8506']=[4]; //C506.支付宝

        $mc_id_u['8508']=[1184]; //C508.网银

        #$mc_id_u['8593']=[1185]; //南哥支转卡
        #$mc_id_u['8212']=[1185]; //C212对接
        #$mc_id_u['8606']=[1185]; //C212对接
        #$mc_id_u['8610']=[1185]; //C212对接
        #$mc_id_u['8614']=[1185]; //C212对接

        $mc_id_u['8599']=[1187]; //C214对接 云闪付
        #$mc_id_u['8595']=[1187]; //C214  8212 云闪付
        $mc_id_u['8607']=[1187]; //C214  8212 云闪付
        $mc_id_u['8611']=[1187]; //C214  8212 云闪付
        $mc_id_u['8615']=[1187]; //C214  8212 云闪付

        $mc_id_u['8600']=[1186]; //C213对接 网银
        $mc_id_u['8608']=[1186]; //C213 8212 网银
        $mc_id_u['8612']=[1186]; //C213 8212 网银


        //$mc_id_u['8596']=[555]; //C213 8212 网银 test C393

        $mc_id_u['8601']=[1210]; //WS215
        //$mc_id_u['8604']=[1210]; //WS215 8212 微信

        #$mc_id_u['8604']=[1211]; //WS215 8212 微信 test C265





        if( $mid=='all')     return $mc_id_u ;
        if( !$mc_id_u[ $mid ] ){
            $mc= $this->getLogin()->createTableMerchant()->getRowByKey( $mid );
            if( $mc['c_user_id']>0) return [ $mc['c_user_id'] ];
            $this->throw_exception("该商户未配置", 90613001);
        }
        return $mc_id_u[ $mid ];
    }

    /**
     * 是不是kc 发过来的单子
     * @param $merchant_id
     * @return bool
     */
    public function  isKC($merchant_id){
        return in_array(  $merchant_id ,[8201,8200]) ;
    }

    public function getPayBackMid(){
        $arr= [ 8387,8229 ,8391,8392,8202,8234,8522,8541   ] ; //,8393 ,8201 ,8521
        return $arr;
    }

    public function isQiangByCuid($c_user_id){
        //$arr=[ 324];

        $config= $this->createVip()->getVersionConfig( $c_user_id );
        //if( $config['rg']) return true;

        return $config['rg']?true:false;
        //return in_array( $c_user_id, $arr );
    }

    public function isShowGoods( $c_user_id){

        return in_array( $c_user_id, [798, 555, 356 , 324 , 1633,1949,2337,2650]  );
    }

    public function isShowFee($c_user_id){
        return true;
        return in_array( $c_user_id, [798, 555, 356 , 324 , 1633,1949,2337,2650]  );
    }

    /**
     * 获取是否支持码商 并且提供版号
     * @param $c_user_id
     * @return bool|int|mixed
     * @throws drException
     */
    public function getMaVersionByCuid( $c_user_id){
        $arr=[4 ,74 , 1185,2333];
        if( !in_array( $c_user_id, $arr)) return false;

        return $this->getVersionBYConsole( $c_user_id );

    }

    public function isUserKouLing( $c_user_id){

        return in_array( $c_user_id,[4,2323]);
    }

    public function  versionType( $version='all'){
        $arr=[ ];
        $arr[4]='随机金额 支付宝UID' ;
        $arr[13]='支付宝.个码' ; //任意金额码  10086 手动复制金额
        $arr[15]='支.个码.动态' ; //任意金额码  使用动态生成二维码
        $arr[30]='支转账不带单号 使用 ali_uid' ;
        $arr[31]='扫码 不带单号 使用优惠金额' ;
        $arr[32]='扫码 带单号' ;
        $arr[35]='支付宝红包模式' ;
        $arr[36]='支付宝反向收款' ;
        $arr[38]='钉钉红包' ;
        $arr[39]='淘宝现金红包' ;
        $arr[239]='淘宝群红包' ;

        $arr[139]='旺信红包' ; #得先打码
        $arr[138]='旺信红包V2' ; #实时出码 必须手动切换群

        $arr[301]='支付宝点餐收款' ;
        $arr[78]='钉钉群收款' ;

        $arr[40]='支付宝.转卡' ;
        $arr[50]='采宝API' ;
        $arr[45]='卡转卡' ;

        $arr[60]='云闪付' ;

        $arr[22]='微信xposed' ;
        $arr[23]='微信.监听消息' ;
        $arr[24]='微信.店员通' ;

        $arr[201]='微信.人工跑分' ;
        $arr[211]='微信.跑分' ;
        $arr[28]='微信.手机号' ;

        $arr[205]='支付宝.跑分' ;
        $arr[351]='支付宝.口令红包' ; #也是现产码
        $arr[80]='淘宝代付' ;
        $arr[90]='支.网银' ; //现产码
        $arr[63]='云闪付.夜' ;
        $arr[65]='平安银行' ;

        $arr[120]='微信群红包' ;
        $arr[130]='微博红包小额' ;
        $arr[131]='微博红包大额' ;

        $arr[320]='话费' ;
        $arr[150]='支付宝群红包' ;

        $arr[140]='转卡.机器' ;
        $arr[145]='转卡.人工' ;

        return $arr;
    }

    /**
     * 205 201 211
     * 1.如果是收单模式 不需要怎么设置
     * 2.如果是事前 安排订单 需要在  $this->getLogin()->getPayBackMid() 设置
     * 3.如果抢单模式 需要设置2  还得设置  $this->getLogin()->createQrPay()->isQiang
     *
     * 通过操作员判断当前使用 哪个版本
     * @param int $console_uid
     * @param int $pay_type
     * @return int|mixed
     * @throws drException
     */
    public function getVersionBYConsole( $console_uid=0 ,$pay_type=1 ){
        if( $console_uid <=0 && $console_uid!='all') $console_uid= $this->getUserId();
        //if( $console_uid <=0) $console_uid= $this->getUserId();
        if($pay_type==300000 ){ //云闪付

        }else {
            //4：随机金额 支付宝UID 17999
            //5：随机金额 支付宝UID 17999
            //3：任意金额码  10086 手动复制金额
            //2：任意转账金额 18000
            //1：码
            //30： 客户端变为 xposed 扫码转账模式 不带单号 使用 ali_uid
            //31： 客户端变为 xposed 扫码转账模式 不带单号 使用优惠金额
            //32： 客户端变为 xposed 扫码 带单号
            //35： 客户端变为 xposed 红包模式
            //36： 客户端变为 xposed 加好友 反向收款
            //38： 客户端变为 xposed 钉钉红包
            //39： 客户端变为 xposed 淘宝红包
            //301： 客户端变为 xposed 支付宝点餐收款 或者店员通
            //78： 客户端变为 xposed 钉钉群收款

            //13： 客户端变为 xposed 支付宝个码 一张二维码模式 收银台

            //60： 客户端变为 xposed 云闪付收款

            //63  客户 云闪付收款 监听消息 适合夜间


            //40： 支付宝转银行卡
            //50： 采宝API
            //45： 银行卡转银行卡

            //22: 客户端变为 xposed 微信
            //23: 微信 监听消息 一张二维码模式
            //24: 微信 xposed 店员通 监听消息 一张二维码模式

            //201 人工跑分模式（微信）
            //211 机器跑分模式（微信）

            //205 人工跑分模式（支付宝）

            //351 口令红包（支付宝）

            //80： 淘宝代付
            //65： 平安银行.收款码

            //$user_version = [ 12 => 4, 15 => 4];
            $user_version = [ ];
            //$user_version[4] = 201 ;  //测试
            $user_version[4] = 90; //145转卡.人工 40转卡.机器  15支付宝.动态 320话费 60  205 //测试5 40转卡 13个码 139旺信红包 138旺信实时码 150支.群红包
            /*
            $user_version[62] = 50 ; //CGH 聚合
            $user_version[40] =  30; //CYG 凯优光实业 40 8255

            $user_version[25] = 3;// 凤凰;
            $user_version[8] = 3; //8=>2, 3 wx
            $user_version[21] = 30; //开心
            $user_version[23] = 4; //星河
            $user_version[18] = 30; //jj  => 4,
            $user_version[15] = 40; //TJ  => 4,
            $user_version[37] = 60 ; //CTC厦门天才 C118
            $user_version[38] = 35; //C998厦门天才
            $user_version[47] = 24; //CYH 厦门天才 银河 C038

            $user_version[64] = 35; //CMX 厦门天才 MXCP
            $user_version[65] = 35; //CMX 厦门天才 CQY
            $user_version[82] = 35; //CMX 厦门天才 CQY
            $user_version[87] = 35; //CDC 厦门天才
            $user_version[88] = 35; //CXP 厦门天才
            $user_version[217] = 24; //C231 厦门天才
            $user_version[267] = 201; //C232 厦门天才
            $user_version[269] = 24; //C234 厦门天才

            */
            $user_version[48] = 205; //C093 厦门天才 C093

            $user_version[32] = 5; //KCTest
            $user_version[34] = 320; //CQQ

            $user_version[49] = 40; //CBB 补补高

            $user_version[51] = 36; //Cq2补补高

            /*
            $user_version[53] = 36; //JYT888
            $user_version[55] = 36; //JYT999
            $user_version[57] = 36; //JYT666
            $user_version[60] = 36; //JYT555
            $user_version[61] = 36; //JYT333
            */

            $user_version[59] = 40; //凯优光实业6 cyg2
            $user_version[93] = 24; //凯优光实业7 cyg3

	        $user_version[97] = 80 ; //JJF C251 //351
	        $user_version[1088] = 80 ; //JJF C261
	        $user_version[1193] = 90 ; //JJF C264
	        $user_version[1211] = 28 ; //98k C265

            $user_version[98] = 40; //JJF C252
            $user_version[1037] = 40; //JJF C262
            $user_version[1040] = 205; //JJF C267

            $user_version[111] = 60; //JJF C253
            $user_version[1959] = 90; //JJF C254

            $user_version[3190] = 40; //98K C256 转卡
            $user_version[3191] = 15; //98K C257 个码

            $user_version[1038] = 60; //JJF C263
            $user_version[1041] = 60; //JJF C268

            $user_version[112] = 32; //JJF C371
            $user_version[113] = 40; //JJF C372
            $user_version[114] = 24; //JJF C373

            $user_version[115] = 60; //湖南 C388
            $user_version[200] = 201; //湖南 C387
            $user_version[317] = 40; //湖南 C387
            $user_version[1091] = 63; //湖南 C386
            $user_version[1103] = 205; //湖南 C385
            $user_version[1219] = 90; //湖南 C383 网银

            $user_version[324] = 201; //湖南 周 C391
            $user_version[356] = 205; //湖南 周 C395
            $user_version[2337] = 201; //湖南 周 C291 #微信
            $user_version[2650] = 15; // 13 湖南 老周 C292

            $user_version[4649] = 15; // 13 湖南 老周 C287

            $user_version[3310] = 15; // 13 湖南 老周 C295
            $user_version[3349] = 15; // 13 湖南 老周 C296
            $user_version[3125] = 40; //湖南 老周 C293
            $user_version[4335] = 40; //145 湖南 老周 C297
            $user_version[4467] = 40; //145 湖南 老周 C298

            $user_version[4902] = 40; //湖南 老周 C285
            $user_version[5063] = 40; //湖南 老周 C283
            $user_version[5073] = 205; //湖南 老周 C282

            $user_version[4647] = 40; // 湖南 老周 C288
            $user_version[5124] = 40; // 湖南 老周 C276
            $user_version[4761] = 40; // 湖南 老周 C287
            $user_version[5082] = 40; // 湖南 老周 C279
            $user_version[4468] = 40; //145 湖南 老周 C299 现金红包

            $user_version[4368] = 145; //五哥 C281
            //
            /*
            if( rand(0,2)==1)    $user_version[798] = 60 ;
            else  $user_version[798] = 60; //湖南 周 C398
            */
            $htime=  intval( date("Hi") ) ;
            $user_version[798] = 60 ;// ($htime>610 && $htime<2320 )? 60: 65;
            $user_version[1949] = 60 ;//YS98

            $user_version[1062] = 40; //湖南 周 C399
            $user_version[1065] = 80; //湖南 周 C396
            $user_version[1092] = 63; //湖南 周 C397
            $user_version[1633] = 120; //湖南 周 C394 微信红包

            $user_version[63] = 45; //

            $user_version[66] = 205; //C321
            $user_version[69] = 40; //C323 先锋
            /*
            $user_version[71] = 301; //C322 先锋
            $user_version[75] = 36; //C324 8324 先锋
            $user_version[76] = 36; //C325 8325 先锋
            $user_version[77] = 36; //C326 8326 先锋
            $user_version[78] = 30; //C327 8327 先锋
            $user_version[89] = 39; //C328 8328 先锋
            $user_version[96] = 39; //C329 8329 先锋
            $user_version[103] = 24; //C330 8330 先锋
            */

            /*
            $user_version[67] = 35 ; //C303 小马哥
            $user_version[68] = 39 ; //C302
            $user_version[70] = 35 ; //C301
            $user_version[72] = 35 ; //C305
            $user_version[73] = 40 ; //C306
            $user_version[80] = 22 ; //C307
            $user_version[81] = 35 ; //C308
            */


            $user_version[83] = 13 ; //8341 C341 巴西
            //$user_version[83] = 205 ; //8341 C341 巴西 修改为 人工跑分
            $user_version[100] = 90 ; //8342 巴西

            $user_version[116] = 28 ; //8345 C345 勤学精干

            /*
            $user_version[74] = 24 ; //8351 路人
            $user_version[90] = 24 ; //8352 路人
            $user_version[92] = 40 ; //8353 路人
            $user_version[105] = 205 ; //8354 C354 路人
            $user_version[107] = 60 ; //8355 路人
            $user_version[109] = 40 ; //8356 路人
            $user_version[117] = 205 ; //8357 路人 C357
            $user_version[119] = 24 ; //8358 路人 C358 南哥
            */

            $user_version[79] = 78 ; //C501 me
            $user_version[104] = 40 ; //C502 me
            $user_version[188] = 60 ; //C503 me 云闪付

            $user_version[84] = 35 ; //T093
            $user_version[85] = 35 ; //TMX
            $user_version[86] = 35 ; //T038

	        $user_version[99] = 39 ; //8361 C361
            $user_version[101] = 40 ; //8362 C362
            $user_version[102] = 40 ; //8363 C363


            $user_version[598] = 60 ; //8511 C511
            $user_version[599] = 60 ; //8515
            $user_version[659] = 40 ; //8512

            $user_version[606] = 120 ; //8521 C521 跑分
            #$user_version[606] = 28 ; //8521 C521 微信手机转账
            $user_version[784] = 80 ; //8525 C525
            $user_version[792] = 60 ; //8530 C530 13 205
            $user_version[989] = 13 ; //8526 C526
            $user_version[1079] = 80 ; //C527

            $user_version[1089] = 80 ; //C505
            $user_version[1090] = 80 ; //C506

            $user_version[1099] = 40 ; //C577
            $user_version[1943] = 40 ; //C578
            $user_version[1108] = 60 ; //Y577

            $user_version[1102] = 60 ; //Y527
            $user_version[1101] = 60 ; //Y526

            $user_version[1197] = 90 ; //  w526

            $user_version[1184] = 90 ; //C508


            $user_version[1186] = 90 ; //C213 南哥
            $user_version[1185] = 40 ; //C212
            $user_version[1187] = 60 ; //C214
            $user_version[1210] = 120 ; //WS215
            $user_version[1630] = 120 ; //C217 微信南哥租用
            //$user_version[1557] = 205 ; //C216 支付跑分
            $user_version[1557] = 13 ; //C216 个码机器上分



            //$user_version[1089] = 80 ; //C505
            $user_version[1164] = 205 ; //C311 财神

            $user_version[1626] = 120 ; //C280 小白 微信

            $user_version[555] = 90 ; //

            $user_version[2322]= 60;//C221 云闪付 南哥2020
            $user_version[2323]= 15;//C222 个码  13  南哥2020
            $user_version[2333]= 40;//C223 支专卡 南哥2020
            $user_version[2438]= 351;//C224 口令红包 南哥2020
            $user_version[2695]= 138;//C225 旺信淘宝现金红包 南哥2020
            $user_version[2808]= 150;//C226 支付宝群红包 南哥2020
            $user_version[2910]= 90;//C227 网银 南哥2020

            $user_version[2645]= 60;//C312 个码 船长 平 ->ping
            $user_version[2691]= 139;//C313 旺信淘宝现金红包 船长 平

            $user_version[2862]= 40;//C315 支付宝跑分 船长 平 人工跑分
            $user_version[5093]= 15;//C318 支付宝个码 船长 平 ->ping


            $user_version[3207]= 40;//C271 支转卡 王总 老王
            $user_version[4408]= 40;//C601 支转卡 王总 老王

            $user_version[4628]= 40; //C337 支转卡 多总.wuge

            $user_version[3305]= 205;//C339 个码 虚空 跑分
            $user_version[3849]= 205;//C338 个码 虚空 跑分

            $user_version[4865]= 40;//C336 个码 虚空.传奇 转卡

            $user_version[4518]= 40;//C316 个码.机器 袁大头 跑分
            $user_version[5084]= 205;//C317 个码.人工 袁大头 跑分

            $user_version[5106]= 40;//C369 神域 支转卡

            $user_version[5107]= 90;//C278 老王 网银.企业支付宝
            $user_version[5122]= 90;//C277 老王 网银.企业支付宝



        }

        if( isset($user_version[ $console_uid ] ) ) return  $user_version[ $console_uid ];

        if( $console_uid=='all' ) return $user_version;

        return 1;
    }

    /**
     * 通过商户ID来判断当前使用 哪个版本
     * @param $mid
     * @param int $pay_type
     * @param array $opt
     * @return int|mixed
     * @throws drException
     */
    public function getVersionByMid($mid ,$pay_type=1, $opt=[] ){
        $mc_id_u=  $this->midConsole();
        $c_user_id=  $mc_id_u[ $mid ][0] ;
        if( !$c_user_id){
            $c_user_id= intval( $opt['c_user_id'] );
            if($c_user_id<=0 ) $this->throw_exception( "商户未初始化",19103101 );
        }
        return $this->getVersionBYConsole($c_user_id ,$pay_type );
    }

    public function getMidFromConsole( $console_uid ){
        $mc= $this->midConsole();
        $mid_arr = [];
        foreach( $mc as $mid=>$c_arr ){
            if( in_array( $console_uid,$c_arr )){
                $mid_arr[]= $mid;
            }
        }

        $mid2 = $this->getLogin()->createTableMerchant()->getColByWhere(['c_user_id'=> $console_uid], ['merchant_id']);
        $mid_arr= array_merge($mid_arr,$mid2 );
        return $mid_arr ;
    }

    function getVirtualMid( $mid , $pay_type ){
        $tall=[];
        $tall[8577][4] =  8581;
        if( isset( $tall[$mid][$pay_type] ) ) return  $tall[$mid][$pay_type] ;

        if( $mid==='all') return $tall;
        return false;
    }

    function getRealMid( $v_mid ){
        $tall= $this->getVirtualMid('all',1 );
        $re=[];
        foreach( $tall as $mid=>$var ){
            foreach( $var as $version=>$vv_mid){
                if( $v_mid== $vv_mid) return $mid;
            }
        }
        return 0;
    }


    /**
     * 操作员组团
     * @param $user_id
     * @return bool|mixed
     */
    public function czGroup( $user_id ){
        $group=[];
        $group[8]=[8,21  ] ;
        //$group[18]=[18 ,23 ] ; //
        $group[12]=[12,25  ] ;
        //return  $group[8];
        if( $group[ $user_id ] ) return $group[ $user_id ];

        return false ;
    }

    public function getUrlGroup(){
        $url[]='https://c9.crosscase.cn';
        $url[]='https://c8.crosscase.cn';
        $url[]='https://c7.crosscase.cn';
        $url[]='https://c6.crosscase.cn';
        return $url;
    }


    /**
     * 授权码
     * @param int $uid
     * @return mixed
     * @throws drException
     */
    public function shouQuan( $uid=0 ){
        if($uid<=0) $uid= $this->getUserId();
        $u_arr=[];
        $u_arr['37']='stc.2019';
        $u_arr['38']='s98.2019';
        $u_arr['65']='qysq.2019';

        $u_arr['64']='mx.2019';//CMX
        //$u_arr['47']='qf038308';//038 YH

        $u_arr['84']='qs939393';//T093
        $u_arr['86']='cs038308';//T038
        $u_arr['85']='tx.2019';//TMX

        $u_arr['87']='qsDc9898';//CDC
        $u_arr['88']='Xjp.9898';//CXP

        #$u_arr['4']='s98.2019';
        return $u_arr[ $uid ];
    }

    public function isShouQuan( ){
        if( ! $this->shouQuan()) return true;
        //if( $this->getLogin()->getc)
        return false;
    }


    /**
     * 获取操作员版本 1到账上分 2下单金额上分
     * @param int $console_uid
     * @return int
     * @throws drException
     */
    public function getConsoleVersion( $console_uid=0 ){
        //return 2;
        if( $console_uid<=0) $console_uid= $this->getUserId();
        if($console_uid<37 ){
            return 1;
        }
        if( in_array($console_uid,[49] )){
            return 1;
        }
        return 2;
    }

    public function getFee( $m_id=0){
        $m_arr = [8352=>2.6,8353=>3.5,8351=>3.0 ,8354=>3.0 ,8355=>1.6 ,8356=>1.6 ];
        if( $m_id<=0) return $m_arr;
        if( isset($m_arr[ $m_id ]) )        return $m_arr[ $m_id ];
        return 0;
    }

    public function getMoneyGu(){

        //return [2=>5, 7=>1,8=>1,9=>1, 100=>5,200=>10,300=>15,500=>20,800=>20,1000=>20,1500=>20,2000=>20,3000=>20,5000=>20,8000=>15,10000=>10 ];
        return [1=>2, 50=>3,100=>5,150=>6,200=>10,300=>15,500=>20,800=>20,1000=>20,1500=>20,2000=>20,3000=>20,5000=>20,8000=>15,10000=>10 ];
    }

    public function getDomain( $key='all' ){
        $tall=[];
        #$tall[1]='https://qz.atbaidu.com';
        //$tall[2]='http://*.atbaidu.com';
       // $tall[3]='http://*.q41n.com';

        $tall[4]='http://*.sllzs.cn';
        $tall[5]='http://*.fusocq.cn';

       //$tall[6]='http://*.crosscase.cn';

        $tall[7]='https://qz.becunion.com';
        $tall[8]='http://*.hyshqs.com';
        //$tall[9]='http://*.ainongnong.cn';
        //$tall[10]='https://pz.easepm.com';
        //$tall[11]='https://py.biqiug.com';
        //$tall[12]='http://*.biqiug.com';
        $tall[13]='http://*.qmailq.com';
        $tall[14]='http://*.pteclub.com.cn';
        //$tall[14]='https://qz.pteclub.com.cn';

        if( $key=='all') return $tall ;

        if( ! isset($tall[$key] )){
            return 'https://pz.easepm.com';
            //$this->throw_exception("域名不不在",9052401);
        }

        return $tall[ $key ];
    }

    public function getDomainByUid( $user_id ,$is_flash=false ){

        //return  'https://qz.becunion.com' ;
        //return  'https://qz.atbaidu.com' ;

        $key='dm_'.$user_id ;
        $v=$this->createCache()->getRedis()->get($key);
        if($v && !$is_flash) return $v;

        try{
            $dm= $this->createUserOne($user_id )->getAttr('dm');

            $host= $this->getDomain( $dm);
        }catch (\Exception $ex ){
            $host= $this->getDomain(1);
        }
        $this->createCache()->getRedis()->set( $key, $host, 600 );
        return $host;
    }

    public function maZb( $c_user_id  ){


        $key= 'mazb_'. $c_user_id;

        try {
            $cache = new cache();
            $zb = $cache->getRedis()->get( $key);
            $this->createRedis()->close();
            //$this->drExit( $user_name );
            if(!$zb || $zb<0) return 21;
            return $zb;
        }catch ( drException $ex ){
            return 20;
        }

        return 20;

    }

    public function maZbSet($c_user_id,$fee){
        $key= 'mazb_'. $c_user_id;

        try {
            $this->createRedis()->set( $key,$fee);
            $this->createRedis()->close();
            return $this;
        }catch ( drException $ex ){
        }
        return $this;
    }

    /**
     * @return \redis
     */
    public function createRedis(){

        $redis = new \redis();
        $redis->connect('redis.server.haoce.com', 6379);
        return $redis;
    }

    public function redisSet( $key, $value, $timeout=0){


        try {

            $redis = $this->createRedis();
            if( $timeout>0) $redis->set( $key,$value, $timeout);
            else $redis->set( $key,$value);
            $redis->close();
            return $this;
        }catch ( drException $ex ){
        }
        return $this;

    }

    public function redisGet($key ){
        try {

            $redis = $this->createRedis();
            $value = $redis->get( $key);
            $redis->close();
            return $value ;
        }catch ( drException $ex ){
        }
        return null;
    }

    /**
     * 云闪付是否在夜里
     * @return bool
     */
    public function isUnipayYue(){
        return false;
    }

    public function cha2Cuser($cha_uid){

        $arr=[];
        $arr[19]=4;
        $arr[1864]= 356 ; //C395
        $arr[1867]= 356 ; //C395
        $arr[1868]= 356 ; //C395
        $arr[1869]= 356 ; //C395
        if(! isset( $arr[$cha_uid]) ) $this->throw_exception( "非查单员", 19120501);

        return $arr[$cha_uid];
    }

    public function checkHeiIp(){
        try {
            $ip = $this->getLogin()->createRedis()->get('heiIp');
            $this->getLogin()->createRedis()->close();
        }catch (drException $ex ){}
        if( !$ip ) return $this;
        $ip_arr = explode("\n", $ip );
        $ip_all = drFun::getIpAll();
        foreach( $ip_arr as $v ){
            $v= trim($v);
            if( $v=='') continue;
            if( strpos(  $ip_all ,$v )!==false ){
                $this->throw_exception("IP被锁定",19122609 );
            }
        }
        return $this;

    }


    public function checkSafeFromExt(){
        $this->checkLogin();
        $sign=$this->getCookUser('sign');
        if(! isset( $_SERVER['HTTP_X_QF_SAFE'])) $this->throw_exception("运行环境不安全",20011901 );
        $tarr= explode('.', $_SERVER['HTTP_X_QF_SAFE'] );
        if( count($tarr)!=2) $this->throw_exception("安全认证格式错误" ,20011902);
        $key= strtolower( $tarr[0] );
        $time = $tarr[1];
        #时间误差
        if(abs( $time-time())>5*60) $this->throw_exception("安全认证超时" ,20011904);

        $server_key= strtolower( substr( md5($sign.'-ks-'. $time ),0,16));

        if( $server_key != $key) $this->throw_exception("安全认证失败" ,20011903);

        return $this;

    }

    public function isShoudan( $c_user_id ){

        return $this->isShoudanV2( $c_user_id) || $this->isShoudanV1($c_user_id);
    }

    /**
     * 是否启用收单模式
     * 价格单一  一户多码 一码多单
     * @param $c_user_id
     * @return bool
     */
    public function isShoudanV1( $c_user_id ){

        $arr= [3305,3849,2862];
        return in_array( $c_user_id, $arr );
    }

    /**
     * 价格单一  一户多码 一码一单
     * @param $c_user_id
     * @return bool
     */
    public function isShoudanV2($c_user_id){
        $arr=[4,2650,4335,4368,4467,4468,4649,4902,5073,5084];
        return in_array( $c_user_id, $arr );

    }

    /**
     * 外面是15版本
     * 是 支付宝手动模式 跟 机器模式结婚 15+205 版本；
     * @param $c_user_id
     * @return bool
     */
    public function isShoudanV3( $c_user_id ){
        $arr=[4,2650];
        return in_array( $c_user_id, $arr );
    }

    public function getTypeCanUpTimeByMaUser(){

        return [205,201,145];
    }

    /**
     * 是不是纯财务
     * @return bool
     */
    public function isJustCW(){
        $attr = $this->getCookUser('attr');
        #if( !$attr || count( $attr)!=1) return false;
        if( in_array('p3', $attr)) return false ;
        if( in_array('p2', $attr)) return true ;
        return false;
    }

    public function getCanXiaType(){
        return [31];
    }

    public function getTgChatId($c_user_id){
        $tg=[];
        $tg[4] =['telegram'=>[ -344503692 ],'potato'=>[12384204]  ];
        $tg[5] =['telegram'=>[-492924716 ]   ]; //okex
        $tg[154] =['telegram'=>[ -344503692 ],'potato'=>[12384204]  ];
        $tg[4649] =['potato'=>[12381385]  ];//C287
        $tg[4467] =['potato'=>[12381564]  ]; //C298
        $tg[4408] =['potato'=>[12382068]  ]; //C601
        $tg[4761] =['potato'=>[12383827]  ]; //C286
        $tg[2333] =['telegram'=>[ -478555031]  ]; //C223  'potato'=>[12400750]
        $tg[2323] =['telegram'=>[ -418320477]  ]; //C222 888553768 'potato'=>[12400726]
        $tg[4647] =['potato'=>[12598131]  ]; //C288
        $tg[4902] =['potato'=>[12675277]  ]; //C285
        $tg[4628] =['telegram'=>[-320093336]  ]; //C337
        $tg[4101] =['telegram'=>[-367726459]  ]; //C223100
        $tg[5063] =['telegram'=>[-490044949]  ]; //C283
        $tg[5093] =['telegram'=>[-447219667]  ]; //C318
        $tg[5107] =['telegram'=>[-496385599]  ]; //C378
        $tg[5124] =['telegram'=>[-491759128]  ]; //C276
        if( !isset( $tg[$c_user_id])) $this->throw_exception( '未配置TG',20080701 );
        return $tg[$c_user_id];
    }

    /**
     * 是否安全码模式 如果是为1 马队充值的时候需要安全码
     * @param $c_user_id
     * @return int
     */
    public function isAnquanMa( $c_user_id){

        $uarr=[4=>1,4467=>1,4902=>1];
        if( isset($uarr[ $c_user_id]) ) return $uarr[ $c_user_id];
        return 0;
    }

    /**
     * 用户开代理
     *  2 为允许最大代理开通
     *  1 不允许开通
     *  0 允许开通
     * @param $c_user_id
     * @return int
     */
    public function isDengji( $c_user_id){
        $arr=[4=>2,4902=>2];
        if(!isset( $arr[$c_user_id])) return 0;
        return $arr[ $c_user_id];
    }

    /**
     * 设置关闭总控相关的台子
     * @param $c_user_id
     * @return bool
     */
    public function isLimitLogin( $c_user_id){
        $arr= [4467,2650 ,4335  ,2333]; //2333,2323
        return in_array($c_user_id, $arr);
    }



}
