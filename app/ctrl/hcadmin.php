<?php
/**
 * 好策后台管理
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/29 0029
 * Time: 下午 12:51
 */

namespace ctrl;


use model\drException;
use model\drFun;
use model\drTpl;
use model\lib\cache;
use model\lib\excel;
use model\lib\sms;
use model\logRecycle;
use model\test;
use model\user;

class hcadmin extends drTpl
{
    function init()
    {
        parent::init();
        if(! $this->checkEditorPre() ) parent::checkAdminByCookie();
        $this->assign('is_super',  $this->isSuperAdmin() );
        if( ! $this->isSuperAdmin() ) $this->drExit("身份错误！");
        //$this->assign('is_super', 1 );
        $this->htmlFile="hcadmin.phtml";

        if (!$this->getLogin()->checkGoogleLogin()) {
            $this->redirect("/google", '请先输入谷歌验证码');
        }

        if( !$this->getLogin()->checkIpSign() ) $this->drExit("您的IP有变更，请重新登录！");

        $http_host= $_SERVER['HTTP_HOST'];
        if( ! in_array($http_host, [ 'hc.atbaidu.com','qunfu.zahei.com']) ) $this->drExit('404');
        if( $this->getLogin()->getUserId()==4 && in_array($http_host, [ 'hc.atbaidu.com']) ) $this->drExit("HI  Fuck!");
        //$this->getLogin()->checkIpSafe();
        try {
            $this->getLogin()->checkIpSafe();
        }catch (drException $ex ){
            $this->drExit($ex->getMessage() );
        }

    }

    /**
     * 编辑在本文件内需要的权限
     * @return bool
     */
    function checkEditorPre(){
        try{
            parent::checkEditorByCookie();
            $act = strtolower( self::$_URI['act'] );
            $in_arr = ['novel'=>1,'snt'=>1,'bookschool'=>1 ];
            if(   isset( $in_arr[$act]) ) return true;
        }catch ( drException $ex ){
        }
        return false;
    }
    function act_know( $pvar){
        $case= $pvar[0];
        switch ($case){
            case 'test':
                $imknow = [
                    [ ['cat'=>'词汇','info'=>'词汇说明'],['cat'=>'动词','info'=>'动词说明'],['cat'=>'情态','info'=>'情态说明','code'=>'TEST01']]
                    ,[ ['cat'=>'词汇','info'=>'词汇说明'],['cat'=>'动词','info'=>'动词说明'],['cat'=>'BE','info'=>'BE动词说明','code'=>'TEST02']]
                    ,[ ['cat'=>'词汇','info'=>'词汇说明'],['cat'=>'名称','info'=>'动词说明'],['cat'=>'BE','info'=>'BE动词说明','code'=>'TEST03']]
                    ,[ ['cat'=>'词汇','info'=>'词汇说明'],['cat'=>'名称','info'=>'动词说明'],['cat'=>'人民','info'=>'人民说明','code'=>'TEST04']]
                    ,[ ['cat'=>'语法','info'=>'语法说明'],['cat'=>'主谓一致','info'=>'动词说明'],['cat'=>'主语','info'=>'主语词说明','code'=>'TEST05']]
                ];
                $re = $this->getLogin()->createKnow()->imKnows( $imknow );
                $this->drExit( $re );
                break;
            case 'modify':
                $know_cat = $this->getLogin()->createKnow()->getKnowCatByCatId( intval($_GET['id']));
                $this->assign('cat', $know_cat);
                $this->tplFile= 'know_modify';
                break;
            default:
                $know  = $this->getLogin()->createKnow()->getAllKnowsToTree(   );
                $this->assign('know',$know );
                break;
        }
    }

    /**
     * 用户列表的各种显示
     * @param $p
     */
    function act_userList( $p ){
        if( $_GET['export']=='excel'){
            set_time_limit(0);
            ini_set('memory_limit','512M');
        }
        $sq= ['name'=>'姓名','email'=>'邮箱','tel'=>'手机'];


        $where=[];
        switch ( $p[0] ){
            case 'search':
                $where= $this->getLogin()->createUser()->getWhere($_GET);
                break;
            case 'ts':
                user::getTypeTs( intval($p[1] ));
                $where['ts']=  intval($p[1] );
                $this->assign('ts_key', $p[1]);
                break;
            case 'pre':
                $this->assign('pre_key', $p[1]);
                $attr= $this->getLogin()->createUser()->getUsersByAttr( $p[1]);
                if( !$attr ) $this->redirect( $this->getReferer(),'该条件不存在用户');
                $where= ['user_id'=> array_keys($attr )];
                break;
        }
        if( trim($_GET['school'])!=''){
            $where['school']= trim($_GET['school']);
        }
        if( !$where ) $where = '1';
        $order= ['user_id'=>'desc'];
        if(isset($_GET['order']) && $_GET['order']) $order= [ $_GET['order']=>'desc' ] ; 

        $list = $this->getLogin()->createUser()->getUserListWithPage( $where ,10 ,$order) ;//1,10

        if( $list['list'] ){
            $this->getLogin()->createUser()->margeOauth($list['list'] )->margeAttr( $list['list'] );
            $bookID='all';
            if( trim($_GET['school']) && trim($_GET['term_key']) ){
                $bookID= $this->getLogin()->createClassBook()->getBooksIdBySchool(  trim($_GET['school']), 0,['term'=>$_GET['term_key'] ]);
                //$this->drExit( $bookID );
            }
            //if( $bookID ) $this->getLogin()->createClassBook()->tjUserRz( $list['list'] ,$bookID);
        }

        $this->assign('userlist', $list );
        $this->assign('sex', user::getTypeSex());
        $this->assign('ts', user::getTypeTs());
        $this->assign('attr_type', user::getKeyAttrAll());
        $pre= user::getKeyAttrPre();
        if(! $this->isSuperAdmin() ) unset($pre['p1']);
        $this->assign('pre_type',$pre);
        $this->assign('sq', $sq );

        if( $_GET['export']=='excel'){
            $this->exportUser();
        }

    }

