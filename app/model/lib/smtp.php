<?php


namespace model\lib;

use model\drException;
use model\model;

define('CHARSET','utf-8');

/**
 * 邮件smtp发送处理
 * <code>
 * define('CHARSET','utf-8');
 * $_qq['server'] ='192.168.1.218';//smtp地址 可以用qq的
 * $_qq['auth_username'] = 'pigai_service@qq.com';//邮箱地址
 * $_qq['auth_password'] = 'password';//密码
 * $_qq['port'] = 25; //端口
 * $_qq['auth'] = 1;
 * $_qq['charset'] = CHARSET; //字体编码
 * $_qq['isdai'] = 1;//是否允许 验证邮箱跟分送邮箱不一致
 * $_qq['from'] = trim(urldecode( $_POST['from']));
 * $_qq['cn_name']=trim(urldecode( $_POST['cn_name'])); //替代名字
 * $sub=trim(urldecode( $_POST['sub']));
 * $body= trim(( $_POST['body']));
 * $to_mail = trim(urldecode( $_POST['to_mail']));
 * $mail = new smtp();
 * $re = $mail->sendMailBySocket( $to_mail ,$sub,$body,$_qq);
 * die( $_qq['from']. " => ".$to_mail."\t" .intval($re)."  Yes\n");
 * </code>
 */

class smtp extends model
{

    var $error='';

    /**
     * @param string $toemail 邮件地址
     * @param string $subject 邮件标题
     * @param string $message 邮件内容
     * @param array $_G 选项 具体参考代码
     * @return bool
     */
    function sendMailBySocket($toemail,$subject,$message,$_G ){
        $this->error='';
        $email_to = $toemail ;
        $email_from =  $_G['from'];//$_G['auth_username'];
        $cn_name = $_G['cn_name'];

        $email_subject = '=?'.CHARSET.'?B?'.base64_encode(preg_replace("/[\r|\n]/", '',  $subject )).'?=';
        $email_message = chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace("\n\r", "\r", $message))))));

        $maildelimiter ="\r\n" ;
        $host = 'qq.com';//'pigai.org';//$_SERVER['HTTP_HOST'];
        $version = 'v0.1';
        $ef_str =    '=?'.CHARSET.'?B?'.base64_encode($cn_name)."?= <".$email_from.">";
        //$headers = "From: $email_from{$maildelimiter}X-Priority: 3{$maildelimiter}X-Mailer: $host $version {$maildelimiter}MIME-Version: 1.0{$maildelimiter}Content-type: text/html; charset=".CHARSET."{$maildelimiter}Content-Transfer-Encoding: base64{$maildelimiter}";
        $headers = "From: $ef_str{$maildelimiter}X-Priority: 3{$maildelimiter}X-Mailer: $host $version {$maildelimiter}MIME-Version: 1.0{$maildelimiter}Content-type: text/html; charset=".CHARSET."{$maildelimiter}Content-Transfer-Encoding: base64{$maildelimiter}";
        $headers .='Reply-To: yangdaorong@haoce.com'.$maildelimiter;
        $headers .='Errors-To: dooy520@qq.com'.$maildelimiter;

        if(!$fp = @fsockopen($_G['server'], $_G['port'], $errno, $errstr, 30)) {
            $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) CONNECT - Unable to connect to the SMTP server", 0);
            return false;
        }
        stream_set_blocking($fp, true);

        $lastmessage = fgets($fp, 512);
        if(substr($lastmessage, 0, 3) != '220') {
            $this->runlog('SMTP', "{$_G[server]}:{$_G[port]} CONNECT - $lastmessage", 0);
            return false;
        }

        fputs($fp, ($_G['auth'] ? 'EHLO' : 'HELO')." pigai\r\n");
        $lastmessage = fgets($fp, 512);
        if(substr($lastmessage, 0, 3) != 220 && substr($lastmessage, 0, 3) != 250) {
            $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) HELO/EHLO - $lastmessage", 0);
            return false;
        }

        while(1) {
            if(substr($lastmessage, 3, 1) != '-' || empty($lastmessage)) {
                break;
            }
            $lastmessage = fgets($fp, 512);
        }

        if($_G['auth']) {
            fputs($fp, "AUTH LOGIN\r\n");
            $lastmessage = fgets($fp, 512);
            if(substr($lastmessage, 0, 3) != 334) {
                $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) AUTH LOGIN - $lastmessage", 0);
                return false;
            }

            fputs($fp, base64_encode($_G['auth_username'])."\r\n");
            $lastmessage = fgets($fp, 512);
            if(substr($lastmessage, 0, 3) != 334) {
                $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) USERNAME - $lastmessage", 0);
                return false;
            }

            fputs($fp, base64_encode($_G['auth_password'])."\r\n");
            $lastmessage = fgets($fp, 512);
            if(substr($lastmessage, 0, 3) != 235) {
                $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) PASSWORD - $lastmessage", 0);
                return false;
            }
            if(  $_G['isdai']!=1 ) $email_from = $_G['auth_username'];//$_G['from'];

        }

        fputs($fp, "MAIL FROM: <".preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from).">\r\n");
        $lastmessage = fgets($fp, 512);
        if(substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "MAIL FROM: <".preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from).">\r\n");
            $lastmessage = fgets($fp, 512);
            if(substr($lastmessage, 0, 3) != 250) {
                $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) MAIL FROM - $lastmessage", 0);
                return false;
            }
        }

        fputs($fp, "RCPT TO: <".preg_replace("/.*\<(.+?)\>.*/", "\\1", $toemail).">\r\n");
        $lastmessage = fgets($fp, 512);
        if(substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "RCPT TO: <".preg_replace("/.*\<(.+?)\>.*/", "\\1", $toemail).">\r\n");
            $lastmessage = fgets($fp, 512);
            $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) RCPT TO - $lastmessage", 0);
            return false;
        }

        fputs($fp, "DATA\r\n");
        $lastmessage = fgets($fp, 512);
        if(substr($lastmessage, 0, 3) != 354) {
            $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) DATA - $lastmessage", 0);
            return false;
        }


        $headers .= 'Message-ID: <'.gmdate('YmdHs').'.'.substr(md5($email_message.microtime()), 0, 6).rand(100000, 999999).'@'.$host.">{$maildelimiter}";

        fputs($fp, "Date: ".gmdate('r')."\r\n");
        fputs($fp, "To: ".$email_to."\r\n");
        fputs($fp, "Subject: ".$email_subject."\r\n");
        fputs($fp, $headers."\r\n");
        fputs($fp, "\r\n\r\n");
        fputs($fp, "$email_message\r\n.\r\n");
        $lastmessage = fgets($fp, 512);
        fputs($fp, "QUIT\r\n");
        if(substr($lastmessage, 0, 3) != 250) {
            $this->runlog('SMTP', "({$_G[server]}:{$_G[port]}) END - $lastmessage", 0);
            return false;
        }
        return true;
    }

    function runlog($r1,$r2,$r3){
        $code = $r3<=0 ? 10086:$r3  ;
        $this->log("[".$r1."] ". $r2 );
        $this->throw_exception( "邮件发送失败" ,$code );
    }

}