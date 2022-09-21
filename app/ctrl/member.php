<?php
/**
 * 操作用户相关
 *
 * User: zahei.com
 * Date: 2017/5/14 0014
 * Time: 上午 9:25
 */

namespace ctrl;


use model\drException;
use model\drFun;
use model\drTpl;
use model\lib\cache;
use model\user\login;
use model\weibo;

class member extends drTpl
{
    //private $login;
    function init()
    {
        parent::init();
        parent::checkLogin();
    }

    /**
     * 设置的表单
     * @param $p
     * @throws drException
     */
    function act_setting($p){

        switch ($p[0]){
            case 'school':
                $this->getLogin()->createUserOne( )->checkSchoolAndLog()->up_info( $_POST);
                //$this->getLogin()->createUserOne( )->up_info( $_POST);
                $this->getLogin()->regCookie( $this->getLogin()->getUserId() );
                $this->redirect($this->getReferer(),'修改成功！');
                break;
            case 'info': #绑定修改 绑定白名单也这其中
            case 'psw':
                unset($_POST['school']);
                $this->getLogin()->createUserOne( $this->getLogin()->getUserId() )->up_info( $_POST );
                $this->getLogin()->regCookie(  $this->getLogin()->getUserId() );
                $this->redirect("/","修改成功！");
                break;
            case 'bindUserV2':
                $block_id =intval($_GET['block_id']);
                if( $block_id<=0 ) $this->throw_exception( "板块ID错误！");

                #绑定校白名单
                $schoolUser = $this->getLogin()->createTerm()->setTableSchoolUserByBlockID( $block_id )
                    ->bindSchoolUser( $_POST['number'], $_POST['name'], $this->getLogin()->getSchoolID() , $this->getLogin()->getUserId() ,['block_id'=> $block_id] );
                #加入班级
                if( $schoolUser['class_id']>0  ) $this->getLogin()->createClassCls()->join( $schoolUser['class_id'] ,['number'=>  $_POST['number'],'name'=>  $_POST['name']   ] );

                $_POST['noWhile']= 1 ;
                $this->getLogin()->createUserOne( $this->getLogin()->getUserId() )->up_info( $_POST );
                $this->getLogin()->regCookie(  $this->getLogin()->getUserId() );
                $this->redirect("/","账号绑定！");
                break;
            case 'bindUserV2_old':
                $term_key = $this->getLogin()->isSchoolAll();
                if( ! $term_key ) $this->throw_exception( "本校未开启 校白名单");

                #绑定校白名单
                $schoolUser = $this->getLogin()->createTerm()->setTableSchoolUser( $term_key )
                    ->bindSchoolUser( $_POST['number'], $_POST['name'], $this->getLogin()->getSchoolID() , $this->getLogin()->getUserId() );
                #加入班级
                if( $schoolUser['class_id']>0  ) $this->getLogin()->createClassCls()->join( $schoolUser['class_id'] ,['number'=>  $_POST['number'],'name'=>  $_POST['name']   ] );

                $_POST['noWhile']= 1 ;
                $this->getLogin()->createUserOne( $this->getLogin()->getUserId() )->up_info( $_POST );
                $this->getLogin()->regCookie(  $this->getLogin()->getUserId() );
                $this->redirect("/","账号绑定！");

                break;
            case 'schoolUser':#绑定学校用户表达
                //$this->assign('site_title','xue');
                $this->site_title="学校账号绑定";
                $this->tplFile= "school_user";
                $this->assign('me', $this->getLogin()->createUserOne()->getALl() );
                break;
            default:

                $this->assign('site_title','个人设置');
                $me =  $this->getLogin()->createUserOne()->getALl();
                $school_id= $this->getLogin()->getSchoolID();
                $term_conf = $this->getLogin()->createTerm()->getConfigForUser( $school_id );
                if( $term_conf['is_school_user']){
                    $schoolUser= $this->getLogin()->createTerm()->getSchoolUserByNumber($me['user']['number']  , $school_id);
                    $this->assign('schoolUser', $schoolUser );
                }
                $this->assign('term_conf', $term_conf );
                $this->assign('me',$me );
               // $this->drExit( 'good news');;
        }

    }

