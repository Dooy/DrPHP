#!/bin/bash
source /etc/profile
DIR="/data/app/elasticsearch.pigai.org/elasticsearch-jdbc-2.3.1.0"
bin=${DIR}/bin
lib=${DIR}/lib
echo $(date)
echo $lib
#curl -XDELETE 'localhost:9200/pigaiessayinit'
#sleep 10
echo '
{
    "type" : "jdbc",
        "jdbc" : {
            "url" : "jdbc:mysql://localhost:3306/pigai_org",
            "user" : "pigai",
            "password" : "pigai789",
            "locale" : "en_US",
            "sql" : "select essay_id as _id,score from eng_essay_version where version=3",
            "elasticsearch" : {
                "cluster" : "elasticsearch",
                "host" : "127.0.0.1",
                "port" : 9300
            },
            "index" : "pigaiessayinit",
            "type" : "essay",
            "index_settings" : {
            "index" : {
                 "number_of_replicas": 0,
                 "number_of_shards": 1
            }
        },
        "type_mapping": {
            "essay" : {
            "properties": {
               "score":{
                   "type":"float",
                   "store":"no"
              }
         }
                }
        }
        }
}
' | java \
       -cp "${lib}/*" \
        -Dlog4j.configurationFile=${bin}/log4j2.xml \
        org.xbib.tools.Runner \
        org.xbib.tools.JDBCImporter

 echo $(date)
