<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/11
 * Time: 20:28
 */

namespace model;


use ctrl\app;
use model\lib\cache;

class logPay extends log
{
    private static $uniPayLog=[];

    function getListWithPageByAccountID( $account_id )
    {
        return $this->createSql()->selectWithPage( $this->getTable(), ['account_id'=>$account_id],20,['opt_id','ltime','ctime','fee','id'],['id'=>'desc'] );
    }

    function getListByAccountID($account_id, $max_id  ){

        return $this->createSql()->select(
            $this->getTable()
            ,['account_id'=>$account_id ,'>'=>['id'=>$max_id ] ]
            ,[0,20]
            ,['opt_id','ltime','ctime','fee','id','pay_type']
            ,['id'=>'desc'] )->getAll();
    }

    /**
     * 收款与提现对账 日兑付
     * @param $account_id
     * @return array
     * @throws drException
     */
    function dayTransfer( $account_id ){
        //$this->getLogin()->createPayLog()->get( )
        $tall = $this->createSql()->select( $this->getTable(),['account_id'=>$account_id,'opt_type'=>10 ],[0,20000],['fee','ctime'])->getAll();
        $payLog=[];
        foreach( $tall as $v) {
            $k=date("Ymd", $v['ctime']);
            $payLog[$k]['cnt']++;
            $payLog[$k]['fee']+=$v['fee'] ;
        }
        unset( $tall );
        $all = $this->getLogin()->createTableTransfer()->getAll( ['account_id'=>$account_id], [],[0,20000],[ 'fee','ctime'] );
        $transfer =[];
        foreach ( $all as $v ){
            $k=date("Ymd", $v['ctime']);
            $transfer[$k]['cnt']++;
            $transfer[$k]['fee']+=$v['fee'] ;
            if( !isset( $payLog[$k])) $payLog[$k]=['cnt'=>0,'fee'=>0 ];
        }
        $total=0;
        ksort($payLog);
        foreach( $payLog as $k=>$v ){
            $v2= isset(  $transfer[$k])?$transfer[$k]:['cnt'=>0,'fee'=>0 ] ;
            $payLog[$k]['tf']= $v2;
            $today =( $v['fee']- $v2['fee']);
            $payLog[$k]['today']= $today;
            $total+= $today;
            $payLog[$k]['total']=  $total;
            $payLog[$k]['key']=  $k ;
        }
        unset( $all);
        $re = [];
        foreach($payLog as $v ) $re[]=$v ;
        $re = array_reverse( $re );
        //$this->drExit( $re  );
        return $re ;
    }

    function yu( $account_id  ){
        $where = ['account_id'=>$account_id ];
        $payLog= $this->getLogin()->createQrPay()->tjPayLogGroup('account_id', $where);
        $tf = $this->getLogin()->createTableTransfer()->tjByGroupToObj( ['account_id'  ],$where,['account_id' ,'count(*) as cnt','sum(fee) as fee']);
        //$tf = $this->getLogin()->createTableTransfer()->tjByGroupToObj('account_id' , $where, ['account_id','count(*) as cnt','sum(fee) as fee']);
        //$this->drExit( $payLog );
        foreach($tf as $k=>$v ){
            if(! isset( $payLog[$k]))  $payLog[$k]=['cnt'=>0,'fee'=>0];
        }

        foreach($payLog as $k=>$v  ){
            if( isset( $tf[$k] ) ){
                $payLog[$k]['dt']= $v['fee']-$tf[$k][0]['fee'];
                $payLog[$k]['tf']= $tf[$k][0];
            }else{
                $payLog[$k]['dt']= $v['fee'];
            }
        }
        return $payLog ;
        //return ['']
    }

    function tui($id ){
        $this->update( $this->getTable(), ['id'=>$id], ['opt_type'=>11] );
        return $this;
    }

    function V3Parse( $var ){
        $type= $this->V3GetType( $var );
        //$this->drExit($type);
        $data=[];
        //$this->drExit($type );
        switch ($type){



            case 132:
                $data = $this->V3FX32( $var['data'] ,132  );
                $this->V3Account( $data );
                $data=[];
                break;

            case 31: #转账
                $data = $this->V3FX31( $var['data'] );
                $this->V3Append( $data );
                $data['pay_log_id']= $this->createSql()->lastID();
                break;
            case 131: #转账uid
                $data = $this->V3FX131( $var['data'] );
                break;

            case 33: #账单
                //红包的交易号 跟账单补一致
                $this->V3FX133( $var['data'] ,  $var['userId'] );
                $data=[];
                break;
            case 41: #网商银行
                $this->V3FX41( $var['data'] );
                break;
            case 35: #红包模式
                $data = $this->V35HB( $var );
                $this->V3Append($data);
                $data['pay_log_id']= $this->createSql()->lastID();
                break;
            case 351:# 口令红包模式
                $data = $this->V351HB( $var );
                $this->V3Append($data);
                $data['pay_log_id']= $this->createSql()->lastID();
                break;
            case  352:
                $this->E352( $var );
                break;

            case 100065: #创建平安银行收款码
                $this->VT65( $var );
                break;

            case 't36': #创建支付宝订单号
                $this->VT36( $var );
                break;
            case 't38': #创建钉钉红包支付宝订单号
                $this->VT38( $var );
                break;
            case 't78': #创建钉钉群收款支付宝订单号
                $this->VT78( $var );
                break;
            case 't60': #创建银闪付
                $this->VT60( $var );
                break;
            case 't90': #支.网页
                $this->VT90( $var );
                break;
            case 't39': #创建淘宝红包支付宝订单号
                $this->VT39( $var );
                break;

            case 't37': #创建活动订单号
                $this->VT37( $var );
                break;

            case 't22': #微信创建二维码
                $this->VT22( $var );
                break;
            case 36: //反向
                $data = $this->V3FX36( $var['data'] );
                $this->V3Append($data);
                $data['pay_log_id']= $this->createSql()->lastID();
                break;

            case 78: #钉钉群收款
                $data = $this->V3FX78( $var );
                break;
            case 120: #微信请你红包
                $data = $this->V3FX120( $var );
                break;

            case 90: #支.网银
                $data = $this->V3FX90( $var );
                break;

            case 91: #支.网银 支付宝
                $data = $this->V3FX91( $var );
                break;
            case 92: #支.网银2 支付宝
                $data = $this->V3FX92( $var );
                break;
            case 93: #支.网银 来之账单
                $data = $this->V3FX93( $var );
                break;
            case 'com.b2alipay.bill.qy': #支.网银 来之企业支付宝账单
                $data = $this->V3FX94( $var );
                break;

            case 60: #云闪付
                $data = $this->V3FX60( $var );
                break;

            case 66: #云闪付 交易记录
                $data = $this->V3FX66( $var );
                break;

            case 61: #云闪付批量
                $data = $this->V3FX61( $var );
                break;

            case 67: #云闪付批量
                $data = $this->V3FX67( $var );
                break;

            case 65: #平安银行
                $data = $this->V3FX65( $var );
                break;
            case 63:
                $data = $this->V3FX63( $var );
                break;

            case 38:#钉钉红包
                $data = $this->V3FX38( $var  );
                $this->V3Append($data);
                $data['pay_log_id']= $this->createSql()->lastID();
                break;

            case 39://淘宝红包
                $data = $this->V3FX39( $var  );
                $this->V3Append($data);
                $data['pay_log_id']= $this->createSql()->lastID();
                break;

            case '3200': #扫码 带备注
                $data = $this->V3FX3200( $var  );
                /*
                $this->V3Append($data);
                $data['pay_log_id']= $this->createSql()->lastID();
                */
                break;

           case 32: #扫码 不带备注
               $data = $this->V3FX32( $var['data'] );
               //$this->drExit( $data );

               break;

            case 22: //微信
                $data = $this->V3FX22( $var  );
                break;
            case 28: //微信 手机号收款
                $data = $this->V3FX28( $var  );
                break;

            case 24: //微信店员
                $data = $this->V3FX24( $var  );
                break;
            case 301: //支付宝点餐
                $data = $this->V3FX301( $var  );
                break;
            case 303: //支付宝店员通
                $data = $this->V3FX303( $var  );
                break;

            case 'q120':
                $this->VT120( $var );
                break;
            case 'j120':
                $this->VTj120( $var );
                break;
            case 'm120':
                $this->VTm120( $var );
                break;
            case 'bill130':
                $this->V3Bill130( $var );
                break;
            case 'dao130':
                $this->V3Dao130( $var );
                break;
            case 'cn.10086.online':
                $this->online10086( $var);
                break;
            case 'cn.10086.order':
                $this->order10086( $var);
                break;
            case 'cn.10086.createBill.320':
                $this->createBill10086( $var);
                break;
            case 'cn.huafei.create':
                $this->getLogin()->createMServer()->createBillByHfID( $var['fee_id']);
                break;
            case 'com.ali.wang.create':
            case 'com.ali.wang.create.error':
                $this->VT139( $var );#旺信 收款码
                break;
            case 'com.ali.wang.pick':
                $this->V3FX139( $var ); #旺信红包 记录
                break;

            case 'myapp.v13.qrGroup': #获取支付宝群链接
                $this->qr150( $var );
                break;

            case 'myapp.v13.group.join':
                $this->join150( $var );
                break;

            case 'myapp.v13.qrMoney':
                $this->qr15( $var );
                break;

            case 'v3.taobao.qun.url'://taoQunQr
                $this->taoQunQr($var);
                break;
            case 'v3.taobao.msg.system':
                $this->taoSystemMsg( $var );
                break;
            case 'v3.taobao.url':
                $this->taobao_url($var);
                break;
            case 'com.b2jd.qr': //京东.网银.产码
                $this->QR96($var);
                break;
            case 'com.b2jd.bill':
                $this->V3FX96($var);
                break;
        }
        return $data;
    }

    function taobao_url($var){
        $data= json_decode( base64_decode( $var['data'] ),true);
        $arg= json_decode( $var['arg'],true);
        //print_r($arg);
        //print_r($data);
        if($arg['ic']){
            $url = 'http://imsg.zahei.com/icomet/push?cname=DU'.$arg['ic'].'&content='.urlencode( json_encode( $data['data'] ) );
            file_get_contents( $url );
        }
        //$this->drExit($var);
    }

