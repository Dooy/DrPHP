<?php
/**
 * 小说内容
 * 读物的内容
 */

namespace model;


class novel extends model
{
    private $tb_novel='novel';
    private $tb_time ='novel_time';
    private $tb_view = 'novel_view';
    private $tb_list = 'novel_view_list';
    private $tb_dict = 'novel_dict';
    private $tb_comment = 'novel_comment';
    private $tb_user_tj= 'user_tj';
    private $site='';
    private $file_chapter=  ['site_id','chapter_id','chapter','mp3','mp3_local','ctime','body'];
    private $user_id =0;
    private $r_arr = ['　'=>''];
    function setSite( $site){
        $this->site = $site;
        return $this;
    }
    function setUserID( $user_id ){
        $this->user_id = $user_id;
    }
    function getUserID(){
        if( $this->user_id<=0 ) $this->throw_exception( "无效账号",7002);
        return  $this->user_id;
    }

    /**
     * 获取章节表
     * @return string
     */
    function getTableChapter(){
        return 'novel_chapter_'. $this->site;
    }

    /**
     * 增加修改章节内容
     * @param $site_id
     * @param $chapter_id
     * @param array $opt
     * @return $this
     */
    function addAndModifyChapter( $site_id, $chapter_id, $opt=[] ){
        if(!$site_id || ! $chapter_id)  $this->throw_exception( "添加小说章节错误错误！",7502 );
        $row = $this->createSql()->select( $this->getTableChapter(),['site_id'=>$site_id,'chapter_id'=>$chapter_id ] )->getRow();
        $opt['site_id']= $site_id ;  $opt['chapter_id']= $chapter_id ;

        if($row ) $this->update( $this->getTableChapter(),['cp_id'=>$row['cp_id']] , $opt, $this->file_chapter );
        else{
            $opt['ctime']= time();
            $this->insert( $this->getTableChapter(),$opt, $this->file_chapter );
        }
        return $this;
    }

    /**
     * 获取小说 目录 分页列表
     * @param array $opt
     * @return array
     */
    function getListWithPage( $opt=[] ){
        $where = isset($opt['where'])? $opt['where'] :'1';
        //$this->drExit( $where );
        switch ( $opt['tb']){
            case 'comment':
                return $this->createSql()->selectWithPage( $this->tb_comment, $where ,30,[],['comment_id'=>'desc']);
                break;
            case 'dict':
            case 'novel_dict':
                return $this->createSql()->selectWithPage( $this->tb_dict, $where ,30,[],['dict_id'=>'desc']);
                break;
            case 'list':
                return $this->createSql()->selectWithPage( $this->tb_list, $where ,30,[],['last_time'=>'desc']);
                break;
            default:
                return $this->createSql()->selectWithPage( $this->tb_novel, $where ,30,[],['novel_id'=>'desc']);
        }

    }
    function getTjCntFromNovelViewList( $where ,$opt=[] ){
        $arr=['cnt_1','cnt_2','cnt_3','cnt_4','cnt_5','cnt_6','cnt_7','cnt_8','cnt_9','cnt_101'];
        $sql = "select  ";
        if( isset($opt['view_list_cnt'])) $sql.=" count(*) as view_list_cnt,";

        foreach( $arr as $v ) $sql.=" sum(".$v.") as ".$v.",";
        $sql = trim( $sql,',')." from ". $this->tb_list." where ". $this->createSql()->arr2where( $where );

        //$this->drExit(  $sql );

        return $this->createSql(  $sql)->getRow();
    }

    function getList( $where, $tb='novel' ){
        switch ( $tb){
            case 'comment':
                $tb= $this->tb_comment ;$order=['comment_id'=>'desc'];
                break;
            case 'dict':
            case 'novel_dict':
                $tb= $this->tb_dict ;$order= ['dict_id'=>'desc'];
                break;
            case 'list':
                $tb= $this->tb_list ;$order=['last_time'=>'desc'] ;
                break;
            default:
                $tb=  $this->tb_novel ;$order= ['novel_id'=>'desc'] ;
        }
        return $this->createSql()->setStartEvery()->select( $tb, $where,[],[],$order)->getAll();
    }

    /**
     * 获取一本小说
     * @param array|int $id
     * @return array
     */
    function getNovelById( $id ,$opt=[]){
        $where= ['novel_id'=> $id  ];
        if( isset( $opt['is_check']) || isset( $opt['is_show'])  ) $where['is_show']=1 ;
        if( is_array( $id)){
            return $this->createSql()->select($this->tb_novel,$where )->getAllByKey('novel_id');
        }
        $row = $this->createSql()->select( $this->tb_novel,$where )->getRow();
        if( !$row )  $this->throw_exception( "小说不存在！",7505 );
        $row['cdn_img']= 'https://cdn.haoce.com/cfsup/novel/' .$row['site'].'/'.$row['img']  ;
        return $row;
    }

    function searchNovelFromArray( $list,$key=['novel_id']){
        $novel_id = [];
        drFun::searchFromArray($list,$key,$novel_id);
        if( !$novel_id) return [];
        return $this->getNovelById($novel_id );
    }

    function modifyNovelById( $id, $post){
        $file=['novel','is_yin','is_shuan','is_show','img' ,'cp','read','word'];
        if( isset($post['user_id']) &&  intval( $post['user_id'] )==0 ) {
            $file[]='user_id'; $post['user_id']= $this->getUserID();
        }
        $this->update( $this->tb_novel,['novel_id'=>$id ], $post, $file );
        return $this;
    }

    function saveChapterOrder($novel_id, $site, $order  ){
        $this->setSite( $site);
        foreach ( $order as $k=>$v ){
            $this->update( $this->getTableChapter(), ['cp_id'=>$v,'site_id'=>$novel_id  ],['chapter_id'=> $k+1 ]);
        }
        return $this;
    }

    function delChapter( $chapter, $site='haoce'){
        $this->setSite($site)
        ->createSql()->delete( $this->getTableChapter(),['cp_id'=>$chapter['cp_id']])->query();
        return $this;
    }

    function upReadCpWord( $novel_id ){
        $novel = $this->getNovelById( $novel_id);
        $this->setSite( $novel['site'] );
        $var= $this->createSql("select count(*) as cp, sum(word) as word from  ". $this->getTableChapter() ." where site_id='".$novel['site_id']."' ")->getRow();
        $var['read']= $this->createSql()->getCount( $this->tb_list, ['novel_id'=>$novel_id])->getOne();
        $this->modifyNovelById( $novel_id, $var);
        return $this;
    }

    /**
     * 获取小说的章节内容
     * @param $site_id
     * @param $opt
     * @return mixed
     */
    function getNovelChapter( $site_id ,$opt=[] ){
        $file = isset($opt['chapter_file']) ?$opt['chapter_file']:[] ;
        $list = $this->createSql()->select( $this->getTableChapter(), ['site_id'=>$site_id],[],$file ,['chapter_id'=>'asc','cp_id'=>'asc'] )->getAll();
        if( !$list )  $this->throw_exception( "小说内容章节不存在！",7506 );
        return $list;
    }

    /**
     * 通过章节ID获取内容
     * @param array|int $chapter_id 章节ID
     * @param array $file
     * @return mixed
     */
    function getChapterByID( $chapter_id, $file=[]){
        return $this->createSql()->select( $this->getTableChapter(),['cp_id'=> $chapter_id],[],$file)->getAllByKey('cp_id');
    }

    /**
     * 获取小说内容章节和内容
     * @param $id
     * @param array $opt
     * @return array
     */
    function getNovelAllById( $id ,$opt=[] ){
        $re=[];
        $re['novel']= $this->getNovelById( $id );
        $this->novelWithLanguage(  $re['novel'] ,$opt );
        $this->setSite(  $re['novel']['site']);
        if( isset($opt['can_error']) ) {
            try{
                $re['chapter']= $this->getNovelChapter(  $re['novel']['site_id'] );
            }catch ( drException $ex ){
                $re['chapter']=[];
            }
        }else $re['chapter']= $this->getNovelChapter(  $re['novel']['site_id'],$opt );

        return $re ;
    }

    function checkNovelCpNull( &$chapter , $site_id ){
        $this->setSite($site_id );
        foreach($chapter as $k=>$v ){
            if( $v['chapter_id']==0 ){
                $this->createSql()->update( $this->getTableChapter(),['chapter_id'=> $k+1 ] ,['cp_id'=>$v['cp_id']])->query();
            }
        }
       //$this->drExit(  $chapter );
    }

    function novelWithLanguage( &$novel ,$opt=[] ){
        $language= false;
        //$this->drExit( $novel );
        if($opt['version'] == 'v2' ){
            switch ($novel['is_shuan']) {
                case '4':
                    $language =  [ '' => '英文', 'cn' => '中文']  ; //'' => '混合',
                    break;
                case '7':
                    $language = [ '' => '日文', 'cn' => '中文'] ; //'' => '混合',
                    break;
                case '2':
                    $language = ['' => '混合', 'html_en' => '英文', 'html_cn' => '中文'];
                    break;
                case '5':
                    $language = ['' => '混合', 'en' => '英文', 'cn' => '中文'];
                    break;
                case '8':
                    $language = ['' => '混合', 'en' => '日文', 'cn' => '中文'];
                    break;
            }

        }else {
            switch ($novel['is_shuan']) {
                case '4':
                    $language =  ['' => '英文', 'cn' => '中文'];
                    break;
                case '7':
                    $language = ['' => '日文', 'cn' => '中文'];
                    break;
                case '2':
                    $language = ['' => '混合', 'html_en' => '英文', 'html_cn' => '中文'];
                    break;
                case '5':
                    $language = ['hl_all' => '混合', 'hl_en' => '英文', 'hl_cn' => '中文'];
                    break;
                case '8':
                    $language = ['hl_all' => '混合', 'hl_en' => '日文', 'hl_cn' => '中文'];
                    break;
            }
        }
        $novel['language']= $language;// $language;
        return $this;
    }

