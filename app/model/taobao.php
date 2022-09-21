<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/29
 * Time: 12:11
 */

namespace model;

class taobao extends model
{
    private  $tbapi;

    function getStatusType($key='n',$t='all'){

        /**
         *
        1.WAIT_BUYER_PAY：等待买家付款
        2.WAIT_SELLER_SEND_GOODS：等待卖家发货
        3.SELLER_CONSIGNED_PART：卖家部分发货
        4.WAIT_BUYER_CONFIRM_GOODS：等待买家确认收货
        5.TRADE_BUYER_SIGNED：买家已签收（货到付款专用）
        6.TRADE_FINISHED：交易成功
        TRADE_CLOSED：交易关闭
        TRADE_CLOSED_BY_TAOBAO：交易被淘宝关闭
        TRADE_NO_CREATE_PAY：没有创建外部交易（支付宝交易）
        10. WAIT_PRE_AUTH_CONFIRM：余额宝0元购合约中
        PAY_PENDING：外卡支付付款确认中
        ALL_WAIT_PAY：所有买家未付款的交易（包含：WAIT_BUYER_PAY、TRADE_NO_CREATE_PAY）
        ALL_CLOSED：所有关闭的交易（包含：TRADE_CLOSED、TRADE_CLOSED_BY_TAOBAO）
        PAID_FORBID_CONSIGN，该状态代表订单已付款但是处于禁止发货状态。
         */

        $type=[];
        $type[]=['n'=> 101,'k'=>'WAIT_BUYER_PAY','cn'=>'等待买家付款'];
        $type[]=['n'=> 102,'k'=>'WAIT_SELLER_SEND_GOODS','cn'=>'等待卖家发货'];
        $type[]=['n'=> 103,'k'=>'SELLER_CONSIGNED_PART','cn'=>'卖家部分发货'];
        $type[]=['n'=> 104,'k'=>'WAIT_BUYER_CONFIRM_GOODS','cn'=>'等待买家确认收货'];
        $type[]=['n'=> 105,'k'=>'TRADE_BUYER_SIGNED','cn'=>'买家已签收（货到付款专用）'];
        $type[]=['n'=> 106,'k'=>'TRADE_FINISHED','cn'=>'交易成功'];
        $type[]=['n'=> 107,'k'=>'TRADE_CLOSED','cn'=>'交易关闭'];
        $type[]=['n'=> 108,'k'=>'TRADE_CLOSED_BY_TAOBAO','cn'=>'交易被淘宝关闭'];
        $type[]=['n'=> 109,'k'=>'TRADE_NO_CREATE_PAY','cn'=>'没有创建外部交易'];
        $type[]=['n'=> 110,'k'=>'WAIT_PRE_AUTH_CONFIRM','cn'=>'余额宝0元购合约中'];
        $type[]=['n'=> 111,'k'=>'PAY_PENDING','cn'=>'外卡支付付款确认中'];
        $type[]=['n'=> 112,'k'=>'ALL_WAIT_PAY','cn'=>'所有买家未付款的交易'];
        $type[]=['n'=> 113,'k'=>'ALL_CLOSED','cn'=>'所有关闭的交易'];
        $type[]=['n'=> 114,'k'=>'PAID_FORBID_CONSIGN','cn'=>'该状态代表订单已付款但是处于禁止发货状态'];

        if( !in_array($key, ['n','k','cn'])) $this->throw_exception("key 错误",190829001);

        $arr=[];
        foreach ( $type as $v ){
            $arr[ $v[$key]]=$v;
        }

        if( $t=='all') return $arr;
        if( ! isset( $arr[$t] ) ) $this->throw_exception($t." 不存在",190829002);

        return $arr[$t];
    }

