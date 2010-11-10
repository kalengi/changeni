<?php
/*
Plugin Name: Changeni
Plugin URI: http://www.dennisonwolfe.com/
Description: The Changeni plugin manages donations on WordPress Multisite installations.
Version: 1.0.0
Author: Dennison+Wolfe Internet Group
Author URI: http://www.dennisonwolfe.com/
*/

/*  Copyright 2009  Dennison+Wolfe Internet Group  (email : tyler@dennisonwolfe.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*==========================================================================*/
//  Database Objects
/*==========================================================================*/
define('CHANGENI_PAYMENTS_TABLE','changeni_payments');
define('CHANGENI_LOGS_TABLE','changeni_logs');
define('CHANGENI_FOLDER', WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) );


if(!isset($_SESSION)) {
    session_start();
}


if(!isset($_SESSION['changeni_cart'])) {
    $_SESSION['changeni_cart'] = array();
}


if ( is_admin() ) {
	//plugin activation
	add_action('activate_changeni/changeni.php', 'changeni_init');
        //settings menu
	add_action('admin_menu', 'changeni_admin_menu');
	//load css
	add_action('admin_print_styles', 'changeni_load_stylesheets' );
	//load js
	add_action('admin_print_scripts', 'changeni_load_admin_scripts' );
	//ajax handling for logged-in users
	add_action('wp_ajax_changeni_action', 'changeni_ajax_callback');
        //ajax handling for for the rest
	add_action('wp_ajax_nopriv_changeni_action', 'changeni_ajax_callback');
}
else{
	//load css
	add_action('wp_head', 'changeni_load_stylesheets');

        //load js
	add_action('wp_print_scripts', 'changeni_load_scripts' );

        //shopping cart bar
        add_action( 'wp_footer', 'display_changeni_cart_summary' );
	
}

//Create rewrite rules
add_filter('rewrite_rules_array', 'add_changeni_rewrite_rules');

//insert query parameter
add_filter('query_vars','add_changeni_query_var');

//intercept page title
add_filter ( 'the_posts', 'changeni_page' );


/* Load admin js files*/
function changeni_load_admin_scripts() {
    
    wp_enqueue_script('changeni-admin', CHANGENI_FOLDER . '/changeni-admin.js', array('jquery-ui-tabs', 'json2'), '1.0');

}

/* Load js files*/
function changeni_load_scripts() {
    global $current_blog;

    wp_enqueue_script('changeni', CHANGENI_FOLDER . '/changeni.js', array('jquery-ui-tabs', 'json2'), '1.0');

    $ajax_url = $current_blog->path . 'wp-admin/admin-ajax.php';
    wp_localize_script( 'changeni', 'changeniJsData', array( 'ajaxUrl' => $ajax_url ) );
}

/* Load css files*/
function changeni_load_stylesheets() {
	$style_file = plugins_url('changeni/changeni.css');
	echo '<link rel="stylesheet" type="text/css" href="' . $style_file . '" />' . PHP_EOL;

}


/* generate changeni page */
function changeni_page($posts) {
    global $wp_query;
   // global $post;
    
    if (isset ( $wp_query->query_vars['show_page'] )){
        
        $request = $wp_query->query_vars['show_page'];
        $request = trim(strtolower($request));
        $page_name = 'changeni-' . $request;
        //$page_title = '';
        switch($request){
            case 'cart':
                $posts[0] = changeni_cart_page($posts[0], $page_name);
                break;
            case 'checkout':
                $posts[0] = changeni_checkout_page($posts[0], $page_name);
                break;
            case 'paid':
                $posts[0] = changeni_record_payment($posts[0], $page_name);
                break;
            case 'thanks':
                $posts[0] = changeni_thanks_page($posts[0], $page_name);
                break;
        }

    }

    return $posts;
}

