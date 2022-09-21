<?php
/**
 * 批改打分 作文打分+ 语音打分
 * User: Administrator
 * Date: 2017/9/3
 * Time: 16:29
 */

namespace model\lib;


use model\book;
use model\drFun;

class pigai
{
    /**
     * 文本打分 推送到批改网
     * @param $topic_id
     * @param $body
     * @param array $opt
     * @return bool
     */
    function publish_topic( $topic_id, $body, $opt=[]){
        $url = 'http://www.pigai.org/?c=test&a=doscore2';
        #$var = ['topic_info'=>$body,'tag_id'=>$opt['tag_id']  ];
        $cl_book = new book();
        $topic =$cl_book->getTopicById( $topic_id );
        $book= $cl_book->getDiscus2wenbenBookID( ) ;//比较特殊的作文号需要 文本当做
         if( $topic['tag_id']==0 && !isset( $book[ $topic['book_id'] ] ) ) {
            $cl_book->huoScore( $topic_id );
            return true;
        } #主题不参加讨论
        $cl_book->topic_info_decode( $topic );
        if( $topic['is_html']) $topic['topic_info'] = strip_tags( $topic['topic_info'] );
        $body= $topic['topic_info'];
        $opt['id']= $topic_id;
        $data=['body'=>$body,'opt'=>$opt ];
        $str = drFun::curlPost($url,  $data);
        $arr = json_decode( $str, true);
        if( $arr['error']===0) return true ;
        return false ;
        //return drFun::http_build_query( $data );
    }
    function receive(){
    }

    /**
     * 语音打分
     * @param $topic_id
     * @return array
     */
    function hiVoice( $topic_id ){
        $cl_book = new book( );
        $topic =$cl_book->getTopicById( $topic_id );
        $tarr =  drFun::json_decode( $topic['topic_info'] );
        $re = $this->hiVoiceDo( $tarr['topic_info'],ROOT_PATH.'/webroot/'.$tarr['file'] );
        $cl_book->updateTopicScore( $topic_id, $re['score'] );
        return $re ;
    }

    /**
     * 2018.1.28朗读打分评分标准：
     *  -1.单次：朗读时长+语音测评+内容长短
     *  -2.多次：单词平均分+次数分
     *  -3.设最低分：60
     * @param $topic_id
     * @return array
     */
    function hiVoiceV2($topic_id){
        $cl_book = new book( );
        $topic =$cl_book->getTopicById( $topic_id );
        $tarr =  drFun::json_decode( $topic['topic_info'] );
        try{
            $re = $this->hiVoiceDo( $tarr['topic_info'],ROOT_PATH.'/webroot/'.$tarr['file'] );
            $score= $re['score'] ;
        }catch ( \Exception $ex ){
            $score = 0;
        }
        //$cl_book->updateTopicScore( $topic_id, $re['score'] );
        //$re=['score'=>];
        $tag_score = $this->tag3Score( $tarr , $score ) ;
        $re['new_score']= $new_score = $tag_score>60?$tag_score:60; #最少60分
        $cl_book->updateTopicScore( $topic_id, $new_score);
        return $re;
    }

    /**
     * 朗读打分将录音的时间 跟 字数长度、字符串长度 考虑进去
     *
     * @param $tarr
     * @param $score
     * @return float|int
     */
    function tag3Score( $tarr, $score ){
        #取字数 或者字符串最高的分数
        $w_score =  max( $this->line_score( drFun::wordCount( $tarr['topic_info']),5,128) ,  $this->line_score( mb_strlen( $tarr['topic_info'],'utf-8'),5,500)  ); //文本分数
        //录音事件分数
        $lu_score = max(30,  $this->line_score(intval($tarr['time']),10,160) );
        $qz = ['s'=>5,'w'=>3,'l'=>2 ];
        if( $score>0 ) return ( $score*$qz['s']+ $w_score* $qz['w']+ $lu_score *$qz['l']  )/array_sum( $qz);
        unset( $qz['s']);
        return ($w_score* $qz['w']+ $lu_score *$qz['l'])/array_sum( $qz);
    }
    function line_score(  $word_number ,$min,$max ,$min_score=29,$max_score=98  ){
        if( $min>=$max || $min_score>$max_score ) throw new \Exception( '打分参数错误！' ,7407);

        if( $word_number<= $min) return $min_score;
        if( $word_number> $max ) return $max_score;
        $score = $min_score+  $word_number*($max_score- $min_score )/(  $max-$min );//number_format( , 2, '.', '');
        return $score ;
    }

