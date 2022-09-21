<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/8 0008
 * Time: 下午 8:31
 */

namespace ctrl;

use DR\DR;
use model\day;
use model\drException;
use model\drFun;
use model\drTpl;
use model\lib\mq;
use model\page;
use model\test;
use model\user;
use model\user\login;

class index extends drTpl
{
    public function init()
    {
        parent::init();  
        //$this->htmlFile="nologin.phtml";
    }
    public function act_index(){
        $host= $_SERVER['HTTP_HOST'];
        if( $host=='readface.cn' ||  $host=='www.readface.cn' ||  $host=='client.readface.cn'){
            $this->redirect('https://www.readface.cn/res/download/','');
        }

        //if( 'pm.readface.cn'== $host  ||'pm.atbaidu.com'== $host ) $this->redirect('/hcadmin','');

        //||   'cz.crosscase.cn'== $host  || 'cz.crosscase.cn:443'== $host

        if(  'cw.atbaidu.com'== $host || 'cw.atbaidu.com:443'== $host ) $this->redirect('/finance','');
        if(   in_array( $host,['cz.qmailq.com:443','cz.qmailq','v1.qmailq.com:443'] )   ) $this->redirect('/console','');
        if(   'cz.ancall.cn'== $host || 'cz.xyxy521.com'== $host || 'cz.ancall.cn:443'== $host || 'cz.xyxy521.com:443'== $host ) $this->redirect('/console','');
        if(   'mc.xyxy521.com'== $host  || 'mc.xyxy521.com:443'== $host ) $this->redirect('/merchant','');

        if(  in_array($host,['mc.atbaidu.com','mc.atbaidu.com:443','pay.biqiug.com','pay.biqiug.com:443','merchant.nekoraw.com','merchant.nekoraw.com:443']) ) $this->redirect('/merchant','');

        if(  in_array($host,['vip.easepm.com','vip.easepm.com:443','vip.xyxy521.com','vip.xyxy521.com:443','vip.qmailq.com','vip.qmailq.com:443']) ) $this->redirect('/vip','');

        $host= strtr($host,[':433'=>''] );

        if( strpos($host,'biqiug.com') !== false ) $this->redirect('https://pay.biqiug.com');
        $this->drExit( 'building ddd: '. $host);
        $user = login::getCookieUser();
        if( ! $user) {
            $this->htmlFile="login.phtml";
        }else{
            $this->redirect('book/index', '');
        }


    }
    public function act_ase(){
        $this->drExit('ase');
    }
    public function act_download(){

            $user_agent = strtolower( $_SERVER['HTTP_USER_AGENT']);
            $is_os = strpos($user_agent,'iphone' ) || strpos($user_agent,'ipad' ) || strpos($user_agent,'ipad' );
            $url_ios='https://itunes.apple.com/cn/app/id1296236146';
            if($is_os ){
                $this->drExit('No ios');
                //header('Location: '. $url_ios);
                exit();
            }elseif( strpos($user_agent,'micromessenger') ){ #微信
                header("HTTP/1.1 206 Partial Content");
                header('Content-Type: text/plain;charset=UTF-8');
                header('Content-Disposition: attachment; filename="laoshi.apk"');
                readfile( dirname(__file__).'/d.txt');
                exit();
            }else{
                header('Location: http://client.readface.cn/res/download/laoshi.apk' );

            }

    }
    public function act_lg(){
        //$this->htmlFile="login.phtml";
    }

    public function act_help(){
        $page = new page(100 );
        $this->assign('page', $page->setEvery(4)->getPageAll() );
        $this->tplFile='index';

    }
    public function act_error(){
        $this->throw_exception( "错误提怎么办？");
    }

    /**
     * 注册
     */
    public function act_reg(){
        $this->setBackUrl();
        $this->assign('site_title','注册');
    }

