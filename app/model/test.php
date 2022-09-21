<?php
/**
 * 测试
 * Date: 2017/5/10
 * Time: 23:51
 */

namespace model;


use model\user\one;

class test extends model
{
    public function ex(){

        $cmd = $this->createSql()->insert( 'user',[ 'trade_no'=>'t1','ctime'=>time() ] );
        $sql = $cmd->getSQL();
        $cmd->query();
        return $sql;

        $sql= "select * from trade ";
        return $this->getAll( $sql );
    }

    public function getUser( $user_id ){
        $one = new one( $user_id);
        return $one->clear()->getALl();
    }

    public function classTrim(){
        $tall = $this->createSql()->select('class',"1",[],['class_id','class'])->getAll();
        $re = [];
        foreach( $tall as $v ){
            if( $v['class']!=trim( $v['class']) ){
                $re[]= $v ;
                $this->createSql( )->update('class',['class'=> trim( $v['class'])],['class_id'=>$v['class_id']])->query();
            }
        }
        return $re ;
    }

    /**
     * 批量打分
     * @param $max_id
     * @param $limit
     * @return array
     */
    public function plHiVoice($max_id, $limit){
        return $this->createSql("select topic_id,tag_id,score from book_topic where  topic_id>".$max_id." order by topic_id  limit ".$limit)->getAll();//->select( 'book_topic',['topic_id'])
    }

    public function bm( &$opt){
        $tb= 'test_bm';
        $row = $this->createSql()->select( $tb, ['user_id'=>$opt['user_id']])->getRow();
        if( $row ) $this->throw_exception( "你已报名过！");
        drFun::checkTel($opt['tel'] );
        $file=['tel'=>['n'=>'电话'],'name'=>['n'=>'姓名'] ,'number'=>['n'=>'学号'] ,'class'=>['n'=>'班级'] ,'college'=>['n'=>'学院'] ,'user_id','ctime'];
        $opt['ctime'] = time();
        $opt['id']= $this->insert( $tb , $opt,$file);
        return $this;
    }
    public function getBm( $where ){
        $tb= 'test_bm';
        return $this->createSql()->select($tb,$where,[0,10])->getAll();
    }


    public function payLogCF(){
        $sql ="SELECT trade_id, COUNT( * ) AS cnt FROM pay_log WHERE   trade_id >0  GROUP BY trade_id  having  cnt> 1  LIMIT 0 , 100";
        $trade_col = $this->createSql($sql)->getCol2() ;
        if( !$trade_col ) return $this;
        $pay_log= $this->createSql()->select('pay_log', ['trade_id'=> array_keys( $trade_col ) ] ,[0,1000],['id' ] )->getCol();

        $trade_arr = $this->createSql()->select('mc_trade', ['pay_log_id'=> $pay_log],[],['pay_log_id','trade_id'])->getCol2();

        $del_pog_id=[];
        foreach ( $pay_log as $id  ){
            if( !isset( $trade_arr[$id] ) ) $del_pog_id[]= $id ;
        }

        if(! $del_pog_id ) return $this;


        $del_all = $this->createSql()->select('pay_log' , ['id'=> $del_pog_id] )->getAll();


        foreach ( $del_all as $v ) {
            $this->getLogin()->createLogRecycle()->append($v['id'] ,221 , $v );
        }
        $this->createSql()->delete('pay_log',  ['id'=> $del_pog_id] ,count($del_pog_id ))->query();


        $this->assign('del_all', $del_all );
        $this->assign('pay_log', $pay_log )->assign('trade_all',$trade_arr)
            ->assign('trade_col',$trade_col)->assign('del', $del_pog_id );

        return $this ;
    }

