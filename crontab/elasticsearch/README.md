# elasticsearch 搭建与配置

## elasticsearch 安装
 elasticsearch
 使用docker 安装 选用  版本 elasticsearch 2.4
 
 ```
 docker pull elasticsearch:2.4-alpine
 
 sudo docker run -d \
    -p 9201:9200 \
    -p 9301:9300 \
    -p 5601:5601 \
    --restart=always \
    -e TZ=Asia/Shanghai \
    --privileged=true \
    --name=elastic2.4 \
    elasticsearch:2.4-alpine
 ```

## elasticsearch-sql 2.4.0.1 插件

https://github.com/NLPchina/elasticsearch-sql/releases/download/2.4.0.1/elasticsearch-sql-2.4.0.1.zip

插件安装 
```
./bin/plugin install https://github.com/NLPchina/elasticsearch-sql/releases/download/2.4.0.1/elasticsearch-sql-2.4.0.1.zip
```

## elasticsearch-jdbc 
非插件
版本 elasticsearch-jdbc-2.3.1.0
https://github.com/jprante/elasticsearch-jdbc/releases/tag/2.3.1.0

配置 请看 本目录下面的 pigai_essay.sh pigai_member.sh pigai_request.sh

## 设计思路 
请参考 https://www.zybuluo.com/dooy/note/384100
