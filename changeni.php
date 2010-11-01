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
//define('PENDING_HEADERS_TABLE','changeni_pending_headers');
//if(empty($changeni_cart)){
//    $changeni_cart = array();
//}


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
	add_action('admin_print_scripts', 'changeni_load_scripts' );
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
        add_action( 'wp_footer', 'display_changeni_cart' );
	
}

//Create rewrite rules
add_filter('rewrite_rules_array', 'add_changeni_rewrite_rules');

//insert query parameter
add_filter('query_vars','add_changeni_query_var');

//intercept page title
add_filter ( 'the_posts', 'changeni_page' );



/* Load js files*/
function changeni_load_scripts() {
    global $current_blog;

    wp_enqueue_script('changeni', WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) . '/changeni.js', array('jquery-ui-tabs', 'json2'), '1.0');

    $ajax_url = $current_blog->path . 'wp-admin/admin-ajax.php';
    wp_localize_script( 'changeni', 'changeniJsData', array( 'ajaxUrl' => $ajax_url ) );
}

/* Load css files*/
function changeni_load_stylesheets() {
	$style_file = plugins_url('changeni/changeni.css');
	echo '<link rel="stylesheet" type="text/css" href="' . $style_file . '" />' . PHP_EOL;

}


/* modify page title and content*/
function changeni_page($posts) {
    global $wp_query;
   // global $post;
    
    if (isset ( $wp_query->query_vars['show_page'] )){
        
       // if($post->post_title == $title){
            $request = $wp_query->query_vars['show_page'];
            $request = trim(strtolower($request));
            $page_name = 'changeni-' . $request;
            //$page_title = '';
            switch($request){
                case 'cart':
                    $posts[0] = changeni_cart_page($posts[0]);
                    break;
                case 'checkout':
                    $page_title = 'Check-out';
                //default:
                //    $page_title = $title;
                    break;
            }

           // return $page_title;
        //}
    }

    return $posts;
}


function changeni_cart_page($cart_page){
    //$cart_page = new stdClass();

    $cart_page->ID = -1;
    $cart_page->post_title = 'Donations Cart';
    $cart_page->post_content = changeni_cart_page_content();
    $cart_page->post_date = current_time('mysql');
    $cart_page->post_date_gmt = current_time('mysql', 1);
    $cart_page->post_category = 0;
    $cart_page->post_excerpt = '';
    $cart_page->comment_status = 'closed';
    $cart_page->ping_status = 'closed';
    $cart_page->post_password = '';
    $cart_page->post_name = $page_name;
    $cart_page->to_ping = '';
    $cart_page->pinged = '';
    $cart_page->post_modified = current_time('mysql');
    $cart_page->post_modified_gmt = current_time('mysql', 1);
    $cart_page->post_content_filtered = '';
    $cart_page->post_parent = 0;
    $cart_page->guid = get_bloginfo('wpurl') . '/' . $page_name;
    $cart_page->menu_order = 0;
    $cart_page->post_type = 'post';
    $cart_page->post_mime_type = '';
    $cart_page->comment_count = 0;

    return $cart_page;
}

function changeni_cart_page_content(){
    $cart_items = $_SESSION['changeni_cart'];
    ob_start();
    
    ?>
        <div id="donations_cart">
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
                                                        <?php echo get_blog_option( $blog_id, 'blogname' ); ?>
                                                     
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
                    } // end if ($header_list)
                    ?>

                </tbody>
            </table>
        </div>
    
    <?php
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
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

