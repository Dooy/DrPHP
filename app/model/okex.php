<?php


namespace model;


class okex extends model
{

    private $_name='system';
    public $_change=0;

    private  $_header= ["Accept:  application/json",
        "App-Type:  web",
        "User-Agent:  Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1",
        "Referer:  https://www.okex.me/earn",
        "Accept-Language:  zh-CN,zh;q=0.9,en;q=0.8",

        "ftID:  521002071457525.111704f0d3453e86fdf6a48de3cbb8620ee83.1010L8o0.119F78228FB7F546",
        "devId:  64791e37-1718-493f-ad81-6cc45d16d1be", //8eabc1b1-b665-4a14-9871-aacc074d872d
        "Authorization:  eyJhbGciOiJIUzUxMiJ9.eyJqdGkiOiJleDExMDE2MDAwODk1MzA1MTQ2MEEzQ0NCNEUwNkQzNTc3ZEFTbyIsInVpZCI6Iko0c1VQR2xuNWp1QXBxWHcvVW1BWFE9PSIsInN0YSI6MCwibWlkIjoiSjRzVVBHbG41anVBcHFYdy9VbUFYUT09IiwiaWF0IjoxNjAwMDg5NTMwLCJleHAiOjE2MDA2OTQzMzAsImJpZCI6MCwiZG9tIjoid3d3Lm9rZXgubWUiLCJpc3MiOiJva2NvaW4iLCJzdWIiOiIzNkM5QTQ1NTIwRDYwMkEyQzMyQTQxRjY4ODFEMzBDOCJ9.Xu7h_w2lX7Gj3hZt_heY7DhQoVyuZchp1zKzItWNH6BFyWiapAOdXL3GjGkko1eOsZkpZAZ0_7XJB5JRc7xRQg",
        "Cookie:  locale=zh_CN; viaServer=%7B%7D; Hm_lvt_01a61555119115f9226e2c15e411694e=1600087410; first_ref=https%3A%2F%2Fwww.okex.me%2Fjoin%2F1%2F2175142%3Fsrc%3Dfrom%3Aandroid-share; u_ip=MTIzLjExOS4yMzguMjQ1; u_pid=D6D6lm9rzXGuBWu; x-lid=5f3658f5950a1c948e570987099b830f0da763790cf639462cade9df955e92f7f12cde9b; PVTAG=274.408.lQgAkddh7YLbsEk6BuWBuGXzr9ml6D6D3ULMmF1a; finger_test_cookie=; ftID=521002071457525.111704f0d3453e86fdf6a48de3cbb8620ee83.1010L8o0.119F78228FB7F546; isLogin=1; Hm_lpvt_01a61555119115f9226e2c15e411694e=1600089532"
    ];


    function setHeader( $header){
        $this->_header= $header;
        return;
    }

    function getOkTokenFromRedis(){

        try{
            $str= $this->getLogin()->redisGet('ok_token_'.$this->_name);
            if($str) return 'Authorization: '.$str;
        }catch (drException $ex){
        }
        return '';

    }