    public function checkAliUidChongfu( &$accountList ){
        //$this->drExit( 'ddd' );
        $ali_uid=[];
        drFun::searchFromArray($accountList ,['ali_uid'],$ali_uid );
        unset( $ali_uid['']);
        if( !$ali_uid ) return $this;
        //$where=
        $tall = $this->createSql()->group('pay_account' , ['ali_uid'],['ali_uid'=> array_keys($ali_uid )],['ali_uid', 'count(*) as cnt'  ] )->getCol2();//->getAll(  );
        //$this->drExit( $tall );
        foreach( $accountList as &$v ){
            $id= $v['ali_uid'];
            if($id=='') continue;
            $v['ali_uid_cnt']= $tall[ $id]?$tall[ $id]:0;
        }
        return $this;
    }

    public function cookieNamePl($trade_id_from, $limit=10000){
        for($i=0;$i<$limit;$i++){
            $tr_id= $trade_id_from-$i;
            $this->getLogin()->createQrPay()->tradePaylogCookieNameByTradeID( $tr_id );
        }
    }

    public function countFailCntByCUserID( $c_user_id ){

        $wh=['user_id'=> $c_user_id ];
        $wh['online']=[1,4,11] ;
        $wh['>=']= ['clienttime'=> ( time()- 3600  ) ];

        return $this->countFailCntByWhere( $wh );
    }
    public function countFailCntByWhere( $wh ){

        $account =  $this->getLogin()->createQrPay()->getAccountIDByWhere( $wh , ['all'=>1001 ] );
        if( !$account ) return [];
        $this->getLogin()->createQrPay()->tjTradeAccountHealth( $account ,['notime'=>1,'limit'=>[0,10000] ] );
        foreach( $account as $v ){
            //echo $v['account_id']." \t ".$v['health']['fail'] ."<br>\n";
            $this->getLogin()->createQrPay()->modifyAccount( $v['account_id'], ['fail_cnt'=> $v['health']['fail'], 'fail_cnt_day'=> $v['health']['fail_cnt_day']   ,'fail_cnt_all'=>  $v['health']['fail_all']  ] );
        }
        //$this->drExit( $account );
        return $account;
    }

    public function checkNext( $trade){
        //$trade= $this->
        $str='trade_no='.$trade['order_no'].'&apikey=3863bf26a0a5fa197db8573d195b5a97';
        $sign= strtoupper( md5($str));
        //notify_url
        $var = parse_url( $trade['notify_url'] );
        $url= $var['scheme'].'://'. $var['host'].'/index/pay/order_query.html';
        $str2= drFun::http_build_query(['sign'=>$sign,'trade_no'=>$trade['order_no'] ] );
        //$this->log("====\nPOST:\n curl -k -d " .'"'. $str2.'" ' ."\t". $url );
        //$this->drExit( $sign."  ". $str ."<br>curl -k -d" .'"'. $str2.'" ' ."\t". $url );
        $re= drFun::curlPost(  $url ,$str2  );
        $arr= json_decode($re, true );
        $stat=[0=>'未支付',  1=>'已支付已通知', 2=>'已经支付未通知' ];
        if( $arr['data']['status']!=1) {
            $str= $arr['code']>0?($arr['msg'].'错误代码:'.$arr['code'] ):(  isset($stat[$arr['data']['status']])? $stat[$arr['data']['status']] :'未知错误' );
            $this->throw_exception( $str );
        }
        return '已支付已通知';

    }


    public function countFailCntByCUserAll(){
        $tall = $this->getLogin()->getVersionBYConsole('all' );
        //$this->drExit( $tall );
        foreach( $tall as $c_uid=>$version ){
            $acc= $this->countFailCntByCUserID( $c_uid );
            echo $c_uid."=". count( $acc)."\n";
        }
        return $this;

    }

