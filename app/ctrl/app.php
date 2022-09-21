<?php
/**
 * App 相关的操作
 * User: Administrator
 * Date: 2017/10/7
 * Time: 19:55
 */

namespace ctrl;


use model\daily;
use model\drFun;
use model\drTpl;
use model\user\one;

class app extends drTpl
{
    function act_login( $p ){
        $switch = $p[0];
        $HC_uid=0;
        if( $this->getLogin()->isLogin() ){
            $user_id = $this->getLogin()->getUserId();
            $HC_uid = intval( $_REQUEST['HC_uid']);
        }
        if($switch=='post' || $HC_uid<=0 || $user_id !=$HC_uid ) {
            $duser = $this->getLogin()->loginByPsw( trim( $_REQUEST['openid']),trim(  $_REQUEST['psw']) );
            $user_id =  $duser['user_id'];
        }

        $this->userConfig( $user_id );

        switch ($switch) {
            case 'post':
                $this->redirect( "" , "欢迎『" .$duser['name'].'』回来');
                break;
        }
    }

    function userConfig( $user_id){
        $one = new one( $user_id );
        $user = $one->getALl();  unset( $user['user']['psw'] ); unset( $user['user']['slat'] );
        if( isset($user['user']['head']) ) $user['user']['head']= 'http://cdn.haoce.com/'. trim( $user['user']['head'],'/' );
        $this->assign('user',  $user );
        $this->assign('shouye', $this->shouye($user ) );
        $this->assign( 'time', time() );
    }
    /*
    function act_config( $p ){
        $type = $p[0];
        switch ($type){
            case 'shouye':
                $this->shouye();
                break;
        }
    }*/
    function act_bookOne($p ){
        $type= $p[0];
        switch ($type){
            case 'detail':
                $book_id= intval( $p[1] );
                $book= $this->getLogin()->createClassBook()->getBookById($book_id );
                $this->getLogin()->createClassBook()->checkIsMySchoolByBook( $book, $this->getLogin()->getCookUser() );
                $bookJoin= $this->getLogin()->createClassBook()->getJoinBook( $book_id) ;
                $this->assign('bookJoin', $bookJoin );
                $tags = $this->getLogin()->createClassBook()->getTagId( );
                $this->tagsNull( $tags, $book);
                $this->assign('tags',$tags );
                drFun::cdnImg($book,['book_img'] ); //,'book_pdf'


                $this->assign('book', $book );

                #指到老师
                $book_admin = $this->getLogin()->createClassBook()->getBookTeacherAdmin($book_id,$bookJoin,['user_id'=>$book['user_id']]  );
                $this->assign('book_admin', $book_admin );
                $user= $this->getLogin()->createUser()->getUserFromArray([$book,$book_admin],['user_id'] );
                drFun::cdnImg($user,['head'] );
                $this->assign('user',$user );

                $book_opt =  $this->getLogin()->createClassBook()->createBookOpt()->getOpt($book_id );
                $this->assign('book_opt', $book_opt );
                $this->assign('task',  $this->getLogin()->createClassBook()->getTaskStr($book, 'all' ,$book_opt ) );
                break;
            case 'topicList':
                $book_id=  intval( $p[1] );
                $tag_id=  intval($_POST['tag_id']);
                $opt=['tag_id'=>$tag_id ];
                $opt['every']= 10 ;
                if (trim( $_POST['sort'])=='cnt') $opt['order']=['comment_cnt'=>'desc'];
                $topic_list = $this->getLogin()->createClassBook()->getTopicListWithPage($book_id, $opt);
                $this->getLogin()->createClassBook()->topic_info_decode_list( $topic_list['list']);
                $this->assign('topic_list', $topic_list);
                $user= $this->getLogin()->createUser()->getUserFromArray([ $topic_list],['user_id'] );
                drFun::cdnImg($user,['head'] );
                $this->assign('user',$user );
                break;
        }
    }

