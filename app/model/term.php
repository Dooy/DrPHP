<?php
/**
 * 处理学期
 *
 * 一些必要说明（2017-08-08）：
 * - 1.控制学期不使用term_id, 而实用学期的开始时间跟截止时间来控制
 * - 2.学期是用时间来划定的，那么要划入学期就得将时间做索引，比如 class.ctime
 * - 3.关于读书学期划分：读书列表不直接使用学期的时间来控制，而且手动归档
 * - 4.关于学生班级管理：由管理统一导入，导入后会在class表的type中置为5 ；学生单学期只能加入 type为5 而且在当前学期时间内的班级；当然背后能指定老师，老师在当前学期不能对班级进行修改，学期过后可以
 * - 5.一键归档：能归档本学期内容所在书单，归档由class中 type -5
 * - 6.一旦学期定下之后，请勿乱更改学期时间
 * - 7.建立了一个 user_school_term 的表，这个记录每个用户 本学期是有有进入系统，以便查看本学期用户，统计等工作：
 * - 8.学期是由单校独立建立呢 还是系统统一建立？
 *
 * 2017-08-09 改动
 * - 1.学期还是由系统规定 并且有个规定的key 比如说 2017-2018第一学期 基本在2017下班年 使用key 为201702，并规定开始时间给截止时间
 * - 1.1 考虑一：如果不是由系统统一来规定，可能很多学校不会主动去建立学期
 * - 1.2 考虑二：对于开发行系统有帮助，学校可以随意取值，不至于得预先建立学校
 * - 2.如果学校自己不建立学期就使用默认的学期
 * - 3.学校自己建立学期必须是在 系统规定的key内，并且一次key只能建立一个学期 同事只能调整时间等相关的参数 并存相关的key；
 * - 4.user_school_term 改为 user_term_201702 201702为当前时间的key ，同样是存储本学期 用户信息，以后每个学期都会建立一张这样的表以表示 本学期的学生
 * - 5.user_school_[key] 表内储的信息为 school,user_id,ctime,class,class_id,number,name
 *
 * Created by zahei.com
 * Date: 2017/8/7
 * Time: 20:26
 */

namespace model;


use model\user\one;

class term extends model
{
    private $tb_term= 'school_term';
    private $tb_school_user ='';
    private $tb_school  ='book_school';
    private $school_id = 0;
    private static  $school_term=[];
    private static $term_now = [];
    private  $file=['term','book_limit','start_time','end_time','s_start_time','s_end_time','school_id','ctime','user_id'
        ,'term_key','end', 'book_limit_min','is_school_user','manfen','manfen_tpl','dafen','is_repeat','is_bu'];
    private $file_school_user = ['school_id','number','name','class','class_id','grade','teacher','user_id','ctime','create_user_id','mj_class','block_id'];

    function __construct( $school_id=0 )
    {
        $this->school_id= $school_id;
    }

    function setSchoolID( $school_id ){
        $this->school_id= $school_id;
    }

    /**
     * 获取一个学校ID
     * @return int
     * @throws drException
     */
    function getSchoolID(){
        if( $this->school_id<=0) $this->throw_exception( "请先选择一所学校",7223);
        return $this->school_id;
    }

    /**
     * 为学校添加学期
     * @param int $school_id
     * @param array $var
     * @return int
     * @throws drException
     */
    function insertTerm( $school_id,  $var ){
        if( $school_id<=0 ) $this->throw_exception( "请先选择一所学校",7202);
        $this->checkInputTerm( $var , true );
        $var['ctime']= time(); $var['school_id']= $school_id ;
        $lastId = $this->createSql()->insert( $this->tb_term, $var )->query()->lastID() ;
        return $lastId ;
    }

    function getTableSchoolUser(){
        if( !$this->tb_school_user ) $this->tb_school_user =   'school_term_user_'. $this->getNow();
        return $this->tb_school_user;
    }

