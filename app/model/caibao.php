<?php
/**
 * 采宝
 * User: Administrator
 * Date: 2019/1/23
 * Time: 9:21
 */

namespace model;


class caibao extends model
{

    private $key = '37c30299e1a700aba4e982708838e272';
    private $app = 'M81604320000001';
    private $operator_id = '21c4a94c9b83917408088440d6d3ec39';
    private $md5_str='';

    function setKey( $key ){
        $this->key= $key;
        return $this;
    }

    function setApp( $app ){
        $this->app = $app;
        return $this;
    }

    function setOperatorId($operator_id){
        $this->operator_id = $operator_id;
        return $this;
    }

    public function h5post( $trade_id ){
        $trade_row = $this->getLogin()->createQrPay()->getTradeByID( $trade_id );

        $account= $this->getLogin()->createQrPay()->getAccountByID( $trade_row['account_id'] );
        $this->setByAccount( $account );
        $arr = $this->h5postDo(  $trade_row['trade_id'], $trade_row['realprice']);

        $cnt = $this->getLogin()->createTableTradeKV()->getCount( ['trade_id'=> $trade_id]);
        if($cnt>0 ){
            $this->getLogin()->createTableTradeKV()->updateByKey($trade_id,['value'=> drFun::json_encode($arr )]);
        }else{
            $this->getLogin()->createTableTradeKV()->append(['value'=> drFun::json_encode($arr ),'trade_id'=> $trade_id ] );
        }
        //echo $str_data ."\n\n<br>\n\n";
        //$this->drExit( $arr );
        return $this;
    }

    public function setByAccount( $account ){
        $this->setKey($account['zhifu_realname'] )->setApp( $account['zhifu_name']  )->setOperatorId( $account['zhifu_account']  );
        return $this;
    }

    public function h5postDo( $id ,$amount ){

        $data=['app'=>$this->app,'operator_id'=> $this->operator_id,'version'=>'2.0','sign_type'=>'MD5','command'=>'open.api.h5'];
        /*$data['local_order_no']= $trade_row['trade_id'];
        $data['amount']= $trade_row['realprice'];*/

        $data['local_order_no']= $id;
        $data['amount']= $amount;

        $data['subject']= 'VIP';
        $data['remark']= 'VIP';
        $data['confirm_way']= 'AUTO_PAY';
        $data['request_id']= $id;
        $data['request_time']= date("YmdHis");
        $data['notify_url']= 'https://qz.atbaidu.com/api/caibao/notify';

        $data['sign']= $this->createSign($data );
        $str_data=  drFun::http_build_query($data);

        $url = 'http://openapi.caibaopay.com/gatewayOpen.htm';

        $this->log("====\nPOST:\n curl -k -d " .'"'. $str_data.'" ' ."\t". $url );
        drFun::cPost( $url, $str_data );

        $this->log("====\n结果(".$id .")：\n " .'"'. $str_data.'" '   );

        $arr = json_decode( $str_data,true  );

        if( $arr['data']['url'] =='' ) $this->throw_exception("后端签名失败", 2018081128 );

        return $arr ;
    }

    function createSign( $data ,$opt=[] ){
        if( !$data || !is_array($data) ){
            $this->throw_exception('数据必须是有效数据',2018082301 );
        }
        ksort( $data );

        unset( $data['order_user_name'] ); //用户名有特殊字符 不参与sign的计算

        $str='';
        if($opt['decode']){
            foreach ($data as $k => $v) $str .= ($k) . '=' .urldecode($v) . '&';
        }else {
            foreach ($data as $k => $v) $str .= ($k) . '=' . ($v) . '&';
        }

        $str.= 'key='.$this->key;

        //echo  $str ;
        //$this->drExit( $str );
        $this->md5_str =   $str;
        return md5( ( $str));
    }
}