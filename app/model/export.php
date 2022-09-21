<?php
/**
 * 导出
 */

namespace model;


use model\lib\excel;

class export extends model
{
    function __construct( )
    {

    }
    function novelList(){
        //$this->drExit( self::$dsData );
        $head=[ 'novel_id'=>'ID',  "novel"=>"书名",  "cat"=> "类别", "is_yin_str"=> "音频" ,"is_shuan_str"=>"双语/无内容" ];
        $ex= new excel();
        $shuang=[0=>'无内容',2=>'双语'];
        foreach (  self::$dsData['list']['list'] as &$v ) {
            $v['is_yin_str']= $v['is_yin']?'含':'';
            $v['is_shuan_str']= $shuang[ $v['is_shuan'] ] ;
        }
        $ex->saveByHeadLine( $head, self::$dsData['list']['list'] );
    }

    function ma2cwtj(){
        $head=[ 'name'=>'码商',  "realname"=>"姓名"
            ,  "cz"=> "充值金额", "cz_cnt"=> "充值单数"
            ,"qdan"=>"抢单金额" ,"qdan_cnt"=>"抢单单数"
            ,'bu'=>'补单金额','bu_cnt'=>'补单单数' ];
        $head['jiang']='佣金';
        $head['jiang_cnt']='佣金单数';
        $head['run']='分润';
        $head['run_cnt']='分润单数';

        $var=[];
        foreach(   self::$dsData['mauser']['list'] as $k=>$v ){
            $uid=$v['user_id'];
            $tem=[];
            $tem['name']= $v['user_id_merge']['name'];
            $tem['realname']= $v['realname'];

            $tem['cz']= abs( self::$dsData['tj']['cz'][$uid]? self::$dsData['tj']['cz'][$uid]['realprice']/100:'0' );
            $tem['cz_cnt']= self::$dsData['tj']['cz'][$uid]? self::$dsData['tj']['cz'][$uid]['cnt']:'0';

            $tem['qdan']= abs( self::$dsData['tj']['qdan'][$uid]? self::$dsData['tj']['qdan'][$uid]['realprice']/100:'0' );
            $tem['qdan_cnt']= self::$dsData['tj']['qdan'][$uid]? self::$dsData['tj']['qdan'][$uid]['cnt']:'0';

            $tem['bu']= abs( self::$dsData['tj']['bu'][$uid]? self::$dsData['tj']['bu'][$uid]['realprice']/100:'0' );
            $tem['bu_cnt']= self::$dsData['tj']['bu'][$uid]? self::$dsData['tj']['bu'][$uid]['cnt']:'0';

            $tem['jiang']= abs( self::$dsData['tj']['jiang'][$uid]? self::$dsData['tj']['jiang'][$uid]['realprice']/100:'0' );
            $tem['jiang_cnt']= self::$dsData['tj']['jiang'][$uid]? self::$dsData['tj']['jiang'][$uid]['cnt']:'0';

            $tem['run']= abs( self::$dsData['tj']['run'][$uid]? self::$dsData['tj']['run'][$uid]['realprice']/100:'0');
            $tem['run_cnt']= self::$dsData['tj']['run'][$uid]? self::$dsData['tj']['run'][$uid]['cnt']:'0';

            $var[]= $tem;

            //$tj['cz'][$uid]?($tj['cz'][$uid]['realprice']/100).'/'.$tj['cz'][$uid]['cnt'] :'-'
        }
        $ex= new excel();
        $ex->saveByHeadLine( $head,  $var );
    }

    function tjZone( $tj ){
        $header=['lo'=>'省市','realprice'=>'交易金额','cnt'=>'笔数'];
        $ex= new excel();
        $tj=  array_values( $tj );
        usort($tj, function ($a,$b){ return $a['cnt']<$b['cnt']?1:-1; });
        $ex->saveByHeadLine( $header,  $tj );
    }


    /**
     * @return excel
     */
    function createExcel(){
        return new excel();
    }

