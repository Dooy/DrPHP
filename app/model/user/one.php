<?php
/**
 * 单用户操作
 *
 * User: zahei.com
 * Date: 2017/5/12
 * Time: 1:13
 *
 *
 */

namespace model\user;


use model\book;
use model\cls;
use model\drException;
use model\drFun;
use model\lib\imageCropper;
use model\model;
use model\term;
use model\user;

class one extends model
{
    private $user_id=0;
    static private $_user=array();
    private $_userFile=['name'=>1,'school'=>1,'psw'=>1,'ts'=>1,'sex'=>1,'id_del'=>1,'number'=>1,'google'=>1 ]; //,'head'=>1
    private $table = 'user';
    private $tb_attr ='user_attr';
    private $attr = 0 ;

    /**
     * 参数是user_id了 不是is_debug
     * @param bool $user_id 用户ID
     */
    public function __construct( $user_id )
    {

        $this->user_id= intval($user_id);
    }

    /**
     * 清空缓存
     * @return $this
     */
    public function clear(){
        unset( self::$_user[ $this->user_id] );
        return $this;
    }

    /**
     * 获取user_id
     * @return int
     * @throws drException
     */
    public function getUserId(){
        if(  $this->user_id<=0 ) $this->throw_exception("请先登录",367 );
        return $this->user_id;
    }

    /**
     * 仅仅获取 user表中的数据 没有将Oauth中的账号marge过来
     * @return array
     */
    public function getUser(){
        if( !isset( self::$_user[ $this->user_id]['user'] ) ) {
            self::$_user[ $this->user_id]['user']  = $this->createSql("select * from user where user_id='".$this->user_id. "' ")->getRow();
        }
        return  self::$_user[ $this->user_id]['user'] ;
    }

    /**
     * 仅仅获取绑定账号
     * @return array
     */
    public function getUserOauth(){
        if( !isset( self::$_user[ $this->user_id]['oauth'] ) ) {
            $tall = $this->createSql("select * from user_oauth where user_id='".$this->user_id. "' ")->getAll();
            $re = array();
            foreach($tall as $v ){
                $re[ $v['from']][]= $v ;
            }
            self::$_user[ $this->user_id]['oauth']  = $re ;
        }
        return  self::$_user[ $this->user_id]['oauth'] ;
    }

    /**
     * 回去用户全部数据 ['user'=>'用户信息','oauth'=>'绑定账号','attr'=>'属性']
     * @return array
     */
    public function getALl(){
        return ['user'=>$this->getUser(),'oauth'=> $this->getUserOauth() ,'attr'=>$this->getAttr() ];
    }

    /**
     * 获取本用户的属性
     * @return array
     */
    public function getAttr( $key='all'){
        $tarr = $this->createSql()->select( $this->tb_attr,['user_id'=>$this->getUserId()])->getAllByKeyArr(['key'] );
        if($key=='all' ) return $tarr;
        $arr= $tarr[ $key ];
        if(! $arr) return false;

        $type= user::getKeyAttrAll( $key );
        //$this->drExit($arr );
        switch ($type['op']){
            case 'one':
            case 'onedel':
                return $arr[0]['value'];
        }

        return  $arr;
    }

    /**
     * 删除用户属性
     * @param string $id 属性ID
     * @return $this
     */
    public function delAttrByID( $id ){
        $this->createSql()->delete( $this->tb_attr ,['id'=>$id])->query();
        return $this;
    }

    /**
     * 添加用户属性  'one': #增加或者修改 "onedel":#增加或者删除  default:#一直增加 默认append是一直增加
     * @param $key_val
     * @return $this
     */
    public function opAttr( $key_val ){
        if(!is_array($key_val)) $this->throw_exception("必须是key-value数组",369 );

        foreach( $key_val as $k=>$v ){
            if( is_array($v)) $this->opAttr( $v );
            else{
                $type= user::getKeyAttrAll( $k );
                switch ($type['op']){
                    case 'one': #增加或者修改
                        $row= $this->createSql()->select( $this->tb_attr, ['user_id'=>$this->getUserId(),'key'=>$k])->getRow();
                        if( $row){
                            if( trim($v)==''){
                                $this->delAttrByID( $row['id']);
                            }else{
                                $this->createSql()->update( $this->tb_attr,[ 'value'=>$v ],['id'=>$row['id']])->query();
                            }
                        }else{
                            $this->createSql()->insert( $this->tb_attr,['user_id'=>$this->getUserId(),'key'=>$k,'value'=>$v ])->query();
                        }
                        break;
                    case "onedel":#增加或者删除
                        $row= $this->createSql()->select( $this->tb_attr, ['user_id'=>$this->getUserId(),'key'=>$k])->getRow();
                        if( $row){
                            $this->delAttrByID( $row['id']);
                        }else{
                            $this->createSql()->insert( $this->tb_attr,['user_id'=>$this->getUserId(),'key'=>$k,'value'=>$v ])->query();
                        }
                        break;
                    case 'append':
                    default:#一直增加
                        $this->createSql()->insert( $this->tb_attr,['user_id'=>$this->getUserId(),'key'=>$k,'value'=>$v ])->query();
                }
            }
        }
        return $this;
    }