    /**
     * 增加修改一本小说
     * @param $site_id
     * @param $novel
     * @param $site 站点名称
     * @param array $opt
     * @return $this
     */
    function addAndModifyNovel( $site_id ,$novel , $site,$opt=[] ){
        if( !$site_id || !$novel || !$site ) $this->throw_exception( "添加小说参数错误！",7501 );
        $row = $this->createSql()->select( $this->tb_novel,['site_id'=>$site_id,'site'=>$site ] )->getRow();
        $file = ['novel'=> $novel];
        if( isset( $opt['img']) ) $file['img']= $opt['img'];
        if( isset( $opt['cat']) ) $file['cat']=  $opt['cat'];
        if( $row ) {
            $this->update( $this->tb_novel,['novel_id'=>$row['novel_id'] ], $file);
        }else{
            $file['site_id'] = $site_id;
            $file['site'] = $site;
            $file['ctime'] =time() ;
            $this->insert( $this->tb_novel, $file  );
        }
        return $this;
    }

    /**
     * 火车头入库 内容页
     * @param $post
     * @return $this
     */
    function  locoy_post( &$post ){
        //url mp3 body site
        $site = trim( $post['site'] );
        //$this->drExit( $site );
        switch ( $site ){
            case '8848':
                $this->post_8848( $post );
                break;
            case 'xmly':
                $this->post_xmly( $post );
                break;
            default:
                $this->throw_exception("未定义操作 ".$site,7504 );
        }
        return $this;
    }

    /**
     * 火车头入库 目录页
     * @param $post
     * @return $this
     */
    function locoy_mulu( &$post ){
        $site = trim( $post['site'] );
        switch ( $site ){
            case '8848':
                $this->mulu_8848( $post );
                break;
            case 'xmly':
                $this->mulu_xmly( $post );
                break;
            default:
                $this->throw_exception("未定义操作 ".$site ,7503 );
        }
        return $this;
    }

    function mulu_xmly( $post){
        $this->setSite( 'xmly');
        $novel= trim( $post['name']);

        preg_match_all("|src=(['\"]+)([^'\"]+)(['\"]+)|U",  $post['img'], $out , PREG_PATTERN_ORDER );
        $img= trim($out[2][0]);
        $arr = explode("/",  trim(  $post['url'],'/' ) );
        $site_id =intval( array_pop( $arr ) );
        //$this->drExit($site_id);
        $opt = [];
        //if( $cat ) $opt['cat']= $cat;
        if( $img ) $opt['img'] = $img;
        $this->addAndModifyNovel($site_id, $novel ,'xmly'  ,$opt );
        return $this;
    }

    /**
     * 8848站采集 文章
     * @param $post
     * @return $this
     */
    function post_8848( &$post){
        //url mp3 body site
        $this->setSite( '8848');
        $arr = $this->getSite8848FromUrl( trim($post['url']) ) ;//explode("/",  trim($post['url']) );
        //$arr= explode('_', $arr[ count($arr)-1 ] );
        $opt['site_id']= intval($arr[0]);
        $opt['chapter_id']= intval(  $arr[1]);
        $opt['mp3']= trim($post['mp3'] );
        $opt['body']= trim($post['body'] );
        $this->addAndModifyChapter(  $opt['site_id'] ,$opt['chapter_id'] ,$opt );
        return $this;
    }
    function post_xmly( &$post){
        $this->setSite( 'xmly');
        $body=  '{'.trim($post['body'] ).'}';
        $arr = json_decode($body ,true );
        //echo '<pre>';        $this->drExit( $arr );
        $opt['site_id']= intval($arr['album_id']);
        $opt['chapter_id']= intval(  $arr['id']);
        $opt['chapter']= trim($arr['title'] );
        $opt['mp3']= trim($post['mp3'] );
        $this->addAndModifyChapter(  $opt['site_id'] ,$opt['chapter_id'] ,$opt );
        return $this;
    }
    function getSite8848FromUrl( $url ){
        $arr = explode("/",  trim( strtr( $url,['.html'=>''] ) ) );
        $cat = $arr[ count($arr)-2 ];
        //$this->drExit( $cat );
        $arr= explode('_', $arr[ count($arr)-1 ] );
        return [ intval($arr[0]),intval(  $arr[1]) ,trim($cat ) ];
    }

    /**
     * 8848站采集目录
     * @param $post
     * @return $this
     */
    function mulu_8848( &$post ){
        //name content site
        $this->setSite( '8848');
        $novel= trim( $post['name']);
        //<img width="225" height="315" src="e01986423ffc117435124738dc6ef396.jpg" />
        preg_match_all("|src=(['\"]+)([^'\"]+)(['\"]+)|U",  $post['img'], $out , PREG_PATTERN_ORDER );
        //echo '<pre>';        $this->drExit( $out );
        $img= trim($out[2][0]);

        //$this->drExit( $_POST );

        preg_match_all("|'url':'([^']+)','title':'([^']+)'|U", trim( $post['content'] ), $out , PREG_PATTERN_ORDER );
        $site_id = 0;
        foreach( $out[1] as $k=>$url ){
            $arr = $this->getSite8848FromUrl( $url );
            $site_id= $arr[0];  $chapter_id = $arr[ 1 ];
            $cat= $arr[2];
            $this->addAndModifyChapter($site_id, $chapter_id ,['chapter'=>  trim( $out[2][$k] ) ] );
        }
        $opt = [];
        if( $cat ) $opt['cat']= $cat;
        if( $img ) $opt['img'] = $img;
        $this->addAndModifyNovel($site_id, $novel ,'8848'  ,$opt );
        return $this;
    }

    /**
     * 累积读书时间
     * @param $novel_id
     * @param $time
     * @param $uid
     * @param array $opt
     * @return $this
     */
    function time( $novel_id, $time,$uid, $opt=[]){
        if($uid<=0 )  $this->throw_exception("请先登录" ,7506 );
        if($time<=0 ||  $novel_id<=0 )  $this->throw_exception("时间太短了" ,7507);
        $row = $this->createSql()->select( $this->tb_time, ['user_id'=>$uid,'novel_id'=>$novel_id ] )->getRow();

        if( !$row ){
            $time = $time>10?10: $time;
            $var= ['user_id'=>$uid,'novel_id'=>$novel_id ,'time'=> $time,'last'=>time()   ];
            $this->insert($this->tb_time,$var );
        }else{
            $this->update( $this->tb_time,['nt_id'=>$row['nt_id'] ] ,['time'=> $this->getTime($time,$row ),'last'=>time()] ) ;
        }
        if( $opt['book_id'] ){
            $book_user = $this->createSql()->select( "book_user", ['user_id'=>$uid,'book_id'=>$opt['book_id'] ])->getRow();
            if( $book_user ){
                $this->update( 'book_user',['id'=>$book_user['id'] ] ,['time'=> $this->getTime($time,$book_user ),'last'=>time()] ) ;
            }
        }
        return $this;
    }

    /**
     * 计算读书时间
     * @param $time
     * @param $row
     * @return int|mixed
     */
    function getTime($time,$row ){
        $time_server = time()-$row['last'];
        if($time_server<0 ) $time_server=0;
        $time = $time_server<30?$time_server: min($time,$time_server );
        return $time+$row['time'];
    }

    /**
     * 分析文章属性并更新
     * @param $novel_id
     * @return $this
     */
    function analyzeNovelByID( $novel_id ){
        $novel = $this->getNovelById( $novel_id );
        $this->setSite( $novel['site'] );
        $chapter = $this->createSql()->select( $this->getTableChapter(),['site_id'=> $novel['site_id']] ,[],[],['cp_id'=>'desc'])->getRow();
        $attr= ['is_yin'=> trim( $chapter['mp3'])?1:0  ,'is_shuan'=>  $this->isShuan( $chapter['body'] ) ];
        //print_r( $novel );      $this->drExit(  $attr );
        $this->update( $this->tb_novel,['novel_id'=>$novel_id], $attr );
        return $this;
    }

    /**
     * 判断文章是否双语
     * @param  string $body
     * @return int
     */
    function isShuan( $body){
        if( !$body ) return 0;
        if( strpos( $body,'qh_en')!==false ) return 2;
        return 1;
    }

    /**
     * 分析是否有音频 是否双语 是否是空内容
     * @return $this
     * @throws drException
     */
    function analyzeAll(){
        $tall = $this->createSql()->select( $this->tb_novel,'1',[ ],['novel_id'])->getAll();
        foreach( $tall as $var ) $this->analyzeNovelByID( $var['novel_id'] );
        return $this;
    }

