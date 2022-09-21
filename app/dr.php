<?php
namespace  DR;

global $_GB; #需要 global缓存都放这里


use model\DR_DB;
use model\drException;
use model\drFun;

/**
 *
 * 类 DR，本框架的核心类，
 * 这里的每个方法都有可能被继承被重写
 *
 * 本框架遵循的一些规则：
 * - 1.Model层基本都在目录model下,请继承类model
 * - 2.View层在跟目录view下时候后缀phtml 目录遵循ctrl/act.phtml 一般有个自定义的htmlFile默认为index.phtml，tplFile为ctrl/act.phtml
 * - 3.Control层基本都在目录ctrl下，请继承drTPL
 * - 4.在控制层不直接操作数据，即不去使用sql,DR_DB类
 * - 5.在model层尽量不是用getLogin中的cookie数据
 * - 6.在view层当 $_GET[display]=json 或者header中有 X-DISPLAY：json 显示json格式
 * - 7.刚刚建立DR框架时候的想法 {@link https://www.zybuluo.com/dooy/note/749717}
 * - 8.关于DB 在ctrl即继承drTpl下别直接去使用DB，而在继承model下经量使用 $this->createSql()
 *
 * phpdoc的语法可以参考 {@link http://yanue.net/post-94.html}
 */
class DR{
    /**
     * @var string 存放URI搜集的ctrl、act等值
     */
    static protected $_URI;
    /**
     * @var bool 是否debug？
     */
    static protected $_DEBUG;
    /**
     * @var bool 存放drFun
     */
    static private $_FUN=false;
    /**
     * @var bool
     */
    public $ischeck=true;
    /**
     * @var array assign存放的变了
     */
    static protected $dsData= array();
    /**
     * @var array url中的输入值 http://a.com/b/c/d/d 这个值为[c,d,d]
     */
    protected $inData= array();
    private $_msg = [];

    /**
     * @var int 活着的时间 单位秒
     */
    public $liveTime= 120;
   
    public function  __construct($debug = false) {
            self::$_DEBUG	= $debug;
            //echo "<br>". get_class($this) ;
            //spl_autoload_register('DR::ctrl_loader');
           // spl_autoload_register('\DR\DR::model_loader');
    }

    /**
     * 初始化一般在ctrl类中会继承
     */
    function init(){
        //$hosts = strtolower( $_SERVER['HTTP_HOST']);
    }

    public function autoload_register(){
        spl_autoload_register('\DR\DR::model_loader');
        return $this;
    }

    /**
     * 初始处理活力ctrl、act以及act的输入参数inData
     *
     * 获取渠道：
     * - 获取 PATH_INFO 这样一个目录/a/b/c/d处理后 a为ctl，b为act  c,d为参数inData；/a/单目录这 ctrl=index act=a
     * - $_GET['c'] 为ctrl $_GET['a'] 为act
     */
    final function setCtrl( ) {
        $path = trim(trim( trim($_SERVER['PATH_INFO'] ),'\\'),'/'); 
        $parr =preg_split ("/[\/]+/", $path  );
        $act=$ctrl='index';
        $a_lenth = count($parr) ;
        if(  $a_lenth ==1 &&  $parr[0]!=''){            
            $ctrl='index';$act= $parr[0];
        }else{
            $ctrl = ( isset($parr[0]) && ''!=($parr[0]))? $parr[0] : 'index';
            $act = ( isset($parr[1]) && ''!=($parr[1]))? $parr[1] : 'index';
        }
       
        if($ctrl=='index' && isset( $_GET['c'])&& trim( $_GET['c'])!='' )$ctrl= trim($_GET['c']) ;
        if($act=='index' && isset( $_GET['a'])&& trim( $_GET['a'])!='' )$act= trim($_GET['a']) ;
       
        self::$_URI['ctl']= $ctrl ;
        self::$_URI['act']= $act ; 
        if( $a_lenth >=2 ){
            for( $i=2; $i<$a_lenth; $i++){
                if( $parr[$i]) $this->inData[]=  $parr[$i] ;
            }
        }
        self::$_URI['inData']= $this->inData ;
        $tarr = preg_split('/[^\/]+\.php/', $_SERVER['PHP_SELF']);
        self::$_URI['dir']= $tarr[0];
        
        
    }