    function toDay(){
        //1186, 1187, 1185,1210,1557,1630
        //,1185 ,1187,1186,1210,1557
        //606,784,792,83,989,1062 ,100,116  ,356,
        //,1079,1099,1102,1101,1108,1089,1197
        //2322,2323,2333,2438 ,2695,2808 ,2910//南哥2020
        $u_arr=[4,2337,2650,3125,3310 ,3349,3849,4335,4368,4408,4467,4468,4518,4628,2862,4647,4649,4761,4902,5063,5073,5082,5093,5107,5122,5124];
        foreach($u_arr as $uid ) {
            $m_arr = $this->getLogin()->getMidFromConsole($uid);

            $mid2 = $this->getLogin()->createTableMerchant()->getColByWhere(['c_user_id'=> $uid], ['merchant_id']);
            $m_arr= array_merge($m_arr,$mid2 );

            foreach ($m_arr as $mid) {
                if( in_array( $mid,[8395]))  continue;
                $trade_row = ['merchant_id' => $mid, 'ctime' => time()-30];
                $this->getLogin()->createQrPay()->toDayByTrade($trade_row);

                $yue= $this->getLogin()->createQrPay()->getMerchantYue( $mid );
                $yue['yue']['now']=time();

                //$this->getLogin()->
                $this->getLogin()->redisSet( 'mYue'. $mid , json_encode($yue['yue']));
            }
            //$this->drExit($m_arr);
        }
        return $this;
    }

    function limitConsole(){
        $http_host = strtolower( $_SERVER['HTTP_HOST'] );
        $http_host= strtr($http_host,[':443'=>''] );


        if( 'hc.atbaidu.com'==$http_host) return $this ;
        if( 'gz.atbaidu.com'==$http_host) return $this ;
        if( 'cw.atbaidu.com'==$http_host) return $this ;

        $no_array=[  'atbaidu.com','ancall.cn' ];
        foreach($no_array as $h){
            if(  strpos( $http_host,$h   ) ) $this->drExit($http_host. '  is building');
        }

        return $this;

    }

    function cAccount( $acc_id ){

        //
        $where= ['account_id'=> $acc_id] ;
        $where['>']['trade_id']=0;
        $page_log = $this->getLogin()->createTablePayLog()->getAll( $where ,[],[0,5000]);
        $ptext=[];
        foreach( $page_log as $v ){
            $pp= json_decode( $v['opt_value'],true);
            $txt= $pp['text'];
            if( $txt=='') continue;
            $ptext[$txt][]= ['id'=>$v['id'],'fee'=>$v['fee'],'trade_id'=> $v['trade_id'] ];

        }
        $total=0;
        foreach($ptext as $k=>$v){
            if( count($v)<=1) {
                unset($ptext[$k]);
                continue;
            }
            $total+= $v[1]['fee'];
        }

        return ['total'=>$total,'detail'=>$ptext ];
        //echo 'total='. ($total/100)."\n<br>";
        //$this->drExit($ptext);
    }

    function flushCha90(){
        $where=['type'=>$this->getLogin()->createQrPay()->getTypeTradeUsing()]; //,'version'=>90
        $acc=$this->getLogin()->createTableTrade()->getColByWhere( $where,['account_id','version'] );
        if( !$acc) return ['cnt'=>0];
        $acc_arr = $this->getLogin()->createQrPay()->getAccountIDByWhere( ['account_id'=>array_keys($acc )] ,['all'=>1 ]);
        $etime= time();
        $stime= time()-30*60;
        $re=['cnt'=>0];

        foreach( $acc_arr as $account ){
            if( $account['type']!=90 ) continue ;
            $re['cnt']++ ;
            drFun::cha90( $account['ali_uid'] ,$stime,$etime);
        }
        return $re;
    }

    function test60(){
        $sql ="select * from pay_log where pay_type=60 and trade_id='' order by pt_id desc ";
    }

    function delPayTem( $type=60 ){
        $where=[];
        $where['<']['ctime']= time()-3*24*3600;
        $where['type']=  $type;
        //$sql="delete * from pay_log_tem where ". $this->createSql()->arr2where($where);
        $this->getLogin()->createTablePayLogTem()->delByWhere($where,100000 );
        //$this->createSql( $sql )->query();
        return $this;
    }



    function delPaySms(){
        $where=[];
        $where['<']['ctime']= time()-3*24*3600;
        $this->getLogin()->createTablePaySms()->delByWhere( $where,100000 );
        return $this;
    }

