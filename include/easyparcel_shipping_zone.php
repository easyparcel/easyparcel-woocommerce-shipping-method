<?php
/*
wordpress_data\wp-content\plugins\woocommerce\includes\admin\settings\views\html-admin-page-shipping-zones.php
wordpress_data\wp-content\plugins\woocommerce\includes\admin\settings\class-wc-settings-shipping.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-ajax.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-install.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-shipping-zone.php
wordpress_data\wp-content\plugins\woocommerce\includes\wc-core-functions.php
wordpress_data\wp-content\plugins\woocommerce\includes\wc-update-functions.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-shipping-zones.php
wordpress_data\wp-content\plugins\woocommerce\assets\js\admin\wc-shipping-zone-methods.js
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 */

if (!class_exists('WC_Easyparcel_Shipping_Zone')) {
    class WC_Easyparcel_Shipping_Zone extends WC_Shipping_Method {
        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public function __construct() {
            $this->id = 'easyparcel_shipping'; // Id for your shipping method. Should be unique.
            $this->method_title = __('EasyParcel Courier Setting'); // Title shown in admin
            $this->method_description = __('A shipping zone is a geographic region where a certain set of shipping methods are offered. WooCommerce will match a customer to a single zone using their shipping address and present the shipping methods within that zone to them.'); // Description shown in admin
            $this->title = "EasyParcel Courier Setting"; // This can be added as an setting but for this example its forced.
            // $this->plugin_url = admin_url() . '/admin.php?page=wc-settings&tab=shipping&section=easyparcel';
            $this->init();
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init() {
        }

        /**
         * Output the shipping settings screen. Overwrite original
         * handle for easyparcel_shipping main and sub pages
         */
        public function admin_options() {
            //check if default shipping zone exist easyparcel shipping method
            global $current_section, $hide_save_button, $wpdb;
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'easyparcel'");
            if(empty($result)){
                $hide_save_button = true;
                echo '<h4><font color="red">Important**</font><br>You will need to setup EasyParcel Shipping first <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section') . '">HERE</a> before proceeding to EasyParcel Courier Setting.';
                return ;
            }

            
            if ('easyparcel_shipping' === $current_section) {
                if (!isset($_GET['zone_id']) && !isset($_REQUEST['courier_id'])) {
                    $this->load_zone_list();
                    $hide_save_button = true;
                } else if (isset($_REQUEST['zone_id']) && !isset($_REQUEST['courier_id']) && isset($_REQUEST['perform'])) {
                    $this->setup_courier_page(absint(wp_unslash($_REQUEST['zone_id'])));
                    $hide_save_button = true;
                } else if (isset($_REQUEST['zone_id']) && !isset($_REQUEST['courier_id'])) {
                    $this->setup_zone(wc_clean(wp_unslash($_REQUEST['zone_id'])));
                    $hide_save_button = true;
                } elseif (isset($_REQUEST['courier_id'])) {
                    $this->edit_courier_panel(absint(wp_unslash($_REQUEST['courier_id'])));
                    $hide_save_button = true;
                }
            }
        }

        public function load_zone_list() {
            if (!class_exists('EP_Shipping_Zones')) {
                // Include Easyparcel API
                include_once 'ep_shipping_zones.php';
            }

            wp_register_script('easyparcel_admin_shipping_zone', plugin_dir_url(__FILE__) . 'js/admin_shipping_zone.js', array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-enhanced-select', 'wc-backbone-modal'), '1.0');
            wp_localize_script(
                'easyparcel_admin_shipping_zone',
                'shippingZonesLocalizeScript',
                array(
                    'zones' => EP_Shipping_Zones::get_zones('json'),
                    'default_zone' => array(
                        'zone_id' => 0,
                        'zone_name' => '',
                        'zone_order' => null,
                    ),
                    'wc_shipping_zones_nonce' => wp_create_nonce('wc_shipping_zones_nonce'),
                    'strings' => array(
                        'unload_confirmation_msg' => __('Your changed data will be lost if you leave this page without saving.', 'easyparcel_zone'),
                        'delete_confirmation_msg' => __('Are you sure you want to delete this zone? This action cannot be undone.', 'easyparcel_zone'),
                        'save_failed' => __('Your changes were not saved. Please retry.', 'easyparcel_zone'),
                        'no_shipping_methods_offered' => __('No shipping methods offered to this zone.', 'easyparcel_zone'),
                        'no_courier_applied' => __('No courier applied to this zone.', 'easyparcel_zone'),
                    ),
                )
            );
            wp_enqueue_script('easyparcel_admin_shipping_zone');

            include_once dirname(__FILE__) . '/views/html_easyparcel_shipping_zones.php';
        }

        public function setup_zone($zone_id) {
            if ('new' === $zone_id) {
                if (!class_exists('EP_Shipping_Zone')) {
                    include_once 'ep_shipping_zone.php';
                }
                $zone = new EP_Shipping_Zone();
            } else {
                if (!class_exists('EP_Shipping_Zones')) {
                    include_once 'ep_shipping_zones.php';
                }
                $zone = EP_Shipping_Zones::get_zone(absint($zone_id));
            }

            if (!$zone) {
                wp_die(esc_html__('Zone does not exist!', 'woocommerce'));
            }
            $allowed_countries = WC()->countries->get_shipping_countries();
            $shipping_continents = WC()->countries->get_shipping_continents();

            // Prepare locations.
            $locations = array();
            $postcodes = array();

            foreach ($zone->get_zone_locations() as $location) {
                if ('postcode' === $location->type) {
                    $postcodes[] = $location->code;
                } else {
                    $locations[] = $location->type . ':' . $location->code;
                }
            }
            $add_btn_disabled = false;
            $couriers = $zone->get_couriers();
            foreach($couriers as $k=>&$v){
                if(!empty($v['courier_dropoff_point'])){
                    $v['courier_display_name'] .= ' (DropOff Point)';
                }
                if($v['service_id'] == 'all' || $v['service_id'] == 'cheapest'){
                    $add_btn_disabled = true;
                }
            }
            // echo'<pre>';print_r($couriers);echo'</pre>';
            wp_register_script('easyparcel_admin_shipping_zone_methods', plugin_dir_url(__FILE__) . 'js/admin_shipping_zone_methods.js', array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal'), '1.0.0');
            wp_localize_script(
                'easyparcel_admin_shipping_zone_methods',
                'shippingZoneMethodsLocalizeScript',
                array(
                    'methods' => $couriers,
                    'zone_name' => $zone->get_zone_name(),
                    'zone_id' => $zone->get_id(),
                    'wc_shipping_zones_nonce' => wp_create_nonce('wc_shipping_zones_nonce'),
                    'add_courier_option' => $add_btn_disabled,
                    'strings' => array(
                        'unload_confirmation_msg' => __('Your changed data will be lost if you leave this page without saving.', 'easyparcel_zone_method'),
                        'save_changes_prompt' => __('Do you wish to save your changes first? Your changed data will be discarded if you choose to cancel.', 'easyparcel_zone_method'),
                        'save_failed' => __('Your changes were not saved. Please retry.', 'easyparcel_zone_method'),
                        'add_method_failed' => __('Shipping method could not be added. Please retry.', 'easyparcel_zone_method'),
                        'no_location_detected' => __('Kindly save your location before proceed to add in courier service', 'easyparcel_zone_method'),
                        'yes' => __('Yes', 'easyparcel_zone_method'),
                        'no' => __('No', 'easyparcel_zone_method'),
                        'default_zone_name' => __('Zone', 'easyparcel_zone_method'),
                    ),
                )
            );
            wp_enqueue_script('easyparcel_admin_shipping_zone_methods');

            include_once dirname(__FILE__) . '/views/html_easyparcel_shipping_zone_methods.php';
        }

        /**
         * Show instance settings
         *
         * @param int $instance_id Shipping instance ID.
         */
        public function edit_courier_panel($courier_id) {
            global $wpdb;
            $courier = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id = $courier_id");
            if (!$courier) {
                wp_die(esc_html__('Courier does not exist!', 'woocommerce'));
            }else{
                $courier = $courier[0];
            }
            // echo'<pre>';print_R($courier); echo'</pre>';
            
            // decide what to use
            
            $charges = self::chargesOption($courier->charges);
            $freeshippingby = self::freeShippingByOption($courier->free_shipping_by);
            if(!empty($courier->charges_value)){
                if (strpos($courier->charges_value, ':') !== false) {
                    $temp = explode(':',$courier->charges_value);
                    $addonCharges = self::addonChargesOption($temp[0]);
                    $addonChargesValue = $temp[1];
                }else{
                    $addonCharges = self::addonChargesOption();
                    $addonChargesValue = $courier->charges_value;
                }
            }else{
                $addonCharges = self::addonChargesOption();
                $addonChargesValue = 0;
            }
            //special checking for got dropoff things
            if(!empty($courier->courier_dropoff_point)){
                if (!class_exists('EP_Shipping_Zones')) {
                    include_once 'ep_shipping_zones.php';
                }
                $zone = EP_Shipping_Zones::get_zone(absint($courier->zone_id));
                $courier_list = self::filteringRegionRate($zone,true);
                $dropoffpoint = self::checkDropoff($courier,$courier_list);
            }
            
            //
            wp_register_script('easyparcel_admin_shipping_zone_courier_setup', plugin_dir_url(__FILE__) . 'js/admin_shipping_zone_courier_setup.js', array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal'), '1.0.0');
            wp_localize_script(
                'easyparcel_admin_shipping_zone_courier_setup',
                'shippingZoneMethodsLocalizeScript',
                array(
                    'courier_id' => $courier_id,
                    'ep_courier_setup_nonce' => wp_create_nonce('ep_courier_setup_nonce'),
                    'type' => 'edit',
                    'service_id' => $courier->service_id,
                )
            );
            wp_enqueue_script('easyparcel_admin_shipping_zone_courier_setup');


            include_once dirname(__FILE__) . '/views/html_easyparcel_setup_courier_edit.php';
        }

        public function setup_courier_page($zone_id) {
            if (!class_exists('EP_Shipping_Zones')) {
                include_once 'ep_shipping_zones.php';
            }
            $zone = EP_Shipping_Zones::get_zone(absint($zone_id));

            if (!$zone) {
                wp_die(esc_html__('Zone does not exist!', 'woocommerce'));
            }
            
            // decide what to use
            $courier_list = array();
            $courier_set = $zone->get_couriers();
            
            $new_list = self::filteringRegionRate($zone,true);

            if(!empty($courier_set)){   
                foreach($courier_set as $k => $v){
                    if(strtolower($v['service_id']) == 'all'){
                        $courier_list[] = array(
                            'service_name' => 'All Couriers',
                            'courier_id' => 'all',
                            'courier_name' => 'All Couriers',
                            'courier_logo' => '',
                            'courier_info' => 'all',
                            'service_id' => 'all',
                            'sample_cost' => '0.00',
                            'sample_cost_display' => '',
                        );
                    }else if(strtolower($v['service_id']) == 'cheapest'){
                        $courier_list[] = array(
                            'service_name' => 'Cheapest Courier',
                            'courier_id' => 'cheapest',
                            'courier_name' => 'Cheapest Courier',
                            'courier_logo' => '',
                            'courier_info' => 'cheapest',
                            'service_id' => 'cheapest',
                            'sample_cost' => '0.00',
                            'sample_cost_display' => '',
                        );
                    }else if(isset($new_list[$v['service_id']])){
                        unset($new_list[$v['service_id']]);
                    }
                }
            }
            if(empty($courier_set)){
                $courier_list[] = array(
                    'service_name' => 'All Couriers',
                    'courier_id' => 'all',
                    'courier_name' => 'All Couriers',
                    'courier_logo' => '',
                    'courier_info' => 'all',
                    'service_id' => 'all',
                    'sample_cost' => '0.00',
                    'sample_cost_display' => '',
                );
                $courier_list[] = array(
                    'service_name' => 'Cheapest Courier',
                    'courier_id' => 'cheapest',
                    'courier_name' => 'Cheapest Courier',
                    'courier_logo' => '',
                    'courier_info' => 'cheapest',
                    'service_id' => 'cheapest',
                    'sample_cost' => '0.00',
                    'sample_cost_display' => '',
                );
            }
            $courier_list = array_merge($courier_list,$new_list);
            
            $charges = self::chargesOption();
            $freeshippingby = self::freeShippingByOption();
            $addonCharges = self::addonChargesOption();
            

            // echo'<pre>';print_r($new_list);echo'</pre>';
            
            //
            wp_register_script('easyparcel_admin_shipping_zone_courier_setup', plugin_dir_url(__FILE__) . 'js/admin_shipping_zone_courier_setup.js', array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal'), '1.0.0');
            wp_localize_script(
                'easyparcel_admin_shipping_zone_courier_setup',
                'shippingZoneMethodsLocalizeScript',
                array(
                    'zone_id' => $zone_id,
                    'ep_courier_setup_nonce' => wp_create_nonce('ep_courier_setup_nonce'),
                    'type' => 'insert',
                )
            );
            wp_enqueue_script('easyparcel_admin_shipping_zone_courier_setup');

            include_once dirname(__FILE__) . '/views/html_easyparcel_setup_courier.php';
        }

        public static function filteringRegionRate($zone,$customize=false){
            $r_data = array();
            $locations = $zone->get_zone_locations();
            if(empty($locations)) {return array();} // no rate to show
            $countries = array_filter($locations, function ($location) {
                return 'country' === $location->type;
            });
            $states = array_filter($locations, function ($location) {
                return 'state' === $location->type;
            });

            //this part do for controlling condition 
            // if more than 1 country, should block at select option thr
            // if state more than 2 (same country), then use country instead
            // for international, 1 country 1 zone.
            
            $my_state = array();
            $other_state = array();
            // print_R($states);
            foreach($states as $state){
                $temp = explode(':',$state->code);
                if($temp[0] == 'MY'){
                    $my_state[] = $temp[1];
                }else{
                    $other_state[] = $temp;
                }
            }
            //do condition returning what to do for rate
            if(count($countries) > 1){
                $country_list = array();
                foreach($countries as $c){
                    $country_list[] = $c->code;
                }
                if(!empty($states)){
                    foreach($states as $state){
                        $temp = explode(':',$state->code);
                        $country_list[] = $temp[0];
                    }
                }
                $r_data['condition'] = 'country';
                $r_data['country'] = $country_list;
            }else if(count($countries) > 0){
                if(!empty($my_state) && !empty($other_state)){
                    return array(); // no rate to show
                }else if(!empty($other_state)){
                    $test_arr = array();
                    foreach($other_state as $ostat){
                        $test_arr[$ostat[0]][] = $ostat[1];
                    }
                    if(count($test_arr) > 1){ // consists multiple country and state
                        return array(); // no rate to show
                    }else{
                        if($countries == key($test_arr)){
                            $r_data['condition'] = 'country';
                            $r_data['country'] = strtolower(key($countries));
                        }
                    }
                }else{
                    // only one country
                    $r_data['condition'] = 'country';
                    $r_data['country'] = strtolower($countries[0]->code);
                }
            }else if(!empty($my_state) && !empty($other_state)){
                return array(); // no rate to show
            }else if(!empty($other_state)){
                $test_arr = array();
                foreach($other_state as $ostat){
                    $test_arr[$ostat[0]][] = $ostat[1];
                }
                if(count($test_arr) > 1){ // consists multiple country and state
                    return array(); // no rate to show
                }else{
                    // for international only get country 
                    $r_data['condition'] = 'country';
                    $r_data['country'] = strtolower(key($test_arr));
                }
            }else if(!empty($my_state)){
                if(count($my_state) > 2){
                    // for MY if more than 2 state, will direct use country
                    $r_data['condition'] = 'country';
                    $r_data['country'] = 'my';
                }else{
                    $r_data['condition'] = 'state';
                    $r_data['country'] = 'my';
                    $r_data['state'] = $my_state;
                }
            }
        
            //do foreach to get rate and mapping
            $rates = array();
            if(!empty($r_data)){
                switch ( $r_data['condition'] ) {
                    case 'country':
                        if(is_array($r_data['country'])){
                            $temp_rate = array();
                            foreach($r_data['country'] as $c){
                                $temp['country'] = $c;
                                $temp['state'] = '';
                                $temp_rate = self::callrate($temp,$customize);
                                if(!empty($temp_rate)){
                                    $rates = array_merge($rates,$temp_rate);
                                }
                            }
                        } else{
                            $temp = array();
                            $temp['country'] = $r_data['country'];
                            $temp['state'] = '';
                            $rates = self::callrate($temp,$customize);
                        }
                        break;
                    case 'state':
                        $temp_rate = array();
                        foreach($r_data['state'] as $state){
                            $temp['country'] = $r_data['country'];
                            $temp['state'] = $state;
                            $temp_rate = self::callrate($temp,$customize);
                            if(!empty($temp_rate)){
                                $rates = array_merge($rates,$temp_rate);
                            }
                        }
                        break;
                }
            }
            // return $rates;
            $groupped = array();
            foreach ($rates as $rate) {
                $groupped[$rate->courier_id][] = $rate;
            }
            $shipping_rate_list = array();
            foreach ($groupped as $cid => $services) {
                foreach ($services as $rate) {
                    $courier_service_label = $rate->service_name;

                    $courier_logo = array();
                    $courier_logo['ep_courier_logo'] = $rate->courier_logo; ### save ep courier logo ###
                    $courier_service_label .= ' ['.$rate->delivery.']';

                    $shipping_rate = array(
                        'rate_id' => $rate->rate_id,
                        'service_id' => $rate->service_id,
                        'service_name' => $rate->service_name,
                        'Service_Type' => $rate->service_type,
                        'courier_id' => $rate->courier_id,
                        'courier_name' => $rate->courier_name,
                        'courier_logo' => $rate->courier_logo,
                        'courier_info' => $rate->delivery,
                        'courier_extra_info' => $courier_service_label,
                        'courier_display_name' => $rate->courier_name,
                        'sample_cost' => $rate->shipment_price,
                        'sample_cost_display' => '('.get_woocommerce_currency().' '.$rate->shipment_price.')',
                        'dropoff_point' => isset($rate->dropoff_point) ? $rate->dropoff_point : (isset($rate->Dropoff_Point) ? $rate->Dropoff_Point : '')
                    );
                    $shipping_rate_list[$rate->service_id] = $shipping_rate;
                }
            }
            return $shipping_rate_list;
        }

        public static function callrate($data,$customize=false){
            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }
            Easyparcel_Shipping_API::init();
            $auth = Easyparcel_Shipping_API::auth();
            if ($auth != 'Success.') {
                // show authentication got problem, prompt user to setup correct email + integration id
                // todo
                return array();
            } else {
                // go get rate
                $destination = array();
                $destination['country'] = $data['country'];
                $destination['state'] = $data['state'];
                $destination['postcode'] = ''; // no have postcode, so ignore it as empty
                if($customize){
                    switch (strtoupper($data['state'])) {
                        case "SBH":
                          $destination['postcode'] = "88000";
                          break;
                        case "SRW":
                        case "SWK":
                            $destination['postcode'] = "93000";
                          break;
                        case "LBN":
                            $destination['postcode'] = "87000";
                          break;
                        default:
                          break;
                    }
                }
                $items = array();
                $items[0]['length'] = 10;
                $items[0]['width'] = 10;
                $items[0]['height'] = 10;
                $weight = 1;
                return $rates = Easyparcel_Shipping_API::getShippingRate($destination,$items,$weight);
            }
        }

        public static function chargesOption($selected =''){
            $charges = array();
            $charges[2] = array('text' => 'EasyParcel Member Rate', 'selected' => '');
            // $charges[3] = array('text' => 'EasyParcel Public Rate', 'selected' => '');
            $charges[4] = array('text' => 'Add On EasyParcel Member Rate', 'selected' => '');
            $charges[1] = array('text' => 'Flat Rate', 'selected' =>'');

            foreach($charges as $k => &$c){
                if($k == $selected){
                    $c['selected'] = 'selected';
                }
            }

            return $charges;
        }
        
        public static function freeShippingByOption($selected =''){
            $option = array();
            $option[1] = array('text' => 'A minimum order amount', 'selected' => '');
            $option[2] = array('text' => 'A minimum order quantity', 'selected' => '');

            foreach($option as $k => &$c){
                if($k == $selected){
                    $c['selected'] = 'selected';
                }
            }

            return $option;
        }
        
        public static function addonChargesOption($selected =''){
            $option = array();
            $option[1] = array('text' => 'Add On By Amount ('.get_woocommerce_currency().')', 'selected' => '');
            $option[2] = array('text' => 'Add On By Percentage (%)', 'selected' => '');

            foreach($option as $k => &$c){
                if($k == $selected){
                    $c['selected'] = 'selected';
                }
            }

            return $option;
        }

        public static function checkDropoff($courier,$courier_list = array()){
            $option = array();
            $option['optional'] = array('text' => 'Drop Off Point', 'selected' => '');
            if(!empty($courier_list[$courier->service_id])){
                if(!empty($courier_list[$courier->service_id]['dropoff_point']))
                foreach($courier_list[$courier->service_id]['dropoff_point'] as $k => $v){
                    if($v->point_id == $courier->courier_dropoff_point){
                        $option[$v->point_id] = array('text' => $v->point_name, 'selected' => 'selected');
                    }else{
                        $option[$v->point_id] = array('text' => $v->point_name, 'selected' => '');
                    }
                }
            }
            return $option;
        }

    }

}