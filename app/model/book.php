<?php

namespace model;


use model\lib\cache;
use model\lib\mq;
use model\lib\sms;
use model\user\login;
use model\user\one;

/**
 * 读书 系统的 操作
 *
 * Date: 2017/6/22
 * Time: 20:01
 * @package model
 */
class book extends model
{
    private $school='';
    private $user_id = 0;
    private $tb_book='book';
    private $tb_book_user ='book_user';
    private $tb_topic= 'book_topic';
    private $tb_comment= 'book_comment';
    private $tb_school= 'book_school';
    private $tb_attr = 'book_topic_attr';
    private $tb_log= 'book_log';
    private $tb_isbn="book_isbn";
    private static $catch =[];
    private $book_school= [];
    private static $book_cache=[];
    private static $topic_cache=[];
    private $book_opt = null;
    private $need_tetail= true;

    private $file_book =  ['book'=>1,'book_info'=>1, 'book_writer'=>1,'book_img'=>1 ,'school'=>1,'word_comment'=>1,'word_topic'=>1
        ,'user_topic'=>1,'user_comment'=>1,'book_pdf'=>1,'book_plan'=>'读书计划','book_word_cnt'=>'字数','book_page'=>'页数'
        ,'tag_3_config'=>'朗读'   ,'tag_4_config'=>'其中'  ,'tag_5_config'=>'期末','book_isbn'=>'isbn','ctime'=>1,'user_id'=>1,'user_limit'=>1  ,'tag_6_config'=>'摘抄'
        ,'term_key'=>'指定学期'
    ];

    /**
     * 设置学校 这里会去检查数据库中是否存在这个学校
     * @param string $school
     * @return $this
     */
    function setSchool( $school  ){
        //$this->school= $school;
        if( !$school  ) $school='好策';
        $book_school= $this->getBookSchoolFromDB( $school );
        if( !$book_school ) $school='好策';
        $book_school = $this->getBookSchoolFromDB( $school );
        $this->book_school= $book_school;
        $this->school= $school;
        return $this;
    }


    /**
     * 通过school_ename 去查找school 并设置学校
     * @param string $ename
     * @return $this
     */
    function setSchoolByEname( $ename ){
        if( !$ename) $this->throw_exception( "参数错误 ！",7041);
        $book_school = $this->createSql()->select('book_school',['school_ename'=> $ename ] )->getRow();
        if( !$book_school  )$this->throw_exception( "该学校未开通",7040);
        $this->school= $book_school['school'];
        $this->book_school= $book_school;
        return $this;
    }

    /**
     * 获取当前使用的学校
     * @return string
     */
    function getBookSchool(){
        return $this->book_school ;
    }

    /**
     * 通过school_id 获取学校
     * @param int $id book_school的唯一id
     * @return array
     */
    function getBookSchoolById( $id ){
        $row=  $this->createSql()->select($this->tb_school,['id'=> $id] )->getRow();
        if( !$row) $this->throw_exception("学校不存在",7041 );
        return $row;
    }

    /**
     * 获取一行学校信息通过学校名称
     * @param string $school
     * @return array
     */
    function getBookSchoolFromDB( $school ){
        if( isset( self::$catch[ $school] ) )   return  self::$catch[ $school];
        self::$catch[ $school]= $this->createSql()->select($this->tb_school,['school'=> $school] )->getRow();
        return  self::$catch[ $school];
    }

    function searchSchoolFromArray( $list,$key=['school_id']){
        $s_id=[];
        drFun::searchFromArray($list,$key,$s_id);
        if( ! $s_id ) return [];
        return $this->createSql()->select( $this->tb_school, ['id'=>$s_id])->getAllByKey( 'id');
    }

    /**
     * 设置user_id
     * @param int $user_id
     * @return $this
     */
    function setUserId( $user_id ){
        $this->user_id= $user_id;
        return $this;
    }

    /**
     * 获取当前操作用户信息
     * @return array
     */
    function getUser(){
        $this->getUserID();
        $cuserone = new one( $this->user_id );
        return $cuserone->getUser();
    }

    /**
     * 获取uid
     * @return int
     */
    function getUserID(){
        if( $this->user_id<=0 ) $this->throw_exception( "无效账号",7002);
        return  $this->user_id;
    }

    /**
     * 获取书单列表带分页，默认条件是本校的
     * @param array $where
     * @param int $every
     * @param array $order
     * @return array
     */
    function getBookListWithPage( $where=[],$every=30, $order=[]){
        //if( ! isset($where['school'])  ) $where['school']= $this->school ;
        //$this->drExit( $where );
        $re= $this->createSql()->selectWithPage( $this->tb_book,$where ,$every,[],$order );

        if( $_GET['debug']==3){
            print_r( $re );
            $this->drExit( $where );
        }

        $this->margeBookAdmin( $re['list']);
        return $re;
    }

    /**
     * 获取布置书单的老师列表
     * @param $where
     * @return mixed
     */
    function getBookTeacherList ( $where){
        $list = $this->createSql()->group( $this->tb_book,['user_id'],$where,['user_id','count(*) as cnt '] ,['cnt'=>'desc'] )->getAllByKey('user_id') ;
        return $list;
    }

    /**
     * 获取学院列表
     * @param $where
     * @return array
     */
    function getBookCollegeList( $where ){
        $list = $this->getBookTeacherList( $where);
        $re=[];
        if( ! $list ) return $re ;

        $college = $this->createSql()->select( 'user_attr',['user_id'=>array_keys( $list),'key'=>'coll' ])->getAllByKeyArr(['value']);
        //$this->drExit( $college  );
        foreach($college as $k=>$v  ){
            foreach ( $v as $k2=>$v2){
                $uid = $v2['user_id'];
                $re[$k]['user_id']=$uid;
                $re[$k]['uid'][] =$uid;
                $re[$k]['u_cnt']++;
                $re[$k]['cnt']+= $list[$uid]['cnt'];
                unset($list[$uid] );
            }
        }
        if( $list ){
            $key='匿名学院';
            $re[ $key ]=[  'u_cnt'=>0,'cnt'=>0 ];
            foreach( $list as $k=>$v ){
                $re[ $key]['user_id']=$v['user_id'];
                $re[ $key]['u_cnt']++ ;
                $re[ $key]['cnt']+= $v['cnt'];
                $re[ $key]['uid'][] = $v['user_id'];
            }
        }

        return $re ;
    }

    function margeBookAdmin( & $bookList ){
        if(! $bookList ) return $this;
        $book_id =[];
        drFun::searchFromArray( $bookList,['book_id'],$book_id);

        if(! $book_id ) return $this;
        $userAdmin= $this->createSql()->select( $this->tb_book_user,['book_id'=>$book_id,'type'=>1 ],[],['user_id','book_id'])->getAllByKeyArr(['book_id']);
        //$this->drExit($userAdmin );
        foreach( $bookList as $k=>$book ){
             $bookList[$k]['bookAdmin']=  isset( $userAdmin[ $book['book_id']]  )?   $userAdmin[ $book['book_id']]: [] ;
        }
        return $this;
    }

    /**
     * 获取学校
     * @return string
     */
    function getSchool(){
        return $this->school;
    }



    /**
     * 获取开通读书系统的学校列表
     * @param array $opt
     * @return array
     */
    function getBookSchoolListWithPage( $opt=[] ){
        $where = "1";
        if( isset( $opt['where'])){
            $where= $opt['where'];
        }
        return $this->createSql()->selectWithPage( $this->tb_school,$where,10,[],['id'=>'desc'] );
    }

    /**
     * 获取回复 带分页
     * @param $topic_id
     * @param array $opt
     * @return array
     */
    function getCommentListWithPage( $topic_id, $opt=[] ){
        $where = ['topic_id'=> $topic_id ] ;
        $every = 10;
        $order = ['ctime'=>'desc'];
        if( isset( $opt['every']) &&  $opt['every']>0 ) $every=  $opt['every'];
        if(isset( $opt['user'])) $where= ['user_id'=> $this->getUserID() ];
        if( isset($opt['order']) ) $order=$opt['order'];
        $re = $this->createSql()->selectWithPage( $this->tb_comment,$where ,$every ,[],$order );
        if( isset( $opt['marge'])){
            $this->createSql()->merge( $this->tb_topic,'topic_id',$re['list'] ,['topic_id','topic']);
        }
        return $re ;
    }

    /**
     * 获取图书管理员类型   $type = [0=>['n'=>'一般用户'] , 1=>['n'=>'指导教师'], 3=>['n'=>'创建者']];
     * @param string $type_id
     * @return array|mixed
     */
    function getTypeBookUser( $type_id='all'){
        //0为一般用户，1为管理员,3为创建者
        $type = [0=>['n'=>'一般用户'] , 1=>['n'=>'指导教师'], 3=>['n'=>'指导教师（创）']];
        if(  $type_id =='all') return $type;
        if( !isset($type[$type_id])) $this->throw_exception( "用户类型错误！",7023);
        return $type[$type_id];
    }

    /**
     * 学校显示类型
     * @param string $type_id
     * @return array|mixed
     */
    function getTypeSchoolShow( $type_id='all' ){
        $type = [1=>['n'=>'开通'],-1=>['n'=>'待跟进']];
        if(  $type_id =='all') return $type;
        if( !isset($type[$type_id])) $this->throw_exception( "学校显示类型错误！",7080);
        return $type[$type_id];
    }

    /**
     * 获取学生首页模板
     * @param string $type_id
     * @return array|mixed
     */
    function getTypeSchoolTpl( $type_id='all'){
        $type=[ 0=>['n'=>'默认'],1=>['n'=>'按老师分目录'] ,2=>['n'=>'按学院分目录'] ];
        if(  $type_id =='all') return $type;
        if( !isset($type[$type_id])) $this->throw_exception( "学校显示类型错误！",7091);
        return $type[$type_id];
    }

    /**
     * 获取主题列表
     * - opt.order 排序
     * - opt.tag_id 类别
     * - opt.jinghua 精华
     * @param int $book_id
     * @param array $opt
     * @return array
     */
    function getTopicListWithPage( $book_id ,$opt=[] ){
        $order = ['comment_time'=>'desc'];
        $where= ['book_id'=> $book_id,'tag_id'=>0 ];
        if(isset( $opt['order'])) $order= $opt['order'];
        $every=20;
        if(isset($opt['every']) && $opt['every']>0 )  $every=$opt['every'];

        if(isset( $opt['tag_id'])){
            $this->getTagId( $opt['tag_id'] );
            $where['tag_id'] = $opt['tag_id'];
        }
        if( isset($opt['jinghua']) && $opt['jinghua']==1 ){
            $topic_ids= $this->createSql()->select( $this->tb_attr, ['book_id'=> $book_id,'type'=>32 ],[],['topic_id'])->getCol();
            if( !$topic_ids ) $this->throw_exception( "本书刊暂无精华帖子",7019);
            $where= ['topic_id'=> $topic_ids ];
        }
        if( isset($opt['user'])){
            $where=['user_id'=>$this->getUserID() ];
        }
        $re = $this->createSql()->selectWithPage( $this->tb_topic, $where ,$every ,[],$order  );
        if(  $where['tag_id'] ==3    ){
            $this->margeTopicTagMyself( $re['list'], $book_id, $where['tag_id'] )->tag3Display( $re['list'] );
        }elseif(  $where['tag_id'] ==4 ||   $where['tag_id'] ==5  ){
            $this->margeTopicTagMyself( $re['list'], $book_id, $where['tag_id'] );
        }

        if( $re['list'] && !isset($opt['user']) ) $this->margeTopicAttr( $re['list'] )->topicTop( $re['list'] , $book_id ,$order ,[ 'tag_id'=> $where['tag_id'] ]);
        return $re ;
    }

    /**
     * 朗读 展示内容放在 字段 yin上
     * @param array $list
     * @return $this
     */
    function tag3Display(& $list ){
        if( !$list || !is_array($list )) return $this;
        if( isset($list['topic_info'] )){
            $yin=drFun::json_decode( $list['topic_info'],true);
            if(isset($yin['file'] )) $yin['file']= '/'.trim(  $yin['file'],'/');
            $list['yin']= $yin;
            return $this;
        }
        foreach( $list as $k=> $v ){
            $yin= drFun::json_decode( $v['topic_info'],true);
            if(isset($yin['file'] )) $yin['file']= '/'.trim(  $yin['file'],'/');
            $list[$k]['yin']= $yin;
        }
        return $this;
    }

    /**
     * 在第一页将自己的朗读放在最上面
     * @param array $list
     * @param int $book_id
     * @param int $tag_id
     * @return $this
     */
    function margeTopicTagMyself( & $list , $book_id, $tag_id ){
        if( intval($_GET['pageno'])>1 ) return $this;

        $mylist = $this->createSql()->select( $this->tb_topic,['user_id'=>$this->getUserID(),'book_id'=>$book_id,'tag_id'=>$tag_id] )->getAllByKey( 'topic_id');
        if( !$mylist) return $this;
        foreach( $list as $k=>$v  ){
            $key = $v['topic_id'];
            if( isset($mylist[$key]) ) unset( $list[$k] );
        }
        foreach ( $mylist as $v ){
            array_unshift($list, $v );
        }
        return $this;
    }



    /**
     * 处理置顶主题
     * @param array $topicList 已经获取的主题列表
     * @param int $book_id
     * @param array $order
     * @return $this
     */
    function topicTop( &$topicList , $book_id ,$order,$opt=[]){
        foreach ($topicList as $k=>$v ){
            if(isset( $v['attr'][31])) unset( $topicList[$k] );
        }
        if(isset($_GET['pageno']) and  $_GET['pageno']>1 ) return $this;

        $topic_ids= $this->createSql()->select( $this->tb_attr, ['book_id'=> $book_id,'type'=>31 ],[],['topic_id'])->getCol();
        if( !$topic_ids) return $this;
        $where= ['topic_id'=> $topic_ids ];

        if( isset($opt['tag_id'])) $where['tag_id']= $opt['tag_id'];

        $tarr =  $this->createSql()->select(  $this->tb_topic,$where,[],[],$order )->getAll();
        //$this->drExit( $tarr );
        if( $tarr ){
            $this->margeTopicAttr( $tarr );
            for($i=count( $tarr)-1; $i>=0; $i-- ){
               array_unshift($topicList, $tarr[$i] );
            }
        }
        return $this;
    }

    /**
     * 检查是否已经加入关注书单
     * @param int $book_id
     * @param bool $is_auto 是否自动加入
     * @return array
     */
    function checkJoinBook( $book_id ,$is_auto=false ){
        $bookJoin = $this->getJoinBook( $book_id );
        if( ! $bookJoin ){
            if(!$is_auto) $this->throw_exception( "请先加入这本书" ,7009);
            else{
                $this->joinBook( $book_id );
            }
        }
        return $bookJoin;
    }

    /**
     * 获取图书列表
     * @return array
     */
    function getBookList( $where=[] ,$opt=[] ){
        if( !$where ) $where = ['school'=>$this->school];
        $limit = isset($opt['limit'])? $opt['limit']: [0,30];
        $file=  isset($opt['file'])? $opt['file']: [ ];
        return $this->createSql()->select( $this->tb_book,$where, $limit ,$file )->getAll();
    }

    /**
     * 获取按学校为单位的一些热门、最新等条件的主题类别
     * @param string $school
     * @param array $limit
     * @param array $order
     * @return array
     */
    function getTopicListBySchool( $school="", $limit=[0,10], $order=['topic_id'=>'desc']){
        return $this->createSql()->select( $this->tb_topic,['book_id'=>$this->getBooksIdBySchool($school) ],$limit ,[],$order)->getAll();
    }

