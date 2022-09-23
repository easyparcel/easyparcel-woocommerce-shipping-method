<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 */

if (!class_exists('WC_Easyparcel_Shipping_Method')) {
    class WC_Easyparcel_Shipping_Method extends WC_Shipping_Method {
        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public function __construct($instance_id = 0) {
            $this->id = 'easyparcel'; // Id for your shipping method. Should be unique.
            $this->method_title = __('EasyParcel Shipping '); // Title shown in admin
            $this->instance_id = empty($instance_id) ? 0 : absint($instance_id);
            $desc = 'Allow your buyers to choose their preferred courier option.';
            $value = get_option('woocommerce_easyparcel_settings');
            $this->title = "EasyParcel Shipping"; // This can be added as an setting but for this example its forced.
            $this->plugin_url = admin_url() . '/admin.php?page=wc-settings&tab=shipping&section=easyparcel';
            $this->supports = array(
                'shipping-zones',
                'settings',
                'instance-settings',
                // 'instance-settings-modal', // this is popout, dont do it
            );
            $this->init();
            $this->settings['cust_rate'] = 'cust_rate';
            if (!empty($value)) {
                if ($value['enabled'] == 'yes' && !empty($this->settings['integration_id']) && !empty($this->settings['easyparcel_email'])) {
                    $desc .= '<br>Setup shipping courier options and zones here.  <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping') . '">HERE</a>';
                }
            }
            $this->method_description = __($desc); // Description shown in admin
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init() {
            // Load the settings API
            $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
            $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
            
            // Save settings in admin if you have any defined
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            
        }

        public function process_admin_options() {
           
            $this->init_settings();
            $post_data = $this->get_post_data();
            if(isset($post_data)){
                $post_data = sanitizeEverything('sanitize_text_field', $post_data);
            }
            if(!empty($post_data)){
                $_POST = array();
                foreach ($this->get_form_fields() as $key => $field) {
                    if ('title' !== $this->get_field_type($field)) {
                        try {
                            $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        } catch (Exception $e) {
                            $this->add_error($e->getMessage());
                        }
                    }
                }
                 //check if base country first
                $WC_Country = new WC_Countries();
                if(strtolower($WC_Country->get_base_country()) != strtolower($this->settings['sender_country'])){
                    $this->init_settings();
                    WC_Admin_Settings::add_error('Nothing changed. The selected country is not same with WooCommerce General Store country'); //Used for debugging
                    return false;
                }

                update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
                return $this->validate_save_settings();
            }
            return false;
        }

        public function validate_save_settings() {
            // WC_Admin_Settings::save();
            // $this->init_settings();
            $err = 0;
            if ($this->settings['enabled'] == 'no') {
                WC_Admin_Settings::add_error("EasyParcel Shipping is disable."); //Used for debugging
                $err++;
            } else {
                if (empty($this->settings['sender_country']) || $this->settings['sender_country'] == 'NONE') {
                    WC_Admin_Settings::add_error("Choose one of the country"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_name'])) {
                    WC_Admin_Settings::add_error("Name cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_contact_number'])) {
                    WC_Admin_Settings::add_error("Contact number cannot be blank"); //Used for debugging
                    $err++;
                } else {
                    $my = '/^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/';
                    $sg = '/\65(6|8|9)[0-9]{7}$/';
                    if (!empty($this->settings['sender_country']) && $this->settings['sender_country'] != 'NONE') {
                        if ($this->settings['sender_country'] == 'MY') {
                            if (!preg_match($my,$this->settings['sender_contact_number'])) {
                                $this->settings['sender_contact_number'] = '';
                                WC_Admin_Settings::add_error("Contact number invalid"); //Used for debugging
                                $err++;
                            }
                        }else{
                            if (!preg_match($sg,$this->settings['sender_contact_number'])) {
                                $this->settings['sender_contact_number'] = '';
                                WC_Admin_Settings::add_error("Contact number invalid"); //Used for debugging
                                $err++;
                            }
                        }
                    }
                }
                if (empty($this->settings['easyparcel_email'])) {
                    WC_Admin_Settings::add_error("EasyParcel Login Email cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_address_1'])) {
                    WC_Admin_Settings::add_error("Address 1 cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_city'])) {
                    WC_Admin_Settings::add_error("City cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_state'])) {
                    WC_Admin_Settings::add_error("State/Zone cannot be blank"); //Used for debugging
                    $err++;
                }

                if (empty($this->settings['sender_postcode'])) {
                    WC_Admin_Settings::add_error("Postcode cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['integration_id'])) {
                    WC_Admin_Settings::add_error("Integration ID cannot be blank"); //Used for debugging
                    $err++;
                }
                if ($err > 0) {
                    WC_Admin_Settings::add_error("EasyParcel Shipping method may not be work properly without details above."); //Used for debugging
                }else{
                    WC_Admin_Settings::save_fields($this->settings);
                    if (!class_exists('Easyparcel_Shipping_API')) {
                        // Include Easyparcel API
                        include_once 'easyparcel_api.php';
                    }
                    if (!empty($this->settings['integration_id'])) {
                        Easyparcel_Shipping_API::init();
                        $auth = Easyparcel_Shipping_API::auth();
                        if ($auth == 'Success.') {
                            $this->show_setup_zone_notice();
                        } else {
                            $this->show_setup_fail_notice($auth);
                        }
                    }
                }
            }
        }

        public function show_setup_zone_notice() {
            add_action('admin_notices', function () {
                echo '<div id="message" class="notice notice-success is-dismissible"><p>Please proceed to setup your preferred shipping courier and zone <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping') . '">HERE</a></p></div>';
            });
        }
        public function show_setup_fail_notice($auth) {
            add_action('admin_notices', function ($auth) {
                echo '<div id="message" class="notice notice-error is-dismissible"><p>' . $auth . ' You have inserted invalid login email OR integration_id</p></div>';
            });
        }

        /**
         * Notification when api key and secret is not set
         *
         * @access public
         * @return void
         */
        public function easyparcel_admin_notice() {

            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }
            Easyparcel_Shipping_API::init();
            $auth = Easyparcel_Shipping_API::auth();
            if ($this->get_option('easyparcel_email') == '' || ($this->get_option('integration_id') == '') || ($this->get_option("cust_rate") != 'fix_rate' && ($this->get_option('sender_postcode') == ''))) {
                echo '<div class="error">Please go to <bold>WooCommerce > Settings > Shipping > Easyparcel Shipping</bold> to add your email,integration_id and sender code. </div>';
            } elseif ($auth != 'Success.') {
                echo '<div class="error">Please go to <bold>WooCommerce > Settings > Shipping > Easyparcel Shipping</bold> enter your valid email,integration_id</div>';
            }
        }
        /**
         * Initialise Gateway Settings Form Fields
         */
        //loading $this->init_form_fields();
        function init_form_fields() {
            $postcode = get_option('woocommerce_store_postcode');
            $city = get_option('woocommerce_store_city');
            $address = get_option('woocommerce_store_address');
            $address2 = get_option('woocommerce_store_address_2');
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable', 'easyparcel'),
                    'type' => 'checkbox',
                    'description' => __('Enable to activate EasyParcel shipping method.', 'easyparcel'),
                    'label' => __('Enable to activate easyparcel shipping method', 'easyparcel'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),

                'sender_country_option' => array(
                    'title' => __('Which country do you wish to send from?', 'easyparcel'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'sender_country' => array(
                    'title' => __('<font color="red">*</font>Country', 'easyparcel'),
                    'type' => 'select',
                    'default' => 'NONE',
                    'options' => array('NONE' => 'Kindly Choose your country', 'MY' => 'Malaysia', 'SG' => 'Singapore'),
                    'required' => true,
                ),

                'sender_detail' => array(
                    'title' => __('Sender Details', 'easyparcel'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'easyparcel_email' => array(
                    'title' => __('<font color="red">*</font>EasyParcel Login Email', 'easyparcel'),
                    'type' => 'text',
                    'description' => __('Enter your registered EasyParcel login email here. If you do not have an EasyParcel account, sign up for free at <a href="https://easyparcel.com" target="_blank">easyparcel.com</a>', 'easyparcel'),
                    'desc_tip' => true,
                    'default' => '',
                    'required' => true,
                ),

                'integration_id' => array(
                    'title' => __('<font color="red">*</font>Integration ID', 'easyparcel'),
                    'type' => 'text',
                    'description' => __('Here’s how to get your integration ID: <br/>
                                        1. Login to your EasyParcel Account<br/>
                                        2. Click on "Dashboard" - "Integrations" - "Add New Store"<br/>
                                        3. Choose "WooCommerce" <br/>
                                        4. Fill in required details <br/>
                                        5. Copy the Integration ID and paste it here.', 'easyparcel'),
                    'desc_tip' => true,
                    'required' => true,
                ),
                'sender_name' => array(
                    'title' => __('<font color="red">*</font>Name', 'easyparcel'),
                    'type' => 'text',
                    'default' => '',
                    'required' => true,
                ),
                'sender_contact_number' => array(
                    'title' => __('<font color="red">*</font>Contact Number', 'easyparcel'),
                    'type' => 'text',
                    'default' => '',
                    'placeholder' => 'key in with countrycode (MY)60 / (SG)65',
                    'required' => true,
                ),
                'sender_alt_contact_number' => array(
                    'title' => __('Alt. Contact Number', 'easyparcel'),
                    'type' => 'text',
                    'default' => '',
                    'placeholder' => 'key in with countrycode (MY)60 / (SG)65',
                ),
                'sender_company_name' => array(
                    'title' => __('Company Name', 'easyparcel'),
                    'type' => 'text',
                    'default' => '',
                    // 'placeholder' => 'company name',
                ),
                'sender_address_1' => array(
                    'title' => __('<font color="red">*</font>Address Line 1', 'easyparcel'),
                    'type' => 'text',
                    'default' => $address,
                    // 'placeholder' => 'Address line 1',
                    'required' => true,
                ),
                'sender_address_2' => array(
                    'title' => __('Address Line 2', 'easyparcel'),
                    'type' => 'text',
                    'default' => $address2,
                    // 'placeholder' => 'Address line 2',
                    'required' => true,
                ),
                'sender_city' => array(
                    'title' => __('<font color="red">*</font>City', 'easyparcel'),
                    'type' => 'text',
                    'default' => $city,
                    // 'placeholder' => 'city',
                    'required' => true,
                ),
                'sender_postcode' => array(
                    'title' => __('<font color="red">*</font>Postcode', 'easyparcel'),
                    'type' => 'text',
                    'default' => $postcode,
                    'required' => true,
                ),
                'sender_state' => array(
                    'title' => __('<font color="red">*</font>State', 'easyparcel'),
                    'type' => 'select',
                    'description' => __('state', 'easyparcel'),
                    'default' => '',
                    'desc_tip' => true,
                    // 'placeholder' => 'state',
                    'required' => true,
                    'options' => array(
                        'jhr' => 'Johor',
                        'kdh' => 'Kedah',
                        'ktn' => 'Kelantan',
                        'kul' => 'Kuala Lumpur',
                        'lbn' => 'Labuan',
                        'mlk' => 'Melaka',
                        'nsn' => 'Negeri Sembilan',
                        'phg' => 'Pahang',
                        'prk' => 'Perak',
                        'pls' => 'Perlis',
                        'png' => 'Penang',
                        'sbh' => 'Sabah',
                        'srw' => 'Sarawak',
                        'sgr' => 'Selangor',
                        'trg' => 'Terengganu',
                        'pjy' => 'Putra Jaya',
                    ),
                ),

                // additional option
                'addon_service_setting' => array(
                    'title' => __('Add On Service Settings', 'easyparcel'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'addon_email_option' => array(
                    'title' => __('Tracking Email', 'easyparcel'),
                    'type' => 'checkbox',
                    'description' => __('EasyParcel will automatically send tracking details to receiver via email when your fulfillment is made for RM0.05.', 'easyparcel'),
                    'label' => __('Enable Tracking Email.', 'easyparcel'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),
                'addon_sms_option' => array(
                    'title' => __('Tracking SMS', 'easyparcel'),
                    'type' => 'checkbox',
                    'description' => __('EasyParcel will automatically send tracking details to receiver via SMS when your  fulfillment is made for RM0.20.', 'easyparcel'),
                    'label' => __('Enable Tracking SMS', 'easyparcel'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),

                // order status update setting
                'order_status_update_setting' => array(
                    'title' => __('Order Status Update Settings', 'easyparcel'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'order_status_update_option' => array(
                    'title' => __('Order Status Auto Update', 'easyparcel'),
                    'type' => 'checkbox',
                    'description' => __('Order status will be updated as "completed" automatically once fulfillment done.', 'easyparcel'),
                    'label' => __('Enable order status auto update.', 'easyparcel'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),
            );
            $this->admin_shipping_init();
            add_action('admin_enqueue_scripts', array($this, 'admin_shipping_init'), 30);
        } // End init_form_fields()

        function admin_shipping_init() {
            wp_register_script('ajax-script', plugin_dir_url(__FILE__) . 'js/admin_shipping.js', array('jquery'), '', true);
            wp_localize_script('ajax-script', 'obj', array('ajax_url' => admin_url('admin-ajax.php'), 'nextNonce' => wp_create_nonce('ajax-nonce'), 'courier_service' => null !== $this->get_option('courier_service') ? $this->get_option('courier_service') : '', 'sender_state' => null !== $this->get_option('sender_state') ? $this->get_option('sender_state') : ''));
            wp_enqueue_script('ajax-script');
        }

        /**
         * calculate_shipping function.
         *
         * @access public
         * @param mixed $package
         * @return void
         */
        public function calculate_shipping($package = array()) {
            if (!WC_Shipping_Zones::get_shipping_method($this->instance_id) || ($this->get_option('enabled') == 'no')) {
                return;
            }

            $destination = $package["destination"];
            $items = array();
            // as default if on at shipping zone , only will load this, so will remove checking on WC shipping zone, now is time to get our zone checking based on priority
            // go get valid shipping courier
            if (!class_exists('EP_Shipping_Zones')) {
                // Include Easyparcel API
                include_once 'ep_shipping_zones.php';
            }
            $EP_Shipping_Zones = new EP_Shipping_Zones();
            $zone = $EP_Shipping_Zones->get_zone_matching_package($package);
            $zone_courier = $zone->get_couriers();

            $product_factory = new WC_Product_Factory();

            foreach ($package["contents"] as $key => $item) {
                // default product - assume it is simple product
                $product = $product_factory->get_product($item["product_id"]);
                $product_data = $product_factory->get_product($item["data"]);
                $product_status = $item["data"]->get_type();
                // if this item is variation, get variation product instead
                if ($product_status == "variation") {
                    $product = $product_factory->get_product($item["variation_id"]);
                }

                for ($i = 0; $i < $item["quantity"]; $i++) {
                    $items[] = array(
                        "weight" => $this->weightToKg($product->get_weight()),
                        "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                        "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                        "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
                    );
                }
            }
            $courier = array();
            if (empty($zone_courier)) {
                wc_add_notice('No Courier Found');
            } else {
                foreach ($zone_courier as $k => $v) {
                    if ($v['status'] != 0) {
                        $courier[$v['service_name']] = array(
                            'id' => $v['service_id'],
                            'label' => $v['courier_display_name'],
                            'service_name' => $v['service_name'],
                            // 'cost' => $rate->shipment_price,
                            'meta_data' => $v['courier_logo'],
                            'dropoff_point' => $v['courier_dropoff_point'],
                            'charges' => $v['charges'],
                            'charges_value' => $v['charges_value'],
                            'free_shipping' => $v['free_shipping'],
                            'free_shipping_by' => $v['free_shipping_by'],
                            'free_shipping_value' => $v['free_shipping_value'],
                        );
                    }
                }
            }
            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }
            try {
                $shr = array();
                Easyparcel_Shipping_API::init();
                $auth = Easyparcel_Shipping_API::auth();
                if ($auth != 'Success.') {
                    wc_add_notice($auth);
                } else {
                    $pickup_available_method = array();
                    $i = 0;
                    $weight = 0;
                    foreach ($items as $item) {
                        $weight += is_numeric($items[$i]['weight']) ? $items[$i]['weight'] : 0;
                        $i++;
                    }

                    $WC_Country = new WC_Countries();

                    $rates = Easyparcel_Shipping_API::getShippingRate($destination, $items, $weight);
                    $weight = ceil($weight);

                    $groupped = array();
                    foreach ($rates as $rate) {
                        $groupped[$rate->courier_id][] = $rate;
                    }

                    foreach ($groupped as $cid => $services) {
                        foreach ($services as $rate) {
                            $courier_service_label = $rate->service_name;
                            ### hide previos logic - S ###
                            // if (count($services) > 1) {
                            //     $courier_service_label = $rate->service_name;
                            //     if ($rate->courier_id == 'EP-CR0O') { // is pgeon, use processed service_name
                            //         $first_at = strcspn($rate->service_name, '-('); // first occurance of either hyphen or bracket
                            //         $courier_service_label = trim(substr($rate->service_name, 0, $first_at));
                            //     }
                            // } else {
                            //     $courier_service_label = $rate->courier_name;
                            // }
                            // if ($rate->service_name == "Mystery Saver") { // when both mysterysaver and qexpress available, mysterysaver should still use service_name
                            //     $courier_service_label = $rate->service_name;
                            // }
                            ### hide previos logic - E ###

                            $courier_logo = array();
                            $courier_logo['ep_courier_logo'] = $rate->courier_logo; ### save ep courier logo ###
                            $courier_service_label .= ' [' . $rate->delivery . ']';
                            
                            $pickup_point = array();
                            if (strtoupper($this->settings['sender_country']) == 'MY') {
                                $pickup_point = $rate->pickup_point;
                            }else if (strtoupper($this->settings['sender_country']) == 'SG') {
                                $pickup_point = $rate->pickup_point;
                            }

                            if (isset($pickup_point) && count($pickup_point) > 1) {
                                $temp_array = array("Choose a Pick Up point");
                                if (strtoupper($this->settings['sender_country']) == 'MY') {
                                    foreach ($pickup_point as $pickup_point) {
                                        $temp_array[$pickup_point->point_id . "::" . $pickup_point->company . " @ " . $pickup_point->point_name] = $pickup_point->company . " @ " . $pickup_point->point_name;
                                    }
                                }else{
                                    foreach ($pickup_point as $pickup_point) {
                                        $temp_array[$pickup_point->point_id . "::" . $pickup_point->point_name] = $pickup_point->point_name;
                                    }
                                }
                                WC()->session->set('EasyParcel_Pickup_' . $rate->service_id, $temp_array);
                                array_push($pickup_available_method, $rate->service_id);
                            }
                            
                            $shipping_rate = array(
                                'id' => $rate->service_id,
                                'service_name' => $courier_service_label,
                                'label' => $courier_service_label,
                                'cost' => $rate->shipment_price,
                                'meta_data' => $courier_logo,
                            );
                            $shr[] = $shipping_rate;
                        }
                    }
                    // get all rate then do the filtering to show only
                    $standbylist = array();
                    $count=0;
                    foreach($courier as $ck => $cv){
                        foreach ($shr as $key => $val) {
                            
                            $val['service_name'] = str_replace("&amp;","&",$val['service_name']);
                            if(strtolower($cv['id']) == 'all'){
                                // do for every shr
                                $cv['label'] = '';
                                $rates = self::doCourierCalculation($val, $cv, $package);
                                $this->add_rate($rates);
                            }else if(strtolower($cv['id']) == 'cheapest'){
                                $count++;
                                // do checking for cheapest
                                $cv['label'] = '';
                                $standbylist[] = self::doCourierCalculation($val, $cv, $package);
                                if($count == count($shr)){
                                    $cheapestrates = $this->get_cheaper_rate($standbylist);
                                    $this->add_rate($cheapestrates);
                                }
                            }else if ($cv['service_name'] == $val['service_name']) {
                                $this->add_rate(self::doCourierCalculation($val, $cv, $package));
                            }else{
                                continue;
                            }
                        }
                    }

                    WC()->session->set('EasyParcel_Pickup_Available', $pickup_available_method);
                }
            } catch (Exception $e) {
                $message = sprintf(__('Easyparcel Shipping Method is not set properly! Error: %s', 'easyparcel'), $e->getMessage());

                $messageType = "error";
                wc_add_notice($message, $messageType);
            }
        }

        public function get_admin_shipping($post) {
            $order = wc_get_order($post->ID);

            $destination = array();
            $destination['country'] = $order->get_shipping_country();
            $destination['state'] = $order->get_shipping_state();
            $destination['postcode'] = $order->get_shipping_postcode();

            $items = array();
            $product_factory = new WC_Product_Factory();

            foreach ($order->get_items() as $item) {
                $product = $product_factory->get_product($item["product_id"]);

                for ($i = 0; $i < $item["quantity"]; $i++) {
                    $items[] = array(
                        "weight" => $this->weightToKg($product->get_weight()),
                        "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                        "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                        "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
                    );
                }
            }

            $i = 0;
            $weight = 0;
            foreach ($items as $item) {
                $weight += is_numeric($items[$i]['weight']) ? $items[$i]['weight'] : 0;
                $i++;
            }

            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }
            Easyparcel_Shipping_API::init();
            ### call EP Get Rate API ###
            $rates = Easyparcel_Shipping_API::getShippingRate($destination, $items, $weight);

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
                    $courier_service_label .= ' [' . $rate->delivery . ']';

                    $dropoff_point = array();
                    if (strtoupper($this->settings['sender_country']) == 'MY') {
                        $dropoff_point = $rate->dropoff_point;
                    }else if (strtoupper($this->settings['sender_country']) == 'SG') {
                        $dropoff_point = $rate->dropoff_point;
                    }

                    $shipping_rate = array(
                        'id' => $rate->service_id,
                        'service_name' => $courier_service_label,
                        'label' => $courier_service_label,
                        'cost' => $rate->shipment_price,
                        'dropoff_point' => $dropoff_point,
                    );
                    array_push($shipping_rate_list, $shipping_rate);
                }
            }

            ### Filter based on setting - S ###
            if (!class_exists('EP_Shipping_Zones')) {
                // Include Easyparcel API
                include_once 'ep_shipping_zones.php';
            }

            $package = array();
            $package['destination']['country'] = $order->get_shipping_country();
            $package['destination']['state'] = $order->get_shipping_state();
            $package['destination']['postcode'] = $order->get_shipping_postcode();

            $EP_Shipping_Zones = new EP_Shipping_Zones();
            $zone = $EP_Shipping_Zones->get_zone_matching_package($package);
            $zone_courier = $zone->get_couriers();

            $courier = array();
            if (!empty($zone_courier)) { //got zone + courier services only filter, else use all courier
                foreach ($zone_courier as $k => $v) {
                    if ($v['status'] != 0) {
                        $courier[$v['service_name']] = array(
                            'id' => $v['service_id'],
                            'label' => $v['courier_display_name'],
                            'service_name' => $v['service_name'],
                            // 'cost' => $rate->shipment_price,
                            'meta_data' => $v['courier_logo'],
                            'dropoff_point' => $v['courier_dropoff_point'],
                            'charges' => $v['charges'],
                            'charges_value' => $v['charges_value'],
                            'free_shipping' => $v['free_shipping'],
                            'free_shipping_by' => $v['free_shipping_by'],
                            'free_shipping_value' => $v['free_shipping_value'],
                        );
                    }
                }

                // get all rate then do the filtering to show only
                $standbylist = array();
                $new_shipping_rate_list = array();
                $count=0;
                foreach($courier as $ck => $cv){
                    foreach ($shipping_rate_list as $key => $val) {
                        $val['service_name'] = str_replace("&amp;","&",$val['service_name']);
                        if(strtolower($cv['id']) == 'all'){
                            array_push($new_shipping_rate_list, $val);
                        }else if(strtolower($cv['id']) == 'cheapest'){
                            $count++;
                            // do checking for cheapest
                            $cv['label'] = '';
                            $standbylist[] = $val;
                            if($count == count($shipping_rate_list)){
                                $cheapestrates = $this->get_cheaper_rate($standbylist);
                                array_push($new_shipping_rate_list, $cheapestrates);
                            }
                        }else if ($cv['service_name'] == $val['service_name']) {
                            $selected_dropoff_point = isset($courier[$cv['service_name']]['dropoff_point']) ? $courier[$val['service_name']]['dropoff_point'] : '';
                            $val['label'] = $cv['label']; // use setting display name
                            $val['selected_dropoff_point'] = $selected_dropoff_point;
                            array_push($new_shipping_rate_list, $val);
                        }else{
                            continue;
                        }
                    }
                }

                $shipping_rate_list = $new_shipping_rate_list; ### overwrite ###
            }
            ### Filter based on setting - E ###

            return $shipping_rate_list;
        }

        /**
         * This function is convert dimension to cm
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function dimensionToCm($length) {
            $dimension_unit = get_option('woocommerce_dimension_unit');
            // convert other units into cm
            // $length = double($length);
            if ($dimension_unit != 'cm') {
                if ($dimension_unit == 'm') {
                    return $length * 100;
                } else if ($dimension_unit == 'mm') {
                    return $length * 0.1;
                } else if ($dimension_unit == 'in') {
                    return $length * 2.54;
                } else if ($dimension_unit == 'yd') {
                    return $length * 91.44;
                }
            }

            // already in cm
            return $length;
        }

        /**
         * This function is convert weight to kg
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function weightToKg($weight) {
            $weight_unit = get_option('woocommerce_weight_unit');
            // convert other unit into kg
            // $weight = double($weight);
            if ($weight_unit != 'kg') {
                if ($weight_unit == 'g') {
                    return $weight * 0.001;
                } else if ($weight_unit == 'lbs') {
                    return $weight * 0.453592;
                } else if ($weight_unit == 'oz') {
                    return $weight * 0.0283495;
                }
            }

            // already kg
            return (float)$weight;
        }

        /**
         * This function return default value for length
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function defaultDimension($length) {
            // default dimension to 1 if it is 0
            // $length = double($length);
            return $length > 0 ? $length : 0.1;
        }

        /**** Price Display*************************/
        /**
         * This function is found the cheapeast Courier from EasyParcel , modified to fit version 1.0.0
         *
         * @access protected
         * @param array
         * @return array
         */
        protected function get_cheaper_rate($rates) {
            $prefer_rates = array();
            $lowest = 0;
            $index = 0;
            if (empty($rates)) {
                return $prefer_rates;
            }
            foreach ($rates as $k => $v) {
                if($k == 0){
                    $prefer_rates = $v;
                }else{
                    if($v['cost'] <= $prefer_rates['cost']){
                        $prefer_rates = array();
                        $prefer_rates = $v;
                    }
                }
            }
            return $prefer_rates;
        }

        /**
         * process easyparcel order function
         */
        public function process_booking_order($obj) {
            $woo_order = wc_get_order($obj->order_id);
            $data = (object) array();
            $data->order_id = $obj->order_id;
            $data->order = $woo_order;

            $weight = 0;
            $length = 0;
            $width = 0;
            $height = 0;
            $item_value = 0;

            $content = '';
            $product_factory = new WC_Product_Factory();

            foreach ($data->order->get_items() as $item) {
                $data->product = $product_factory->get_product($item["product_id"]);
                $item_value += $item->get_subtotal();

                for ($i = 0; $i < $item["quantity"]; $i++) {
                    $weight += $this->weightToKg($data->product->get_weight());
                    $height += $this->defaultDimension($this->dimensionToCm($data->product->get_height()));
                    $width += $this->defaultDimension($this->dimensionToCm($data->product->get_width()));
                    $length += $this->defaultDimension($this->dimensionToCm($data->product->get_length()));
                }

                $content .= $item["name"] . ' ';
            }

            $data->item_value = $item_value;
            $data->weight = $weight;
            $data->length = $length;
            $data->width = $width;
            $data->height = $height;
            $content = trim($content); // trim content first
            if (strlen($content) >= 30) {
                $content = substr($content, 0, 30) . '...';
            }
            $data->content = $content;
            $data->service_id = $obj->shipping_provider;
            $data->drop_off_point = $obj->drop_off_point;
            $data->collect_date = $obj->pick_up_date;

            // echo "<pre>";print_r($data);echo "</pre>";

            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }

            Easyparcel_Shipping_API::init();
            ### call EP Submit Order API ###
           $order_result = Easyparcel_Shipping_API::submitOrder($data);

            $return_result = '';

            if (!empty($order_result)) {

                if (!empty($order_result->order_number)) {
                    ### add woo order meta ###
                    $data->ep_order_number = $order_result->order_number;
                    $data->order->update_meta_data('_ep_order_number', $data->ep_order_number);
                    $data->order->update_meta_data('_ep_selected_courier', $obj->courier_name);
                    $data->order->save(); // save meta

                    ### call EP Pay Order API ###
                    $payment_result = Easyparcel_Shipping_API::payOrder($data);
                    if (!empty($payment_result)) {
                        if ($payment_result->error_code == 0) { ### EP Pay Order API Success ###
                            if (isset($payment_result->result)) {

                                $obj_awb = $this->process_woo_payment_awb($payment_result->result[0]);

                                if ( empty($obj_awb->ep_awb) ) {
                                    #### RECALL AWB ####
                                    sleep(15); //sleep 15 sec to recall pay order api

                                    $payment_result2 = Easyparcel_Shipping_API::payOrder($data);
                                    if ($payment_result2->error_code == 0) { ### EP Pay Order API Success ###
                                        if (isset($payment_result2->result)) {
                                            $obj_awb = $this->process_woo_payment_awb($payment_result2->result[0]); ### second times ###
                                        }
                                    }
                                }

                                $data->order->update_meta_data('_ep_payment_status', "Paid");
                                $data->order->update_meta_data('_ep_awb', $obj_awb->ep_awb);
                                $data->order->update_meta_data('_ep_awb_id_link', $obj_awb->ep_awb_id_link);
                                $data->order->update_meta_data('_ep_tracking_url', $obj_awb->ep_tracking_url);
                                $data->order->save(); // save meta

                                if ($obj_awb->ep_awb) {
                                    // need to put after save meta
                                    $this->process_woo_order_status_update_after_payment($data);
                                }

                            } else {
                                $return_result = "EasyParcel Payment Failed. " . $payment_result->error_remark;
                            }
                        } else {
                            $return_result = "EasyParcel Payment Failed. " . $payment_result->error_remark;
                        }

                    } else {
                        $return_result = "EasyParcel Payment Failed. " . $payment_result->error_remark;
                    }
                } else {
                    $return_result = "EasyParcel Order Failed. " . $order_result->remarks;
                }
            } else {
                $return_result = "EasyParcel Order Failed. " . $order_result->remarks;
            }

            return $return_result;

        }

        /**
         * process easyparcel bulk order function
         */
        public function process_bulk_booking_order($obj) {
            $weight = 0;
            $length = 0;
            $width = 0;
            $height = 0;

            $content = '';
            $product_factory = new WC_Product_Factory();

            $order_ids = explode(',', $obj->order_id);
            $bulk_order = array();
            foreach ($order_ids as $order_id) {
                $woo_order = wc_get_order($order_id);
                $data = (object) array();
                $data->order_id = $order_id;
                $data->order = $woo_order;

                ###Declare for bulk usage###
                $total_weight = 0;
                $total_length = 0;
                $total_width = 0;
                $total_height = 0;
                $item_value = 0;
                $full_content = '';

                foreach ($data->order->get_items() as $item) {
                    $data->product = $product_factory->get_product($item["product_id"]);
                    $item_value += $item->get_subtotal();

                    ###Declare for bulk usage###
                    $weight = 0;
                    $length = 0;
                    $width = 0;
                    $height = 0;
                    $content = '';
                    for ($i = 0; $i < $item["quantity"]; $i++) {
                        $weight += $this->weightToKg($data->product->get_weight());
                        $height += $this->defaultDimension($this->dimensionToCm($data->product->get_height()));
                        $width += $this->defaultDimension($this->dimensionToCm($data->product->get_width()));
                        $length += $this->defaultDimension($this->dimensionToCm($data->product->get_length()));
                    }

                    $total_weight += $weight;
                    $total_length += $height;
                    $total_width += $width;
                    $total_height += $length;

                    $full_content .= $item["name"] . ' ';
                }

                $data->item_value = $item_value;
                $data->weight = $total_weight;
                $data->length = $total_length;
                $data->width = $total_width;
                $data->height = $total_height;
                $full_content = trim($full_content); // trim content first
                if (strlen($full_content) >= 30) {
                    $full_content = substr($full_content, 0, 30) . '...';
                }
                $data->content = $full_content;
                $data->service_id = $obj->shipping_provider;
                $data->drop_off_point = $obj->drop_off_point;
                $data->collect_date = $obj->pick_up_date;

                array_push($bulk_order, $data);
            }

            // echo "<pre>";print_r($bulk_order);echo "</pre>";

            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }

            Easyparcel_Shipping_API::init();
            ### call EP Bulk Submit Order API ###
            $order_result = Easyparcel_Shipping_API::submitBulkOrder($bulk_order);

            $return_result = '';

            $paid_bulk_order_id = array();
            $paid_bulk_ep_order_number = array();

            if (!empty($order_result)) {
                if (!empty($order_result->result)) {
                    for ($i = 0; $i < count($bulk_order); $i++) {
                        if (isset($order_result->result[$i]) && !empty($order_result->result[$i]->order_number)) {

                            array_push($paid_bulk_order_id, $bulk_order[$i]->order_id);
                            array_push($paid_bulk_ep_order_number, $order_result->result[$i]->order_number);
                            ### add woo order meta ###
                            $bulk_order[$i]->order->update_meta_data('_ep_order_number', $order_result->result[$i]->order_number);
                            $bulk_order[$i]->order->update_meta_data('_ep_selected_courier', $obj->courier_name);
                            $bulk_order[$i]->order->save(); // save meta

                        } else {
                            $return_result .= "EasyParcel Order Failed. " . $order_result->result[$i]->remarks;
                        }

                    }
                } else {
                    $return_result .= "EasyParcel Order Failed. " . $order_result->error_remark;
                }
            }

            if (!empty($paid_bulk_ep_order_number) && !empty($paid_bulk_order_id)) {
                ### call EP Bulk Pay Order API ###
                $payment_result = Easyparcel_Shipping_API::payBulkOrder($paid_bulk_ep_order_number);

                if (!empty($payment_result)) {
                    if ($payment_result->error_code == 0) { ### EP Pay Order API Success ###
                    if (!empty($payment_result->result)) {
                        for ($i = 0; $i < count($paid_bulk_order_id); $i++) {
                            if (isset($payment_result->result[$i])) {

                                $obj_awb = $this->process_woo_payment_awb($payment_result->result[$i]);
                               
                                $woo_order = wc_get_order($paid_bulk_order_id[$i]);
                                
                                $woo_order->update_meta_data('_ep_payment_status', "Paid");
                                $woo_order->update_meta_data('_ep_awb', $obj_awb->ep_awb);
                                $woo_order->update_meta_data('_ep_awb_id_link', $obj_awb->ep_awb_id_link);
                                $woo_order->update_meta_data('_ep_tracking_url', $obj_awb->ep_tracking_url);
                                $woo_order->save(); // save meta

                                if ($obj_awb->ep_awb) {
                                    // bulk need declare $data - need to put after save meta
                                    $data = (object) array();
                                    $data->order = $woo_order;
                                    $this->process_woo_order_status_update_after_payment($data);
                                }
                            } else {
                                $return_result .= "EasyParcel Payment Failed. " . $payment_result->result[$i]->messagenow;
                            }
                        }
                    }
                    } else {
                        $return_result .= "EasyParcel Payment Failed. " . $order_result->error_remark;
                    }
                } else {
                    $return_result .= "EasyParcel Payment Failed. " . $order_result->error_remark;
                }

            }

            return $return_result;

        }

        private function process_woo_payment_awb($result) {
            $data = (object) array();
            $data->ep_awb = '';
            $data->ep_awb_id_link = '';
            $data->ep_tracking_url = '';
            
            if (isset($result->parcel)) {
                if(is_object($result->parcel)){
                    $data->ep_awb = $result->parcel->awb;
                    $data->ep_awb_id_link = $result->parcel->awb_id_link;
                    $data->ep_tracking_url = $result->parcel->tracking_url;
                }else if (is_array($result->parcel)){
                    $data->ep_awb = $result->parcel[0]->awb;
                    $data->ep_awb_id_link = $result->parcel[0]->awb_id_link;
                    $data->ep_tracking_url = $result->parcel[0]->tracking_url;
                }
            }            
            return $data;
        }

        private function process_woo_order_status_update_after_payment($data) {
            if ($this->settings['order_status_update_option'] == 'yes') {
                $data->order->update_status('completed');
            }
        }

        public function doCourierCalculation($rate, $setting, $package) {
            $rate['label'] = !empty($setting['label']) ? $setting['label'] : $rate['label'];

            $order_total = $package['cart_subtotal'];
            $order_quantity = 0;
            $skipcost = false;

            foreach ($package["contents"] as $key => $item) {
                $order_quantity += $item["quantity"];
            }

            if ($setting['free_shipping'] == 1 || $setting['free_shipping'] == '1') {

                switch ($setting['free_shipping_by']) {
                case "1": //amount
                    if ($order_total >= (float) $setting['free_shipping_value']) {
                        $skipcost = true;
                    }
                    break;
                case "2": // quantity
                    if ($order_quantity >= (float) $setting['free_shipping_value']) {
                        $skipcost = true;
                    }
                    break;
                }
            }
            if ($skipcost) {
                $rate['cost'] = 0;
            } else {
                switch ($setting['charges']) {
                case "1": //flat
                    $rate['cost'] = (float) $setting['charges_value'];
                    break;
                case "2": // member , no care for $raw_method->charges_value
                    $rate['cost'] = $rate['cost'];
                    break;
                case "3": // public ?  , no care for $raw_method->charges_value
                    $rate['cost'] = $rate['cost']; // no public but set back to normal
                    break;
                case "4": // addon ,
                    // $raw_method->charges_value
                    $c_value = explode(":", $setting['charges_value']);
                    if ($c_value[0] == 1) {
                        if (strpos($c_value[1], '-') !== false && strpos($c_value[1], '-') == 0) { // under minus price
                            $deduct = substr($c_value[1], 1, strlen($c_value[1]));
                            $rate['cost'] = $rate['cost'] - (float) $deduct;
                        } else if (strpos($c_value[1], '+') !== false && strpos($c_value[1], '+') == 0) { // under plus price
                            $add = substr($c_value[1], 1, strlen($c_value[1]));
                            $rate['cost'] = $rate['cost'] + (float) $add;
                        } else {
                            $add = $c_value[1];
                            $rate['cost'] = $rate['cost'] + (float) $add;
                        }
                    } else if ($c_value[0] == 2) { // add on by %
                        $c_value[1] = str_replace('%', '', $c_value[1]); //remove % if exist
                        if (strpos($c_value[1], '-') !== false && strpos($c_value[1], '-') == 0) { // under minus %
                            $deduct = substr($c_value[1], 1, strlen($c_value[1]));
                            if($deduct >= 100){$deduct = 100;}
                            $rate['cost'] = $rate['cost'] - ($rate['cost'] * ((float) $deduct / 100));
                        } else if (strpos($c_value[1], '+') !== false && strpos($c_value[1], '+') == 0) { // under plus %
                            $add = substr($c_value[1], 1, strlen($c_value[1]));
                            $rate['cost'] = $rate['cost'] + ($rate['cost'] * ((float) $add / 100));
                        } else {
                            $rate['cost'] = $rate['cost'] + ($rate['cost'] * ((float) $c_value[1] / 100));
                        }
                    }
                    break;
                }
            }
            //check if valid 0 >
            $rate['cost'] = ($rate['cost'] < 0) ? 0 : $rate['cost'];
            $rate['cost'] = number_format((float) $rate['cost'], 2, '.', '');
            return $rate;
        }

        public /**
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
    }

}
