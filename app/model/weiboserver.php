<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/6
 * Time: 21:03
 */

namespace model;


class weiboserver extends model
{
    private $host = 'http://v1.atbaidu.com';
    private $acc_id;


    /**
     * @var weibo
     */
    private $wb;
    //private $account;
    function __construct( $acc_id )
    {
        parent::__construct();
        $this->acc_id= $acc_id;
        //$this->account= $this->getLogin()->createQrPay()->getAccountByID( $acc_id );

    }

    function setHost( $host ){
        $this->host= $host; //;
        return $this;
    }

    function getHost(){
        return $this->host;
    }

    function getAttr(){
        $attr = $this->getLogin()->createTablePayAccountAttr()->getRowByWhere( ['account_id'=>$this->acc_id,'type'=>130] );
        $attr['attr']= drFun::json_decode( $attr['attr']);
        return $attr;
    }

    function getCookie(){
        $attr= $this->getAttr();
        $cookie = $attr['attr']['cookie'];
        if( !$cookie) $this->throw_exception("cookie不存在",19120609);
        return  $cookie;
    }

    /**
     * @return weibo
     */
    public function createWeibo(){
        if( $this->wb ) return $this->wb;
        $wb= new weibo();
        $where=['account_id'=> $this->acc_id ,'type'=>[131,132] ];
        $cookie= $this->getLogin()->createTablePayAccountAttr()->getAllByKey('type',$where);
        $wb->setH5Cookie($cookie[131]['attr'] )->setWeiboAppCookie($cookie[132]['attr']);
        $this->wb= $wb;
        return $wb;
    }

    public function createBill( $amount, $beizhu, $gid){
        // $gid,$amount,$count,$beizhu

        //$qun= $this->getLogin()->createTableQun()->getRowByWhere( ['account_id'=> $this->acc_id] , ['order'=>['member_count'=>'desc'] ] );
        //if( !$qun || $qun['member_count']<2 ) $this->throw_exception("至少需要2位群成员",19121018 );
        $data=['amount'=>$amount, 'beizhu'=>$beizhu,'gid'=>$gid ,'count'=> self::hongCount($amount )];
        $re = $this->postCookie( 'wb.bill', $data);
        $this->log( json_encode($re),'debug.log');
        return $this;
    }

    public static function hongCount( $amount  ){
        $cnt=1;
        if( $amount<=200) $cnt=1;
        elseif( $amount<=400) $cnt= 2;
        elseif( $amount<=1000)  $cnt=5;
        elseif( $amount<=2000)  $cnt=10;
        elseif( $amount<=4000)  $cnt=20;
        elseif( $amount<=5000)  $cnt=25;
        else{
            drFun::throw_exception(  "进支持小于5000！", 19121021 );
        }

        $singalAmount=$amount/$cnt;
        if( intval($singalAmount*1000) %10>0 ) drFun::throw_exception(  "总额跟数量必须是倍数！", 19121019 );

        return $cnt;
    }
    private function postCookie($cmd, $data=[] ){
        $d['cmd']=  $cmd;
        $d['h5']= $this->createWeibo()->getH5Cookie();
        $d['app']= $this->createWeibo()->getWeiboAppCookie();
        $d['data']= drFun::json_encode($data);
        $d['aid']= $this->acc_id;
        return $this->post( $d );
    }

    private function post(  $data ,$url='test/weibo' ){

        $str= is_array( $data)? drFun::http_build_query($data): $data ;
        $this->log( $this->host.'/'. trim($url ,'/') ,'debug.log');
        drFun::cPost( $this->host.'/'. trim($url ,'/'),$str ,10);

        return $str;
    }

    /**
     *
     * 抢红包思路：
     * 由账号找到n个群
     * 由群列出相应的群成员
     * 发包人+群成员cookie 打包下发到 爬虫服务器上
     *
     * 爬虫服务器：
     * 1.先发包
     * 2.群成员抢红包
     * 
     * @param $hb_row
     */
    public function qiang( $hb_row){
        $chatroom= $this->getLogin()->createTableQun( )->getAll(['account_id'=>$hb_row['account_id']]);

    }

}