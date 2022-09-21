<?php
/**
 * 每日一句
 */

namespace model;


class daily extends model
{
    private $user_id=0;
    private $tb_daily = 'du_daily';
    function setUserID( $uid ){
        $this->user_id= $uid;
        return $this;
    }
    function getUserID(){
        if( $this->user_id<=0 ) $this->throw_exception( "无效账号",7401);
        return  $this->user_id;
    }

    function upload( $file, $post){
        if(  $file['error'] ) $this->throw_exception( "发生错误，录音可能文件太大！",7402);
        //$this->throw_exception( $_POST['bb'].']' .(isset($_POST['time'])?'time':'notime'));
        $p_file=[];
        $p_file['daily']= $this->getDaily( $post['daily'] );
        //$d = drFun::txUpload( $file ,['dir'=>'daily','ext'=> ['amr'=>1]]  );
        $d = drFun::cfsUpload( $file ,['dir'=>'daily','ext'=> ['amr'=>1]]  );
        $p_file['file']= $d['file'];
        $p_file['time']= intval($post['time']);
        $p_file['ctime']= time();
        $p_file['user_id']=  $this->getUserID();
        $p_file['daily']= $this->getDaily( $post['daily'] );
        $du_id = $this->insert( $this->tb_daily, $p_file  );

        $this->getLogin()->padLogAdd($du_id,484,'录制'. $p_file['daily'].'的朗读，时长为'. $p_file['time'] .'秒 '  );
        return $this;
    }

    function getDaily($daily ){
        $daily2 = intval( strtr(  $daily ,['-'=>'']));
        if($daily2<=0 ) $this->throw_exception("daily参数错误！". $daily,7403);
        return $daily2;
    }

    function getMe( $daily){
        return $this->createSql()->select( $this->tb_daily,['user_id'=>$this->getUserID(),'daily'=>$this->getDaily($daily)] ,[],[],['daily_id'=>'desc'])->getAll();
    }
    function getListWithPage( $daily ,$opt=[] ){
        $order = ['good_cnt'=>'desc','daily_id'=>'asc']; //
        if( isset( $opt['order'])) $order=$opt['order'] ;
        $darr = $this->createSql()->selectWithPage( $this->tb_daily,['daily'=>$this->getDaily($daily)],20,[],$order);
        return $darr ;
    }
    function getListMe($daily ,$opt=[] ){
        $order = ['good_cnt'=>'desc','daily_id'=>'desc']; //
        $list = $this->createSql()->select( $this->tb_daily,['user_id'=>$this->getUserID(),'daily'=>$this->getDaily($daily)],[],[],$order)->getAll();//selectWithPage( $this->tb_daily,['daily'=>$this->getDaily($daily)],20,[],$order);
        drFun::cdnImg(  $list ,['file'] ,$opt['uid']==5 ? 'txcos':'http' );
        //if( $opt['uid']==5 ) $this->drExit($opt );
        return $list ;
    }
    function getById( $id ){
        $row = $this->createSql()->select( $this->tb_daily,['daily_id'=>$id])->getRow();
        if(! $row)  $this->throw_exception("已经删除或者不存在！",7404);
        return $row;
    }
    function delByID( $id ){
        $row = $this->getById( $id);
        if( $row['user_id']!=$this->getUserID() ) $this->throw_exception("仅自己可删除！",7405);
        drFun::recycleLog( $row['user_id'],204, $row  );
        $this->createSql()->delete(  $this->tb_daily,['daily_id'=>$id] )->query();
        return $this ;
    }
}