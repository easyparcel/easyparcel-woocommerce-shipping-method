<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }	
                
    // add_action('woocommerce_after_shipping_rate', 'ep_pickup_point', 20, 2); #use easyparcel.php ep_pickup_point_shipping function
    add_action('wp_footer', 'ep_pickup_point_script_js');
    add_action('wp_ajax_ep_pickup_point', 'set_ep_pickup_point');
    add_action('wp_ajax_nopriv_ep_pickup_point', 'set_ep_pickup_point');
    add_action( 'woocommerce_checkout_create_order', 'save_ep_pickup_point_as_order_meta', 30, 1 );
    add_action( 'woocommerce_admin_order_data_after_shipping_address', 'admin_order_display_ep_pickup_point', 30, 1 );
    add_filter( 'woocommerce_get_order_item_totals', 'display_ep_fields_on_order_item_totals', 1000, 3 );

    function ep_pickup_point_settings() {
        $pickup_available_methods = array() ;
        if (isset(WC()->session)) {
            $pickup_available_methods = WC()->session->get("EasyParcel_Pickup_Available") ? WC()->session->get("EasyParcel_Pickup_Available") : array() ;
        }
        return array(
            'targeted_methods' => $pickup_available_methods, // Your targeted method(s) in this array
            'field_id' => 'ep_pickup_point', // Field Id
            'field_type' => 'select', // Field type
            'field_label' => '', // Leave empty value if the first option has a text (see below).
            'label_name' => __("Pick Up Point", "woocommerce"), // for validation and as meta key for orders
        );
    }

    function ep_pickup_point($method, $index) {
        extract(ep_pickup_point_settings()); // Load settings and convert them in variables
        $chosen = WC()->session->get('chosen_shipping_methods'); // The chosen methods
        $value = WC()->session->get($field_id);
        $value = WC()->session->__isset($field_id) ? $value : WC()->checkout->get_value('_' . $field_id);
        $options = array(); // Initializing
        if (!empty($chosen) && $method->id === $chosen[$index] && in_array($method->id, $targeted_methods)) {

            $field_options = WC()->session->get('EasyParcel_Pickup_'.$method->id);
            echo esc_html('<div class="custom-ep_pickup_point">');
            // Loop through field otions to add the correct keys
            foreach ($field_options as $key => $option_value) {
                $option_key = ($key === 0) ? '' : $key;
                $options[$option_key] = $option_value;
            }
            woocommerce_form_field($field_id, array(
                'type' => $field_type,
                'label' => '', // Not required if the first option has a text.
                'class' => array('form-row-wide ' . $field_id . '-' . $field_type),
                'required' => true,
                'options' => $options,
            ), $value);
            echo '</div>';
        }
    }

    function ep_pickup_point_script_js() {
        // Only cart & checkout pages
        if (is_cart() || (is_checkout() && !is_wc_endpoint_url())):
            // Load settings and convert them in variables
            extract(ep_pickup_point_settings());
            $js_variable = is_cart() ? 'wc_cart_params' : 'wc_checkout_params';
            // jQuery Ajax code
            ?>
        <script type="text/javascript">
        jQuery( function($){
            if (typeof <?php echo esc_attr($js_variable); ?> === 'undefined')
                return false;
            $(document.body).on( 'change', 'select#<?php echo esc_attr($field_id); ?>', function(){
                var value = $(this).val();
                $.ajax({
                    type: 'POST',
                    url: <?php echo esc_attr($js_variable); ?>.ajax_url,
                    data: {
                        'action': 'ep_pickup_point',
                        'value': value
                    },
                    success: function (result) {
                        console.log(result); // Only for testing (to be removed)
                    }
                });
            });
        });
        </script>
        <?php
        endif;
    }

    function set_ep_pickup_point() {
        if(isset($_POST)){
            $_POST = sanitizeEverything('sanitize_text_field', $_POST);
        }
        if (isset($_POST['value'])) {
            extract(ep_pickup_point_settings());
            if (empty($_POST['value'])) {
                $value = 0;
                $label = 'Empty';
            } else {
                $value = $label = wc_clean($_POST['value']);
            }
            WC()->session->set($field_id, $value);
            echo esc_attr($label) . ' | ' . esc_attr($value);
            die();
        }
    }

    function has_ep_pickup_point_field() {
        $settings = ep_pickup_point_settings();
        return array_intersect(WC()->session->get('chosen_shipping_methods'), $settings['targeted_methods']);
    }

    function save_ep_pickup_point_as_order_meta($order) {
        if(isset($_POST)){
            $_POST = sanitizeEverything('sanitize_text_field', $_POST);
        }
        extract(ep_pickup_point_settings());
        if (has_ep_pickup_point_field() && isset($_POST[$field_id]) && !empty($_POST[$field_id])) {
            $array = explode("::",$_POST[$field_id]);
            $order->update_meta_data('_' . $field_id."_backend", $array[0]);
            $order->update_meta_data('_' . $field_id, $array[1]);
            WC()->session->__unset($field_id);
        }
    }

    function admin_order_display_ep_pickup_point($order) {
        extract(ep_pickup_point_settings());
        $ep_pickup_point = $order->get_meta('_' . $field_id);
        if (!empty($ep_pickup_point)) {
            echo '<p><strong>' . esc_attr($label_name) . '</strong>: ' . esc_attr($ep_pickup_point) . '</p>';
        }
    }

    

    function display_ep_fields_on_order_item_totals($total_rows, $order, $tax_display) {
        // Load settings and convert them in variables
        $new_total_rows = [];
        // Loop through order total rows
        foreach ($total_rows as $key => $values) {
            $new_total_rows[$key] = $values;
            // Inserting the ep_pickup_point under shipping method
            if ($key === 'shipping') {
                extract(ep_pickup_point_settings());
                $ep_pickup_point = $order->get_meta('_' . $field_id); // Get ep_pickup_point
                if (!empty($ep_pickup_point)) {
                    $new_total_rows[$field_id] = array(
                        'label' => $label_name . ":",
                        'value' => $ep_pickup_point,
                    );
                }

                $ep_awb = (!empty($order->get_meta('_ep_awb'))) ? $order->get_meta('_ep_awb') : '- '; // Get EP AWB
                
                $selected_courier = (!empty($order->get_meta('_ep_selected_courier'))) ? $order->get_meta('_ep_selected_courier') : '-';
                // if(!empty($selected_courier)){
                //     $order_data = $order->get_data();
                //     $selected_courier = $order_data['currency']." ".number_format($order_data['shipping_total'],2)." via ".$selected_courier;
                // }
                
                $new_total_rows["final_courier"] = array(
                    'label' => "Fulfillment " . ":",
                    'value' => $selected_courier,
                );
                
                $ep_tracking_url = (!empty($order->get_meta('_ep_tracking_url'))) ? '<a href="'.$order->get_meta('_ep_tracking_url').'" target="_blank"><u>'.$ep_awb.'</u></a>' : '- '; // Get EP Tracking URL
                $new_total_rows["Tracking"] = array(
                    'label' => "Tracking" . ":",
                    'value' => $ep_tracking_url,
                );
            }
        }
        return $new_total_rows;
    }