    /**
     * 通过学号从白名单中获取班级名称
     * @param $list
     * @return $this
     */
    function getUserClassFromSchoolUserByNumber( &$list ){
        $number =[];
        drFun::searchFromArray($list,['number'] ,$number);
        if( ! $number ) return $this ;
        $tall= $this->createSql()->select( $this->getTableSchoolUser(), ['school_id'=> $this->getLogin()->getSchoolID(), 'number'=> array_keys($number) ] ,[],['number','class','teacher'])->getAllByKey( 'number');
        //$this->drExit( $tall  );
        foreach ( $list as &$v) if( isset($tall[ $v['number'] ] )) $v['number_class']= $tall[ $v['number'] ];
        return $this;
    }

    function setTableSchoolUser( $term_key ){
        $this->tb_school_user =   'school_term_user_'. $term_key;
        return $this;
    }

    function setTableSchoolUserByBlockID( $block_id ){
        $this->setTableSchoolUser('1001');
        return $this;
    }


    /**
     * 检查term的输入
     * @param array $var
     * @param bool $is_insert
     * @return $this
     * @throws drException
     */
    function checkInputTerm( &$var, $is_insert=true  ){
        $file= $this->file;
        if( (isset($var['term']  ) || $is_insert ) && trim($var['term'])==''  ) $this->throw_exception( "名称不允许为空",7200);
        if( (isset($var['book_limit']) || $is_insert) && intval($var['book_limit'])<0  ) $this->throw_exception( "选书限制必须大于等于0",7201);

        if( isset($var['start_time'])  || $is_insert ) $var['start_time']= drFun::str2time( $var['start_time'] ,'请检查学期开始时间');
        if( isset($var['end_time'])  || $is_insert ) $var['end_time']= drFun::str2time( $var['end_time'] ,'请检查学期截止时间' );
        if( isset($var['s_start_time'])  || $is_insert ) $var['s_start_time']= drFun::str2time( $var['s_start_time'] ,'请检查选课开始时间'  );
        if( isset($var['s_end_time'])  || $is_insert ) $var['s_end_time']= drFun::str2time( $var['s_end_time'],'请检查选课截止时间' );
        if( isset( $var['term_key'])  || $is_insert )  $this->getConfig( $var['term_key'] );

        if(  isset($var['start_time']) &&  isset($var['end_time'])  &&  isset($var['s_start_time'])  &&   isset($var['s_end_time'])  ) {
            if ($var['start_time'] >= $var['end_time']) $this->throw_exception("选课的开始时间必须小于截止时间", 7203);
            if ($var['s_start_time'] >= $var['s_end_time']) $this->throw_exception("选课的开始时间必须小于截止时间", 7204);
            if ($var['s_start_time'] < $var['start_time'] || $var['s_end_time'] > $var['end_time']) $this->throw_exception("选课必须在学期时间内", 7205);
        }
        $var['end']= drFun::str2time( $var['end'],'请检查答题截止时间' );
        if( isset($var['dafen'])) $var['dafen'] = drFun::json_encode( $var['dafen'] );
        //$this->drExit(  $var );
        foreach( $var as $k=>$v ){
            if( !in_array($k, $file)) unset( $var[$k] );
        }
        return $this;
    }

    /**
     * 获取TermList
     * @param array $where
     * @param array $order
     * @return array
     */
    function getTermListWithPage( $where ,$order=['term_id'=>'desc'] ){
        $re = $this->createSql()->selectWithPage( $this->tb_term, $where,30,[], $order);
        return $re ;
    }



    /**
     * 获取单行Term通过 Term_id
     * @param int $term_id
     * @return array
     * @throws drException
     */
    function getTermByID( $term_id ){
        $row = $this->createSql()->select( $this->tb_term,['term_id'=> $term_id])->getRow();
        if( !$row ) $this->throw_exception( "此ID不存在",7206);
        //$this->drExit( $row );
        $this->dafenConfig( $row );
        return $row;
    }

    function dafenConfig( &$row ){
        $config =    $this->getConfig( $row['term_key']);

        if( $row['dafen']) $row['dafen'] = is_array( $row['dafen'])? $row['dafen'] : drFun::json_decode(  $row['dafen']);
        else $row['dafen'] = $config['dafen'];
    }

