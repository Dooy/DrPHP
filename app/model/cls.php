<?php
/**
 * 班级相关的操作
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/21 0021
 * Time: 下午 2:23
 */

namespace model;




use model\user\login;
use model\user\one;

class cls extends model
{
    private $user_id=0;
    private  $table='class';
    private $table_stu= 'class_student';
    private $tb_role = 'class_role';
    private $tb_teacher='class_teacher';
    private $_clsFile= ['class'=>1,'grade'=>1,'user_id'=>1,'stu_cnt'=>1,'ctime'=>1,'school_id'=>1,'school_user_cnt'=>1,'term_key'=>1 ];
    private $_clsStuFile = [ 'user_id'=>1, 'class_id'=>1, 'name'=>1,'number'=>1,'ctime'=>1, 'type'=>1 ,'task_cnt'=>1 ];
    function __construct( $user_id )
    {
        $this->user_id = intval( $user_id );
    }

    /**
     * 获取一位班级学生用户
     * @param int $id
     * @return array
     */
    function getClassStudentByID( $id ){
        $re= $this->createSql()->select( $this->table_stu,['id'=> $id])->getRow();

        $this->class_stu( $re );
        //print_r( $re );$this->drExit( );
        return $re ;
    }

    /**
     * 从班级学生列表中删除出来
     * @param int $id
     * @return $this
     * @throws drException
     */
    public function remove( $id ){
        if( $id<=0) $this->throw_exception("参数ID错误",530  );
        $urow = $this->getClassStudentByID( $id );
        if(! $urow )$this->throw_exception("该记录不存在",531  );
        if( !( $urow['cls_info']['user_id']== $this->user_id || $urow['user_id']== $this->user_id  )) $this->throw_exception("越权操作",532  );
        $class_id = $urow['class_id'];
        $this->createSql()->delete( $this->table_stu,['id'=>$id])->query();
        $this->updateClassStu($class_id);
        return $this;
    }

    /**
     * 获取当前用户 没则抛出异常
     * @return int
     * @throws drException
     */
    public function getUserId(){
        if( $this->user_id<=0 ) $this->throw_exception( "UID 必须大于",534 );
        return $this->user_id;
    }

    /**
     * 加入班级，一般会带着学号和班级，这个时候如果用户信息number class为空 会去更新用户信息
     * @param int $class_id
     * @param array $var
     * @return $this
     * @throws drException
     */
    public function join( $class_id,$var =[] ){
        if( $class_id<=0) $this->throw_exception("班群号必去大于0",510  );
        $dclass= $this->getClassWithTypeById($class_id );

        $where =  ['user_id'=>$this->getUserId(),'class_id'=> $class_id];
        $opt= $where;
        $one = $this->createSql()->getCount( $this->table_stu, $opt )->getOne();

        drFun::arrExtentByKey(   $opt, $var,$this->_clsStuFile, false );



        if(! $one) {
            $opt['ctime']=time();
            $this->createSql()->insert($this->table_stu, $opt)->query();
        }else{
            $this->createSql()->update( $this->table_stu,$opt,$where)->query();
        }
        $this->updateClassStu( $class_id);

        $uData= ['number'=>$var['number'],'class_id'=>$class_id ];
        $this->initUserNumberAndClass( $uData );
        return $this;
    }

    /**
     * 初始学号和班级 同时会更改 term_user_[key]表，如果存在 就不修改
     * @param $uData ['number'=>$var['number'],'class_id'=>$class_id ];
     * @return $this
     * @throws drException
     */
    public function initUserNumberAndClass( $uData ){
        if( $uData['number'] ) {
            $uOne = new one($this->getUserId());
            $user = $uOne->clear()->getUser();
            if (!$user['number']) $uOne->up_info(['number' => $uData['number']]);
        }
        $term = new term();
        $uTerm = $term->getUserTermByUid( $this->getUserId() );
        $up_arr = [];

        if ( (!$uTerm['class_id'] || !$uTerm['class'] )&& $uData['class_id']>0) {
            $cls = $this->getClassById($uData['class_id']);
            $up_arr['class_id']=  $uData['class_id'];
            $up_arr['class']= $cls['class'];
        }

        if( $uData['number']  ){
            $up_arr['number']=  $uData['number'] ;
        }
        if( $up_arr ){
            $term->editUserTerm( $this->getUserId(), $up_arr );
        }
        //$login = new login();        $login->regCookie(  $this->getUserId() );
        return  $this;
    }