function changeni_init_page($page, $page_name, $page_title){
    $page->ID = -1;
    $page->post_title = $page_title;
    $page->post_date = current_time('mysql');
    $page->post_date_gmt = current_time('mysql', 1);
    $page->post_category = 0;
    $page->post_excerpt = '';
    $page->comment_status = 'closed';
    $page->ping_status = 'closed';
    $page->post_password = '';
    $page->post_name = $page_name;
    $page->to_ping = '';
    $page->pinged = '';
    $page->post_modified = current_time('mysql');
    $page->post_modified_gmt = current_time('mysql', 1);
    $page->post_content_filtered = '';
    $page->post_parent = 0;
    $page->guid = get_bloginfo('wpurl') . '/' . $page_name;
    $page->menu_order = 0;
    $page->post_type = 'post';
    $page->post_mime_type = '';
    $page->comment_count = 0;

    return $page;
}

function get_changeni_cart_listing(){
    $cart_items = $_SESSION['changeni_cart'];
    ob_start();
        ?>
            <table class="widefat">
                <thead>
                        <tr>
                        <th class="cart_count" id="cart_count" scope="col">
                                Item
                        </th>
                        <?php
                        $columns = array('Item_count', 'Organization', 'Amount', 'Recurrence');
                        ob_start();
                            foreach($columns as $column_name) {
                                if(strtolower($column_name) == 'item_count'){
                                    continue;
                                }
                            ?>
                                <th scope="col"><?php echo $column_name; ?></th>
                            <?php
                            }
                            $column_names = ob_get_contents();
                        ob_end_clean();

                        echo $column_names;
                        ?>
                        </tr>
                </thead>
                <tfoot>
                        <tr>
                        <th class="cart_count" id="cart_count_footer" scope="col">
                                Item
                        </th>
                                <?php echo $column_names; ?>
                        </tr>
                </tfoot>
                <tbody id="changeni-donation-list" class="list:site">
                    <?php
                    if ( $cart_items ) {
                        $class = '';
                        $item_count = 0;
                        foreach ( $cart_items as $blog_id => $donation ) {
                            $item_count++;
                            $class = ( 'alternate' == $class ) ? '' : 'alternate';
                            echo "<tr class='$class'>";
                            foreach ( $columns as $column_name ) {
                                switch ( strtolower($column_name) ) {
                                    case 'item_count':
                                        ?>
                                                <td valign="top" scope="row" class="item-count-column">
                                                       <?php echo $item_count; ?>
                                                </td>
                                        <?php
                                        break;
                                    case 'amount':
                                        ?>
                                                <td valign="top" scope="row" class="amount-column">
                                                       <?php echo $donation['amount']; ?>
                                                </td>
                                        <?php
                                        break;
                                    case 'recurrence':
                                        ?>
                                                <td valign="top" scope="row" class="recurrence-column">
                                                       <?php echo $donation['frequency']; ?>
                                                </td>
                                        <?php
                                        break;
                                    default:
                                        ?>
                                                <td valign="top">
                                                        <a href="<?php echo get_blog_option( $blog_id, 'siteurl' ) ; ?>"><?php echo $donation['name']; ?></a>

                                                </td>
                                        <?php
                                        break;
                                }
                            }
                        }
                    }
                    else {
                    ?>
                        <tr>
                                <td colspan="<?php echo (int) count( $columns ); ?>"><?php _e( 'No donations yet.' ) ?></td>
                        </tr>
                    <?php
                    } // end if ($cart_items)
                    ?>

                </tbody>
            </table>
        <?php
        $cart_listing = ob_get_contents();
    ob_end_clean();

    return $cart_listing;
}

function changeni_cart_page($cart_page, $page_name){
    $cart_page = changeni_init_page($cart_page, $page_name, 'Donations Cart');
    
    $cart_items = $_SESSION['changeni_cart'];
    ob_start();
        ?>
            <div id="donations_cart" class="donations_ui">
                <?php echo get_changeni_cart_listing(); ?>
                <?php
                    if ( $cart_items ) {
                    ?>
                        <form method="post" action="/changeni/checkout/">
                            <p class="submit">
                                <input type="submit" class="button-primary" value="Checkout"/>
                            </p>
                        </form>
                    <?php
                    }
                ?>
            </div>

        <?php
        $page_content = ob_get_contents();
    ob_end_clean();
    
    
    
    $cart_page->post_content = $page_content;
    
    return $cart_page;
}


