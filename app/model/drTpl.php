<?php
namespace model;
use DR\DR;
use model\user\login;

/**
 * html tpl 显示类 ctrl基本继承本类
 *
 */
class drTpl extends DR{
    private $dr;
    public $htmlFile='index.phtml';
    public $tplFile='';
    public $site_title ='';
    public $cookieUser =[];
    protected $admin=[];
    protected $is_admin = false ;
    protected $is_school= false ;
    private $display_format= 'html';

    static private $login=null;

    /**
     * 处理了cookie中的admin
     */
    public function __construct( )
    {
        $this->adminInit();
    }

    /**
     * 允许处理管理员选项 admin is_admin is_school
     */
    protected function adminInit(){
        $attr = $this->getLogin()->getCookUser('attr');
        foreach( $attr as $key ){
            $this->admin[ $key ]= 1 ;
        }
        if( isset( $this->admin['p1'])   ) $this->is_admin=1;
        elseif( isset( $this->admin['p2']) )$this->is_admin=2;
        if( isset( $this->admin['p3'])  ) $this->is_school =1;

        if( DR::$_URI['ctl'] !='api'){
            $this->assign('_admin', $this->admin);
            $this->assign('_is_admin', $this->is_admin);
            $this->assign('_is_school', $this->is_school);
            $this->assign('_is_teacher', $this->getLogin()->isTeacher());
            $this->assign('_cdn', drFun::getCdn()); //$_SERVER['SERVER_ONLINE']=='qqyun' ?'https://cdn.haoce.com':''
        }

        return $this;
    }


    /**
     * 初始化了一段变量在dsData中
     * - $DR_SELF
     * - DR_HTML_DIR
     * - DR_CTL ctrl函数名称
     * - DR_ACT ctrl中act名称
     * - WWW_ROOT 网页路径
     * - WWW_RES 静态文件路径 一把是 /res/ 或者可用CDN
     */
    public function init(){
        parent::init();
        $dr=array();
        $dr['DR_SELF']= $_SERVER['REQUEST_URI'];
        $tarr = preg_split('/[^\/]+\.php/', $_SERVER['PHP_SELF']);
        $dr['DR_HTML_DIR']= $tarr[0];
        $dr['DR_CTL']=  DR::$_URI['ctl'];
        $dr['DR_ACT']=  DR::$_URI['act'];
        $dr['WWW_ROOT']= WWW_ROOT;
        $dr['WWW_RES']= WWW_RES;

        $this->dr= $dr;
    }

    /**
     * 获取显示类型有可能是html 有可能是json
     *
     * 判断的一句为  $_GET['display']=='json' ||  $_SERVER['HTTP_X_DISPLAY']=='json' 头文件放X-DISPLAY: json 就显示json,其他都显示为html
     * @return string
     */
    final function getDisplayType(){
        if(isset( $_GET['display']) &&  $_GET['display']=='json' )   return 'json' ;
        if(isset( $_SERVER['HTTP_X_DISPLAY']) &&   $_SERVER['HTTP_X_DISPLAY']=='json') return 'json';
        //return 'html';
        return  $this->display_format ;
    }

    function setDisplay($ds){
        $this->display_format = $ds ;
    }

