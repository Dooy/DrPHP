/**
 * Created by Administrator on 2017/7/26.
 */

function banner_index () {
    var conf={cnt: $('.banner').length };
    var _banner=1;
    var ui={   }

    function initBt(){
        var cnt = conf.cnt =   $('.banner').length ;
        var ni=0;
        $('.banner').each( function(i){
            if( $(this).css('display')!='none' ){
                ni=i;return false;
            }
        });
        var html='';

        for(var i=0;i<cnt;i++){
            html+='<a href="javascript:;" '+(i==ni?'class="select"':'')+' content="'+i+'"></a>&nbsp;&nbsp;';
        }

        $('.dr-index-bt').html( html );
        ui.bt= $('.dr-index-bt a');
        ui.bt.click(function(){
            _banner= parseInt( $(this).attr('content') );
            banner();
        });
    }

    function banner(   ){
        var now ;
        $('.banner').each( function(i){
            if( $(this).css('display')!='none' ){
                now=$(this);
                return false;
            }
        });
        var cnt = conf.cnt;
        _banner =(_banner)%cnt;
        var nxt = $('.banner').eq( _banner );
        now.fadeOut( 300 ,function(){
            now.hide();
            nxt.fadeIn(10);
            banner_select( _banner );
            _banner++;
        });
    }
    function banner_select( ni ){
        $('.dr-index-bt a').removeClass( );
        $('.dr-index-bt a').each(function(i){
            if(ni==i){
                $(this).addClass('select');
                return false;
            }
        });
    }
    function initBanner(    ){
        initBt();
        setInterval( banner,12000 );
    }
    initBanner();
}