    /**
     * 更新class有多少学生 class.stu_cnt 加入
     * @param int $class_id
     * @return $this
     * @throws \Exception
     */
    public function updateClassStu( $class_id ){
        $cnt = $this->createSql()->getCount( $this->table_stu,['class_id'=> $class_id ])->getOne();
        $this->createSql()->update($this->table,['stu_cnt'=> $cnt],['class_id'=> $class_id ])->query();
        return $this;
    }

    /**
     * 获取当前用户的一个班级学生列表
     * @param int $class_id
     * @return array
     */
    public function getClassStudent( $class_id ){
        $opt= ['user_id'=>$this->user_id,'class_id'=> $class_id];
        $row = $this->createSql()->select( $this->table_stu, $opt)->getRow();
        return $row;
    }

    /**
     * 获取学生班级名单 以user_id做统计
     * @param array|int $uid
     * @return array
     */
    public function getStudentClassByUid( $uid ){
        return $this->createSql()->select( $this->table_stu ,['user_id'=> $uid ] ,[],[],['id'=>'asc'])->getAllByKeyArr(['user_id']);
    }

    /**
     * 获取学生最后加入班级名单
     * @param  array|int  $uid
     * @return array
     */
    public function getLastStudentClassByUid( $uid ){
        return $this->createSql()->select( $this->table_stu ,['user_id'=> $uid ] ,[],[],['id'=>'asc'])->getAllByKey( 'user_id' );
    }

    public function getClassList( $c_id ){
        return $this->createSql()->select( $this->table ,['class_id'=>$c_id ])->getAllByKey('class_id');
    }

    /**
     * 获取一个班级的用户列表
     * @param $class_id
     * @return array
     */
    public function getClassStudentList( $class_id ){
        $opt= [ 'class_id'=> $class_id];
        $tall = $this->createSql()->select( $this->table_stu, $opt)->getAll();
        return $tall;
    }

    public function orderStudentList( &$list ,$key,$desc_asc='desc' ){
        switch ($key){
            case 'number':
                $fun= function ( $a,$b) use ( $desc_asc){
                    $a_num = intval( preg_replace('/([^0-9]+)/i', "", $a['number']));
                    $b_num = intval( preg_replace('/([^0-9]+)/i', "", $b['number']));
                    if( $a_num==$b_num ) return 0;
                    $rz= $a_num>$b_num?1:-1;
                    return  $desc_asc=='desc'? $rz: -$rz;
                };
                break;
            default:
                $this->throw_exception("暂不支持这类排序",7223);
        }

        usort($list, $fun );
    }

    /**
     * 获取一行class，并且检查 班级是否归档  {@link getClassById}
     * @param int $class_id
     * @param string $type
     * @return mixed
     * @throws drException
     */
    function getClassWithTypeById(  $class_id,$type='nocheck' ){
        $dclass = $this->getClassById($class_id );
        if($type!='nocheck' && $dclass['type'] != $type  ) $this->throw_exception("班群号当前不存在",512  );
        elseif( !in_array( $dclass['type'],[0,5 ] )){
            $this->throw_exception("班级已经归档！",512  );
        }
        return $dclass;
    }

    /**
     * 获取一个班级名称，班级名下的学生
     * @param int $class_id
     * @return array
     * @throws drException
     */
    public function getClassAndStudent( $class_id ){
        $re= ['class'=> $this->getClassWithTypeById($class_id,0 ),'stu'=> $this->getClassStudent( $class_id)];
        return $re ;
    }

