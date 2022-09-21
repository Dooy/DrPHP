<?php
/**
 * 分页处理
 * User: Administrator
 * Date: 2017/5/21 0021
 * Time: 下午 8:34
 */

namespace model;


use DR\DR;

class page extends DR
{

    private $totalpage;
    private $current;
    private $total ;
    private $every;

    /*
    * @param key string 分页区别标记
    * @param int size 每页记录数
    * @param int groupsize 每组页码数
    * @param int current 当前页码
    */
    function __construct ($total , $every=30)
    {

        if( $every<=0) $this->throw_exception("每页条数必须大于0", 601  );
        if( $total<=0 ) $this->throw_exception("总数必须大于0", 602  );
        $this->totalpage = ceil( $total/$every) ;
        $this->total= $total;
        $this->every= $every;
        $page= isset($_GET['pageno']) && intval($_GET['pageno'])>0 ?intval($_GET['pageno']):1 ;
        $this->current = $page;


    }
    private function setTotalpage(){
        $this->totalpage = ceil(  $this->total / $this->every) ;
        if(  $this->current >0 and  $this->current >  $this->totalpage ) {
            $this->current =  $this->totalpage;
        }
    }
    function setEvery( $every ){
        if( $every<=0) $this->throw_exception("每页条数必须大于0", 601  );
        $this->every= $every;
        $this->setTotalpage();
        return $this;
    }
    function setTotal( $total){
        if( $total<=0 ) $this->throw_exception("总数必须大于0", 602  );
        $this->total= $total;
        $this->setTotalpage();
        return $this;
    }

    /*
    *设置链接头信息
    * @param string remove 要移除的变量名成
    */
    function _setLinkhead ()
    {
        parse_str ($_SERVER['QUERY_STRING'], $query);
        //parse_str ($_SERVER['REQUEST_URI'], $query);
        foreach ($query as $k => $v)
        {
            if (empty ($v) === true && $v != 0)
            {
                unset ($query[$k]);
            }
        }

        if (isset ($query['pageno']))
            unset ($query['pageno']);

        foreach ($query as $k => $v)
        {
            $tmp[] = $k . '=' . urlencode($v);
        }

        $tmp[] = 'pageno' . '=';

        $tarr= explode('?', $_SERVER['REQUEST_URI'] );

         return ( $tarr[0]. '?' . implode('&', $tmp));
    }