function changeni_verify_IPN($payment_info){
    $paypal_url = get_site_option('changeni_paypal_url');
    $payment_info['cmd'] = '_notify-validate';
    $paypal_options = array(
            'timeout' => 5,
            'body' => $payment_info,
            'user-agent' => ('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.8) Gecko/20100722 Firefox/3.6.8 (.NET CLR 3.5.30729)')
    );

    $verification = wp_remote_post($paypal_url, $paypal_options);
    if($verification['body'] == 'VERIFIED' ) {
           return $payment_info;
    } else {
            $log_file = $_SERVER['DOCUMENT_ROOT'] . '\wp-content\debug_output\debug_output.log';
            $fh = fopen($log_file, 'a') or die("can't open debug file");
            $stringData = '<div>Paypal IPN verification: ' . print_r('IPN verification failure', 1) . '</div><br><br>' . "\r\n";
            fwrite($fh, $stringData);
            fclose($fh);

            changeni_log_ipn_error('IPN verification failure', $payment_info);
            exit;
    }
}

function changeni_log_ipn_error($message, $payment_info){
    global  $wpdb;

    $raw_data = serialize($payment_info);

    $table_name = $wpdb->prefix . CHANGENI_LOGS_TABLE;
    $rows_affected = $wpdb->insert( $table_name,
                                                array( 'message' => $message,
                                                    'raw_data' => $raw_data ) );

    return ;
}

function changeni_record_payment($thanks_page, $page_name){
        $log_file = $_SERVER['DOCUMENT_ROOT'] . '\wp-content\debug_output\debug_output.log';
	$fh = fopen($log_file, 'a') or die("can't open debug file");
	$stringData = '<div>Paypal IPN: ' . print_r($_POST, 1) . '</div><br><br>' . "\r\n";
	fwrite($fh, $stringData);
	fclose($fh);

        $payment_info = $_POST;
        
        //ensure Paypal IPN is legit
        $payment_info = changeni_verify_IPN($payment_info);

        //local checks
        global  $wpdb;

        if(strtolower($payment_info['payment_status']) !== 'completed'){
            changeni_log_ipn_error('Transaction is in Pending status', $payment_info);
            exit;
        }

        $paypal_account = get_site_option('changeni_paypal_account');
        if($paypal_account !== $payment_info['receiver_email']){
            changeni_log_ipn_error('Unknown receiver account', $payment_info);
            exit;
        }


        $table_name = $wpdb->prefix . CHANGENI_PAYMENTS_TABLE;
        $query = $wpdb->prepare( "SELECT COUNT(payment_id) FROM $table_name WHERE txn_id = '%s'", $payment_info['txn_id'] );
        $duplicates = $wpdb->get_var( $query );

        if(!$duplicates){
            changeni_log_ipn_error('Duplicate transaction', $payment_info);
            exit;
        }

        //save transaction
        $item_count = $payment_info['num_cart_items'];
        $first_name = $payment_info['first_name'];
        $last_name = $payment_info['last_name'];
        $payer_email = $payment_info['payer_email'];
        $txn_id = $payment_info['txn_id'];

        $tz = get_option('timezone_string');
        date_default_timezone_set( $tz );
		
        $payment_date = strtotime($payment_info['payment_date']);
        $payment_date = date('Y-m-d H:i:s', $payment_date);
        $payment_date_gmt = get_gmt_from_date($payment_info['payment_date']);
        $payment_type = ($payment_info['payment_type'] == 'cart') ? 'manual' : 'auto';
        
        for($i=1; $i<=$item_count; $i++){
            $item_code = $payment_info["item_number$i"];
            $item_code = explode('-', $item_code);
            $blog_id = $item_code[0];
            $blog_name = get_blog_option( $blog_id, 'blogname' );
            $amount = $payment_info["mc_gross_$i"];

            $rows_affected = $wpdb->insert( $table_name,
                                                array( 'first_name' => $first_name,
                                                    'last_name' => $last_name,
                                                    'email' => $payer_email,
                                                    'txn_id' => $txn_id,
                                                    'payment_date' => $payment_date,
                                                    'payment_date_gmt' => $payment_date_gmt,
                                                    'payment_type' => $payment_type,
                                                    'blog_id' => $blog_id,
                                                    'blog_name' => $blog_name,
                                                    'amount' => $amount ) );
        }

        header( "Content-Type: text/plain" );
        echo 'Transaction captured';
        exit;
        

}