    /**
     * 导出用户
     */
    function exportUser(){

        $ex= new  excel();
        $that = $this;
        $fun= function ( &$head,&$list) use($that ){
            $head=['name'=>'姓名','account'=>'账号','school'=>'学校','ts'=>'身份','number'=>'学号' ,'ctime'=>'注册','last_time'=>'最后登录',"book_cnt"=>'选书',
                "topic_cnt"=>'发帖',
                "comment_cnt"=>'回复',
                "tag_3_cnt"=>'朗读',
                "tag_4_cnt"=>'期中',
                "tag_5_cnt"=>'期末',
                "tag_6_cnt"=>'摘抄'
            ];
            $userlist = $that->getAssign('userlist');
            $ts= $that->getAssign('ts');
            foreach ($userlist['list'] as $v ){
                $tem_var= ['name'=>$v['name'],'account'=>$v['oauth'][0]['openid'],'school'=>$v['school'],'ts'=>$ts[$v['ts']]];
                $tem_var['ctime']=  $v['ctime']? date("Y-m-d H:i:s", $v['ctime']):'-';
                $tem_var['last_time']= $v['last_time']?  date("Y-m-d H:i:s", $v['last_time']):'-';
                $tem_var['number']= $v['number'] ;
                $tem_var['user_id']= $v['user_id'] ;
                foreach( $v['tjrz'] as $k2=>$v2 )$tem_var[$k2]= $v2 ;

                $list[]= $tem_var;
            }
            unset($userlist );
            if($_GET['is_school_user']=='1' && $_GET['school_id']){
                $this->withSchoolUser( $head,$list , $_GET['school_id'] );
            }elseif( isset( $_GET['is_school_user'])  ){
               $this->withClassAndTeacher( $head,$list , $_GET['school_id'] );
            }

        };
        $ex->saveWithFun( $fun  );
    }

    /**
     * 导出学生的班级和任课老师
     * - 学生有可能加入多个班级 从class_student中筛选出最后一次加入的班级
     * - 有这个班级去找任课老师 如果无任课老师class_role 其中一个， 直接建立班级的老师
     * @param $head
     * @param $list
     * @param $school_id
     * @return $this
     */
    function withClassAndTeacher( &$head, &$list, $school_id ){
        if( !$list ) return $this;
        $head['class']='班级';
        $head['teacher']='任课老师';

        $uarr = [];
        foreach ( $list as $v )$uarr[ ] = $v['user_id'];
        $student_class = $this->getLogin()->createClassCls()->getLastStudentClassByUid( $uarr );
        //$this->drExit( $student_class );
        if( ! $student_class ) return $this;
        $cls = [];
        foreach ($student_class as $v  )$cls[ $v['class_id']] = $v['class_id'];

        $class_list = $this->getLogin()->createClassCls()->getClassList(  array_keys($cls) );
        $role = $this->getLogin()->createClassCls()->getRoleByClassID(  array_keys($cls)  );
        $user = $this->getLogin()->createUser()->getUserFromArray([$class_list, $role] );
        foreach($list as &$v){
            $uid = $v['user_id'];
            $cid = $student_class[ $uid]['class_id'];
            $v['class'] = $class_list[$cid ]['class'];
            $v['teacher'] = $this->getRenkeTeacher($user, $class_list[$cid ],$role[$cid] );
        }
        return $this ;
    }

    private  function getRenkeTeacher( $user, $class,$role ){
        //$this->drExit( $user );
        if( !$role && !$class ) return '';
        if( ! $role ) return $user[$class['user_id'] ]['name'];
        $str ='';
        //$this->drExit( $role );
        foreach($role as $v  ){
           $str.=   $user[$v['user_id'] ]['name'].',';
        }
        return trim($str,',');
    }

    /**
     * 写入白名单
     * @param $head
     * @param $list
     * @param $school_id
     * @return $this
     */
    function withSchoolUser( &$head, &$list, $school_id){
        $head['teacher']='老师';
        $head['class']='班级';
        $number=[];
        foreach( $list as $v ){
            $number[ $v['number']]= $v['number'];
        }
        if(! $number ) return $this;
        $var = $this->getLogin()->createTerm()->getSchoolUserByNumber( array_keys($number),$school_id );
        unset( $number );
        foreach($list as &$v){
            $key =  $v['number'];
            if( isset($var[$key] ) ){
                if( $v['user_id']!= $var[$key]['user_id']) continue ; #当uid不一样 就不要 防止一位同学注册2个账号
                $v['teacher']=$var[$key]['teacher'];
                $v['class']=$var[$key]['class'];
            }
        }
        return $this;
    }

    function checkSuperAdmin(){

        if(! $this->isSuperAdmin() ) $this->throw_exception("权限不足,请用超级管理！" );
    }

    function isSuperAdmin(){
        $ckuser  = $this->getLogin()->getCookUser();
        if( !in_array("p1",$ckuser['attr'] )) return false ;

        return in_array( $this->getLogin()->getUserId(),[2142,4] );


    }

