/**
 * Created by Administrator on 2017/5/14 0014.
 */
String.prototype.trim=function()
{
    if( typeof arguments[0]=='string'){
        var reg = new RegExp( "(^"+arguments[0] +"*)|("+arguments[0]+"*$)",'g');
    }else{
        var reg = new RegExp( "(^\\s*)|(\\s*$)",'g');
    }
    return this.replace(  reg  ,'');
}

Date.prototype.Format = function (fmt) {
    var o = {
        "M+": this.getMonth() + 1, //月份
        "d+": this.getDate(), //日
        "h+": this.getHours(), //小时
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


var DR= DR ||{};
DR.WWW_ROOT= '/';
DR.timeFormat =function ( intTime,fmt ) {
    var d = new Date();
    d.setTime(intTime*1000 );
    return d.Format( fmt );
}
DR.ajax=function ( url) {
    var wconf={
        success:null,
        close_success:null,
        close_error:null
    }
    if( arguments.length>2 )  $.extend( wconf, arguments[2]);
    var opt={
        type:'POST',
        dataType:'json',
        data:{ts:1},
        headers:{'X-Display':'json' },
        loadingstr:'正在提交...',
        success:function (rep) {
            if( typeof dshowwait !="undefined")  dshowwait.close();
            if(typeof  wconf.close_success =="function"){
                wconf.close_success( rep );
                return;
            }

            if(rep.error!=0){
                this.drError(rep );
            }else if(typeof  wconf.success =="function"){
                wconf.success( rep );
                return ;
            }else{
                DR.tipFromRep( rep );
            }
        },
        drError:function ( rep) {
            DR.tip( rep.error_des+'('+ rep.error +')',{'style':'error'} ); //
        },
        error:function () {
            if(typeof  wconf.close_error =="function"){
                wconf.close_error( rep );
                dshowwait.close();
                return;
            }
            DR.tip('网络开小差了？！',{'style':'error'} ); //
            if( typeof dshowwait !="undefined") dshowwait.close();
        }
    }

    //if( url!='/')
    if( arguments.length>1 )  $.extend( opt, arguments[1]);

     url = url.trim().trim('/')
    opt.url =  '/'+ url ;
     if(opt.loadingstr ) {
         try {
             var dshowwait = window.parent.DR.tip(opt.loadingstr, {'type': 'showModal', time: 0, style: 'loading'});
         } catch (e) {
             var dshowwait = DR.tip(opt.loadingstr, {'type': 'showModal', time: 0, style: 'loading'}); //,style:'info'
         }
     }
    $.ajax( opt );
}

DR.showWait =function ( msg ) {
    var dshowwait = DR.tip( msg , {'type': 'showModal', time: 0, style: 'loading'}); //,style:'info'
    return dshowwait;
}

DR.tipFromRep = function ( rep ) {
    if( typeof rep.redirect == 'undefined'){
        DR.tip2("执行成功，但缺少提示"  , {style: 'success'});
        return ;
    }

    var msg = rep.redirect.msg==''?'操作成功':  rep.redirect.msg;
    var url =  rep.redirect.url;
    if(url == ''){
        DR.tip2(msg, {style: 'success'});
    }else {
        DR.tip(msg, {style: 'success', info: '<a  style="color: #999;display: block" href="'+url+'" target="_top">3秒后自动跳转</a>'});
        setTimeout( function () {
            try {
                if(typeof _iframe_now == 'undefined' )          window.parent.location.href = url;
                else  location.href = url;
            } catch (e) {
                location.href = url;
            }
        }, typeof rep.redirect.timeout=='undefined' ?2500:rep.redirect.timeout  );
    }
}
/**
 * tips
 * @param msg
 * @returns {*}
 */
DR.tip=function ( msg ) {
    var opt={
        style:'error',//tips
        cb:function () {      },
        time:2900,
        type:'', //showModal
        info:''
    }

    if( arguments.length>1 )  $.extend( opt, arguments[1]);

    var loding = opt.style!='loading'?'': '<div class="sui-loading loading-xxsmall loading-inline"><i class="sui-icon icon-pc-loading"></i></div>';
    var html='<div class="sui-msg msg-large msg-'+opt.style+' msg-naked"><div class="msg-con">'+msg+'</div><s class="msg-icon">'+loding+'</s></div>';
    html+= opt.info;
    var d = dialog({
        //title: '错误',
        content: '<div style="text-align: center;padding: 10px 15px;"  >'+html+'</div>' ,
        //width:200
    });
    if( opt.type =='showModal' ) d.showModal();
    else  d.show();
    if( opt.time>0 ) {
        setTimeout(function () {
            opt.cb();
            d.close().remove();
        }, opt.time);
    }
    return d;
};
DR.tipSuccess= function ( msg ) {
    var opt={style:'success' };
    DR.tip( msg, opt );
}
DR.tip2 = function( msg ){
    var opt={
        style:'error',//tips
        cb:function () {      },
        time:2900,
        type:'', //showModal
        info:''
    }
    if( arguments.length>1 )  $.extend( opt, arguments[1]);
    var d= document.createElement('div');
    d.className="dr-tip2";
    d.innerHTML= '<div class="msg-'+opt.style+' sui-msg  msg-large"><div class="msg-con">'+msg+'</div><s class="msg-icon"></s></div>';
    document.body.appendChild(d);
    $(d).slideDown(500);
    setTimeout( function () { $(d).slideUp(300,function () { $(this).remove() }) }, opt.time);

}

DR.get_suffix=function (filename) {
    pos = filename.lastIndexOf('.')
    suffix = ''
    if (pos != -1) {
        suffix = filename.substring(pos)
    }
    return suffix;
}
DR.random_string= function (len) {
    len = len || 32;
    var chars = 'abcdefhijkmnprstwxyz2345678';
    var maxPos = chars.length;
    var pwd = '';
    for (var i = 0; i < len; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * maxPos));
    }
    return pwd;
}
DR.alioss='https://cdn.nekoraw.com/';

