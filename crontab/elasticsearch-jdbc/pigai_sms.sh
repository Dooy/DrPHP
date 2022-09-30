#!/bin/bash
source /etc/profile
DIR="/data/app/elasticsearch.pigai.org/elasticsearch-jdbc-2.3.1.0"
bin=${DIR}/bin
lib=${DIR}/lib
echo $(date)
echo $lib
echo '
{
    "type": "jdbc",
    "jdbc": {
        "url": "jdbc:mysql://localhost:3306/pigai_org",
        "user": "pigai",
        "password": "pigai789",
        "locale": "en_US",
        "sql": "select id,mobile,len,re ,FROM_UNIXTIME(ctime) as ctime from sms",
        "elasticsearch": {
            "cluster": "elasticsearch",
            "host": "127.0.0.1",
            "port": 9300
        },
        "index": "pigaisms",
        "type": "sms",
        "index_settings": {
            "index": {
                "number_of_replicas": 0,
                "number_of_shards": 1
            }
        },
        "type_mapping": {
            "sms": {
                "dynamic_templates": [
                    {
                        "es": {
                            "match": "*",
                            "match_mapping_type": "integer",
                            "mapping": {
                                "type": "integer",
                                "index": "not_analyzed"
                            }
                        }
                    },
                    {
                        "elong": {
                            "match": "*",
                            "match_mapping_type": "long",
                            "mapping": {
                                "type": "long",
                                "index": "not_analyzed"
                            }
                        }
                    },
                    {
                        "en": {
                            "match": "*",
                            "match_mapping_type": "string",
                            "mapping": {
                                "type": "string",
                                "store": "no",
                                "index": "not_analyzed"
                            }
                        }
                    }
                ],
                "properties": {
                    "school": {
                        "type": "string",
                        "store": "no",
                        "index": "not_analyzed"
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