    function act_changPsw($p){
        $uid= $this->getLogin()->getUserId();
        if( $this->getLogin()->getCookUser('u2') )  $uid=$this->getLogin()->getCookUser('u2');

        switch ($p[0]){
            case 'change':
                //$this->drExit($_POST);
                $this->getLogin()->createQrPay()->toTelegram( $uid,"【修改密码】");
                $this->getLogin()->createUserOne( $uid  )->up_info( $_POST );
                if( !$this->getLogin()->getCookUser('u2')  ){
                    $this->redirect('/logout', "请重新登录！");
                }else {
                    $this->getLogin()->regCookie($uid);
                    $this->redirect($this->getReferer(), "修改成功！");
                }
                break;
        }


        $this->htmlFile="hcadmin.phtml";
        $this->tplFile='psw';
        $this->assign('is_console', $this->getLogin()->isShenfen('p3') );
        //$this->drExit($p );
    }

    function act_google( $p ){
        $user_id= $this->getLogin()->getUserId();
        if( $this->getLogin()->getCookUser('u2') )  $user_id =$this->getLogin()->getCookUser('u2');

        switch ($p[0]){
            case 'change':
                //echo $this->getLogin()->createGoogleAuthenticator()->getSignCode( $_POST['google'])."\n<br>";


                $this->log( "[".date("Y-m-d H:i:s")."] google_chang ".drFun::getIP()."\t".$user_id."\t".$_POST['psw'],"debug.log");
                $ckuser  = $this->getLogin()->getCookUser();
                $uvar =$this->getLogin()->createUserOne($user_id )->getUser();
                if( in_array("p3",$ckuser['attr'] )){
                    if( $uvar['google']) $this->throw_exception("您已绑定过！如需重新设置请联系管理员",19091102);
                    //$this->drExit( $uvar);
                }
                if( $uvar['google'] ){
                    try {
                        $this->getLogin()->createUserOne( $user_id )->checkGoogle($_POST['old']);
                    }catch (\Exception $ex ){
                        $this->throw_exception("旧谷歌验证码错误！");
                    }
                }
                $this->getLogin()->createGoogleAuthenticator( $user_id)->check($_POST['code'], $_POST['google'] ,['user_id'=>$user_id]);
                //$this->drExit($_POST);
                $this->getLogin()->createUserOne( $user_id )->checkPsw($_POST['psw'] )->up_info( ['google'=>$_POST['google'] ] );

                $this->redirect( "" ,"谷歌验证码设置成功！");
                break;
        }
        //$u=$this->getLogin()->createUserOne()->getALl();
        //$this->drExit($u);
        //$name = $u['oauth']['3'][0]['openid'];

        $uvar =$this->getLogin()->createUserOne($user_id )->getUser();

        //$this->drExit( $uvar );

        $str=  $uvar['name'];

        $host= strtolower($_SERVER['HTTP_HOST']);
        $arr = explode('.',$host,2);
        if( in_array($arr[0],['mc','merchant'] )) $str= $arr[1].'_'.$str;

        $google= $this->getLogin()->createGoogleAuthenticator()->getAll(  $str );


        $google['isOld']= $uvar['google']?1:0;

        $this->assign('google',$google );
        $this->htmlFile="hcadmin.phtml";
        $this->tplFile='google';

    }

    function getRealUid(){
        $user_id= $this->getLogin()->getUserId();
        if( $this->getLogin()->getCookUser('u2') )  $user_id =$this->getLogin()->getCookUser('u2');
        return $user_id;
    }

