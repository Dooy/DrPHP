<?php
/**
 * 将很多函数实化在本类中
 * User: haoce.com
 * Date: 2017/5/11
 * Time: 23:20
 */

namespace model;


use model\lib\smtp;
use model\user\login;

class drFun
{
    
    /**
     * Email 验证
     * @param $email
     * @throws drException
     */
    static public function checkEmail( $email ){
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z\\-]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (! preg_match( $pattern, $email ) ) throw  new drException( "Email 格式错误", 45);

    }

    /** 手机号码验证
     * @param $tel
     * @throws drException
     */
    static public function checkTel( $tel ){

        #if(!preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $tel) )
        if(! self::isTelNumber($tel ) )
            throw  new drException( "手机号码 格式错误", 45);;

    }
    static public function isTelNumber( $tel ){
        return preg_match('#^13[\d]{9}$|^14[^4]{1}\d{8}$|^15[^4]{1}\d{8}$|^16[\d]{9}$|^17[^4]{1}\d{8}$|^18[\d]{9}$|^19[\d]{9}$#', $tel);
    }

    static public function checkUrl( $url ){
        if (! self::isUrl($url)) {
            throw  new drException( "url格式错误！", 48);;
        }
    }

    static public function isUrl( $url ){
        return preg_match("/\b(?:(?:https?|ftp|wxp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$url);
    }

    static public function checkXss($str){
        if( is_array( $str)){
            foreach ( $str as $v ) self::checkXss( $v );
        }
        elseif( strpos($str,'<')!==false ||  strpos($str,'>')!==false) {
            self::throw_exception( "含有敏感字符",90526);
        }
    }
    static public function strToTime( $key){
        switch ($key){
            case 'lastmonth':
                if(date("m")==1) $str= ( date("Y")-1).'-12-01' ;
                else $str= date("Y").'-'.(date("m")-1).'-01';
                return ['e'=> strtotime( date("Y-m-").'01'),'s'=>strtotime($str) ];
                break;
            case 'yesterday':
                $e= strtotime( date("Y-m-d"));
                return ['s'=> $e-24*3600,'e'=>$e ];
                break;
            case 'month':
                return ['s'=> strtotime( date("Y-m-").'01')];
                break;
            case 'today':
            default:
                return ['s'=>strtotime( date("Y-m-d")) ];
        }
    }

    /**
     * setcookie重写
     * @param $name
     * @param $value
     * @param int $expire
     * @param string $path
     */
    static public function setcookie($name, $value ,$expire=0,$path='/') {
        setcookie($name, $value, $expire, $path);
    }
    static public function http_build_query( $arr){
        return  strtr( http_build_query($arr) ,  array('&amp;'=>'&') );
    }

    /**
     * 字数计算
     * @param $str
     * @return mixed
     */
    static public function wordCount( $str ){
        return str_word_count( $str );
    }

    /**
     * 数组复制 模仿jquery中的extend
     * @param array $toArr
     * @param array $srcArr 复制来源
     * @param bool $onlyReplace   true取代，保持$toArr原来的key ； false取代，$toArr的key会增加
     */
    public static function arrExtend( &$toArr, $srcArr ,$onlyReplace=true ){
        foreach( $srcArr as $k=>$v ){
            if(  isset($toArr[$k] )  ){
                $toArr[$k]= $v;
            }elseif( !$onlyReplace) {
                $toArr[$k]= $v;
            }
        }
    }

    /**
     * 签名算法
     * @param array $arr 待加密的素组
     * @param string $seckey 私密
     * @return string
     */
    static public function sign( $arr, $seckey ){
        unset( $arr['sign'] );
        ksort( $arr);
        return md5( $seckey. self::http_build_query( $arr));
    }

    /**
     * cpost
     * @param string$url
     * @param mixed $post_data
     * @return mixed
     */
    static public function curlPost($url,$post_data = ""){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,3);
        if( strpos($url,'https')!== false  ){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        }
        if(!empty($post_data)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post_data)? self::http_build_query($post_data): $post_data );
        }
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * 金额格式化 参数是分
     * @param int $fen 分
     * @return mixed
     */
    static function moneyFormat( $fen ) {
        $fen=  intval( $fen );
        $re[0]= intval(  $fen/100);
        $if = $fen%100;
        if( $if<10  ) $if= "0".$if ;
        $re[1]=  $if;
        return $re ;
    }

    /**
     * 路由 得到url链接
     * @param string $query url_query
     * @param mixed $opt 可以是字符串也可以是k=>v的数组
     * @return string
     */
    static public function rount( $query,$opt=''){
        $query = trim($query,'/');
        $url = WWW_ROOT;
        $rewrite= 1;
        if( strpos( $query,'http')!==false ){
            $url = $query;

        }elseif($rewrite){
            $url.=$query;
        }else{
            $url.='?r='.strtr( $query,array('?'=>'&') );
        }
        if( is_array( $opt)){
            $u_arr = parse_url($url) ;
            $tem= explode('?', $url,2);
            $url = $tem[0];

            $arr= [];       parse_str( $u_arr['query'] ,$arr  );
            unset( $opt['r']);
            self::arrExtend($arr, $opt, false );
            $opt=self::http_build_query( $arr);
        }
        if( $opt=='' ) return  $url ;
        $url .= ( strpos( $url,'?')==false ?'?':'&'). $opt ;
        return $url;
    }

    static public  function xml_to_array($xml)
    {
        if (!$xml) {
            return false;
        }
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    /**
     * 参考 路由 rount
     * @see rount
     * @param $query
     * @param string $opt
     * @return string
     */
    static public function R( $query,$opt=''){
        return self::rount( $query,$opt);
    }

    /**
     * 建立目录
     * @param string $rootdir 已经存在的根目录
     * @param string $dir 需要建立的更目录 可以多级如 a/b/c
     * @return string 建立文件的相对目录
     */
    static public function mkdir( $rootdir, $dir ){
        //$this->throw_exception('test') ;
        if(! is_dir( $rootdir) ) self::throw_exception('根目录不存在') ;
        $arr = preg_split("/[\/\\\]+/",$dir);
        //return $arr;
        $r= '';
        $rootdir= trim( $rootdir.'/','/'). DIRECTORY_SEPARATOR ;
        if( DIRECTORY_SEPARATOR=='/' ) $rootdir=DIRECTORY_SEPARATOR .$rootdir;

        foreach($arr as $v ){
            if( $v =='' ) continue;
            $rootdir.= $v.DIRECTORY_SEPARATOR;
            $r .=$v.'/';
            if( is_dir( $rootdir)) continue;
            if (!mkdir( $rootdir )) self::throw_exception( "权限问题" );
        }
        return $r ;
    }
    static function throw_exception( $msg, $code=114 ){
        throw new drException( $msg,$code );
    }

    /**
     * 根据arr_key中的key 修改原数组 默认是替换的
     * 用户合并 有规则的数据
     *
     *  example arrExtentByKey([a=>1,b=>1],[a=>2,c=>2,d=>2],[a=>3,b=>3,c=>3],true) 得到[a=>2,b=>1,c=>2]
     * @param array $arr
     * @param array $arr_from
     * @param array $arr_key
     * @param bool $isReplace
     */
    static public function arrExtentByKey( &$arr, $arr_from, $arr_key, $isReplace = true ){
        foreach ($arr_key as $key=>$v ){
            if(! $isReplace && isset($arr[ $key]) ) continue;
            if( isset($arr_from[$key] ) ) $arr[ $key]= $arr_from[$key];
        }
    }

    /**
     * 根据arry_key中的key 比较arr 跟 arr_new中提取不相同的数组，换句话 key在 arry_key，arr_new 都存在而且arr_new[key]!=arr[key]
     * 用在 修改 原来有老数据  有新数据过来 得到 要修改的数组
     *
     * example arrDiffByKey([a=>1,b=>1],[a=>2,c=>2,d=>2],[a=>3,b=>3,c=>3]) 得到[a=>2,c=>2]
     * @param array $arr 原来
     * @param array $arr_new 新数组
     * @param array $arr_key 需要修改的key [key=>v] 关键是3数组中的key
     * @return array
     */
    static public function arrDiffByKey( $arr,$arr_new,$arr_key ){
        $re = [];
        foreach ($arr_key as $key=>$v ){
            if( isset( $arr_new[$key] ) and  $arr_new[$key] !=$arr[$key] ) $re[$key]=  $arr_new[$key];
        }
        return $re ;
    }

    /**
     * 检查上传文件后缀
     * @param string $file 文件名称
     * @param array $me_ext
     * @throws drException
     */
    static public function uploadCheckExt( $file, $me_ext=[] ){
        $ext=$me_ext?  $me_ext:['jpg'=> 1,'png'=>1,'gif'=>1,'zip'=>1,'rar'=>1,'doc'=>1,'xls'=>1 ,'ppt'=>1 ,'docx'=>1,'xlsx'=>1 ,'pptx'=>1,'pdf'=>1,'txt'=>1];

        $path = pathinfo( $file['name'] );
        if( ! isset( $ext[ strtolower($path['extension'])] ) )self::throw_exception("文件类型不支持！" );
    }

    /**
     * 上传文件，将文件都是保存在 ROOT_PATH.'/webroot/upload' 目录下
     * @param array $file $_FILE['file']文件变量
     * @param array $opt [dir=>prefix保留路径]
     * @param string $p_dir
     * @return array
     * @throws drException
     */
    static public function upload( $file ,$opt=[] ,$p_dir='cfsup' ){ //upload
        if(isset( $opt['ext'] )) self::uploadCheckExt( $file ,$opt['ext'] );
        else self::uploadCheckExt( $file );
        $path = pathinfo( $file['name'] );
        if($opt['dir']=='yin'){
            $p_dir= 'cfsup';//'txup';
        }
        $_dir = ROOT_PATH.'/webroot/';

        if( !is_dir( $_dir.'/'.$p_dir )) self::throw_exception( '请先建立 '.$p_dir.' 目录'  );
        if( isset( $opt['dir']) and   $opt['dir']){
            $r = self::mkdir($_dir, $p_dir.'/'.$opt['dir'] .'/' . date('Y/m/d'));
        }else {
            $r = self::mkdir($_dir, $p_dir.'/' . date('Y/m/d'));
        }

        $r .=  uniqid().'.'.  strtolower($path['extension']);
        $mr = move_uploaded_file( $file['tmp_name'], $_dir.DIRECTORY_SEPARATOR.$r) ;
        if( !$mr) self::throw_exception( '移动错误！' ,335 );

        return ['file'=> $r ,'ext'=>  strtolower($path['extension']) ,'size'=> $file['size'],'name'=>$path['basename'] ];
    }

    /**
     * 腾讯上传目录
     * @param $file
     * @param array $opt
     * @return array
     */
    static public function txUpload(  $file ,$opt=[] ){
        //return self::upload( $file ,$opt,'txup' ); //cos用不起 读请求太多了
        return self::upload( $file ,$opt,'cfsup' );
    }

    static public function cfsUpload(  $file ,$opt=[] ){
        return self::upload( $file ,$opt,'cfsup' );
    }

    static public function move( $file,$new_dir){
        $path = pathinfo(  $file );
        $_dir = ROOT_PATH.'/webroot/';
        if(! is_file( $_dir. $file  )) self::throw_exception( "文件不存在无法移动",341);
        $p_dir = 'upload';
        if($new_dir=='yin' )   $p_dir = 'cfsup';//'txup';
        $r = self::mkdir($_dir, $p_dir.'/' .$new_dir.'/'. date('Y/m/d'));
        $r .=   $path['basename'];
        rename( $_dir.$file, $_dir.$r );
        return ['file'=> $r ];
    }

    /**
     * 将excel文件内容转化数组
     * @param string $inputFileName 文件路径
     * @return array
     */
    static public function excelReadToArray( $inputFileName  ){
        $dir = ROOT_PATH.'/lib/'; //get_include_path()  .PATH_SEPARATOR.
        //exit( $dir );
        //set_include_path(  $dir ); //get_include_path() . PATH_SEPARATOR . '../../../Classes/'
        include_once $dir.'PHPExcel/IOFactory.php';

        $inputFileType = \PHPExcel_IOFactory::identify($inputFileName);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objReader->setReadDataOnly(true);

        $objPHPExcel = $objReader->load($inputFileName);

        $sheetData = [];//$objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        $sheetALl = $objPHPExcel->getAllSheets();
        foreach( $sheetALl as $sheet){
            $sheetData[]=['title'=>$sheet->getTitle(),'data'=> $sheet->toArray(  null,true,true,true )];
        }
        self::trim( $sheetData );
        return $sheetData ;
    }

    /**
     * @param $data
     */
    static public function trim( &$data ){
        if( is_array( $data)){
            foreach ( $data as $k=>&$v ) self::trim( $v);
        }else{
            $data= trim( $data );
        }
    }

    /**
     * 去除 html 防止 xss htmlentities
     * @param $arr
     */
    static public function strip( &$arr ){
        foreach( $arr as $k=>$v ){
            if( is_array( $v )) self::strip( $v );
            else $arr[$k]= htmlentities( $v );
        }
    }

    /**
     * 发送邮件
     * @param $email 邮件地址
     * @param $title
     * @param $body
     * @return bool
     */
    static public function sendMail( $email,$title,$body){

        //smtp 协议已经被封停 只能通过外部的smtp来发邮件
        /*
        $_qq=[];
        $_qq['server'] ='127.0.0.1';
        $_qq['auth_username'] = 'all@mail.haoce.com';
        $_qq['auth_password'] = 'hc@98test!';//A!81ECE*95C81E(E72CEA6A5ABA

        //$_qq['auth_username'] = 'easyclick@pigai.org';
        //$_qq['auth_password'] = 'easyclick.123gg';//给坤诚使用的
        $_qq['port'] =2052;// 25; //2052
        $_qq['auth'] = 1;
        $_qq['charset'] = 'utf-8';
        $_qq['isdai'] = 1;//是否允许 验证邮箱跟分送邮箱不一致
        $_qq['from'] =  'service@haoce.com'; //trim(urldecode( $_POST['from']));
        $_qq['cn_name']= "好策";//trim(urldecode( $_POST['cn_name']));

        $mail = new smtp();
        $re = $mail->sendMailBySocket( $email ,$title,$body,$_qq);
        */
        $arr= ['title'=>$title,'to_mail'=> $email,'body'=>$body ];
        $str = drFun::curlPost( 'http://117.79.131.115/engine/test/mail_smtp_api.php', $arr);
        $re_arr = self::json_decode( $str );
        $re = $re_arr['re'];
        return $re ;

    }

    /**
     * 获取验证码
     * @param $text
     * @param array $opt
     */
    static public function imgYzm($text, $opt=[]){
        $im = imagecreatetruecolor(62, 25);


        //$white = imagecolorallocate($im, 255, 128, 255);
        //$white = imagecolorallocate($im, 192, 220, 192);
        $white = imagecolorallocate($im, 238 , 238, 238);
        //$grey = imagecolorallocate($im, 192, 220, 192);
        $grey = imagecolorallocate($im, 128, 128, 128);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, 399, 29, $white);
        $font = ROOT_PATH. '/lib/arial.ttf';

        // Add some shadow to the text
        imagettftext($im, 20, 0, 2, 23, $grey, $font, $text);

        // Add the text
        imagettftext($im, 20, 0, 1, 22, $black, $font, $text);

        // Using imagepng() results in clearer text compared with imagejpeg()
//        if( isset( $_GET['debug'] ) ){
//            die("set code v2=". $_SESSION['hd_code'] .' ; v2='. $text );
//        }
        header("Content-type: image/png");
        imagepng($im);
        imagedestroy($im);
    }

    static public function vCodeYzm(){
        session_start();
        require_once  ROOT_PATH.'/lib/vcode/Vcode.php';
        $dir= ROOT_PATH.'/lib/vcode/';
        //$verify = new \Vcode("num", 5, 16, null, 70); //仅数字计算结果
        //$verify = new \Vcode();//字母 数字 汉字 结果
        $verify = new  \Vcode('img', 2, 18, 150, 250, false, true, 0, 0
            , $dir."/src/img/" . mt_rand(1, 19) . ".jpg", $dir.'/src/font/msyhbd.ttc', [255, 250, 250]);;
        $_SESSION['v_code'] = json_encode( $verify->getData());
        $verify->show();
    }

    /**
     * 从数组中查找 有关于key建的值
     *
     * <code>
     * $arr=['uid'=>123,['uid'=>444],['uid'=>7],'d'=4444];
     * $re=[];
     * drFun::searchFromArray( $arr,['uid'],$re );
     * print_r($re); //得到[123=>123,444=>444,7=>7]
     * </code>
     * @param array $arr
     * @param array $keys
     * @param $re
     */
    public static function searchFromArray(  &$arr, $keys=['user_id'] , &$re ){
        foreach( $arr as $k=> $v ){
            if( is_array( $v)) self::searchFromArray( $v, $keys, $re );
            elseif( in_array( $k, $keys)){
                $re[ $v ]= $v;
            }
        }
        //return $this;
    }

    static function isProxy($url){

        if( strpos( $url,'tp://120.76.201.101')) return true;
        if( strpos( $url,'ttp://119.23.239.147')) return true;
        if( strpos( $url,'tp://120.79.54.142')) return true;
        if( strpos( $url,'ttp://pay.x23qf1.cn')) return true;
        return false;
    }

    /**
     * @param $url
     * @param $data
     * @param int $timeout
     * @param array $header
     * @return http_code
     */
    static function cPost( $url ,&$data, $timeout=0 ,$header= array(), $opt=[] ){
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url );



        if( strpos($url,'https')!== false  ){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        }elseif(  isset( $opt['proxy']) || drFun::isProxy( $url ) ){
            curl_setopt($ch, CURLOPT_PROXY, '47.89.11.39' );
            curl_setopt($ch, CURLOPT_PROXYPORT, 8088 );
        }
        //

        /* //代理
        if ( isset( $opt['proxy']) ){
            curl_setopt($ch, CURLOPT_PROXY, $opt['proxy']['ip'] );
            curl_setopt($ch, CURLOPT_PROXYPORT, $opt['proxy']['port'] );
        }elseif ( in_array( strtolower($_SERVER['HTTP_HOST']),['qunfu.zahei.com','imsg.zahei.com'] ) ){

        }else {
            curl_setopt($ch, CURLOPT_PROXY, '193.112.201.59' );
            curl_setopt($ch, CURLOPT_PROXYPORT, 8088 );
        }
        */


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

        $data  = curl_exec ($ch);
        $data = trim($data);
        $info= curl_getinfo( $ch );
        curl_close ($ch);
        //print_r( $info );
        return $info['http_code'];
    }

    /**
     * 下载文件
     * @param string $file  文件正式路径
     * @param string $name 文件保存名字可中文
     * @param array $opt
     */
    static function download( $file , $name, $opt=[] ){
        if(! is_file( $file)) self::throw_exception( "文件不存在！",337);
        $path = pathinfo( $file );
        $ext = $path['extension'];
        $name = strtr( $name, array(' '=>'_') );
        $theFileName= $name.".". $ext;
        header ("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header ("Content-Type: application/octet-stream");
        header ("Content-Length: " . filesize($file));
        header ("Content-Disposition: attachment; filename=$theFileName");
        readfile($file);
    }

    /**
     * 字符串转换时间截
     * @param string $str
     * @param string $error
     * @return int
     * @throws 时间格式有错误 338
     */
    static function str2time( $str, $error="时间格式有错误！" ){
        $time =  strtotime( $str );
        if( ! $time ) self::throw_exception($error,338 );
        return $time;
    }

    static function json_encode( $arr ){
        $rp =['\r'=>'','\n'=>'[BR]'];
       return  strtr( json_encode(  $arr ), $rp);
    }

    static function json_decode( $str ){
        return json_decode( strtr($str,['[BR]'=>'\r\n',"\n"=>'\n',"\r"=>'\r'] ), true );
    }

    /**
     * 回收添加
     * @param int $user_id 一般都是被删除人的user_id
     * @param int $op_type
     * @param mixed $op_value
     * @param array $opt
     */
    static function recycleLog( $user_id, $op_type, $op_value, $opt=[]){
        $login = new login();
        $login->createLogRecycle()->append( $user_id, $op_type, $op_value, $opt );
    }

    /**
     * 数组重置清洗
     * <code>
     * $ex_arr=[e1=>'good',e2=>'nes',e3=>'123'];
     * $key_var=[e1=>r1,e2=>r2,e_no=>r_no];
     * $re=arrayKeyReset($ex_arr,$key_var );
     * print_r($re); //[r1=>'good',r2=>'nes']
     * </code>
     * @param $ex_arr
     * @param $key_var
     * @return array
     */
    static function arrayKeyReset( $ex_arr, $key_var ){
        $re = array();
        foreach (  $key_var as $k=>$v  ){
            if( isset( $ex_arr[ $k])) $re[$v]= $ex_arr[ $k];
        }
        return $re ;
    }

    /**
     * 数组清洗
     * @param $arr
     * @param $key
     */
    static function arrayClear( &$arr, $key){

        foreach( $arr as &$var){
            if( is_array($key)){
                foreach( $key as $k2) unset($var[$k2]);
            }else{
                unset($var[$key]);
            }
        }
    }

    /**
     * 将字符串或者数据转化为一行
     * @param $arr
     * @return string
     */
    static function line( $arr ){
        $line="\r\n";
        $str = is_array($arr)?json_encode($arr): strtr( $arr,["\n"=>'\n',"\r"=>'\r'] );
        return $str. $line ;
    }

    /**
     * 防止注入
     * @param $str
     * @return string
     */
    static function addslashes($str)
    {
        return  get_magic_quotes_gpc() ?addslashes(stripcslashes($str)): addslashes($str) ;
    }

    /**
     * 获取终端 1web , 2 web-wap ,3app
     * @return int
     */
    static function getClient(){
        //0未定义 1web , 2 web-wap ,3app-anzuo 4 app-ios 5未知APP

        //MB_os[ios]
        if( $_REQUEST['MB_os']['ios']) return 4;
        if( $_REQUEST['MB_os']['android']) return 3;
        if( isset($_REQUEST['MB_os']) ) return 5;
        if(strpos($_SERVER["HTTP_USER_AGENT"],'Html5Plus') )  return 7;
        if(strpos($_SERVER["HTTP_USER_AGENT"],'MicroMessenger') )  return 6;
        if( self::isMobile() ) return 2;
        return 1;
    }
    static function isMobile(){
        //if()
        $useragent=isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $useragent_commentsblock=preg_match('|.∗?|',$useragent,$matches)>0?$matches[0]:'';

        $mobile_os_list=array('Google Wireless Transcoder','Windows CE','WindowsCE','Symbian','Android','armv6l','armv5','Mobile','CentOS','mowser','AvantGo','Opera Mobi','J2ME/MIDP','Smartphone','Go.Web','Palm','iPAQ');
        $mobile_token_list=array('Profile/MIDP','Configuration/CLDC-','160×160','176×220','240×240','240×320','320×240','UP.Browser','UP.Link','SymbianOS','PalmOS','PocketPC','SonyEricsson','Nokia','BlackBerry','Vodafone','BenQ','Novarra-Vision','Iris','NetFront','HTC_','Xda_','SAMSUNG-SGH','Wapaka','DoCoMo','iPhone','iPod');

        $found_mobile= self::CheckSubstrs($mobile_os_list,$useragent_commentsblock) ||
            self::CheckSubstrs($mobile_token_list,$useragent);

        if ($found_mobile){
            return true;
        }else{
            return false;
        }
    }
    static function isWeixin(){
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false )      return true;
        return false;
    }

    static function getClientV2(){
        $useragent=isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if( strpos( $useragent, 'OppoBrowser')  || strpos( $useragent, 'OPPO') ) return 11;
        if( strpos( $useragent, 'VivoBrowser')  || strpos( $useragent, 'vivo')) return 12;
        if( strpos( $useragent, 'iPhone')) return 101;
        if( strpos( $useragent, 'Android')) return 1;
        return 0;
    }
    static function getLastMonth(){
        $year= date("Y");
        $m=  date("m");
        if(  $m ==1){
            $year--;
            $m=12;
        }else{
            $m--;
        }
        if( $m<10 ) $m= '0'.$m;
        $ystr = $year.'-'. $m.'-01';

        return strtotime( $ystr );
    }

    static function CheckSubstrs($substrs,$text){
        foreach($substrs as $substr)
            if(false!==strpos($text,$substr)){
                return true;
            }
        return false;
    }

    static function checkStopWord( $word ){
        if( !$word ) return  ;
        $file = ROOT_PATH.'/config/stop_word.txt';
        $handle = fopen( $file , "r");

        if ( !$handle) return   ;
        $i=0;
        while (!feof($handle)) {
            $buffer = trim( fgets($handle, 4096));
            $i++;
            if( !$buffer) continue ;
            if( stripos( $word,$buffer) !==false  ){
                fclose($handle);
                self::throw_exception(   "哎呦！碰到敏感词: " .$buffer ,11401);
            }
        }
        fclose($handle);
        return    ;
    }

    static function cdnImg( &$arr,$key_array , $type='http'){

        if(! is_array($arr) || !$arr || !$key_array ||! is_array($key_array )) return ;

        $host='https://qz.atbaidu.com/';
        $host='https://cdn.nekoraw.com';
        if($_SERVER['SERVER_ONLINE'] =='debug') $host ='http://'.$_SERVER['HTTP_HOST'].'/';
        //if( $type=='txcos' ) $host='http://txcos-1253971217.costj.myqcloud.com/';//https://txcos.haoce.com/
        //if( $type=='txcos' ) $host='http://txcos.haoce.com/';//


        foreach( $arr as $k=>&$v){
            if( is_array( $v )) self::cdnImg($v,$key_array, $type );
            elseif( in_array($k, $key_array) && $k!==0 ){
                if( !$v ) continue;
                if( substr( $v,0,4)=='http' ) continue;
                //$host =  strpos($v,'txup')!==false ? 'https://txcos.haoce.com/':'https://cdn.haoce.com/';
                $v= $host. $v;
            }
        }
    }

    static function getSession($key){
        session_start();

        return $_SESSION[$key];
    }

    static function setSession($key,$value){
        session_start();
        $_SESSION[$key]= $value;
    }

    /**
     *
     * @param $src 源图片路径
     * @param null $width 缩略图宽度（只指定高度时进行等比缩放）
     * @param null $height 缩略图高度（只指定宽度时进行等比缩放）
     * @param null $filename 保存路径（不指定时直接输出到浏览器）
     * @return bool
     */
    static function imgResize($src, $width = null, $height = null, $filename = null) {

        if (!isset($width) && !isset($height)) return false;
        if (isset($width) && $width <= 0)   return false;
        if (isset($height) && $height <= 0)     return false;
        $size = getimagesize($src);
        if (!$size) return false;
        list($src_w, $src_h, $src_type) = $size;
        $src_mime = $size['mime'];
        switch($src_type) {
            case 1 :
                $img_type = 'gif';
                break;
            case 2 :
                $img_type = 'jpeg';
                break;
            case 3 :
                $img_type = 'png';
                break;
            case 15 :
                $img_type = 'wbmp';
                break;
            default :
                return false;
        }
        if (!isset($width)) $width = $src_w * ($height / $src_h);
        if (!isset($height)) $height = $src_h * ($width / $src_w);
        $imagecreatefunc = 'imagecreatefrom' . $img_type;
        $src_img = $imagecreatefunc($src);
        $dest_img = imagecreatetruecolor($width, $height);
        imagecopyresampled($dest_img, $src_img, 0, 0, 0, 0, $width, $height, $src_w, $src_h);
        $imagefunc = 'image' . $img_type;
        if ($filename) {
            $imagefunc($dest_img, $filename);
        } else {
            header('Content-Type: ' . $src_mime);
            $imagefunc($dest_img);
        }
        imagedestroy($src_img);
        imagedestroy($dest_img);
        return true;
    }

    /**
     * 计算中英文字数 “I am2 good-new_man 中国人！”7字
     * @param $str UTF8的字符串
     * @return int
     */
    static function wordCountEnAndCn($str){
        $str = strip_tags( $str );
        $len= str_word_count($str,0,'0123456789-_');
        $str = preg_replace('/[a-zA-Z0-9\-_ \r\n\t]+/', '', $str, -1);
        $num = mb_strlen($str, 'utf8');
        return $num+ $len;
    }


    static function numFormat( &$list, $key, $opt=[]){
        $bei= $opt['bei']>0?$opt['bei'] : 1000;
        if( !is_array( $list)) return false;
        if( isset($list[$key]) ) $list[$key]= intval(0.5+$list[$key]/$bei );
        else{
            foreach ( $list as $k=>$v ){
                if( isset( $v[$key])){
                    $list[$k][$key]= intval(0.5+ $list[$k][$key]/$bei );
                }
            }
        }
        return true;
    }

    static function  getCdn(){
        if( $_SERVER['SERVER_ONLINE']=='debug' ) return '';
        //if( drFun::getSkin()=='1001') return 'https://cdn.biqiug.com';

        return 'https://cdn.nekoraw.com';
        return 'https://cdn.becunion.com';
        return 'https://cz.easepm.com';
        if($_SERVER['HTTP_X_IP_VERSION']=='V6') return '';
        if( $_SERVER['SERVER_ONLINE']=='qqyun' ) return 'https://cdn.abd.com';
        return '';
    }
    static function getHttpHost(){
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * 从文本中取到标题
     * @param $text
     * @param int $_cn_len
     * @param int $_len
     * @return string
     */
    static function getTitleFromText( $text ,$_cn_len=20,$_len=100 ){
        $text= trim( trim( $text),"\n");
        $tarr = explode("\n", $text,2);
        $title= $tarr[0];
        $len= mb_strlen( $title,'utf-8');
        $stlen= strlen( $title);
        if($len<$_cn_len|| $stlen<$_len) return $title;
        $title = mb_substr( $title,0,20,'utf-8')  ;
        return $title;
    }

    static function getIP() {
        /*
        if(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $onlineip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $onlineip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $onlineip = $_SERVER['REMOTE_ADDR'];
        }
        */
        $onlineip= self::getIpAll();
        preg_match("/[\d\.]{7,15}/", $onlineip, $onlineipmatches);
        $onlineip = $onlineipmatches[0] ? $onlineipmatches[0] : 'unknown';
        unset($onlineipmatches);
        return $onlineip;
    }

    static function getIpAll(){
        /**
         * if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $onlineip = getenv('HTTP_CLIENT_IP');
        } else
         * if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $onlineip = getenv('HTTP_CLIENT_IP');
        } else
         */
        $onlineip='';



        if(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $onlineip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $onlineip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $onlineip = $_SERVER['REMOTE_ADDR'];
        }
        return $onlineip;
    }
    static function getRealIP(){
        if( $_SERVER['HTTP_CF_CONNECTING_IP'] ) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if( $_SERVER['HTTP_ALI_CDN_REAL_IP'] ) return $_SERVER['HTTP_ALI_CDN_REAL_IP'];
        if( $_SERVER['HTTP_X_REAL_IP'] ) return $_SERVER['HTTP_X_REAL_IP'];
        //return self::getIP();
        return '';
    }

    static function rankStr($num , $char = '0123456789abcdefghijklmnopqrstuvwxyz'){
        $num= intval( $num );
        $char_num = strlen( $char);
        if( $num<=0 || $char_num<=0)  return '';
        $string='';
        for($i = $num; $i > 0; $i--) {
            $string .= $char[mt_rand(0, $char_num - 1)];
        }
        return $string;
    }

    static function cut( $str, $s_str, $e_str){
        $s_post = strpos( $str  , $s_str ,0 );
        if( $s_post===false ) return '';
        $s_post+= strlen($s_str );
        $e_post = strpos($str,$e_str,  $s_post);
        if( $e_post===false ) return '';
        return substr( $str,$s_post,$e_post-  $s_post );
    }

    static function yuan2fen($yuan){
        $yuan=strtr( $yuan,[','=>'','￥'=>'','+'=>'','元'=>'']);
        $fen= intval( ($yuan+0.0009)*100);
        return $fen;
    }

    static function cha90($ali_uid,$stime,$end){
        $arr['data']=['u'=>$ali_uid, 'startTime'=> $stime*1000, 'endTime'=> $end*1000 ,'from'=>'bu90' ];
        $arr['cmd']='bill';
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$ali_uid.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }

    static function createBill($account_ali_id,$pay_ali_id,$money,$remark='' ){
        $data='';
        $arr['cmd']='createBill';
        $arr['data']=['u'=>$pay_ali_id,'d'=>$remark,'p'=> $money ,'k'=>$remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$account_ali_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        return $data;
    }


    static function createKoulin( $account_ali_id,$scode,$trade_id ){
        $data='';
        $arr['cmd']='kl';
        $arr['data']=['scode'=>$scode,'id'=>$trade_id ];

        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$account_ali_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }
    static function sendMsgAli( $account_ali_id,$to_ali_id,$msg){
        $data ='';
        $arr['cmd']='sendTextMsg';
        $arr['data']=['u'=>$to_ali_id,'m'=> $msg ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$account_ali_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        return $data;
        //{"cmd":"sendTextMsg","data":{"m":"good news","u":"2088002122250336"}}
    }
    static function createWxqr($wx_id,$money,$remark='' ){
        $arr=[];$data='';
        $arr['cmd']='wx.qr';
        $arr['data']=["m"=>$money ,"r"=> $remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        return $data;
    }
    static function taoQunQr($tao_id,$qid){
        //{"cmd":"qun.url","data": {"qid":"0_G_3914560573#3_1591433546499_0","id":"0_G_3914560573_1591433546499"}}
        $arr=[];$data='';
        $arr['cmd']='qun.url';
        $arr['data']=["id"=>$qid];
        $url = 'http://imsg.zahei.com/icomet/push?cname=TB'.$tao_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }
    static function taoQunQuery($tao_id,$apiName, $data){ //$qid,$task_id
        //{"cmd":"qun.query", "data":{"apiName":"mtop.taobao.chatting.group.task.approve","data":{"groupId":"0_G_3914560573_1591433546499","taskId":"26c31eb20567455c856901ed0a8bc8b0","taskResult":"1"}}}
        $arr=[];
        $arr['cmd']='qun.query';
        $arr['data']=["apiName"=>$apiName,'data'=>$data];
        $url = 'http://imsg.zahei.com/icomet/push?cname=TB'.$tao_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }
    static function taoQunClear($tao_id,$qid){
        //{"cmd":"qun.del","data": {"qid":"0_G_3914560573#3_1591433546499_0"}}
        //return ;
        $arr=[];$data='';
        $arr['cmd']='qun.del';
        $arr['data']=["qid"=>self::qidToDai3($qid).'_0' ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=TB'.$tao_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }
    static function sendTaoMsg($tao_id,$qid, $msg){
        //{"cmd":"qun.msg","data": {"qid":"0_G_3914560573#3_1591433546499","msg":"我是啥","summary":"我靠啊"}}
        $arr=[];$data='';
        $arr['cmd']='qun.msg';
        $arr['data']=["qid"=>self::qidToDai3($qid) ,"msg"=> $msg ,'summary'=>$msg];
        $url = 'http://imsg.zahei.com/icomet/push?cname=TB'.$tao_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }
    static function qidToDai3($qid){
        if( strpos($qid,'#3')) return $qid;

        $t = explode('_', trim($qid) );
        $qid= $t[0].'_'.$t[1].'_'.$t[2].'#3_'.$t[3]  ;
        return $qid;
    }
    static function dai3ToQid($qid){
        return strtr( $qid,['#3'=>'']);
    }
    static function sendWxMsg($wx_id,$t_wx_id, $msg){
        $arr=[];$data='';
        $arr['cmd']='wx.sendMsg';
        $arr['data']=["m"=>$msg ,"t"=> $t_wx_id ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }
    static function delQunMember($wx_id,$chatroom, $member){
        //{"cmd":"qun.del","data":{"gid":"24097739083@chatroom","uid":"dooy520"}}
        $arr['cmd']='qun.del';
        $arr['data']=["gid"=>$chatroom ,"uid"=> $member ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }

    static function aliQunQr($aliUid, $gid){
        //{"cmd":"qrGroup","data":{"gid":"0067890000020200215223650181"}}
        $arr['cmd']='qrGroup';
        $arr['data']=["gid"=>$gid   ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$aliUid.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }

    static function aliClearQunMember( $aliUid, $gid){
        //{"cmd":"clearGroup","data":{"gid":"0067890000020200215223650181"}}
        $arr['cmd']='clearGroup';
        $arr['data']=["gid"=>$gid   ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$aliUid.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }

    static function aliDelQunMember( $aliUid, $gid, $uid ){
        //{"cmd":"delMGroup","data":{"gid":"0067890000020200215223650181","uid":"2088722481576992"}}
        $arr['cmd']='delMGroup';
        $arr['data']=["gid"=>$gid ,'uid'=> $uid  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$aliUid.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }

    static function aliDelQunQr( $aliUid, $gid, $qr){
        //{"cmd":"qr.del","data":{"gid":"0067890000020200215223650181","qr":"https://qr.alipay.com/cgx14089ypqcq5nlmhlnae4" }}
        $arr['cmd']='qr.del';
        $arr['data']=["gid"=>$gid ,'qr'=> $qr  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$aliUid.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );

    }
    static function clearQunMember( $wx_id,$chatroom ){
        $arr['cmd']='qun.delall';
        $arr['data']=["gid"=>$chatroom  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }
    static function qunMemberList( $wx_id,$chatroom ){
        $arr['cmd']='qun.list';
        $arr['data']=["gid"=>$chatroom  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }



    static function wxQunqr($wx_id,$chatroom){
        //{"cmd":"qun.qr","data":{"gid":"24097739083@chatroom"}}
        $arr['cmd']='qun.qr';
        $arr['data']=["gid"=>$chatroom   ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }
    // {"cmd":"qun.qrdel","data":{"gid":"23302057968@chatroom","qr":"https://weixin.qq.com/g/A1TiHyEwTWM8NEPv" }}
    static function delQunQr($wx_id,$chatroom,$qr ){
        $arr= [];
        $arr['cmd']='qun.qrdel';
        $arr['data']=["gid"=>$chatroom ,"qr"=> $qr ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=W'.$wx_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }
    static function activeShou(  $account_ali_id,$group_id, $money,$remark){
        //{"cmd":"activeShou","data":{"handle":"80182019","m":"80182019","g":"0315290000020190301002610334","ePrice":"12.00","n":"2","allPrice":"24.00"}}
        $data='';
        $arr= [];
        $arr['cmd']='activeShou';
        $money=''.$money;
        $arr['data']=['g'=>$group_id,'m'=>$remark,'handle'=>$remark ,'ePrice'=> $money,"n"=>"1","allPrice"=>$money ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$account_ali_id.'&content='.urlencode( json_encode( $arr ) );
        //drFun::cPost( $url,$data,10  );
        file_get_contents( $url );
        return $data;

    }
    static function delFriend($account_ali_id,$ali_id){
        $data='';
        $arr['cmd']='delF';
        $arr['data']=['u'=>$ali_id  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=C'.$account_ali_id.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        return $data;

    }

    static function createDingBill( $dingID, $money, $remark ){
        $data='';
        $arr['cmd']='createBill';
        $arr['data']=['m'=>$money,'r'=>$remark,'o'=> $remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=D'.$dingID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    static function createUniQr( $uniID, $money, $remark ){
        $data='';
        $arr['cmd']='qr';
        $arr['data']=['money'=>$money,'remark'=>$remark,'id'=> $remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=N'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    static function createB2JDQr( $uniID,$money, $remark,$bank ){
        $arr['cmd']='qr';
        $arr['data']=['amount'=>$money,'id'=> $remark ,'bn'=>$bank  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=JD'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
    }

    static function createB2AlipayQr( $uniID, $money, $remark,$bank,$type){
        $data='';
        $arr['cmd']='qr';
        $arr['cmd']='qr';
        $arr['data']=['amount'=>$money,'remark'=>$remark,'id'=> $remark ,'bank'=>$bank,'type'=>$type  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=BA'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    static function updateB2Alipay( $uniID, $alipayNo){
        $data='';
        $arr['cmd']='bill';
        $arr['data']=['alipayNo'=>$alipayNo  ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=BA'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        return $data;
    }

    static function createPingAnQr( $uniID, $money, $remark ){
        $data='';
        $arr['cmd']='qr';
        $arr['data']=['money'=>$money,'remark'=>$remark,'id'=> $remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=P'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    static function icometTrade( $cname, $trade ){
        unset( $trade['notify_url']);
        unset( $trade['return_url']);

        $url = 'http://imsg.zahei.com/icomet/push?cname='.$cname.'&content='.urlencode( json_encode( $trade ) );
        file_get_contents( $url );
    }

    /**
     * 云闪付建立二维码
     * @param $uniID
     * @param $money
     * @param $remark
     * @param $y_money
     * @return string
     */
    static function createUniQrV2( $uniID, $money, $remark , $y_money){
        $data='';
        $arr['cmd']='qr';
        $arr['data']=['money'=>$money,'remark'=>$remark,'id'=> $remark ,'m2'=>$y_money ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=N'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    /**
     * 云闪付更新
     * @param $uniID
     * @return string
     */
    static function updateUniQr($uniID){
        $data='';
        $arr['cmd']='time';
        $arr['data']=['time'=>12000];
        $url = 'http://imsg.zahei.com/icomet/push?cname=N'.$uniID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;

    }


    static function createDingBillV2( $dingID, $money, $remark ,$f_dingID ){
        $data='';
        $arr['cmd']='setGroup';
        //$arr['data']=['n'=>'cmd_'.$f_dingID.'_'.$money.'_'.$remark.'_'. $remark];
        $arr['data']=['n'=>'cmd_'.$f_dingID.'_'.$money.'_收款账单_'. $remark];
        $url = 'http://imsg.zahei.com/icomet/push?cname=D'.$dingID.'&content='.urlencode( json_encode( $arr ) );
        //die( $url );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    static function createDingBillV3( $dingID, $money, $dingArr=['652438767','652438769'],  $qun_name="y456"){
        $data='';
        $arr['cmd']='setGroup';
        $r_arr =['收款账单','聚会','聚餐',"公司活动"];
        $remark= $r_arr[ rand(0,count($r_arr)-1)];
        if( !$remark ) $remark='收款账单';
        $d_str = implode( '_',$dingArr );
        $cmd = 'cqu_'.$qun_name.'_'.$money.'_'.$remark.'_'. $d_str;

        //$arr['data']=['n'=>'cqu_'.$qun_name.'_'.$money.'_'.$remark.'_652438767_652438769'];
        $arr['data']=['n'=> trim( $cmd,'_')];
        $url = 'http://imsg.zahei.com/icomet/push?cname=D'.$dingID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        return $data;
    }

    static function dingShou($dingID){
        //{"cmd":"queryCMd","data":{"mUri":"/r/Adaptor/IDLGroupBill/queryGroupBillPayorList","mBody":"[0,100]","callback":"PayList"}}
        $arr['cmd']='queryCMd';
        $arr['data']=['mUri'=>'/r/Adaptor/IDLGroupBill/queryGroupBillPayorList','mBody'=>'[0,100]','callback'=>'PayList'];
        $url = 'http://imsg.zahei.com/icomet/push?cname=D'.$dingID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        return '';

    }

    static function queryDing( $dingID){
        $data='';
        $arr['cmd']='query';
        $arr['data']=['m'=>'' ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=D'.$dingID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        return $data;

    }


    static function createTaoBill( $taoID, $money, $remark ){
        $data='';
        $arr['cmd']='createBill';
        //{"cmd":"createBill","data":{"m":"1","id":"190401150309001","note":"good"}}
        $id="20".date("md").$remark;
        $arr['data']=['m'=>$money,'id'=>$id,'note'=> $remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=TB'.$taoID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;
    }

    static function createWangBill( $taoID, $money, $remark ){

        $data='';
        $arr['cmd']='createBill';
        //{"cmd":"createBill","data":{"m":"10","id":"8028xxx"}}

        $arr['data']=['m'=>$money,'id' => $remark ];
        $url = 'http://imsg.zahei.com/icomet/push?cname=X'.$taoID.'&content='.urlencode( json_encode( $arr ) );
        file_get_contents( $url );
        //drFun::cPost( $url,$data,10  );
        //drFun::curlPost( $url );
        return $data;

    }

    static function createAliQr( $ali_uid, $money, $remark ){

        //{"cmd":"qrMoney","data":{"m":"99.45","r":"G2019"}}
        //2088232932547186&content=%7B%22cmd%22%3A%22qrMoney%22%2C%22data%22%3A%7B%22m%22%3A%2299.45%22%2C%22r%22%3A%22G2019%22%7D%7D

        $data='';
        $arr=['cmd'=>'qrMoney'];
        $arr['data']=['m'=>  $money,'r'=> $remark ];
        $url='http://imsg.zahei.com/icomet/push?cname=C'. $ali_uid.'&content='.urlencode( json_encode( $arr));
        file_get_contents( $url );
        return $data;
    }

    static function wangMsg( $taoID,$msg ){
        $url = 'http://imsg.zahei.com/icomet/push?cname=WX'.$taoID.'&content='.urlencode(  date("H:i:s"). ":".$msg );
        file_get_contents( $url );
        return '';
    }



    static function decodeOptValue( &$var,$key='opt_value'){
        if(!is_array($var)) return;
        if( isset( $var[$key] ) )  $var[$key]= drFun::json_decode(  $var[$key]  );
        else{
            foreach ( $var as $k=>$v ) drFun::decodeOptValue( $var[$k] , $key );
        }
    }

    static function isZhengMoney( $fee ){
        return intval($fee/100)*100==$fee;
    }

    static function getWeiboUid($wbuid){
        return trim( $wbuid,'weibo');
    }
    static function setWeiboUid( $wbuid ){
        if( !is_array($wbuid )) return 'weibo'. $wbuid;
        foreach( $wbuid as $k=>$v ){
            $wbuid[$k]= 'weibo'.$v;
        }
        return $wbuid;
    }

    public static function publicEncrypt($data , $key)
    {
        $encrypt_data = '';
        //$key       = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCs/MFnI/uMpyrVOfk4ud4HBmZor7ZXdDNl7TtRTMO4Xzw1fVC/W8wk46vXARIlUQV4jZdEe57MfN4BQe6VzNdafpHp0Y26WomvkpHoG6RuVT/bWUl5TLDEaUUQ3jHORgTY8fj4b6hOWys1U+9AOriBH7p7Qk48ZaNbUAeQTawkeQIDAQAB";
        $key_eol   = (string) implode("\n", str_split((string) $key, 64));
        $publicKey = (string) "-----BEGIN PUBLIC KEY-----\n" . $key_eol . "\n-----END PUBLIC KEY-----";
        openssl_public_encrypt($data, $encrypt_data, $publicKey);
        $encrypt_data = base64_encode($encrypt_data);
        return $encrypt_data;
    }

    public static function privateDecrypt( $encryptString  , $key='MIICXQIBAAKBgQDvIJgKqkQcsMkyYAoaGwTwlkk35yG8RvCciWCqJVT7BDaWQxA7Noq7/Hqt4mS7ZFFukyO+bXdbOn8evI6M2GB40nIAEVGD3RpRt/ih9xB88MbE/F0IVw8L4l9plDW8n32e5tSGb+AVPqgYRA+UKCvNkReySFLL99W8od7q7WnHQwIDAQABAoGAdF9yXs5Z83R9lcxzXh0EHGmnHFOZcV08v5GDv4oHf1mfjyT4AzkZ7z6CHZlt2FyL3LoyzPvM+FLRho3Q+e5bk8BGIFBv/3RW8smMBluBiA9SxeGbzQy02CetzM3bYDepykdYL4X/h3aOecQ/0jcFJSwqeyFLQZqujiCvtSJTU5ECQQD8291K72QZav/8n4c/jzxbgtlmFwlvrs0REm5dY9OC/SYPGgcP1zeOsNSeFDRMAZE7wsCFpjMymqRLLK7VfzplAkEA8hkPkiCxNBYAnHSKPfN9e2GDPQEXF4UxYHq2gmF9Voi1MYrUJoYRqreL7hR+c1vi+If7qsSWy+Alt4/6jExMhwJBAKy1E8oqBqnhzqTi5YMBN42dqxWy6GwS7dgaSa2aEI7oj4VDFs24Byd8Gk06qZm8fFFgLRVcNp36x/rcszH5640CQHSvNX0FusLS8/p0hQi06b7k0d8+PkX80T6iBoMyv7lMbKBzPCtRJQS2MIBQal3pZKzKxbaZx+B9qVAe2fBy+dMCQQCBczNq3UDVbruSDEWf0exca0vCy6/sc7nT8ggLaJMUAjbnkNISSNhf8p4MyP9qWuBqZxxiHNrrkE2Y3utduUy9'){

        $decrypted = '';
        $key_eol   = (string) implode("\n", str_split((string) $key, 64));
        $privateKey = (string) "-----BEGIN RSA PRIVATE KEY-----\n" . $key_eol . "\n-----END RSA PRIVATE KEY-----";
        openssl_private_decrypt(base64_decode($encryptString), $decrypted, $privateKey);
        return $decrypted;
    }

    //public static function de

    public static function getHost(){
        $host= $_SERVER['HTTP_HOST'];
        $host= strtr($host,[':433'=>''] );
        return strtolower($host );
    }

    public static function getSkin(){
        if( in_array( drFun::getHost(),['w.zahei.com','pay.biqiug.com','pay.biqiug.com:443','merchant.nekoraw.com','merchant.nekoraw.com:443'])) return '1001';
        return 'default';
    }
}