    /**
     * 编辑term
     * @param int $term_id
     * @param array $var
     * @return $this
     * @throws drException
     */
    function editTerm( $term_id, $var ){
        if( $term_id<=0 ) $this->throw_exception( "参数非法",7207);
        $this->checkInputTerm( $var , true );
        $this->createSql()->update($this->tb_term, $var,['term_id'=> $term_id ])->query();
        return $this;
    }

    /**
     * 编辑term 如果没有就直接增加
     * @param $school_id
     * @param $var
     * @return $this
     * @throws drException
     */
    function editTermBySchool( $school_id, $var){
        $term_key = $this->getNow();
        $term_config = $this->getConfigForSchool( $school_id, $term_key );
        //$this->drExit($term_config );
        if( $term_config['term_id'] >0 ){
           // $this->editTerm($term_config['term_id'],$var );
            $this->update( $this->tb_term, ['term_id'=>$term_config['term_id'] ],$var, $this->file );
        }else{
           drFun::arrExtend( $term_config, $var );
           $this->insertTerm( $school_id, $term_config );
        }
        return $this;
    }

    /**
     * 检查学期是否有交叉 返回有交叉的错误信息
     * @param array $list
     * @return array
     */
    function checkTermTime( &$list ){
        $old=[];
        $error=[];
        $time= time();
        $old_key ='';
        foreach( $list as $k=>$v ){
            if( $old ){
                if( $v['end_time']> $old['start_time'] ) {
                    $list[$k]['end_time_error']=1;
                    $list[ $old_key ]['start_time_error']=1;
                    $error[] =  $list[ $old_key ]['term'] .'与' . $list[$k]['term'].'交叉';
                }
            }
            //if( $time> $v['start_time'] &&  $time<$v['end_time'] )  $list[$k]['is_now']=1;
            $old= $v ;
            $old_key= $k;
        }
        return $error;
    }


    /**
     * 获取系统配置
     * @param string $term_key
     * @return array
     * @throws drException
     */

    function getConfig( $term_key='all' ){
        //2018/2/16 0:0:0~2018/8/15 23:59:59  2018/3/1 0:0:0~ 2018/4/30 23:59:59
        //$re['201801']=['term_key'=>201801,'start_time'=> '1518710400','end_time'=>'1534348799','book_limit'=>3, 's_start_time'=> '1519833600','s_end_time'=>'1525103999','term'=>'2017至2018春季学期'];
        //2017/6/1 0:0:0 ~2018/2/15 23:59:59 2017/8/1 0:0:0~2017/10/31 23:59:59
        //1508083199  1509465599 10.31 23:59
        if($term_key=='allSchool' && $this->getLogin()->isSchoolAll() ){
            $re['1001']= ['term_key'=>1001,'start_time'=> '1518710400','end_time'=>'1534348799','book_limit'=>3,'book_limit_min'=>1
                , 's_start_time'=> '1519833600','s_end_time'=>'1525103999','term'=>'校学期','end'=>1534348799,'dafen'=>[0=>2,3=>2,4=>2,5=>4,6=>0 ]
                ,'is_bu'=>1,'is_repeat'=>1
            ];
        }
        $re['201803']= ['term_key'=>201803,'start_time'=> '1534262400','end_time'=>'1550246340','book_limit'=>3,'book_limit_min'=>1
        , 's_start_time'=> '1535731200','s_end_time'=>'1539619140','term'=>'2018至2019秋季学期','end'=>1548950340,'dafen'=>[0=>2,3=>2,4=>2,5=>4,6=>0 ]
            ,'is_bu'=>1,'is_repeat'=>1
        ];

        $re['201802']= ['term_key'=>201802,'start_time'=> '1530374400','end_time'=>'1537027140','book_limit'=>3,'book_limit_min'=>1
            , 's_start_time'=> '1531065540','s_end_time'=>'1535817540','term'=>'2018暑假','end'=>1537027140,'dafen'=>[0=>2,3=>2,4=>2,5=>4,6=>0 ]
            ,'is_bu'=>1,'is_repeat'=>1
        ];

        $re['201801']= ['term_key'=>201801,'start_time'=> '1518710400','end_time'=>'1534348799','book_limit'=>3,'book_limit_min'=>1
            , 's_start_time'=> '1519833600','s_end_time'=>'1525103999','term'=>'2017至2018春季学期','end'=>1534348799,'dafen'=>[0=>2,3=>2,4=>2,5=>4,6=>0 ]
            ,'is_bu'=>1,'is_repeat'=>1
        ];

        $re['201800']= ['term_key'=>201800,'start_time'=> '1515297874','end_time'=>'1519833599','book_limit'=>3,'book_limit_min'=>1
            , 's_start_time'=> '1516377600','s_end_time'=>'1518278399','term'=>'2017至2018寒假','end'=>1519833599,'dafen'=>[0=>2,3=>2,4=>2,5=>4,6=>0 ]
            ,'is_bu'=>1,'is_repeat'=>1
        ];
        $re['201702']=['term_key'=>201702,'start_time'=> '1496246400','end_time'=>'1518710399','book_limit'=>3,'book_limit_min'=>1
            , 's_start_time'=> '1501516800','s_end_time'=>'1508083199','term'=>'2017至2018秋季学期','end'=>1516031999,'dafen'=>[0=>2,3=>2,4=>2,5=>4,6=>0 ]
            ,'is_bu'=>0,'is_repeat'=>1
        ];


        foreach ( $re as $k=>$v ){
            $re[$k]['manfen']=3;
            $re[$k]['manfen_tpl']=1;
        }
        if( $term_key=='all' || $term_key=='allSchool'  )    return $re;

        if(isset( $re[$term_key])) return $re[$term_key];

        $this->throw_exception( $term_key.' 不存在！',7209);
    }