    /**
     * 获取小说列表
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getNovelListWitchPage( $opt=[] ){
        $where=['is_show'=>1 ];
        if( isset( $opt['q']) &&  $opt['q']  ) $where['like']=['novel'=>'%'.$opt['q'].'%'  ];
        if( isset( $opt['is_shuan']) &&  $opt['is_shuan']!='' ) $where['is_shuan']= trim( $opt['is_shuan'] );
        if( isset( $opt['tag'] ) &&  trim($opt['tag'])!='' )  $where['novel_id']= $this->getLogin()->createTagNovel()->getIDByTag( trim($opt['tag']) );


        $every = (isset( $opt['every']) &&  $opt['every']>1)? $opt['every']:12;
        $list =  $this->createSql()->selectWithPage( $this->tb_novel, $where ,$every );
        $this->withNovelImg( $list['list'] );
        return $list;
    }

    function getNovelListWitchPageBySchool(){
        $arr=[498,589,438,437,516,547,546,545,543,540,539,538,537,534,533,532,531,530,529,526,525,524,521,520,518,515,514,513,512,511,508,506,505,504,503,502,500,497,496,495,494,492,463,457,456,455,447,422,603,588,587,585,584,583,582,581,580,578,577,576,574,572,571,570,565,561,557,556,555,554,553,552,551,550,549,548,544,542,541,536,535,528,523,522,519,517,510,509,507,501,499,493,491,490,489,488,487,485,484,482,481,480,479,478,476,475,474,473,472,470,469,468,467,466,465,464,462,460,459,458,454,453,452,451,450,449,448,446,445,444,443,442,441,440,436,435,434,433,432,431,429,428,426,425,424,420,419,418,417,416,411,410,409,408,407,406,405,404,403,402,401,400,399,398,397,396,395,394,393,392,391,390,389,388,387,386,385,384,383,382,381,380,379,378,377,376,375,374,373,372,371,370,369,368,367,366,365,364,598,599,596,593,591,592];
        if( $this->getLogin()->getSchool()=='安徽建筑大学城市建设学院'  ){
            $arr=[687,547,546,545,543,540,539,538,537,534,533,532,531,530,529,526,525,524,521,520,518,516,515,514,513,512,511,508,506,505,504,503,502,500,498,497,496,495,494,492,463,457,456,455,447,422,603,589,588,587,585,584,583,582,581,580,578,577,576,574,572,571,570,565,561,557,556,555,554,553,552,551,550,549,548,544,542,541,536,535,528,523,522,519,517,510,509,507,501,499,493,491,490,489,488,487,485,484,482,481,480,479,478,476,475,474,473,472,470,469,468,467,466,465,464,462,460,459,458,454,453,452,451,450,449,448,446,445,444,443,442,441,440,438,437,436,435,434,433,432,431,429,428,426,425,424,420,419,418,417,416,411,410,409,408,407,406,405,404,403,402,401,400,399,398,397,396,395,394,393,392,391,390,389,388,387,386,385,384,383,382,381,380,379,378,377,376,375,374,373,372,371,370,369,368,367,366,365,364,598,599,596,593,591,592];
        }elseif ( $this->getLogin()->getSchool()=='西南林业大学'){
            $arr=[614,459,648,435,615,340,659,574,546,618,655,653,654,495,463,652,657,612,616,446,650,651,613,617,417,649,647,535,639,640,641,642,643,644,645];
        }
        $every = 12;
        $pageNo = intval($_GET['pageno'])-1;
        if( $pageNo<=0)  $pageNo=0;
        $page = new page( count($arr ),$every);
        $n_id = array_slice($arr,$every*$pageNo, $every );
        if(! $n_id ) return [];
        $pg_re=  $page->pageLinks( );
        $re['page']=$pg_re['html'];
        $re['page_total']=  $pg_re['page_total'];
        $str_id = implode(',',$n_id );
        $re['list']= $this->createSql("select * from `".$this->tb_novel."`  where novel_id in( ".$str_id.")  ORDER BY FIELD (novel_id, ".$str_id.")")->getAll();
        $this->withNovelImg( $re['list'] );
        return $re ;
    }

    function getNovelListWithPageByBlockID( $block_id ){
        $list = $this->getLogin()->createBlock()->getListWithPage( ['block_id'=>$block_id ] ,['every'=>12 ] );
        $blockNovel=[];
        foreach( $list['list'] as $v )$blockNovel[ $v['novel_id']]= $v ;
        $novel_id = [];
        drFun::searchFromArray( $list['list'] ,['novel_id'], $novel_id );
        $str_id = implode(',',$novel_id );
        $list['list']= $this->createSql("select * from `".$this->tb_novel."`  where novel_id in( ".$str_id.")  ORDER BY FIELD (novel_id, ".$str_id.")")->getAll();
        foreach( $list['list'] as &$v){
            if( isset( $blockNovel[$v['novel_id']] ) ) $v['block_info']=  $blockNovel[$v['novel_id']] ;
        }
        $this->withNovelImg( $list['list'] );
        return $list ;
    }



    function withNovelImg( &$list ){
        if(! is_array($list )) return $this;
        if( isset($list['site'] ) && $list['img'] ){
            $list['cdn_img']= 'https://cdn.haoce.com/cfsup/novel/' .$list['site'].'/'.$list['img']  ;
            return $this;
        }
        foreach( $list as &$v ){
            $v['cdn_img']= 'https://cdn.haoce.com/cfsup/novel/' .$v['site'].'/'.$v['img']  ;
        }
        return $this;
    }

    /**
     * 获取我读的小说内容
     * @param $opt
     * @return array
     * @throws drException
     */
    function getMyNovelList( $opt ){
        if( intval($opt['pageno'])>1 ) return [];
        $tall = $this->createSql()->select( $this->tb_list,['user_id'=>$this->getUserID()],[],[],['last_time'=>'desc'])->getAllByKey('novel_id');
        return $this->myNovelWithInfo($tall );
    }

    function getMyNovel( $opt=[]){
        $where=['user_id'=> $this->getUserID() ];
        if( is_array($opt['where']) ) drFun::arrExtend($where, $opt['where'], false );
        $file= isset($opt['file'])? $opt['file'] : [];
        //$this->drExit( $this->createSql()->select( $this->tb_list,$where,[],$file )->getSQL() );
        return $this->createSql()->select( $this->tb_list,$where,[],$file )->getAllByKey( 'novel_id');
    }

    function getMyNovelTj( $where ){
        $tall =  $this->createSql()->select( $this->tb_list, $where ,[],[ ])->getAll( );
        $re=['xuefen'=>0,'book'=>0,'finish'=>0 ];
        foreach($tall as $v ){
            if( $v['progress']>=10000 )$re['finish']++;
            if( $v['type']==10 )$re['xuefen']++;
        }
        $re['book']= count($tall );
        return $re ;
    }

    function myNovelWithInfo( $tall ){
        if( !$tall) return [];
        $id_arr  = [];
        foreach ( $tall as $v )  $id_arr[ $v['novel_id'] ]= $v;
        unset( $tall);
        $list = $this->createSql()->select($this->tb_novel,['novel_id'=>  array_keys($id_arr) ],[],[],['novel_id'=>  array_keys($id_arr)] )->getAll();
        $this->withNovelImg( $list);
        foreach ( $list as &$v) $v['myInfo']= $id_arr[$v['novel_id']] ;
        return $list;
    }

    function getMyNovelListWithPage( $where ,$opt=[] ){
        $every= isset( $opt['every'] )? $opt['every']  : 30;
        $re = $this->createSql()->selectWithPage( $this->tb_list, $where ,$every ,[],['last_time'=>'desc'] );
        $re['list']= $this->myNovelWithInfo( $re['list'] );
        return $re ;
    }

    function getNovelListGroupBy($where ,$group_file   ){
        return $this->createSql()->group( $this->tb_list,[$group_file], $where,[$group_file, 'count(*) as cnt','sum(word) as word','sum(dtime) as dtime'],['cnt'=>'desc'])->getAll();
    }



    /**
     * 获取章节站点
     * @param string $site
     * @return array|mixed
     */
    function getSite( $site='all'){
        $s_arr = ['8848'=>['n'=>'8848'],'xmly'=>['n'=>'喜雅']];
        if( $site =='all') return $s_arr ;
        if( !isset( $s_arr[ $site])) $this->throw_exception("网站不存在",7509 ) ;
        return  $s_arr[ $site] ;

    }

    /**
     * 更新章节
     * @param $cp_id
     * @param $site
     * @param $opt
     * @return $this
     */
    function updateChapterByID( $cp_id,$site,$opt ){
        $this->setSite( $site)->update( $this->getTableChapter(),['cp_id'=>intval($cp_id) ],$opt,[ 'site_id','chapter_id','chapter','mp3','mp3_local','view' ,'body','body_cn','del_time'] );
        return $this;
    }

    /**
     * 内容分类
     * @param string $type
     * @return array|mixed
     * @throws drException
     */
    function getTypeNeiRong( $type='all'){
        $cat = [0=>'无内容',2=>'交叉双语HTML',3=>'仅中文',1=>'仅英文',4=>'中英单独',5=>'中英交叉',6=>'仅日语',7=>'中日单独',8=>'中日交叉'];
        if( $type =='all') return$cat;
        if( isset( $cat[ $type ]) ) return $cat[ $type ];
        $this->throw_exception("内容分类不存在！",7509 ) ;
    }

    /**
     * 按小说更新字数
     * @param $novel_id
     * @return $this
     * @throws \Exception
     */
    function updateChapterWordByNovel(  $novel_id    ){
        $novel = $this->getNovelAllById( $novel_id );
        foreach( $novel['chapter'] as $v ){
            $number =drFun::wordCountEnAndCn($novel['novel']['is_shuan']==3?  $v['body_cn']: $v['body']);
            $this->update( $this->getTableChapter(),['cp_id'=> $v['cp_id']], ['word'=> $number] );
        }
        return $this;
    }