    function realHeader(){
        $this->_name='me';
        $ok_token= $this->getOkTokenFromRedis();

        $_header= ["Accept:  application/json",
            "App-Type:  web",
            "User-Agent:  Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1",
            "Referer:  https://www.okex.me/earn",
            "Accept-Language:  zh-CN,zh;q=0.9,en;q=0.8",
            /*
            'ftID: 521012071456922.111d50d12992c21844a27357be5b152e1cd02.1000L8o0.F36DE898C5BE6E5E',
            'devId: 8eabc1b1-b665-4a14-9871-aacc074d872d',
            'Authorization: eyJhbGciOiJIUzUxMiJ9.eyJqdGkiOiJleDExMDE2MDAyNTk4NTMxMDhBRDI4QUM3NTdGNTAwMzEycFpIbiIsInVpZCI6Ik9JaTNXMHE1M3RRMEtMUUJmaVlYWnc9PSIsInN0YSI6MCwibWlkIjoiT0lpM1cwcTUzdFEwS0xRQmZpWVhadz09IiwiaWF0IjoxNjAwOTUzOTY0LCJleHAiOjE2MDE1NTg3NjQsImJpZCI6MCwiZG9tIjoid3d3Lm9rZXgubWUiLCJpc3MiOiJva2NvaW4iLCJzdWIiOiI4ODU1QkQwMkI1NDUwMkUyQzMyQTQxRjY4ODFEMzBDOCJ9.WMPIiWZNyNsFnJNKnbm1KAGhXCgytRNrM7JWF_fpgK27s8OOJJyMzUOscYkNBt57LzG8h_jDq9NO8W-bK_AzdA',
            'Cookie: isLogin=1; Hm_lpvt_01a61555119115f9226e2c15e411694e=1600953964; Hm_lvt_01a61555119115f9226e2c15e411694e=1600088432,1600498384; locale=zh_CN; _help_center_session=c2lELzVON2JaVGwyeWNidDFaNnAxd3M3aWdjQzhrcG1IOGJ2SDVrOFIxQUpwZW1RMVNxSWtaMmp1djhia2xBZEQzcTl5djY0MUVqQkVVZmVvVnhDeUE9PS0tTXltQ0dSbHFNamVtQldtSkV1c0Rxdz09--0b9cd076f2b9f503ef8c7ff6dcec6f53fc821cb8; __cfduid=d5d0de800da64173d4930f5bfb738f4ca1600474330; x-lid=b28719746f842127804db716e912d56196eef246134a5931d40fe79f54b8d524b6d7452a; u_ip=MTIzLjExOS4yMzguMjQ1'
            */
            $ok_token?$ok_token:'Authorization: eyJhbGciOiJIUzUxMiJ9.eyJqdGkiOiJleDExMDE2MDAyNTk4NTMxMDhBRDI4QUM3NTdGNTAwMzEycFpIbiIsInVpZCI6Ik9JaTNXMHE1M3RRMEtMUUJmaVlYWnc9PSIsInN0YSI6MCwibWlkIjoiT0lpM1cwcTUzdFEwS0xRQmZpWVhadz09IiwiaWF0IjoxNjAyODI0MjM5LCJleHAiOjE2MDM0MjkwMzksImJpZCI6MCwiZG9tIjoid3d3Lm9rZXgubWUiLCJpc3MiOiJva2NvaW4iLCJzdWIiOiI4ODU1QkQwMkI1NDUwMkUyQzMyQTQxRjY4ODFEMzBDOCJ9.KJDo4FwSMZMb6JnqLdVKlIfXUEKFshel5RgJjGX6oB4DrmWzx3ho5mk720Fb5HWMSQhSYVGXb8YGkRC7fBm9DQ',
            'ftID: 521012071456922.111d50d12992c21844a27357be5b152e1cd02.1000L8o0.F36DE898C5BE6E5E',
            'Cookie: Hm_lpvt_01a61555119115f9226e2c15e411694e=1602829653; Hm_lvt_01a61555119115f9226e2c15e411694e=1600498384; first_ref=https%3A%2F%2Fwww.okex.me%2Fearn; isLogin=1; locale=zh_CN; _help_center_session=c2lELzVON2JaVGwyeWNidDFaNnAxd3M3aWdjQzhrcG1IOGJ2SDVrOFIxQUpwZW1RMVNxSWtaMmp1djhia2xBZEQzcTl5djY0MUVqQkVVZmVvVnhDeUE9PS0tTXltQ0dSbHFNamVtQldtSkV1c0Rxdz09--0b9cd076f2b9f503ef8c7ff6dcec6f53fc821cb8; __cfduid=d5d0de800da64173d4930f5bfb738f4ca1600474330; x-lid=b28719746f842127804db716e912d56196eef246134a5931d40fe79f54b8d524b6d7452a; u_ip=MTIzLjExOS4yMzguMjQ1',
            'devId: 8eabc1b1-b665-4a14-9871-aacc074d872d'
        ];



        return $this->setHeader($_header);
    }

    function aZeHeader(){
        $this->_name='aze';

        $ok_token= $this->getOkTokenFromRedis();

        $_header= ["Accept:  application/json",
            "App-Type:  web",
            "User-Agent:  Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1",
            "Referer:  https://www.okex.me/earn",
            "Accept-Language:  zh-CN,zh;q=0.9,en;q=0.8",


            'devId: 7aaa1406-8917-4a8c-bd1a-13dd06449c77',
            'ftID: 521032071457028.01137f5e1ddeb3b50683feee8d75ac2d55a41.1010L8o0.D3068F5FC60732EE',
            $ok_token?$ok_token:'Authorization: eyJhbGciOiJIUzUxMiJ9.eyJqdGkiOiJleDExMDE2MDIyMjM5MDE0NDJBMTlEMzI4QzlFNzhEMzcxWXdJcSIsInVpZCI6InYzOUtvRXhYaGFQUW9IbzdVdExoNGc9PSIsInN0YSI6MCwibWlkIjoidjM5S29FeFhoYVBRb0hvN1V0TGg0Zz09IiwiaWF0IjoxNjAyODI5MTIyLCJleHAiOjE2MDM0MzM5MjIsImJpZCI6MCwiZG9tIjoid3d3Lm9rZXgubWUiLCJpc3MiOiJva2NvaW4iLCJzdWIiOiJBRDNCMjRBRjgwNzBGNzk1ODk0MjAzQTlBNDVFMDY0NyJ9.nP0D6-5CYsWIfXNtQXGfqNBb7_lhKuRbWfEOcm906GsIh1QoLnsLDymNCDV_RJMYDqYxOhobNwPi0WjIwwbp6w',
            'Cookie: x-lid=87e2d5274e622d52274abea69db05094c1ce8ca4ed0ea782fcac72095583c01931c74dde; u_ip=MTIzLjExOS4yMzUuMTYx; isLogin=1; _gcl_au=1.1.1146986172.1602223957; _ga=GA1.2.1854442759.1602223957; _ym_d=1602223960; _ym_uid=1602223960536191582; locale=zh_CN; Hm_lvt_01a61555119115f9226e2c15e411694e=1601435763,1602495838,1602744649,1602829120; first_ref=https%3A%2F%2Fwww.okex.me%2F; Hm_lpvt_01a61555119115f9226e2c15e411694e=1602829123'
        ];

        return $this->setHeader($_header);
    }

