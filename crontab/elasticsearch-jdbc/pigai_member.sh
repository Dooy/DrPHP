#!/bin/bash
source /etc/profile
DIR="/data/app/elastic2.4/elasticsearch-jdbc-2.3.1.0"
bin=${DIR}/bin
lib=${DIR}/lib
echo $lib
echo $(date)
#curl -XDELETE 'localhost:9200/pigaimember'
curl -XDELETE 'http://81.69.15.31:9201/pigaimember'
sleep 10

echo '
{
    "type" : "jdbc",
        "jdbc" : {
            "url" : "jdbc:mysql://45.113.201.79:3306/pigai_org",
            "user" : "cikuu",
            "password" : "cikuutest!",
            "locale" : "en_US",
	    "sql":"SELECT  s.is_tongji,   m.user_id as _id,m.user_id,m.email_check , mi.tel , FROM_UNIXTIME( m.ctime) as ctime ,m.email ,m.teacher_or_student as ts,FROM_UNIXTIME(m.lastlogin) as lastlogin , FROM_UNIXTIME( m.end_time) as end_time ,m.lg_cnt,m.score ,m.renzheng,m.pay, FROM_UNIXTIME(m.gt_time) as gt_time,m.school_type as m_school_type,mi.name ,mi.school,mi.student_number as stu_number,mi.class as stu_class,mi.request_cnt,mi.essay_cnt ,mi.stu_cnt,mi.rz,mi.pigai_cnt,mi.version_cnt,mi.sex ,s.city,  s.city2,  s.city3 ,s.type2 as school_type  ,ui.invite_id as invite_uid FROM  member as m left join member_info AS mi on mi.user_id=m.user_id left join school AS s ON mi.school = s.school   left join user_invite as ui on ui.user_id=m.user_id where m.user_id>25889597 ",
            "elasticsearch" : {
                "host" : "81.69.15.31",
                "port" : 9301
            },
            "index" : "pigaimember",
            "type" : "member",
            "index_settings" : {
            "index" : {
                "number_of_replicas": 0,
                "number_of_shards": 1
            }
        },
        "type_mapping": {
            "member" : {
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