    function bookList(){
        $head=['book'=>'书名','book_writer'=>'作者','book_isbn'=>'ISBN','cat'=>'类别','difficult_rank'=>'难度' ,'novel_id'=>'绑定小说'];
        $head['user_cnt']='成员';       $head['topic_cnt']='主题';        $head['comment_cnt']='评论';        $head['tag_3_cnt']='朗读';
        $head['tag_4_cnt']='期中';      $head['tag_5_cnt']='期末';        $head['tag_6_cnt']='摘抄';
        foreach (  self::$dsData['list']['list'] as &$v ) {
            $isbn= $v['book_isbn'];
            $v['cat']=  self::$dsData['bookIsbn'][$isbn ]['cat'];
            $v['difficult_rank']=  self::$dsData['bookIsbn'][$isbn ]['difficult_rank'];
            $v['novel_id']=  self::$dsData['bookIsbn'][$isbn ]['novel_id'];
        }
        $this->createExcel()->saveByHeadLine( $head, self::$dsData['list']['list'] );
    }

    function blockList(){
        //选读/完成/领取	耗时/字数/笔记
        $head=['novel'=>'书名','read_cnt'=>'选读','finish_cnt'=>'完成','xuefen_cnt'=>'领取','dtime'=>'耗时（单位秒）' ,'word'=>'字数','comment_cnt'=>'笔记','wenda'=>'问答'];
        foreach (  self::$dsData['list']['list'] as &$v ) {
            $novel_id = $v['novel_id'];
            $v['novel']=  self::$dsData['novel'][ $novel_id ]['novel'];
        }
        $this->createExcel()->saveByHeadLine( $head, self::$dsData['list']['list'] );

    }

    function blockUser(){
        $head=['user_id'=>'UID','name'=>'姓名','number'=>'学号','openid'=>'手机号码','cnt'=>'选书' ,'word'=>'字数'  ,'dtime'=>'时长（秒）'];
        foreach (  self::$dsData['list'] as &$v ) {
            $v['name']= $v['user_id_merge']['name'];
            $v['number']= $v['user_id_merge']['number'];
            $v['openid']= $v['oauth_merge']['openid'];
        }
        $this->createExcel()->saveByHeadLine( $head,  self::$dsData['list'] );

    }

    function schoolUserList(){
        $head=['name'=>'姓名','number'=>'学号','class'=>'班级','class_id'=>'班级号','teacher'=>'任课老师' ,'user_id'=>'UID','book_cnt'=>'选书'];
        $this->createExcel()->saveByHeadLine( $head, self::$dsData['schoolUser']['list'] );
    }