    /**
     * 用户操作：批量导入、单个添加、删除openid、修改属性、编辑等
     * @param $p
     */
    function act_user( $p ){
        switch ($p[0]){
            case 'clearGoogle':
                $uid= intval($p[1]);
                //$this->drExit( $_POST );
                $this->getLogin()->createQrPay()->toTelegram($uid, "【管理员清除谷歌】\n管理员正在清除你的谷歌，记得请重新设置");
                $this->getLogin()->createQrPay()->toTelegram(4,"【清楚谷歌】\nUID:". $uid);
                $this->getLogin()->createUserOne()->checkGoogle( $_POST['code'] );
                $this->getLogin()->createUserOne( $uid)->up_info([ 'google'=>'' ]);
                $opt_value="清谷歌：来之后台 ". $this->getLogin()->getUserId() ."   IP:".drFun::getIP() ;
                $this->getLogin()->createLogGt()->append( $uid,3, $opt_value);

                $this->redirect( '','谷歌验证码已经清空！');
                break;
            case 'implode':
                $file = $_FILES['file'];
                $re = [];
                $this->getLogin()->createUser()->imUserFromExcel( $file['tmp_name'],$re );

                $this->assign('rz',$re );
                $this->assign('files',$_FILES );
                $this->assign('file','456/fdddd' );
                break;
            case'openid_del' :
                $this->checkSuperAdmin();
                $this->getLogin()->createUser()->delOpenidByID( intval($p[1]));
                $this->redirect($this->getReferer(),'删除成功！');
                break;
            case 'op':
                $this->checkSuperAdmin();
                $this->getLogin()->createUserOne( intval($p[1]) )->opAttr( $_POST['attr'] );
                $this->redirect("",'操作成功！');
                break;
            case 'recycleList':
                $type = $this->getLogin()->createLogRecycle()->getType( );
                $this->assign('type', $type );
                $this->assign('book_tag', $this->getLogin()->createClassBook()->getTagId());
                unset( $type[202]);
                $this->assign('list',  $this->getLogin()->createLogRecycle()->getList(  intval( $p[1]) , ['decode'=>true,'where'=>['opt_type'=> array_keys($type)]]  ) );
                break;
            case 'recycleBack':
                $this->assign('post', $_POST);
                $log = new logRecycle( $this->getLogin()->getUserId() );
                $log->back( $_POST );
                break;
            case 'attr':
                //user::getKeyAttr()
                $this->getLogin()->createUserOne( intval($p[1]) )->opAttr( $_POST['attr'] );
                //$this->assign('post', $_POST );
                $this->redirect("",'操作成功！');
                break;
            case 'edit_info':
                //$this->assign('post',$_POST);

                $this->getLogin()->createUserOne( intval($p[1]) )->up_info( $_POST );
                $this->redirect("",'修改成功！');
                break;
            case 'edit_psw':
                $ma_uid=  intval($p[1]);
                $this->getLogin()->createQrPay()->toTelegram($ma_uid, "【管理员重置密码】\n管理员正在重置您的密码");
                $this->getLogin()->createQrPay()->toTelegram(4,"【重置密码】\nUID:". $ma_uid);
                $this->getLogin()->createUserOne()->checkGoogle( $_POST['code'] );
                $this->getLogin()->createUserOne(  $ma_uid )->up_psw( trim($_POST['passwd']) );

                $opt_value="修改密码：来之后台 ". $this->getLogin()->getUserId() ."   IP:".drFun::getIP() ;
                $this->getLogin()->createLogGt()->append( $ma_uid,3, $opt_value);
                $this->redirect("",'修改成功！');
                break;
            case 'bind':
                $this->getLogin()->createUserOne( intval($p[1]) )->bindOauth( trim($_POST['openid']) );
                $this->redirect("",'绑定成功！');
                break;
            case 'add':
                $this->tplFile="user_add";
                break;
            case 'gt': #沟通记录
                //$this->assign('post',$_POST );
                $opt_value =trim( $_POST['opt_value']);
                if( !$opt_value ) $this->throw_exception("沟通记录不允许为空！");
                $this->getLogin()->createLogGt()->append( intval($p[1]),3, $opt_value);
                $this->redirect($this->getReferer(),'添加成功！');
                break;
            case 'reg': #单个添加
                $login = new user\login();
                $new_user_id = $login->reg( $_POST,['noYzm'=>1 ] );
                $this->getLogin()->createLogGt()->append( $new_user_id,2 );
                if(  $new_user_id) $this->getLogin()->sendRegPsw( $_POST['openid'], $_POST['password'],$_POST );
                $this->redirect('hcadmin/userList','添加成功！');
                break;
            case "edit":
            default:
                $this->assign('me', $this->getLogin()->createUserOne( intval($p[1]))->getALl() );
                $this->assign('from_type', user::getTypeOauthFrom());
                $this->assign('pre_type', user::getKeyAttrPre());

                $this->assign('reback', $this->getReferer() );
                ///$this->assign('attr_type', user::getKeyAttr());

                $gt_list=  $this->getLogin()->createLogGt()->getListWithPage(  intval($p[1]),['where'=>['between'=>['opt_type'=>[1,150] ] ] ]  );
                $this->assign('gt_list',$gt_list);
                $this->assign('user',$this->getLogin()->createUser()->getUserFromArray($gt_list['list'] ));

                $this->assign('gt_action',R('hcadmin/user/gt/'. intval($p[1]) ) );
                $this->tplFile="user";
        }

    }

    function regSchoolUser( $ename ,$school){
        $ename = trim($ename );  $school= trim($school );
        $open_id = $ename.'@haoce.com';
        $u_row = $this->getLogin()->createUser()->getUserOauth( $open_id, 1 );
        if( !$u_row ) {
            $rvar = ['openid' => $open_id, 'name' => $school, 'ts' => 3, 'school' => $school, 'password' => 'hc123456'];
            $new_user_id = $this->getLogin()->reg($rvar, ['noYzm' => 1]);
            //$this->getLogin()->createLogGt()->append( $new_user_id,2 );
            if ($new_user_id) {
                $this->getLogin()->sendRegPsw($rvar['openid'], $rvar['password'], $rvar);
                $this->getLogin()->createUserOne($new_user_id)->opAttr(['p3' => 1]);
            }
        }else{
           $new_user_id= $u_row['user_id'];
        }
        $book_id_arr = [126, 493,424 ,57,53,50,487];
        $this->copyBook($book_id_arr  ,$new_user_id,$school  );
        return $this;
    }
    function copyBook($book_id_arr ,$new_user_id,$school ){
        foreach($book_id_arr as $book_id ){
            try{
                $opt=['is_check_school'=>1 ];
                $this->getLogin()->createClassBook()->copy( $book_id ,$new_user_id,$school, $opt );
            }catch ( drException $ex ){ }
        }
        return $this;
    }

