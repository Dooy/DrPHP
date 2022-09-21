<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/4
 * Time: 22:11
 */

namespace model;


class weibo extends cpost
{
    //private $cookie='SUB=_2A25w7d2ODeRhGeFN71oZ9irJyzuIHXVQYI3GrDV6PUJbitAfLW-kkWtNQAxBPTHxxQfC4oP3uFbpn9LaMx_-DClC; SUBP=0033WrSXqPxfM725Ws9jqgMF55529P9D9WF4n-fo1umgYm7CwN662S6z5NHD95QNe0BR1hqXSK5NWs4Dqcj.i--4iK.Ri-isi--4i-zRi-isi--fi-2NiK.7i--fi-2NiK.7; SCF=Ai9aHoDqzO6L0UtgANAzlRjssEBY16IDj7BlpvZWLijic2qmRK8vC3jcvvP4CGpgrw..; SUHB=0sqB8magqK_MmF; HTTP_USER_AGENT_WEIBO=Meizu-M1813__weibo__9.12.0__android__android8.1.0; _T_WM=1142d04dc130b840bd012555f7119388; CONTENT-HONGBAO-G0=bd9d764a9c05a84ddf2cde771c48a2ed';


    public $wb_client_id='7E715572538E1A21B4A336FCAE3FB19B';
    public $wb_token='';//'1ffb1b';
    public $wb_uid='';//'7348864507';
    public $wb_name='';
    public $wb_info=[];
    private $h5_cookie='';
    private $app_cookie='';
    private $host='http://qf3.zahei.com';


    public function setH5Cookie($str){
        $this->h5_cookie= $this->anlyCookieFromJson($str);
        return $this;
    }

    public function setWeiboAppCookie( $str ){
        $this->app_cookie = $this->anlyCookieFromJson( $str );
        return $this;
    }

    public function getH5Cookie(){
        return $this->h5_cookie;
    }
    public function getWeiboAppCookie(){
        return $this->app_cookie;
    }


    private function anlyCookieFromJson( $str ){
        $arr = json_decode( $str,true);
        if( !$arr ) {
            if( strpos( $str,'; ')){

                return $str;
            }
            $this->throw_exception("请使用JSON格式",19120606 );
        }
        $cookie = '';
        foreach ($arr as $v){
            $cookie.= $v['name'].'='.$v['value'] . '; ';
            //if( $v['name']=='XSRF-TOKEN' ) $this->wb_token= $v['value'] ;
            //if( $v['name']=='XSRF-TOKEN' ) $this->wb_token= $v['value'] ;
        }
        return trim( $cookie,'; ');
    }

    function getInfo(){
        $re=['wb_uid'=> $this->wb_uid,'wb_name'=>$this->wb_name,'wb_token'=>$this->wb_token,'info'=>$this->wb_info  ];
        $re['h5_cookie']= $this->h5_cookie;
        $re['app_cookie']= $this->app_cookie;
        return $re ;
    }

    function getAppUID(){
        $url='https://mall.e.weibo.com/h5/redenvelope/create?page=2';
        $str = $this->cpost($url);
        //  $CONFIG['uid'] = '7348864507';

        $uid= drFun::cut($str,"CONFIG['uid'] = '","'");
        if( !$uid) $this->throw_exception('UID获取失败', 19121001);
        return $uid;

    }
    function checkTwoCookie(){
        $uid= $this->setClient('h5')->setUserInfo()->setClient('app')->getAppUID();
        if($uid!=$this->wb_uid) $this->throw_exception("2个COOKIE不是相同一人", 19121002 );
        return $this;
    }
    /*
    function setCookie( $cookie ){
        $this->cookie= $cookie;
        return $this;
    }
    */
    public function setClient($client){
        //$this->client= $client;
        if($client=='weibo' || $client=='app'){
            $this->setCookie( $this->app_cookie)->setUserAgent( 'Mozilla/5.0 (Linux; Android 8.1.0; M1813 Build/O11019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/65.0.3325.109 Mobile Safari/537.36 Weibo (Meizu-M1813__weibo__9.12.0__android__android8.1.0)' );
        }else{
            $this->setCookie($this->h5_cookie)->setUserAgent('Mozilla/5.0 (Linux; Android 8.1.0; M1813 Build/O11019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/65.0.3325.109 Mobile Safari/537.36');
        }
        return $this;
    }