DR.uploadone =function ( bt_id ) {
    if( typeof  plupload == 'undefined' ){
        DR.tip( "请先加载 plupload js",{style:'error'});
        return false ;
    }
    var opt ={
        url:'/test/oos/start2',
        getUrl:'/test/oos/start3',
        query:'abc=123',
        ext:'', //默认 jpg,gif,png,zip,rar
        max_file_size:'10mb',
        cb:function () {}
        ,before:function () {  return true ;  }
    }
    if(arguments.length>1 ) $.extend( opt ,arguments[1]);
    var url = opt.url+ ( opt.url.indexOf('?')>0?'&':'?')+opt.query;


    var ext = opt.ext=='' ?[
        {title : "Image files", extensions : "jpg,gif,png"},
        {title : "Zip files", extensions : "jpg,png,gif,zip,rar,doc,xls,ppt,docx,xlsx,pptx,pdf,txt"}
    ]: [{title : "文件类型", extensions : opt.ext}];

    var upconfig = {d:null } ;

    var new_multipart_params={};
    var uploader = new plupload.Uploader({
        runtimes : 'html5,flash,silverlight,html4',
        browse_button :  bt_id , // you can pass an id...
        //container: document.getElementById( 'uploadcontent' ), // ... or DOM Element itself
        url :url  ,
        flash_swf_url : '/res/js/plupload/Moxie.swf',
        silverlight_xap_url : 'res/js/plupload/Moxie.xap',
        headers:{'X-Display':'json' },
        filters : {
            max_file_size : opt.max_file_size,
            mime_types:ext
        },

        init: {
            PostInit:function () {
                upconfig.d = new   dialog( );
            },
            FilesAdded: function(up, files) {
                //alert('开始上传');
                //if(! opt.before()) return false ;

                DR.ajax( opt.getUrl,{} ,{success:function (rep) {


                        var dp= rep.data.dp;
                        //console.log( dp  );
                        new_multipart_params.key= dp.dir + DR.random_string(8)+  DR.get_suffix( files[0].name );
                        new_multipart_params.policy= dp.policy;
                        new_multipart_params.OSSAccessKeyId= dp.accessid;
                        new_multipart_params.success_action_status='200';
                        new_multipart_params.callback= dp.callback;
                        new_multipart_params.signature= dp.signature;
                        //console.log( new_multipart_params );

                        uploader.setOption({
                            'url': dp.host,
                            'multipart_params': new_multipart_params
                        });
                        uploader.start();
                    }
                });

                //upconfig.d.content('开始上传'   ).show();
                //uploader.start();
            },

            UploadProgress: function(up, file) {

                var loding =  '<div class="sui-loading loading-xxsmall loading-inline"><i class="sui-icon icon-pc-loading"></i></div>';
                var html='<div class="sui-msg msg-large msg-loading msg-naked"><div class="msg-con">正在上传... <span>' + file.percent + '%</span></div><s class="msg-icon">'+loding+'</s></div>';
                upconfig.d.content( html );
            },

            Error: function(up, err) {
                if(upconfig.d!= null  ) upconfig.d.close();
                var str ='上传发生错误！('+ err.code+')';
                var str_error='';
                if( err.code==-601) {
                    str='错误：请注意文件格式！('+ err.code+')';
                    if(  opt.ext  ) str ='仅支持格式：'+ opt.ext   ;

                }
                DR.tip( str ,{style:'error',info:'<div  style="color: #999">错误：'+err.message+str_error+'</div>' });
                //document.getElementById('console').appendChild(document.createTextNode("\nError #" + err.code + ": " + err.message));
            },
            FileUploaded:function (up, file, res) {
                if(upconfig.d!= null  ) upconfig.d.close();

                DR.log( res  );
                /*
                eval('var rep = '+res.response );
                if(  rep.error==0 ){
                    DR.tip('上传成功',{style:'success'});
                    opt.cb( rep.data  );
                }else{
                    DR.tip(  rep.error_des ,{style:'error'});
                }
                */
                if(  res.status==200 ){
                    DR.tip('上传成功',{style:'success'});
                    opt.cb(  { res:res.response, file: new_multipart_params.key  }  );
                }else{
                    DR.tip(  '上传失败' ,{style:'error'});
                }


            }
        }
    });
    uploader.init();

};