    /**
     * v1版本打卡，v2版本记录阅读过程
     * @param $opt
     * @return $this
     * @throws drException
     */
    function doView( $opt ){
        if( $opt['word']<=0 ||  !$opt['word'] ){
           $this->updateChapterWordByNovel( $opt['novel_id']);
           $opt['word']= $this->createSql()->select( $this->getTableChapter(),['cp_id'=>$opt['cp_id']] ,[],['word'])->getOne() ;
        }
        if( $opt['word']<=0  ) $this->throw_exception( "字数错误！",7510); // $_POST['progress'] =10000;


        $_file=['novel_id'=>['n'=>'小说ID'],'cp_id'=>['n'=>'章节'],'word'=>['n'=>'字数'],'ctime','dtime'=>1 ,'user_id'=>[ 'n'=>'用户ID'],'school_id','term_key','progress','last','last_time'  ];
        $where = ['user_id'=>$opt['user_id'] ,'novel_id'=>$opt['novel_id'],'cp_id'=>$opt['cp_id'] ];
        $row  = $this->createSql()->select( $this->tb_view, $where )->getRow();
        if($row && $opt['version']=='v2' ){
            if( $opt['dtime']<$row['dtime'] ) return $this;
            $this->update( $this->tb_view,['view_id'=>$row['view_id']], ['dtime'=>$opt['dtime'], 'progress'=>max( $opt['progress'],$row['progress'] ),'last'=>$opt['progress'] ,'last_time'=>time() ] );
        }elseif(  $row ) $this->throw_exception( "请勿重复提交！",7511);
        else {
            //$this->createSql()->update( $this->tb_view, $opt,$_file );
            $opt['ctime'] = $opt['last_time'] =time();
            $opt['last']=$opt['progress'] ;
            $id = $this->insert($this->tb_view, $opt, $_file);
        }
        if( $opt['progress']>=10000 ) {
            $this->viewWithNovelAndChapter($opt);
            $this->getLogin()->padLogAdd($id, 485, "花费" . timeShow($opt['dtime']) . '阅读《' . $opt['novel'] . '》' . $opt['chapter']);
        }
        return $this;
    }

    /**
     * 从mq中统计时间
     * @param $opt
     * @return $this
     */
    function toUserTj( $opt ){
        $var=[];
        if(  !isset($opt['count_v2'])  ||  $opt['count_v2']<=0  ) {
            $this->throw_exception( "时间不合规" ,7528 );
        }
        $user_id = intval($opt['user_id'] );
        if( $user_id<=0 )  $this->throw_exception( "用户信息不存在" ,7529 );
        $time= $opt['count_v2'];
        if($time>300 )$time= 300; #如果时间大约5分钟 300秒按5分钟来计算
        $var['time_today']=$time;
        $var['time_all']=$time;
        $cnt = $this->createSql()->getCount(   $this->tb_user_tj, ['user_id'=>$user_id])->getOne();
        if( $cnt<=0) {
            $var['user_id']= $user_id;
            $this->insert(  $this->tb_user_tj,$var  );
        }else{
            $this->update(  $this->tb_user_tj ,['user_id'=>$user_id] , ['+'=> $var ]);
        }
        return $this ;
    }

    function getUserRankTimeToday( $opt=[ ]){
        $re=['rank'=>'-' ,'time_today'=>0 ];
        $user_id = isset( $opt['user_id'])?  $opt['user_id']: $this->getLogin()->getUserId() ;
        $re['time_today']= intval( $this->createSql()->select( $this->tb_user_tj,['user_id'=>$user_id],[],['time_today'] )->getOne());
        if( isset($opt['view_time'])  ){
            $re['view_time'] =  $opt['view_time'];
            if( $re['time_today'] >$opt['view_time'] )  $re['time_today']= $opt['view_time'];
        }
        $total = $this->createSql()->getCount( $this->tb_user_tj ,['>'=>['time_today'=>0] ])->getOne();
        if(   $re['time_today'] <=0 ){
            $re['rank'] = $total>0?0:'-';
            return $re;
        }
        $cnt = $this->createSql()->getCount( $this->tb_user_tj ,['>'=>['time_today'=>  $re['time_today'] ] ])->getOne();
        $re['rank']= 100- intval ( 100*( $cnt/$total )+0.5 );
        $re['info']= [ $cnt,$total  ];
        return $re ;
    }

    function clearToday(){
        //$sql = "update ".   $this->tb_user_tj." set time_today=0";
        $this->createSql()->update(  $this->tb_user_tj,['time_today'=>0],'1')->query();
        return $this;
    }

    /**
     * 添加查词记录
     * @param $opt
     * @return $this
     */
    function dictLog( $opt ){
        if( trim( $opt['word'] )==''  )$this->throw_exception( "字数错误！",7516);
        $_file=['novel_id'=>['n'=>'小说ID'],'cp_id'=>['n'=>'章节'],'word'=>['n'=>'单词'],'ctime'  ,'user_id'=>[ 'n'=>'用户ID'],'school_id','term_key' ];
        $opt['ctime']= time();
        $id = $this->insert( $this->tb_dict, $opt,$_file  );
        $this->getLogin()->padLogAdd( $id, 486,"阅读查词为 ".$opt['word']  );
        return $this;
    }

    /**
     * 增加笔记
     * @param $opt
     * @return $this
     */
    function addComment( $opt ){
        if( $opt['comment_word']<=5  ) $opt['comment_word']= drFun::wordCountEnAndCn(  $opt['comment_word']);
        drFun::strip( $opt );
        if( $opt['comment_word']<=5 ) $this->throw_exception( '哎呦！就不能多写几个字么？', 7518);
        $_file=['novel_id'=>['n'=>'小说ID'],'cp_id'=>['n'=>'章节'],'comment'=>['n'=>'评论'],'ctime'  ,'user_id'=>[ 'n'=>'用户ID'],'school_id','term_key','comment_word' ];
        $opt['ctime']= time();
        $id = $this->insert( $this->tb_comment, $opt,$_file  );
        $this->updateChapterCommentCnt( $opt['novel_id'],$opt['cp_id'] );

        $this->viewWithNovelAndChapter($opt );
        $this->getLogin()->padLogAdd( $id, 487,"做". $opt['comment_word'].'字笔记在《'.$opt['novel'].'》'.$opt['chapter'] );

        $this->getLogin()->createWenda()->upCnt(7, $opt['user_id'], $opt['novel_id']);
        return $this;
    }

    /**
     * 删除笔记
     * @param $comment_id
     * @param $uid
     * @param array $opt
     * @return $this
     */
    function delCommentByID( $comment_id,$uid, $opt=[] ){
        $where = ['comment_id'=> $comment_id];
        $comment= $this->createSql()->select( $this->tb_comment, $where)->getRow();
        if(! $comment ) $this->throw_exception( '评论已不存在！', 7518);
        if( !( $opt['isAdmin'] || $uid == $comment['user_id'] )) $this->throw_exception( '仅本人才能删除', 7517);
        $this->createSql()->delete( $this->tb_comment,$where)->query();
        $this->updateChapterCommentCnt( $comment['novel_id'],$comment['cp_id'] );
        drFun::recycleLog($comment['user_id'],206, $comment  );

        $this->getLogin()->createWenda()->upCnt(7, $comment['user_id'], $comment['novel_id']);
        return $this;
    }

    /**
     * 更新章节读书笔记数
     * @param $novel_id
     * @param $cp_id
     * @return $this
     */
    function updateChapterCommentCnt( $novel_id, $cp_id ){
        $novel = $this->getNovelById( $novel_id );
        $cnt = $this->createSql()->getCount( $this->tb_comment,['novel_id'=>$novel_id,'cp_id'=>$cp_id])->getOne();
        $this->setSite( $novel['site'])->createSql()->update( $this->getTableChapter(),['comment'=>$cnt],['cp_id'=>$cp_id])->query();
        return $this;
    }

    /**
     * 更新章节阅读次数
     * @param $novel_id
     * @param $cp_id
     * @return $this
     */
    function updateChapterViewCnt( $novel_id, $cp_id){
        $novel = $this->getNovelById( $novel_id );
        $this->setSite( $novel['site'])->createSql()->update( $this->getTableChapter(),['+'=>['view'=>1]],['cp_id'=>$cp_id])->query();

        $opt=['novel_id'=>$novel_id ,'cp_id'=>$cp_id ];
        $this->viewWithNovelAndChapter($opt );
        $this->getLogin()->padLogAdd( $cp_id , 481,"刚刚进入阅读《".$opt['novel'].'》'.$opt['chapter'] );

        return $this;
    }

    /**
     * 获取笔记加分页
     * @param $where
     * @return array
     * @throws drException
     */
    function getCommentsWithPage( $where ){
        return $this->createSql()->selectWithPage( $this->tb_comment, $where ,30,[] ,['good_cnt'=>'desc','comment_id'=>'ASC'] );
    }

    /**
     * 获取本章节本人的笔记
     * @param $novel_id
     * @param $cp_id
     * @return mixed
     */
    function getMeCommentCnt( $novel_id, $cp_id ){
        $where = ['novel_id'=>$novel_id,'cp_id'=>$cp_id,'user_id'=>$this->getUserID() ];
        return $this->getCommentCntByWhere( $where );
        //return $this->createSql()->getCount($this->tb_comment,)->getOne();
    }
    function getCommentCntByWhere( $where ){
        return $this->createSql()->getCount($this->tb_comment,$where)->getOne();
    }

