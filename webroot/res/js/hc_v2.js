var HC = HC ||{};
 
HC.version= '1.2.8';

String.prototype.trim=function()
{
    if( typeof arguments[0]=='string'){
        var reg = new RegExp( "(^"+arguments[0] +"*)|("+arguments[0]+"*$)",'g');
    }else{
        var reg = new RegExp( "(^\\s*)|(\\s*$)",'g');
    }
    return this.replace(  reg  ,'');
}
/**
 * 表单提交
 * @param {HTMLFormElement} fm
 */
HC.serialize= function( fm){
	var re={};
	
	var doItem=function(el){
		for(var i=0; i<el.length; i++){
			var item= el[i];			 
			var k = item.name || item.id; 
			if(!k) continue;
			switch(item.tagName.toLocaleLowerCase() ){
				case 'select':
				re[k]=   item.options[ item.selectedIndex ].value;				 
				break;
				case 'input':
				re[k]= item.value?item.value:'';				
				break;
			}	
			if( !doCheck( item, re[k]) )return false ;
		}
		return true ;
	};
	/**
	 * 
	 * @param {HTMLElement} item
	 */
	var doCheck= function( item ,value ){
		var check=  item.getAttribute('data-check' );
		if( !check) return true;
		if( check.indexOf('kong')>-1 && value==''){	
			HC.msg( item.getAttribute('data-err-kong' ) || "请勿留空" ); 
			item.focus();
			return false ;
		}
		return true; 
	}
	
	var input = fm.querySelectorAll('input');
	//console.error( input.length );
	if (!doItem(input) ) return false ;
	var input = fm.querySelectorAll('select');
	//console.error( input.length );
	if (!doItem(input) ) return false ;
	
	return re;
};

/**
 * 
 * @param {String} url
 */
HC.ajax= function( url){

    if(window.plus && plus.networkinfo.getCurrentType() === plus.networkinfo.CONNECTION_NONE) {
        plus.nativeUI.toast('似乎已断开与互联网的连接', {
            verticalAlign: 'top'
        });
        return;
    }

	var config ={
		data:{}
		,success:null
		,error:function (rep ) { error_default(rep) }
		,url:''
		,waitMsg:''
		,isLocal:true
		
	}
	if( arguments.length>1) mui.extend( config,arguments[1], false );
	console.log( "AJAX:"+ JSON.stringify( config ));
	if( config.data===false ) { console.error('Ajax data 为空！'); return false ;}
	
	
	var error_default=function (rep) {
		if(rep.error=='190999'){
            var btnArray = [ '提交验证'];
            mui.prompt('请打开身份验证器查看动态数字：', '6位数字', '谷歌验证码', btnArray, function(e) {
            	console.log(  e.value );
            	HC.ajax('/index/google/post',{
            		data:{code: e.value }
            		,success:function (rep) {
            			location.reload();
                    }
				});

            });

			return ;
		}
        HC.msg('错误:'+ rep.error_des +"("+ rep.error+")" );
    }
	
	
	
	var doAjax= function(){
		if( config.waitMsg!==null ) HC.showWaiting(config.waitMsg );
		console.log("提交"+ config.url  );
		mui.ajax( config.url  ,{
			data: config.data ,
			dataType:'json',//服务器返回json格式数据
			type:'post',//HTTP请求类型
			timeout:10000,//超时时间设置为10秒；
			headers:{ 'X-Display':'json' },	              
			success:function(rep){
				HC.closeWaiting();
				if( rep.redirect && rep.redirect.msg  ) HC.msg( rep.redirect.msg  );

				if( rep.error==0){
					if( typeof config.success =='function') config.success( rep.data );
					else{
                        if(rep.redirect && rep.redirect.url&& rep.redirect.url!=''){
                            location.href = rep.redirect.url ;
                        }
					}
				}else{
					config.error(rep );
				}


			},
			error:function(xhr,type,errorThrown){
				HC.closeWaiting();
				console.log(type);
				if(type=='timeout') HC.msg( "超时了...");
				 else if( type=='abort') HC.msg("连接失败,请检查你的网络");
				 else HC.msg( "发生错误！");
			}
		});
	}
	var withData= function(){
		
		config.data.MB_time = HC.timenow();
		config.data.MB_os =  mui.os;
		
		try{
			config.data.MB_version= plus.runtime.version;;
            var cinfo = plus.push.getClientInfo();
			config.data.MB_uuid= plus.device.uuid;
			config.data.push_cid= cinfo.clientid;
			config.data.push_token= cinfo.token;
			var top = plus.webview.getTopWebview();
			config.data.MB_wid= top.id ;
		}catch(e){}
		try{
			var obj =HC.getState();
			config.data.HC_uid=obj.user.user.user_id;
		}catch(e){}
		
	}
	var doUrl= function(){
		url=url.trim();
		if( url.substr(0,4).toLocaleLowerCase()=='http' ||  !config.isLocal ) {
			config.url  = url;
		}else{
			config.url  = HC.getHost()+ url;
		}	
		 
	}
	doUrl();
	withData();
	doAjax();
}

HC.getHost= function(){
	//alert('good');
	//console.log( location );
	return location.origin;
	return 'http://bobo.zahei.com/';
	return 'http://bobo.zahei.com/';
}
	
