<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/25
 * Time: 0:58
 */

namespace model;


class vip extends model
{
    private $mb_bill_last_id;
    function getTypeUserMa( $type='all'){
        $tall=[0=>'资料待完善',1=>'待审核',10=>'通过',-10=>'未通过',-11=>'禁用',-20=>'账目异常' ,-100=>'删除'];
        if($type=='all') return $tall;

        if( !isset( $tall[ $type]) ) $this->throw_exception("该状态不存在！",9052501);

        return  $tall[ $type];
    }

    /**
     * 获取角色
     * @param string $type
     * @return array|mixed
     * @throws drException
     */
    function getTypeRole($type='all'){
        $tall=[1=>'码商',11=>'服务商',21=>'商户代理']; //21 商家代理
        if( $type=='all') return $tall;
        if( !isset( $tall[$type])) $this->throw_exception("该类型不存在",2020041401);

        return $tall[$type];

    }

    function getTypeBill($type='all'){
        return $this->getTypeCW($type);
    }

    /**
     * 操作类型、方向
     * @param string $type
     * @return array
     * @throws drException
     */
    function getTypeCW($type='all'){
        $tall=[];
        $tall[40]= ['n'=>'充值待审核','op'=>0,'do'=>'create','can'=>[41,42] ]; #创建
        $tall[41]= ['n'=>'充值到账','op'=>1,'do'=>'update','can_from'=>[40] ]; #更改状态 40=>41
        $tall[42]= ['n'=>'充值驳回','op'=>0,'do'=>'update','can_from'=>[40] ]; #更改状态 40=>42

        $tall[45]= ['n'=>'提现待审核','op'=>-1,'do'=>'create','can'=>[46,47]]; #创建
        $tall[46]= ['n'=>'提现驳回','op'=>0,'do'=>'update','can_from'=>[45] ]; #更改状态 45=>46
        $tall[47]= ['n'=>'提现汇出','op'=>-1,'do'=>'update','can_from'=>[45] ]; #更改状态 45=>47

        $tall[10]= ['n'=>'奖励','op'=>1,'do'=>'create']; #抢单成功后得到 创建

        $tall[11]= ['n'=>'抢单锁定','op'=>-1,'do'=>'create','can'=>[12,13,51]]; #抢单 创建
        $tall[12]= ['n'=>'抢单成功','op'=>-1,'do'=>'update','can_from'=>[11] ]; #抢单 更改状态  11=>12
        $tall[13]= ['n'=>'抢单失败','op'=>0 ,'do'=>'update','can_from'=>[11] ];  #抢单 更改状态  11=>13 都是 通一个id ,'can'=>[12]

        $tall[50]= ['n'=>'抢单补单.过','op'=>-1,'do'=>'create','no_check'=>true ]; # 补单无奖励 创建 过期
        $tall[51]= ['n'=>'抢单补单','op'=>-1,'do'=>'update' ,'no_check'=>true]; # 补单无奖励 创建 未过期

        $tall[55]= ['n'=>'错单扣款','op'=>-1,'do'=>'create','no_check'=>true ]; # 错单

        /*
        $tall[21]= ['n'=>'错单冻结','op'=>-1,'do'=>'create']; #补单 创建
        $tall[22]= ['n'=>'错单解冻','op'=>1,'do'=>'create'];  #补单 创建
        */
        $tall[30]= ['n'=>'分润','op'=>1,'do'=>'create']; //三级代理
        $tall[31]= ['n'=>'撤销','op'=>-1,'do'=>'create']; //三级代理

        $tall[61]= ['n'=>'拨分.转入','op'=>1,'do'=>'create'];  //转账 转入
        $tall[62]= ['n'=>'拨分.汇出','op'=>-1,'do'=>'create']; //转账 汇出


        $tall[63]= ['n'=>'回分.转入','op'=>1,'do'=>'create'];  //回分 转入
        $tall[64]= ['n'=>'回分.汇出','op'=>-1,'do'=>'create']; //回分 汇出



        $tall[71]= ['n'=>'资金冻结','op'=>-1,'do'=>'create','can'=>[72]]; #冻结
        $tall[72]= ['n'=>'资金解冻','op'=>0,'do'=>'update','can_from'=>[71] ]; #冻结 更改状态  71=>72

        $tall[81]= ['n'=>'奖励.夜间','op'=>1,'do'=>'create'];  //转账 转入

        //针对会员.充值 (资金 会员=>服务商)
        #$tall[140]=['n'=>'服.充值.下单','op'=>0,'do'=>'create' ]; #创建 会员
        $tall[141]= ['n'=>'服.充值.到账','op'=>1,'do'=>'update' ]; #更改状态 服务商 ,'can_from'=>[143]
        $tall[142]= ['n'=>'服.充值.驳回','op'=>0,'do'=>'update' ,'can'=>[146] ]; #更改状态 服务商 ,'can_from'=>[143]
        $tall[143]= ['n'=>'服.充值.已汇款','op'=>0,'do'=>'create'  ,'can'=>[141,142,144]  ]; # 会员
        $tall[144]= ['n'=>'服.充值.取消','op'=>0,'do'=>'update'  ]; # 会员 ,'can_from'=>[140,143]
        #$tall[145]= ['n'=>'服.充值.超时','op'=>0,'do'=>'update','can_from'=>[140]  ]; # 系统
        $tall[146]= ['n'=>'服.充值.到账.管理','op'=>1,'do'=>'update'  ]; # 管理员 ,'can_from'=>[142]
        //$tall[147]= ['n'=>'服.充值.驳回.管理','op'=>0,'do'=>'update','can_from'=>[141]  ]; # 管理员

        //针对服务商.充值  (资金 会员=>服务商 )
        #$tall[240]=['n'=>'会.充值.下单','op'=>-1,'do'=>'create'  ]; #创建 会员
        $tall[241]= ['n'=>'上分.到账','op'=>-1,'do'=>'update' ]; #更改状态 服务商,'can_from'=>[243]
        $tall[242]= ['n'=>'上分.驳回','op'=>0,'do'=>'update' ,'can'=>[246] ]; #更改状态 服务商 ,'can_from'=>[243]
        $tall[243]= ['n'=>'上分.已汇款','op'=>-1,'do'=>'create' ,'can'=>[241,242,244]  ]; # 会员
        $tall[244]= ['n'=>'上分.取消','op'=>0,'do'=>'update'  ]; # 会员 ,'can_from'=>[240,243]
        #$tall[245]= ['n'=>'会.充值.超时','op'=>0,'do'=>'update','can_from'=>[240]  ]; # 系统
        $tall[246]= ['n'=>'上分.到账.管理','op'=>-1,'do'=>'update'  ]; # 管理员 ,'can_from'=>[242]

        //针对服务商.下发 (资金 服务商=>商户 )
        $tall[260]=['n'=>'下发.待审核','op'=>0,'do'=>'create','can'=>[262,261]  ]; #创建 服务商
        $tall[261]=['n'=>'下发.成功','op'=>1,'do'=>'update', 'can_from'=>[260] ]; # 管理员
        $tall[262]=['n'=>'下发.驳回','op'=>0,'do'=>'update'  , 'can_from'=>[260]]; # 管理员
        #$tall[263]=['n'=>'下发.已汇款','op'=>0,'do'=>'update' , 'can_from'=>[260] ]; #创建 服务商

        //针对会员.充值 (资金 会员=>商户 )
        $tall[270]=['n'=>'充值V2.待审核','op'=>0,'do'=>'create' ,'can'=>[272,271] ]; # 会员
        $tall[271]=['n'=>'充值V2.成功','op'=>1,'do'=>'update', 'can_from'=>[270] ]; # 管理员
        $tall[272]=['n'=>'充值V2.驳回','op'=>0,'do'=>'update'  , 'can_from'=>[270]]; # 管理员
        #$tall[273]=['n'=>'充值.已汇款','op'=>0,'do'=>'update' , 'can_from'=>[270] ]; #创建 会员

        //下发 或者充值V2 流程
        //A不可抢 管理员 转给服务商 B可抢
        //B可抢 服务商或码商 抢单 C已抢
        //C已抢 服务商或码商 汇款 D待审核
        //D待审核 管理员审核 通过 E完成（服务商或码商加余额 加酬劳）
        //D待审核  管理员审核 驳回 状态是到 A不可抢(还是到B可抢状态 需要商量)


        $tall[230]= ['n'=>'上分.酬劳','op'=>1,'do'=>'create']; //三级代理
        $tall[231]= ['n'=>'下发.酬劳','op'=>1,'do'=>'create']; //
        $tall[232]= ['n'=>'充值V2.酬劳','op'=>1,'do'=>'create']; //

        if( $type=='all') return $tall;
        if( !isset( $tall[$type]))  $this->throw_exception("该错误操作类型不存在", 9052502);
        return $tall[$type] ;

    }