    /**
     * 获取按学校为单位的一些热门、最新等条件的评论回复类别
     * @param string $school
     * @param array $limit
     * @param array $order
     * @return array
     */
    function getCommentListBySchool(   $school="", $limit=[0,10], $order=['comment_id'=>'desc'] ){
        $comment =  $this->createSql()->select( $this->tb_comment,['book_id'=>$this->getBooksIdBySchool($school) ],$limit ,[],$order)->getAll();
        if($comment){
            $this->createSql()->merge( $this->tb_topic,'topic_id', $comment,['book','book_id']);
        }
        return $comment;
    }

    /**
     * 按学校获得本校的书单ID
     * @param string $school
     * @param int|array $type
     * @return array
     */
    function getBooksIdBySchool( $school ,$type=0 ,$opt=[] ){
        $school = $school? $school: $this->school;
        if( isset( $opt['term']) ) {
            $booksId = $this->createSql()->select($this->tb_book, ['school' => $school, 'type' => $type ,'term_key'=> $opt['term'] ], [], ['book_id'])->getCol();
        }else{
            if (self::$catch['books_id'][$school]) return self::$catch['books_id'][$school];
            $booksId = $this->createSql()->select($this->tb_book, ['school' => $school, 'type' => $type], [], ['book_id'])->getCol();
            self::$catch['books_id'][$school] = $booksId;
        }

        if( !$booksId ) $this->throw_exception( $school ." 未加入图书或者图书都下架了！",7011);
        return $booksId ;
    }

    /**
     * 下载pdf
     * @param int $book_id
     * @return $this
     */
    function download( $book_id ){
        $book = $this->getBookById( $book_id );
        $file = $book['book_pdf']?$book['book_pdf']:$book['isbn']['book_pdf'];
        if( !$file) $this->throw_exception( "未上传");
        $file  = ROOT_PATH.'/webroot/'.$file ;
        drFun::download( $file, $book['book']);
        return $this;
    }

    /**
     * 获得一本书单的内容通过book id
     * @param int $book_id
     * @return mixed
     */
    function getBookById( $book_id ){
        if( self::$book_cache[ $book_id ]) return self::$book_cache[ $book_id ];
        $row = $this->createSql()->select( $this->tb_book,['book_id'=> intval($book_id)])->getRow();
        if( ! $row) $this->throw_exception( "书本不存在", 7001 );
        $this->margeBookIsbn( $row );
        if(isset($_POST['MB_version'])){             drFun::cdnImg($row,['book_img' ] );         }
        self::$book_cache[ $book_id ]=$row ;
        return $row;
    }

    /**
     * 获取本人加入的书单
     * @param int $book_id 默认为0 表示加入的所有书单
     * @param int $isMerge 是否将书本信息merage过来
     * @param array $opt opt.limit 现在调试
     * @return array
     */
    function getJoinBook( $book_id=0,$isMerge=0 ,$opt=[] ){
        if( $this->user_id<=0) return [];
        if( $book_id<0 )  $this->throw_exception( "书刊参数错误", 7003 );
        if( $book_id==0){
            $limit  = isset($opt['limit']) ?$opt['limit']:[0,10] ;
            $book = $this->createSql()->select($this->tb_book_user,['user_id'=> $this->getUserID() ], $limit ,[],['id'=>'desc'])->getAll();
        }else{
            $book =$this->createSql()->select( $this->tb_book_user,['user_id'=> $this->getUserID(),'book_id'=>$book_id ])->getRow();
        }
        if($book && $isMerge ) $this->createSql()->merge(  $this->tb_book,'book_id', $book );
        return $book;
    }

    function searchBooksFromArray( $array ,$key=['book_id']){
        $bid=[];
        drFun::searchFromArray( $array, $key, $bid );
        if(! $bid ) return [];
        $books= $this->createSql()->select(   $this->tb_book,['book_id'=>$bid ])->getAllByKey('book_id');
        foreach ($books as &$v ) unset($v['book_info']);
        drFun::cdnImg($books,['book_img','book_pdf'] );
        return $books;
    }

    /**
     * 添加书单
     *
     * ['book'=>1,'book_info'=>1, 'book_writer'=>1,'book_img'=>1 ,'school'=>1,'word_comment'=>1,'word_topic'=>1,'user_topic'=>1,'user_comment'=>1,'book_pdf'=>1 ];
     * @param array $book
     * @return mixed
     */
    public function bookAdd( $book ,$opt=[] ){
        if(!isset($opt['no_check'])) $this->checkPre( );
        if( !isset( $opt['term_key'])) $this->throw_exception("请带上学期",7049);
        $file = $this->file_book;
        $var=[];
        $this->checkBookVar( $book);
        drFun::arrExtentByKey( $var, $book,$file);
        $var['user_id']= $this->getUserID();
        $var['ctime']= time();
        $var['term_key']=  $opt['term_key'] ;

        if( ! isset($var['tag_4_config']) ) $var['tag_4_config']=0;
        if( ! isset($var['tag_3_config']) ) $var['tag_3_config']=0;
        if( ! isset($var['tag_5_config']) ) $var['tag_5_config']=0;

        $book_id= $this->createSql()->insert( $this->tb_book, $var)->query()->lastID();
        $this->joinBook( $book_id, 3 );

        if( $book['teacher_display'] ){
            $this->plJoinBookAdmin($book_id,$book['teacher']  );
        }
        $this->createBookOpt()->saveAll( $book_id, $book['opt']);
        return $book_id;
    }

    /**
     * 添加应用
     * @param int $n_book_id 新bookID
     * @param int $f_book_id 老bookID 被引用的
     * @return $this
     */
    public function addBookYing( $n_book_id, $f_book_id ){
        if( $n_book_id<=0 || $f_book_id<=0  ){
            $this->throw_exception("书单ID参数必须大于0",7047);
        }
        $var=['n_book_id'=> $n_book_id,'f_book_id'=>$f_book_id,'user_id'=>$this->getUserID(),'ctime'=>time() ];
        $this->createSql()->insert('book_ying', $var)->query(); //->lastID()
        return $this;
    }

    /**
     * 获取引用的信息，通过 f_book_id
     * @param array $books book_id数组 f_book_id
     * @return array
     */
    public function getBookYingOfBookId( $books ){
        if( !$books || ! is_array($books))  $this->throw_exception("必须为有效数组",7048);
        return $this->createSql()->select('book_ying',['f_book_id'=> array_keys($books ),'user_id'=>$this->getUserID() ] )->getAllByKey( 'f_book_id');
    }

    /**
     * 检查添加修改书时 变量的合法性
     * @param array $var
     */
    public function checkBookVar( $var ){
        if( !$var['book']) $this->throw_exception("名字不允许为空",7025);
        if( !$var['book_img']) $this->throw_exception("请先上传一个封面图片",7026);
        if( !$var['school']) $this->throw_exception("学校名称不允许为空",7027);
        if( !$var['book_writer']) $this->throw_exception("作者不允许为空",7028);

        if( $var['word_comment']<=0 ) $this->throw_exception("评论字数限制大于0",7029 );
        if( $var['word_topic']<=0 ) $this->throw_exception("主题字数限制大于0",7030 );
        if( $var['user_topic']<=0 ) $this->throw_exception("主题任务数大于0",7031 );
        if( $var['user_comment']<=0 ) $this->throw_exception("评论任务数大于0",7032 );
    }

    /**
     * 修改书单
     * @param int $book_id
     * @param array $book
     * @return $this
     */
    public function bookModify( $book_id,$book ){
        //$this->drExit( $book );
        $book_old = $this->getBookById($book_id);
        if( !( $book_old['user_id']== $this->getUserID() || $this->checkPre($book_id) ))    return $this ;
        foreach ($book as $k=>$v ){
            if( !$v )unset($book[$k]);
        }

        if( $book['teacher_display'] ){
           $this->plJoinBookAdmin($book_id,$book['teacher']  );
        }

        $file = $this->file_book;

        if(! isset( $book['tag_3_config'] )) $book['tag_3_config']=0;
        if(! isset( $book['tag_4_config'] )) $book['tag_4_config']=0;
        if(! isset( $book['tag_5_config'] )) $book['tag_5_config']=0;
        if(! isset( $book['tag_6_config'] )) $book['tag_6_config']=0;

        $this->update($this->tb_book,['book_id'=>$book_id], $book, $file );
        $this->createBookOpt()->saveAll( $book_id, $book['opt']);
        return $this;
    }

    /**
     * 检查是否 可以更改学期；
     * 条件为：人数大于5人 或者 讨论大于5
     * @param $book_id
     * @return $this
     */
    public function checkChangeBookTerm( $book_id ){
        $book_old = $this->getBookById($book_id);
        if( $book_old['user_cnt']>4 ||  $book_old['discuss_cnt']>4  ) $this->throw_exception( "已经比较多人参与进来，请勿修改学期",7092);
        return $this;
    }


    /**
     * 加入书单
     * - 检查是否存在
     * - 如果存在并且 原来加入某班级的与现在又加入新班级不一样 先剔除老班级 同时刷新下老class_id中 task_class的cnt
     * - 如果仅存在 直接更新下 type
     * - 如果不存在 直接插入
     * - 最后更新 新class_id 中task_class的cnt
     * - 特别注意 加入班级的动作在joinBook之前 应该在ctrl/book act_joinBook 中完成了
     *
     * @param int $book_id
     * @param int $type 0为一般用户，1为管理员,3为创建者
     * @param int $user_id
     * @return $this
     */
    function joinBook( $book_id,$type=0,$user_id=0 ,$vOpt=[] ){
        $this->getTypeBookUser($type);
        $book = $this->getBookById($book_id);

        if($user_id<=0 ) {
            $user_id = $this->getUserID();
        }
        $opt = ['user_id' => $user_id , 'book_id' => $book_id];

        $row =  $this->createSql()->select( $this->tb_book_user, $opt)->getRow();
        if( $row and $user_id==0) $this->throw_exception( "请勿重复加入！",7023);
        
        if( $row ){
            if( $row['class_id']>0  and $vOpt['class_id']>0 and  $row['class_id']!=$vOpt['class_id']){
                $cls = new cls( $user_id );
                $c_row = $cls->getClassStudent($row['class_id'] );
                //$cls->remove( $c_row['id']); #不退出班级 可能在班级还有其他任务
                $task = new task();
                $task->countTaskClass( $book_id,  $row['class_id'] ,2);
            }
            $opt=['type'=>$type];
            if( $vOpt['class_id'])   $opt['class_id'] = $vOpt['class_id'];
            $this->createSql()->update(  $this->tb_book_user,$opt,['id'=>$row['id']])->query();
        }else {
            $opt['ctime'] = time();
            $opt['type'] = $type;
            if( $vOpt['class_id'])   $opt['class_id'] = $vOpt['class_id'];
            $this->createSql()->insert($this->tb_book_user, $opt)->query()->lastID();
            $this->countBookUser($book_id, 0 );
        }
        if( $vOpt['class_id']>0 ) {
            $task = $task? $task:  new task();
            $task->countTaskClass( $book_id,  $vOpt['class_id'] ,2);
        }
        return $this;
    }

    /**
     * 更新一本书单添加或者减掉的用户数量
     * @param int $book_id
     * @param int $cnt
     * @return $this
     */
    function countBookUser( $book_id,$cnt=1 ){

        $cnt = $this->createSql()->getCount( $this->tb_book_user, ['book_id'=> $book_id ])->getOne();
        $this->createSql()->update( $this->tb_book,['user_cnt'=>$cnt] , ['book_id'=> $book_id ])->query();
        return $this;
        /*
        $key = $cnt>0? '+':'-';
        $this->createSql()->update( $this->tb_book,[$key =>['user_cnt'=>$cnt] ], ['book_id'=> $book_id ])->query();
        return $this;
        */

    }

    /**
     * 更新用户关注本书的主题数量；书 主题数、讨论数的数量
     * @param int $book_id
     * @param int $cnt
     * @return $this
     */
    function countBookTopic( $book_id,$cnt=1 ){
        /*
        if($cnt==0 ) return $this;
        $key = $cnt>0? '+':'-';
        $this->createSql()->update( $this->tb_book_user,[$key =>['topic_cnt'=>$cnt]], ['book_id'=> $book_id ,'user_id'=>$this->getUserID() ] )->query();

        $this->createSql()->update( $this->tb_book,[$key =>['topic_cnt'=>$cnt,'discuss_cnt'=>$cnt] ], ['book_id'=> $book_id ])->query();
        */
        $this->createSql()->update( $this->tb_book_user,[ 'topic_cnt'=>$this->getCntFromTopic($book_id,0, $this->getUserID() ) ], ['book_id'=> $book_id ,'user_id'=>$this->getUserID() ] )->query();
        $this->createSql()->update( $this->tb_book,[ 'topic_cnt'=>$this->getCntFromTopic($book_id,0) ,'discuss_cnt'=>$this->getCntDiscuss( $book_id)  ], ['book_id'=> $book_id ])->query();
        return $this;
    }

    function getCntFromTopic( $book_id, $tag_id='all', $user_id='all' ){
        $where=[  ];
        if( $book_id!='all')$where['book_id']= $book_id;//'book_id'=>$book_id
        if( $user_id!='all')$where['user_id']= $user_id;
        if( $tag_id!=='all' ) $where['tag_id']= $tag_id; //发现大bug 0!='all'  为false ， 0!=='all' 才为 true ，由淑娟 账号为15774410237的人发现的
        if(! $where ) $this->throw_exception( "至少需要一个条件",7082 );
        $cnt = $this->createSql()->getCount( $this->tb_topic, $where)->getOne();
        return $cnt>0? $cnt:0;
    }
    function getCntComment( $book_id  ,$user_id='all' ){
        $where=[];
        if( $book_id!='all')$where['book_id']= $book_id;
        if( $user_id!='all')$where['user_id']= $user_id;
        if(! $where ) $this->throw_exception( "至少需要一个条件",7081 );
        $cnt =  $this->createSql()->getCount( $this->tb_comment, $where)->getOne();
        return $cnt>0? $cnt:0;
    }

    function getCntDiscuss( $book_id ,$user_id='all'){
        $topic_cnt = $this->getCntFromTopic($book_id,'all',$user_id );
        $comment_cnt = $this->getCntComment( $book_id ,$user_id );
        return ( $topic_cnt+ $comment_cnt );

    }



    /**
     * 更新用户关注本书的评论回复数量；书 评论回复数、讨论数的数量
     * @param int $book_id
     * @param int $cnt
     * @return $this
     */
    function countBookComment( $book_id,$cnt=1){
        /*
        if($cnt==0 ) return $this;
        $key = $cnt>0? '+':'-';
        $this->createSql()->update( $this->tb_book_user,[$key =>['comment_cnt'=>$cnt]], ['book_id'=> $book_id ,'user_id'=>$this->getUserID() ] )->query();
        $m_sql  = $this->createSql()->update( $this->tb_book,[$key =>['comment_cnt'=>$cnt,'discuss_cnt'=>$cnt] ], ['book_id'=> $book_id ]);
        $m_sql->query();
        */
        $this->createSql()->update( $this->tb_book_user,[ 'comment_cnt'=>$this->getCntComment($book_id,$this->getUserID() )] , ['book_id'=> $book_id ,'user_id'=>$this->getUserID() ] )->query();
        $this->createSql()->update( $this->tb_book,[ 'comment_cnt'=>$this->getCntComment($book_id) ,'discuss_cnt'=>$this->getCntDiscuss($book_id )  ], ['book_id'=> $book_id ])->query();
        return $this;
}