    function tagsNull( &$tags , $book ){
        foreach( $tags as $k=>&$v ){
            if( $k==0 ){
                $v['cnt']= $book['topic_cnt'];
            }elseif( !$book['tag_'.$k.'_config']){
                unset( $tags[$k]);
            }else{
                $v['cnt']= $book['tag_'.$k.'_cnt'];
            }
            $v['tag_id']= $k;
        }
    }
    function act_test(){
        $this->htmlFile= "mui-app.phtml";
    }
    function act_page($p){
        $cdn = drFun::getCdn();//$_SERVER['SERVER_ONLINE']=='qqyun' ?'https://cdn.haoce.com':'';
        //qf.zahei.com:443
        $host= $_SERVER['HTTP_HOST'];
        //$host= strtolower($host, strtr($host,[':443'=>'']));
        $is_qf= in_array($host,['vip.easepm.com','vip.easepm.com:443','cl.xyxy521.com:443', 'vip.qmailq.com','vip.qmailq.com:443','cl.xyxy521.com','vip.xyxy521.com:443', 'vip.xyxy521.com', 'w.zahei.com', 'qunfu.zahei.com','wo.atbaidu.com:443','wo.atbaidu.com']  );
        $this->assign('hc_app',$cdn.'/res');
        $this->assign('local_cdn',$cdn.'/res');//$this->isPlus()?'_www':
        $this->assign('version','2019111101');
        $this->assign('local_version','?v=2019111101');
        $this->assign('p', $p );

        if( $this->getLogin()->isLogin() ){
            $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $this->getLogin()->getUserId() );
            $ma['pf']= $this->getLogin()->createVip()->getPf(  $ma['c_user_id'] ) ;
            $this->assign('ma',$ma )->assign('s2','s2');
        }