    /**
     * 读书 学校管理
     * @param $p
     */
    function act_bookSchool($p){

        switch ($p[0]){
            case 'add':
                $this->assign('action',R('hcadmin/bookSchool/add_post'));
                $this->tplFile='book_school_from';
                break;
            case 'initSchool':
                $school = $this->getLogin()->createClassBook()->getBookSchoolById( $p[1] );

                try {
                    $books = $this->getLogin()->createClassBook()->getBooksIdBySchool($school['school']);
                }catch ( drException $exception){ }
                if( count($books)>=7  )  $this->throw_exception( "已初始化过！");

                $this->regSchoolUser( $school['school_ename'] , $school['school']);
                $this->redirect("","初始化成功！");
                break;
            case 'add_post':
                //$file = ['logo'=>['n'=>'logo'],'school'=>['n'=>'学校名称'] ,'school_slogan'=>['n'=>'校训'],'school_ename'=>['n'=>'校域名'] ];
                //$this->getLogin()->insert( 'book_school', $_POST, $file);
                $this->getLogin()->createClassBook()->addBookSchool( $_POST );
                $this->regSchoolUser( $_POST['school_ename'] ,$_POST['school']);
                $this->redirect( R('hcadmin/bookSchool'),'添加成功！');
                break;
            case 'edit':
                $school = $this->getLogin()->createClassBook()->getBookSchoolById( $p[1] );
                $this->assign('action',R('hcadmin/bookSchool/edit_post/'. $p[1] ));
                $this->assign('school', $school);
                $this->tplFile='book_school_from';
                $this->assign('gt_action',R('hcadmin/bookSchool/gt/'. intval($p[1]) ) );
                $this->assign('gt_opt',['row'=>8,'placeholder'=>'放一些会议纪要' ]);

                $gt_list=  $this->getLogin()->createLogGt()->getListWithPage(  intval($p[1]),['where'=>['between'=>['opt_type'=>[101,110] ] ] ]  );
                $this->assign('gt_list',$gt_list);
                $this->assign('user',$this->getLogin()->createUser()->getUserFromArray($gt_list['list'] ));

                break;
            case 'gt':
                $opt_value =trim( $_POST['opt_value']);
                if( !$opt_value ) $this->throw_exception("沟通记录不允许为空！");
                $this->getLogin()->createLogGt()->append( intval($p[1]),101, $opt_value);
                $this->redirect($this->getReferer(),'添加成功！');
                break;
            case 'edit_post':
                //$school = $this->getLogin()->createClassBook()->getBookSchoolById( $p[1] );
                //$this->getLogin()->update( 'book_school',['id'=>$p[1]],$_POST,['logo','school','school_slogan','school_ename' ]);
                $this->getLogin()->createClassBook()->editBookSchool($p[1],$_POST  );
                $this->redirect( R('hcadmin/bookSchool'),'修改成功！');
                break;
            case 'pl_sms':
                $this->checkSuperAdmin();
                $school_id =  intval($_GET['school_id']);
                $re= $this->getLogin()->createClassBook()->createSmsTixingBySchoolId( $school_id );
                $this->getLogin()->createLogGt()->append( $school_id ,102, "共生成<span style='color: red'>" .$re['num'].'条待发短信</span>(选课截止)');
                $this->redirect( "","共生成".$re['num']."条待发短信，请联系程序员启动！");
                break;
            case 'edit_show':
                $is_show = intval($p[2]);
                $id = intval($p[1]);
                $this->getLogin()->createClassBook()->getTypeSchoolShow( $is_show );
                $this->getLogin()->createClassBook()->editBookSchool( $id,['is_show'=>$is_show ]);
                $this->redirect( "","编辑成功！");
                break;

            case 'edit_tpl':
                $is_show = intval($p[2]);
                $id = intval($p[1]);
                $this->getLogin()->createClassBook()->getTypeSchoolTpl( $is_show );
                $this->getLogin()->createClassBook()->editBookSchool( $id,['tpl'=>$is_show ]);
                $this->redirect( "","编辑成功！");
                break;

            default:
                $opt=[];
                if(isset($_GET['school']) && trim($_GET['school'])!='' ) $opt['where']=['school'=>trim($_GET['school'])  ];
                elseif(  trim($_GET['is_show'])!=''){
                    $is_show= intval( $_GET['is_show'] );
                    $this->getLogin()->createClassBook()->getTypeSchoolShow( $is_show );
                    $opt['where']['is_show']= $is_show;
                }else{
                    //$opt['where']['is_show']= 1;
                }
                $bookSchool= $this->getLogin()->createClassBook()->getBookSchoolListWithPage( $opt );
                $this->assign('bookSchool', $bookSchool )
                    ->assign('term_now', $this->getLogin()->createTerm()->getNow(-1))
                    ->assign('tpye_tpl',$this->getLogin()->createClassBook()->getTypeSchoolTpl() )
                    ->assign('type', $this->getLogin()->createClassBook()->getTypeSchoolShow());

                $school_ids = [];
                drFun::searchFromArray($bookSchool,['id'] , $school_ids  );
                //$this->assign( 'term', $this->getLogin()->createTerm()->getSchoolTermBySchoolIDs($school_ids) );

                #统计学校用户 以后可能会非常慢
                if( $bookSchool['list'] ){
                    $schoolArr= [];
                    drFun::searchFromArray($bookSchool['list'],['school'], $schoolArr );
                    $this->assign('userCount', $this->getLogin()->createUser()->getUserCountGroupBySchool( $schoolArr));
                }
                #end
        }
    }

    /**
     * 读书书单管理 书单列表
     * @param $p
     */
    function act_bookList( $p ){
        $sq=['school'=>'学校','book'=>'书名'];
        $this->assign('sq',$sq);
        switch ($p[0]){
            case 'search':
                if( trim($_GET['school'])=='') $this->throw_exception( "学校不为空！");
                $where=['school'=>trim($_GET['school']) ];
                break;
            case 's':
                $k =trim($_GET['type']);
                if(!isset($sq[$k])) $k='school';
                if($k=='book'){
                    $where=['like'=>['book'=>'%'.trim($_GET['q']).'%'] ];
                }else{
                    $where=[$k=> trim($_GET['q'])];
                }
                break;
            case 'uid':
                $where=['user_id'=>$p[1] ];
                break;
            case 'type':
                $where['type']= intval($p[1] );
                break;
            case 'pdf':
                $where['book_pdf']= '';
                break;
            default:
                $where=['>'=>['type'=>-2]];
        }
        //$this->drExit( $where );
        $this->assign('p',$p);
        if(  trim( $_GET['term'] ) )  $where['term_key']=  trim( $_GET['term'] );
        if( !$where)  $where= "1";
        //$list = $this->getLogin()->createClassBook()->getBookListWithPage( $where ,10,['book_id'=>'desc']);
        $list = $this->getLogin()->createClassBook()->getBookListWithPage($where, 10, ['book_id' => 'desc']);
        $this->assign('list',$list );
        $this->assign('bookIsbn',$this->getLogin()->createClassBook()->getBookIsbnByBookList( $list['list']) );
        $this->assign('user', $this->getLogin()->createUser()->getUserFromArray( $list ,['user_id'] ));
        $this->assign('bookType', $this->getLogin()->createClassBook()->getTypeBook( ))
            ->assign('term_key', trim( $_GET['term'] ) )
            ->assign('termList', $this->getLogin()->createTerm()->getConfig())
            ->assign('term_now', $this->getLogin()->createTerm()->getNow() );
        if( $_GET['export']) $this->getLogin()->createExport()->bookList() ;
    }