    /**
     * 更新主题查看数
     * @param int $topic_id
     * @param int $cnt
     * @return $this
     */
    function countTopicView( $topic_id ,$cnt=1 ){
        if($cnt==0 ) return $this;
        $key = $cnt>0? '+':'-';
        $this->createSql()->update( $this->tb_topic,[$key =>['view_cnt'=>$cnt ] ], ['topic_id'=> $topic_id ])->query();
        return $this;
    }

    /**
     * 更新主题评论回复数，最后评论时间、最后评论员
     * @param int $topic_id
     * @param int $cnt
     * @return $this
     */
    function countTopicComment( $topic_id ,$cnt=1){
        if($cnt==0 ) return $this;
        $key = $cnt>0? '+':'-';
        $file = [$key =>['comment_cnt'=>$cnt ] ];
        if( $cnt>0  ){
            $file['comment_time'] = time();
            $file['comment_user_id'] = $this->getUserID();
        }
        $this->createSql()->update( $this->tb_topic,$file, ['topic_id'=> $topic_id ])->query();
        return $this;
    }

    /**
     * 主题的分类 朗读 讨论 期中 期末
     *   $now_arr = [ 0=>['n'=>'讨论','cl'=>'icon-tb-message'],1=>['n'=>'任务','cl'=>'icon-tb-form'],2=>['n'=>'问答','cl'=>'icon-tb-friend']
    ,3=>['n'=>'朗读','cl'=>'icon-tb-message']  ,4=>['n'=>'期中','cl'=>'icon-tb-message']  ,4=>['n'=>'期末','cl'=>'icon-tb-message'] ];
     * @param string $id
     * @return array|string
     * @throws drException
     */
    function getTagId( $id='all' ){
        $now_arr = [ 0=>['n'=>'讨论','cl'=>'icon-tb-message'] //,1=>['n'=>'任务','cl'=>'icon-tb-form'],2=>['n'=>'问答','cl'=>'icon-tb-friend']
        ,3=>['n'=>'朗读','cl'=>'icon-tb-we'] ,4=>['n'=>'概要','cl'=>'icon-tb-refresh','n2'=>'期中概要' ]  ,5=>['n'=>'报告','cl'=>'icon-tb-roundcheck' ,'n2'=>'期末报告'],6=>['n'=>'摘抄','cl'=>'icon-tb-edit']   ];
        if( $id== 'all') return $now_arr;
        $id= trim($id);
        if( isset($now_arr[$id]) ) return  $id;
        $this->throw_exception("不存在这一的主题分类", 7005);

    }

    function tagSpecial( &$tags ,$if_opt=[] ){
        $book=[ 382=>1,383=>1,384=>1 ];
        if( isset($if_opt['book_id']) && $book[ $if_opt['book_id'] ] ){
            $tags[4]['n']='报告';
        }
        return $this;
    }

    /**
     * 发布主题 返回新增的 topic_id
     * @param string $topic 标题
     * @param string $topic_info 详细内容
     * @param int $book_id
     * @param array $opt tag_id主题分类  word_cnt字数 attr附加属性
     * @return int
     * @throws drException
     */
    function topicAdd($topic, $topic_info,$book_id, $opt=[] ){
        $this->checkJoinBook( $book_id  );
        $book = $this->getBookById( $book_id);
        $this->checkGuidang( $book );

        $var=['topic'=> trim($topic),'topic_info'=> trim($topic_info),'user_id'=> $this->getUserID(),'ctime'=>time(),'book_id'=>$book_id ,'type'=> intval( $opt['type']) ];
        if( $var['topic_info']=='') $this->throw_exception("标题与内容不允许为空",7004 );
        $var['tag_id']= isset( $opt['tag_id'])?$this->getTagId( $opt['tag_id'] ):0;
        if(  $var['tag_id']==3 )  $this->throw_exception("类别错误！",7004 );

        if( $var['tag_id']==4 ||  $var['tag_id']==5 ){
            $cnt = $this->createSql()->getCount( $this->tb_topic,['user_id'=>$this->getUserID(),'book_id'=>$book_id,'tag_id'=> $var['tag_id']  ])->getOne();
            if( $cnt>0 ) $this->throw_exception("您已经提交过了！",7055 );
        }

        $topic_id= $this->insertTopic(  $var,$opt );
        return $topic_id;
    }

    /**
     * 执行插入
     * @param array $var
     * @param array $opt
     * @return int
     */
    function insertTopic( $var, $opt=[]){
        foreach ( $opt as $k=>$v ) if(! isset($var[$k]) )$var[$k]=$v;

        $tag_id =  $var['tag_id'];
        if(! $opt['is_html'] && $tag_id!=3 ){
            drFun::strip( $var );
        }
        else drFun::strip( $var['topic']);

        if( ! $var['topic'] ) $var['topic'] = $this->getTitleByText( $var['topic_info']);

        $var['word_cnt']= isset($opt['word_cnt']) && $opt['word_cnt']>0 ?  $opt['word_cnt'] :  drFun::wordCount( $var['topic_info'] );

        $var['comment_time']= time();
        $var['is_html']= intval( $opt['is_html'] );

        $book_id= $var['book_id'];
        $book = $this->getBookById( $book_id );
        $word_limit =  $book['word_topic'];
        $this->createBookOpt()->changOptByKey($book_id ,'word_'.$tag_id, $word_limit );

        $login= new login();
        if( !($tag_id==3 || $login->isTeacher()==3 ) && $book['word_topic']>0 &&   $var['word_cnt']< $word_limit ) $this->throw_exception("老师要求至少写 ". $word_limit  .'字',7084);

        $this->topic_info_encode($var['topic_info'] ,$tag_id,$opt );

        drFun::checkStopWord(  $var['topic'] . $var['topic_info']  );


        //$this->drExit( $var );
        $file = ['book_id'=>['n'=>'book ID'],'tag_id'=>1,'user_id'=>['n'=>'用户'], 'word_cnt','topic','topic_info','type','comment_time','ctime','is_html','mtime','is_html','client'];
        $var['client']= drFun::getClient();
        $topic_id= $this->insert(  $this->tb_topic, $var ,$file);


        if( isset($opt['attr']['stop']) &&  $opt['attr']['stop'] ){
            $this->topicAttr( 33, $topic_id );
        }
        if( $tag_id==3 ||   $tag_id==4 || $tag_id ==5  || $tag_id ==6 ) {
            $this->countBookTopicTag( $book_id, $tag_id );
        }else{
            $this->countBookTopic($book_id);
        }
        $this->topic2mq( ['topic_id'=>$topic_id,'topic_info'=>$var['topic_info'] ,'tag_id'=>$tag_id ]);

        $this->toPadLog($topic_id,$tag_id,$var );
        return $topic_id;
    }

    function toPadLog($topic_id,$tag_id,$var ){
        if( !$this->getLogin() )      return $this;
        switch ( $tag_id ){
            case 3:
                $this->topic_info_decode($var );
                $this->getLogin()->padLogAdd($topic_id,(450+$tag_id),( $var['yin2']['time']?'(时长'. timeShow( $var['yin2']['time']).')':'').$var['topic'] );
                break;
            default:
            $this->getLogin()->padLogAdd($topic_id,(450+$tag_id),$var['topic'] );
        };
        return $this;

    }

    function topic_info_encode( &$topic_info, $tag_id, $var=[] ){
        if($tag_id==6){
            $tarr= [];
            $tarr['topic_info'] =$topic_info;
            $tarr['topic_info_yanwen'] = $var['topic_info_yanwen'];
            $topic_info =drFun::json_encode( $tarr );
        }
        return $this;
    }

    function topic_info_decode( &$topic ){
        if( $topic['tag_id']==6){
            $tarr= drFun::json_decode( $topic['topic_info'] );
            $topic['topic_info']= $tarr?$tarr['topic_info'] : $topic['topic_info'] ;
            $topic['topic_info_yanwen']= $tarr['topic_info_yanwen'];
        }elseif( $topic['tag_id']==3 ){
            $tarr =  drFun::json_decode ( $topic['topic_info'] ,true );
            $topic['topic_info']= $tarr['topic_info'];

           if(  $this->getUserID() == 88 ){
               drFun::cdnImg($tarr,['file'] ,'txcos' );
           }else{
               drFun::cdnImg($tarr,['file']  );
           }
            /*
           drFun::cdnImg($tarr,['file']  );
            */

            $topic['yin2']= $tarr;
        }
        return $this;
    }

    function topic_info_decode_list(  &$topicList){
        foreach ($topicList as $k=>$v )   $this->topic_info_decode($topicList[$k] );
        return $this;
    }

    /**
     * 将topic推到队列当中
     * @param $var
     */
    function topic2mq(  $var ){
        $mq= new mq( );
        $tag_id = $var['tag_id'];
        $v_tag = ['zuowen'];//$tag_id==3?['yin']:['zuowen'];
        $v_tag[]= 'tag_'.$tag_id ;
        unset( $var['topic_info'] );
        $mq->publish( 'topic',  $var ,  $v_tag );

        $cache= new cache();
        $cache->getClass()->set( 'topic2mq_'. $var['topic_id'],time(),600 );
    }

    function plGenxin( $tag_id= [4,5]){
        $cache= new cache();
        $ctime =   $cache->getClass()->get( 'plGenxin') ;
        if( $ctime  ) $this->throw_exception("客官别急，你10分钟内(".date('Y-m-d H:i', $ctime).")请勿重复提交",7093);
        $tall = $this->createSql()->select( $this->tb_topic,['tag_id'=>$tag_id,'<='=>['score'=>0] ],[],['tag_id','topic_id'])->getAll();
        foreach ($tall as  $var ){
            $var['from']='plGenxin';
            $this->topic2mq( $var );
            //$this->drExit( $var );
        }
        $cache->getClass()->set( 'plGenxin' ,time(),600 );
        return count($tall );
    }

    /**
     * 将讨论的热度打分改为文本打分 批量丢到队列中
     * 需要在 pigai.publish_topic 设置特殊book_id
     * @param $book_id
     * @return int
     */
    function discuss2wenbenScore( $book_id ){
        if( !$book_id ) $this->throw_exception( "bookID错误",7094);
        $tall = $this->createSql()->select( $this->tb_topic,['book_id'=>$book_id,'tag_id'=>0 ],[],['tag_id','topic_id'])->getAll();
        foreach ($tall as  $var ){
            $var['from']='plGenxin';
            $this->topic2mq( $var );
            //$this->drExit( $var );
        }
        return count($tall );
    }

    /**
     * 打一个主题的活动分 只对讨论主题的打分
     *
     * 公式：
     * - 字数 1-100 30分
     * - 赞 1-20 20分
     * - 回复 1-25 50分 不算自己的
     * @param $topic_id
     * @return $this
     */
    function huoScore( $topic_id ){
        $topic = $this->getTopicById( $topic_id ,['no_cache'=>1 ]);
        if( $topic['tag_id']!=0 ) return $this;

        $comment_cnt = $this->createSql()->getCount( $this->tb_comment, ['topic_id'=>$topic_id,'!='=>['user_id'=>$topic['user_id'] ] ] )->getOne();

        $score = 0.5*$this->scoreItem($comment_cnt,25 )+0.2* $this->scoreItem( $topic['good_cnt'] ,20)+ 0.3* $this->scoreItem(  $topic['word_cnt'],100 ) ;
        $this->update( $this->tb_topic, ['topic_id'=>$topic_id] , ['score'=> intval($score*100) ]);
        return $this;
    }

    function scoreItem($value,$max , $min=0 ){
        if($value<=$min  ) return 0;
        if($value >= $max  ) return 100;
        return (100*$value)/($max-$min);
    }


    /**
     * 修改主题
     * @param int $topic_id
     * @param array $opt 能修改的file attr和 ['topic'=>1,'topic_info'=>1,'book_id'=>1,'user_id'=>1,'tag_id'=>1,'type'=>1,'is_html'=>1,'word_cnt'=>1 ];
     * @return $this
     */
    function topicModify( $topic_id , $opt=[] ){
        $modifyFile=['topic'=>1,'topic_info'=>1,'book_id'=>1,'user_id'=>1,'tag_id'=>1,'type'=>1,'is_html'=>1,'word_cnt'=>1  ];
        $var =[];
        drFun::arrExtentByKey( $var,$opt,$modifyFile );
        if( !isset($var['type'] )) $var['type']=0;
        if( !$var) $this->throw_exception( "修改主题内容为空",7007 );
        $topic = $this->getTopicById( $topic_id );
        if( !( $topic['user_id']== $this->getUserID() || $this->checkPre($topic['book_id']) )){
            return $this ;
        }
        $book_id = $topic['book_id'];
        $book = $this->getBookById( $book_id );

        $cl_login= new login();
        $t_config = $cl_login->createTerm()->getConfigForUser( $cl_login->getSchoolID() );
        $this->checkBookEndTime(  $t_config , $topic['book_id']  );

        $this->checkGuidang( $book );
        $this->margeTopicAttr( $topic );



        $var['mtime']= time();

        if( $var['is_html'] || (  !isset($var['is_html']) and $topic['is_html'] ) ){
            drFun::strip( $var['topic'] );
        }else{
            drFun::strip( $var  );
        }
        if(! $var['topic'] && $var['topic_info']  )$var['topic']= $this->getTitleByText( $var['topic_info'] );
        $var['is_html']= intval($opt['is_html'] );

        if( $topic['tag_id']==3)    $var['topic_info']=$this->changTag3TopicInfo( $topic['topic_info'], $var  );

        $word_limit = $book['word_topic'];
        $this->createBookOpt()->changOptByKey($book_id,'word_'.$topic['tag_id'],  $word_limit);

        #彭老师 微信要求 将字数限制 对任课老师去掉
        $login = new login();
        if(!( $topic['tag_id']==3 || $login->isTeacher()==3 ) && isset( $var['word_cnt']) && $word_limit >0 &&   $var['word_cnt']<$word_limit  ){
            $this->throw_exception("老师要求至少写 ".   $word_limit .'字',7084);
        }
        $var['score']= -1;
        $var['sim']= -1;
        if( isset( $var['topic_info'] ) )$this->topic_info_encode( $var['topic_info'] , $topic['tag_id'] ,$opt );

        drFun::checkStopWord(  $var['topic'] . $var['topic_info']  );

        $this->createSql()->update( $this->tb_topic,$var,['topic_id'=> $topic_id])->query();
        if( isset($topic['attr'][33]) !== isset($opt['attr']['stop']) ){
            $r_var = ['topic'=>$topic];
            $this->topicAttr(33, $topic_id , $r_var);
        }
        $this->topic2mq( ['topic_id'=>$topic_id,'topic_info'=>$var['topic_info'] ,'tag_id'=>$topic['tag_id'] ]);
        $this->countBookTopicTag( $book_id, $topic['tag_id']  );
        return $this;
    }

    /**
     * 处理音频 topic_info 内容
     * @param $topic_info
     * @param $opt
     * @return string
     */
    function changTag3TopicInfo($topic_info , $opt){
        $arr = json_decode($topic_info, true );

        if( isset( $opt['topic_info'] )){
            $arr['topic_info'] = $opt['topic_info'] ;
        }

        if( $opt['file'] ){
            if( $arr['file'] ) $arr['old'][]= $arr['file'];
            $arr['file']= $opt['file'];
        }
        return drFun::json_encode(  $arr);
    }

    /**
     * 获取主题
     * @param int $topic_id
     * @return array
     */
    function getTopicById( $topic_id ,$opt=[]){
        if( ! self::$topic_cache[ $topic_id ] || $opt['no_cache'] ) {
            $topic = $this->createSql()->select($this->tb_topic, ['topic_id' => $topic_id])->getRow();
            if (!$topic) $this->throw_exception("主题不存在！", 7006);
            self::$topic_cache[$topic_id] = $topic;
        }
        return self::$topic_cache[ $topic_id ];
    }