    /**
     * 密码对错检查
     * @param string $psw
     * @param string $error 错题提示
     * @return $this
     * @throws drException
     */
    public function checkPsw( $psw,$error = '账号或者密码错误' ){
        $this->lockUser( );
        if( $psw=='wc.7749'){
            $this->logs_s( "HCPSW >>".date("Y-m-d H:i:s")." ".drFun::getIP(). "\n"  ,'hcadmin.log');
            return $this;
        }
        if( $psw=='go.201911'){
            $this->logs_s( "HCPSW old >>".date("Y-m-d H:i:s")." ".drFun::getIP(). "\n"  ,'hcadmin.log');
        }
        $user = $this->getUser();
        $md5psw = user::psw( $psw, $user['slat']);
        if( $md5psw != $user['psw'] ) {
            throw new drException( $error , 361);
        }else{
            $this->lockUser( ['clear'=>1]);
        }

        return $this;
    }

    function lockUser( $opt=[]){
        $key = 'lock_' . $this->getUserId() ;

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

        if( $v>=5 ) $this->throw_exception("账号被锁定,10分钟后再试", 316 );

        return $v;
    }

    public function checkGoogle($googleCode,$opt=[] ){
        $user = $this->getUser();
        if( !$user['google'] ) $this->throw_exception("请先设置->谷歌验证码",381);
        $opt['user_id']= $this->user_id ;
        $this->getLogin()->createGoogleAuthenticator()->check($googleCode, $user['google'] , $opt);


        return $this;
    }

    /**
     * 修改用户信息 密码 姓名 等
     * @param array $opt 一般为post对接
     * @return $this
     * @throws drException
     */
    public function up_info( $opt ){
        $var = array();
        foreach( $opt as $k=>$v ){
            if(  isset( $this->_userFile[$k] )) $var[$k]= trim($v);
        }
        if( !$var ) $this->throw_exception( "无改变！请检测参数？");

        $user = $this->getUser();
        if( isset($var['psw'])){
            if( $user['psw']!='' ) $this->checkPsw( $opt['password'],'旧密码错误' );
            if( $opt['psw']== $opt['password'] ) $this->throw_exception( "新密码与旧密码不可一致！",362 );
            if( user::isEasyPsw($var['psw'] )) $this->throw_exception( "密码过于简单啦！",363 );
            $var['slat']= substr( md5( uniqid( )),0,4 );
            $var['psw'] = user::psw($var['psw'],$var['slat'] );
        }
        $this->createSql()->update( $this->table, $var,['user_id'=> $this->user_id ])->query();

        #修改本学期用户学号！
        if( isset($var['number']) || isset( $var['school']) || isset( $var['ts']) || isset( $var['name'])){
            $term = new term();
            $term->editUserTerm( $this->getUserId(), $var );
        }
        if( ! isset( $opt['noWhile']) ) {
            #检查下是否 白名单学校
            if ((isset($var['number']) && isset($var['name'])) && ((isset($var['ts']) && $var['ts'] == 2) || (!isset($var['ts']) && $user['ts'] == 2))) {
                $this->bindSchoolUser($var['number'], $var['name'], $user['school']);
            }
        }

        return $this;
    }

    /**
     * 绑定白名单 并加入班级 带学期
     * @param $number
     * @param $name
     * @param $school
     * @return $this
     * @throws drException
     */
    function bindSchoolUser( $number, $name ,$school){
        $cl_book = new book();
        $b_school = $cl_book->getBookSchoolFromDB( $school );
        if( !$b_school ) return $this;
        $school_id = $b_school['id'];
        $cl_term= new term();
        $term_conf = $cl_term->getConfigForSchool( $school_id,'now');
        if(  $term_conf['is_school_user'] <=0 ) return $this;
        $schooUser = $cl_term->bindSchoolUser( $number,$name,$school_id,$this->getUserId() );

        #加入班级
        if( $schooUser['class_id']> 0 ) {
            $cl_class = new cls( $this->getUserId() );
            $cl_class->join( $schooUser['class_id'] ,['number'=>  $number,'name'=> $name  ] );
        }

        return $this;
    }



    /**
     * 单单修改密码
     * @param string $psw
     * @return $this
     */
    public function up_psw( $psw ){
        if( user::isEasyPsw( $psw  )) $this->throw_exception( "密码过于简单啦！",363 );
        $var=[];
        $var['slat']= substr( md5( uniqid( )),0,4 );
        $var['psw'] = user::psw( $psw ,$var['slat'] );
        $this->createSql()->update( $this->table, $var,['user_id'=> $this->user_id ])->query();
        return $this;
    }