    function studentList(){
        $header = ['k'=>'序号','number'=>'学号','name'=>'姓名' ,'class'=>'班级' ,'book_cnt'=>'选书','wc_cnt'=>'完成','bu'=>'补选' ,'tag_last_cnt_cj'=>'最终分数','topic_cnt'=>'讨论' ,'comment_cnt'=>'回帖' ,'tag_0_cnt_cj'=>'讨论成绩'];

        foreach( self::$dsData['tags'] as $k=>$v ){
            if($k<=0) continue;
            $header['tag_'.$k.'_cnt']=$v['n'];
            $header['tag_'.$k.'_cnt_cj']=$v['n'].'成绩';
        }
        $otherList=[];
        $header2 = $header;
        $ik=0;
        foreach( self::$dsData['studentList'] as $key => &$v ){
            $ik++;
            $v['k']=  isset($v['ct'])?$v['ct']['A']: $key+1 ; //
            $uid = $v['user_id'];// $class_id =  $v['class_id'];
            //$v['number']= self::$dsData['user'][ $uid]['number'];
            //$v['name']= self::$dsData['user'][ $uid]['name'];

            $v['class']=  self::$dsData['class'][ 'class'];
            $v['wc_cnt']= isset( $v['finish']['wc_cnt'])?  $v['finish']['wc_cnt']  : '-';
            $v['book_cnt']=   $v['tjrz']['book_cnt']<=0 ? '未加入' :  $v['tjrz']['book_cnt'] ;
            $v['bu']= ( self::$dsData['term_config']['s_end_time']>0 && $v['finish']['ctime']> self::$dsData['term_config']['s_end_time'] )?'补选':'-';
            foreach($v['tjrz'] as $k2=>$v2  )  $v[$k2]=$v2;
            if( $v['finish']['score'] ){
                foreach ( $v['finish']['score'] as $k2=>$v2  ) $v['tag_'.$k2.'_cnt_cj']=$v2;
            }
            if( self::$dsData['is_order_class_teacher'] &&  !isset($v['ct']) ) {
                $otherList[]=$v ;
                unset(  self::$dsData['studentList'][$key] );
            }

            if( isset($v['ct']) ) {
                foreach ($v['ct'] as $k3=>$v3 ) {
                        $header['_'.$k3]='原'.$k3.'列';
                        $v['_'.$k3]= $v3 ;
                }
            }

        }
        if($_GET['export']==2 ){
            $list=[];
            //$list =  self::$dsData['studentList'];
            foreach( self::$dsData['studentList'] as $vv ){
                $list[]=$vv;
                foreach( $vv['finish']['detail'] as $k3=>$v3){
                    if( $k3<1) continue;
                    $v=[];
                    foreach( $header as $k2=>$v2){
                        if( isset( $v3['book_user'][$k2] ) ) $v[ $k2 ]= $v3['book_user'][$k2] ;
                    }
                    foreach( $v3['score'] as   $k2=>$v2) $v['tag_'.$k2.'_cnt_cj']=$v2;
                    $list[]=$v;
                }
            }
            self::$dsData['studentList']= $list;
            unset( $list );
        }
        //$this->drExit(   self::$dsData['user']  );
        $opt = [ 'start'=>'班级  '.  self::$dsData['class'][ 'class'] ];
        if(  self::$dsData['is_order_class_teacher'] ) {
            $opt['title']='白名单' ;$opt2= $opt; $opt2['title']='外加入';
            $this->createExcel()->start($opt)->writeHead($header)->writeLine(self::$dsData['studentList'])
                ->sheet(1,$opt2 )->writeHead($header2)->writeLine( $otherList )
                ->save("Haoce_class_" . date("Y-m-d_His"));
        }else {
            $this->createExcel()->start($opt)->writeHead($header)->writeLine(self::$dsData['studentList'])->save("Haoce_class_" . date("Y-m-d_His"));
        }

        //$this->createExcel()->saveByHeadLine($header,   self::$dsData['studentList']  ,'',[ ] );
    }

    private function getBookClassInfo( &$list,$book_id ){
        //$uid= [];
        //drFun::searchFromArray($list,['user_id'] ,$cls_id );
        $tall = $this->createSql()->select( 'book_user',['book_id'=>$book_id],[],['user_id','book_id','class_id'] )->getAllByKey('user_id');
        if( !$tall )  return $this;
        $cls_id = [];
        drFun::searchFromArray($tall,['class_id'] ,$cls_id );
        unset( $cls_id[0] );  if( ! $cls_id ) return $this ;
        // $this->getLogin()->createClassCls()->getClassById( $cls_id )
        $cl_class = new cls();
        $class_all = $cl_class->getClassById($cls_id );
        foreach($list as &$v){
            $v['class']  =$class_all[  $tall[ $v['user_id']]['class_id']  ]['class'];
            //$this->drExit($class );
        }
        //$this->drExit( $class_all );
        return $this;
    }

