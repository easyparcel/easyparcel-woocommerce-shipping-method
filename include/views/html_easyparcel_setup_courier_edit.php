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
	<span class="wc-shipping-zone-name"><?php esc_html_e( 'Courier Setting > ', 'easyparcel_zone_method' ); ?><?php echo esc_html( $courier->courier_display_name ? $courier->courier_display_name : __( 'Courier', 'easyparcel_zone_method' ) ); ?></span>
</h2>

<table class="form-table wc-shipping-zone-settings" id="courier-setting-table">
	<tbody>
		<?php if ( 0 !== $courier->id ) : ?>
			
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="courier_service">
						<?php esc_html_e( 'Courier Service', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred couriers to be displayed on the checkout page.', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					
					<input type="text" data-attribute="" name="" id="" value="<?php echo esc_attr($courier->courier_name) ?>" placeholder="" disabled>
					<img class="img-wrap" id="courier_service_img" width="auto !important" height="30px !important" src="<?php echo esc_attr($courier->courier_logo) ?>" style="display:inline-block;">
				</td>
			</tr>
			<?php if (!empty($courier->courier_dropoff_point)) : ?>
			<tr valign="top" class="" id="courier_dropoff_panel">
				<th scope="row" class="titledesc">
					<label for="charges">
						<?php esc_html_e( 'Courier Dropoff Point', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Choose the dropoff point you wish to dropoff your parcel. [optional]', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="dropoff_point" id="dropoff_point" name="dropoff_point" data-placeholder="<?php esc_attr_e( 'Select your dropoff point', 'easyparcel_zone_method' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $dropoffpoint as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<?php endif; ?>
			<tr valign="top" class="" id="courier_display_name_panel" style="display:none">
				<th scope="row" class="titledesc">
					<label for="courier_display_name">
						<?php esc_html_e( 'Courier Display Name', 'easyparcel_zone_method' ); ?>
						<?php echo wc_help_tip( __( 'Customise the courier display name shown to buyer in cart/payment page', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="courier_display_name" name="courier_display_name" id="courier_display_name" value="<?php echo esc_attr($courier->courier_display_name) ? esc_attr($courier->courier_display_name) : '' ?>" placeholder="">
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
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
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
					<input type="text" data-attribute="addon_1_charges_value_1" name="addon_1_charges_value_1" id="addon_1_charges_value_1" value="<?php echo esc_attr($addonChargesValue) ? esc_attr($addonChargesValue) : '' ?>" placeholder="">
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
						<?php
						foreach ( $addonCharges as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
					<input type="text" data-attribute="addon_4_charges_value_2" name="addon_4_charges_value_2" id="addon_4_charges_value_2" value="<?php echo esc_attr($addonChargesValue) ? esc_attr($addonChargesValue) : '' ?>" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label><input class="form-check-input" type="checkbox" value="" id="free_shipping" <?php echo ($courier->free_shipping==1) ? 'checked' : ''; ?>> <?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel_zone_method' ); ?></input></label>
					
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
						<?php echo wc_help_tip( __( 'Provide free shipping if the order amount is same as or higher than the amount set.', 'easyparcel_zone_method' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="free_shipping_value" name="free_shipping_value" id="free_shipping_value" value="<?php echo esc_attr($courier->free_shipping_value) ? esc_attr($courier->free_shipping_value) : ''?>" placeholder="">
				</td>
			</tr>

		<?php endif; ?>
	</tbody>
</table>

<p class="submit edit_courier">
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