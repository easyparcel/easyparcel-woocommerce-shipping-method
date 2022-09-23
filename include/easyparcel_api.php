<?php
if (!class_exists('Easyparcel_Shipping_API')) {
    class Easyparcel_Shipping_API {

        private static $apikey = '';
        private static $apiSecret = '';
        private static $easyparcel_email = '';
        private static $authentication = ''; # Indicate from EP
        private static $integration_id = '';

        private static $sender_name = '';
        private static $sender_contact_number = '';
        private static $sender_alt_contact_number = '';
        private static $sender_company_name = '';
        private static $sender_address_1 = '';
        private static $sender_address_2 = '';
        private static $sender_postcode = '';
        private static $sender_city = '';
        private static $sender_state = '';
        private static $sender_country = '';

        private static $addon_email_option = '';
        private static $addon_sms_option = '';

        // private static $getrate_api_url = ''; // Hide it cause didn't use bulk api
        // private static $submitorder_api_url = ''; // Hide it cause didn't use bulk api
        // private static $payorder_api_url = ''; // Hide it cause didn't use bulk api

        private static $getrate_bulk_api_url = '';
        private static $submit_bulk_order_api_url = '';
        private static $pay_bulk_order_api_url = '';

        private static $auth_url = '';

        /**
         * init
         *
         * @access public
         * @return void
         */
        public static function init() {

            $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

            self::$sender_country = $WC_Easyparcel_Shipping_Method->settings['sender_country'];
            $host = 'http://connect.easyparcel.'.strtolower(self::$sender_country);

            // self::$getrate_api_url = $host . '/?ac=EPRateChecking'; // Hide it cause didn't use bulk api
            // self::$submitorder_api_url = $host . '/?ac=EPSubmitOrder'; // Hide it cause didn't use bulk api
            // self::$payorder_api_url = $host . '/?ac=EPPayOrder'; // Hide it cause didn't use bulk api

            self::$getrate_bulk_api_url = $host . '/?ac=EPRateCheckingBulk';
            self::$submit_bulk_order_api_url = $host . '/?ac=EPSubmitOrderBulk';
            self::$pay_bulk_order_api_url = $host . '/?ac=EPPayOrderBulk';

            self::$auth_url = $host . '?ac=EPCheckCreditBalance';

            self::$easyparcel_email = $WC_Easyparcel_Shipping_Method->settings['easyparcel_email'];
            self::$integration_id = $WC_Easyparcel_Shipping_Method->settings['integration_id'];

            self::$sender_name = $WC_Easyparcel_Shipping_Method->settings['sender_name'];
            self::$sender_contact_number = $WC_Easyparcel_Shipping_Method->settings['sender_contact_number'];
            self::$sender_alt_contact_number = $WC_Easyparcel_Shipping_Method->settings['sender_alt_contact_number'];
            self::$sender_company_name = $WC_Easyparcel_Shipping_Method->settings['sender_company_name'];
            self::$sender_address_1 = $WC_Easyparcel_Shipping_Method->settings['sender_address_1'];
            self::$sender_address_2 = $WC_Easyparcel_Shipping_Method->settings['sender_address_2'];
            self::$sender_postcode = $WC_Easyparcel_Shipping_Method->settings['sender_postcode'];
            self::$sender_city = $WC_Easyparcel_Shipping_Method->settings['sender_city'];
            self::$sender_state = $WC_Easyparcel_Shipping_Method->settings['sender_state'];

            self::$addon_email_option = $WC_Easyparcel_Shipping_Method->settings['addon_email_option'];
            self::$addon_sms_option = $WC_Easyparcel_Shipping_Method->settings['addon_sms_option'];

        }

        public static function countryValidate() {
            $WC_Country = new WC_Countries();
            if(strtolower($WC_Country->get_base_country()) == strtolower(self::$sender_country)){
                return true;
            }else{
                return false;
            }
        }

        public static function curlPost($data) {
            $r = '';

            // $ch = curl_init();
            // curl_setopt($ch, CURLOPT_URL, $data->url);
            // curl_setopt($ch, CURLOPT_POST, 1);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $data->pfs);
            // curl_setopt($ch, CURLOPT_HEADER, 0);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            // ob_start();
            // $r = curl_exec($ch);
            // ob_end_clean();
            // curl_close($ch);
            $args = array(
                'body'        => $data->pfs,
                'blocking'    => true,
                'headers'     => array(),
            );
            $r = wp_remote_post($data->url, $args );
            return $r;
        }

        public static function auth() {
            // $auth = array(
            //     'user_email' => self::$easyparcel_email,
            //     'integration_id' => self::$integration_id,
            // );
            $auth = array(
                'api' => self::$integration_id,
            );

            $data = (object) array();
            $data->url = self::$auth_url;
            $data->pfs = $auth;

            $r = self::curlPost($data);
            if (!is_wp_error($r)) {
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';
            }else{
                $json = '';
            }
            // Success.

            // echo '<pre>';print_r($data);echo '</pre>';
            // echo '<pre>';print_r($auth);echo '</pre>';
            // echo '<pre>';print_r($r);echo '</pre>';
            // echo '<pre>';print_r($json->error_code);echo '</pre>';
            // die();

            if(isset($json->error_code) && $json->error_code != '0'){
                return $json->error_remark;
            }else{
                return 'Success.';
            }
        }

        public static function getShippingRate($destination,$items,$weight)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                if ($weight == 0 || $weight == '') {$weight = 0.1;}

                $i = 0;
                $length = 0;
                $width = 0;
                $height = 0;
                foreach ($items as $item) {
                    if (is_numeric($item['width']) && is_numeric($item['length']) && is_numeric($item['height'])) {
                        $length += (float) $item['length'];
                        $width += (float) $item['width'];
                        $height += (float) $item['height'];
                    }
                    $i++;
                }

                $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

                if ($WC_Easyparcel_Shipping_Method->settings['cust_rate'] == 'normal_rate') {self::$easyparcel_email = '';
                    self::$integration_id = '';
                }

                //prevent user select fix Rate but didnt put postcode no result
                if ($WC_Easyparcel_Shipping_Method->settings['cust_rate'] == 'fix_rate' && self::$sender_postcode == '') {self::$sender_postcode = '11950';}

                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'pick_country' => strtolower(self::$sender_country),
                    'pick_code' => self::$sender_postcode,
                    'pick_state' => self::$sender_state,
                    'send_country' => strtolower($destination['country']),
                    'send_country' => $destination['country'],
                    'send_state' => ($destination['state'] == '') ? (($destination['country'] == 'sg') ? 'central' : '') : $destination['state'],
                    'send_code' => ($destination['postcode'] == '') ? (($destination['country'] == 'sg') ? '058275' : '') : $destination['postcode'], # required
                    'weight' => $weight,
                    'width' => $width,
                    'height' => $height,
                    'length' => $length,
                );
                array_push($bulk_order['bulk'], $f);
                // print_r($f);die();

                $data = (object)array();
                $data->url = self::$getrate_bulk_api_url;
                $data->pfs = http_build_query($bulk_order);
                
                $r = self::curlPost($data);
                
                if(is_array($r)){
                    $json = (!empty($r['body']) && !is_wp_error($r['body'])) ? json_decode($r['body']) : '';
                }else{
                    $json = '';
                }

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($bulk_order);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                /* debug on problem hapen to php newer than 7.2
                if(!empty($json) && isset($json->result[0])){
                    if(sizeof($json->result[0]->rates) > 0){
                        return $json->result[0]->rates;
                    }else{
                        return array();
                    }
                }else{
                    return array();
                }
                */

                if(!empty($json) && isset($json->result[0])){
                    if(!empty($json->result[0]->rates)){
                        return $json->result[0]->rates;
                    }else{
                        return array();
                    }
                }else{
                    return array();
                }
                
            }

            // if no support sender country
            return array(); // return empty array
        }

        /*public static function getShippingRate($destination,$items,$weight) // Hide it cause didn't use bulk api
        {
            if(self::countryValidate()){

                if ($weight == 0 || $weight == '') {$weight = 0.1;}

                $i = 0;
                $length = 0;
                $width = 0;
                $height = 0;
                foreach ($items as $item) {
                    if (is_numeric($item['width']) && is_numeric($item['length']) && is_numeric($item['height'])) {
                        $length += (float) $item['length'];
                        $width += (float) $item['width'];
                        $height += (float) $item['height'];
                    }
                    $i++;
                }

                $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

                if ($WC_Easyparcel_Shipping_Method->settings['cust_rate'] == 'normal_rate') {self::$easyparcel_email = '';
                    self::$integration_id = '';
                }

                //prevent user select fix Rate but didnt put postcode no result
                if ($WC_Easyparcel_Shipping_Method->settings['cust_rate'] == 'fix_rate' && self::$sender_postcode == '') {self::$sender_postcode = '11950';}

                $pv = '';
                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'pick_country' => strtolower(self::$sender_country),
                    'pick_code' => self::$sender_postcode,
                    'pick_state' => self::$sender_state,
                    'send_country' => strtolower($destination['country']),
                    'send_country' => $destination['country'],
                    'send_state' => ($destination['state'] == '') ? (($destination['country'] == 'sg') ? 'central' : '') : $destination['state'],
                    'send_code' => ($destination['postcode'] == '') ? (($destination['country'] == 'sg') ? '058275' : '') : $destination['postcode'], # required
                    'weight' => $weight,
                    'width' => $width,
                    'height' => $height,
                    'length' => $length,
                );
                // print_r($f);die();

                foreach($f as $k => $v){ $pv .= $k . '=' . $v . '&'; }

                $data = (object)array();
                $data->url = self::$getrate_api_url;
                $data->pfs = $pv;
                
                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();
                if(!empty($json->rates)){
                    if(sizeof($json->rates) > 0){
                        return $json->rates;
                    }else{
                        return array();
                    }
                }else{
                    return array();
                }
                
            }

            // if no support sender country
            return array(); // return empty array
        }*/

        public static function submitOrder($obj)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                $send_point = ''; // EP Buyer Pickup Point
                if( $obj->order->meta_exists('_ep_pickup_point_backend') && !empty($obj->order->get_meta('_ep_pickup_point_backend')) ){
                    $send_point = $obj->order->get_meta('_ep_pickup_point_backend');
                }
                $send_name = $obj->order->get_shipping_first_name().' '.$obj->order->get_shipping_last_name();
                $send_company = $obj->order->get_shipping_company();
                $send_contact = $obj->order->get_billing_phone();
                if( version_compare( WC_VERSION, '5.6', '>=' ) ){
                    ### WC 5.6 and above only can use shipping phone ###
                    if( !empty($obj->order->get_shipping_phone()) ){
                        $send_contact = $obj->order->get_shipping_phone();
                    }
                }
                $send_addr1 = $obj->order->get_shipping_address_1();
                $send_addr2 = $obj->order->get_shipping_address_2();
                $send_city = $obj->order->get_shipping_city();
                $send_code = !empty($obj->order->get_shipping_postcode()) ? $obj->order->get_shipping_postcode() : '';
                $send_state = !empty($obj->order->get_shipping_state()) ? $obj->order->get_shipping_state() : '';
                $send_country = !empty($obj->order->get_shipping_country()) ? $obj->order->get_shipping_country() : '';

                //add on email
                if(self::$addon_email_option == 'yes' && strtolower(self::$sender_country) != 'sg'){
                    $send_email = $obj->order->get_billing_email();
                }else{
                    $send_email = '';
                }

                //add on sms
                if(self::$addon_sms_option == 'yes' && strtolower(self::$sender_country) != 'sg'){
                    $sms = 1;
                }else{
                    $sms = 0;
                }

                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,

                    'pick_point' => $obj->drop_off_point, # optional
                    'pick_name' => self::$sender_name,
                    'pick_company' => self::$sender_company_name, # optional
                    'pick_contact' => self::$sender_contact_number,
                    'pick_mobile' => self::$sender_alt_contact_number, # optional
                    'pick_unit' => self::$sender_address_1, ### for sg address only ###
                    'pick_addr1' => self::$sender_address_1,
                    'pick_addr2' => self::$sender_address_2, # optional
                    'pick_addr3' => '', # optional
                    'pick_addr4' => '', # optional
                    'pick_city' => self::$sender_city,
                    'pick_code' => self::$sender_postcode,
                    'pick_state' => self::$sender_state,
                    'pick_country' => self::$sender_country,

                    'send_point' => $send_point, # optional
                    'send_name' => $send_name,
                    'send_company' => $send_company, # optional
                    'send_contact' => $send_contact,
                    'send_mobile' => '', # optional
                    'send_unit' => $send_addr1, ### for sg address only ###
                    'send_addr1' => (strtolower(self::$sender_country) == 'sg') ? $send_addr2 : $send_addr1,
                    'send_addr2' => $send_addr2, # optional
                    'send_addr3' => '', # optional
                    'send_addr4' => '', # optional
                    'send_city' => $send_city,
                    'send_code' => $send_code, # required
                    'send_state' => $send_state,
                    'send_country' => $send_country,
                    
                    'weight' => $obj->weight,
                    'width' => $obj->width,
                    'height' => $obj->height,
                    'length' => $obj->length,
                    'content' => $obj->content,
                    'value' => $obj->item_value,
                    'service_id' => $obj->service_id,
                    'collect_date'	=> $obj->collect_date,
                    'sms'	=> $sms, # optional
                    'send_email'	=> $send_email, # optional
                    'hs_code'	=> '', # optional
                    'REQ_ID'	=> '', # optional
                    'reference'	=> '' # optional
                );
                array_push($bulk_order['bulk'], $f);
                // print_r($f);die();
                // echo "<pre>";print_r($f);echo "</pre>";
                // echo "<pre>";print_r($obj);echo "</pre>";
                // die();

                $data = (object)array();
                $data->url = self::$submit_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);
                
                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json) > 0 && isset($json->result[0])){
                    return $json->result[0];
                }else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }
        
        /*public static function submitOrder($obj) // Hide it cause didn't use bulk api
        {
            if(self::countryValidate()){

                $send_point = ''; // EP Buyer Pickup Point
                if( $obj->order->meta_exists('_ep_pickup_point_backend') && !empty($obj->order->get_meta('_ep_pickup_point_backend')) ){
                    $send_point = $obj->order->get_meta('_ep_pickup_point_backend');
                }
                $send_name = $obj->order->get_shipping_first_name().' '.$obj->order->get_shipping_last_name();
                $send_company = $obj->order->get_shipping_company();
                $send_contact = $obj->order->get_billing_phone();
                if( version_compare( WC_VERSION, '5.6', '>=' ) ){
                    ### WC 5.6 and above only can use shipping phone ###
                    if( !empty($obj->order->get_shipping_phone()) ){
                        $send_contact = $obj->order->get_shipping_phone();
                    }
                }
                $send_addr1 = $obj->order->get_shipping_address_1();
                $send_addr2 = $obj->order->get_shipping_address_2();
                $send_city = $obj->order->get_shipping_city();
                $send_code = !empty($obj->order->get_shipping_postcode()) ? $obj->order->get_shipping_postcode() : '';
                $send_state = !empty($obj->order->get_shipping_state()) ? $obj->order->get_shipping_state() : '';
                $send_country = !empty($obj->order->get_shipping_country()) ? $obj->order->get_shipping_country() : '';

                //add on email
                if(self::$addon_email_option == 'yes' && strtolower(self::$sender_country) != 'sg'){
                    $send_email = $obj->order->get_billing_email();
                }else{
                    $send_email = '';
                }

                //add on sms
                if(self::$addon_sms_option == 'yes' && strtolower(self::$sender_country) != 'sg'){
                    $sms = 1;
                }else{
                    $sms = 0;
                }

                $pv = '';
                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,

                    'pick_point' => $obj->drop_off_point, # optional
                    'pick_name' => self::$sender_name,
                    'pick_company' => self::$sender_company_name, # optional
                    'pick_contact' => self::$sender_contact_number,
                    'pick_mobile' => self::$sender_alt_contact_number, # optional
                    'pick_unit' => self::$sender_address_1, ### for sg address only ###
                    'pick_addr1' => self::$sender_address_1,
                    'pick_addr2' => self::$sender_address_2, # optional
                    'pick_addr3' => '', # optional
                    'pick_addr4' => '', # optional
                    'pick_city' => self::$sender_city,
                    'pick_code' => self::$sender_postcode,
                    'pick_state' => self::$sender_state,
                    'pick_country' => self::$sender_country,

                    'send_point' => $send_point, # optional
                    'send_name' => $send_name,
                    'send_company' => $send_company, # optional
                    'send_contact' => $send_contact,
                    'send_mobile' => '', # optional
                    'send_unit' => $send_addr1, ### for sg address only ###
                    'send_addr1' => (strtolower(self::$sender_country) == 'sg') ? $send_addr2 : $send_addr1,
                    'send_addr2' => $send_addr2, # optional
                    'send_addr3' => '', # optional
                    'send_addr4' => '', # optional
                    'send_city' => $send_city,
                    'send_code' => $send_code, # required
                    'send_state' => $send_state,
                    'send_country' => $send_country,
                    
                    'weight' => $obj->weight,
                    'width' => $obj->width,
                    'height' => $obj->height,
                    'length' => $obj->length,
                    'content' => $obj->content,
                    'value' => $obj->item_value,
                    'service_id' => $obj->service_id,
                    'collect_date'	=> $obj->collect_date,
                    'sms'	=> $sms, # optional
                    'send_email'	=> $send_email, # optional
                    'hs_code'	=> '', # optional
                    'REQ_ID'	=> '', # optional
                    'reference'	=> '' # optional
                );
                // print_r($f);die();
                // echo "<pre>";print_r($f);echo "</pre>";
                // echo "<pre>";print_r($obj);echo "</pre>";
                // die();

                foreach($f as $k => $v){ $pv .= $k . '=' . $v . '&'; }

                $data = (object)array();
                $data->url = self::$submitorder_api_url;
                $data->pfs = $pv;
                
                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json)){
                    return $json;
                }else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }*/

        public static function payOrder($obj)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );
                
                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'order_no' => $obj->ep_order_number,
                );
                array_push($bulk_order['bulk'], $f);
                // print_r($f);die();

                $data = (object) array();
                $data->url = self::$pay_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);

                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json)){
                    return $json;
                } else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }

        /*public static function payOrder($obj) // Hide it cause didn't use bulk api
        {
            if(self::countryValidate()){

                $pv = '';
                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'order_no' => $obj->ep_order_number,
                );
                // print_r($f);die();

                foreach ($f as $k => $v) {$pv .= $k . '=' . $v . '&';}

                $data = (object) array();
                $data->url = self::$payorder_api_url;
                $data->pfs = $pv;

                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if (!empty($json)) {
                    return $json;
                } else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }*/

        public static function submitBulkOrder($orders)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                foreach ($orders as $obj) {
                    $send_point = ''; // EP Buyer Pickup Point
                    if( $obj->order->meta_exists('_ep_pickup_point_backend') && !empty($obj->order->get_meta('_ep_pickup_point_backend')) ){
                        $send_point = $obj->order->get_meta('_ep_pickup_point_backend');
                    }
                    $send_name = $obj->order->get_shipping_first_name().' '.$obj->order->get_shipping_last_name();
                    $send_company = $obj->order->get_shipping_company();
                    $send_contact = $obj->order->get_billing_phone();
                    if( version_compare( WC_VERSION, '5.6', '>=' ) ){
                        ### WC 5.6 and above only can use shipping phone ###
                        if( !empty($obj->order->get_shipping_phone()) ){
                            $send_contact = $obj->order->get_shipping_phone();
                        }
                    }
                    $send_addr1 = $obj->order->get_shipping_address_1();
                    $send_addr2 = $obj->order->get_shipping_address_2();
                    $send_city = $obj->order->get_shipping_city();
                    $send_code = !empty($obj->order->get_shipping_postcode()) ? $obj->order->get_shipping_postcode() : '';
                    $send_state = !empty($obj->order->get_shipping_state()) ? $obj->order->get_shipping_state() : '';
                    $send_country = !empty($obj->order->get_shipping_country()) ? $obj->order->get_shipping_country() : '';

                    //add on email
                    if(self::$addon_email_option == 'yes' && strtolower(self::$sender_country) != 'sg'){
                        $send_email = $obj->order->get_billing_email();
                    }else{
                        $send_email = '';
                    }

                    //add on sms
                    if(self::$addon_sms_option == 'yes' && strtolower(self::$sender_country) != 'sg'){
                        $sms = 1;
                    }else{
                        $sms = 0;
                    }

                    $f = array(
                        'pick_point' => $obj->drop_off_point, # optional
                        'pick_name' => self::$sender_name,
                        'pick_company' => self::$sender_company_name, # optional
                        'pick_contact' => self::$sender_contact_number,
                        'pick_mobile' => self::$sender_alt_contact_number, # optional
                        'pick_unit' => self::$sender_address_1, ### for sg address only ###
                        'pick_addr1' => self::$sender_address_1,
                        'pick_addr2' => self::$sender_address_2, # optional
                        'pick_addr3' => '', # optional
                        'pick_addr4' => '', # optional
                        'pick_city' => self::$sender_city,
                        'pick_code' => self::$sender_postcode,
                        'pick_state' => self::$sender_state,
                        'pick_country' => self::$sender_country,

                        'send_point' => $send_point, # optional
                        'send_name' => $send_name,
                        'send_company' => $send_company, # optional
                        'send_contact' => $send_contact,
                        'send_mobile' => '', # optional
                        'send_unit' => $send_addr1, ### for sg address only ###
                        'send_addr1' => (strtolower(self::$sender_country) == 'sg') ? $send_addr2 : $send_addr1,
                        'send_addr2' => $send_addr2, # optional
                        'send_addr3' => '', # optional
                        'send_addr4' => '', # optional
                        'send_city' => $send_city,
                        'send_code' => $send_code, # required
                        'send_state' => $send_state,
                        'send_country' => $send_country,
                        
                        'weight' => $obj->weight,
                        'width' => $obj->width,
                        'height' => $obj->height,
                        'length' => $obj->length,
                        'content' => $obj->content,
                        'value' => $obj->item_value,
                        'service_id' => $obj->service_id,
                        'collect_date'	=> $obj->collect_date,
                        'sms'	=> $sms, # optional
                        'send_email'	=> $send_email, # optional
                        'hs_code'	=> '', # optional
                        'REQ_ID'	=> '', # optional
                        'reference'	=> '' # optional
                    );

                    array_push($bulk_order['bulk'], $f);
                    
                }
                
                // print_r($f);die();
                // echo "<pre>";print_r($orders);echo "</pre>";
                // echo "<pre>";print_r($bulk_order);echo "</pre>";
                // die();

                $data = (object)array();
                $data->url = self::$submit_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);
                
                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json)){
                    return $json;
                }else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }

        public static function payBulkOrder($orders)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                foreach ($orders as $order_no) {
                    $f = array(
                        'order_no' => $order_no,
                    );

                    array_push($bulk_order['bulk'], $f);
                }

                // print_r($f);die();
                // echo "<pre>";print_r($orders);echo "</pre>";
                // echo "<pre>";print_r($bulk_order);echo "</pre>";
                // die();

                $data = (object) array();
                $data->url = self::$pay_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);

                $r = self::curlPost($data);
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if (!empty($json)) {
                    return $json;
                } else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }
    }
}