DR.uploadoneOld =function ( bt_id ) {
    if( typeof  plupload == 'undefined' ){
        DR.tip( "请先加载 plupload js",{style:'error'});
        return false ;
    }
    var opt ={
        url:'/ajax/upload',
        query:'abc=123',
        ext:'', //默认 jpg,gif,png,zip,rar
        max_file_size:'10mb',
        cb:function () {}
        ,before:function () {  return true ;  }
    }
    if(arguments.length>1 ) $.extend( opt ,arguments[1]);
    var url = opt.url+ ( opt.url.indexOf('?')>0?'&':'?')+opt.query;


    var ext = opt.ext=='' ?[
        {title : "Image files", extensions : "jpg,gif,png"},
        {title : "Zip files", extensions : "jpg,png,gif,zip,rar,doc,xls,ppt,docx,xlsx,pptx,pdf,txt"}
    ]: [{title : "文件类型", extensions : opt.ext}];

    var upconfig = {d:null } ;

    var uploader = new plupload.Uploader({
        runtimes : 'html5,flash,silverlight,html4',
        browse_button :  bt_id , // you can pass an id...
        //container: document.getElementById( 'uploadcontent' ), // ... or DOM Element itself
        url :url  ,
        flash_swf_url : '/res/js/plupload/Moxie.swf',
        silverlight_xap_url : 'res/js/plupload/Moxie.xap',
        headers:{'X-Display':'json' },
        filters : {
            max_file_size : opt.max_file_size,
            mime_types:ext
        },

        init: {
            PostInit:function () {
                upconfig.d = new   dialog( );
            },
            FilesAdded: function(up, files) {
                //alert('开始上传');
                if(! opt.before()) return false ;
                upconfig.d.content('开始上传'   ).show();
                uploader.start();
            },

            UploadProgress: function(up, file) {

                var loding =  '<div class="sui-loading loading-xxsmall loading-inline"><i class="sui-icon icon-pc-loading"></i></div>';
                var html='<div class="sui-msg msg-large msg-loading msg-naked"><div class="msg-con">正在上传... <span>' + file.percent + '%</span></div><s class="msg-icon">'+loding+'</s></div>';
                upconfig.d.content( html );
            },

            Error: function(up, err) {
                if(upconfig.d!= null  ) upconfig.d.close();
                var str ='上传发生错误！('+ err.code+')';
                var str_error='';
                if( err.code==-601) {
                    str='错误：请注意文件格式！('+ err.code+')';
                    if(  opt.ext  ) str ='仅支持格式：'+ opt.ext   ;

                }
                DR.tip( str ,{style:'error',info:'<div  style="color: #999">错误：'+err.message+str_error+'</div>' });
                //document.getElementById('console').appendChild(document.createTextNode("\nError #" + err.code + ": " + err.message));
            },
            FileUploaded:function (up, file, res) {
                if(upconfig.d!= null  ) upconfig.d.close();
                DR.log( res  );
                eval('var rep = '+res.response );
                if(  rep.error==0 ){
                    DR.tip('上传成功',{style:'success'});
                    opt.cb( rep.data  );
                }else{
                    DR.tip(  rep.error_des ,{style:'error'});
                }


            }
        }
    });
    uploader.init();

};

