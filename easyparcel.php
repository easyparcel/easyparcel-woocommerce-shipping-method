<?php
/*
 * Plugin Name: EasyParcel - Delivery Made Easy
 * Plugin URI: https://easyparcel.com/
 * Description: EasyParcel plugin allows you to enable order fulfillment without leaving your store and allow your customer to pick their preferable courier during check out. To get started, activate EasyParcel plugin and proceed to Woocommerce > Settings > Shipping > EasyParcel Shipping to set up your Integration ID.
 * Version: 1.1.4
 * Author: EasyParcel
 * Author URI: https://www.easyparcel.com/
 * Developer: EasyParcel
 * Text Domain: woocommerce-extension
 * Domain Path: /languages
 *
 * WC requires at least: 4
 * WC tested up to: 6.9.3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/*
© 2021 All rights reserved by EasyParcel Sdn Bhd
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $jal_db_version;
$jal_db_version = '1.0';
/*
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Integration_Easyparcel')):
        class WC_Integration_Easyparcel {
            /**
             * Construct the plugin.
             */
            public function __construct() {
                include_once 'include/easyparcel_meta_box.php';
                include_once 'include/easyparcel_pickup_point.php';
                include_once 'include/easyparcel_bulk_fulfillment.php';
                // add_action('woocommerce_email_after_order_table', 'easyparcel_field_after_order_table', 10, 1);
                add_action('woocommerce_shipping_init', array($this, 'init'));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                // Ajax for shipping zone
                add_action( "wp_ajax_ep_shipping_zones_save_changes", 'ep_shipping_zones_save_changes');
                add_action( "wp_ajax_ep_shipping_zone_methods_save_changes", 'ep_shipping_zone_methods_save_changes');
                add_action( "wp_ajax_ep_courier_setting_save_changes", 'ep_courier_setting_save_changes');
                add_action( 'admin_menu', 'wpdocs_register_my_custom_menu_page' );
            }
            /**
             * Initialize the plugin.
             */
            public function init() {
                // start a session

                // Checks if WooCommerce is installed.
                add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
                if (class_exists('WC_Integration')) {
                    // Include our integration class.
                    include_once 'include/easyparcel_shipping.php';
                    include_once 'include/easyparcel_shipping_zone.php'; //shipping zone

                    $value = get_option('woocommerce_easyparcel_settings');
                    if (isset($value['enabled']) && $value['enabled'] =='yes') {
                        if (!class_exists('Easyparcel_Shipping_API')) {
                            include_once 'include/easyparcel_api.php';
                        }
                        Easyparcel_Shipping_API::init();
                        $auth = Easyparcel_Shipping_API::auth();
                        if ($auth == 'Success.') {
                            add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_zone_method'));
                        }else{
                            remove_filter('woocommerce_shipping_methods', array($this, 'add_shipping_zone_method'));
                        }
                    } else {
                        add_action('admin_notices', function () {
                            echo esc_html('<div id="message" class="notice notice-error is-dismissible"><p>Kindly setup and activate your EasyParcel Shipping '.'<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel') . '">Here</a></p>'.'</div>');
                        });
                        remove_filter('woocommerce_shipping_methods', array($this, 'add_shipping_zone_method'));
                    }
                    // register 1 tab for easy access
                    // add_filter('woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50); // temporary dont turn on first, not in requirement
                } else {
                    // throw an admin error if you like
                }

                // add the filter - Custom shipping method label
                add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'filter_woocommerce_cart_shipping_method_full_label' ), 10, 2 );


                // Order Part
                // add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed_integration' ), 10 , 3);
            }

            public function filter_woocommerce_cart_shipping_method_full_label( $label, $method ) {
                $new_label = '';

                switch ( $method->get_method_id() ) {
                    case 'easyparcel':
                        $new_label = '<img src="'.$method->meta_data['ep_courier_logo'].'" width="60" height="40" style="display:inline-block;border-radius:4px;border:1px solid #ccc;" /> '.$label;
                        // pickup point list
                        $new_label .= $this->ep_pickup_point_shipping($method, 0);
                    break;
                    default:
                        $new_label = $label;
                }
                
                return $new_label; 
            }

            public function ep_pickup_point_shipping($method, $index) {
                $pickup_available_methods = array() ;
                if (isset(WC()->session)) {
                    $pickup_available_methods = WC()->session->get("EasyParcel_Pickup_Available") ? WC()->session->get("EasyParcel_Pickup_Available") : array() ;
                }

                $field_id = "ep_pickup_point";

                $chosen = WC()->session->get('chosen_shipping_methods'); // The chosen methods
                $value = WC()->session->get($field_id);
                $value = WC()->session->__isset($field_id) ? $value : WC()->checkout->get_value('_' . $field_id);
                $options = array(); // Initializing
                $html = '';

                if (!empty($chosen) && $method->id === $chosen[$index] && in_array($method->id, $pickup_available_methods)) {
                    $field_options = WC()->session->get('EasyParcel_Pickup_'.$method->id);
                    $html .= '<div class="custom-ep_pickup_point">';
                    // Loop through field otions to add the correct keys
                    $html .= '<select name="ep_pickup_point" id="ep_pickup_point" class="select">';
                    foreach ($field_options as $key => $option_value) {
                        $option_key = ($key === 0) ? '' : $key;
                        $options[$option_key] = $option_value;
                        $selected = ( $option_key == $value ) ? 'selected' : '';
                        $html .= '<option value="'.$option_key.'" '.$selected.'>'.$option_value.'</option>';
                    }
                    $html .= '</select>';

                    $html .= '</div>';
                }
                return $html;
            }

            public function order_status_changed_integration($order_id, $pre_status, $next_status) {
                $data = (object) array();
                $data->order_id = $order_id;

                $status_list = array('completed');

                // if(in_array($next_status, $status_list)){
                try {
                    $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
                    $WC_Easyparcel_Shipping_Method->process_booking_order($data);

                } catch (Exception $e) {
                    $message = sprintf(__('Easyparcel status changed! Error: %s', 'easyparcel'), $e->getMessage());
                    wc_add_notice($message, "error");
                }
                // }
            }

            /**
             * For add in setting tab for easy access to plugin
             */
            public static function add_settings_tab($settings_tabs) {
                $settings_tabs['shipping&section=easyparcel'] = __('EasyParcel', 'easyparcel');
                return $settings_tabs;
            }

            public function add_shipping_method($methods) {
                if (is_array($methods)) {
                    $methods['easyparcel'] = 'WC_Easyparcel_Shipping_Method';
                }
                return $methods;
            }

            public function add_shipping_zone_method($methods) {
                if (is_array($methods)) {
                    $methods['easyparcel_zone'] = 'WC_Easyparcel_Shipping_Zone';
                }
                return $methods;
            }

            

            /**
             *  Add Settings link to plugin page
             */
            public function plugin_action_links($links) {
                return array_merge(
                    $links,
                    array('<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel') . '"> ' . __('Settings', 'easyparcel') . '</a>')
                );
            }
        }

        $WC_Integration_Easyparcel = new WC_Integration_Easyparcel(__FILE__);
        
        // finish install, create db and store
        include_once 'database/create.php'; // database

        // function easyparcel_field_after_order_table( $order ) {
        //     $shipping_provider = (!empty($order->get_meta('_ep_selected_courier'))) ? $order->get_meta('_ep_selected_courier') : '- ';
        //     $tracking_number = (!empty($order->get_meta('_ep_awb'))) ? '<u>'.$order->get_meta('_ep_awb').'</u>' : ' - ';
        //     $tracking_url = (!empty($order->get_meta('_ep_tracking_url'))) ? '<a href="'.$order->get_meta('_ep_tracking_url').'" target="_blank">' : '';
        //     echo '<p><strong>Fulfillment : </strong>'.$shipping_provider.'</p>';
        //     echo '<p><strong>Tracking :</strong> '.$tracking_url.$tracking_number.'</a></p>';
        // }

    endif;

}
function wpdocs_register_my_custom_menu_page() {
    add_menu_page(
        'easyparcel_shipping',
        'EasyParcel Shipping',
        'manage_options',
        'admin.php?page=wc-settings&tab=shipping&section=easyparcel',
        '',
        'dashicons-store',
        66
    );
    add_submenu_page( 'admin.php?page=wc-settings&tab=shipping&section=easyparcel', 'EasyParcel Courier Setting', 'EasyParcel Courier Setting','manage_options', 'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping');

}