    public function act_login( $p ){
        switch ($p[0]){
            case 'vip':
                $uid= $p[1];
                $time= $p[2];
                $sign= $p[3];
                if( abs($time-time())> 60 ) $this->throw_exception("超时");
                if( $sign!= $this->getLogin()->createVip()->getSign($uid. $time )) $this->throw_exception("秘钥错误");
                $this->getLogin()->loginByUid( $uid );

                drFun::setSession('lg','console');
                $this->redirect(  '/vip');
                break;
            case 'post':

                $psw= $_POST['psw'];
                if( $_POST['psw_encrypt'] ){
                    $psw= drFun::privateDecrypt( $_POST['psw_encrypt'] );
                    //$this->drExit('psw='. $psw );
                }
                $duser = $this->getLogin()->loginByPsw( $_POST['openid'], $psw);

                $loginback= $this->getBackUrl( $duser ) ;
                $this->redirect( drFun::R( $loginback) , "欢迎 " .$duser['name'].'   回来');
                break;
        }
        //if($_GET['loginback']!='' ) drFun::setcookie('loginback', $_GET['loginback']);

        $this->setBackUrl();
        $this->assign('loginback', trim($_GET['loginback'] )? trim($_GET['loginback'] ): $_COOKIE['loginback']);
    }

    public function act_logout(){
        $login= new login();
        $login->logout();
        $this->redirect($this->getBackUrl(),'登出成功！');
    }

    private function setBackUrl(){
        if( !isset($_REQUEST['loginback']) || ''==trim( $_REQUEST['loginback']) ){
            return ;
        }
        drFun::setcookie( 'apiback', trim($_REQUEST['loginback']) );
        drFun::setcookie( 'loginback', trim($_REQUEST['loginback']) );

    }
    private function getBackUrl( $duser= false ){
        $url = '/';
        $url= $_COOKIE['loginback']? trim(  $_COOKIE['loginback'] ):$url;
        $url= $_REQUEST['loginback']? trim(  $_REQUEST['loginback'] ): $url;
        $url = trim($url );
        if( substr($url,0,4)=='http' && $duser ){
            unset( $duser['psw'] );
            unset( $duser['slat'] );
            $re=[];
            $re['haoce_info']= base64_encode( json_encode( $duser ) );
            $re['time']= time();
            $re['sign']= md5 (  $re['haoce_info']."-md5@haoce!-".   $re['time'] );
            $url = R( $url,$re );
        }
        //$this->drExit($url );
        drFun::setcookie( 'loginback','',time()-3600 );

        return $url;
    }



    /**
     * 忘记密码
     */
    public function act_forgot($p ){
        session_start();
        $type = intval($_GET['sep']);
        if( $type==1 ){
            $login = new login();
            $login->checkYzm( $_POST['openid'], trim($_POST['yzm']) );
            //$this->assign('post', $_POST);
            $_SESSION['openid']=  $_POST['openid'];
            //$this->redirect("",);
        }elseif($type==2){
            $openid = trim($_SESSION['openid']);
            if( $openid=='' ) $this->throw_exception( "请重新获取验证码！");

            if(  $_POST['password']!=$_POST['repassword']){
                $this->throw_exception( "二次密码错误！");
            }
            $this->getLogin()->changPasswordByOpenId($openid, $_POST['password'] );
            unset(  $_SESSION['openid'] );
            $this->redirect( "login","重置成功，请登录！");
        }elseif ($type==12){
            if(  $_POST['password']!=$_POST['repassword'])   $this->throw_exception( "二次密码错误！");
            user::isEasyPsw( $_POST['password'] )      ;
            $this->getLogin()->checkYzm( $_POST['openid'], trim($_POST['yzm']) );
            $this->getLogin()->changPasswordByOpenId( $_POST['openid'] , $_POST['password'] );
        }
        $this->assign('site_title','重置密码');

        $this->setBackUrl();
    }

    public function act_cmain(){
        $this->htmlFile="cmain.phtml";
    }

    public function act_uLogin( $p ){
        $uid= $p[0];
        $time= $p[1];
        $key= md5( $uid.'ad78888'.$time );
        if($p[2] !=$key ) $this->drExit('错误');
        $this->getLogin()->regCookie( $uid );
        $this->redirect('console/main');
    }

    /**
     * 后台管理员入口
     */
    public function act_hcadmin(){
        $http_host= $_SERVER['HTTP_HOST'];
        if( ! in_array($http_host, [ 'hc.atbaidu.com' ,'qunfu.zahei.com']) ) $this->drExit('404');
        //parent::checkAdminByCookie();
        if(! $this->getLogin()->isLogin() ) $this->redirect('/login?loginback='.urlencode('/hcadmin'),'请先登录');
        $this->htmlFile= "admin.phtml";
        try{
            parent::checkAdminByCookie();
        }catch ( drException $ex ){
            //$this->drExit( $ex->getMessage() );
            $this->redirect('/login?loginback='.urlencode('/hcadmin'), $ex->getMessage() );
        }
    }