HC.showWaiting = function( str ){
	try{plus.nativeUI.showWaiting( str);}catch(e ){	}	
}
HC.closeWaiting = function(){
	try{		plus.nativeUI.closeWaiting(); }catch(e){}
}
HC.msg = function(str ){
    try{
        api.toast({  msg: msg, duration: 3000, location: 'bottom'});
	}catch (e) {
        try{

            mui.toast( str );
        }catch(e){alert(str);}
    }

}

HC.cache={
	set:function(key,obj){
		obj= obj ||{};	
		plus.storage.setItem( key , JSON.stringify(obj));	
	},
	get:function( key ){
		var str = plus.storage.getItem(key ) || "{}";
		return JSON.parse( str );
	},
	del:function( key ){
		try{
			plus.storage.removeItem( key );
			return true;
		}catch(e){
			return false;
		}
	}
}



HC.timenow = function(){
	var timestamp = Date.parse(new Date());
	timestamp = timestamp / 1000;
	return  timestamp;
}

HC.saveState= function( obj ){
	HC.cache.set('_stats', obj );
}
HC.getState= function(){
	return HC.cache.get( '_stats');
}

HC.login =function(data){
	console.log("login:"+ JSON.stringify( data )); //{"openid":"12@qq.com","psw":"258369"}
	 
	var config={
		success:function(){
			HCREG.toMain();
		}
		,url:'/app/login/post',
		waitMsg:'正在登录'
	}
	if( arguments.length>1) mui.extend(config,arguments[1] );
	
	HC.ajax(  config.url  ,{
		data:data,
		waitMsg:config.waitMsg
		,success:function(rep){	
			//HC.msg("登录成功");				
			HC.cache.set('_login', data );
			HC.saveState( rep );
			console.error( JSON.stringify(rep));
			config.success(rep);			
		}
	});	
	
}

/**
 * @description 刷新登录
 */
HC.reLogin = function(  ){
	var config={
		success:function(){}
	}
	if(arguments.length>0 ) mui.extend(config,arguments[0]);
	var data= HC.cache.get('_login');
	HC.login( data,{waitMsg:null,url:'/app/login/reload',success:function(rep){		 
		config.success(rep);
	}});
}

/**
 * 
 * @param {HTMLElement} dom
 */
HC.href=function( dom){	
	alert(dom.id);
}
/**
 * @description 获取刚刚打开上个窗口 必须在 plusReady 之后执行 ，得配合 HC.vue 带cache
 */
HC.getWinExtras = function(){
	var obj = HC.cache.get( '_winExtras');
	if( ! obj.id || plus.webview.currentWebview().id!= obj.id){
		console.error("错误：_winExtras ID不一致" );
		return {detail:{} } ;
	}
	return  obj;
}

/**
 * 格式化时间的辅助类，将一个时间转换成x小时前、y天前等
 */