    function book_detail( $opt=[]){
        $header = ['number'=>'学号','name'=>'姓名' ,'class'=>'班级','score'=>'成绩','ctime'=>'新建时间','comment_cnt'=>'回复','view_cnt'=>'查看','good_cnt'=>'赞','sim'=>'相似(%)','topic'=>'主题','topic_info'=>'内容'];

        $this->getBookClassInfo( self::$dsData['topic_list']['list'],  self::$dsData['book_id']);
        foreach( self::$dsData['topic_list']['list'] as &$v ){
            $uid = $v['user_id'];
            $v['number']= self::$dsData['user'][$uid]['number'];
            $v['name']= self::$dsData['user'][$uid]['name'];
           // $v['class']= self::$dsData['user'][$uid]['class'];
            $v['score']=  $v['score']<=0? 0 : $v['score']/100 ;
            $v['sim']=  $v['score']<=0? 0 : $v['sim']/100 ;
            $v['ctime']= date("Y-m-d H:i",  $v['ctime']);
            if( $v['sim']<2)  $v['sim'] =0 ;
        }
        if($opt['ex_type']=='doc')  $this->book_detail_doc();
        else $this->createExcel()->saveByHeadLine($header,  self::$dsData['topic_list']['list']  ,'',['start'=>'【'.self::$dsData['tags'][ self::$dsData['tag_id'] ]['n'].'】  '.  self::$dsData['book']['book'] ] );

    }

    function book_detail_doc(){
        $header = ['number'=>'学号','name'=>'姓名' ,'class'=>'班级','score'=>'成绩','ctime'=>'新建时间','comment_cnt'=>'回复','view_cnt'=>'查看','good_cnt'=>'赞','sim'=>'相似(%)' ];
        $str = '<html xmlns:o="urn:schemas-microsoft-com:office:office"  xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
        $str .=  '<head></head><body><div style="width: 800px; margin: 0 auto;">';
        $str .= "<div><h1 style='text-align: center'>【".self::$dsData['tags'][ self::$dsData['tag_id'] ]['n']."】". self::$dsData['book']['book']."</h1></div>";

        foreach(self::$dsData['topic_list']['list']  as $v ){
            $str.="<h2  style='text-align: center'>".$v['topic']."</h2>";
            $str.='<p style="text-align: center"><table  border="1"  style="border-collapse:collapse;width: 700px" ><tr>';
            foreach($header as $k2=>$v2  ) $str.='<td>'.$v2.'</td>';
            $str.='</tr><tr>';
            foreach($header as $k2=>$v2  ) $str.='<td>'.$v[$k2].'</td>';
            $str.='</tr></table></p>';
            $str .=  "<div style='text-align: center;width: 800px; margin: 0 auto; '><p style='text-indent:20px;text-align: left;'>".strtr($v['topic_info'],array("\n"=>"</p><p style='text-indent:20px;text-align: left'>"))."</p></div>";
        }
        $str .= "</div></body></html>";
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment;filename="haoce-'.date("Y-m-d_His").'.doc"');
        header('Cache-Control: max-age=0');
        echo $str;
        $this->drExit();
    }

    function novelView(){
        $header=['read_time'=>'时间(最后阅读)','name'=>'姓名','number'=>'学号','class'=>'班级','novel'=>'书名','cp_cnt'=>'涉及章节数','word'=>'已阅字数','progress'=>'进度（%）','dtime'=>'用时(秒)'];
        if($_GET['tb']=='list'){
            $header['cnt_1']='讨论';
            $header['cnt_2']='报告';
            $header['cnt_4']='摘抄随想';
            $header['cnt_7']='笔记';
            if($_GET['is_ren']){
                $header['ren_score_1']='讨论(人工分)';
                $header['ren_score_2']='报告(人工分)';
                $header['ren_score_4']='摘抄随想(人工分)';
            }
        }
        foreach(   self::$dsData['list']['list'] as &$v ){
            $v['read_time']= date("Y-m-d H:i", $v['last_time']?$v['last_time']:$v['ctime'] );
            $v['progress']= $v['progress']>10000? 100: $v['progress']/100   ;
            $v['name']=     self::$dsData['user'][$v['user_id']]['name'] ;
            $v['number']=     self::$dsData['user'][$v['user_id']]['number'] ;
            $v['class']=     self::$dsData['user'][$v['user_id']]['number_class']['class'] ;
            if($_GET['is_ren']){
                $v['ren_score_1']= isset( $v['ren_score'][1])?$v['ren_score'][1]:'-';
                $v['ren_score_2']= isset( $v['ren_score'][2])?$v['ren_score'][2]:'-';
                $v['ren_score_4']= isset( $v['ren_score'][4])?$v['ren_score'][4]:'-';
            }
        }
        $this->createExcel()->saveByHeadLine( $header,     self::$dsData['list']['list'] );
    }