        switch ($p[0]){
            case 'main':
                $this->htmlFile= "app/main.phtml";
                break;
            default:
                if( !$is_qf ) $this->drExit('404');

                $this->htmlFile= "app/".$p[0].".phtml";
        }
    }

    function  isPlus(){
        $u_str = $_SERVER['HTTP_USER_AGENT'];
        return strpos( $u_str,'Html5Plus');
    }

    function act_daily( $p ){
        switch ($p[0]) {
            case 'del':
                $id = intval($p[1]);
                $this->getLogin()->createDaily()->delByID( $id );
                break;
            case 'upload':
                $this->getLogin()->createDaily()->upload( $_FILES['file'], $_POST );
                break;
            case 'list':
                $daily = isset($p[1])?$p[1] : '2017-11-07';
                $list = $this->getLogin()->createDaily()->getListWithPage( $daily );
                if($list['list']) {
                    $listMe= [];
                    if( intval($_GET['pageno'])<=1 && 5==$this->getLogin()->getUserId()  ){
                        //$listMe =  $this->getLogin()->createDaily()->getListMe( $daily );
                        //$this->assign('listMe', $listMe );
                    }
                    drFun::cdnImg( $list['list'],['file'] , $this->getLogin()->getCookUser('uid')==5?'txcos':'http' );
                    $user = $this->getLogin()->createUser()->getUserFromArray([$list['list'], $listMe]);
                    drFun::cdnImg($user ,['head'] );
                    $this->assign('users',  $user );
                }
                $this->assign('list', $list );

                break;
            case 'listMe':
                $daily = isset($p[1])?$p[1] : '2017-11-07';
                $listMe = $this->getLogin()->createDaily()->getListMe( $daily)  ;
                $this->assign('listMe', $listMe );
                $user = $this->getLogin()->createUser()->getUserFromArray($listMe['list']);
                drFun::cdnImg($user ,['head'] );
                $this->assign('users',  $user );
                break;
        }
    }
    function act_js( $p ){
        switch ($p[0]){
            case 'preload':
                $this->htmlFile="app/preoload.js";
                break;

        }

        $this->displayJs();
    }
    function shouye( $user ){
        $hots ='http://'. $_SERVER['HTTP_HOST'];
        $img=[];
        $img[]=['img'=>'http://cdn.haoce.com/res/mui/img/b1.png']; //,'url'=>'https://www.baidu3.com/'
        $img[]=['img'=>'http://cdn.haoce.com/res/mui/img/b2.jpg' ];
        $img[]=['img'=>'http://cdn.haoce.com/res/mui/img/b3.jpg' ];
        //$this->assign('img', $img);
        $main=[];

        if( $user['user']['school']=='安徽建筑大学城市建设学院' ){
            $main[] = ['title' => '我的读书', 'url' => 'app/page/bookMeV2', 'id' => 'bookMeV2', 'cls' => 'mui-icon icon-tb-addressbook', 'open_type' => 'http_native', 'style' => 'color:#feb32b; '];
            $main[]= ['title'=>'荫桐阅读','url'=>'app/page/novelListV2?block_id=101','id'=>'novelListV2','cls'=>'mui-icon icon-tb-shop','open_type'=>'http_native'  ,'title_type'=>'transparent' , 'style' => 'color:#ff4582; ' ];
            $main[] = ['title' => '天天朗读', 'url' => 'app/page/dailySentence', 'id' => 'daily_sentence', 'cls' => 'mui-icon icon-tb-we', 'open_type' => 'http_native', 'title_type' => 'transparent'];
        }elseif( $user['user']['user_id']==10 ) {
            $main[] = ['title' => '我的读书', 'url' => 'app/page/bookMe', 'id' => 'bookMe', 'cls' => 'mui-icon icon-tb-addressbook', 'open_type' => 'http_native', 'style' => 'color:#feb32b; ']; //
            $main[] = ['title' => '校书刊', 'url' => 'app/page/bookSchool', 'id' => 'bookSchool', 'cls' => 'mui-icon icon-tb-home', 'open_type' => 'http_native', 'style' => 'color:#ff4582; '];
            $main[] = ['title' => '天天朗读', 'url' => 'app/page/dailySentence', 'id' => 'daily_sentence', 'cls' => 'mui-icon icon-tb-we', 'open_type' => 'http_native', 'title_type' => 'transparent'];
            $main[] = ['title' => '我的追美', 'url' => 'app/page/bookMeV2', 'id' => 'bookMeV2', 'cls' => 'mui-icon icon-tb-friendfamous', 'open_type' => 'http_native' ];
            $main[]= ['title'=>'追美阅读计划','url'=>'app/page/novelListV2','id'=>'novelListV2','cls'=>'mui-icon icon-tb-shop','open_type'=>'http_native'  ,'title_type'=>'transparent' , 'style' => 'color:#00bdff; ' ];



        }elseif('西南林业大学'== $user['user']['school'] ){
            $main[] = ['title' => '我的读书', 'url' => 'app/page/bookMe', 'id' => 'bookMe', 'cls' => 'mui-icon icon-tb-addressbook', 'open_type' => 'http_native', 'style' => 'color:#feb32b; ']; //
            $main[] = ['title' => '校书刊', 'url' => 'app/page/bookSchool', 'id' => 'bookSchool', 'cls' => 'mui-icon icon-tb-home', 'open_type' => 'http_native', 'style' => 'color:#ff4582; ']; //,'is_cache'=>true
            $main[] = ['title' => '天天朗读', 'url' => 'app/page/dailySentence', 'id' => 'daily_sentence', 'cls' => 'mui-icon icon-tb-we', 'open_type' => 'http_native', 'title_type' => 'transparent'];
            $main[]= ['title'=>'读书show','url'=>'app/page/novelListV2?block_id=103','id'=>'novelListV2','cls'=>'mui-icon icon-tb-shop','open_type'=>'http_native'  ,'title_type'=>'transparent' , 'style' => 'color:#00bdff; ' ];
            $main[] = ['title' => '排行榜', 'url' => 'app/page/rank', 'id' => 'rank', 'cls' => 'mui-icon icon-tb-order', 'open_type' => 'http_native', 'title_type' => 'transparent' ,'sub_title'=>'阅读用时排行'];
            $main[] = ['title' => '有声双语阅读', 'url' => 'app/page/novelList', 'id' => 'novelList', 'cls' => 'mui-icon hao-icon-zhongyingwenqiehuan', 'open_type' => 'http_native', 'title_type' => 'transparent'];// ,'style_title'=>'color:green'
            $main[] = ['title' => '经典阅读', 'url' => 'app/page/novelList?is_shuan=3', 'id' => 'novelList', 'cls' => 'mui-icon hao-icon-yuedu', 'open_type' => 'http_native', 'style' => 'font-weight:bold;', 'title_type' => 'transparent'];

        }else {
            $main[] = ['title' => '我的读书', 'url' => 'app/page/bookMe', 'id' => 'bookMe', 'cls' => 'mui-icon icon-tb-addressbook', 'open_type' => 'http_native', 'style' => 'color:#feb32b; ']; //
            $main[] = ['title' => '校书刊', 'url' => 'app/page/bookSchool', 'id' => 'bookSchool', 'cls' => 'mui-icon icon-tb-home', 'open_type' => 'http_native', 'style' => 'color:#ff4582; ']; //,'is_cache'=>true
            $main[] = ['title' => '天天朗读', 'url' => 'app/page/dailySentence', 'id' => 'daily_sentence', 'cls' => 'mui-icon icon-tb-we', 'open_type' => 'http_native', 'title_type' => 'transparent'];
            $main[] = ['title' => '有声双语阅读', 'url' => 'app/page/novelList', 'id' => 'novelList', 'cls' => 'mui-icon hao-icon-zhongyingwenqiehuan', 'open_type' => 'http_native', 'title_type' => 'transparent'];// ,'style_title'=>'color:green'
            $main[] = ['title' => '经典阅读', 'url' => 'app/page/novelList?is_shuan=3', 'id' => 'novelList', 'cls' => 'mui-icon hao-icon-yuedu', 'open_type' => 'http_native', 'style' => 'font-weight:bold;', 'title_type' => 'transparent'];
        }

        if( $user['user']['school']=='黑龙江大学' ) {
            $main[]= ['title'=>'夏令营','url'=>'app/page/novelListV2?block_id=102','id'=>'novelListV2','cls'=>'mui-icon icon-tb-shop','open_type'=>'http_native'  ,'title_type'=>'transparent' , 'style' => 'color:#00bdff; ' ];
            $main[]= ['title'=>'夏令营报名','url'=>'bm','id'=>'bm','cls'=>'mui-icon mui-icon-navigate','open_type'=>'http_native'   , 'style' => 'color:#ff4582; ' ];
        }



        /*
         $book_school = $this->getLogin()->createClassBook()->getBookSchool();
        $diaocha=  $this->getLogin()->createTask()->getDiaoCha( $book_school,['pre'=>'sj_']);
        if($diaocha){
            $main[]= ['title'=>'调查问卷','url'=>'app/page/http','id'=>'http','cls'=>'mui-icon mui-icon-help','is_cache'=>true,'open_type'=>'http_native','style_title'=>'color:red' ,'style'=>'color:#00bdff' ,'href'=>$diaocha['url'] ];
        }
        */


        if( isset($user['attr']['p1']) || isset($user['attr']['p2']) ) $main[]= ['title'=>'统计','url'=>'app/page/hcAdmin','id'=>'hcAdmin','cls'=>'mui-icon icon-tb-cascades','open_type'=>'http_native'  ];
        $testUser= [5=>1,88=>1];
        if( isset( $testUser[$user['user']['user_id']] )  ){
            $main[]= ['title'=>'荫桐读书工程','url'=>'app/page/novelListV2?block_id=102','id'=>'novelListV2','cls'=>'mui-icon icon-tb-shop','open_type'=>'http_native'  ,'title_type'=>'transparent'  ];

            //$main[]= ['title'=>'PDF阅读','url'=>'http://w.haoce.org/res/pdf.js/web/viewer.html?file='.urlencode('/res/Martin_Eden.pdf'),'id'=>'pdf','cls'=>'mui-icon icon-tb-game','open_type'=>'native'  ];//http_
            //$main[]= ['title'=>'PDF DEMO','url'=>'http://w.haoce.org/app/page/pdf','id'=>'pdf_demo','cls'=>'mui-icon icon-tb-game','open_type'=>'native'  ]; //http_
            $main[]= ['title'=>'小说播放','url'=>'app/page/novelChapterOne?web_debug=1','id'=>'novelChapterOne','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native','novel_id'=>361 ];//,'is_cache'=>true
            //$main[]= ['title'=>'主题内容','url'=>'app/page/topic','id'=>'topic_test','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native','right'=>'回复' ,'topic_id'=> 148];
            $main[]= ['title'=>'视频播放','url'=>'/app/page/player','id'=>'player_test','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native','title_type'=>'transparent' ];
            //$main[]= ['title'=>'testAD','url'=>'app/page/ad','id'=>'ad_test','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native','right'=>'回复' ];
            //$main[]= ['title'=>'单元测试','url'=>'app/page/test','id'=>'1_test','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native'  ];
            $main[]= ['title'=>'问答任务','url'=>'app/page/wenda','id'=>'wenda','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native' ,'is_cache'=>true, 'novel_id'=>1,'cat_id'=>2,'sub_title'=>'《xi wang zi》' ,'title_type'=>'transparent' ];
            //$main[]= ['title'=>'火车票','url'=>'app/page/huoche','id'=>'huoche','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native'  ];
            $main[]= ['title'=>'翻译','url'=>'app/page/snt','id'=>'snt','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native'  ];

            $main[]= ['title'=>'新首页','url'=>'app/page/sy','id'=>'sy','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native' ,'is_cache'=>true, 'title_type' => 'transparent'  ];

            //$main[]= ['title'=>'划词测试','url'=>'app/page/test_tap','id'=>'test_tap','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native'  ];
            $main[]= ['title'=>'分页','url'=>'app/page/readerFull?web_debug=1','id'=>'reader','cls'=>'mui-icon icon-tb-profile','open_type'=>'http_native' ,'title_type'=>'transparent'  ];//,'title_back'=>false


            //$main[]= ['title'=>'上传头像','url'=>'_www/html/head_img.html','id'=>'head_img','cls'=>'mui-icon icon-tb-profile','open_type'=>'native'  ];
            //$main[]= ['title'=>'本地test','url'=>'_www/html/test.html','id'=>'locat_test','cls'=>'mui-icon icon-tb-profile','open_type'=>'native'  ];
            //$main[]= ['title'=>'调查问卷','url'=>'app/page/http','id'=>'http','cls'=>'mui-icon mui-icon-help','is_cache'=>true,'open_type'=>'http_native','style_title'=>'color:red' ,'style'=>'color:#00bdff' ,'href'=>'https://webdemo.agora.io/videocall/' ];
            //$main[]= ['title'=>'沉浸式','url'=>'http://lab.pigai.org/hello3/examples/best-practices/list-to-detail/listview.html','id'=>'best-practices','cls'=>'mui-icon icon-tb-profile','open_type'=>'native' ];

        }
        if(  3== $user['user']['ts'] ){ //3155== $user['user']['user_id'] ||
            $main[]= ['title'=>'任课班级','url'=>'app/page/class','id'=>'class','cls'=>'mui-icon icon-touch-users','open_type'=>'http_native'  ]; //,'right'=>'回复'
        }
        if(  isset($user['attr']['p3'])  ||   isset($user['attr']['p1']) ){ #校管理 和 超级管理

            $main[]= ['title'=>'布置选书','url'=>'app/page/http','id'=>'http','cls'=>'mui-icon icon-tb-roundadd','is_cache'=>true,'open_type'=>'http_native'  ,'href'=>'https://appclient.haoce.com/book/s/hc' ];
            $main[]= ['title'=>'正在发生','url'=>'help/pad','id'=>'help_pad','cls'=>'mui-icon icon-tb-deliver','open_type'=>'http_native'  ,'title_type'=>'transparent'  ];
            $main[]= ['title'=>'阅读可视化','url'=>'app/page/http','id'=>'http','cls'=>'mui-icon icon-tb-explore','is_cache'=>true,'open_type'=>'http_native' ,'href'=>'https://appclient.haoce.com/school/novelView?tb=list&show=bar' ];
        }


        #$about=['qq'=>'62669680'];
        return ['img'=> $img,'main'=>$main ,'html'=>'','is_mark'=>false ,'user'=>$user ];
    }

    function act_v2home(){
        $img=[];
        $img[]=['img'=>'https://cdn.haoce.com/res/mui/img/b1.jpg','title'=>'培养独立思考的教育'  ,'url'=>'app/page/readerFull',id=>'readerFull','novel_id'=> 363   ,open_type=>'http_native', title_type=>'transparent',is_cache=>true ]; //,'url'=>'https://www.baidu3.com/'
        $img[]=['img'=>'https://cdn.haoce.com/res/mui/img/a2.jpg' ];
        $img[]=['img'=>'https://cdn.haoce.com/res/mui/img/b3.jpg' ];

        $init_tab=[
            ['cls'=>'hao-icon-dushugongcheng' , 'bgColor'=>'#55B8F7' ,'url'=>'app/page/novelListV2?block_id=102','id'=>'novelListV2' ,'open_type'=>'http_native'  ,'title_type'=>'transparent']
            ,['cls'=>'hao-icon-xialingying' , 'bgColor'=>'#FAE44C' ,'url'=>'app/page/novelListV2?block_id=102','id'=>'novelListV2' ,'open_type'=>'http_native'  ,'title_type'=>'transparent']
            ,['cls'=>'hao-icon-dushushow' , 'bgColor'=>'#96E663','url'=>'app/page/novelListV2?block_id=103','id'=>'novelListV2' ,'open_type'=>'http_native'  ,'title_type'=>'transparent' ]
        ];
        /*
        $tab=[ ['title'=>'读书工程','cls'=>'hao-icon-dushugongcheng' , 'bgColor'=>'#55B8F7']
            ,['title'=>'校书刊','cls'=>'hao-icon-waiguoyuxueyuan' , 'bgColor'=>'#FF6766' , 'url' => 'app/page/bookSchool', 'id' => 'bookSchool' , 'open_type' => 'http_native']
            ,['title'=>'夏令营','cls'=>'hao-icon-xialingying' , 'bgColor'=>'#FAE44C' ,'url'=>'app/page/novelListV2?block_id=102','id'=>'novelListV2' ,'open_type'=>'http_native'  ,'title_type'=>'transparent']
            ,['title'=>'读书show','cls'=>'hao-icon-dushushow' , 'bgColor'=>'#96E663','url'=>'app/page/novelListV2?block_id=103','id'=>'novelListV2' ,'open_type'=>'http_native'  ,'title_type'=>'transparent' ]];
        */
        $tab= [ ];
        $block = $this->getLogin()->createBlock()->getNameList( ['school_id'=>$this->getLogin()->getSchoolID(),'>'=>['type'=>0] ,'<='=>['type'=> $this->getLogin()->isTeacher()?10: 1 ]  ]);
        if( $block ) {
            foreach ($block as $k=> $v ){
                $tem= $init_tab[ $k%count($init_tab)];
                $tem['title']= $v['block'];
                $tem['url']= 'app/page/novelListV2?block_id='.$v['block_id'] ;
                $tab[]= $tem ;
            }
        }

        if( $this->getLogin()->getUserId()== 93536 ){
            $tab[]= ['title'=>'读书工程','cls'=>'hao-icon-xialingying' , 'bgColor'=>'#FAE44C'];
        }
        $tab[]=['title'=>$this->getLogin()->getSchool()=='黑龙江东方学院'?'教案展':'校书刊','cls'=>'hao-icon-waiguoyuxueyuan' , 'bgColor'=>'#FF6766' , 'url' => 'app/page/bookSchool', 'id' => 'bookSchool' , 'open_type' => 'http_native'] ;
        
        $tab2=[];
        if( $this->getLogin()->isTeacher()) {
            $tab2 = [
                ['title' => '任课班级', 'cls' => 'hao-icon-banji', 'url' => 'app/page/class', 'id' => 'class', 'open_type' => 'http_native']
                , ['title' => '实时动态', 'cls' => 'hao-icon-shishi', 'url' => 'help/pad', 'id' => 'help_pad', 'open_type' => 'http_native', 'title_type' => 'transparent']
            ];
        }
        //if( isset($user['attr']['p3']) ) {
        if( $this->getLogin()->isSchoolAdmin() ) {
            $tab2[] = ['title'=>'可视化','cls'=>'hao-icon-keshihua','url'=>'app/page/http','id'=>'http' ,'is_cache'=>true,'open_type'=>'http_native' ,'href'=>'https://appclient.haoce.com/school/novelView?tb=list&show=bar'  ];
            //$tab2[] = ['title' => '数据概况', 'cls' => 'hao-icon-shuju', 'url' => 'app/page/hcAdmin', 'id' => 'hcAdmin', 'open_type' => 'http_native'];
            $tab2[] = ['title' => '数据概况', 'cls' => 'hao-icon-shuju' ,'url'=>'app/page/http','id'=>'http' ,'is_cache'=>true,'open_type'=>'http_native' ,'href'=>'https://appclient.haoce.com/school/main' ];
        }



        $this->assign('img', $img )->assign('tab', $tab )->assign('tab2', $tab2);
    }
}