    function doMessageNoCheck( $message ){

        $msg= json_decode( $message['message'],true);
        $account= $this->getLogin()->createQrPay()->getAccountByAliUid('taobao'.$msg['UserId'] );//getAccountIDByWhere([''] );
        if( !$account ) $this->throw_exception("淘宝账号未初始化", 19090702 );
        $account_id= $account['account_id'];
        $this->getLogin()->createQrPay()->updateClientTime($account_id ,['account'=>$account ] );
        $tbapi = $this->getLogin()->createTaoboApi( $account['account_id'] ,['account'=> $account] );
        $this->setTaobaoApi($tbapi );

        $re=[];
        $content= json_decode( $msg['Content'],true);
        switch ( $msg['Topic']){
            //case 'taobao_trade_TradeAlipayCreate':
            case 'taobao_trade_TradeBuyerPay':

                //


                $qr= $this->getLogin()->createTableTaobaoQr()->getRowByWhere(['tid'=>$content['tid'] ]);
                //print_r($qr);
                if( $qr['type']==12 ) $this->throw_exception($content['tid'] ." \t已经成功" );



                //$trade=['tid'=>$content['tid'],'pay_time'=>$msg['PubTime']  ];
                //$this->doFenByTrade( $trade);

                $mq = new mq();
                $msg['qcnt']++;
                $mq->rabbit_publish('taobao_message_fail', $msg );
                //$this->drExit( $trade );
                $trade = $this->getTaobaoApi()->taobao_trade_fullinfo_get(   $content['tid'] );
                $this->tradeMain($trade, $account_id, $re );
                break;
        }
        $re['content'] =$content;
        //print_r( $re );
        //$this->drExit( $msg );

        return $re ;

    }

    function doMessage( $message   ){
        $msg= json_decode( $message['message'],true);
        $account= $this->getLogin()->createQrPay()->getAccountByAliUid('taobao'.$msg['UserId'] );//getAccountIDByWhere([''] );
        if( !$account ) $this->throw_exception("淘宝账号未初始化", 19090702 );
        $account_id= $account['account_id'];
        $this->getLogin()->createQrPay()->updateClientTime($account_id ,['account'=>$account ] );
        $tbapi = $this->getLogin()->createTaoboApi( $account['account_id'] ,['account'=> $account] );
        $this->setTaobaoApi($tbapi );

        $re=[];
        $content= json_decode( $msg['Content'],true);
        switch ( $msg['Topic']){
            case 'taobao_trade_TradeAlipayCreate':
            case 'taobao_trade_TradeBuyerPay':
                //$trade= $tbapi->taobao_trade_fullinfo_get( $content['tid'] );
                $trade = $this->getTaobaoApi()->taobao_trade_fullinfo_get(   $content['tid'] );

                $this->tradeMain($trade, $account_id, $re );
                break;
        }
        $re['content'] =$content;
        //print_r( $re );
        //$this->drExit( $msg );

        return $re ;
        //return $this;
    }

    /**
     * @param  $tbapi taobaoapi
     * @return $this
     */
    function setTaobaoApi( $tbapi){
        //return $this->getLogin()->createTaoboApi();
        $this->tbapi= $tbapi;
        return $this;
    }
    /**
     * @return taobaoapi
     * @throws drException
     */
    function getTaobaoApi(){
        if( !$this->tbapi ) $this->throw_exception("淘宝API 未初始化", 19090701 );
        return $this->tbapi;
    }

    function toDoMain( $account_id ){
        $account = $this->getLogin()->createQrPay()->getAccountByID( $account_id );
        $tbapi= $this->getLogin()->createTaoboApi( $account_id, ['account'=>$account ] );
        $this->setTaobaoApi( $tbapi );
        $list= $tbapi->taobao_trades_sold_increment_get(['status'=>'all','page_size'=>100 ]);

        $re=['success'=>[] ,'error'=>[] ];
        //$this->drExit($list);
        foreach($list['trades']['trade'] as $v){

            $this->tradeMain($v, $account_id, $re );

            if( $v['status']=='WAIT_SELLER_SEND_GOODS' ){
                try{
                    $tbapi->taobao_logistics_dummy_send($v['tid'] );
                }catch (drException $ex ){}

            }

        }

        return $re ;
    }
    function tradeMain( $v, $account_id, &$re, $opt=[] ){
        try {
            switch ($v['status']){
                case 'WAIT_BUYER_PAY':
                    $this->totoPayLogTem($v, $account_id);
                    $re['success']['WAIT_BUYER_PAY']++;
                    break;
                case 'WAIT_SELLER_SEND_GOODS':
                case 'WAIT_BUYER_CONFIRM_GOODS':
                case 'TRADE_FINISHED':
                    $this->doFen( $v, $account_id );
                    $re['success']['dofen']++;
                    break;
                default:

            }
            $re['success']['all']++;
            //$this->drExit( $v );
        }catch (\Exception $ex ){
            $re['error'][]= $ex->getMessage();
        }
    }

