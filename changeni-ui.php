<?php

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


/* 
	File Information: Changeni ui page
*/

changeni_admin_page();

function changeni_admin_page() {
	
	if(!empty($_POST['uninstall'])) {
		changeni_remove_settings();
		return;
	}

        /*if(isset( $_POST['doaction'] ) || isset( $_POST['doaction2'] )){
            $bulk_ids = isset( $_POST['bulk_ids'] ) ? (array) $_POST['bulk_ids'] : array();
            $action = $_POST['required_action'] != -1 ? $_POST['required_action'] : $_POST['required_action2'];

            foreach ( $bulk_ids as $key => $id ) {
                changeni_do_ui_command($id, $action);
            }
        }
        else{
            $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
            $action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';

            changeni_do_ui_command($id, $action);
        }*/

        changeni_show_ui();
}
/*
function changeni_do_ui_command($id, $action){
    global  $wpdb;

    
    if($id > 0){
        $table_name = $wpdb->prefix . PENDING_HEADERS_TABLE;

        switch ( $action ) {
            case 'execute':
                $query = "SELECT * FROM {$table_name} WHERE header_id = {$id}";
                $header = $wpdb->get_results( $query, ARRAY_A );
                if($header){
                    $header = $header[0];
                    $required_action = $header['required_action'];
                    $blog_details = get_blog_details($header['blog_id']);
                    if($blog_details){
                        if($required_action == 'add'){
                             changeni_update_host_header($header['blog_id'], $blog_details->domain, $required_action);
                        }
                    }
                    else{
                        if($required_action == 'remove'){
                             changeni_update_host_header($header['blog_id'], $header['header_name'], $required_action);
                        }
                    }

                    $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE header_id = %d", $id ) );

                }
                break;
            case 'delete':
                $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE header_id = %d", $id ) );

                break;
        }
    }
}
*/

function changeni_remove_settings(){
	if($_POST['uninstall'] == 'UNINSTALL Changeni'){
		//run the configuration only if at the root site
                //global $current_blog;

                //if($current_blog->path !== '/'){
                if(!is_main_site()){
                    ?>
			<div class="wrap">
				<h2>Cannot Uninstall</h2>
				<p class="deactivation_message">
					Uninstall can only be done from the main site admin.
				</p>
			</div>
                    <?php
                    return;
                }
                ?>
			<div id="message" class="updated fade">
				<?php 
					$changeni_options = array('Paypal Url' => 'changeni_paypal_url',
								'IPN Url' => 'changeni_ipn_url',
								'Thanks Page' => 'changeni_thanks_page',
								'Cancel Page' => 'changeni_cancel_page',
								'Paypal Account' => 'changeni_paypal_account',
								'Recurrence Period' => 'changeni_recurrence_period',
								'Paypal API Url' => 'changeni_paypal_api_url',
								'Paypal API Version' => 'changeni_paypal_api_version',
								'Paypal API Username' => 'changeni_paypal_api_username',
								'Paypal API Password' => 'changeni_paypal_api_password',
								'Paypal API Signature' => 'changeni_paypal_api_signature',
								'DB version' => 'changeni_db_version');
					foreach($changeni_options as $option_key => $option_value){
						$delete_setting = delete_site_option($option_value);
                                                //there may be unused local options:
                                                delete_option($option_value);
						if($delete_setting) {
							?> 
							<p class="setting_removed">Setting: <?php echo $option_key; ?> => Removed</p>
							<?php
						} 
						else {
							?> 
							<p class="setting_not_removed">Setting: <?php echo $option_key; ?> => Not Removed </p>
							<?php
						}
					}

                                        //remove tables
                                        global  $wpdb;

                                        $table_list = array(CHANGENI_PAYMENTS_TABLE,
                                                            CHANGENI_LOGS_TABLE);
                                        foreach($table_list as $table_name){
                                            $table_name = $wpdb->prefix . $table_name;
                                            if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                                                $wpdb->query("DROP TABLE IF EXISTS $table_name");
                                                ?>
                                                    <p class="setting_removed">Table: <?php echo $table_name; ?> => Removed</p>
                                                <?php
                                            }
                                            else{
                                                ?>
                                                    <p class="setting_not_removed">Table: <?php echo $table_name; ?> => Not found</p>
                                                <?php
                                            }
                                        }
                                        
				?>
			</div>
		<?php
		
		$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=changeni%2Fchangeni.php';
		if(function_exists('wp_nonce_url')) { 
			$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_changeni/changeni.php');
		}
		
		?>
			<div class="wrap">
				<h2>Deactivate Changeni</h2>
				<p class="deactivation_message">
					<a href="<?php echo $deactivate_url; ?>">Click Here</a> to deactivate the Changeni plugin automatically
				</p>
			</div>
		<?php
	}
}