    /**
     * 单本书管理：新增、修改单本书
     * @param $p
     */
    function act_book( $p ){
        $this->tplFile='book';
        $this->assign('action', R('book/book/add'));
        switch ($p[0]){
            case 'edit':
                $book_id = $p[1];
                $book = $this->getLogin()->createClassBook()->getBookById( $book_id );
                $this->assign('book_opt',$this->getLogin()->createClassBook()->createBookOpt()->getOpt( $book_id ) );

                $this->assign('book',$book);
                $this->assign('action', R('book/book/modify/'. $book_id));
                break;
            case 'add':
                $this->assign('action', R('book/book/add'));
                break;
            case 'admin':
                $book_id= $p[1];
                $book = $this->getLogin()->createClassBook()->getBookById( $book_id );
                $this->assign('book',$book);
                $this->assign('book_user_type',$this->getLogin()->createClassBook()->getTypeBookUser());
                $book_user = $this->getLogin()->createClassBook()->getBookUser( $book_id,['type'=>1 ,'limit'=>[]]);
                $this->assign('book_users',$book_user );
                $this->assign('user',$this->getLogin()->createUser()->getUserFromArray($book_user));
                $this->tplFile = "book_admin";
                break;
            case 'edit_type':
                $book_id= $p[1];
                $book = $this->getLogin()->createClassBook()->getBookById( $book_id );
                $this->getLogin()->createClassBook()->updateBookType( $book_id, intval($p[2]));
                $this->redirect("",'修改成功！');
                break;
            case 'admin_add':
                $book_id= $p[1];
                $uids= $_POST['uids'];
                $tarr = explode("\n", $uids );
                foreach( $tarr as $uid){
                    $uid= intval($uid);
                    if($uid<=0) continue;
                    $this->getLogin()->createClassBook()->setUserId( $uid)->joinBook( $book_id,1, $uid );
                }
                $this->redirect("",'添加成功！');
                break;

            case 'admin_del':
                $book_id= $p[1];
                $uid= $p[2];
                $this->getLogin()->createClassBook()->setUserId( $uid)->joinBook( $book_id,0, $uid );
                $this->redirect("",'删除成功！');
                break;

            case 'edit_term':
                $book_id= $p[1];
                $book = $this->getLogin()->createClassBook()->getBookById( $book_id );
                $this->getLogin()->createClassBook()->bookModify($book_id,['term_key'=> trim(  $p[2])] );
                $this->redirect("",'修改成功！');
                break;

        }
        $this->assign('tags', $this->getLogin()->createClassBook()->getTagId( ))
            ->assign('termList', $this->getLogin()->createTerm()->getConfig())
            ->assign('term_key', $book['term_key'] ? $book['term_key'] :$this->getLogin()->createTerm()->getNow());
    }