    /**
     * 添加班级,新建一个空班级
     * @param $opt
     * @return int
     * @throws drException
     */
    function classAdd( $opt ){
        if( isset($opt['grade'] ) ) {
            $opt['grade'] = intval($opt['grade']);
            if (!$opt['class'] || $opt['grade'] <= 0) $this->throw_exception('添加参数出错', 500);
            if (!self::getGrade($opt['grade'])) $this->throw_exception('年级参数出错', 501);
        }

        $var=  $opt;
        $var['user_id']=  $this->user_id ;//$var['user_id']<=0 ?  $this->user_id :  $var['user_id'];
        $var['ctime'] = time();

        $where = ['user_id' => $this->user_id, 'class' => $opt['class']];
        if($opt['type']!=5 ) {
            $row = $this->createSql()->select($this->table, $where)->getRow();// $this->createSql("select * from ". $this->table ." where user_id='".$this->user_id."'  and class= :class",[':class'=>$opt['class'] ] );
            if ($row) $this->throw_exception($opt['class'] . "已经存在，请核实！", 502);
        }
        if( $this->getLogin()->createBlock()->isBlockTerm($var['term_key']) ) $var['block_id']= $this->getLogin()->createBlock()->isBlockTerm($var['term_key']) ;
        return $this->insert( $this->table, $var,['user_id'=>'n','class'=>'n','ctime','grade','type','school_id','school_id','term_key','block_id' ] ) ;//$this->createSql()->insert( $this->table, $var )->query()->lastID() ;

    }

    /**
     * 获取 一个班级
     * @param int $class_id
     * @return array
     * @throws drException
     */
    public function getClassById( $class_id ){
        if( is_array($class_id )) return $this->getClassList($class_id );

        $row = $this->createSql()->select( $this->table,['class_id'=>$class_id])->getRow();
        if( !$row) $this->throw_exception("班群号不存在或者已经删除！" ,511 );
        return $row;
    }

    /**
     * 检查班级 是不是同一个学校
     * @param $class_id
     * @param $school_id
     * @return $this
     * @throws drException
     */
    public function checkSchoolByCid( $class_id,$school_id  ){
        $c_row = $this->getClassById( $class_id );
        if( $c_row['school_id'] != $school_id ) $this->throw_exception( "不在一个学校越权操作！", 522  );
        return $this;
    }

    /**
     * 获取一个老师的班级列表 并附带分页
     * @param int $type
     * @return array
     */
    public function getTList( $type=0 ){
        $where= ['user_id'=>$this->user_id,'type'=>$type ];
        return $this->createSql()->selectWithPage( $this->table, $where,10,[],[ 'class_id'=>'desc'] );
    }

    /**
     * 获取一个学生的班级类别，并附带分页
     * @param int $type
     * @return array
     */
    public function getSList( $type=0  ){
        $where= ['user_id'=>$this->user_id,'type'=>$type ];
        $re = $this->createSql()->selectWithPage( $this->table_stu, $where,10,[],[ 'id'=>'desc'] );
        $this->class_stu( $re['list']);
        return $re ;
    }

    /**
     * marge班级名称
     * @param array $class_stu
     * @return $this
     */
    public function class_stu( &$class_stu ){
        if( !$class_stu ) return $this;
        $cid_arr =[];

        if( isset($class_stu['class_id']) ){
            //$cid_arr[  $class_stu['class_id'] ] =$class_stu['class_id'] ;
            $class_id =          $class_stu['class_id'];
            $class = $this->getClassById( $class_id);
            $class_stu['class']= $class['class'];
            $class_stu['cls_info']= $class ;

        }
        else {
            foreach ($class_stu as $v) {
                if (isset($v['class_id'])) $cid_arr[$v['class_id']] = $v['class_id'];

            }
            if( !$cid_arr ) $this->throw_exception( "班级信息错误！", 513);
            $tall = $this->createSql()->select( $this->table,['class_id'=> array_keys( $cid_arr)] )->getAllByKey('class_id');

            foreach ($class_stu as $k=> &$v) {
                if( !isset($v['class_id']))  continue;//$cid_arr[$v['class_id']] = $v['class_id'];
                $class_id = $v['class_id'];
                $v['class']= $tall[ $class_id]['class'];
                $v['cls_info']=  $tall[ $class_id];
            }

        }

        return $this;







    }

