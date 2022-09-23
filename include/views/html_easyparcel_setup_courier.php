<?php
/**
 * Shipping zone admin
 *
 * @package WooCommerce\Admin\Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>
	<span class="wc-shipping-zone-name"><?php esc_html_e( 'Courier Setting > ', 'easyparcel_zone_method' ); ?><?php echo esc_html( $zone->get_zone_name() ? $zone->get_zone_name() : __( 'Zone', 'easyparcel_zone_method' ) ); ?></span>
</h2>

<table class="form-table wc-shipping-zone-settings" id="courier-setting-table">
	<tbody>
		<?php if ( 0 !== $zone->get_id() ) : ?>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="zone_region">
						<?php esc_html_e( 'Zone Region', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'The zone regions you setup for', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<?php echo esc_attr($zone->get_formatted_location()) ?>
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="courier_service">
						<?php esc_html_e( 'Courier Service', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred couriers to be displayed on the checkout page.', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="courier_services" id="courier_services" name="courier_services" data-placeholder="<?php esc_attr_e( 'Select courier service', 'easyparcel_zone_method' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $courier_list as $k => $v ) {
							$dropoff_option = (!empty($v['dropoff_point'])) ? 'yes' : 'no' ;
							echo '<option value="' . esc_attr( $k ).'"  data-service_name="' . esc_html($v['service_name']) . '" data-courier_id="' . esc_html($v['courier_id']) . '" data-courier_name="' . esc_html($v['courier_name']) .'" data-courier_logo="' . esc_html($v['courier_logo']) .'" data-courier_info="' . esc_html($v['courier_info']) .'" data-service_id="' . esc_html($v['service_id']) .'" data-sample_cost="' . esc_html($v['sample_cost']) .'" data-dropoff="' . $dropoff_option .'" >' . esc_html($v['service_name']). ' ['. esc_html($v['courier_info']) . '] '.'</option>';
						}
						?>
					</select>
					<?php 
					foreach ( $courier_list as $k => $v ) {
						if(!empty($v['dropoff_point'])){
							echo '<div id="'.esc_html($v['courier_id']).'" style="display:none">';
							foreach($v['dropoff_point'] as $dpk => $dpv){
								echo '<option value="' . esc_attr( $dpv->point_id ) . '">' . esc_html( $dpv->point_name ) . '</option>';
							}
							echo'</div>';
						}
					}
					?>
					<img class="img-wrap" id="courier_service_img" width="auto !important" height="30px !important" src="" style="display:inline-block;">
				</td>
			</tr>
			<tr valign="top" class="" id="courier_dropoff_panel" style="display:none">
				<th scope="row" class="titledesc">
					<label for="charges">
						<?php esc_html_e( 'Courier Dropoff Point', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Choose the dropoff point you wish to dropoff your parcel. [optional]', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="dropoff_point" id="dropoff_point" name="dropoff_point" data-placeholder="<?php esc_attr_e( 'Select your dropoff point', 'easyparcel_zone_method' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						
					</select>
				</td>
			</tr>
			<tr valign="top" class="" id="courier_display_name_panel" style="display:none">
				<th scope="row" class="titledesc">
					<label for="courier_display_name">
						<?php esc_html_e( 'Courier Display Name', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Customise the courier display name shown to buyer in cart/payment page', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="courier_display_name" name="courier_display_name" id="courier_display_name" value="" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="charges">
						<?php esc_html_e( 'Shipping Rate Setting', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred shipping rate setting to be shown to your buyers on the checkout page.', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="charges_option" id="charges_option" name="charges_option" data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel_zone_method' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $charges as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top" class="" id="addon_1" style="display:none"> <!-- addon option 1 -->
				<th scope="row" class="titledesc">
					<label for="addon_option_1">
						<?php esc_html_e( 'Add On Options', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Key in your flat rate for this courier service', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="addon_1_charges_value_1" name="addon_1_charges_value_1" id="addon_1_charges_value_1" value="" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="" id="addon_4" style="display:none"> <!-- addon option 4 -->
				<th scope="row" class="titledesc">
					<label for="addon_option_4">
						<?php esc_html_e( 'Add On Options', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred type for add on option.<br>For add on by amount, key in any amount.<br>For add on by percentage, key in a number between 1 and 100.', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="addon_4_charges_value_1" id="addon_4_charges_value_1" name="addon_4_charges_value_1" data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel_zone_method' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<option value="1">Add On By Amount (<?php echo get_woocommerce_currency(); ?>)</option>
						<option value="2">Add On By Percentage (%)</option>
					</select>
					<input type="text" data-attribute="addon_4_charges_value_2" name="addon_4_charges_value_2" id="addon_4_charges_value_2" value="" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label><input class="form-check-input" type="checkbox" value="" id="free_shipping"> <?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel_zone_method' ); ?></input></label>
					
				</th>
				<td class="forminp">
				</td>
			</tr>
			<tr valign="top" class="free_shipping_tab" id="free_shipping_tab" style="display:none">
				<th scope="row" class="titledesc">
					<label for="free_shipping_by">
						<?php esc_html_e( 'Free shipping requires..', 'easyparcel_zone_method' ); ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="free_shipping_by" id="free_shipping_by" name="free_shipping_by" data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel_zone_method' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $freeshippingby as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top" class="free_shipping_tab" id="free_shipping_tab" style="display:none">
				<th scope="row" class="free_shipping_by_desc">
					<label for="free_shipping_by">
						<span id="free_shipping_text">Minimum Order Amount</span>
						<?php echo wc_help_tip( __('Provide free shipping if the order amount is same as or higher than the amount set.', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="free_shipping_value" name="free_shipping_value" id="free_shipping_value" value="" placeholder="">
				</td>
			</tr>

		<?php endif; ?>
	</tbody>
</table>

<p class="submit setup_courier">
	<button type="submit" name="submit" id="submit" class="button button-primary button-large wc-shipping-zone-method-save" value="<?php esc_attr_e( 'Save changes', 'easyparcel_zone_method' ); ?>"><?php esc_html_e( 'Save changes', 'easyparcel_zone_method' ); ?></button>
	<button type="submit" name="back" id="back" class="button button-primary button-large wc-shipping-zone-method-back" value="<?php esc_attr_e( 'Back', 'easyparcel_zone_method' ); ?>"><?php esc_html_e( 'Back', 'easyparcel_zone_method' ); ?></button>
</p>
<style>
table#courier-setting-table th {
	width:30%;
}
table#courier-setting-table td {
	width:70%;
}
</style>