/* update the database*/
function changeni_update_database() {

    global  $wpdb;
    $table_name = $wpdb->prefix . PENDING_HEADERS_TABLE;

    //add the table if its not present (upgrade or reactivation)
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE ".$table_name." (
                header_id bigint(20) NOT NULL AUTO_INCREMENT,
                blog_id bigint(20) unsigned NOT NULL,
                header_name varchar(200) NOT NULL default '',
                required_action varchar(20) NOT NULL,
                last_error longtext NOT NULL,
                PRIMARY KEY  (header_id)
                ) $charset_collate;";
        $result = dbDelta($sql);
     }

    //populate the table with current hostnames
    $query = "SELECT blog_id, domain FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";
    $query .= " ORDER BY {$wpdb->blogs}.blog_id ";
    $blog_list = $wpdb->get_results( $query, ARRAY_A );

    if ( $blog_list ) {
            foreach ( $blog_list as $blog ) {

                $rows_affected = $wpdb->insert( $table_name,
                                                array( 'blog_id' => $blog['blog_id'],
                                                        'header_name' => $blog['domain'],
                                                        'required_action' => 'add',
                                                        'last_error' => '' ) );
            }
	}
}

/* initialize the plugin settings*/
function changeni_init() {
    //trigger refresh of rewrite rules
    global $wp_rewrite;

    $wp_rewrite->flush_rules();

 return;
    if(!get_site_option('changeni_website_name')){
	add_site_option('changeni_website_name', '[IIS Website name]');
	add_site_option('changeni_server_ip', '[IIS Website IP address]');

    }

    changeni_update_database();
    add_site_option('changeni_db_version', '1.0.0');


}

/* Configuration Screen*/
function changeni_admin_menu() {
        add_menu_page( 'Changeni', 'Changeni', 'manage_options', 'changeni', 'changeni/changeni-ui.php');
        add_submenu_page( 'changeni', 'Changeni Settings', 'Settings', 'manage_options', 'changeni/changeni-ui.php');
	
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

            $cart_url = $current_site->path . 'changeni/cart/';
            $changeni_nonce = wp_create_nonce( 'changeni-donation-add' );

            $output = '';


            $output .= "<div class='changeni_donation_box' id='changeni_donation_box-$id'>" . PHP_EOL;
            $output .= "    <h2>Donate to this organization</h2>" . PHP_EOL;
            $output .= "    <div class='changeni_donation_box_content' id='changeni_donation_box_content'>" . PHP_EOL;
            $output .= "        <span class='changeni_donation_form' id='changeni_donation_form'>" . PHP_EOL;
            $output .= '            <form method="post" action="">' . PHP_EOL;
            $output .= '                <input name="cmd" id="changeni_cmd" type="hidden" value="add_donation"  />';
            $output .= '                <input name="action" id="changeni_action" type="hidden" value="changeni_action"  />';
            $output .= '                <input name="_ajax_nonce" id="changeni_ajax_nonce" type="hidden" value="' . $changeni_nonce . '"  />';
            $output .= '                <label for="donation_amount">US$</label><input name="donation_amount" id="donation_amount" type="text" value=""  />';
            $output .= '                <input type="submit" class="button" value="Give" />' . PHP_EOL;
            $output .= '                <img src="' . WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) . '/images/ajax_busy.gif' . '" id="ajax_busy_img"  alt="changeni submitting"/>' . PHP_EOL;
            $output .= "                <span class='donation_freq' id='donation_freq'>" . PHP_EOL;
            $output .= '                    <br /><input type="radio" id="donation_freq_radio_monthly" name="donation_freq_radio" class="donation_freq_radio" value="monthly" checked="checked" /><label class="donation_freq_label" for="donation_freq_radio_monthly">monthly</label>' . PHP_EOL;
            $output .= '                    <input type="radio" id="donation_freq_radio_once" name="donation_freq_radio" class="donation_freq_radio" value="one-time" /><label class="donation_freq_label" for="donation_freq_radio_once">one-time</label>' . PHP_EOL;
            $output .= '                </span>' . PHP_EOL;
            $output .= '            </form>' . PHP_EOL;
            $output .= '        </span>' . PHP_EOL;
            $output .= "        <span class='action_links' id='action_links'>" . PHP_EOL;
            $output .= '            <a href="' . $cart_url . '">veiw cart</a> &nbsp; | &nbsp; checkout' . PHP_EOL;
            $output .= '        </span>' . PHP_EOL;
            //$output .= '        <div id="info_message"></div>' . PHP_EOL;
            $output .= '    </div>' . PHP_EOL;
            $output .= '</div>' . PHP_EOL;

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
    global $current_blog;
    //global $changeni_cart;

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

    $blog_id = $current_blog->blog_id;
    $_SESSION['changeni_cart'][$blog_id]['amount'] = $amount;
    $_SESSION['changeni_cart'][$blog_id]['frequency'] = $frequency;
    $cart_total = get_changeni_cart_total($_SESSION['changeni_cart']);

    $result = array( 'message' => "Current donation is $$amount $frequency",
            'totalItems' => $cart_total->item_count ,
            'totalAmount' => $cart_total->amount_total);

    return $result;

}