    /**
     * 获取当前 201702
     * @param int $school_id
     * @return string
     */
    function getNow($school_id=0){
        //$term_key_sy = '201702';
        $term_key_sy = '201801';
        if( $school_id <0 ) return $term_key_sy ;

        if( $school_id==0  ) $school_id=  $this->school_id;
        if( $school_id>0 ){
            if( isset( self::$term_now[ $school_id] )) return self::$term_now[ $school_id];

            $term_key = $this->createSql()->select($this->tb_school,['id'=>$school_id ],[],['now_term_key'])->getOne();
            if( $term_key && $term_key>0 ){
                self::$term_now[ $school_id] = $term_key;
                return $term_key;
            }
        }
        return $term_key_sy ;
    }

    function saveTermNow( $term_key ){
        $this->update( $this->tb_school,['id'=>$this->getSchoolID()],['now_term_key'=>$term_key ]);
        return $this;
    }

    /**
     * 获取当前操作表格
     * @param string $term_key
     * @return string
     */
    public function getUserTermTable( $term_key='' ){
        $term_key= $term_key==''?  $this->getNow(): $term_key;
        $this->getConfig( $term_key );
        $tb= 'user_term_'.$term_key;
        return $tb;
    }

    /**
     * 获取学期用户
     * @param $uid
     * @return array
     */
    public function getUserTermByUid( $uid ){
        return 1;
        return  $this->createSql()->select( $this->getUserTermTable(),['user_id'=>$uid] )->getRow();
    }

    /**
     * 增加user_term表 资料
     * @param $user_id
     * @param array $opt
     * @return $this
     */
    public function addUserTerm( $user_id,$opt=[]){
        if( $user_id<=0) $this->throw_exception("uid 参数错误",7210 );

        $opt['user_id']= $user_id;
        $opt['ctime']= time();
        $this->insert( $this->getUserTermTable() ,$opt,['user_id','ctime', 'school','class','class_id','number','name','teacher_uid','ts'] );
        return $this ;
    }

    /**
     * 修改user_term表 资料
     * @param $user_id
     * @param array $opt
     * @return $this
     */
    public function editUserTerm( $user_id, $opt=[]){
        if( $user_id<=0) $this->throw_exception("uid 参数错误",7211 );
        $this->update( $this->getUserTermTable() , ['user_id'=> $user_id], $opt,[ 'ctime', 'school','class','class_id','number','name','teacher_uid','ts']  );
        return $this;
    }

