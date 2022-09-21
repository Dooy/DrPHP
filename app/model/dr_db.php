<?php

namespace model;

use DR\DR;

/**
 * mysql数据库操作类，在使用中经量用sql类代替
 *
 * - 使用 {@link $mysql_link}解决实例带来的重复链接server
 * - 配置在  ROOT_PATH . '/config/conf_db.php' 文件内
 *
 * @package model
 */
class DR_DB extends DR
{
    /**
     * @var bool 链接link 实例数据库链接，不粗腰每次实例的时候都重新链接mysql
     */
    static public $mysql_link = false;
    static public $qlog = array();
    protected $mysqli; //主
    protected $s_mysqli; //从
    protected $sql;
    protected $rs;
    protected $query_num = 0;
    protected $fetch_mode = MYSQLI_ASSOC;
    protected $cache_type = 'file';
    protected $cache_dir = './cache/';
    protected $cache_time = 1800;
    protected $reload_cache = false;
    private $query_log = array();
    private $is_magic_quotes;

    function __construct()
    {
        $this->loadDB();
        $this->is_magic_quotes = get_magic_quotes_gpc();
    }

    function init()
    {
        parent::init();
    }

    function filesFilter($var, $filesKey)
    {
        $re = array();
        foreach ($filesKey as $k) {
            if (isset($var[$k])) $re[$k] = $var[$k];
        }
        return $re;
    }

    public function loadDB()
    {
        //global $_mysql_link;
        $_mysql_link = DR_DB::$mysql_link;
        if ($_mysql_link) {
            $this->s_mysqli = $this->mysqli = $_mysql_link;
            //echo "loadDb2<br>\n";
            return true;
        }
        //echo "test<br>\n\n";
        include_once ROOT_PATH . '/config/conf_db.php';

        $this->connect($conf_db['dbhost'], $conf_db['dbuser'], $conf_db['dbpass'], $conf_db['dbname'], $conf_db['dbport']);
        //$_mysql_link =  $this->mysqli;
        DR_DB::$mysql_link = $this->mysqli;
        return true;
    }


    public function connect($dbhost, $dbuser, $dbpass, $dbname, $dbport)
    {
        //$dbhost, $dbuser, $dbpass, $dbname, $dbport
        $this->mysqli = new \mysqli($dbhost, $dbuser, $dbpass, $dbname, $dbport);
        if (mysqli_connect_errno()) {
            $this->mysqli = false;
            //echo "\n<!--".'<h2>'.mysqli_connect_error().'</h2>';
            //echo '<p>Host: '.$this->mysqli->host_info.'</p>'."\n-->";
            $this->error("connect_error:" . $this->mysqli->host_info, mysqli_connect_errno(), "系统维修 " .mysqli_connect_error());



        } else {
            $this->mysqli->set_charset("utf8");
        }

        $this->s_mysqli = $this->mysqli;
    }


    public function getServerHost()
    {
        $re['master'] = $this->mysqli->host_info;
        $re['slave'] = $this->s_mysqli->host_info;
        return $re;
    }

    public function __destruct()
    {

        $this->free();
        $this->close();
    }

    protected function free()
    {
        if (is_object($this->rs)) @$this->rs->free();
    }

    function close()
    {
        //$this->log("mysql close");
		if( is_object( $this->mysqli ) ) $this->mysqli->close();

    }

    public function fetch()
    {
        return $this->rs->fetch_array($this->fetch_mode);
    }

    protected function getQuerySql($sql, $limit = null)
    {
        if (@preg_match("/[0-9]+(,[ ]?[0-9]+)?/is", $limit) && !preg_match("/ LIMIT [0-9]+(,[ ]?[0-9]+)?$/is", $sql)) {
            $sql .= " LIMIT " . $limit;
        }
        return $sql;
    }

    protected function get_cache($sql, $method)
    {
        $cache = lib_cache::T($this->cache_type);
        $key = $sql . $method;
        if ($this->cache_type == 'file') {
            $cache->set_cache_dir($this->cache_dir);
        }
        $cache->set_cache_time($this->cache_time);
        $res = $cache->get_cache($key);
        if ($this->reload_cache || !$res) {
            $res = $this->$method($sql);
            $cache->set_cache($key, $res);
        }
        return $res;
    }

    public function query_num()
    {
        return $this->query_num;
    }

    public function set_cache_type($cache_type)
    {
        $this->cache_type = $cache_type;
    }

    public function set_cache_dir($cache_dir)
    {
        $this->cache_dir = $cache_dir;
    }