    /**
     * 更新打卡用户的学校 仅用过初始化一次
     * @deprecated
     * @return $this
     */
    function ka_school(){
        $col = $this->createSql()->select( $this->tb_view,['school_id'=>0],[],['user_id','school_id'])->getAllByKey('user_id');
        if( !$col ) return $this;
        $school = $this->createSql()->select( 'user',['user_id'=> array_keys( $col)],[],['user_id','school'])->getCol2();
        $school_id = $this->createSql()->select( 'book_school',['school'=> $school],[],['school','id'])->getCol2();

        foreach($school as $uid=>$svar ){
            if(! isset($school_id[$svar] ) ) continue;
            $sid = $school_id[$svar];
            $this->createSql()->update( $this->tb_view,['school_id'=>$sid],['user_id'=>$uid])->query();
            //echo  $sql ."\n";
        }
        //$this->drExit();
        return $this;
    }

    function getViewByNovelId( $novel_id,$where=[] ){
        if( $novel_id<=0  ) $this->throw_exception("ID参数错误！" ,7512);
        $where['novel_id']=$novel_id;
        return$this->createSql()->select( $this->tb_view , $where)->getAllByKey( 'cp_id');
    }

    function getViewList( $where ){
        return $this->createSql()->select( $this->tb_list, $where )->getAll() ;
    }

    /**
     * 统计打卡情况
     * @param $user_id
     * @param $tj
     * @return $this
     * @throws drException
     */
    function getViewTjByUid( $user_id ,&$tj ){
        //$sql = "select ";
        $tarr = $this->createSql()->select( $this->tb_view, ['user_id'=> $user_id],[],['dtime','word','ctime'] )->getAll();
        //$this->drExit(  $this->createSql()->select( $this->tb_view, ['user_id'=> $user_id],[],['dtime','word','ctime'] )->getSQL()  );
        $tj=['cnt'=>0, 'dtime'=>0,'word'=>0 ];
        $day=[];
        foreach( $tarr as $v ){
            $tj['cnt']++ ;
            $tj['dtime'] += $v['dtime'];
            $tj['word'] += $v['word'];
            $day[ date('Ymd',$v['ctime'])]++;
        }
        $tj['m_avg']= $tj['dtime'] <=0? 0: number_format( $tj['word']*60/$tj['dtime'] ,2 );
        $tj['day']= count($day );
        return $this;
    }

    /**
     * 统计读某本书的进度 总进度=章节进度*章节字数/所有字数
     * @param $novel_id
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function doViewListProcessByNovelID( $novel_id ,&$opt=[]){
        //if( $user_id<=0 ) $user_id
        $user_id = $opt['user_id']>0? $opt['user_id']: $this->getUserID();
        $tall = $this->createSql()->select( $this->tb_view, ['user_id'=>$user_id ,'novel_id'=> $novel_id] )->getAll();
        if( !$tall) return $this;
        $row = $this->createSql()->select($this->tb_list,['user_id'=>$user_id ,'novel_id'=> $novel_id] )->getRow();

        #只有状态为1的时候才记录，10为已经完结一定不能记录时间
        if( $row && $row['type']==10 ) return $this;

        $novel= $this->getNovelById( $novel_id );
        //$this->drExit($novel );
        $chapter= $this->createSql()->select($this->setSite( $novel['site'])->getTableChapter(),['site_id'=>$novel['site_id']],[],['cp_id','word'] )->getAll();
        $word=[];
        foreach ($chapter as $v ) $word[$v['cp_id']] = $v['word']>0? $v['word']:1;
        $p_var=['ctime'=>$tall[0]['ctime'],'last_time'=> $tall[0]['ctime'],'dtime'=>0 ,'school_id'=>$tall[0]['school_id'] ,'term_key'=>isset($opt['term_key'])?$opt['term_key'] :$tall[0]['term_key'] ];
        $p_var['cp_cnt'] = count( $tall );
        if( isset($opt['cp_id'] ))  $p_var['last_cp_id'] = $opt['cp_id'] ;
        $process=0;
        //print_r($word );

        foreach($tall as $v ){
            $p_var['ctime']= min(  $p_var['ctime'],$v['ctime'] );
            $p_var['last_time']=  time() ;//max(  $p_var['last_time'],$v['last_time'] );
            $p_var['dtime'] += $v['dtime'] ;
            $process+= $v['progress'] *$word[$v['cp_id']];
        }
        //echo "\n".$process."\n";
        //$this->drExit( $p_var );

        $p_var['progress']= intval($process/array_sum($word ) );

        if(  $p_var['progress']>10000 )    $p_var['progress'] = 10000;
        if(  $p_var['progress']<0 )    $p_var['progress'] = 0;

        $p_var['word']= intval($process/10000 );

        if( $row ){
            unset($p_var['ctime'] );
            $this->update( $this->tb_list,['id'=>$row['id']], $p_var);
        }else{
            $p_var['user_id'] = $user_id;
            $p_var['novel_id'] = $novel_id;
            $this->insert(  $this->tb_list, $p_var) ;
        }
        $opt['process'] = $p_var['progress'];
        return $this;

    }

    function addNovelViewList($novel_id, $user_id, $opt=[]){
        $row = $this->createSql()->select($this->tb_list,['user_id'=>$user_id ,'novel_id'=> $novel_id] )->getRow();
        if(  $row ) $this->throw_exception(  "已存在，请勿重复添加",7540);
        $opt['user_id'] = $user_id;
        $opt['novel_id'] = $novel_id;
        $opt['ctime'] = time();
        if( ! $opt['school_id']) $opt['school_id']= $this->getLogin()->getSchoolID();
        $this->insert(  $this->tb_list, $opt ) ;
        return $this;
    }

    /**
     * 修改 novel_view_list
     * @param int $id
     * @param array $up_opt
     * @return $this
     * @throws \Exception
     */
    function modifyNovelViewList( $id, $up_opt ){
        $this->update(  $this->tb_list, ['id'=> $id ] ,$up_opt );
        return $this;
    }

    /**
     * 自主添加书本
     * @param $novel
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function addNovelFromHaoce( $novel, &$opt=[] ){
        if( ! trim($novel) ) $this->throw_exception( "参数错误！",7514);
        $row = $this->createSql()->select( $this->tb_novel,['novel'=> $novel] )->getRow();
        if( $row ) $this->throw_exception($novel.' 已添加过！',7513 );
        $opt['novel']= $novel;
        $opt['ctime']= time();
        $opt['site']= 'haoce'; //if( ! isset($opt['site']) )
        $this->insert($this->tb_novel, $opt,['novel','ctime','site','user_id','is_shuan']); //,'img'
        $id = $this->createSql()->lastID();
        $this->update( $this->tb_novel,['novel_id'=>$id],['site_id'=>$id ]);
        $opt['novel_id']= $id;
        return $this;
    }

    /**
     * 自主添加章节
     * @param $novel_id
     * @return $this
     */
    function addChapterFromHaoce( $novel_id ){
        $novel = $this->getNovelById($novel_id );
        //if( $novel['site']!='haoce' ) $this->throw_exception("目前仅支持好策自添加书" ,7515);
        //$cnt =  $this->setSite('haoce')->createSql()->getCount( $this->getTableChapter(), ['site_id'=> $novel_id] )->getOne();
       if ($novel['site']!='haoce' && $novel['site_id'] ) $novel_id= $novel['site_id'];
        $cnt =  $this->setSite(  $novel['site'] )->createSql()->getCount( $this->getTableChapter(), ['site_id'=> $novel_id] )->getOne();
        $cnt++;
        $this->setSite( $novel['site'])->insert( $this->getTableChapter(), ['site_id'=> $novel_id,'chapter'=>'新章节'.$cnt ,'ctime'=> time(),'chapter_id'=>$cnt ]);
        return $this;
    }



    /**
     * 统计---以天、日为维度 对查词、打卡进行统计
     * @param $where
     * @param array $opt opt.key 统计维度day/user/month，
     * @return array
     */
    function  tjViewDate( $where ,$opt=[]){

        if( $opt['tb']=='dict' ){
            $tall= $this->createSql()->select( $this->tb_dict,$where,[0,12000],['user_id','novel_id' ,'word','school_id','ctime'] )->getAll();
        } else{
            $tall= $this->createSql()->select( $this->tb_view,$where,[0,10000],['user_id','novel_id','dtime','word','school_id','ctime'],['view_id'=>'desc'] )->getAll();
        }
        $school=[]; $novel=[]; $user=[]; $day=[];
        $re=[];
        foreach($tall as &$v ){
            $k= $this->getTjKey($v,  $opt['key']) ;
            $re[$k]['cp']++;
            if( isset($v['dtime']) ) $re[$k]['time']+=$v['dtime'];
            if( $opt['tb']=='dict' ){
                //$re[$k]['word']+=$v['word'];
            }else{
                $re[$k]['word']+=$v['word'];
                $day[$k][  date("Ymd", $v['ctime']) ]= 1 ;
            }

            $school[$k][ $v['school_id']]=  $v['school_id'];
            $user[$k][ $v['user_id']]=  $v['user_id'];
            $novel[$k][ $v['novel_id']]=  $v['novel_id'];
        }
        foreach ( $re as $k=> &$v  ){
            $re[$k]['school']= count( $school[$k]);
            $re[$k]['user']= count( $user[$k]);
            $re[$k]['novel']= count( $novel[$k]);
            if( $day ) $re[$k]['day']= count($day[$k] );
        }
        krsort( $re);
        if( $opt['out']=='array'){
            $re2=[]; $year = date("Y"); //=strtr( $k,[$year=>''] )
            foreach( $re as $k=> $v){  $v['key'] = $k ; $re2[]=$v;}
            return $re2;
        }
        //$this->drExit( $re );
        return $re ;
    }