    /**
     * 默认 获取当前学期的配置
     * @param int $school_id
     * @param string $term_key
     * @return array
     */
    function getConfigForUser($school_id ,$term_key='now'){
        if( $term_key=='now') $term_key = $this->getNow();
        return $this->getConfigForSchool($school_id, $term_key );
    }


    /**
     * 系统+学校自建学期
     * @param int $school_id
     * @param string $term_key
     * @return array
     */
    public function getConfigForSchool( $school_id, $term_key='all' ){
        if( isset( self::$school_term[$school_id ] )) $termList= self::$school_term[$school_id ];
        else $termList= self::$school_term[$school_id ]  = $this->createSql()->select( $this->tb_term,['school_id'=> $school_id] )->getAllByKey( 'term_key' );

        $config = $this->getConfig( );
        foreach ( $config as $k=>$v ){
            if( isset( $termList[$k])){
                $config[$k] = $termList[$k] ;
            }
        }
        if( $term_key=='all') return $config;
        if( $term_key=='now') $term_key= $this->getNow($school_id);
        if( isset($config[ $term_key ] ) ){
            $this->dafenConfig(  $config[ $term_key ] );
            return $config[ $term_key ];
        }
        $this->throw_exception( $term_key.' 不存在！',7212);
    }

    /**
     * 获取本学期由学校导入的学校列表，格式是suggest的
     * @param $school_id
     * @param string $term_key
     * @return array
     */
    function getClassListForSuggest( $school_id,$term_key='now' ){
        if( $term_key=='now' ) $term_key = $this->getNow() ;

        /* 按学期 得到班级
        //$tconf = $this->getConfigForSchool( $school_id, $term_key );
        //$where = ['school_id'=>$school_id,'between'=>['ctime'=> [ $tconf['start_time'], $tconf['end_time']] ],'type'=>5  ];
        */
        $where = ['school_id'=>$school_id,'term_key'=>$term_key ,'type'=>5  ];
        $cls = new cls();
        $tall = $cls->getList( $where );
        $rz=[];
        foreach( $tall as $v ){
            $rz[]=['value'=>$v['class'],'data'=>['class_id'=>$v['class_id'],'class'=>$v['class'] ] ];
        }
        return $rz ;
    }

    /**
     * 获取学期用户列表
     * @param array $where
     * @param string $term_key
     * @param array $opt
     * @return array
     */
    function getUserTermListWithPage( $where, $term_key='' ,$opt=[] ){
        if( $term_key=='' ) $term_key = $this->getNow() ;
        $table = $this->getUserTermTable( $term_key );
        //$where=['school'=>$school ];
        return $this->createSql()->selectWithPage( $table,$where );
    }

    private  function isBlockTerm(){
        $table= $this->getTableSchoolUser();
        $term_key = strtr($table,['school_term_user_'=>'']);
        return $this->getLogin()->createBlock()->isBlockTerm( $term_key );
    }

    /**
     * 单行更新白名单
     * @param $school_id
     * @param $create_uid
     * @param $var
     * @return $this
     * @throws drException
     */
    function updateAndInsertSchoolUser( $school_id,$create_uid,&$var ){
        if($school_id<=0 || $create_uid<=0 || $var['number']=='' ) $this->throw_exception( "白名单插入参数错误",7216);

        //if( ) $this->drExit('gao='.$block_id );

        //$this->drExit( $this->getTableSchoolUser() );
        if( $block_id= $this->isBlockTerm()  ){ #当如果是block
            $row = $this->createSql()->select( $this->getTableSchoolUser(),['number'=> $var['number'],'block_id'=>$block_id ])->getRow();
            $var['block_id']= $block_id;
        }else{
            $row = $this->createSql()->select( $this->getTableSchoolUser(),['number'=> $var['number'],'school_id'=>$school_id ])->getRow();
        }
        if( $row['user_id']>0 ){
            $var['up_in']='no';
            return $this ;
        }
        if(  $row ){
            $this->update($this->getTableSchoolUser(),['id'=>$row['id']], $var, $this->file_school_user );
            $var['up_in']='update';
           return $this;
        }
        $var['ctime']= time();
        $var['school_id']= $school_id;
        $var['create_user_id']= $create_uid;
        $this->insert( $this->getTableSchoolUser(), $var, $this->file_school_user );
        $var['up_in']='insert';
        return $this;
    }