    public function act_cha(){
        if(! $this->getLogin()->isLogin()    ) $this->redirect('/login?loginback='.urlencode('/cha'),'请先登录');

        $pk = $this->getLogin()->getCookUser('pk');
        if( $pk!=7 ) $this->redirect('/login?loginback='.urlencode('/cha'),'请使用查单员登录');

        $cid= $this->getLogin()->getCookUser('u2');
        $cha= $this->getLogin()->createUserOne( $cid)->getUser();
        $this->assign('site_title', $cha['name'].'-查单 ' .$this->getLogin()->getCookUser('name'));
        //echo  $pk ;
        //$this->drExit( $cha );
        $this->htmlFile= "cha.phtml";
    }

    public function act_console(){

        $http_host = $_SERVER['HTTP_HOST'];

        //if( 'qf.zahei.com'== $http_host   ) $this->redirect('https://cz.crosscase.cn/console','');

        //if( ! in_array($http_host, ['pm.readface.cn','qunfu.zahei.com']) ) $this->drExit('404');
        if(  in_array($http_host, ['qunfu.readface.cn']) ) $this->drExit('404');

        $this->getLogin()->createTest()->limitConsole();

        if(! $this->getLogin()->isLogin()    ) $this->redirect('/login?loginback='.urlencode('/console'),'请先登录');
        if(!$this->getLogin()->isShenfen('p3')  ) $this->redirect('/login?loginback='.urlencode('/console'),'请使用操作员账号');

        $this->assign('site_title',$this->getLogin()->getCookUser('name').'-控制台');
        $is_group =$this->getLogin()->czGroup( $this->getLogin()->getUserId() )  ;

        if( $is_group && !isset($_GET['no']) ) $this->redirect('/consoleV2');

        $this->assign('isShou', $this->getLogin()->shouQuan()?1:0 );
        $version= $this->getLogin()->getVersionBYConsole( $this->getLogin()->getUserId() );

        $isMaVesion = $this->getLogin()->getMaVersionByCuid(  $this->getLogin()->getUserId());
        //if( in_array( $this->getLogin()->getUserId(), [ 1185]))
        $this->assign('version', $version)->assign('isMaVesion',$isMaVesion );
        //$this->assign('is_group', $is_group );
        $this->htmlFile= "console.phtml";

        $http_host = $_SERVER['HTTP_HOST'];
        /*if( strpos($http_host,'qmailq.com')){}
        else*/
            //if( in_array( $this->getLogin()->getUserId() ,[4335,2650] )  ) $this->drExit("暂时关闭"); // ,4902,4467
            if(  $this->getLogin()->isLimitLogin( $this->getLogin()->getUserId() )  ) {
                //$str=""
                //header()
                //header("HTTP/1.1 404 Not Found");
                //header("Status: 404 Not Found");
                header('Location: http://www.404.com/');
                $this->drExit("访问不存在");
            } // ,4902,4467

    }

    public function act_vip(){
        if(! $this->getLogin()->isLogin()    ) {
            $this->redirect('/login?loginback='.urlencode('/vip'),'请先登录');
        }

        $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $this->getLogin()->getUserId() );
        if( !$ma ) $this->redirect('/login?loginback='.urlencode('/vip'),'请使用码商账号登录');

        $ma['version']=$this->getLogin()->getVersionBYConsole(  $ma['c_user_id'] );
        //$userone= $this->createUserOne(  );
        $duser   = $this->getLogin()->createUserOne()->getUser();
        $ma['isGoogle']= $duser['google']?1:0;
        $ma['is_shoudan']= $this->getLogin()->isShoudan( $ma['c_user_id'] );


        $site_title= $this->getLogin()->getCookUser('name').'-抢单后台';
        if( $ma['role']==21) {
            $site_title = $this->getLogin()->getCookUser('name') . '-商户代理后台';
        }
        $this->assign('site_title',$site_title);
        $this->assign('ma', $ma );
        $this->assign('cwTab',  $this->getLogin()->createVip()->getTjCatShow()  );
        $this->htmlFile= "vip.phtml";

