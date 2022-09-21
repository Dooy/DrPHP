<?php
/**
 * 用户表的相关操作
 *
 * User: zahei.com
 * Date: 2017/5/11 0011
 * Time: 下午 8:10
 */

namespace model;


use model\user\login;

class user extends model
{
    private $from= [1=>'email',2=>'tel',3=>'QQ',4=>'微博',5=>'微信'];
    private  $tb_user = 'user';
    private $tb_oauth='user_oauth';
    private $tb_attr='user_attr';

    /**
     * 增加用户
     * @param string $oauth_openid 唯一ID
     * @param string $oauth_from 用户类型
     * @param string $name 姓名
     * @param int $ts 身份1教师 2学生
     * @param array $opt
     * @param array $oauth_opt
     * @return int user_id
     * @throws \Exception
     */

    public function add( $oauth_openid, $oauth_from, $name, $ts=2, $opt=[],$oauth_opt=[] ){
        if( ! isset( $this->from[$oauth_from]) ) throw new drException('来源类型错误',10);

        if( $oauth_from==1 ) $this->fun()->checkEmail( $oauth_openid );
        if( $oauth_from==2 ) $this->fun()->checkTel( $oauth_openid );
        //if( ! in_array( $ts,[1,2])  ) $this->throw_exception( "身份错误！", 11 );
        user::getTypeTs($ts) ;

        $userOauth = $this->getUserOauth( $oauth_openid, $oauth_from );

        if(  $userOauth ) throw new drException('该用户存在',9);
        $opt['ctime']= time();
        $opt['ts']= $ts;
        $opt['name']= $name;

        $this->checkSchool( $opt['school']);

        if(isset( $opt['psw'])){
            if( self::isEasyPsw( $opt['psw'])) $this->throw_exception( "密码过于简单", 14 );
            $opt['slat']= substr( md5( uniqid( )),0,4 );
            $opt['psw'] = $this->psw( $opt['psw'], $opt['slat'] );
        }
        $opt['client']= drFun::getClient() ;
        $user_id =  $this->createSql()->insert('user', $opt )->query()->lastID() ; 

        $this->addUserOauth( $oauth_openid,$oauth_from,$user_id, $oauth_opt );

        return $user_id;

    }

    function checkSchool( $school ){
        if( ! $school  ) $this->throw_exception("请填写学校",1018);
        $cnt = $this->createSql()->getCount('book_school',['school'=>$school ])->getOne();
        if( $cnt<=0 ) $this->throw_exception("填写的学校不存在",1019);
        return $this;
    }

    /**
     * 判断是否简单密码
     * @param string $psw
     * @return bool
     */
    public static function isEasyPsw( $psw ){
        if( strlen($psw)<6 ){
            drFun::throw_exception( "密码必须大于6位",234);
        }
        $easy=['123456'=>1,'1234567'=>1,'654321'=>1,'7654321'=>1 ];
        if(  isset( $easy[$psw]) ) drFun::throw_exception( "密码过于简单", 14 );
        return isset( $easy[$psw]);
    }

    /**
     * 通过open_id 取得单个用户
     * @param string $oauth_openid
     * @param int $oauth_from
     * @return mixed
     */
    public function getUserOauth( $oauth_openid, $oauth_from  ){
        return  $this->createSql( "select *   from user_oauth where openid= :openid and `from`= :from"
            ,[':openid'=>$oauth_openid,':from'=>$oauth_from ] )->getRow();
    }

    /**
     * 通过openid 判断Oauth from 的类型
     * @param string $openid
     * @return int
     * @throws drException
     */
    public function getOpenidOauthfrom( $openid ){
        return 3;
        try{
            drFun::checkEmail($openid );
            return 1;
        }catch ( drException $e ){        };

        try{
            drFun::checkTel( $openid );
            return 2;
        }catch ( drException $e ){   };

        throw new drException("账号信息目前仅支持email、电话",231 );

        return 0;
    }