function changeni_show_ui() {
		
    ?>

        <div id="changeni-admin" class="wrap">
            <?php screen_icon(); ?>
            <h2>Changeni</h2>
            <ul id="tabs" class="changeni_tabs">
                <li><a href="#changeni_options">Settings</a></li>
                <li><a href="#changeni_uninstall">Uninstall</a></li>
            </ul>

            <!-- Settings Form -->
            <div id="changeni_options">
                 <h3>Settings</h3>
                 <?php changeni_options_tab(); ?>
            </div>

            <!-- Uninstall Plugin -->
            <div id="changeni_uninstall">
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">

                    <h3>Uninstall Changeni plugin</h3>
                    <p>
                            The uninstall action removes all Changeni plugin settings that have been saved in your WordPress database. Use this prior to deactivating the plugin.
                    </p>
                    <p class="warning">
                            Please note that the deleted settings cannot be recovered. Proceed only if you do not wish to use these settings any more.
                    </p>
                    <p class="uninstall_confirmation">
                            <input type="submit" name="uninstall" value="UNINSTALL Changeni" class="button" onclick="return confirm('You Are About To Uninstall Changeni From WordPress.\n\n Choose [Cancel] To Stop, [OK] To Uninstall.')" />
                    </p>
                </form>
            </div>
        </div>

    <?php
}

function changeni_options_tab(){
    ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'changeni_settings' ); ?>
                    <?php $changeni_paypal_url = get_site_option('changeni_paypal_url'); ?>
                    <?php $changeni_ipn_url = get_site_option('changeni_ipn_url'); ?>
                    <?php $changeni_thanks_page = get_site_option('changeni_thanks_page'); ?>
                    <?php $changeni_cancel_page = get_site_option('changeni_cancel_page'); ?>
                    <?php $changeni_paypal_account = get_site_option('changeni_paypal_account'); ?>
                    <?php $changeni_recurrence_period = get_site_option('changeni_recurrence_period'); ?>
                    <?php $changeni_paypal_api_url = get_site_option('changeni_paypal_api_url'); ?>
                    <?php $changeni_paypal_api_version = get_site_option('changeni_paypal_api_version'); ?>
                    <?php $changeni_paypal_api_username = get_site_option('changeni_paypal_api_username'); ?>
                    <?php $changeni_paypal_api_password = ''; ?>
                    <?php $changeni_paypal_api_signature = get_site_option('changeni_paypal_api_signature'); ?>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Paypal Url</th>
                            <td>
                                  <input name="changeni_paypal_url" type="text" value="<?php echo $changeni_paypal_url; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">IPN Url</th>
                            <td>
                                 <input name="changeni_ipn_url" type="text" value="<?php echo $changeni_ipn_url; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Thanks Page</th>
                            <td>
                                 <input name="changeni_thanks_page" type="text" value="<?php echo $changeni_thanks_page; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Cancel Page</th>
                            <td>
                                 <input name="changeni_cancel_page" type="text" value="<?php echo $changeni_cancel_page; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Paypal Account</th>
                            <td>
                                 <input name="changeni_paypal_account" type="text" value="<?php echo $changeni_paypal_account; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Recurrence Period</th>
                            <td>
                                 <input name="changeni_recurrence_period" type="text" value="<?php echo $changeni_recurrence_period; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Paypal API Url</th>
                            <td>
                                 <input name="changeni_paypal_api_url" type="text" value="<?php echo $changeni_paypal_api_url; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Paypal API Version</th>
                            <td>
                                 <input name="changeni_paypal_api_version" type="text" value="<?php echo $changeni_paypal_api_version; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Paypal API Username</th>
                            <td>
                                 <input name="changeni_paypal_api_username" type="text" value="<?php echo $changeni_paypal_api_username; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Paypal API Password</th>
                            <td>
                                 <input name="changeni_paypal_api_password" type="text" value="<?php echo $changeni_paypal_api_password; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Paypal API Signature</th>
                            <td>
                                 <input name="changeni_paypal_api_signature" type="text" value="<?php echo $changeni_paypal_api_signature; ?>"  />
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                         <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                    </p>
                </form>
    <?php
}

?>