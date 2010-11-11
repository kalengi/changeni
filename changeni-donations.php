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
	File Information: Changeni donations page
*/

changeni_report_page();

function changeni_report_page() {
	$filter = changeni_filter_donations();
	

        changeni_show_report($filter);
}



function changeni_filter_donations($skip_date_filter = false){
	global $current_blog;
        global $wpdb;

        $filter = '';
        if(!is_main_site()){
            $filter = $wpdb->prepare( "blog_id = %d", $current_blog->blog_id );
        }

        if(isset($_POST['m']) && !$skip_date_filter) {
            $month_filter = $_POST['m'];
            if($month_filter > 0){
                $date_filter = $wpdb->prepare( " CONCAT(YEAR(payment_date), LPAD(MONTH(payment_date),2,'0')) = '%s'", $month_filter );

                if(empty($filter)){
                    $filter = $date_filter;
                }
                else{
                    $filter .= " && ($date_filter) ";
                }
            }
	}
        return $filter;

}

function changeni_show_report($filter) {
		
    ?>

        <div id="changeni-report" class="wrap">
            <?php screen_icon(); ?>
            <h2>Donations</h2>
            
            <!-- Donations List -->
            <div id="changeni_donations_list">
                <?php 
                    if(is_main_site()){
                        changeni_donations_summary($filter);
                    }
                    else{
                        changeni_donations_list($filter);
                    }
                ?>
                
            </div>

        </div>

    <?php
}