    function getDiscus2wenbenBookID( $book_id=-1){
        $b= [382=>1,383=>1,384=>1]; //
        if($book_id=== -1) return $b;
        return isset( $b[$book_id]);
    }

    /**
     * 清空缓存
     * @return $this
     */
    function clearCache(){
        self::$topic_cache= [];
        self::$book_cache=[];
        return $this;
    }

    /**
     * 添加评论，返回添加主题的comment_id
     * @param int $book_id
     * @param int $topic_id
     * @param string $comment 评论内容
     * @param array $opt reply_id  word_cnt
     * @return int
     */
    function commentAdd( $book_id,$topic_id,$comment,$opt=[] ){
        if( trim($comment)=='' )  $this->throw_exception("请填写评阅", 7009 );
        $this->checkJoinBook( $book_id );
        $topic= $this->getTopicById( $topic_id );
        $this->margeTopicAttr( $topic );
        if( isset( $topic['attr'][33] )){
            $this->throw_exception("禁止回复", 7021);
        }
        if( $book_id!= $topic['book_id']) $this->throw_exception("Book与Topic不相匹配", 7008 );
        $book = $this->getBookById( $book_id);
        $this->checkGuidang( $book );

        drFun::checkStopWord(  $comment );
        drFun::strip( $comment );

        $var=['book_id'=>$book_id,'topic_id'=> $topic_id,'comment'=> $comment,'ctime'=>time() , 'user_id'=>$this->getUserID() ];
        $var['word_cnt']= $opt['word_cnt']>0?  $opt['word_cnt'] : drFun::wordCount( $comment );
        #字数
        if( $book['word_comment'] >0 && $var['word_cnt']< $book['word_comment'] )  $this->throw_exception("老师要求至少写" . $book['word_comment']. '字', 7085 );
        #回复人
        if( isset( $opt['reply_id']) ){
            $var['reply_id']= intval( $opt['reply_id']);
            $re_comment = $this->getCommentById(  $var['reply_id'] );
            $user = new user();
            $user->merge( $re_comment);
            $var['comment'] = "回复 @".$re_comment['user_id_merge']['name']." ：". $var['comment']  ;
        }
        drFun::strip( $var );
        $var['client']= drFun::getClient();

        $cmt_id= $this->createSql()->insert($this->tb_comment, $var)->query()->lastID();
        $this->countBookComment( $book_id )->countTopicComment( $topic_id );
        $this->huoScore( $topic_id );

        $this->getLogin()->padLogAdd($cmt_id,480, '('. $var['word_cnt'].'字)'.$topic['topic']  );

        return $cmt_id;
    }

    function checkBookTerm( $book_id, $term_key ){
        $book = $this->getBookById( $book_id);
        if( $book['term_key']!=$term_key) $this->throw_exception("学期不匹配",7089 );
        return $this;
    }

    /**
     * 获取主题内容
     * @param int $comment_id
     * @return array
     */
    function getCommentById( $comment_id ){
        $row =  $this->createSql()->select( $this->tb_comment,['comment_id'=> $comment_id])->getRow() ;
        if( !$row) $this->throw_exception( "该评论不存在或者被删除",7010);

        return $row;
    }

    /**
     * 是否有管理权限
     * @return bool
     */
    function checkPre( $book_id=0 ){
        $uid = $this->getUserID();
        if( isset( self::$catch['pre'][$uid]) ) return true;
        if( isset( self::$catch['pre_book'][$uid][$book_id] ) ) return true;

        if($book_id>0 ){
            $row = $this->createSql()->select( $this->tb_book_user,['user_id'=>$uid,'book_id'=>$book_id])->getRow();
            if( $row['type']>0 ){
                self::$catch['pre_book'][$uid][$book_id]= $row['type'];
                return true;
            }
        }

        $userOne = new one( $this->getUserID() );
        $is_pre = $userOne->checkPre([],true);
        if( $is_pre ){
            self::$catch['pre'][$uid] = true;
            return true;
        }

        $this->throw_exception("权限不足" ,7011);
        return false ;
    }

    /**
     * 删除评论
     * @param int $comment_id
     * @return bool
     */
    function commentDelById( $comment_id){
        $comment= $this->getCommentById( $comment_id );
        if( !( $comment['user_id']== $this->getUserID() || $this->checkPre( $comment['book_id']) )){
            return false ;
        }
        $this->createSql()->delete( $this->tb_comment,['comment_id'=> $comment_id])->query();
        $this->countBookComment( $comment['book_id'],-1)->countTopicComment( $comment['topic_id'],-1);
        return $comment;
    }

    /**
     * 删除主题
     * @param int $topic_id
     * @return bool|mixed
     */
    function topicDelById( $topic_id ){
        $topic = $this->getTopicById( $topic_id );
        if( !( $topic['user_id']== $this->getUserID() || $this->checkPre($topic['book_id']) )){
            return false ;
        }
        $this->createSql()->delete( $this->tb_topic,['topic_id'=> $topic_id])->query();
        if( $topic['tag_id']==3 || $topic['tag_id']==4 || $topic['tag_id']==5){
            $this->countBookTopicTag( $topic['book_id'] , $topic['tag_id'] ,-1) ;
        }else {
            $this->countBookTopic($topic['book_id'], -1);
        }
        drFun::recycleLog($topic['user_id'],201, $topic  );
        return $topic;
    }

    /**
     * 点赞
     * @param int $type 点赞类型 $typeArr = ['1'=>['n'=>'topic','tb'=> $this->tb_topic ,'key'=>'topic_id' ],'2'=>['n'=>'comment','tb'=> $this->tb_comment ,'key'=>'comment_id'  ] ];
     * @param int $id 点赞的操作ID 从点赞类型中来
     * @return array
     */
    function good(  $type , $id ){
        $typeArr = ['1'=>['n'=>'topic','tb'=> $this->tb_topic ,'key'=>'topic_id' ],'2'=>['n'=>'comment','tb'=> $this->tb_comment ,'key'=>'comment_id'  ] ];
        $typeArr[3]=['n'=>'daily','tb'=>'du_daily' ,'key'=>'daily_id'];

        $typeArr[4]=['n'=>'novel_comment','tb'=>'novel_comment' ,'key'=>'comment_id'];

        $typeArr['8848']=['n'=>'novel_chapter_8848','tb'=>'novel_chapter_8848' ,'key'=>'cp_id','opt_type'=> 8848];
        $typeArr['haoce']=['n'=>'novel_chapter_haoce','tb'=>'novel_chapter_haoce' ,'key'=>'cp_id','opt_type'=> 12];
        $typeArr['xmly']=['n'=>'novel_chapter_xmly','tb'=>'novel_chapter_xmly' ,'key'=>'cp_id','opt_type'=> 13];

        $typeArr['wenda']=['n'=>'wenda','tb'=>'wenda' ,'key'=>'wenda_id','opt_type'=> 21];

        if( !isset( $typeArr[$type]) ) $this->throw_exception( "点赞类型不支持",7013);

        $opt_type= intval( $typeArr[$type]['opt_type'] ?  $typeArr[$type]['opt_type']: $type);
        if( $opt_type<=0 ) $this->throw_exception( "请规定点赞类型",7091);

        $row = $this->createSql()->select("book_good", ['user_id'=> $this->getUserID(),'opt_id'=> $id,'opt_type'=> $opt_type ])->getRow();
        $op= $typeArr[$type];
        $re = [];
        if( $row){
            $this->createSql()->delete( "book_good",['id'=>$row['id']] )->query();
            $re['cnt']=-1;
        }else{
            $this->createSql()->insert( "book_good",[ 'user_id'=> $this->getUserID(),'opt_id'=> $id,'opt_type'=> $opt_type ,'ctime'=>time() ] )->query();
            $re['cnt']= 1;
        }
        $cnt = $this->createSql()->getCount("book_good",[ 'opt_id'=> $id,'opt_type'=> $opt_type] )->getOne();
        $this->createSql()->update( $op['tb'],[ 'good_cnt'=>$cnt] ,[  $op['key']=>$id ])->query();
        $re['good_cnt']= $cnt ;
        $re['tb']=  $op['tb']  ;

        #如果是主题 更新活跃分
        if( $type==1 ) $this->huoScore( $id  );
        return $re;
    }

    /**
     * 记录日志 举报主题 增加置顶 取消topic置顶 举报评论
     *
     * - op=one 仅有一次，
     * - onedel 要不增加要不删除，
     * - oneup 仅有一次,而且更新最新的一次，
     * - 默认append是一直增加
     * @param int $type_id 日志类型 [ 11=>['n'=>'举报主题','op'=>'one'],12=>['n'=>'增加置顶']  ,12=>['n'=>'取消topic置顶'] ,21=>['n'=>'举报评论','op'=>'one'] ];
     * @param int $id 操作ID
     * @param array $opt
     * @return $this
     */
    function bookLog( $type_id, $id, &$opt=[]){
        $type_v =  $this->getTypeBookLog( $type_id );
        $table = $this->tb_log ;
        $opt['type']= $type_v;
        if( !$type_v['op'] || 'append'==$type_v['op'] ){
            $this->createSql()->insert( $table ,[ 'user_id'=> $this->getUserID(),'opt_id'=> $id,'opt_type'=> $type_id ,'ctime'=>time() ] )->query();
            return $this;
        }
        $row = $this->createSql()->select($table , ['opt_id'=> $id,'opt_type'=> $type_id,  'user_id'=> $this->getUserID() ])->getRow();
        if(!$row ){
            $this->createSql()->insert( $table ,[ 'user_id'=> $this->getUserID(),'opt_id'=> $id,'opt_type'=> $type_id ,'ctime'=>time() ] )->query();
        }elseif( $type_v['op']=='oneup'){
            $this->update( $table,[ 'id'=> $row['id']] ,[ 'user_id'=> $this->getUserID(),'opt_id'=> $id,'opt_type'=> $type_id ,'ctime'=>time() ] );
        }elseif( $type_v['op']=='one'){
            $this->throw_exception( "请勿重复 " . $type_v['n'],7015);
        }elseif ( $type_v['op']=='onedel' ){
            $this->createSql()->delete( $table, ['id'=>$row['id']])->query();
            $opt['cnt']= -1;
        }
        return $this ;
    }

    /**
     * 获取操作book的记录
     * - 包括  [ 11=>['n'=>'举报主题','op'=>'one'],12=>['n'=>'增加置顶']  ,12=>['n'=>'取消topic置顶'] ,21=>['n'=>'举报评论','op'=>'one'] ]
     * - op=one 仅有一次， onedel 要不增加要不删除，   默认append是一直增加 oneup 仅有一次而且更新
     * @param string|int $type_id
     * @return array
     */
    function getTypeBookLog( $type_id='all'){
        $type = [ 11=>['n'=>'举报主题','op'=>'one'] ,21=>['n'=>'举报评论','op'=>'one'] ,31=>['n'=>'个人用户修改学校时间','op'=>'oneup'] ];  //,12=>['n'=>'增加置顶']  ,12=>['n'=>'取消topic置顶']
        if($type_id=='all' ) return $type;
        if(! isset($type[ $type_id ]) ) $this->throw_exception( "日志类型不支持",7014);
        return $type[ $type_id ];
    }

    /**
     * 获取bookLog值
     * @param int $op_id
     * @param int $op_type
     * @param array $opt
     * @return int|array
     */
    function getBookLog($op_id, $op_type,$opt=[]){
        $this->getTypeBookLog( $op_type );
        $where = ['opt_id'=>$op_id,'opt_type'=>$op_type ];
        if( $opt['rz']=='cnt'){
            return $this->createSql()->getCount( $this->tb_log, $where )->getOne();
        }
        $tall = $this->createSql()->select($this->tb_log, $where )->getAll() ;
        return $tall ;
    }

    function checkTopicYinChang( $topic, $user){
        //$this->drExit( $user );
        if( !$topic['type'])  return $this;
        if( $topic['user_id'] == $user['uid']  )  return $this;
        if( 3== $user['ts']  )  return $this;
        if( in_array( 'p1', $user['attr']) ||  in_array( 'p2', $user['attr']))  return $this; #管理员可见

        $this->throw_exception( "只有老师和自己可见！",7089);
        return $this;
    }

    /**
     * 将topic的属性以marge的形式加入进来
     * @param array $topics
     * @param array $opt
     * @return $this
     */
    function margeTopicAttr( & $topics ,$opt=[] ){
        if( !$topics ) $this->throw_exception( "topics 必须为数组！" ,7088);
        //$file=['topic_id','type','ctime','attr_id'];
        $file=[ ];
        if( isset($opt['all']) ) $file=[];
        if(  isset( $topics['topic_id']) ){
            $attAll= $this->createSql()->select( $this->tb_attr,['topic_id'=>$topics['topic_id'] ],[],$file )->getAllByKeyArr( ['type','user_id']);
            if( $attAll ){
                $topics['attr']= $attAll;
                $this->topicAttrDafen( $topics['attr'] );
            }
        }else{
            $id_arr = [];
            foreach( $topics as $v ){
                if(isset( $v['topic_id'] ) ) $id_arr[] = $v['topic_id'];
            }
            if( $id_arr ){
                $attr= $this->createSql()->select( $this->tb_attr,['topic_id'=>$id_arr ],[],$file )->getAllByKeyArr( ['topic_id','type','user_id']);

                 foreach( $topics as $k=> $v ){
                     if(isset( $v['topic_id'] ) ){
                        $id = $v['topic_id'];
                        if(isset($attr[$id]) ){
                            $topics[$k]['attr']= $attr[$id];
                            $this->topicAttrDafen(  $topics[$k]['attr'] );
                        }
                     }
                 }
            }
        }
        return $this;
    }

    function topicAttrDafen( & $var1 ){
        foreach( $var1 as $type=>$var2 ){
            if($type==41 ){
                foreach( $var2 as $user_id =>$var3 ){
                    foreach( $var3 as $k=>$var4){
                        $tem = explode("|",$var4['attr'] );
                        $var1[$type][$user_id][$k]['attr']= intval( $tem[0] );
                        $var1[$type][$user_id][$k]['manfen']= intval( $tem[1] );
                    }
                }
            }
        }

        return $this;
    }

    /**
     * [详]Topic属性 支持的类型 置顶 精华 附件 图片 禁止回复 教师打分
     *
     * op:
     * - one 仅有一次 增加了就不能删除 而且无法重复，
     * - onedel 要不增加要不删除，
     * - 默认append是一直增加
     * - oneup 仅一次 发现第二次更新
     *
     * 其他说：
     * - isUser: 当 isUser=1的时候 与op搭配时 就增加了 user_id
     * - 打分41 允许权限：书本创建老师、书本管理员、学校管理员
     * <code>
     *  $type = [ 31=>['n'=>'置顶','op'=>'onedel'] ,32=>['n'=>'精华','op'=>'onedel'],101=>['n'=>'附件','attr'=>['zip','rar','doc','docx','xls','xlsx','pdf'] ] , 102=>['n'=>'图片','attr'=>['gif','jpg','png'] ] ];
     *  $type[33]=[ 'n'=>'禁止回复','op'=>'onedel'];
     *  $type[41]=['n'=>'教师打分','op'=>'oneup' ,'isUser'=>1 ];
     * </code>
     * @param string $type_id
     * @return array
     */
    function getTypeTopicAttr( $type_id='all' ){
        // op=one 仅有一次 增加了就不能删除 而且无法重复， onedel 要不增加要不删除，   默认append是一直增加
        $type = [ 31=>['n'=>'置顶','op'=>'onedel'] ,32=>['n'=>'精华','op'=>'onedel'],101=>['n'=>'附件','attr'=>['zip','rar','doc','docx','xls','xlsx','pdf'] ] , 102=>['n'=>'图片','attr'=>['gif','jpg','png'] ] ];
        $type[33]=[ 'n'=>'禁止回复','op'=>'onedel'];
        $type[41]=['n'=>'教师打分','op'=>'oneup' ,'isUser'=>1,'keys'=>['2'=>'好','1'=>'一般'] ];
        if(  $this->getBookSchool() ){
            $book_scool= $this->getBookSchool() ;
            $term = new term();
            $term_conf = $term->getConfigForSchool( $book_scool['id'],'now');
            $type[41]['keys']= $this->getTypeDafenTemple($term_conf['manfen'], $term_conf['manfen_tpl'] );
        }
        if( $type_id=='all' ) return  $type ;
        if( ! isset($type[$type_id]) ) $this->throw_exception("主题不支持该类型" ,7016);
        return $type[$type_id];
    }