    /**
     * 增加 Oauth 用户
     * @param string $oauth_openid
     * @param int $oauth_from
     * @param int $user_id
     * @param array $opt
     * @return int
     */
    public function addUserOauth( $oauth_openid, $oauth_from, $user_id ,$opt=[]){
        $opt = ['openid'=>$oauth_openid,'from'=>$oauth_from,'ctime'=>time(),'user_id'=>$user_id ];

        return $this->createSql()->insert('user_oauth', $opt )->query()->lastID() ;
    }

    /**
     * 密码混编 使用随机slat加密
     * @param string $psw
     * @param string $slat
     * @return string
     * @throws \Exception
     */
    static  public function psw( $psw,$slat ){
        if($psw=='' || $slat=='' ) drFun::throw_exception( '密码不也许为空！',12 ) ;//throw new drException('密码不也许为空！',12);
        return md5($slat.md5( $psw) );
    }

    /**
     * merge用户信息
     * @param array $arr
     * @param array $opt
     * @return $this
     * @throws \Exception
     */
    function merge( &$arr ,$opt=[] ){
        $this->createSql()->merge( $this->tb_user,'user_id',$arr ,[], $opt);
        $key='user_id';
        if( $opt['key'] )   $key= $opt['key'][0] ;
        $key=$key.'_merge';
        foreach( $arr as &$v) {
            unset( $v[$key]['psw']);
            unset( $v[$key]['slat']);
            unset( $v[$key]['google']);
        }
        //foreach ($arr  as &$v) drFun::cdnImg( $v['user_id_merge']);
        return $this;
    }
    function mergeOauth( &$arr ,$opt=[] ){
        $opt['m_name']='oauth_merge';
        $this->createSql()->merge( $this->tb_oauth,'user_id',$arr ,[], $opt);
        return $this;
    }

    /**
     * 从数组中寻找大量user_id 用这些user_id 获取用户信息
     * @param array $arr
     * @param array $keys
     * @param array $opt
     * @return array
     */
    function getUserFromArray( $arr, $keys=['user_id'],$opt=[]){
        if( !is_array( $arr ) ) return  [];
        $u_arr =[];
        if( isset($opt['init']) && $opt['init'] && is_array( $opt['init'] )) {
            $u_arr=$opt['init'];
            $opt['value']=1;
        }

        $this->searchUserID($arr, $keys, $u_arr) ;
        if(! $u_arr) return [];
        return $this->getUserFromUid( $u_arr ,$opt );
    }
    function getUserFromUid( $u_arr ,$opt=[]){
//        if($_GET['debug2']=='sql_user'){
//            print_r( $u_arr );
//            $this->drExit(  $this->createSql()->select( $this->tb_user,['user_id'=> array_keys($u_arr) ])->getSQL() ) ;
//        }
        $uid= array_keys($u_arr);
        if( $opt['value'] ==1)  $uid= array_values($u_arr);
        $list =  $this->createSql()->select( $this->tb_user,['user_id'=>$uid   ])->getAllByKey( 'user_id');
        foreach( $list as $k=>&$v ){
            unset($v['psw']);       unset($v['slat']);
            unset($v['google']);
        }
        if(isset($_POST['MB_version'])){        drFun::cdnImg($list,['head'] );         }
        return $list;
    }

    /**
     * 从数组中寻找user_id
     * @param array $arr
     * @param array $keys
     * @param array $re
     * @return $this
     */
    function searchUserID(  $arr, $keys=['user_id'], &$re  ){
        foreach( $arr as $k=> $v ){
            if( is_array( $v)) $this->searchUserID( $v, $keys, $re );
            elseif( in_array( $k, $keys)){
                $re[ $v ]= $v;
            }
        }
        return $this;
    }

    function getUserByWhere( $where ,$opt=[] ){
        return $this->createSql()->select( $this->tb_user, $where,[0,1000])->getAll();
    }

    /**
     * 获取用户列表带分页
     * @param array|string $where
     * @param int $every
     * @param array $order
     * @return array
     */
    function getUserListWithPage( $where='1',$every=30, $order=[]){
        //$where = '1';
        return $this->createSql()->selectWithPage( $this->tb_user,$where,$every,[],$order );
    }

