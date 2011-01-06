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
                            var ls=jQ("#changeni_network_list");
                            if(ls.html() !== null){
                                ls.change(function(){
                                        var url = jQ(this).val();
                                        jQ(location).attr('href',url);
                                        return false;
                                });
                            }
                            

                            if(jQ("#changeni_donation_box_content").html() !== null){
                                var f=jQ("#changeni_donation_form form");
                                f.submit(function(){
                                        changeni.addToCart(this);
                                        return false;
                                });

                                changeni.updateDonationForm();
                            }

                            var v=jQ("#tg-tip-amount-validator");
                            if(v.html() !== null){
                                v.validate({
                                    event: 'change',
                                    rules:
                                    {
                                        tg_tip_amount: {changeniValidateDollarAmount:true}
                                    },
                                    messages:
                                    {
                                        tg_tip_amount: {changeniValidateDollarAmount:"Please enter a valid dollar amount"}
                                    },

                                    errorClass: "invalid-tg-tip-amount",
                                    errorContainer:"#tg-tip-validate-error",
                                    errorLabelContainer:"#tg-tip-validate-error ul",
                                    wrapper:"li"
                                });

                                jQ("#tg_tip_amount").change(function(){
                                        debugger;
                                        var p=jQ(this);
                                        if(p.hasClass("invalid-tg-tip-amount")){
                                            return false;
                                        }

                                        var subtotal = jQ("#donation-subtotal").val()*1.00;
                                        var tip = p.val().replace('$', '')*1.00;
                                        var total = tip+subtotal;
                                        var tipFormat = tip.toFixed(2).toString();
                                        tipFormat = '$' + tipFormat;

                                        jQ("#tg_tip_amount").val(tipFormat);
                                        jQ("#tg_tip_percentage").html(Math.round(tip/subtotal*100));
                                        jQ("#tg_tip_total").html(total.toFixed(2));
                                        jQ("#tg-tip-amount-approved").val(tip);
                                        return true;
                                });
                            }
                            
                            
                        },
		updateDonationForm : function(){
                            var recurrence=changeniJsData.recurrence;
                            switch(recurrence){
                                case 'monthly':
                                    //jQ("#changeni_donation_form input").attr('disabled', true);
                                    jQ("#changeni_donation_form [id='donation_freq_radio_once']").attr('disabled', true);
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
                                            
                                            jQ("#info_message").html(result.message);
                                            jQ("#changeni_item_count").html(result.totalItems);
                                            jQ("#changeni_amount_total").html('$'+result.totalAmount);

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
                        });
                
                // add the validate dollar amount method
                jQ.validator.addMethod("changeniValidateDollarAmount", function(value, element) {
                        return this.optional(element) || /^(\$)?(\d{1,3})(\.\d{1,2})?$/.test(value);
                });

	}

)(jQuery);