    function getUserHongInfo(){
        $re = $this->headerPost('https://hongbao.weibo.cn/h5/pay?groupid=1000303',''  );
        $config_str = drFun::cut($re['body'],' $config={','};');
        $config_str =  strtr( $config_str,['<!--拆包页-->'=>'']) ;
        //echo  $this->cookie."\n";
        $arr= $this->str2Arr( trim( $config_str ));

        //$this->drExit($arr );
        $this->wb_name = drFun::cut( $re['body'] ,"screenName = '","';");
        //print_r( $arr );
        //$this->drExit(  $re );
        if( !$arr['st'] || !$arr['cuid']) $this->throw_exception("未必获取到token", 19120601);
        $this->wb_token= $arr['st'];
        $this->wb_uid= $arr['cuid'];

        //$this->drExit(  $this->wb_uid );
        return $this;
    }

    function setUserInfo(){

        $re= $this->headerPost('https://m.weibo.cn/profile/info');
        $json= json_decode($re['body'],true);

        //$this->drExit($re );
        if(!$json['data']['user']) $this->throw_exception("获取用户信息失败" ,19120703);

        $user=  $json['data']['user'];
        unset($user['profile_image_url']);
        unset($user['profile_url']);
        unset($user['cover_image_phone']);
        unset($user['avatar_hd']);
        $this->wb_uid= $user['id'];
        $this->wb_name = $user['screen_name']; //    [screen_name] => 阿鸿姐姐
        $this->wb_info= $user;

        $re= $this->headerPost('https://security.weibo.com/mobile/index');
        //
        $this->wb_info['mobile']= drFun::cut( $re['body'],'id="countryname">','</label>') ;//                                       <label id="countryname">156*****302</label>



        //$this->drExit( $re);
        return $this;
    }




    function create($amount,$num,$beizhu='',$opt=[]){

        $json= $this->create_d1($amount,$num,$beizhu ,$opt );
        //$json['url']='https://pay.sc.weibo.com/api/merchant/pay/cashier?sign_type=RSA&sign=SDk6bk8AFwX4IFi3Zlk6%2B8Z7bbv%2BtveuDqewriaGerESwp3PB3nNi04IBJ8AfQAVvf3LTS82IxneCXQm4wwXR7cSQ54D2NkG2kxX%2BNcZ5dJ79Tw1xWiM9g4NyUVQR4acweSKQCkowo%2FBlRR%2By0peRz%2FqcimIsHFtUBObM97Mtd0%3D&seller_id=5136362277&appkey=743219212&out_pay_id=7300006072262&notify_url=https%3A%2F%2Fhb.e.weibo.com%2Fv2%2Fbonus%2Fpay%2Fwnotify&return_url=https%3A%2F%2Fhb.e.weibo.com%2Fv2%2Fbonus%2Fpay%2Fwreturn%3Fsinainternalbrowser%3Dtopnav&subject=%E5%BE%AE%E5%8D%9A%E7%BA%A2%E5%8C%85&body=%E7%B2%89%E4%B8%9D%E7%BA%A2%E5%8C%85&total_amount=100&cfg_follow_uid=5136362277&cfg_share_opt=0&cfg_follow_opt=0';
        echo $json['url']."\n";
        $url= $json['url'] ;
        $url2= $this->create_d2(  $url);
        $arr = $this->create_d3(  $url2 );
        $this->create_d4($arr['url'], $arr['data'],$url2 );

    }