    function payNoMatch( $opt=[]){

        $where=['trade_id'=>0,'opt_type'=>10];
        $where['>']=['fee'=>2000,'ctime'=>(time()-300) ,'id'=>'4457419'];


        $pay= $this->getLogin()->createTablePayLog()->getAll( $where,['id'=>'desc'],[0,100], ['ali_trade_no','pay_type','id','fee','ctime','trade_id','opt_type' ] );


        $this->assign('paylog', $pay);
        foreach( $pay as $v){
            try {

                $this->log("payNoMatch>>" . $v['id']." ". $v['ali_trade_no']  );
                $this->getLogin()->createQrPay()->payMatchByLogID($v['id']);

            }catch (drException $ex ){
                //echo "";
            }
        }
    }

    function telegramSendMessageByCUserID( $c_user_id, $message){

    }

    function telegramSendMessage($chat_id, $message){
        if( !$chat_id) $this->throw_exception( "chat id 没找到",20080703);
        $url='https://api.telegram.org/bot1391982971:AAGkrMeuhJoNRRA9c88FEq3rMakZjQy_KPw/sendMessage';
        $data=['chat_id'=>$chat_id, 'text'=>$message];
        $str = drFun::http_build_query($data);
        drFun::cPost( $url, $str, 30);

        return $str;
    }
    function potatoSendMessage($chat_id, $message){
        if( !$chat_id) $this->throw_exception( "chat id 没找到",20080703);
        //
        $url='https://api.rct2008.com:8443/10279058:5WOlsm8HU7Rz9mEQYIRQFvn6/sendTextMessage';
        $data=['chat_id'=>$chat_id, 'text'=>$message,'chat_type'=>2 ];
        // "chat_type":2,"chat_id":12381385,"text":"hello tg"}
        $str = json_encode( $data) ;//drFun::http_build_query($data);
        drFun::cPost( $url, $str, 30,['Content-Type: application/json;charset=utf-8'] );
        return $str;
    }

    function sendMessage( $chat ){
        //$chatS= $chat['chat_id'];
        //$msg = $chat['msg'];
        if( is_array( $chat['chat_id']['potato'])){
            foreach ($chat['chat_id']['potato'] as $id){
                $this->potatoSendMessage( $id, $chat['msg'] );
            }
        }
        if( is_array( $chat['chat_id']['telegram'])){
            foreach ($chat['chat_id']['telegram'] as $id){
                $this->telegramSendMessage( $id, $chat['msg'] );
            }
        }
    }

    function merchantTjDay( $where2,$p ,&$sv ){
        $where2['type']= $this->getLogin()->createQrPay()->getTypeTradeSuccess() ;

        $key='ctime';
        //$tradeAll = $this->getLogin()->createTableTrade()->getAll($where2, [],[0,50000], ['type','pay_type','user_id','price','realprice','ctime'] );
        $tradeAll = $this->getLogin()->createTableTrade()->getAll($where2, [],[0,50000], ['price','user_id','realprice',$key ] );


        $tj=[]; $uid=[];
        foreach($tradeAll as $v){
            $d=date('Y-m-d',$v[$key]);
            $tj[ $d]['cnt']++;
            $tj[ $d]['price']+= $v['price'];
            $tj[ $d]['realprice']+= $v['realprice'];
            $k2= $v['user_id'] ;//$v['pay_type']
            $tj[ $d]['detail'][ $k2]['realprice'] = $v['realprice'];
            $tj[ $d]['detail'][ $k2]['price'] = $v['price'];
            $tj[ $d]['detail'][ $k2]['cnt']++;
            $uid[ $k2]=1;
        }

        $sv['tj']= $tj;
        $sv['tj']= $uid ? $this->getLogin()->createUser()->getUserFromUid( ['user_id'=> array_keys($uid) ]) :[ ];
        return $this;
    }