    function getMaUser($user_id){
        $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $user_id);
        if(!$ma) $this->throw_exception("改用户不是码商", 9052506);
        return $ma;
    }


    /**
     * $opt_type
     * 3 我已汇款   会员
     * 4 取消     会员
     * 1 到账     服务商
     * 2 驳回     服务商
     * 6 到账.管理     管理员
     * @param $fw_id 服务ID
     * @param $opt_type 操作类型
     * @param $opt_user_id 操作用户
     * @param array$opt 操作用户
     * @return $this
     * @throws drException
     */
    function chongzhiFromFw( $fw_id, $opt_type,$opt_user_id, $opt=[]){

        $row = $this->getLogin()->createTableMaFuwu()->getRowByKey( $fw_id);

        if( !$row) $this->throw_exception( "编号不存在",20041503);

        if( $row['opt_type']==5) $this->throw_exception( "订单已经超时",20041607);

        drFun::decodeOptValue( $row);

        $this->checkCzRole( $row, $opt_type,$opt_user_id);

        if( $row['type']==2) return $this->chongzhiFromFwWithSystem( $row,$opt_type,$opt_user_id, $opt );

        if($row['type']!=1 ) $this->throw_exception( "该类型不存在",20041504);



        $up=['opt_type'=> $opt_type,'utime'=> time()];

        //if( $opt_type==4 && ($row['ma_bill_id']) )

        if( $opt_type==3){
            if( $row['ma_bill_id'] || $row['opt_id']) $this->throw_exception( "已经付款！",20041507);
            $bank=['c_id'=> $row['c_id'],'c_name'=>$row['c_name'],'c_add'=>$row['c_add'],'c_bank'=>$row['c_bank']];
            $opt_value=  $row['opt_value'];
            $opt_value['bank']=  $bank ;
            $opt_value['price']=  $row['realprice'] ;
            $opt_value['fw_id']=  $fw_id ;

            $this->maBillCreate( 243, $row['ma_user_id'],$row['realprice'],'付款人:'. $row['opt_value']['beizhu'],['opt_value'=>$opt_value] );
            $up['ma_bill_id']= $this->getMBillLastID() ;
            $this->maBillCreate( 143, $row['user_id'],$row['realprice'],'付款人:'. $row['opt_value']['beizhu'],['opt_value'=>$opt_value] );
            $up['opt_id']= $this->getMBillLastID();
        }elseif( $row['ma_bill_id'] &&   $row['opt_id'] ){
            $this->maBillUpdate( $row['ma_bill_id'],240+$opt_type,['no_check'=>1] );
            $this->maBillUpdate( $row['opt_id'],140+$opt_type,['no_check'=>1] );
        }elseif( $opt_type!=4){
            $this->throw_exception("BillId记录不存在",20041508 );
        }

        if( in_array( $opt_type,[1,6])){
            $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $row['ma_user_id'] );
            $price = intval($row['realprice']*$ma['fee']/10000);
            if( $price>0 ){
                $this->maBillCreate( 230, $row['ma_user_id'],$price,'FW'.$fw_id."t".$row['opt_id']  );
            }


        }

        $this->getLogin()->createTableMaFuwu()->updateByKey( $fw_id, $up);

        return $this;
    }

    function chongzhiFromFwWithSystem($row,$opt_type,$opt_user_id, $opt=[]){
        $fw_id= $row['fw_id'];
        $up=['opt_type'=> $opt_type,'utime'=> time()];

        if( $opt_type==3 ){
            if( $row['ma_bill_id'] || $row['opt_id']) $this->throw_exception( "已经付款！",2020041507);
            $bank=['c_id'=> $row['c_id'],'c_name'=>$row['c_name'],'c_add'=>$row['c_add'],'c_bank'=>$row['c_bank']];
            $opt_value=  $row['opt_value'];
            $opt_value['bank']=  $bank ;
            $opt_value['price']=  $row['realprice'] ;
            $opt_value['fw_id']=  $fw_id ;

            $this->maBillCreate( 40, $row['user_id'],$row['realprice'],'付款人:'. $row['opt_value']['beizhu'],['opt_value'=>$opt_value] );
            $up['opt_id']= $this->getMBillLastID();

        }elseif ( $opt_type==4 && $row['opt_id'] && $row['opt_type']==3 ){
            //$this->throw_exception("马上去做 驳回");
            $this->maBillUpdate( $row['opt_id'],42);
        }elseif( $opt_type==4 &&  $row['opt_type']==0 ){

        }else{
            $this->throw_exception("不可操作"   ,20041601);
        }
        $this->getLogin()->createTableMaFuwu()->updateByKey( $fw_id, $up);
        return $this;
    }

    function updateMaBillImgById( $id, $img){

        $row = $this->getLogin()->createTableMaBill()->getRowByKey( $id );

        $opt_value = drFun::json_decode( $row['opt_value']);
        $opt_value['img']= $img;

        $this->getLogin()->createTableMaBill()->updateByKey($id, ['opt_value'=>drFun::json_encode( $opt_value)] );
        return $this;
    }

    function getFwOptType( $type='all'){

        $re=[0=>'待付款',1=>'到账',2=>'驳回',3=>'已付款待审核',4=>'主动取消',5=>'超时取消',6=>'到账.管理'];
        if( $type=='all') return $re ;
        if( !isset( $re[$type])) $this->throw_exception("类型不存在",2020041506);
        return $re[$type];
    }

    function checkCzRole( $row, $opt_type,$opt_user_id){
        switch ($opt_type){
            case 1:
            case 2:
                if($row['ma_user_id']== $opt_user_id ) return true;
                break;
            case 3:
            case 4:
                if($row['user_id']== $opt_user_id ) return true;
                break;
            case 6:
                if($row['c_user_id']== $opt_user_id ) return true;
                break;
        }

        $this->throw_exception("非法操作", 2020041505);
        return false;
    }




    /**
     *
     * 银行卡充值
     * @param $where
     * @return array
     */
    function getCZTongji( $where, $opt=[] ){

        $tall= $this->getLogin()->createTableMaBill()->getAll( $where,[],[0,10000],['realprice','opt_value']);

        //$this->drExit($tall);
        //return [];
        $re=[];
        if(isset($opt['table']['list']) && count( $opt['table']['list'])>0 ){
            foreach( $opt['table']['list'] as $v){
                $c_id= $v['c_id'];
                $v['cnt']=0;
                $v['realprice']=0;
                $re[$c_id]= $v ;
            }
        }
        foreach( $tall as $v){
            $opt2= drFun::json_decode( $v['opt_value']);
            $bank= $opt2['bank'];
            $c_id= trim($bank['c_id']);
            if( isset( $re[$c_id]) ){
                $re[$c_id]['realprice']+= $v['realprice'];
                $re[$c_id]['cnt']++;
            }elseif( !isset( $opt['table']) ){
                $re[$c_id]= $bank;
                $re[$c_id]['realprice'] = $v['realprice'];
                $re[$c_id]['cnt']=1;
            }
        }

        if( !$re) return [];

        $fun=function ($a,$b){
            return $a['realprice']>$b['realprice'];
        };

        usort( $re , $fun);
        //$this->assign('opt', $opt);
        return $re;
    }

    function checkMaAmountHealth( $user_id){
        $ma= $this->getMaUser( $user_id);

        $sql="select sum(realprice) as amount,count(*) as cnt from ma_bill where ma_user_id='".$user_id."' ";
        $row= $this->createSql($sql)->getRow();

        $dt= $ma['amount']-$row['amount'] ;
        $row['dt']= $dt ;
        $row['yc']=0;
        if( abs($dt)>1 ){
            $row['yc']=1;
            $this->getLogin()->createTableUserMa()->updateByKey( $user_id,['type'=>-20 ]);
        }
        return $row;
    }

    /**
     * 建立码商订单
     * $price 单位分
     * @param $type
     * @param $ma_user_id
     * @param $price
     * @param $bei_zhu
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function maBillCreate( $type,$ma_user_id,$price,$bei_zhu,$opt=[] ){
        $this->mb_bill_last_id =0;
        $op_type= $this->getTypeCW( $type);

        if($op_type['do']!='create') $this->throw_exception("该方法不可建立", 9052503);
        $ma = $this->getMaUser($ma_user_id );

        $price = intval($price);

        if($price<=0  ) $this->throw_exception("金额不允许小于0", 9052506);

        $opt['type']=$type ;
        $opt['ma_user_id']=$ma_user_id ;
        $opt['c_user_id']=$ma['c_user_id'] ;
        $opt['price']=$price ;
        $opt['beizhu']=$bei_zhu ;
        if(is_array($opt['opt_value']))  $opt['opt_value'] =drFun::json_encode($opt['opt_value']  );
        if(! isset($opt['ctime']))  $opt['ctime']= time();

        $opt['dtime']= time();

        $opt['price']=$price ;
        $opt['realprice']= $price*$op_type['op'];

        //$this->drExit( $opt );
        $amount= $ma['amount']+$opt['realprice'] ;

        #if( $amount<0 && !$op_type['no_check']    )  $this->throw_exception("余额不足", 9052509);

        $opt['amount']= $amount;
        $opt['ip'] =drFun::getIP();

        $this->getLogin()->createTableMaBill()->append( $opt );

        $this->mb_bill_last_id = $opt['mb_id']=  $this->getLogin()->createSql()->lastID();

        //$this->getLogin()->createTableUserMa()->updateByKey( $ma_user_id, ['amount'=> $amount]);
        if( $opt['realprice']!=0 ) {
            //$this->getLogin()->createTableUserMa()->updateByKey($ma_user_id, ['+' => ['amount' => $opt['realprice']]]);
            $this->updateMaAmount(  $ma_user_id , $opt['realprice']  );
            $this->getLogin()->createTableMaBillLog()->append($opt);
        }
        return $this;

    }

    function getMBillLastID(){
        return $this->mb_bill_last_id;
    }

    function maBillUpdate($mb_id,$type,$opt=[] ){



        $row = $this->getLogin()->createTableMaBill()->getRowByKey($mb_id);
        if( !$row) $this->throw_exception( "该记录不存在！", 9052507);

        $ma = $this->getMaUser($row['ma_user_id'] );
        if(!$opt['no_check']) {
            $me_user_id = isset($opt['me_user_id']) ? $opt['me_user_id'] : $this->getLogin()->getUserId();
            if (!($me_user_id == $row['ma_user_id'] || $me_user_id == $row['c_user_id'])) $this->throw_exception("该用户不是您的记录无法操作！", 9052508);
        }

        $op_arr_old = $this->getTypeCW( $row['type']);
        $op_arr = $this->getTypeCW( $type );
        if($op_arr['do']!='update') $this->throw_exception("改方法不可修改", 9052503);

        if( !in_array($type, $op_arr_old['can'])  ) $this->throw_exception( "操作方向不允许！", 9052509);

        $var=[];

        $var['realprice']= $row['price']* $op_arr['op'];

        $dt_price =  $row['price']*($op_arr['op']- $op_arr_old['op'] );
        if($opt['beizhu']) $var['beizhu'] = $opt['beizhu'] ;
        if($opt['opt_value']) $var['opt_value'] = is_array($opt['opt_value'])? drFun::json_encode($opt['opt_value']) : $opt['opt_value'] ;

        $amount= $ma['amount']+ $dt_price ;

        //if( $amount<0  && !$op_arr['no_check']   )  $this->throw_exception("余额不足", 9052509);

        if( $dt_price!=0  ) { //自己有变动 才去更改
            $var['amount'] = $amount;
            $var['ip'] =drFun::getIP();
        }
        $var['dtime'] = time();

        $var['type']= $type;
        $this->getLogin()->createTableMaBill()->updateByKey( $mb_id, $var );
        //$this->getLogin()->createTableUserMa()->updateByKey( $row['ma_user_id'], ['amount'=> $amount]);

        if(  $dt_price!=0  ) {

            //$this->getLogin()->createTableUserMa()->updateByKey( $row['ma_user_id'], ['+'=>['amount'=>$dt_price  ] ]);
            $this->updateMaAmount( $row['ma_user_id'], $dt_price );
            foreach ($var as $k=>$v ) $row[$k]=$v ;
            $row['realprice']= $dt_price ;
            $row['ctime']= time();
            $this->getLogin()->createTableMaBillLog()->append( $row);
        }

        return $this ;
    }

    function updateMaAmount( $ma_user_id, $dt_price){
        $tb = $this->getLogin()->createTableUserMa()->getTable();
        /*if( $dt_price<0 ) $sql = "update ".$tb." set    amount=amount-".abs($dt_price )." where user_id='".$ma_user_id."' limit 1";
        else */
        $sql = "update ".$tb." set    amount=amount+".$dt_price."  where  user_id='".$ma_user_id."' limit 1";
        $this->createSql($sql)->query();
        return $this;
    }

    function bu( $trade_id ){
        $trade= $this->getLogin()->createQrPay()->getTradeByID( $trade_id );

        if( (time()-$trade['ctime']) >24*3600 ) $this->throw_exception( "已经超过24小时，请场外补单！"   ,2018081151);

        $ma_user_id = $trade['ma_user_id'];
        if( !$ma_user_id) return $this;
        $bill2 = $this->getLogin()->createTableMaBill()->getRowByWhere(['beizhu'=>$trade['trade_id'],'type'=>[50,51]  ]);

        if($bill2) $this->throw_exception("已经补单上分过了！");

        $bill= $this->getLogin()->createTableMaBill()->getRowByWhere(['beizhu'=>$trade['trade_id'],'type'=>11 ]);
        if($bill){
            $this->maBillUpdate( $bill['mb_id'],51,['no_check'=>1]);
        }else{
            $this->maBillCreate(50, $ma_user_id, $trade['realprice'], $trade['trade_id']);
        }
        $this->fenLunReal( $trade_id );
        return $this;
    }

    /**
     * 分润+鼓励
     * @param $trade_id
     * @param array $opt
     * @return $this
     * @throws
     */
    function fenLun( $trade_id ,$opt=[]){
        /*
        $trade= $this->getLogin()->createQrPay()->getTradeByID( $trade_id );
        $ma_user_id = $trade['ma_user_id'];
        if( !$ma_user_id) return $this;
        $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $ma_user_id );
        $price= intval($trade['realprice']* $ma['fee']/10000 );
        */
        $wh= ['beizhu'=>$trade_id,'type'=>11 ];
        if(  $opt['is_cha_bu']){
            $wh['type']=13;
        }
        $bill= $this->getLogin()->createTableMaBill()->getRowByWhere($wh);
        if( !$bill ) $this->throw_exception( "该记录已经删除或者已经发货");
        $this->getLogin()->createVip()->maBillUpdate( $bill['mb_id'],12 , $opt ); // $ma_user_id,$price,$trade['trade_id']

        /*
        if( $price >0 ) {
            //鼓励
            $this->getLogin()->createVip()->maBillCreate(10, $ma_user_id, $price, $trade['trade_id']);
            //分论
            $this->fenLunMather($ma, $trade);
        }
        */
        $this->fenLunReal(  $trade_id );
        return $this;
    }

    function fenLunReal( $trade_id ){

        $bill= $this->getLogin()->createTableMaBill()->getRowByWhere(['beizhu'=> $trade_id,'type'=>10 ]);
        if( $bill )  $this->throw_exception( "该记录已经做过佣金" . $trade_id, 90603001);


        $trade= $this->getLogin()->createQrPay()->getTradeByID( $trade_id );
        $ma_user_id = $trade['ma_user_id'];

        if( ! in_array($trade['user_id'],[2650,3125,3310,3305,3349,4335,4467,4468,4628,2862,4647,4761,4649, 4902,5063,5082,5093,5107,5122,5124]) ) {
            $version = $this->getLogin()->getVersionBYConsole($trade['user_id']);
            #如果是云闪付 线下结算
            if (in_array($version, [60, 120, 40, 13, 351,15])) return $this;
        }

        if( !$ma_user_id) return $this;

        $ma= $this->getLogin()->createTableUserMa()->getRowByKey( $ma_user_id );
        $price= intval($trade['realprice']* $ma['fee']/10000 );

        if( $price<=0 ) return $this;


        //鼓励
        $this->getLogin()->createVip()->maBillCreate(10, $ma_user_id, $price, $trade['trade_id']);
        //分论
        $this->fenLunMather($ma, $trade);

        return $this;
    }

    /**
     * 纯分润
     * @param $ma
     * @param $trade
     * @throws drException
     */
    function fenLunMather( $ma, $trade ){
        if( $ma['m_user_id']== $ma['c_user_id']) return ;
        $ma_mather= $this->getLogin()->createTableUserMa()->getRowByKey( $ma['m_user_id'] );
        if( $ma['fee']<=0) return ;
        //if( $ma_mather['fee']< $ma[] )
        $dt_fee= $ma_mather['fee']- $ma['fee'];
        if($dt_fee<=0) {

            //$this->fenLunMather($ma_mather, $trade );
            return ;
        }
        $price= intval($trade['realprice']* $dt_fee/10000 );
        if( $price >0 ) $this->getLogin()->createVip()->maBillCreate(30 ,  $ma['m_user_id'],$price,$trade['trade_id'] );

        $this->fenLunMather($ma_mather, $trade );
        return ;
    }


    function getVipBank( $c_user_id){
        $attr = $this->getLogin()->createUserOne($c_user_id)->getAttr();

        $re['c_id']= $attr['c_id'][0]['value'];
        $re['c_name']= $attr['c_name'][0]['value'];
        $re['c_bank']= $attr['c_bank'][0]['value'];
        $re['c_add']= $attr['c_add'][0]['value'];

        return $re ;
        //$this->drExit($attr);
    }


    function clearCanModifyMa($ma){
        $key=['realname','card_id','card_bank','card_address','tel','qq','sfz'];
        foreach($ma as $k=>$v){
            if( !in_array($k, $key)) unset($ma[$k]);
        }
        return $ma;
    }

    function wansanVip($user_id, $var){
        $var= $this->clearCanModifyMa($var);

        if(isset($var['realname'])) $var['type']=1;


        $this->getLogin()->createTableUserMa()->modifyBYKey($user_id, $var );

        $opt_value="修改：". print_r($var,true );
        $this->getLogin()->createLogGt()->append( $user_id,3, $opt_value);
        return ;
    }

    function tjBillGroup( $group_file, $where){
        $table = $this->getLogin()->createTableMaBill()->getTable();

        $sql="select  `".$group_file."`, sum(realprice) as realprice , count(*) as cnt from ". $table. " where ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;
        if( in_array(12, $where['type'])){
            //$this->assign('qdansql', $sql );
        }
        //$this->drExit($sql);
        return $this->createSql($sql)->getAllByKey( $group_file ) ;
    }

    function liveTimeByTodayTrade( $where){
        $where['type']= [1,11];
        $where['>=']['ctime'] = strtotime( date("Y-m-d") );

        $tj=  $this->getLogin()->createQrPay()->tjTradeGroup('ma_user_id',$where );
        $ma_user_id= array_keys( $tj);
        //$this->drExit($ma_user_id);
        if( !$ma_user_id ) return $this;
        $this->getLogin()->createTableUserMa()->updateByWhere(['user_id'=>$ma_user_id],['live_time'=>time()] );
        return $this;
    }

    function tj($where ,$opt=[] ){
        $where_bill = $where;
        $tj=[];
        $no_arr= $opt['no']? $opt['no']:[] ;
        $where['type']= [11];
        if( !in_array('trade_bu',$no_arr)) $tj['trade_bu'] = $this->getLogin()->createQrPay()->tjTradeGroup('ma_user_id',$where );
        $where['type']= [1];
        if( !in_array('trade',$no_arr)) $tj['trade'] = $this->getLogin()->createQrPay()->tjTradeGroup('ma_user_id',$where );

        /*
        $where_bill['type']=[41];//充值
        if( !in_array('cz',$no_arr)) $tj['cz'] = $this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );

        $where_bill['type']=[12];//抢单
        if( !in_array('qdan',$no_arr))  $tj['qdan'] = $this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );
        $where_bill['type']=[11];//抢单中
        if( !in_array('qdaning',$no_arr)) $tj['qdaning'] = $this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );

        $where_bill['type']=[50,51];//补单
        if( !in_array('bu',$no_arr))  $tj['bu'] = $this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );

        $where_bill['type']=[10];//奖励
        if( !in_array('jiang',$no_arr)) $tj['jiang'] =$this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );


        $where_bill['type']=[30];//分润
        if( !in_array('run',$no_arr)) $tj['run'] = $this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );
        */
        $cat= $this->getTjCat();

        if(isset( $where_bill['user_id'])){
            $where_bill['c_user_id']= $where_bill['user_id'];
            unset( $where_bill['user_id'] );
        }
        foreach ($cat as $k=>$v ){
            $where_bill['type']= $v['type'];
            if( !in_array($k ,$no_arr)) $tj[ $k ] =$this->getLogin()->createVip()->tjBillGroup('ma_user_id',$where_bill );
        }
        return $tj;
    }

    function getPf( $c_uid){
        if (in_array( $c_uid ,[4,356,3305,2650,3849,4335,4368,4467,4468] )) return 'wo' ;
        $host= strtolower( $_SERVER['HTTP_HOST'] );
        if( in_array( $host,['wo.atbaidu.com','wo.atbaidu.com:443'])) return 'wo';
        $this->assign('ss', $host);
        return '';
    }

    function getVersionBYCUid($c_uid ){
        $version=[];
        //$version[4]=211 ;
        //$version[4]=205 ;
        //$version[105]=205 ;
        $version_arr =[201,205, 351, 60,120 ,40,13,351 ,15,145,140,239,39];//允许搞个码的版本
        $v= $this->getLogin()->getVersionBYConsole( $c_uid );
        //if( $this->getLogin()->getVersionBYConsole( $c_uid )==205 )  return 205 ;
        if( in_array($v, $version_arr) )  return $v  ;

        if(isset($version[$c_uid])) return $version[$c_uid] ;
        return 201;
    }

    function phBang( $c_uid){
        $w2= ['type'=>  $this->getLogin()->createQrPay()->getTypeTradeSuccess(),'user_id'=>$c_uid];
        $ma_paihang =  $this->getLogin()->createQrPay()->tjTradeGroup('ma_user_id', $w2 );
        $fun= function ($a,$b){
            return $a['realprice']<$b['realprice'];
        };
        usort( $ma_paihang, $fun);
        $this->getLogin()->createUser()->merge( $ma_paihang,['key'=>['ma_user_id'] ]);
        //$this->drExit( $ma_paihang );
        return $ma_paihang;
    }

    function getTjCatShow(){
        $cat= $this->getTjCat();
        //$cat['error']=['n'=>'错单扣款', 'type'=>[55] ];
        $cat['chaoshi']=['n'=>'抢单超时', 'type'=>[13] ];

        return $cat;
    }

    function getTjCat(){
        $cat=[];
        $cat['cz']=     ['must'=>1,'n'=>'充值','type'=>[41,141,271] ];
        $cat['tx']=     ['must'=>0,'n'=>'提现.审核','type'=>[45] ];
        $cat['txok']=   ['must'=>1,'n'=>'提现.成功','type'=>[47] ];
        $cat['qdan']=   ['must'=>1,'n'=>'抢单成功','type'=>[12] ];
        $cat['qdaning']=['must'=>0,'n'=>'抢单中','type'=>[11] ];
        $cat['bu']=     ['must'=>0,'n'=>'补单','type'=>[50,51] ];
        $cat['jiang']=  ['must'=>1,'n'=>'佣金','type'=>[10] ];
        $cat['run']=    ['must'=>1,'n'=>'分润','type'=>[30] ];
        $cat['err']=    ['must'=>0,'n'=>'错单','type'=>[55] ];
        $cat['tfin']=   ['must'=>0,'n'=>'拨分.转入','type'=>[61] ];
        $cat['tfout']=  ['must'=>0,'n'=>'拨分.汇出','type'=>[62] ];

        $cat['h2fin']=   ['must'=>0,'n'=>'回分.转入','type'=>[63] ];
        $cat['h2fout']=  ['must'=>0,'n'=>'回分.汇出','type'=>[64] ];

        $cat['shangfen']=  ['must'=>0,'n'=>'上分服务','type'=>[241,243] ];
        $cat['xiafa']=  ['must'=>0,'n'=>'下发服务','type'=>[261] ];
        $cat['choulao']=  ['must'=>0,'n'=>'服务酬劳','type'=>[230,231,232] ];


        $cat['dong']=  ['must'=>0,'n'=>'冻结','type'=>[71] ];
        return $cat;
    }

    function getSign( $tr_id ,$mc='adf888' ){
        $md5= md5($tr_id . $mc);
        $md5 = substr( $md5,6, 8);
        return $md5;
    }

    function getAccountChild( $mother_uid, $key ,$opt=[]){
        if($key!= $this->getAccountKey( $mother_uid) ) $this->throw_exception("非法入境");
        $where=  ['m_user_id'=>$mother_uid ];
        $file = $opt['file']?  $opt['file']:[] ;
        $tall = $this->getLogin()->createTableUserMa()->getAll( $where ,[],[0,1000], $file);

        $uid=[];
        drFun::searchFromArray( $tall ,['user_id'],$uid  );
        $u_cnt=[];
        if($uid){
            $table = $this->getLogin()->createTableUserMa()->getTable();
            $sql ="select m_user_id, count(*) as cnt from ".$table ." where ". $this->createSql()->arr2where( ['m_user_id'=>$uid ])." group by m_user_id";

            //$this->drExit( $sql );
            $u_cnt = $this->createSql($sql)->getCol2( );

            //$this->getLogin()->createTableUserMa()->getColByWhere()
        }
        foreach ($tall as $k=>$v ){
            $tall[$k]['key']= $this->getAccountKey( $v['user_id']);
            $tall[$k]['c_cnt']= $u_cnt[$v['user_id'] ] ?  $u_cnt[$v['user_id'] ]:0  ;
        }
        return $tall ;

    }
    function getAccountKey( $user_id){
        return substr( md5("wergoodsddd". $user_id ),6,6 );
    }

    function getVersionConfig( $c_user_id ){
        $conifg=[];
        $conifg[4]=['zx'=>0,'fei'=>1 ];
        $conifg[115]=['zx'=>0,'fei'=>1 ];
        $conifg[200]=['zx'=>0,'fei'=>1 ];
        $conifg[324]=['zx'=>0,'fei'=>1 ]; //,'rg'=>1
        $conifg[606]=['zx'=>0,'fei'=>1 ]; //,'rg'=>1
        $conifg[105]=['zx'=>0,'fei'=>1 ];
        $conifg[4]=['zx'=>2,'fei'=>1 ,'rg'=>1 ]; //zx 为1允许转账 2允许转账+回流
        $conifg[356]=['zx'=>2,'fei'=>1 ,'rg'=>1 ];
        $conifg[2650]=['zx'=>2,'fei'=>1 ,'rg'=>1 ];
        $conifg[4335]=['zx'=>2,'fei'=>1 ,'rg'=>1 ];
        $conifg[4467]=['zx'=>2,'fei'=>1 ,'rg'=>1 ]; //,4467,4468
        $conifg[4468]=['zx'=>2,'fei'=>1 ,'rg'=>1 ]; //,4467,
        $conifg[3310]=['zx'=>2,'fei'=>1 ,'rg'=>1 ];
        $conifg[784]=['zx'=>0,'fei'=>1 ,'rg'=>1 ];
        if( isset( $conifg[$c_user_id]) ) return  $conifg[$c_user_id] ;

        return ['zx'=>0,'fei'=>0,'rg'=>0 ]; //允许人工
    }
    function zone($tj){
        $re=[];
        $zone_int=['北京','上海','天津','重庆','广东','山东','江苏','河南','河北','浙江','陕西','湖南','福建','云南','四川','广西','安徽','海南','江西','湖北','山西','辽宁','黑龙江','内蒙古','贵州','甘肃','青海','新疆','西藏','吉林','宁夏','台湾','澳门','香港'];
        foreach( $tj as $lo =>$v ){
            $lo= strtr( $lo,['省'=>'','市'=>'']);
            if( !in_array( $lo, $zone_int)) {
                $v['lo']=$lo='其他';

            }
            if( !isset( $re[$lo] )){
                $v['realprice']= $v['realprice']/100;
                $v['price']= $v['price']/100;
                $re[$lo]=$v;
            }
            else{
                $re[$lo]['realprice']+=$v['realprice']/100;
                $re[$lo]['price']+=$v['price']/100;
                $re[$lo]['cnt']+=$v['cnt'];
            }
        }
        return $re;
    }

    function treeMather( $ma_user_id, &$re,$opt=[] ){

        if( !$opt['arr'] ){
            $row= $this->getLogin()->createTableUserMa()->getRowByKey( $ma_user_id );
            if( !$row ) $this->throw_exception("该码商不存在",90716001 );
            $tb_user_ma  = $this->getLogin()->createTableUserMa()->getTable();

            $opt['c_user_id']= $row['c_user_id'];

            $opt['user_ma']=  $this->createSql()->select( $tb_user_ma, ['c_user_id'=>$row['c_user_id'] ] ,[0,5000])->getAllByKey( 'user_id');

            //echo  "ddd=";
            //$this->drExit( $opt );
            //$re[]=$opt['user_ma'][$ma_user_id];
        }
        $var= $opt['user_ma'][$ma_user_id];
        if( !$var ) return ;
        $re[]= $var;
        if( $var['m_user_id']== $opt['c_user_id'] ) return ;

        $this->treeMather($var['m_user_id'], $re, $opt  );

    }

    function treeMap($c_user_id ,$me_uid){
        $tb_user_ma  = $this->getLogin()->createTableUserMa()->getTable();
        $user_ma = $this->createSql()->select( $tb_user_ma, ['c_user_id'=>$c_user_id ] ,[0,5000],['m_user_id','user_id','fee','amount','realname'])->getAllByKey( 'user_id');
        if( !$user_ma ) $this->throw_exception("请先开通账号" ,90706001);
        $arr=[];
        //$this->getLogin()->createUser()->merge( $user_ma );
        foreach( $user_ma  as $v ) $arr[ $v['m_user_id']][]= $v;//['user_id'];

        if( $me_uid<=0 ) $me_uid = $c_user_id;
        $all_uid=[ $me_uid ];
        $re= $this->arrToTree( $arr,$me_uid ,$all_uid );

        $vs =  $user_ma[$me_uid]; $vs['key']=  $this->getAccountKey( $me_uid ) ;

        $this->assign('user_ma', $user_ma )->assign('all_uid', $all_uid);
        return ['tree'=> [['value'=>0,'v'=> $vs,'name'=>$me_uid == $c_user_id?"总体" :'我','children'=>$re]],'uid'=> $all_uid ];
        //print_r( $all_uid );
        //$this->drExit( $re );

        //$this->getLogin()->createUser()->merge( $user_ma );
        //$this->drExit( $user_ma );
    }

    function arrToTree($arr, $star_uid, &$all_uid ){
        $re=[];
        foreach ( $arr[$star_uid] as $v ){
            $uid= $v['user_id'];
            $all_uid[]= $uid;
            $v['key']= $this->getAccountKey( $uid );
            //$re[$uid] = is_array($arr[$uid] ) ? $this->arrToTree( $arr,$uid, $all_uid ): $uid;
            $var=['v'=>$v,'name'=> $v['realname']? $v['realname']:'U'.$uid ] ;

            if(   is_array( $arr[$uid] ) )  $var['children']= $this->arrToTree( $arr,$uid, $all_uid );
            else{
                $var['value']=2019;
            }

            $re[]= $var ;
        }
        return $re ;
    }

    function mapOpr( &$tree ,$u_var, $opt=['key'=>'realprice','opt'=>'','tokey'=>'m_realprice'] ){
        foreach( $tree as &$me_tree ){
            $uid= $me_tree['v']['user_id'];
            $new_value=  $u_var[$uid][ $opt['key'] ] ;


            if($me_tree['children'] ) $this->mapOpr($me_tree['children'], $u_var,  $opt);

            $me_tree['v'][ $opt['tokey'] ]= $new_value ;
            if($opt['opt']=='+'){
                foreach( $me_tree['children'] as $vs ){
                    $me_tree['v'][ $opt['tokey'] ]+= $vs['v'][ $opt['tokey'] ];
                }
            }
            if( $opt['tokey']=='t_realprice' &&   $me_tree['v'][ $opt['tokey'] ]>0 ){
                //; $var['label']=['color'=>'#FF0000'];
                $me_tree['label']['color']= '#FF0000';

            }

        }

        return $this;
    }

    function actMap($p, $c_user,$m_uid, $obj,$opt=[]){

        switch ($p[0]){
            case 'shou':
                $se= drFun::strToTime( $p[1] );
                if( $_GET['s_time'] )$se['s']= strtotime( $_GET['s_time'] );
                if( $_GET['e_time'] )$se['e']= strtotime( $_GET['e_time'] );
                $uid = $p[2]; $key=  $p[3];
                if( $this->getAccountKey($uid) != $key) $this->throw_exception("非法进入"  );

                $wh['type']=[1,11]  ;
                $wh['ma_user_id']= $uid ;
                $wh['>=']['ctime'] = $se['s'];
                if(isset($se['e'])) $wh['<']['ctime']= $se['e'] ;

                $trade_jd= $this->getLogin()->createQrPay()->tjTradeGroup('account_id',$wh );
                if( $trade_jd ) $this->createSql()->merge("pay_account", 'account_id' ,$trade_jd  );
                $this->assign('trade', $trade_jd );
                break;
            case 'tab':
            default:
                $se= drFun::strToTime( $p[1] );
                if( $_GET['s_time'] )$se['s']= strtotime( $_GET['s_time'] );
                if( $_GET['e_time'] )$se['e']= strtotime( $_GET['e_time'] );

                $start_time=  $se['s'];

                $re = $this->getLogin()->createVip()->treeMap( $c_user,$m_uid   );
                $wh= ['ma_user_id'=> $re['uid']];
                $wh['type']=[1,11];

                $wh['>=']['ctime'] = $start_time;
                if(isset($se['e'])) $wh['<']['ctime']= $se['e'] ;
                //$wh['>']=['ctime'=> strtotime( date("Y-m-d"))- 24*3600];

                $trade_jd= $this->getLogin()->createQrPay()->tjTradeGroup('ma_user_id',$wh );

                $u=[]; foreach ( $re['uid'] as $id) $u[$id]=$id ;
                $user = $this->getLogin()->createUser()->getUserFromUid( $u );
                $ma_user_info = $this->getLogin()->createTableUserMa()->getAllByKey('user_id',['c_user_id'=>$c_user ] );
                //$this->assign('ma_user_info', $ma_user_info );
                $this->getLogin()->createVip()->mapOpr($re['tree'], $user , ['key'=>'name','opt'=>'','tokey'=>'u_name'] );


                //die();
                //$this->drExit(  $user );
                //print_r($trade_jd );
                $this->getLogin()->createVip()->mapOpr($re['tree'], $trade_jd );
                $this->getLogin()->createVip()->mapOpr($re['tree'], $trade_jd , ['key'=>'cnt','opt'=>'','tokey'=>'m_cnt'] );

                $this->getLogin()->createVip()->mapOpr($re['tree'], $trade_jd , ['key'=>'cnt','opt'=>'+','tokey'=>'t_cnt'] );
                $this->getLogin()->createVip()->mapOpr($re['tree'], $trade_jd , ['key'=>'realprice','opt'=>'+','tokey'=>'t_realprice'] );
                $this->getLogin()->createVip()->mapOpr($re['tree'], $ma_user_info , ['key'=>'amount','opt'=>'+','tokey'=>'t_amount'] );

                //$this->drExit( $re );
                $server=[];
                $server['tab']=   ['today'=>'今日','yesterday'=>'昨日','month'=>'本月','lastmonth'=>'上月' ];
                $p[1]= $p[1]? $p[1]:'today';
                $server['p']= $p ;
                $this->assign('tree', $re['tree'][0] );
                $this->assign('server',  $server );

                $obj->tplFile="map_tree";
                break;
        }
    }


    function getQzone( $city='all'){

        $zone=['广东','山西','四川','重庆','湖北','江苏','云南','浙江','山东','辽宁','河北','湖南','广西','贵州','河南','福建','北京','安徽','上海','江西','陕西','天津','新疆','甘肃','黑龙江','内蒙古','宁夏','海南','吉林','西藏','青海',''];
        if( $city!='all'){
            if( ! in_array( $city, $zone)) $this->throw_exception($city."地区不存在！请修改",190725001 );
        }
        return $zone ;
    }

    function accQzoneTj( $acc, $opt=[]){
        $re=[];
        $zone = $this->getQzone();
        foreach($zone as $v ){
            $re[ $v ]=['cnt'=>0,'online'=>0,'canUser'=>0 ];
        }
        foreach( $acc as $v ){
            $qz= $v['lo'];
            if( !isset( $re[$qz] )) continue;
            $re[$qz]['cnt']++;
            if( in_array($v['online'] ,[1,11,4 ] ) && $v['clienttime']>=( time()-300 )){
                $re[$qz]['online']++;
                if( in_array($v['type'],[ 201,211 ,205] ) &&  $opt['ma_user'][ $v['ma_user_id'] ]['amount'] )   $re[$qz]['canUser']++;
            }
        }
        return $re ;
    }

    function tradeQzone( $where ){
        $tall = $this->getLogin()->createQrPay()->tjTradeGroup('lo',  $where  );
        if( !$tall ) return $tall;
        $total= 0;
        foreach($tall as $v  ) $total+= $v['cnt'];
        foreach( $tall as &$v){
            $v['pr']= intval(10000*$v['cnt']/$total)/100;
            //$this->drExit( $v );
        }
        //$this->drExit( $tall );
        return $tall;
    }

    function accMather( $c_user_id ){

        $where=['user_id'=>   $c_user_id  ];
        //$where['>=']['clienttime'] = time() - 60 * 30;
        $account= $this->getLogin()->createQrPay()->getAccountIDByWhere( $where , ['all'=>1 ]);
        $ma_user= $this->getLogin()->createTableUserMa()->getAllByKey('user_id', ['c_user_id'=>  $c_user_id]);

        $ma = $this->getLogin()->createVip()->accQzoneTj( $account , ['ma_user'=>$ma_user ]);
        $this->assign('acc', $ma );

        $tjlo=[];
        $wh=['user_id'=>  $c_user_id ];
        $wh['>']['ctime']=time()-24*3600 ;
        $tjlo['24h']= $this->getLogin()->createVip()->tradeQzone(  $wh  );
        $wh['>']['ctime']=time()-7*24*3600 ;
        $tjlo['week']= $this->getLogin()->createVip()->tradeQzone(  $wh  );
        $wh['>']['ctime']=time()-30*24*3600 ;
        $tjlo['30D']= $this->getLogin()->createVip()->tradeQzone(  $wh  );
        $this->assign('tjlo', $tjlo );
    }

    /**
     * 获取人工抢单的账号类型
     * 支付宝用 205  微信用 201
     * @param $c_user_id
     * @return int
     * @throws drException
     */
    function getQiangAccountTypeByCuid( $c_user_id ){
        $version= $this->getLogin()->getVersionBYConsole($c_user_id );
        if( in_array($version,[201,211] )) return 201;
        if( in_array($version,[205] )) return 205;
        return 201;
    }

    function getSignPsw($psw, $uid){
        $psw= $psw.'@ddd'.$uid;
        $psw = substr( md5($psw ),3,12);
        return $psw;
    }

    function mergMaUser( &$list,$key=  'ma_user_id'  ){
        $uid=[];

        drFun::searchFromArray( $list, [$key] ,$uid);
        //$this->drExit( $uid );
        if(!$uid) return $this;

        $uvar = $this->getLogin()->createTableUserMa()->getAllByKey('user_id',['user_id'=> array_values($uid) ] );

        //$this->drExit( $uvar );
        foreach( $list as &$var ){
            $k= $var[ $key ];
            if( isset($uvar[$k]) ) $var[$key.'_merg']= $uvar[$k];
        }
        //$this->drExit( $list );
        return $this;
    }

    //连续补单

    /**
     * @param $ma_user_id
     * @return array
     */
    function maTrdeBu( $ma_user_id ){
        $re=['cnt'=>0,'last'=>0,'ma_user_id'=>$ma_user_id];

        if( $ma_user_id<=0 ) return $re ;
        $where['ma_user_id']= $ma_user_id;
        $trade = $this->getLogin()->createTableTrade( 2)->getAll( $where, ['trade_id'=>'desc'],[0,100],['type', 'ctime','trade_id'] );

        //$this->drExit( $trade);

        $v2=[];
        foreach( $trade as $v ){
            $v2=$v;
            if( $v['type']==1) break;
            if( $v['type']==11) $re['cnt']++;
        }
        $re['last']=$v2['ctime'];

        $key= 'MaOut'. $ma_user_id;

        $timeout = $this->getLogin()->redisGet( $key );
        $re['timeout']= $timeout?$timeout:0;
        //$this->assign('tradeTest', $trade );
        return $re ;
    }

    function getPayRankByMaUid( $c_user_id, $ma_user_id){
        $r=5000;
        $list = $this->getLogin()->createTablePayRank()->getAll(['c_user_id'=>$c_user_id],['utime'=>'asc'],[0,1000] );

        //$this->drExit($list);
        foreach ( $list as $k=>$v ){
            if( $v['ma_user_id']==$ma_user_id) return $k+1;
        }

        return $r;
    }

    function getCUserID2Fw( $c_user_id){

        return intval($c_user_id)+20000000;
    }

    function getFwsUserID( $c_user_id, $price){

        $list = $this->getLogin()->createTablePayRank()->getAll(['c_user_id'=> $this->getCUserID2Fw( $c_user_id)],['utime'=>'asc'],[0,100],['ma_user_id','utime'] );
        if( !$list) return [];

        $muid=[];
        drFun::searchFromArray( $list,['ma_user_id'], $muid );
        $ma_user= $this->getLogin()->createTableUserMa()->getAllByKey('user_id',['user_id'=>$muid] );

        foreach($list as $v){
            $ma =$ma_user[$v['ma_user_id']];
            if( $price< $ma['amount']){
                //$this->drExit($ma );
                $this->getLogin()->createTablePayRank()->delByWhere( ['ma_user_id'=>$v['ma_user_id']]);
                return $ma;
            }
        }
        return [];
    }


    function tradeLog( $where, $opt=[]){
        $every = 100;
        //$file=['trade_id','merchant_id','pay_type' ,'price','realprice','order_no','ctime','pay_time','pay_log_id','type','account_id','notify_cnt','notify_success',''];
        //$mlist =  $this->getLogin()->createQrPay()->getTradeWithPage( $where,['every'=> $every ]) ;
        //$list = $mlist['list'];
        $list= $this->getLogin()->createTableTrade()->getAll( $where, ['trade_id'=>'desc'],[0,$every]);
        unset( $mlist);
        if( !$list) return [];


        $acc_id=[];
        drFun::searchFromArray( $list, ['account_id']  ,$acc_id );
        $account_all=[];
        $acc_file=['ma_user_id','account','zhifu_name','zhifu_account'];
        if( $acc_id ){

            $account_all= $this->getLogin()->createQrPay()->getAccountIDByWhere(['account_id'=> array_keys($acc_id) ] ,['all'=>1]  );
            //$this->assign('account', $account_all );
            foreach( $account_all as $k=>$v ){
                $account_all[$k]= $this->fileArr($v, $acc_file);
            }
        }

        $this->getLogin()->createQrPay()->searchBuyerFromTrade( $list );
        //$this->getLogin()->createQrPay()->searchMauserFromTrade( $list );

        $ma_uid=[];
        drFun::searchFromArray( $list,['ma_user_id'],$ma_uid );
        unset($ma_uid[0]);
        $ma_user=['user_id','realname','tel','amount'];
        if( $ma_uid ) $ma_user_all  = $this->getLogin()->createTableUserMa()->getAllByKey('user_id', ['user_id'=> array_keys( $ma_uid)] ,[],[0,1000], $ma_user);

        $pay_log=['buyer','ali_trade_no','ip'];
        foreach( $list as $k=>$v){
            unset( $list[$k]['notify_url']);
            unset( $list[$k]['return_url']);
            unset( $list[$k]['goods_name']);
            unset( $list[$k]['attach']);
            unset( $list[$k]['order_user_id']);
            unset( $list[$k]['rate']);
            unset( $list[$k]['version']);
            unset( $list[$k]['ali_uid']);
            unset( $list[$k]['qr_id']);
            //$acc= $account_all[ $v['account_id']];

            //$list[$k]['account']= $this->fileArr( $acc, $acc_file );//['account'=>$acc['account'],'zhifu_name'=>$acc['zhifu_name'],'zhifu_account'=>$acc['zhifu_account']  ];

            if(isset($v['ma_user'])){
                //$list[$k]['ma_user']= $this->fileArr($v['ma_user'], $ma_user );
            }
            if( isset( $v['pay_log'])){
                $list[$k]['pay_log']= $this->fileArr($v['pay_log'], $pay_log );
            }
        }


        return ['list'=>$list,'account'=>$account_all,'ma_user'=>$ma_user_all ];

    }

    function fileArr( $arr ,$file){
        $re=[];
        foreach( $file as $v){
            $re[ $v]= $arr[$v];
        }
        return $re ;
    }

    function setAnquan( $c_user_id){
        $rank= rand(1000,9999);
        $this->getLogin()->redisSet( 'AQ_'.$c_user_id, $rank);
        $this->getLogin()->createQrPay()->toTelegram( $c_user_id,"【设置安全码】\n当前安全码：". $rank );
        return $this;
    }

    function getAnQuan( $c_user_id ){
        if( !$this->getLogin()->isAnquanMa($c_user_id)) return 0;
        $rank=  intval( $this->getLogin()->redisGet( 'AQ_'.$c_user_id) );
        if( $rank<=0) return -1;//$this->throw_exception('请管理员设置下安全码',20081401);
        return $rank;

    }


}