    public function set_cache_time($cache_time)
    {
        $this->cache_time = $cache_time;
    }

    public function query($sql, $limit = null)
    {
        $sql = $this->getQuerySql($sql, $limit);
        $this->sql = $sql;


        $sql = trim($sql);
        if (!stripos($sql, 'count') and stripos($sql, 'select') === 0) {
            $this->rs = $this->s_mysqli->query($sql); //从
            $link = $this->s_mysqli;
        } else {
            $this->rs = $this->mysqli->query($sql); //主服务器
            $link = $this->mysqli;
        }

        if (!$this->rs) {
            $e_arr = debug_backtrace();
            $str = '';
            foreach ($e_arr as $k => $v) $str .= "\n#" . $k . " " . $v['file'] . " " . $v['function'] . " " . $v['line'];
            $this->error($sql .   $str);
        } else {
            $this->query_num++;
            DR_DB::$qlog[]= $sql;
            return $this->rs;
        }
    }

    function getLog()
    {
        return $this->query_log;
    }

    /**
     * 取得结果集中的第一列
     *
     * @param string $sql SQL 语句
     * @param mixed $limit 根据接收的 limit 变量来限制结果集
     * @return array
     */
    public function getCol($sql, $limit = null)
    {
        $this->query($sql, $limit);
        $this->fetch_mode = MYSQLI_NUM;
        $result = array();
        //print_r($this->fetch());
        while ($rows = $this->fetch()) {
            $result[] = $rows[0];
        }
        $this->free();
        return $result;
    }

    function getCol2($sql, $limit = null)
    {
        $this->query($sql, $limit);
        $this->fetch_mode = MYSQLI_NUM;
        $result = array();
        //print_r($this->fetch());
        while ($rows = $this->fetch()) {
            $result[$rows[0]] = $rows[1];
        }
        $this->free();
        return $result;
    }

    public function insertOne($table, $var)
    {
        if (!$var) return false;
        $sql = "insert into `" . $table . "`  set ";
        foreach ($var as $k => $v) $sql .= " `" . $k . "`='" . $this->addslashes($v) . "',";
        if (!$this->query(trim($sql, ','))) return false;
        return $this->lastID();
    }

    /**
     * @param $table
     * @param $arr 二维数组
     */


    public function insertArr($table, $arr)
    {
        $i = 0;
        $sql = "insert into `" . $table . "` ";
        foreach ($arr as $var) {
            $i++;
            if ($i == 1) {
                $files = array_keys($var);
                $sql .= "(";
                foreach ($files as $v) $sql .= "`" . $v . "``,";
                $sql = trim($sql, ',') . ") values";
            }
            $sql .= "(";
            foreach ($files as $ik => $v) {
                if ($ik > 0) $sql .= ",";
                $sql .= "'" . $this->addslashes($var[$v]) . "''";
            }
            $sql .= "),";
        }
        $this->query(trim($sql, ","));
        return $this->lastID();
    }

    public function addslashes($str)
    {
        return $this->is_magic_quotes?addslashes(stripcslashes($str)): addslashes($str) ;
    }


    public function getOne($sql)
    {
        $this->query($sql, 1);
        $this->fetch_mode = MYSQLI_NUM;
        $row = $this->fetch();
        $this->free();
        return $row[0];
    }

    public function get_one($sql)
    {
        return $this->getOne($sql);
    }

    public function cache_one($sql, $reload = false)
    {
        $this->reload_cache = $reload;
        $sql = $this->getQuerySql($sql, 1);
        return $this->get_cache($sql, 'getOne');
    }

    public function getRow($sql, $fetch_mode = MYSQLI_ASSOC)
    {
        $this->query($sql,  stripos($sql,'limit')? null: 1);
        $this->fetch_mode = $fetch_mode;
        $row = $this->fetch();
        $this->free();
        return $row;
    }

    public function get_row($sql, $fetch_mode = MYSQLI_ASSOC)
    {
        return $this->getRow($sql);
    }

    public function cache_row($sql, $reload = false)
    {
        $this->reload_cache = $reload;
        $sql = $this->getQuerySql($sql, 1);
        return $this->get_cache($sql, 'getRow');
    }

    public function getAll($sql, $limit = null, $fetch_mode = MYSQLI_ASSOC)
    {
        $this->query($sql, $limit);
        $all_rows = array();
        $this->fetch_mode = $fetch_mode;
        while ($rows = $this->fetch()) {
            $all_rows[] = $rows;
        }
        $this->free();
        return $all_rows;
    }

