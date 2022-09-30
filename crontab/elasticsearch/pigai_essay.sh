#!/bin/bash
source /etc/profile
DIR="/data/app/elastic2.4/elasticsearch-jdbc-2.3.1.0"
bin=${DIR}/bin
lib=${DIR}/lib
echo $(date)
echo $lib
#curl -XDELETE 'localhost:9200/pigaiessay'
#sleep 50
echo '
{
    "type" : "jdbc",
        "jdbc" : {
            "url" : "jdbc:mysql://127.0.0.1:3306/pigai_org",
            "user" : "root",
            "password" : "8888",
            "locale" : "en_US",
            "sql":"select  s.is_tongji, e.essay_id as _id,e.essay_id,e.user_id ,e.request_id,FROM_UNIXTIME(e.ctime) as ctime ,e.score   ,e.sy_score,e.type,e.version ,e.author_id as similar  ,mi.name ,mi.school ,mi.student_number as stu_number,mi.class as stu_class ,s.city,  s.city2,  s.city3 ,s.type2 as school_type   ,ui.invite_id as invite_uid  ,ea.good,ea.teacher_view,ea.view   ,r.user_id as t_user_id,r.essay_type, FROM_UNIXTIME(r.valid_end_time) as valid_end_time, r.manfen ,r.teacher_name ,rq.f_request_id ,ecat.cat_id from  eng_essay  as e   left join   member_info as mi  on e.user_id=mi.user_id    left join school as s on mi.school=s.school   left join user_invite as ui on ui.user_id=e.user_id   left join   eng_essay_attr as ea on e.essay_id=ea.essay_id   left join  eng_rq_ying as rq on rq.request_id=e.request_id    left join  eng_essay_request as r on e.request_id =r.request_id left join  eng_rq_cat as ecat on e.request_id =ecat.request_id where e.ctime>1609430400",
            "elasticsearch" : {
                "host" : "127.0.0.1",
                "port" : 9301
            },
            "index" : "pigaiessay",
            "type" : "essay",
            "index_settings" : {
            "index" : {
                 "number_of_replicas": 0,
                 "number_of_shards": 1,
                 "max_result_window" : 10000000
            }
        },
        "type_mapping": {
            "essay" : {
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
