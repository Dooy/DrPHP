function haoceSim(   ) {
    var ui={
        sim:$('.dr-sim'),
        sim_radius: $('.dr-sim-radius'),
        left:$('.dr-sim-left .dr-sim-item'),
        right: $('.dr-sim-right .dr-sim-item')
        ,close:$('.dr-sim-close')
    }
    var hc= new DR.haoceUser();
    var that = this;
    this.data={
        'l':{time:'',id:''},
        'r':{time:'',id:''}
    }
    this.sim = function( content ){
        var opt={
            user_id:  hc.getUserID(),
            limit:1
        }
        if( arguments.length>1) $.extend( opt, arguments[1]);
        DR.ajax('/HaoCeCheckWebService/CheckService'
            ,{
                "data":{'content':content,'user_id': opt.user_id,"limit":opt.limit }
            }
            ,{
                close_success:function (rep ) {
                    DR.log('sim', rep );
                    if( typeof rep.error != 'undefined'){
                        DR.tip( rep.error );
                        return;
                    }
                    toHtml(rep );
                }
                /*,
                 close_error:function () {
                 DR.tip( "发生错误！");
                 }*/
            }
        );
    }
    this.simByTopicID= function ( topic_id) {
        DR.ajax('book/sim/topic_id/'+topic_id,{},{success:function (rep) {
            DR.log( "sim topic ",rep);
            that.data.l.id  =  rep.data.topic.user_id ;
            that.sim( rep.data.topic.topic_info,{'user_id': rep.data.topic.user_id  });
            that.data.l.time ='时间：'+ DR.timeFormat( parseInt( rep.data.topic.mtime!='0'? rep.data.topic.mtime:  rep.data.topic.ctime),'yyyy-MM-dd hh:mm');
        }} )
    }
    function toHtml( rep ) {
        var obj = rep[0];
        var lhtml='<h4>本文</h4><div  style="padding: 10px 0 10px 0;color: #999"><span>'+ that.data.l.time +'</span> <span class="u-info"></span></div> <div class="dr-sim-content">'+content_decode(obj.lcontent)+'</div>';
        ui.left.html(  lhtml );
        var tarr = obj.topic_id.split('_');
        that.data.r.time= tarr.length>=2?('时间：'+ DR.timeFormat( parseInt(tarr[1]),'yyyy-MM-dd hh:mm')):'';
        that.data.r.id= obj.user_id ;

        var rhtml='<h4>'+obj.title +'</h4> <div style="padding: 10px 0 10px 0;color: #999"><span>'+   that.data.r.time  +'</span> <span class="u-info"></span></div>  <div class="dr-sim-content">'+content_decode(obj.rcontent)+'</div>';
        ui.right.html(  rhtml );
        ui.sim_radius.html( score_decode(obj.sim ) );
        divShow();
        divHover();
        loadUser();
    }
    function loadUser() {
        DR.ajax('ajax/user',{data:{ uid: that.data.l.id +','+that.data.r.id} },{success:function (rep) {
            DR.log( "ajax user ",rep);
            if( typeof rep.data.user[ that.data.r.id] != 'undefined' ){
                var obj=  rep.data.user[ that.data.r.id];
                //DR.log( "right ",obj);
                ui.right.find('.u-info').html('姓名：'+obj.name+ '   学校：'+  obj.school);
            }
            if( typeof rep.data.user[ that.data.l.id] != 'undefined'  ){
                var obj=  rep.data.user[ that.data.l.id];
                //DR.log( "left ",obj);
                ui.left.find('.u-info').html('姓名：'+obj.name+ '   学校：'+  obj.school);
            }
        }} );
    }
    function divHover() {
        $('.dr-sim-content b').hover(function () {
            var cl_name = $(this).attr('class');
            $('.'+cl_name).css({'background-color':'#28a3ef','color':'#fff'});
            //alert(cl_name );
        },function () {
            var cl_name = $(this).attr('class');
            $('.'+cl_name).css( {'background-color':'transparent','color':'red'});
        })
    }
    function score_decode( sim_numb ) {
        var tarr = sim_numb.split('.');
        var mzs = parseInt( tarr[0] );
        if( mzs>=100 ) return  '<b>100</b><span>%</span>';
        return '<b>'+tarr[0]+'</b><span>'+(tarr.length>1?'.'+tarr[1]:'')+'%</span>';
    }
    function content_decode( str ) {
        return str.replace( /id=/g,'class=hl_').replace(/\n/g,'<br>') ;
    }
    function goStart() {
        reg();
    }
    function divClose() {
        ui.sim.slideUp( 600 );
    }
    function divShow() {
        ui.sim.show(   );
    }
    function reg() {
        ui.close.click( divClose );
    }
    goStart();
}