    /**
     * 转向
     * @param string $url 转向链接
     * @param string $msg 转向时提示信息
     * @param string $style 转向
     */
    function redirect( $url,$msg='',$style="success"){
        if($url=='') $this->_msg=['url'=>'','msg'=>$msg ];
        else   $this->_msg=['url'=> drFun::R($url),'msg'=>$msg ];

//        if( $msg  && $url!='') {
//            $dis = ['msg' => $msg, 'style' => $style];
//            drFun::setcookie('_DError', json_encode($dis));
//        }
        $this->_msg['timeout']=1; #毫秒
        $this->display();
        $this->drExit();
    }

    /**
     * 发送错误显示
     * @param string $error_des 错误信息
     * @param int $error 错误编码
     * @param mixed $data 附带数据
     */
    function Err ( $error_des ,$error=110, $data=''){
        $this->displayJson($data,$error, $error_des);
        $this->drExit();
    }

    /**
     * json显示
     * @param mixed $data 显示的数据
     * @param int $error 错误编码
     * @param string $error_des 错误信息
     */
    function displayJson( $data,$error=0,$error_des='' ){
        if( $data ) $re['data']= $data;
        else $re['data']= null;
//        $re['error']= $error_des;
//        $re['ret']= $error;
        $re['error']= $error;
        $re['error_des']= $error_des;
        if( $this->_msg  ) $re['redirect']= $this->_msg;
        if(isset($_GET['callback']) && trim( $_GET['callback']) ){
            header('Content-Type: application/x-javascript; charset=UTF-8');
            $cb= htmlentities( trim(  $_GET['callback']));
            echo $cb.'('.json_encode($re) .')';
            $this->drExit();
        }
        if( isset($_GET['showsql'] )){
            $re['sql']= DR_DB::$qlog;
        }
       header('Content-Type:application/json; charset=utf-8');
       echo(  json_encode($re));
       $this->drExit( );
    }

    /**
     * 存放在 {@link dsData} 里面
     * @param string $k key
     * @param mixed $v 值
     * @return $this
     */
    public function assign( $k,$v ) {
        self::$dsData[ $k ] = $v;
        return $this;
    }

    /**
     * 获取 assign的词汇
     * @param $k
     * @return mixed|null
     */
    public function getAssign( $k ){
        return isset(self::$dsData[ $k ])? self::$dsData[ $k ]: null;
    }

    /**
     * ctr 刚刚开始运行的地方 之后才是 ctr的init
     */
    function runStart(){}

    /**
     *
     * 整个框架的运行过程，本框架的核心
     *
     * 处理的流程：
     * - 1.获取ctrl和act的值
     * - 2.实例一个ctrl类
     * - 3.运行ctrl中的runStart
     * - 4.件事ctrl类中是否存在act的方法
     * - 5.运行ctrl钟洪的init
     * - 6.使用try 运行ctrl类中的act方法
     * - 7.如果try不成功则获取错误信息
     * - 8.如果try成功这直接线下 ctrl->display
     */
    final public function  run(   ){
        
        $this->autoload_register()->setCtrl();
       
        $str = "\\ctrl\\". strtr( strtolower(self::$_URI['ctl']) ,array('/'=>'','\\'=>'' ) );
        try {
            $ctl = new $str;
        }catch ( \Exception $ex){
            $this->displayJson('', $ex->getCode(),$ex->getMessage() );
        }
        $func	= 'act_'.strtolower(self::$_URI['act']);  
         $ctl->runStart();
        if( $ctl->ischeck && !method_exists($ctl,$func) ) {
            header('HTTP/1.1 404 Not Found');
            $ctl->Err('不存在的方法：'.$func,403);
        } else {
            try {
                $ctl->init();
                $ctl->$func($this->inData);
            }catch ( \Exception $e){
                $this->log("[".$e->getCode()."]".$e->getMessage()."\n". $e->getTraceAsString() );
                $this->logErr("[".$e->getCode()."]".$e->getMessage()."\n". $e->getTraceAsString() );

                //$this->log_s( $e->getTraceAsString() );
                $code = $e->getCode() ;
                $ctl->Err( $e->getMessage(),$code===0?1:$code );
            }
            $this->assign('DR_PVAR', $this->inData);
            $ctl->display();
        }
    }

    /**
     * 错误输出
     * @param string $msg 错误信息
     * @param int $code 错误代码
     * @throws drException
     */
    public function throw_exception( $msg, $code=113 ){
        throw new drException( $msg ,$code );
    }