    function doFen( $trade , $account_id){
        if( !in_array( $trade['status'], ['WAIT_SELLER_SEND_GOODS','WAIT_BUYER_CONFIRM_GOODS','TRADE_FINISHED']) )
            $this->throw_exception("只接3总付款状态", 19090505 );
        $this->doFenByTrade( $trade   );
    }

    /**
     * 必须包含 tid pay_time
     * @param $trade
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function doFenByTrade( $trade , $opt=[]){
        $tid = $trade['tid'];
        $qr= $this->getLogin()->createTableTaobaoQr()->getRowByWhere(['tid'=>$tid]);

        if( !$qr) $this->throw_exception("不存在代付记录！",19090506);
        if( $qr['type']==12) return $this;
        drFun::decodeOptValue( $qr );

        $pay_time = strtotime($trade['pay_time']);

        $this->getLogin()->createTableTaobaoQr()->updateByKey( $qr['qr_id'],['type'=>12, 'pay_time'=> $pay_time] );

        $re=[];
        $re['pay_type']=80;
        $re['account_id']= $re['account_ali_uid']= $qr['account_id'];
        $re['buyer']=$qr['opt_value']['d']['data']['applyerNickName'] ;//d.data.applyerUserName  d.data.applyerNickName
        if(  !$re['buyer'] )  $re['buyer']= $qr['opt_value']['d']['data']['applyerUserName'] ;
        $re['ali_uid']= $qr['opt_value']['d']['data']['applyerId'];
        $re['ali_account']= $qr['opt_value']['d']['data']['hidden_logon_id'];
        $re['ali_trade_no']= $qr['alipay_no'];//支付宝订单ID
        $re['ali_trade_no']= "TB".$tid; //淘宝订单ID
        $re['fee']= $qr['fee'];
        $re['ctime']=  $pay_time ;// = strtotime($trade['pay_time']);
        $re['ltime']= $re['ctime'].'000';
            /**
         *
        $re['buyer']= $arr['plArr']['payerUserName'];//付款人姓名
        $re['ali_uid']= $arr['plArr']['payerUserId'];//付款人 uid
        $re['ali_trade_no']= $arr['plArr']['transferNo']; //支付宝订单号
        $re['ali_account']= $arr['plArr']['payerLoginId'];//支付宝登录账号，联系方式
        $re['fee']= drFun::yuan2fen(  $arr['plArr']['amount']  );//ceil( 100*  $arr['plArr']['amount']);
        $re['ctime']= floor( $arr['mct']/1000 );
        $re['ltime']= $arr['mct'];
         */

        $this->getLogin()->createPayLog()->V3Append( $re);
        $log_id= $this->createSql()->lastID();
        if( $qr['trade_id'] !=0 ){
            $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $qr['trade_id'] );
            if( ! in_array($trade_row['type'], $this->getLogin()->createQrPay()->getTypeTradeUsing() ) ) return $this;
            if( $trade_row['ctime']>$pay_time )  return $this;

