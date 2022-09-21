<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/11
 * Time: 16:19
 */

namespace model;


use model\lib\mq;

class qrpay extends model
{
    private $tb_qr='pay_qr';
    private $tb_account='pay_account';
    private $tb_bank='pay_bank';
    private $tb_trade="mc_trade";
    private $tb_merchant="merchant";
    private $tb_cookie='cookie_name';

    private $tb_finance='mc_finance';

    private $user_id=0;
    private $last_time=[];

    private $file_bank=['bank','name','bank_name','card','bank_address','cnt','ctime','user_id'];
    private $file_finance=['merchant_id','ctime','user_id','run_time','run_time_str','fee','card','card_out','back_id'];
    private $file_account=['fail_cnt_all','fail_cnt_day','fail_cnt','card_index','bank_id','account','zhifu_name','zhifu_account' ,'ctime','user_id','type','online','yuer','process','zhifu_realname','ali_uid','clienttime','ma_user_id','price_max','lo'];
    private $file_trade=['yue','rate','lo','lc','version','ma_user_id','user_id','merchant_id','pay_type','price','goods_name','notify_url','order_no','order_user_id','attach','realprice','ctime','qr_id','account_id'];

    function __construct( $user_id)
    {
        parent::__construct(false);
        $this->setUserID( $user_id);
    }

    function setUserID( $user_id ){
        $this->user_id=$user_id;
    }

    function getUserID(){
        return $this->user_id;
    }


    function getAccountByUid( $account, $uid ){
        if( ! $account ||  !$uid )$this->throw_exception('参数错误！', 2018081102);

        $row = $this->createSql()->select( $this->tb_account,['user_id'=> $uid,'account'=> $account ])->getRow();
        if( !$row){
            $row = $this->createSql()->select( $this->tb_account,['ma_user_id'=> $uid,'account'=> $account ])->getRow();
        }
        if( !$row ) $this->throw_exception('矮油，收款账号不存在，请填写正确收款编号！', 2018081101);
        return $row ;
    }

    function getAccountByID( $account_id ){
        if( is_array(  $account_id))
            return $this->createSql()->select( $this->tb_account, ['account_id'=>$account_id ] )->getAllByKey('account_id');

        return $this->createSql()->select( $this->tb_account, ['account_id'=>$account_id ] )->getRow();
    }
    function quchongAliUidByAccout( $account ){
        $tall = $this->createSql()->select( $this->tb_account , ['ali_uid'=> $account['ali_uid'] ])->getAll();
        if( count($tall )<=1) $this->throw_exception( "已经是唯一了！");
        $account_id =[];
        drFun::searchFromArray($tall,['account_id'],$account_id  );
        unset( $account_id[ $account['account_id']] );
        $where=['card_index'=>$account['ali_uid'] ];



        $this->update(  $this->tb_account, $where, ['card_index'=>$account['ali_uid'].'_quchong' ] );

        $this->update( $this->tb_account,['account_id'=> array_keys( $account_id)], ['ali_uid'=>$account['ali_uid'].'_quchong'  ] );

        //$this->drExit( 'db='. $this->tb_account  );
        //$where['!=']=['ma_user_id'=> ];
        //$this->drExit( $account_id );
        return $this;

    }
    function delAccountByID( $account_id ){
        $re = $this->createSql()->delete( $this->tb_account, ['account_id'=>$account_id] )->query();
        //$this->drExit( $re );
        return $this;
    }

    function getAccountIDByWhere( $where ,$opt=[]){
        if( $opt['all']){
            //return $this->createSql()->select( $this->tb_account, $where , [],[],['clienttime'=>'desc'] )->getAllByKey('account_id');
            if(  $opt['all']==1001 ) return $this->createSql()->select( $this->tb_account, $where , [],[],['account'=>'ASC'] )->getAll();
            return $this->createSql()->select( $this->tb_account, $where , [],[],['account'=>'ASC'] )->getAllByKey('account_id');
        }elseif ($opt['alldan'] ){
            return $this->createSql()->select( $this->tb_account, $where , [0,1],[],['account'=>'ASC'] )->getAllByKey('account_id');
        }elseif ($opt['qr2'] ){
            return $this->createSql()->select( $this->tb_account, $where,[],['account_id'] ,['utime'=>'ASC'] )->getCol();

        }elseif ($opt['dan_row'] ){
            return $this->createSql()->select( $this->tb_account, $where,[ ],[ ] ,['utime'=>'ASC'] )->getRow();

        }elseif ($opt['dan'] ){
            return $this->createSql()->select( $this->tb_account, $where,[0,1],['account_id'] ,['utime'=>'ASC'] )->getCol();
        }

        //$this->drExit( $this->createSql()->select( $this->tb_account, $where,[],['account_id'] )->getSQL() );
        $sql= $this->createSql()->select( $this->tb_account, $where,[],['account_id'], ['utime'=>'ASC'] )->getSQL();

        //$this->logs_s( "getAccountIDByWhere =\t".$sql. "\n"  ,'debug.log');

        return $this->createSql()->select( $this->tb_account, $where,[],['account_id'], ['utime'=>'ASC'] )->getCol();

    }

    function getMoneyConfig( $type="all",$opt=[]){
        /*
        $re=['0.01'=>1,'0.1'=>2,1=>2
        ,10=>3,20=>6,50=>6, 100=>6, 200=>8, 300=>8 ,500=>10,1000=>10,2000=>10,3000=>10];

        $re=['0.01'=>1,'0.1'=>2,1=>2
            ,10=>3,20=>3,50=>4, 100=>5, 200=>6, 300=>6 ,500=>6,1000=>6,2000=>6,3000=>6];
        */
        $re=['0.01'=>1,20=>2,50=>3, 100=>7, 200=>8 ,500=>8,1000=>8,2000=>7,3000=>7]; //, 300=>8

        $conf= [];
        foreach( $re as $k=>$cnt ){
            //$arr=[];
            $fee= intval( floatval($k) *100 );
            if( 'group'==( $opt['display'] ) ){
                for($i=0; $i<$cnt;$i++ ) $conf[$fee][$fee-$i]= $fee-$i;
            }elseif('array'==( $opt['display'] ) ){
                for($i=0; $i<$cnt;$i++ ) $conf[]= $fee-$i;
            }else{
                for($i=0; $i<$cnt;$i++ ) $conf[$fee-$i]= $fee-$i;
            }
        }
        if( 'group'==  $opt['display'] &&   $type!="all" ){
            //$this->drExit( $conf );
            if( isset( $conf[$type] ) ) return  $conf[$type];
            $this->throw_exception(($type/100)."元 金额二维码不存在！",2018081117);
        }
        return $conf;
    }

    function getMoneyConfigV2( ){

        $conf= $this->getMoneyConfig('all',['display'=>'array']);
        rsort( $conf );
        usort( $conf,function ($a,$b){ return  ($a%10==$b%10)? $a<$b :$a%10<$b%10 ; } );
        $myConf=[1999,4999,9999,19999,49999,99999,199999,299999 ,1 ,9998,19998,49998,99998,199998,299998   ,9997,19997,49997,99997,199997,299997 ,299996,199996,99996 ];
        foreach ($conf as $v ){
            if( !in_array( $v,$myConf ) && $v%100!= 0 ){
                $myConf[]=$v;
            }
        }
        $myConf[]=1998;
        $myConf[]=1997;
        $myConf[]=1996;

        return $myConf;
    }