function changeni_thanks_page($thanks_page, $page_name){
    unset ($_SESSION['changeni_cart']);
    $thanks_page = changeni_init_page($thanks_page, $page_name, 'Thank you');

    ob_start();
        ?>
            <div id="donations_thanks" class="donations_ui">
                Thanks for the donation!
            </div>

    <?php
        $page_content = ob_get_contents();
    ob_end_clean();

    
    $thanks_page->post_content = $page_content;

    return $thanks_page;
}

function changeni_checkout_page($checkout_page, $page_name){
    $checkout_page = changeni_init_page($checkout_page, $page_name, 'Check-out');

    $cart_items = $_SESSION['changeni_cart'];
    ob_start();
        ?>
            <div id="donations_checkout" class="donations_ui">
                <?php echo get_changeni_cart_listing(); ?>
                
                <?php
                    if ( $cart_items ) {
                        $cart_total = get_changeni_cart_total($cart_items);
                    ?>
                        <form method="post" action="/changeni/donate/">
                            <label for="donation_amount">US$</label><input name="donation_amount" id="donation_amount" type="text" value="<?php echo $cart_total->amount_total; ?>"  />

                            <p class="submit">
                                <input type="submit" class="button-primary" value="Donate"/>
                            </p>
                        </form>
                
                
                        <form action="<?php echo get_site_option('changeni_paypal_url'); ?>" method="post">
                                <input type="hidden" name="cmd" value="_cart">
                                <input type="hidden" name="upload" value="1">
                                <input type="hidden" name="business" value="<?php echo get_site_option('changeni_paypal_account'); ?>">
                                <input type="hidden" name="currency_code" value="USD">
                                <input type="hidden" name="no_shipping" value="1" />
                                <input type="hidden" name="tax" value="0" />
                                <input type="hidden" name="no_note" value="1" />
                                <input type="hidden" name="return" value="<?php echo get_site_option('changeni_thanks_page'); ?>">
                                <input type="hidden" name="cancel_return" value="<?php echo get_site_option('changeni_cancel_page'); ?>">
                                <input type="hidden" name="notify_url" value="<?php echo get_site_option('changeni_ipn_url'); ?>">
                                <input type="submit" class="button-primary" value="Donate"/>

                                <?php 
                                    $item_count = 0;
                                    foreach ( $cart_items as $blog_id => $donation ) {
                                        $item_count++;
                                ?>
                                        <input type="hidden" name="item_name_<?php echo $item_count; ?>" value="<?php echo $donation['name']; ?>">
                                        <input type="hidden" name="item_number_<?php echo $item_count; ?>" value="<?php echo $blog_id . '-' . $donation['short_name']; ?>">
                                        <input type="hidden" name="quantity_<?php echo $item_count; ?>" value="1">
                                        <input type="hidden" name="amount_<?php echo $item_count; ?>" value="<?php echo $donation['amount']; ?>">
                                        
                                <?php
                                  }
                                ?>
                        </form>

                    <?php
                    }
                ?>
            </div>

        <?php
        $page_content = ob_get_contents();
    ob_end_clean();



    $checkout_page->post_content = $page_content;

    return $checkout_page;
}

/* insert changeni query var*/
function add_changeni_query_var($query_vars) {
    array_push($query_vars, 'show_page');
    return $query_vars;
}

/* create changeni rewrite rules*/
function add_changeni_rewrite_rules($wp_rewrite_rules) {
	global $wp_rewrite;

	$rule_key = '%changeni%';
	$url_pattern = '([^/]+)';
	$query_string = 'show_page=';

	$wp_rewrite->add_rewrite_tag($rule_key, $url_pattern, $query_string);

	$url_structure = $wp_rewrite->root . "changeni/$rule_key/";
	$rewrite_rules = $wp_rewrite->generate_rewrite_rules($url_structure);

	$wp_rewrite_rules = $rewrite_rules + $wp_rewrite_rules;
	return $wp_rewrite_rules;
}



/* Changeni widget*/
class Changeni_Widget extends WP_Widget {
	function Changeni_Widget() {
		parent::WP_Widget(false, $name = 'Changeni',
                                        $widget_options = array('description' => 'Tracks donations sitewide on a multisite WordPress installation'));
	}