    private function  getTjKey( $var, $opt_key){
        switch ($opt_key){
            case 'user':
            case 'user_id':
                return $var['user_id'] ;
                break;
            case 'month':
                return date("Ym", $var['ctime']);;
                break;
            case 'day':
            default:
                return date("Ymd", $var['ctime']);;
        }
        return date("Ymd", $var['ctime']);
    }



    function chart_bar( $data , $file =['user'=>'人数','cp'=>'章节','word'=>'字数（万字）','time'=>'阅读时间(小时)'] ){

        if( isset($file['word'])) drFun::numFormat( $data, 'word',['bei'=>10000]);
        if( isset($file['time'])) drFun::numFormat( $data, 'time',['bei'=>3600]);
        //if( isset($file['time'])) drFun::numFormat( $data, 'time',['bei'=>60]);

        $re['categories']= [];//array_keys( $data );
        $re['series']= [];
        $i=0;
        foreach ( $data as $k=>$v  ){
            if($i>15 ) break;
            $re['categories'][]=$k;
            foreach($file as $k2=>$v2  ){
                $re['series'][ $v2][]= $v[$k2];
            }
            $i++;
        }
        $se=[];
        foreach (  $re['series'] as $k=>$v )$se[]= ['name'=>$k,'data'=>$v ];
        $re['series']= $se;
        unset( $se);
        return $re ;
    }

    /**
     * 画图进度条
     * @param $list
     */
    function chart_bar_precent( $list ){
        $novel=[];
        $where=[];
        foreach( $list as $v ){
            $novel[]=$v['novel_id'];
            $where[]='(user_id='.$v['user_id']." and novel_id='".$v['novel_id']."' )";
        }
        $chapterList = $this->getChapterByNovelID($novel,['file'=>['cp_id','site_id','word','chapter'] ] );
        $cp_view = $this->createSql("select * from ".$this->tb_view." where  ". implode(' or ',$where ))->getAllByKeyArr(['user_id','novel_id','cp_id']);
        $max=0; $chapter=[];
        foreach( $chapterList as $id=> &$vList){
            $max = max( $max, count($vList ));
            foreach( $vList as $v ) $chapter[$id][]=$v[0];
        }
        $item=[];   for($i=0 ,$c=count($list);$i< $c;$i++)$item[]=0;
        $re=[];     for($i=0 ,$c=2*$max;$i< $c;$i++ ) $re[]= $item;
        foreach( $list as $k=> $v ){
            $i=0;
            foreach($chapterList[$v['novel_id']] as $cp_id=> $v2 ){
                $word = $v2[0]['word'];
                $processt = isset($cp_view[$v['user_id']][ $v['novel_id']][ $cp_id ] )?$cp_view[$v['user_id']][ $v['novel_id']][ $cp_id ][0]['progress'] :0;
                $re[$i*2][ $k ]= intval( $word-$word*$processt/10000);
                $re[$i*2+1][ $k ]= intval( $word*$processt/10000);
                $i++;
            }
        }

        //$this->assign('chapterList',$chapterList );
        $this->assign('chapter',$chapter );
        $this->assign('max',$max )->assign('bar',$re )->assign('cp_view',$cp_view );


        //$this->assign('cp_view', $cp_view );
    }

    function chart_bar_precent_byUid( &$list  ,$user_id ){
        $novel=[];
        $where=[]; $kv=[];
        foreach( $list as $k=> $v ){
            $novel[]=$v['novel_id'];
            $where[]='(user_id='.$user_id ." and novel_id='".$v['novel_id']."' )";
            $kv[ $v['novel_id'] ] =$k;
        }
        if( !$novel ) return $this;
        $chapterList = $this->getChapterByNovelID($novel,['file'=>['cp_id','site_id','word','chapter'] ,'order'=>['cp_id'=>'asc'] ]  );

        $cp_view = $this->createSql("select * from ".$this->tb_view." where  ". implode(' or ',$where ))->getAllByKeyArr(['novel_id','cp_id']);


        foreach($chapterList as $novel_id=>$v  ){
            $total=0; $novel = [];
            foreach( $v as $k2=>$v2 ){
                $var = $v2[0];
                $var['process']= intval($cp_view[$novel_id ][$k2][0]['progress'])/100;
                $var['p_info']= $cp_view[$novel_id ][$k2][0];
                $novel[]= $var;
                $total += $var['word'];
            }
            foreach( $novel as &$v3 ) $v3['t']= ceil(10000*$v3['word']/$total)/100;
            $list[ $kv[$novel_id] ]['bar']= $novel;
        }
        //$this->drExit( $list );
        return $this;
    }

    /**
     * 获取章节
     * @param $novel_id
     * @param array $opt
     * @return array
     */
    function getChapterByNovelID( $novel_id ,$opt=[]){
        if( !is_array( $novel_id)) $novel_id[]= $novel_id;
        $novel = $this->getNovelById( $novel_id );
        $site=[];
        foreach( $novel as  $v)       $site[$v['site']][]=$v['site_id'];
        $re=[];
        $order =['chapter_id'=>'desc','cp_id'=>'desc'];
        if( isset($opt['order'])) $order= $opt['order'] ;
        foreach($site as $site_name=>$v  ){
            $re[$site_name] = $this->setSite($site_name )->createSql()->select($this->getTableChapter(), ['site_id'=>$v ],[], isset($opt['file'])?$opt['file']:[], $order )->getAllByKeyArr(['site_id','cp_id']);
        }

        //$this->drExit( $re );
        $list=[];
        foreach( $novel as $v ){
            $list[$v['novel_id']]= $re[ $v['site']][$v['site_id']];
        }

        return $list ;
    }

    /** 统计打卡总体情况
     * @param $where
     * @param array $opt opt.tb='dict' 统计词典
     * @return array
     */
    function tjView( $where ,$opt=[]){

        $start=0;$every=50000;
        $school = $novel = $user=$word= [];


        while(1) {
            if ($opt['tb'] == 'dict') {
                if( $start==0)    $re = ['user' => 0, 'novel' => 0, 'cp' => 0, 'word' => 0, 'school' => 0];
                $tall = $this->createSql()->select($this->tb_dict, $where, [$start,$every], ['user_id', 'novel_id', 'word', 'school_id'], ['dict_id' => 'desc'])->getAll();
            } else {
                if( $start==0)      $re = ['user' => 0, 'time' => 0, 'novel' => 0, 'cp' => 0, 'word' => 0, 'school' => 0];
                $tall = $this->createSql()->select($this->tb_view, $where, [$start,$every], ['user_id', 'novel_id', 'dtime', 'word', 'school_id'], ['view_id' => 'desc'])->getAll();
            }
            if( !$tall ) break;


            foreach ($tall as $v) {
                $re['cp']++;
                $school[$v['school_id']] = $v['school_id'];
                $user[$v['user_id']] = $v['user_id'];
                $novel[$v['novel_id']] = $v['novel_id'];

                if ($opt['tb'] == 'dict') {
                    $word[strtolower($v['word'])] = 1;
                } else {
                    $re['time'] += $v['dtime'];
                    $re['word'] += $v['word'];
                }
            }
            $start+= $every;
        }
        $re['school']= count( $school);
        $re['user']= count( $user);
        $re['novel']= count( $novel);
        if( $opt['tb']=='dict') {
            $re['word']= count( $word);
        }
        return $re ;
    }

    /**
     * 统计view第二个版本
     * @param $where
     * @param array $opt
     * @return array|mixed
     */
    function tjViewV2($where ,$opt=[] ){
        if( isset($opt['cache_key']) && $opt['cache_key'] ){
            $tj_view= json_decode( $this->getLogin()->createCache()->getClass()->get( $opt['cache_key'] ) ,true);
            if( $tj_view  )  return $tj_view ;
        }
        $re = ['user' => 0, 'time' => 0, 'novel' => 0, 'novel_cnt' => 0, 'cp' => 0, 'word' => 0, 'school' => 0 ];

        $tb= $this->tb_list;
        $where =   $this->createSql()->arr2where( $where);
        $tem = $this->createSql(  "select COUNT( * ) AS novel_cnt, SUM(  `dtime` ) AS time, SUM( cp_cnt ) AS cp, SUM( word ) AS word from ".$tb ." where " . $where  )
            ->getRow();
        foreach( $tem as $k=>$v ) $re[$k]= $v ;
        if( isset( $opt['finish'] )){
            $re['finish']=$this->createSql( "select COUNT( * ) AS novel_cnt  from ".$tb ." where " . $where ."  and progress='10000'")->getOne();
            $re['xuefen']=$this->createSql( "select COUNT( * ) AS novel_cnt  from ".$tb ." where " . $where ."   and type='10' ")->getOne();
        }else {
            $re['user'] = $this->createSql(" select count(*) as cnt from ( select user_id from `" . $tb . "` where " . $where . " group by user_id ) aa")->getOne();
            $re['novel'] = $this->createSql(" select count(*) as cnt from ( select novel_id from `" . $tb . "`  where " . $where . " group by novel_id ) aa")->getOne();
            $re['school'] = $this->createSql(" select count(*) as cnt from ( select school_id from `" . $tb . "`  where " . $where . " group by school_id ) aa")->getOne();
        }
        if( isset($opt['cache_key']) && $opt['cache_key'] ){
            $this->getLogin()->createCache()->getClass()->set($opt['cache_key'] ,$re,300 );
        }
        foreach( $re as &$v ) $v = intval( $v );
        return $re ;
    }

