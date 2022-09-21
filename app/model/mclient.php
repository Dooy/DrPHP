<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/1/6
 * Time: 11:23
 */

namespace model;


class mclient extends model
{
    function mq( $data ){

        $this->log( date("Y-m-d H:i:s"). " mq>>". json_encode( $data) );
        //print_r($body);
        //$data= json_decode($body,true );
        echo "cmd=".$data['cmd']."\n";
        switch ($data['cmd']){
            case 'cn.10086.online':
                $this->y1008online( $data);
                break;
            case 'cn.10086.order':
                $this->y1008online( $data);
                break;
            case 'cn.10086.createBill.320':
                $this->y10086CreateBill($data);
                break;
        }
        //print_r($body);
    }

    function y10086CreateBill($data){
        $y10086= $this->getLogin()->createY10086();
        try {
            $re= $y10086->setCookie($data['cookie'])->getBill($data['tel'], $data['fee'] / 100);
            $url= $re['data']['payUrl'];
            $data['back']=$y10086->getPayLink($url  );
            $data['back']['payLink']=$url;
        }catch (drException $ex ){
            $data['error']=$ex->getCode();
            $data['error_des']=$ex->getMessage();
        }
        $this->back($data);
        return $this;

    }

    function y1008online($data){
        $y10086= $this->getLogin()->createY10086();
        $data['back'] = $y10086->setCookie($data['cookie'])->getOrderList($data['tel'] ? $data['tel']:  $data['zhifu_account']);
        $this->back($data);
        return $this;
    }
    function back( $arg){
        //$re=['re'=> drFun::json_encode($re)];
        $str= drFun::http_build_query($arg);
        drFun::cPost( 'http://qf3.zahei.com/client/payLogV3Client', $str, 10);
        $this->log( date("Y-m-d H:i:s"). " back>>". json_encode( $arg) );
    }

}