    /**
     * 统计全站用户信息
     * @return array
     */
    function tjUser( $school='' ){
        if( $school=='' )   $col = $this->createSql("SELECT ts, count( * ) AS cnt  FROM `user` GROUP BY ts ")->getCol2();
        else {
            $col = $this->createSql("SELECT ts, count( * ) AS cnt  FROM `user` where school=:school  GROUP BY ts ",[':school'=>$school ])->getCol2();
        }
        $re= ['total'=>array_sum( $col),'detail'=> $col ];
        $re['today'] =$this->createSql()->getCount( $this->tb_user,['>'=>['ctime'=> strtotime( date("Y-m-d"))]])->getOne();
        $yesterday = $this->createSql()->getCount( $this->tb_user,['>'=>['ctime'=> (strtotime( date("Y-m-d"))-86400)  ]])->getOne();
        $re['yesterday']= $yesterday- $re['today'] ;
        return $re ;
    }

    /**
     * marge第三方Oauth信息
     * @param array $re
     * @param array $opt
     * @return $this
     */
    public  function margeOauth( &$re, $opt=[]){
        $uids =[];
        $this->searchUserID($re,['user_id'] ,$uids );
        if(!$uids ) return $this;
        $tall = $this->createSql()->select( $this->tb_oauth,['user_id'=>$uids])->getAllByKeyArr( ['user_id'  ]);
        //$this->drExit( $tall );
        foreach( $re as $k=>$v ){
            $key = $v[ 'user_id'];
            $re[$k ]['oauth']= $tall[$key];
        }
        return $this;
    }

    /**
     * marge用户attr信息
     * @param $re
     * @return $this
     */
    function margeAttr(  &$re ){
        $uids =[];
        $this->searchUserID($re,['user_id'] ,$uids );
        if(!$uids ) return $this;
        $tall = $this->createSql()->select( $this->tb_attr,['user_id'=>$uids])->getAllByKeyArr( ['user_id','key']);
        foreach( $re as $k=>$v ){
            $key = $v[ 'user_id'];
            $re[$k ]['attr']= $tall[$key];
        }
        return $this;

    }

    /**
     * 从excel当中导入用户信息. 表头也会尝试导入，不成功也忽略错误
     * @param string $ex_file excel文件
     * @param array $re 返回用户信息
     * @return $this
     */
    public function imUserFromExcel( $ex_file ,&$re=[] ){
        $data = drFun::excelReadToArray( $ex_file);
        $success  =0; $err = [];
        $ik = 0;
        $login = new login();
        foreach ($data as $sheet ){
            $k2=0;
            foreach ($sheet['data'] as $v ){
                $k2++;
                if($v['A']=='' ) continue;
                $ik++;
                try{
                    $from_id = $this->getOpenidOauthfrom( $v['A']);
                    $ts= ( trim($v['D'])=='教师'|| trim($v['D'])=='老师'?3:2);
                    $new_user_id = $this->add( $v['A'],$from_id, $v['C'], $ts ,['psw'=>$v['B'],'school'=>$v['E']] );
                    $login->createLogGt()->append( $new_user_id,1 ,trim($v['F'] ));
                    if(  $new_user_id>0 )       $login->sendRegPsw( $v['A'], $v['B'] ,['ts'=>$ts,'name'=> $v['C'] ] );

                    $success++;
                }catch ( \Exception $ex ){
                    if( $k2 >1 ) $err[ ]=['error'=>$ex->getMessage(),'d'=>$v ];
                    $userOauth = $this->getUserOauth( $v['A'],$from_id);
                    $new_user_id= $userOauth['user_id'];
                    if( $v['F']!='' and $ex->getCode()==9 ){
                        $login->createLogGt()->append($new_user_id,1 ,trim($v['F'] ));
                    }
                }

                if( $new_user_id>0 && $v['G']!=''){
                    //$this->drExit('user_id'. $new_user_id );
                    $this->getLogin()->createUserOne( $new_user_id )->opAttr( ['coll'=>$v['G'] ] );
                }
            }
        }
        $re['total']= $ik;
        $re['success']= $success ;
        $re['error']= $err;

        return $this;
    }