    /**
     * 删除归档一个班级
     * @param int $class_id
     * @return $this
     */
    public function delClass( $class_id  ){
        $this->upType( $class_id,-1);
        return $this;
    }

    /**
     * 恢复一个已经归档的班级
     * @param int $class_id
     * @return $this
     */
    public function backClassFromDel( $class_id ){
        $this->upType( $class_id, 0 );
        return $this;
    }

    /**
     * 更新一个班级在这台 操作类型有 -5，-1,0 5
     * @param int $class_id
     * @param int $type
     * @return $this
     */
    public function upType( $class_id, $type ){
        //$type_arr= $this->typeArr();
        if( ! $this->typeArr( $type) )  $this->throw_exception("操作类型不支持" ,506 );
        $row = $this->createSql()->select( $this->table,['class_id'=> $class_id])->getRow();
        if( ! $row) $this->throw_exception("呵呵班级不存在啊" ,503 );
        if( $row['user_id'] != $this->user_id)  $this->throw_exception("越权操作！" ,504 );
        $this->createSql()->update( $this->table, ['type'=> $type],['class_id'=>$class_id ])->query();
        return $this;
    }

    /**
     * 获取班级的操作属性
     * @param int $type
     * @return array|bool|mixed
     */
    private  function typeArr( $type=-100){
        $type_arr = ['-1'=>'归档' ,0=>'教师添加' ];
        $type_arr[5]= '校添加';
        $type_arr[-5]= '校归档';
        if($type==-100 ){
            return $type_arr;
        }
        if( isset( $type_arr[ $type ] )) return $type_arr[ $type ]   ;
        $this->throw_exception("操作类型不支持" ,506 );
    }

    /**
     * 获取班级的操作属性
     * @param string $type
     * @return array|bool|mixed
     */
    function getType( $type='all'){
        $all = $this->typeArr( -100 );
        if( $type=='all' ) return $all;
        if( isset( $all[$type])) return $all[$type];
        $this->throw_exception( $type." 不存在",517 );
    }

    /**
     * 真删除一个班级，目前函数是空的！
     * @param int $class_id
     * @return $this
     */
    public function realDelClass( $class_id ){
        $cnt = $this->createSql()->getCount( $this->table_stu , ['class_id'=> $class_id])->getOne();
        if( $cnt>0 ) $this->throw_exception("班级已有学生加入无法删除" ,517 );

        $row = $this->createSql()->select( $this->table,['class_id'=> $class_id])->getRow();
        if( ! $row) $this->throw_exception("班级已经删除或者不存在" ,503 );
        $login = new login();
        if( !( $row['user_id'] == $this->user_id   ) ) { //|| $login->isSchoolAdmin()
            $this->throw_exception("班级不是你建立的无法删除！" ,514 );
        }

        $login->createLogRecycle()->append($class_id, 202 , $row );
        $this->createSql()->delete(   $this->table,['class_id'=> $class_id] )->query();
        //删除 class 表
        //删除 class_student
        return $this;
    }