function ep_shipping_zones_save_changes(){
    if (!class_exists('EP_Shipping_Zones')) {
        include_once 'include/ep_shipping_zones.php';
    }
    if(isset($_POST)){
        $_POST = sanitizeEverything('sanitize_text_field', $_POST);
    }
    
    if ( ! isset( $_POST['wc_shipping_zones_nonce'], $_POST['changes'] ) ) {
        wp_send_json_error( 'missing_fields' );
        wp_die();
    }

    if ( ! wp_verify_nonce( wp_unslash( $_POST['wc_shipping_zones_nonce'] ), 'wc_shipping_zones_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    // Check User Caps.
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    $changes_sanitized = wp_unslash( $_POST['changes'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    foreach ( $changes_sanitized as $zone_id => $data ) {
        $data = wp_unslash($data); // perform sanitize as requested
        if ( isset( $data['deleted'] ) ) {
            if ( isset( $data['newRow'] ) ) {
                // So the user added and deleted a new row.
                // That's fine, it's not in the database anyways. NEXT!
                continue;
            }
            EP_Shipping_Zones::delete_zone( $zone_id );
            continue;
        }

        $zone_data = array_intersect_key(
            $data,
            array(
                'zone_id'    => 1,
                'zone_order' => 1,
            )
        );

        if ( isset( $zone_data['zone_id'] ) ) {
            if (!class_exists('EP_Shipping_Zone')) {
                include_once 'include/ep_shipping_zone.php';
            }
            $zone = new EP_Shipping_Zone( $zone_data['zone_id'] );
            if ( isset( $zone_data['zone_order'] ) ) {
                $zone->set_zone_order( $zone_data['zone_order'] );
            }

            $zone->save();
        }
    }

    wp_send_json_success(
        array(
            'zones' => EP_Shipping_Zones::get_zones( 'json' ),
        )
    );
}

function ep_shipping_zone_methods_save_changes() {
    if(isset($_POST)){
        $_POST = sanitizeEverything('sanitize_text_field', $_POST);
    }

    if ( ! isset( $_POST['wc_shipping_zones_nonce'], $_POST['zone_id'], $_POST['changes'] ) ) {
        wp_send_json_error( 'missing_fields' );
        wp_die();
    }

    if ( ! wp_verify_nonce( wp_unslash( $_POST['wc_shipping_zones_nonce'] ), 'wc_shipping_zones_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    global $wpdb;

    $zone_id = wc_clean( wp_unslash( $_POST['zone_id'] ) );
    if (!class_exists('EP_Shipping_Zone')) {
        include_once 'include/ep_shipping_zone.php';
    }
    $zone    = new EP_Shipping_Zone( $zone_id );
    $changes = wp_unslash( $_POST['changes'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

    if ( isset( $changes['zone_name'] ) ) {
        $zone->set_zone_name( wc_clean( $changes['zone_name'] ) );
    }

    if ( isset( $changes['zone_locations'] ) ) {
        $zone->clear_locations( array( 'state', 'country', 'continent' ) );
        $locations = array_filter( array_map( 'wc_clean', (array) $changes['zone_locations'] ) );
        foreach ( $locations as $location ) {
            // Each posted location will be in the format type:code.
            $location_parts = explode( ':', $location );
            switch ( $location_parts[0] ) {
                case 'state':
                    $zone->add_location( $location_parts[1] . ':' . $location_parts[2], 'state' );
                    break;
                case 'country':
                    $zone->add_location( $location_parts[1], 'country' );
                    break;
                case 'continent':
                    $zone->add_location( $location_parts[1], 'continent' );
                    break;
            }
        }
    }

    if ( isset( $changes['zone_postcodes'] ) ) {
        $zone->clear_locations( 'postcode' );
        $postcodes = array_filter( array_map( 'strtoupper', array_map( 'wc_clean', explode( "\n", $changes['zone_postcodes'] ) ) ) );
        foreach ( $postcodes as $postcode ) {
            $zone->add_location( $postcode, 'postcode' );
        }
    }

    if ( isset( $changes['methods'] ) ) {
        foreach ( $changes['methods'] as $instance_id => $data ) {
            $method_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id = %d", $instance_id ) );

            if ( isset( $data['deleted'] ) ) {
                if ( $wpdb->delete( "{$wpdb->prefix}easyparcel_zones_courier", array( 'id' => $instance_id ) ) ) {
                    delete_option( $option_key );
                    // do_action( 'woocommerce_shipping_zone_method_deleted', $instance_id, $method_id, $zone_id );
                }
                continue;
            }

            $method_data = array_intersect_key(
                $data,
                array(
                    'method_order' => 1,
                    'enabled'      => 1,
                )
            );

            if ( isset( $method_data['method_order'] ) ) {
                $wpdb->update( "{$wpdb->prefix}easyparcel_zones_courier", array( 'courier_order' => absint( $method_data['method_order'] ) ), array( 'id' => absint( $instance_id ) ) );
            }

            if ( isset( $method_data['enabled'] ) ) {
                $is_enabled = absint( 'yes' === $method_data['enabled'] );
                if ( $wpdb->update( "{$wpdb->prefix}easyparcel_zones_courier", array( 'status' => $is_enabled ), array( 'id' => absint( $instance_id ) ) ) ) {
                    // do_action( 'woocommerce_shipping_zone_method_status_toggled', $instance_id, $method_id, $zone_id, $is_enabled );
                }
            }
        }
    }

    $zone->save();
    if (!class_exists('EP_Shipping_Zones')) {
        include_once 'include/ep_shipping_zones.php';
    }
    $hehe = EP_Shipping_Zones::get_zone( absint( $zone->get_id() ) );
    
    wp_send_json_success(
        array(
            'zone_id'   => $zone->get_id(),
            'zone_name' => $zone->get_zone_name(),
            'methods'   => $hehe->get_couriers(false,'json')
        )
    );
}


function ep_courier_setting_save_changes() {
    if(isset($_POST)){
        $_POST = sanitizeEverything('sanitize_text_field', $_POST);
    }
    if ( ! wp_verify_nonce( wp_unslash( $_POST['ep_courier_setup_nonce'] ), 'ep_courier_setup_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    global $wpdb;

    switch ( $_POST['method'] ) {
        case 'update':
            if ( ! isset( $_POST['ep_courier_setup_nonce'], $_POST['courier_id'], $_POST['data'], $_POST['method'] ) ) {
                wp_send_json_error( 'missing_fields' );
                wp_die();
            }
            foreach($_POST['data'] as &$d){
                if(!empty($d)){
                    $d = trim($d);
                }
            }
            $courier_id = wc_clean( wp_unslash( $_POST['courier_id'] ) );
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id = $courier_id");
            if(empty($result)){
                wp_send_json_error( 'courier not found' );
                wp_die();
            }
            if(!empty($result[0]->zone_id)){ $zone_id = $result[0]->zone_id;}else{$zone_id='';}
            // check if courier display name exist
            if(checkCourierName($_POST['data']['courier_display_name'],$courier_id)){
                wp_send_json_error( 'courier_name_exist' );
                wp_die();
            }

            $updated = $wpdb->query( $wpdb->prepare( 
                "
                    UPDATE {$wpdb->prefix}easyparcel_zones_courier 
                    SET courier_display_name = %s, 
                    charges = %s, 
                    charges_value = %s, 
                    free_shipping = %s, 
                    free_shipping_by = %s, 
                    free_shipping_value = %s, 
                    courier_dropoff_point = %s 
                    WHERE id = %s
                ",
                $_POST['data']['courier_display_name'],
                $_POST['data']['charges_option'],
                $_POST['data']['charges_value'],
                $_POST['data']['free_shipping'],
                $_POST['data']['free_shipping_by'],
                $_POST['data']['free_shipping_value'],
                $_POST['data']['courier_dropoff_point'],
                $courier_id
            ) );
            
            break;
        case 'insert':
            if ( ! isset( $_POST['ep_courier_setup_nonce'], $_POST['zone_id'], $_POST['data'], $_POST['method'] ) ) {
                wp_send_json_error( 'missing_fields' );
                wp_die();
            }
            foreach($_POST['data'] as &$d){
                if(!empty($d)){
                    $d = trim($d);
                }
            }
            $zone_id = wc_clean( wp_unslash( $_POST['zone_id'] ) );
            // check if courier display name exist
            if(checkCourierName($_POST['data']['courier_display_name'])){
                wp_send_json_error( 'courier_name_exist' );
                wp_die();
            }
            $col = $wpdb->get_results("SELECT max(courier_order)+1 as courier_order FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id = $zone_id");
            $col[0]->courier_order = (empty($col[0]->courier_order)) ? 0 : $col[0]->courier_order;
            $table = $wpdb->prefix.'easyparcel_zones_courier';
            $res = $wpdb->query(
                $wpdb->prepare(
                "
                INSERT INTO $table
                ( zone_id, service_id, service_name, service_type, courier_id, courier_name, courier_logo, courier_info, courier_display_name, courier_dropoff_point, sample_cost, charges, charges_value, free_shipping, free_shipping_by, free_shipping_value, courier_order, status )
                VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %d, %d )
                ",
                array(
                    $_POST['data']['zone_id'],
                    $_POST['data']['courier_service'],
                    $_POST['data']['service_name'],
                    'parcel',
                    $_POST['data']['courier_id'],
                    $_POST['data']['courier_name'],
                    $_POST['data']['courier_logo'],
                    $_POST['data']['courier_info'],
                    $_POST['data']['courier_display_name'],
                    $_POST['data']['courier_dropoff_point'],
                    $_POST['data']['sample_cost'],
                    $_POST['data']['charges_option'],
                    $_POST['data']['charges_value'],
                    $_POST['data']['free_shipping'],
                    $_POST['data']['free_shipping_by'],
                    $_POST['data']['free_shipping_value'],
                    $col[0]->courier_order,
                    1,
                   )
                )
             );
            break;
    }

    wp_send_json_success(
        array(
            'zone_id'   => $zone_id,
            'result'=>'success'
        )
    );
}

function checkCourierName($courierName,$courier_id =''){
    global $wpdb;
    $result = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT count(*) 
                FROM {$wpdb->prefix}easyparcel_zones_courier
                WHERE courier_display_name = %s
                AND courier_display_name NOT IN ('All Couriers','Cheapest Courier')
            ",
            $courierName
        )
        );
    if(!empty($courier_id)){
        // update part
        if($result > 0){
            $result2 = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT count(*) 
                        FROM {$wpdb->prefix}easyparcel_zones_courier
                        WHERE courier_display_name = %s
                        AND courier_display_name NOT IN ('All Couriers','Cheapest Courier')
                        AND id = %s
                    ",
                    $courierName, $courier_id
                )
                );
            if($result2 > 0){
                return false;
            }else{
                return true;
            }
        }
    }else{
        // insert part
        if($result > 0){
            return true;
        }
    }
    return false;
}

/**
 *  do sanitise
 */
function sanitizeEverything( $func, $arr )
{
    $newArr = array();
    foreach( $arr as $key => $value ){
        $newArr[ $key ] = ( is_array( $value ) ? sanitizeEverything( $func, $value ) : ( is_array($func) ? call_user_func_array($func, $value) : $func( $value ) ) );
    }

    return $newArr;
}
?>