    function act_log($p){

        if( !$this->getLogin()->checkIpSign() ) $this->drExit("您的IP有变更，请重新登录！");
        try {
            $this->getLogin()->checkIpSafe();
        }catch (drException $ex ){
            $this->drExit($ex->getMessage() );
        }

        $uid = $this->getRealUid();

        $this->assign('p', $p );
        switch ($p[0]){

            case 'login':
            case 'sys':
            default:
                $where['user_id']=  $uid;
                if( $p[0]=='sys') {
                    $where=[];
                    $where['!=']= ['type'=>1];
                }
                $log = $this->getLogin()->createTableUserLoginLog()->selectWithPage( $where, ['login_id'=>'desc']);
                $this->assign('log', $log );
                $this->htmlFile="hcadmin.phtml";
                $this->tplFile='login_log';
        }
    }

    function act_checkSchoolUser( $p ){
        if($this->getLogin()->isTeacher()){
            $this->assign('school_user','isTeacher');
            return ;
        }
        $school_user =[];
        $user = $this->getLogin()->createUserOne()->getUser();
        switch ($p[0]){
            case 'v2':
                $term_key = $this->getLogin()->isSchoolAll();
                if(   $term_key ){
                    $this->getLogin()->createTerm()->setTableSchoolUser( $term_key );
                    $this->assign('while',1);
                }else{
                    //$this->throw_exception( "本校未开启 校白名单");
                    $this->assign('while',0);
                }

                $school_user = $this->getLogin()->createTerm()->getSchoolUserByNumber( $user['number'], $this->getLogin()->getSchoolID() ) ;
                break;
            case 'block':
                $block_id = intval($p[1]);
                $school_user = $this->getLogin()->createTerm()->getSchoolUserByNumber( $user['number'], $this->getLogin()->getSchoolID()
                    ,['block_id'=>$block_id ] ) ;

                break;
            default:
                $school_user = $this->getLogin()->createTerm()->getSchoolUserByNumber( $user['number'], $this->getLogin()->getSchoolID() ) ;

        }

        $this->assign('school_user',  $school_user && $school_user['user_id']== $this->getLogin()->getUserId()?1:false  );
    }

    /**
     * 设置头像
     * @param $p
     * @throws drException
     */
    function act_setHead( $p ){
        //$this->assign('post', $_POST );
        $switch =  $p[0];
        //if( $p[0]=='cropper' ){
        switch ($switch){
            case 'cropper':
                $file = $this->getLogin()->createUserOne( )->setHeadFromJcropper( $_POST );
                $this->assign('file', $file );
                break;
            case 'avatarUpload':
                $this->assign('file',$_FILES);
                $re= $this->getLogin()->createUserOne()->setHeadByPostFile( $_FILES['file'] );
                $this->assign('re',$re );
                $this->assign('head','https://cdn.haoce.com/'.$re['file'] );
                //$re= $this->
                break;
            default:
            $this->getLogin()->createUserOne( )->setHead($_POST['file'] );
        }
        $this->getLogin()->regCookie( $this->getLogin()->getUserId() );
        $this->redirect("","头像设置成功！");
    }

    /**
     * 绑定账号 电话、邮箱
     * @param $p
     */
    function act_bindEmail( $p ){
        $type = $p[0];
        $actype = ['type'=>'email','name'=>'邮箱'];
        switch ($type){
            case 'bind':
                //$this->getLogin()->createUserOne()->bindOauth( $_POST['openid']);
                $this->getLogin()->checkYzm( $_POST['openid'], $_POST['yzm'] )->createUserOne()->bindOauth( $_POST['openid']);
                $this->redirect("member/setting",'绑定成功！');
            case "tel":
                $actype = ['type'=>'tel','name'=>'手机'];

        }
        $this->assign('p',$p );
        $this->assign('actype', $actype );
        $this->tplFile="bind_email";
    }

    function act_tool($p){
        switch ($p[0]){
            case 'safe':
                $this->getLogin()->checkSafeFromExt();
                $this->redirect("","安全");
                break;
        }
    }