function display_changeni_cart(){
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



// =======================================   Foreign code beyond this point!!  =============================================


/* register settings*/
function register_changeni_settings() {
return;
    register_setting( 'changeni_settings', 'changeni_website_name', 'changeni_update_website_name_option' );
	register_setting( 'changeni_settings', 'changeni_server_ip', 'changeni_update_server_ip_option' );
}

/* Update site option hack since register_setting isn't handling it*/
function changeni_update_website_name_option($option) {
    global $changeni_lock_website_name_option;

    if($changeni_lock_website_name_option){
        $changeni_lock_website_name_option = false;
    }
    else{
        $changeni_lock_website_name_option = true;
        update_site_option('changeni_website_name', $option);
    }

    return $option;
}

function changeni_update_server_ip_option($option) {
    global $changeni_lock_server_ip_option;

    if($changeni_lock_server_ip_option){
        $changeni_lock_server_ip_option = false;
    }
    else{
        $changeni_lock_server_ip_option = true;
        update_site_option('changeni_server_ip', $option);
    }

    return $option;
}





/* Register new blog*/
function changeni_add_domain($blog_id, $user_id, $domain) {
    changeni_update_host_header($blog_id, $domain, 'add');
    
}
add_action( 'wpmu_new_blog', 'changeni_add_domain', 10, 3 );


/* Remove deleted blog*/
function changeni_remove_domain($blog_id) {
    $blog_details = get_blog_details($blog_id);
    changeni_update_host_header($blog_id, $blog_details->domain, 'remove');

}
add_action( 'delete_blog', 'changeni_remove_domain', 10, 1 );


function changeni_update_host_header($blog_id, $domain, $cmd){
    $hostname = $domain ;
    $website = get_site_option('changeni_website_name');
    $ip = get_site_option('changeni_server_ip');
    $reg_cmd = 'iisbroker.exe' . ' /action:' . $cmd . ' /website:"' . $website . '" /hostname:' . $hostname . ' /ip:' . $ip ;
    $output = array();
    $error = 0;
    $result = '';
    exec($reg_cmd . " 2>&1", $output, $error);
    if (($error != 0) && empty($output)){
            $last_error = error_get_last();
            $error = $last_error['message'];
    }
    else{
            $error = '';
            switch (strtolower($output[0])){
                case 'added':
                    $result = 'The domain ' . $hostname . ' has been added to IIS';
                    break;
                case 'removed':
                    $result = 'The domain ' . $hostname . ' has been removed from IIS';
                    break;
                case 'exists':
                    $result = 'The domain ' . $hostname . ' already exists on IIS';
                    break;
                case 'missing':
                    $result = 'The domain ' . $hostname . ' is missing from IIS';
                    break;
                default:
                    $error = implode(PHP_EOL, $output);
                    break;
            }
    }

    if(!empty($error)){
        global  $wpdb;
        $table_name = $wpdb->prefix . PENDING_HEADERS_TABLE;

        $rows_affected = $wpdb->insert( $table_name,
                                                array( 'blog_id' => $blog_id,
                                                    'header_name' => $domain,
                                                    'required_action' => $cmd,
                                                    'last_error' => $error ) );
        return;
    }
    ?>
        <div id="changeni_message" class="updated">
            <?php echo print_r($result, 1); ?>

        </div>

    <?php
    
}

?>