    function create_d1($amount,$num,$beizhu='',$opt=[]){

        $tooken= $this->wb_token;
        $wb_uid= $this->wb_uid;
        $arr['uid']= $wb_uid;
        $arr['groupid']='1000303';
        $arr['eid']='0';
        $arr['amount']= $amount;
        $arr['num']= $num;
        $arr['share']='0';
        $arr['_type']='1';
        $arr['isavg']='0';
        $arr['tab']='2';
        $arr['pass']='qq123456';
        $arr['passtip']= $beizhu;

        $url='https://hongbao.weibo.cn/aj_h5/createorder?st='.$tooken.'&current_id='.$wb_uid.'&_rnd='.time().rand(100,999);

        $header= ['X-Requested-With'=>'XMLHttpRequest','Accept'=>'application/json','Content-Type'=>'application/x-www-form-urlencoded' ];
        $header['User-Agent']= 'Mozilla/5.0 (Linux; Android 8.1.0; M1813 Build/O11019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/65.0.3325.109 Mobile Safari/537.36 Weibo (Meizu-M1813__weibo__9.12.0__android__android8.1.0)' ;
        $str= $this->cpost( $url, $arr, $header);

        if( !$str) $this->throw_exception("建单第一步有问题",19120401);
        $re = json_decode( $str,true);
        if( !$re['url']) $this->throw_exception("错误：". $str,19120402);
        return $re;
        ///$this->drExit($str);
    }
    function create_d2( $url ){
        //$url= strtr($url,['api/merchant/pay/cashier'=>'pay/pc/cashier']);
        //echo $url."\n";
        $header=['User-Agent'=>'Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Mobile Safari/537.36'];
        //$header=['User-Agent'=>'Mozilla/5.0 (Linux; Android 8.1.0; M1813 Build/O11019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/65.0.3325.109 Mobile Safari/537.36 Weibo (Meizu-M1813__weibo__9.12.0__android__android8.1.0)'];
        $str = $this->cpost($url,"", $header );
        return $str;
    }
    function create_d3( $str ){

        //$this->drExit( $str );
        $re=[];
        $re['url']= drFun::cut($str,'action="','"');
        $data=[];
        preg_match_all ('/<input name="([^"]+)".+value="([^"]+)"\/>/U', $str, $pat_array);
        //print_r($pat_array );
        foreach( $pat_array[1] as $k=> $v){
            $data[ $v]=$pat_array[2][$k];
        }
        $re['data']= $data;
        if( !$data||  !$re['url']) $this->throw_exception("建单第三步有问题",19120405);

        //echo $url;
        //$this->drExit( $re );
        return $re ;
        //$this->drExit( $re );
    }
    function create_d4($url, $data,$rf ){
        $str = drFun::http_build_query($data);
        $header= ['Referer'=> 'https://pay.sc.weibo.com/charge/wap/charge?sign_type=md5&source=2&uid=7348864507&out_charge_id=174445933387612812&subject=%E5%BE%AE%E5%8D%9A%E7%BA%A2%E5%8C%85&amount=10&return_url=https%3A%2F%2Fpay.sc.weibo.com%2Fpay%2Fwap%2Fcharge%2Fcallback&partner=bonus&sign=14ded488583b83c17e6d7c625ec56aad&seller_id=5136362277','origin'=>'https://pay.sc.weibo.com'];
        $this->cPostDo($url,$str,30 , $this->keyHeader($header)  );
        $this->drExit($str);
    }

