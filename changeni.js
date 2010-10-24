/*
 * Script for Changeni plugin
 * Version:  1.0
 * Author : Dennison+Wolfe Internet Group
 */ 
 
(
	function(jQ){
		changeni =
		{
		siteUrl: '',
		init : function(){
                            if(jQ("#changeni_donation_box_content").html() == null){
                                return;
                            }
                            debugger;
                            var f=jQ("#changeni_donation_form form");
                            f.submit(function(){
                                    changeni.addToCart(this);
                                    return false;
                            });
					
                        },
		addToCart : function(f){
                            f=jQ(f);
                            debugger;
                            var data=f.serialize();
                            jQ.ajax({
                                    url: "/wp-admin/admin-ajax.php",
                                    type: 'POST',
                                    data: data,
                                    dataType: 'html',
                                    success:function(result, status, XMLHttpRequest){
                                            var s=jQ("#info_message");
                                            //var r=result.replace(/&#8203;/gi,'');
                                            s.html(result);
                                            debugger;
                                            //var testUrl = s.find("a:last");
                                            //testUrl.attr("target", "_blank");
                                            //s.html('');
                                            //s.append(testUrl);
                                            s.show();
                                            jQ("#ajax_busy_img").hide();
                                            return false;
                                    }
                            });
                            jQ("#changeni_donation_form input[type='submit']").hide();
                           // jQ("#redirmap-container input[name=cancel]").hide();
                            jQ("#ajax_busy_img").show();
                    }
		};
		
		jQ(document).ready(function(){
                                changeni.init();
                        })
	}
)(jQuery);