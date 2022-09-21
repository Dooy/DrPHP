<?php
/**
 * 放必要的函数
 * 这边的函数一般啊 view层使用
 *
 * User: haoce.com
 * Date: 2017/5/20 0020
 * Time: 下午 8:50
 */

/**
 * 路由
 * @param string $query
 * @param string $opt
 * @return string
 */
function R(  $query,$opt=''){
    return \model\drFun::rount( $query,$opt );
}

/**
 * 使用头像链接
 * @param string $img
 * @return string
 */
function H( $img ){
    $cdn="https://cdn.haoce.com";
    if(!$img) return $cdn.'/res/img/none_user.jpg';
    if( substr($img,0,4)==='http') return $img;
    return $cdn.'/'.trim( $img,'/');
}

/**
 * 处理html防止xss
 * @param string $str
 * @param array $tarr
 * @return string
 */
function Html( $str ,$tarr=[] ){
     \model\drFun::strip( $str );
     if( $tarr ) $str = strtr( $str, $tarr );
     return $str;
}

function Hnl2br( $str ){
    if( strpos($str,'>' ) !==false &&   strpos($str,'>' ) !==false)    return $str;
    return nl2br( $str );
}

/**
 * 分数显示
 * @param $score
 * @return string
 */
function Score( $score ){
    if( $score< 0) return '异常';
    /*
    if( $score<40*100) return '哎呦不及格，请加把劲';
    return number_format($score/100, 2,'.','');
    */
    if( $score<50*100) return '差！';
    if( $score<75*100) return '中！';
    return '好！';
}


function tplFile( $tpl_file ){
    $skin = \model\drFun::getSkin();
    if( $skin!='default'){
        $file= ROOT_PATH.'/skin/'.$skin.'/'.$tpl_file;
        if( is_file( $file) ) return $file;
    }
    return ROOT_PATH.'/view/'.$tpl_file;
}

function timeShow( $time ){
    $h= intval( $time/3600);
    $m= intval(  ($time%3600)/60);
    $s = $time%60;
    return ($h>0? $h.'时':'' ). ($m>0? $m.'分':'' ). ($s>0? $s.'秒':'' );
}

function timeShowV2($time, $opt=[]){
    $time= time() - $time;

    if( $opt['limit'] && $time<$opt['limit']) return '';
    $h= intval( $time/3600);
    $m= intval(  ($time%3600)/60);
    $s = $time%60;
    if( $h>0) return $h.'小时前';
    if( $m>0) return $m.'分钟前';
    if( $s>0) return $s.'秒前';
}

function intShow( $int ){
    if( $int>100000000) return number_format($int/100000000,3 ).'亿';
    //if( $int>50000000)  return number_format($int/10000000 ,2).'千万';
    if( $int>100000)    return intval($int/10000 ).'万';
    if( $int>10000)    return number_format($int/10000 ,1).'万';
   return $int;
}