    function act_novel( $p ){
        switch ( $p[0] ){
            case 'analyze':
                $this->getLogin()->createNovel()->analyzeAll() ;
                $this->redirect("",'分析成功！');
                break;
            case 'saveChapter':
                $this->getLogin()->createNovel()->updateChapterByID($_POST['chapter']['cp_id'],$_POST['novel']['site'],$_POST['chapter']  );
                $this->redirect("","修改成功！");
                break;
            case 'wordCount':
                $this->getLogin()->createNovel()->updateChapterWordByNovel( $p[1] );
                $this->redirect($this->getReferer(),"更新成功！");
                break;
            case 'chapter':
                $novel_id = $p[1];
                $novel= $this->getLogin()->createNovel()->getNovelAllById($novel_id ,['can_error'=>1 ]);
                //$this->getLogin()->createNovel()->checkNovelCpNull( $novel['chapter'],$novel['novel']['site'] );

                $novel['ct_arr']= $this->getLogin()->createNovel()->getTypeNeiRong( );
                $novel['yin_arr']=[0=>'不含',1=>'含' ];
                $this->assign('novel', $novel );
                $this->tplFile="novel_chapter";
                break;
            case 'delTag':
                $id= $p[1];
                $this->getLogin()->createTagNovel()->delByID( $id );
                break;
            case 'addTag':
                $novel_id = $p[1];
                $text= trim($_POST['text']);
                $rz = $this->getLogin()->createTagNovel()->addByText( $novel_id,$text );
                $this->assign('rz', $rz );
                break;
            case 'loadTag':
                $novel_id = $p[1];
                $tag = $this->getLogin()->createTagNovel()->getTagByTagID($novel_id );
                $this->assign('tag', $tag );
                break;
            case 'imTag': #批量导入
                if( ! $this->isSuperAdmin() ) $this->throw_exception( "批量导入仅对超级管理员开放！");
                $rz = $this->getLogin()->createTagNovel()->imFromExcel( $_FILES['file']['tmp_name'] );
                $this->assign('rz', $rz );
                //$this->drExit( $_FILES );
                break;
            case 'bind':
                $novel_id = intval($_GET['novel_id']);
                $isbn= trim($_GET['isbn']);
                $this->getLogin()->createClassBook()->addUpdateBookIsbn($isbn,['novel_id'=>$novel_id ] );
                $this->redirect($this->getReferer(),'绑定成功！');
                //$this->assign( )
                break;
            case 'editShow':
                $novel_id = intval(  $p[1]);
                $this->getLogin()->createNovel()->modifyNovelById( $novel_id,['is_show'=>intval(  $p[2]) ]);
                $this->redirect("",'修改成功！');
                break;
            case 'editPost':
                $novel_id = intval(  $p[1]);
                $this->getLogin()->createNovel()->modifyNovelById( $novel_id,$_POST );
                $this->redirect("",'修改成功！');
                break;
            case 'edit':
                $novel_id = intval(  $p[1]);
                $this->assign('novel', $this->getLogin()->createNovel()->getNovelById($novel_id));
                $this->tplFile= 'novel_edit';
                $this->assign('ct_arr', [0=>'无内容',1=>'有内容',2=>'双语']);
                $this->assign('yin', [0=>'不含',1=>'含' ]);
                break;
            case 'addNovel':
                $opt=['user_id'=>$this->getLogin()->getUserId()];
                $this->getLogin()->createNovel()->addNovelFromHaoce($_POST['novel'] ,$opt); // ,['user_id'=>$this->getLogin()->getUserId()]
                $this->redirect($this->getReferer(),'添加成功');
                break;
            case 'addChapter':
                $this->getLogin()->createNovel()->addChapterFromHaoce( intval($p[1]) );
                $this->redirect($this->getReferer(),'添加成功');
                break;
            case 'imgChange':
                $file = $_FILES['file'];
                $this->assign('file', $file );
                if( $file['size']>204800) $this->throw_exception( "请处理图片大小 请小于200KB");
                //$this->drExit( $file  );
                $novel= $this->getLogin()->createNovel()->getNovelById( $p[1]);
                $opt = ['dir'=>'novel/'.trim( $novel['site'])];
                $r = drFun::txUpload($file, $opt);
                $r['f2']= trim($r['file'],'cfsup/'.$opt['dir'] );
                $this->assign('f', $r );
                break;
            case 'saveOrder':
                $this->getLogin()->createNovel()->saveChapterOrder( $_POST['novel_id'], $_POST['site'],$_POST['order']) ;
                $this->getLogin()->createNovel()->updateChapterWordByNovel(  $_POST['novel_id']   );
                $this->redirect("",'排序保存成功！');
                break;
            case 'delChapter':
                $this->getLogin()->createNovel()->delChapter( $_POST['chapter'] ) ;
                $this->redirect("",'删除成功！！');
                break;
            case 'copy':
                if( 101!=$this->getLogin()->getUserId()  ) $this->throw_exception( "该功能仅haoce@haoce.com 支持");
                $this->getLogin()->createNovel()->copyToEnCn( intval($p[1]) ) ;
                $this->redirect("",'中英分离成功！！');
                break;
            case 'gengxin':
                if($_GET['noError']){
                    try{
                        $this->getLogin()->createNovel()->upReadCpWord( $p[1] );
                    }catch ( drException $ex ){ }


                }else                $this->getLogin()->createNovel()->upReadCpWord( $p[1] );
                $this->redirect("",'更新成功！');
                break;
            default:
                $where = [];
                if( trim($_GET['q'])) $where=['like'=>['novel'=>'%'.trim($_GET['q']).'%'] ];
                if( trim($_GET['isbn'])){
                    $book_isbn= $this->getLogin()->createClassBook()->getBookIsbnByIsbn( trim($_GET['isbn']));
                    $this->assign('book_isbn', $book_isbn );
                    if( $book_isbn['novel_id'])   $this->assign('novel_isbn', $this->getLogin()->createNovel()->getNovelById( $book_isbn['novel_id'] ) );
                }
                if(isset($_GET['is_yin'])) $where['is_yin']=1;
                if( isset($_GET['is_shuan']) ) $where['is_shuan']= intval($_GET['is_shuan']);
                if( isset($_GET['is_show']) ) $where['is_show']= intval($_GET['is_show']);

                $this->assign('ct_arr', $this->getLogin()->createNovel()->getTypeNeiRong( ) );
                $list = $this->getLogin()->createNovel()->getListWithPage(['where'=> $where?$where:'1' ] );
                $this->assign('list', $list );
                $this->assign('user', $this->getLogin()->createUser()->getUserFromArray($list) );

                $this->assign('show', [0=>'隐藏',1=>'上架']);
                if( $_GET['export']){
                    $this->getLogin()->createExport()->novelList();
                }

        }
    }

    function act_snt( $p ){
        switch ( $p[0]){
            case 'implode':
                $file = $_FILES['file'];
                $re = [];
                $this->getLogin()->createSnt()->implodeFromExcel( $file['tmp_name'], $re,['user_id'=>$this->getLogin()->getUserId() ] );
                $this->assign('re', $re );
                $this->redirect('','成功');
                break;
            case 'del':
                $id = intval( $p[1]);
                $this->getLogin()->createSnt()->delByID( $id );
                $this->redirect( $this->getReferer(),'删除成功！');
                break;
            default:
                break;
        }
        $list =  $this->getLogin()->createSnt()->selectWithPage();
        $this->assign('list',$list);
        if( $list['list'] ){
            $this->assign('isbn', $this->getLogin()->createClassBook()->getBookIsbnByBookList( $list['list']));
            $this->assign('user',  $this->getLogin()->createUser()->getUserFromArray($list['list']) );
        }


        //$tall = $this->getLogin()->createSnt()->


    }

    /**
     * 登录后首页显示
     */
    function act_main(){
//        $this->assign('tj_user', $this->getLogin()->createUser()->tjUser() );
//        $this->assign('ts_type',user::getTypeTs() );
//        $this->assign('tj_book',$this->getLogin()->createClassBook()->tjAll() );
//        $this->assign('tj_school',$this->getLogin()->createClassBook()->tjBookSchool() );
//        $this->assign('tagName', $this->getLogin()->createClassBook()->getTagId());
//
//        $tj_view = $this->getLogin()->createNovel()->tjViewV2("1",['cache_key'=>'hc_tj_view' ]);
//        $this->assign('tj_view', $tj_view );

        //$this->assign('tj_dict', $this->getLogin()->createNovel()->tjView("1",['tb'=>'dict']) );
    }

    /**
     * 短信列表
     * @param $p
     */
    function act_smsList( $p ){
        $cl_sms = new sms();
        switch ( $p[0]){
            case 's':
                $smsList= $cl_sms->getSmsListWithPage( ['mobile'=>trim($_GET['q'])] );
                break;
            default:
                $smsList= $cl_sms->getSmsListWithPage( );
        }
        $this->assign('sms', $smsList );
        $this->tplFile='sms_list';
    }

    function act_recycleList( $p ){
        $tid = isset($_GET['tid'])? intval($_GET['tid']):204;
        $this->getLogin()->createLogRecycle()->getType( $tid );
        $type=  $this->getLogin()->createLogRecycle()->getType('all');
        $where= ['opt_type'=>$tid] ;
        $list = $this->getLogin()->createLogRecycle()->getAllListWithPage( ['where'=>$where,'order'=>['id'=>'desc'] ] );
        $user = $this->getLogin()->createUser()->getUserFromArray($list['list'] );
        $this->assign('tid', $tid)->assign( 'type', $type)->assign('list', $list )->assign('user', $user );
        $this->tplFile='recycle_list';
    }

