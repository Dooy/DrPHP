<?php
/**
 * 短信
 * Created by zahei.
 * User: Administrator
 * Date: 2017/7/7
 * Time: 15:51
 */

namespace model\lib;


use model\drFun;
use model\model;

class sms extends model
{
    private $tb_log='sms_log';
    function sendSms( $mobile, $content ,&$re=[] ){
        $time = time()-24*3600;
        $cnt = $this->createSql("select count(*) as cnt from ". $this->tb_log. " where mobile='".$mobile."' and ctime>=". $time )->getOne();
        if( $cnt>=5 ) $this->throw_exception( "短信发送已超过上限",10087 );

        //$code = $this->cnanV2_sendSms(  $mobile, $content );
        $code = $this->send(  $mobile, $content  );//$this->dieXin_sendSms(  $mobile, $content );
        $var= ['mobile'=>$mobile, 'content'=>$content,'is_post'=>$code>0?1:-1,'ctime'=>time(),'code'=>$code ];
        $this->createSql()->insert( $this->tb_log, $var)->query();
        $re['code']= $code ;
        return $this;
    }

    /**
     * 这里能容纳更多的发送
     * @param $mobile
     * @param $content
     * @return int
     */
    private  function send(  $mobile, $content ){
        return $this->dieXin_sendSms(  $mobile, $content );
    }

    function getYu( $yys){
        $fun = $yys.'_getYu';
        return $this->$fun();
    }



    /**
     * 253短信接口
     * @param $mobile
     * @param $content
     * @return int
     */
    function cnanV2_sendSms( $mobile, $content ) {
        $post_data = array();
        $post_data['un'] ="N8299897";//账号
        $post_data['pw'] = "4ypVtleWAob126";//密码
        $post_data['msg']= $content;
        $post_data['phone'] = $mobile ;//手机
        $post_data['rd']=1;

        $data='';
        foreach($post_data as $k=>$v ){
            $data.= $k.'='. urlencode($v).'&';
        }
        $url="https://sms.253.com/msg/send";
        $re = drFun::cPost( $url ,$data );
        //$re = $this->spss()->cpost( $url ,$data );

        if( $re==200){
            $arr = preg_split("/[,\r\n]/",$data);
            $recode = intval( $arr[1] );
            if( $recode>0 ) return (-6000-$recode);
            return 6000;
        }else{
            return -6000;
        }

    }


    /**
     * 蝶信互联短信
     * @param $mobile
     * @param $content
     * @return int
     */
    function dieXin_sendSms($mobile, $content){
        // 帐号
        $user = '100213';
        $passwd = 'hAocEKj03211';
        $url = 'http://61.129.57.233:7891/mt?';

        // 发送短信
        $message = $content; // 填写测试短信

        $post_data =array(
            'un' => $user,
            'pw' => $passwd,
            'da' => $mobile,
            'sm' => $message,
            'dc' => 15,
            'tf' => 3
        );

        $post_data = http_build_query($post_data);
        $url .= $post_data;

        $ch = curl_init($url) ;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_TIMEOUT, 5 );
        $data = curl_exec($ch);
        parse_str($data, $res);

        if(isset($res['id'])){
            return 70000;
        }
        return  -70000 ;
    }
    function dieXin_getYu() {
        /**
         * 企业代码：0114
        密码：hc654123
        用户账号空着不填。
         */
        $user = '100213';
        $passwd = 'hAocEKj03211';
        $url = 'http://61.129.57.233:7891/bi?'; // 接口账号100213 密码hAocEKj03211

        $post_data =array(
            'un' => $user,
            'pw' => $passwd,
        );
        $post_data = http_build_query($post_data);
        $url .= $post_data;

        $ch = curl_init($url) ;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        parse_str($data, $res);
        //return isset($res['bl'])? $res['bl']: $res;
        return ['yu'=> isset($res['bl'])? $res['bl'] : $res  ];
    }

    function getSmsListWithPage( $where=[] ){
        if( !$where) $where = "1";
        $order= ['sms_id'=>'desc'];
        $list = $this->createSql()->selectWithPage( $this->tb_log,$where ,30,[],$order);
        return $list ;
    }

    /**
     * 批量生成待发短信 is_post=-100 code=100
     * @param $mobiles
     * @param $body
     * @return $this
     */
    function sms_wait( $mobiles, $body ){
        if( !$mobiles ) $this->throw_exception( '手机号码是空的！', 10088);
        //$var= ['mobile'=>$mobile, 'content'=>$content,'is_post'=>$code>0?1:-1,'ctime'=>time(),'code'=>$code ];
        //$this->createSql()->insert( $this->tb_log, $var)->query();
        $sql = "insert into ". $this->tb_log. " (mobile,content,is_post,ctime,code) values ";
        $sql2 = '';
        $body = drFun::addslashes( $body);
        if( is_array( $mobiles )){
            foreach( $mobiles as $v ){
                drFun::checkTel( $v );
                $sql2.="('".$v."','".$body."' ,-100,".time().",100),";
            }
        }else{
            drFun::checkTel( $mobiles );
            $sql2.="('".$mobiles."','".$body."' ,-100,".time().",100)";
        }
        $this->createSql( trim( $sql.$sql2,','))->query();
        return $this;
    }

    /**
     * 批量发待发短信
     * @param int $every
     */
    function send_wait( $every=2 ){
        $tall = $this->createSql()->select( $this->tb_log,[ 'is_post'=>-100 ],[0, $every ] )->getAll() ;
        if( !$tall ) return false ;
        // $var= ['mobile'=>$mobile, 'content'=>$content,'is_post'=>$code>0?1:-1,'ctime'=>time(),'code'=>$code ];
        foreach ( $tall as $v ){
            $code = $this->send( $v['mobile'],  $v['content']);
            $var= [ 'is_post'=>$code>0?1:-1 ,'code'=>$code ];
            $this->update( $this->tb_log,['sms_id'=>$v['sms_id']], $var );
            echo "\n[".date("Y-m-d H:i:s")."]". $v['mobile']."\t".$code ;
            usleep( rand( 200000, 1000000 )) ;
        }
        return true;
    }


}