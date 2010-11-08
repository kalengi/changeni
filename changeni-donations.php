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

        if(isset( $_POST['doaction'] ) || isset( $_POST['doaction2'] )){
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
        }

        changeni_show_ui();
}

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

function changeni_remove_settings(){
	if($_POST['uninstall'] == 'UNINSTALL Changeni'){
		//run the configuration only if at the root site
                global $current_blog;

                if($current_blog->path !== '/'){
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

                                        //remove tablels
                                        global  $wpdb;

                                        $table_name = $wpdb->prefix . CHANGENI_PAYMENTS_TABLE;
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
                <li><a href="#changeni_options">IIS Website Options</a></li>
                <li><a href="#changeni_pending_headers">Pending Headers</a></li>
                <li><a href="#changeni_uninstall">Uninstall</a></li>
            </ul>

            <!-- Options Form -->
            <div id="changeni_options">
                 <h3>IIS Website Options</h3>
                 <?php changeni_options_tab(); ?>
            </div>

            <!-- Pending Headers List -->
            <div id="changeni_pending_headers">
                <h3>Pending Headers</h3>
                <?php changeni_pending_header_list_tab(); ?>
                
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

function changeni_pending_header_list_tab(){
    global  $wpdb;
    
    $pagenum = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
    $pagenum =  empty($pagenum) ? 1 : $pagenum;
    $per_page = 15;

    $table_name = $wpdb->prefix . PENDING_HEADERS_TABLE;
    $query = "SELECT * FROM {$table_name} ";
    $query .= " ORDER BY {$table_name}.header_name ";

    $total = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT(header_id)', $query ) );

    $query .= " LIMIT " . intval( ( $pagenum - 1 ) * $per_page ) . ", " . intval( $per_page );
    $header_list = $wpdb->get_results( $query, ARRAY_A );

    $num_pages = ceil($total / $per_page);
    $page_links = paginate_links( array(
            'base' => add_query_arg( 'paged', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;' ),
            'next_text' => __( '&raquo;' ),
            'total' => $num_pages,
            'current' => $pagenum
    ));

    $action_page = $_SERVER['PHP_SELF'] . '?page=' . plugin_basename(__FILE__);

    ?>
                <form id="form-pending-header-list" action="<?php echo $action_page; ?>" method="post">
                    <div class="tablenav">
                        <div class="alignleft actions">
                                <select name="required_action">
                                        <option value="-1" selected="selected"><?php _e( 'Bulk Actions' ); ?></option>
                                        <option value="execute">Execute</option>
                                        <option value="delete">Delete</option>
                                </select>
                                <input type="submit" value="Apply" name="doaction" id="doaction" class="button-secondary action" />
                                <?php wp_nonce_field( 'bulk-changeni-headers', '_wpnonce_bulk-changeni-headers' ); ?>
                        </div>

                        <?php if ( $page_links ) { ?>
                        <div class="tablenav-pages">
                            <?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
                            number_format_i18n( ( $pagenum - 1 ) * $per_page + 1 ),
                            number_format_i18n( min( $pagenum * $per_page, $total ) ),
                            number_format_i18n( $total ),
                            $page_links
                            ); echo $page_links_text; ?>
                        </div>
                        <?php } ?>

                    </div>
                    <div class="clear"></div>

                    <?php
                    // define the columns to display, the syntax is 'internal name' => 'display name'
                    $columns = array(
                            'blog_id'           => __( 'Blog Id' ),
                            'header_name'     => __( 'Domain' ),
                            'required_action'  => __( 'Pending Action'),
                            'last_error'   => __( 'Last Error')
                    );


                    ?>
                    <table class="widefat">
                        <thead>
                                <tr>
                                <th class="manage-column column-cb check-column" id="cb" scope="col">
                                        <input type="checkbox" />
                                </th>
                                <?php
                                $col_url = '';
                                foreach($columns as $column_id => $column_display_name) {
                                        $column_link = "<a href='";
                                        $order2 = '';
                                        if ( $order_by == $column_id )
                                                $order2 = ( $order == 'DESC' ) ? 'ASC' : 'DESC';

                                        $column_link .= esc_url( add_query_arg( array( 'order' => $order2, 'paged' => $pagenum, 'sortby' => $column_id ), remove_query_arg( array('action', 'updated'), $_SERVER['REQUEST_URI'] ) ) );
                                        $column_link .= "'>{$column_display_name}</a>";
                                        $col_url .= '<th scope="col">' . $column_link . '</th>';
                                }
                                echo $col_url ?>
                                </tr>
                        </thead>
                        <tfoot>
                                <tr>
                                <th class="manage-column column-cb check-column" id="cb1" scope="col">
                                        <input type="checkbox" />
                                </th>
                                        <?php echo $col_url ?>
                                </tr>
                        </tfoot>
                        <tbody id="changeni-pending-header-list" class="list:site">
                            <?php
                            if ( $header_list ) {
                                $class = '';
                                foreach ( $header_list as $header ) {
                                    $class = ( 'alternate' == $class ) ? '' : 'alternate';
                                    echo "<tr class='$class'>";
                                    foreach ( $columns as $column_name => $column_display_name ) {
                                        switch ( $column_name ) {
                                            case 'blog_id':
                                                ?>
                                                        <th scope="row" class="check-column">
                                                                <input type="checkbox" id="blog_header_<?php echo $header['header_id'] ?>" name="bulk_ids[]" value="<?php echo esc_attr( $header['header_id'] ) ?>" />
                                                        </th>
                                                        <th valign="top" scope="row">
                                                                <?php echo $header['blog_id'] ?>
                                                        </th>
                                                <?php
                                                break;
                                            case 'header_name':
                                                ?>
                                                    <td class="column-title">
                                                        <a href="<?php echo esc_url( admin_url( 'ms-sites._php?action=execute&amp;id=' . $header['header_id'] ) ); ?>" class="edit"><?php echo $header['header_name']; ?></a>
                                                        <?php
                                                            // Preordered.
                                                            $actions = array(
                                                                    'execute' => '',
                                                                    'delete' => ''
                                                            );

                                                            $actions['execute'] = '<span class="execute"><a href="' . esc_url( $action_page . '&amp;action=execute&amp;id=' . $header['header_id'] ) . '">' . __( 'Execute' ) . '</a></span>';
                                                            $actions['delete'] = '<span class="delete"><a href="' . esc_url( $action_page . '&amp;action=delete&amp;id=' . $header['header_id'] ) . '">' . __( 'Delete' ) . '</a></span>';
                                                            $actions = array_filter( $actions );
                                                            if ( count( $actions ) ) : ?>
                                                            <div class="row-actions">
                                                                    <?php echo implode( ' | ', $actions ); ?>
                                                            </div>
                                                            <?php endif; ?>

                                                    </td>
                                                <?php
                                                break;
                                            case 'required_action':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $header['required_action']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'last_error':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $header['last_error']; ?>
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
                                        <td colspan="<?php echo (int) count( $columns ); ?>"><?php _e( 'No pending headers found.' ) ?></td>
                                </tr>
                            <?php
                            } // end if ($header_list)
                            ?>

                        </tbody>
                    </table>
                    <div class="tablenav">
                            <?php
                            if ( $page_links )
                                    echo "<div class='tablenav-pages'>$page_links_text</div>";
                            ?>

                            <div class="alignleft actions">
                                <select name="required_action2">
                                        <option value="-1" selected="selected"><?php _e( 'Bulk Actions' ); ?></option>
                                        <option value="execute">Execute</option>
                                        <option value="delete">Delete</option>
                                </select>
                                <input type="submit" value="Apply" name="doaction2" id="doaction2" class="button-secondary action" />
                            </div>
                            <br class="clear" />
                    </div>
                </form>
   <?php
}
function changeni_options_tab(){
    ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'changeni_settings' ); ?>
                    <?php $changeni_website_name = get_site_option('changeni_website_name'); ?>
                    <?php $changeni_server_ip = get_site_option('changeni_server_ip'); ?>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Website description</th>
                            <td>
                                  <input name="changeni_website_name" type="text" value="<?php echo $changeni_website_name; ?>"  />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Server IP</th>
                            <td>
                                 <input name="changeni_server_ip" type="text" value="<?php echo $changeni_server_ip; ?>"  />
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