    function getName(){
        return $this->_name;
    }

    function doBuy(  ){
        //$this->realHeader();
        $var = $this->okexJiedai();
        $yue = $this->getYue();

        $yueBao= $this->getYueBao();

        $zjTotal=  intval( $yue['data']['total']);
        $yue['data']['total'] = $zjTotal +$yueBao;

        $amount= $this->getCanAmount($yue,$var);

        if($amount<=0){
            $strMsg= '['.$this->getName().']哎呀资金不足啊！当前余额[' .$yue['data']['total'] .']';
            if($yueBao>0 ) $strMsg.='，其中余币宝['.$yueBao.']' ;
            $this->telegramSendMessage( $strMsg );
            return $this;
        }

        $dt = intval($amount-$zjTotal);
        if( $dt >0 ){
            $this->yueSellOut( $dt  );
            $strMsg= '['.$this->getName().']余币宝赎回['.$dt.']';
            $this->telegramSendMessage( $strMsg );
            sleep(1);
        }

        //if( $rate<800 && $amount)
        $this->buy(   $amount , $var['borrowOrderId'] ,$var['period'] );
        $this->telegramSendMessage( '['.$this->getName().']【已下单 请查阅】' ." \n金额：". $amount );
        return $this;
    }

    function okexJD2Telegram(){
        $key= 'okexJD';
        $okexJD= $this->getLogin()->redisGet($key);
        //$okexJD='test';

        try {
            //$this->getLogin()->redisSet( 'okexJD', );
            $var= $this->okexJiedai();
            $rate= intval(  365*$var['rate']*10000 );
            $new=  $var['borrowOrderId'] ;// $var['investBound'].'ex'.$rate;
            $str='';
            if( $new!=$okexJD   ){
                $this->getLogin()->redisSet( $key , $new);
                if($rate>600){
                    $str.='【年化 '.($rate/100).'%】';
                    $str.="\n总量：". $var['totalAmount'];
                    $str.="\n最少：".$var['investBound'];
                    $str.="\n抵押物：".$var['risk']['pledgeCurrency'].'';
                    $str.="\n剩余：". ($var['totalAmount']- $var['subscribedAmount']);
                    $str.="\n期限：". $var['period'] .'天';
                    $str.="\n当前：". date('H:i:s');
                    //$str.="\nkey：".$new;
                }

                if( $rate>700 ){
                    $this->aZeHeader();
                    $this->doBuy();
                }
                if( $rate>830 ){
                    $this->realHeader();
                    $this->doBuy();
                }
            }
        }catch (drException $ex ){
            $str= "[抵押借贷]".$ex->getMessage();
        }
        if($str)  $this->telegramSendMessage( $str);

        return $this;
    }

    function telegramSendMessage(  $msg){

        $test = new  test();
        return $test->telegramSendMessage( -492924716 , $msg);

    }

    function okexJiedai(){

        //$this->drExit('ok');

        $re= $this->okexJiedaiItem();
        if( !$re || !$re['data']['orderList']) $this->throw_exception("未取到okex内容可能已经过期了");


        $fun= function ($a , $b){
            if($a['rate']==$b['rate'] ){

                //return substr($a['borrowOrderId'],-7)<substr($b['borrowOrderId'],-7);
                return  $a['borrowOrderId'] < $b['borrowOrderId'] ;
            }
            return $a['rate']<$b['rate'];
        };

        usort($re['data']['orderList'], $fun );
        //必须要用 usort 如果用 uasort key 不变
        return $re['data']['orderList'][0];


    }
    function okexJiedaiItem(){
        $url='https://www.okex.me/v2/asset/outer/earn/borrow-orders?currencyId=7&day=0';
        return $this->cget($url, ['guest'=>1 ]) ;
    }

