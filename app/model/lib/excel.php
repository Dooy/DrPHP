<?php
/**
 * excel相关处理
 *
 * 用法：
 * - 用法1 $excel= new excel(); $excel->start()->writeHead($head)->writeLine($listArr)->save("文件名称");
 * - 用法2 $excel= new excel(); $excel->saveWithFun( $fun,$fileName); $fun=function(&$head,&list){}
 *
 * User: Administrator
 * Date: 2017/8/29
 * Time: 16:34
 */

namespace model\lib;


use model\model;

class excel extends model
{
    private $objPHPExcel;
    private $row_num=0;
    private $keys =[];
    private $sheet_index=0;
    function __construct( )
    {
        $dir = ROOT_PATH.'/lib/';
        //include_once $dir.'PHPExcel/IOFactory.php';
//        $inputFileType = \PHPExcel_IOFactory::identify($inputFileName);
//        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
//        $objReader->setReadDataOnly(true);
    }

    /**
     * 初始化一个 PHPExcel
     * @return $this
     */
    function start( $opt=[]){
        require_once ROOT_PATH.'/lib/PHPExcel.php';//require_once 'lib/PHPExcel.php';'.;
        $objPHPExcel =  new \PHPExcel();//new \PHPExcel();

        $objPHPExcel->getProperties()->setCreator("go")
            ->setLastModifiedBy("go")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("go.com ")
            ->setKeywords("office 2007 go php")
            ->setCategory("Haoce");
        $this->objPHPExcel = $objPHPExcel;
        $this->row_num=0;
        $this->sheet_index =0;

        $this->headStart($opt );
        $objPHPExcel->getActiveSheet()->setTitle( $opt['title'] ? mb_substr( $opt['title'],0,20,'utf-8') :'qf');

        return  $this;
    }

    private  function headStart($opt){
        if( isset( $opt['start'])){
            $this->row_num++;
            $start=0;
            $this->getObjPHPExcel()->setActiveSheetIndex(  $this->sheet_index )->setCellValue( $this->getChar($start++).$this->row_num , $opt['start']  );
            $this->row_num++;
        }
        return $this;
    }

    function sheet($sheet_index, $opt=[]){
        $this->row_num=0;
        $this->getObjPHPExcel()->createSheet($sheet_index );
        $this->sheet_index= intval($sheet_index );
        $this->headStart($opt );
        $this->getObjPHPExcel()->setActiveSheetIndex(  $this->sheet_index )->setTitle( $opt['title'] ? mb_substr( $opt['title'],0,20,'utf-8') :'好策_'.$sheet_index );
        return $this;
    }


    /**
     * 获取objPHPExcel
     * @return \PHPExcel
     */
    function getObjPHPExcel(){
        if(   $this->objPHPExcel  ) return  $this->objPHPExcel;
        $this->throw_exception( "未初始化请先 start",3301);
    }
    private function setObjPHPExcel( $obj ){
        $this->objPHPExcel=$obj;
        return $this;
    }

    /**
     * 写头文件 一层数组 [k1=>v1,k2=>v2]
     * @param array $head
     * @return $this
     */
    function writeHead( $head ){

        $this->row_num++;
        $start=0;
        $objPHPExcel= $this->getObjPHPExcel();
        $this->keys = array_keys( $head);
        if( ! $this->keys  ) {
            $this->throw_exception( "head key 不存在",3302);
        }

        foreach( $head as $k=>$v  ){
            $objPHPExcel->setActiveSheetIndex( $this->sheet_index )->setCellValue( $this->getChar($start++).$this->row_num , $v );
        }
        $this->setObjPHPExcel( $objPHPExcel );
        return $this;
    }

    /**
     * 写每行 二层数组 [[k1=>v1,k2=>v2],[]]
     * @param $listArr
     * @return $this
     */
    function writeLine( $listArr ){
        if( ! $this->keys  ) {
            $this->throw_exception( "请先 writeHead",3303);
        }
        $objPHPExcel = $this->getObjPHPExcel();
        foreach( $listArr  as $k=>$v ){
            $this->row_num++;
            $r_num = $this->row_num;
            $start=0;
            foreach( $this->keys as $k2 ){
                $str = $v[ $k2]; //.' '
                if(! is_numeric($str)) $str  .=' ';
                $objPHPExcel->setActiveSheetIndex( $this->sheet_index )->setCellValue( $this->getChar($start++).$r_num , $str );
            }
        }
        $this->setObjPHPExcel( $objPHPExcel );
        return $this;
    }

    /**
     * 保存
     * @param $file
     */
    function save( $file,$opt=[] ){
        $objPHPExcel = $this->getObjPHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.$file.'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        $this->drExit();
    }

    /**
     * 将数值 1 2 3。。。转为 A B C ...
     * @param $leng
     * @return string
     */
    function getChar($leng){
        $start = 65;
        $a = intval($leng/26);
        $a = $a>0? chr( $start + $a-1) :'' ;
        $b = chr( 65+ $leng%26);
        return $a.$b;
    }


    /**
     * 去除excel中的特殊符号
     * @param $str
     * @return string
     */
    function clear( $str ){
        $arr = array('='=>'','>'=>'','<'=>'');
        $str = strtr($str,  $arr );
        return $str ;
    }

    /**
     * 闭包保存 将要处理的事件放在  $function( &$head,&$list) 当中
     * @param string $function
     * @param string $file
     * @return $this
     */
    function saveWithFun( $function, $file=""){
        $head=[];
        $list=[];
        $function( $head, $list );
        $this->saveByHeadLine( $head, $list,$file );
        return $this;
    }

    function saveByHeadLine( $head, $list , $file="", $opt=[] ){
        if( $_GET['save_name']) $file= trim( $_GET['save_name'] ) ;
        elseif( $file=='') $file="ex_".date("Y-m-d_His");
        else $file= $file."_".date("Y-m-d_His");
        //$this->drExit($file );
        $this->start( $opt )->writeHead($head)->writeLine( $list)->save( $file ,$opt );
        return $this;
    }

    /**
     * 闭包带过来是excel的类  $function( $excel ); $excel->writeHead($head)->writeLine( $list)
     * @param string $function
     * @return $this
     */
    function saveByFun(  $function ){
        $file="ex_".date("Y-m-d_His");
        $this->start();
        $function( $this );
        $this->save( $file );
        return $this;
    }
}