    function taoSystemMsg( $var ){
        $data= json_decode( base64_decode( $var['data'] ),true);

        $content= $data['originalData']['content'];
        if(strpos($content,'申请入群')){
            $activeContent = $data['originalData']['activeContent'];
            $da=[];
            $gid= $da['groupId']= drFun::cut($activeContent,'groupId=','&');
            $da['taskId'] = drFun::cut($activeContent,'taskId=','"');
            $da['taskResult'] = "1";

            #允许加入
            drFun::taoQunQuery( $var['taoID'],'mtop.taobao.chatting.group.task.approve', $da );
            $tem=  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $gid  ] ); //,'type'=>238
            if($tem['type']==237){
                drFun::sendTaoMsg($var['taoID'], $da['groupId'] , '多人加入正在清空群' );
                drFun::taoQunClear( $var['taoID'], $da['groupId'] );
                return ;
            }
            $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no' => $gid,'!='=>['ali_beizhu'=>''] ] , ['type' => 237] );
            $msg = "请发送红包 ". (($tem['realprice'] / 100) );
            if( $tem['realprice'] <=0 ) $msg='欢迎';
            drFun::sendTaoMsg($var['taoID'], $da['groupId'] , $msg );
            //print_r($data);
        }
        #$this->drExit( $var);
    }

    function createBill10086( $var ){
        $huafei = $this->getLogin()->createTableHfTrade()->getRowByKey( $var['hf_id'] );
        if(  $huafei['type']!=3 ) $this->throw_exception( "仅能处理构建总的订单",20010824 );
        if( $var['tel']!=$huafei['tel']) $this->throw_exception( "手机号码有错误",20010825 );

        //4构建失败 5构建成功
        $up['type']= 4;//$var['back']['error']?4:5;
        $up['opt_value']= drFun::json_encode( $var['back'] );
        if( $var['back']['data']['orderId'] )  {
            $up['type']= 5;
            $up['ali_trade_no']=  $var['back']['data']['orderId'];
            $up['ali_trade_ctime']=  intval($var['back']['data']['ts']/1000 );
            $up['account_id']= $var['account_id'];
        }elseif( $var['error']>0 ){
            $up['account_id']= $var['account_id'];
            $up['opt_value']= drFun::json_encode(['error'=> $var['error'],'error_des'=>$var['error_des'] ] );
        }
        $this->getLogin()->createTableHfTrade()->updateByKey( $var['hf_id'] , $up);
    }

    function online10086( $var ){
        $acc= $this->getLogin()->createQrPay()->getAccountByID( $var['account_id']);
        if($acc['zhifu_account']!=$var['tel']) $this->throw_exception("账户非法",20010801);
        if( isset($var['back']['retCode']) && intval($var['back']['retCode'])==0 ){
            $this->getLogin()->createQrPay()->modifyAccount( $acc['account_id'] , ['online'=> 1 ] )->updateClientTime(  $acc['account_id']  );
        } 
    }

    function order10086( $var){
        $acc= $this->getLogin()->createQrPay()->getAccountByID( $var['account_id']);
        if($acc['zhifu_account']!=$var['zhifu_account']) $this->throw_exception("账户非法",20010801);
        if( isset($var['back']['retCode']) && intval($var['back']['retCode'])==0 ){
            //$this->drExit($var['back']);
            $this->getLogin()->createQrPay()->updateClientTime(  $acc['account_id']  );

            foreach( $var['back']['data']['orderInfo'] as $vs){ //.back.data.orderInfo
                //if( $vs[''])
                try {
                    $this->bill320($vs, $acc);
                }catch (drException $ex ){

                }
            }
        }
    }

    function bill320($var , $account ){
        if( $var['busiStatus']!=4) return $this;

        $re['pay_type']=320 ;
        $re['account_ali_uid']=  $account['ali_uid'] ;

        //$data = json_decode($var['data'],true );

        //$row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>$data['outBizNo']] );

        $re['ali_trade_no']= $var['orderId'];//$row['ali_beizhu']?$row['ali_beizhu']:$data['outBizNo'];

        $tem = $this->getLogin()->createTableHfTrade()->getRowByWhere( [ 'ali_trade_no'=>$re['ali_trade_no']]);

        $re['ali_beizhu']=  $tem['trade_id']? $tem['trade_id']:'' ;// $var['orderListInfoItems'][0]['rechargeNum'];

        //$re['ali_trade_no']= $data['alipayOrderId'];  //支付宝订单号
        $re['ali_account']= $var['customerId'];//真实付款人姓名
        //$re['buyer']= $data['targetAlipayAccountName'];//真实付款人姓名
        $re['ali_uid']='-1' ;//$data['targetUid'];//付款人uid
        $re['ltime']= $t=  $var['createTime'];

        $re['ctime']= strtotime( substr($t,0,4).'-'. substr($t,4,2).'-'.substr($t,6,2).' '.substr($t,8,2).':'.substr($t,10,2).':'.substr($t,12,2) );//floor(  $re['ltime'] /1000 );
        $re['push_id']=    $account['account_id'];
        $re['fee']= drFun::yuan2fen( $var['orderListInfoItems'][0]['chargeFee'] )  ;//drFun::yuan2fen($data['amount']);
        $var['t']= date("Y-m-d H:i:s");
        $re['opt_value']=  $var;
        $re['account_id']= $account['account_id'];

        //print_r( $account );
        //$this->drExit( $re );

        $this->V3Append($re);
        $re['pay_log_id']= $lastId = $this->createSql()->lastID();
        $this->getLogin()->createTableHfTrade()->updateByKey( $tem['hf_id']  ,[ 'pay_time'=>$re['ctime'],'type'=>11,'pay_log_id'=>$re['pay_log_id'] ]);
        //$this->drExit( $re );
        $mqVar=$tem;
        $mqVar['cmd']= 'cn.huafei.trade';
        try{
            //$this->toMqTrade( $mqVar );
            $this->getLogin()->createQrPay()->toMqTrade( $mqVar );
        }catch ( drException $ex ){  }


        $this->getLogin()->createQrPay()->payMatchByLogID(  $lastId );

        return $this;
    }



    //反向收款
    function VT36( $var ){
        $data= json_decode( $var['data'], true );
        $re=[];
        $re['type']= 1;
        if( $data['success']===false){
            if( $data['code']=='AE0310514398') return [];
            $re['type']= 2;
            $re['data']= $data['message'];
        }else {
            if (trim($var['transferNo']) == '') return [];
            $cnt = $this->getLogin()->createTablePayLogTem()->getCount(['ali_trade_no' => $var['transferNo']]);
            if ($cnt > 0) $this->throw_exception("订单号：" . $var['transferNo'] . "  添加过！");
        }
        $re['ctime']= time();
        $re['ali_trade_no']= $var['transferNo'];
        $re['ali_uid']= $var['tUid'];
        $re['ali_beizhu']= $var['remark'];
        $re['account_ali_uid']= $var['userId'];
        $re['fee']= drFun::yuan2fen( $var['money'] )  ;
        $this->getLogin()->createTablePayLogTem()->append( $re );
        return $re ;
        //$this->drExit($re );
    }

    //红包错误
    function E352($var ){
        $re=[];
        $re['type']= 352;
        $re['ctime']= time();
        $arg = json_decode( $var['arg'], true );
        $re['ali_trade_no']=   trim($arg['scode'] ) ;
        $re['ali_uid']= $var['userId'] ;
        $re['ali_beizhu']= trim($arg['id'] );
        $re['account_ali_uid']=  $var['userId'] ;

        $re['fee']= 1  ;
        $re['data']= drFun::json_encode( ['cls'=> $var['cls'],'data'=> $var['data'] ,'arg'=>$arg ] );
        //print_r( $re );

        //$this->drExit( $var );
        $this->getLogin()->createTablePayLogTem()->append( $re );
        return $re ;
    }

    function VTm120($var){
        if( !$var['memberlist'] && $var['chatroom']){
            $wh_acc= [  'ali_trade_no'=>$var['chatroom']  ] ;
            //print_r( $wh_acc );
            //$this->drExit( $var );
            $this->getLogin()->createTablePayLogTem()->delByWhere( $wh_acc ,5);
            drFun::sendWxMsg( $var['wxID'],$var['chatroom'], "打扰了！");
        }

        return [];
    }

    function join150( $var ){

        if( !$var['gid']) $this->throw_exception("参数错误！",20021601);
        //$this->drExit( $var );
        $tem=  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $var['gid']  ] ); //,'type'=>121

        if($tem['type']==152){
            drFun::aliDelQunMember( $var['userId'], $var['gid'],$var['muid']);

            return ;
        }

        $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no' => $var['gid'],'!='=>['ali_beizhu'=>''] ] , ['type' => 152] );


        $msg = "请发送红包 ". (($tem['realprice'] / 100) );
        if( $tem['realprice'] <=0 ) $msg='欢迎';
        #drFun::sendWxMsg($re['me_id'], $re['chatroom'], ($tem['realprice'] / 100)); //."元红包" "请发 ".($tem['realprice']/100)

        drFun::sendMsgAli( $var['userId'], $var['gid'], $msg );
    }

    function VTj120( $var ){
        $re['chatroom']= trim($var['guid'],':');
        $re['me_id']= $var['wxID'];
        $content= json_decode( $var['content'],true );
        //sysmsg.sysmsgtemplate.content_template.link_list.link[0].memberlist.member
        $member= $content['sysmsg']['sysmsgtemplate']['content_template']['link_list']['link'][0]['memberlist']['member'];

        $re['friend_id']= $member['username'];
        $re['friend_name']= $member['nickname'];
        $re['utime']= $re['ctime']= time();

        if( $re['chatroom']=='') $this->throw_exception("参数错误",19112121);

        $tem=  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $re['chatroom']  ] ); //,'type'=>121
        if( $tem ){
            if($tem['type']==122){
                drFun::delQunMember($re['me_id'],$re['chatroom'],$re['friend_id'] );
            }else {
                //uid
                $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $re['me_id'] );
                if( $acc && in_array( $acc['user_id'],[606] ) ){
                    drFun::sendWxMsg($re['me_id'], $re['chatroom'], "请发包 ".($tem['realprice']/100) ); //."元红包"
                }
                #drFun::sendWxMsg($re['me_id'], $re['chatroom'], "↓↓↓↓复制↓↓↓↓"); //."元红包" "请发 ".($tem['realprice']/100)
                #drFun::sendWxMsg($re['me_id'], $re['chatroom'], ($tem['realprice'] / 100)); //."元红包" "请发 ".($tem['realprice']/100)
            }
            $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no' => $re['chatroom'],'!='=>['ali_beizhu'=>''] ] , ['type' => 122] );
        }

        drFun::qunMemberList( $var['wxID'] , $re['chatroom'] );
        //$this->drExit( $re );
        $row= $this->getLogin()->createTableUserWx()->getRowByWhere(['chatroom'=>$re['chatroom'],'me_id'=> $re['me_id'],'friend_id'=> $re['friend_id'] ] );

        if($row){
            $this->getLogin()->createTableUserWx()->updateByKey( $row['id'],['utime'=>time() ] );
            return [];
        }
        $this->getLogin()->createTableUserWx()->append($re );
        return [];
    }

    function qr15( $var ){
        $re=[];
        $data = json_decode( $var['data'],true);

        $re['type']= 15;
        $re['ctime']= intval($var['dt']/1000);
        $re['ali_trade_no']=  trim($data['codeId']) ;
        $re['ali_uid']= '' ;
        $re['ali_beizhu']= $var['remark'];
        $re['account_ali_uid']=  $var['userId'] ;
        $re['fee']=  drFun::yuan2fen( $var['money']) ; #当时间使用

        $data['alipayOrderString']= $data['qrCodeUrl'];
        $re['data']= drFun::json_encode(  $data );
        $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid']  );
        if( !$acc )  $this->throw_exception("账号不存在");

        $re['account_id']= $acc['account_id'];
        if( !$data['alipayOrderString'] ){
            $re['type']= -15;
        }

        ##$this->drExit( $re );

        $this->getLogin()->createTablePayLogTem()->append( $re );


        $tr_id = $var['remark'] ;
        $trade = $this->getLogin()->createTableTrade()->getRowByKey(  $tr_id );
        if( $trade && $trade['type']==4){
            $this->getLogin()->createQrPay()->upTradeByID(  $tr_id , ['type'=>3]);
        }


        return [];

    }

    //支付宝群二维码
    function qr150( $var ){

        $re= [];
        $arg= json_decode( $var['arg'],true);
        $data = json_decode( $var['data'],true);

        $re['type']= 150;
        $re['ctime']= intval($var['dt']/1000);
        $re['ali_trade_no']=  trim($arg['gid']) ;
        $re['ali_uid']= '' ;
        $re['ali_beizhu']='';
        $re['account_ali_uid']=  $var['userId'] ;
        $re['fee']= 0  ; #当时间使用
        $data['qr']= $data['qrcode'];
        $re['data']= drFun::json_encode(  $data );
        if(! $data['qrcode']) $this->throw_exception("群二维不存在",19112001);
        if( !$re['ali_trade_no']) $this->throw_exception("群号不能为空",19112006);


        //print_r( $re );$this->drExit( $var);


        $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid']  );
        if( !$acc ) {
            //$this->drExit( $re );
            drFun::sendMsgAli(  $var['userId'], $re['ali_trade_no'],"请先在后台添加！");
            return [];
        }

        $row = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_trade_no'=> $re['ali_trade_no'] ] );

        if( $row ){
            //$this->getLogin()->createTablePayLogTem()->delByWhere(['ali_trade_no'=> $re['ali_trade_no'] ] );
            $upvar=['ctime'=> time() , 'data'=> $re['data']  ];
            $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no'=> $re['ali_trade_no'] ]  ,  $upvar );
            $row['data']= drFun::json_decode( $row['data']);
            //drFun::delQunQr($var['wxID'],$re['ali_trade_no'],$row['data']['qr'] );
            drFun::sendMsgAli(  $var['userId'], $re['ali_trade_no'],"群已经更新！");

            drFun::aliDelQunQr( $var['userId'], $re['ali_trade_no'], $row['data']['qr']);
            return [];
        }
        $re['account_id']= $acc['account_id'];
        $this->getLogin()->createTablePayLogTem()->append( $re );
        drFun::sendMsgAli(  $var['userId'], $re['ali_trade_no'],"群已经添加");
        return [];



    }

    function taoClearQid( $qid ){

        return strtr( trim( $qid),['#3'=>'']) ;
    }

    /**
     * 淘宝群二维码加入 新建
     * @param $var
     * @return array
     * @throws drException
     */
    function taoQunQr( $var ){
        $re=[];
        $re['type']= 239;
        $re['ctime']= time();
        $re['ali_uid']= '' ;
        $re['ali_beizhu']='';
        $re['account_ali_uid']=  $var['taoID'] ;
        $re['fee']= 0  ; #



        $data = json_decode(  base64_decode($var['data']) ,true);
        $arg= json_decode( $var['arg'],true);
        $re['ali_trade_no']= $this->taoClearQid( trim($arg['id']) ) ;

        $opt_var=['qr'=>$data['data']['shortUrl'] ];
        if( !$opt_var['qr']) $this->throw_exception("群二维不存在",20062108);
        $re['data']= drFun::json_encode(  $opt_var );

        if( !strpos($re['ali_trade_no'],'G_'.$var['taoID']))$this->throw_exception("不是本人的群",20062118);

        //$this->drExit( $re );

        $row = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_trade_no'=> $re['ali_trade_no'] ] );
        //if( $row && $row['type']!=120 ) $this->throw_exception("当前码正在使用",19112002);

        $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid']  );
        if( !$acc ) {
            $this->throw_exception( "账号不存在！", 20062109);
        }
        if( $row ){
            //$this->getLogin()->createTablePayLogTem()->delByWhere(['ali_trade_no'=> $re['ali_trade_no'] ] );
            $upvar=['ctime'=> time() , 'data'=> $re['data']  ];
            $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no'=> $re['ali_trade_no'] ]  ,  $upvar );
            $row['data']= drFun::json_decode( $row['data']);
            //drFun::delQunQr($var['wxID'],$re['ali_trade_no'],$row['data']['qr'] );
            #$this->throw_exception(  drFun::qidToDai3( $var['taoID'] )  );
            drFun::sendTaoMsg(  $var['taoID'] ,  drFun::qidToDai3($re['ali_trade_no']),"群已经更新");
            return [];
        }
        $re['account_id']= $acc['account_id'];
        $this->getLogin()->createTablePayLogTem()->append( $re );
        drFun::sendTaoMsg(   $var['taoID'],  drFun::qidToDai3($re['ali_trade_no']),"群已经添加");

        return [];

        //print_r($re );
        //print_r($var );
        //$this->drExit( $data );
    }

    //微信群二维码
    function VT120( $var ){
        $re=[];

        $re['type']= 120;
        $re['ctime']= time();

        $re['ali_trade_no']=  trim($var['chatroom']) ;
        $re['ali_uid']= '' ;
        $re['ali_beizhu']='';
        $re['account_ali_uid']=  $var['wxID'] ;
        $re['fee']= 0  ; #当时间使用
        $re['data']= drFun::json_encode(  $var );
        if( !$var['qr']) $this->throw_exception("群二维不存在",19112001);
        if( !$re['ali_trade_no']) $this->throw_exception("群号不能为空",19112006);

        $row = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_trade_no'=> $re['ali_trade_no'] ] );
        //if( $row && $row['type']!=120 ) $this->throw_exception("当前码正在使用",19112002);

        $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid']  );
        if( !$acc ) {
            //$this->drExit( $re );
            drFun::sendWxMsg(  $var['wxID'], $re['ali_trade_no'],"请先在后台添加！");
            return [];
        }
        if( $row ){
            //$this->getLogin()->createTablePayLogTem()->delByWhere(['ali_trade_no'=> $re['ali_trade_no'] ] );
            $upvar=['ctime'=> time() , 'data'=> $re['data']  ];
            $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no'=> $re['ali_trade_no'] ]  ,  $upvar );
            $row['data']= drFun::json_decode( $row['data']);
            drFun::delQunQr($var['wxID'],$re['ali_trade_no'],$row['data']['qr'] );
            drFun::sendWxMsg(  $var['wxID'], $re['ali_trade_no'],"群已经更新");
            return [];
        }
        $re['account_id']= $acc['account_id'];
        $this->getLogin()->createTablePayLogTem()->append( $re );
        drFun::sendWxMsg(  $var['wxID'], $re['ali_trade_no'],"群已经添加");

        //检查群 是不是自己的
        drFun::qunMemberList( $var['wxID'], $re['ali_trade_no'] );
    }

    //微信
    function VT22($var){

        $re=[];
        $re['type']= 22;

        $re['ctime']= time();
        $re['ali_trade_no']=  $var['qrcode'] ;
        $re['ali_uid']= $var['wxID'] ;
        $re['ali_beizhu']= trim($var['desc'] );
        $re['account_ali_uid']=  $var['wxID'] ;
        $re['fee']= drFun::yuan2fen(  $var['money'] )  ;
        $re['data']= drFun::json_encode( ['data'=> $var ] );

        $cnt = $this->getLogin()->createTablePayLogTem()->getCount(['ali_trade_no' =>  $re['ali_trade_no'] ]);
        if ($cnt > 0) $this->throw_exception("二维码：" .  $re['ali_trade_no'] . "  添加过！");
        //$this->drExit( $re );
        $this->getLogin()->createTablePayLogTem()->append( $re );
        return $re ;

    }
    function VT37( $var ){
        $data= json_decode( $var['data'], true );
        $re=[];
        $re['type']= 37;

        $_arg = json_decode( $var['arg'], true );

        if( $data['success']===false){
            if( $data['code']=='AE0310514398') return [];
            $re['type']= 2;
            $re['data']= $data['message'];
        }else {
            if (trim( $data['batchNo'] ) == '') return [];
            $cnt = $this->getLogin()->createTablePayLogTem()->getCount(['ali_trade_no' => $data['batchNo'] ]);
            if ($cnt > 0) $this->throw_exception("订单号：" . $data['batchNo'] . "  添加过！");
        }

        $re['ctime']= time();
        $re['ali_trade_no']=  $data['batchNo'] ;
        $re['ali_uid']= $_arg['tUid'];
        $re['ali_beizhu']= $_arg['handle'] ;
        $re['account_ali_uid']= $var['userId'];
        $re['fee']= drFun::yuan2fen( $_arg['ePrice'] )  ;
        $re['data']= drFun::json_encode( ['data'=> $data,'arg'=> $_arg] );
        $this->getLogin()->createTablePayLogTem()->append( $re );
        return $re ;
    }

    function VT65($var){
        $re=[];
        $re['ctime']= time();
        $re['type']= 65;
        $data= json_decode( $var['data'], true );
        $arg= json_decode( $var['arg'], true );
        $re['fee']=    drFun::yuan2fen( $data['txnAmtSum'] );
        $re['account_ali_uid']= $var['pingAnID'] ; //收款人
        $re['ali_beizhu'] = $arg['remark'] ;


        if( !$data['qrCode'] ){
            $re['type']= 2;
            $re['data']= $data['msg']?$data['msg']:'二维码生成错误,请重新下单' ;
        }else{
            $re['ali_trade_no'] =  $arg['remark'] ;
            $opt_data = [];
            $opt_data['alipayOrderString'] = $data['qrCode'];
            $opt_data['data']= $data;
            $opt_data['arg']= $arg ;

            $re['data'] = drFun::json_encode($opt_data);
            //
            $cnt =  $this->getLogin()->createTablePayLogTem()->getCount( ['ali_trade_no'=>$re['ali_trade_no']  ] );
            //$this->drExit( $re );
            if( $cnt>0 ) return [];

        }

        $acc= $this->getLogin()->createQrPay()->getAccountByAliUid(  $re['account_ali_uid'] );
        $re['account_id']= $acc['account_id'];

        $this->getLogin()->createTablePayLogTem()->append($re);
        return  $re;
    }


    function QR96($var){
        $re=[];
        $re['ctime']= time();
        $re['type']= 96;
        $data= json_decode( $var['data'], true );



        $re['fee']=    drFun::yuan2fen( $data['arg']['amount'] )    ;
        $re['ali_beizhu'] = $data['arg']['id'] ;
        $re['account_ali_uid']= $var['account'] ; //收款人
        $re['ali_trade_no'] =  $data['alipayNo'] ;

        if( $data['epccGwMsg'] && $data['url']){
            $data['alipayOrderString']=['url'=>  $data['url'],'epccGwMsg'=>$data['epccGwMsg'] ];
            unset($data['url'] );
            unset($data['epccGwMsg'] );
            $re['data'] = drFun::json_encode($data);
            $cnt =  $this->getLogin()->createTablePayLogTem()->getCount( ['ali_trade_no'=>$re['ali_trade_no']  ] );
            if( $cnt>0 ) $this->throw_exception($re['ali_trade_no']. " 该订单已经添加过！",19101701);
        }else{
            $re['type']= 2;
            $re['data']=  '订单生成错误,请重新下单' ;
        }


        //print_r($data);
        //print_r($re);
        //$this->drExit($var);
        $this->getLogin()->createTablePayLogTem()->append($re);
    }

    function VT90( $var ){
        $re=[];
        $re['ctime']= time();
        $re['type']= 90;

        $data= json_decode( $var['data'], true );

        $re['fee']=    drFun::yuan2fen( $data['arg']['amount'] )    ;
        $re['ali_beizhu'] = $data['arg']['remark'] ;
        $re['account_ali_uid']= $var['account'] ; //收款人
        $re['ali_trade_no'] =  $data['alipayNo'] ;
        if( $data['epccGwMsg'] && $data['url']){
            $data['alipayOrderString']=['url'=>  $data['url'],'epccGwMsg'=>$data['epccGwMsg'] ];
            unset($data['url'] );
            unset($data['epccGwMsg'] );
            $re['data'] = drFun::json_encode($data);
            $cnt =  $this->getLogin()->createTablePayLogTem()->getCount( ['ali_trade_no'=>$re['ali_trade_no']  ] );
            if( $cnt>0 ) $this->throw_exception($re['ali_trade_no']. " 该订单已经添加过！",19101701);
        }else{
            $re['type']= 2;
            $re['data']=  '订单生成错误,请重新下单' ;
        }
        //print_r( $re );
        //$this->drExit( $var );
        $this->getLogin()->createTablePayLogTem()->append($re);
    }

    function VT60( $var ){
        $re=[];
        $re['ctime']= time();
        $re['type']= 60;
        $data= json_decode( $var['data'], true );

        //if( !$data ) return [];

        $arg= json_decode( $var['arg'], true );

        $re['fee']=    drFun::yuan2fen( $arg['money'] )    ;
        $re['account_ali_uid']= $var['uniID'] ; //收款人

        $re['ali_beizhu'] = $arg['remark'] ;

        if( $data['msg']!='success' ) {
            if(isset($arg['m2']) && intval( $arg['m2'])>0 ) return [];
            $re['type']= 2;
            $re['data']= $data['msg']?$data['msg']:'二维码生成错误,请重新下单' ;

        }else{
            $re['ali_trade_no'] =  $arg['remark'] ;//$data['params']['orderId'];
            $opt_data = [];
            $opt_data['alipayOrderString'] = $data['params']['certificate'];

            if (!$opt_data['alipayOrderString']) return [];

            $opt_data['arg'] = $arg;
            $re['data'] = drFun::json_encode($opt_data);

            if(isset($arg['m2']) && intval( $arg['m2'])>0 ){
                $re['realprice']=  $re['fee'];
                $re['fee']= intval( $arg['m2']) ;
                $re['type']= 61;
                $re['ali_trade_no']= $re['ali_beizhu'];
                unset( $re['ali_beizhu']  );

            }
            $cnt =  $this->getLogin()->createTablePayLogTem()->getCount( ['ali_trade_no'=>$re['ali_trade_no']  ] );
            if( $cnt>0 ) return [];
        }



        //$this->drExit( $re );

        /*
        print_r( $re );
        print_r( $data );
        print_r( $arg );
        $this->drExit($var );
        */

        $this->getLogin()->createTablePayLogTem()->append($re);

    }

    function VT78($var){
        $data= json_decode( $var['data'], true );
        $data['alipayOrderString']= $data['payUrl'];
        unset( $data['payUrl']);
        $arg= json_decode( $var['arg'], true );

        $re=[];
        //print_r( $arg );
        $bill= json_decode($arg['bill'] ,true );
        //print_r($bill);
        //$this->drExit( $var );
        $re['fee']= $arg['amount']?   drFun::yuan2fen( $arg['amount'] ) : drFun::yuan2fen( $bill[0]['amount'] )  ;
        $re['account_ali_uid']=$arg['creatorUid'] ; //收款人
        $where= $re;
        $where['type']=-78;
        $row=  $this->getLogin()->createTablePayLogTem()->getRowByWhere($where,['order'=>['pt_id'=>'desc'] ] );
        //if( $row )


        $re['ctime']= time();
        $re['type']= 78;

        $re['ali_trade_no']=  $arg['creatorUid'].'_'.$arg['groupBillId']."_".$var['dingID'] ;

        $ali_row=  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=> $re['ali_trade_no'] ]) ;



        //$re['ali_beizhu']= $arg['groupBillName'] ; //备注先去 生成好
        $re['ali_uid']=  $var['dingID'] ;//付款人
        unset($arg['linkUrl']);
        $data['arg']= $arg ;
        $re['data']= drFun::json_encode( $data );

        if( $ali_row ){
            if($ali_row['type']==77 ) unset( $re['type'] );
            $this->getLogin()->createTablePayLogTem()->updateByKey( $ali_row['pt_id'],$re );
        }
        elseif( $row ){
            $this->getLogin()->createTablePayLogTem()->updateByKey( $row['pt_id'],$re );
        }else{
            $this->getLogin()->createTablePayLogTem()->append($re);
        }
    }

    function VT38( $var ){
        //$this->drExit($var);
        $data= json_decode( $var['data'], true );
        $re=[];
        $re['type']= 38;
        $re['ctime']= time();
        $re['ali_trade_no']=  $var['dingdingOrderId'] ;
        $re['ali_uid']= '';
        $re['ali_beizhu']= $var['remark'] ;
        $re['account_ali_uid']= $var['dingID'];
        $re['fee']= drFun::yuan2fen( $var['money'] )  ;
        $re['data']= drFun::json_encode( $data );
        $this->getLogin()->createTablePayLogTem()->append( $re );
    }

    function V3Bill130( $var ){


        $arg= drFun::json_decode( $var['data']);

        $rz= drFun::json_decode( $var['rz'] );
        $re=[];
        $re['type']= 130;
        $re['ctime']= time();
        $re['ali_trade_no']= drFun::cut($rz['url'],'out_pay_id=','&') ;
        $re['ali_uid']=$arg['gid'] ;
        $re['ali_beizhu']= $arg['beizhu'] ;
        $re['account_ali_uid']='';
        $re['account_id']= $var['aid'];
        $re['fee']= drFun::yuan2fen( $arg['amount'] )  ;

        $re['data']= drFun::json_encode( ['alipayOrderString'=> $rz['body'] ,'url'=> $rz['url']] );

        $wh=['ali_trade_no'=> $re['ali_trade_no']];
        $cnt = $this->getLogin()->createTablePayLogTem()->getCount($wh);
        if( $cnt>0) $this->throw_exception("已经添加过", 100);
        //print_r( $re  );
        //$this->drExit( $var );
        $this->getLogin()->createTablePayLogTem()->append( $re );
    }

    function VT39( $var){

        if( $var['alipay']=='' ) return ;
        $re=[];
        $arg = json_decode( $var['arg'],true );
        $re['type']= 39;
        $re['ctime']= time();
        $re['ali_trade_no']= $arg['id'] ;
        $re['ali_uid']= '';
        $re['ali_beizhu']=    $arg['note'] ;
        $re['account_ali_uid']= $var['taoID'];
        $re['fee']= $arg['m'] ;//drFun::yuan2fen( $var['money'] )  ;
        //$arr = json_encode(  base64_decode( $var['surl'] ) , true );
        $re['data']= drFun::json_encode( ['alipayOrderString'=>$var['alipay'],'surl'=> $var['surl'] ] );

        $this->log("VT39>>". $re['ali_trade_no'] ." 开始！" );
        //$this->drExit("dddV");

        $wh = ['ali_trade_no' => $re['ali_trade_no']];
        $cnt = $this->getLogin()->createTablePayLogTem()->getCount($wh);
        if( $cnt>0) {
            $this->log("VT39>>". $re['ali_trade_no'] ." 已经存在！" );
            $this->throw_exception(  $re['ali_trade_no'] ." 已经存在！");
        }

        $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $var['taoID'] );
        $re['account_id']= $account['account_id'];


        $this->getLogin()->createTablePayLogTem()->append( $re );
    }

    function VT139( $var ){
        //$this->drExit($var );
        //print_r( $var );
        $re=[];
        $arg = json_decode( $var['data'],true );
        $re['type']=139;
        $re['ctime']=time();
        $re['ali_trade_no']= $arg['hongbaoId'] ;
        $re['ali_uid']= '';
        $re['ali_beizhu']= $arg['id']? $arg['id']: $var['id'];
        $re['account_ali_uid']= $var['wangID'];
        $re['fee']= intval($arg['money']) ;//
        $ext = json_decode( $var['ext'] ,true );
        //drFun::yuan2fen( $var['money'] )  ;
        $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $var['wangID'] );

        if( $arg['url']){
            $re['data']= drFun::json_encode( ['alipayOrderString'=>$arg['url'] ] );
        }
        else{
            if( $var['error']&& !$arg['error']) $arg['error']= $var['error'] ;
            $re['data']= $arg['error']?$arg['error']:'支付码生成失败，请重新下单';
            $re['type']= 2 ;
            drFun::wangMsg(  $var['wangID'], '错误>>'. $re['data']);

            if( $acc['online']==11 ){ #如果是主线上  则修改为备线
                $this->getLogin()->createQrPay()->upAccountByID($acc['account_id'],['online'=>1] );
            }
        }
        if( $re['ali_trade_no'] ) {
            $wh = ['ali_trade_no' => $re['ali_trade_no']];
            $cnt = $this->getLogin()->createTablePayLogTem()->getCount($wh);
            $str = '成功>>['.($arg['money']/100).'元] '. $arg['hongbaoId'];
            if( !$ext['url']  || !is_array( $ext) ){
                $str .=' 但是未生成H5短信链接';
                //$re['type']= 135 ; #直接过期
            }else{

                $re['data']= drFun::json_encode( $ext ); //['alipayOrderString'=>$arg['url'] ]
            }
            drFun::wangMsg(  $var['wangID'],$str  );
            if ($cnt > 0) $this->throw_exception("已经添加过", 100);

            //$trade= $this->getLogin()->createQrPay()->getTradeByID(  $arg['id']);
            $trade = $this->getLogin()->createTableTrade()->getRowByKey( $arg['id']);
            if( $trade && $trade['type']==4){
                $this->getLogin()->createQrPay()->upTradeByID( $arg['id'] , ['type'=>3]);
            }
            if( $trade && in_array($trade['type'],[3,1,11] )){
                $this->throw_exception("订单已经处理！",20030504);
            }
        }




        $re['account_id']= $acc['account_id'];


        //$this->drExit( $re );

        $this->getLogin()->createTablePayLogTem()->append( $re );
    }

    /**
     * 口令红包
     * @param $var
     * @return array
     * @throws drException
     */
    function V351HB( $var){

        $re=[];

        $re['pay_type']=351;
        $re['account_ali_uid']= $var['userId'];
        $arg= json_decode($var['arg'] ,true );
        if( !$arg['scode'] ||  !$arg['id']) $this->throw_exception("必要参数缺失");

        $data = json_decode( $var['data'],true);

        //print_r($data );





        $re['buyer']= $data['giftCrowdInfo']['creator']['userName'];//付款人姓名
        $re['ali_uid']= $data['giftCrowdInfo']['creator']['userId'] ;//付款人uid
        $re['ali_trade_no']=  $data['giftCrowdInfo']['crowdNo']; //支付宝订单号
        $re['ali_account']= $data['giftCrowdInfo']['creator']['alipayAccount'];//支付宝登录账号，联系方式
        $re['fee']=  drFun::yuan2fen($data['giftCrowdInfo']['amount']);
        $re['ctime']=  time();
        $re['ltime']=  $re['ctime']. rand(100,999);
        $re['ali_beizhu']=   $arg['id']  ;//$data['giftCrowdInfo']['remark'] ;

        $data['arg']= $arg;
        $re['opt_value']= $data ;
        //drFun::delFriend($re['account_ali_uid'],$re['ali_uid']  );

        //$this->drExit( $re );

        return $re;
    }


    /**
     * 红包模式
     * @return array
     * @param $var
     */
    function V35HB( $var ){

        if( isset($var['gid']) && $var['gid']){

            return $this->V150HB( $var );
        }
        //print_r( $var);

        //$data= json_decode( $var['data'],true);
        //$data=$var['data'];// strtr( $var['data'], ['\\'=>'','"'=>''] );
        $giftCrowdInfo  = drFun::cut($var['data'],'"giftCrowdInfo":',',"guessResult"' );//发红包
        $giftCrowdFlowInfo  = drFun::cut($var['data'],'"giftCrowdFlowInfo":',',"giftCrowdInfo"' );//收红包
        $giftCrowdFlowInfo= json_decode( $giftCrowdFlowInfo  ,true);
        $giftCrowdInfo= json_decode( $giftCrowdInfo  ,true);

        //print_r($giftCrowdFlowInfo );
        //print_r(  $giftCrowdInfo );

        $re=[];

        $re['pay_type']=35;
        $re['account_ali_uid']= $giftCrowdFlowInfo['receiver']['userId'] ;//drFun::cut( $data, 'userId=',',') ; //收款人id



        $re['buyer']= $giftCrowdInfo['creator']['userName'];//付款人姓名
        $re['ali_uid']= $giftCrowdInfo['creator']['userId'] ;//付款人uid
        $re['ali_trade_no']=  $giftCrowdInfo['crowdNo']; //支付宝订单号
        $re['ali_account']= $giftCrowdInfo['creator']['alipayAccount'];//支付宝登录账号，联系方式
        $re['fee']= floor( 100*  (0.001+ $giftCrowdInfo['amount'] ));
        $re['ctime']=  time();
        $re['ltime']=  $re['ctime']. rand(100,999);
        $re['ali_beizhu']=  $giftCrowdInfo['remark'] ;
        drFun::delFriend($re['account_ali_uid'],$re['ali_uid']  );
        return $re;
        //$this->drExit( $re );
    }

    function V150HB( $var ){

        $giftCrowdInfo  = drFun::cut($var['data'],'"giftCrowdInfo":',',"guessResult"' );//发红包
        $giftCrowdFlowInfo  = drFun::cut($var['data'],'"giftCrowdFlowInfo":',',"giftCrowdInfo"' );//收红包
        $giftCrowdFlowInfo= json_decode( $giftCrowdFlowInfo  ,true);
        $giftCrowdInfo= json_decode( $giftCrowdInfo  ,true);

        //print_r($giftCrowdFlowInfo );
        //print_r(  $giftCrowdInfo );

        $re=[];

        $re['pay_type']=150 ;
        $re['account_ali_uid']=  $var['userId'];//$giftCrowdFlowInfo['receiver']['userId'] ;//drFun::cut( $data, 'userId=',',') ; //收款人id



        $re['buyer']= $giftCrowdInfo['creator']['userName'];//付款人姓名
        $re['ali_uid']= $giftCrowdInfo['creator']['userId'] ;//付款人uid
        $re['ali_trade_no']=  $giftCrowdInfo['crowdNo']; //支付宝订单号
        $re['ali_account']= $giftCrowdInfo['creator']['alipayAccount'];//支付宝登录账号，联系方式
        $re['fee']= drFun::yuan2fen( $var['money']);
        $re['ctime']=  time();
        $re['ltime']=  $re['ctime']. rand(100,999);

        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>$var['gid']] );


        $re['ali_beizhu']= $row['ali_beizhu']?$row['ali_beizhu']:  $var['gid'] ;

        #drFun::delFriend($re['account_ali_uid'],$re['ali_uid']  );
        drFun::aliClearQunMember(  $var['userId'], $var['gid']);
        return $re;


        //$this->drExit( $var );
    }
    function getAliUserByUid( $ali_uid ){
        return $this->createSql()->select('ali_user', ['ali_uid'=> $ali_uid ] )->getRow();
    }

    function V3Account( $re ){

        $ali_user = $this->getAliUserByUid( $re['ali_uid']);
        if(! $re['ali_account'] ) return $this;

        if( !$ali_user   ){
            $this->insert('ali_user', $re,[ 'buyer','ali_account','ali_uid'] );
        }elseif( $re['ali_account'] != $ali_user['ali_account']  || $re['buyer'] != $ali_user['buyer']  ){
            $this->update('ali_user',['id'=>$ali_user['id']], $re,['buyer','ali_account']  );
        }
        ///$this->drExit($re);
        return $this;
    }

    function V3FX41( $data ){
        $re['pay_type']=41;
        $re['account_ali_uid']= drFun::cut( $data, 'userId=',',') ; //收款人id

        //$data= drFun::cut($data,'msgData=[','], pushData');

        $data= strtr( $data, ['\\'=>''] ); //,'"'=>''

        //$arr = json_decode($data,true );

        $re['ltime']= drFun::cut( $data,'mct":', ',');
        $re['ctime']= floor(  $re['ltime'] /1000 );

        $d_str = drFun::cut($data,'"content":"','"');


        $tarr =[];
        preg_match_all( '/([\d\.,]+)元/i', $d_str,$tarr);
        $re['fee'] = ceil($tarr[1][0] *100);
        $re['opt_value']= $d_str;

        $tarr =[];
        preg_match_all( '/尾号([\d]+)/i', $d_str,$tarr);
        $weihao= $tarr[1][0];

        $tarr= explode("通过", $d_str);
        $re['buyer']= $tarr[0];

        try{
            $this->V3Append41( $re, $weihao );
        }catch ( drException $ex ){
            //$this->drExit( $ex );
        }


    }

    function V3FX133( $data ,$ali_uid ){
        //$ali_user = $this->getAliUserByUid( $ali_uid);
        //if($ali_user ) return ;
        $data= json_decode( $data,true );

        for($i=1,$c=count($data[0]['billListItems']);$i<$c; $i++ ){
            $v=$data[0]['billListItems'][$i];
            $fee= $v['consumeFee'];
            if(substr($fee,0,1)!='+') continue;

            $fee= substr($fee,1);
            $fee= strtr( $fee,[','=>''] );

            $var=['account_ali_uid'=>$ali_uid];
            $var['ctime'] = $v['gmtCreate']/1000;
            $var['fee'] = floor(($fee+0.001)*100);
            $var['ali_trade_no']= $v['bizInNo'];
            $var['pay_type'] = 33;
            $arr= explode( '-',$v['consumeTitle'],2 );
            $var['ali_beizhu'] = $arr[0];//$v['consumeTitle'];
            unset($v['actionParam']);
            $var['opt_value']= $v;
            $var['opt_value']['t']= date("Y-m-d H:i:s");
            $var['buyer']=$arr[1];

            $dt= time()-  $var['ctime'];
            if( $dt>24*3600*2) continue;
            if( '余额宝'==$arr[0] ) continue;
            //$this->drExit($var );
            try {
                $this->V3Append($var);
                $lastId = $this->createSql()->lastID();
                #$this->getLogin()->createQrPay()->payMatchByLogID( $lastId );
            }catch (drException $ex){
            }

        }
    }

    function V3FX131($data){


        $re['pay_type']=131;
        $re['account_ali_uid']= drFun::cut( $data, 'userId=',',') ; //收款人id

        if( ! $re['account_ali_uid'] ){
            $this->throw_exception("未找到该账号", 20190101004);
        }

        $pl = drFun::cut($data,'"pl":"','"');
        $str = base64_decode($pl );

        $tarr=[];
        preg_match_all('/2088(\d{12})/U', $str,$tarr);
        $re['ali_uid']= $tarr[0][0];

        preg_match_all('/ANSFER([\d]+)([^\d])/U', $str,$tarr);
        $re['ali_trade_no']= $tarr[1][0];


        if(! $re['ali_uid'] ) $this->throw_exception("未找到ALIUID", 20190101005);
        if(! $re['ali_trade_no'] ) $this->throw_exception("未找到支付宝账号", 20190101006);

        $data= strtr( $data, ['\\'=>'','"'=>''] );

        $re['ltime']= drFun::cut( $data,'mct:', ',');
        $re['ctime']= floor(  $re['ltime'] /1000 );

        $payLog = $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();

        if(! $payLog ) {
            $key = 'U'.  $re['ali_trade_no'] ;
            $cache = new cache();
            $cache->getRedis()->set( $key , $re['ali_uid']  , 300 );
        }
        $re['pay_log_id'] = ( $payLog['ali_uid']=='' )?$payLog['id']: '' ;
        if( $re['pay_log_id'] ){
            $this->update( $this->getTable(), ['id'=>$re['pay_log_id']],  [ 'ali_uid'=>$re['ali_uid']  ] );
        }
        //print_r( $data );
        //$this->drExit( $re );

        return $re ;

    }
    function V3FX303( $var ){
        $data= $var['data'];
        $re['pay_type']=303;
        $re['account_ali_uid']= drFun::cut( $data, 'userId=',',') ; //收款人id
        $re['ali_uid']  =$re['ali_account']= '-1';

        $data= strtr( $data, ['\\'=>'','"'=>''] );

        $re['ltime'] =   drFun::cut($data,'gmtCreate:',',');  ;
        $re['ctime']=   intval($re['ltime']/1000 );
        $re['push_id']=  $re['ltime'] ;

        $re['fee']=  drFun::yuan2fen( drFun::cut($data,'mainAmount:',',')  );

        $buyer = drFun::cut($data,'付款方：,content:','}');
        $buyer_arr = explode(" ", $buyer);
        $re['buyer']=$buyer_arr[0]; //付款人姓名
        $re['ali_account']= $buyer_arr[1];;//支付宝登录账号，联系方式

        //print_r( $data );
        //$this->drExit( $re );

        $this->V3Append($re);
        $re['pay_log_id'] = $this->createSql()->lastID();

        return $re ;

    }

    function V3FX96( $var){

        //preg_match_all('|<tr>(.+)</tr>|',$var['data'],$arr);
        $arr =explode('</tr>', $var['data']);
        if(count($arr)<2) return ;
        $data_arr = [];
        for($i=0,$c=count($arr);$i< $c ;$i++){
            //$str= $arr[$i] ;// strip_tags( $arr[$i],['td']);

            $ta2= explode('</td>', $arr[$i] );
            $item=[];
            foreach ($ta2 as $t) $item[]= trim(strip_tags($t)) ;//strtr( strip_tags($t),[' '=>'']);
            if(count($item)>2 && $item[2]=='转入' && strpos($item[3],'充值单:')) {
                $data_arr[]= $item;
            }

        }
        if(!$data_arr) return;

        foreach ($data_arr as $item){
            $orderNo= drFun::cut($item[3],'充值单:',',');
            if( $item[2]!='转入' ||  !$orderNo) continue;
            $re=[];
            $re['pay_type']=96 ;
            $re['ali_trade_no']= $orderNo;

            $payLog = $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();
            if( $payLog ) continue ;
            $re['ali_uid']  =$re['ali_account']= '-1';
            $ctime= strtotime($item[0]);
            $re['ctime']= $re['ltime'] =$ctime;
            $re['push_id']=  $re['ltime'] ;
            $re['fee']=  drFun::yuan2fen(  $item[1] );
            $re['account_ali_uid']=$var['i'];

            $item['t']= date("Y-m-d H:i:s");
            $re['opt_value']=$item ;

            try{
                $this->V3Append($re);
                $re['pay_log_id'] = $this->createSql()->lastID();
                if(  $re['pay_log_id'] >0 ){ //撮合
                    $this->getLogin()->createQrPay()->payMatchByLogID(   $re['pay_log_id']  );
                }
            }catch (\Exception $e){
            }

            //$this->drExit($re);
        }

        //print_r($data_arr);
        //$this->drExit($arr);
        //$this->drExit($var);
    }

    function V3FX94($var){
        $data_arr = json_decode( $var['data'] ,true );
        //$this->drExit($data_arr);
        foreach( $data_arr['result']['detail'] as $k=>$data ){
            if($data['accountType']!='充值') continue;
            $re=[];
            $re['pay_type']=94 ;
            $re['ali_trade_no']=$data['orderNo'];
            $payLog = $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();
            if( $payLog ) continue ;
            $re['ali_uid']  =$re['ali_account']= '-1';

            $ctime= strtotime($data['tradeTime']);
            $re['ctime']= $re['ltime'] =$ctime;
            $re['push_id']=  $re['ltime'] ;
            $re['fee']=  drFun::yuan2fen(  $data['tradeAmount'] );
            $re['account_ali_uid']=$var['i'];

            $data['t']= date("Y-m-d H:i:s");
            $re['opt_value']=$data ;

            try{
                $this->V3Append($re);
                $re['pay_log_id'] = $this->createSql()->lastID();
                if(  $re['pay_log_id'] >0 ){
                    //撮合
                    $this->getLogin()->createQrPay()->payMatchByLogID(   $re['pay_log_id']  );
                }

            }catch (\Exception $e){

            }
        }
    }

    function V3FX93($var ){

        $data_arr = json_decode( $var['data'] ,true );

        //print_r( $data_arr );
        for( $i=0,$c=count($data_arr);$i<$c; $i++ ){
            $data= $data_arr[$i ];
            //if( substr( $data['consumeFee'],0,1)!='+' ) continue ;
            if(  $data['consumeTitle']!= '余额充值') continue ;
            $re=[];
            $re['pay_type']=93 ;
            $re['ali_trade_no']=$data['bizInNo'];
            //echo 'ik='. $re['ali_trade_no'].$data['consumeTitle'] ."\n\n";
            $payLog = $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();
            if( $payLog ) continue ;

            $re['ali_uid']  =$re['ali_account']= '-1';
            $re['ltime'] =  $data['gmtCreate'] ;
            $re['ctime']=   intval($re['ltime']/1000 );
            $re['push_id']=  $re['ltime'] ;
            $re['fee']=  drFun::yuan2fen(  $data['consumeFee'] );
            $re['account_ali_uid']=$var['userId'];
            $buyer_arr = explode("来自", $data['consumeTitle'] );
            $re['buyer']=$buyer_arr[1]; //付款人姓名
            unset($data['actionParam']);
            $re['opt_value']=$data ;

            $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $re['ali_trade_no'] ] );
            $re['ali_beizhu']= $row['ali_beizhu'] ;

            //$this->drExit( $re );
            try{
                $this->V3Append($re);
                $re['pay_log_id'] = $this->createSql()->lastID();
                if(  $re['pay_log_id'] >0 ){
                    //撮合
                    $this->getLogin()->createQrPay()->payMatchByLogID(   $re['pay_log_id']  );
                }
            }catch (\Exception $e){

            }

            //print_r( $re );

        }

        //print_r( $data );
        //$this->drExit( $var );
        return [];
    }

    function V3FX301( $var ){
        $data_arr = json_decode( $var['data'] ,true );
        for( $i=0,$c=count($data_arr);$i<$c; $i++ ){
            $data= $data_arr[$i ];
            if( substr( $data['consumeFee'],0,1)!='+' ) continue ;
            $re=[];
            $re['pay_type']=301;
            $re['ali_trade_no']=$data['bizInNo'];
            $payLog = $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();
            if( $payLog ){
                if( $payLog['ali_beizhu']=='' ){

                    //$this->log("zhidianc>>".  json_encode($payLog));
                    $this->createSql()->update("pay_log", ['ali_beizhu'=> '商家服务' ] , ['id'=> $payLog['id']] )->query();
                    //$re['pay_log_id'] = $row['id'];
                    try{
                       $this->getLogin()->createQrPay()->payMatchByLogID(   $payLog['id']  );
                    }catch (drException $ex ){
                    }
                }
                continue ;
            }
            $re['ali_uid']  =$re['ali_account']= '-1';
            $re['ltime'] =  $data['gmtCreate'] ;
            $re['ctime']=   intval($re['ltime']/1000 );
            $re['push_id']=  $re['ltime'] ;
            $re['fee']=  drFun::yuan2fen(  $data['consumeFee'] );
            $re['account_ali_uid']=$var['userId'];
            $buyer_arr = explode("来自", $data['consumeTitle'] );
            $re['buyer']=$buyer_arr[1]; //付款人姓名
            unset($data['actionParam']);
            $re['opt_value']=$data ;
            $re['opt_value']['t']= date("Y-m-d H:i:s");
            //$this->drExit( $re );
            try{
                $this->V3Append($re);
                $re['pay_log_id'] = $this->createSql()->lastID();
                if(  $re['pay_log_id'] >0 ){
                    //撮合
                    $this->getLogin()->createQrPay()->payMatchByLogID(   $re['pay_log_id']  );
                }
            }catch (\Exception $e){

            }

                //print_r( $re );

        }

        //print_r( $data );
        //$this->drExit( $var );
        return [];

    }
    function V3FX24( $var ){
        $c_arr =  drFun::xml_to_array( $var['content'])  ;
        $re['pay_type']=24;
        $re['ali_uid']  = $re['account_ali_uid']=  $var['wxID'] ;
        $re['ali_account']= '-1';
        //appmsg.mmreader.template_detail.line_content.topline.key.word
        $key_word= $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['key']['word'];
        if(! in_array($key_word, ['收款金额','付款金额'])  ){
            $this->throw_exception("收款模板不支持",201903301);
        }
        $re['fee']=  drFun::yuan2fen( $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['value']['word'] );

        $url= $c_arr['appmsg']['url'];
        $tem_arr = explode("openid=",$url);
        $number = $this->getNumber( $tem_arr[ count($tem_arr)-1 ] );

        $re['ltime'] = $re['ctime']=   $c_arr['appmsg']['mmreader']['template_header']['pub_time'];
        $re['ltime'].= ''.$number%1000 ;
        $re['push_id']=  $re['ltime'] ;

        $des  =  $c_arr['appmsg']['mmreader']['category']['item']['digest'];;//appmsg.mmreader.category.item.digest

        $dy = drFun::cut($des,'存入店长' ,'(*');
        $qm = drFun::cut($des,'存入店长' ,'的零钱');

        $opt_value= ['dy'=>$dy,'qm'=>$qm,'des'=>$des,'c'=>$c_arr   ];

        $re['opt_value']= $opt_value ;
        if( $dy || $qm){
            if( $qm ) $account  = $this->getLogin()->createQrPay()->getAccountIDByWhere( ['card_index'=>$var['wxID'],'zhifu_account'=> $qm ] ,['dan_row'=>1]);
            if( $dy&& !$account) $account  = $this->getLogin()->createQrPay()->getAccountIDByWhere( ['card_index'=>$var['wxID'],'zhifu_account'=> $dy ] ,['dan_row'=>1]);
            if(  $account ) {
                //$this->drExit(  $account );
                $re['account_id']= $account['account_id'];
                if( $account['ali_uid']) $re['account_ali_uid']= $account['ali_uid'];
            }
        }

        //$this->drExit( $re );

        $this->V3Append($re);
        $re['pay_log_id'] = $this->createSql()->lastID();

        if(  $re['pay_log_id'] <=0 ) $this->logErr("===pay_log_id==".date("Y-m-d H:i:s")."==\n". print_r($re,true ));

        //
        //$this->drExit( json_encode( $c_arr ) );

        return $re ;
    }
    function V3FX22($var ){
        $c_arr =  drFun::xml_to_array( $var['content'])  ;



        $re['pay_type']=22;
        $re['account_ali_uid']=  $var['wxID'] ;
        $re['ali_account']= $re['ali_uid']  = '-1';
        $key_word= $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['key']['word'];
        if(! in_array($key_word, ['收款金额','付款金额'])  ){
            $this->throw_exception("收款模板不支持",201903301);
        }
        $re['fee']=  drFun::yuan2fen( $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['value']['word'] );

        $url= $c_arr['appmsg']['url'];
        $tem_arr = explode("openid=",$url);
        $number = $this->getNumber( $tem_arr[ count($tem_arr)-1 ] );

        $re['ltime'] = $re['ctime']=   $c_arr['appmsg']['mmreader']['template_header']['pub_time'];
        $re['ltime'].= ''.$number%1000 ;
        $re['push_id']=  $re['ltime'] ;

        if( $c_arr['appmsg']['mmreader']['template_detail']['line_content']['lines']['line'][0]['key']['word']=='收款方备注'){
            $re['ali_beizhu']=$c_arr['appmsg']['mmreader']['template_detail']['line_content']['lines']['line'][0]['value']['word'];
        }
        //$this->drExit( json_encode( $c_arr));

        //$this->drExit( $re );
        $this->V3Append($re);
        $re['pay_log_id'] = $this->createSql()->lastID();

        return $re;
    }

    function V3FX28($var ){
        $c_arr =  drFun::xml_to_array( $var['content'])  ;



        $re['pay_type']=28;
        $re['account_ali_uid']=  $var['wxID'] ;
        $re['ali_account']= $re['ali_uid']  = '-1';
        $key_word= $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['key']['word'];
        if(! in_array($key_word, ['收款金额','付款金额'])  ){
            $this->throw_exception("收款模板不支持",201903301);
        }
        $re['fee']=  drFun::yuan2fen( $c_arr['appmsg']['mmreader']['template_detail']['line_content']['topline']['value']['word'] );

        $url= $c_arr['appmsg']['url'];
        $tem_arr = explode("openid=",$url);
        $number = $this->getNumber( $tem_arr[ count($tem_arr)-1 ] );

        $re['ltime'] = $re['ctime']=   $c_arr['appmsg']['mmreader']['template_header']['pub_time'];
        $re['ltime'].= ''.$number%1000 ;
        $re['push_id']=  $re['ltime'] ;

        if( $c_arr['appmsg']['mmreader']['template_detail']['line_content']['lines']['line'][0]['key']['word']=='收款方备注'){
            $re['ali_beizhu']=$c_arr['appmsg']['mmreader']['template_detail']['line_content']['lines']['line'][0]['value']['word'];
        }
        $des= strtr( $c_arr['appmsg']['des'],['，已存入零钱。点击可查看详情'=>'' ]);
        $re['buyer']=  $c_arr['appmsg']['mmreader']['template_detail']['line_content']['lines']['line'][0]['value']['word'];
        $opt_value= [ 'text'=>$des,'c'=>$c_arr ,'t'=>date("Y-m-d H:i:s")  ];
        $re['opt_value']= $opt_value ;

        #$this->drExit( json_encode( $re));

        #$this->drExit( $re );
        $this->V3Append($re);
        $re['pay_log_id'] = $this->createSql()->lastID();

        return $re;
    }

    function getNumber($str ){
        $number=0;
        for($i=0, $c=strlen($str); $i<$c;$i++){
            $number+=ord( $str{$i});
        }
        //$this->drExit( "number=".$number );
        return $number;
    }
    function V3FX3200( $var ){
        //print_r($var );

        $re['pay_type']=32;
        $re['account_ali_uid']=  $var['userId'] ;
        $data= strtr( $var['data'], ['\\'=>'','"'=>''] );

        //echo "\n".$data."\n";

        $content = drFun::cut($data,'content:[',']');
        $c_arr = explode("},{",  $content);
        $re['ali_account']= $re['ali_uid']  = '-1';
        $re['ali_beizhu'] = $this->getContent3200($c_arr,'理由' );
        $re['ali_trade_no']=  drFun::cut( $data,'&tradeNO=','&');
        $re['fee']=  drFun::yuan2fen(drFun::cut( $var['data'],'"money":"','"') );
        $re['ltime']=drFun::cut( $data,'gmtCreate=',',');
        $re['ctime']= floor(  $re['ltime'] /1000 );
        $re['push_id']=  $re['ltime'] ;

        if( !$re['ali_beizhu']) return [];

        $re['opt_value']['t']= date("Y-m-d H:i:s");


        $where2=['ali_trade_no'=> $re['ali_trade_no'] ];
        $row  = $this->createSql()->select("pay_log" ,$where2 )->getRow();
        if(  $row ){
            $this->createSql()->update("pay_log", ['ali_beizhu'=> $re['ali_beizhu'] ] , ['id'=> $row['id']] )->query();
            $re['pay_log_id'] = $row['id'];
        }else {
            $this->V3Append($re);
            $re['pay_log_id'] = $this->createSql()->lastID();
        }
        return $re ;
        //$this->drExit($re );
    }
    function getContent3200($c_arr, $key){
        $re = '';
        foreach($c_arr as $v ){
            if( strpos( $v,$key )){
                $re = drFun::cut( $v,'content:',',');
            }
        }
        return $re ;
    }

    function V3FX39( $var ){


        $arg = json_decode($var['arg'],true );
        $data =json_decode( $var['data'], true);
        $data_result= json_decode( $data['data']['result'], true);
        //print_r( $var );
        //print_r( $data_result );
        $deteail= $data_result['result']['details'][0];


        $re['account_ali_uid']= $var['taoID'] ;
        $re['ali_uid']  = '-1';
        $re['ali_account']= $deteail['receiver'];


        //自己发红模式
        //$re['ali_beizhu']= substr($arg['hongbao_id'],-11 );
        $re['pay_type']=39;
        $gid=   trim($arg['hongbao_id']);// substr($arg['hongbao_id'],-11 );

/*
        $re['pay_type']=239;
        //入群发红包模式 还得需用 ccode去pay_tem 里面找 当前群是搞到 那个8018的号
        $gid=  $arg['ccode'] ;
*/
        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>$gid ] );
        $re['ali_beizhu']= $row['ali_beizhu']?$row['ali_beizhu']: $gid;

        if( 39==$re['pay_type'] && $row){
            $this->getLogin()->createTablePayLogTem()->updateByKey( $row['pt_id'], [ 'type'=>-39]); ##成功
        }




        $re['ali_trade_no']= $arg['hongbao_id'] ;  //支付宝订单号
        $re['fee']= $deteail['amount'];
        $re['ltime']= $deteail['timestamp'];
        $re['ctime']= floor(  $re['ltime'] /1000 );
        $re['push_id']= $arg['hongbao_id'];
        //$this->drExit( $re );
        return $re ;
    }

    function V3FX139( $var ){
        $data= json_decode( $var['data'], true);

        $re=[];
        $re['pay_type']=139;
        $re['account_ali_uid']= $var['wangID'] ;
        $re['ali_uid']  = '-1';
        $re['ali_account']=  $data['id'];

        $re['ali_trade_no']= $data['hongbaoId'] ;  //支付宝订单号

        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>$re['ali_trade_no'] ] );

        $re['ali_beizhu']=  $row['ali_beizhu']?$row['ali_beizhu']: $re['ali_trade_no'] ;


        $re['fee']= intval($data['money']);
        $re['ltime']= time().'000';
        $re['ctime']= time();
        $re['push_id']= $data['hongbaoId'];

        $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no'=>$re['ali_trade_no'] ] , ['type'=>136]);

        //$this->drExit( $re );

        $this->V3Append($re);
        $lastID= $re['pay_log_id'] = $this->createSql()->lastID();
        try {
            $this->getLogin()->createQrPay()->payMatchByLogID($lastID);
            $this->log("vt139>>". $lastID);

        }catch (\Exception $ex){
            $this->log("vt139 last id=".$lastID." error>>". $ex->getCode()." msg=". $ex->getMessage()  );
        }

    }


    function getUnipayOrder( $order ){

        if($order['orderStatus']!='02') $this->throw_exception("未成功状态", 190829001);



        $order_id = $order['orderId'];

        $year= substr($order_id,0,4 );
        $arr= explode("   ",$order['tn']);

        if(!in_array($year,[2019,2020])) $year= date('Y');

        $stime= substr($arr[ count($arr)-1],-10);
        //echo $stime.'<br>';
        $stime= $year."-".substr($stime,0,2  ).'-'.substr($stime,2,2  )." ". substr($stime,4,2  ).":". substr($stime,6,2  ).":". substr($stime,8,2  );

        //$this->drExit( $stime);

        /*
        $stime= substr($order_id,0,4 ).'-'.substr($order_id,4,2 ).'-'.substr($order_id,6,2 )." ".substr($order_id,8,2 );
        $stime.=":".substr($order_id,10,2 );
        $stime.=":".substr($order_id,12,2 );
        */

        //

        $stime= strtotime( $stime );

        $re=[];


        //$this->drExit( date("Y-m-d H:i:s" ,$stime)   );
        $re['ali_trade_no']= "U". $arr[ count($arr)-1];
        $re['ctime']= $stime>0 ?$stime: strtotime($order['orderTime']  ) ;
        $re['buyer']= $re['ali_account']= strtr($order['title'] ,['向*'=>'','-收款'=>'']) ;
        $re['fee']=  drFun::yuan2fen( $order['amount']);


        //$this->drExit(  $re );

        return $re ;
    }

    function V3FX65( $var ){
        $data= json_decode($var['data'],true );

        //print_r( $data );
        $re=['success'=>0,'error'=>[] ];
        foreach($data['transRecordsList'] as $v  ){
            try {
                $this->V3FX65Item( $v, $var['pingAnID'] );
                $re['success']++;
            }catch ( drException $ex ){
                $re['error'][]='['.$ex->getCode().']'.$ex->getMessage();
            }
        }
        //$this->drExit( $var );
        return $re;
    }
    function V3FX65Item( $v,$pinganID ){
        $re=['ali_trade_no'=>'P'.$v['transNo'],'ctime'=> strtotime($v['compsiteTime'])];
        $key= $re['ali_trade_no'].'_'. $pinganID;
        if(  self::$uniPayLog[ $key ]) $this->throw_exception("已经存在过".  $key,19092903 );
        $re['pay_type']=65;
        $re['account_ali_uid']= $pinganID ;
        $re['ltime']=  $re['ctime']*1000 ;
        $re['ali_uid']='1';
        $re['opt_value']= ['data'=> $v ,'t'=>date("Y-m-d H:i:s")  ];
        $re['fee']= drFun::yuan2fen($v['transAmount']);
        if( $v['transType']!='C')   $this->throw_exception( '仅添加收入', 19092907) ; ;

        $row= $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();
        if( $row ) $this->throw_exception($re['ali_trade_no']." 已经存在", 19092906) ;
        $this->V3Append($re);
        $lastID= $this->createSql()->lastID();

        if(  $re['fee']==1  ||  $re['fee']==1111  ) {
            $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid'] );
            $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
        }
        $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );

        return $this;

    }

    function V3FX67( $var){
        $data= json_decode($var['data'],true );

        $re=['success'=>0,'error'=>[] ];
        $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $var['uniID'] );

        //print_r($data);
        foreach( $data['params']['transRecordsList'] as $v ){
            try {
                $v['account_id']= $account['account_id'] ;
                $this->V3FX67Item($v, $var['uniID']);
                $re['success']++;
            }catch ( drException $ex ){
                $re['error'][]='['.$ex->getCode().']'.$ex->getMessage();
            }
        }
        //$this->drExit($re);
        return $re ;

    }
    function V3FX67Item( $v, $uniID ){


        $re=[];

        $this->getUnipayPay( $v['key'],'',$re ,['date'=> $v['transTime'] ] );

        $key= $re['ali_trade_no'].'_'. $uniID;
        if(  self::$uniPayLog[ $key ]) $this->throw_exception("已经存在过".  $key,19111501 );

        self::$uniPayLog[ $key ]=1;


        $re['fee']= intval($v['transAmount']);
        if($re['fee']<=0) $this->throw_exception("金额不为空".  $key,19111502 );
        #$re['opt_id']=$uniID;

        $re['pay_type']=67;
        $re['account_ali_uid']= $uniID ;
        $re['ltime']=  $re['ctime']*1000 ;
        $re['ali_uid']='1';
        $re['opt_value']= ['data'=> $v ,'t'=>date("Y-m-d H:i:s")  ];

        //print_r($re );       $this->drExit($v);

        //$wh_row= ['ali_trade_no'=> $re['ali_trade_no'],'account_id'=>$v['account_id']  ];
        $wh_row= ['ctime'=> $re['ctime'],'fee'=> $re['fee'],'account_id'=>$v['account_id']  ];
        $row= $this->createSql()->select( $this->getTable(), $wh_row )->getRow();

        if( $row ) $this->throw_exception($re['ali_trade_no']." 已经存在", 190830001) ;

        $this->V3Append($re);
        $lastID= $this->createSql()->lastID();
        $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );

    }
    function V3FX61($var ){

        $data= json_decode($var['data'],true );
        //print_r( $data );
        //'117625206448206'!= $var['uniID']

        #if( !in_array(  $var['uniID'], ['117625206448206'] ) ) return ;

        $re=['success'=>0,'error'=>[] ];
        $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $var['uniID'] );

        foreach( $data['params']['uporders'] as $v ){
            try {
                $v['account_id']= $account['account_id'] ;
                $this->V3FX61Item($v, $var['uniID']);
                $re['success']++;
            }catch ( drException $ex ){
                $re['error'][]='['.$ex->getCode().']'.$ex->getMessage();
            }
        }
        //print_r($re );
        //$this->drExit($var );
    }

    function V3FX61Item( $v ,$uniID ){
        $re=$this->getUnipayOrder($v);
        $key= $re['ali_trade_no'].'_'. $uniID;
        if(  self::$uniPayLog[ $key ]) $this->throw_exception("已经存在过".  $key,190830002 );

        self::$uniPayLog[ $key ]=1;
        $re['pay_type']=61;
        $re['account_ali_uid']= $uniID ;
        $re['ltime']=  $re['ctime']*1000 ;
        $re['ali_uid']='1';
        #$re['opt_id']= $uniID;

        $re['opt_value']= ['data'=> $v ,'t'=>date("Y-m-d H:i:s")  ];

        if( $re['ctime']< 1567098508) $this->throw_exception("时间不对！".  $key,190830004 );
        if( $v['account_id']>0 ){
            $row= $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'],'account_id'=>$v['account_id']  ] )->getRow();
            //$this->logs_s( "V3FX61Item2=\t".$v['account_id']. "\n"  ,'debug.log');
        }else{
            $row= $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'],'opt_id'=>$re['account_ali_uid'] ,'pay_type'=>61] )->getRow();
        }

        if( $row ) $this->throw_exception($re['ali_trade_no']." 已经存在", 190830001) ;

        $this->V3Append($re);
        $lastID= $this->createSql()->lastID();

        //$this->drExit( $re );
        $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );

        //$this->
        //print_r($v );

    }

    function V3FX63($var){

        return [];


        $re['pay_type']=63 ;
        $re['account_ali_uid']= $var['uniID'] ;
        $data = json_decode($var['data'],true );
        if( $data['body']['title']!='动账通知' ){

            return [];
        }

        if( $data['info']['rk']=='' ) return [];
        $rk=  explode( '-',$data['info']['rk'] );
        $re['ali_trade_no']= $rk[2].'N'.$rk[3];
        $time = date('Y-m-').  strtr(drFun::cut( $data['body']['alert'] ,'卡于','分' ), ['日'=>' ','时'=>':' ]).':'. substr($rk[3],-2 );

        $re['ali_uid']='1';//付款人uid
        $re['ctime']= strtotime($time)>0 ? strtotime($time) :time()  ;//付款人uid
        $re['ltime']=  $re['ctime']. rand(100, 999) ;
        $re['push_id']= $var['uniID'] ;
        $re['fee']= drFun::yuan2fen(  drFun::cut( $data['body']['alert'] ,'入账','元' )   );
        $re['opt_value']= ['data'=> $data ,'text'=> $data['body']['alert'] ,'t'=>date("Y-m-d H:i:s")  ];


        /*print_r( $re );
        print_r( $data );
        $this->drExit( $var);
        */

        $cnt = $this->createSql()->getCount( $this->getTable() , ['ali_trade_no'=>  $re['ali_trade_no'] ] )->getOne();
        if( $cnt >0)    $this->throw_exception( "重复上传", 19082802);
        $this->V3Append($re);
        $re['pay_log_id']= $this->createSql()->lastID();
        return $re ;
    }

    function V3FX92( $var ){

        $re['pay_type']=92;
        $re['account_ali_uid']=  $var['userId'] ;

        $data= strtr( $var['data'],['"'=>'','\\'=>''] );
        $re['ali_trade_no']= drFun::cut($data,'tradeNO=','&' );
        $re['ali_uid']='1';//付款人uid
        $re['ltime']= drFun::cut($data,'gmtCreate:',',' );
        $re['ctime']=  intval($re['ltime']/1000 );
        $re['push_id']= $var['userId'] ;
        $re['fee']= drFun::yuan2fen(  drFun::cut(  $data,'￥',',' )   );
        $re['opt_value']= ['data'=> $data  ,'t'=>date("Y-m-d H:i:s")  ];
        $re['buyer']= ''; // drFun::cut($data,'交易对象：,content:','}' );

        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $re['ali_trade_no'] ] );
        $re['ali_beizhu']= $row['ali_beizhu'] ;

        #$this->drExit( $re );

        $this->V3Append($re);
        $re['pay_log_id']= $this->createSql()->lastID();
        return $re ;


    }

    function V3FX91($var){
        $re['pay_type']=91;
        $re['account_ali_uid']=  $var['userId'] ;

        $data= strtr( $var['data'],['"'=>'','\\'=>''] );
        $re['ali_trade_no']= drFun::cut($data,'tradeNO=','&' );
        $re['ali_uid']='1';//付款人uid
        $re['ltime']= drFun::cut($data,'gmtCreate:',',' );
        $re['ctime']=  intval($re['ltime']/1000 );
        $re['push_id']= $var['userId'] ;
        $re['fee']= drFun::yuan2fen(  drFun::cut(  $data,'￥',',' )   );
        $re['opt_value']= ['data'=> $data  ,'t'=>date("Y-m-d H:i:s")  ];
        $re['buyer']= ''; // drFun::cut($data,'交易对象：,content:','}' );

        /*
            print_r($re );
            echo  $data ;
            $this->drExit($var );
        */
        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $re['ali_trade_no'] ] );
        $re['ali_beizhu']= $row['ali_beizhu'] ;

        if($_GET['tt']){
            //$this->drExit( $re );
        }

        $this->V3Append($re);
        $re['pay_log_id']= $this->createSql()->lastID();
        return $re ;

    }

    function V3FX90( $var ){
        $re['pay_type']=90;
        $re['account_ali_uid']= $var['account'] ;

        $data = json_decode($var['data'],true );
        $data['bank']= strip_tags($data['bank'] );
        $arg= json_decode($var['arg'],true );
        $re['ali_trade_no']= $data['alipayNo'];
        $re['ali_account']=   $data['bank'] ;
        $re['buyer']=   $data['buyer'] ;//真实付款人姓名
        $re['ali_uid']='1';//付款人uid
        $re['ctime']= strtotime($data['time'] );
        $re['ltime']=  $re['ctime']*1000 ;
        $re['push_id']=$var['account'] ;
        $re['fee']= drFun::yuan2fen( $data['amount'] );
        if(  $re['fee']<=0 ) return [];
        $re['opt_value']= ['data'=> $data,'arg'=>$arg  ,'t'=>date("Y-m-d H:i:s")  ];
        //$row= $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'] ] )->getRow();
        //if( $row )  $this->throw_exception( "重复上传", 19101704);

        //print_r( $data );  print_r( $re );       $this->drExit( $var );
        //$this->getLogin()->createTablePayLogTem()->getRowByWhere()
        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>  $re['ali_trade_no'] ] );
        $re['ali_beizhu']= $row['ali_beizhu'] ;

        if($_GET['tt']){
            #$this->drExit( $re );
        }

        $this->V3Append($re);
        $re['pay_log_id']= $this->createSql()->lastID();

        return $re ;


    }

    function getUnipayPay( $key, $id_prefix, &$re,$opt=[]){
        $arr= explode('-', $key );
        $year= substr( $opt['date'],0,4);
        $time= $arr[3];

        if(!in_array($year,[2019,2020,2021,2022] )) $year=date("Y");
        $time = $year.'-'.substr($time,0,2).'-'.substr($time,2,2)." ".substr($time,4,2).":".substr($time,6,2) .":".substr($time,8,2);
        //$time= date
        $re['ctime']= strtotime( $time);
        //$re['ali_trade_no']= $id_prefix.'t'.$arr[3];
        $re['ali_trade_no']= 'U'.$arr[4].$arr[3];
        return $this;
    }

    function V3FX66( $var ){

        //return [];
        $re['pay_type']=66;
        $re['account_ali_uid']= $var['uniID'] ;

        $data= json_decode( $var['data'] , true );

        if( $data['params']['inOutType']!='02') $this->throw_exception("必须是入账", 19111501);



        $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid'] );
        $id_pre='J'.intval($account['account_id'])."f".$data['params']['transAmount'] ;

        $this->getUnipayPay( $data['params']['rowKey'],$id_pre,$re ,['date'=> $data['params']['transTime']] );

        $re['fee']=intval( $data['params']['transAmount'] );
        if(  $re['fee']<=0 ) return [];
        $re['ltime']=  $re['ctime']*1000 ;
        $re['push_id']=    $var['uniID'] ;

        $payerCardNo = $data['params']['payerCardNo'] ;
        $parr = explode(" ", $payerCardNo);

        $re['ali_account']=$parr[0].'('. substr($data['params']['payerCardNo'],-4).')' ; //真实付款人姓名
        $re['buyer']=   $re['ali_account'].$data['params']['payerNm']  ;//真实付款人姓名
        $re['ali_uid']='1';//付款人uid
        $re['opt_value']= ['data'=> $data,'t'=>date("Y-m-d H:i:s")  ];

        $re['ali_beizhu']=  $data['params']['postscript']? trim( base64_decode( $data['params']['postscript'] )):'';

        //$this->drExit( $re );

        //$wh_row= ['ali_trade_no'=> $re['ali_trade_no'],'account_id'=>$account['account_id']  ];
        $wh_row= ['ctime'=> $re['ctime'],'fee'=> $re['fee'],'account_id'=>$account['account_id']  ];
        $row= $this->createSql()->select( $this->getTable(), $wh_row )->getRow();

        if( $row ){
            $upvar=['buyer'=> $re['buyer']  ] ;
            if( $re['ali_beizhu']  ) $upvar['ali_beizhu']= $re['ali_beizhu'] ;

            $this->createSql()->update( $this->getTable(), $upvar,['id'=>$row['id']] )->query();
            $this->throw_exception( "重复上传", 190828002);
        }

        $this->V3Append($re);
        $lastID= $re['pay_log_id'] = $this->createSql()->lastID();
        //$this->getLogin()->createQrPay()->payMatchByLogID( $lastID );
        //$this->logs_s( "pay_log_id60 =\t".$lastID. "\n"  ,'debug.log');
        return $re ;

    }

    function V3FX60( $var ){

        $re['pay_type']=60;
        $re['account_ali_uid']= $var['uniID'] ;
        $data = json_decode($var['data'],true );
        $data['params']['orderDetail'] = json_decode( $data['params']['orderDetail'],true );
        $arg= json_decode($var['arg'],true );

        $order=  $this->getUnipayOrder( $arg['order']);



        $re['ali_beizhu']=  $data['params']['orderDetail']['postScript'] ;
        $re['ali_trade_no']= $order['ali_trade_no'] ;//$data['params']['orderDetail']['walletOrderId'] ;

        $re['ali_account']= $data['params']['orderDetail']['payUserName'] ; //真实付款人姓名
        $re['buyer']=  $data['params']['orderDetail']['payCardInfo'].$data['params']['orderDetail']['payUserName'] ;//真实付款人姓名
        $re['ali_uid']='1';//付款人uid
        //
        $re['ctime']= $order['ctime'];
        if( abs($re['ctime']-time())>24*3600 )    $re['ctime']=strtotime( $data['params']['orderTime'] ) ;
        if( abs($re['ctime']-time())>24*3600 )    $this->throw_exception("已经超过24小时！" , 19100105) ;

        $re['ltime']=  $re['ctime']*1000 ;//strtotime( $data['params']['orderTime'] )*1000 ;

        $re['push_id']=   $data['params']['orderDetail']['voucherNum'] ;
        $re['fee']= intval( $data['params']['transAt'] ); //到账   totalAmount实际金额
        if(  $re['fee']<=0 ) return [];
        $re['opt_value']= ['data'=> $data,'arg'=>$arg  ,'t'=>date("Y-m-d H:i:s")  ];

        #$re['opt_id']= $re['account_ali_uid'];

        $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid'] );
        //$row= $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'],'opt_id'=>$re['account_ali_uid'] ,'pay_type'=>61] )->getRow();
        $row= $this->createSql()->select( $this->getTable(), ['ali_trade_no'=> $re['ali_trade_no'],'account_id'=>$account['account_id']  ] )->getRow();

        if( $row ){
            $this->createSql()->update( $this->getTable(),['buyer'=> $re['buyer'],'ali_beizhu'=> $re['ali_beizhu']  ],['id'=>$row['id']] )->query();

            if(  $re['fee']==1  ||  $re['fee']==1111  ) {

                $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
            }
            $this->throw_exception( "重复上传", 190828002);
        }



        //$this->drExit( $re );

        /*
        print_r( $re );
        $this->drExit( $var );
        */
        $this->V3Append($re);
        $lastID= $re['pay_log_id'] = $this->createSql()->lastID();
        $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );
        //$this->logs_s( "pay_log_id60 =\t".$lastID. "\n"  ,'debug.log');
        return $re ;
    }

    function V3FX120( $var ){
        $re['pay_type']=120;
        $re['account_ali_uid']= $var['wxID'] ;



        $data = json_decode($var['data'], true );
        $arg = json_decode($var['arg'], true );

        $data['version']= $var['version'];
        //$this->drExit( $var['data']);

        //echo $data['sessionUserName'];
        $arg['talker']=  $arg['talker'] ? $arg['talker']:$data['sessionUserName'];

        //$this->drExit( $arg );
        $row=[];
        $row=[];
        if(  $arg['talker'] ) $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>$arg['talker']] );

        $re['ali_beizhu']= $row['ali_beizhu']?$row['ali_beizhu']: $arg['talker'];

        $re['ali_trade_no']= $data['record'][0]['receiveId'];
        $re['ctime']= $data['record'][0]['receiveTime'];
        $re['ltime']= $re['ctime']*1000;
        $re['fee']= $data['record'][0]['receiveAmount'];

        $data['talker'] = $arg['talker'];

        $re['opt_value']= $data;
        $re['ali_uid']= $data['sendUserName'];

        //$this->drExit( $re );
        //buyer


        $row= $this->getLogin()->createTableUserWx()->getRowByWhere(['friend_id'=> $re['ali_uid'] ]);
        if($row){
            $re['buyer']= $row['friend_name'];
        }

        $account= $this->getLogin()->createQrPay()->getAccountByAliUid( $var['wxID'] );
        if( $data['receiveStatus']!=2){

            //$this->log($var['data'],'log/');
            $this->logs_s(  $var['wxID']."\t".$var['version']."\t". $var['data']   ,'hongbao.log');
            if( $data['totalNum']==1 ){
                if( $account ) $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 2 ] );


                drFun::clearQunMember( $var['wxID'],$data['talker'] );

                $wh_acc= ['account_id'=>$account['account_id'] , 'ali_trade_no'=>$data['talker']  ] ;
                $qr = $this->getLogin()->createTablePayLogTem()->getRowByWhere(  $wh_acc );
                if( $qr ){
                    //drFun::wxQunqr( $var['wxID'],$data['talker'] );
                    //$this->getLogin()->createTablePayLogTem()->delByWhere( $wh_acc ,5);
                    $this->logs_s(  "wxQunqr>> ".$var['wxID']."\t".$data['talker']   ,'hongbao.log');
                }

                drFun::qunMemberList( $var['wxID'],$data['talker'] );
                //drFun::delQunQr()
                //$this->drExit( $data );
            }

            return [];
        }

        if(  $re['fee']==1 && $account ) {
            //$this->logs_s(  "online120>>".$var['wxID']."\t".$re['fee']."\t".$account['user_id']."\t".$var['version']."\t". $var['data']   ,'debug.log');
            $this->online120($account, $var);
            drFun::qunMemberList( $var['wxID'],$data['talker'] );
        }

        $this->V3Append($re);
        $lastID= $this->createSql()->lastID();
        $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );

        //if( $account && $re['fee']==1)  $this->online120( $account,$var );
    }

    function V3Dao130One( $var ){


        $re=[];
        $re['pay_type']=130;
        $re['account_ali_uid']= $var['acc']['ali_uid'];
        $re['ali_beizhu']='';
        $re['ali_trade_no']= $var['id'];  //支付宝订单号
        $re['ctime']= strtotime( $var['time']);
        $re['account_id']=   $var['acc']['account_id'];
        $re['fee']= drFun::yuan2fen($var['amount']);
        $re['ltime']= $re['ctime'].'000';
        $re['push_id']= $re['ctime'];

        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=> $var['id'] ] );
        $re['ali_beizhu']= $row['ali_beizhu']?$row['ali_beizhu']:'';

        $hongbao=['type'=>1, 'hb_id'=> $var['id'],'ctime'=> $re['ctime'],'beizhu'=> $re['ali_beizhu'], 'account_id'=>   $var['acc']['account_id'] ];
        $hongbao['chatroom']= $row['ali_uid'];
        $hongbao['fee']= $re['fee'];
        $hongbao['user_id']=   $var['acc']['user_id'];

        $status= explode( '/',trim( $var['status']));
        $hongbao['r_cnt']= intval($status[0] );
        $hongbao['a_cnt']= intval($status[1] );

        //print_r( $hongbao );
        //print_r( $re );
        //$this->drExit($var);


        $cnt= $this->getLogin()->createTableQunHongBao()->getCount(['type'=>1,  'hb_id'=> $var['id'] ] );
        if( $cnt<=0)  $this->getLogin()->createTableQunHongBao()->append( $hongbao );


        $this->V3Append($re);
        $lastID= $this->createSql()->lastID();
        $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );



    }

    function online120( $account, $var){
        $this->logs_s(  "online120>>".$var['wxID']."\t".$account['user_id']."\t".$var['version']."\t". $var['data']   ,'debug.log');
        if( in_array( $account['user_id'],[4,1633] )){
            if( !in_array($var['version'],['V2.2.2'])) return ;
        }
        $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
    }

    function V3FX78( $var ){
        $re['pay_type']=78;
        $re['account_ali_uid']= $var['dingID'] ;

        $data = json_decode($var['data'],true );

        if( substr($data['amount'],0,1)!='+' ) return [];

        $row =  $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=>$data['outBizNo']] );

        $re['ali_beizhu']= $row['ali_beizhu']?$row['ali_beizhu']:$data['outBizNo'];

        $re['ali_trade_no']= $data['alipayOrderId'];  //支付宝订单号
        $re['ali_account']= $data['targetAlipayAccountLogonId'];//真实付款人姓名
        $re['buyer']= $data['targetAlipayAccountName'];//真实付款人姓名
        $re['ali_uid']= $data['targetUid'];//付款人uid
        $re['ltime']= $data['finishTime'];
        $re['ctime']= floor(  $re['ltime'] /1000 );
        $re['push_id']=   $data['orderNo'];
        $re['fee']= drFun::yuan2fen($data['amount']);
        $re['opt_value']= $data;


        if( $row && $data['outBizNo']  ) $this->getLogin()->createTablePayLogTem()->updateByWhere( ['ali_trade_no'=>$data['outBizNo'] ] ,['type'=>77]);

        /*
        print_r( $re );
        print_r( $data );
        $this->drExit( $var );
        */

        $this->V3Append($re);
        $re['pay_log_id']= $this->createSql()->lastID();

        return $re ;
    }

    function V3Dao130( $var ){
        $rBill= drFun::json_decode($var['rBill']);

        $account= $this->getLogin()->createQrPay()->getAccountByID( $var['aid']);

        $fa=  $rBill['fa'];
        foreach( $fa['id'] as $k=>$v ){
            $hongbao=['id'=> $v, 'time'=>$fa['time'][$k] ];
            $hongbao['amount']= $fa['amount'][$k];
            $hongbao['status']= $fa['status'][$k];
            $hongbao['acc']= $account;
            try {
                $this->V3Dao130One($hongbao);
            }catch (drException $ex ){

            }
        }
        //print_r( $rBill );
        //$this->drExit( $var );
    }

    function V3FX38( $var ){
        //print_r($var );

        $data= json_decode($var['data'],true );
        //$this->drExit($data );
        $re['pay_type']=38;
        $re['account_ali_uid']= $var['dingID'] ;
        $re['ali_account']= $re['ali_uid']  = $data['cid'];
        $re['ali_beizhu']= $var['remark'];

        $re['ali_trade_no']= $var['tradeNo'];  //支付宝订单号
        $re['fee']= drFun::yuan2fen($var['money']);
        $re['ltime']= $data['createTime'];
        $re['ctime']= floor(  $re['ltime'] /1000 );
        $re['push_id']= $var['redId'];

        //$this->drExit($re );
        return $re ;
    }

    function V3FX36($data){
        $re['pay_type']=36;
        $re['account_ali_uid']= drFun::cut( $data, 'userId=',',') ; //收款人id
        $data= strtr( $data, ['\\'=>'','"'=>''] );

        $buyer = drFun::cut($data,'付款方：,content:','}');

        if( !$buyer) $buyer = drFun::cut($data,'付款人：,content:','}');

        $buyer_arr = explode(" ", $buyer);
        $re['buyer']=$buyer_arr[0]; //付款人姓名
        $re['ali_account']= $buyer_arr[1];;//支付宝登录账号，联系方式

        $re['ali_trade_no']=  drFun::cut($data,'tradeNO=','&');  //支付宝订单号
        $re['fee']= drFun::yuan2fen( drFun::cut($data,'money:',',') );// ceil(  drFun::cut($data,'money:',',') *100 );
        $re['ltime']= drFun::cut( $data,'mct:', ',');
        $re['ctime']= floor(  $re['ltime'] /1000 );
        $re['push_id']= drFun::cut($data,', id=',',');
        $re['ali_beizhu']= drFun::cut($data,':收款理由：,content:','}');

        $re['opt_value']['t']= date("Y-m-d H:i:s");

        $row = $this->getLogin()->createTablePayLogTem()->getRowByWhere(['ali_trade_no'=>$re['ali_trade_no']]);
        $re['ali_uid']= $row?$row['ali_uid'] :'1';
        if( $row['ali_beizhu'])  $re['ali_beizhu'] = $row['ali_beizhu'];
        return $re ;
/*
        print_r( $re );
        echo "\n\n\n<br>";
        echo $data;
        echo "\n\n\n<br>";
        $this->drExit( $data );
*/
    }

    function V3FX31( $data ){
        $re['pay_type']=31;
        $re['account_ali_uid']= drFun::cut( $data, 'userId=',',') ; //收款人id

        $data= strtr( $data, ['\\'=>'','"'=>''] );

        $buyer = drFun::cut($data,'付款方：,content:','}');
        $buyer_arr = explode(" ", $buyer);
        $re['buyer']=$buyer_arr[0]; //付款人姓名
        $re['ali_account']= $buyer_arr[1];;//支付宝登录账号，联系方式


        if( !$re['buyer']    ) $this->throw_exception( "付款人信息错误！",20190101004 );
        //$this->drExit($buyer);



        //$re['ali_uid']= $arr['plArr']['payerUserId'];// 付款人的ID 需要下一个ID获取到

        $re['ali_trade_no']=  drFun::cut($data,'tradeNO=','&');  //支付宝订单号
        $re['fee']= ceil(  drFun::cut($data,'money:',',') *100 );
        $re['ltime']= drFun::cut( $data,'mct:', ',');
        $re['ctime']= floor(  $re['ltime'] /1000 );
        $re['push_id']= drFun::cut($data,', id=',',');
        $re['ali_beizhu']= drFun::cut($data,'注：,content:','}');

        $key = 'U'.  $re['ali_trade_no'] ;
        $cache = new cache();
        $re['ali_uid']=  $cache->getRedis()->get($key );
        //,'t'=>

        $re['opt_value']['t']= date("Y-m-d H:i:s");

        return $re ;
    }

    function V3FX32( $data, $type=32 ){
        $re['pay_type']=32;
        $re['account_ali_uid']= drFun::cut( $data, 'userId=',',') ; //收款人id

        //echo $data."<br>\n\n<br>";
        $msgData= drFun::cut($data,'msgData=[','], p');

        $arr = json_decode( $msgData,true );
        $arr['plArr']= json_decode($arr['pl']  ,true);

        $re['buyer']= $arr['plArr']['payerUserName'];//付款人姓名
        $re['ali_uid']= $arr['plArr']['payerUserId'];//付款人 uid
        $re['ali_trade_no']= $arr['plArr']['transferNo']; //支付宝订单号
        $re['ali_account']= $arr['plArr']['payerLoginId'];//支付宝登录账号，联系方式
        $re['fee']= drFun::yuan2fen(  $arr['plArr']['amount']  );//ceil( 100*  $arr['plArr']['amount']);
        $re['ctime']= floor( $arr['mct']/1000 );
        $re['ltime']= $arr['mct'];

        $re['opt_value']['t']= date("Y-m-d H:i:s");

        if( $type==132 ) return $re ;


        $where2=['ali_trade_no'=> $re['ali_trade_no'] ];
        $row  = $this->createSql()->select("pay_log" ,$where2 )->getRow();
        if(  $row ){
            $this->createSql()->update("pay_log", ['ali_uid'=> $re['ali_uid'],'ali_account'=> $re['ali_account']  ] , ['id'=> $row['id']] )->query();
            $re['pay_log_id'] = $row['id'];
        }else {
            $this->V3Append($re);
            $re['pay_log_id'] = $this->createSql()->lastID();
        }
        return $re;
    }

    function V3GetType( $var ){



        $ali_uid= drFun::cut(  $var['data'] , 'userId=',',') ;
        if( $var['userId']) $ali_uid=  $var['userId'];

        if( $ali_uid ) {
            $acc= $this->getLogin()->createQrPay()->getAccountByAliUid( $ali_uid );

            if( $acc['type']!=90 ) $this->V3UpClientTimeByAliUid( $ali_uid );
        }


        if('wb.bill'==$var['cmd']) return 'bill130';
        if('weibo.refresh.bill'==$var['cmd']) return 'dao130';


        if( 'v3.dingding.CreateCny'==$var['cls'] ) return 't38';
        if( 'v3.taobao.create'==$var['cls'] ) return 't39';
        if( 'v3.dingding.OpenCny'==$var['cls'] ) return  38;
        if( 'v3.taobao.pickHongBao'==$var['cls'] ) return  39;
        if( 'v3.uniPay.order.detail'==$var['cls'] ) return  60;
        if( 'v3.uniPay.order.list'==$var['cls'] ) return  61;
        if( 'v3.uniPay.tongzhi'==$var['cls'] ) return  63;
        if( 'com.pingan.bill'==$var['cls'] ) return  65;

        if( 'v3.uniPay.bigData.detail'==$var['cls'] ) return  66;

        if( 'v3.uniPay.bigData.list'==$var['cls'] ) return  67;

        if( 'v3.uniPay.qr'==$var['cls'] ) return  't60';
        if( 'com.pingan.qr'==$var['cls'] ) return  100065;
        if( 'com.b2alipay.qr'==$var['cls'] ) return  't90';
        if( 'com.b2alipay.bill'==$var['cls'] ) return   90;
        if( 'org.myapp.wx.bill.receive'==$var['cls'] ) {

            if(strpos( $var['content'] ,'店员消息')) return 24;
            if(strpos( $var['content'] ,'手机号收款到账通知')) return 28;

            return  22;
        }
        if( 'org.myapp.wx.qrcode'==$var['cls'] ) return  't22';
        if( 'org.myapp.wx.qun.qr'==$var['cls'] ) return  'q120';
        if( 'org.myapp.wx.qun.join'==$var['cls'] ) return  'j120';
        if( 'org.myapp.wx.qun.memberlist'==$var['cls'] ) return  'm120';
        if( 'org.myapp.wx.hongbao.pick'==$var['cls'] ) return  120;

        if( 'myapp.v13.ReceiveCrowdTask'==$var['cls'] ){
            if( isset($var['arg'])){
                $arg= json_decode($var['arg'], true  );
                if($arg['scode'] && $arg['id'] ) return 351;//
            }
            return 35;
        }
        if( 'myapp.v13.createBill'==$var['cls'] ){

            return 't36';
        }
        if('v3.dingding.DDHelper'==$var['cls'] ){
            //$this->drExit($var );
            $arg= json_decode( $var['arg'],true);
            if( $arg['mUri']=='/r/Adaptor/IDLGroupBill/payGroupBillV2') return 't78';
            if( $arg['mUri']=='/r/Adaptor/WalletBill/queryBillDetail') return  78 ;
            //$this->drExit( $arg );
        }
        if( 'myapp.v13.activeShou'==$var['cls'] ){
            return 't37';
        }

        if( $var['cls']=='com.alipay.android.phone.messageboxstatic.biz.sync.d'   && strpos( $var['data'],'余额充值' ) ) {
            return 91;
        }
        if( $var['cls']=='com.alipay.android.phone.messageboxstatic.biz.dao.TradeDao'   && strpos( $var['data'],'余额充值' ) ) {
            return 92;
        }

        if( $var['cls']=='com.alipay.android.phone.messageboxstatic.biz.sync.d' &&  strpos( $var['data'],'到账' )  && strpos( $var['data'],'tradeNO' ) && strpos( $var['data'],'收款理由' ) ) {
            return 36;
        }

        if( $var['cls']=='com.alipay.android.phone.messageboxstatic.biz.sync.d'   && strpos( $var['data'],'商家服务·店员通' ) ) {
            return 303;
        }

        if( $var['cls']=='myapp.v13.getAliBillList'  ) {
            $arg= json_decode( $var['arg'] ,true );
            if( $arg['from']=='bu90' ) return 93;
            if( $acc['type']==90 ) return 93;
            if( $arg['from']=='商家服务'   ) return 301;
            //return 301;
        }

        //31 转账 32扫码付款  131为转账送过来的uid 33来之账单
        if( $var['cls']=='com.alipay.android.phone.messageboxstatic.biz.sync.d' && strpos( $var['data'],'tradeNO' )) {
            return 31;
        }
        if( $var['cls']=='com.alipay.mobile.payee.ui.PayeeQRActivity' && strpos( $var['data'],'transferNo' )){

            $msgData= drFun::cut($var['data'],'msgData=[','], p');
            $arr = json_decode( $msgData,true );
            $arr['plArr']= json_decode($arr['pl']  ,true);

            if( $arr['plArr']['state']==='1' ){ //一定要状态等1  状态等2 为退款
                //var_dump( $arr );
                //$this->drExit( $arr );
                return 32;
            }
            return 132;
        }
        if($var['cls']=='com.alipay.android.phone.messageboxstatic.biz.dao.TradeDao' &&  strpos( $var['data'],'到账' ) ){ //
            return 3200;
        }
        if( $var['cls']=='com.alipay.mobile.payee.ui.PayeeQRActivity' ){
            return 132;
        }
        if( $var['cls']=='com.alipay.mobile.socialchatsdk.chat.data.ChatDataSyncCallback' ){
            $pl = drFun::cut( $var['data'],'"pl":"','"');
            $str = base64_decode($pl );
            if( strpos($str,'RANSFER') ){
                return 131;
            }
        }
        if($ali_uid &&  $var['cls']=='com.alipay.mobile.bill.list.ui.BillListActivity_'){
            return 33;
        }


        if( $var['cls']=='com.alipay.mobile.rome.longlinkservice.syncmodel.SyncMessage' && strpos( $var['data'],'银行卡收款通知' ) ){
            return 41;

        }
        if( 'myapp.error.klcheck'===$var['cls'] ){
            return 352;
        }

        if( isset($var['cmd']) && $var['cmd'] ) return  $var['cmd'] ;

        if( isset($var['cls']) && $var['cls'] ) return  $var['cls'] ;


        return 0;
    }

    function V3UpClientTimeByAliUid( $ali_uid ){
        $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $ali_uid );
        if( $account ) $this->getLogin()->createQrPay()->updateClientTime($account['account_id']  );
        return $this;
    }
    function V3Append( $re ){

        if( $re['account_id']  ){
            $account= $this->getLogin()->createQrPay()->getAccountByID($re['account_id']);
        }else{
            $account = $this->getLogin()->createQrPay()->getAccountByAliUid( $re['account_ali_uid'] );
        }
        if( !$account ) $this->throw_exception("未找到该账号", 20190101001);
        if( $re['fee']<=0 )$this->throw_exception("金额不能小于0？", 20190101002 );
        if( in_array( $re['pay_type'] ,[22,24,303,28 ])  ){
        }elseif( !$re['ali_trade_no']   )$this->throw_exception("支付宝订单号不能为空？", 20190101003 );

        $re['user_id']= $account['user_id'];
        $re['ma_user_id']= $account['ma_user_id'];
        $re['account_id']= $account['account_id'];
        $opt_value= isset( $re['opt_value'])? $re['opt_value']:'' ;
        $this->setUserId( $re['user_id'] )->append( $re['account_ali_uid'],  10 ,$opt_value , $re  );

        //


        #if(  ($re['fee']==1  ||  $re['fee']==2 ||  $re['fee']==1111||  $re['fee']==111) && !in_array($re['pay_type'],[33,120] ) && $account['online']<10  ) $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
        if( in_array($re['fee'],[1,111,1111,2,11,100] ) && !in_array($re['pay_type'],[33,120] ) && $account['online']<10  ) $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
        if( in_array( $re['pay_type'] ,[96]) && $account['online']<10 &&   in_array($re['fee'],[1100] )){
            $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
        }
        return $this ;
        //$log->append( $pay['id'] ,$opt_type ,$pay,['ltime'=>$pay['postTime'],'fee'=>$fee,'account_id'=>$account['account_id'],'pay_type'=> $pay_type  ,'ip'=>drFun::getIP()  ]);

    }

    function V3Append41( $re ,$weihao ){
        $account = $this->getLogin()->createQrPay()->getAccountByAliUidAndWeihao( $re['account_ali_uid'],$weihao );
        //$this->drExit($account );
        if( !$account ) $this->throw_exception("未找到该账号", 20190101001);
        if( $re['fee']<=0 )$this->throw_exception("金额不能小于0？", 20190101002 );

        $re['user_id']= $account['user_id'];
        $re['account_id']= $account['account_id'];
        $opt_value= isset( $re['opt_value'])? $re['opt_value']:'' ;
        //$this->drExit( $re );
        $this->setUserId( $re['user_id'] )->append( $re['account_ali_uid'],  10 ,$opt_value , $re  );

        $lastID= $this->createSql()->lastID();

        if(  $re['fee']==1  ||  $re['fee']==1111  ) $this->getLogin()->createQrPay()->modifyAccount( $account['account_id'] , ['online'=> 1 ] );
        else{
            $this->getLogin()->createQrPay()->payMatchByLogID( $lastID );
        }
        return $this ;

    }





}