	function form($instance) {
		$title = esc_attr($instance['title']);
                ?>
                        <p><label for="<?php echo $this->get_field_id('title'); ?>">
                                <?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
                        </label></p>
                <?php
	}

	function update($new_instance, $old_instance) {
		return $new_instance;
	}

	function widget($args, $instance) {
		extract( $args );
                $title = apply_filters('widget_title', $instance['title']);
		echo $before_widget;
			if ( $title ){
				echo $before_title . $title . $after_title;
			}
			$id_parts = explode('-', $widget_id);
                        $id = end($id_parts);
                        echo $this->show_donation_widget($id);
		echo $after_widget;
	}

	function show_donation_widget($id){
            global $current_site;

            $site_root = 'http://' . $current_site->domain;
            $cart_url = $site_root . '/changeni/cart/';
            $checkout_url = $site_root . '/changeni/checkout/';
            $changeni_nonce = wp_create_nonce( 'changeni-donation-add' );

            ob_start();
                ?>
                    <div class='changeni_donation_box' id='changeni_donation_box-<?php echo $id; ?>'>
                        <h2>Donate to this organization</h2>
                        <div class='changeni_donation_box_content' id='changeni_donation_box_content'>
                            <span class='changeni_donation_form' id='changeni_donation_form'>
                                <form method="post" action="">
                                    <input name="cmd" id="changeni_cmd" type="hidden" value="add_donation"  />
                                    <input name="action" id="changeni_action" type="hidden" value="changeni_action"  />
                                    <input name="_ajax_nonce" id="changeni_ajax_nonce" type="hidden" value="<?php echo $changeni_nonce; ?>"  />
                                    <label for="donation_amount">US$</label><input name="donation_amount" id="donation_amount" type="text" value=""  />
                                    <input type="submit" class="button" value="Give" />
                                    <img src="<?php echo WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) . '/images/ajax_busy.gif'; ?>" id="ajax_busy_img"  alt="changeni submitting"/>
                                    <span class='donation_freq' id='donation_freq'>
                                        <br /><input type="radio" id="donation_freq_radio_monthly" name="donation_freq_radio" class="donation_freq_radio" value="monthly" checked="checked" /><label class="donation_freq_label" for="donation_freq_radio_monthly">monthly</label>
                                        <input type="radio" id="donation_freq_radio_once" name="donation_freq_radio" class="donation_freq_radio" value="one-time" /><label class="donation_freq_label" for="donation_freq_radio_once">one-time</label>
                                    </span>
                                </form>
                            </span>
                            <span class='action_links' id='action_links'>
                                <a href="<?php echo $cart_url; ?>">veiw cart</a> &nbsp; | &nbsp; <a href="<?php echo $checkout_url; ?>">checkout</a>
                            </span>
                        </div>
                    </div>
                <?php
                    $output = ob_get_contents();
            ob_end_clean();

            return $output;
	}

}

add_action('widgets_init', create_function('', 'return register_widget("Changeni_Widget");'));

//ajax handling
function changeni_ajax_callback(){
	//global $changeni_cart;

	if ( empty($_POST['cmd']) )
		die(-1);

	//$what = "('post','page')";
	$command = strtolower($_POST['cmd']);
	switch($command){
            case 'add_donation':
                $nonce = $_POST['_ajax_nonce'];
                if(wp_verify_nonce( $nonce, 'changeni-donation-add' )){
                    $amount = $_POST['donation_amount'];
                    $frequency = $_POST['donation_freq_radio'];
                    $result = changeni_add_donation($amount, $frequency);

                } else{
                    $result = 'Smart ass!';
                }
                break;
            default:
                $result = 'unrecognized command';
                break;
        }

        if(!is_array($result)){
            $result = array('message' => $result);
        }

        $result['success'] = true;
        $result['nonce'] = wp_create_nonce( 'changeni-donation-add' );
        $json_result = json_encode( $result);


        header( "Content-Type: application/json" );
        echo $json_result;
        die;

}