    function merchantTj( $where2,$p ,&$sv ){

        if( is_numeric(trim($_GET['q'])) && intval($_GET['q'])>0  ) {
            $where=[];

            $where['or'] = [['pid' => intval($_GET['q'])], ['merchant_id' => intval($_GET['q'])]];
            $sv['merchant'] = $this->getLogin()->createTableMerchant()->getAllByKey('merchant_id',$where,[],[] );
            if(! $sv['merchant'] ) $this->throw_exception('未指定商户代理');
            //$wh=[ 'merchant_id'=> array_keys( $sv['merchant'] ) ];
            $where2['merchant_id']= array_keys( $sv['merchant'] );
        }


        switch ($p[0]){


            case 'lastMonth':
                $month= date("m")-1;
                $year = date("Y");
                if( $month<=0 ){
                    $month=12;
                    $year--;
                }
                $where2['>=']=['ctime'=>strtotime(  ($year."-".$month."-01"))];
                $where2['<=']=['ctime'=>strtotime( date("Y-m-01"))];
                break;

            case 'yesterday':

                $where2['>=']=['ctime'=>strtotime( date("Y-m-d"))-3600*24];
                $where2['<=']=['ctime'=>strtotime( date("Y-m-d"))];
                break;

            case 'search':
                if( $_GET['ctime_s'] ) $where2['>=']=['ctime'=>strtotime( $_GET['ctime_s']) ];
                if( $_GET['ctime_e'] ) $where2['<=']=['ctime'=>strtotime( $_GET['ctime_e']) ];
                if($_GET['payType']>0){
                    $where['pay_type']=intval( $_GET['payType'] );
                }


                break;
            case 'month':
                $where2['>=']=['ctime'=>strtotime( date("Y-m-01"))];
                break;
            case 'today';
            default:
                $where2['>=']=['ctime'=>strtotime( date("Y-m-d"))];
                //$this-
                $p[0]= 'today';
                //$_GET['ctime_s']= date();
                break;

        }
        if( $where2['>=']['ctime']) $_GET['ctime_s']= date("Y-m-d H:i",  $where2['>=']['ctime']);
        if( $where2['<=']['ctime']) $_GET['ctime_e']= date("Y-m-d H:i",  $where2['<=']['ctime']);

        $where2['type']= $this->getLogin()->createQrPay()->getTypeTradeSuccess() ;
        if($_GET['pay_type']>0) $where2['pay_type']= intval( $_GET['pay_type'] );

        $sv['tj']= $this->getLogin()->createQrPay()->tjTradeGroup('merchant_id', $where2 );

        $sv['pay_type']= $this->getLogin()->createQrPay()->getPayTypeFromUser();
        $tab=['today'=>'今日','yesterday'=>'昨日','month'=>'本月','lastMonth'=>'上月'];
        if($p[0]=='search') $tab['search']='查找搜索';
        //$this->assign('tab', $tab )->assign('p',$p )->assign('get',$_GET);
        $sv['tab']=$tab;
        $sv['p']=$p;
        $sv['get']=$_GET;
        $sv['trade_cnt']= count( $sv['tj'] );

        $sv['yueUrl']= '/vip/mc/yue';
        $mid= array_keys($sv['tj']);
        if(!isset($sv['merchant']) && $mid ){

            $sv['merchant'] = $this->getLogin()->createTableMerchant()->getAllByKey('merchant_id',['merchant_id'=>$mid],[],[] );
            $this->getLogin()->createQrPay()->clearAppSecret( $sv['merchant'] );
        }

        $this->assign('get',$_GET)->assign('p', $p );
        //return $sv ;
        return $this;
    }