    function createQunHongbao( $gid,$amount,$count,$beizhu,$opt=[] ){
        $this->setClient('weibo');

        //$re = $this->createQunHongbao_suij(  $gid,$amount,$count,$beizhu,$opt);
        $singalAmount=$amount/$count;
        if( intval($singalAmount*1000) %10>0 ) $this->throw_exception( "总额跟数量必须是倍数！", 19120901 );

        $re = $this->createQunhongBao_ding(  $gid,$amount,$count,$beizhu, $singalAmount,$opt);
        //$this->drExit($re );
        if( !$re['data']['url'] ) $this->throw_exception("错误：". $re['msg'],19120701);

        $str= $this->setClient('')->create_d2( $re['data']['url']);

        $body= drFun::cut($str,'<body>','	<script>');

        $re=['url'=>$re['data']['url'],'body'=>base64_encode($body) ];
        if( ! $body ) $this->throw_exception('获取支付信息出错',19120704);
        //$this->drExit($re );
        return $re ;
    }

    function createQunhongBao_ding($gid ,$amount,$count,$beizhu,$singalAmount ,$opt=[] ){

        $arr=[
            'bag_type'=>'1',
            'puicode'=>'',
            'msgtype'=>'2',
            'msgid'=> $gid,
            'aid'=>'01BypYpdPknahYV86jlbbX4UDz1nZyaQdrpy3G8Au9KvohG6s.',
            'singalAmount'=>$singalAmount,
            'count'=>$count,
            'blessing'=>$beizhu ,
            'amount'=>$amount ,
            '_t'=>'0'
        ];
        $url='http://mall.e.weibo.com/aj/redenvelope/create';
        $re= $this->headerPost($url ,$arr, ['X-Requested-With'=>'XMLHttpRequest']);
        return json_decode( $re['body'] ,true);

    }
    function createQunHongbao_suij($gid,$amount,$count,$beizhu,$opt=[]){
        //$this->drExit($re );
        $url='http://mall.e.weibo.com/aj/redenvelope/create';
        $arr['bag_type']='0';
        $arr['puicode']='';
        $arr['msgtype']='2';
        $arr['msgid']=$gid ;//'4446667619035347';
        $arr['aid']='01AypYpdPknahYV86jlbbX4UKz1nZyaQdrpy3G8Au9KvohX6s.';
        $arr['amount']= $amount ;
        $arr['count']=$count;
        $arr['blessing']= $beizhu;
        $arr['_t']='0';
        //$this->setReferer();

        $re= $this->headerPost($url ,$arr, ['X-Requested-With'=>'XMLHttpRequest']);

        return json_decode( $re['body'] ,true);
        //$this->drExit( $re );
    }

    function getToken(){
        $url='https://m.weibo.cn/?&jumpfrom=weibocom';

        $str= $this->cpost($url );

        $token= drFun::cut($str,"st: '","'"); //      st: '1fa408',

        //$this->drExit( $str  );
        return $token;
    }

    function sendHongbao($gid,$hb_id ){
        //$token= $this->getToken();

        //$re = $this->headerPost( 'https://mall.e.weibo.com/redenvelope/draw?set_id='.$hb_id );


        $this->getUserHongInfo();

        $url='https://m.weibo.cn/groupChat/userChat/sendMsg/';
        $arr=['group_id'=> $gid,
            'luckybag_url'=>'https://mall.e.weibo.com/redenvelope/draw?set_id='.$hb_id,
            'st'=>$this->wb_token,//, // 'adac0b'   [st] => adac0b
            'format'=>'cards'
        ];


        //$this->drExit( $arr );
        $ref=' https://m.weibo.cn/groupChat/userChat/groupListForLuckyBag?title=%5B%E9%98%BF%E9%B8%BF%E5%A7%90%E5%A7%90...%5D%E7%9A%84%E7%BA%A2%E5%8C%85&des=good123&btn=%E5%8F%91%E9%80%81&img=https%3A%2F%2Fimg.t.sinajs.cn%2Ft4%2Fappstyle%2Fred_envelope%2Fimages%2Fmobile%2Fcard_icon.png&content=%E8%AF%B4%E8%AF%B4%E5%88%86%E4%BA%AB%E5%BF%83%E5%BE%97...&luckybag_url=https%3A%2F%2Fmall.e.weibo.com%2Fredenvelope%2Fdraw%3Fset_id%3D6000061632167&name=%E5%88%86%E4%BA%AB%E7%BE%A4%E7%BB%84%E7%BA%A2%E5%8C%85';

        $re= $this->setReferer($ref)->headerPost($url ,$arr, ['X-Requested-With'=>'XMLHttpRequest']);
        return $re ;
        //$this->drExit($re );

    }