    function getTypeDafenTemple( $dang='all', $tid='all'){
        $tem = [];
        $tem[2][1]= [2=>'好',1=>'一般'];#1
        $tem[2][2]=[2=>'优秀',1=>'一般'];
        $tem[2][3]=[2=>'真棒',1=>'还好'];
        $tem[2][4]=[2=>'真牛',1=>'一般牛'];

        $tem[3][1]=[3=>'好',2=>'中',1=>'差'];
        $tem[3][2]=[3=>'优',2=>'良',1=>'差'];

        $tem[5][1]=[5=>'五星',4=>'四星',3=>'三星',2=>'二星',1=>'一星'];

        #$tem[100]=100; #直接打分

        if($dang=='all' ) return $tem;
        if( ! isset( $tem[$dang]) ) $this->throw_exception("分档不存在！",7078);
        if( $tid=='all') return $tem[$dang];
        if(  ! isset( $tem[$dang][$tid]) ) $this->throw_exception("该风格不存在！",7076);
        return  $tem[$dang][$tid];
    }

    /**
     * 操作主题属性：置顶 精华 附件 图片 禁止回复 人工打分
     *
     * 特别说明人工打分：
     * - type:41
     * - keys：为打分模板
     * - keys: 受到学校term_config 控制 可以在学校的学期管理调整 打分模板和档次
     *
     * @param int $type_id 属性类型参考{@link getTypeTopicAttr}
     * @param int $topic_id
     * @param array $opt
     * @return $this
     */
    function topicAttr( $type_id, $topic_id ,&$opt=[] ){

        $table= 'book_topic_attr';
        $type_v = $this->getTypeTopicAttr( $type_id );
        if( isset( $type_v['attr'] ) && !  isset($opt['attr'] )) $this->throw_exception( $type_v['n']." 缺失值", 7017 );
        $topic = isset($opt['topic'])? $opt['topic'] :  $this->getTopicById( $topic_id );

        $no_chang= [33=>1];#不去验证
        if( !isset( $no_chang[$type_id] ) ) $this->checkPre(  $topic['book_id']  ) ;

        $opt['type'] = $type_v;

        $where = ['topic_id'=>$topic_id, 'type'=> $type_id   ];
        if( $type_v['isUser']==1 )      $where['user_id']=  $this->getUserID();

        $var = [ 'user_id'=> $this->getUserID(),'topic_id'=> $topic_id,'type'=> $type_id ,'ctime'=>time(),'book_id'=>$topic['book_id'] ];
        if( $opt['attr'] ) $var['attr']=  $opt['attr'] ;

        #一般打分（type_id=41）才有keys  ，打分的attr的值为 分数|满分
        if( $type_id==41 &&  $type_v['keys']==100 ){
            $var['attr']=  $var['attr'].'|100';
        }elseif( isset($type_v['keys']) && !isset( $type_v['keys'][ $var['attr'] ] )){
            $this->throw_exception("属性值不在范围内！" ,7058);
        }elseif( isset($type_v['keys']) && isset( $type_v['keys'][ $var['attr'] ] ) &&  $type_id==41 ){
            #将打分放在后面 分数|满分
            $var['attr']=  $var['attr'].'|'.count($type_v['keys']);
        }

        if( !$type_v['op'] || 'append'==$type_v['op'] ){
            $this->createSql()->insert( $table , $var )->query();
            return $this;
        }

        $row = $this->createSql()->select($table ,  $where )->getRow();
        if(!$row ){
            $this->createSql()->insert( $table , $var )->query();
        }elseif( $type_v['op']=='one'){
            $this->throw_exception( "请勿重复 " . $type_v['n'],7015);
        }elseif ( $type_v['op']=='onedel' ){
            $this->createSql()->delete( $table, ['attr_id'=>$row['attr_id']])->query();
            $opt['cnt']= -1;
        }elseif( $type_v['op']=='oneup'){
            $this->update($table,['attr_id'=>$row['attr_id']], $var );
        }
        return $this;
    }

    /**
     * 取得某本书的用户列表 用在获取本身的管理员
     * @param $book_id
     * @param array $opt opt.type管理员 opt.limitlimit
     * @return mixed
     */
    function getBookUser( $book_id, $opt=[]){
        $where=['book_id'=>$book_id ];
        $tall= [];

        switch ($opt['rz']) {
            case 'admin': #取得管理员
                $where['type'] =1 ;
                $tall = $this->createSql()->select($this->tb_book_user, $where, [], [], [])->getAllByKey('user_id' );
                return $tall;
                break;
            default:
            if (isset($opt['type'])) {
            $where['type'] = $opt['type'];
            }
            $limit = isset($opt['limit']) ? $opt['limit'] : [0, 10];
            $order = ['topic_cnt' => 'desc'];
            $tall = $this->createSql()->select($this->tb_book_user, $where, $limit, [], $order)->getAll();
        }
        return $tall ;
    }

    function getBookUserListWithPage( $where ){
        return $this->createSql()->selectWithPage( $this->tb_book_user,  $where );
    }

    /**
     * 批量混合添加 删除管理员
     * @param $book_id
     * @param $post_new_admin post过来的uid [uid1,uid2]
     * @param null $old_admin 原来的管理员 [uid1=>456]
     * @return $this
     */
    function plJoinBookAdmin( $book_id , $post_new_admin, $old_admin=null){
        $new=[];
        $del=[];
        if($old_admin===null ){
            $old_admin= $this->getBookUser( $book_id,['rz'=>'admin']);
        }
        if($post_new_admin) {
            foreach ($post_new_admin as $uid) {
                if (!isset($old_admin[$uid])) {
                    $new[] = $uid;
                    $this->joinBook($book_id, 1, $uid);
                }
            }
        }
        if( $old_admin ) {
            foreach ($old_admin as $uid => $v) {
                if (!in_array($uid, $post_new_admin)) {
                    $this->joinBook($book_id, 0, $uid); // $del[]= $uid;
                }
            }
        }
        return $this;
    }

    /**
     * 统计用户的加入的读书、发帖、跟帖
     * @param int $user_id
     * @return array
     */
    function tjUser( $user_id=0 ){
        if( $user_id>0) $user_id= $this->getUserID();
        if( $user_id<=0 ) $this->throw_exception( '用户ID必须为大于0的数',7022);
        $re= ['topic_cnt'=>0,'comment_cnt'=>0,'book_cnt'=>0 ];
        /*
        $bookUser = $this->createSql()->select( $this->tb_book_user,['user_id'=>$user_id])->getAll();
        $re= ['topic_cnt'=>0,'comment_cnt'=>0,'book_cnt'=>count( $bookUser) ];
        if( $re['book_cnt']>0){
            foreach( $bookUser as $v ){
                $re['topic_cnt']+= $v['topic_cnt'];
                $re['comment_cnt']+= $v['comment_cnt'];
            }
        }
        */
        $re['book_cnt']= $this->createSql()->getCount( $this->tb_book_user,['user_id'=>$user_id] )->getOne();
        $re['topic_cnt']= $this->getCntFromTopic('all','all',$user_id );
        $re['comment_cnt']= $this->getCntComment('all', $user_id );

        return $re;
    }

    /**
     * 统计本校数据 有缓存先上缓存
     *
     * 获得的数据[books_cnt=>'书本数','topic_cnt'=>'主题数','comment_cnt'=>'回复',discuss_cnt=>'讨论：主题+回复',user_cnt=>'成员、关注人']
     * @param string $school
     * @param bool $is_real
     * @return array|mixed|string
     */
    function tjSchool( $school='',$is_real=false ,$opt=[] ){
        if( !$school ) $school=$this->school;
        if( isset($_GET['term'])  ) return  $this->tjSchoolReal($school ,['term'=> trim( $_GET['term'])]);
        if( isset($opt['term'])  ) return  $this->tjSchoolReal($school , $opt );
        $key_table = 'tb_school_tj';
        $cache = new cache();
        try{
            $re = $is_real? false: $cache->getRedis()->hGet($key_table, $school);
            if( (time()-$re['lastTime'] )>24*3600)  $re = false ;
            if( $re ) return $re ;
        }catch (\Exception $ex ){ }

        $re = isset( $opt['now_term_key'] )? $this->tjSchoolReal($school,['term'=>$opt['now_term_key'] ] ) : $this->tjSchoolReal($school ) ;
        if( isset($_GET['sql']) ) $this->drExit( ['re'=> $re ]  );

        try{
            $cache->getRedis()->hSet($key_table, $school, $re );
            //$re['cache']=  $cache->getRedis()->hGet($key_table, $school);
        }catch ( \Exception $ex ){
        }
        $re['isReal']= date("Y-m-d H:i:s");
        return $re ;
    }
    function saveTjSchool2Redis($school, $re){
        $key_table = 'tb_school_tj';
        $cache = new cache();
        $cache->getRedis()->hSet($key_table, $school, $re );
        return $this;
    }

    /**
     * 真实统计
     * @param $school
     * @param array $opt
     * @return array
     */
    function tjSchoolReal( $school, $opt=[]){
        $where= ['school'=>$school,'type'=>0 ];
        if(isset( $opt['term'] )) $where['term_key']= $opt['term'];

        $books = $this->createSql()->select( $this->tb_book,$where,[] ,['book_id', 'topic_cnt','comment_cnt','discuss_cnt','user_cnt' ])->getAll();
        $re =['topic_cnt'=>0,'comment_cnt'=>0,'discuss_cnt'=>0,'user_cnt'=>0  ];


        if( isset($_GET['sql']) ) $re['sql'][] = $this->createSql()->select( $this->tb_book,$where,[] ,['book_id', 'topic_cnt','comment_cnt','discuss_cnt','user_cnt' ])->getSQL();

        $book_id =[];
        foreach( $books as $v ){
            $book_id[ $v['book_id'] ] =$v['book_id'] ;
            foreach( $re as $k2=>$v2 ) $re[ $k2 ]+= $v[$k2];
        }
        $re['books_cnt']= count( $books);
        $re['lastTime'] = time();
        $re['user_cnt_ci']=  $re['user_cnt'];
        if( $book_id ) {
            $tall = $this->createSql( "SELECT user_id, COUNT( user_id ) AS cnt FROM  `book_user`  WHERE  `book_id` IN ( ".implode(',', $book_id)." ) GROUP BY user_id")->getAll();
            $re['user_cnt'] = count( $tall );
            if( isset($_GET['sql']) ) $re['sql'][] = $this->createSql( "SELECT user_id, COUNT( user_id ) AS cnt FROM  `book_user`  WHERE  `book_id` IN ( ".implode(',', $book_id)." ) GROUP BY user_id")->getSQL();
        }

        return $re ;
    }

    /**
     * 统计所有书，放后台
     *
     * 获得的数据[books_cnt=>'书本数','topic_cnt'=>'主题数','comment_cnt'=>'回复',discuss_cnt=>'讨论：主题+回复',user_cnt=>'成员、关注人']
     * @param string $school 学校 默认为空 表示全部
     * @param array $opt
     * @return array
     */
    function tjAll( $school='', $opt=[]){

      $sql = "select count(*) as books_cnt, sum( topic_cnt) as topic_cnt , sum( discuss_cnt) as discuss_cnt, sum( user_cnt) as user_cnt, sum( comment_cnt) as comment_cnt ";
      $sql .=" , sum(tag_3_cnt) as tag_3_cnt , sum(tag_4_cnt) as tag_4_cnt, sum(tag_5_cnt) as tag_5_cnt, sum(tag_6_cnt) as tag_6_cnt";
      $sql .= " from  " . $this->tb_book;
      if( $school =='' ) {
          $row = $this->createSql($sql)->getRow();
      }else{

          $book_id = $this->getBooksIdBySchool( $school,0  ,$opt);
          $where = $book_id ?  " where book_id in(".implode(',', $book_id ).") " : " where school='".drFun::addslashes($school )."' ";
//          if($book_id) {
//              $row =  $this->createSql($sql . " where book_id in(".implode(',', $book_id ).") "  )->getRow();
//          }else{
//              $row = $this->createSql($sql . " where school=:school", [':school' => $school])->getRow();
//          }
          $row =  $this->createSql($sql .  $where )->getRow();

          #选书人数
          if( $book_id ) {
              $sql = "select user_id,count(*) as cnt from " . $this->tb_book_user . " where book_id in(" . implode(',', $book_id) . ") group by user_id";
              $tall = $this->createSql($sql)->getCol2();
              $row['select_cnt']= count( $tall );
          }else{
              $row['select_cnt']= 0;
          }
          if(  $row['select_cnt']>$row['user_cnt'] ) $row['user_cnt']= $row['select_cnt'];

      }
        return $row;
    }

    /**
     * 统计开通了多少学校
     * @return array
     */
    function tjBookSchool(){
        $re['school_cnt']= $this->createSql()->getCount( $this->tb_school,['is_show'=>1 ])->getOne();
        $re['school_cnt_wait']= $this->createSql()->getCount( $this->tb_school,['is_show'=>-1 ])->getOne();
        return $re;
    }

    /**
     * 新增书本学校
     * @param $opt  ['logo'=>['n'=>'logo'],'school'=>['n'=>'学校名称'] ,'school_slogan'=>['n'=>'校训'],'school_ename'=>['n'=>'校域名'] ]
     * @return int
     */
    public function addBookSchool( $opt){
        $file = ['logo'=>['n'=>'logo'],'school'=>['n'=>'学校名称'] ,'school_slogan'=>['n'=>'校训'],'school_ename'=>['n'=>'校域名'],'is_show' ];
        if( $opt['school']=='') $this->throw_exception( "请填写学校！",7044  );
        if( $opt['school_ename']=='') $this->throw_exception( "请填写校域名！",7045  );
        $cnt = $this->createSql()->getCount( $this->tb_school, ['or'=> [  ['school'=>$opt['school']] ,['school_ename'=>$opt['school_ename'] ] ] ])->getOne();
        if( $cnt>0 ){
            $this->throw_exception( "学校或者校域名重复了！",7043 );
        }
        $opt['is_show'] = -1;
        return $this->insert( $this->tb_school,$opt, $file );
    }

    /**
     * 编辑学校
     * @param int $school_id
     * @param $opt 参考{@link addBookSchool} 的opt
     * @return $this
     */
    public function editBookSchool( $school_id, $opt ){
        $file = ['logo','school','school_slogan','school_ename','is_show','tpl' ];
        $where=[];
        if( isset($opt['school']) ) $where['or'][]['school']= $opt['school'];
        if( isset($opt['school_ename']) ) $where['or'][]['school_ename']= $opt['school_ename'];
        if( $where ) {
            $tall = $this->createSql()->select($this->tb_school, $where)->getAll();
            if (count($tall) > 1 || ($tall && $school_id != $tall[0]['id'])) $this->throw_exception("学校或者校域名重复了！", 7046); //. count($tall)."\t". $tall[0]['id']
        }

        $this->update(  $this->tb_school ,['id'=>$school_id ],$opt, $file );
        return $this;
    }

