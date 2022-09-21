<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/12 0012
 * Time: 下午 8:15
 */

namespace model;


class drException extends \Exception
{
    // 重定义构造器使 message 变为必须被指定的属性
    public function __construct($message, $code = 0, Exception $previous = null) {
        // 自定义的代码

        // 确保所有变量都被正确赋值
        parent::__construct($message, $code, $previous);
    }

    /*
    // 自定义字符串输出的样式
    public function __toString() {
        return parent::__toString();
        //return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
    */

    public function customFunction() {
        echo "A custom function for this type of exception\n";
    }
}