    function mcExport(){
        $header = ['merchant_id'=>'商户号','money'=>'火币','order_no' =>'提现号','ctime'=>'创建时间','card_name'=>'收款人' ];
        $header['card_id']='银行卡号';
        $header['card_bank']='银行';
        $header['card_address']='开户行';
        $header['type_name']='状态';
        foreach(   self::$dsData['list']['list'] as &$v ){
            $v['type_name'] =  self::$dsData['server']['type'][$v['type']];
            $v['money'] = $v['money']/100 ;
            $v['ctime'] = date("Y-m-d H:i:s", $v['ctime']) ;
            $v['card_id']=' '. $v['card_id'];
        }
        $this->createExcel()->saveByHeadLine( $header,     self::$dsData['list']['list'] );
    }

    function mcTrade(){
        $header = ['merchant_id'=>'商户号','order_no'=>'商户平台订单号','ctime'=>'下单时间','price'=>'下单金额','rate'=>'水费','yue'=>'余额','pay_time'=>'支付时间','type'=>'支付状态','pay_type'=>'付款通道','notify_time'=>'回调上分时间' ];

        foreach(self::$dsData['mlist']['list'] as &$v ){
            $v['ctime']= date("Y-m-h H:i:s",$v['ctime']);
            $v['pay_time']= $v['pay_time']? date("Y-m-h H:i:s",$v['pay_time']):'';
            $v['notify_time']= $v['pay_time']? date("Y-m-h H:i:s",$v['notify_time']):'';
            $v['price']= $v['price']/100;
            $v['rate']= $v['rate']/100;
            $v['yue']= $v['yue']/100;
            $v['type']= self::$dsData['type'][$v['type']];
            $v['pay_type']= self::$dsData['pay_type2'][$v['pay_type']];
        }

        $this->createExcel()->saveByHeadLine( $header,     self::$dsData['mlist']['list'] );
    }

    function mxList(){

        $header = ['merchant_id'=>'商户号','merchant'=>'商户' ,'trade_cnt'=>'上分笔数','trade_realprice'=>'上分金额（元）' ];

        foreach ( self::$dsData['mc_list']['list'] as &$v){
            $v['trade_cnt']=  self::$dsData['tj_trade'][ $v['merchant_id']]['cnt'];
            $v['trade_realprice']=  self::$dsData['tj_trade'][ $v['merchant_id']]['realprice']/100;
        }
        $this->createExcel()->saveByHeadLine( $header,     self::$dsData['mc_list']['list']  );
    }

    function cashList(){

        $header=['account'=>'编号','zhifu_name'=>'收款人', 'zhifu_realname'=>'真实姓名' , 'zhifu_account'=>'收款账号','tj_fee'=>'收款金额','tj_cnt'=>'收款笔数'];
        $header['trade_realprice']='上分金额';
        $header['trade_cnt']='上分笔数';
        $header['trade_price']='下单金额';
        $header['ma_name']='码商';
        //$array=[];
        foreach( self::$dsData['live'] as &$v  ){
            $var= $v;
            foreach( $v['tj'] as $v2 ){
                $var['tj_fee']=$v2['fee']/100;
                $var['tj_cnt']=$v2['cnt'];
                break;
            }
            foreach( $v['trade'] as $v2 ){
                $var['trade_realprice']=$v2['realprice']/100;
                $var['trade_cnt']= $v2['cnt'];
                $var['trade_price']= $v2['price']/100;
                break;
            }
            $var['ma_name']= $v['ma_user_id']<=0?'':self::$dsData['server']['muser'][$v['ma_user_id']]['name'] ;//server.muser[v.ma_user_id].name
            $v=$var;
        }
        $this->createExcel()->saveByHeadLine( $header,     self::$dsData['live']  );

    }