    /**
     * @param $account_id
     * @return bool
     * @throws drException
     */
    function isMaKeYong( $account_id ,$opt=[] ){
        $account = $trade = $this->getAccountByID( $account_id );
        if(  in_array($account['type'] ,[80, 145] )  ) return true ;
        if( in_array($account['type'] ,[201,205] )  ){
        #if( in_array($account['type'] , $this->getLogin()->getTypeCanUpTimeByMaUser()  )  ){
            $conf=[10086];
            return $this->checkMakeYongByConf( $account_id, $conf,3  );
            return ;
        }

        $version = $opt['version']>0? $opt['version']  : $this->getLogin()->getVersionBYConsole( $this->getLogin()->getUserId() , $trade['type']);
        if( in_array( $account['type'],[60,65]) ){
            $version= $account['type'];
        }elseif(in_array($account['type'],[3,363,263,364] )  ){
            $version = 63;
        }




        if(24== $version || in_array($account['type'] ,[211] ) ){
            //$account = $trade ;//$this->getAccountByID($account_id);

            if( !$account['card_index'] ) $this->throw_exception("请先添加店员微信！");

            $row = $this->getAccountByAliUid( $account['card_index']);
            if( !$row ) $this->throw_exception("请先添加店员微信！");

            if( in_array($account['type'] ,[211] ) ){
                $conf=[10086];
                return $this->checkMakeYongByConf( $account_id, $conf,3  );
            }
        }

        switch ( $version){
            case 2:
            case 4:
            case 5:
            case 30:
            case 32:
            case 35:
            case 351:
            case 36:
            case 37:
            case 38:
            case 78:
            case 60:
            case 39:
            case 22:
            case 31:
            case 302:
            case 65:
            case 90:
                if(! $trade['ali_uid'] ) return false ;
                return true;
                break;
            case 1:
                $conf=[1999,4999,9999,19999,49999,99999,199999,299999 ,1];
                return $this->checkMakeYongByConf( $account_id, $conf,1 );
                break;
            case 3:
            case 13:
            case 15:
            case 23:
            case 24:
            case 301:
            case 201:
            case 205:
            case 63:
                $conf=[10086];
                return $this->checkMakeYongByConf( $account_id, $conf,3  );
                break;
            case 40:
            case 45:
                return $this->checkMakeYongV40( $account_id  );;
                break;
            default:
                return false ;
                break;
        }
    }
    function checkMakeYongV40($account_id){
        $account = $this->getAccountByID($account_id );
        return $account['zhifu_realname']  && $account['zhifu_name']  && $account['zhifu_account'] ;
    }
    function checkMakeYongByConf($account_id,  $conf ,$version ){
        $fee= $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id ] ,[0,20000],['fee','fee'])->getCol2();
        foreach ($conf as  $k ){
            if( !isset( $fee[$k])){
                return  false ;
            }
        }
        return true;
    }

    function checkMakeYong( $account_id , $opt=[]){
        if(! $this->isMaKeYong( $account_id , $opt)) $this->throw_exception("缺少必要的码  "  ,499 );
        return $this;
    }

    function getMoneyConfigYuMa( $account_id ){
        //$re=[1];
        //$conf= $this->getMoneyConfig('all',['display'=>'array']);
        $conf= $this->getMoneyConfigV2();

        $fee= $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id ] ,[0,20000],['fee','fee'])->getCol2();


        foreach ($conf as $ik=> $k ){
            if( isset( $fee[$k])) unset( $conf[$ik] );
        }
        if( !$conf )  $conf[]=  299991;
        ///return $conf ;

        return array_values( $conf ) ;
    }

    function appendQr( $account_id, $user_id, $fee, $img, $opt=[]){
        $account= $this->getAccountByID( $account_id );
        if( $account['user_id']!= $user_id ) $this->throw_exception("账号错误",2018081104);

        if( $fee<1) $this->throw_exception( '费用太小', 2018081103 );
        $tall = $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id,'fee'=>$fee],[],['qr_id'])->getCol();
        if( $tall ){
            $var = ['img'=>$img ,'ctime'=>time() ];
            if( $opt['qr_text']) $var['qr_text']=trim($opt['qr_text'] );
            $this->update( $this->tb_qr,['qr_id'=>$tall], $var );
            return $this;
        }
        $var = ['account_id'=>$account_id,'fee'=>$fee,'img'=>$img,'user_id'=>$user_id,'ctime'=>time() ,'last_time'=> (time()- rand(0,5*3600))  ];
        if( $opt['qr_text']) $var['qr_text']=trim($opt['qr_text'] );
        $this->insert( $this->tb_qr, $var);

        return $this;
    }

    function addBank( $bank ){
        if( !$bank['bank'] || !$bank['name'] || !$bank['card']) $this->throw_exception("名称、开户人、卡号不允许为空！",2018081105);
        $uid = $this->getUserID();
        if( $this->createSql()->getCount( $this->tb_bank,['user_id'=>$uid,'bank'=>$bank['bank'] ])->getOne() )
            $this->throw_exception($bank['bank']." 已经存在！",2018081106);

        if( $this->createSql()->getCount( $this->tb_bank,['card'=>$bank['card'] ])->getOne() )
            $this->throw_exception($bank['card']." 卡号已经存在！",2018081107);

        $bank['user_id']= $uid;
        $bank['ctime']= time();

        $this->insert( $this->tb_bank, $bank ,$this->file_bank );
        return $this;

    }
    function modifyBank( $bank_id ,$bank ){
        if( isset($bank['bank'])){
            $row= $this->createSql()->select( $this->tb_bank, ['user_id'=>$this->getUserID(),'bank'=>$bank['bank'] ])->getRow();
            if(  $row && $row['bank_id']!= $bank_id)  $this->throw_exception($bank['bank']." 已经存在！",2018081108);
        }
        if( $bank['card'] ){
            $row= $this->createSql()->select( $this->tb_bank, ['card'=>$bank['card'] ])->getRow();
            if(  $row && $row['bank_id']!= $bank_id)  $this->throw_exception($bank['card']." 卡号 已经存在！",2018081109);
        }
        $this->update($this->tb_bank,['bank_id'=>$bank_id],$bank ,$this->file_bank );
        return $this;
    }

    function addAccountWeibo($var, $user_id, $opt=[]){
        $cookie=$var['cookie'];
        //$this->drExit( $_POST );
        //if(!$cookie) $this->throw_exception( "请填写Cookie",19120603);

        //$arr= json_decode( $cookie,true );


        $wb = new weibo();
        $wb->setH5Cookie( $_POST['h5_cookie'] )->setWeiboAppCookie( $_POST['app_cookie'] );
        $info = $wb->checkTwoCookie()->getInfo() ;//$wb->setCookieFromJson( $cookie )->setClient('weibo')->setUserInfo()->getInfo();

        //$this->drExit($info);

        if( !$info['wb_uid'] ) $this->throw_exception( "未获取到账号信息！",19060608 );

        $var=['zhifu_account'=>$info['info']['mobile']? $info['info']['mobile'] :$info['wb_name'],'zhifu_name'=>$info['wb_name'] ];
        $var['type']=130;
        $var['user_id']= $user_id ;
        $var['ctime']= time();
        //$var['process']= $taobao['session'].'|'.$taobao['refresh_token'];
        $var['ali_uid']='weibo'. $info['wb_uid'];
        $var['bank_id']=1;
        $var['clienttime']= time();

        $row  = $this->createSql()->select( $this->tb_account,['ali_uid'=>$var['ali_uid'] ] )->getRow();
        if( $row)  {
            //$this->throw_exception("账号". $info['wb_name']." 已经存在库总！",19120607);
            //echo 'dddd';
            //$this->drExit($row);
            $this->update(  $this->tb_account,['account_id'=>$row['account_id'] ] ,['clienttime'=>  $var['clienttime']] );
            //
            $info['rz']= "更新账号". $info['wb_name'] ."成功";
            $info['acc']=  $row;
            $wh= ['account_id'=> $row['account_id'] ,'type'=>130];
            $this->getLogin()->createTablePayAccountAttr()->updateByWhere($wh , [ 'ctime'=>time() , 'attr'=> drFun::json_encode( $info )]);
            $this->upAccountCookie( $row['account_id'], $info['h5_cookie'] ,131 );
            $this->upAccountCookie( $row['account_id'], $info['app_cookie'],132 );
            return $info;
        }

        $id =  $this->createSql("select max(account_id) as account_id from " . $this->tb_account )->getOne();
        $var['account'] = "WB" . ($id+1);

        $this->insert( $this->tb_account, $var, $this->file_account);
        $last_id= $this->createSql()->lastID();

        $attr=['account_id'=> $last_id,'ctime'=>time(), 'type'=>130, 'attr'=> drFun::json_encode( $info )];
        $this->getLogin()->createTablePayAccountAttr()->append(  $attr );
        $attr=['account_id'=> $last_id,'ctime'=>time(), 'type'=>131, 'attr'=> $info['h5_cookie'] ];
        $this->getLogin()->createTablePayAccountAttr()->append(  $attr );
        $attr=['account_id'=> $last_id,'ctime'=>time(), 'type'=>132, 'attr'=> $info['app_cookie'] ];
        $this->getLogin()->createTablePayAccountAttr()->append(  $attr );

        $info['rz']= "新增账号". $info['wb_name'] ."成功";
        $info['acc']=  $var;

        //$this->drExit( $info );

        return $info;
    }

    function upAccountCookie($account_id ,$cookie ,$type=131){
        if(! $cookie )$this->throw_exception('Cookie不允许为空', 19120704 );
        $wh= ['account_id'=> $account_id ,'type'=>$type];
        $this->getLogin()->createTablePayAccountAttr()->updateByWhere($wh , [ 'ctime'=>time() , 'attr'=>  $cookie ]);
        return $this;
    }

    function addAccountBefore48($var ){

        //if( ! in_array( $this->getUserID(),[4,1185, 2333] ) ) $this->throw_exception( "增加子卡 功能暂未开放！",19121503);

        $ali_uid= intval($var['ali_uid']);
        $acc= $this->getAccountByID($ali_uid);
        if( $acc['user_id']!= $this->getUserID() ) $this->throw_exception( "非法",19121501);
        if( !in_array($acc['type'],[4,47]))$this->throw_exception( "该账号不能当主账号", 19121502);
        $this->upAccountByID( $ali_uid,['type'=>47]);
        //$this->drExit($var);
        return $this;
    }

    function addAccount( $var, $opt=[] ){
        $var['bank_id']= intval(  $var['bank_id'] );

        if( $var['bank_id']<=0) $var['bank_id']=1;

        if(! $var['account'] || ! $var['zhifu_name'] || ! $var['zhifu_account'] ||  $var['bank_id']<=0 ){
            $this->throw_exception("别名、姓名、支付账号不允许为空！",2018081110);
        }

        if( $this->createSql()->getCount( $this->tb_account,['user_id'=> $this->getUserID(),'account'=>$var['account'] ])->getOne() )
            $this->throw_exception($var['account']." 已经存在！",2018081111);


        if( $this->createSql()->getCount( $this->tb_account,[ 'user_id'=> $this->getUserID(), 'type'=>$var['type'],'zhifu_account'=>$var['zhifu_account'] ])->getOne() )
            $this->throw_exception($var['zhifu_account']." 账号已经存在！",2018081112);

        $var['user_id']= $this->getUserID();
        $var['ctime']= time();
        $this->insert( $this->tb_account, $var, $this->file_account);
        return $this;
    }



    function addAccountFromTaobao( $taobao){
        if( !$taobao['nick'] ||  !$taobao['session'] || !$taobao['refresh_token'] ||  !$taobao['user_id'] ) $this->throw_exception("参数错误！",190830004);
        $var=['zhifu_account'=>$taobao['nick'],'zhifu_name'=>$taobao['nick'] ];
        $var['type']=80;
        $var['user_id']= $this->getUserID();
        $var['ctime']= time();
        $var['process']= $taobao['session'].'|'.$taobao['refresh_token'];
        $var['ali_uid']='taobao'. $taobao['user_id'];
        $var['bank_id']=1;

        ///$cnt = $this->createSql()->getCount( $this->tb_account,['ali_uid'=>$var['ali_uid'] ] )->getOne();
        $row  = $this->createSql()->select( $this->tb_account,['ali_uid'=>$var['ali_uid'] ] )->getRow();
        if( $row)  {
            //echo 'dddd';
            //$this->drExit($row);
            $this->update(  $this->tb_account,['account_id'=>$row['account_id'] ] ,['process'=> $var['process']] );
            $this->throw_exception("账号". $taobao['nick']." 已经存在库总！",190830004);
        }

        $id =  $this->createSql("select max(account_id) as account_id from " . $this->tb_account )->getOne();
        $var['account'] = "TB" . ($id+1);
        //$this->drExit($taobao );
        $this->insert( $this->tb_account, $var, $this->file_account);
        $last_id= $this->createSql()->lastID();
        $this->getLogin()->createTaoboApi($last_id )->taobao_tmc_user_permit();//->taobao_tmc_user_permit();
        return $this;
    }

    function addAccountFromPingAn( $pingan ){
        if( !$pingan['bankCardMask'] || !$pingan['bankCardSign'] || !$pingan['clientName']  || !$pingan['telNo'] ){
            $this->throw_exception("参数错误！",19092901);
        }
        $ma_user_id= $this->getLogin()->getUserId();
        $ma= $this->getLogin()->createVip()->getMaUser(  $ma_user_id );
        $var=['zhifu_account'=> $pingan['bankCardMask'] ,'zhifu_name'=>$pingan['clientName'] ];
        $var['type']=65;
        $id =  $this->createSql("select max(account_id) as account_id from " . $this->tb_account )->getOne();
        $var['account'] = "PA".  ($id+1);
        $var['ali_uid']=  $pingan['bankCardSign'] ;
        $var['ctime']= time();
        $var['user_id']= $ma['c_user_id'] ;
        $var['ma_user_id']= $ma_user_id ;
        $var['bank_id']=1;
        $row  = $this->createSql()->select( $this->tb_account,['ali_uid'=>$var['ali_uid'] ] )->getRow();
        if( $row){
            $this->throw_exception("卡号". $pingan['bankCardMask'] ." 已经存在库总！",19092902);
        }
        $this->insert( $this->tb_account, $var, $this->file_account);
        return $this;
    }

    function addAccountFromB2Alipay( $arg ){
        if( !$arg['name'] || !$arg['account'] || !$arg['uid']){
            $this->throw_exception("参数错误！",19101601);
        }
        $ma_user_id= $this->getLogin()->getUserId();
        $ma= $this->getLogin()->createVip()->getMaUser(  $ma_user_id );
        $var=['zhifu_account'=> $arg['account'] ,'zhifu_name'=> $arg['name']];
        $var['type']=90;
        $id =  $this->createSql("select max(account_id) as account_id from " . $this->tb_account )->getOne();
        $var['account'] = "BA".  ($id+1);
        $var['ali_uid']=  $arg['uid'] ;
        $var['ctime']= time();
        $var['user_id']= $ma['c_user_id'] ;
        $var['ma_user_id']= $ma_user_id ;
        $var['bank_id']=1;
        $row  = $this->createSql()->select( $this->tb_account,['ali_uid'=>$var['ali_uid'] ] )->getRow();
        if( $row){
            $this->throw_exception("账号".  $arg['account'] ." 已经存在库总！",19101602);
        }
        $this->insert( $this->tb_account, $var, $this->file_account);
        return $this;
    }

    function addAccountFromB2JD($arg){
        if( !$arg['name'] || !$arg['account'] || !$arg['uid']){
            $this->throw_exception("参数缺失！",19101601);
        }

        $ma_user_id= $this->getLogin()->getUserId();
        $ma= $this->getLogin()->createVip()->getMaUser(  $ma_user_id );
        $var=['zhifu_account'=> $arg['account'] ,'zhifu_name'=> $arg['name']];
        $var['type']=96;
        $id =  $this->createSql("select max(account_id) as account_id from " . $this->tb_account )->getOne();
        $var['account'] = "JD".  ($id+1);
        $var['ali_uid']=  $arg['uid'] ;
        $var['ctime']= time();
        $var['user_id']= $ma['c_user_id'] ;
        $var['ma_user_id']= $ma_user_id ;
        $var['bank_id']=1;
        $row  = $this->createSql()->select( $this->tb_account,['ali_uid'=>$var['ali_uid'] ] )->getRow();
        if( $row){
            $this->throw_exception("账号".  $arg['account'] ." 已经存在库总！",19101602);
        }
        $this->insert( $this->tb_account, $var, $this->file_account);

    }

    function addAccountMa($ma_user_id,$var ){
        $ma= $this->getLogin()->createVip()->getMaUser($ma_user_id );

        $var['bank_id']= intval(  $var['bank_id'] );

        $id =  $this->createSql("select max(account_id) as account_id from " . $this->tb_account )->getOne();

        $var['account'] = "M".( $id+1); //$ma_user_id.'a'
        if( trim( $var['zhifu_name'])=='' )$var['zhifu_name']= $var['zhifu_account'];

        if( $var['bank_id']<=0) $var['bank_id']=1;

        //$this->drExit($var );

        if(! $var['account'] || ! $var['zhifu_name'] || ! $var['zhifu_account'] ||  $var['bank_id']<=0 ){
            $this->throw_exception("别名、姓名、支付账号、银行卡不允许为空！",2018081110);
        }

        if( $this->createSql()->getCount( $this->tb_account,['user_id'=>$ma['c_user_id'] ,'account'=>$var['account'] ])->getOne() )
            $this->throw_exception($var['account']." 已经存在！",2018081111);

        if( $var['type']==201 && $this->getLogin()->createVip()->getVersionBYCUid( $ma['c_user_id'] )==205 ){
            $var['type']=205;
        }


        if( intval($var['m_uid']) >0 ){
            if($this->getLogin()->createVip()->getAccountKey( $var['m_uid'] ) !=  $var['m_key'] ){
                $this->throw_exception("非法添加",90604002);
            }
            $ma_user_id= intval($var['m_uid']) ;
        }


        if($var['type']==211 ){ #如果是店长号 需要特别处理下
            if( !$var['card_index']) $this->throw_exception("店长参数错误！", 90604003 );
            $cnt= $this->createSql()->getCount( $this->tb_account,[ 'card_index'=> $var['card_index'] , 'type'=>$var['type'],'zhifu_account'=>$var['zhifu_account'] ])->getOne();
            if( $cnt >0  ) $this->throw_exception($var['zhifu_account']." 账号已经存在！" , 90604004 );


        }elseif( $var['type']==260 ) {
            if (!$var['ali_uid']) $this->throw_exception($var['zhifu_account'] . " 参数错误！", 19093001);
            $acc = $this->getAccountByAliUid($var['ali_uid']);
            if ($acc) $this->throw_exception($var['zhifu_account'] . " 账号已经存在！", 19093002);
        }elseif(  in_array($var['type'],[148,147]  )){

            $wh=[ 'user_id'=>  $ma['c_user_id'] ,'zhifu_account'=>$var['zhifu_account'] ,'type'=>[ 148,147,4,47,48] ];
            $cnt =$this->createSql()->getCount( $this->tb_account, $wh)->getOne();

            if($cnt>0) $this->throw_exception($var['zhifu_account']." 账号已经存在！",19122801);

        }elseif( $this->createSql()->getCount( $this->tb_account,[ 'ma_user_id'=> $ma_user_id , 'type'=>$var['type'],'zhifu_account'=>$var['zhifu_account'] ])->getOne() )
            $this->throw_exception($var['zhifu_account']." 账号已经存在！",2018081112);

        $var['user_id']= $ma['c_user_id'] ;
        $var['ma_user_id']= $ma_user_id ;
        $var['ctime']= time();
        $this->insert( $this->tb_account, $var, $this->file_account);
        return $this;

    }

    function modifyAccount($account_id, $var , $opt=[]){
        //$old = $this->createSql()->select($this->tb_account, ['account_id'=>$account_id])->getRow();



        if( $opt['user_id']>0 )  $uid=  $opt['user_id'] ;
        else $uid= $this->getUserID() ;

        if( isset($var['account'])){
            $row= $this->createSql()->select( $this->tb_account,['user_id'=> $uid,'account'=>$var['account'] ] )->getRow();
            if($row&& $row['account_id'] != $account_id )  $this->throw_exception($var['account']." 已经存在！",2018081113);
        }
        if( isset( $var['zhifu_account']) ){
            $wheres= [ 'zhifu_account'=>$var['zhifu_account'] ];

            if( $opt['ma_user_id']) $wheres['ma_user_id']= $opt['ma_user_id']  ;

            $row =  $this->createSql()->select( $this->tb_account, $wheres)->getRow();
            //$this->drExit($row );
            if($row && $row['account_id'] != $account_id )  $this->throw_exception($var['zhifu_account']." 账号已经存在！",2018081114);
        }
        //print_r($this->file_account );        $this->drExit($var );
        $this->update( $this->tb_account,[ 'account_id'=>$account_id ], $var, $this->file_account );
        return $this;
    }

    function getAccountListWithPage( $where ,$opt=[]){
        return $this->createSql()->selectWithPage( $this->tb_account, $where ,20 ,[],[ 'account_id'=> 'desc' ]);
    }
    function getBankListWithPage($where ,$opt=[]){
        return $this->createSql()->selectWithPage( $this->tb_bank, $where);
    }

    function checkPayType($pmid,$pay_type, $me_mid  ){
        //$where=[];
        //$where['or']=['merchant_id'=>$pmid ];
        $str_name = $this->getLogin()->createQrPay()->getPayTypeFromUser( $pay_type );
        $row = $this->getLogin()->createTableMerchant()->getRowByWhere( ['merchant_id'=>$pmid,'pay_type'=>$pay_type ] );
        if( $row && $row['merchant_id']!= $me_mid) $this->throw_exception($str_name. " 付费方式已经存在！", 19102303);
        $row = $this->getLogin()->createTableMerchant()->getRowByWhere( ['pid'=>$pmid,'pay_type'=>$pay_type ] );
        if( $row && $row['merchant_id']!= $me_mid) $this->throw_exception($str_name. " 付费方式已经存在！", 19102304);
        return $this;
    }

    function newAppID( $iCnt=10){
        for($i=0;$i<$iCnt ;$i++){
            $app_id= drFun::rankStr(8);
            $cnt= $this->getLogin()->createTableMerchant()->getCount( ['app_id'=>$app_id ] );
            if( $cnt<=0) return $app_id;
        }
        $this->throw_exception("生成app id 发生未知错误",19110201);
        return $app_id;
    }

    function getTypeMcExport( $type='all'){
        $arr= [1=>'待转账',11=>'已转账待确认',12=>'已驳回',21=>'已转账',22=>'失败',31=>'待服务商转账'
            ,61=>'服务商.审核成功'
            ,62=>'服务商.转账汇款'
            ,63=>'服务商.审核驳回'
            ,64=>'服务商.上传凭证'
            ,65=>'服务商取消',66=>'抢单'];
        return $arr;
    }
    function getTypeMcExport3( $type='all'){
        $arr= [1=>'取消服务商',11=>'已转账待确认',12=>'驳回',21=>'转账成功',22=>'失败',31=>'推给服务商'
            ,61=>'服务商.审核成功'
            ,62=>'服务商.转账汇款'
            ,63=>'服务商.审核驳回'
            ,64=>'服务商.上传凭证'
            ,65=>'服务商取消',66=>'服务商.抢单'];
        return $arr;
    }

    function getTypeMcExport2( $type='all'){
        $arr=[];
        $arr[1]=['n'=>'取消服务商','can'=>[21,12,31] ,'op'=>-1 ];
        //$arr[11]=['n'=>'转账待确认','can'=>[21,12] ,'op'=>-1 ];
        $arr[12]=['n'=>'驳回' ,'op'=>0 ];
        $arr[21]=['n'=>'转账成功'  ,'op'=>-1 ];

        $arr[31]=['n'=>'推给服务商'  ,'op'=>-1,'can'=>[21,12,1] ];

        if( $type=='all')    return $arr;
        if( !isset( $arr[$type] ) ) $this->throw_exception( $type. "类型不存在");
        return $arr[$type];
    }



    function getPayTypeFromUser( $type='all'){

        $arr=[1=>'支付宝H5',2=>'微信',4=>'云闪付',5=>'支付宝扫码',11=>'网银',21=>'转卡' ];
        if( $type=='all') return $arr;
        if( !isset($arr[ $type])) $this->throw_exception("该付款方式不存在",19102301);
        return $arr[ $type];
    }

    function getTypePay( $type='all'){

        $arr= [1=>'支付宝',2=>'微信V1',3=>'云闪付.夜',4=>'支转卡',45=>'卡转卡',50=>'财付达',0=>'未知' ,11=>'补单',21=>'支付宝V2', 31 =>'支转账', 32=>'支扫码', 33 =>'支账单'
            ,41=>'支网商' ,42=>'转卡短信' ];

        $arr[47]='支转卡.主';
        $arr[48]='支转卡.子';

        $arr[147]='代.主卡';
        $arr[148]='代.子卡';

        $arr[51]='聚合支付'; //52: 51
        $arr[52]='聚合微信'; //52: 51
        $arr[35]='红包'; //52: 51
        $arr[36]='反向收款'; //52: 51
        $arr[38]='钉钉红包'; //52: 51
        $arr[39]='淘宝'; //52: 51
        $arr[239]='代.淘宝'; //
        $arr[22]='微信(店员)'; //52: 51 店员
        $arr[24]='微店长'; //
        $arr[28]='微手机'; //
        $arr[301]='支点餐'; //
        $arr[302]='钉小二'; //
        $arr[303]='支店员'; //
        $arr[78]='钉钉收款'; //38
        $arr[60]='云闪付'; //
        $arr[61]='云.账单'; //
        $arr[63]='云.动账'; //
        $arr[65]='云.平安';

        $arr[66]='云.交易';
        $arr[67]='云交.账单';

        $arr[363]='付临门';
        $arr[364]='付临门.码商';

        $arr[260]='云.码商';
        $arr[263]='云.码商.夜';
        $arr[80]='淘宝代付'; //
        $arr[90]='支.网银'; //
        $arr[91]='支网银'; //
        $arr[92]='支网银2'; //
        $arr[93]='网银账单'; //
        $arr[94]='支企.网银'; //

        $arr[96]='京东.网银'; //

        $arr[201]='微信抢单'; //
        $arr[205]='支付宝抢单'; //
        $arr[211]='微信店长'; //
        $arr[351]='口令红包'; //

        $arr[150]='群红包'; //

        $arr[460]='云.酷卡'; //猫池
        $arr[110]='人工抢单'; //
        $arr[120]='微信红包'; //
        $arr[121]='微红包.码商'; //

        $arr[56]='场外补分'; //
        $arr[57]='场外扣分'; //

        $arr[130]='微博红包'; //
        $arr[13]='支付宝个码'; //
        $arr[14]='支个码.代理'; //
        $arr[5]='个码扫码'; //

        $arr[320] = '话费'; //

        $arr[139] = '旺信'; //

        $arr[145] = '转卡.人工'; //
        $arr[140] = '转卡.机器'; //

        if($type=='display' ){
            //店员 ,201=>'微信抢单' ,3=>'云闪付.夜', 320=>'话费'
            return [ 1=>'支付宝',4=>'支转卡',22=>'微信',24=>'微店长',39=>'淘宝',60=>'云闪付',139=>'旺信' ]; //,45=>'卡转卡',38=>'钉钉' ,302=>'钉小二'  ,3=>'云闪付' //  ,50=>'财宝'
        }
        if($type!='all' ){
            unset( $arr[0]);
            if( isset( $arr[$type] )) return $arr[$type];
            $this->throw_exception("该支付方式不支持！" . $type ,2018081121);
        }
        return $arr ;
    }

    function getBankType( $type='all'){
        $arr=[
            200101=>['c'=>'CCB','n'=>'建设银行','is95'=>1],
            200102=>['c'=>'ICBC','n'=>'工商银行','is95'=>1],
            200103=>['c'=>'ABC','n'=>'农业银行','is95'=>1 ],
            200104=>['c'=>'BOC','n'=>'中国银行','is95'=>1],
            200105=>['c'=>'PSBC','n'=>'邮储银行','is95'=>1],
            200106=>['c'=>'COMM','n'=>'交通银行'],
            200107=>['c'=>'CMB','n'=>'招商银行','is95'=>1],
            200108=>['c'=>'CEB','n'=>'光大银行'],
            200109=>['c'=>'CIB','n'=>'兴业银行'],
            200110=>['c'=>'CITIC','n'=>'中信银行'],
            200111=>['c'=>'CMBC','n'=>'民生银行'],
            200112=>['c'=>'SPDB','n'=>'浦发银行'],
            200113=>['c'=>'SPABANK','n'=>'平安银行'],
            200114=>['c'=>'GDB','n'=>'广发银行'],
            200115=>['c'=>'HXBANK','n'=>'华夏银行']
            ,200116=>['c'=>'ANTBANK','n'=>'网商银行']
            ,200117=>['c'=>'ZJKCCB','n'=>'张家口银行']
            ,200118=>['c'=>'BOCFCB','n'=>'中银富登村镇银行']
            ,200119=>['c'=>'NBCBANK','n'=>'宁波通商银行']
            ,200120=>['c'=>'HDBANK','n'=>'邯郸银行']
            ,200121=>['c'=>'HNRCC','n'=>'湖南省农村信用社']
            ,200122=>['c'=>'SXRCU','n'=>'山西省农村信用社']
            ,200123=>['c'=>'SXRCCU','n'=>'陕西省农信社']
            ,200124=>['c'=>'BOQZ','n'=>'泉州银行']
            ,200125=>['c'=>'FJNX','n'=>'福建省农村信用社联合社']

            ,200126=>['c'=>'BJBANK','n'=>'北京银行']
            ,200127=>['c'=>'CDCB','n'=>'成都银行']
            ,200128=>['c'=>'BOHAIB','n'=>'渤海银行']
            ,200129=>['c'=>'CQBANK','n'=>'重庆银行']
            ,200130=>['c'=>'HSBANK','n'=>'徽商银行']
            ,200131=>['c'=>'CSCB','n'=>'长沙银行']
            ,200132=>['c'=>'GDRCC','n'=>'广东农信']
            ,200133=>['c'=>'ARCU','n'=>'安徽农村信用社']
            ,200134=>['c'=>'BOD','n'=>'东莞银行']
            ,200135=>['c'=>'NCB','n'=>'江西银行']
            ,200136=>['c'=>'JXRCU','n'=>'江西农村信用社']
            ,200137=>['c'=>'JJBANK','n'=>'九江银行']
            ,200138=>['c'=>'YNRCC','n'=>'云南农村信用社']
            ,200139=>['c'=>'HRXJB','n'=>'华融湘江银行']
            ,200140=>['c'=>'SXBANK','n'=>'三湘银行']
            ,200141=>['c'=>'BOSZ','n'=>'苏州银行']
            ,200142=>['c'=>'GZB','n'=>'赣州银行']
            ,200143=>['c'=>'EGBANK','n'=>'恒丰银行']
            ,200144=>['c'=>'HBC','n'=>'湖北银行']
            ,200145=>['c'=>'HKB','n'=>'汉口银行']
            ,200146=>['c'=>'HURCB','n'=>'湖北省农信社']
            ,200147=>['c'=>'GZRCU','n'=>'贵州省农村信用社联合社']
            ,200148=>['c'=>'BHB','n'=>'河北银行']
            ,200149=>['c'=>'GCB','n'=>'广州银行']
            ,200150=>['c'=>'TCCB','n'=>'天津银行']
            ,200151=>['c'=>'DTB','n'=>'大同银行']
            ,200152=>['c'=>'GLBANK','n'=>'桂林银行']
            ,200153=>['c'=>'GHB','n'=>'广东华兴银行']
            ,200154=>['c'=>'BOCZ','n'=>'沧州银行']
            ,200156=>['c'=>'JLRCU','n'=>'吉林农村信用社']
            ,200157=>['c'=>'JSB','n'=>'晋商银行']
            ,200158=>['c'=>'HNRCU','n'=>'河南省农村信用社']
            ,200159=>['c'=>'BOP','n'=>'平顶山银行']

        ];
        if($type!='all'){
            if( isset( $arr[$type] )) return $arr[$type];
            $this->throw_exception("改支付方式不支持！" ,2018081127);
        }
        return $arr ;
    }

    function getTypeOnline( $type='all'){
        //$arr= [ 0=>'待登录' ,1=>'备线收款',2=>'禁用',3=>'服务未启用',11=>'主线收款'];
        $t_arr = $this->getTypeOnlineV2();
        $arr = [];
        foreach( $t_arr as $k=>$v ) $arr[ $k]= $v['n'];
        if($type!='all' ){
            //unset( $arr[0]);
            if( isset( $arr[$type] )) return $arr[$type];
            $this->throw_exception("状态不在！" ,2018081121);
        }
        return $arr ;
    }
    function getTypeOnlineV2( $type='all' ){
        $arr= [ 0=>['n'=>'待登录','cls'=>'']
            ,1=>['n'=>'备线收款', 'cls'=>'sui-btn btn-success btn-bordered']
            ,2=>['n'=>'停用下线', 'cls'=>'sui-btn']
            ,3=>['n'=>'服务未启用', 'cls'=>'sui-btn btn-warning']
            ,4=>['n'=>'小额收款', 'cls'=>'sui-btn btn-success btn-bordered']
            ,11=>['n'=>'主线收款', 'cls'=>'sui-btn btn-success']
            ,12=>['n'=>'无权收款', 'cls'=>'sui-btn btn-bordered btn-danger']
            ,13=>['n'=>'刷脸提现', 'cls'=>'sui-btn btn-bordered btn-warning']
            ,14=>['n'=>'账号冻结', 'cls'=>'sui-btn btn-bordered btn-warning']
            ,15=>['n'=>'已恢复', 'cls'=>'sui-btn  btn-bordered']
            ,16=>['n'=>'非法', 'cls'=>'sui-btn  btn-bordered']
            ,17=>['n'=>'名字不符', 'cls'=>'sui-btn  btn-bordered']
            ,-100=>['n'=>'删除', 'cls'=>'sui-btn  btn-bordered']
        ]    ;
        if($type!='all' ){
            //unset( $arr[0]);
            if( isset( $arr[$type] )) return $arr[$type];
            $this->throw_exception("状态不在！" ,2018081125);
        }
        return $arr ;

    }

    function getAccountByAliUid($ali_uid ){
        $where=['ali_uid'=>$ali_uid ];
        return  $this->createSql()->select( $this->tb_account, $where  )->getRow();
        //return  $this->createSql()->select( $this->tb_account, $where ,[],[],['account_id'=>'ASC'] )->getRow();
    }
    function getAliUidByAccountID( $account_id ){
        $where = ['account_id'=> $account_id];
        $re= $this->createSql()->select($this->tb_account,$where,[],['ali_uid','account_id'])->getCol2();
        //$this->drExit( $this->createSql()->select($this->tb_trade,$where,[],['ali_uid','ali_uid'])->getSQL()  );
        unset($re['']);
        return   $re ;
    }
    function getAliUidByWhere( $where, $opt=[]){
        if($opt['all']){
            $re = $this->createSql()->select($this->tb_account, $where, [0,1000])->getAllByKey('ali_uid');
        }else {
            $re = $this->createSql()->select($this->tb_account, $where, [0,1000], ['ali_uid', 'account_id'])->getCol2();
        }
        unset($re['']);
        return   $re ;
    }
    function getAccountByAliUidAndWeihao( $ali_uid ,$weihao){
        $weihao= trim($weihao);
        if( $weihao=='' ) return [];
        $where=['ali_uid'=>$ali_uid,'type'=>4 ];
        $tall = $this->createSql()->select( $this->tb_account, $where )->getAll();
        //$this->drExit( $weihao );
        $len= strlen( $weihao );

        foreach( $tall as $v ){
            if( substr($v['zhifu_account'],-$len )== $weihao) return $v ;
        }
        return [];
    }

    function getTypeTrade( $type='all' ){
        $arr= ['0'=>'下单','4'=>'支付转入','3'=>'支付中','1'=>'支付成功', '5'=>'产码中','2'=>'超时', 10=>'补单审核', 11=>'补单成功', 12=>'补单驳回'];
        if( $type!='all' ){
            if( isset( $arr[$type] )) return $arr[$type];
            $this->throw_exception("该方式不支持！" ,2018081130);
        }
        return $arr ;
    }

    function getTypeTradeSuccess(){
        return [1,11];
    }

    /**
     * 正在使用中的编号
     * @return array
     */
    function getTypeTradeUsing( ){
        //return [0,4,3];
        return [ 4,3];
    }

    function getTypeTradeUsingPayMatch(){
        return [3];
    }

    function getTypeTradeUsingLimit(){
        return [0,4,3];
    }

    function getTypeBank( ){
        //if( $uid<=0) $uid= $this->getUserID();
        return $this->createSql()->select( $this->tb_bank,['user_id'=>$this->getUserID()],[],['bank_id','bank'])->getCol2();
    }

    function getTypeAccount(){
        return $this->createSql()->select( $this->tb_account,['user_id'=>$this->getUserID()],[],['account_id','account'])->getCol2();
    }

    function getQrListBy( $account_id ){
        return $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id])->getAllByKeyArr(['fee']);
    }

    function getMerchantByAppID( $app_id, $opt=[]){
        if( !$app_id )$this->throw_exception("参数错误！ app_id 缺失" ,2018081116);
        $row = $this->createSql()->select($this->tb_merchant,['app_id'=>$app_id])->getRow();
        if( !$row){
            $row = $this->createSql()->select($this->tb_merchant,['merchant_id'=>$app_id])->getRow();
        }
        if( !$row ) $this->throw_exception("商户号：".$app_id." 不存在！" ,2018081115);

        return $row ;
    }

    function getMerchantByID($mc_id ){
        if( is_array( $mc_id) ){
            return $this->createSql()->select($this->tb_merchant,['merchant_id'=>$mc_id])->getAllByKey( 'merchant_id');
        }
        $row = $this->createSql()->select($this->tb_merchant,['merchant_id'=>$mc_id])->getRow();
        if( !$row ) $this->throw_exception($mc_id." 不存在！" ,2018081115);
        return $row ;

    }

    function getMerchantSafeByID( $mc_id ){
        $row = $this->getMerchantByID( $mc_id);
        unset( $row['app_id']);
        unset( $row['app_secret']);
        return $row;
    }


    function getMerchantYue($mc_id, $opt=[]){
        $mc = isset($opt['mc']) ? $opt['mc']: $this->getMerchantSafeByID( $mc_id );

        $sql="select sum(notify_all)  as notify_all,sum(notify_all_cnt)  as notify_all_cnt, sum( fee) as fee  from mc_day where merchant_id='".  drFun::addslashes ( $mc_id)."'   ";
        if($mc['last_day']>0 ) $sql.=" and `day`>'".$mc['last_day']."' ";
        $row = $this->createSql($sql)->getRow();

        $sql2= $sql="select sum( real_money) as real_money, sum(money) as money,count(*) as xcnt from mc_export    where merchant_id='". drFun::addslashes ( $mc_id)."'  and  real_money!=0 ";
        if($mc['last_export_id']>0 ) $sql2.=" and `export_id`>'".$mc['last_export_id']."' ";
        //$this->drExit( $mc );
        $ex = $this->createSql($sql2)->getRow();

        #$row['sql2']= $sql2 ;

        #当有时间的时候 统计下时间段内的下发
        if(isset($opt['op_time'])){
            if( $opt['op_time']['start'] ) $sql2.=" and ctime>='". $opt['op_time']['start']."' ";
            if( $opt['op_time']['end'] ) $sql2.=" and ctime<='". $opt['op_time']['end']."' ";
            $ex2 = $this->createSql($sql2)->getRow();

            $yesterday=['money'=>abs($ex2['money'] ),'real_money'=> abs($ex2['real_money']),'real_money_cnt'=>abs($ex2['xcnt']) ];
            $yesterday['xfee']= $yesterday['real_money']-$yesterday['money'] ;
            $row['tj_time']= $yesterday;
        }

        $row['real_money'] = abs($ex['real_money']);
        $row['real_money_cnt'] = abs($ex['xcnt']);
        $row['money'] = abs($ex['money']);
        $row['xfee'] =   $row['real_money'] -  $row['money'] ;

        $row['yue']= $row['notify_all']-  $row['real_money']-  $row['fee'];
        $mc['yue']= $row;
        return $mc ;

    }

    function clearMerchant( $mc_id){
        $mc_id = intval($mc_id);
        $mc = $this->getMerchantYue( $mc_id );

        $last_day= $this->createSql("select max(`day`) from mc_day where  merchant_id='".$mc_id."' ")->getOne();
        $last_export_id =  $this->createSql("select max(export_id) from mc_export where merchant_id='".$mc_id."' ")->getOne();

        $var= ['last_day'=>$last_day, 'last_export_id'=>$last_export_id,'clear_time'=>time() ];

        //$this->drExit( $var );
        $this->getLogin()->createTableMerchant()->updateByKey( $mc_id,$var) ;
        $mc['up']= $var;
        #日志
        $this->getLogin()->createLogGt()->append( $this->getUserID() , 103 , $mc );
        return $this;
    }

    function getMerchantListWithPage( $where){
        return $this->createSql()->selectWithPage( $this->tb_merchant, $where ,100 );
    }

    function getQrByID( $id ){
        return $this->createSql()->select($this->tb_qr, ['qr_id'=>$id ])->getRow();
    }
    function clearAppSecret( &$arr){
        if( isset( $arr['app_secret'] )){
            unset(  $arr['app_secret'] );
            return $this;
        }
        foreach( $arr as &$v ) unset( $v['app_secret']);
        return $this;
    }

    function getLiveQrV5($price, $opt=[]){


        //if( !in_array( $price,$p_arr ) )$this->throw_exception( "试试其他金额",7894);
        if( $price<10 )$this->throw_exception( "试试其他金额",7894);

        $mc_id_u= $this->getLogin()->midConsole();

        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
        $wh['>=']= ['clienttime'=> ( time()- $this->liveTime ) ];

        $account_id_xiao=[];
        if( $price < 2000 ){
            $wh['online']= 4 ; #小额收款
            $account_id_xiao= $this->getAccountIDByWhere( $wh );
        }
        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );

        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );
        if( !$account_id ) $account_id= $account_id_zhu;
        if( !$account_id ) $account_id= $account_id_xiao;
        if (!$account_id) $this->throw_exception("后端未启动!", 2018081128);

        //$qr_id=18000;
        $qr_id=17999;

        $row = $this->createSql()->select( $this->tb_qr, ['qr_id'=>$qr_id ])->getRow();
        $acc_id= $this->getOneAccountId( $account_id_xiao, $account_id_zhu, $account_id );
        $account = $this->getAccountByID( $acc_id );

        $row['account_id']= $acc_id;
        $row['user_id']= $account['user_id'];

        $opt=['xiao_cnt'=>$account_id_xiao?3:1  , 'version'=>2];
        $row['fee']= $this->getPriceRandV5( $price,$row , $opt );
        return $row;

    }

    function getPriceRandV5( $price , $trade_row ,$opt=[] ){
        $qr_id= $trade_row['qr_id'] ;
        $where = [ 'qr_id'=>$qr_id,'type'=> $this->getTypeTradeUsingLimit() ,'account_id'=>$trade_row['account_id'] ];
        $qr_row = $this->createSql()->select( $this->tb_trade,$where ,[0,1000],['realprice','realprice'])->getCol2();
        $cnt= $opt['cnt']>10 ? $opt['cnt'] :9  ;
        $yes=[];

        if( ! $price%100 ) $price=$price-1;

        $e =   $price-$cnt ;
        for (  $i =  $price ; $i > $e; $i--) {
            if (!isset($qr_row[$i])){
                $yes[]=$i;
                //return $i;
            }
        }



        if( !$yes )    $this->throw_exception( "请试一试其他金额！",458);

        $fee=$yes[ rand(0, count($yes)-1) ];
        $fee = $this->getPriceBest( $yes, $where );
        if( !$fee) $fee= $yes[0];
        //$this->drExit( $fee );
        return $fee;
    }

    function getLiveQrV3sMa($price, $opt=[]){
        $mc_id_u= $this->getLogin()->midConsole();
        $c_user_id= $mc_id_u[$opt['merchant_id']];
        $c_user_id= $this->getCUserIDbyMerchant( $opt );

        $ma_user= $this->getPayMaUser($c_user_id, $price ,$opt );
        if(  !$ma_user ) $this->throw_exception("没有符合的码啊" ,19111201);

        $opt['ma_user_id'] = $ma_user;

        return $this->getLiveQrV3s( $price, $opt );
    }

    function getLiveQrV40Ma($price, $opt=[]){
        $opt['account_type'] = $opt['account_type2']?$opt['account_type2'] : [147,148];
        $opt['no_clear']= 1 ;
        return $this->getLiveQrV2Ma( $price , $opt);
    }

    function getLiveQrV2Ma( $price, $opt=[] ){

        $mc_id_u= $this->getLogin()->midConsole();
        $c_user_id= $mc_id_u[$opt['merchant_id']];
        $c_user_id= $this->getCUserIDbyMerchant( $opt );

        $ma_user= $this->getPayMaUser($c_user_id, $price ,$opt );
        if(  !$ma_user ) $this->throw_exception("没有符合的码啊" ,19100102);

        $opt['ma_user'] = $ma_user;

        return $this->getLiveQrV2( $price, $opt );
    }

    function getLiveQrV320(  $price, $opt=[]){
        $wh=[];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );
        $wh['type']=  320;//[320];
        $wh['>=']= ['clienttime'=> ( time()-180 ) ];
        try{
            $wh['online']= 11; #主线
            $qr= $this->getQrFromHfTrade( $wh, $price, $opt);
        }catch (drException $ex ){
            $wh['online']=  [1,4 ]; #备线
            $qr= $this->getQrFromHfTrade( $wh, $price, $opt);
        }
        return $qr;
    }
    function getQrFromHfTrade( $wh,  $price, $opt=[] ){
        $account_id = $this->getAccountIDByWhere( $wh );
        if(! $account_id ) $this->throw_exception( "请通道上号！", 20010828);

        $where=['account_id'=>$account_id,'fee'=>$price,'type'=>5 ];
        $where['>']['endtime'] = time();

        $qr= $this->getLogin()->createTableHfTrade()->getAll( $where  );
        if( !$qr ) $this->throw_exception("请尝试其他面值",20010827);

        $rz= $qr[ rand(0,count($qr)-1)];
        $rz['qr_id'] = $rz['hf_id'];
        //$this->getLogin()->createTableHfTrade()->updateByKey( $rz['qr_id'], ['type'=>10] );
        return $rz;
    }

    function getLiveQrV2( $price, $opt=[] ){



        $p_arr= [1999,4999,9999,19999,49999,99999,199999,299999];
        if( !isset($opt['no_check']) ) {
            //if (!in_array($price, $p_arr)) $this->throw_exception("试试其他金额V2", 7894);
        }

        $mc_id_u= $this->getLogin()->midConsole();

        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );

        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;

        if( isset($opt['ma_user'] ))   $wh['ma_user_id']= $opt['ma_user'] ;

        if( $opt['version']==60 ) {
            $wh['type']=60;
            if( isset($opt['ma_user'] ))   $wh['type']=260;
        }
        if( $opt['version']==65 ) $wh['type']=65;
        if( $opt['version']==63 ) $wh['type']=3;

        if($opt['version']==40){
            $wh['>=']= ['clienttime'=> ( time()- 120 ) ];
        }else{
            $wh['>=']= ['clienttime'=> ( time()-120 ) ];
        }

        //if( $opt['merchant_id']==8233 || 8225== $opt['merchant_id'] ){
        if( in_array($opt['merchant_id'],[8223,8233,8225] )){
            $wh['nolike']= ['account'=>'XB%'] ; #XB不收款
        }



        if( $price < 2000 ){
            $wh['online']= 4 ; #小额收款
            $account_id_xiao= $this->getAccountIDByWhere( $wh ,['dan'=>1]);
        }
        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh ,['dan'=>1]);




        $wh['online']= 11; #主线
        #$account_id_zhu = $this->getAccountIDByWhere( $wh ,['dan'=>1]);

        $account_id_zhu  = $this->getAccountIDByWhere( $wh  );
        $this->clearYesPrice($account_id_zhu  , $price );
        //$this->logs_s("account_id_zhu===". json_encode( $account_id_zhu)  ,'debug.log');

        if( !$account_id ) $account_id= $account_id_zhu;
        if( !$account_id ) $account_id= $account_id_xiao;
        if (!$account_id) $this->throw_exception("后端未启动", 2018081128);

        $qr_id=18000;
        //if( $opt['version']==4 ||  $opt['version']==40 ||  $opt['version']==31 )   $qr_id=17999;
        if( in_array($opt['version'] ,[31,4,60,40,301]  ))   $qr_id=17999;


        $row = $this->createSql()->select( $this->tb_qr, ['qr_id'=>$qr_id ])->getRow();
        $acc_id= $this->getOneAccountId( $account_id_xiao, $account_id_zhu, $account_id );
        $account = $this->getAccountByID( $acc_id );

        $row['account_id']= $acc_id;
        $row['user_id']= $account['user_id'];

        //$opt=['xiao_cnt'=>$account_id_xiao?2:1  , 'version'=>2];
        $opt['xiao_cnt'] = $account_id_xiao?2:1;
        $opt['version'] = 2;
        $row['fee']= $this->getPriceRandV2S( $price,$row , $opt );
        return $row;

    }

    function clearYesPrice(& $account_id, $price ){
        if( !$account_id ) return $this;
        $yes= $this->getYesPrice( $price ); //json_encode( $yes)
        $where=['account_id'=> $account_id, 'type'=> $this->getTypeTradeUsingLimit()  ];
        $where['realprice']= $yes;

        $sql ="select account_id,count(*) as cnt from ".  $this->tb_trade." where ". $this->createSql()->arr2where( $where ) ." group by account_id"  ;
        //$qr_row = $this->createSql()->select( $this->tb_trade,$where ,[0,1000],['realprice','realprice'])->getCol2();
        $acc_cnt= $this->createSql($sql)->getCol2();
        $yes_cnt= count( $yes );
        //$this->logs_s("clearYesPrice===".$price."==". count( $yes ) ."==".json_encode( $yes)."==".json_encode( $account_id)."==\n". json_encode( $acc_cnt)  ,'debug.log');
        foreach($account_id as $k=> $aid  ){
            if( isset( $acc_cnt[$aid] ) &&   $acc_cnt[$aid]>=$yes_cnt ) unset(  $account_id[$k] );
        }
        //$this->logs_s("yesClear===". json_encode( $account_id)  ,'debug.log');
        if( !$account_id ) return $this;
        $account_id= array_values( $account_id );
        $account_id= [ $account_id[0] ];
        return $this;
    }

    function getOneAccountId($account_id_xiao, $account_id_zhu, $account_id, $opt=[]){
        $arr= $account_id_xiao;
        if(!$arr) $arr=  $account_id_zhu;
        if(!$arr) $arr=  $account_id;

        $arr = array_values( $arr );

        #$this->logs_s("getOneAccountId===1". json_encode( $arr)  ,'debug.log');
        #$this->logs_s("getOneAccountId===2". json_encode( $arr2)  ,'debug.log');

        $k= rand(0, count($arr)-1);
        //$this->drExit( $arr[$k]);

        $account_id = $opt['first']?  $arr[0] : $arr[$k];
        $this->accountUTime(  $account_id );
        return $account_id;
    }

    function clearAccount( &$account_id ,$account_id_using,$opt=[]){
        if( !$account_id_using ) return ;
        foreach ( $account_id as $k=>$v_id ){
            if( $opt['iskey'] && in_array($k, $account_id_using) ){
                unset( $account_id[$k] );
            }elseif( in_array($v_id, $account_id_using) ){
                unset( $account_id[$k] );
            }
        }
    }

    function getCUserIDbyMerchant( $merchant){
        $mc_id_u= $this->getLogin()->midConsole();
        if(  $mc_id_u[ $merchant['merchant_id']]  )  return  $mc_id_u[ $merchant['merchant_id']] ;

        if( !isset($merchant['c_user_id']) ){
            $merchant= $this->getMerchantByID( $merchant['c_user_id'] );
        }
        if( intval( $merchant['c_user_id'])<=0 ) $this->throw_exception("商户未安排通道",19110301);
        return $merchant['c_user_id'];
    }



    /**
     * 首先去 login->getMoneyGu 查找是不是固定金额，如果是固定金额直接把码取出啦；
     *
     * @param $price
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getLiveQrV60(  $price, $opt=[] ){
        /*
        $p_key = $price/100;
        $guMoeny= $this->getLogin()->getMoneyGu();
        if(  !$guMoeny[ $p_key] || $price%100!=0 ) return $this->getLiveQrV2($price, $opt );
        */

        $qr= $this->getLiveQrV2($price, $opt );

        if( $this->getLogin()->isUnipayYue() ){
            $account= $this->getAccountByID( $qr['account_id']);
            $where=['account_ali_uid'=>$account['ali_uid'],'fee'=>$qr['fee'],'type'=>60  ];
            $where['>']['ctime']= time()- 20*3600;
            $tem= $this->getLogin()->createTablePayLogTem()->getAll( $where, ['pt_id'=>'desc'],[0,30] ,['pt_id']); //
            $this->logs_s( "getLiveQrV60\t".json_encode($tem ). "\n"  ,'debug.log');
            if( !$tem) $this->throw_exception("请尝试其他金额！", 458 );
            $tem_qr= $tem[ rand(0, count( $tem)-1)];
            $qr['qr_id']= $tem_qr['pt_id'];
        }

        return $qr;


        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['type']= [60 ]  ;
        $wh['>=']= ['clienttime'=> ( time()- 60*2 ) ];


        $wh['online']= [11,1  ];
        $account_id_online = $this->getAccountIDByWhere( $wh );

        if(! $account_id_online ) $this->throw_exception( "后端未启动！", 2018081128);

        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );

        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );

        if( $account_id_zhu ) $account_id= $account_id_zhu;
        $ali_uid_accountID = $this->getLogin()->createQrPay()->getAliUidByAccountID($account_id);

        if(! $ali_uid_accountID ) $this->throw_exception( "后端未启动，请联系客服！", 2018081128);

        //60当场生成，61空单等trade号进来，62被安排trade号ali_beizhu支付中
        $where=['account_ali_uid'=>array_keys($ali_uid_accountID ) ,'ali_beizhu'=>'', 'type'=>61,'fee'=>$price];

        $where['>=']=['ctime'=> time()-24*3600 ]; //24小时过期



        $tb_payLog= $this->getLogin()->createTablePayLogTem()->getTable();
        $sqlDb=$this->createSql()->select( $tb_payLog,$where,[0,2000],['pt_id'],['pt_id'=>'desc']);


        //$this->log("dbTestV2:".  print_r($account_id, true )  );
        $pt_id = $sqlDb->getCol();
        //
        if( !$pt_id ) {

            return $this->getLiveQrV2($price, $opt );
            //$this->throw_exception( "请尝试其他金额" , 9053102);
        }

        $rk= rand(0, count($pt_id)-1);
        //$this->log("dbTestCnt: ".count($pt_id)." \t rk=".$rk."  \n\tsql:". $sqlDb->getSQL() );

        $qr_id = $pt_id[ $rk ];
        $pt_row = $this->getLogin()->createTablePayLogTem()->getRowByKey($qr_id);

        $qr = ['qr_id'=>$qr_id ];
        $qr['account_id']=   $ali_uid_accountID[$pt_row['account_ali_uid']];
        $qr['fee']= $pt_row['realprice'];
        if( $qr['fee']<=0  )   $this->throw_exception( "二维码金额出错" , 9061);
        return $qr;

    }

    function getLiveQrV80( $price, $opt=[]){
        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $wh['user_id']= $c_user_id= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']= $c_user_id= $this->getCUserIDbyMerchant( $opt );;
        $wh['type']= [80 ]  ;
        $wh['online']= [11,1  ];

        //$account_id_online = $this->getAccountIDByWhere( $wh );
        //if(! $account_id_online ) $this->throw_exception( "请通道上号！", 19090504);

        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );

        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );
        if( $account_id_zhu ) $account_id= $account_id_zhu;

        if(! $account_id ) $this->throw_exception( "请通道上号！", 19090504);

        //$this->logs_s( "getLiveQrV80\t".print_r($account_id,true ). "\n"  ,'debug.log');

        $where=['type'=>10 , 'account_id'=> $account_id,'fee'=>$price,'user_id'=>$c_user_id  ];

        //$qr_all= $this->getLogin()->createTableTaobaoQr()->getAll($where, ['qr_id'=>'desc'],[0,500],['qr_id','qr_text','fee','account_id','user_id','type']  );

        $qr_all= $this->getQrBylunAccount( $where, $account_id);


        if( !$qr_all ) $this->throw_exception( "请尝试其他金额" , 9056);

        $qr= $qr_all[ rand(0, count($qr_all)-1 )];

        $this->accountUTime(  $qr['account_id'] );

        return $qr;

    }
    function getQrBylunAccount( $where, $account_id){
        $qr_all2 = $this->getLogin()->createTableTaobaoQr()->getAllByKeyArr( ['account_id'],$where, ['qr_id'=>'desc'],[0,500],['qr_id','qr_text','fee','account_id','user_id','type']  );
        //$this->logs_s( "getQrBylunAccount\t".print_r($qr_all2,true ). "\n"  ,'debug.log');
        foreach( $account_id as $id){
            if( isset( $qr_all2[$id] ) ){
                //$this->logs_s( "getQrBylunAccount2 \t".print_r( $qr_all2[$id],true ). "\n"  ,'debug.log');
                //$this->logs_s( "getQrBylunAccount\t". $id."\t". count($qr_all2[$id]). "\n"  ,'debug.log');
                return  $qr_all2[$id];
            }
        }
        return [];
    }

    function getLiveQrV120Ma( $price, $opt=[] ){
        $opt['no_clear']= 1 ;
        $c_user_id= $this->getCUserIDbyMerchant( $opt );

        $ma_user= $this->getPayMaUser($c_user_id, $price ,$opt );
        if(  !$ma_user ) $this->throw_exception("没有符合的码啊" ,19100102);

        $opt['ma_user'] = $ma_user;

        return $this->getLiveQrV120( $price, $opt );
    }

    function getLiveQrV120( $price, $opt=[]){
        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $wh['user_id']= $c_user_id= $this->getCUserIDbyMerchant( $opt );;
        $wh['type']= [22]  ;
        $wh['>=']= ['clienttime'=> ( time()- 15 ) ];

        $type=120;
        //$this->throw_exception("当前版本 ". $opt['version'] );
        if( $opt['version']==150 ) {
            $wh['type'] = 1;
            $type=150;
        }
        if(  $opt['version']==239 ){
            $wh['type'] = 39;
            $type=239;
        }


        if( isset($opt['ma_user'] )) {
            $wh['ma_user_id']= $opt['ma_user'] ;
            $wh['type']= [121]  ;
            if(  $opt['version']==239 ){
                $wh['type'] = 239;
            }
        }

        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );

        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );
        if( $account_id_zhu ) $account_id= $account_id_zhu;
        if(! $account_id ) $this->throw_exception( "请通道上号！", 19112109);

        $where=['type'=>$type  , 'account_id'=> $account_id   ];
        $qr= $this->getQrFromPayLogTem($where, $account_id);
        $qr['fee']= $price ;


        return $qr;

    }

    function getQrFromPayLogTem( $where ,$account_id, $opt=[]){
        $qr_all2 = $this->getLogin()->createTablePayLogTem()->getAllByKeyArr( ['account_id'],$where, ['fee'=>'desc'],[0,500]   );
        $qr_arr = [];
        foreach( $account_id as $id){
            if( isset( $qr_all2[$id] ) ){
                $qr_arr=  $qr_all2[$id];
                break;
            }
        }
        if( !$qr_arr ) $this->throw_exception( "群码不足请稍后再下单！", 19112110);
        $qr= $qr_arr[ rand(0, count($qr_arr)-1 )];

        $qr['qr_id']= $qr['pt_id'] ;
        $this->accountUTime(  $qr['account_id'] );
        return $qr ;
    }

    function getLiveQrV78( $price, $opt=[] ){

        //if( $price>1000*100 ) $this->throw_exception( "内侧阶段只接受1000内订单！", 90507);

        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

        $wh['type']= [38,78]  ;

        if( $opt['version']==139 ) $wh['type']= 139 ;

        $wh['>=']= ['clienttime'=> ( time()- 60 ) ];
        $wh['online']= [11,1  ];
        $account_id_online = $this->getAccountIDByWhere( $wh );

        if(! $account_id_online ) $this->throw_exception( "后端未启动！", 2018081128);

        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );

        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );

        if( $account_id_zhu ) $account_id= $account_id_zhu;
        $ali_uid_accountID = $this->getLogin()->createQrPay()->getAliUidByAccountID($account_id);

        //if(! $ali_uid_accountID ) $this->throw_exception( "后端未启动，请联系客服！", 2018081128);
        //$this->drExit($ali_uid_accountID );

        //-78先有trade号，78空单等trade号进来，79空单超时，77已经支付，76被安排trade号ali_beizhu 支付中
        //$where=['account_ali_uid'=>array_keys($ali_uid_accountID ) ,'ali_beizhu'=>'', 'type'=>78,'fee'=>$price];

        $where=['account_id'=> $account_id ,'ali_beizhu'=>'', 'type'=>78,'fee'=>$price];

        if( $opt['version']==139 ){
            $where['type']= 139 ;
            unset($where['ali_beizhu'] );
        }


        /*
        $tb_payLog= $this->getLogin()->createTablePayLogTem()->getTable();
        $sqlDb=$this->createSql()->select( $tb_payLog,$where,[0,60],['pt_id'],['pt_id'=>'desc']);
        //$sqlDb=$this->createSql()->select( $tb_payLog,$where,[0,30],['pt_id'],['pt_id'=>'asc']);

        $pt_id = $sqlDb->getCol();
        if( !$pt_id ) $this->throw_exception( "请尝试其他金额!!" , 9056);
        $this->log("v78139:".  json_encode( $pt_id ). "   sql>>". $sqlDb->getSQL() );

        $qr_id = $pt_id[ rand(0, count($pt_id)-1)];
        $pt_row = $this->getLogin()->createTablePayLogTem()->getRowByKey($qr_id);

        */
        $pt_row = $this->getBestPayTemAccount( $where );


        $qr = ['qr_id'=>$pt_row['pt_id']  ];
        $qr['account_id']=  $pt_row['account_id'] ;//$ali_uid_accountID[$pt_row['account_ali_uid']];
        $qr['fee']= $pt_row['fee'];

        $this->accountUTime(  $pt_row['account_id'] );



        return $qr;

    }


    function getBestPayTemAccount( $where){
        $tb_payLog= $this->getLogin()->createTablePayLogTem()->getTable();
        $sqlDb=$this->createSql()->select( $tb_payLog,$where,[0,60],['pt_id','account_id'],['pt_id'=>'desc']);

        $tarr= $sqlDb->getAllByKeyArr( ['account_id']);

        $this->log("getBestPayTemAccount:".  json_encode( $tarr ). "   sql>>". $sqlDb->getSQL() );

        if( !$tarr ) $this->throw_exception( "请尝试其他金额!!" , 9056);
        $acc_id = $this->getAccountIDByWhere( ['account_id'=>array_keys($tarr )]);

        $pt_id = $tarr[ $acc_id[0] ];
        $this->log("getBestPayTemAccount row:".  json_encode( $pt_id )." acc=". json_encode($acc_id) ) ;
        $qr_id = $pt_id[ rand(0, count($pt_id)-1)];
        $pt_row = $this->getLogin()->createTablePayLogTem()->getRowByKey( $qr_id['pt_id'] );

        //$this->log("getBestPayTemAccount row:".  json_encode(  $pt_row) ) ;

        return  $pt_row ;
    }

    function getLiveQrV211($price, $opt = []){
        $mc_id_u= $this->getLogin()->midConsole();
        $c_user_id=   $mc_id_u[$opt['merchant_id']];
        $c_user_id=   $this->getCUserIDbyMerchant( $opt );;

        $ma_user= $this->getPayMaUser($c_user_id, $price ,['no_clear'=>1 ] );
        if(  !$ma_user ) $this->throw_exception("没有符合的码",9060401);
        $opt['version']= 211;
        $opt['ma_user_id']= $ma_user;
        return $this->getLiveQrV3s( $price, $opt);

    }

    function isTe( $mid){
        $marr=[8351=>1,8226=>1,8352=>1,8234=>1,8358=>1,8541=>1]; //8226,8351,8352 ,8226=>1,8352=>1  ,8521=>1
        if( isset( $marr[$mid])) return true ;
        return false;
    }

    /**
     * 是否支持人工抢单
     * @param $mid
     * @return bool
     */
    function isQiang($mid){
        $marr=[];//[8080=>1,8392=>1,8393=>1  ];
        if( isset( $marr[$mid])) return  $marr[$mid]  ;
        return false;

    }

    /**
     * 抢单流程
     * @param $trade_id
     * @param $ma_user_id
     * @param $account_id
     * @return $this
     * @throws drException
     */
    function qiang( $trade_id, $ma_user_id,$account_id ){
        //如果老乱账 可使用redis 在这个地方锁定处理
        $r_key = 'QD_'. $trade_id;
        $r_vaule= $this->getLogin()->createCache()->getRedis()->get( $r_key);
        if(  $r_vaule=='1' ) $this->throw_exception("订单已经被锁定",190805008);
        if(  $r_vaule== '2'  ) $this->throw_exception("订单已被抢",190805009);
        $this->getLogin()->createCache()->getRedis()->set($r_key,'1',60 );

        try {

            $trade = $trade_row = $this->getTradeByID($trade_id);
            if ($trade['type'] != 0 ) $this->throw_exception("已经过期或者已经被抢", 190805001);
            if ($trade['qr_id'] > 0) $this->throw_exception("订单已被抢", 190805006);


            $wh_trade = ['ma_user_id'=> $ma_user_id ,'price'=>$trade['price'],'type'=>$this->getTypeTradeUsingLimit()  ];

            if( $this->getTradeByWhere( $wh_trade ) ) $this->throw_exception("双重价格！", 190805013);

            $ma = $this->getLogin()->createTableUserMa()->getRowByKey($ma_user_id);
            if ($ma['amount'] < $trade['realprice']) $this->throw_exception("余额不足", 190805002);
            if ($trade['user_id'] != $ma['c_user_id']) $this->throw_exception("对不起这单在测试？", 190805004);

            $account = $this->getAccountByID($account_id);

            if ($account['ma_user_id'] != $ma_user_id) $this->throw_exception("收款账号不属于你", 190805003);

            $var = [];
            $qr = $this->createSql()->select($this->tb_qr, ['account_id' => $account_id, 'fee' => 10086], [0, 2000], ['account_id', 'qr_id'])->getRow();
            if (!$qr) $this->throw_exception("哎呀，二维码错误！", 190805007);
            $var['account_id'] = $account_id;
            $var['qr_id'] = $qr['qr_id'];
            $var['ma_user_id'] = $ma_user_id;
            $var['type'] = 4;

            if ($var['ma_user_id'] > 0) {
                $this->getLogin()->createVip()->maBillCreate(11, $var['ma_user_id'], $trade_row['realprice'], $trade_row['trade_id']);
            }
            $this->getLogin()->createQrPay()->upTradeByID($trade_row['trade_id'], $var);
            $this->getLogin()->createCache()->getRedis()->set($r_key, '2', 60);

        }catch (drException $ex ){
            $this->getLogin()->createCache()->getRedis()->set($r_key, '0', 60);

            $this->throw_exception( $ex->getMessage() , $ex->getCode() );
        }

        return $this;

    }

    /**
     * 返回必须还有 qr_id account_id ma_user_id
     *
     * 本函数仅适用于一张收款码场景
     * @param $trade_row
     * @param array $opt
     * @return array
     * @throws
     */
    function getBackQRV201( $trade_row , $opt=[]){
        $this->log("getBackQRV201>>id ".$trade_row['trade_id'] );

        $isTe= $this->isTe(  $trade_row['merchant_id'] );

        #$isTe=1;


        if( !$isTe ) {
            $ma_user = $this->getPayMaUser($trade_row['user_id'], $trade_row['realprice'], ['no_clear' => 1]);
            if (!$ma_user) $this->throw_exception("没有符合的码", 90613002);
            $opt['ma_user'] = $ma_user;
        }else{
            /*
            //手工回调 跟自动回调结合  没用码商 又使用分地区 适合 路人
            if( $trade_row['lo']=='广东' ){
                //把相同价格的码商去掉了
                $ma_user = $this->getPayMaUser($trade_row['user_id'], $trade_row['realprice']); //, ['no_clear' => 1]
                if( $ma_user )$ma_user[]=0; else $ma_user =[0];
            }else{
                $ma_user =[0];
            }
            $opt['ma_user'] = $ma_user;
            $opt['type']= 24;
            $opt['type']= [24,201];
            */
            //
            //if( in_array($trade_row['lo'],['广东','北京'] )  ){

            #201人工 211店员通(店长)
            //if( in_array($trade_row['lo'],['广东','湖南'] )  ){
            if( 1 ){
                $ma_user = $this->getPayMaUser($trade_row['user_id'], $trade_row['realprice']);
                $this->log("getBackQRV201>>ma_user>>". json_encode( $ma_user ));
                //if( $ma_user && count($ma_user)>1 ){ //2987 调试老周用户
                if( in_array( 2987, $ma_user) ){ //2987 调试老周用户
                    $opt['type']= [201,211];
                    $opt['type']= [201];
                }else{
                    $ma_user=[];
                }
            }
            if( !$ma_user ){
                $ma_user = $this->getPayMaUser($trade_row['user_id'], $trade_row['realprice'], ['no_clear' => 1]);
                if (!$ma_user) $this->throw_exception("没有符合的码", 90613002);
            }
            $opt['ma_user'] = $ma_user;
        }



        $opt_local=$opt; $opt_local['is_rand']=1 ;


        
        //先通个cookie找到
        $qr= $this->searchQRByCookie( $trade_row, $opt );
        if(  $qr ){
            $this->log("searchQRByCookie:".$trade_row['trade_id'] );
            return $qr;
        }



        if( $trade_row['user_id']==324 ){
            $opt['zone_limit']= 1 ; //严格按地区走啊
            if( rand(1,100)<=35   ){
                $qr= $this->DSearchQRByNewAccountV2(  $trade_row, $opt_local );
                if ($qr){
                    $this->log("DSearchQRByNewAccountV2:".$trade_row['trade_id'] );
                    return $qr;
                }
            }
            $qr= $this->DsearchQRByLocal(  $trade_row, $opt_local );
            if ($qr){
                $this->log("DsearchQRByLocal:".$trade_row['trade_id'] );
                return $qr;
            }

            $qr= $this->DSearchQRByNewAccountV2(  $trade_row, $opt_local );
            if ($qr){
                $this->log("DSearchQRByNewAccountV2 After:".$trade_row['trade_id'] );
                return $qr;
            }


            //通过地理位置找到 会有地区调度
            $qr= $this->searchQRByLocal( $trade_row, $opt_local );
            if(  $qr ) {
                $this->log("V324searchQRByLocal:".$trade_row['trade_id'] );
                return $qr;
            }


            //地区调度
            $qr= $this->searchQRByNewAccount( $trade_row, $opt_local );
            if(  $qr ){
                $this->log("V324SearchQRByNewAccount:".$trade_row['trade_id'] );
                return $qr;
            }



            //$this->throw_exception( "正在调试",2019 );
            return [];
        }







        //50%的机会先去要 新号
        if( rand(1,100)<=80   ){ // &&  $trade_row['merchant_id']==8387
            try {
                $qr = $this->searchQRByNewAccountV2($trade_row, $opt_local);
                if ($qr) return $qr;
            }catch ( drException $ex ){

            }
        }



        //通过地理位置找到
        $qr= $this->searchQRByLocal( $trade_row, $opt_local );
        if(  $qr ) return $qr;



        $qr= $this->searchQRByNewAccount( $trade_row, $opt_local );
        if(  $qr ) return $qr;


        return [];
    }

    function getOldAccount(  $trade_row ,$opt=[] ){
        $wh=[];
        $wh['type']= 211 ;
        if( isset($opt['type']) )  $wh['type']=$opt['type']  ;
        //$wh['type']= 201 ;
        //if( $trade_row['version']==205 ) $wh['type']= 205 ;
        $wh['user_id']= $trade_row['user_id'];
        $wh['online']= [1,4, 11];
        $wh['<=']['fail_cnt']= 8;
        $old=  $this->getAccountIDByWhere(  $wh );
        if( !$old ) {
            unset( $wh['<='] );
            $old=  $this->getAccountIDByWhere(  $wh );
            if(  !$old ) $this->throw_exception("请联系管理员，未上号！",90614001 );
        }
        return $old ;
    }

    function DSearchQRByNewAccountV2(  $trade_row ,$opt=[] ){
        $wh=[];
        $wh['type']= 211 ;
        if( isset($opt['type']) )  $wh['type']=$opt['type']  ;
        //$wh['type']= 201 ;
        //if( $trade_row['version']==205 ) $wh['type']= 205 ;

        $wh['user_id']= $trade_row['user_id'];
        $wh['online']= [1,4, 11];
        $wh['lo']= '';
        $wh['<=']['fail_cnt']= 8;

        $account_id =  $this->getAccountIDByWhere(  $wh );
        $re=  $this->searchQRByNewAccountFromAccountID($trade_row, $account_id ,$opt  );
        return $re ;

    }

    function searchQRByNewAccountV2( $trade_row ,$opt=[] ){
        $old = $this->getOldAccount(   $trade_row ,$opt );
        $wh3=['account_id'=>$old];

        $using= count($old)<30?[]: $this->getTypeTradeUsingLimit() ;
        $using[]=1;             $using[]=11;
        $wh3['type']=  $using;
        $sql= $this->createSql()->select( $this->tb_trade, $wh3 ,[0,3000] ,['account_id','lo']  );
        $tr_lo = $sql->getCol2();
        $account_id= $old;
        //print_r( $old );
        foreach ($account_id as $k => $aid) {
            //if ( isset($tr_lo[$aid]) && !in_array($tr_lo[$aid], $city_arr)) unset($account_id[$k]); // $tr_lo[ $aid]!=$trade_row['lo']
            if ( isset($tr_lo[$aid])  ) unset($account_id[$k]); // $tr_lo[ $aid]!=$trade_row['lo']
        }
        $re=  $this->searchQRByNewAccountFromAccountID($trade_row, $account_id ,$opt  );
        return $re ;
    }
    /**
     *
     * @param $trade_row
     * @param array $opt
     * @return bool|mixed
     * @throws drException
     */
    function searchQRByNewAccount(  $trade_row ,$opt=[] ){
        $wh=[];
        $wh['type']= 211 ;
        if( isset($opt['type']) )  $wh['type']=$opt['type']  ;
        //$wh['type']= 201 ;
        //if( $trade_row['version']==205 ) $wh['type']= 205 ;
        $wh['user_id']= $trade_row['user_id'];
        $wh['online']= [1,4, 11];
        $wh['<=']['fail_cnt']= 8;
        $old=  $this->getAccountIDByWhere(  $wh );
        if( !$old ) {
            unset( $wh['<='] );
            $old=  $this->getAccountIDByWhere(  $wh );
            if(  !$old ) $this->throw_exception("请联系管理员，未上号！",90614001 );
        }



        $city=['北京','天津','上海' ,'重庆'];
        $city_arr=[ $trade_row['lo'] ];
        if( in_array( $trade_row['lo'],$city )) $city_arr[]=  $trade_row['lo'].'市' ;



        $account_id=[];

        $wh3=['account_id'=>$old];

        #3天内的单子
        #$wh3['>']=['ctime'=>( strtotime( date("Y-m-d")) -24*3600*3 ) ];




        //纯新号  #和本地好
        if( !$account_id) {
            //$opt['fail_cnt']=
            $using= count($old)<30?[]: $this->getTypeTradeUsingLimit() ;
            $using[]=1;             $using[]=11;
            $wh3['type']=  $using;
            $sql= $this->createSql()->select( $this->tb_trade, $wh3 ,[0,3000] ,['account_id','lo']  );
            $tr_lo = $sql->getCol2();
            $account_id= $old;
            //print_r( $old );
            foreach ($account_id as $k => $aid) {
                //if ( isset($tr_lo[$aid]) && !in_array($tr_lo[$aid], $city_arr)) unset($account_id[$k]); // $tr_lo[ $aid]!=$trade_row['lo']
                if ( isset($tr_lo[$aid])  ) unset($account_id[$k]); // $tr_lo[ $aid]!=$trade_row['lo']
            }
            $re=  $this->searchQRByNewAccountFromAccountID($trade_row, $account_id ,$opt  );
            if( $re ) return $re ;
            //$this->drExit( $tr_lo );
        }


        //再去找下 曾经的本地号
        //if( !$account_id){
        $wh3['type']=  [1,11];
        $sql= $this->createSql()->select( $this->tb_trade,  $wh3 ,[0,3000] ,['account_id','lo']   );
        $tr_lo = $sql->getAll();
        $account_id2=[];
        //$this->drExit( $tr_lo );
        foreach ( $tr_lo as $v ){
            if(   in_array( $v['lo'] ,$city_arr )  ) $account_id2[ $v['account_id'] ]=$v['account_id'];
        }
        $account_id= array_keys( $account_id2 );
        $re=  $this->searchQRByNewAccountFromAccountID($trade_row, $account_id ,$opt  );
        if( $re ) return $re ;
        //}





        //实在没有就全部上
        //if(  !$account_id) {
            $wh['<=']['fail_cnt']= 20;
            $old=  $this->getAccountIDByWhere(  $wh );
            $account_id= $old;
        //}
        return $this->searchQRByNewAccountFromAccountID($trade_row, $account_id ,$opt  );
        /*
        $qr_account_id = $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id,'fee'=>10086 ],[0,2000],['account_id','qr_id']  )->getCol2();
        return $this->searchQRByYesAccount($trade_row,$qr_account_id ,$opt );
        */

    }

    function searchQRByNewAccountFromAccountID( $trade_row,  $account_id ,$opt ){
        if( !$account_id ) return false ;
        if( in_array( $opt['version'],[40] )){
            $qr_account_id= $this->getQrAccountId17998( $account_id );
        }else {
            $qr_account_id = $this->createSql()->select($this->tb_qr, ['account_id' => $account_id, 'fee' => 10086], [0, 2000], ['account_id', 'qr_id'])->getCol2();
        }
        if( !$qr_account_id ) return false ;
        return $this->searchQRByYesAccount($trade_row,$qr_account_id ,$opt );
    }

    function getQrAccountId17998($account_id){
        if( !is_array($account_id) || !$account_id ) $this->throw_exception("必须为数组",20070901);
        $re=[];
        foreach ( $account_id as $id ) $re[ $id]= 17998;
        return $re ;
    }

    function DsearchQRByLocal( $trade_row ,$opt=[] ){
        $wh=[];
        $wh['type']= 211 ;
        if( isset($opt['type']) )  $wh['type']=$opt['type']  ;
        //$wh['type']= 201 ;
        //if( $trade_row['version']==205 ) $wh['type']= 205 ;

        $wh['user_id']= $trade_row['user_id'];
        $wh['online']= [1,4, 11];
        $wh['lo']=  strtr( $trade_row['lo'], ['市'=>'']);
        $wh['<=']['fail_cnt']= 8 ;

        $account_id =  $this->getAccountIDByWhere(  $wh );
        $re=  $this->searchQRByNewAccountFromAccountID($trade_row, $account_id ,$opt  );
        return $re ;

    }
    function searchQRByLocal( $trade_row ,$opt=[] ){
        if( $trade_row['lo']=='' ) return false ;
        $opt['fail_cnt']= 13;
        $city=['北京','天津','上海' ,'重庆'];
        $where=['lo'=> [$trade_row['lo']],'type'=>[1,11] ,'user_id'=> $trade_row['user_id'] ];
        if( in_array($trade_row['lo'], $city )) $where['lo'][]= $trade_row['lo'].'市';

        $sql= $this->createSql()->select( $this->tb_trade, $where,[0,30],['account_id','qr_id'] ,['trade_id'=>'desc']);

        $cookie_account_id= $sql->getCol2();
        //$this->drExit(  $cookie_account_id  );
        if(  !$cookie_account_id ) return false;

        //$this->drExit( $cookie_account_id );

        return $this->searchQRByYesAccount($trade_row,$cookie_account_id,$opt );

    }

    /**
     * 通个cookie查找 码
     * @param $trade_row
     * @param $opt
     * @return bool
     * @throws drException
     */
    function searchQRByCookie( $trade_row ,$opt=[]  ){
        if( $trade_row['cookie']=='' ) return false ;
        $where=['cookie'=> $trade_row['cookie'],'type'=>[1,11] ,'user_id'=> $trade_row['user_id'] ];

        $sql= $this->createSql()->select( $this->tb_trade, $where,[0,30],['account_id','qr_id'] ,['trade_id'=>'desc']);
        $cookie_account_id= $sql->getCol2();
        if(  !$cookie_account_id ) return false;
        $opt['is_shu']=1 ;
        return $this->searchQRByYesAccount($trade_row,$cookie_account_id,$opt );

        //$this->drExit( $cookie_account_id );

        //if( $opt['ma_user'] ) $this->clearAccountByMaUser($account_id, [4,5,6] );

        //$this->drExit( $qr );
    }

    function getUsingAccountIDByTradeAndYesAccount(  $trade_row , $yesAccount  ){
        $cookie_account_id= $yesAccount;
        $price= $trade_row['realprice'];
        $account_id_using = $this->createSql()->select( $this->tb_trade, ['account_id'=> array_keys( $cookie_account_id ), 'type'=> $this->getTypeTradeUsingLimit(),'realprice'=>$price ]
            , [0,10000],['account_id'])->getCol();

        /*
        //把自己不剔除 这样自己的号会一直安排在 这个号上
        $account_id_using=[];
        $tall = $this->createSql()->select( $this->tb_trade, ['account_id'=> array_keys( $cookie_account_id ), 'type'=> $this->getTypeTradeUsingLimit(),'realprice'=>$price ]
            , [0,10000],['account_id','cookie','trade_id'])->getAll(  );
        if( $tall ){
            foreach ( $tall as  $v ){
                if( $v['cookie']!=$trade_row['cookie'] ) {
                    $account_id_using[ $v['cookie'] ] =$v['cookie'];
                }
            }
            $account_id_using = array_keys( $account_id_using );
        }
        //end 把自己不剔除
        */
        return $account_id_using;
    }

    function searchQRByYesAccount($trade_row, $yesAccount ,$opt=[] ){
        $cookie_account_id= $yesAccount;
        //$price= $trade_row['realprice'];
        $account_id_using= $this->getUsingAccountIDByTradeAndYesAccount($trade_row, $yesAccount);


        $this->clearAccount($cookie_account_id,  $account_id_using ,['iskey'=>1 ]);
        if(  !$cookie_account_id ) return false;

        $wh['online']= [11];
        $wh['account_id']= array_keys( $cookie_account_id );
        $wh['>=']= ['clienttime'=> ( time()- 60 ) ];

        $account_id=[];
        if( $opt['is_shu']){ //熟客
            $wh['online']= [4];
            $account_id= $this->getAccountIDByWhereAndClearMauser( $wh,$trade_row, $opt  );
        }

        if(  $opt['fail_cnt']>0   ) $wh['<=']= ['fail_cnt'=> $opt['fail_cnt'] ];



        if( !$account_id) {
            $wh['online']= [11];
            $account_id= $this->getAccountIDByWhereAndClearMauser( $wh,$trade_row , $opt);
        }
        if( !$account_id ) {
            $wh['online']= [1];
            $account_id= $this->getAccountIDByWhereAndClearMauser( $wh ,$trade_row, $opt );
        }
        if( !$account_id ) return false;

        $ks=[]; //is_rand
        foreach ( $account_id as $k=>$v  ) $ks[]= $k;

        $qr=[];

        if( isset($opt['is_rand'])){
            $k2 = rand(0,  count($ks)-1 );
            $qr= $account_id[ $ks[$k2] ];
        }
        if( !$qr ) {
            foreach ($account_id as $qr) {
                break;
            }
        }
        //因为是一张码 适用直接可用 原来的码
        $qr['qr_id']= $cookie_account_id[ $qr['account_id'] ];

        return $qr;


    }
    function getAccountIDByWhereAndClearMauser( $wh , $trade_row , $opt=[]  ){
        $account_id = $this->getAccountIDByWhere($wh ,['all'=>1 ]);
        if( !$account_id ) return [];
        if( $opt['ma_user'] ) $this->clearAccountByMaUser($account_id, $opt['ma_user'] ,$opt );
        return $account_id;

    }
    //


    function clearAccountByMaUser( &$account, $ma_user ,$opt=[] ){
        if( !$ma_user ) $this->throw_exception("码商异常无法清洗",90613002);
        foreach( $account as $k=>$v  ){

            #允许非码商进来
            //if( $opt['is_zero'] && $v['ma_user_id']==0 ) continue;

            if( ! in_array( $v['ma_user_id'], $ma_user )) unset( $account[$k] );
        }
        return $this;
    }

    function getLiveQrV205( $price, $opt = []){

        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $c_user_id= $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $c_user_id= $wh['user_id']= $this->getCUserIDbyMerchant( $opt );

        $acc= $this->accountPXV2( $c_user_id, $price);
        if( !$acc ) $this->throw_exception("请稍后尝试下单",19112901);
        $account_id= $acc[0][ 'account_id'];

        $this->getLogin()->createTablePayRank()->delByWhere( [ 'account_id'=> $account_id ]);

        $row = $this->createSql()->select( $this->tb_qr, ['account_id'=> $account_id ,'fee'=> 10086   ] )->getRow();


        $this->createSql()->update( $this->tb_qr, ['last_time'=>time() ], ['qr_id'=>$row['qr_id'] ]  )->query() ;
        $this->accountUTime(  $row['account_id'] );
        $row['fee']= $price ;

        if( $row['account_id']<=0  ) $this->throw_exception("请尝试其他金额?",9053109);
        //$this->drExit( $row );
        return $row;

    }

    /**
     * 一位用户只能
     * @param $price
     * @param array $opt
     * @throws drException
     * @return array
     */
    function getLiveQrV201( $price, $opt = []){

        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $c_user_id= $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $c_user_id= $wh['user_id']= $this->getCUserIDbyMerchant( $opt );
        $wh['type']= 201 ;

        if( $c_user_id==356 ) {
            $acc= $this->getLogin()->createQrPay()->accountPX( $c_user_id ,$price ,['clear_account'=>1]);
            if( !$acc ) $this->throw_exception("请过1分钟后重新下单", 19110503 );
        }

        if( $opt['version']==205 )  $wh['type']= 205 ;
        if( $opt['version']==145 )  $wh['type']= 145 ;


        //$wh['online']= [11,1,4 ];
        $wh['online']= [11 ];
        $ma_user= $this->getPayMaUser($c_user_id, $price );
        if(  !$ma_user ) $this->throw_exception("没有符合的码啊" . $wh['type'],9052701);
        $wh['ma_user_id']= $ma_user;

        $wh['>=']['price_max']= $price;

        if( $opt['utime']>0  ){
            $wh['<=']['utime']=  time()- $opt['utime'] ;
        }

        $opt['clear_price']= $price;
        try {
            //$account_id = $this->findBestAccountID($wh, ['clear_price' => $price]);
            $account_id = $this->findBestAccountID($wh,  $opt);
        }catch ( \Exception $ex ){
            $wh['online']= [1 ,4 ];
            //$account_id = $this->findBestAccountID($wh, ['clear_price' => $price]);
            $account_id = $this->findBestAccountID($wh,  $opt);
        }
        //$this->drExit($account_id );
        if( !in_array($opt['version'],[145])) {
            $row = $this->createSql()->select($this->tb_qr, ['account_id' => $account_id, 'fee' => 10086])->getRow();
            $this->createSql()->update($this->tb_qr, ['last_time' => time()], ['qr_id' => $row['qr_id']])->query();
        }else{
            $row=['account_id'=>$account_id,'qr_id'=>10096];
        }
        $this->accountUTime(  $row['account_id'] );

        $row['fee']= $price ;

        if( $row['account_id']<=0  ) $this->throw_exception("请尝试其他金额?",9053109);
        //$this->drExit( $row );
        return $row;
    }

    function findBestAccountID( $where ,$opt=[]){

        //$this->logs_s("ms where ===\n". print_r( $this->createSql()->arr2where($where),true )  ,'debug.log');

        //得到收款账号ID
        $account_id  = $this->getAccountIDByWhere( $where ); //要这个地方 优化下 被检查、亚健康的账号 处理掉
        if(  !$account_id ) $this->throw_exception("请尝试其他金额?",9053101);
        //$this->drExit( $account_id );




        if( $opt['clear_account'] ){ //一单一码
            $account_id_using= $this->createSql()->select( $this->tb_trade, [ 'account_id' => $account_id , 'type'=> $this->getTypeTradeUsingLimit() ]  //,'realprice'=>$price
                , [0,10000],['account_id' ,'account_id'])->getCol2();
            //$this->logs_s( print_r($account_id_using,true ) ,'debug.log');
            $this->clearAccount($account_id, $account_id_using);
        }elseif( $opt['clear_price'] >0 ) {//去价格
            $wh_clear= ['account_id' => $account_id, 'type' => $this->getTypeTradeUsingLimit(), 'realprice' => $opt['clear_price'] ];
            $account_id_using = $this->createSql()->select($this->tb_trade,$wh_clear, [0, 10000], ['account_id'])->getCol();
            $this->clearAccount($account_id, $account_id_using);
        }



        if(  count($account_id)<=0  ) $this->throw_exception("请尝试其他金额?",9053101);

        //$this->logs_s("acc===\n". print_r($account_id,true )  ,'debug.log');
        if( $opt['merchant_id'] == 8395 ) $this->logs_s("[".date('Y-m-d H:i:s')."]acc===".json_encode( $account_id )  ,'debug.log');

        $account_id= array_values( $account_id );

        //$aid= $account_id[ rand(0,count($account_id)-1)];
        $aid= $account_id[ 0 ];




        if( intval($aid)<=0  ) $this->throw_exception("请尝试其他金额?",9053101);
        return $aid;
    }

    function getPayMaUser( $c_user_id,$price ,$opt=[] ){

        $danMin = intval( $this->getLogin()->redisGet( 'danMin'. $c_user_id ) );
        if( $danMin<=0 ) $danMin=500;

        //$this->log("danMin>>".$c_user_id."\t".$danMin );

        $dt_price= $danMin*100+ $price; //500元保证金 //
        /*
        if( in_array( $c_user_id,[3305])){
            $dt_price= 3000*100+ $price; //300元保证金 //
        }elseif( in_array( $c_user_id,[2650]) ){
            $dt_price= 800*100+ $price; //300元保证金 //
        }
        */
        $wh_ma= ['c_user_id'=>$c_user_id ];
        //$wh_ma['>']=[ ];
        $wh_ma['type']=[  10  ]; //审核通过的

        $wh_ma['>']=['live_time'=> (time()-180) ,'amount'=>$dt_price ];

        if( $opt['no_clear'] ||  $opt['no_clear2'] ) unset($wh_ma['>']['live_time']);

        $dbMa= $this->getLogin()->createTableUserMa();
        $ma_user= $dbMa->getColByWhere($wh_ma ,['user_id'] );

        //$this->logs_s("getPayMaUser where ===\n". print_r( $this->createSql()->arr2where($wh_ma),true )  ,'debug.log');
        //$this->logs_s("ma_user ===\n". print_r($ma_user,true )  ,'debug.log');



        if( !$ma_user ) return [];

        if( $opt['no_clear'] ) return $ma_user;

        //清理当前金额
        $wh_trade = ['ma_user_id'=> $ma_user,'price'=>$price,'type'=>$this->getTypeTradeUsingLimit()  ];

        $ma_trade= $this->createSql( )->select($this->tb_trade,$wh_trade,[0,1000],['ma_user_id','price'] )->getCol2();
        if( $ma_trade ){
            foreach ($ma_user as $k=>$v ){
                if( isset( $ma_trade[$v] )) unset( $ma_user[ $k]);
            }
        }
        //if(  !$ma_user ) $this->throw_exception("没有符合的码商",9052703);

        return $ma_user;
    }


    function getLiveQrV13Ma($price ,$opt = []){
        //ma_user_id
        $opt['no_clear']= 1 ; //no_clear
        $c_user_id= $this->getCUserIDbyMerchant( $opt );

        $ma_user= $this->getPayMaUser($c_user_id, $price ,$opt );
        if(  !$ma_user ) $this->throw_exception("请通保证有足够的码商" ,19100102);

        $opt['ma_user_id'] = $ma_user;

        return $this->getLiveQrV3s( $price ,$opt );

    }

    function getLiveQrV30Ma( $price ,$opt = [] ){
        $opt['no_clear']= 1 ; //no_clear
        $c_user_id= $this->getCUserIDbyMerchant( $opt );

        $ma_user= $this->getPayMaUser($c_user_id, $price ,$opt );
        if(  !$ma_user ) $this->throw_exception("请通保证有足够的码商" ,19100102);

        $opt['ma_user_id'] = $ma_user;
        return $this->getLiveQrV30( $price ,$opt );
    }

    /**
     * 一张任意收款码，金额不变 realprice=price
     * @param $price
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getLiveQrV3s( $price ,$opt = []){

        $mc_id_u= $this->getLogin()->midConsole();

        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
        //$wh['>=']= ['clienttime'=> ( time()- $this->liveTime ) ];
        $live=60;
        if( in_array($opt['version'],[13,15 ] ) ){
            $live=30;
        }
        $wh['>=']= ['clienttime'=> ( time()-$live ) ];

        //if( isset($opt['ma_user'] ))   $wh['ma_user_id']= $opt['ma_user'] ;

        if( in_array($opt['version'],[23,28 ] ) ) {
            $wh['type']= 22 ;
        }
        if( $opt['version']==24 ) {
            $wh['type']= 24 ;
        }
        if( $opt['version']==211 ) {
            $wh['type']= 211 ;
        }
        if( $opt['version']==63 ) $wh['type']= [3,363] ;
        if( $opt['version']==90 ) $wh['type']= 90 ;


        if( isset($opt['ma_user_id'])){
            //$this->throw_exception("暂时不可下单！");
            $wh['ma_user_id']=  $opt['ma_user_id']; #
            if( $opt['version']==63 ) $wh['type']= [263,364];
            if( $opt['version']==13  ||  $opt['version']==15 ) $wh['type']= 14;
        }

        //$this->drExit( $wh  );

        $wh['online']= [11,1,4 ];
        $account_id_online = $this->getAccountIDByWhere( $wh );
        if(! $account_id_online ) $this->throw_exception( "无足够的码！", 2018081128);


        if( $opt['clearV1']){ #一码一单
            $account_id_using = $this->createSql()->select($this->tb_trade, ['account_id' => $account_id_online, 'type' => $this->getTypeTradeUsingLimit() ]
                , [0, 10000], ['account_id'])->getCol();

            #$this->log('clearV1>>'. json_encode($account_id_using));
        }else {
            #正在使用这个价格的账号
            $account_id_using = $this->createSql()->select($this->tb_trade, ['account_id' => $account_id_online, 'type' => $this->getTypeTradeUsingLimit(), 'realprice' => $price]
                , [0, 10000], ['account_id'])->getCol();
        }


        if( $price < 10000 ){
            $wh['online']= 4 ; #小额收款
            $account_id_xiao= $this->getAccountIDByWhere( $wh );
            $this->clearAccount($account_id_xiao,  $account_id_using );
        }



        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );
        $this->clearAccount($account_id,  $account_id_using );


        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );
        $this->clearAccount($account_id_zhu ,  $account_id_using );

        if( !$account_id ) $account_id= $account_id_zhu;
        if( !$account_id ) $account_id= $account_id_xiao;
        if (!$account_id) {
            if( $opt['clearV1']){
                $this->throw_exception('没有足够的码!', 20050301);
            }else {
                $this->throw_exception('尝试其他金额!', 2018081228);
            }
        }




        $fee=[10086=>10086];

        #随即 通过使用次数排序获取固码
        $order= ['cnt'=>'ASC','fee'=>'desc']  ;
        $order= [ ]  ;
        $row=[];

        if( $account_id_xiao ){
            $fee_xiao= array_values($fee );
            //$fee_xiao[]=1998;  $fee_xiao[]=1997;  $fee_xiao[]=1996;
            $row = $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id_xiao,'fee'=> $fee_xiao   ] ,[],[], $order)->getRow();
        }
        if( $account_id_zhu &&  !$row ){
            if( count($account_id_zhu)>1 )$order=[  'fee'=>'desc', 'last_time'=>'ASC' ];
            $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id_zhu,'fee'=> array_values($fee )   ] ,[],[], $order)->getRow();
        }
        if( !$row  ) $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id,'fee'=> array_values($fee )   ] ,[],[], $order)->getRow();
        //if( !$row  ) $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id,'fee'=> array_values($fee )   ] ,[],[], $order)->getRow();

        if( !$row )  $this->throw_exception( "请换其他金额试一试！",2018081118);

        $this->createSql()->update( $this->tb_qr, ['last_time'=>time() ], ['qr_id'=>$row['qr_id'] ]  )->query() ;

        $this->accountUTime(  $row['account_id'] );

        //$row['fee']= $this->getPriceRand( $price,$row );

        /*
        if( in_array($opt['version'],[301] )) {
            $opt['xiao_cnt'] = $account_id_xiao?2:1;
            $opt['version'] = 2;
            $row['fee']= $this->getPriceRandV2S( $price,$row , $opt );
        } else */
            $row['fee']= $price ;//$this->getPriceRandV2S( $price,$row  );

        return $row ;
    }

    /**
     * 空码生成 realprice 清价格
     * @param $price
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getLiveQrV30price( $price ,$opt = []){
        if($price<9900 && $opt['merchant_id']== 8133 ){
            //$this->throw_exception("试试其他金额", 5748);
        }
        $mc_id_u= $this->getLogin()->midConsole();

        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
        $wh['>='] = ['clienttime' => (time() - 60 * 3 )];

        if( $opt['version']==28 ) {
            $wh['type']= 22 ;
        }

        if( isset($opt['ma_user_id'])){
            //$this->throw_exception("暂时不可下单！");
            $wh['ma_user_id']=  $opt['ma_user_id']; #
        }

        //$this->drExit( $wh  );

        $wh['online']= [11,1,4 ];
        $account_id_online = $this->getAccountIDByWhere( $wh );
        if(! $account_id_online ) $this->throw_exception( "后端未启动！", 2018081128);


        $account_id_using= $this->createSql()->select( $this->tb_trade, ['account_id'=> $account_id_online, 'type'=> $this->getTypeTradeUsingLimit(),'realprice'=>$price ]
            , [0,10000],['account_id'])->getCol();



        if( $price < 10000 ){
            $wh['online']= 4 ; #小额收款
            $account_id_xiao= $this->getAccountIDByWhere( $wh );
            $this->clearAccount($account_id_xiao,  $account_id_using );
        }



        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );
        $this->clearAccount($account_id,  $account_id_using );


        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );
        $this->clearAccount($account_id_zhu ,  $account_id_using );

        if( !$account_id ) $account_id= $account_id_zhu;
        if( !$account_id ) $account_id= $account_id_xiao;
        if (!$account_id) {
            $this->throw_exception( '尝试其他金额!' , 2018081228);
        }
        $qr = ['qr_id'=>17998 ];
        $qr['account_id']=   $this->getOneAccountId( $account_id_xiao,$account_id_zhu, $account_id ,['first'=>1 ]);;
        $qr['fee']= $price;

        return $qr;
        //$this->drExit($qr );
    }


    /**
     * 空码生成 realprice  默认不清价格，清价格请加clear_price
     * @param $price
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getLiveQrV30( $price ,$opt = []){
        if($price<9900 && $opt['merchant_id']== 8133 ){
            //$this->throw_exception("试试其他金额", 5748);
        }
        $mc_id_u= $this->getLogin()->midConsole();

        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
        $wh['>='] = ['clienttime' => (time() - 60 * 3 )];

        if( $opt['version']==45 ) {
            $wh['type']= 45 ;
        }elseif( $opt['version']==90 ) { $wh['type']= 90 ;
        }elseif( $opt['version']==22 ) {
            $wh['type']= 22 ;
        }elseif( $opt['version']==139 || $opt['version']==138 ) {
            $wh['type']= 139 ;
        }elseif( $opt['version']==39 ) {
            $wh['type']= 39 ;
        }elseif( $opt['version']==38 ) {
            $wh['type']= 38 ;
        }elseif( $opt['version']==40 ) {
            $wh['type'] = [ 4,47,48];
        }elseif( $opt['version']==50 ) {
            $wh['type']= 50 ;
            unset( $wh['>='] );
        }

        if( isset($opt['ma_user_id'])){
            //$this->throw_exception("暂时不可下单！");
            $wh['ma_user_id']=  $opt['ma_user_id']; #
            $wh['type']= 14;
            if( $opt['version']==40 ) {
                $wh['type'] = [147, 148];
            }
            if(  $opt['version']==39 ){
                $wh['type'] = 239;
            }
        }

        if( $opt['account_type2']){
            $wh['type'] =  $opt['account_type2'] ;
        }


        if( $price < 10000 ){
            $wh['online']= 4 ; #小额收款
            $account_id_xiao= $this->getAccountIDByWhere( $wh ); //,['dan'=>1]
        }
        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh ); //,['dan'=>1]

        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );//,['dan'=>1]

        if( $opt['clear_price'] ){ #清价格
            $wh['online']= [11,1,4 ];
            $account_id_online = $this->getAccountIDByWhere( $wh );
            if(! $account_id_online ){
                #$this->log("mahao 20062105 >>". $this->createSql()->arr2where( $wh));
                $this->throw_exception( "请上号上码！", 20062105);
            }
            $account_id_using= $this->createSql()->select( $this->tb_trade, ['account_id'=> $account_id_online, 'type'=> $this->getTypeTradeUsingLimit(),'realprice'=>$price ]
                , [0,10000],['account_id'])->getCol();
            $this->clearAccount($account_id,  $account_id_using );
            $this->clearAccount($account_id_zhu,  $account_id_using );
            $this->clearAccount($account_id_xiao,  $account_id_using );

        }
        if( !$account_id ) $account_id= $account_id_zhu;

        if( !$account_id ) $account_id= $account_id_xiao;

        if (!$account_id){
            #$this->log("mahao 20062106 >>". $this->createSql()->arr2where( $wh));
            $this->throw_exception($opt['clear_price']?'请尝试其他金额':"后端未启动！", 20062106);
        }

        //$account = $this->getOneAccountId( $account_id_xiao,$account_id_zhu, $account_id);

        $qr = ['qr_id'=>17998 ];
        $qr['account_id']=   $this->getOneAccountId( $account_id_xiao,$account_id_zhu, $account_id,['first'=>1 ] );;
        $qr['fee']= $price;
        //$account = $this->getOneAccountId( $qr['account_id'] );
        //$qr['account_ali_uid']= $account['ali_uid'];

        return $qr;
        //$this->drExit($qr );
    }

    function getAccountIDByMerchantId( $merchant_id, $online ,$opt=[] ){
        $mc_id_u= $this->getLogin()->midConsole();
        $wh=[];
        $wh['user_id']= $mc_id_u[ $merchant_id];
        $opt['merchant_id']= $merchant_id;
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;
        $wh['online']= $online ; #4小额收款
        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
        $wh['>=']= ['clienttime'=> ( time()- 60*60*5 ) ];

        $account_id = $this->getAccountIDByWhere( $wh );
        return $account_id;
    }

    /**
     * 一张收款码 任意二维码
     * realprice 金额变动
     * @param $price
     * @param array $opt
     * @return array
     * @throws drException
     */
    function getLiveQrV3( $price ,$opt = [] ){
        if( !isset($opt['no_check']) ) {
            $p_arr = [1999, 4999, 9999, 19999, 49999, 99999, 199999, 299999];
            if (!in_array($price, $p_arr)) $this->throw_exception("试试其他金额", 7894);
        }

        $mc_id_u= $this->getLogin()->midConsole();

        $wh=[];
        $wh['user_id']= $mc_id_u[$opt['merchant_id']];
        $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

        $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
        //$wh['>=']= ['clienttime'=> ( time()- $this->liveTime ) ];
        //$wh['>=']= ['clienttime'=> ( time()- 60*60*5 ) ];
        $wh['>=']= ['clienttime'=> ( time()- 60*5 ) ];


        if( isset($opt['ma_user_id'])){
            //$this->throw_exception("暂时不可下单！");
            $wh['ma_user_id']=  $opt['ma_user_id']; #
            $wh['type']= 14;
        }

        //$this->drExit( $wh  );

        if( $price < 10000 ){
            $wh['online']= 4 ; #小额收款
            $account_id_xiao= $this->getAccountIDByWhere( $wh );
        }



        $wh['online']= 1 ; #// [1,4 ]; #备线
        $account_id = $this->getAccountIDByWhere( $wh );


        $wh['online']= 11; #主线
        $account_id_zhu = $this->getAccountIDByWhere( $wh );
        if( !$account_id ) $account_id= $account_id_zhu;

        if( !$account_id ) $account_id= $account_id_xiao;

        if (!$account_id) $this->throw_exception("后端未启动！", 2018081128);


        $fee=[10086=>10086];

        #随即 通过使用次数排序获取固码
        $order= ['cnt'=>'ASC','fee'=>'desc']  ;
        $order= [ ]  ;
        $row=[];

        if( $account_id_xiao ){
            $fee_xiao= array_values($fee );
            //$fee_xiao[]=1998;  $fee_xiao[]=1997;  $fee_xiao[]=1996;
            $row = $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id_xiao,'fee'=> $fee_xiao   ] ,[],[], $order)->getRow();
        }
        if( $account_id_zhu &&  !$row ){
            if( count($account_id_zhu)>1 )$order=[  'fee'=>'desc', 'last_time'=>'ASC' ];
            $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id_zhu,'fee'=> array_values($fee )   ] ,[],[], $order)->getRow();
        }
        if( !$row && $price>9800 ) $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id,'fee'=> array_values($fee )   ] ,[],[], $order)->getRow();
        //if( !$row  ) $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id,'fee'=> array_values($fee )   ] ,[],[], $order)->getRow();

        if( !$row )  $this->throw_exception( "请换其他金额试一试！",2018081118);

        $this->createSql()->update( $this->tb_qr, ['last_time'=>time() ], ['qr_id'=>$row['qr_id'] ]  )->query() ;

        //$row['fee']= $this->getPriceRand( $price,$row );
        $row['fee']= $this->getPriceRandV2S( $price,$row  );

        return $row ;

    }

    function getYesPrice($price){
        if( $price <=1900 ) return [ $price  ];

        $cnt= ceil( 0.05*$price/100 ); #优化金额
        if( $price%10==0 ) $price--;
        if( $cnt>50 )  $cnt=50;
        $yes=[];
        $e =   $price-$cnt ;
        for (  $i =  $price ; $i > $e; $i--) { #去已经存在的价格
            if( $i%10 ) $yes[]=$i;

        }
        if( !$yes ) return [ $price  ];
        return $yes;
    }

    function getPriceRandV2S($price , $trade_row ,$opt=[]){

        if( $trade_row['fee']!=10086 && $opt['version']!=2) $this->throw_exception( '该码不可用',458);
        $qr_id= $trade_row['qr_id'] ;


        $where = [ 'qr_id'=>$qr_id,'type'=> $this->getTypeTradeUsingLimit() ]; ;//$where= '1';

        if($opt['version']==2 ) $where['account_id']= $trade_row['account_id'] ;

        //当前码当前竞争
        $qr_row = $this->createSql()->select( $this->tb_trade,$where ,[0,1000],['realprice','realprice'])->getCol2();
        //$this->drExit( $qr_row );

        $xiao_cnt= 1;
        if( $opt['xiao_cnt']>1 ) $xiao_cnt = $opt['xiao_cnt'];

        //$this->logs_s( "price_60V3=".$price."\n" ,'debug.log');

        //$i= 100* (intval($price/100)+1)-1;
        $old_price= $price;
        if($price%100==0) $price= $price-1;
        //$this->logs_s( "price_60V4=".$price."\n" ,'debug.log');
        $p_cnt = [1999=> $xiao_cnt ,4999=>16,9999=>17,19999=>17,49999=>18,99999=>18,199999=>18,299999=>18];
        $p_cnt = [1999=> 10 ,4999=>16,9999=>17,19999=>17,49999=>18,99999=>18,199999=>18,299999=>18];

        $cnt= isset( $p_cnt[$price] )?$p_cnt[$price]:18 ;

        $yes=[];
        if( $price<=2000 ) $cnt=1;
        if( $price<=1900 ) $cnt=1;

        if( $cnt>1 && $trade_row['fee']==10086){ //

            $cnt=50;

            /*
            if( $opt['merchant_id']==8277 ){
                $cnt= ceil( 0.05*$price/100 );
                if($cnt<=0){
                    $price++;
                    $cnt=1;
                }
            }elseif( $price<=2000 ){
                $price++;
                $cnt=1;
            }
            elseif( $price<=6000 )$cnt=10;
            elseif( $price<=10000 )$cnt=15;
            elseif( $price<=20000 )$cnt=20;
            elseif( $price<=50000 )$cnt=30;
            */
            $cnt= ceil( 0.05*$price/100 );
            if( $cnt>50 ){
                $cnt=50;
            }
            if($cnt<=0){
                $cnt=1;
            }else{
                if($old_price%100!=0) $price--;
            }

            if( 8133== $opt['merchant_id'] ){
                $cnt=70;
            }
        }

        $e =   $price-$cnt ;
        for (  $i =  $price ; $i > $e; $i--) { #去已经存在的价格
            if (!isset($qr_row[$i])){
                if( $trade_row['fee']==10086) {
                    if( $i%10 ) $yes[]=$i;
                }else{
                    $yes[]=$i;
                }
                //return $i;
            }
        }

        /*

        if( $opt['version']==2 ){
            $e =   $price-$cnt ;
            for (  $i =  $price ; $i > $e; $i--) {
                if (!isset($qr_row[$i])){
                    $yes[]=$i;
                    //return $i;
                }
            }
        }else{
            $i = 100 * intval($price / 100)+1;
            $e = $i + $cnt ;
            for ( ; $i < $e; $i++) {
                if (!isset($qr_row[$i])){
                    $yes[]=$i;
                    //return $i;
                }
            }
        }
        */
        if( !$yes ) {

            $this->logs_s("yes_price===".$price."==".$opt['merchant_id']."==". $trade_row['account_id'] ."==\n"  ,'debug.log');
            $this->throw_exception( "请试一试其他金额！",458);
        }


        //$this->logs_s("yes_price===".$price."==".$trade_row['fee']."==\n". print_r($yes,true )  ,'debug.log');

        //unset( $where['type'] );

        if( $trade_row['fee']==10086) $fee=$yes[ rand(0, count($yes)-1) ];
        else $fee = $this->getPriceBest( $yes, $where );
        if( !$fee) $fee= $yes[0];

        //$this->logs_s("yes_fee2===".$fee."=="   ,'debug.log');

        //$where['account_id']= $trade_row['account_id'] ;
        //$this->accountUTime( $trade_row['account_id'] );
        return $fee;
    }

    function accountUTime( $account_id ){
        $this->update( $this->tb_account, ['account_id'=> $account_id ] ,['utime'=>time() ]);
        return $this;
    }

    function getPriceBest( $yes, $where){
        unset( $where['qr_id'] );
        $where['type']=[2,1,11]; //只选超时,成功，补单
        //$where['realprice']= $yes;
        $where['>=']['ctime']= time()-3600;
        $tr_all = $this->createSql()->select( $this->tb_trade,$where ,[0,1000],['realprice','trade_id' ],['trade_id'=>'asc'])->getCol2();
        $yes2=[];
        $i=1;
        foreach( $yes as $v ) {
            $yes2[$v]=  isset($tr_all[$v])? intval( $tr_all[$v] ): $i++ ;
        }
        //$this->drExit( $yes2 );
        asort( $yes2 ) ;

        $yes3= array_keys($yes2 );

        //$this->drExit( $yes3 );
        $bestFee=$yes3[0];
        //$this->assign('tr_all', $tr_all )->assign('yes',$yes )->assign('yes2',$yes2)->assign('bestFee',$bestFee );
        return $bestFee;
    }

    function getPriceRandV2($price , $trade_row ,$opt=[] ){



        if( $trade_row['fee']!=10086 && $opt['version']!=2) $this->throw_exception( '该码不可用',458);
        $qr_id= $trade_row['qr_id'] ;

        $where = [ 'qr_id'=>$qr_id,'type'=> $this->getTypeTradeUsingLimit() ]; ;//$where= '1';

        if($opt['version']==2 ) $where['account_id']= $trade_row['account_id'] ;

        $qr_row = $this->createSql()->select( $this->tb_trade,$where ,[0,1000],['realprice','realprice'])->getCol2();
        //$this->drExit( $qr_row );

        $xiao_cnt= 1;
        if( $opt['xiao_cnt']>1 ) $xiao_cnt = $opt['xiao_cnt'];

        //$i= 100* (intval($price/100)+1)-1;
        $p_cnt = [1999=> $xiao_cnt ,4999=>2,9999=>5,19999=>7,49999=>10,99999=>10,199999=>10,299999=>10];

        $cnt= isset( $p_cnt[$price] )?$p_cnt[$price]:5  ;

        if( $opt['version']==2 ){
            $e =   $price-$cnt ;
            for (  $i =  $price ; $i > $e; $i--) {
                if (!isset($qr_row[$i])) return $i;
            }
        }else{
            $i = 100 * intval($price / 100)+1;
            $e = $i + $cnt ;
            for ( ; $i < $e; $i++) {
                if (!isset($qr_row[$i])) return $i;
            }
        }

        $this->throw_exception( "请试一试其他金额！",458);


        /*
        if( $price ==9999 ){
            $i = intval($price / 100) +1 ;
            $e = $i + $cnt;
            for (; $i < $e; $i++) {
                $ik = $i * 100;
                if (!isset($qr_row[$ik])) return $ik;
            }

        }else {
            $i = intval($price / 100);
            $e = $i - $cnt;
            for (; $i > $e; $i--) {
                $ik = $i * 100;
                if (!isset($qr_row[$ik])) return $ik;
            }
        }
        if( $price>=4999 ){


        }else {
            $this->throw_exception( "请试一试其他金额！",458);

        }
        */


    }


    function getPriceRand( $price , $trade_row){


        if( $trade_row['fee']!=10086) $this->throw_exception( '该码不可用',458);
        $qr_id= $trade_row['qr_id'] ;

        $where = [ 'qr_id'=>$qr_id,'type'=> $this->getTypeTradeUsingLimit() ]; ;//$where= '1';

        $qr_row = $this->createSql()->select( $this->tb_trade,$where ,[0,1000],['realprice','realprice'])->getCol2();
        //$this->drExit( $qr_row );

        //$i= 100* (intval($price/100)+1)-1;
        $p_cnt = [1999=>1 ,4999=>3,9999=>5,19999=>7,49999=>10,99999=>10,199999=>20,299999=>20];

        $cnt= isset( $p_cnt[$price] )?$p_cnt[$price]:5  ;
        if( $price ==9999 ){
            $i = intval($price / 100) +1 ;
            $e = $i + $cnt;
            for (; $i < $e; $i++) {
                $ik = $i * 100;
                if (!isset($qr_row[$ik])) return $ik;
            }

        }else {
            $i = intval($price / 100);
            $e = $i - $cnt;
            for (; $i > $e; $i--) {
                $ik = $i * 100;
                if (!isset($qr_row[$ik])) return $ik;
            }
        }

        /*
        if( $price>=4999 ){


        }else {
            $this->throw_exception( "请试一试其他金额！",458);
            $i = 100 * intval($price / 100);
            $e = $i + $cnt ;
            for (; $i < $e; $i++) {
                if (!isset($qr_row[$i])) return $i;
            }
        }
        */
        $this->throw_exception( "请试一试其他金额！",458);
    }

    /**
     * 获取二维码调度程序
     * @param $price
     * @param $opt
     * @return array
     * @throws drException
     */
    function getLiveQr( $price ,$opt = []){
        //$mins= intval( date("HH"));
        $min = intval( date("Hi"));
        if( $min>2356 || $min<3 ) $this->throw_exception( "隔日结算中",1151);

        $no_limit = [ 8088 ]; # 8111 不限制19元的商户

        $is_19_limit = false ;// !in_array( $opt['merchant_id'] , $no_limit);


        $p_arr= [1999,4999,9999,19999,49999,99999,199999,299999];
        if( !in_array( $price,$p_arr ) )$this->throw_exception( "试试其他金额",7894);

        #if( $price< 1900 and $price>100 )   $this->throw_exception( "试试其他金额",7894);



        try {
            if ($price >= 100) $fee = $this->getMoneyConfig(intval($price / 100 + 0.5) * 100, ['display' => 'group']);
            else  $fee = $this->getMoneyConfig($price, ['display' => 'group']);
        }catch (drException $e ){
            $fee = $this->getMoneyConfigXl( $price );
        }



        $mc_id_u= $this->getLogin()->midConsole();

        $account_id_xiao=[];

        if( $opt['merchant_id'] && isset(  $mc_id_u[$opt['merchant_id']]) ){
            $wh=[];
            $wh['user_id']= $mc_id_u[$opt['merchant_id']];
            $wh['user_id']=  $this->getCUserIDbyMerchant( $opt );;

            //$wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;
            $wh['type']= isset($opt['account_type'])? $opt['account_type'] :1 ;

            $wh['>=']= ['clienttime'=> ( time()- $this->liveTime ) ];

            if( $opt['is_debug_version'])    $wh['>=']= ['clienttime'=> ( time()- 60*60*5 ) ];

            if( $price < 10000 ){
                $wh['online']= 4 ; #小额收款
                $account_id_xiao= $this->getAccountIDByWhere( $wh );
            }



            $wh['online']= 1 ; #// [1,4 ]; #备线
            $account_id = $this->getAccountIDByWhere( $wh );


            $wh['online']= 11; #主线
            $account_id_zhu = $this->getAccountIDByWhere( $wh );
            if( !$account_id ) $account_id= $account_id_zhu;

            if( !$account_id ) $account_id= $account_id_xiao;

        }else {
            #筛选账号 深度休眠的情况下 10~20分钟更新一次
            $sql = "select account_id from " . $this->tb_account . " where clienttime>" . $this->getTimeLimit(120);
            $account_id_zhu=$account_id = [1]; //$this->createSql($sql)->getCol();

        }
        if (!$account_id) $this->throw_exception("后端未启动！", 2018081128);




        #随即 获取固码
        /*
        $tall = $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id,'fee'=> array_values($fee ) , 'lock_time'=>0 ],[0,200] )->getAll();
        if( !$tall )  $this->throw_exception( "请换其他金额试一试！",2018081117);
        $ik= rand(1, count($tall));
        $row= $tall[ $ik-1 ];
        */



        #把金额大的书干掉
        foreach($fee as $k=>$v ) if( $v> $price ) unset(  $fee[$k]);

        //$this->log("fee  \n".$price ."\n". print_r($fee,true ) );

        if( ! $fee ) $this->throw_exception( "请换其他金额试一试！",1120);

        #随即 通过使用次数排序获取固码
        $order= ['cnt'=>'ASC','fee'=>'desc']  ;
        $order= ['last_time'=>'ASC','fee'=>'desc']  ;
        $row=[];

        if( $account_id_xiao ){
            $fee_xiao= array_values($fee );
            $fee_xiao[]=1998;  $fee_xiao[]=1997;  $fee_xiao[]=1996;
            $row = $this->createSql()->select( $this->tb_qr, ['account_id'=>$account_id_xiao,'fee'=> $fee_xiao , 'lock_time'=>0 ] ,[],[], $order)->getRow();
        }

//        if( $price>10 && $price<2100 &&  $is_19_limit  && !$row)  { #限制19.99
//            if( rand(1,3)==2) $this->throw_exception( "请换其他金额试一试",2018081118);
//        }

        if( $account_id_zhu &&  !$row ){
            if( count($account_id_zhu)>1 )$order=[  'fee'=>'desc', 'last_time'=>'ASC' ];
            $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id_zhu,'fee'=> array_values($fee ) , 'lock_time'=>0 ] ,[],[], $order)->getRow();
        }
        if( !$row  ) $row = $this->createSql()->select( $this->tb_qr,['account_id'=>$account_id,'fee'=> array_values($fee ) , 'lock_time'=>0 ] ,[],[], $order)->getRow();

        if( !$row )  $this->throw_exception( "请换其他金额试一试！",1119);

