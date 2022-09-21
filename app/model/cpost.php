<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/6
 * Time: 13:12
 */

namespace model;


class cpost extends model
{
    private $cookie='SUB=_2A25w7d2ODeRhGeFN71oZ9irJyzuIHXVQYI3GrDV6PUJbitAfLW-kkWtNQAxBPTHxxQfC4oP3uFbpn9LaMx_-DClC; SUBP=0033WrSXqPxfM725Ws9jqgMF55529P9D9WF4n-fo1umgYm7CwN662S6z5NHD95QNe0BR1hqXSK5NWs4Dqcj.i--4iK.Ri-isi--4i-zRi-isi--fi-2NiK.7i--fi-2NiK.7; SCF=Ai9aHoDqzO6L0UtgANAzlRjssEBY16IDj7BlpvZWLijic2qmRK8vC3jcvvP4CGpgrw..; SUHB=0sqB8magqK_MmF; HTTP_USER_AGENT_WEIBO=Meizu-M1813__weibo__9.12.0__android__android8.1.0; _T_WM=1142d04dc130b840bd012555f7119388; CONTENT-HONGBAO-G0=bd9d764a9c05a84ddf2cde771c48a2ed';
    private $referer='';
    private $user_Agent='Mozilla/5.0 (Linux; Android 8.1.0; M1813 Build/O11019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/65.0.3325.109 Mobile Safari/537.36';
    private $_header ='';


    function cpost(  $url , $data='',$header= array()  , $opt=[]){
        //$header['cookie'] = $this->cookie;
        $header['X-DevTools-Emulate-Network-Conditions-Client-Id'] = $this->wb_client_id;
        $str= is_array($data)?drFun::http_build_query( $data): $data;

        //echo $str."\n" ;
        //
        $r = $this->cPostDo(   $url , $str,  30 ,$this->keyHeader($header) , $opt);
        if(!$str) {
            //print_r($r );
            $this->throw_exception( "无内容返回:",19120403 ); //. $r
        }
        //$this->drExit("good2=". $r );
        return $str;
    }


    public function setCookie($cookie){
        if( $cookie=='') $this->throw_exception( "Cookie 不允许为空！",2019120903);
        $this->cookie=$cookie;
        return $this;
    }

    public function clearCookie(){
        $this->cookie='';
        return $this;
    }

    public function getCookie(){
        return $this->cookie;
    }

    public function setUserAgent( $user_agent){
        $this->user_Agent= $user_agent;
        return $this;
    }

    function setReferer($Referer){
        $this->referer= $Referer;
        return $this;
    }

    function headerPost( $url , $data='',$header= array()  , $opt=[]){
        $str= is_array($data)?drFun::http_build_query( $data): $data;
        $opt['cookie']=1;

        //$header['User-Agent']= $header['User-Agent']?$header['User-Agent']: 'Mozilla/5.0 (Linux; Android 8.1.0; M1813 Build/O11019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/65.0.3325.109 Mobile Safari/537.36';

        if( $this->referer) $header['Referer'] = $this->referer;
        //if( $this->cookie ) $header['cookie'] = $this->cookie;
        $this->cPostDo(   $url , $str,  30 ,$this->keyHeader($header) , $opt);

        $this->referer = $url ;
        return $this->headerBody($str);
    }



    function headerBody($body){

        $re=[];
        //$arr = explode("\r\n\r\n", $body,2);
        $re['body']= trim($body) ;//$arr[1];
        $tem=[];
        $tem= explode("\r\n",  $this->getResponHead() );
        $header=[];
        foreach( $tem as $v){
            if(trim($v)=='') continue ;
            $t= explode(":",$v ,2);
            $header[$t[0]][]= trim($t[1]);
            //    [Set-Cookie] => Array
            if( $t[0]=='Set-Cookie') $this->cookieAnly( trim($t[1]) );
        }
        //print_r( $header);
        //$this->drExit( $header );
        $re['header']= $header;
        return $re ;
    }

    function str2Arr( $str ){

        $tem= explode("\n", $str);
        $header=[];
        foreach( $tem as $v){
            $t= explode(":",$v ,2);
            $header[ trim($t[0])]= trim( trim( trim(trim($t[1]),',') ,'"'),"'");

        }
        return $header;
    }

    function cookieAnly( $cookie ){
        $tem= explode(";", $cookie,2);
        $this->cookie= trim(trim( trim($this->cookie),';')."; ". $tem[0], '; ') ;
        //$this->drExit($this->cookie);
        return $this;
    }

    function keyHeader( $head_key){

        $head_key['User-Agent']=$this->user_Agent;
        if( $this->cookie) $head_key['cookie']=$this->cookie;
        $head=[];
        foreach($head_key as $key=>$v){
            $head[]=$key.': '.$v;
        }
        return $head;
    }

    function cPostDo( $url ,&$data, $timeout=0 ,$header= array(), $opt=[] ){
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url );



        if( strpos($url,'https')!== false  ){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        }else{
            //curl_setopt($ch, CURLOPT_PROXY, '47.89.11.39' );
            //curl_setopt($ch, CURLOPT_PROXYPORT, 8088 );
        }
        //



        curl_setopt ($ch, CURLOPT_HEADER, 0);
        if( $data  ){
            //if(is_array( $data )) $data= self::http_build_query( $data);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data );
        }
        if( $timeout>0 ){
            curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout );
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT , $timeout  );
        }

        if( $header  ) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        if($opt['cookie']){
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }
        $this->_header='';
        $data  = curl_exec ($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->_header = substr($data, 0, $headerSize);
        $data =  substr($data,   $headerSize, strlen($data)-$headerSize );
        $info= curl_getinfo( $ch );



        curl_close ($ch);
        //print_r( $info );
        return $info;
    }

    function getResponHead(){
        return $this->_header;
    }


}