function changeni_add_donation($amount, $frequency){
    
    if(empty($amount)){
        return 'no amount specified';
    }

    if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/im', $amount) || $amount <= 0){
        return 'invalid amount';
    }

    if(empty($frequency)){
        return 'frequency not specified';
    }

    $frequency = strtolower($frequency);
    switch($frequency){
        case 'monthly':
            break;
        case 'one-time':
            break;
        default:
            return 'invalid frequency';
    }

    global $current_blog;
    global $current_site;

    $short_name = '';
     if (is_subdomain_install()) {
        $short_name = str_replace('.' . $current_site->domain, '', $current_blog->domain);
     }
     else{
         $short_name = str_replace('/', '', $current_blog->path);
     }

    $blog_id = $current_blog->blog_id;
    $_SESSION['changeni_cart'][$blog_id]['amount'] = $amount;
    $_SESSION['changeni_cart'][$blog_id]['frequency'] = $frequency;
    $_SESSION['changeni_cart'][$blog_id]['short_name'] = $short_name;
    $_SESSION['changeni_cart'][$blog_id]['name'] = get_blog_option( $blog_id, 'blogname' );
    $cart_total = get_changeni_cart_total($_SESSION['changeni_cart']);

    $result = array( 'message' => "Current donation is $$amount $frequency",
            'totalItems' => $cart_total->item_count ,
            'totalAmount' => $cart_total->amount_total);

    return $result;

}


function display_changeni_cart_summary(){
    global $current_site;

    //$changeni_cart = $_SESSION['changeni_cart'];

    $cart_total = get_changeni_cart_total($_SESSION['changeni_cart']);

    ?>
        <div id="changeni_cart">
            <a href="/" class="main_site_link">
                <img src="<?php echo WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) . '/images/hat.png'; ?>" id="changeni_logo"  alt="changeni logo"/>
                <h2><?php echo $current_site->site_name ; ?></h2>
            </a>
            <div class="changeni_cart_info" >Total amount: <span id="changeni_amount_total"><?php echo $cart_total->amount_total ; ?></span></div>
            <div class="changeni_cart_info" > Total items: <span id="changeni_item_count"><?php echo $cart_total->item_count ; ?></span></div>
            <div class="changeni_cart_info" id="info_message"></div>
        </div>
    <?php

}


function get_changeni_cart_total($changeni_cart = array()){
    $cart_total = new Changeni_Cart();

    $cart_total->total_items($changeni_cart);
    return $cart_total;
}


//data definition
class Changeni_Cart {
    public $item_count = 0;
    public $amount_total = 0;

    public function total_items($cart_items = array()){
        if(!empty($cart_items)){
            foreach($cart_items as $donation){
                $this->item_count++;
                $this->amount_total += $donation['amount'];
            }
        }
    }
}


/* update the database*/
function changeni_update_database() {

    global  $wpdb;
    $table_name = $wpdb->prefix . CHANGENI_PAYMENTS_TABLE;

    //add the table if its not present (upgrade or reactivation)
   // if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $sql = "CREATE TABLE ".$table_name." (
            payment_id bigint(20) NOT NULL AUTO_INCREMENT,
            blog_id bigint(20) unsigned NOT NULL,
            blog_name varchar(200) NOT NULL default '',
            txn_id varchar(200) NOT NULL default '',
            payment_date datetime NOT NULL,
            payment_date_gmt datetime NOT NULL,
            first_name varchar(200) NOT NULL default '',
            last_name varchar(200) NOT NULL,
            email varchar(200) NOT NULL,
            payment_type varchar(20) NOT NULL,
            amount decimal(11,2) NOT NULL DEFAULT '0',
            PRIMARY KEY  (payment_id)
            ) $charset_collate;";
    $result = dbDelta($sql);

    $table_name = $wpdb->prefix . CHANGENI_LOGS_TABLE;
    $sql = "CREATE TABLE ".$table_name." (
            log_id bigint(20) NOT NULL AUTO_INCREMENT,
            message varchar(200) NOT NULL default '',
            raw_data longtext NOT NULL,
            PRIMARY KEY  (log_id)
            ) $charset_collate;";
    $result = dbDelta($sql);
    // }


}