    /**
     * 批量导入白名单
     * @param string $excel_file 文件路径
     * @param int $create_uid 创建人
     * @param int $school_id 学校ID
     * @param array $re
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function imSchoolUserFromExcel( $excel_file,$create_uid,$school_id, &$re,$opt=[] ){
        $data = drFun::excelReadToArray( $excel_file  ) ;//drFun::excelReadToArray( $excel_file );

        #校白名单不分学期直接走 1001
        $term_key = isset($opt['term_key'])? $opt['term_key'] : $this->getNow();

        $this->setTableSchoolUser( $term_key );

        $key_var = ['A'=>'number','B'=>'name','C'=>'grade','D'=>'class','E'=>'teacher','F'=>'mj_class'];
        $cls = new cls( $create_uid );
        $cls_exit = $cls->getTermNowClass( $school_id ,$term_key);
        //$this->drExit( $cls_exit);
        $total  = 0;
        $class_arr = [];
        $block_id= $this->getLogin()->createBlock()->isBlockTerm( $term_key );
        foreach ( $data[0]['data'] as $k=>$v ){
            if($k<=1) continue;
            $var = drFun::arrayKeyReset( $v , $key_var );
            if( $var['class'] &&  !isset( $cls_exit[ $var['class'] ] ) ) {
                $class_var = ['class' =>  $var['class'] , 'user_id' => $create_uid, 'ctime' => time(), 'type' => 5, 'grade' => $cls->getGidByGrade( $var['grade']), 'school_id' => $school_id ,'term_key'=> $term_key ];
                if( $block_id ) $class_var['block_id']= $block_id;
                $class_var['class_id'] = $cls->classAdd($class_var);
                $cls_exit[ $var['class'] ]=  $class_var;
            }
            $var['class_id']= intval(  $cls_exit[ $var['class'] ]['class_id'] );
            $class_arr[ $var['class_id']] =   $var['class_id'];
            if( $var['number']=='' ){ continue; }
            if( $block_id ) $var['block_id']= $block_id;
            $this->updateAndInsertSchoolUser( $school_id,$create_uid, $var );
            $total++;
            $re[ $var['up_in']]++;
        }
        $re['total']= $total ;

        #$this->drExit( $class_arr );

        #更新班级白名单人数
        foreach ($class_arr as $class_id=>$v )  $this->updateClassSchoolUserCnt( $class_id );

        return $this;
    }

    /**
     * 修改单个学生白名单班级
     * @param $school_user_id
     * @param $new_class_id
     * @param $teacher_name
     * @param $school_id
     * @return $this
     * @throws drException
     */
    function modifySchoolUserClass( $school_user_id, $new_class_id, $teacher_name ,$school_id ){
        if( $school_user_id<=0 || $new_class_id<=0 || !$teacher_name) $this->throw_exception( "参数错误！不允许为空");
        $row_school_user  = $this->createSql()->select( $this->getTableSchoolUser(),['id'=> $school_user_id])->getRow();
        if( $row_school_user['school_id']!= $school_id ) $this->throw_exception( "跨学校了！",7222);
        $cl_cls = new cls();
        $row_class = $cl_cls->getClassById( $new_class_id );
        if( $row_class['school_id']!= $school_id ) $this->throw_exception( "班级跨学校了！",7222);

        #修改白名单
        $this->update(  $this->getTableSchoolUser(),['id'=> $school_user_id],['class_id'=>$new_class_id,'teacher'=>$teacher_name,'class'=>$row_class['class']  ]);

        if( $row_school_user['user_id']>0 ) {
            #修改加入班级
            $where = ['user_id' => $row_school_user['user_id'], 'class_id' => $row_school_user['class_id']];
            $this->update('class_student', $where, ['class_id' => $new_class_id]);
            #修改选课
            $this->update('book_user', $where, ['class_id' => $new_class_id]);
            #修改加入2个班级学生数
            $cl_cls->updateClassStu( $new_class_id )->updateClassStu(  $row_school_user['class_id'] );
        }

        #更新2个白名单人数
        $this->updateClassSchoolUserCnt($new_class_id  )->updateClassSchoolUserCnt( $row_school_user['class_id']    );
        return $this;


    }

