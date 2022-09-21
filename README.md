# DrPHP
自己写的 一个php 简易框架
### 环境要求
PHP版本必须大于5.6
### nginx 配置
```
server
{
    listen 80; 
    server_name   dr.drphp.com ;
    index index.html index.php index.shtml index.htm ; 
    root  D:\DrPHP\webroot;

    location / {
           if (!-e $request_filename) {
            rewrite ^/(.*)  /index.php/$1 last;
        }
        break;
    }

    #include nginx_haoce.conf;

      location ~ \.php(/|$)
    {   
	if ( $fastcgi_script_name != "/index.php" ) {
	    return 403;
	}     
        #include fastcgi_72; 
	fastcgi_pass   127.0.0.1:9000;
	fastcgi_index  index.php;
	fastcgi_split_path_info  ^((?U).+\.php)(/?.+)$;
	fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
	fastcgi_param  PATH_INFO  $fastcgi_path_info;
	fastcgi_param  PATH_TRANSLATED  $document_root$fastcgi_path_info;
	include        fastcgi_params;
	fastcgi_param  SERVER_ONLINE        debug;
    } 

    location ~ ^(.*)\/\.svn\/{
        return 403;
    }

    location ~* .(jpg|gif|png|js|css|ico)$ {
       if (-f $request_filename) {
             expires max;
             break;
       }
   }


}
```