        $http_host = $_SERVER['HTTP_HOST'];
        if( strpos($http_host,'qmailq.com') || strpos( $http_host,'cl.')!==false || strpos( $http_host,'atbaidu') ){}
        elseif(in_array( $ma['c_user_id'],[2650]) || $this->getLogin()->isLimitLogin( $ma['c_user_id'] )){
            header('Location: http://www.404.com/');
            $this->drExit('访问不存在！');
        }
    }

    public function act_message($p){

        $str=print_r($_POST,true);
        $this->log("==act_message===". "==\n".$str , 'message_'.date("Ymd").'.log');
        $mq = new mq();
        //$var=['data'=>$_POST,''];
        $mq->rabbit_publish('taobao_message', $_POST );
        //$this->getLogin()->createTaobao()->doMessage( $_POST) ;
        $this->drExit('ok');

    }



    public function act_consoleV2(){
        $re=[];
        $re['url']= $this->getLogin()->getUrlGroup();
        $uArr = $this->getLogin()->czGroup( $this->getLogin()->getUserId() );
        if( !$uArr ) $this->throw_exception( "您无权限");
        $f2= [];
        foreach( $uArr as $v )$f2[$v]= $v;
        $user = $this->getLogin()->createUser()->getUserFromUid( $f2 );
        $this->assign('userList', $user )->assign('fuser', $uArr)->assign('re', $re );
        $this->assign('site_title', '多开控制台');
        $this->htmlFile= "console_v2.phtml";
    }

    public function act_finance(){
        $http_host = $_SERVER['HTTP_HOST'];
        //if( ! in_array($http_host, ['cw.readface.cn','qunfu.zahei.com']) ) $this->drExit('404');

        //if( 'cw.zahei.com'== $http_host  ) $this->redirect('https://cw.crosscase.cn/finance','');

        if(! $this->getLogin()->isLogin() ) $this->redirect('/login?loginback='.urlencode('/finance'),'请先登录');
        if(!$this->getLogin()->isShenfen('p2')  ) $this->redirect('/login?loginback='.urlencode('/finance'),'请使用财务账号');
        $this->assign('site_title','财务');
        $fuser = $this->getLogin()->financeUser(); $f2=[];
        foreach( $fuser as $v )$f2[$v]= $v;
        $user = $this->getLogin()->createUser()->getUserFromUid( $f2 );
        $this->assign('userList', $user )->assign('fuser', $fuser);
        $this->htmlFile= "finance.phtml";
    }
    public function act_merchant(){

        if(! $this->getLogin()->isLogin()  || !$this->getLogin()->getCookUser('mc_id') ) $this->redirect('/login?loginback='.urlencode('/merchant'),'请先登录');

        $this->assign('site_title',$this->getLogin()->getCookUser('name').'(商户号：'. $this->getLogin()->getCookUser('mc_id').')');
        $mc= $this->getLogin()->createTableMerchant()->getRowByKey( $this->getLogin()->getCookUser('mc_id') );
        $sv=['pay_type'=> $mc['pay_type']];  //
        if( $mc['child_len']>0 ){
            //$this->assign('pay',);
            $sv['pay']=  [1=>'支付宝H5',2=>'微信', 4=>'云闪付',11=>'网银',5=>'支付宝扫码'];
            if( $this->getLogin()->getCookieOther('pt'))  $sv['pay_type']= $this->getLogin()->getCookieOther('pt') ;
        }
        //$this->getLogin()->set
        $this->htmlFile= "merchant.phtml";
        $this->assign('sv', $sv)->assign('cookie', $this->getLogin()->getCookUser()) ;
    }

    public function act_taobao(){

        //print_r($_SERVER);
        //$this->drExit($_GET);
        $url='https://cz.xyxy521.com/console/zhifu/alitaobao?'.$_SERVER['QUERY_STRING'] ;
        $this->redirect( $url );
        die();
        $client = drFun::getClient();
        $url ='https://render.alipay.com/p/f/fd-j6lzqrgm/guiderofmklvtvw.html?shareId=2088002122250336&campStr=p1j%2BdzkZl018zOczaHT4Z5CLdPVCgrEXq89JsWOx1gdt05SIDMPg3PTxZbdPw9dL&sign=oJC%2BdxH1%2B7iAco%2Bgc6MH7RVY3Kp5Yzhvb8hvUd0wc%2BM%3D&scene=offlinePaymentNewSns';
        $url ='https://render.alipay.com/p/f/fd-j6lzqrgm/guiderofmklvtvw.html?shareId=2088712103314389&campStr=p1j%2BdzkZl018zOczaHT4Z5CLdPVCgrEXq89JsWOx1gdt05SIDMPg3PTxZbdPw9dL&sign=I8NUFpu%2BRMe0UQ%2FYRdgIiyVABoCQknJQbgWFa0lj9z0%3D&scene=offlinePaymentNewSns';
        if($client==2) {
            header('Location: '.$url);
            parent::drExit();//释放资源
        }
        $this->assign('client',$client );
        $this->htmlFile= "taobao.phtml";
    }

    /**
     * 编辑人员入口
     */
    public function act_editor(){
        parent::checkEditorByCookie();
        $this->htmlFile= "editor.phtml";
    }

    public function act_imgYzm(){
        session_start();
        $text = mt_rand(1000,9999);
        $_SESSION['hd_code'] = $text;
        drFun::imgYzm( $text );
        $this->drExit();
    }

    public function act_vCode(){
        drFun::vCodeYzm();
        $this->drExit();
    }

    /**
     * 学校管理员入口
     */
    public function act_school(){
        parent::checkSchoolAdminByCookie();
        $school = $this->getLogin()->getCookUser('school');
        $term_conf = $this->getLogin()->createTerm()->getConfigForSchool( $this->getLogin()->getSchoolID(), $this->getLogin()->createTerm()->getNow() );
        $this->assign('term_conf', $term_conf )
        ->assign('isSchoolAll', $this->getLogin()->isSchoolAll() );
        $this->site_title =$school. "-后台管理";
        $this->assign('block', $this->getLogin()->createBlock()->getNameList(['school_id'=>$this->getLogin()->getSchoolID() ]));
        $this->htmlFile="admin_school.phtml";
    }

    public function act_webim(){
        if( !$this->getLogin()->isLogin() ) $this->redirect( "/login?loginback=" . urlencode($_SERVER['REQUEST_URI']) ); //
        $groupid='zb';
        $list= $this->getLogin()->createLogChat()->getList( $groupid,['decode'=>1 ]);
        $this->assign('list', $list );
        $user = $this->getLogin()->createUser()->getUserFromArray( $list );
        $this->assign('user', $user );
        $this->site_title= "好策读书";
        $this->htmlFile='webim.phtml';
    }

    public function act_bm(){
        $this->setCdn();
        $this->htmlFile='help/bm.phtml';
    }

    function setCdn(){
        $cdn = drFun::getCdn();//$_SERVER['SERVER_ONLINE']=='qqyun' ?'https://cdn.haoce.com':'';
        $this->assign('hc_app',$cdn.'/res/hcapp')->assign('version','2018050605');
    }

    function act_ts(){
        $cl_day = new day();
        //$cl_day->setMcID(8080)->setDay("2018-09-07")->appendByTrade()->appendByFinance();
        $cl_day->setMcID(8597)->setDay("2019-10-26")->appendByTradeNotify()->appendByTrade(); ;//appendByTrade()->appendByFinance();
    }

    function act_er(){
        $this->throw_exception('ddd',405);
    }

    function act_look(){

        $this->htmlFile='look.phtml';
    }

    function act_health(){
        //$this->getLogin()->createQrPay()->checkHealth([1,11,2]);

        $re = [];
        $this->getLogin()->createQrPay()->checkNoOnline($re);

        $this->displayJson( $re );
        //$this( $re );
    }

    function act_uploadFile( ){
        $file = $_FILES['file'];
        $opt =[];
        $opt['dir']='qr';
        $opt['ext']= ['jpg'=> 1,'png'=>1,'gif'=>1] ;
        $r= drFun::upload( $file ,$opt); //
    }

    function act_google( $p ){

        switch ($p[0]){
            case 'post':
                //$this->drExit($_POST);
                if( !$this->getLogin()->isLogin() )  $this->throw_exception("请先登录！");
                $this->getLogin()->createUserOne( $this->getLogin()->getRealUid()  )->checkGoogle( $_POST['code'] );
                session_start();
                $_SESSION['google']= $this->getLogin()->getUserId();

                $this->redirect("","验证成功,请刷新进入！");
                break;
            case 'price':
                $this->drExit( $this->getLogin()->checkGoogleLogin()  );
                break;
        }

    }



    //function act_



}
