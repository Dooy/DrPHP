<?php
/**
 * BI 分析
 * Date: 2018/10/8
 * Time: 20:14
 */

namespace ctrl;


use model\drTpl;

class bi extends drTpl
{
    private  $bi=null ;

    function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $this->htmlFile="hcadmin.phtml";
        //if( !$this->getLogin()->isShenfen('p2') ) $this->drExit("无权访问！！");
    }

    /**
     * @return \model\bi
     */
    function bi(){
        if( $this->bi==null )     $this->bi= new \model\bi();

        return $this->bi;
    }

    function act_trade( $p ){
        $day='yesterday';
        switch ($p[0]){
            case 'today':
            case 'yesterday':
                $day= $p[0];
                break;
            default:
                break;
        }
        $this->assign('trade', $this->tradeBYDay( $day ) );

        $this->tplFile = 'trade';

    }

    function act_bu( $p ){

        $this->site_title="补单分析";
        $tabs=[0=>'今日','yesterday'=>'昨日',2=>'近3天',6=>'近一周',30=>'近一月'];

        $this->assign('tabs', $tabs );

        $where['type']= 11;
        $day= $p[0] ;
        if( $p[0]=='yesterday' ){
            $_GET['ctime_s']=  $_GET['ctime_e'] =  date("Y-m-d", time()- 24*3600);
        }elseif( $p[0]=='search' ){
            $day='search';
        }else{
            $day= ($p[0]>0 )? intval($p[0]):0 ;
            $_GET['ctime_s'] = date("Y-m-d", time()- $day *24*3600);
        }

        if(  $_GET['ctime_s'] )  $where['>=']=['ctime'=> strtotime(  $_GET['ctime_s']) ];
        if(  $_GET['ctime_e'] )  $where['<=']=['ctime'=> strtotime(  $_GET['ctime_e'].' 23:59:59') ];

        $this->assign('list', $this->bi()->tjTradeCntByMin($where) )->assign('tab', $tabs )->assign('day',$day );
        $this->assign('get',$_GET );
        $this->tplFile = 'bu';
    }

    function act_test(){

        $this->drExit( date("Y-m-d H:i:s") );

    }

    function tradeBYDay( $day ){
        $where= ['type'=>$this->getLogin()->createQrPay()->getTypeTradeSuccess() ];

        switch ( $day ){
            case 'today':
                $_GET['s_time']=  date("Y-m-d") ;
                break;
            case 'yesterday':
                $_GET['e_time']=  $_GET['s_time']= date("Y-m-d",time()-24*3600) ;
                break;
            default:
                //$_GET['e_time']=  $_GET['s_time']= $day;
                break;
        }

        if(  $_GET['s_time'] ) $where['>=']['ctime']= strtotime(   $_GET['s_time'] );
        if(  $_GET['e_time'] ) $where['<=']['ctime']= strtotime(   $_GET['e_time'].' 23:59:59' );

        $tall = $this->getLogin()->createQrPay()->tjTradeGroup('realprice', $where );
        return $this->trade_count( $tall );

    }

    function trade_count( $trade_cnt){
        $re = [];
        foreach( $trade_cnt as $k=>$v  ){
            $k2= intval($k/100+0.3);
            $re[$k2]['total'] += $v['total']/100;
            $re[$k2]['cnt'] += $v['cnt'] ;
        }
        foreach( $re as &$v) $v['total'] = intval( $v['total'] );
        return $re ;
    }

}