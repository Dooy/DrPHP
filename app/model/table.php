<?php
/**
 * Table
 * User: Administrator
 * Date: 2018/9/10
 * Time: 19:44
 */

namespace model;


class table extends model
{
    private $table;
    private $file;
    private $key_file;


    public function setTable($table){
        $this->table=  $table;
        $file= $this->getFile();
        if( $file ) $this->setFile( $file );
        $key = $this->getKeyFile();
        if( $key ) $this->setKeyFile( $key );
        return $this;
    }

    function getKeyFile(){
        switch ( $this->table){
            case 'mc_export':
                return 'export_id';
        }
        return '';
    }

    public function getFile(){
        $file=[];
        switch ($this->table ){
            case 'mc_export':
                $file=['notify_url','money','merchant_id','merchant_user_id','ctime','cz_user_id','cz_time','type','card_id','card_name','card_bank','card_address','order_no'];
                break;
        }
        return $file;
    }

    public function setFile( $file ){
        $this->file= $file;
        return $this;
    }

    public function setKeyFile( $key_file ){
        $this->key_file= $key_file;
        return $this;
    }

    public function append( $var ){
        $this->insert( $this->table, $var, $this->file );
        return $this;
    }
    public function lastID(){
        return $this->createSql()->lastID();
    }
    public function modifyBYKey( $key_id ,$var ){
        $this->update( $this->table, [ $this->key_file=>$key_id ] ,$var , $this->file );
        return $this ;
    }
    public function getAll( $where ,$order=[],$limit=[], $file=[] ,$opt=[]){

        return $this->createSql(  )->select( $this->table, $where , $limit , $file,  $order,$opt)->getAll();
    }
    public function getAllByKey($key ,$where ,$order=[],$limit=[], $file=[] ,$opt=[] ){
        return $this->createSql(  )->select( $this->table, $where , $limit , $file,  $order,$opt)->getAllByKey($key);
    }
    public function getAllByKeyArr($key_arr ,$where ,$order=[],$limit=[], $file=[] ,$opt=[] ){
        return $this->createSql(  )->select( $this->table, $where , $limit , $file,  $order,$opt)->getAllByKeyArr($key_arr);
    }

    public function getTable(){
        return $this->table;
    }

    public function selectWithPage( $where ,$order=[],$every=30 , $file=[]  ){
        if( !$order ) $order=[ $this->key_file=>'desc'];
        return $this->createSql()->selectWithPage( $this->table, $where,$every , $file ,$order );
    }

    public function getRowByKey($key_id){
        return $this->createSql()->select($this->table, [$this->key_file=>$key_id] )->getRow();
    }

    public function updateByKey( $key_id, $var ){
        $this->update( $this->table,  [$this->key_file=>$key_id] ,$var, $this->file );
        return $this;
    }

    public function updateByWhere( $where, $var){
        $this->update( $this->table,  $where ,$var, $this->file );
        return $this;
    }

    public function delByKey( $key_id ){
        $this->createSql()->delete( $this->table, [$this->key_file=>$key_id] )->query();
        return $this;
    }

    public function delByWhere($where,$limit=100){
        $db = $this->createSql()->delete( $this->table, $where,$limit);//->query();
        //$this->drExit( "dddd=". $db->getSQL() );
        $db->query();
        return $this;
    }

    public function getRowByWhere( $where , $opt=[]){
        $order= $opt['order']?$opt['order']:[];
        //$this->drExit($opt);
        return $this->createSql()->select($this->table, $where,[],[],$order )->getRow();
    }

    public function getColByWhere( $where,$file ){

        $db = $this->createSql()->select($this->table, $where ,[],$file );
        //$this->log("DBMA2:". $db->getSQL());
        return count($file)==2? $db->getCol2(): $db->getCol();
    }

    public function merge( $table,$key, &$data,$file=[], $opt=[]){
        $this->createSql()->merge(  $table,$key,  $data,$file , $opt );
        return $this;
    }

    /**
     * @param array $group_file
     * @param array $where
     * @param array $file
     * @param array $order
     * @param array $limit
     * @throws drException
     * @return array
     */
    public function tjByGroupToObj($group_file ,$where ,$file ,$order=[],$limit =[] ){
        return $this->createSql()->group( $this->table,$group_file, $where, $file,$order,$limit  )->getAllByKeyArr( $group_file );
    }

    /**
     * @param $group_file
     * @param $where
     * @param $file
     * @param array $order
     * @param array $limit
     * @return array
     * @throws drException
     */
    public function tjByGroup($group_file ,$where ,$file ,$order=[],$limit =[] ){
        return $this->createSql()->group( $this->table,$group_file, $where, $file,$order,$limit  )->getAll(  );
    }

    public function getCount($where ){
        return $this->createSql()->getCount( $this->table, $where )->getOne();
    }

}