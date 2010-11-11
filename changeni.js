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
                            var f=jQ("#changeni_donation_form form");
                            f.submit(function(){
                                    changeni.addToCart(this);
                                    return false;
                            });

                            changeni.updateDonationForm();
                        },
		updateDonationForm : function(){
                            var recurrence=changeniJsData.recurrence;
                            debugger;
                            switch(recurrence){
                                case 'monthly':
                                    jQ("#changeni_donation_form input").attr('disabled', true);
                                    break;
                                case 'one-time':
                                    jQ("#changeni_donation_form [id='donation_freq_radio_monthly']").attr('disabled', true);
                                    break;
                                default:
                                    jQ("#changeni_donation_form input").attr('disabled', false);
                                    break;
                            }
                            

                        },
		addToCart : function(f){
                            f=jQ(f);
                            var data=f.serialize();
                            var url=changeniJsData.ajaxUrl;
                            jQ.ajax({
                                    url: url,
                                    type: 'POST',
                                    data: data,
                                    dataType: 'json',
                                    success:function(result, status, XMLHttpRequest){
                                            debugger;

                                            jQ("#info_message").html(result.message);
                                            jQ("#changeni_item_count").html(result.totalItems);
                                            jQ("#changeni_amount_total").html(result.totalAmount);

                                            jQ("#ajax_busy_img").hide();
                                            jQ("#changeni_donation_form input[name='donation_amount']").val('');
                                            jQ("#changeni_donation_form input[type='submit']").show();
                                            return false;
                                    }
                            });
                            jQ("#changeni_donation_form input[type='submit']").hide();
                            jQ("#ajax_busy_img").show();
                    }
		};
		
		jQ(document).ready(function(){
                                changeni.init();
                        })
	}
)(jQuery);