//        if( $price>10 && $price<2100 && ( time()-$row['last_time'])< 120 &&    $is_19_limit)  {
//              $this->throw_exception( "请换其他金额试一试",2018081118);
//        }

        #加锁 方式串起来
        $this->update( $this->tb_qr,['qr_id'=>$row['qr_id']  ], ['lock_time'=>time(),'last_time'=>time(),'+'=>['cnt'=>1]]);

        return $row;
    }

    /**
     * 获取相邻价位
     * @param $price
     * @return mixed
     */
    function getMoneyConfigXl( $price ){
        $fee= $this->getMoneyConfig(  'all' ,['display'=>'group']);
        $kol=1;
        foreach ( $fee as $k=>$v ){
            if( $kol<$price &&  $k<$price) {
                $kol = $k;
                continue;
            }
            break;
        }
        return $fee[ $kol];
    }

    function createTrade(  $trade){
        if( !$trade['goods_name'] )  $this->throw_exception( "请填写商品名称",2018081120);
        if( !$trade['notify_url'] )  $this->throw_exception( "notify_url 不可为空",2018081120);
        if( !$trade['order_no'] )  $this->throw_exception( "order_no 不可为空",2018081120);
        if( !$trade['price'] )  $this->throw_exception( "价格不可为空",2018081120);
        $this->getTypePay( $trade['pay_type'] ) ;
        $trade['ctime']= time();
        $this->insert( $this->tb_trade , $trade , $this->file_trade );
        return $this;
    }
    function getTradeByOrderNo( $mc_id, $order_no){
        $row= $this->createSql()->select( $this->tb_trade,['merchant_id'=> $mc_id,'order_no'=>$order_no ])->getRow();
        if( $row['pay_log_id'] ) $this->throw_exception( '订单:'.$order_no.'，已支付成功！',2018081122);
        return $row;
    }

    function updateClientTime( $account_id  ,$opt=[]){
        $online= $opt['online']==3?3:1 ;
        if( $opt['account'] ) $acc= $opt['account'];
        else $acc= $this->getAccountByID( $account_id);
        //$var = ['clienttime'=>time(),'online'=> $online ];
        $var = ['clienttime'=>time()  ];
        if( $acc['online']==2 ||  $acc['online']==11 ) unset( $var['online']);
        $this->update( $this->tb_account,['account_id'=> $account_id], $var);

        if( in_array( $acc['type'],[47,147] )){
            $this->update( $this->tb_account,['ali_uid'=> $account_id ,'type'=>[48,148]], $var);
        }
        //if( $acc['online']==2 )  $this->throw_exception( "账号被禁用，登录控制台解禁！",2018081146);
        return $this;
    }

    function updateClientTimeV2( $account ){
        $tb = 'pay_account_online';
        $where= ['account_id'=>$account['account_id'],'type'=> $account['type'] ];
        $acc= $this->createSql()->select( $tb, $where )->getRow();
        if($acc) $this->update( $tb, $where , ['clienttime'=>time()  ] );
        else{
            $where['clienttime']=  time();
            $this->insert( $tb, $where);
        }
        return $this;
    }
    /*
    function getAccountOnlineByID( $account_id){
        $tb = 'pay_account_online';
        return $this->createSql()->select($tb, ['account_id'=> $account_id  ])->getRow();
    }
    */

    function updateClientTimeByMaUid( $ma_user_id ,$opt=[] ){


        //$online=[1,11];
        #$where=['ma_user_id'=> $ma_user_id ,'online'=>[1,11,4 ],'type'=>[201,205]  ];
        $where=['ma_user_id'=> $ma_user_id ,'online'=>[1,11,4 ],'type'=>$this->getLogin()->getTypeCanUpTimeByMaUser()   ];
        if( $opt['account_id'] ){
            $where=['ma_user_id'=> $ma_user_id ,'account_id'=>$opt['account_id'] ];
        }
        $var = ['clienttime'=>time()  ];
        $this->update( $this->tb_account, $where , $var);
        return $this;
    }

    function updateClientTimeByCardIndex($card_index, $type=24 ){
        $var = ['clienttime'=>time()  ];
        $this->update( $this->tb_account,['card_index'=> $card_index,'type'=>$type ], $var);
        return $this;
    }

    function getTradeByID( $trade_id ){
        $row = $this->createSql()->select( $this->tb_trade,['trade_id'=>$trade_id])->getRow();
        if( ! $row )  $this->throw_exception( "哎呀，不存在该记录！(".$trade_id.")" ,201808112301);
        return $row;
    }
    function getTradeRowByWhere( $where ){
        $row = $this->createSql()->select( $this->tb_trade, $where )->getRow();
        if( ! $row )  $this->throw_exception( "哎呀，不存在该记录！",2018081124 );
        return $row;

    }

    function getTradeByWhere( $where ,$opt=[] ){
        $limit = [];
        $order=  [];
        if( $opt['limit'])  $limit= $opt['limit'];

        if( $opt['order'])  $order= $opt['order'];

        $row = $this->createSql()->select( $this->tb_trade, $where,$limit,[],$order   )->getAll();
        //$this->assign('trade2',$row );
        //if( ! $row )  $this->throw_exception( "哎呀，不存在该记录！",90527002 );
        return $row;
    }

    /**
     * 处理超时情况
     * @return $this
     * @throws \Exception
     */
    function timeOut(){
        #10分钟内清除锁码超时
        $sql="update " .$this->tb_qr." set  `lock_time`=0  where lock_time<" .$this->getTimeLimit(9) ;
        $this->createSql($sql)->query();

        #10分钟内 将
        $sql= "update ".$this->tb_trade." set type=2 where type in(0,3,4,5) and ctime<". $this->getTimeLimit(9) ;
        $this->createSql($sql)->query();

        $uid='324,4,1211,1040,784,1633,2337,3125,4368,4649,4761,5107'; //M54749 4335

        if($uid ){

            #7分钟内清除锁码超时
            $sql="update " .$this->tb_qr." set  `lock_time`=0  where lock_time<" .$this->getTimeLimit(6) . ' and  user_id in ( '. $uid.')';
            $this->createSql($sql)->query();

            #7分钟内 将
            $sql= "update ".$this->tb_trade." set type=2 where type in(0,3,4) and ctime<". $this->getTimeLimit(6) . ' and  user_id in ( '. $uid.')' ;
            $this->createSql($sql)->query();
        }
        $uid2='356,2323,2650,3305,3310,3349,3849';
        if($uid2 ){
            #7分钟内清除锁码超时
            $sql="update " .$this->tb_qr." set  `lock_time`=0  where lock_time<" .$this->getTimeLimit(4) . ' and  user_id in ( '. $uid2.')';
            $this->createSql($sql)->query();
            #7分钟内 将
            $sql= "update ".$this->tb_trade." set type=2 where type in(0,3,4) and ctime<". $this->getTimeLimit(4 ) . ' and  user_id in ( '. $uid2.')' ;
            $this->createSql($sql)->query();
        }


        return $this;
    }

    function timeOutV4($type='4,0',$limit_fen=2 ){

        $sql= "update ".$this->tb_trade." set type=2 where type in( ".$type.") and ctime<". $this->getTimeLimit($limit_fen ) ;
        $this->createSql($sql)->query();
        return $this;
    }
    function getTimeLimit( $fenZhong=10){
        $time= time() -$fenZhong*60; #4分钟
        return $time ;
    }

    /**
     * @param array $type_from
     * @param int $up_type
     * @param int|array $trade_type
     * @return $this
     * @throws drException
     */
    function timeOut78( $type_from=[76,78] ,$up_type=78 ,$trade_type=2 ){

        $tb_paylog= $this->getLogin()->createTablePayLogTem()->getTable();
        $pt_id = $this->createSql()->select( $tb_paylog, ['type'=> $type_from,'!='=>['ali_beizhu'=>''] ],[0,2000],['ali_beizhu', 'pt_id'])->getCol2();

        if(! $pt_id ) return $this;

        #获取超时的
        $trade = $this->createSql()->select( 'mc_trade', ['trade_id'=> array_keys($pt_id ),'type'=>$trade_type ],[0,1000],['trade_id'],['trade_id'=>'desc'])->getCol();
        if( ! $trade) return $this;

        $id_arr = [];
        foreach ( $trade as $id ) $id_arr[]=$pt_id[ $id ] ;

        //$sql = $this->createSql()->update($tb_paylog,['type'=>78,'ali_beizhu'=>'' ],['ali_beizhu'=> $trade] )->getSQL();
        $this->createSql()->update($tb_paylog,['type'=>$up_type,'ali_beizhu'=>'' ],['pt_id'=> $id_arr] )->query();//->getSQL();

        return $this;

    }


    /**
     * 把旧的二维码用起来
     * @param $where
     * @param $upvar
     * @param array $opt
     * @return $this
     */
    function payTemOldQrSet( $where, $upvar ,$opt=[]){ //,$trade_id
        /*
        $where=['account_id'=> $account_id,'fee'=>$fee,'type'=>$type,'ali_beizhu'=>''];
        if( $opt['timeout']){
            $where['>']['ctime']= time()- $opt['timeout'];
        }
        */
        $row = $this->getLogin()->createTablePayLogTem()->getRowByWhere( $where );
        if($row){
            $this->getLogin()->createTablePayLogTem()->updateByKey($row['pt_id'],$upvar);
        }
        return $this;
    }

    function payTemTimeOut( $type, $to_type, $timeout=1800){
        $where=[];
        $where['<']['ctime']= time()- $timeout ;
        $where['type']=  $type;
        $this->getLogin()->createTablePayLogTem()->updateByWhere( $where,['type'=> $to_type ]);
        return $this;
    }

    /**
     * 话费
     * @return $this
     * @throws drException
     */
    function timeOut320(){

        $wh= ['type'=>10];

        $trade_id = $this->getLogin()->createTableHfTrade()->getColByWhere( $wh ,['trade_id']);
        //5构建成功 6不可构建  10支付中 11支付成功
        if( !$trade_id ) return $this;

        $trade = $this->createSql()->select( 'mc_trade', ['trade_id'=>  $trade_id,'type'=>2  ],[0,1000],['trade_id'],['trade_id'=>'desc'])->getCol();
        if( ! $trade) return $this;
        $wh2 =$wh3=['trade_id'=> $trade];
        $wh2['>']['endtime']= time();
        $this->getLogin()->createTableHfTrade()->updateByWhere( $wh2 , ['type'=>5,'trade_id'=>0 ]);

        $wh3['<']['endtime']= time();
        $this->getLogin()->createTableHfTrade()->updateByWhere( $wh3 , ['type'=>21  ]);
        return $this;
    }

    /**
     * 淘宝代付
     * @return $this
     * @throws drException
     */
    function timeOut80(){

        return $this;

        $trade_id = $this->getLogin()->createTableTaobaoQr()->getColByWhere(['type'=>11] ,['trade_id']);
        if( !$trade_id ) return $this;
        #获取超时的
        $trade = $this->createSql()->select( 'mc_trade', ['trade_id'=>  $trade_id,'type'=>2  ],[0,1000],['trade_id'],['trade_id'=>'desc'])->getCol();
        if( ! $trade) return $this;
        $this->getLogin()->createTableTaobaoQr()->updateByWhere(['trade_id'=> $trade] , ['type'=>10,'trade_id'=>0 ]);
        return $this;
    }

    function timeOut201(){

        $where = ['type'=>11 ]; //12成功 13失败
        $tall_bill = $this->getLogin()->createTableMaBill()->getColByWhere($where,['beizhu','mb_id'] );
        if(! $tall_bill ) return $this;
        //$this->drExit( $tall_bill );

        $trade = $this->getLogin()->createTableTrade()->getColByWhere(['trade_id'=> array_keys( $tall_bill),'type'=>[1,11,2] ] , ['trade_id','type']);
        if(! $trade) return $this ;
        foreach ($trade as $trade_id=>$type){
            $mb_id = $tall_bill[$trade_id];
            //$me_user_id=
            if( !$mb_id) continue;
            switch ($type){
                case 1:#正常回调
                    $this->getLogin()->createVip()->fenLun( $trade_id ,['no_check'=>1] );
                    break;
                case 11:#补单
                    $this->getLogin()->createVip()->maBillUpdate( $mb_id,51,['no_check'=>1]);
                    $this->getLogin()->createVip()->fenLunReal( $trade_id );
                    break;
                case 2:
                    $this->getLogin()->createVip()->maBillUpdate( $mb_id,13,['no_check'=>1]);
                    break;
            }
        }
        //$this->drExit( $trade );

        return $this;

    }



    /**
     * 支付撮合V2
     * 1.转账支付 备注带上
     * 2.支付账单 获取账单记录
     * 3.无超时限制
     * @param $log
     * @return $this
     * @throws drException
     */
    function payMatchByLogV2( $log ){
        if( $log['opt_id'] <=0 )  $this->throw_exception( "非法支付无法撮合",4081 );
        $trade_id = $log['opt_id'];
        $tr_row = $this->getLogin()->createQrPay()->getTradeByID($trade_id );
        if( !$tr_row )  $this->throw_exception( "未找到订单！",4083 );
        if( $tr_row['pay_log_id']>0  ){
            $this->throw_exception( "该订单以及被支付过",4082 );
        }
        if( $tr_row['realprice']!= $log['fee'] ) $this->throw_exception( "支付金额被用户修改过！ ",4084 );

        $pay_log_id= $log['id'];

        #修改交易记录 把支付信息更新
        $this->update( $this->tb_trade,['trade_id'=>$tr_row['trade_id']], ['pay_time'=> $log['ctime'], 'type'=>1,'pay_log_id'=>$pay_log_id ] );
        #解锁固码
        $this->update( $this->tb_qr, ['qr_id'=>$tr_row['qr_id'] ], [ 'lock_time'=>0 ,'+'=>['cnt_success'=>1] ]);
        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $tr_row['qr_id'] , 'trade_id'=>$tr_row['trade_id'] ]);

        #之后应该去执行 notify
        $tr_row['pay_time']= $log['ctime'];
        $tr_row['type']= 1 ;
        $tr_row['pay_log_id']=  $pay_log_id ;
        try{
            $this->toMqTrade( $tr_row );
        }catch ( \Exception $ex ){  }
        return $this;
    }

    function payMatchByLogV45($log ,$opt=[]){
        $pay_log_id= $log['id'];
        $account = $this->getAccountByID(  $log['account_id'] );
        $using = $this->getTypeTradeUsing();

        if( !$log['buyer'] ) $this->throw_exception( "记录错误 未添加汇款者",19003 );

        $where = ['account_id'=>$log['account_id'],'buyer'=>$log['buyer'],'type'=>$using,'realprice'=>$log['fee']  ];
        $where['<']=['ctime'=> ($log['ctime'] )  ]; //下单在前付款在后 误差2分钟

        $trade  = $this->createSql()->select( $this->tb_trade, $where,[],[],['trade_id'=>'desc'])->getRow();
        if(! $trade ){
            $using[]=2;
            $where['type']=$using;
            $where['>']=['ctime'=> ($log['ctime']-60*60 )  ]; #1个小时
            $trade  = $this->createSql()->select( $this->tb_trade, $where,[],[],['trade_id'=>'desc'])->getRow();
        }


        $tr_row =$trade;
        if( ! $trade  ) $this->throw_exception( "没找到相应的交易记录V4",19004 );

        #修改交易记录 把支付信息更新
        $this->upTradeByID( $trade['trade_id'],['realprice'=>$log['fee'],'type'=>1,'pay_time'=> $log['ctime'], 'pay_log_id'=> $pay_log_id ]  );

        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $trade['qr_id'] , 'trade_id'=>$trade['trade_id'] ]);
        //$this->findBestTrade( $trade_all );

        $tr_row['pay_time']= $log['ctime'];
        $tr_row['realprice']=  $log['fee'];
        $tr_row['type']= 1 ;
        $tr_row['pay_log_id']=  $pay_log_id ;
        try{
            $this->toMqTrade( $tr_row );
        }catch ( \Exception $ex ){  }
        return $this;

    }

    function matchSuccess( $tr_row, $log){
        $pay_log_id= $log['id'];

        #修改交易记录 把支付信息更新
        $this->upTradeByID( $tr_row['trade_id'],['realprice'=>$log['fee'],'type'=>1,'pay_time'=> $log['ctime'], 'pay_log_id'=> $pay_log_id ]  );

        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $tr_row['qr_id'] , 'trade_id'=>$tr_row['trade_id'] ]);
        //$this->findBestTrade( $trade_all );

        $tr_row['pay_time']= $log['ctime'];
        $tr_row['realprice']=  $log['fee'];
        $tr_row['type']= 1 ;
        $tr_row['pay_log_id']=  $pay_log_id ;
        try{
            $this->toMqTrade( $tr_row );
        }catch ( \Exception $ex ){  }
        return $this;
    }

    function payMatchByTradeV45( $trade ){
        $account = $this->getAccountByID(  $trade['account_id'] );
        if( $account['type']!=45 ) $this->throw_exception("收款账号类型错误", 19008);
        if( in_array( $trade['type'], $this->getTypeTradeSuccess())  ||$trade['pay_log_id'] )  $this->throw_exception( "该订单已经上分匹配过",19004 );

        if( ! $trade['buyer'])$this->throw_exception( "无汇款人",19006 );
        $where=['account_id'=>$trade['account_id'],'fee'=>$trade['realprice'],'opt_type'=>10,'pay_type'=>45,'trade_id' =>0 ];
        $where['>=']=['ctime'=>($trade['ctime']-3600) ];
        $where['<=']=['ctime'=>($trade['ctime']+3600) ];
        $log =$this->createSql()->select( 'pay_log', $where )->getRow();
        if( !$log ) $this->throw_exception( "没找到相应的收款记录V4",19007 );

        $this->matchSuccess( $trade, $log);
        return $this;

    }

    function payMatchByLogV36(  $log ,$opt=[] ){
        $this->drExit( $log );
        $using = $this->getTypeTradeUsing();
        $using[]=2; //超时加进来匹配
    }

    function payMatchByLogV78(  $log ,$opt=[] ){
        //$this->drExit( $log);
        if( (time() -$log['ctime'])>600) $this->throw_exception("仅能处理付款时间10分钟内的订单",19024);
        if( !$log['ali_beizhu'])  $this->throw_exception("备注出现问题！");
        $trade = $this->getTradeByID( $log['ali_beizhu'] );
        if( $trade['ctime']<=0 ||   ($trade['ctime']-$log['ctime'] )>5) $this->throw_exception("付款时间必须大于建立订单",19025);

        $using = $this->getTypeTradeUsing();
        $cnt = $this->createSql()->getCount( $this->tb_trade, ['type'=>$using,'qr_id'=>$trade['qr_id']  ])->getOne();
        if( $cnt>1)   $this->throw_exception("当前收款码有2个使用中！",19025);

        if( $trade['realprice'] != $log['fee'])   $this->throw_exception("支付金额错误！",19014);

        if(! in_array( $trade['type'] ,$using))  $this->throw_exception("超时了或者已经匹配成功V2",19015);

        if( $trade['account_id'] != $log['account_id'] )  $this->throw_exception("账号不相同？",19016);

        if( $log['pay_type']==120 ){
            $upvar=['ali_beizhu'=> '' ,'type'=>120,'realprice'=> 0 ] ;
            $this->getLogin()->createTablePayLogTem()->updateByKey( $trade['qr_id'] ,$upvar );
        }
        elseif( $log['pay_type']==150  ){
            $upvar=['ali_beizhu'=> '' ,'type'=>150,'realprice'=> 0 ] ;
            $this->getLogin()->createTablePayLogTem()->updateByKey( $trade['qr_id'] ,$upvar );
        }
        //$this->logs_s( "payMatchByLogV78 =". $log['type'] ."\t qr_id=".$trade['qr_id']  ,'debug.log');

        $this->matchSuccess( $trade, $log);

        return $this;
    }

    function payMatchByLogV60(  $log ,$opt=[] ){

        if( !$log['ali_beizhu'])  $this->throw_exception("备注出现问题！");

        if( $log['ali_beizhu'] =='收款'){
            if( (time() -$log['ctime'])>600) $this->throw_exception("仅能处理付款时间10分钟内的订单",19024);
            return $this->payMatchByPrice( $log, $opt );
        }

        if( (time() -$log['ctime'])>3600) $this->throw_exception("仅能处理付款时间1小时内的订单",19024);

        if( ! ( substr($log['ali_beizhu'],0,1 )=='M' ||substr($log['ali_beizhu'],0,4 )=='8018') ){
            return $this->payMatchByPrice( $log, $opt );
        }

        $pay_tem = $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=> $log['ali_beizhu'] ] );
        if( $pay_tem['type']==60 ){
            $opt['noprice']=1;
            return $this->payMatchByLogV35($log,$opt );
        }
        if(! $pay_tem['ali_beizhu'] ) $this->throw_exception("备注已经充值！", 19316);



        $trade = $this->getTradeByID( $pay_tem['ali_beizhu'] );

        if( $trade['ctime']<=0 ||   ($trade['ctime']-$log['ctime'] )>5) $this->throw_exception("付款时间必须大于建立订单",19025);
        $using = $this->getTypeTradeUsing();
        $using[]=2;
        $cnt = $this->createSql()->getCount( $this->tb_trade, ['type'=>$using,'qr_id'=>$trade['qr_id']  ])->getOne();
        if( $cnt>1)   $this->throw_exception("当前收款码有2个使用中！",19025);
        if( $trade['realprice'] != $log['fee'])   $this->throw_exception("支付金额错误！",19014);
        if(! in_array( $trade['type'] ,$using))  $this->throw_exception("超时了或者已经匹配成功",19015);

        if( ($trade['ctime']-$log['ctime'] )>2400) $this->throw_exception("相差1小时",19019);

        if( $trade['account_id'] != $log['account_id'] )  $this->throw_exception("账号不相同？",19016);
        $this->matchSuccess( $trade, $log);

        if( $pay_tem['type']>60 ){
            $this->getLogin()->createTablePayLogTem()->updateByKey( $pay_tem['pt_id'] ,[ 'type'=>61 ]);
        }
        return $this;
    }

    function payMatchByLogV90( $log ,$opt=[] ){

        $pay_tem = $this->getLogin()->createTablePayLogTem()->getRowByWhere( ['ali_trade_no'=> $log['ali_trade_no'] ] );
        if( !$pay_tem ) $this->throw_exception("缓存订单可能删除",19101705);
        $log['ali_beizhu']= $pay_tem['ali_beizhu'];
        return $this->payMatchByLogV35( $log );
        //$this->drExit( $pay_tem );
    }

    function payMatcheByLogV15($log, $opt=[]){

        //if( strpos($log['ali_beizhu'],'8018')!==false ){
            //return $this->payMatchByLogV35( $log );
        //}

        $dt= abs( time()- $log['ctime']);
        if($log['pay_type']==32 && $log['ali_beizhu']=='' && $dt<120 ){
            $this->throw_exception( "支扫码缺少备注",20041101);
        }

        if( strpos($log['ali_beizhu'],'8018')!==false  ){

            $trade_row= $this->getLogin()->createTableTrade(2)->getRowByKey( $log['ali_beizhu'] );
            if( $trade_row && $trade_row['account_id']!= $log['account_id']){
                $this->log("qrCodeError>>". $log['ali_beizhu']."\t". $log['ali_trade_no'] );
                $this->getLogin()->createTablePayLogTem()->updateByKey( ['ali_beizhu'=>$log['ali_beizhu'] ],['type'=>-15] );
                //$this->getLogin()->crea
                $this->throw_exception( "二维码账号不统一", 20041102);
            }
        }
        $where = ['account_id'=>$log['account_id'],'realprice'=>$log['fee'],'type'=>15];
        $tem = $this->getLogin()->createTablePayLogTem()->getRowByWhere(  $where );
        //if( $tem && $log['ali_beizhu']=='收款' ) $this->throw_exception( "有可能是修改金额的",20033101);
        return $this->payMatchByPrice( $log ,['qr_id'=>17999],['qz'=>1] );

    }

    function payMatchByLogV35(  $log ,$opt=[] ){
        //$pay_log_id= $log['id'];
        //$account = $this->getAccountByID(  $log['account_id'] );


        if( in_array($log['pay_type'],[351,320,139,39]) ){
            $using = $this->getTypeTradeUsingPayMatch();
        //}elseif( in_array($log['pay_type'],[90,91,92,93 ] ) ){
            //$using = $this->getTypeTradeUsingPayMatch();

        //}elseif( in_array($log['pay_type'],[ ])){
           // $using = $this->getTypeTradeUsing();
        }else{
            $using = $this->getTypeTradeUsing();
            $using[]=2; //超时加进来匹配
        }


        if( !$log['ali_beizhu'])  $this->throw_exception("备注出现问题！");

        $trade = $this->getTradeByID( $log['ali_beizhu'] );
        if( $trade['realprice'] != $log['fee'])   $this->throw_exception("支付金额错误！",19014);

        if(! in_array( $trade['type'] ,$using))  $this->throw_exception("超时了或者已经匹配成功",3519015);

        if( $trade['account_id'] != $log['account_id'] )  $this->throw_exception("账号不相同？",19016);

        $this->matchSuccess( $trade, $log);

        return $this;
    }

    /**
     * 支付撮合第三版本
     * 1.根据阿里uid 下单uid 跟支付uid要相同
     * 2.仅4分钟 正式环境不能用超时
     * 3.多个订单找到最优的时间
     * @param $log
     * @return $this
     * @throws drException
     */
    function payMatchByLogV3( $log ,$opt=[]){
        $pay_log_id= $log['id'];
        $account = $this->getAccountByID(  $log['account_id'] );
        $using = $this->getTypeTradeUsing();
        //$using[]=2;// 正式环境得去掉

        //
        $where = ['account_id'=>$log['account_id'],'ali_uid'=>$log['ali_uid'],'type'=>$using,'realprice'=>$log['fee']  ];
        $where['<']=['ctime'=> ($log['ctime'] )  ]; //下单在前付款在后 误差2分钟
        $trade  = $this->createSql()->select( $this->tb_trade, $where,[],[],['trade_id'=>'desc'])->getRow();
        if(!$trade) {

            $where = ['account_id' => $log['account_id'], 'ali_uid' => $log['ali_uid'], 'type' => $using];
            $where['<'] = ['ctime' => ($log['ctime'] + 60)]; //下单在前付款在后 误差2分钟
            $trade = $this->createSql()->select($this->tb_trade, $where, [], [], ['trade_id' => 'desc'])->getRow();

            if(abs(   $log['fee'] - $trade['realprice']  )>100 ){ #金额误差1块钱
                //$this->assign('log', $log)->assign('trade',$trade );
                $this->throw_exception("金额不匹配=". $log['fee'] .'-'. $trade['realprice'], 19003);
            }


            if( ($log['fee'] - $trade['realprice']) > -100) { #亏本1块钱

            }elseif ($trade['merchant_id'] >= 8222  ) {
                $this->throw_exception("8222商户不支持金额不对的匹配", 19003);
            }



        }

        $tr_row =$trade;
        if( ! $trade  ){
            if( $log['fee']%100){ //如果有小数点 使用价格匹配
                return $this->payMatchByPrice($log );
            }
            else{
                $this->throw_exception( "没找到相应的交易记录1",19002 );
            }
        }

        #修改交易记录 把支付信息更新
        //$this->upTradeByID( $trade['trade_id'],['realprice'=>$log['fee'],'type'=>1,'pay_time'=> $log['ctime'], 'pay_log_id'=> $pay_log_id ]  );
        $this->upTradeByID( $trade['trade_id'],[ 'type'=>1,'pay_time'=> $log['ctime'], 'pay_log_id'=> $pay_log_id ]  );

        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $trade['qr_id'] , 'trade_id'=>$trade['trade_id'] ]);
        //$this->findBestTrade( $trade_all );

        $tr_row['pay_time']= $log['ctime'];
        $tr_row['realprice']=  $log['fee'];
        $tr_row['type']= 1 ;
        $tr_row['pay_log_id']=  $pay_log_id ;
        try{
            $this->toMqTrade( $tr_row );
        }catch ( \Exception $ex ){  }
        return $this;
    }

    function findBestTrade($trade_all, $fee){
        if( count($trade_all )<=1) return $trade_all[0];
        foreach ($trade_all as $k=>$v){
            $trade_all[$k]['dt']= abs( $v['realprice']-$fee);
        }
        $fun =function ($a,$b){
            if($a['dt']==$b['dt']) return $a['ctime']>$b['ctime']?1:-1;
            return $a['dt']>$b['dt']?1:-1;
        };
        usort($trade_all, $fun );
    }

    /**
     * 支付撮合
     * 1.发起支付
     * 2.等待支付3分钟内去支付
     * 3.第4分钟去接触锁码 参考 crontab/timeout.php
     * @param $pay_log_id
     * @return $this
     * @throws drException
     */
    function payMatchByLogID( $pay_log_id ,$opt=[] ){


        if( $pay_log_id<=0) $this->throw_exception( "参数错误！"  ,20030503 );

        $log = $this->getLogin()->createPayLog()->getById( $pay_log_id );

        if( $log['trade_id'] )  $this->throw_exception( "该支付记录以及处理完成"  ,2018081126 );



        $log['ali_beizhu'] = trim( $log['ali_beizhu'],'=' );

        $account = $this->getAccountByID(  $log['account_id'] );
        $version= $this->getLogin()->getVersionBYConsole(  $log['user_id'] , $account['type'] );
        //$opt['version']= $version;
        $opt2=['version'=> $version];
        if( in_array($version, [205,13,40])  ||   in_array($log['pay_type'],[205,201 ,28,94] ) ){
            //$this->drExit($log );
            return $this->payMatchByPrice($log, ['qr_id'=>17999],$opt2 );;
        }

        if(in_array( $version,[15] ) ){ //&& in_array( $account['user_id'] ,[4])
            return $this->payMatcheByLogV15( $log );
        }elseif( in_array( $version,[15] )){
            if( strpos($log['ali_beizhu'],'8018')!==false ){
                return $this->payMatchByLogV35( $log );
            }

            return $this->payMatchByPrice( $log );
        }

        if( in_array($version, [320]) ){
            return $this->payMatchByLogV35( $log,$opt );
        }



        if($log['pay_type']==4 && in_array($account['type'],[3,263])  ){ //使用云闪付夜间模式 然后使用
            return $this->payMatchByPrice( $log, $opt );
        }

        if( in_array($account['type'],[ 47,48 ]) ){
            if( !in_array($log['pay_type'] ,[ 47,48 ] )) $this->throw_exception( "忽略上分",19121504 );
            return $this->payMatchByPrice( $log, $opt );
        }

        if(in_array($account['type'],[363,364]) ){
            /*
            $fee= $log['fee'] ;
            $int_fee= ceil($fee/100 )*100;
            $dt= $int_fee-$fee;
            $dt_rank=$int_fee*0.004;
            if( $dt<0 || $dt>$dt_rank) $this->throw_exception( "相差太大 " ."dt=".$dt."  dt_rank=". $dt_rank );

            //$this->throw_exception( "dt=".$dt."  dt_rank=". $dt_rank. " ". $int_fee );
            $log['fee'] = $int_fee;
            $log['realprice'] = $fee;
            return $this->payMatchByPrice( $log, $opt );
            */
            return $this->payMatchByPrice363( $log, $opt );
        }

        if( $log['pay_type']==4 && in_array($account['type'],[60,260])  ){ #
            //return $this->payMatchByPrice( $log, $opt );
            $this->throw_exception("暂时不支持！调试中");
        }
        if( in_array($log['pay_type'],[  60  ] ) ){
            return $this->payMatchByLogV60( $log,$opt );
            //$this->throw_exception("暂时不支持！调试中");
        }


        //$this->drExit($log );
        if( 21== $log['pay_type'] ){ //账单撮合
            return $this->payMatchByLogV2( $log );
        }
        if( in_array($log['pay_type'],[31,32 ] )){
            if( $log['ali_beizhu']=='转账' || $log['ali_beizhu']=='收款'  ){
                return $this->payMatchByLogV3( $log,$opt );
            }
            if( $log['ali_beizhu']!='' ){
                return $this->payMatchByLogV35( $log,$opt );
            }
            return $this->payMatchByLogV3( $log,$opt );
        }
        if(in_array($log['pay_type'],[90,91,92,93,96 ] )){
            return $this->payMatchByLogV90( $log,$opt );
        }

        if( in_array($log['pay_type'],[ 78 ,120 ,150,239] ) ){
            //return $this->payMatchByLogV35( $log,$opt );
            return $this->payMatchByLogV78( $log,$opt );
        }

        if( in_array($log['pay_type'],[  61 ,460,3 ,65 ]) ){
            //if( (time() -$log['ctime'])>600) $this->throw_exception("仅能处理付款时间10分钟内的订单",19024);

            return $this->payMatchByPrice( $log, $opt );
        }


        if( in_array($log['pay_type'],[35,36,38,22,39,351,139 ,138 ] )){
            if( $log['ali_beizhu']=='收款'  ){
                //&& strlen($log['ali_uid'])>1
                return $this->payMatchByLogV3( $log,$opt );
            }
            return $this->payMatchByLogV35( $log,$opt );
        }

        if( in_array($log['pay_type'],[33] )){
            return $this->payMatchByLogV3( $log,$opt );
        }
        if( in_array($log['pay_type'],[45 ] )){
            return $this->payMatchByLogV45( $log,$opt );
        }



        $is_debug_version= in_array(  $log['user_id']  ,[8] ); #操作者 uid
        $is_debug_version =  in_array($version, [3,23,24,201 ,301,303 ]) ; //$version==3


        $fee= $is_debug_version?10086: $log['fee'] ;

        if( $version==4 ||  $version==5 ||  $version==40 ){ //空码
            $qr=['qr_id'=>17999];
            $sql = "select * from " . $this->tb_trade . " where account_id='" .$log['account_id'] . "' and realprice='" . $log['fee'] . "'  and type in(3,4)   "; #时间应该留给调度来处理
            //$tr_row = $this->createSql()->select($this->tb_trade, ['account_id'=>$log['account_id'],'realprice'=>$log['fee'],'type'=>$this->getTypeTradeUsing()  ]  )->getRow();//$this->createSql($sql)->getRow();
            #$tr_row = $this->createSql()->select($this->tb_trade, ['account_id'=>$log['account_id'],'realprice'=>$log['fee'],'type'=>[3]  ]  )->getRow();//$this->createSql($sql)->getRow();
            //$tr_all = $this->createSql()->select($this->tb_trade, ['account_id'=>$log['account_id'],'realprice'=>$log['fee'],'type'=>[3]  ]  )->getAll();//$this->createSql($sql)->getRow();
            //if( count($tr_all)>1  ) $this->throw_exception("");
            $types = $this->getTypeTradeUsingPayMatch() ;
            $tr_all  = $this->createSql()->select($this->tb_trade, ['account_id'=>$log['account_id'],'realprice'=>$log['fee'],'type'=>$types ]  )->getAll();//->getRow();
            if( count($tr_all)>1  ) $this->throw_exception("有2条记录无法撮合",190527);
            $tr_row = $tr_all[0];

        }else { //有码，比如一张 or多张
            //$fee=  $log['fee'] ;

            //$qr = $this->createSql()->select( $this->tb_qr,  ['account_id'=>$log['account_id'] ,'fee'=>$log['fee']   ] )->getRow();

            $qr = $this->createSql()->select($this->tb_qr, ['account_id' => $log['account_id'], 'fee' => $fee])->getRow();

            #随机码 用户自行添加金额
            //if( !$qr) $qr= $this->createSql()->select( $this->tb_qr,  ['account_id'=>$log['account_id'] ,'fee'=>10086   ] )->getRow();

            if (!$qr) $this->throw_exception("固码不存在", 2018081127);

            //$tr_row = $this->getTradeRowByWhere( ['pay_log_id'=>$pay_log_id ]);

            //$sql ="select * from ". $this->tb_trade . " where qr_id='".$qr['qr_id']."'  and type=0 and ctime>='".$this->getTimeLimit( 10 )."' ";
            $sql = "select * from " . $this->tb_trade . " where qr_id='" . $qr['qr_id'] . "' and realprice='" . $log['fee'] . "'  and type in(3,4)   "; #时间应该留给调度来处理
            $sql = "select * from " . $this->tb_trade . " where qr_id='" . $qr['qr_id'] . "' and realprice='" . $log['fee'] . "'  and type in(3)   "; #时间应该留给调度来处理
            $tr_all = $this->createSql($sql)->getAll();
            if( count($tr_all)>1  ) $this->throw_exception("有2条记录无法撮合",190527);
            $tr_row = $tr_all[0];
        }
        if( $_GET['ds'] == 1 ){
            $this->drExit(  $tr_row  );
        }

        //$tr_row = $this->createSql()->select($this->tb_trade,['qr_id'=>$qr['qr_id'],'type'=> $this->getTypeTradeUsing()  ])->getRow();
        if( !$tr_row )  $this->throw_exception( "未找到支付请求或者已成功",2018081129 );


        if( count($tr_all)>1  ) $this->throw_exception("有2条记录无法撮合",190527);




        #修改交易记录 把支付信息更新
        $this->update( $this->tb_trade,['trade_id'=>$tr_row['trade_id']], ['pay_time'=> $log['ctime'], 'type'=>1,'pay_log_id'=>$pay_log_id ] );




        #解锁固码
        $this->update( $this->tb_qr, ['qr_id'=>$qr['qr_id'] ], [ 'lock_time'=>0 ,'+'=>['cnt_success'=>1] ]);
        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $qr['qr_id'] , 'trade_id'=>$tr_row['trade_id'] ]);

        #之后应该去执行 notify




        $tr_row['pay_time']= $log['ctime'];
        $tr_row['type']= 1 ;
        $tr_row['pay_log_id']=  $pay_log_id ;
        try{
            $this->toMqTrade( $tr_row );
            #cookie 对于名字
            $this->tradePaylogCookieNameByTradeID( $tr_row['trade_id'] );
        }catch ( \Exception $ex ){  }
        return $this;
    }

    function payMatchByPrice363(  $log, $opt ){
        $fee= $log['fee'] ;
        if($fee<=20000) {
            $int_fee = ceil($fee / 100) * 100;
            $dt = $int_fee - $fee;
            $dt_rank = $int_fee * 0.004;
            if ($dt < 0 || $dt > $dt_rank) $this->throw_exception("相差太大 " . "dt=" . $dt . "  dt_rank=" . $dt_rank);
        }else{
            $int_fee= $this->getZs($fee);
        }
        $log['fee'] = $int_fee;
        #$log['realprice'] = $fee;
        return $this->payMatchByPrice( $log, $opt );
    }
    function getZs($fee){
        $i_fee=intval($fee/(1-0.0038));
        $i_fee2= intval( ($i_fee+5)/100 )*100;
        $dt= abs($i_fee2-$i_fee );
        if($dt >5 ) $this->throw_exception( "相差太大 " ."dt=".$dt ) ;
        return $i_fee2;
    }

    function payMatchByPrice( $log, $qr=['qr_id'=>17999], $opt=[]  ){

        //$qr=['qr_id'=>17999];
        $pay_log_id= $log['id'];;
        $types = $this->getTypeTradeUsingPayMatch() ;
        if($_GET['qz'] || $opt['qz']){
            $types= $this->getTypeTradeUsing();
        }

        $tr_all  = $this->createSql()->select($this->tb_trade, ['account_id'=>$log['account_id'],'realprice'=>$log['fee'],'type'=>$types ]  )->getAll();//->getRow();

        if( count($tr_all)>1  ) $this->throw_exception("有2条记录无法撮合",190527);


        if( in_array($opt['version'],[40] )){
            #$this->throw_exception('o m god');
            #$this->log('om-god');
            if( in_array( $log['pay_type'],[4,42]) ){
                $this->throw_exception("这个模式下不匹配，没匹配到卡", 20061801);
            }

        }elseif( in_array( $log['fee'],[10000,20000,30000,50000,100000,200000,300000,500000,1000000]) || $log['fee']%10000==0 ){

            $where=['account_id'=>$log['account_id'],'type'=>$types ] ;
            $where['>']['realprice']=  $log['fee']-1001 ;
            $where['<']['realprice']=  $log['fee']+1001 ;

            $cnt= $this->getLogin()->createTableTrade()->getCount( $where );

            if($cnt>1){
                $this->log("modify_price>>". $pay_log_id." ". $log['fee']." ". $log['ali_trade_no']);
                $this->throw_exception('可能发生修改金额',20042901);
            }
        }

        $tr_row = $tr_all[0];
        if( !$tr_row )  $this->throw_exception( "未找到支付请求或者已成功",2018081129 );

        //$this->drExit( $tr_row );
        //$this->createSql($sql)->getRow();

        #由
        $lstime=600;
        //if( in_array($tr_row['user_id'],[2333] )) $lstime=240;
        if( $log['ctime']-$tr_row['ctime']>$lstime  ) $this->throw_exception("超过".$lstime."秒不处理",19070501);

        if( $log['ctime']<$tr_row['ctime'] )  $this->throw_exception("收款时间必须大于建单时间",19070508);



        //$tr_row = $this->createSql()->select($this->tb_trade,['qr_id'=>$qr['qr_id'],'type'=> $this->getTypeTradeUsing()  ])->getRow();



        #修改交易记录 把支付信息更新
        $upvar= ['pay_time'=> $log['ctime'], 'type'=>1,'pay_log_id'=>$pay_log_id ];
        if( isset($log['realprice']) ){
            $upvar['realprice']= $log['realprice'];
        }
        $this->update( $this->tb_trade,['trade_id'=>$tr_row['trade_id']],  $upvar );




        #解锁固码
        $this->update( $this->tb_qr, ['qr_id'=>$qr['qr_id'] ], [ 'lock_time'=>0 ,'+'=>['cnt_success'=>1] ]);
        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $qr['qr_id'] , 'trade_id'=>$tr_row['trade_id'] ]);

        #之后应该去执行 notify




        $tr_row['pay_time']= $log['ctime'];
        $tr_row['type']= 1 ;
        $tr_row['pay_log_id']=  $pay_log_id ;
        try{
            $this->toMqTrade( $tr_row );
            #cookie 对于名字
            $this->tradePaylogCookieNameByTradeID( $tr_row['trade_id'] );
        }catch ( \Exception $ex ){  }
        return $this;


    }

    function tradePaylogCookieNameByTradeID( $trade_id ){
        $trade = $this->getTradeByID( $trade_id);
        $this->searchBuyerFromTrade( $trade )->addCookieName($trade['cookie'], $trade['pay_log']['buyer'] );
        return $this ;
    }

    function toMqTrade( $trade, $ex='qf_notify'){
        $mq= new mq();
        $mq->rabbit_publish( $ex , $trade );
        return $this ;
    }
    function toMqTradeV2( $trade, $ex='qf_notify' ,$opt=[] ){
        $mq= new mq();
        $mq->rabbit_publish( $ex , $trade ,"","" ,$opt);
        return $this ;
    }

    function upTradeByID( $trade_id, $opt){
        $this->createSql()->update( $this->tb_trade, $opt,['trade_id'=>$trade_id] )->query();
        //$sql = $this->createSql()->update( $this->tb_trade, $opt,['trade_id'=>$trade_id] )->getSQL();
        //$this->drExit($sql );
        return $this;
    }

    function upAccountByID( $account_id, $opt){
        $this->createSql()->update( $this->tb_account, $opt,['account_id'=>$account_id] )->query();
        return $this;
    }

    function upAccountByWhere( $where, $opt ){
        $this->createSql()->update( $this->tb_account, $opt, $where )->query();
        return $this;
    }

    function getMerchantByUid( $uid ){
        return $this->createSql()->select( $this->tb_merchant, ['user_id'=> $uid ] )->getAll();
    }

    function getTradeWithPage( $where ,$op=[] ){
        $every= $op['every']>0? $op['every'] :30;
        $file=[];
        $order= ['trade_id'=>'desc'];
        if( isset( $op['file'])) $file= $op['file'];
        if( isset( $op['order'])) $order = $op['order'];
        return $this->createSql()->selectWithPage( $this->tb_trade, $where, $every ,$file , $order);
    }



    function getTradeCnt( $where ){
        return   $this->createSql()->getCount(  $this->tb_trade , $where)->getOne() ;
    }

    function getTradeTj( $where ){
        $sql="select `type`,sum(realprice) as realprice, count(*) as cnt from ". $this->tb_trade. " where ". $this->createSql()->arr2where($where ) ." group by `type`";
        $tj['detail']= $this->createSql($sql)->getAllByKey( 'type');
        $tj['total']= 0;
        $tj['total_price']= 0;
        foreach($tj['detail'] as $v ){
            $tj['total']+= $v['cnt'];
            $tj['total_price'] += $v['realprice'] ;
        }
        return $tj ;
    }
    function getTradeTj2( $where ){

    }

    /**
     * 财务增加结算
     * @param $finance
     * @return $this
     * @throws drException
     */
    function appendFinance( $finance ){
        //'merchant_id','ctime','user_id','run_time','run_time_str','fee'
        //2018081140
        if( !$finance['merchant_id'] ) $this->throw_exception("商户号不能为空",2018081140);
        if( !$finance['run_time_str'] ) $this->throw_exception("汇款时间不能为空",2018081141);
        $finance['run_time']= strtotime( $finance['run_time_str']);
        if( ! $finance['run_time'] )$this->throw_exception("请注意汇款时间格式！",2018081144);
        if( $finance['run_time']>time() ) $this->throw_exception("汇款时间不能大于当前时间",2018081142);

        $finance['fee'] = intval( $finance['fee'] );
        if( $finance['fee']<=0 ) $this->throw_exception("金额不能小于0",2018081143);

        $finance['ctime']= time();
        $finance['user_id']=  $finance['user_id']? $finance['user_id']: $this->getLogin()->getUserId();

        $this->insert( $this->tb_finance, $finance, $this->file_finance );
        return $this ;
    }

    function getFinanceByID( $mf_id ){
        $row = $this->createSql()->select($this->tb_finance,['mf_id'=>$mf_id ] )->getRow();
        if( !$row )$this->throw_exception("该记录不存在",2018081145);
        return $row;
    }

    function getFinanceWithPage( $where ,$opt=[] ){

        return $this->createSql()->selectWithPage( $this->tb_finance , $where  ,30,[],$opt['order']?$opt['order']:[]);
    }

    function delFinanceByID( $mf_id ){
        $this->createSql()->delete( $this->tb_finance, ['mf_id'=>$mf_id])->query();
        return $this;
    }

    /**
     * 单统计
     * @param $where
     * @return mixed
     * @throws drException
     */
    function tjFinance( $where ){
        $sql = "select sum(fee) as fee,count(*) as cnt from ". $this->tb_finance."  where  ". $this->createSql()->arr2where($where );
        $re= $this->createSql($sql)->getRow() ;
        return $re ;
    }

    /**
     * 按group统计
     * @param $group_file
     * @param $where
     * @return array
     * @throws drException
     */
    function tjFinanceGroup($group_file, $where ){
        $sql = "select `".$group_file."`,sum(fee) as fee,count(*) as cnt from ". $this->tb_finance."  where  ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;
        return $this->createSql($sql)->getAllByKey( $group_file ) ;//'merchant_id'
    }

    /**
     * 订单上分统计
     * @param $where
     * @return array
     * @throws drException
     */
    function tjTrade( $where ){
        $sql="select  sum(realprice) as realprice , sum(price) as price, count(*) as cnt from ". $this->tb_trade. " where ". $this->createSql()->arr2where($where )  ;
        return $this->createSql( $sql)->getRow() ;
    }

    /**
     * 订单上分 分组 统计
     * @param $group_file
     * @param $where
     * @return array
     * @throws drException
     */
    function tjTradeGroup( $group_file, $where){
        if( $group_file =='realprice' ){
            $sql="select  `".$group_file."`, sum(realprice) as total, sum(price) as price, count(*) as cnt from ". $this->tb_trade. " where ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;
        }else
            $sql="select  `".$group_file."`, sum(realprice) as realprice, sum(price) as price , count(*) as cnt from ". $this->tb_trade. " where ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;

        //$this->assign('tj_sql', $sql );

        return $this->createSql($sql)->getAllByKey( $group_file ) ;
    }

    function toDayByTrade( $trade ){
        $cl_day = new day();
        $cl_day->setMcID($trade['merchant_id'] )->setDay(   date("Y-m-d", $trade['ctime']));
        $cl_day->appendByTradeNotify()->appendByTrade();
        //$cl_day->appendByTradeNotify();
        return $this ;
    }


    function liveTraceItem( &$account, $where ,$key ){
        $where['account_id']= array_keys( $account);

        $where['type']= [1,11]; //统计成功、补单成功的

        $where2=$where;

        $tj= $this->getLogin()->createQrPay()->tjTradeGroup('account_id',  $where2);
        foreach ( $tj as $k=>$v ){
            $account[$k]['trade'][ $key ]= $v ;
        }
        unset( $where['type'] );

        $tj= $this->getLogin()->createQrPay()->tjTradeGroup('account_id',  $where);
        foreach ( $tj as $k=>$v ){
            $account[$k]['td_all'][ $key ]= $v ;
            //if(  $account[$k]['trade'][ $key ] ) $account[$k]['trade'][ $key ]['pc']= $v['cnt']? number_format(100*$account[$k]['trade'][ $key ]['cnt']/$v['cnt'],2).'%' :'-';
            if(  $account[$k]['trade'][ $key ] ) $account[$k]['trade'][ $key ]['pc']= $v['cnt']? intval(100*$account[$k]['trade'][ $key ]['cnt']/$v['cnt']).'%' :'-';
        }
        return $this;
    }


    function liveCashItem( &$account, $where ,$key ){
        $where['account_id']= array_keys( $account);
        $where['opt_type']=[10];
        $tj= $this->getLogin()->createQrPay()->tjPayLogGroup('account_id',  $where);
        foreach ( $tj as $k=>$v ){
            $account[$k]['tj'][ $key ]= $v ;
        }
        return $this;

    }

    function liveCash( &$account , $h ){
        if($h=='yesterday'){
            $where['>=']['ctime']=  strtotime(date("Y-m-d",time()-24*3600));
            $where['<=']['ctime']=  strtotime(date("Y-m-d"))-1;
        }else{
            $where['>=']['ctime']= $h=='today'? strtotime( date('Y-m-d')): time()-$h*3600;
        }
        $this->liveCashItem($account,$where,$h )->liveTraceItem($account,$where,$h  );
        return $this;
    }


    /**
     * 收款分组统计
     * @param $group_file
     * @param $where
     * @return array
     * @throws drException
     */
    function tjPayLogGroup( $group_file, $where ){
        $sql="select  `".$group_file."`, sum(fee) as fee , count(*) as cnt from  pay_log  where ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;
        //$this->assign('tjPayLogGroupSql', $sql );
        return $this->createSql($sql)->getAllByKey( $group_file ) ;
    }

    function tjPayQrGroup( $group_file, $where){
        $sql="select  `".$group_file."`  , count(*) as cnt from ".$this->tb_qr."  where ". $this->createSql()->arr2where($where ) ." group by ".$group_file ;
        //$this->log( $sql );
        return $this->createSql($sql)->getAllByKey( $group_file ) ;
    }

    function getTestQr( $where ){
        $where['fee']=1;
        return $this->createSql()->select( $this->tb_qr,$where )->getAllByKey( 'account_id');
    }

    public  function updateQrByWhere( $where ,$var ){
        $this->update( $this->tb_qr, $where ,$var );
    }

    function getQrRowByWhere( $where ){
        return $this->createSql()->select( $this->tb_qr,$where )->getRow( );
    }

    public function bu4( $tr_id,$pay_log_id){
        $log = $this->getLogin()->createPayLog()->getById( $pay_log_id );
        if( !$log ) $this->throw_exception("收款记录不存在！");
        if( $log['trade_id'] )  $this->throw_exception( "该收款记录以及处理完成",2018081126 );

        $trade  = $this->getLogin()->createQrPay()->getTradeByID( $tr_id );
        if( ! $trade  ) $this->throw_exception( "没找到相应的交易记录",19002 );
        if( in_array( $trade['type'],[1,11]) ||  $trade['pay_log_id']>0 ){
            $this->throw_exception( "订单已经在成功昨天，无法补单",19003 );
        }

        #修改交易记录 把支付信息更新
        $this->upTradeByID( $trade['trade_id'],['realprice'=>$log['fee'],'type'=>11,'pay_time'=> $log['ctime'], 'pay_log_id'=> $pay_log_id ]  );

        #连接支付信息的 交易记录
        $this->getLogin()->createPayLog()->updateByWhere( ['id'=>$pay_log_id ] ,  [ 'qr_id'=> $trade['qr_id'] , 'trade_id'=>$trade['trade_id'] ]);
        #到队列中去
        $this->getLogin()->createQrPay()->toMqTrade(  $this->getLogin()->createQrPay()->getTradeByID( $tr_id )  );

        return $this;

    }

    public function bu( $tr_id ,$op_type=11 ,$opt=[] ){

        $success = $this->getLogin()->createQrPay()->getTypeTradeSuccess() ;


        if(! in_array(  $op_type , $success ) ) $this->throw_exception( "仅支持1,11！",5611 );

        $t= 11;
        $tr_row = $this->getLogin()->createQrPay()->getTradeByID( $tr_id );

        if( in_array( $tr_row['type'], $success ) )$this->throw_exception( "补单已经成功！",5610 );
        $tr_up=[];
        if( $opt['buyer']) $tr_up['buyer']= $opt['buyer'] ;
        $is_chang_price= false ;
        if( ($_GET['fee']) ){
            $is_chang_price= true ;
            //$fee = ceil( floatval( trim( $_GET['fee'] ) )*100 );
            $int = floatval( trim( $_GET['fee'] ) );
            $fee = floor(($int+0.001)*100);
        }else{
            $fee =0 ;
        }
        #补单改价格
        if( $op_type==11 &&  $fee>0 &&  $fee != $tr_row['realprice'] ){

            //$fee = ceil( floatval( trim( $_GET['fee'] ) )*100 );
            $int = floatval( trim( $_GET['fee'] ) );
            $fee = floor(($int+0.001)*100);

            if( $fee<=0  ) $this->throw_exception( "价格必须大于0 ",2018081169);
            $realprice= $fee ;
            //$this->update( $this->tb_trade, ['trade_id'=> $tr_row['trade_id'] ], ['realprice'=>$realprice] );
            $tr_up['realprice'] = $tr_row['realprice']= $fee;
            //if($fee==1989 ) $this->throw_exception("有是1989");
            $is_chang_price= true ;
            //$this->drExit( $realprice );
        }
        #end 补单改价格

        //if( !($tr_row['type'] ==10 ||  $tr_row['type'] ==2 ) ) $this->throw_exception( "仅补单审核可操作！",2018081147);

        if( $tr_id !='80183554056') {
            if ((time() - $tr_row['ctime']) > 24 * 3600) $this->throw_exception("已经超过24小时，请场外补单！", 2018081151);
        }



        //$sql ="select * from pay_log where trade_id='".$tr_id."' ";
        if(  $_GET['pad_log'] ){
            $row = $this->getLogin()->createPayLog()->getById($_GET['pad_log'] );
            if( $row['account_id'] != $tr_row['account_id'] ){
                $this->throw_exception( "收款编码不一致！",2018081171);
            }
            //if( $is_chang_price && $tr_row['realprice'] !=$row['fee']   ){
            if( $is_chang_price &&  ceil($fee) != ceil($row['fee'])   ){
                $this->throw_exception( "补单价格，与你提交价格不一致！ ".$fee.':' .$row['fee']  ,2018081170);
            }
            if( !$row ) $this->throw_exception( "该日志不存在！",2018081148);
            if( $row['trade_id'] ) $this->throw_exception( "该日志加过了！",2018081149);
            //$this->drExit($row );
            $this->createSql()->update('pay_log', ['trade_id' => $tr_id, 'qr_id' => $tr_row['qr_id']], ['id' => $row['id']])->query();
        }else {
            $row = $this->createSql()->select('pay_log', ['trade_id' => $tr_id])->getRow();
            if (!$row) {
                //补单出现差错  查找单的时候 大面积 应该限制在 一个收款编号内：结果导致 收款小于上分
                $wh = [ 'account_id'=> $tr_row['account_id'] ,  'fee' => $tr_row['realprice'], 'trade_id' => 0, '>=' => ['ctime' => $tr_row['ctime']] ];

                if( $is_chang_price ){
                    $wh['<=']=['ctime'=>  $tr_row['ctime']+ 60*5 ]; #找200内的单子
                }
                //$row= "select * from pay_log where trade_id='0' and fee='".$tr_row['realprice']."' and ctime>='".$tr_row['ctime']."'  ";
                $row = $this->createSql()->select('pay_log', $wh)->getRow();
                //$this->drExit( $row );
                if ($row) {
                    $this->createSql()->update('pay_log', ['trade_id' => $tr_id, 'qr_id' => $tr_row['qr_id']], ['id' => $row['id']])->query();
                }
            }
        }

        //$this->drExit( 'dddd');

        if(! $row ){         #新建补单记录
            if( $opt['is_limit']==1 ){
                $this->throw_exception( "补单失败，未找到可补单收款凭证！" .$fee , 2029 );
            }
            $realprice = $tr_row['realprice'];
            $c_user_id= $opt['c_user_id'] ? $opt['c_user_id'] : $this->getLogin()->getUserId();
            $log =  new log('pay_log', $c_user_id );

            //$iVar =['ltime'=>  rand(10000000, 99000000 ),'fee'=> $realprice
            $iVar =['ltime'=>  rand(10000000, 99000000 ),'fee'=> $realprice
                ,'account_id'=>$tr_row['account_id'],'pay_type'=> 11 ,'ip'=>drFun::getIP() ,'trade_id'=>$tr_id ];

            //$account= $this->getAccountByID( $tr_row['account_id']); //80346583
            $iVar['ma_user_id']= $tr_row['ma_user_id'] ;

            //$this->drExit($iVar );
            $this->assign('ivue',$iVar );

            //$log->append(  rand(10000000, 99000000 ) ,10  , ['tip'=>'补单'], $iVar);
            $log->append(  $tr_id ,10  , ['tip'=>'补单'], $iVar);

            $tr_up['pay_log_id']=  $this->createSql()->lastID();

        }else{
            $tr_up['pay_log_id']= $row['id'];
        }

        $tr_up['type']=  $op_type ;
        //$tr_up['pay_time']= time() ;
        $tr_up['pay_time']= $row['ctime']>0 ? $row['ctime']:  time();
        //$tr_row['pay_time']= $log['ctime'];

        #日志
        $this->getLogin()->createLogGt()->append($tr_id, $t );
        #补单
        $this->getLogin()->createQrPay()->upTradeByID($tr_id, $tr_up );
        #到队列中去
        $this->getLogin()->createQrPay()->toMqTrade(  $this->getLogin()->createQrPay()->getTradeByID( $tr_id )  );
        return $this ;

    }

    /**
     * 检查账号的监控程度
     * @param $account
     * @param $opt
     * @return $this
     * @throws drException
     */
    function tjTradeAccountHealth(  &$account ,$opt=[] ){
        $acc_id=[];
        if(  !$opt['limit'] ) $opt['limit']  =  [0,2000];
        drFun::searchFromArray( $account,['account_id'], $acc_id );
        if( !$acc_id ) return $this;
        $wh2= ['account_id'=> $acc_id];
        if( isset( $opt['c_user_id'])){
            unset( $wh2['account_id'] );
            $wh2['user_id']= $opt['c_user_id'];
        }
        if( !isset( $opt['notime']) )$wh2['>']=['ctime'=> strtotime( date("Y-m-d") ) ];
        if( isset( $opt['limitTime']) ){
            //$wh2['>']=['ctime'=>   ) ];
            $wh2['>']['ctime']= $opt['limitTime'] ;
        }

        $tall = $this->createSql()->select($this->tb_trade,$wh2 , $opt['limit'] ,['account_id','type','ctime'],['trade_id'=>'desc'] )->getAll();
        $re=[];
        $int_var = ['fail'=>0,'cnt'=>0,'is_success'=>0,'fail_all'=>0,'fail_cnt_day'=>0 ];
        $fail_day=[];
        foreach($tall as $v ){
            $key= $v['account_id'];
            if(! isset($re[ $key ]) )  $re[ $key ]= $int_var;//['fail'=>0,'cnt'=>0,'is_success'=>0,'fail_all'=>0 ,'fail_cnt_day'=>0 ];
            $re[ $key ]['cnt']++;
            if( $v['type']==1 ||  $v['type']==11 ) {
                $re[ $key ]['is_success']=1;
                //continue;
            }

            if( $v['type']==4 || $v['type']=='0' )  continue ; #去掉 下单->支付转入（扫码）->支付中   ->成功上分（失败超时）

            if(  $re[ $key ]['is_success']!=1 ){
                $re[ $key ]['fail_all']++ ;
                $dsk = date('Ymd',  $v['ctime']);//date_format($v['ctime'],'Ymd' );
                $fail_day[$key][ $dsk ] =1;
                $re[ $key ]['fail_cnt_day'] = count( $fail_day[$key] ) ;
            }


            //$pay_time= $this->getPayLogLastTime( $key);
            //if($pay_time>0 && $v['ctime']<$pay_time) continue;

            if(  $re[ $key ]['is_success']!=1 ) $re[ $key ]['fail']++ ;
        }

        foreach( $account as &$v ){
            $v['health']= isset($re[ $v['account_id'] ])?  $re[ $v['account_id'] ]: $int_var ;
        }
        return $this ;
    }

    function getPayLogLastTime( $account_id ){
        if(  isset( $this->last_time[$account_id] ) )    return  $this->last_time[ $account_id ];
        $row = $this->createSql()->select('pay_log', ['account_id' => $account_id, 'pay_type' => [1, 2,21,3,31,32,4,35 ,36,37,38,39,24,23,78,60,301,303 ]], [], [], ['id' => 'desc'])->getRow();
        $this->last_time[$account_id] = $row['ctime']? $row['ctime']:-1 ;
        return  $this->last_time[ $account_id ];
    }

    function searchBuyer( & $var ){
        if( !is_array($var ) ||  $var['buyer'] ) return $this;

        if( isset( $var['opt_value'] )){
            $tarr= is_array($var['opt_value'])? $var['opt_value'] : json_decode( $var['opt_value'] ,true);
            //$this->drExit( $tarr );
            if(isset($tarr['billName'] )) {
                $arr= explode('-',$tarr['billName']  );
                $var['buyer']=  $arr[  count($arr)-1 ] ;
            }elseif(isset($tarr['text'] )){
                $arr= explode('通过',$tarr['text']  );
                $var['buyer']= count($arr)>1? $arr[0]:'';

            }elseif( isset($tarr['tip'] )){
                $var['buyer']= '人为补单';
            }else{
                $var['buyer']='';
            }


        }else{
            foreach( $var as &$v) $this->searchBuyer($v);
        }

        return $this;
    }

    function buyer2cookie(  & $var ){
        if( !is_array($var )) return $this;
        $buy=[];
        drFun::searchFromArray( $var,['buyer'],$buy  );
        if(!$buy ) return $this;
        unset($buy['']);
        $cookie = $this->getCookieName(['name'=> array_keys($buy ) ] );
        $name_cookie=[];
        foreach ($cookie as $v ){
            $name_cookie[ $v['name'] ][]= $v ;
        }
        //$this->drExit( $cookie );
        //$this->assign('cookieDebug', $name_cookie);

        foreach($var as $k=>$v ){
            if(isset( $v['buyer']) && $name_cookie[ $v['buyer'] ] ){
                $var[$k]['cookie']= $name_cookie[ $v['buyer'] ];
            }
            else   $var[$k]['cookie']=[];
        }
        return $this;
    }

    function searchBuyerFromTrade( &$trade ){
        $pay_log_id=[];
        drFun::searchFromArray( $trade, ['pay_log_id'],$pay_log_id );
        unset( $pay_log_id[0] );
        if( !$pay_log_id ) return $this;
        $pay_log = $this->createSql()->select('pay_log',['id'=> array_keys($pay_log_id)])->getAllByKey('id');
        $this->searchBuyer( $pay_log );
        if($trade['pay_log_id'] ){
            $trade['pay_log'] = $pay_log[$trade['pay_log_id']];
        }else {
            foreach ($trade as &$v) {
                if (isset($pay_log[$v['pay_log_id']])) {
                    $v['pay_log'] = $pay_log[$v['pay_log_id']];

                    $o_value = drFun::json_decode( $v['pay_log']['opt_value'] );
                    if( in_array($v['account_id'],[56,57]) &&   $o_value['text'] ) $v['pay_log']['ali_beizhu']= $o_value['text'];

                    unset($v['pay_log']['opt_value']);
                }
            }
        }
        //$this->drExit( $trade );
        return $this;
    }

    function searchMauserFromTrade( &$trade ){
        $ma_arr=[];
        drFun::searchFromArray( $trade, ['ma_user_id'],$ma_arr );
        unset( $ma_arr[0]);
        if(! $ma_arr ) return $this;

        $tall = $this->getLogin()->createTableUserMa()->getAllByKey('user_id', ['user_id'=> array_keys( $ma_arr)]);
        //$this->drExit( $tall['511'] );
        foreach( $trade as &$v ){
            if( isset( $tall[ $v['ma_user_id'] ] )){
                $v['ma_user']= $tall[ $v['ma_user_id'] ]   ;

            }
        }
        return $this;
    }

    /**
     * 通过控制台修改账号状态  主要是 禁用、备线收款、主线收款 之间切换
     * @param $account_id
     * @param $online
     * @return $this
     * @throws drException
     */
    function modifyOnlineByConsole( $account_id, $online){
        $arr=[2,1,11,4,16 ,12, 17 ]; //2禁用 1备线 11 主线
        if( !in_array($online,$arr ))  $this->throw_exception('不支持该类型修改', 2018081134);
        $account = $this->getLogin()->createQrPay()->getAccountByID( $account_id );

        $this->assign('oldAcc', $account );

        if( $online==1 &&  in_array( $account['online'] ,[17] ) ){
            $this->throw_exception('请使用扫码支付一笔0.02元才能上线', 190721001);
        }
        if( $online==1 &&  in_array( $account['online'] ,[16] ) && $this->getLogin()->getUserId()!=$account['user_id']  ){
            $this->throw_exception('无权收款仅能管理员才能启用上线', 190721002);
        }
        if(in_array($account['type'],[211,24 ] ) && $online!=2 ){
            $cnt= $this->getLogin()->createPayLog()->getCountByWhere( ['account_id'=>$account_id ] );
            //$this->throw_exception( " good=". $cnt  );
            if( $cnt<=0) $this->throw_exception( "第一次上线，请扫码付款既能启用"  );
        }
        if( $account['online']==2 && ! in_array( $this->getLogin()->getVersionBYConsole( ),[2 ]) && ( ! in_array($account['type'],[50,302,38,201,205,211,24,80,145] )   )  )  $this->throw_exception('请给我支付一笔自然解禁！', 2018081133);

        $this->getLogin()->createQrPay()->modifyAccount( $account_id , ['online'=> $online ] );
        if( in_array($account['type'],[131,130]) ) $this->getLogin()->createWeiboHelp()->updateLiveCntByAccountID( $account_id );
        return $this;
    }

    /**
     * 检查并修改设置为禁用
     * @param int $online
     * @param array $opt
     * @return $this
     * @throws drException
     */
    function checkHealth( $online=11 , &$opt=[] ){
        $tall = $this->getAccountIDByWhere( ['online'=>$online] ,['all'=>1] );
        if( !$tall ) return $this;

        $type=  isset($opt['type']) ? $opt['type']: 1;
        $fail_cnt= $opt['fail_cnt']>0 ? $opt['fail_cnt']:10  ;

        $this->tjTradeAccountHealth($tall );

        foreach($tall as $v){
            if( $v['health']['fail']>= $fail_cnt  ){
                if( $type>0 ) $this->modifyOnlineByConsole( $v['account_id'],$type  );
                $opt['re_fail_cnt']++;
                $this->logErr( "[".$online."]检查设置为禁用 fail_cnt=" .$fail_cnt."\ttype=".$type )->logErr($v);
            }
        }
        //$this->drExit( $tall );
        return $this;
    }

    function checkNoOnline( &$re){
        //$acc = $this->getLogin()->get
        $t_cn= $this->getLogin()->financeUser('all');
        $user = [];
        foreach( $t_cn as $k=>$v){
            if($k==7) continue;
            foreach($v as $v2){
                $user[$v2]= $v2;
            }
        }

        if( !$user) return $this;
        $where = ['user_id'=>array_keys($user),'online'=>[1,11]];
        //$where = ['user_id'=>array_keys($user) ];


        //$tall = $this->createSql()->select( $this->tb_account, $where    )->getAllByKey('user_id');
        $account_info = $this->createSql()->group(  $this->tb_account , ['user_id'], $where ,['user_id','count(*) as cnt'])->getAllByKey('user_id');

        $user = $this->getLogin()->createUser()->getUserFromUid( $user  );

        foreach( $user as $k=>$v ){
            if( isset($account_info[$v['user_id']] ) ){
                $v['acc_info']= $account_info[$v['user_id']];
                $re['yes'][]=$v;
            }else{
                $re['no'][]=$v;
            }
        }

        $re['health']='fail';

        return $this;
    }

    function addCookieName($cookie,$name){
        if( $cookie=='' || trim($name)==''  ) return $this;
        $var = $where = ['cookie'=>$cookie,'name'=>$name ];
        if( $this->getCookieName($where) ) return $this;
        $this->createSql()->insert( $this->tb_cookie, $var)->query();
        return $this;
    }
    function getCookieName( $where ,$opt=[] ){
        return $this->createSql()->select($this->tb_cookie, $where )->getAll();
    }

    function addCookieNamePL($cookie,$name_arr){
        if( $cookie=='' || !$name_arr) return $this;
        if( !is_array( $name_arr ) ) return $this;

        foreach ($name_arr as $k=>$v ) $name_arr[$k]= trim( $v );

        $where = ['cookie'=>$cookie,'name'=>$name_arr ];
        $name_cookie= $this->createSql()->select( $this->tb_cookie,  $where ,[0,1000],['name','cookie'])->getCol2();
        foreach( $name_arr as $name ){
            $name= trim( $name);
            if( !$name) continue ;
            if(!isset( $name_cookie[$name] )){
                $var=['cookie'=>$cookie,'name'=>$name];
                $this->createSql()->insert( $this->tb_cookie, $var)->query();
                $name_cookie[$name] = $cookie ;
            }
        }
        return $this;
    }

    /**
     * 统计目前钉钉有多少码
     * @param $ali_uid
     * @return array
     * @throws drException
     */
    function tjAliMa( $ali_uid ){

        $tb_log = $this->getLogin()->createTablePayLogTem()->getTable();
        $tall = $this->createSql()->select( $tb_log,['account_ali_uid'=>$ali_uid,'type'=>[78]  ],[0,10000],['account_ali_uid','fee'] )->getAll();
        $re=[]; $key=[];
        foreach( $tall as $v ){
            $re[ $v['account_ali_uid'] ][$v['fee']]['cnt']++;
            $key[ $v['fee'] ]++;
        }
        ksort($key );
        return ['tj'=>$re,'key'=>$key ] ;

    }

    function getQRByWhere( $where ,$opt=[] ){
        $tb_log = $this->getLogin()->createTablePayLogTem()->getTable();

        $sql= $this->createSql()->select( $tb_log, $where ,[0,10000],[ ] );

        //$this->drExit( $sql->getSQL() );

        $tall = $opt['bykey'] ? $sql->getAllByKey( $opt['bykey'] ): $sql->getAll();

        return $tall ;

    }

    /**
     * @param $trade_row
     * @param $qf_ck
     * @param array $opt
     * @return array
     * @throws drException
     */
    function changQr( $trade_row, $qf_ck, $opt=[] ){
        $where=['cookie'=>$qf_ck,'type'=>[1,11] ,'user_id'=> $trade_row['user_id'] ];

        if( $trade_row['ma_user_id']>0){
            $where['ma_user_id']=  $trade_row['ma_user_id'];
        }

        $sql= $this->createSql()->select( $this->tb_trade, $where,[0,10],['account_id','qr_id'] ,['trade_id'=>'desc']);
        $tall= $sql->getCol2();

        if(  !$tall) {
            //unset( $where['type'] );
            //$tall= $this->createSql()->select( $this->tb_trade, $where,[0,10],['account_id','qr_id'] ,['trade_id'=>'desc'])->getCol2();
        }
        if( !$tall) return $trade_row;

        if( $tall[ $trade_row['account_id'] ]    ) return $trade_row;

        $price= $trade_row['realprice'];
        $account_id_using= $this->createSql()->select( $this->tb_trade, ['account_id'=> array_keys( $tall ), 'type'=> $this->getTypeTradeUsingLimit(),'realprice'=>$price ]
            , [0,10000],['account_id'])->getCol();



        $this->clearAccount($tall, $account_id_using,['iskey'=>1 ]);

        //print_r( $tall );        $this->drExit( $account_id_using );

        if( !$tall) return $trade_row;

        $wh['online']= [1,4,11];
        $wh['account_id']= array_keys( $tall );
        $wh['>=']= ['clienttime'=> ( time()- 100 ) ];
        $account_id = $this->getAccountIDByWhere($wh);
        //$this->drExit( $account_id );
        if( !$account_id ) return $trade_row;



        $aid = $account_id[0];
        $qr_id = $tall[$aid];
        $this->upTradeByID( $trade_row['trade_id'], ['account_id'=> $aid,'qr_id'=>$qr_id ]);

        //print_r( $account_id );
        //$this->drExit("sql= ". $sql->getSQL() );
        //$this->drExit( $account_id );

        return $this->getTradeByID( $trade_row['trade_id']);

        //return $trade_row;
    }

    function getAccLoByTrade( $account_id  , $opt=[]){
        $sql="select lo, count(*) as cnt from ". $this->tb_trade." where account_id='".$account_id."' and type in(1,11) GROUP by lo";
        $trow= $this->createSql( $sql)->getAll();
        if( !$trow ) return [];
        usort($trow ,function ($a,$b){ return $a['cnt']<$b['cnt'] ;} );
        //echo  $sql.'<br>';
        //$this->drExit( $trow );
        if( $opt['isUp'] ) $this->update( $this->tb_account, ['account_id'=>$account_id ], ['lo'=> $trow[0]['lo'] ] ,$this->file_account  );
        return $trow ;
    }

    function tongBUFromLoginConfig(){
        $midConsole = $this->getLogin()->midConsole() ;

        //$this->drExit($midConsole );
        foreach( $midConsole as $mid=> $arr ){
            if( intval( $arr[0])<=0 ) continue;
            //$this->createSql()->update($this)
            $this->getLogin()->createTableMerchant()->updateByKey( $mid,['c_user_id'=>intval( $arr[0]) ]);
        }
        return ;
    }

    /**
     * @param $c_user_id
     * @param $price
     * @return array
     * @throws drException
     */
    function accountPX( $c_user_id, $price ,$opt=[] ){
        $re=[];
        $ma_user= $this->getLogin()->createQrPay()->getPayMaUser( $c_user_id, $price );
        if( !$ma_user ) return $re;

        $where=['ma_user_id'=> $ma_user ,'online'=>[1,11] ];

        if( ! $opt['noUtime'] )  $where['<=']['utime']=  time()- 60  ;

        $acc= $this->createSql()->select( $this->tb_account, $where,[0,2000],['account_id','utime','online'],['online'=>'desc','utime'=>'asc' ] )->getAll();
        if( !$acc) return $acc ;

        if( $opt['clear_account'] ){ //一单一码
            $account_id=[];
            foreach( $acc as $v )$account_id[]= $v['account_id'];
            $account_id_using= $this->createSql()->select( $this->tb_trade, [ 'account_id' => $account_id , 'type'=> $this->getTypeTradeUsingLimit() ]  //,'realprice'=>$price
                , [0,10000],['account_id' ,'account_id'])->getCol2();
            $acc2= $acc;
            $acc=[];
            foreach( $acc2 as $v ){
                if( !isset($account_id_using[ $v['account_id']] )) $acc[]= $v ;
            }
        }

        return $acc;
    }

    function accountPXV2( $c_user_id, $price ,$opt=[]  ){
        $re=[];
        $ma_user= $this->getLogin()->createQrPay()->getPayMaUser( $c_user_id, $price ,['no_clear2'=>1 ]);
        if( !$ma_user ) return $re;
        $acc= $this->getLogin()->createTablePayRank()->getAll( ['c_user_id'=>$c_user_id, 'ma_user_id'=> $ma_user  ] ,['utime'=>'asc' ]);
        return $acc ;
    }

    function accountPxWithAcc( &$acc_list, $px){
        $px_acc=[];
        $r=10000;
        foreach( $px as $k=>$v  ) {
            $ks= $k+1;
            $px_acc[$v['account_id']]= $ks;
        }
        foreach ( $acc_list as $k=>$v ) {
            $ks=  intval( $px_acc[$v['account_id']]);
            $acc_list[$k]['px']= $ks;
            if( $ks>0 ) $r= min( $r, $ks);
        }
        return $r;
    }


    function changeBudan( &$list){
        $trade_id= [];
        foreach( $list as $v ){
            $trade_id[]= $v['trade_id'];
        }
        if( !$trade_id ) return $this;
        $tv=$this->getLogin()->createTableTrade()->getColByWhere( ['trade_id'=> $trade_id],['trade_id','type'] );
        if($_GET['ds']){
            $this->drExit( $tv);
        }
        foreach($list as $k=>$v){
            $trade_id= $v['trade_id'];
            if( $tv[ $trade_id]==1){
                $list[$k]['pay_type']=110;
                $list[$k]['buyer']='';
            }
        }

        return $this;
    }

    function get205Time( $opt=[] ){
        return 15*61;
    }

    function getRealPrice( $price ){
        $yh= intval( $price*5/10000);
        if($yh<=0) return $price;
        if($yh>50) $yh=50;

        $yh= rand(1,$yh);

        $realprice= $price-$yh;

        return $realprice;
    }

    function toTelegram( $uid, $msg){
        $msg.="\n\nIP: ". drFun::getIP();
        $msg.="\nat: ". date("m-d H:i");
        //$msg."\n-------------";

        try {
            $chat_id =  $this->getLogin()->getTgChatId( $uid );
        }catch (drException $e ){
            return $this;
        }

        $data=['chat_id'=>$chat_id,'msg'=>$msg,'uid'=>$uid ];

        $this->toMqTrade( $data,'telegram');

        return $this;
    }


}
