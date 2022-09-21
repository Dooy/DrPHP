<?php


namespace model;


class logRecycle extends  log
{
    function __construct( $user_id)
    {
        $table = 'user_recycle_log';
        parent::__construct($table, $user_id);
    }
    function back( $obj ){
        $type = $this->getType( $obj['opt_type'] );
        //$this->drExit($type  );
        $this->insert($type['t'], $obj['opt_value'] );
        $this->update( $this->getTable(),['id'=>$obj['id'] ], ['is_back'=>1]);
    }


}