    /**
     * 获取年级 gid=-2 以分类的形式存在！
     * @param int $gid
     * @return array|mixed
     */
    public static function getGrade( $gid=-1 ){
        $re= [
            '大学'=>[
             2017=>'2017级',2016=>'2016级',2016=>'2016级',2015=>'2015级',2014=>'2014级',2013=>'2013级'
                ,   31=>'大一'
            ,32=>'大二'
            ,33=>'大三'
            ,34=>'大四'
            ]

            ,'研究生'=>[41=>'研一'
                ,42=>'研二'
                ,44=>'研三']

            ,'高中'=>[21=>'高一'
                ,22=>'高二'
                ,23=>'高三'
                ,24=>'高四']

            ,'初中'=>[11=>'初一'
                ,12=>'初二'
                ,13=>'初三'
                ,14=>'初四']

          , '小学'=>[ 1=>'一年级'
            ,2=>'二年级'
            ,3=>'三年级'
            ,4=>'四年级'
            ,5=>'五年级'
            ,6=>'六年级']

            ,''=>[51=>'博士'
            ,61=>'培训'
            ,99=>'其他']

        ];
        if( $gid==-2){
            return $re ;
        }
        $re2 = [];
        foreach( $re as $k=>$v ){
            if( is_array( $v)){
                foreach( $v as $k2=>$v2 ) $re2[$k2]=$v2;
            }else $re2[$k]=$v ;
        }
        if( $gid<0 ) return $re2;
        return $re2[ $gid];
    }

    /**
     * 通过年级名字获取年级ID
     * @param string $grade
     * @return int|string
     */
    function getGidByGrade( $grade ){
        $all = self::getGrade(-1);
        foreach ( $all as $gid=>$name){
            if( $grade==$name || $grade==$gid ) return $gid;
        }
        return 99; //其他
    }

    /**
     * 获取本学期的学校
     * @param $school_id
     * @return array
     * @throws \Exception
     */
    function getTermNowClass( $school_id ,$term_key ){
        //$term = new term();
        //$term_config = $term->getConfigForSchool( $school_id , $term->getNow() );
        //$cls_exit= $this->createSql()->select($this->table,['school_id'=>$school_id  ,'between'=>[ 'ctime'=>[ $term_config['start_time'], $term_config['end_time']]  ],'type'=>[5,-5]]  )->getAllByKey( 'class');
        $block_id = $this->getLogin()->createBlock()->isBlockTerm( $term_key );
        if( $block_id ){
            $cls_exit= $this->createSql()->select($this->table,[ 'block_id'=>$block_id  ,'type'=>[5,-5]]  )->getAllByKey( 'class');
        }else{
            $cls_exit= $this->createSql()->select($this->table,['school_id'=>$school_id ,'term_key'=>$term_key  ,'type'=>[5,-5]]  )->getAllByKey( 'class');
        }
        return $cls_exit;
    }