    /**
     * 获取通过函数处理，php5.6开始支持
     * @param string $sql
     * @param string $function 函数体
     * @param int $limit
     * @param int $fetch_mode
     * @return $this
     */
    public function getWithFun( $sql ,$function   , $limit = null, $fetch_mode = MYSQLI_ASSOC){
        $this->query($sql, $limit);
        //$all_rows = array();
        $this->fetch_mode = $fetch_mode;
        while ($rows = $this->fetch()) {
            //$all_rows[] = $rows;
            $function( $rows );
        }
        $this->free();
        return $this;
    }

    public function getAll2($sql, $limit = null, $fetch_mode = MYSQLI_ASSOC)
    {
        $this->query($sql, $limit);
        $all_rows = array();
        $this->fetch_mode = $fetch_mode;
        while ($rows = $this->fetch()) {
            $all_rows[$rows[0]] = $rows;
        }
        $this->free();
        return $all_rows;
    }

    # added 2012.2.14
    public function getMap($sql, $pk, &$arr)
    {
        $this->query($sql, null);
        $this->fetch_mode = MYSQLI_ASSOC;
        while ($rows = $this->fetch()) {
            $arr[$rows[$pk]] = $rows;
        }
        $this->free();
    }

    public function getAll2key($sql, $key, $limit = null, $fetch_mode = MYSQLI_ASSOC)
    {
        $this->query($sql, $limit);
        $all_rows = array();
        $this->fetch_mode = $fetch_mode;
        while ($rows = $this->fetch()) {
            $all_rows[$rows[$key]] = $rows;
        }
        $this->free();
        return $all_rows;
    }

    public function get_all($sql, $limit = null, $fetch_mode = MYSQLI_ASSOC)
    {
        return $this->getAll($sql);
    }

    public function cache_all($sql, $reload = false, $limit = null)
    {
        $this->reload_cache = $reload;
        $sql = $this->getQuerySql($sql, $limit);
        return $this->get_cache($sql, 'getAll');
    }

    public function insert_id()
    {
        return $this->mysqli->insert_id;
    }

    public function lastID()
    {
        return $this->insert_id();
    }

    public function escape($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape($val);
            }
        } else {
            $str = addslashes(trim($str));
        }
        return $str;
    }

    /**
     * 错误收集
     * @param $msg
     * @param int $error
     * @param string $error_des
     */
    function error($msg,$error=222,$error_des='系统错误')
    {
        /*
        $re['where'] = 'SQL';
        $re['msg'] = $msg;
        $re['ct'] = time();
        $re['ct_str'] = date("Y-m-d H:i:s");
        $re['url'] = $_SERVER['HTTP_HOST'] . "/?" . $_SERVER['QUERY_STRING'];
        */
		$this->log($msg );
        //$this->throw_exception("系统错误",222 );
        $this->displayJson( "system error" , $error,  $error_des);
        $this->drExit();
    }

    /**
     * 日志收集保留在 db.log 中
     * @param string $str
     */
    public function log( $str ){
        $file= dirname( ( dirname( dirname( __FILE__))) ).'/db.log';
        if( is_file($file)) file_put_contents($file, "\n[".date("Y-m-d H:i:s")."]\n".$str ,  FILE_APPEND );
    }

    /*
      * 队列
      */
    function mq_set($exchanges, $arr, $Routing_Key = '', $query_name = '')
    {
        if (!class_exists('AMQPConnection')) return false;
        $params = array('host' => 'mq_server.pigai.org',
            'port' => 5672,
            'login' => 'pigai',
            'password' => 'NdyX3KuCq',
            'vhost' => '/',
            'connection_timeout' => 3,
            'read_write_timeout' => 1
        );
        //print_r($params );
        try {
            $cnn = new AMQPConnection($params);
            $cnn->connect();
        } catch (AMQPConnectionException $e) {
            //$this->Ex_error($e);
            return false;
        }
        $ch = new AMQPChannel($cnn);
        $exchange = new AMQPExchange($ch);
        $exchange->setName($exchanges); #队列通道名称
        //if( !$r )  return 0 ;
        //$re = $key='e_'.$arr['rid'].'_'.$arr['user_id'];
        $key = $Routing_Key == '' ? 'info' : $Routing_Key;
        $res = $exchange->publish(is_array($arr) ? json_encode($arr) : $arr, $key, AMQP_NOPARAM, array('delivery_mode' => 2, 'priority' => 9));


        $cnn->disconnect();
        return $res;
    }


}
