<?php
/**
 * 队列处理
 * User: zahei.com
 * Date: 2017/9/3
 * Time: 11:46
 */

namespace model\lib;




use DR\DR;
use model\drFun;

class mq
{
    private $acountTopic;
    private $acountQueue;
    private $mqConnect;

    /**
     * 获取队列账号
     * @return \Account
     */
    private  function getAcountTopic(){
        if( !$this->acountTopic ){
            $file = ROOT_PATH.'/lib/cmq/config.php';
            include  $file;
            $this->acountTopic  = new \Account( _cmq_endPoint_topic ,  _cmq_secretId, _cmq_secretKey);

        }
        return $this->acountTopic;
    }

    /**
     * 主题模式：发布消息
     * @param string $topic_name
     * @param mixed $msg
     * @param array $tag
     * @return array
     */
    public  function publish(  $topic_name, $msg, $tag=[]  ){
        try
        {
            if( is_array($msg)) $msg= json_encode( $msg );

            $my_topic = $this->getAcountTopic()->get_topic($topic_name);
            $msgid = $tag? $my_topic->publish_message($msg, $tag): $my_topic->publish_message($msg);
            //DR::logs_s( $msg ,'mq.log');

        }catch(\CMQExceptionBase $e)
        {

            DR::logs_s( $e,'mq.log');
            $msgid=[];
        }
        return $msgid;
    }

    /**
     * 获取队列账号
     * @return \Account
     */
    private function getAcountQueue( $server ){
        if( !$this->acountQueue ){
            $file = ROOT_PATH.'/lib/cmq/config.php';
            include  $file;
            $this->acountQueue = new \Account( $server?$server: _cmq_endPoint_queue ,  _cmq_secretId, _cmq_secretKey );
        }
        return  $this->acountQueue ;
    }

    /**
     * 批量获取消息 并删除
     * @param string $queue_name
     * @param string $fun
     * @param array $opt
     * @return $this
     */
    public function batch_receive_message( $queue_name , $fun, $opt=[]){
        $server = "";
        if( isset( $opt['server'] ) )  $server= $opt['server'] ;
        $my_queue = $this->getAcountQueue( $server )->get_queue($queue_name);
        $time_out = $opt['time_out']>0 ? $opt['time_out']:30;

        while(1) {
            try {
                $arr = $my_queue->batch_receive_message(15, $time_out);
                $receiptHandle = [];

                #必须先删除 不然超时删除不了
                foreach ( $arr as $obj ) $receiptHandle[] = $obj->receiptHandle;
                if ($receiptHandle) $my_queue->batch_delete_message($receiptHandle);
                foreach ($arr as $obj) {  $fun($obj->msgBody);}
                #end

                /* 先处理后删除
                foreach ($arr as $obj) {
                    if($fun($obj->msgBody))    $receiptHandle[] = $obj->receiptHandle;
                }
                if ($receiptHandle) $my_queue->batch_delete_message($receiptHandle);
                */

            }catch (\Exception $e) {
                if ($e->getCode() == 7000) {
                   echo "\n[".date("Y-m-d H:i:s")."] no object";
               } else {
                   #if ($receiptHandle) $my_queue->batch_delete_message($receiptHandle);
                   exit("batch_receive_message Fail! Exception: " . $e->getCode() . "\nmessage:" . $e->getMessage());
               }
               break;
            }
        }
        return $this;
    }

    function rabbit_publish( $exchanges, $arr ,$Routing_Key='', $query_name='' ,$opt=[] ){
        //if( !class_exists('\AMQPConnection') ) return false;
        $params = array('host' =>'mq_server.haoce.com',
            'port' => 5672,
            'login' => 'haoce',
            'password' => 'hc123456',
            'vhost' => '/',
            'connection_timeout' => 3,
            'read_write_timeout' => 1
        );

        if( $opt['host']==2){
            $params['host']='mq2.haoce.com';
        }
        try{
            $cnn = new \AMQPConnection($params);
            $cnn->connect();
        }catch(\AMQPConnectionException $e){
            return false;
        }
        $ch = new \AMQPChannel($cnn);
        $exchange = new \AMQPExchange($ch);
        $exchange->setName( $exchanges ); #队列通道名称

        $key= $Routing_Key==''?'info': $Routing_Key ;
        $res = $exchange->publish( is_array( $arr) ?json_encode($arr): $arr , $key , AMQP_NOPARAM, array('delivery_mode'=>2, 'priority'=>9));
        $cnn->disconnect();
        return $res;
    }

    function rabbit_consume($mq_name, $processFunName, $opt=[]){
        $params = array(
            'host' =>'mq_server.haoce.com',
            'port' => 5672,
            'login' => 'haoce',
            'password' => 'hc123456',
            'vhost' => '/');
        if($opt['host']==2){
            $params['host']= 'mq2.haoce.com';
        }
        $cnn = new \AMQPConnection($params);
        if(!$cnn->connect()){
            //die('Cannot connect to the broker!\n');
            drFun::throw_exception('Cannot connect to the broker!\n',313);
        }

        $ch = new \AMQPChannel($cnn);

        $queue = new \AMQPQueue($ch);
        $queue->setName( $mq_name );
        //$queue->consume('processMessage');
        $queue->consume( $processFunName);
    }

}