    /**
     * 导入班级名称 通过学校管理员
     * 说明：
     * - 只导入本学期，会去检查本学期导入的班级情况
     * - 如果本学期存在 则进行修改
     * - 利用 teacher_account teacher_name去检查账号
     * @param string $file
     * @param array $opt
     * @return array
     * @throws drException
     */
    function implodeForSchool( $file,$opt=[]){
        $arr= drFun::excelReadToArray( $file );
        if( !isset( $opt['term_key'] ) ||  !$opt['term_key'] ) $this->throw_exception("必须包含学期信息！",521 );


        $rz=['insert'=>0,'update'=>0 ];
        if($opt['user_id']<=0 || $opt['school']=='' ||$opt['school_id']<=0 ){
            $this->throw_exception("opt 中必须包含 user_id、school,school_id 而且不为空！",515 );
        }
        $list =[]; $teacher_name = []; $teacher_account= [];
        foreach( $arr as $k0=> $sheet){
            foreach ($sheet['data'] as $k=>$v ){
                if($k<=1) continue;
                $key =$v['A'] ;// trim($v['A']);
                if( $key=='') continue;
                #多账号
                if( trim($v['C'])!='' ){
                    //$teacher_account[  trim($v['C']) ]=1;
                    $tem= preg_split(  '/[ ,|]+/', trim($v['C']) );
                    foreach( $tem as &$a_v) {
                        $a_v = trim( $a_v );
                        $teacher_account[ $a_v ] = 1;
                    }
                    $v['C']= $tem;

                }
                if( trim($v['D'])!='' )$teacher_name[  trim($v['D']) ]=1;

                $list[$key]= $v ;
            }
        }
        unset( $arr );
        if( count($list)<=0 ) $this->throw_exception( "无可以导入的资料",516);
        $cls_exit= $this->getTermNowClass(  $opt['school_id'],$opt['term_key'] );

        #以账号 搜索老师当任课老师
        $accout_key = !$teacher_account?[]:  $this->createSql()->select('user_oauth',['openid'=> array_keys($teacher_account)] ,[],['openid','user_id'])->getCol2();

        #以名字 搜索老师当任课老师
        foreach( $teacher_account as $key=>$v ) if( isset( $accout_key[$key])) unset( $teacher_account[$key] );
        if( $teacher_account ) {
            $where =['name' => array_keys($teacher_account), 'school' => $opt['school'], 'ts' => 3];
            $accout_group = $this->createSql()->group('user',['name'],$where,['name','count(*) as cnt'])->getCol2();
            $str='';
            foreach($accout_group as $k=>$v ){
                if( $v>1 ) $str.= $v.'位'.$k.',' ;
            }
            if( $str ) $this->throw_exception( trim($str,',' ) ." 同名请处理后再导入！",518 );

            $accout_key2 =!$teacher_account?[]:  $this->createSql()->select('user', $where, [], ['name', 'user_id'])->getCol2();
            drFun::arrExtend($accout_key2, $accout_key, false);
            $accout_key = $accout_key2;
            unset($accout_key2);
        }
        #end 用名字去搜索老师当任课老师

        $insert =[];
        foreach( $list as $k=>$v ){
            $name = $v['D'];
            $accout =  $v['C'];
            $user_id = $opt['user_id'];  // isset($accout_key[ $accout ])?$accout_key[ $accout ]:( isset(  $name_key[ $name ])?$name_key[ $name ]: $opt['user_id'] );
            if( !isset( $cls_exit[ $k] ) ) {
                $class_var  = ['class' => $v['A'], 'user_id' => $user_id, 'ctime' => time(), 'type' => 5, 'grade' => $this->getGidByGrade($v['B']), 'school_id' => $opt['school_id'],'term_key'=>$opt['term_key']   ];
                $class_id = $this->classAdd( $class_var );
                $rz['insert']++;
            }else{
                $arr = [  'grade' => $this->getGidByGrade($v['B']), 'school_id' => $opt['school_id'] ];
                //,'term_key'=>$opt['term_key']
                //if(  isset($accout_key[$v['D']]) )    $arr['user_id']= $accout_key[$v['D']];
                //if(  isset($name_key[$v['C']]) )    $arr['user_id']= $name_key[$v['C']];
                $this->update($this->table,  ['class_id'=>$cls_exit[ $k]['class_id']], $arr );
                $rz['update']++;
                $class_id= $cls_exit[ $k]['class_id'];
            }
            if( $accout ){
                $user_role=[];
                foreach ( $accout as $a_c){
                    if( isset( $accout_key[$a_c] ) ) $user_role[ $accout_key[$a_c] ]= $accout_key[$a_c];
                }
                $this->replaceClassRole($class_id, $user_role );
            }
        }
        if( $insert ) $this->createSql()->insertPL( $this->table, $insert )->query();
        return $rz;
    }

    /**
     * 替换角色 先删除全部角色，然后
     * @param int $class_id
     * @param array $user_role user_id 数组
     * @param int $role
     * @return $this
     */
    function replaceClassRole( $class_id,$user_role,$role =2){
        $this->createSql()->delete( $this->tb_role,['class_id'=>$class_id,'role'=>$role ],300)->query() ; //->getSQL();//
        $insert=[];
        foreach( $user_role as $uid  ){
            $insert[]= ['class_id'=>$class_id,'user_id'=>$uid, 'role'=>$role ];
        }
        if( $insert ) $this->createSql()->insertPL( $this->tb_role, $insert )->query();
        return $this;
    }

    /**
     * 获取学校班级列表 带分页
     * @param int $school_id
     * @param array $where
     * @return array
     */
    function getListForSchoolWithPage( $school_id,$where=[  ]){
        $where2['school_id']= $school_id;
        foreach ( $where as $k=>$v ) $where2[$k]= $v;
        return $this->createSql()->selectWithPage( $this->table,$where2,30,[],['class_id'=>'desc']);
    }