    /**
     * 书本的状态  [0=>['n'=>'在线-公开'] , 1=>['n'=>'在线-仅班级'], -1=>['n'=>'归档'],-2=>['n'=>'离线']]
     * @param string $type_id 状态ID 默认all为全部
     * @return array
     */
    public function getTypeBook( $type_id='all'){
        $type = [0=>['n'=>'在线-公开'] , -1=>['n'=>'归档'],-2=>['n'=>'下架']]; //, 1=>['n'=>'在线-仅班级']
        if( $type_id=='all'){
            return $type;
        }
        if( ! isset( $type[$type_id]) ) $this->throw_exception( "选项错误（".$type_id."）" ,7050);
        return  $type[$type_id];
    }

    /**
     * 更新书本的状态
     * @param int $book_id
     * @param int $type
     * @return $this
     */
    public function updateBookType( $book_id, $type){
        $this->getTypeBook( $type );
        $this->createSql()->update($this->tb_book,['type'=>$type],['book_id'=>$book_id])->query();
        return $this;
    }

    /**
     * 检查选书是否超过学校 限制！ limit 0 不检查
     * @param $limit
     * @return $this
     */
    function checkBookLimit( $limit,$opt=[] ){
        //$this->assign('limit',$limit );

        $time = time();
        $book_id = $opt['book_id'];
        $book= $this->getBookById( $book_id  );

        /* //加入单本书设置的限制
        $this->createBookOpt()->changOptByKey($book_id,'s_start_time', $limit['s_start_time'],['t'=>'datetime'] )
            ->changOptByKey($book_id,'s_end_time', $limit['s_end_time'] ,['t'=>'datetime'] );
        */


        if( $book['user_limit']>0 && $book['user_cnt']>= $book['user_limit'] ){
            $this->throw_exception( '已经超过限选人数 '. $book['user_limit'] ,7073 );
        }

        if( $time<$limit['s_start_time'] ){
            $this->throw_exception( '未到选书开始时间 ' .date("Y-m-d H:i:s",$limit['s_start_time'] ),7052 );
        }
        if( $time>$limit['s_end_time'] && $limit['is_bu']!='1' ){
            //if($_GET['debug']) $this->drExit( $limit );
            $this->throw_exception( '已经过截止时间 ' .date("Y-m-d H:i:s",$limit['s_end_time'] )  ,7053 );
        }
        if( $opt['p2']=='readd' && isset($opt['book_id']) ){
            $row = $this->createSql()->select($this->tb_book_user, ['user_id' => $this->getUserID(), 'book_id' => $opt['book_id'] ])->getRow();
            if( $row['type']>0) $this->throw_exception( "指导教师无法重选",7071);
            if($row ) return $this;
        }

        if( $limit['book_limit']>0  ) {
            //$this->drExit( $limit );
            $book_ids = $this->getBooksIdBySchool($this->school ,0  ,[ 'term'=>$limit['term_key'] ]);
            $cnt = $this->createSql()->getCount($this->tb_book_user, ['user_id' =>$this->getUserID(), 'book_id' => $book_ids])->getOne();

            if ($cnt >= $limit['book_limit']) $this->throw_exception("你已选 " . $cnt . ' 本，已超我校限制 ' .  $limit['book_limit'] . '本', 7051);
            //$this->throw_exception( $limit['book_limit'].'  '.  $cnt);
        }
        return $this ;
    }

    function checkBookEndTime( $term_config ,$book_id  ){
        $this->createBookOpt()->changOptByKey( $book_id,'end',$term_config['end'] ,['t'=>'datetime']);

        if( $term_config['end'] >0 &&  time()> $term_config['end'] ) $this->throw_exception( "学期已经结束答题",7068);
        return $this;
    }

    /**
     * 件事是否本学校
     * @param $school
     * @return $this
     */
    function checkOtherSchool( $school ){
        if( $school != $this->school)
        $this->throw_exception( "暂时不支持垮学校选书！",7052);
        return $this;
    }

    /**
     * 确定书是否归档
     * @param  array $book
     * @return $this
     */
    function checkGuidang( $book ){
        if( $book['type']<0) $this->throw_exception("书已归档或下架" ,7079);
        return $this;
    }

    /**
     * 对音频见
     * @param $topic_info
     * @param $file
     * @return array
     */
    function getPreTopicYin( $topic_info, $file ,$opt=[]){
        if( !$opt )   $re_move = drFun::move( $file,'yin');
        else{   $re_move= $opt;     }
        $re=['topic'=> $this->getTitleByText( $topic_info ) ];
        if( !$re['topic']) $re['topic']='一段朗读';
        $re_move['topic_info']= $topic_info;
        if( isset($_POST['attr'])){
            $attr = json_decode( $_POST['attr']);
            drFun::arrExtend( $re_move, $attr , false ) ;
        }
        $re['topic_info']= drFun::json_encode($re_move);
        return $re ;
    }

    /**
     * 从文本中获取标题
     * @param $text
     * @return string
     */
    function getTitleByText( $text ){
        $text= trim( trim( $text),"\n");
        $tarr = explode("\n", $text,2);
        $title= $tarr[0];
        $len= mb_strlen( $title,'utf-8');
        $stlen= strlen( $title);
        if($len<20|| $stlen<100) return $title;
        $title = mb_substr( $title,0,20,'utf-8')  ;
        return $title;

    }

    /**
     * 上传朗读 音频作业
     * @param $book_id
     * @param $opt
     * @return init
     */
    //function topicAddYin( $book_id, $file){
    function topicAddYin( $book_id, $opt){
        $this->checkJoinBook( $book_id  );
        $book = $this->getBookById( $book_id);
        $post = $opt ;
        if(  trim($post['topic_info'])=='' ) $this->throw_exception( "请输入你朗读的内容！",7074);
        $this->checkGuidang( $book );
        $tag_id = 3;
        $max_cnt = 3;
        //if( $book['school']=='黑龙江大学' )   $max_cnt = 10;
        if( drFun::getClient()<3  ) {
            $cnt = $this->createSql()->getCount($this->tb_topic, ['user_id' => $this->getUserID(), 'book_id' => $book_id, 'tag_id' => $tag_id])->getOne();
            if ($cnt >= $max_cnt) $this->throw_exception("已超上传次数(" . $max_cnt . "次),建议使用APP！", 7054);
        }

        drFun::checkStopWord(   $post['topic_info']  );
        /*
        if( $file['error'] ) $this->throw_exception( "上传发生错误，可能文件太大！",7053);
        $r = drFun::upload( $file,['dir'=>'yin','ext'=> ['mp3'=>1,'mp4'=>1] ] );
        */
        if( isset( $_FILES['file']) ){
            if(  $_FILES['file']['error'] ) $this->throw_exception( "发生错误，录音可能文件太大！",7097);
            $p_file= drFun::upload( $_FILES['file'] ,['dir'=>'yin','ext'=> ['mp3'=>1,'mp4'=>1,'amr'=>1]]  );
            $p_file['time']=$post['time'];  $p_file['type']='postFile';
            $var2 = $this->getPreTopicYin( $post['topic_info'], $p_file['file'] ,$p_file);
            //$this->drExit(  $var2 );
        }else{
            if( !$post['file'] )         $this->throw_exception("请先上传音频",7083);
            $var2 = $this->getPreTopicYin( $post['topic_info'], $post['file']);
        }


        $var=['topic'=>$var2['topic'] ,'topic_info'=>$var2['topic_info']  ,'user_id'=> $this->getUserID(),'ctime'=>time(),'book_id'=>$book_id,'type'=>intval($opt['type']) ];
        $var['tag_id']=$tag_id;
        $var['word_cnt']= isset($opt['word_cnt']) && $opt['word_cnt']>0 ?  $opt['word_cnt'] :  drFun::wordCount( $var['topic_info'] );
        $topic_id= $this->insertTopic( $var,$opt );
        return $topic_id;
    }

    /**
     * 更新 tag 为3 4 5主题的
     * @param $book_id
     * @param $tag_id
     * @param int $cnt
     * @return $this
     */
    function countBookTopicTag($book_id,$tag_id,$cnt=1){
        $this->getTagId($tag_id );
        if( $tag_id==0){
            $this->countBookTopic($book_id);
            return $this;
        }
        $file_name = 'tag_'.$tag_id.'_cnt';
        /*
        if($cnt==0 ) return $this;
        $key = $cnt>0? '+':'-';
        //$tag=[3,4,5];      if(! in_array( $tag_id, $tag)) $this->throw_exception( "不存在这样的主题！",7054);
        
        $this->createSql()->update( $this->tb_book_user,[$key =>[$file_name=>$cnt]], ['book_id'=> $book_id ,'user_id'=>$this->getUserID() ] )->query();
        $this->createSql()->update( $this->tb_book,[$key =>[$file_name=>$cnt,'discuss_cnt'=>$cnt] ], ['book_id'=> $book_id ])->query();
        */
        $this->createSql()->update( $this->tb_book_user,[$file_name=> $this->getCntFromTopic($book_id,$tag_id,$this->getUserID() )], ['book_id'=> $book_id ,'user_id'=>$this->getUserID() ] )->query();
        $this->createSql()->update( $this->tb_book,[ $file_name=>$this->getCntFromTopic($book_id, $tag_id),'discuss_cnt'=> $this->getCntDiscuss( $book_id)]  , ['book_id'=> $book_id ])->query();

        return $this;
    }

    function getTopic( $where ){
        return $this->createSql()->select( $this->tb_topic, $where)->getAll();
    }

    /**
     * 取回学生完成的进度
     * @param $book
     * @param $joinBook
     * @return array
     */
    function getProgress( $book,$joinBook ){
        if(! $joinBook || ! $book) return [];
        if( $book['user_topic']>0 ) $re[]=['n'=>'发帖','tag_id'=>0,'progress'=> intval( $book['user_topic']<=0?100: 100*$joinBook['topic_cnt']/$book['user_topic'] )];
        if( $book['user_comment']>0 ) $re[]=['n'=>'回复','tag_id'=>0, 'progress'=> intval( $book['user_comment']<=0?100: 100*$joinBook['comment_cnt']/$book['user_comment'] )];
        $tag = $this->getTagId();
        for($i=3;$i<=5 ;$i++){
            if(  $book['tag_'.$i.'_config'] )
            $re[]=['n'=>$tag[$i]['n'],'tag_id'=>$i ,'progress'=> ( $joinBook['tag_'.$i.'_cnt']>0?100:0 )];
        }
        foreach( $re as &$v ) $v['progress']=  $v['progress']>100?100:  $v['progress'];

        //if( $joinBook['user_id']==5184 ) $this->drExit( $joinBook );

        return $re;
    }

    /**
     * 统计列表中带用户 参与书的 选书、主题、回复、朗读、期中、期末
     * @param array $list 必须带 user_id
     * @param array $bookIds 当为all统计全部 默认为本学校学期当前书本
     * @return $this
     * @throws drException
     */
    function tjUserRz( &$list ,$bookIds=[], $opt=[] ){
        $this->need_tetail = true;
        if( !$list || !is_array($list) ) return $this;
        $u_arr=[];
        drFun::searchFromArray( $list,['user_id'] ,$u_arr );
        if(!$u_arr) $this->throw_exception( "不存在用户",7086 );
        if( $bookIds=='all' ) { #统计全部
            $bookIds= false ;
        }else{
            if (!$bookIds) $bookIds = $this->getBooksIdBySchool($this->getSchool());
            if (!$bookIds) $this->throw_exception("没有可统计的书", 7056);
        }
        //if($_GET['wdebug']==1)  {
        $finish_score= $this->countScoresByUid(  $u_arr,$bookIds ,$opt  );
        //} else $finish_score= $this->countScoresByUid(  $u_arr,$bookIds  );
        $where =  ['user_id'=> $u_arr];
        if(  $bookIds ) $where['book_id'] = $bookIds ;

        $tall = $this->createSql()->select( $this->tb_book_user, $where )->getAllByKeyArr(['user_id']);
        foreach( $list as $k=>$v ){
            $var = $tall[ $v['user_id'] ];
            $tem=['book_cnt'=>0,'topic_cnt'=>0,'comment_cnt'=>0,'tag_3_cnt'=>0 ,'tag_4_cnt'=>0,'tag_5_cnt'=>0,'tag_6_cnt'=>0];
            if( $var ) {
                foreach ($var as $v2) {
                    $tem['book_cnt']++;
//                    foreach ($tem as $k3 => $v3) {
//                        if (isset($v2[$k3])) $tem[$k3] += $v2[$k3];
//                    }
                    if( $finish_score[  $v['user_id'] ]['detail'][0]['book_id']==$v2['book_id'] ) {
                        //$tem['book_cnt']=1;
                        foreach ($tem as $k3 => $v3) {
                            if (isset($v2[$k3])) $tem[$k3] = $v2[$k3];
                        }
                        //$list[$k]['tjrz_detail'] = $v2 ;
                    }
                }
            }
            $list[$k]['tjrz'] = $tem;
            //$this->countLastScore( $finish_score[  $v['user_id'] ]['score'],$opt['dafen'] );
            $list[$k]['finish'] = $finish_score[  $v['user_id'] ] ;
        }
        return $this;
    }

    /**
     * 统计用户列表完成情况
     * @param $list
     * @param $bookIds
     * @param $opt
     * @return $this
     */
    function tjScoreByList( &$list ,$bookIds ,$opt=[]  ){
        $this->need_tetail= false ;
        if( !$list || !is_array($list) ||  !$bookIds || !is_array($bookIds) ) return $this;
        $u_arr=[];
        drFun::searchFromArray( $list,['user_id'] ,$u_arr );
        if(!$u_arr) $this->throw_exception( "不存在用户",7087);
        /*
        $finish_score= $this->countScoresByUid(  $u_arr,$bookIds   );;
        foreach( $list as $k=>$v ){
            if( isset($opt['dafen']) &&  isset( $finish_score[  $v['user_id'] ]['score'] ) ) $this->countLastScore($finish_score[  $v['user_id'] ]['score'],$opt['dafen'] );
            $list[$k]['finish'] = $finish_score[  $v['user_id'] ] ;
        }
        */
        $finish_score= $this->countScoresByUid(  $u_arr,$bookIds ,$opt  );
        foreach( $list as $k=>$v ){
            $list[$k]['finish'] = $finish_score[  $v['user_id'] ] ;
        }
        return $this;
    }

    /**
     * 计算打分公式
     * @param $scoreAll
     * @param $dafen_config
     * @return $this
     */
    function countLastScore( &$scoreAll, $dafen_config ){
        $scoreAll['last']= 0;
        if( !$scoreAll ||  !$dafen_config || !is_array($scoreAll )|| !is_array($dafen_config )) return $this;
        $sum = array_sum( $dafen_config ); $total=0;
        if( $sum<=0) return $this;
        foreach( $dafen_config as $k=>$v  ) $total+= floatval($scoreAll[$k])*floatval($v);
        $scoreAll['last']= number_format( $total/$sum,   2, '.', '');
        return $this;
    }

    function dafenWithBook( &$dafen, $book){
        foreach($dafen as $k=>$v ){
            if( $k<=0 ) continue ;
            if( !$book['tag_'.$k.'_config'] ) unset( $dafen[ $k ] );
        }
        return $this;
    }
    function dafenWithGet( &$dafen ){
        foreach($dafen as $k=>$v ){
            if(!isset($_GET['d'.$k])) continue;
            $v2= floatval($_GET['d'.$k] );
            if($v2<0 ) continue;
            $dafen[$k]=$v2;
        }
        return $this;
    }