    /**
     * 最终退出
     * @param mixed $str
     */
    final public function drExit( $str=0 ) {
         //global $_mysql_link;
        //DR_DB::$mysql_link;
        if( is_object(DR_DB::$mysql_link ) )   DR_DB::$mysql_link->close();
        if(is_array( $str)) print_r( $str);
        elseif( $str ) echo $str ;
        exit(  );
    }

    /**
     * 最终显示
     */
    public function display(){

        $this->displayJson(self::$dsData );
        $this->drExit();
    }

    public function display_js(){
        //$this->displayJson( self::$dsData );
        $str='';
        foreach(  self::$dsData as $k=>$v ){
            $str.="var ".$k.'=';
            if( is_array($v) )$str.= json_encode( $v);
            else $str.="'".$v."'";
            $str.=";\n";
        }
        header('Content-Type: application/x-javascript; charset=UTF-8');
        echo $str;
        $this->drExit();
    }

    /**
     * 模块注册
     * @param string  $class
     */
    public static function model_loader( $class) {

        //$class_file = APP_PATH.'/model/'. strtolower($class).'.php';
        $class= strtr($class, array('\\'=>'/')  );
        $class_file = APP_PATH.'/'. $class.'.php';
        $class_file2 = APP_PATH.'/'. strtolower($class).'.php';
        if( is_file($class_file) )  include_once $class_file;
        elseif( is_file($class_file2) ) include_once $class_file2;
        else{

            drFun::throw_exception( $class . "' model file is no file!" , 404 );

//            try {
//                throw  new \Exception($class . "' model file is no file!", 404);
//            }catch ( \Exception $ex ){
//                self::logs_s(   $ex->getTraceAsString() );
//                $re=[];
//                $re['data']= '';
//                $re['error']= 404;
//                $re['error_des']=  $ex->getMessage();
//                header('HTTP/1.1 404 Not Found');
//                header('Content-Type:application/json; charset=utf-8');
//                echo (json_encode($re));
//                exit();
//            }
        }
    }

    /**
     * 日志记录 保证在根目录的 log.log当中
     * @param string $str
     * @param string $file_name
     * @return $this
     */
    public function log( $str ,$file_name=''){
        if( $file_name=='' )  $file_name='log_'.date("Ymd").'.log';
        $file= ( dirname( dirname( __FILE__))).'/log/'. $file_name ;//log_'.date("Ymd").'.log';
        if( is_array($str)) $str= print_r($str,true);
        if( is_file($file)) @file_put_contents($file, "\n[".date("Y-m-d H:i:s")."]-".drFun::getIP()."\t".$str ,  FILE_APPEND );
        else {
           @file_put_contents($file, "\n[".date("Y-m-d H:i:s")."]-".drFun::getIP()."\t".$str ,  FILE_APPEND );
           @chmod( $file,0777 );
        }


        return $this;
    }
    /**
     * 日志记录 保证在根目录的 log.log当中
     * @param string $str
     * @return $this
     */
    public function logErr( $str ){
        $file= ( dirname( dirname( __FILE__))).'/error.log';
        if( is_array($str)) $str= print_r($str,true);
        if( is_file($file)) file_put_contents($file, "\n[".date("Y-m-d H:i:s")."]-".drFun::getIP()."\n".$str ,  FILE_APPEND );
        return $this;
    }
    /**
     * 日志记录 保证在根目录的 log.log当中
     * @param string $str
     */
    static function logs_s( $str, $fileName= 'log.log' ) {

        $file= ( dirname( dirname( __FILE__))).'/'. $fileName;
        if( is_file($file))  file_put_contents($file, "\nlogs_s [".date("Y-m-d H:i:s")."]-".drFun::getIP()."\n".$str ,  FILE_APPEND );

    }


    /**
     * @return bool|drFun
     */
    public function fun(){
        if(! self::$_FUN )          self::$_FUN= new drFun();
        return self::$_FUN;
    }

    function ex_page(){
        if( $_GET['debug_page'] )  {
            ini_set( 'display_errors', '1' ) ;
            error_reporting( E_ALL ); //E_ERROR | E_WARNING | E_PARSE| E_NOTICE
        }
        if( !isset($_GET['export']) ) return false ;
        if( isset($_GET['export_total']) && isset($_GET['max']) && $_GET['export_total']> $_GET['max'] && !isset($_GET['start']) ){
            $var= $_GET;
            $var['page_number']=  ceil(  $_GET['export_total']/$_GET['max'] );
            $this->assign('ex_var',$var );
            return $var;
        }
        return false;
    }


}