    function okexJD2Telegram(){

        /*
        $key= 'okexJD';
        $okexJD= $this->getLogin()->redisGet($key);
        //$okexJD='test';

        try {
            //$this->getLogin()->redisSet( 'okexJD', );
            $var= $this->okexJiedai();
            $rate= intval(  365*$var['rate']*10000 );
            $new= $var['investBound'].'ex'.$rate;
            $str='';
            if( $new!=$okexJD || $rate>900  ){
                $this->getLogin()->redisSet( $key , $new);
                if($rate>600){
                    $str.='【年化 '.($rate/100).'%】';
                    $str.="\n总量：". $var['totalAmount'];
                    $str.="\n最少：".$var['investBound'];
                    $str.="\n抵押物：".$var['risk']['pledgeCurrency'].'';
                    $str.="\n剩余：". ($var['totalAmount']- $var['subscribedAmount']);
                    $str.="\n期限：". $var['period'] .'天';
                    $str.="\nkey：". $new;
                }
            }
        }catch (drException $ex ){
            $str= "[抵押借贷]".$ex->getMessage();
        }
        if($str)  $this->telegramSendMessage(-492924716 , $str);

        */
        $okex= new okex();
        $okex->okexJD2Telegram();

        return $this;
    }

    /*
    function okexJiedai(){

        $re= $this->okexJiedaiItem();
        if( !$re || !$re['data']['orderList']) $this->throw_exception("未取到okex内容可能已经过期了");

        $fun= function ($a , $b){
          return $a['rate']<$b['rate'];
        };
        uasort($re['data']['orderList'], $fun );
        return $re['data']['orderList'][0];

    }
    function okexJiedaiItem(){
        $curl = curl_init();

        $time= time().rand(100,999);

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.okex.me/v2/asset/outer/earn/borrow-orders?t=".$time."&currencyId=7&day=0",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Accept:  application/json",
                "ftID:  521002071457525.111704f0d3453e86fdf6a48de3cbb8620ee83.1010L8o0.119F78228FB7F546",
                "devId:  64791e37-1718-493f-ad81-6cc45d16d1be",
                "App-Type:  web",
                "Authorization:  eyJhbGciOiJIUzUxMiJ9.eyJqdGkiOiJleDExMDE2MDAwODk1MzA1MTQ2MEEzQ0NCNEUwNkQzNTc3ZEFTbyIsInVpZCI6Iko0c1VQR2xuNWp1QXBxWHcvVW1BWFE9PSIsInN0YSI6MCwibWlkIjoiSjRzVVBHbG41anVBcHFYdy9VbUFYUT09IiwiaWF0IjoxNjAwMDg5NTMwLCJleHAiOjE2MDA2OTQzMzAsImJpZCI6MCwiZG9tIjoid3d3Lm9rZXgubWUiLCJpc3MiOiJva2NvaW4iLCJzdWIiOiIzNkM5QTQ1NTIwRDYwMkEyQzMyQTQxRjY4ODFEMzBDOCJ9.Xu7h_w2lX7Gj3hZt_heY7DhQoVyuZchp1zKzItWNH6BFyWiapAOdXL3GjGkko1eOsZkpZAZ0_7XJB5JRc7xRQg",
                "User-Agent:  Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1",
                "Referer:  https://www.okex.me/earn",
                "Accept-Language:  zh-CN,zh;q=0.9,en;q=0.8",
                "Cookie:  locale=zh_CN; viaServer=%7B%7D; Hm_lvt_01a61555119115f9226e2c15e411694e=1600087410; first_ref=https%3A%2F%2Fwww.okex.me%2Fjoin%2F1%2F2175142%3Fsrc%3Dfrom%3Aandroid-share; u_ip=MTIzLjExOS4yMzguMjQ1; u_pid=D6D6lm9rzXGuBWu; x-lid=5f3658f5950a1c948e570987099b830f0da763790cf639462cade9df955e92f7f12cde9b; PVTAG=274.408.lQgAkddh7YLbsEk6BuWBuGXzr9ml6D6D3ULMmF1a; finger_test_cookie=; ftID=521002071457525.111704f0d3453e86fdf6a48de3cbb8620ee83.1010L8o0.119F78228FB7F546; isLogin=1; Hm_lpvt_01a61555119115f9226e2c15e411694e=1600089532"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $re= json_decode($response,true);
        //echo $response;
        return $re;
    }
    */

}