function changeni_donations_summary($filter){
    global  $wpdb;
    global $wp_locale;


    $pagenum = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
    $pagenum =  empty($pagenum) ? 1 : $pagenum;
    $per_page = 15;


    $wpdb_prefix = $wpdb->prefix;
    if(!is_main_site()){
        $wpdb_prefix = $wpdb->get_blog_prefix( 1 );
    }
    $table_name = $wpdb_prefix . CHANGENI_PAYMENTS_TABLE;
//SELECT `blog_name`, SUM(`amount`) Donations FROM `wp_changeni_payments` WHERE `payment_date` > '2010-11-05 15:35:57' GROUP BY `blog_name`
    $query = "SELECT blog_name, SUM(amount) donations FROM {$table_name} ";
    if(!empty($filter)){
        $query .= " WHERE {$filter}";

    }
   
    $total = $wpdb->get_var( str_replace( 'SELECT blog_name, SUM(amount) donations', 'SELECT COUNT(DISTINCT blog_name)', $query ) );

    $total_amount = $wpdb->get_var( str_replace( 'SELECT blog_name, SUM(amount) donations', 'SELECT SUM(amount)', $query ) );

    $query .= " GROUP BY blog_name";

    $query .= " LIMIT " . intval( ( $pagenum - 1 ) * $per_page ) . ", " . intval( $per_page );
    $donations_summary = $wpdb->get_results( $query, ARRAY_A );



    $num_pages = ceil($total / $per_page);
    $page_links = paginate_links( array(
            'base' => add_query_arg( 'paged', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;' ),
            'next_text' => __( '&raquo;' ),
            'total' => $num_pages,
            'current' => $pagenum
    ));

    $action_page = $current_blog->path . $_SERVER['PHP_SELF'];
    $action_page = str_replace('//', '/', $action_page);
    $action_page .= '?page=' . plugin_basename(__FILE__);

    ?>
                <form id="form-changeni-donations-list" action="<?php echo $action_page; ?>" method="post">
                    <div class="tablenav">
                        <div class="alignleft ">
                            <?php // view filters
                                
                                $query = "SELECT DISTINCT YEAR(payment_date) AS pay_year, MONTH(payment_date) AS pay_month FROM {$table_name}";
                                $ommit_date_filter = changeni_filter_donations(true);
                                if(!empty($ommit_date_filter)){
                                    $query .= " WHERE {$ommit_date_filter}";

                                }
                                $query .= " ORDER BY {$table_name}.payment_date DESC";

                                $month_filter = $wpdb->get_results( $query);

                                $month_count = count($month_filter);

                                if ( $month_count && !( 1 == $month_count && 0 == $month_filter[0]->pay_month ) ) {
                                    $m = isset($_POST['m']) ? (int)$_POST['m'] : 0;
                                    ?>
                                    <select name='m'>
                                    <option<?php selected( $m, 0 ); ?> value='0'><?php _e('All dates'); ?></option>
                                    <?php
                                        foreach ($month_filter as $month_filter_row) {
                                                if ( $month_filter_row->pay_year == 0 ){
                                                        continue;
                                                }
                                                $month_filter_row->pay_month = zeroise( $month_filter_row->pay_month, 2 );

                                                if ( $month_filter_row->pay_year . $month_filter_row->pay_month == $m ){
                                                        $default = ' selected="selected"';
                                                }
                                                else{
                                                        $default = '';
                                                }

                                                echo "<option$default value='" . esc_attr("$month_filter_row->pay_year$month_filter_row->pay_month") . "'>";
                                                echo $wp_locale->get_month($month_filter_row->pay_month) . " $month_filter_row->pay_year";
                                                echo "</option>\n";
                                        }
                                    ?>
                                    </select>
                                    <input type="submit" id="post-query-submit" value="<?php esc_attr_e('Filter'); ?>" class="button-secondary" />
                                <?php }
                             ?>
                            
                        </div>
                        <div class="clear"></div>

                        <div class="alignleft ">
                            <span class="donation_total">Total amount: $<?php echo $total_amount; ?></span>
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
                            'blog_name'           => __( 'Organization' ),
                            'donations'   => __( 'Amount')
                    );

                    if(!is_main_site()){
                        unset($columns['blog_name']);
                    }

                    ?>
                    <table class="widefat">
                        <thead>
                                <tr>
                                <th class="manage-column column-cb check-column" id="cb" scope="col">

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

                                </th>
                                        <?php echo $col_url ?>
                                </tr>
                        </tfoot>
                        <tbody id="changeni-donation-list" class="list:site">
                            <?php
                            if ( $donations_summary ) {
                                $class = '';
                                foreach ( $donations_summary as $donation ) {
                                    $class = ( 'alternate' == $class ) ? '' : 'alternate';
                                    ?>
                                        <tr class='$class'>
                                            <th valign="top" scope="row">
                                                    
                                            </th>
                                    <?php
                                    foreach ( $columns as $column_name => $column_display_name ) {
                                        switch ( $column_name ) {
                                            case 'blog_name':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['blog_name']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'donations':
                                                ?>
                                                        <td valign="top">
                                                                $<?php echo $donation['donations']; ?>
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
                                        <td colspan="<?php echo (int) count( $columns ) + 1; ?>"><?php _e( 'No donations found.' ) ?></td>
                                </tr>
                            <?php
                            } // end if ($donations_summary)
                            ?>

                        </tbody>
                    </table>
                    <div class="tablenav">
                            <?php
                            if ( $page_links )
                                    echo "<div class='tablenav-pages'>$page_links_text</div>";
                            ?>

                            <div class="alignleft ">
                                <span class="donation_total">Total amount: $<?php echo $total_amount; ?></span>
                            </div>

                            <br class="clear" />
                    </div>
                </form>
   <?php
}

function changeni_donations_list($filter){
    global  $wpdb;
    global $wp_locale;
    global $current_blog;

    
    $pagenum = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
    $pagenum =  empty($pagenum) ? 1 : $pagenum;
    $per_page = 15;


    $wpdb_prefix = $wpdb->prefix;
    if(!is_main_site()){
        $wpdb_prefix = $wpdb->get_blog_prefix( 1 );
    }
    $table_name = $wpdb_prefix . CHANGENI_PAYMENTS_TABLE;

    $query = "SELECT * FROM {$table_name} ";
    if(!empty($filter)){
        $query .= " WHERE {$filter}";
        
    }
    $query .= " ORDER BY {$table_name}.payment_date DESC";

    $total = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT(payment_id)', $query ) );

    $total_amount = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT SUM(amount)', $query ) );


    $query .= " LIMIT " . intval( ( $pagenum - 1 ) * $per_page ) . ", " . intval( $per_page );
    $donations_list = $wpdb->get_results( $query, ARRAY_A );



    $num_pages = ceil($total / $per_page);
    $page_links = paginate_links( array(
            'base' => add_query_arg( 'paged', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;' ),
            'next_text' => __( '&raquo;' ),
            'total' => $num_pages,
            'current' => $pagenum
    ));

    $action_page = $current_blog->path . $_SERVER['PHP_SELF'];
    $action_page = str_replace('//', '/', $action_page);
    $action_page .= '?page=' . plugin_basename(__FILE__);

    ?>
                <form id="form-changeni-donations-list" action="<?php echo $action_page; ?>" method="post">
                    <div class="tablenav">
                        <div class="alignleft ">
                            <?php // view filters
                                
                                $query = "SELECT DISTINCT YEAR(payment_date) AS pay_year, MONTH(payment_date) AS pay_month FROM {$table_name}";
                                $ommit_date_filter = changeni_filter_donations(true);
                                if(!empty($ommit_date_filter)){
                                    $query .= " WHERE {$ommit_date_filter}";

                                }
                                $query .= " ORDER BY {$table_name}.payment_date DESC";

                                $month_filter = $wpdb->get_results( $query);

                                $month_count = count($month_filter);

                                if ( $month_count && !( 1 == $month_count && 0 == $month_filter[0]->pay_month ) ) {
                                    $m = isset($_POST['m']) ? (int)$_POST['m'] : 0;
                                    ?>
                                    <select name='m'>
                                    <option<?php selected( $m, 0 ); ?> value='0'><?php _e('All dates'); ?></option>
                                    <?php
                                        foreach ($month_filter as $month_filter_row) {
                                                if ( $month_filter_row->pay_year == 0 ){
                                                        continue;
                                                }
                                                $month_filter_row->pay_month = zeroise( $month_filter_row->pay_month, 2 );

                                                if ( $month_filter_row->pay_year . $month_filter_row->pay_month == $m ){
                                                        $default = ' selected="selected"';
                                                }
                                                else{
                                                        $default = '';
                                                }

                                                echo "<option$default value='" . esc_attr("$month_filter_row->pay_year$month_filter_row->pay_month") . "'>";
                                                echo $wp_locale->get_month($month_filter_row->pay_month) . " $month_filter_row->pay_year";
                                                echo "</option>\n";
                                        }
                                    ?>
                                    </select>
                                    <input type="submit" id="post-query-submit" value="<?php esc_attr_e('Filter'); ?>" class="button-secondary" />
                                <?php }
                             ?>

                        </div>
                        <div class="clear"></div>

                        <div class="alignleft ">
                            <span class="donation_total">Total amount: $<?php echo $total_amount; ?></span>
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
                            'payment_id'           => __( '' ),
                            'blog_name'           => __( 'Organization' ),
                            'payment_date'     => __( 'Payment date' ),
                            'amount'   => __( 'Amount'),
                            'payment_type'   => __( 'Type'),
                            'first_name'  => __( 'First name'),
                            'last_name'   => __( 'Last name'),
                            'email'   => __( 'Email')
                    );

                    if(!is_main_site()){
                        unset($columns['blog_name']);
                    }

                    ?>
                    <table class="widefat">
                        <thead>
                                <tr>
                                <th class="manage-column column-cb check-column" id="cb" scope="col">
                                        
                                </th>
                                <?php
                                $col_url = '';
                                foreach($columns as $column_id => $column_display_name) {
                                        if($column_id == 'payment_id'){
                                            continue;
                                        }
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
                                        
                                </th>
                                        <?php echo $col_url ?>
                                </tr>
                        </tfoot>
                        <tbody id="changeni-donation-list" class="list:site">
                            <?php
                            if ( $donations_list ) {
                                $class = '';
                                foreach ( $donations_list as $donation ) {
                                    $class = ( 'alternate' == $class ) ? '' : 'alternate';
                                    echo "<tr class='$class'>";
                                    foreach ( $columns as $column_name => $column_display_name ) {
                                        switch ( $column_name ) {
                                            case 'payment_id':
                                                ?>
                                                        <th valign="top" scope="row">
                                                                <?php echo $donation['payment_id'] ?>
                                                        </th>
                                                <?php
                                                break;
                                            case 'blog_name':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['blog_name']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'payment_date':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['payment_date']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'amount':
                                                ?>
                                                        <td valign="top">
                                                                $<?php echo $donation['amount']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'payment_type':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['payment_type']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'first_name':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['first_name']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'last_name':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['last_name']; ?>
                                                        </td>
                                                <?php
                                                break;
                                            case 'email':
                                                ?>
                                                        <td valign="top">
                                                                <?php echo $donation['email']; ?>
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
                                        <td colspan="<?php echo (int) count( $columns ); ?>"><?php _e( 'No donations found.' ) ?></td>
                                </tr>
                            <?php
                            } // end if ($donations_list)
                            ?>

                        </tbody>
                    </table>
                    <div class="tablenav">
                            <?php
                            if ( $page_links )
                                    echo "<div class='tablenav-pages'>$page_links_text</div>";
                            ?>

                            <div class="alignleft ">
                                <span class="donation_total">Total amount: $<?php echo $total_amount; ?></span>
                            </div>

                            <br class="clear" />
                    </div>
                </form>
   <?php
}


?>