    /**
     * 获取班级列表
     * @param array $where
     * @return array
     */
    function getList( $where){
        return $this->createSql()->select( $this->table, $where )->getAllByKey('class_id');
    }

    function getListWithPage( $where ){
        return $this->createSql()->selectWithPage(  $this->table,$where,15 );
    }



    /**
     * 编辑一个班级的信息
     * @param int $class_id
     * @param array $opt
     * @return $this
     */
    function editClass( $class_id, $opt ){
        $this->update( $this->table,['class_id'=>$class_id],$opt, $this->_clsFile );
        return $this;
    }

    /**
     * 将班级角色marge进班级里来
     * @param array $class 当中一定含有class_id
     * @return $this
     */
    function margeClassRole( &$class ){
        if(! is_array($class )|| ! $class ) return$this;
        if( isset( $class['class_id'])){
            $class['role'] = $this->createSql()->select( $this->tb_role,['class_id'=>$class['class_id'] ])->getAllByKeyArr(['user_id']);
            return $this;
        }
        $cid_arr =[];
        drFun::searchFromArray( $class, ['class_id'], $cid_arr);
        if( !$cid_arr ) return $this;
        $role =  $this->createSql()->select( $this->tb_role,['class_id'=>$cid_arr ])->getAllByKeyArr(['class_id','user_id']);
        foreach( $class as $k=>$v  ){
            if(!isset( $v['class_id']) ) continue ;
            $class_id = $v['class_id'];
            $class[$k]['role']= $role[$class_id ];
        }
        return $this;
    }

    /**
     * 将任课老师加入到指导老师中去
     * @param $class_info
     * @param $book_admin
     * @return $this
     */
    function margeRoleToBookAdmin($class_info ,&$book_admin ){
        if( !$class_info['role'] )return $this ;
        foreach($book_admin as $k=> $v ){
            if( isset(  $class_info['role'][ $v['user_id']] )){
                unset(  $class_info['role'][ $v['user_id']] );
                $book_admin[$k]['zd']=1;
            }
        }
        foreach(  $class_info['role'] as $k3=>$v3  ){
            //$book_admin[]= ['user_id'=>$k3 ];
            array_unshift( $book_admin, ['user_id'=>$k3 ,'zd'=>1] );
        }

        return $this;
    }

    /**
     * 获取某角色下的class id 数组
     * @param int $role
     * @return array
     */
    function getClassIDByRole( $role=2 ,$opt=[] ){
        $where =['user_id'=>$this->getUserId(),'role'=>$role ] ;
        if( isset( $opt['term_key'])) $where['term_key']=  $opt['term_key'];
        return $this->createSql()->select( $this->tb_role, $where ,[],['class_id','class_id'] )->getCol2();
    }

    /**
     * 获取班级ID获取角色
     * @param array|init $class_id
     * @param int $role
     * @return array
     */
    function getRoleByClassID( $class_id, $role=null){
        if( !$class_id ) return [];
        $where = [ 'class_id'=>$class_id ];
        if( $role!==null )$where['role']= $role;
        return $this->createSql()->select( $this->tb_role, $where)->getAllByKeyArr( ['class_id']);
    }

    /**
     * 获取某个班级所有的角色
     * @param $class_id
     * @param bool $role
     * @return mixed
     */
    function getClassRosesByClassID( $class_id, $role= false){
        $where= ['class_id'=>$class_id];
        if( $role!==false ) $where['role']= $role;
        return $this->createSql()->select($this->tb_role,$where )->getAll();
    }

    /**
     * 批量更新班级白名单人数
     * @return $this
     */
    function updateClassSchoolUserCntPL( ){
        $cl_term = new term();
        $sql = "select class_id,count(*) as cnt from ". $cl_term->getTableSchoolUser()." group by class_id";
        $tall = $this->createSql($sql )->getAll( $sql );
        foreach( $tall as $k=>$v ){
             $this->createSql()->update( $this->table,['school_user_cnt'=>$v['cnt'] ],['class_id'=>$v['class_id'] ])->query();
        }
        return $this;
    }