    /**
     * 统计笔记
     * @param $where
     * @return array
     */
    function tjComment( $where ){
        $tb = $this->tb_comment;
        $where = $this->createSql()->arr2where( $where );
        $re=['cnt'=>0,'word'=>0];
        $tem=$this->createSql( "select COUNT( * ) AS cnt , SUM( comment_word ) AS word from ".$tb ." where " . $where )
            ->getRow();
        foreach( $tem as $k=>$v ) $re[$k]= intval( $v );
        return $re ;
    }

    /**
     * 统计查词
     * @param $where
     * @param array $opt
     * @return array
     */
    function tjDict( $where ,$opt=[] ){
        if( isset($opt['cache_key']) && $opt['cache_key'] ){
            $tj_view= json_decode( $this->getLogin()->createCache()->getClass()->get( $opt['cache_key'] ) ,true);
            if( $tj_view  )  return $tj_view ;
        }
        $tjKey=['cp','novel','user'];
        if( isset($opt['tjKey'] )) $tjKey = $opt['tjKey'];
        $re = ['user' => 0, 'novel' => 0, 'cp' => 0 , 'school' => 0,'word' => 0];
        $where =   $this->createSql()->arr2where( $where);
        $tb=  $this->tb_dict ;
        if(in_array('cp', $tjKey ) ) $re['cp']= $this->createSql("select count(*) as cnt from ".$tb."  where ". $where)->getOne();
        if(in_array('novel', $tjKey ) )  $re['novel']=$this->createSql(" select count(*) as cnt from ( select novel_id from `".$tb."`  where ".$where." group by novel_id ) aa")->getOne();
        if(in_array('user', $tjKey ) )   $re['user']=$this->createSql( " select count(*) as cnt from ( select user_id from `".$tb."` where ".$where." group by user_id ) aa")->getOne();
        if(in_array('word', $tjKey ) )   $re['word']=$this->createSql( " select count(*) as cnt from ( select word from `".$tb."` where ".$where." group by word ) aa")->getOne();
        if( $where=='1' )$re['school']=$this->createSql( " select count(*) as cnt from ( select school_id from `".$tb."`  where ".$where." group by school_id ) aa")->getOne();

        if( isset($opt['cache_key']) && $opt['cache_key'] ){
            $this->getLogin()->createCache()->getClass()->set($opt['cache_key'] ,$re,300 );
        }
        foreach( $re as &$v) $v= intval( $v );
        return $re ;
    }


    function getViewListWithPage( $where){
        $list = $this->createSql()->selectWithPage( $this->tb_view, $where,30,[],['view_id'=>'desc'] );
        return $list;
    }

    /**
     * 将列表信息汇入书名、章节等资料
     * @param $list
     * @return $this
     */
    function viewWithNovelAndChapter( &$list ){
        if( !$list ) return $this;
        $novel_id =[];
        $is_dan= false ;
        if( isset($list['novel_id'] ) && isset($list['cp_id'] ) ){
            $list=[$list]; $is_dan =true;
        }

        foreach( $list as $v ){
            $novel_id[$v['novel_id']][$v['cp_id']]= $v['cp_id'];
        }

        $novel= $this->createSql()->select( $this->tb_novel, ['novel_id'=> array_keys( $novel_id)],[],['novel_id','novel','site'])->getAllByKey('novel_id');

        $chapter_id=[];
        foreach( $novel as $v ){
            $t_cp = $novel_id[ $v['novel_id']];
            foreach($t_cp as $v2 ) $chapter_id[ $v['site']][$v2 ]=$v2;
        }

        $chapter=[];
        foreach( $chapter_id as $site=>$v ){
            $chapter[$site] =$this->setSite( $site)->getChapterByID( $v ,['cp_id','chapter']);
        }
        foreach( $list as $k=>$v ){
            $novel_id = $v['novel_id']; $cp_id=  $v['cp_id'];
            $site= $novel[ $novel_id ]['site'];
            $list[$k]['site']= $site;
            $list[$k]['novel']=  $novel[ $novel_id ]['novel'];
            $list[$k]['chapter']=    $chapter[$site][$cp_id ]['chapter'];
        }
        if( $is_dan ) $list =$list[0];
        return $this;
    }

    /**
     * 从一本书弄成2本
     * @param $novel_id
     * @return $this
     */
    function copyToEnCn( $novel_id){
        $novel = $this->getNovelById( $novel_id );
        if( !($novel['site']=='8848'|| 'xmly'==$novel['site'] )) $this->throw_exception("该本书不支持中英分离", 7519);
        $this->assign('novel', $novel );
        $chapter = $this->setSite( $novel['site'])->getNovelChapter( $novel['site_id']);
        switch ( $novel['is_shuan'] ){
            case 4:
                foreach ($chapter as &$v  )  $v['new']=['en'=> $v['body'],'cn'=>$v['body_cn'] ];
                //$this->assign('chapter',$chapter );
                //
                break;
            case 5:
            case 8:
            case 2:
                foreach ($chapter as &$v  ) $this->split2EnCn($v ,$novel['is_shuan']  );
                //$this->drExit( $chapter );
                break;
            default:
                $this->throw_exception("该类型不支持！",7520);
        }




        $this->copyToEnCnInsert($novel,$chapter,'en' );
        $this->copyToEnCnInsert($novel,$chapter,'cn' );


        //
        return $this;
    }

    /**
     * 分离的书入库
     * @param $novel
     * @param $chapter
     * @param $en_cn
     * @return $this
     */
    function copyToEnCnInsert( $novel, $chapter,$en_cn){
        $arr=['en'=>'[英]','cn'=>'[中]'];
        $novel['user_id'] = $this->getUserID();
        $novel['is_shuan']= $en_cn=='cn'?3:1;
        $this->addNovelFromHaoce( $arr[$en_cn].$novel['novel'],$novel  );
        $novel_id=  $novel['novel_id'];
        $this->setSite('haoce');
        //$this->setSite( $novel['site']);
        foreach ( $chapter as $v ){
            $v['site_id']= $novel_id;
            if($en_cn=='cn'){
                $v['body_cn']= $v['new'][$en_cn];
                $v['body']='';
            }else{
                $v['body']= $v['new'][$en_cn];
                $v['body_cn']='';
            }
            unset($v['new'] ); unset($v['cp_id'] );
            $this->insert( $this->getTableChapter(), $v,['body','body_cn','site_id','chapter_id','chapter','mp3','mp3_local','ctime'] );
        }
        $this->updateChapterWordByNovel( $novel_id);
        return $this;
    }

    /**
     * 为入库、切分
     * @param $chapter
     * @param int $is_shuan
     * @return $this
     */
    function split2EnCn( &$chapter ,$is_shuan=2 ){
        switch ($is_shuan){
            case 5:
            case 8:
                $html = strip_tags($chapter['body']?$chapter['body']:$chapter['body_cn'] );
                $arr= preg_split( "/[\r\n]+/", strtr( $html,$this->r_arr));
                break;
            case 2:
                $html = strip_tags($chapter['body'], '<div>');
                preg_match_all('|<div[^<>]+>([^<>]+)</div>|U', $html, $out, PREG_PATTERN_ORDER);
                $arr = $out[1];
                break;
            default:
                $this->throw_exception("该类型不支持！",7521 );
                break;
        }
        $chapter['new']=['cn'=>'','en'=>''];
        foreach($arr as $k =>&$v ){
            $v= trim( $v );
            if( $k%2)   $chapter['new']['cn'].=$v."\n";
            else    $chapter['new']['en'].=$v."\n";
        }
        return $this;
    }

    /**
     * 将章节内容转化为前端可用的段落信息
     * @param $chapter
     * @param $is_shuan
     * @param array $opt
     * @return array
     */
    function chapterToJson( $chapter,$is_shuan, $opt=[]){
        //$this->assign('old_chapter', $chapter);
        $re=[];
        $r_arr= $this->r_arr;
        switch ($is_shuan ){

            case '2':  #中英交叉HTML
                $html = strip_tags($chapter['body'],'<div>' );
                preg_match_all ('|<div[^<>]+>([^<>]+)</div>|U', $html,     $out, PREG_PATTERN_ORDER);
                if( !$out[1] || count( $out[1] )<=0   ){
                    $this->initContents($chapter, preg_split( "/[\r\n]+/", strip_tags( $chapter['body']) ),$re,'en')
                       ;// ->initContents($chapter, preg_split( "/[\r\n]+/", strip_tags( $chapter['body_cn']) ),$re,'cn');
                }else{
                    $this->initContents($chapter, $out[1] ,$re,'%' );
                }
                break;
            case 4: #中英单独
            case 7: #中日单独
                $this->initContents($chapter, preg_split( "/[\r\n]+/", strip_tags( $chapter['body']) ),$re,'en')
                ->initContents($chapter, preg_split( "/[\r\n]+/", strip_tags( $chapter['body_cn']) ),$re,'cn');
                break;
            case 5: #中英交叉
            case 8: #中日交叉
                $html = trim(  strip_tags(trim($chapter['body'])?$chapter['body']:$chapter['body_cn'] ) );
                $arr= preg_split( "/[\r\n]+/", strtr( $html,$r_arr));
                $this->initContents($chapter,$arr,$re,'%' );
            break;
            default:
                $html = strip_tags(trim($chapter['body'])?$chapter['body']:$chapter['body_cn'] );
                //if(  $chapter['cp_id'] == 5987 ) $this->drExit( $html );
                $arr= preg_split( "/[\r\n]+/",   strtr( $html,$r_arr) );
                $this->initContents($chapter,$arr,$re,'en' );
                break;
        }
        if( count($re)<=0 )   $this->initContents($chapter,['本章节是空的请联系管理员！'],$re,'en' );



        return ['id'=>intval($chapter['cp_id']),'title'=>$chapter['chapter'] ,'contents'=>$re ] ;
    }

