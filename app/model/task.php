<?php
/**
 * 操作任务相关的程序
 *
 * User: Administrator
 * Date: 2017/5/21 0021
 * Time: 下午 2:23
 */

namespace model;



use model\user\login;

class task extends model
{
    private $table ='task';
    private $tb_class ='task_class';

    /**
     * 获取 任务班级 task class的类型
     * @param string $type
     * @return array
     */
    function getTypeTaskClass( $type='all'){
        $all = [1=>['n'=>'一般任务'],2=>['n'=>'图书']];
        if( $type=='all') return $all;
        if( !isset( $all[$type ])) $this->throw_exception( "班级任务类型不存在！",6001);
        return $all[ $type ];
    }

    /**
     * 计算任务量
     * @param int $task_id
     * @param int $class_id
     * @param int $type 一般任务，选书任务
     * @return $this
     */
    function countTaskClass( $task_id,$class_id, $type){
        $this->getTypeTaskClass( $type );
         switch ($type){
             case 2:
             case 'book':
                 return $this->countTaskClassBook( $task_id, $class_id );
                 break;
             default:
                 $this->throw_exception( "呵呵还在建设");
         }
         return $this;
    }

    /**
     * 计算选书任务，如果没有直接插入
     *
     * @param $book_id
     * @param $class_id
     * @return $this
     */
    function countTaskClassBook( $book_id,$class_id ){
        $re=['cnt'=>0 ];
        $re['cnt']= $this->createSql()->getCount( "book_user",['book_id'=>$book_id,'class_id'=>$class_id])->getOne();
        $where= [ 'task_id'=>$book_id,'class_id'=>$class_id ,'type'=>2 ];
        $row = $this->createSql()->select( $this->tb_class,$where )->getRow();
        if( $row){
            $re['type']= 2;
            $this->update( $this->tb_class,['tc_id'=> $row['tc_id'] ], $re );
        }else{
            $re['task_id']= $book_id;
            $re['class_id']= $class_id;
            $re['type']= 2;
            $this->insert( $this->tb_class, $re );
        }
        return $this ;
    }

    /**
     * 获取Task_class 资料
     * @param $where
     * @return array
     */
    function getTaskClass( $where ){
       return $this->createSql()->select( $this->tb_class,$where,[],[],['cnt'=>'desc'])->getAll();
    }

    function getTaskClassBook( $book_id ){
        return $this->getTaskClass(['task_id'=>$book_id,'type'=>2]);
    }

    /**
     * 获取任务班级的人数 getTaskClassCnt(['task_id'=>$book_id,'type'=>2 ]);
     * @param $where
     * @return int
     */
    function getTaskClassCnt( $where ){
        return $this->createSql()->getCount( $this->tb_class ,$where)->getOne();
    }

    function getTaskClassCntBook( $book_id ){
        return $this->getTaskClassCnt( ['task_id'=>$book_id,'type'=>2]);
    }

    function getDiaoCha($book_school,$opt=[]){
        $login= new login();
        if( $login->getSchool()=='黑龙江大学'){
            return ['url'=>'https://mp.weixin.qq.com/s/vZYhh4SlXpd_6aMgAQR_iA','txt'=>'关于“经典阅读”夏令营分营活动报名的通知'];
        }
        return false;
        $parm=$book_school['school_ename'].'_'. $login->getUserId();
        $diaocha=
            /*['s'=>['url'=>'https://www.wjx.cn/jq/19521243.aspx?udsid=369681&sojumpparm='.$parm,'txt'=>'美女帅哥点这里！好策读书调查问卷（有奖参与）'
                ,'long'=>"各位同学大家好！好策读书自贵校应用以来，得到您的积极配合与大力支持。为了能够更好地为师生服务，我们特意制作一份匿名调查问卷，大约需要5-8分钟完成。希望能够听到您的心声！" ]*/

            ['s'=>['url'=>'https://www.wjx.cn/jq/20170655.aspx?udsid=127557&sojumpparm='.$parm,'txt'=>'【华中科技大学】课外英语原著阅读调查'
                ,'long'=>"亲爱的同学：你好！<br>我们是华中科技大学外国语学院大学外语系教学团队。一个学期的英语学习刚刚结束，我们希望了解你在课外阅读行动计划活动中的学习情况，为教学和研究服务。我们非常珍视你所提供的宝贵信息，衷心感谢你的合作！" ]

            ,'t'=>[ 'url'=>'https://www.wjx.cn/jq/19520686.aspx?udsid=184166&sojumpparm='.$parm ,'txt'=>'热情期盼老师们参与有奖调查问卷！好策读书调查问卷（教师篇）'
            ,'long'=>'各位老师大家好！好策读书自贵校应用以来，得到您的积极配合与大力支持。为了能够更好地为师生服务，我们特意制作一份匿名调查问卷，大约需要5-8分钟完成。希望能够听到您的心声！']];
        $school_arr=['华中科技大学'=>1 ]; //,'好策'=>1
		if( $login->isTeacher( ) ) return $diaocha['t'];

        if( !isset( $school_arr[$login->getSchool()] )) return false ;
        return $login->isTeacher( ) ?$diaocha['t']:$diaocha['s'];
    }

}