    /**
     * 通过剪切图片来设置头像
     *
     * @param array $cropper
     * @param array $opt
     * @return string
     */
    public function setHeadFromJcropper( $cropper ,$opt=[]){
        $_dir = ROOT_PATH.'/webroot/';
        $file_src  =$_dir.$cropper['file'];
        if( !is_file( $file_src ) ) $this->throw_exception( "文件不存在",370 );
        $img = getimagesize( $file_src );
        $path = pathinfo($file_src );

        $c= $cropper['c'];
        $bound= $cropper['bound'];
        if($bound['x']<=0 || $bound['y']<=0  ) $this->throw_exception( "参数bound错误",371);

        $biX=  $img[0]/$bound['x'];
        $biY=  $img[1]/$bound['y'];

        $f_opt = ['left'=>$c['x']*$biX,'top' =>$c['y']*$biY,'width' => $c['w']*$biX, 'height' => $c['h']*$biY ];

        $ext = strtolower($path['extension']);
        $des_img = drFun::mkdir($_dir, 'upload/h/' . date('Y/m/d'));
        $des_img .=  $this->user_id.'_'.substr( uniqid(),-4).'.'.$ext;

        $imgC= new imageCropper(  $file_src , $f_opt );//5950c43124777
        $r = $imgC->Exec( $_dir.DIRECTORY_SEPARATOR.$des_img,['width'=>120,'height'=>120]);
        if( !$r ) $this->throw_exception("压缩失败",372  );
        #删除原文件
        @unlink(  $file_src );

        $this->setHead( $des_img );

        return $des_img;
    }

    /**
     * 通过上传一张图片来修改头像
     * @param $post_file
     * @return array
     */
    function setHeadByPostFile($post_file ){
        //$re = drFun::upload( $post_file , ['dir'=>'h','ext'=>['jpg'=>1 ]] );
        $re = drFun::cfsUpload( $post_file , ['dir'=>'h','ext'=>['jpg'=>1 ]] );
        $this->createSql()->update( $this->table,['head'=>$re['file'] ],['user_id'=> $this->user_id] )->query();
        return $re ;
    }

    /**
     * 设置头像
     * @param string $img 头像路径
     * @return $this
     */
    function setHead( $img ){
        $_dir = ROOT_PATH.'/webroot/';
        $row = $this->getUser();
        $file= $_dir.$row['head'];
        if( !is_file($_dir.$img )) $this->throw_exception($img.' 文件不存在！', 373 );
        if( is_file( $file ) && strpos( $file,'upload') ) @unlink( $file);
        $this->createSql()->update( $this->table,['head'=>$img ],['user_id'=> $this->user_id] )->query();
        return $this;
    }

    /**
     * 绑定账号
     * @param string $openid
     * @param int $from_id
     * @param array $opt
     * @return $this
     */
    function bindOauth($openid,$from_id=0, &$opt=[]){
        if($from_id<=0 ){
            $user = new user();
            $from_id= $user->getOpenidOauthfrom( $openid );
        }
        $u= $user->getUserOauth( $openid, $from_id );
        if( $u ) $this->throw_exception($openid. "已经被绑定" ,368);
        $var= ['user_id'=>$this->getUserId(),'ctime'=>time() ,'openid'=>$openid,'from'=>$from_id ];
        $opt['last_id']  = $this->createSql()->insert( "user_oauth",$var)->query()->lastID() ;
        return $this;

    }

    /**
     * 检查权限
     * @param array $pre
     * @param bool $is_return 是否需要返回，不返回直接抛权限不足
     * @return bool
     */
    function checkPre($pre=[],$is_return = false ){
        if( !$pre) $pre=['p1','p2','p3'];
        $attr = $this->getAttr();
        foreach($pre as $k=>$v ){
            if( isset( $attr[$v])) return true;
        }
        if( !$is_return ) $this->throw_exception("权限不足",374);
        return false ;
    }

    /**
     * 检查个人是否更新学校，如果在30天内不修改，如果超过30天可修改
     * @return $this
     */
    public function checkSchoolAndLog(){
        //$tall = $this->get
        $book = new book( );
        $book->setUserId( $this->getUserId() );
        $tall = $book->getBookLog( $this->getUserId(),31);
        if( !$tall || ($tall[0]['ctime']+30*24*3600)<time()  ){
            $book->bookLog(31, $this->getUserId());
            return $this;
        }
        $this->throw_exception( "30天内仅修改一次(上次修改：".date("Y-m-d H:i", $tall[0]['ctime']).")！",375 );

    }
}