DR.log = function (   ) {
    try{
        var v = arguments;
        var p = 'DR ';
        var t = new Date().toTimeString().substr(0, 8);
        if(v.length == 1){
            console.log(t, p, v[0]);
        }else if(v.length == 2){
            console.log(t, p, v[0], v[1]);
        }else if(v.length == 3){
            console.log(t, p, v[0], v[1], v[2]);
        }else if(v.length == 4){
            console.log(t, p, v[0], v[1], v[2], v[3]);
        }else if(v.length == 5){
            console.log(t, p, v[0], v[1], v[2], v[3], v[4]);
        }else{
            console.log(t, p, v);
        }
    }catch(e){  }
}

DR.login = function ( from_obj ) {
     
    from_obj.validate({
        messages: {
            openid: ["亲，没填账号呢", "账号不瞎填哦"],
            psw: ["亲，密码没填","亲，密码需要大于6位"]
        }
        , success: function() {
            var data = from_obj.serialize();
            //DR.tip( "good : "+ config.vtab +"<br>"+data  );
            DR.ajax('/index/login/post', {'data':data });
            return false;
        }
    });
}

DR.loginV2 = function ( from_obj ) {

    from_obj.validate({
        messages: {
            openid: ["亲，没填账号呢", "账号不瞎填哦"],
            psw: ["亲，密码没填","亲，密码需要大于6位"]
        }
        , success: function() {
            //alert('god');
            var psw= $('#psw').val();
            $('#psw').val('');
            $('#psw_encrypt').val( DR.encrypt( psw) );
            var data = from_obj.serialize();
            //DR.tip( "good : "+ config.vtab +"<br>"+data  );
            DR.ajax('/index/login/post', {'data':data });
            return false;
        }
    });
}

DR.encrypt=function( str){
    var key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDvIJgKqkQcsMkyYAoaGwTwlkk35yG8RvCciWCqJVT7BDaWQxA7Noq7/Hqt4mS7ZFFukyO+bXdbOn8evI6M2GB40nIAEVGD3RpRt/ih9xB88MbE/F0IVw8L4l9plDW8n32e5tSGb+AVPqgYRA+UKCvNkReySFLL99W8od7q7WnHQwIDAQAB';
    var encrypt = new JSEncrypt();
    encrypt.setPublicKey( key );
    var encrypted = encrypt.encrypt( str );
    return encrypted;
}

