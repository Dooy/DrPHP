var DC = DC ||{};

DC.msg= function( msg ){
  try{
    api.toast({  msg: msg, duration: 3000, location: 'bottom'});
	}catch(e){alert(str);}
}

DC.dateUtils = {
	UNITS: {
		'年': 31557600000,
		'月': 2629800000,
		'周': 604800000,
		'天': 86400000,
		'小时': 3600000,
		'分钟': 60000,
		'秒': 1000
	},
	humanize: function(milliseconds) {
		var humanize = '';
		mui.each(this.UNITS, function(unit, value) {
			if(milliseconds >= value) {
				humanize = Math.floor(milliseconds / value) + unit + '前';
				return false;
			}
			return true;
		});
		return humanize || '刚刚';
	},
	format: function(dateStr) {
		var date = this.parse(dateStr);
		return this.format_date( date );

	},
	week:function (date) {
        var a = new Array("日", "一", "二", "三", "四", "五", "六");
        var week= date.getDay();
        return "星期"+ a[week];
    },
    format_date:function ( date) {
        var _format = function(number) {
            return(number < 10 ? ('0' + number) : number);
        };
        var _getDate=function ( AddDayCount) {
            var dd = new Date();
            dd.setDate(dd.getDate()+AddDayCount);          dd.setHours(0);            dd.setMinutes(0);            dd.setSeconds(0);
            return dd;
        }

        var today = _getDate( 0);
        var yeterday =  _getDate(-1 );

        var diff = Date.now() - date.getTime();
        if( date.getTime() >today.getTime() || diff < 10*this.UNITS['分钟']   ) {
            return  _format(date.getHours()) + ':' + _format(date.getMinutes())+':'+  _format(date.getSeconds());
        }else if( date.getTime() >today.getTime() || diff < this.UNITS['小时']   ) {
        	return  _format(date.getHours()) + ':' + _format(date.getMinutes())
        }else if( date.getTime() >yeterday.getTime() ) {
            return  '昨天';
        }else if(diff < this.UNITS['天']) {
            return this.humanize(diff);
        }else if(diff < this.UNITS['周']) {
            return this.week( date );
        }
        return date.getFullYear() + '/' + _format(date.getMonth() + 1) + '/' + _format(date.getDate()) ;// + '-' + _format(date.getHours()) + ':' + _format(date.getMinutes());
    }
	,parse:function (str) {//将"yyyy-mm-dd HH:MM:ss"格式的字符串，转化为一个Date对象
		var a = str.split(/[^0-9]/);
		return new Date (a[0],a[1]-1,a[2],a[3],a[4],a[5] );
	}
	,format_time:function ( seconds) {
        var newDate = new Date();
        newDate.setTime(seconds * 1000);
        return   this.format_date( newDate );
    }
    ,date_format:function (seconds,format_str ) {

        Date.prototype.Format = function (fmt) {
            var o = {
                "M+": this.getMonth() + 1, //月份
                "d+": this.getDate(), //日
                "H+": this.getHours(), //小时
                "m+": this.getMinutes(), //分
                "s+": this.getSeconds(), //秒
                "q+": Math.floor((this.getMonth() + 3) / 3), //季度
                "S": this.getMilliseconds() //毫秒
            };
            if (/(y+)/.test(fmt)) fmt = fmt.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
            for (var k in o)
                if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
            return fmt;
        }
        var newDate = new Date();
        seconds= parseInt(seconds );
        if( ! seconds ) return '';
        newDate.setTime( seconds * 1000);
        return newDate.Format(format_str );
    }

};

DC.extend= function( obj,from_obj ){
    for(var p in obj)   if( typeof from_obj[p]!='undefined') obj[p]= from_obj[p];
}

DC.getHost=function(){
    //return 'https://qunfuapp.readface.cn';
    return '/';
    return 'https://qf.zahei.com';
    return 'https://qunfuclient.readface.cn:4443';
    //return 'http://qunfu.zahei.com';
}
DC.trim= function(str){
    return str.replace(/(^\s*)|(\s*$)/g, '');
}
DC.getUrl=function( url ){
    url = DC.trim(url);
    if(url.substr(0,4)=='http') return url;
    if( url.substr(0,1)=='/')return DC.getHost() + url ;
    return DC.getHost()+'/'+ url ;
}

DC.sign=function( data ){

}

DC.closeApp=function(){
    api.closeWidget({
        id: api.appId,
        retData: {
            name: 'closeWidget'
        },
        animation: {
            type: 'flip',
            subType: 'from_bottom',
            duration: 300
        }
    });
}

DC.ajax= function (url ) {
    var conf={
        method:'post',
        data:{t:'test'},
        isCheck:true,
        success:function (rep ) {
            console.log( 'success: '+ JSON.stringify(rep) );
        }
        ,error:function (rep) {
            var arr = ['连接错误','超时','授权错误','数据类型错误'];
            if( typeof arr[rep.code]   !='undefined') {
                DC.msg('错误：'+  arr[rep.code]  );
            }else{
                DC.msg('未知错误：'+  rep.code  );
            }
            console.log( 'error:'+ JSON.stringify(rep) );
        }
        ,error2:null
        ,files:null
    }
    if( arguments.length>1) DC.extend( conf,arguments[1]);

    url =  DC.getUrl(url);
    console.log('url:' +url );

    var data= {
        values:conf.data
    };
    if( conf.files ) data.files =  conf.files;



    api.ajax({
        url: url,
        method: 'post',dataType:'json'
        ,data: data
        ,headers:{ 'X-Display':'json' }
        ,timeout: ( conf.files ? 60: 9 )
    }, function(ret, err) {
        if (ret) {
            //conf.success( ret );
            if( ret.error>0 &&  conf.isCheck ){
                if( typeof  conf.error2 =='function' ){
                    conf.error2( ret );
                }else   DC.msg('错误：'+  ret.error_des +'('+ret.error +')');
                return ;
            }
            if( typeof conf.success =='function' ){
                conf.success( conf.isCheck ? ret.data :ret  );
            }

        } else {
            conf.error( err );
        }
    });

}