    /**
     * 继承DR的显示方法，通过{@link getDisplayType} 显示不同的格式内容
     */
    public function display(){
        if(  $this->site_title ) {
            $this->assign('site_title', $this->site_title );
        }
        if( $this->getDisplayType()=='json') $this->dJson();

        if( $_GET['local']=='write'){
            $this->assign('hc_app','../../..');
            $this->assign('local_version','');
            $p = $this->getAssign( 'p' ) ;
            $file = dirname( dirname( dirname(__FILE__) ) ).'/webroot/res/hcapp/html/'. $this->getAssign( '_c' ) .'/'.$this->getAssign( '_a' ).'/'.$p[0].'.html';
            ob_start();
            $this->dHtml();
            $string = ob_get_contents();
            file_put_contents($file, $string);
            ob_end_flush();
            parent::drExit('write: '. $file );
        }

        $this->dHtml();
        parent::drExit(); //释放资源

    }
    private function dJson(){
        if(isset($_GET['display'])) {
            foreach ( $this->dr as $k=>$v  ) $this->assign($k,$v );
            $this->assign('_query_num', $this->getLogin()->db()->query_num() );
        }
        drFun::setcookie('_DError', '',time()-3600,'/' );
        parent::display();
    }
    private function dHtml(){
        if(  self::$dsData ) extract( self::$dsData ); #释放变量

        #释放变量
        extract(  $this->dr   );
        $dr=  $this->dr;
        $tpl_dir = ROOT_PATH.'/view/';

        if(isset($_GET['isiframe'])){
            $this->htmlFile='iframe.phtml';
        }

        $tpl_file = $this->getTplFile();
        //if(!is_file($tpl_file) )            $this->Err ("tplFile不存在：". DR::$_URI['ctl'].'/'.DR::$_URI['act'].'.phtml'  );

        $html_file= $this->getHtmlFile();//$tpl_dir.$this->htmlFile;
        if(!is_file( $html_file) )            $this->Err ("htmlFile不存在：". $tpl_dir.$this->htmlFile   );
        drFun::setcookie('_DError', '',time()-3600,'/' );
        header("Content-type:text/html;charset=utf-8");
        //include  $tpl_dir.$this->htmlFile;
        include   $html_file;

    }

    function getHtmlFile(){
        $tpl_dir = ROOT_PATH.'/view/';
        $skin= drFun::getSkin();
        if( $skin!='default') {
            $file=  ROOT_PATH.'/skin/'.$skin.'/'.$this->htmlFile ;
            if(is_file( $file)) return $file;
        }
        return $tpl_dir.$this->htmlFile;
    }

    public function displayJs(){
        if(  self::$dsData ) extract( self::$dsData ); #释放变量
        #释放变量
        extract(  $this->dr   );
        $dr=  $this->dr;
        $tpl_dir = ROOT_PATH.'/view/';
        $_file_tpl =  $tpl_dir.$this->htmlFile;
        if(!is_file( $_file_tpl ) )            $this->Err ("htmlFile不存在：". $_file_tpl  );
        header('Content-Type: application/x-javascript; charset=UTF-8');
        include $_file_tpl;
        parent::drExit(); //释放资源

    }
    private function getTplFile(){

        $temfile = $this->tplFile?$this->tplFile: strtolower(DR::$_URI['act']);
        $skin= drFun::getSkin();
        if( $skin!='default') {
            $file=  ROOT_PATH.'/skin/'.$skin.'/'.DR::$_URI['ctl'].'/'.$temfile.'.phtml';
            if(is_file( $file)) return $file;
        }

        $tpl_file= ROOT_PATH.'/view/'.DR::$_URI['ctl'].'/'.$temfile.'.phtml';
        return $tpl_file;
    }

    /**
     * 继承DR::runStart；设置了 模板中 $_cu cookie用户值、get $_GET值，处理 _DREEOR {@link redirect}
     */
    public function runStart(){
        //die('dddd');
        $derror = $_COOKIE['_DError'];
        if( $derror!='' ){
            $this->assign('_DREEOR',  $derror );

            #删除cookie 放在 display里面
            # drFun::setcookie('_DError', '',time()-3600,'/' );
        }
        $this->ischeck= false ;

        $this->cookieUser =  login::getCookieUser();
        drFun::cdnImg(  $this->cookieUser,['head'] );
        //cookie user
        $get= $_GET;
        if( DR::$_URI['ctl'] !='api'){
            $this->assign('_cu',$this->cookieUser );
            drFun::strip($get);
            $this->assign('get', $get );
            $this->assign('_a', DR::$_URI['act'] );
            $this->assign('_c', DR::$_URI['ctl'] );
        }


        if( $this->ex_page() ){
            $this->init();
            $this->htmlFile="ex_page.phtml";
            $this->site_title = '导出分页';
            $this->display();
        }
    }

    /**
     * 这里干了一些 如果模板文件存在 即使 act_$name 不存在也直接显示模板内容
     * @param string $name
     * @param array $args
     */
    public function  __call($name,  $args) {
        $tpl_file = $this->getTplFile();

        if(!is_file($tpl_file) ){
            header('HTTP/1.1 404 Not Found');
            parent::Err("action is not exit "  ,403 );

        }
    }

