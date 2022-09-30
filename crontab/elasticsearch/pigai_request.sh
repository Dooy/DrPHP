#!/bin/bash
source /etc/profile
DIR="/data/app/elastic2.4/elasticsearch-jdbc-2.3.1.0"
bin=${DIR}/bin
lib=${DIR}/lib
echo $lib
echo $(date)
#curl -XDELETE 'localhost:9200/pigairequest'
#sleep 10
echo '
{
    "type" : "jdbc",
        "jdbc" : {
            "url" : "jdbc:mysql://17.0.0.1:3306/pigai_org",
            "user" : "root",
            "password" : "cikuutest!",
            "locale" : "en_US",
	    "sql":" select   s.is_tongji, r.request_id as _id,r.request_id , r.essay_title , r.essay_type,FROM_UNIXTIME(r.create_time) as ctime,r.user_id  , FROM_UNIXTIME(r.valid_begin_time) as valid_begin_time , FROM_UNIXTIME(r.valid_end_time) as valid_end_time,r.essay_cnt  ,r.type,r.did,r.cat_id,r.manfen  ,mi.school ,s.city,  s.city2,  s.city3 ,s.type2 as school_type,mi.rz  ,rq.f_request_id  ,ui.invite_id as invite_uid  from   eng_essay_request as r  left join  member_info as mi  on r.user_id=mi.user_id   left join school as s on mi.school=s.school    left join  eng_rq_ying as rq on rq.request_id=r.request_id   left join user_invite as ui on ui.user_id=r.user_id where r.request_id>2558576",
            "elasticsearch" : {
                "host" : "127.0.0.1",
                "port" : 9301
            },
            "index" : "pigairequest",
            "type" : "request",
            "index_settings" : {
            "index" : {
                "number_of_replicas": 0,
                "number_of_shards": 1
            }
        },
        "type_mapping": {
            "request" : {
               "dynamic_templates": [
                { "es": {
                      "match":              "*", 
                      "match_mapping_type": "integer",
                      "mapping": {
                          "type":           "integer",
                          "index":       "not_analyzed"
                      }
                }},
                { "elong": {
                      "match":              "*", 
                      "match_mapping_type": "long",
                      "mapping": {
                          "type":           "long",
                          "index":       "not_analyzed"
                      }
                }},

                { "en": {
                      "match":              "*", 
                      "match_mapping_type": "string",
                      "mapping": {
                          "type":           "string",
                          "store":           "no",
                          "index":       "not_analyzed"
                      }
                }} 
            ]    ,
            "properties": {
                    "school": {
                   "type":"string",
                  "store":"no",
                "index":"not_analyzed"
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