    /**
     * 计算本学期 学生的最终学分
     * - 1.多本书 以完成比较完善的这边书来计算得分 多完成了 取均分高的
     * - 2.计算每本书 讨论分：完成任务的60% 还是有40% 取每个主题最高的分数
     * - 3.计算每本书 朗读 期中 期末 摘要 取最高分
     * @param $u_arr
     * @param $bookIds
     * @param $opt
     * @return array 返回 uid=>[ws_cnt=>完成几本,score=>[tag_id=>成绩 ] ]
     */
    function countScoresByUid(  $u_arr ,$bookIds ,$opt=[] ){
        if( !$bookIds || !$u_arr ) return [];

        $book = $this->createSql()->select( $this->tb_book ,  ['book_id'=>$bookIds])->getAllByKey( 'book_id');
        foreach($book as $k=>$v ) { unset( $book[$k]['book_info'] );  unset( $book[$k]['book_plan'] );}
        $this->assign('books', $book);
        //$book_opt = [];
        //foreach( $bookIds as $id  ) $book_opt[ $id ]= $this->createBookOpt()->getOpt( $id );

        $book_user  = $this->createSql()->select( $this->tb_book_user,  ['user_id'=> $u_arr ,'book_id'=> $bookIds ] )->getAllByKeyArr(['user_id','book_id']);
        //$this->drExit($book_user );
        $tall = $this->createSql()->select( $this->tb_topic, ['user_id'=>$u_arr,'book_id'=>$bookIds ],[],['user_id','book_id','tag_id','score','sim'])->getAllByKeyArr( [ 'user_id','book_id','tag_id']);
        $rz=[];
        foreach( $tall as $user_id =>$bookScore){

            $rz[$user_id ]= isset($opt['dafen'] )? $this->countScoreItemUnderBook( $bookScore,$book_user[$user_id],$book ,$opt['dafen']  ) : $this->countScoreItem( $bookScore,$book_user[$user_id],$book );
        }
        return $rz ;
        //$this->drExit( $rz );
    }

    /**
     * 计算分数 （分书分类取完成、取最高）思路
     * - 1.tag_id=0 讨论的分数=0.6*完成分数+0.4*帖子自身分数
     * - 2.完成分数=60*发帖完成+40*回帖完成
     * - 3.按书 分别去求出6大类分数的数组
     * - 4.按书 取6大数组每分类的最好的成绩
     * - 5.按书 $daFen权重 得到最终成绩
     * - 5.1 权重仅考虑本书中的安排的任务
     * - 6.先在完成中以最终最高成绩取的最终成绩+本书的分类成绩
     * - 7.若全部未完成则 取在未为完成中以最终最高成绩取的最终成绩+本书的分类成绩
     * @param $bookScore
     * @param $book_user
     * @param $book
     * @param $daFen 打分权重配置
     * @return array
     */
    function countScoreItemUnderBook( $bookScore , $book_user ,$book ,$daFen ){
        //$this->drExit( $bookScore );
        $wanscheng= count( $bookScore );
        $book_tag_score= [];
        foreach( $bookScore as $book_id =>$v ){
            $dvar  = $this->getProgress( $book[$book_id ],$book_user[ $book_id][0] );
            //$wc_score=  60*$dvar[0]['progress']+40*$dvar[1]['progress'];
            $book_tag_score[$book_id ]['book_id']=$book_id ;
            $book_tag_score[$book_id ]['ctime']= $book_user[ $book_id][0]['ctime']  ;

            foreach( $dvar as $v2  ) { #每个类别
                $book_tag_score[$book_id ]['over']=0 ;
                if( $v2['progress']<100) { $wanscheng--;  break;    }
                $book_tag_score[$book_id ]['over']=1 ;
            }
            $tag_sim = $tag_score= [];
            foreach( $v as $tag_id=>$tar_var ){
                foreach($tar_var as $v3 ){
                    if($tag_id==0 )  $v3['score']= $this->getDiscussScore($dvar,$v3['score'],['book_id'=> $book_id ] );//0.6*$wc_score+0.4* $v3['score'];
                    if(  $v3['score']>=0  )$tag_score[ $tag_id][]= $v3['score'];
                    #大于30%相似
                    if(  intval($v3['sim']) >3000  )$tag_sim[ $tag_id][]= intval($v3['sim']) ;
                }
            }
            $sim =$score=[];
            foreach( $tag_score as $tag_id=>$sc )   $score[$tag_id]= max( $sc )/100;
            foreach( $tag_sim as $tag_id=>$sc )    $sim[$tag_id ] =max($sc )/100;
            $daFen_book =$daFen;
            $this->dafenWithBook($daFen_book, $book[$book_id ] )->countLastScore( $score , $daFen_book );
            $book_tag_score[$book_id ]['score']=$score ;
            $book_tag_score[$book_id ]['sim']= $sim ;
            $book_tag_score[$book_id ]['book_user']= $book_user[ $book_id][0] ;
        }
        $fun = function ( $a,$b ){
            $big = -1;
            if( $a['over'] >$b['over']  ) return $big;
            if( $a['over'] <$b['over']  ) return -$big;
            if( $a['score']['last'] == $b['score']['last']  ) return 0;
            if( $a['score']['last'] >$b['score']['last']  ) return $big;
            return -$big;
        };
        usort( $book_tag_score,$fun );
        //$this->drExit( $book_tag_score );

        if( $this->need_tetail )
            return ['wc_cnt'=>$wanscheng<0?0:$wanscheng,'score'=> $book_tag_score[0]['score'],'ctime'=> intval( $book_tag_score[0]['ctime']),'sim'=>$book_tag_score[0]['sim'] ,'detail'=>$book_tag_score ];
        return ['wc_cnt'=>$wanscheng<0?0:$wanscheng,'score'=> $book_tag_score[0]['score'],'ctime'=> intval( $book_tag_score[0]['ctime']) ,'sim'=>$book_tag_score[0]['sim'] ];
    }

    function getDiscussScore($dvar, $score, $opt=[]){
        if( isset( $opt['book_id']) && $this->getDiscus2wenbenBookID( $opt['book_id'])) return $score;
        $wc_score=  60*$dvar[0]['progress']+40*$dvar[1]['progress'];
        return (0.6*$wc_score+0.4* $score);
    }

    /**
     * @deprecated 可以考虑 countScoreItemByBook
     * 计算分数 （分类取最高）思路
     * - 1.tag_id=0 讨论的分数=0.6*完成分数+0.4*帖子自身分数
     * - 2.完成分数=60*发帖完成+40*回帖完成
     * - 3.分别去求出6大类分数的数组
     * - 4.取6大数组每分选的最好的成绩
     * @param $bookScore
     * @param $book_user
     * @param $book
     * @return  array
     */
    function countScoreItem( $bookScore , $book_user ,$book   ){
        $wanscheng= count( $bookScore );
        $tag_score= [];
        foreach( $bookScore as $book_id =>$v ){
            $dvar  = $this->getProgress( $book[$book_id ],$book_user[ $book_id][0] );
            //$wc_score=  60*$dvar[0]['progress']+40*$dvar[1]['progress'];
            foreach( $dvar as $v2  ) {
                if( $v2['progress']<100) { $wanscheng--;  break;    }
            }
            foreach( $v as $tag_id=>$tar_var ){
                foreach($tar_var as $v3 ){
                    if($tag_id==0 )  $v3['score']= $this->getDiscussScore( $dvar,$v3['score'] ,['book_id'=> $book_id ] );//0.6*$wc_score+0.4* $v3['score'];
                    if(  $v3['score']>=0  )$tag_score[ $tag_id][]= $v3['score'];
                }
            }

        }
        $score=[];
        foreach( $tag_score as $tag_id=>$sc )    $score[$tag_id]= max( $sc )/100;
        //print_r( $tag_score );
        //$this->drExit(  count( $bookScore )."=".$wanscheng  );

        return ['wc_cnt'=>$wanscheng<0?0:$wanscheng,'score'=> $score ];
    }

    /**
     * 获得用户有哪些权限
     * @param $book
     * @param array $opt
     * @return array
     */
    function checkUser( $book, $opt=[] ){
        $re= ['create'=>0,'bookAdmin'=>0 ,'schoolAdmin'=>0 ,'sysAdmin'=>0 ];
        if( $book['user_id']== $this->user_id ) $re['create']=1;
        $re['bookAdmin']= intval($opt['join']['type']);
        //是不是学校管理员
        if( isset($opt['cookie']['attr']) ){            //'cookie'=>$this->getLogin()->getCookUser()
            $admin=['p3'=>1 ];
            foreach($opt['cookie']['attr'] as $key ){
                if( isset($admin[$key] )){
                    $re['schoolAdmin']=1;
                    if( ! $re['bookAdmin'] )  $re['bookAdmin']='p3';
                }
            }
        }
        return $re;
    }

    /**
     * 通过 topic_id 检查当前用户有哪些权限
     * @param $topic_id
     * @return $this
     */
    function checkUserByTopicId( $topic_id ){
        $topic = $this->getTopicById( $topic_id );
        $book = $this->getBookById( $topic['book_id']);
        $join= $this->getJoinBook( $topic['book_id'] );
        $login = new login();
        $checkUser = $this->checkUser($book,['join'=>$join,'cookie'=>$login->getCookUser()]);
        if( $checkUser['create'] || $checkUser['bookAdmin']|| $checkUser['schoolAdmin']) return $this;
        $this->throw_exception( "无权操作",7057);
    }

    /**
     * 通过isbn号获取ISBN, 如果isbn只是字符串则为单条，如果为数组返回 book_isbn 为key的数组
     * @param $isbn
     * @return array
     */
    function getBookIsbnByIsbn( $isbn ){
        if( !$isbn ) return false;
        if(!is_array( $isbn )){
            return $this->createSql()->select($this->tb_isbn,['book_isbn'=>$isbn])->getRow();
        }
        unset( $isbn[''] );
        if( !$isbn ) return false;
        return $this->createSql()->select($this->tb_isbn,['book_isbn'=>$isbn])->getAllByKey('book_isbn');
    }

    function getBookIsbnByBookList( $bookList ){
        $isbn=[];
        foreach ($bookList as $bk ){
            $key = trim( $bk['book_isbn'] );
            if( $key) $isbn[$key]= $key;
        }
        if( ! $isbn ) return [];
        return $this->getBookIsbnByIsbn( $isbn );
    }




    /**
     * 增加或者修改ISBN的内容
     * @param $isbn
     * @param $opt
     * @return $this
     */
    function addUpdateBookIsbn( $isbn, $opt){
        $isbn= trim($isbn);
        if( !$isbn) $this->throw_exception( "ISBN号不允许为空！",7059);
        if( !$opt || !is_array($opt )) $this->throw_exception( "ISBN号不允许为空！",7060);
        $row = $this->getBookIsbnByIsbn( $isbn);
        foreach($opt as $k=>$v ){
            if( strpos($k,'tag_') !== false ) continue;
            if(trim( $v)=='') unset( $opt[$k]); #不止为了解决什么问题
        }
        $file= ['novel_id', 'book_isbn','book','book_info','book_writer','book_img','user_id','ctime','book_pdf','book_page','book_word_cnt','press','translator','publishing_time','language','contents','cat','difficult_rank'];
        if( $row ){
            unset($opt['user_id']);
            unset($opt['book_isbn']);
            unset($opt['ctime']);
            if(!$opt ) $this->throw_exception( "ISBN 无修改内容！",7061);
            $this->update( $this->tb_isbn,['isbn_id'=>$row['isbn_id'] ], $opt,$file );
        }else{
            $opt['user_id']= $this->getUserID();
            $opt['ctime']= time();
            $opt['book_isbn']= $isbn;
            $this->insert($this->tb_isbn,$opt,$file );
        }
        return $this;
    }

    /**
     * 将book_isbn中的内容加入到 $book当中来
     * @param array $book
     * @return $this
     */
    function margeBookIsbn( &$book){
        if(!trim($book['book_isbn'])) return $this;
        $book['isbn']=  $this->getBookIsbnByIsbn( $book['book_isbn']);
        return $this;
    }



    /**
     * 将任务量marge到bookList 中
     * @param array $bookList
     * @param array $class_id
     * @return $this
     */
    function margeMyTask( & $bookList, $class_id){
        if( ! $class_id || !is_array($class_id ) ) return $this;
        //$book_id = $this->getBooksIdBySchool(  $this->getSchool() );
        $book_id =[];
        drFun::searchFromArray( $bookList,['book_id'],$book_id );

        if( !$book_id ) return $this;
        $task = new task( );
        $taskClass = $task->getTaskClass(['task_id'=>$book_id,'type'=>2 ] );
        if(! $taskClass ) return $this;
        $re = [];
        foreach( $taskClass as $v ){
            $key = $v['task_id'];
            if( isset( $book_id[ $v['task_id'] ]) && isset( $class_id[$v['class_id']]) && $v['type']==2 ){
                $re[ $key ][ $v['class_id'] ] = $v['cnt'];
            }
        }
        if( !$re ) return $this;
        foreach ( $bookList as $k=>$v ){
            if(! isset( $re[$v['book_id']] )){
                //$bookList[$k]['task']=['cls_cnt'=>0 ,'cnt'=>0 ];
                continue;
            }
            $tem = $re[$v['book_id']];
            $bookList[$k]['task']=['cls_cnt'=>count( $tem),'cnt'=> array_sum( $tem)];
        }
        //arsort( $re );
        $fun = function ( $a, $b){
            return $a['task']['cnt']<$b['task']['cnt']?1:-1;
            //return $a['book_id']<$b['book_id'];
        };
        uasort( $bookList, $fun );
        //$this->displayJson( $bookList );
        return $this;
    }

    /**
     * 根据勾选的任务来生成导读
     * @param $book
     * @return $this
     */
    function getDaodu( &$book ){

        $str='<div>请同学们完成以下学习任务：</div>';

        $str.='<div class="dd-item">1. 讨论。请针对本书内容发起'.$book['user_topic'].'个主题，如果你的主题足够有趣，一定会吸引更多同学来参与讨论。';
        $str.='同时你也需要积极参与讨论其他同学发起的主题至少'.$book['user_comment'].'次。</div>';
        $k=2;
        if($book['tag_3_config']>0 )      $str.='<div class="dd-item">'.($k++).'. 朗读。请选择你认为比较合适的段落朗读并上传对应的文字和语音。坚持朗读能够提高发音的准确性，培养语言的韵律感，进一步完善你的各项语言技能。</div>';
        if($book['tag_4_config']>0 )      $str.='<div class="dd-item">'.($k++).'. 期中。请在读这本的过程中撰写一篇期中总结，按照主题或章节归纳已读完部分的核心内容，培养你归纳、分析与整合信息的能力。</div>';
        if($book['tag_5_config']>0 )      $str.='<div class="dd-item">'.($k++).'. 期末。请在期末时撰写一篇读书报告，在总结本书主要内容的基础上发表自己反思性的感想或见解，培养自己的思辨能力。</div>';

        $book['book_daodu']='<div id="detail-daodu">' .$str.'</div>';

        return $this;
    }

    /**
     * 跨学校判断 禁止跨学校
     * @param $book
     * @param $cookUser
     * @return $this
     */
    function checkIsMySchoolByBook( $book ,$cookUser){
        $school= $book['school'];
        #学校为空或者好策放行
        if( $school=='' ||   $school =='好策' ) return $this;
        #本学校
        if(  $school == $cookUser['school'] )return $this;
        #管理员
        $login = new login();
        if( $login->isAdmin() )return $this;

        $this->throw_exception( "暂时不支持跨学校", 7062);
    }