    //显示页面跳转菜单
    function jump () {
        if ($this->totalpage <= 1) return '&nbsp;';
        $linkhead = $this->_setLinkhead ();
        ob_start ();
        echo "<select onchange=\"window.location='" . $linkhead . "'+this.value\">";
        for ($i = 1; $i <= $this->totalpage; $i ++) {
            if ($i != $this->current) echo "<option value='" . $i . "'>" . $i . "</option>";
            else echo "<option value='" . $i . "' selected>" . $i . "</option>";
        }
        echo "</select>";
        $jump = ob_get_contents ();
        ob_end_clean ();
        return $jump;
    }
    function pageLinksM(){
        if ($this->totalpage <= 1) return '';
        $linkhead = $this->_setLinkhead ();

        $current = $this->current;
        $totalpage = $this->totalpage;
        $separator = ' ';

        $first ='';//"<a title='首页' href='".$linkhead."1'>首页</a>".$separator;

        $last ='';// "<a title='尾页' href='".$linkhead.$totalpage."'>尾页</a>".$separator;

        //$prev = "<a title='上一页' href='".$linkhead.($current-1)."'>上一页</a>".$separator;
        $prev = '<li><a href="'.$linkhead.($current-1).'">&laquo;</a></li>'.$separator;

        $next = '<li><a href="'.$linkhead.($current+1).'">&raquo;</a></li>'.$separator; //"<a title='下一页' href='".$linkhead.($current+1)."'>下一页</a>".$separator;

        $content = '';
        if($current > 5)
            $content .= $first;
        if($current > 1)
            $content .= $prev;

        //循环
        $min = $totalpage <= 6? 1: intval(($current-3)/3)*3+1;
        $max = $totalpage <= 6? $totalpage: $min + 5;

        if($max > $totalpage)
        {
            $max = $totalpage;
            $min = $max-5;
        }

        if( $type==2 ){
            for($i=$min; $i<=$max; $i++)
            {
                $content .= $i==$current? "<li  class=\"active\"><a>".$current."</a></li>".$separator
                    : " <li><a title='第" . $i . "页' href='" . $linkhead . $i . "'>" . $i . "</a></li>".$separator;
            }

        }else {
            for($i=$min; $i<=$max; $i++)
            {
                $content .= $i==$current? "<li  class=\"active\"><a>".$current."</a></li>".$separator
                    : "<li><a title='第" . $i . "页' href='" . $linkhead . $i . "'>" . $i . "</a></li>".$separator;
            }
        }

        if($current < $totalpage)
            $content .= $next;
        if($current < $totalpage-4)
            $content .= $last;


        //die( $totalpage );
        if( $totalpage>12) {
            //$content.='<input type="text" name="" id="page_id" value="" style="width:40px;"><input type="button" value="Go" onclick="javascript:var po =document.getElementById(\'page_id\');if(po.value==\'\') return false;window.location=\''. $linkhead .'\'+po.value "  id="pageGo">共('.$totalpage.'页)';
        }
        $content = '<nav><ul class="pagination">'.$content.'</ul></nav>';
        return $content;

    }
    //显示页面链接
    function pageLinks ( $type=0 ) {
        if ($this->totalpage <= 1) return  ['html'=>'','page_total'=> 0];
        $linkhead = $this->_setLinkhead ();

        $current = $this->current;
        $totalpage = $this->totalpage;
        $separator = '</li><li>';
        $separator_select= '</li><li class="active">';

        if($type!==3) {
            $first = "<a title='首页' href='".$linkhead."1'>首页</a>".$separator;
            $last = "<a title='尾页' href='".$linkhead.$totalpage."'>尾页</a>".$separator;
        }
        $prev = "<a title='上一页' href='".$linkhead.($current-1)."'>«</a>".$separator;
        $next = "<a title='下一页' href='".$linkhead.($current+1)."'>»</a>".$separator;
        $di='第';
        $ye='页';


        $content = '<div class="sui-pagination"><ul>'.( $current==1?'<li  class="active">':'<li >');

        if($current > 5)
            $content .= $first;
        if($current > 1)
            $content .= $prev;

        //循环
        $min = $totalpage <= 10? 1: intval(($current-5)/5)*5+1;
        $max = $totalpage <= 10? $totalpage: $min + 9;

        if($max > $totalpage)
        {
            $max = $totalpage;
            $min = $max-9;
        }

        if( $type==2 ){
            for($i=$min; $i<=$max; $i++)
            {
                $content .= $i==$current? "<B>".$current."</B>".$separator
                    : "<a title='" .$di. $i .$ye. "' href='" . $linkhead . $i . "'>" . $i . "</a>".$separator;
            }
        }elseif($type==3) {
        }else {
            for($i=$min; $i<=$max; $i++)
            {
                $content.= "<a title='".$di. $i .$ye."'  href='" . $linkhead . $i . "'>" . $i . "</a>".( ($i+1)==$current? $separator_select : $separator);
            }
        }

        if($current < $totalpage)
            $content .= $next;
        if($current < $totalpage-4)
            $content .= $last;

        if( $totalpage>12 and $type !== 3) {
            //$gong = '共';      $pg = '页';
            //$content.='<input type="text" name="" id="page_id" value="" style="width:40px;"><input type="button" value="Go" onclick="javascript:var po =document.getElementById(\'page_id\');if(po.value==\'\') return false;window.location=\''. $linkhead .'\'+po.value "  id="pageGo"> '.$gong.'('.$totalpage.' '.$pg.')';
            $content.= '<li class="disabled"><a  >共'.$this->totalpage.'页</a></li>'.$separator;
        }
        $content.='</li></ul></div>';

        return ['html'=>$content,'page_total'=>intval($totalpage)];
    }
    function getCurrent(){
        return $this->current;
    }
    function getPageAll(){
        return ['page'=>$this->pageLinks(),'pageno'=> $this->current,'every'=> $this->every ];
    }
    function getEvery(){
        return $this->every;
    }
    function pageJs () {
        if ($this->totalpage <= 1) return '&nbsp;';
        $linkhead = $this->_setLinkhead ();

        $current = $this->current;
        $totalpage = $this->totalpage;
        $separator = ' ';

        $first = "<a title=\"首页\" href=\"javascript:fenye(1)\">首页</a>".$separator;

        $last = "<a title=\"尾页\" href=\"javascript:fenye(".$totalpage.")\">尾页</a>".$separator;

        $prev = "<a title=\"上一页\" href=\"javascript:fenye(".($current-1).")\">上一页</a>".$separator;

        $next = "<a title=\"下一页\" href=\"javascript:fenye(".($current+1).")\">下一页</a>".$separator;

        $content = '';
        if($current > 5)
            $content .= $first;
        if($current > 1)
            $content .= $prev;

        //循环
        $min = $totalpage <= 10? 1: intval(($current-5)/5)*5+1;
        $max = $totalpage <= 10? $totalpage: $min + 9;

        if($max > $totalpage)
        {
            $max = $totalpage;
            $min = $max-9;
        }

        for($i=$min; $i<=$max; $i++)
        {
            $content .= $i==$current? "<B>".$current."</B>".$separator
                : "<a title=\"第" . $i . "页\" href=\"javascript:fenye(".$i.")\">[" . $i . "]</a>".$separator;
        }

        if($current < $totalpage-4)
            $content .= $last;
        if($current < $totalpage)
            $content .= $next;


        return $content;
    }
}