    function hiVoiceDo( $text, $file_mp3 ){
        if( ! is_file( $file_mp3 )) throw new \Exception($file_mp3."\n 文件不存在",7405);
        $text= strtr($text,['"'=>"''","\n"=>'\n',"\r"=>'\r']);// urlencode($text );
        $ext= strpos( $file_mp3,'.mp3')?'mp3': 'amrnb';
        $cmd='curl --connect-timeout 10 -m 120 -X POST -H "score-coefficient:1.6" -H "session-id:`uuidgen`" -H "appkey:od2bmgt5g6yqe2la3yksdr423iqlqxzgf7svhbq7" -H "Transfer-Encoding: chunked" -H "Content-Length"  --form text="'.$text.'" --form mode="B" --form voice=@'.$file_mp3.' \'http://edu.hivoice.cn:8085/eval/'.$ext.'\'';
        exec(  $cmd ,$out);
        $json= (json_decode( $out[0],true));

        if(! $json ) throw new \Exception("返回有问题" .$out ,7406);
        $score_total=0;
        $word_cnt_total=0;
        foreach($json['lines'] as $v ){
            $word_cnt = strlen( $v['sample'] );
            $score_total += $v['score']*$word_cnt;
            $word_cnt_total += $word_cnt;
        }
        $re = ['score'=>0 ];
        if( $word_cnt_total>0 ) $re['score'] = $score_total/$word_cnt_total  ;
        if($re['score']<=0.01 ) $re['score']=0.01;
        $re['cmd']= $cmd;
        return $re ;
    }


    /**
     * 单个添加主题到ES
     * @param $topic_id
     * @param array $re
     * @return $this
     */
    function postEsByTopicId( $topic_id ,&$re=[] ){
        $cl_book = new book( );
        $topic =$cl_book->getTopicById( $topic_id );
        if( $topic['tag_id']==3 ) return $this;
        $cl_book->topic_info_decode( $topic );
        if( $topic['is_html']) $topic['topic_info'] = strip_tags( $topic['topic_info'] );
        $str =  $cl_book->formatTopic2Es( $topic );
        $re['code'] = drFun::cPost('http://es54.jukuu.com/haoce/topic/_bulk',$str ,10,['Authorization: Basic Y2lrdXU6ODI2MDA4MTg=']);
        $re['rz']= $str;
        return $this;
    }

    /**
     * @param int $topic_id
     * @param array $re
     * @return $this
     */
    function simByTopicID( $topic_id ,&$re=[] ){
        $cl_book = new book( );
        $topic =$cl_book->getTopicById( $topic_id );
        if( $topic['tag_id']==3  ) return $this;
        $cl_book->topic_info_decode( $topic );
        if( $topic['is_html']) $topic['topic_info'] = strip_tags( $topic['topic_info'] );

        $var=['user_id'=> $topic['user_id']  ,'limit'=>5,'content'=>$topic['topic_info'] ]; //
        $str = drFun::http_build_query( $var );
        $re['code'] = drFun::cPost('http://bbs.pigai.org/HaoCeCheckWebService/CheckService',$str ,30 );
        $json = json_decode($str,true );
        $re['rz']= $json;
        $sim = -1;
        if( isset( $json['error'])) $sim=-2;
        elseif( !$json ) $sim= 0;
        elseif( $json[0]['sim'] ){
            $sim  = $json[0]['sim'] ;
        }
        $re['sim']= $sim;
        $cl_book->updateTopicSim($topic_id, $sim);
        return $this;
    }