DR.user = function ( obj_id ) {
    var opt={
        success:function ( uobj ) {
            if( uobj != null ){
                setUserHtml( uobj );
            }else{
                setUserLogin();
            }
        }
    }
    if( arguments.length>1) $.extend( opt, arguments[1]);

    var setUserHtml=function ( user) {
        var _is_school =  isSchool(user);
        var html='<li class="sui-dropdown"><a href="javascript:void(0);" data-toggle="dropdown" class="dropdown-toggle" style="'+(_is_school?'color:red':'')+'">'+user.name +' <i class="caret"></i></a>';
        html+= '<ul role="menu" class="sui-dropdown-menu">';
        html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'book/user/">我的读书</a></li>';
        if( isAdmin(user) ) html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'hcadmin">系统管理</a></li>';
        if( _is_school ) html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'school">后台管理</a></li>';
        if( _checkAdmin(user,'p4') ) html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'editor" target="_blank">编辑管理</a></li>';
        if( isTeacher(user) ) html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'cls/teacherClasslist">任课班级</a></li>';
        html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'member/setting/">个人设置</a></li>';
        html+= '    <li role="presentation"><a role="menuitem" tabindex="-1" href="'+DR.WWW_ROOT+'logout">登出</a></li>';
        html+= '    </ul>';
        html+= '    </li>';
        $('#'+obj_id ).html( html );
    }
    var isAdmin= function ( user) {
        if( typeof  user.attr == 'undefined' ) return false ;
        var attr= user.attr;
        for( p in attr){
            if( attr[p]=='p1' ||   attr[p]=='p2') return true;
        }
        return false ;
    }
    
    var _checkAdmin= function ( user,attr_v ) {
        if( typeof  user.attr == 'undefined' ) return false ;
        var attr= user.attr;
        for( p in attr){  if( attr[p]== attr_v ) return true; }
        return false ;
    }

    var isTeacher= function (user) {
        if( typeof  user.ts == 'undefined' ) return false ;
        if(  user.ts=='1' ||  user.ts=='3' ) return user.ts;
        return false;
    }

    var isSchool = function (user) {
        if( typeof  user.attr == 'undefined' ) return false ;
        var attr= user.attr;
        for( p in attr){
            if( attr[p]=='p3'  ) return true;
        }
        return false ;
    }

    var setUserLogin=function () {
        var html = '<li><a href="'+DR.WWW_ROOT+'login">登录</a></li>';
        html += '<li><a href="'+DR.WWW_ROOT+'reg">注册</a></li>';
        $('#'+obj_id ).html( html );
    }
    var _uhao = DR.getCookie( '_UHAO');
    var uobj = null ;
    if( _uhao != null ){
        try{
            eval(" uobj= "+  decodeURIComponent( _uhao)  );
        }catch (e){    }
    }
    opt.success( uobj )

}

DR.haoceUser = function () {
    var _uhao = DR.getCookie( '_UHAO');
    var uobj = null ;
    if( _uhao != null ){
        try{
            eval(" uobj= "+  decodeURIComponent( _uhao)  );
        }catch (e){    }
    }


    this.getAll= function () {
        return uobj;
    }
    this.getByKey= function ( key ) {
        if(  uobj == null ) return null;
        if( typeof uobj[key]== 'undefined')       return null;
        return uobj[key];
    }

    this.getUserID= function () {
        var uid = that.getByKey('uid');
        return uid? uid:0;
    }
    var that = this;
}

DR.getCookie =   function (name)
{
    var arr,reg=new RegExp("(^| )"+name+"=([^;]*)(;|$)");
    if(arr=document.cookie.match(reg))
        return arr[2] ;
    else
        return null;
}

DR.delCookie = function (name){
    var exp = new Date();
    exp.setTime(exp.getTime() - 1);
    var cval=DR.getCookie(name);
    //if(cval!=null)
        document.cookie= name + "=;expires="+exp.toGMTString();
        //document.cookie= name + "="+cval+";expires="+exp.toGMTString();
}

DR.ajaxForm = function ( from_id ) {
    var obj = $('#'+from_id );
    if( obj.length<=0){
        DR.tip( from_id +' 表单不存在，请检查' )
        return false ;
    }
    var url  = obj.attr('action').trim();
    if(url.indexOf('http')<0 ) url = DR.WWW_ROOT+ url.trim('/');
    DR.log( url );
    var opt={'success':''};
    if( arguments.length>1 ) $.extend( opt ,arguments[1]);;
    DR.ajax( url,{data: obj.serialize()} ,opt );
}

DR.ajaxAndValidate = function (form_id ) {
    var opt2={'success':''};
    if( arguments.length>2 ) $.extend( opt2 ,arguments[2]);;

    var opt ={
        messages: { }
        , success: function() {
            DR.ajaxForm(form_id ,opt2 );
            return false;
        }
    };
    if( arguments.length>1 ) $.extend( opt, arguments[1]);
    DR.log("ajaxAndValidate",opt.messages);
    $("#"+form_id).validate( opt );
}

DR.cache ={
    iframe:{}
}