    /**
     * 更新班级表单行
     * @param int $class_id
     * @param array $var
     * @return $this
     */
    function updateClassByID($class_id, $var ){
        $this->update( $this->table, ['class_id'=>$class_id], $var, $this->_clsFile);
        return $this;
    }

    /**
     * 导入教师个人 班级名单 一个班级允许多位老师导入，一位老师一个班级只能修改
     * @param $ex_file
     * @param $post
     * @return $this
     */
    function imClassTeacherUserFromExcel( $ex_file ,$post ){
        $data = drFun::excelReadToArray( $ex_file);
        //$this->drExit(  $data  );
        $data=$data[0]['data'];
        $class_id= intval( $post['class_id']);
        if($class_id<=0 ) $this->throw_exception( "参数错误", 7703 );
        $cnt=  count( $data)-1;
        if( $cnt<=0 ) $this->throw_exception( "没数据", 7701 );
        $head= $data[1];
        if( $head['A']!='序号' ||   $head['B']!='学号' ||   $head['C']!='姓名') $this->throw_exception( "数据格式不对，第一行必须为：序号、学号、姓名", 7702 );
        $where = ['user_id'=>$this->getUserId(), 'class_id'=>$class_id ];
        $row = $this->createSql()->select( $this->tb_teacher,$where)->getRow();
        if($row  ){
            $this->update( $this->tb_teacher , ['ct_id'=>$row['ct_id'] ],['class_info'=>drFun::json_encode($data ),'cnt'=> $cnt ] );
        }else{
            $var =['class_info'=>drFun::json_encode($data ),'cnt'=>$cnt ,'user_id'=>$this->getUserId(), 'class_id'=>$class_id ,'ctime'=>time() ];
            $this->insert(  $this->tb_teacher , $var  );
        }
        return $this;
    }

    function getClassTeacherListByClassID( $class_id ,$file=['ct_id','cnt','user_id','ctime'] ){
        return $this->createSql()->select( $this->tb_teacher,['class_id'=>$class_id],[], $file )->getAll();
    }

    function orderByClassTeacher( &$list,$class_id,$opt=[] ){
        $where = ['user_id'=>$this->getUserId(), 'class_id'=>$class_id ];
        $row = $this->createSql()->select( $this->tb_teacher,$where)->getRow();
        if( !$row ) return false;
        $tem  = drFun::json_decode(  $row['class_info'] );
        unset( $row );

        $head = $tem[1]; $number=[];
        foreach ($tem as $k =>$v  ){
            if( $k<2 || !$v['B'] ) continue;
            $number[ $v['B']]= $v ;
        }
        unset( $tem );
        foreach($list as &$var ){
            if( isset( $number[$var['number']] )){
                $var['ct']=  $number[$var['number']];
                unset(  $number[$var['number']] );
            }
        }
        foreach($number as $k=>$v  ){
            $list[]=['number'=>$v['B'],'name'=> $v['C'],'ct'=>$v ];
        }
        $sort = function ($a,$b){
            $num =1;
            if( !isset($a['ct']['A']) &&  !isset($b['ct']['A']) ) return 0;
            if( isset($a['ct']['A']) &&  !isset($b['ct']['A']) ) return $num;
            if( !isset($a['ct']['A']) &&  isset($b['ct']['A']) ) return -$num;
            if( $a['ct']['A'] ==$b['ct']['A'] ) return 0;
            if( $a['ct']['A'] >$b['ct']['A'] ) return $num;
            return  -$num;
        };
        usort( $list ,$sort );
        return true ;
    }

    /**
     * @param $list
     * @param $class_id
     * @param term $term
     * @return $this
     */
    function whileStudentList( $list ,$class_id , $term){
        $while =  $term->getSchoolUserByClassID( $class_id)  ;
        $this->assign('while', $while );
        //print_r( $while );
        //$this->drExit( $list );
        return $this;

    }




}