    /**
     * 获取学校白名单用户列表
     * @param int $school_id
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getSchoolUserListWithPage( $school_id, $opt=[]){
        //$where= isset( $opt['where'] )? $opt['where'] :[];
        $where =['school_id'=>$school_id ];
        if( isset( $opt['where'] ) ){
            foreach( $opt['where'] as $k=>$v ) $where[$k]= $v ;
        }
        $re = $this->createSql()->selectWithPage( $this->getTableSchoolUser(), $where );
        return $re;
    }

    /**
     * 是否含有专家班级 mj_class
     * @param $list
     * @return bool
     */
    function isHaveMjClass( $list ){
        if( !is_array( $list )) return false ;
        if( isset($list['mj_class']) ) return ''!= $list['mj_class'];
        foreach($list as $v )     if( $v['mj_class']!='') return true;
        return false ;
    }

    /**
     * 删除白名单
     * @param int $id
     * @return $this
     * @throws drException
     */
    function delSchoolUserById( $id ,$opt=[] ){
        $where = ['id'=>$id];
        $row = $this->createSql()->select( $this->getTableSchoolUser(), $where)->getRow();

        if( !$row ) $this->throw_exception("不存在或已删除！",7220);
        if( $row['school_id'] != $opt['school_id'] )   $this->throw_exception("不是本学校无法删除！",7221);

        if( $row['user_id']>0 ) $this->throw_exception("该账号已经绑定过，无法删除！",7224);
        $this->createSql()->delete( $this->getTableSchoolUser(), $where  )->query();
        if( $row['class_id']>0  ) $this->updateClassSchoolUserCnt( $row['class_id'] );
        return $this;
    }

    /**
     * 更新一个班级的白名单人数
     * @param $class_id
     * @return $this
     * @throws drException
     */
    function updateClassSchoolUserCnt( $class_id ){
        $cnt = $this->createSql()->getCount( $this->getTableSchoolUser(),['class_id'=>$class_id])->getOne();
        $cl_class = new cls();
        $cl_class->updateClassByID( $class_id,['school_user_cnt'=> $cnt] );
        return $this;
    }