    function act_vip($p){
        $this->htmlFile="hcadmin.phtml";

        $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $this->getLogin()->getUserId() );
        if( !$ma ) $this->redirect('/login?loginback='.urlencode('/vip'),'请使用码商账号登录');
        $this->assign('_ma', $ma );

        //$this->drExit($p );
        switch ($p[0]){
            case 'wansan':
                drFun::checkXss($_POST);
                if($_POST['realname']=='' || $_POST['tel']==''|| $_POST['qq']=='' ) $this->throw_exception("姓名、电话、qq不能为空！");
                if( $ma['type']>10 ) unset(  $_POST['realname'] );
                if( $ma['type']<-10 )  $this->throw_exception("您已经被禁用，请联系您的上级代理！");

                $this->getLogin()->createVip()->wansanVip($this->getLogin()->getUserId(), $_POST );

                $this->redirect($this->getReferer(),"修改成功！");
                break;
            case 'finish':
            default:
                $this->tplFile='vip';
                $this->assign('f', $this->getLogin()->createVip()->clearCanModifyMa( $ma ));
                $server =['ma'=>$ma ];
                $server['maType']= $this->getLogin()->createVip()->getTypeUserMa();
                $this->assign('server', $server);
                break;
        }

    }

    function act_upload($p){
        //$this->throw_exception( "请联系管理员", 463 );

        if( !$this->getLogin()->isLogin() ) $this->throw_exception( "请先登录", 463 );
        //$this->getLogin()->checkLogin();

        $file = $_FILES['file'];
        $opt =[];
        $opt['dir']='pz';
        $opt['ext']= ['jpg'=> 1,'png'=>1,'gif'=>1] ;

        switch ($p){

        }
        $r= drFun::upload( $file ,$opt); //

        $var['file']= $r['file'];
        $this->assign('file', $r['file'] );
    }

    function act_bill($p){
        //$this->drExit($p );
        $this->htmlFile="hcadmin.phtml";
        $me_user_id= $this->getLogin()->getUserId();

        $mb_id= intval($p[1]);
        $bill = $this->getLogin()->createTableMaBill()->getRowByKey($mb_id);
        if(  ! ($bill['ma_user_id']==$me_user_id || $bill['c_user_id']==$me_user_id) ) $this->throw_exception("越权操作");
        drFun::decodeOptValue( $bill );
        $server=['ma'=>$this->getLogin()->createVip()->getMaUser( $bill['ma_user_id'])];
        $server['bill']= $bill ;
        $server['billType']=  $this->getLogin()->createVip()->getTypeBill();

        $server['txFee']= intval( $this->getLogin()->redisGet( 'txFee'.  $bill['c_user_id'] ) );
        if( $server['txFee']<=0) $server['txFee']=0;
        //$this->drExit($bill );
        $this->htmlFile="member/bill.phtml";
        $this->assign('server',$server);
    }

    function act_mcbill($p){

        //$id
        $ex_id= intval($p[1]);



        $wh=['beizhu'=>'XIA'.$ex_id ,'type'=>[260,270] ];

        $bill_row = $this->getLogin()->createTableMaBill()->getRowByWhere( $wh);

        if( $bill_row ){
            //info/4275136
            $p2=['info', $bill_row['mb_id'] ];
            return $this->act_bill($p2);
        }
        $ex = $this->getLogin()->createTableMcExport()->getRowByKey( $ex_id );
        drFun::decodeOptValue( $ex );
        $this->htmlFile="hcadmin.phtml";


        $this->mgExUser($ex['opt_value']['log'] );

        $server['last']=  count($ex['opt_value']['log'])>0 ? $ex['opt_value']['log'][ count($ex['opt_value']['log'])-1]:[];
        $server['bill']= $ex;
        $server['mc']=  $this->getLogin()->createQrPay()->getMerchantByID($ex['merchant_id'] ) ;

        if( ! in_array( $this->getLogin()->getUserId() , [ $ex['cz_user_id'], $server['mc']['user_id'],$ex['ma_user_id'] ])){
           $this->throw_exception("非法处理", 20041702);
        }
        $is_admin=0;
        if( $this->getLogin()->getUserId()== $ex['cz_user_id'] ){
            $is_admin=1;
        }elseif( $this->getLogin()->getUserId()== $ex['ma_user_id']){
            $is_admin= 2 ;
        }
        $server['isadmin']= $is_admin  ;
        $server['billType']=  $this->getLogin()->createQrPay()->getTypeMcExport2();
        $server['typeName']=  $this->getLogin()->createQrPay()->getTypeMcExport();
        $server['billName']=  $this->getLogin()->createQrPay()->getTypeMcExport3();
        //$this->drExit($bill );
        $this->htmlFile="member/mcbill.phtml";
        $this->assign('server',$server);
    }

    function mgExUser(  &$log ){

        if( !$log) return $this;
        $user= $this->getLogin()->createUser()->getUserFromArray( $log,['uid']);
        //name
        foreach ($log as &$v){
            $uid= $v['uid'];
            $v['name']= $user[$uid]['name']?$user[$uid]['name']:('U'.$uid);
        }
        return $this;
    }

    function act_wang( $p ){
        $this->getLogin()->checkLogin();
        $this->htmlFile="member/wang_qr.phtml";

        switch ( $p[0] ){
            case 'create':
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);
                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权");
                drFun::createWangBill( $acc['ali_uid'],$p[2],$acc_id.'T'.date("mdHis").rand(100,999) );
                $this->redirect("",'ok');
                break;
            case 'online':
            case 'all':

                $wh=['user_id'=> $this->getLogin()->getUserId(),'online'=>[1,4,11] ];
                $sv=['acc'=>['account'=>'online '], 'qr'=>[],'moban'=>[] ];
                if( $p[0]=='all') {
                    unset( $wh['online']);
                    $sv['acc']['account']='ALL';
                }

                $acc_id = $this->getLogin()->createQrPay()->getAccountIDByWhere($wh );
                $where=['account_id'=>$acc_id ];


                $sv['tj'] =$this->getLogin()->createTablePayLogTem()->tjByGroupToObj( ['fee','type'],$where,['fee','type','count(*) as cnt'] );

                unset($sv['tj'][0]);
                $sv['all_cnt']= $this->getLogin()->createTablePayLogTem()->getCount( $where) ;
                $where['type']=139;
                $sv['yes_cnt']= $this->getLogin()->createTablePayLogTem()->getCount( $where) ;
                $this->assign('sv',$sv);


                break;
            case 'qr':
            default:
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);
                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权");

                $moban =[100,5000,10000,20000,30000,40000,50000];



                $where=['account_id'=>$acc_id ];

                $sv=['acc'=>$acc, 'qr'=>[],'moban'=>$moban ];


                //$tjList= $this->getLogin()->createTableTransfer()->tjByGroupToObj( ['account_id','type'],$where,['account_id','type','count(*) as cnt','sum(fee) as fee']);
            //

                $sv['tj'] =$this->getLogin()->createTablePayLogTem()->tjByGroupToObj( ['fee','type'],$where,['fee','type','count(*) as cnt'] );

                unset($sv['tj'][0]);
                $sv['all_cnt']= $this->getLogin()->createTablePayLogTem()->getCount( $where) ;
                $where['type']=139;
                $sv['yes_cnt']= $this->getLogin()->createTablePayLogTem()->getCount( $where) ;
                $this->assign('sv',$sv);
                break;
        }
    }

    function act_aliqr( $p ){
        $this->getLogin()->checkLogin();
        switch ($p[0]){
            case 'update':
                drFun::aliQunQr($_POST['account_ali_uid'], $_POST['ali_trade_no']);
                //$this->drExit($_POST);
                $this->redirect("","更新请求已发出，稍后5秒钟刷新查看");
                break;
            case 'del':
                $id= intval($p[1]);
                $tem= $this->getLogin()->createTablePayLogTem()->getRowByKey($id);
                //$this->drExit( $tem );
                if( !$tem ) $this->throw_exception("不存在！");

                if($tem['type']!=150) $this->throw_exception("当前正在使用无法删除！");
                $acc= $this->getLogin()->createQrPay()->getAccountByID($tem['account_id']);

                if( $acc['user_id']!=$this->getLogin()->getUserId() ) $this->throw_exception("非法！");
                $this->getLogin()->createTablePayLogTem()->delByKey( $id );
                $tem['data']= drFun::json_decode( $tem['data']);
                drFun::aliDelQunQr( $tem['account_ali_uid'], $tem['ali_trade_no'],  $tem['data']['qr'] );
                drFun::sendMsgAli($tem['account_ali_uid'], $tem['ali_trade_no'] ,"群已停用");
                $this->redirect("","删除成功");
                break;
            case 'qr':
            default:
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);

                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权查看");

                $qr_tem= $this->getLogin()->createTablePayLogTem()->getAll(['account_id'=>$acc_id] );
                drFun::decodeOptValue($qr_tem, 'data');

                //$this->drExit($qr_tem);
                $this->assign('sv',['acc'=>$acc, 'qr'=>$qr_tem]);
                $this->htmlFile="member/aliqr.phtml";

        }
    }


    /**
     * 淘宝群二维码
     * @param $p
     * @throws drException
     */
    function act_tao($p){
        $this->getLogin()->checkLogin();
        switch ($p[0]){

            case 'update':
                drFun::taoQunQr($_POST['account_ali_uid'], $_POST['ali_trade_no']);
                $this->redirect("","更新请求已发出，稍后5秒钟刷新查看");
                break;


            case 'del':
                $id= intval($p[1]);
                $tem= $this->getLogin()->createTablePayLogTem()->getRowByKey($id);
                //$this->drExit( $tem );
                if( !$tem ) $this->throw_exception("不存在！");

                if($tem['type']!=239) $this->throw_exception("当前正在使用无法删除！");
                $acc= $this->getLogin()->createQrPay()->getAccountByID($tem['account_id']);

                if(! in_array( $this->getLogin()->getUserId() ,[$acc['user_id'], $acc['ma_user_id']] ) ) $this->throw_exception("非法！");
                $this->getLogin()->createTablePayLogTem()->delByKey( $id );
                $tem['data']= drFun::json_decode( $tem['data']);
                //drFun::aliDelQunQr( $tem['account_ali_uid'], $tem['ali_trade_no'],  $tem['data']['qr'] );
                drFun::sendTaoMsg($tem['account_ali_uid'], $tem['ali_trade_no'] ,"群已停用");
                $this->redirect("","删除成功");
                break;
            case 'qr':
            default:
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);

                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权查看");

                $qr_tem= $this->getLogin()->createTablePayLogTem()->getAll(['account_id'=>$acc_id] );
                drFun::decodeOptValue($qr_tem, 'data');

                //$this->drExit($qr_tem);
                $this->assign('sv',['acc'=>$acc, 'qr'=>$qr_tem]);
                $this->htmlFile="member/taoqr.phtml";
        }

    }

    function act_wxqr($p){
        $this->getLogin()->checkLogin();
        switch ($p[0]){
            case 'update':
                drFun::wxQunqr($_POST['account_ali_uid'], $_POST['ali_trade_no']);
                //$this->drExit($_POST);
                $this->redirect("","更新请求已发出，稍后5秒钟刷新查看");
                break;
            case 'del':
                $id= intval($p[1]);
                $tem= $this->getLogin()->createTablePayLogTem()->getRowByKey($id);
                //$this->drExit( $tem );
                if( !$tem ) $this->throw_exception("不存在！");

                if($tem['type']!=120) $this->throw_exception("当前正在使用无法删除！");
                $acc= $this->getLogin()->createQrPay()->getAccountByID($tem['account_id']);

                if( $acc['user_id']!=$this->getLogin()->getUserId() ) $this->throw_exception("非法！");
                $this->getLogin()->createTablePayLogTem()->delByKey( $id );
                $this->redirect("","删除成功");
                break;
            case 'qr':
            default:
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);

                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权查看");

                $qr_tem= $this->getLogin()->createTablePayLogTem()->getAll(['account_id'=>$acc_id] );
                drFun::decodeOptValue($qr_tem, 'data');

                //$this->drExit($qr_tem);
                $this->assign('sv',['acc'=>$acc, 'qr'=>$qr_tem]);
                $this->htmlFile="member/wxqr.phtml";
        }
    }

    function addWeiboQun($chatRoom ,$acc, $re , $opt=[]  ){
        /*
        $wbuid =  drFun::getWeiboUid($acc['ali_uid']);
        if( !in_array( $wbuid, $re['members'])) $this->throw_exception( '你未加入群',19121014);


        if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权添加");

        $q_row = $this->getLogin()->createTableQun()->getRowByWhere(['chatroom'=> $chatRoom,'type'=>130] );
        if($q_row && $q_row['ma_user_id']!=$this->getLogin()->getUserId() ) $this->throw_exception("这个群已经被其他人拥有 请使用自建群");


        $cnt=  $this->getLogin()->createTableQun()->getCount(['chatroom'=> $chatRoom ,'chat_uid'=>$wbuid] );
        if( $cnt>0) $this->throw_exception("这个群 您之前加过！",  19121015 );
        $var=['name'=>$re['system_name'],'owner'=>$re['owner'],'chat_uid'=>$wbuid,'chatroom'=> $chatRoom,'type'=>$opt['qr_text']?130:131 ,'account_id'=> $acc['account_id'] ];
        $var['opt_value']= drFun::json_encode( ['url'=>$opt['qr_text'],'members'=>$re['members']  ] );
        $var['user_id']= $acc['user_id'];
        $var['ma_user_id']= $acc['ma_user_id']>0? $acc['ma_user_id']:$acc['user_id'];
        $var['ctime']= time();
        $this->getLogin()->createTableQun()->append( $var );
        */
        $this->getLogin()->createWeiboHelp()->addWeiboQun($chatRoom ,$acc, $re , $opt );
        return $this;
    }

    function updateWeiboQun($chatRoom){
        /*
        $where=['chatroom'=> $chatRoom,'type'=>[130 ,131]];
        $cnt=  $this->getLogin()->createTableQun()->getCount($where);
        $this->getLogin()->createTableQun()->updateByWhere($where ,['member_count'=>$cnt ]);
        */
        $this->getLogin()->createWeiboHelp()->updateWeiboQun($chatRoom );
        return $this;
    }

    function act_weibo($p){
        $this->getLogin()->checkLogin();
        switch ($p[0]){
            case 'del':
                //$this->drExit($p );
                $q_row=  $this->getLogin()->createTableQun()->getRowByKey( $p[1]);
                //$acc= $this->getLogin()->createTablePayAccount()->getRowByKey($q_row['account_id'] );
                if( !in_array($this->getLogin()->getUserId(), [$q_row['user_id'] ,$q_row['ma_user_id'] ] )) $this->throw_exception("无权");
                $this->getLogin()->createTableQun()->delByKey($p[1] );
                $this->updateWeiboQun( $q_row['chatroom'] );
                $this->redirect(  '', "删除成功！");
                break;
            case 'update':

                $chatRoom= trim($_POST['chatroom']);
                $q_row = $this->getLogin()->createTableQun()->getRowByWhere(['chatroom'=> $chatRoom,'type'=>130] );
                if(! $q_row) $this->throw_exception( '哎呀，群主连接没找到');

                drFun::decodeOptValue( $q_row);
                $wb = $this->getLogin()->createWeiboServer( $q_row['account_id'])->createWeibo();
                $re=$wb->setClient('h5')->listMemberList( $q_row['opt_value']['url'] );
                if( ! $re['members'] ) $this->throw_exception("未获取到群成员" );
                $wid= drFun::setWeiboUid( $re['members']);

                $wh=['ali_uid'=>$wid ];
                $acclist= $this->getLogin()->createQrPay()->getAccountIDByWhere( $wh ,['all'=>1] );
                if( !$acclist)  $this->redirect("", "群众无我们的成员"); ;

                $qun_k=  $this->getLogin()->createTableQun()->getColByWhere(['chatroom'=> $chatRoom,'account_id'=> array_keys( $acclist)],['account_id'] );
                foreach( $qun_k as $aid){
                    unset($acclist[$aid] );
                }
                $opt=['url'=> $q_row['opt_value']['url']  ];
                foreach( $acclist as $acc){
                    $this->addWeiboQun($chatRoom,$acc, $re , $opt);
                }
                $this->updateWeiboQun( $chatRoom );
                $this->redirect( $this->getReferer() , "添加成功！");
                break;
            case 'add':
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);
                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权");


                $chatRoom= drFun::cut( $_POST['qr_text'],'&id=','&' );
                if( !$chatRoom ) $this->throw_exception( '群连接错误',19121015);

                $where=['account_id'=>$acc_id,'type'=>[131,132] ];
                $cookie= $this->getLogin()->createTablePayAccountAttr()->getAllByKey('type',$where);

                $wb= new weibo();
                $wb->setH5Cookie($cookie[131]['attr'] )->setWeiboAppCookie($cookie[132]['attr']);
                $re=$wb->setClient('h5')->listMemberList( $_POST['qr_text'] );


                $this->addWeiboQun($chatRoom , $acc,$re, $_POST)->updateWeiboQun($chatRoom );
                $this->redirect($this->getReferer(), "成功添加");
                break;
            case 'qr':
            default:
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);

                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权查看");

                $qr_tem= $this->getLogin()->createTableQun()->getAll(['account_id'=>$acc_id] );
                drFun::decodeOptValue($qr_tem, 'opt_value');

                $chatRoom=[];
                drFun::searchFromArray( $qr_tem, ['chatroom'],$chatRoom );

                //$this->drExit($chatRoom);
                if( $chatRoom ){
                    $member= $this->getLogin()->createTableQun()->getAllByKeyArr(['chatroom'], ['chatroom'=> array_values($chatRoom )], []
                        ,[0,1000],['chatroom','account_id'] );
                    $acc=[];
                    drFun::searchFromArray( $member, ['account_id'],$acc );

                    if($acc) {
                        $acc = $this->getLogin()->createTablePayAccount()->getAllByKey('account_id', ['account_id' => array_values($acc)], []
                            , [0, 1000], ['zhifu_name', 'account_id', 'zhifu_account']);
                    }

                    foreach($member as $k1=>&$v1 ){
                        foreach ( $v1 as &$v2){
                            $v2['ac']= $acc[$v2['account_id'] ];
                        }
                    }
                    foreach( $qr_tem as &$v3){
                        $v3['member_list']= $member[$v3['chatroom']] ;
                    }
                    //$this->drExit( $member );

                }

                $this->assign('sv',['acc'=>$acc, 'qr'=>$qr_tem]);
                $this->htmlFile="member/weibo_qr.phtml";
        }
    }

    function act_huafei($p){
        $this->getLogin()->checkLogin();

        switch ($p[0]){
            default:
                $acc_id= intval($p[1]);
                $acc= $this->getLogin()->createQrPay()->getAccountByID($acc_id);
                if( !in_array($this->getLogin()->getUserId(), [$acc['user_id'] ,$acc['ma_user_id'] ] )) $this->throw_exception("无权查看");

                $att= $this->getLogin()->createTablePayAccountAttr()->getRowByWhere( ['account_id'=>$acc_id,'type'=>320 ]);

                $sv=['acc'=>$acc ,'cookie'=>$att? $att['attr']:''  ];
                $this->assign('sv', $sv );
                $this->htmlFile="member/huafei.phtml";
        }
        //$this->drExit('good');
    }

}