    function exOrder($pvar){

        $where=['merchant_id'=>intval($pvar['merchant_id'])];
        $where['>']['ctime']= $start = strtotime($pvar['start']);
        $where['<=']['ctime']=$end   = strtotime($pvar['end']);
        if( $end< $start) $this->throw_exception("截止时间必须大于开始时间", 19122601);

        //$this->drExit($pvar);
        $head=['merchant_id'=>'商户号','order_no'=>'来源订单号','trade_id'=>'内部订单号','ctime'=>'下单时间','realprice'=>'实付金额','price'=>'上分金额','type2'=>'状态', 'pay_time'=>'支付时间','notify_time'=>'上分时间'];
        $head['bss']='备注';


        $file= ['merchant_id','order_no','trade_id','price','realprice','type','ctime','pay_time','notify_time','account_id'];
        $list= $this->createSql()->select('mc_trade',$where,[0,200000],$file)->getAll();
        if( !$list ) $this->throw_exception( "这个条件没有下单记录", 19122602);
        //$this->drExit($list);
        $tradeType = $this->getLogin()->createQrPay()->getTypeTrade();
        foreach($list as &$v){
            $v['type2']= $tradeType[$v['type']] ;//$this->getLogin()->createQrPay()->getTypePay( $v['type']);
            $v['ctime']= date("Y-m-d H:i:s",$v['ctime']);
            $v['pay_time']= $v['pay_time']?date("Y-m-d H:i:s",$v['pay_time']):'';
            $v['notify_time']= $v['notify_time']?date("Y-m-d H:i:s",$v['notify_time']):'';
            $v['realprice']= $v['realprice']/100;
            $v['price']= $v['price']/100;
            $v['trade_id']= ' '.$v['trade_id'] ;
            $v['bss']=   in_array( $v['account_id'],[56,57] )?'场外':'';
        }
        $this->createExcel()->saveByHeadLine( $head, $list );
        return $this;
    }

    function exOrderByWhere( $where ){

        $head=['merchant_id'=>'商户号','order_no'=>'来源订单号','trade_id'=>'内部订单号','ctime'=>'下单时间','realprice'=>'实付金额','price'=>'上分金额','type2'=>'状态', 'pay_time'=>'支付时间','notify_time'=>'上分时间'];
        $head['bss']='备注';
        $head['lo']='省份';
        $head['lc']='城市';
        $head['ip']='IP';


        $file= ['ip','merchant_id','order_no','trade_id','price','realprice','type','ctime','pay_time','notify_time','account_id','lo','lc'];
        $list= $this->createSql()->select('mc_trade',$where,[0,200000],$file)->getAll();
        if( !$list ) $this->throw_exception( "这个条件没有下单记录", 19122602);
        //$this->drExit($list);
        $tradeType = $this->getLogin()->createQrPay()->getTypeTrade();
        foreach($list as &$v){
            $v['type2']= $tradeType[$v['type']] ;//$this->getLogin()->createQrPay()->getTypePay( $v['type']);
            $v['ctime']= date("Y-m-d H:i:s",$v['ctime']);
            $v['pay_time']= $v['pay_time']?date("Y-m-d H:i:s",$v['pay_time']):'';
            $v['notify_time']= $v['notify_time']?date("Y-m-d H:i:s",$v['notify_time']):'';
            $v['realprice']= $v['realprice']/100;
            $v['price']= $v['price']/100;
            $v['trade_id']= ' '.$v['trade_id'] ;
            $v['bss']=   in_array( $v['account_id'],[56,57] )?'场外':'';
        }
        $this->createExcel()->saveByHeadLine( $head, $list );
        return $this;
    }

}