    /**
     * 小工具
     * @param $p
     */
    function act_tool( $p ){
        switch ( $p[0]){
            case 'hei':
                try {

                    $this->getLogin()->createRedis()->set('heiIp',trim($_POST['ip'])   );
                    $this->getLogin()->createRedis()->close();
                    //$this->createRedis()->set( );
                    //$this->createRedis()->close();

                }catch ( drException $ex ){
                }
                $this->redirect('','设置成功！');
                break;
            case 'exBookTjByIsbn':
                $head= ['book_isbn'=>'ISBN','book'=>'书名','user_cnt'=>'选书人数', 'topic_cnt'=>'主题','comment_cnt'=>'回复','tag_3_cnt'=>'朗读','tag_4_cnt'=>'期中','tag_5_cnt'=>'期末','tag_6_cnt'=>'摘抄'];
                $tall = $this->getLogin()->createClassBook()->getBookList('1',['limit'=>[],'file'=>array_keys( $head) ] );
                $line=[];
                $key= $head; unset( $key['book_isbn']);unset( $key['book']);
                $head['cnt'] = '布置次数';
                foreach( $tall as $v ){
                    $isbn= trim( $v['book_isbn']);
                    if(! $isbn) continue;
                    if(! isset($line[$isbn]) ) {
                        $v['cnt']=1;$line[$isbn]= $v ; continue;
                    }
                    $line[$isbn]['cnt']++;

                    foreach( $key as $k2=>$v2  ){
                        $line[$isbn][$k2]+= intval($v[$k2]);
                    }
                }
                $fun= function ( $a,$b){ if($a['user_cnt']== $b['user_cnt']) return 0; return $a['user_cnt']< $b['user_cnt']?1:-1;  };
                usort($line,$fun );
                //$this->drExit( $line );
                $ex= new excel();
                $ex->saveByHeadLine( $head,$line );
                break;
            case 'classSchoolUserCnt':
                $re= [];
                $this->getLogin()->createClassCls()->updateClassSchoolUserCntPL( $re );
                $this->redirect("","更新成功！");
                break;
            case 'isbn':
                $this->assign('isbn' , $this->getLogin()->createClassBook()->updateBookIsbnPL() );
                break;
            case 'sms':            #短信语音
                $cl_sms = new sms();
                $re= $cl_sms->getYu( trim( $p[1]) );
                $this->assign('sms', $re );
                $this->redirect("","还剩下".$re['yu']."条");
                break;
            case 'excel_class':
                $arr = drFun::excelReadToArray( $_FILES['file']['tmp_name'] ) ;
                $re=[];
                foreach(  $arr[0]['data'] as $v ){
                    $b = $v['B'];//strtr($v['B'],[' '=>'_']);
                    $re[$v['A']][ $b ] = $b;
                }
                $arr = [];
                foreach( $re as $k=>$v ){
                    $arr[]= ['A'=>$k,'B'=>implode(',', $v )];
                }
                //$this->drExit(  $re );

                $excel = new excel();
                $excel->start()->writeHead(['A'=>'班级','B'=>'老师'])->writeLine( $arr)->save( "class_tool_".date("Ymd_His"));
                break;
            case 'pl_mq':
                $cnt = $this->getLogin()->createClassBook()->plGenxin( );
                $this->redirect("","成功提交".$cnt."条");
                break;
            case 'score_discuss_wen':
                $bid = explode(',', trim( trim($_GET['bid']) ,',')) ;
                $cnt = $this->getLogin()->createClassBook()->discuss2wenbenScore( $bid  );
                $this->redirect("","成功提交".$cnt."条");
                break;
            case 'langdu_suiji':
                $cnt = $this->getLogin()->createClassBook()->langdu_suiji();
                $this->redirect("","成功处理".$cnt."条");
                break;
            case 'ka_school':
                $this->getLogin()->createNovel()->ka_school();
                $this->redirect("",'更新成功！');
                break;
            case 'school_college':
                //$this->assign('post', $_POST );
                $this->getLogin()->createUser()->modifyCollegeDefault($_POST['school'],$_POST['coll']);
                $this->redirect("",'修改成功！');
                break;
            case 'novelViewOne': #阅读记录重复
                $this->getLogin()->createNovel()->tbListOne();
                break;
            case 'viewListMaxID':
                $this->assign('max_id', $this->getLogin()->createNovel()->getViewListMaxID() );
                break;
            default:
                $this->assign('bgcolor','#eee');
                ;
        }
        $st=['ip'=>''];
        try {
            $st['ip']=$this->getLogin()->createRedis()->get('heiIp');
            if(  !$st['ip']  ) $st['ip']='';
            $this->getLogin()->createRedis()->close();
        }catch ( drException $ex ){
        }
        $this->assign('sv', $st );
        $this->tplFile= !isset( $p[0])?'tool':'tool_'.strtolower($p[0]);
        //$this->displayJson(['tpl'=>$this->tplFile ]);
    }

    function act_block($p){
        switch ($p[0]){
            case 'add':
                $this->getLogin()->createBlock()->addBlockName($_POST['block'],$_POST['school']);
                $this->redirect( $this->getReferer(),"增加成功！");
                break;
            case 'modify':
                $this->getLogin()->createBlock()->modifyBlockName(intval($p[1]),['block'=> $_POST['block'] ]);
                $this->redirect( '',"修改成功！");
                break;
            case 'del':
                $this->getLogin()->createBlock()->delNameByID(intval($p[1])  );
                $this->redirect( '',"删除！");
                break;
            case 'type':
                $this->getLogin()->createBlock()->modifyBlockName( intval($p[1]),['type'=> intval($p[2]) ]);
                $this->redirect( '',"修改成功！");
                break;
            case 'edit':
                $this->getLogin()->createBlock()->modifyBlockName( intval($p[1]),[ trim($p[2]) => trim($p[3]) ]);
                $this->redirect( '',"修改成功！");
                break;
            default:
                $this->assign('list',$this->getLogin()->createBlock()->getNameListWithPage( '1' ) );
                $this->assign('school', $this->getLogin()->createClassBook()->searchSchoolFromArray( $this->getAssign('list') ) )
                ->assign('blockType', $this->getLogin()->createBlock()->getTypeBlockName());
                $cl_block= $this->getLogin()->createBlock();
                $this->assign('attr',['icon'=> $cl_block->getTypeIcon(),'bg_color'=>$cl_block->getTypeBgColor()
                    ,'is_class'=>$cl_block->getTypeIsClass() ,'hv'=>$cl_block->getTypeHv(),
                ]);
                $this->tplFile='block';
        }
    }