    /**
     * 转向有可能是html 会注册一个 _DError cookie存放错误信息 在转向后显示并删除 删除在runStart {@link runStart}
     * @param string $url
     * @param string $msg
     * @param string $style
     */
    public function redirect( $url,$msg='',$style='success'){
        if('json'== $this->getDisplayType() ){
            //$this->drExit('good');
            parent::redirect( $url,$msg ,$style );
            $this->drExit();
        }
//        if( strpos( $url,'http' )!==0 ){
//            $url = $this->dr['DR_HTML_DIR'].trim(  trim( trim( $url ),"/"),"\\");
//        }
        $url = $url? drFun::rount( $url ): ( $_SERVER['HTTP_REFERER']? $_SERVER['HTTP_REFERER']: drFun::rount( '/' ) );
        if( $msg ) {
            $dis = ['msg' => $msg, 'style' => $style];
            drFun::setcookie('_DError', json_encode($dis) ,0,'/');
        }

        $is_ali = strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient');

        header('Location: '.$url);
        parent::drExit();//释放资源
        eixt();
    }
    
    public function displayJson( $data,$error=0,$error_des=''  ) {
        parent::displayJson($data, $error, $error_des);
        parent::drExit();
    }
    public function Err($error_des, $error = 110, $data = '')
    {
        if( $this->getDisplayType()=='json'){
            parent::Err($error_des, $error, $data);
        }

        $url = $_SERVER['HTTP_REFERER'];
        if( !$url ) $url= WWW_ROOT;
        elseif( strtolower( substr( $url,0,4))=='http'){
            $url = R($url,['error_des'=>$error_des, 'error'=>$error] );
        }
        $this->redirect( $url, $error_des,'error' );
    }
    /**
     * @return login
     */
    public function getLogin(){
        if( self::$login ==null  ) self::$login= new login();
        return self::$login;
    }



    /**
     * 检查是否老师
     * @return $this
     */
    public function checkTeacher(){
        $this->getLogin()->checkTeacher();
        return $this;
    }

    /**
     * 判断是否登录，没有登录跳到登录页面
     * @return $this
     */
    public function checkLogin(){
        $user = login::getCookieUser();
        if( ! $user) {
            drFun::setcookie('loginback', $_SERVER['REQUEST_URI']);
            $this->redirect('login','请先登录','error');
        }
        return $this;
    }

    /**
     * 通过cookie判断是否系统管理员
     * @return $this
     * @throws drException
     */
    public function checkAdminByCookie(){
        $this->checkLogin();
        //return $this;
        $user = login::getCookieUser();
        if( $user['attr']){
            $admin=['p1'=>1,'p2'=>1];
            foreach($user['attr'] as $key ){
                if( isset($admin[$key] )) return $this;
            }
        }
        $this->throw_exception( "权限不足",444);
    }
    /**
     * 通过cookie判断是否编辑人员
     * @return $this
     * @throws drException
     */
    public function checkEditorByCookie(){
        $this->checkLogin();
        $user = login::getCookieUser();
        if( $user['attr']){
            $admin=[ 'p4'=>1];
            foreach($user['attr'] as $key ){  if( isset($admin[$key] )) return $this;  }
        }
        $this->throw_exception( '编辑权限不足',444);
    }

    /**
     * 通过cookie判断是否学校管理员
     * @return $this
     * @throws drException
     */
    public function checkSchoolAdminByCookie(){
        $this->checkLogin();
        $user = login::getCookieUser();
        if( $user['attr']){
            $admin=['p3'=>1 ];
            foreach($user['attr'] as $key ){
                if( isset($admin[$key] )){
                    return $this;
                }
            }
        }
        $this->throw_exception( "权限不足",445);

    }

    /**
     * 通过cookie 获取系统管理员
     * @return bool|string
     */
    public function isAdmin(){
        return $this->is_admin ;
    }

    /**
     * 通过cookie 获取是否学校管理员
     * @return bool|string
     */
    public function isSchoolAdmin(){
        return $this->is_school ;
    }

    /**
     * 获取 HTTP_REFERER 当没有的时候 获取空！
     * @return string
     */
    public function getReferer(){
        $url = $_SERVER['HTTP_REFERER'];
        if( !$url) return '';
        return $url ;
    }


} 