DR.iframe_fun = function () {
    var that = $(this);
    var url = that.data('url');
    if( typeof DR.cache.iframe[ url ] =="undefined" ){
        DR.cache.iframe[ url ] = new DR.iframe( url);
    }
    DR.cache.iframe[ url ].show();
}

DR.iframe = function ( url ) {

    var d2 = dialog({
        //title: '错误',
        content: 'Loading' ,
        width:600,
        //height:500
    });
    var that2= this;
    var id='d_' + parseInt( 10000*Math.random());
   start2 = function () {
        url += url.indexOf('?')>0?'&':'?';
        url +='isiframe=1';
        d2.content( '<div style="position: relative"><button i="close" class="ui-dialog-close" title="cancel" id="'+id+'-close" style="position: absolute;top:-10px; right:-10px;">×</button></div><iframe id="'+id+'"  src="'+url+'" style="width: 100%;height: 100%; border: 0px;"></iframe>' );
        $('#'+id+'-close').click( function () {
            that2.close();
        });
        var iobj =$('#'+ id );
        iobj.bind('load',function () {
            if( document.documentElement.clientWidth<600  ){
                d2.width( document.documentElement.clientWidth-60 );
            }else{
                d2.width(600);
            }
            d2.height(  window.frames[0].document.documentElement.scrollHeight  );
            $('#'+id).css('height',  window.frames[0].document.documentElement.scrollHeight +'px');
            //alert( document.documentElement.clientWidth );
            //window.frames[0].window.reload();
            //alert( document.documentElement.clientWidth+'px' );
        });


    }
    this.close= function () {
        d2.close();
    }
    this.show=function () {
        d2.showModal();
    }

    start2();
}
DR.R= function (url) {
    return DR.WWW_ROOT + url.trim('/');
}
DR.wordCount  = function ( str ) {
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

DR.play= function ( my_jPlayer,click_select ) {
    var that_now=null;
    my_jPlayer.jPlayer({
        ready: function () {
            //$("#jp_container .track-default").click();
        },
        timeupdate: function(event) {
            that_now.html( parseInt(event.jPlayer.status.currentPercentAbsolute, 10) + "%");
            //DR.log( 'timeupdate', event.jPlayer );
        },
        play: function(event) {
            //my_playState.text(opt_text_playing);
            DR.log( 'play', event.jPlayer );
        },
        pause: function(event) {
            //my_playState.text(opt_text_selected);

        },
        ended: function(event) {
            that_now.html('播放完成');
        },
        swfPath: "/res/js/jplayer",
        cssSelectorAncestor: "#jp_container",
        supplied: "mp3,m4a, oga",
        wmode: "window"
    });
    //$('.mp3play').click(function () {
    $( click_select).click(function () {
        var that  = $(this);
        that_now = that.find('span');
        that_now.html('...');
        DR.log( that.data('file') );
        my_jPlayer.jPlayer("setMedia", {
            mp3: that.data('file')
        });
        my_jPlayer.jPlayer("play");
    });
}

DR.ajaxUrl= function () {
    var that = $(this);
    DR.ajax( that.data('url')  );
}


/**
 * 必须含有 .autocomplete .class
 * Example在 school_user_list.phtml中
 */
DR.classSelect =function () {
    var conf={
        classList:[]
        ,from:$('#op-select')
    }

    var d3 = dialog({
        title: '',
        content: '<form style="text-align: center;padding: 10px 15px;" id="class-select"   class="sui-form form-horizontal" action="'+conf.from.attr('action')+'" >'+conf.from.html()+'</form>' ,
        width:400
        ,cancel: function () {
            //alert('禁止关闭');
            this.close();
            return false;
        },
        cancelDisplay: false
    });

    conf.select = $('#class-select');

    var initClass = function () {
        conf.select.find('.autocomplete').autocomplete({
            lookup:   conf.classList  ,
            minChars: 0,
            onSelect: function (suggestion) {
                conf.select.find('.class_id').val( suggestion.data.class_id );
            }
        });
        DR.ajaxAndValidate( 'class-select');
    }
    if(   conf.classList.length>0 ){
        initClass();
    }

    //d3.showModal();
    //
    var loadData= function ( onSuccess ) {
        DR.ajax( '/school/schoolUser/classSuggest',{},{success:function ( rep ) {
            conf.classList= rep.data.classList;
            initClass();
            onSuccess();
        }})
    }

    var toHtml= function () {
        console.log( 'classList', conf.classList  );
        //alert(   conf.select.length );

        d3.showModal();
    }
    this.click= function () {
        var cf = {
            title:'选择班级并加入',
            action:''
        }
        if( arguments.length>0 ) $.extend(cf, arguments[0]);
        d3.title( cf.title  );
        //alert(   conf.select.attr('action') );
        if( cf.action ){
            conf.select.attr('action',  cf.action );
        }
        if( conf.classList.length<=0 ) loadData( toHtml );
        else toHtml();
    }
}

window.DR=DR;


var BOOK= BOOK ||{};
BOOK.cache={
    iframe:false
};
BOOK.addBook = function () {
    var that = $(this);
    var d={ book_id:that.data('bookid') };
    var opd = $('#op-select');
    var url = DR.R('/book/join/add/'+ d.book_id);
    var url_over = DR.R('/book/join/add_over/'+ d.book_id);
    var is_re_add = that.data('readd')=='1'? true: false ;
    if( is_re_add) {
        url+= '/readd';
        url_over+= '/readd';
    }
    if( that.data('no-limit')=='1')   url+="?no_limit=1"

    DR.ajax( url ,{data:d },{'success':function (rep) {
        if( typeof  rep.data.class != 'undefined' ){
            var d3 = dialog({
                title: '选择班级并加入',
                content: '<form style="text-align: center;padding: 10px 15px;" id="ds-123"   class="sui-form form-horizontal" action="'+url+'">'+opd.html()+'</form>' ,
                width:400
            });
            d3.showModal();
            var opds =  $('#ds-123');
            if( is_re_add )opds.find('.ds-readd').hide();

            opds.find('#autocomplete').autocomplete({
                lookup: rep.data.class ,
                minChars: 0,
                onSelect: function (suggestion) {
                    opds.find('.class_id').val( suggestion.data.class_id );
                }
            });
            opds.find('.ajax-add-class').data('url',url_over ).click(DR.ajaxUrl );
            DR.ajaxAndValidate( 'ds-123');
        }else{
            DR.log( rep );
            DR.tipFromRep( rep );
        }

    }});
}
BOOK.reply= function () {
    var wconf={
        pr:'.topic-href',
        'f':'#f-hide'
    }
    var that = $(this);
    var ui={
        pr: that.parents( wconf.pr ),
        f: $( wconf.f )
    }
    var cmt_id = ui.pr.data('comment_id');
    DR.log('cmtid', cmt_id );
    if( that.data('op')=='none' ){
        ui.pr.after( '<form id="cm-'+cmt_id+'" action="'+DR.R('book/commentAdd/'+ ui.pr.data('book_id')+'/'+ ui.pr.data('topic_id')+'/'+cmt_id )+'">'+ ui.f.html() +'</form>' );
        that.data('op','close');
        var pobj=  $('#cm-'+ cmt_id);
        pobj.find('button').click( function () {
            var str =pobj.find('.comment').val();
            alert(str);
            pobj.find('.word_cnt').val( DR.wordCount(str));
            DR.ajaxForm( "cm-"+cmt_id );
            return false ;
        });
    }
    var cmobj = $('#cm-'+ cmt_id);
    if('close'== that.data('op') ) {
        that.data('op','open').html('取消回复');
        cmobj.show();
    }
    else{
        that.data('op','close').html('回复');
        cmobj.hide();
    }
}

BOOK.good= function () {
    var that = $(this);
    DR.ajax( DR.R('book/good/'+ that.data('url')) ,{},{'success':function (rep ) {
        DR.log( rep );
        var good_cnt = parseInt( that.data('good') ) + rep.data.good.cnt ;
        if(  rep.data.good.cnt>0 ) that.addClass('go-zan');    else  that.removeClass('go-zan') ;
        that.data('good', good_cnt ).html( "赞("+ good_cnt+")");
    }});
}
BOOK.juBao= function () {
    var that = $(this);
    DR.ajax( DR.R('book/bookLog/'+ that.data('url')) );
}
BOOK.ajaxUrl= function () {
    var that = $(this);
    DR.ajax( that.data('url')  );
}
BOOK.iframe = function () {
    var that = $(this);
    var url = that.data('url');
    if( typeof url=="undefined") url = that.attr('href');
    if( typeof url=="undefined")  return false ;
    //DR.log("url",url );
    if( BOOK.cache.iframe ==false ){
        BOOK.cache.iframe = new DR.iframe( url );
    }
    BOOK.cache.iframe.show();
    return false ;
}
/*end 图书相关*/

var MEMBER= MEMBER ||{};
MEMBER.yzm = function ( type ) {
    var wconfig={
        img:$('#imgYzmW .add-on img')
        ,btn:$('#openidw .add-on')
        ,time:0
        ,type:'yzm'
    }
    if(arguments.length>1) $.extend( wconfig, arguments[1]);

    if(wconfig.type=='vCode') {
        var vCode={
            conf:{ cnt:0, data: new Array() },
            ui:[ $('#v-code-0'),$('#v-code-1') ],
            flash:function () {
                vCode.conf.cnt=0;
                vCode.conf.data= new Array;
                $('#imgyzm').val( ''  );
                $('.v-clode-dt').hide();
            },
            click:function ( e ) {
                if( vCode.conf.cnt>1  ){
                    this.src=  DR.R('vCode?r=' + Math.random());
                    return;
                }
                e = e || window.event;
                vCode.ui[ vCode.conf.cnt%2 ].show().css('top',( e.pageY-12) +'px' ).css('left',( e.pageX-12 ) +'px' );
                var offsetX = e.pageX -   wconfig.img.offset().left;
                var offsetY = e.pageY -   wconfig.img.offset().top;
                console.log(offsetX,offsetY);
                vCode.conf.data.push( offsetX,offsetY);
                vCode.conf.cnt++;
                if( vCode.conf.cnt%2==0 ){
                    var str = vCode.conf.data.join(',');
                    $('#imgyzm').val(  str );
                    vCode.conf.data= new Array;
                    DR.ajax( DR.R('ajax/checkVCodeYzm'),{data:{'imgyzm':str },drError:function (rep ) {
                        wconfig.img.attr('src', DR.R('vCode?r=' + Math.random()) )   ;
                        DR.tip2( rep.error_des+'('+ rep.error +')',{'style':'error'} );
                    }});
                };

            }
        }
        wconfig.img.click( vCode.click );
        wconfig.img.load( vCode.flash );
        //alert( wconfig.type );
        //DR.tip2("正确")
    }else{
        wconfig.img.click(function () {
            this.src = DR.R('imgyzm?r=' + Math.random());
        });
    }

    var send_fun=function () {
        if( wconfig.time>0  ){
            DR.tip( wconfig.time+ "S 后可操作！ ");
            return ;
        }
        var _that = $(this) ;
        var ipt = _that.parents('.input-append').find('input');
        if( $.trim( ipt.val() )=='' ){
            DR.tip('请填写账号',{style:'error'});
            ipt.focus();
            return false;
        }
        var op_data = {'openid':$.trim( ipt.val()),'imgyzm':$.trim( $('#imgyzm').val()) ,'type':wconfig.type  };
        if(op_data.imgyzm==''){
            $('#imgYzmW').show();
            DR.tip( wconfig.type=='vCode'?'请先找出图片汉字':'请先填写图片验证码',{style:'error'});
            $('#imgyzm').focus();
            return false ;
        }
        DR.ajax('ajax/sendyzm/'+type
            ,{data:op_data,drError:function (rep) {
                DR.tip( rep.error_des+'('+ rep.error +')',{'style':'error'} );
                $('#imgYzmW .add-on img').click();
            }}
            ,{success:function ( rep ) {

                wconfig.time=60 ;
                wconfig.timecl =  setInterval( function () {
                    wconfig.time--;
                    if(wconfig.time<=0 ){
                        _that.html( '获取验证码');
                        clearInterval( wconfig.timecl );
                        wconfig.timecl= -1;
                        return ;
                    }
                    _that.html( '验证码('+wconfig.time+ 'S)');
                }, 1000);


                DR.tip( rep.redirect.msg,{style:'info'} );

            }} );
    };
    wconfig.btn.click( send_fun );

}

