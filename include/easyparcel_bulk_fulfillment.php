<?php
    add_filter( 'bulk_actions-edit-shop_order', 'bulk_actions_order_fulfillment', 20, 1 );
    add_filter( 'handle_bulk_actions-edit-shop_order', 'handle_bulk_actions_order_fulfillment', 10, 3 );
    add_action( 'admin_notices', 'bulk_actions_order_fulfillment_admin_notice' );
    add_action( 'wp_ajax_easyparcel_bulk_fulfillment_popup', 'easyparcel_bulk_fulfillment_popup' );	
    add_filter( 'manage_shop_order_posts_columns', 'shop_order_columns', 99 );
    add_action( 'manage_shop_order_posts_custom_column', 'render_shop_order_columns' );
    add_filter( 'manage_shop_order_posts_columns', 'destination_columns', 99 );
    add_filter( "manage_edit-shop_order_sortable_columns", 'destination_columns_sortable' );
    add_action( 'pre_get_posts', 'shop_order_column_destination_sortable_orderby' );
    add_action( 'manage_shop_order_posts_custom_column', 'render_destination_columns' );
    add_action( 'wp_ajax_wc_shipment_tracking_save_form_bulk', 'save_bulk_order_ajax' );

    function bulk_actions_order_fulfillment( $actions ) {
        $actions['order_fulfillment'] = __( 'Order Fulfillment', 'woocommerce' );
        return $actions;
    }

    function handle_bulk_actions_order_fulfillment( $redirect_to, $action, $post_ids ) {
        if ( $action !== 'order_fulfillment' ){
            return $redirect_to;
        }

        $processed_ids = array();

        foreach ( $post_ids as $post_id ) {
            // $order = wc_get_order( $post_id );
            // $order_data = $order->get_data();
            $processed_ids[] = $post_id;
        }
        return $redirect_to = add_query_arg( array(
            'order_fulfillment' => '1',
            'processed_count' => count( $processed_ids ),
            'processed_ids' => implode( ',', $processed_ids ),
        ), $redirect_to );
    }

    function bulk_actions_order_fulfillment_admin_notice() {
        if ( empty( $_REQUEST['order_fulfillment'] ) ) return;
        $count = intval( $_REQUEST['processed_count'] );
        printf( '<div id="message" class="updated fade"><p>' .
            _n( 'Processed %s Order for fulfillment.',
            'Processed %s Orders for fulfillment.',
            $count,
            'order_fulfillment'
        ) . '</p></div>', $count );
    }


    function easyparcel_bulk_fulfillment_popup() {
        if(isset($_POST)){
            $_POST = sanitizeEverything('sanitize_text_field', $_POST);
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            exit( 'You are not allowed' );
        }

        check_ajax_referer( 'easyparcel_bulk_fulfillment_popup', 'security' );
        $order_ids =  isset( $_POST['order_ids'] ) ? wc_clean( $_POST['order_ids'] ) :'';
        $paid_order_ids = array();
        $unpaid_order_ids = array();

        foreach($order_ids as $order_id) {
            $order = wc_get_order( $order_id );
            $easyparcel_paid_status = ($order->meta_exists('_ep_payment_status')) ? 1 : 0; # 1 = Paid / 0 = Pending
            if($easyparcel_paid_status){
                array_push($paid_order_ids, $order_id);
            }else{
                array_push($unpaid_order_ids, $order_id);
            }
        }

        ob_start();
        if($unpaid_order_ids){
            $order_id = $order_number = implode(',', $unpaid_order_ids);
            $post = (object)array();
            $post->ID = $unpaid_order_ids[0];

            $default_provider = '';

            $api_detail = get_api_detail_bulk($post);
            $shipment_providers_by_country = $api_detail->shipment_providers_list;
		    $dropoff_point_list = json_encode($api_detail->dropoff_point_list);
            // echo "<pre>";print_r($api_detail);echo "</pre>";
            // die();
            ob_start();
        ?>
        <div id="easyparcel_fulfillment_popout" class="fulfillment_popup_wrapper add_fulfillment_popup" >
            <div class="fulfillment_popup_row">
                <div class="popup_header">
                    <h3 class="popup_title"><?php esc_html_e( 'Shipment Fulfillment'); ?> - #<?php esc_html_e( $order_number ); ?></h2>					
                    <span class="dashicons dashicons-no-alt popup_close_icon"></span>
                </div>
                <div class="popup_body">
                    <form id="add_fulfillment_form" method="POST" class="add_fulfillment_form">	
                        <p class="form-field form-50">
                            <label for="shipping_provider"><?php esc_html_e( 'Courier Services:'); ?></label>
                            <select class="chosen_select shipping_provider_dropdown" id="shipping_provider" name="shipping_provider">
                                <option value=""><?php esc_html_e( 'Select Preferred Courier Service'); ?></option>
                                <?php 
                                    foreach ( $shipment_providers_by_country as $providers ) {		
                                        $providers->ts_slug;	
                                        $selected = ( $providers->provider_name == $default_provider ) ? 'selected' : '';
                                        echo '<option value="' . esc_attr( $providers->ts_slug ) . '" ' . esc_html( $selected ) . '>' . esc_html( $providers->provider_name ) . '</option>';
                                    }
                                ?>
                            </select>
                        </p>
                        <?php
                        #### drop off - S ####
                        echo '<p class="form-field drop_off_field form-50"></p>';
                        woocommerce_wp_hidden_input( array(
                            'id'    => 'easyparcel_dropoff',
                            'value' => $dropoff_point_list
                        ) );
                        #### drop off - E ####
                        ?>
                        <p class="form-field date_shipped_field form-50">
                            <label for="date_shipped"><?php esc_html_e( 'Drop Off / Pick Up Date'); ?></label>
                            <input type="text" class="ast-date-picker-field" name="date_shipped" id="date_shipped" value="<?php echo esc_html( date_i18n( __( 'Y-m-d'), current_time( 'timestamp' ) ) ); ?>" placeholder="<?php echo esc_html( date_i18n( esc_html_e( 'Y-m-d'), time() ) ); ?>">						
                        </p>								
                        <hr>
                        <p>		
                            <input type="hidden" name="action" value="add_shipment_fulfillment">
                            <input type="hidden" name="order_id" id="order_id" value="<?php esc_html_e( $order_id ); ?>">
                            <input type="button" name="Submit" value="<?php esc_html_e( 'Fulfill Order'); ?>" class="button-primary btn_green button-save-form">    
                        </p>			
                    </form>
                </div>								
            </div>
            <div class="popupclose"></div>
        </div>
        <?php
            ob_end_flush(); 
        }else{
            $selected_paid_order_id = implode(',', $paid_order_ids);
            echo '<script>alert("your selected shipment have been fulfill.")</script>';
        }
        exit;	
    }

    function save_bulk_order_ajax() {
        if(isset($_POST)){
            $_POST = sanitizeEverything('sanitize_text_field', $_POST);
        }
        check_ajax_referer( 'easyparcel_bulk_fulfillment_popup', 'security', true );
        $shipping_provider = isset( $_POST['shipping_provider'] ) ? wc_clean( $_POST['shipping_provider'] ) : '';
		$courier_name = isset( $_POST['courier_name'] ) ? wc_clean( $_POST['courier_name'] ) : '';
		$drop_off_point = isset( $_POST['drop_off_point'] ) ? wc_clean( $_POST['drop_off_point'] ) : '';
        $pick_up_date = isset( $_POST['pick_up_date'] ) ? wc_clean($_POST['pick_up_date']) : '';
		$order_id = isset( $_POST['order_id'] ) ? wc_clean($_POST['order_id']) : '';

		### Bulk Order Part ###
		if (!class_exists('WC_Easyparcel_Shipping_Method')) {
			include_once 'easyparcel_shipping.php';
		}
		$WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

        if ( $pick_up_date != '' && $shipping_provider != '' ) {
            $obj = (object)array();
            $obj->order_id = $order_id;
            $obj->pick_up_date = $pick_up_date;
            $obj->shipping_provider = $shipping_provider;
            $obj->courier_name = $courier_name;
            $obj->drop_off_point = $drop_off_point;
            $ep_order = $WC_Easyparcel_Shipping_Method->process_bulk_booking_order($obj);
            if(!empty($ep_order)){
                print_r($ep_order);
            }else{
                echo 'success';	
            }
            
        }else{
            echo 'Please fill all the required data.';
        }

        die();
    }

    function get_api_detail_bulk($post) {

		if (!class_exists('WC_Easyparcel_Shipping_Method')) {
			include_once 'easyparcel_shipping.php';
		}

		$WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
		$rates = $WC_Easyparcel_Shipping_Method->get_admin_shipping($post);

		$obj = (object)array();
		$obj->shipment_providers_list = array();
		$obj->dropoff_point_list = array();

		foreach($rates as $rate){
			$shipment_provider = (object)array();
			$shipment_provider->ts_slug = $rate['id'];
			$shipment_provider->provider_name = $rate['label'];
			array_push($obj->shipment_providers_list, $shipment_provider);

			$dropoff = array();
			$dropoff[$rate['id']] = $rate['dropoff_point'];
			array_push($obj->dropoff_point_list, $dropoff);
		}
		// echo "<pre>";print_r($obj->dropoff_point_list);echo "</pre>";

		return $obj;
	}

    function shop_order_columns( $columns ) {
        $columns['easyparcel_order_list_shipment_tracking'] = __( 'Shipment Tracking', 'easyparcel-shipping-integration' );
        return $columns;
    }

    function render_shop_order_columns( $column ) {
        global $post;
        if ( 'easyparcel_order_list_shipment_tracking' === $column ) {
            echo wp_kses_post( get_shipment_tracking_column( $post->ID ) );
        }
    }

    function destination_columns( $columns ) {
        $columns['easyparcel_order_list_destination'] = __( 'Destination', 'easyparcel-shipping-integration' );
        return $columns;
    }

    function render_destination_columns( $column ) {
        global $post, $the_order;
        if ( ! is_a( $the_order, 'WC_Order' ) ) {
            $the_order = wc_get_order( $post->ID );
        }

        if ( $column == 'easyparcel_order_list_destination' ) {
            $WC_Country = new WC_Countries();
            if(strtolower($WC_Country->get_base_country()) !== strtolower($the_order->get_shipping_country())){
                echo "International";
            }else{
                echo "Domestic";
            }
        }
    }

    function get_shipment_tracking_column( $order_id ) {
        
        wp_enqueue_style( 'easyparcel_order_list_styles', plugin_dir_url(__FILE__).'/css/admin.css', array());
        wp_enqueue_script( 'easyparcel-admin-order-js', plugin_dir_url(__FILE__).'/js/admin_order.js', array( 'jquery' ));
        wp_localize_script(
            'easyparcel-admin-order-js',
            'easyparcel_orders_params',
            array(
                'order_nonce' => wp_create_nonce( 'easyparcel_bulk_fulfillment_popup' ),
            )
        );
        ob_start();

        $order = wc_get_order($order_id);
        $easyparcel_paid_status = ($order->meta_exists('_ep_payment_status')) ? 1 : 0;
        if($easyparcel_paid_status == 1){
            $selected_courier = (!empty($order->get_meta('_ep_selected_courier'))) ? $order->get_meta('_ep_selected_courier') : '-';
            $awb = (!empty($order->get_meta('_ep_awb'))) ? $order->get_meta('_ep_awb') : '-';
            $tracking_url = (!empty($order->get_meta('_ep_tracking_url'))) ? $order->get_meta('_ep_tracking_url') : '-';
            $awb_link = (!empty($order->get_meta('_ep_awb_id_link'))) ? $order->get_meta('_ep_awb_id_link') : '-';
            echo '<ul class="easyparcel_order_list_shipment_tracking">';
                printf(
                    '<li>
                        <div><b>%s</b>
                        </div>
                        <a href="%s" target="_blank">%s</a>
                        <a href="%s" target="_blank">[Download AWB]</a>
                    </li>',
                    esc_html( $selected_courier),
                    esc_html( $tracking_url ),
                    esc_html( $awb ),
                    esc_html( $awb_link )
                );
            echo '</ul>';
        } else {
            echo '–';			
        }		
        return apply_filters( 'easyparcel_get_shipment_tracking_column', ob_get_clean(), $order_id );
    }

    
    function destination_columns_sortable( $columns )
    {
        $meta_key = '_shipping_country';
        return wp_parse_args( array('easyparcel_order_list_destination' => $meta_key), $columns );
    }

    // Make sorting work properly (by numerical values)
    function shop_order_column_destination_sortable_orderby( $query ) {
        global $pagenow;
        if ( 'edit.php' === $pagenow && isset($_GET['post_type']) && 'shop_order' === $_GET['post_type'] ){
            $orderby  = $query->get( 'orderby');
            $meta_key = '_shipping_country';
            if ('_shipping_country' === $orderby){
                $query->set('meta_key', $meta_key);
                $query->set('orderby', 'meta_value');
            }
        }
    }