/* initialize the plugin settings*/
function changeni_init() {

    //run the configuration only if at the root site
    //global $current_blog;
    global $current_site;

    //if($current_blog->path !== '/'){
    //    return;
    //}

    if(!is_main_site()){
        return;
    }
    //trigger refresh of rewrite rules
    global $wp_rewrite;

    $wp_rewrite->flush_rules();


    if(!get_site_option('changeni_paypal_account')){
	add_site_option('changeni_paypal_url', 'https://www.paypal.com/cgi-bin/webscr');
	add_site_option('changeni_ipn_url', 'http://' . $current_site->domain . '/changeni/paid/');
        add_site_option('changeni_thanks_page', 'http://' . $current_site->domain . '/changeni/thanks/');
        add_site_option('changeni_cancel_page', 'http://' . $current_site->domain . '/');
        add_site_option('changeni_paypal_account', '[Paypal email]');

    }

    changeni_update_database();
    add_site_option('changeni_db_version', '1.0.0');


}

/* register settings*/
function register_changeni_settings() {

    register_setting( 'changeni_settings', 'changeni_paypal_url', 'changeni_update_paypal_url_option' );
    register_setting( 'changeni_settings', 'changeni_ipn_url', 'changeni_update_ipn_url_option' );
    register_setting( 'changeni_settings', 'changeni_thanks_page', 'changeni_update_thanks_page_option' );
    register_setting( 'changeni_settings', 'changeni_cancel_page', 'changeni_update_cancel_page_option' );
    register_setting( 'changeni_settings', 'changeni_paypal_account', 'changeni_update_paypal_account_option' );
}

/* Update site option hack since register_setting isn't handling it*/
function changeni_update_paypal_url_option($option) {
    global $changeni_lock_paypal_url_option;

    if($changeni_lock_paypal_url_option){
        $changeni_lock_paypal_url_option = false;
    }
    else{
        $changeni_lock_paypal_url_option = true;
        update_site_option('changeni_paypal_url', $option);
    }

    return $option;
}

function changeni_update_ipn_url_option($option) {
    global $changeni_lock_ipn_url_option;

    if($changeni_lock_ipn_url_option){
        $changeni_lock_ipn_url_option = false;
    }
    else{
        $changeni_lock_ipn_url_option = true;
        update_site_option('changeni_ipn_url', $option);
    }

    return $option;
}

function changeni_update_thanks_page_option($option) {
    global $changeni_lock_thanks_page_option;

    if($changeni_lock_thanks_page_option){
        $changeni_lock_thanks_page_option = false;
    }
    else{
        $changeni_lock_thanks_page_option = true;
        update_site_option('changeni_thanks_page', $option);
    }

    return $option;
}

function changeni_update_cancel_page_option($option) {
    global $changeni_lock_cancel_page_option;

    if($changeni_lock_cancel_page_option){
        $changeni_lock_cancel_page_option = false;
    }
    else{
        $changeni_lock_cancel_page_option = true;
        update_site_option('changeni_cancel_page', $option);
    }

    return $option;
}

function changeni_update_paypal_account_option($option) {
    global $changeni_lock_paypal_account_option;

    if($changeni_lock_paypal_account_option){
        $changeni_lock_paypal_account_option = false;
    }
    else{
        $changeni_lock_paypal_account_option = true;
        update_site_option('changeni_paypal_account', $option);
    }

    return $option;
}


/* Configuration Screen*/
function changeni_admin_menu() {
        $menu_slug = 'changeni/changeni-ui.php';
        if(!is_main_site()){
            $menu_slug = 'changeni/changeni-donations.php';
        }
        add_menu_page( 'Changeni', 'Changeni', 'manage_options', $menu_slug, '', 'div');

        if(is_main_site()){
            add_submenu_page( $menu_slug, 'Changeni Settings', 'Settings', 'manage_options', 'changeni/changeni-ui.php');
        }

        add_submenu_page( $menu_slug, 'Donations', 'Donations', 'manage_options', 'changeni/changeni-donations.php');
	
	//call register settings function
	add_action( 'admin_init', 'register_changeni_settings' );
	$plugin = plugin_basename(__FILE__); 
	add_filter( 'plugin_action_links_' . $plugin, 'changeni_plugin_actions' );
}


/* Add Settings link to the plugins page*/
function changeni_plugin_actions($links) {
    $settings_link = '<a href="ms-admin.php?page=changeni/changeni-ui.php#changeni_options">Settings</a>';

    $links = array_merge( array($settings_link), $links);

    return $links;

}



?>