    function getHeader(){

        return $this->_header;
    }

    function buy( $amount, $borrowOrderId,$day ){

        $amount = intval($amount);
        $day= intval($day);
        if( $amount<=0) $this->throw_exception('金额必须大于0',20091601);
        $data='{"amount":'.$amount.',"borrowOrderId":"'.$borrowOrderId.'","capitalType":128,"currencyId":7,"productsType":5,"targets":[{"productId":-1,"target":6}],"type":'.$day.'}';

        $url='https://www.okex.me/v2/asset/earn/invest';
        $re= $this->cPost($url, $data);

        $this->log('buy_data>>'. $data);
        $this->log('buy_re>>'. json_encode($re ) );

        return $re ;
    }

    function yueSellOut( $amount){
        $data='{"amount": '.$amount.',"currencyId":7,"investType":2}';
        if($amount<=0)$this->throw_exception('金额必须大于0',20101501);

        $url='https://www.okex.me/v2/asset/earn/invest-financial';
        $re= $this->cPost($url, $data);
        $this->log('yueSellOut_data>>'. $data  );
        $this->log('yueSellOut>>'. json_encode($re ) );
        return $this;

    }

    function getYueBao(){

        $url='https://www.okex.me/v2/asset/earn/user-asset?valuationUnit=USD';

        $re= $this->cget( $url);

        //$this->drExit($re);
        foreach ( $re['data']['earnUserOrderList'] as $v){
            if( $v['productsType']==1) return intval($v['balance']);
        }
        return 0;
    }

    function getCanAmount($yue,$var){
        $total= $yue['data']['total'];



        if($total< $var['investBound']) return 0;


        $rate= intval(  365*$var['rate']*10000 );
        $limit = 10000;
        if( $rate<700) return 0;
        if( $total>$limit && $rate<900 ) $total=$limit;
        /*
        if( $rate <900) {
            if( $var['investBound']>300 &&  $rate <800)  return 0;
                return $var['investBound'] ;
        }
        */

        $max= ($var['totalAmount']- $var['subscribedAmount']);

        if($total>$max) return $max;

        $dt = intval($total/ $var['investBound'] );
        return $dt*$var['investBound'];

    }

    function getYue(){

        $url='https://www.okex.me/v2/asset/earn/currency-asset?capitalType=128&currencyId=7&productsType=5';
        return $this->cget($url) ;

    }

    private function cget( $url, $opt=[]){

        $curl = curl_init();

        $time= time().rand(100,999);

        $c_opt= [CURLOPT_URL => $url.''.(strpos($url,'?')?'&':'?').'t='.$time,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $this->getHeader() ,
            CURLOPT_HEADER=>true
        ];
        if( $opt['guest']) unset( $c_opt[ CURLOPT_HTTPHEADER ]);
        curl_setopt_array($curl,$c_opt);
        //curl_setopt($curl, CURLOPT_HEADER, true);

         $result = curl_exec($curl);

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($result, 0, $headerSize);
        $response = substr($result, $headerSize);


        curl_close($curl);



        $re= json_decode($response,true);
        $this->modifyHeader($header);
        return $re;

    }

    function modifyHeader($header){
        //$this->drExit($header);
        $arr= explode("\r\n", $header);
        //$this->drExit($arr);
        $header=[];
        foreach($arr as $v){
            $t2= explode(':',$v);
            $header[ trim($t2[0])]= trim($t2[1]);
        }
        //$this->drExit($header);
        if( $header['X-Ok-Token']){
            $this->log('ok_token_'.$this->_name."\t". $header['X-Ok-Token'] );
            try {
                $this->getLogin()->redisSet('ok_token_' . $this->_name, $header['X-Ok-Token']);
            }catch (drException $ex ){

            }
            $this->_change++;
            //$this->getLogin()->
        }
    }

    private function cPost( $url, $data,$opt=[]){
        $curl = curl_init();

        $time= time().rand(100,999);

        $header=  $this->getHeader();
        $header[]='Content-Type: application/json;charset=utf-8';

        curl_setopt_array($curl, array(

            //CURLOPT_URL => 'https://www.okex.me/v2/asset/earn/currency-asset?t='.$time.'&capitalType=128&currencyId=7&productsType=5',
            CURLOPT_URL => $url.''.(strpos($url,'?')?'&':'?').'t='.$time,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => $header ,
            CURLOPT_POSTFIELDS=> $data
        ));

        $result = curl_exec($curl);

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($result, 0, $headerSize);
        $response = substr($result, $headerSize);

        curl_close($curl);

        $re= json_decode($response,true);
        $this->modifyHeader($header);
        return $re;
    }

}