    /**
     * 组装段落信息
     * @param $chapter
     * @param $arr
     * @param $re
     * @param string $l_en_cn
     * @return $this
     */
    function initContents($chapter,$arr, &$re, $l_en_cn ='en'){
        $j=0;
        foreach ( $arr as $k=>$v ){
            $v= trim( $v);
            if($v=='') continue;
            $en_cn= $l_en_cn;
            if( $l_en_cn ==='%' )   $en_cn= $j%2?'cn':'en';
            if($en_cn!='cn') $this->margeSpan($v);
            $var=['text'=> $v,'type'=>'text','id'=>$chapter['cp_id']*100000+$j, 'format'=>['align'=>'left','bold'=>false,'italic'=>false,'size'=>1 ]  ] ;
            $var['lg']= $en_cn;
            $re[]= $var;
            $j++;
        }
        return $this;
    }

    /**
     * 加入 span 标签 便于前端查词
     * @param $v
     * @return $this
     */
    function margeSpan( &$v ){
        $v = preg_replace('/([a-z\-]+)([^a-z\-]+)/i','<span>$1</span>$2', strip_tags( trim(html_entity_decode( $v )) ));
        return $this;
    }

    /**
     * 结业，领取学分
     * @param $novel_id
     * @param int $user_id
     * @param int $school_id
     * @param array $opt
     * @return array
     */
    function quXuefen( $novel_id, $user_id=0 ,$school_id=0 ,$opt=[]){
        $re=['vList'=>[] ];
        $user_id= $user_id<=0? $this->getUserID(): $user_id;
        $school_id = $school_id<=0 ? $this->getLogin()->getSchoolID(): $school_id;

        $re['vList']  =  $this->createSql()->select( $this->tb_list, ['novel_id'=>$novel_id,'user_id'=> $user_id ])->getRow();
        if(  $re['vList']['type']==10) $this->throw_exception("您已取过学分！", 7526);

        $re['error']=[];

        if ( !$re['vList']) $this->throw_exception("您没看这本书", 7522);
        try{
            if ( $re['vList']['progress'] < 10000) $this->throw_exception("目前进度到 " . ($re['vList']['progress'] / 100) . "%,请先完成阅读！", 7523);
            if (($re['vList']['dtime'] <= 0 || $re['vList']['word'] / $re['vList']['dtime'] > 20))
                $this->throw_exception("您读得太快，常人做不到！", 7524);
        }catch (drException $ex ){
            if(  $opt['check'] ) $this->throw_exception( $ex->getMessage(),$ex->getCode() );
            $re['error']['vList']= ['error_des'=> $ex->getMessage(),'error'=> $ex->getCode()  ];
        }

        $re['wenda'] = $this->getLogin()->createWenda()->getMyRequestByNovelId( $novel_id );
        $this->getLogin()->createWenda()->margeFinish( $re['wenda']  ,  $novel_id );

        try{
            foreach(  $re['wenda'] as $v ){
                if( ! $v['finish'] )   $this->throw_exception("任务待完成", 7525);
            }
        }catch ( drException $ex ){
            if(  $opt['check'] ) $this->throw_exception( $ex->getMessage(),$ex->getCode() );
            $re['error']['wenda']= ['error_des'=> $ex->getMessage(),'error'=> $ex->getCode()  ];
        }
        if( count($re['error'] )<=0 ) {
            $this->doViewListProcessByNovelID( $novel_id );
            $this->update( $this->tb_list, ['id'=> $re['vList']['id'] ],['last_time'=>time(),'term_key'=>$this->getLogin()->getCheckTerm() ,'type'=>10 ]);
        }
        return $re ;
    }

    function quXueFenV2( $novel_id, $user_id=0 ,$opt=[] ){
        $re=[];
        $re['progress']=['text'=>'阅读进度达到100%','cls'=>'success'];
        $re['time']=['text'=>'阅读质量已达标','cls'=>'success'];
        $re['note']=['text'=>'笔记未提交','cls'=>'error'];
        $re['report']=['text'=>'读书报告未提交','cls'=>'error'];
        $re['discuss']=['text'=>'请发布至少一篇讨论','cls'=>'warn'];
        $re['suixiang']=['text'=>'随想未提交','cls'=>'warn'];

        $user_id= $user_id<=0? $this->getUserID(): $user_id;
        //$school_id = $school_id<=0 ? $this->getLogin()->getSchoolID(): $school_id;

        $vList =  $this->createSql()->select( $this->tb_list, ['novel_id'=>$novel_id,'user_id'=> $user_id ])->getRow();
        if (  ! $vList ) $this->throw_exception("您没看这本书", 7522);

        try{
            if ( $vList['progress'] < 10000) $this->throw_exception("目前进度到 " . intval($vList['progress'] / 100) . "%,请先完成阅读！", 7523);
        }catch (drException $ex ){
            $re['progress']['text']= $ex->getMessage();
            $re['progress']['cls']='error';
        }

        try{
            if ( $vList['dtime'] <= 0 || $vList['word'] /$vList['dtime'] > 20)                $this->throw_exception("您读得太快，常人做不到！", 7524);
        }catch (drException $ex ){
            $re['time']['text']= '阅读质量未达标';
            $re['time']['cls']='warn';
        }

        $note_cnt = $this->createSql()->getCount( $this->tb_comment,['novel_id'=>$novel_id,'user_id'=> $user_id ] )->getOne();
        if( $note_cnt>0  )  $re['note']=['text'=>'笔记已完成','cls'=>'success'];

        $sui_cnt = $this->getLogin()->createWenda()->checkTask('suixiang',['novel_id'=>$novel_id,'user_id'=> $user_id ,'cat_id'=>4]  );
        if($sui_cnt>0  ) $re['suixiang']=['text'=>'随想已完成','cls'=>'success'];

        $report_cnt = $this->getLogin()->createWenda()->checkTask('report', ['novel_id'=>$novel_id,'user_id'=> $user_id ,'pid'=>1001] );
        if( $report_cnt )  $re['report']=['text'=>'读书报告已完成','cls'=>'success'];

        $discuss_cnt = $this->getLogin()->createWenda()->checkTask('discuss',['novel_id'=>$novel_id,'user_id'=> $user_id ,'cat_id'=>1]  );
        if( $discuss_cnt ) $re['discuss']=['text'=>'讨论已完成','cls'=>'success'];

        if( $this->getUserID()== $user_id  && $vList['type']!=10 ){
            $success= true;
            foreach ($re as $v ) if( $v['cls']=='error') $success= false;
            if( $success){
                $this->doViewListProcessByNovelID( $novel_id )
                    ->update( $this->tb_list, ['id'=> $vList['id'] ],['last_time'=>time(),'term_key'=>$this->getLogin()->getCheckTerm() ,'type'=>10 ]);
            }
        }
        return ['rz'=> $re ];

    }

    function rank( $where ,$group , $key ,$opt=[] ){
       $limit=[0,100];
       $tb = $this->tb_list;
       $order= isset( $opt['order'])?$opt['order'] : [];
       return $this->createSql()->rank( $tb , $where,  $group , $key  , $limit ,$order)->getAll();
    }

    function getMeDictListWithStartEvery( $user_id =0 ){
        $user_id= $user_id>0? $user_id: $this->getUserID();
        return $this->createSql()->setStartEvery()->select( $this->tb_dict, ['user_id'=> $user_id],[],['word','count(word) as cnt' ],['cnt'=>'desc'],['group'=>'word'])->getALL() ;
    }


    /**
     * 阅读记录重复
     * @return $this
     * @throws \Exception
     */
    function tbListOne(){
        $tall = $this->createSql("SELECT  `novel_id` ,  `user_id` , COUNT( * ) AS cnt FROM  `novel_view_list` GROUP BY  `novel_id` ,  `user_id` HAVING cnt >1 ")->getAll();
        if(! $tall ) return $this;
        $del=[];
        foreach( $tall as $v ){
            unset( $v['cnt']);
            $tem_arr = $this->createSql()->select( $this->tb_list, $v ,[],[],['progress'=>'desc'] )->getAll();
            foreach( $tem_arr as $k2=>$v2 ){
                if( $k2>0 ){
                    $del[]= $v2['id'];
                }
            }
        }
        //$this->drExit($del );
        if( $del ) $this->createSql()->delete( $this->tb_list,['id'=> $del], 500)->query();
        //$sql=  $this->createSql()->delete( $this->tb_list,['id'=> $del], 500)->getSQL();
        //$this->throw_exception( $sql );
        return $this;
    }

    function getViewListMaxID(){
        return $this->createSql("select max(id) from ". $this->tb_list )->getOne();
    }


}