            $log = $this->getLogin()->createPayLog()->getById( $log_id);
            if( $log['fee']== $trade_row['realprice'])
                $this->getLogin()->createQrPay()->matchSuccess( $trade_row, $log );
        }
        return $this;
    }


    function toPayLogTemByList( $list ,$account_id){

        $re=['success'=>0 ,'error'=>[] ];

        //$this->drExit( $list );
        foreach($list['trades']['trade'] as $v){
            try {
                $this->totoPayLogTem($v, $account_id);
                $re['success']++;
                //$this->drExit( $v );
            }catch (\Exception $ex ){
                $re['error'][]= $ex->getMessage();
            }
            //$this->drExit( $list );
        }
        //$this->drExit( $re );
        return $re ;
    }

    /**
     * type 80 等待付款 二维码没提交进来
     * type 81 等待付款 二维码提交进来了
     * @param $trade
     * @param $account_id
     * @return $this
     * @throws drException
     */
    function totoPayLogTem($trade , $account_id){

        if( $trade['status']!='WAIT_BUYER_PAY' )  $this->throw_exception("只接受等待付款状态", 190829003 );
        $tid= $trade['tid'];
        if( $tid<=0 )  $this->throw_exception("参数错误", 190829004 );

        $cnt = $this->getLogin()->createTablePayLogTem()->getCount( ['ali_beizhu'=>$tid ]);
        if( $cnt>0 )  $this->throw_exception($tid ." 已经存在", 190829005 );






        $var=['type'=>80]; //80 等待付款 二维码没提交进来
        $var['account_id']=$account_id;

        $var['ali_beizhu']=$tid;
        $var['fee']= $var['realprice']=drFun::yuan2fen( $trade['payment'] );
        $var['ctime']= strtotime( $trade['created']);

        //$this->drExit( $trade );
        $alipayNo= $this->getAlipayNo( $tid, $account_id );
        $var['ali_trade_no']= $alipayNo['trade_amount']['alipay_no'];

        $var['data'] = drFun::json_encode(  $trade );

        //
        if( $_GET['dds']==1 ){
            print_r( $trade );
            $this->drExit( $var );
        }
        $this->getLogin()->createTablePayLogTem()->append(  $var );

        return $this;
    }

    function getAlipayNo($tid, $account_id ){
        $tall = $this->getLogin()->createTaoboApi( $account_id )->taobao_trade_amount_get( $tid );
        //$this->drExit( $tall );
        //$alino= $tall['trade_amount']['alipay_no'];
        return $tall;
    }

    function getQrType( $type='all'){
        $re=[0=>'待验证',9=>'非本店铺',10=>'通过',11=>'支付中',12=>'支付成功', -10=>'格式错误',-11=>'商户错乱',21=>'电话错误',22=>'电话为空',23=>'淘宝取消' ]; //,5=>'已过期'
        $re[24]='非本店铺.超时';
        $re[25]='重复';
        if( $type=='all' ) return $re ;
        if( !isset($re[$type]) ) $this->throw_exception("类型错误",190830005);
        return $re[$type];
    }

    function listQr( &$list){
        //
        foreach($list as $k=>$v ){
            $list[$k]['qr_query']= strtr( $v['qr_text'],['https://qr.alipay.com/_d?_b=peerpay&enableWK=YES&'=>'']);
        }
        return $this;
    }

    function qrAdd($qr_text, $ma){

        $var=['ma_user_id'=>$ma['user_id'],'user_id'=>$ma['c_user_id'],'ctime'=>time(),'type'=>0 ];
        $txt = drFun::cut( $qr_text,'biz_no=','_');
        if( !$txt) $this->throw_exception("请扫描代付二维码", 190904001);
        $var['biz_no']= $txt;
        $var['qr_text']= $qr_text;

        $cnt = $this->getLogin()->createTableTaobaoQr()->getCount(['biz_no'=>$var['biz_no'] ]);
        if($cnt>0) $this->throw_exception( "二维码已经被添加过", 190904002 );
        $this->getLogin()->createTableTaobaoQr()->append( $var);
        return $this;
    }

    function qrAnly($d,$id){
        $var=[];
        $var['alipay_no']= $d['data']['tradeModelList'][0]['bizNo'];//data.peerPayNo
        $var['fee']= drFun::yuan2fen( $d['data']['realAmount'] );
        $biz_no=  $d['data']['peerPayNo'];
        $row = $this->getLogin()->createTableTaobaoQr()->getRowByKey( $id );
        drFun::decodeOptValue( $row);
        if($biz_no!= $row['biz_no']) $this->throw_exception("账号不匹配");
        $var['opt_value']['d']= $d ;
        $var['opt_value'] = drFun::json_encode(  $var['opt_value'] );
        $var['type']= 9 ; //
        $var['buyer']= $d['data']['applyerNickName'];
        //applyerUserName
        if( ! $var['buyer'])    $var['buyer']= $d['data']['applyerUserName'];

        $cnt=  $this->getLogin()->createTableTaobaoQr()->getCount(['alipay_no'=>$var['alipay_no'] ] );
        if( $cnt>1)  $var['type']= 25 ; //
        $this->getLogin()->createTableTaobaoQr()->updateByKey($id, $var );
        if( $cnt>1) $this->throw_exception("重复！");
        return $this;
    }

    function matchFromQr( $qr_id ) {
        $qr = $this->getLogin()->createTableTaobaoQr()->getRowByKey($qr_id);
        if( $qr['type']!=9 ) $this->throw_exception( "匹配状态不对！",19090501 );

        $tem = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['type'=>[81,80 ],'ali_trade_no'=>$qr['alipay_no'] ] );
        if(!$tem) $this->throw_exception( "未找到", 19090502);

        drFun::decodeOptValue($qr );

        $tid= $tem['ali_beizhu'];
        $trade= json_decode(  $tem['data'],true );
        if(! isset( $trade['orders']['order'][0]['order_attr']) ){ //orders.order[0].order_attr
            $trade = $this->getLogin()->createTaoboApi( $tem['account_id'] )->taobao_trade_fullinfo_get($tid );
        }
        $order_attr = json_decode( $trade['orders']['order'][0]['order_attr'],true  );
        $mobile =  $order_attr['mobile'] ? $order_attr['mobile']:'';

        /*
        $type= $this->isMyMobile( $mobile )? 10 :21;
        if( $mobile=='') $type=22;
        */
        if( $mobile)  $type= $this->isMyMobile( $mobile )? 10 :21;
        else $type=10;


        $qr['opt_value']['trade']=$trade;

        $created = strtotime( $trade['created']);
        //$this->drExit( $order_attr );

        $var=['tid'=>$tem['ali_beizhu'],'type'=>$type,'created'=>$created,'account_id'=>$tem['account_id'] ,'mobile'=>$mobile ,'opt_value'=>drFun::json_encode(  $qr['opt_value']) ];
        $this->getLogin()->createTableTaobaoQr()->updateByKey($qr_id, $var );
        if( $type ==10 ) $this->getLogin()->createTablePayLogTem()->updateByKey( $tem['pt_id'],['type'=>81 ] );
        return $this;
    }

    function tjMa( $where ,$group_file='fee' ){
        $table= $this->getLogin()->createTableTaobaoQr()->getTable();
        $sql="select  `".$group_file."` , sum(fee) as price , count(*) as cnt from ". $table . " where ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;

        //$this->assign('tj_sql', $sql );

        return $this->createSql($sql)->getAllByKey( $group_file ) ;
    }

    function isMyMobile( $mobile){
        $arr=['18092706787','17765030431','18049470838','18149263470','18149301874','17391848498','18109284184','17392282809','15389418314','17391604866','17319956478','17392278257','17392541218','17392546773','17391924788','15319406149','17392474377','17391800477','15339271254','15309272064','17392545560','17392472543','17392545713','18149263470','18149301874','17391848498','18109284184','17392282809','15389418314','17391604866','17319956478','17392278257','17392541218','17392546773','17391924788','15319406149','17392474377','17391800477','15339271254','15309272064','17392545560','17392472543','17392545713','17319956478'];
        $arr[]='13385003311';
        $arr[]='17155875325';
        $arr[]='17319956478';
        $arr[]='13385003311';
        $arr[]='18060898881';
        $arr[]='13599846633';
        $arr[]='18192721472';
        $arr[]='15159364601';
        $arr[]='15309267747';
        $arr[]='13303301694';
        $arr[]='15159316351';
        $arr[]='17764709711';
        $arr[]='15159316571';

        $arr[]='13363080643';
        $arr[]='17831940863';
        $arr[]='18303015090';
        $arr[]='17832643386';
        $arr[]='13303301694';
        $arr[]='15652760753';
        $arr[]='17764709711';
        $arr[]='17129587162';
        $arr[]='17045996983';
        $arr[]='17129586715';
        $arr[]='17129586718';
        $arr[]='18703640423';


        $arr[]='17507321100';
        $arr[]='15211105052';
        $arr[]='17507323990';
        $arr[]='18573124905';
        $arr[]='18573124827';
        $arr[]='17829337079';
        $arr[]='17629131535';
        $arr[]='13309217871';
        $arr[]='17829337079';

        $arr[]='15829704533';

        return in_array( $mobile, $arr);
    }

    function searchQr( &$list ){
        $qr=[];
        foreach( $list as $v ){
            if( $v['version']!=80) continue;
            $qr[  $v['trade_id'] ]= $v['qr_id'];
        }

        if( !$qr ) return $this;

        $file= ['qr_id','trade_id','buyer','created'];
        if( count($list)<=1 ) $file=[];
        $tall = $this->getLogin()->createTableTaobaoQr()->getAllByKey('qr_id',['qr_id'=> array_values($qr )] ,[],[0,1000],$file );
        if( !$tall) return $this;

        foreach( $list as &$v ){
            if( $v['version']!=80) continue;
            //$qr[  $v['trade_id'] ]= $v['qr_id'];
            $qr_id = $v['qr_id'];
            if( isset( $tall[ $qr_id]) ){
                if( $tall[ $qr_id]['opt_value'] ) $tall[ $qr_id]['opt_value']=drFun::json_decode( $tall[ $qr_id]['opt_value']  );
                $v['tb_qr']= $tall[ $qr_id] ;
            }
        }

        return $this;
    }

}