    function pickHongBao($hb_id ){
        //$re = $this->headerPost( 'https://mall.e.weibo.com/redenvelope/draw?set_id='.$hb_id );
        $re = $this->headerPost( 'https://mall.e.weibo.com/H5/Redenvelope/Draw?set_id='.$hb_id );

        preg_match_all('|CONFIG\[\'([^\']+)\'\] = "([^"]+)"|U',  $re['body'],   $out, PREG_PATTERN_ORDER);

        $rz =[];
        foreach(  $out[2] as $k=>$v ){
            $rz[ $out[1][$k]]=$v;
        }
        return $rz;
    }

    function myHongBao(){
        $ref='https://mall.e.weibo.com/h5/redenvelope/redlist';
        $re = $this->setReferer($ref)->headerPost( 'https://mall.e.weibo.com/h5/aj/redenvelope/redstaticlist',['_t'=>'0'], ['X-Requested-With'=>'XMLHttpRequest'] );

        //    <a href="https://mall.e.weibo.com/h5/redenvelope/recvdetailowner?set_id=6000061660532&uicode=">
        $re['body']=json_decode( $re['body'] ,true);

        $str= drFun::cut( $re['body']['data']['html'],'<!-- 发出的红包 -->' ,'<!-- 发出的红包 -->');
        $str2= drFun::cut( $re['body']['data']['html'],'<!-- 收到的红包 -->' ,'<!-- 收到的红包 -->');

        $re=['fa'=>[] ,'sh'=>[] ];
        $out=[];
        preg_match_all('|recvdetailowner\?set_id=([0-9]+)&|U',  $str,   $out, PREG_PATTERN_ORDER);
        $re['fa']['id']=$out[1];$out=[];
        preg_match_all('|class="time">([^<>]+)<|U',  $str,   $out, PREG_PATTERN_ORDER);
        $re['fa']['time']=$out[1];$out=[];

        preg_match_all('|class="amount">([0-9\. ]+)元<|U',  $str,   $out, PREG_PATTERN_ORDER);
        $re['fa']['amount']=$out[1];$out=[];
        preg_match_all('|([0-9\/]+)个|U',  $str,   $out, PREG_PATTERN_ORDER);
        $re['fa']['status']=$out[1];


        $out=[];
        preg_match_all('|([^\?<>"\/]+)\?set_id=([0-9]+)&|U',  $str2,   $out, PREG_PATTERN_ORDER);
        //$this->drExit( $out);
        $re['sh']['r']=$out[1];
        $re['sh']['id']=$out[2];

        preg_match_all('|class="amount fc_grey">([0-9\. ]+)元<|U',  $str2,   $out, PREG_PATTERN_ORDER);
        $re['sh']['amount']=$out[1];$out=[];

        preg_match_all("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2}) +([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})/",  $str2,   $out, PREG_PATTERN_ORDER);
        //$this->drExit( $out );
        $re['sh']['time']=$out[0];$out=[];


        //$this->drExit(  $re['body']['data']['html']  );
        return $re;
    }

    function listMemberList($url){
        //$url='https://api.weibo.com/webim/query_group.json?is_pc=1&query_member=1&sort_by_jp=1&query_member_count=5000&id='.$gid.'&source=209678993';

        $this->setReferer('https://api.weibo.com/chat/');
        $re= $this->headerPost($url);

        $rz= json_decode($re['body'],true);
        if( !$rz ) $this->throw_exception( "未获取到资料 ",19121011 );
        //    [members] => Array
        if(! $rz['members'])$this->throw_exception( "未获取到群成员",19121012 );
        return $rz ;

    }



}