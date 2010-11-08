/*
 * Script for Changeni plugin admin
 * Version:  1.0
 * Author : Dennison+Wolfe Internet Group
 */ 
 
(
	function(jQ){
		changeni =
		{
		siteUrl: '',
		init : function(){
                            if(jQ("#changeni-admin").html() == null){
                                return;
                            }

                            jQ("#changeni-admin").tabs({selected: 0 //default tab
                                                    });
                            return;
					
                        }
		};
		
		jQ(document).ready(function(){
                                changeni.init();
                        })
	}
)(jQuery);