    function act_fqa( $p ){
        $where=['school_id'=>0 ];
        switch ($p[0]) {
            default:
                $where['cat_id']=10;
                $this->tplFile='fqa';
                break;
        }
        $this->assign('where', $where);
    }

    function act_tool2( $p ){

        $test = new test();
        switch ($p[0]){
            case 'payLogCF':
                $test->payLogCF() ;
                $this->redirect( '',"成功删除！");
                break;
        }
    }

    function checkPayType($pmid,$pay_type, $me_mid  ){
        //$where=[];
        //$where['or']=['merchant_id'=>$pmid ];
        /*
        $str_name = $this->getLogin()->createQrPay()->getPayTypeFromUser( $pay_type );
        $row = $this->getLogin()->createTableMerchant()->getRowByWhere( ['merchant_id'=>$pmid,'pay_type'=>$pay_type ] );
        if( $row && $row['merchant_id']!= $me_mid) $this->throw_exception($str_name. " 付费方式已经存在！", 19102303);
        $row = $this->getLogin()->createTableMerchant()->getRowByWhere( ['pid'=>$pmid,'pay_type'=>$pay_type ] );
        if( $row && $row['merchant_id']!= $me_mid) $this->throw_exception($str_name. " 付费方式已经存在！", 19102304);
        */
        $this->getLogin()->createQrPay()->checkPayType( $pmid,$pay_type, $me_mid );
        return $this;
    }

    function act_merchant( $p ){

        $this->drExit("开发中！");

        $where=[];
        switch ($p[0]){
            case 'modify':
                $this->assign('post',$_POST);
                $mid= intval($p[1]);
                $merCh = $this->getLogin()->createQrPay()->getMerchantByID($mid );
                $var = $_POST;
                unset( $var['app_id']);
                unset( $var['app_secret']);
                unset( $var['merchant_id']);

                $var['merchant']= trim($_POST['merchant']) ;
                $var['user_id']= intval($_POST['user_id']);
                if(  $var['user_id']<=0 || !$var['merchant']    ) $this->throw_exception("参数错误！");
                if( $merCh['pid']>0  )  $this->checkPayType(   $merCh['pid'] ,$var['pay_type'] ,   $mid );

                $var['fa_fee']= drFun::yuan2fen($_POST['fa_fee']);
                if( $var['fa_fee']<0 ) $var['fa_fee']=600;

                $var['rate']=  intval($_POST['rate']);
                if( $var['rate']<0 ) $var['rate']=0;

                $this->getLogin()->createTableMerchant()->modifyBYKey($mid, $var);
                $this->redirect("", "修改成功！");
                break;
            case 'cuser':
                $user= $this->getLogin()->getVersionBYConsole('all');//$this->getLogin()->createUser()->getUsersByAttr(  ['p3']  );
                //$this->drExit($user);
                if( !$user) $this->throw_exception("不存在操作，请添加");
                $user= $this->getLogin()->createUser()->getUserFromUid(  ($user ));
                //$this->drExit( $user);
                $this->assign('cuser', $user);
                break;
            case 'create':
                $var= $_POST;
                //$var=['merchant_id'=>intval($_POST['merchant_id']), 'merchant'=>trim($_POST['merchant'])];
                $var['merchant_id']=intval($_POST['merchant_id']);
                $var['merchant']= trim($_POST['merchant']) ;
                $var['user_id']= intval($_POST['user_id']);

                if(  $var['user_id']<=0 || !$var['merchant']  ||  $var['merchant_id']<=0  ) $this->throw_exception("参数错误！");

                if( $var['pid']>0) $this->checkPayType(  $var['pid'],$var['pay_type'] ,  $var['merchant_id']);

                $var['fa_fee']= drFun::yuan2fen($_POST['fa_fee']);
                $var['rate']=  intval($_POST['rate']);
                if( $var['fa_fee']<0 ) $var['fa_fee']=600;
                if( $var['rate']<0 ) $var['rate']=0;
                $var['ctime']= time();
                $var['app_id']=  drFun::rankStr(8);
                $var['app_secret']=  drFun::rankStr(32 );
                $this->getLogin()->createTableMerchant()->append( $var);
                //$this->drExit($_POST);
                $this->assign('merchant',$var);
                if( $var['pid']>0 ){
                    $cnt= $this->getLogin()->createTableMerchant()->getCount(['pid'=>$var['pid'] ] );
                    $this->getLogin()->createTableMerchant()->updateByKey( $var['pid'], ['child_len'=>$cnt ] );
                }
                $this->redirect("", "添加成功！");

                break;
            case 'search':
            default:
                if( $_GET['pageno']<=1 ) $this->getLogin()->createQrPay()->tongBUFromLoginConfig();

                if( is_numeric(trim($_GET['q'])) ){
                    $where['or']=[['pid'=>intval($_GET['q'])],['merchant_id'=>intval($_GET['q'])]  ];
                    //$this->drExit( $where);
                }

                $mlist = $this->getLogin()->createTableMerchant()->selectWithPage(  $where?$where:'1',['merchant_id'=>'desc'],30, ['type','merchant_id','merchant','user_id','ctime','fa_fee','rate','c_user_id','pay_type','pid','child_len']);
                $this->getLogin()->createUser()->merge($mlist['list'] );
                $this->getLogin()->createUser()->merge($mlist['list'],['key'=>['c_user_id' ],'m_name'=>'cuser'] );
                $this->assign('mlist',$mlist );
                $this->tplFile="mcadmin";
                break;
        }
        $type=['version'=>$this->getLogin()->versionType(), 'mid2cuid'=> $this->getLogin()->midConsole() ,'cuid2version'=> $this->getLogin()->getVersionBYConsole('all') ];
        $type['pay']= $this->getLogin()->createQrPay()->getPayTypeFromUser( );

        $this->assign('type', $type);

    }






}