    /**
     * 用户身份类型 2=>'学生',1=>'一般教师',3=>'认证教师'
     * @param string $ts
     * @return array
     */
    static public function getTypeTs( $ts='all'){
        $type=[2=>'学生',1=>'一般教师',3=>'认证教师'];
        if( $ts=='all') return $type;
        if( !isset( $type[$ts])) drFun::throw_exception("身份错误！",18 );
        return ( $type[$ts]);
    }

    /**
     * 性别类型 1=>'男',2=>'女',0=>'-'
     * @param string $sex
     * @return array
     */
    static public function getTypeSex( $sex='all' ){
        $type=[1=>'男',2=>'女',0=>'-'];
        if( $sex =='all') return $type;
        if( !isset( $type[$sex])) drFun::throw_exception("性别类型错误",19 );
        return ( $type[$sex]);
    }

    /**
     * 账号类型 '1'=>'邮箱',2=>'手机',3=>'QQ',4=>'微博',5=>'微信'
     * @param string $from
     * @return array
     */
    static public function getTypeOauthFrom( $from='all'){
        $type= ['1'=>'邮箱',2=>'手机',3=>'QQ',4=>'微博',5=>'微信'];
        if( $from=='all') return $type;
        if( !isset( $type[$from])) drFun::throw_exception("账号类型错误！",20 );
        return ( $type[$from]);

    }

    /**
     * 获取用户属性 有哪些用户属性请参考{@link getKeyAttrAll}
     * @param $attr
     * @return array
     */
    public function getUsersByAttr( $attr ){
        if( is_array( $attr )){
            foreach ($attr as $v )  self::getKeyAttrAll(  $v );
        }else   self::getKeyAttrAll(  $attr );
        $re = $this->createSql()->select('user_attr',['key'=>$attr ] )->getAllByKeyArr(['user_id']);
        return $re ;
    }

    /**
     * 获取用户 权限管理 属性 key不超过5个字母
     * op=one 仅有一次 可删除可修改， onedel 要不增加要不删除，   默认append是一直增加
     * @param string $key key不超过5个字母
     * @return array
     */
    static public function getKeyAttrPre( $key='all'){
        //
        $type=['p1'=>['n'=>'超级管理员','op'=>'onedel']];
        $type['p2']= ['n'=>'财务','op'=>'onedel'] ;
        $type['p3']= ['n'=>'操作员','op'=>'onedel'] ;
        $type['p4']= ['n'=>'商户','op'=>'onedel'] ;
        $type['p7']= ['n'=>'查单员','op'=>'onedel'] ;
        //$type['p5']= ['n'=>'码商','op'=>'onedel'] ; //直接在 user_ma出现的就是码商
        if( $key=='all' ) return $type;
        if( !isset( $type[$key])) drFun::throw_exception("管理员类型错误！",21 );
        return ( $type[$key]);
    }

    /**
     * 获取用户 一般 属性 key不超过5个字母
     * @param string $key key不超过5个字母
     * @return array
     */
    static public function getKeyAttr(  $key='all' ){
        $type =[];// self::getKeyAttrPre();
        $type['zw']=['n'=>'职务','op'=>'one'] ;
        $type['zj']=['n'=>'职级','op'=>'one'] ;
        $type['yz']=['n'=>'语种','op'=>'one'] ;
        $type['mail']=['n'=>'邮箱','op'=>'one'] ;
        $type['bz']=['n'=>'备注','op'=>'one'] ;
        $type['coll']=['n'=>'学院','op'=>'one'] ;
        $type['dm']=['n'=>'域名','op'=>'one'] ;
        $type['c_id']=['n'=>'收款账号','op'=>'one'] ;
        $type['c_name']=['n'=>'收款人','op'=>'one'] ;
        $type['c_bank']=['n'=>'银行','op'=>'one'] ;
        $type['c_add']=['n'=>'开户行','op'=>'one'] ;
        if( $key=='all' ) return $type;
        if( !isset( $type[$key])) drFun::throw_exception("用户一般属性类型不存在！",22 );
        return ( $type[$key]);
    }