    /**
     * 绑定学校用户（绑定白名单）
     * -1.用学号与schol_id检查是否存在（shcool_id 换为block_id）
     * -2.如果没有直接out、学号大小写、名字不对都直接退出
     * -3.当uid=0
     * -3a.检查自己之前有绑定过否？（block_id表 是允许一个表有能绑定多个uid）
     * -3b.如果跟之前的学号的id是同一个则返回
     * -3c.如果不是，先清除自己绑定过的（看看要不要block_id）,再重新绑定
     * @param string $number
     * @param string $name
     * @param int $school_id
     * @param int $user_id
     * @param array $opt
     * @return array
     * @throws drException
     */
    function bindSchoolUser( $number,$name,$school_id ,$user_id,$opt=[] ){
        if( isset( $opt['block_id']) ){
            $this->setTableSchoolUserByBlockID( $opt['block_id'] );
            $row = $this->createSql()->select( $this->getTableSchoolUser(),['number'=>$number,'block_id'=>intval($opt['block_id'])])->getRow();
        }else{
            $row = $this->createSql()->select( $this->getTableSchoolUser(),['number'=>$number,'school_id'=>$school_id])->getRow();
        }

        if( !$row   ) $this->throw_exception( "输入的学号不存在", 7217);
        if( $row['number']!=$number ) $this->throw_exception( "注意学号大小写", 7219);
        if( $row['name']!=$name ){
            $this->log( "debug:". $row['name']."\t".$name  );
            $this->throw_exception( "学号、姓名对应不上", 7217);
        }
        if( $row['user_id']==0){
            $where_chang = ['user_id'=> $user_id];

            #当时block模式得带上block_id 同一个表内多个uid 用block_id 与uid唯一
            if( isset( $opt['block_id'])  ) $where_chang['block_id']= intval( $opt['block_id'] ) ;

            $old  = $this->createSql()->select(  $this->getTableSchoolUser() , $where_chang)->getRow();
            if( $old['id']==$row['id'] ) return $row;

            if( $old && $user_id>0 ){ ##解绑
                $this->update( $this->getTableSchoolUser(),$where_chang ,['user_id'=> 0 ] );
            }
            $this->update( $this->getTableSchoolUser(),['id'=>$row['id']],['user_id'=> $user_id]);
        }elseif($user_id!=  $row['user_id'] ) {
            $cl_one = new one( $row['user_id']  );
            $user_auth= $cl_one->getUserOauth();
            foreach ($user_auth as $k => $v ){
                $str=$v[0]['openid'];break;
            }
            $this->throw_exception( "学号已经被 ".$str. " 绑定，请检查是不是您的另一账号？" ,7218 );
        }
        //$this->getLogin()->createClassCls()->join( $school_user['class_id'],['number'=>  $user['number'] ,'name'=> $user['name']  ] );
        //$cl_class = new cls( $user_id);
        return $row;
    }

    function getBindSchoolUser( $where ){
          return $this->createSql()->select( $this->getTableSchoolUser(),$where )->getAll();
    }


    /**
     * 获取单个白名单用户
     * @param string|array $number
     * @param int $school_id
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getSchoolUserByNumber($number,$school_id ,$opt=[] ){
        if( isset($opt['block_id']) ){
            $this->setTableSchoolUserByBlockID( $opt['block_id'] );

            //return $this->createSql()->select( $this->getTableSchoolUser(),['number'=>$number,'school_id'=>$school_id ,'block_id'=>$opt['block_id'] ])->getRow();

            return $this->createSql()->select( $this->getTableSchoolUser(),['number'=>$number  ,'block_id'=>$opt['block_id'] ])->getRow();
        }

        if( is_array($number) ){
            return    $this->createSql()->select( $this->getTableSchoolUser(),['number'=>$number,'school_id'=>$school_id])->getAllByKey('number');
        }
        return $this->createSql()->select( $this->getTableSchoolUser(),['number'=>$number,'school_id'=>$school_id])->getRow();
    }

    /**
     * 获取本学期多校的配置
     * @param $school_ids
     * @return array
     * @throws drException
     */
    function getSchoolTermBySchoolIDs( $school_ids  ){
        if( !$school_ids ) $this->throw_exception( "参数错误！" ,7219 );
        $now = $this->getNow();
        return $this->createSql()->select( $this->tb_term,['school_id'=>$school_ids,'term_key'=>$now ] )->getAllByKey('school_id');
    }

    /**
     * 白名单用户
     * @param $class_id
     * @return mixed
     * @throws drException
     */
    function getSchoolUserByClassID( $class_id ){
        return $this->createSql()->select( $this->getTableSchoolUser(),['class_id'=>$class_id])->getAll();
    }

    function modifySchoolUserByRow( $row ){
        $tall = $this->createSql()->select( $this->getTableSchoolUser() , ['school_id'=>$row['school_id'],'number'=>$row['number'] ] )->getAll();
        if( count($tall )>1 or $tall[0]['id'] !=$row['id']) $this->throw_exception('学号重复！', 7220 );
        //$this->update(  $this->getTableSchoolUser() , ['id'=> $row['id'] ], $row );
        $sql = $this->createSql()->update( $this->getTableSchoolUser(),$row, ['id'=> $row['id'] ] )->query() ;//(  $this->getTableSchoolUser() , ['id'=> $row['id'] ], $row );
        //$this->drExit( $sql );
        return $this;
    }

}