    /**
     * 小众群体 为0.2（上下对半了） 大众群体为0.8
     * @param array $col 成绩
     * @param array $scoreConfig  [  'agv'=> 70,'min'=>50,'max'=>90];
     * @return array
     * @throws \Exception
     */
    function lisan($col,$scoreConfig ){
        //print_r($scoreConfig ); die();
        if( $scoreConfig['min']> $scoreConfig['agv'] ||  $scoreConfig['agv']>  $scoreConfig['max']) throw  new \Exception("参数必须：高分>中分>地方");
        $FBmin = 0.2;//小众群体 为0.2（上下对半了） 大众群体为0.8
        $avg = array_sum($col)/count($col);
        asort($col);
        $FB['min']= array_slice($col, 0, floor($FBmin*count($col)/2 ) );
        arsort($col);
        $FB['max']= array_slice($col, 0, floor($FBmin*count($col)/2 ) );
        $FB['mmaxR']=0;
        $FB['cnt']= count($col);
        foreach( $col as $v){
            if($v>$avg) $FB['mmaxR']++;
        }
        //$FB['mminR']= count($col)-  $FB['mmaxR'];
        $FB['mminR'] =  count($col)-  $FB['mmaxR'] - count( $FB['min']); #中底部 人数
        $FB['mmaxR'] = $FB['mmaxR'] - count( $FB['max']);#中高部 人数
        $FB['minR'] = count( $FB['min']);#底部 人数
        $FB['maxR'] = count( $FB['max']);#高部 人数

        $scoreConfig['max1']=($scoreConfig['max'] * $FB['mmaxR']+ $scoreConfig['agv']*$FB['maxR'])/( $FB['mmaxR']+$FB['maxR'] );
        $scoreConfig['min1']=($scoreConfig['min'] * $FB['mminR']+ $scoreConfig['agv']*$FB['minR'])/( $FB['minR']+$FB['mminR'] );



        rsort($scoreConfig );
        $arr[]= $FB['maxR']; #高
        $arr[]= $FB['mmaxR'];#中高
        $arr[]= $FB['mminR'];#中低  #去除低分不按期望分来 2011-3-4 张提议
        $arr[]= $FB['minR'];#低

        $start=0;
        //print_r($scoreConfig );       print_r($arr); die();
        foreach($arr as $k=>$len ){
            $this->yingse( $col,$start,$len,$scoreConfig[$k],$scoreConfig[$k+1],$k);
            $start += $len;
        }
        //print_r( $col );
        return $col;

    }

    /**
     * @param $col
     * @param $start
     * @param $len
     * @param $max
     * @param $min
     * @param $key
     */
    function yingse(&$col,$start,$len,$max,$min ,$key){
        $re= array();
        $i= -1;
        $maxKey= $start+$len;
        foreach( $col as $k=>$v){
            $i++;
            if($i<$start) continue;
            if($i>=$maxKey) break;
            //if( is_array($v)){ print_r( $col ); die("asdf=".$start);}
            $re[$k]=floatval($v);
        }
        $zmax= max( $re );
        $zmin= min( $re );
        $str =(   $zmin.",". $zmax .",".$min.",".$max);
        //echo ( $str )."<br>\n";
        if($zmax!= $zmin) {
            // die( 'asdf='. ( $max-$min ) );
            $chu= floatval($zmax)- floatval($zmin);
            $dt = ($max-$min)/$chu;
            //die('dt='.$dt);
            foreach($re as $k=>$v){
                //echo "";
                $v =  $min+ ($col[$k]-$zmin)*$dt ;
                $v= sprintf("%0.2f",$v);
                $col[$k] = $v;//array( $col[$k],$v );
            }
        }
    }


}