    /**
     * @param $school
     * @param $college
     * @return $this
     */
    function modifyCollegeDefault( $school,$college){
        if(!$school ||  !$college) $this->throw_exception( "学校、学院不允许为空",1028);
        $uids = $this->createSql()->select($this->tb_user,['ts'=>3,'school'=>$school],[],['user_id','user_id'])->getCol2();
        if( !$uids ) return $this;
        $u_arr = $this->createSql()->select($this->tb_attr,['user_id'=>$uids,'key'=>'coll'],[],['user_id'])->getCol();
        foreach( $u_arr as $uid ) unset( $uids[$uid] );
        if( !$uids ) return $this;
        $col=[];
        foreach ( $uids as $uid )  $col[]=['user_id'=>$uid , 'key'=>'coll' ,'value'=> $college ];
        $this->createSql()->insertPL($this->tb_attr,$col )->query();
        return $this;
    }

    /**
     * 获取用户 全部（一般{@link getKeyAttrPre} +权限管理 {@link getKeyAttr}） 属性 由
     *
     * @param string $key key不超过5个字母
     * @return array
     */
    static public function getKeyAttrAll( $key='all'  ){
        $type =  self::getKeyAttrPre();
        $type2 =  self::getKeyAttr();

        foreach( $type2 as $k=>$v ) $type[$k]= $v ;
        if( $key=='all' ) return $type;
        if( !isset( $type[$key])) drFun::throw_exception("该类型不存在" . $key,22 );
        return ( $type[$key]);
    }

    /**
     * 从$_GET转换为 where
     * @param $get
     * @return array
     */
    public function getWhere( $get ){
        $q = trim($get['q'] );
        $where=[];
        if( isset($_GET['start_uid']) &&  intval($_GET['start_uid'])>0 ) $where['>']['user_id']=  intval($_GET['start_uid'])  ;

        if( ($get['sq']=='email' ||$get['sq']=='tel' ) and $q!=''){
            $from_id= $this->getOpenidOauthfrom(  $get['q'] );
            $oauth = $this->getUserOauth($q,$from_id );
            if( !$oauth ) $this->throw_exception( "账号不存在！");
            return ['user_id'=>$oauth['user_id']];
        }elseif(    $q!='' ){
            $where['name']= $q;
        }
        $school = trim($get['school']);
        if($school) $where['school']= $school;



        return  $where;
        //return $where?$where:"1";
    }

    /**
     * 获取一个学校的老师 并 key为 user_id
     * @param $school
     * @param $ts 3 为认证教师 1为一般老师 如果2个都需要 就直接 [1,3]
     * @return array
     */
    public function getTeachersBySchool( $school ,$ts=3 ){

        return  $this->createSql()->select( $this->tb_user,['school'=>$school,'ts'=>$ts ])->getAllByKey( 'user_id');
    }

    /**
     * 删除账号绑定ID
     * @param string $id openid
     * @return $this
     */
    function delOpenidByID( $id ){
        $row = $this->createSql()->select($this->tb_oauth,['id'=>$id])->getRow() ;//
        if( !$row ) $this->throw_exception("账号不存在",23 );
        $cnt = $this->createSql()->getCount( $this->tb_oauth,['user_id'=>$row['user_id']])->getOne();
        if( $cnt<=1) $this->throw_exception("账号仅存在一个绑定无法删除",24 );
        $this->createSql()->delete($this->tb_oauth,['id'=>$id] )->query();
        return $this;
    }

    /**
     * group 学校的用户cnt
     * @param $school
     * @return array
     */
    function getUserCountGroupBySchool( $school ){
        if(!is_array( $school ) || !$school ) $this->throw_exception( "必须带学校",1025 );
        $sql = "SELECT school, ts, COUNT( * ) AS cnt FROM  ".$this->tb_user." where school in('".implode("','", $school)."') GROUP BY school, ts";
        $tall = $this->createSql($sql)->getAllByKeyArr( ['school','ts']);
        return $tall;
    }

    function getSchoolList(){
        $list = $this->createSql()->select('book_school','1',[],['id','school'])->getAllByKey('id');
        foreach( $list as $k=>&$v ){
            if( $v['school']=='好策'||  $v['school']=='首都大学') unset($list[$k] );
        }
        return $list;
    }

}