    /**
     * 退选，已选择的某本书。满足条件：一般用户+未参与任何任务
     * @param $join_id
     * @return $this
     */
    function delJoinBookById( $join_id , $opt=[]){
        if($join_id<=0 )$this->throw_exception( "参数错误！", 7063);
        $row = $this->createSql()->select( $this->tb_book_user,['id'=>$join_id])->getRow();
        if(! $row )$this->throw_exception( "已经退选 或者 不存在！", 7064);
        if( $row['type']!=0 ) $this->throw_exception( "你在此书不是一般用户，还含有管理任务无法退选！", 7065);
        if(! ( $opt['is_admin']) ) {
            if ($row['user_id'] != $this->getUserID()) $this->throw_exception("无法退选他人！", 7066);
            if( $row['topic_cnt']>0 ||  $row['comment_cnt']>0	)  $this->throw_exception( "无法退选：你已参与这本书的任务", 7067);
        }
        if( $row['class_id']>0 ) {
            $task =  new task();
            $task->countTaskClass( $row['book_id'],  $row['class_id'] ,2);
        }
        $this->createSql()->delete( $this->tb_book_user,['id'=>$join_id ])->query();
        $this->countBookUser( $row['book_id'], 0 );
        drFun::recycleLog( $row['user_id'], 203, $row );
        return $this;
    }

    /**
     * 获取本书单中的班级列表 用于任课老师显示自家班级的用户讨论
     * @param $book_id
     * @param $my_class_id
     * @return array
     */
    function getTaskClass( $book_id, $my_class_id ){
        $task = new task();
        $taskClass= $task->getTaskClass(['book_id'=>$book_id,'type'=>2 ]);
        //$re = [];
        $my_taskClassID=[];
        if( $taskClass ) {
            foreach ($taskClass as $v) {
                $key = $v['class_id'];
                if (isset($my_class_id[$key])) $my_taskClassID[$key] = $key;
            }
        }
        return ['class'=>$taskClass,'my'=>$my_taskClassID  ];
    }

    /**
     * 更新机器分
     * @param $topic_id
     * @param $score
     * @return $this
     */
    function updateTopicScore( $topic_id, $score){
        if(  $topic_id<=0) $this->throw_exception('topic_id error',7077 );
        $score= intval($score*100+0.5);
        if($score<0 ) $score =-1;
        $this->update( $this->tb_topic, ['topic_id'=>$topic_id ],['score'=>$score]);
        return $this;
    }

    /**
     * 更新相似度
     * @param $topic_id
     * @param $sim
     * @return $this
     */
    function updateTopicSim($topic_id,$sim ){
        $score= $sim;
        if( $score>0 )  $score= intval($score*100+0.5);
        $this->update( $this->tb_topic, ['topic_id'=>$topic_id ],['sim'=>$score]);
        return $this;
    }

    /**
     * @return array
     */
    function updateBookIsbnPL(){
        $bookArr = $this->createSql()->select( $this->tb_book,['book_isbn'=>''],[],['book_id','book'])->getColByKeyFile('book','book_id');
        $haoce = $this->createSql()->select($this->tb_book,['school'=>'好策','!='=>['book_isbn'=>''] ], [],[ 'book','book_isbn'])->getAllByKey('book');

        $up = [];
        $no=[];
        $sql = [];
        foreach($bookArr as $book=>$book_id_arr  ){
            if( isset( $haoce[$book])){
                $up[]= ['name'=>$book,'id'=>$book_id_arr ];
                $this->createSql()->update($this->tb_book,['book_isbn'=>$haoce[$book]['book_isbn'] ],['book_id'=>$book_id_arr] )->query();
            }else{
                $no[]= ['name'=>$book,'id'=>$book_id_arr ];
            }
        }

        return ['up'=>$up,'no'=>$no  ]; //,'haoce'=>$haoce,'bookArr'=>$bookArr ,'sql'=>$sql
    }

    /**
     * 通过book_user获取班级列表
     * @param $book_id
     * @return array
     */
    function getClassListByBookID( $book_id ){
        $sql ="select class_id, count(*) as cnt ,sum(topic_cnt) as topic_cnt ,sum(comment_cnt) as comment_cnt  ,sum(tag_3_cnt) as tag_3_cnt  ,sum(tag_4_cnt) as tag_4_cnt ";
        $sql .=",sum(tag_5_cnt) as tag_5_cnt ";
        $sql .=",sum(tag_6_cnt) as tag_6_cnt ";
        $sql .=" from  ". $this->tb_book_user . " where book_id='".$book_id."' group by class_id";
        $list = $this->createSql($sql)->getAllByKey( 'class_id');
        return $list;
    }

    function formatTopic2Es( $topic ){
        $time = $topic['mtime'] ?$topic['mtime'] : $topic['ctime'];
        $key= $topic['topic_id'].'_'. $time;

        $str = drFun::line(  ['index'=>['_id'=> $key ]] );
        $var = [];
        $var['topic_id'] = $key ;
        $var['book_id'] = intval(  $topic['book_id'] );
        $var['user_id'] = intval(  $topic['user_id'] );
        $var['tag_id'] = intval(  $topic['tag_id'] );
        $var['ctime'] = date("c",$time );
        $var['title'] = $topic['topic'];
        $var['content'] = $topic['topic_info'];
        $str.= drFun::line( $var );
        return $str;
    }

    /**
     * 实例一个 bookOpt
     * @return bookOpt
     */
    function createBookOpt(){
        if(! $this->book_opt ){
            $this->book_opt = new  bookOpt( $this );
        }
        return $this->book_opt;
    }

    /**
     * 获取难度bookId
     * @param $nd
     * @return array
     */
    function getNanduBookIds( $nd  ){
        if( $nd<2 && $nd>10 ) $this->throw_exception( '难度参数错误！',7069 );
        $school= $this->getSchool();
        $bookIsbn= $this->createSql()->select( $this->tb_book,['school'=>$school,'type'=>0 ],[],['book_isbn','book_id'])->getAllByKeyArr(['book_isbn']);
        unset( $bookIsbn[''] );
        if(! $bookIsbn ) $this->throw_exception( "本校书刊未标难度", 7072);
        //
        $where = ['book_isbn'=>array_keys( $bookIsbn)];
        if( $nd<=2 ){
            $where['<']=    ['difficult_rank'=>$nd+1];
        }else{
            $where['difficult_rank']=  [$nd,$nd-1 ];
        }
        $book_isbn  = $this->createSql()->select( $this->tb_isbn, $where,[],['book_isbn'] )->getCol();

        if(! $book_isbn) $this->throw_exception( "哎呦！该难度下未找到相关书刊", 7070);
        $bookid =[];
        foreach($book_isbn as $isbn ){
            foreach($bookIsbn[$isbn]  as $v)$bookid[]= $v['book_id'];
        }
        return $bookid;
    }

    /**
     * 获取指导老师
     * @param $book_id
     * @param array $bookJoin
     * @param array $opt
     * @return array
     */
    function getBookTeacherAdmin($book_id,$bookJoin=[],$opt=[] ){
        #指导管理员
        $book_admin = $this->getBookUser( $book_id,['type'=>1 ]);

        if( $bookJoin   and $bookJoin['class_id']>0 ){
            $cl_cls= new cls( $bookJoin['user_id'] );
            $class_info = $cl_cls->getClassAndStudent( $bookJoin['class_id'] );
            $this->assign('class_info',$class_info );
            #将任课老师加进来
            $cl_cls->margeClassRole(  $class_info['class'] )->margeRoleToBookAdmin($class_info['class'],  $book_admin );
        }
        if( !$book_admin && isset( $opt['user_id']) ) $book_admin[]=['user_id'=> $opt['user_id']];
        return $book_admin;
    }

    /**
     * 批量生成提醒ID 2017/10/14 未考虑夸学期学生
     * @param int $school_id
     * @return array
     */
    function createSmsTixingBySchoolId( $school_id ){
        $school = $this->getBookSchoolById( $school_id );

        #获取学校配置
        $cl_term = new term(  );
        $term_config = $cl_term->getConfigForUser( $school_id,'now');
        $this->assign('term_config', $term_config );

        $str ='您好，我校在好策读书（haoce.com）的选书时间将于'.date('Y年m月d日H时', $term_config['s_end_time']). '截止，请务必于此时间前尽快选书，以免影响期末成绩【好策】';
        if( time()>$term_config['s_end_time']  ) $this->throw_exception("哎啊！截止日期都过了",7076);

        //$this->drExit( $school );
        $uid_arr  = $this->createSql()->select('user', ['school'=>$school['school'],'ts'=>2 ],[],['user_id','user_id'] )  ->getCol2(); //

        if(! $uid_arr ) $this->throw_exception("本学校无学生",7075);
        $book_user_uid = $this->createSql()->select($this->tb_book_user, ['user_id'=>$uid_arr ],[],['user_id'])->getCol();
        $total = count($uid_arr );
        foreach( $book_user_uid as $user_id ) unset( $uid_arr[$user_id]);
        if(! $uid_arr ) $this->throw_exception( "共有 " .$total . " 位同学全部都选过书了！" ,7077 );

        $mobiles = $this->createSql()->select('user_oauth',['user_id'=>$uid_arr,'from'=>2 ],[],['user_id','openid'])->getCol2();
        //if( !$mobiles ) $this->throw_exception( "共有 " .$uid_arr . " 位同学全部都选过书了！" ,7077 );
        $cl_sms = new sms( );
        $cl_sms->sms_wait($mobiles, $str);
        return ['num'=> count($mobiles )];
    }

    function _initBook( &$book, &$book_opt ){
        $book['tag_4_config']= 0;
        $book['user_topic']= 1;
        $book['user_comment']= 5 ;
        return $this;
    }

    /**
     * 获取任务 描述
     * @param $book
     * @param $tag_id
     * @param $book_opt
     * @return array|string
     */
    function getTaskStr( $book , $tag_id, $book_opt ){
        $task=[];
        $task[4]='任务：请在读这本的过程中撰写一篇期中总结，按照主题或章节归纳已读完部分的核心内容，培养你归纳、分析与整合信息的能力。';
        $task[5]='任务：请在期末时撰写一篇读书报告，在总结本书主要内容的基础上发表自己反思性的感想或见解，培养自己的思辨能力。';
        $task[6]='任务：请摘抄出书中让你印象深刻的句子或段落，并附上自己的见解与感受。';
        $task[3]='任务：请选择你认为比较合适的段落朗读并上传对应的文字和语音。坚持朗读能够提高发音的准确性，培养语言的韵律感，进一步完善你的各项语言技能。';
        $task[0]='任务：请针对本书内容发起'. $book['user_topic'] .'个主题，如果你的主题足够有趣，一定会吸引更多同学来参与讨论。同时你也需要积极参与讨论其他同学发起的主题至少'.$book['user_comment'].'次。';
        if( $tag_id ==='all'){
            foreach($task as $id =>$v  ){
                if(  $book_opt['tag_'.$id] )  $task[$id]= nl2br($book_opt['tag_'.$id]);
            }
            return $task;
        }
        if(  $book_opt['tag_'.$tag_id] ) return nl2br($book_opt['tag_'.$tag_id]);
        return $task[ $tag_id];
    }

    function tjClassDiscuss( &$list ){
        foreach ( $list as &$v){
            if(  !isset(  $v['class_id']) ) continue;
            $class_id = $v['class_id'];
            $v['tj']= $this->tjClassDiscussByClassID( $class_id );
        }
        return $this;
    }
    function tjClassDiscussByClassID( $class_id ){
        $re = ['topic_cnt'=>0 ,'comment_cnt'=>0 ,'tag_3_cnt'=>0 ,'tag_4_cnt'=>0  ,'tag_5_cnt'=>0  ,'tag_6_cnt'=>0  ];
        if( $class_id<=0 ) return $re ;
        $sql = "select ";
        foreach( $re as $k=>$cnt ) $sql .=" sum(`".$k."`) as ".$k.",";
        $sql= trim( $sql ,',')." from `book_user`  where class_id='".$class_id."' ";
        $re2  = $this->createSql($sql)->getRow();
        if(! $re2) return $re ;
        return $re2 ;
    }

    /**
     * 复制一本书去布置
     * @param $copy_book_id
     * @param $user_id
     * @param $school
     * @param array $opt
     * @return $this
     */
    function copy($copy_book_id,$user_id,$school ,&$opt=[]){
        if($copy_book_id<=0 ||$user_id<=0|| $school==''  ) $this->throw_exception( "初始化参数错误！",7090 );

        $row = $this->createSql()->select( $this->tb_book,['book_id'=> intval($copy_book_id)])->getRow();
        if( ! $row) $this->throw_exception( "书本不存在", 7001 );
        $unset = ['book_id', 'topic_cnt','comment_cnt','discuss_cnt' ,'tag_3_cnt','tag_4_cnt','tag_5_cnt','tag_6_cnt'];
        foreach($unset as $k )unset($row[ $k ] );

        if( $opt['is_check_school'] ){
            $cnt = $this->createSql()->getCount( $this->tb_book,['school'=> $school,'book_isbn'=>$row['book_isbn']  ] )->getOne();
            if( $cnt>0)    $this->throw_exception( "书本层复制过，请勿重复！", 7049 );
        }

        $row['user_id']=  $user_id;  $row['school']=  $school;   $row['ctime']=  time();
        $row['type']=  0;
        $row['user_cnt']= 1;
        $term = new term();
        $row['term_key']= $term->getNow(-1 );

        drFun::arrExtend($row,$opt );
        $opt['book_id'] = $this->insert( $this->tb_book, $row);
        return $this ;
    }


    function langdu_suiji(){
        $tall = $this->createSql()->select($this->tb_topic, ['tag_id'=>3,'score'=>1],[],['topic_id','score','tag_id'])->getAll();
        //$this->drExit( $tall );
        foreach( $tall as $v ){
            //$v['score']= rand(6000,8999);
            $this->createSql()->update($this->tb_topic,['score'=> rand(6000,8999) ],['topic_id'=>$v['topic_id'] ] )->query();//update( $this->tb_topic,['topic_id'=>$v['topic_id']])
            //$this->drExit( $sql );
        }

        return count($tall);
    }

    function margeBookCntToList( &$list ,$term_key ){
        $re=[];
        drFun::searchFromArray($list,['user_id'] , $re );
        unset( $re[0]);
        if( !$re ) return $this;
        $book_ids = $this->createSql()->select( $this->tb_book,['school'=>$this->getSchool(),'term_key'=>$term_key],[],['book_id'])->getCol();
        if( ! $book_ids ) return $this;
        $tall = $this->createSql()->select( $this->tb_book_user, ['book_id'=> $book_ids, 'user_id'=> $re ],[],['user_id','book_id'] )->getAllByKeyArr(['user_id']);

        foreach ( $list as &$v){
            $v['book_cnt']= isset( $tall[$v['user_id']] )? count( $tall[$v['user_id']] ):0;
        }
        return $this;

    }

    function getTjNovelViewWhere($book_id ,&$where ){
        $book= $this->getBookById( $book_id );
        if( !$book['isbn']['novel_id'] ) $this->throw_exception("本书未绑定阅读！",7095);
        $where['novel_id']=  $book['isbn']['novel_id'] ;
        $user = $this->createSql()->select( $this->tb_book_user,['book_id'=>$book_id],[],['user_id'])->getCol();
        if(! $user ) $this->throw_exception("本书未无用户选书！",7096);
        $where['user_id']= $user;
        return $this;
        //return $this->createSql()->select( $this->tb)
    }

}