HC.dateUtils = {
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

/**
 * 
 * @param {Object} list
 * @param {Object} userObj
 */
HC.mergeUser = function ( list,userObj ) {
	var config={
		key:'user_id'
	};
	for(var p in list ){
		var key= list[p][ config.key];
		if(!key || !userObj[ key]) continue; 
		try{
			if(! userObj[ key].head || userObj[ key].head=='')  userObj[ key].head='http://cdn.haoce.com/res/img/none_user.jpg';
		}catch(e){}
		
		list[p]['mgUser']= userObj[ key];
	}
}

/**
 *
 * @param obj
 * @param fromObj
 */
HC.marge= function ( obj,fromObj) {
	for(var p in fromObj)  obj[p]= fromObj[p];
}
/**
 * 时间显示
 * @param {Array} list
 * @param {String} key
 */
HC.timeAt = function (list, key ) {
	for(var p in list ){
		var time =  list[p][  key ];
        if(!time) continue;
        list[p]['timeAt']= HC.dateUtils.format_time( time );
	}
}

/**
 * @description 配合 cacheOpen
 */
HC.cacheBack = function () { 
	var config={		cb:function(){} ,before:null	}
	if(arguments.length>0 ) mui.extend( config,arguments[0] );
	
    //重写返回逻辑
    mui.back = function() {

    	if( typeof config.before =="function" ){
            config.before();
    		setTimeout( function () {
                plus.webview.currentWebview().hide("auto", 300);
            },100);
		}else{
            plus.webview.currentWebview().hide("auto", 300);
		}
    	//alert('good stop');

    }

    //窗口隐藏时，重置页面数据
    mui.plusReady(function () {
        var self = plus.webview.currentWebview();
        self.addEventListener("hide",function (e) {
            //window.scrollTo(0, 0);
            config.cb();
        },false);
    });
};

HC.realBack = function () {
    var oldBack= mui.back;
    mui.back = function() {
        if( mui.os.android ){
			/*
			 var hc_close = function ( id ) {
			 try{
			 plus.webview.getWebviewById( id).close('auto');
			 }catch (e){}
			 }
			 hc_close('bookOne');
			 hc_close('topicList');
			 hc_close('topic');
			 */
            var me =  plus.webview.currentWebview();
            me.close("auto", 300);
        }else{
            oldBack();
        }

    }
}


/**
 * @description 预加载原生配置 HC.cacheBack使用 
 * @example 
 * 	 var co= new HC.cacheOpen();<br> 
 * 	 co.init( 'abc.html','abc'); <br>
 	 co.open('我是标题','get_detail',objData);
 */
HC.cacheOpen =function(){
    //console.error( JSON.stringify(book));
    var webview_detail = null; //详情页webview
	var clickButton=function () {  //
        mui.fire( webview_detail,'topRight');
    }
    var titleNView = { //详情页原生导航配置
        backgroundColor: '#f7f7f7', //导航栏背景色
        titleText: '', //导航栏标题
        titleColor: '#000000', //文字颜色
        //type: 'transparent', //透明渐变样式
        autoBackButton: true, //自动绘制返回箭头
        splitLine: { //底部分割线
            color: '#cccccc'
        }

    }
     
    this.open = function (title,sub_event,data) {
        //触发子窗口变更新闻详情
        mui.fire( webview_detail,sub_event,  data);

        //更改详情页原生导航条信息
        titleNView.titleText =  title;
        webview_detail.setStyle({
            "titleNView": titleNView
        });
        setTimeout(function () {
            webview_detail.show("slide-in-right", 300);
        },150);
    }
    this.init =function (  url,id ) {
    	//var id='pro_'+ id;
    	
    	
		var config={
			right:false
		}
		if( arguments.length>2 ) mui.extend(config,arguments[2]);
		
    	var wb = plus.webview.getWebviewById( id );	
    	console.log('old id: '+ id );
		if( wb ){
			console.log('old wb: '+ wb.id );
			wb.setStyle( { "titleNView": titleNView} );
			webview_detail = wb;		
    		return ;
    	} 
    	
    	if( config.right ){
            //titleNView.buttons[0].text= config.right;
			//不支持动态添加
            titleNView.buttons=[{text: config.right,float:'right',onclick:clickButton ,color:'#007aff',fontSize:'16px'}];
		}
		
    	console.error( "init url2:"+ url );
    	
        webview_detail = mui.preload({
            url: url,// HC.getHost()+'app/page/bookOne',
            id:  id,//'bookOne',
            styles: {
                "render": "always",
                "popGesture": "hide",
                "bounce": "vertical",
                "bounceBackground": "#efeff4",
                "titleNView": titleNView
            }
        });
    }
}
/**
 * @description  统计自
 * @param str
 * @returns {number}
 */
HC.wordCount  = function ( str ) {

    sLen = 0;
    try{
        //先将回车换行符做特殊处理
        str = str.replace(/(\r\n+|\s+|　+)/g,"龘");
        //处理英文字符数字，连续字母、数字、英文符号视为一个单词
        str = str.replace(/[\x00-\xff]/g,"m");
        //合并字符m，连续字母、数字、英文符号视为一个单词
        str = str.replace(/m+/g,"*");
        //去掉回车换行符
        str = str.replace(/龘+/g,"");
        //返回字数
        sLen = str.length;
    }catch(e){
    }
    return sLen;
}

/**
 * @description nl转义<br>
 */
String.prototype.nl2br=function(){
    var breakTag =   '<br/>';
    return this.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1'+ breakTag +'$2');
}

/**
 * @description 下载 回去检查是否存在文件如果存在 就不下载了
 * @param {String} urlfile
 * @param {String} filename
 */
HC.download = function( urlfile,filename){
	var conf={
		success:function(entry){},
		error:function(e){}
	}
	if(arguments.length>2){ 		mui.extend(conf,arguments[2]);	}
	var filename2 = "_downloads/"+filename;
    console.log("开始下载："+ filename2 +" from:"+ urlfile);
	plus.io.resolveLocalFileSystemURL( filename2 ,conf.success
		,function(){
			var dtask = plus.downloader.createDownload(  urlfile, {'filename': filename2}, function ( d, status ) {
				if ( status == 200 ) {
					conf.success( d );
                    console.error("下载成功2："+ d.filename );
				}else{
					conf.error( d, status);
                    console.error("下载发错错误：" +status );
				}
		 	});
			dtask.start();	
		});			
}

/**
 * @description	播放http声音，会下载
 * @param {URL} urlfile
 * 
 */
HC.player=function(urlfile ){
	this.file = urlfile;
	var conf={
		type:'mp3',
		start:function( p){   },
		error:function(){},				
		p:null,
		end:function(){},
		savename:null,
		getPos:function( pos ){ console.log("pos:"+ pos ) ;} //pos -1 结束 -2错误了 -3强制停止
		,_timer:null
		,is_stop: false //是否人工强制停止
	}
	if(arguments.length>1 ){		mui.extend( conf,arguments[1] );	}
	
	var httpplay= function(){		
		savename= conf.savename!=null? conf.savename: urlfile.split('/').pop();
		if(savename.indexOf('.')<0){
            savename+='.mp3';
		}
		conf.getPos(-5); //下载中
		HC.download(urlfile,savename,{
			error:function(){ _pend();			conf.getPos(-4); },
			success:function(){
				var filename = "_downloads/"+savename;
                //var filename = "_doc/"+savename;
				console.error( "play2: "+ filename );
				localplay( filename );
			}
		} ); 
	}
	var localplay= function( filename ){
		var that = this;
		if( conf.is_stop ) {
			console.log("强制停止");
			return ;
		}
		var p = plus.audio.createPlayer(  filename );
		var _end=function(){
			_pend();
			conf.getPos(-1);
			conf.end();
		}
		var _error=function(){
			_pend();
			conf.getPos(-2);
			conf.error();
		}
		p.play(  _end,  _error);

        that.p = conf.p= p;
		conf.start( p ); 		
		conf._timer = setInterval(  _checkPos, 500 );

		 
	}
	var _checkPos=function(){
		conf.getPos( conf.p.getPosition());
	}
	
	var _pend= function(){
		try{
			clearInterval( conf._timer);
		}catch(e){}
		conf._timer = null;
	}
	
	
	this.start = function(){
		if(urlfile.indexOf('http')>=0){
			httpplay();
		}else{
			localplay( urlfile );
		}
	}
	this.stop=function(){	
		conf.is_stop = true ; //强制停止
		_pend();
		if( conf.p) conf.p.stop();
		conf.getPos(-3);
	}
	this.getPlay =function () {
		return conf.p ;
    }
	this.format= function(pos){
		if(pos==-1) return '完成';
		if(pos==-2) return '错误';
		if(pos==-3) return '停止';	
		if(pos==-4) return '下载失败';	
		if(pos==-5) return '下载';
		var conf2={   is:''  }
		if( arguments.length>1 ) mui.extend(conf2, arguments[1] );
		var _format = function(number) {
            return(number < 10 ? ('0' + number) : number);
        };
        pos=pos+0.5;
        var m= parseInt( pos/60 );
        var s= parseInt( pos%60 );
        if(m>0) return _format(m)+':'+ _format(s);
        if( conf2.is=='all' )  return  '00:'+ _format(s);
        return _format(s)+'s';
	}
}

/**
 * @description 载入
 * @param {WebviewIdString} id
 */
HC.reload=function(id){
	 
	var wbview = plus.webview.getWebviewById(id);
	if( wbview)  mui.fire( wbview, 'dr_reload' );
}

 
 
/**
 * @description 录音 样例参考 book_topic_from  将hold release绑定在按钮上
 * @param {Object} obj 一般为vue存在的内容 里面  obj.tips 提示信息  ，obj.is_doing 控制显示 true正在录音 false结束
 * @param {Function} onSuccess
 */
HC.recorder= function(obj,onSuccess){
	var conf={
		recordCancel:false,
		recorder:null,
		timeLimit:1.5 //单位秒
		,format:'amr'
		,holdBefore:function(){ return true ; }
	}
	if(arguments.length>2) mui.extend( conf,arguments[2]);
	/**
	 * @description 按住
	 */
	this.hold=function( ){
		if(! conf.holdBefore()) return ;
		obj.tips="录音中,上划可取消";
		conf.recordCancel= false;
		
		var wb= plus.webview.currentWebview();
		console.log(wb.id );
		wb.setStyle({bounce:'none'});	
		conf.recorder= plus.audio.getRecorder();
		if (conf.recorder == null) {
			HC.msg("请设置允许好策APP录音！");
			return;
		}
		
		obj.is_doing= true;
		startTimestamp = (new Date()).getTime();
		conf.recorder.record({
			filename: "_doc/audio/",
			format: conf.format 
		}, function(path) {			 
			if( conf.recordCancel== true){
				return false;
			}
			onSuccess( {path:path,time:parseInt(0.5+(stopTimestamp - startTimestamp)/1000) ,ext:conf.format  });
		}, function(e) {
			HC.msg("录音时出现异常: " + e.message);
		});
	}
	/**
	 * @description 离开手
	 */
	this.release=function(){
		if(! conf.recorder ) return ;
		obj.is_doing= false;	
		stopTimestamp = (new Date()).getTime();
		if( (stopTimestamp - startTimestamp) < 1000*conf.timeLimit){
			conf.recordCancel= true;
			HC.msg('时间太短了,保证在' +conf.timeLimit +'秒以上' );
		}
		conf.recorder.stop();
	}
	/**
	 * @description 一般绑定在body的darp上
	 */
	this.drapEnd=function(event){
		//console.log("detail:"+Math.abs(event.detail.deltaY) );
		if(Math.abs(event.detail.deltaY)>30){
			obj.tips="松开可取消";
			conf.recordCancel= true;			 
		}else{
			obj.tips="录音中,上划可取消";
			conf.recordCancel= false;
		}
		
	}
}

HC.html=function ( str ) {
	//if( str.indexOf('<')>=0 && str.indexOf('>')>0 )  return str ;
	var s_patt=/<[a-zA-ZX]/g; var e_patt=/[a-zA-ZX]>/g;
	if( s_patt.test( str) && e_patt.test(str) )  return str ;
	return '<p>'+  str.replace(/\n/g,'</p><p>')+'</p>';
}
/**
 *
 * @param value
 * @param Arr
 * @returns {boolean}
 */
HC.inArray= function( value,Arr ){
	for( var p in Arr ) if( value==Arr[p]) return true;
	return false;
}
/**
 *
 * @param Arr
 * @returns {Array}
 */
HC.arrayKeys= function ( Arr ) {
	var re=[];
    for( var p in Arr ) re.push( p);
    return re;
}

/**
 * @description 上传
 * @param {URL} url
 * @param {File} file
 */
HC.upload =function(url,file){
	var config ={
		blocksize:2 //单位M
		,url:''
		,success:null//function(rep){}
		,error:null
		,data:{}
	}
	if( arguments.length>2) mui.extend( config,arguments[2]);
	var doUpload=function(){
		doUrl();
		var task = plus.uploader.createUpload( config.url ,
			{ method:"POST",blocksize: config.blocksize *1024000  ,priority:100 },
			function ( t, status ) {
                HC.closeWaiting();
				// 上传完成
				if ( status == 200 ) { 	
					console.error( t.responseText );	
					
					var rep= JSON.parse( t.responseText );
					if( ! rep ) {
						HC.msg("上传成功，非JSON数据");
						return false ;
					}
					if( rep.redirect && rep.redirect.msg  ) HC.msg( rep.redirect.msg  );
					if( rep.error==0){						
						if( typeof config.success =='undefined') {
							HC.msg("上传成功！");
							return ;
						}else config.success( rep ) ;
					}else{
						HC.msg('错误:'+ rep.error_des +"("+ rep.error+")" );
					} 
				} else {
					if(typeof config.error != 'function' ) HC.msg('上传发送错误！');
					else config.error(t,status);
                    console.error("error:"+ status );
                    console.error( JSON.stringify(t) );
				}
			},function () {
                HC.msg('上传发送错误500！');
                HC.closeWaiting();
            }
		); 
		task.addFile( file, {key:"file"} );
		withData();
		taskAddData( task );
		task.setRequestHeader( 'X-Display','json');			 
		task.start();
		
	}
	var withData= function(){		
		config.data.MB_time = HC.timenow(); 
		config.data.MB_version= HC.version; 
		config.data.MB_os =  mui.os;
		try{
			var cinfo = plus.push.getClientInfo();
			config.data.MB_uuid= plus.device.uuid;
			config.data.push_cid= cinfo.clientid;
			config.data.push_token= cinfo.token;
			var top = plus.webview.getTopWebview();
			config.data.MB_wid= top.id ;
		}catch(e){}
	}
	var taskAddData= function( task  ){
		for(p in config.data  ){
			var str = config.data[p];
			var type= typeof str;
			if( type =='function' || type=='undefined') continue ;
			if( type == "object") 	str = JSON.stringify( str );
			if( type == "number") 	str +='';
			task.addData( p ,  str );
		}
	}
	
	var doUrl= function(){
		url=url.trim();
		if( url.substr(0,4).toLocaleLowerCase()=='http') {
			config.url  = url;
		}else{
			config.url  = HC.getHost()+ url;
		}
	}
	
	doUpload();
}

/**
 * 比较版本大小，如果新版本nv大于旧版本ov则返回true，否则返回false
 * @param ov 老版本
 * @param nv 新版本
 * @returns {boolean}
 */
HC.compareVersion = function(ov, nv) {
    if (!ov || !nv || ov == "" || nv == "")      return false;
	var ova = ov.split(".", 4);
	var nva = nv.split(".", 4);
    for (var i = 0; i < ova.length && i < nva.length; i++) {
        var no = parseInt( ova[i]),    nn = parseInt( nva[i]);
        if (nn > no )   return true;
        if (nn < no)    return false;
    }
    return false;
}
/**
 * 文字转语音播放
 */
HC.speak =function () {
    this.config={
        iso:{sppech:null}
        ,android:{isDo: false}
    }
    this.start=function (str ) {
        if( mui.os.ios )speack_ios( str );
        else  speek_android( str );
    }
    var that = this;
    this.stop=function () {
        if( mui.os.ios ) {
            if (that.config.iso.sppech) {
                that.config.iso.sppech.stopSpeakingAtBoundary(0);
            }
        }else if(  that.config.android.isDo ){
            speek_android( '关闭朗读' );
            that.config.android.isDo= false;
        }
    }
    //mui.os.ios
    speack_ios=function ( str  ) {
        if( that.config.iso.sppech==null){
            var AVSpeechSynthesizer = plus.ios.importClass("AVSpeechSynthesizer");
            var sppech =that.config.iso.sppech = new AVSpeechSynthesizer();
        }else{
            that.config.iso.sppech.stopSpeakingAtBoundary(0);
            var sppech = that.config.iso.sppech ;
        }
        var AVSpeechUtterance = plus.ios.importClass("AVSpeechUtterance");
        var AVSpeechSynthesisVoice = plus.ios.import("AVSpeechSynthesisVoice");
        var voice = AVSpeechSynthesisVoice.voiceWithLanguage("zh-CN");
        var utterance =  AVSpeechUtterance.speechUtteranceWithString( str );
        //var voice = AVSpeechSynthesisVoice.voiceWithLanguage("en-GB");

        // var delegate = plus.ios.implements("AVSpeechSynthesizerDelegate",{"speechSynthesizer:didFinishSpeechUtterance:":function(){ console.log(arguments.length ); }});
        //// sppech.plusSetAttribute('delegate', delegate);
        //sppech.setDelegate( delegate );//
        //utterance.plusSetAttribute("rate",-0.0001);
        utterance.setVoice(voice);
        sppech.speakUtterance(utterance);
    }
    speek_android =function (str) {
    	HC.msg("安卓版本在待完善中");
    	return false;
        that.config.android.isDo= true;
        var main = plus.android.runtimeMainActivity();
        var SpeechUtility = plus.android.importClass('com.iflytek.cloud.SpeechUtility');
        SpeechUtility.createUtility(main,"appid=53feacdd");
        var SynthesizerPlayer = plus.android.importClass('com.iflytek.cloud.SpeechSynthesizer');
        var play = SynthesizerPlayer.createSynthesizer(main, null);
        play.startSpeaking( str ,null);

    }
}

HC.timeShow =function ( time ) {
	var h= parseInt(time/3600);
	var m= parseInt(time%3600/60);
	var s= parseInt(time%60);
	var cf={
        show:'2'
	}
	if( arguments.length>1) HC.extend( cf,arguments[1]);
	if( cf.show==1){
		if( h>0 )  return h+'<i>小时</i>';
		if( m>0 )  return m+'<i>分钟</i>';
		if( s>0 )  return s+'<i>秒</i>';
		return '0';
	}
	if( h>0) return (h>0?h+'时':'')+(m>0?m+'分':'' );
	return (h>0?h+'时':'')+(m>0?m+'分':'' )+(s>0?s+'秒':'' )
}
HC.extend= function ( $srcObj,$fromObj) {
	for( var p in  $fromObj ) if(typeof $srcObj[p] != 'undefined') $srcObj[p]= $fromObj[p];
}
HC.intShow = function ( i ) {
	if(i>50000) return parseInt(i/10000)+'万';
	if(i>10000)return parseInt(i/1000)+'千';
	return i;
}
HC.good=function (type,id, obj ) {
    HC.ajax( 'book/good/'+  type+'/'+id   ,{'success':function (rep ) {
        console.log('good',rep );
        obj.good_cnt= parseInt( rep.good.good_cnt ) ;
    }});
}

HC.clip={
	setText:function (text ) {
		//console.log(  mui.os.android+': god ');
        if( plus.os.name == "Android"){
            var Context = plus.android.importClass("android.content.Context");
            var main = plus.android.runtimeMainActivity();
            var clip = main.getSystemService(Context.CLIPBOARD_SERVICE);
            //var str =  plus.android.invoke(clip,"getText");
            plus.android.invoke(clip, "setText", text);
            //console.error( str );
        }else if( plus.os.name == "iOS" ){
            var UIPasteboard  = plus.ios.importClass("UIPasteboard");
            var generalPasteboard = UIPasteboard.generalPasteboard();
            try{
                generalPasteboard.setValueforPasteboardType(text, "public.utf8-plain-text");
            }catch(e){
                generalPasteboard.plusCallMethod({setValue:text, forPasteboardType:"public.utf8-plain-text"});
            }

        }
        return this;
    },
	getText:function () {
		if( mui.os.android ){
            var Context = plus.android.importClass("android.content.Context");
            var main = plus.android.runtimeMainActivity();
            var clip = main.getSystemService(Context.CLIPBOARD_SERVICE);
            return plus.android.invoke(clip,"getText");
        }else if( mui.os.ios){
            var UIPasteboard = plus.ios.importClass("UIPasteboard");
            var generalPasteboard = UIPasteboard.generalPasteboard();
            // 设置/获取文本内容:
            //generalPasteboard.setValueforPasteboardType("testValue", "public.utf8-plain-text");
            //var _val = generalPasteboard.valueForPasteboardType("public.utf8-plain-text");
            //TODO 应用在后台的时候获取剪切版数据被系统限制了，只有在app内才能访问接口
            var _val=generalPasteboard.plusCallMethod({valueForPasteboardType:"public.utf8-plain-text"});
            console.log("ios复制返回的数据是：",_val);
            return _val || '';
		}
		return '';
    }
}


HC.scroll=function (id ) {
    var cf={
        onScroll:function (rep) {
        }
        ,upEnd:function (rep) {
            console.warn('upEnd');
            obj.initRep();
        }
        ,downEnd:function (rep) {
            obj.initRep();
            console.warn('downEnd');
        }
        ,dt:60
    }
    if( arguments.length>1) HC.extend( cf,arguments[1] );
    var rep  , obj = mui('#'+id).scroll({indicators: true    });
    document.getElementById( id ).addEventListener('scroll', function(e) {
        rep.y=  e.detail.y;
        cf.onScroll( rep );
        if(  e.detail.y===0 ||  e.detail.y===  e.detail.maxScrollY ){
            if( Math.abs( rep.dt )> cf.dt ){
                if(  rep.style=='down' ) cf.downEnd(rep)
                if(  rep.style=='up') cf.upEnd(rep)
            }
            return ;
        }
        if(  e.detail.y>0 ){
            rep.dt= e.detail.y;
            rep.style='down';
        }else if( e.detail.y< e.detail.maxScrollY){
            rep.dt= e.detail.y- e.detail.maxScrollY;
            rep.style='up';
        }else{
            rep.dt= 0;
            rep.style='';
        }
        rep.is_dt= Math.abs( rep.dt)>cf.dt ;
        //console.log('scroll'  , e.detail.y ,rep, e.detail.lastY, e.detail);
    });
    obj.initRep=function () {
        rep={ dt:0,y:0,style:'' ,is_dt:false}
        cf.onScroll( rep );
    }

    obj.initRep();
    return obj;
}

HC.immersed = function () {
    var ms=(/Html5Plus\/.+\s\(.*(Immersed\/(\d+\.?\d*).*)\)/gi).exec(navigator.userAgent);
    return (ms&&ms.length>=3)?parseFloat(ms[2]): 0;
}


HC.ajaxGet =function (url,onSuccess) {
    var config ={
        //data:{}
        //,success:function(data){}
        error:function (rep ) { HC.msg('错误:'+ rep.error_des +"("+ rep.error+")" ); }
        //,url:''
        ,waitMsg:''
        ,isLocal:true

    }
    if( arguments.length>2 ) mui.extend(config, arguments[2] );
    //HC.msg('....God2');
    mui.ajax(url ,{
        //data: config.data ,
        //dataType:'json',//服务器返回json格式数据
        type:'get',//HTTP请求类型
        timeout:100000,//超时时间设置为100秒；
        //headers:{ 'X-Display':'json' },
        //headers:{'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'},
        success: onSuccess,
        error:function(xhr,type,errorThrown){
            HC.closeWaiting();
            console.log(type);
            if(type=='timeout') HC.msg( "超时了...");
            else if( type=='abort') HC.msg("连接失败,请检查你的网络");
            else HC.msg( "发生错误！");
        }
    });
    //mui.getJSON( )
}
HC.cut=function (str, s_str, e_str) {
    var s_pos = str.indexOf(s_str);
    if( s_pos<0 ) return false;
    s_pos+=s_str.length;
    var e_pos = str.indexOf(e_str,s_pos );
    if( e_pos<0 ) return false;
    return str.substr(s_pos, e_pos-s_pos);

}

HC.openWx=function(){
    if ( plus.os.name == "Android" ) {
    	console.log('good news');
        plus.runtime.launchApplication( {pname:"com.tencent.mm"},function () {
        } );
    }
    if ( plus.os.name == "iOS" ) {
        plus.runtime.launchApplication({action: "weixin://RnUbAwvEilb1rU9g9yBU"}, function () {
        });
    }

}

/**
 * var hc_v= new HC.video('video');
 * hc_v.local_play_init();
 * hc_v.play('****mp3');
 * @param div_id
 */
HC.video=function (div_id) {
    var video=null;
    var config={
    	dt:0
	}
	if( arguments.length>1 ) mui.extend(config, arguments[1] );
    var time=0;
    this.local_play_init=function () {
        video = new plus.video.VideoPlayer( div_id,{url:'rtmp://live.hkstv.hk.lxdns.com/live/hks'});
        // 监听开始播放事件
        video.addEventListener('play', function(e){

        	HC.closeWaiting();
        	time=0;
        	//HC.msg("客官慢用");
            //plus.nativeUI.alert('Video play');
        }, false)
        // 监听播放进度更新事件
        video.addEventListener('timeupdate', function(e){
            //console.log(JSON.stringify(e));
            try{
                time= e.event.detail.currentTime ;
			}catch (e) {
                time= e.detail.currentTime   ;
            }

        }, false);
        // 监听播放结束事件
        video.addEventListener('ended', function(e){
            //plus.nativeUI.alert('Video ended');
            video.exitFullScreen();
            //console.error(JSON.stringify(e));
        }, false);
        video.addEventListener('fullscreenchange', function(e){
            console.error(JSON.stringify(e));
            try{
                if( !e.event.detail.fullScreen)  endPlay();
			}catch (e2) {
                if( !e.detail.fullScreen) endPlay();
            }


        }, false);
        video.addEventListener('error', function(e){
            HC.closeWaiting();
            console.error(JSON.stringify(e));
            //video.stop();
            video.exitFullScreen();
            HC.msg('网络不够流畅，请重试');
        }, false);

    };
    function checkPlay() {
    	//return false;
    	var obj = HC.cache.get('v_play123');
        var nd = new Date();
    	var today = HC.dateUtils.date_format(nd.getTime(),"MMdd");
    	if( typeof obj.count == "undefined" || today!=obj.dStr  ){
			obj =  {count:1, dStr:  today};
            //console.log( JSON.stringify(obj ));
            HC.cache.set('v_play123', obj  );
    		return true ;
		}
        obj.count++;
        HC.cache.set('v_play123', obj  );
        console.log( JSON.stringify(obj ));
        if( obj.count>3 && typeof obj.is_play=='undefined'){
        	return false;
		}
    	return true;
    }
    function endPlay() {
        video.stop();
        HC.tj('player', time, {'div_id':div_id });
    }
    function openPlay(url) {
        HC.showWaiting("客官不急,正在载入");
        console.log( 'url:'+ url );
        video.setOptions({src:url});
        video.seek(0);
        video.play( );
        setTimeout( function () {
            console.log('requestFullScreen');
            video.requestFullScreen( config.dt  );
        },2000)

        console.log( 'url2:'+ url );

    }
    this.play=function(url){
        if( !checkPlay()) {
        	//HC.msg('');

            var btnArray = ['取消', '复制并打开微信粘贴至群'];
            var str='发现一个APP非常不错，“老铁TV”APP，老司机带你上车！下载地址: http://readface.cn，你懂的，你懂的！';
            mui.confirm("请复制下面这段话分享至微信群\n\n"+str, '独乐乐，不如众乐乐', btnArray, function(e) {
                if (e.index == 1) {
                    HC.clip.setText( str );
                    setTimeout( HC.openWx, 300);
                    var obj= HC.cache.get('v_play123');
                    obj.is_play= true;
                    HC.cache.set('v_play123', obj );
                } else {

                }
            })

        	return false;
		}
        openPlay(url);

    }
    this.getVideo= function () {
        return video;
    }

}

HC.tj=function (p, obj) {
	if( typeof plus =='undefined') return ;
	if( arguments.length>2){
		if( typeof  obj!='number' ||   typeof  arguments[2]!='object'){
			console.log('统计累积时间，第二参数为为int，第三参数为json');
			return ;
		}
        plus.statistic.eventDuration( p, obj,arguments[2] );
	}else {
        if(  typeof  obj!='object'){
            console.log('统计第二参数为json');
            return ;
        }
        plus.statistic.eventTrig( p, obj );
	}
	console.log( p+' : '+ JSON.stringify( obj));
	return ;

}

HC.get_suffix=function (filename) {
    pos = filename.lastIndexOf('.')
    suffix = ''
    if (pos != -1) {
        suffix = filename.substring(pos)
    }
    return suffix;
}
HC.random_string= function (len) {
    len = len || 32;
    var chars = 'abcdefhijkmnprstwxyz2345678';
    var maxPos = chars.length;
    var pwd = '';
    for (var i = 0; i < len; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * maxPos));
    }
    return pwd;
}
HC.alioss='https://cdn.nekoraw.com/';
HC.pluploadToOSS= function( bt_id ){
    if( typeof  plupload == 'undefined' ){
        //DR.tip( "请先加载 plupload js",{style:'error'});
		HC.msg('请先加载 plupload js');
        return false ;
    }
    var opt ={
        url:'/test/oos/start2',
		getUrl:'/test/oos/start2',
        query:'abc=123',
        ext:'', //默认 jpg,gif,png,zip,rar
        max_file_size:'10mb',
        cb:function () {}
        ,before:function () {  return true ;  }
    }
    if(arguments.length>1 ) mui.extend( opt ,arguments[1]); // mui.extend(config, arguments[1] );
    var url2 = opt.url+ ( opt.url.indexOf('?')>0?'&':'?')+opt.query;
    var ext = opt.ext=='' ?[
        {title : "Image files", extensions : "jpg,gif,png,jpeg"}
    ]: [{title : "文件类型", extensions : opt.ext}];

    var new_multipart_params={};
    var uploader = new plupload.Uploader({
        runtimes : 'html5,flash,silverlight,html4',
        browse_button :  bt_id , // you can pass an id...
        url :url2  ,
        flash_swf_url : '/res/js/plupload/Moxie.swf',
        silverlight_xap_url : 'res/js/plupload/Moxie.xap',
        headers:{'X-Display':'json' },
        filters : {
            max_file_size : opt.max_file_size,
            mime_types:ext
        },

        init: {
            PostInit:function () {
                //upconfig.d = new   dialog( );
            },
            FilesAdded: function(up, files) {
                //alert('开始上传');
                if(! opt.before()) return false ;
                //HC.msg('开始上传');
                //upconfig.d.content('开始上传'   ).show();
                //uploader.start();
                HC.ajax( opt.getUrl ,{success:function (rep) {

						//console.log( rep.dp  );
						var dp= rep.dp;
                        new_multipart_params.key= dp.dir + HC.random_string(8)+  HC.get_suffix( files[0].name );
                        new_multipart_params.policy= dp.policy;
                        new_multipart_params.OSSAccessKeyId= dp.accessid;
                        new_multipart_params.success_action_status='200';
                        new_multipart_params.callback= dp.callback;
                        new_multipart_params.signature= dp.signature;
                        //console.log( new_multipart_params );

                        uploader.setOption({
                            'url': rep.dp.host,
                            'multipart_params': new_multipart_params
                        });
                        uploader.start();
                    }
                });
            },

            UploadProgress: function(up, file) {
                HC.msg('正在上传... '+file.percent + '%' );
            },

            Error: function(up, err) {
                var str ='上传发生错误！('+ err.code+')';
                if( err.code==-601) {
                    //str='请注意文件格式！('+ err.code+')';
                    //if(  opt.ext  ) str ='仅支持格式：'+ opt.ext   ;
					str ='仅支持图片 jpg,gif,png,jpeg';
                }
                HC.msg('错误:'+ str );
            },
            FileUploaded:function (up, file, res) {
				//console.log('FileUploaded', res );

                if(  res.status==200 ){
                    HC.msg('上传成功' );
                    opt.cb(  { res:res.response, file: new_multipart_params.key  }  );
                }else{
                    HC.msg('上传失败' );
                }


            }
        }
    });
    uploader.init();

}

var DR= DR ||{};





