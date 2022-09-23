jQuery( function( $ ) {
	var easyparcel_shipping_fulfillment = {
		init: function() {
			$( '#easyparcel-shipping-integration-order-fulfillment').on( 'click', 'button.button-save-form', this.save_form );
			$( '#easyparcel-shipping-integration-order-fulfillment').on( 'change', 'select#shipping_provider', this.shipping_provider );
			jQuery(document).ready(this.shipping_provider); //pre-load get dropoff list
		},

		save_form: function () {	
			var error;	
			var tracking_number = jQuery("#tracking_number");
			var tracking_url = jQuery("#tracking_url");
			var shipping_provider = jQuery("#shipping_provider");
			var pick_up_date = jQuery("#pick_up_date");

			if( tracking_number.val() === '' ){				
				showerror( tracking_number );error = true;
			} else{
				hideerror(tracking_number);				
			}

			if( tracking_url.val() === '' ){				
				showerror( tracking_url );error = true;
			} else{
				hideerror(tracking_url);				
			}

			if( shipping_provider.val() === '' ){				
				jQuery("#shipping_provider").siblings('.select2-container').find('.select2-selection').css('border-color','red');
				// error = true;
			} else{
				jQuery("#shipping_provider").siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
				hideerror(shipping_provider);
			}

			if( pick_up_date.val() === '' ){				
				showerror( pick_up_date );error = true;
			} else{
				hideerror(pick_up_date);				
			}
			
			if(error == true){
				return false;
			}
			// if ( !$( 'input#tracking_number' ).val() ) { #EDIT CASE NEED CHECK
			// 	return false;
			// }

			$( '#easyparcel-fulfillment-form' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
						
			var product_data = [];
			jQuery(".ASTProduct_row").each(function(index){
				var ASTProduct_qty = jQuery(this).find('input[type="number"]').val();
				if(ASTProduct_qty > 0){
					product_data.push({
						product: jQuery(this).find('.product_id').val(),				
						qty: jQuery(this).find('input[type="number"]').val(),				
					});					
				}
			});	
			
			var jsonString = JSON.stringify(product_data);						
			var data = {
				action:                   'wc_shipment_tracking_save_form',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
				shipping_provider:        $( '#shipping_provider' ).val(),
				courier_name:   		  $( '#shipping_provider' ).find('option:selected').text(),
				drop_off_point:			  $( '#drop_off' ).val(),
				pick_up_date:			  $( '#pick_up_date' ).val(),
				tracking_number:          $( 'input#tracking_number' ).val(),
				tracking_url:          	  $( 'input#tracking_url' ).val(),
				date_shipped:             $( 'input#date_shipped' ).val(),
				productlist: 	          jsonString, 
				security:                 $( '#easyparcel_fulfillment_create_nonce' ).val()
			};

			jQuery.ajax({
				url: woocommerce_admin_meta_boxes.ajax_url,		
				data: data,
				type: 'POST',				
				success: function(response) {
					$( '#easyparcel-fulfillment-form' ).unblock();
					if ( response == 'success' ) {
						$( '#easyparcel-shipping-integration-order-fulfillment #tracking-items' ).append( response );
						$( '#easyparcel-shipping-integration-order-fulfillment button.button-show-tracking-form' ).show();
						// $( '#shipping_provider' ).selectedIndex = 0;
						// $( 'input#tracking_number' ).val( '' );
						// $( 'input#tracking_url' ).val( '' );
						// $( 'input#date_shipped' ).val( '' );
						jQuery('#order_status').val('wc-completed');
						jQuery('#order_status').select2().trigger('change');	
						jQuery('#post').before('<div id="order_updated_message" class="updated notice notice-success is-dismissible"><p>Order updated.</p><button type="button" class="notice-dismiss update-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');

						location.reload(true);
						return false;
					}else{
						// console.log(response);
						alert(response);
					}

				},
				error: function(response) {
					console.log(response);			
				}
			});			
			return false;
		},

		shipping_provider: function () {

			var shipping_provider = $( '#shipping_provider' ).val();
			var easyparcel_dropoff = $( '#easyparcel_dropoff' ).val();
			var selected_easyparcel_dropoff = $( '#selected_easyparcel_dropoff' ).val();
			$('.drop_off_field').html('');

			var easyparcel_dropoff_list = JSON.parse(easyparcel_dropoff);

			for(let i = 0; i < easyparcel_dropoff_list.length; i++){
				if(easyparcel_dropoff_list[i][shipping_provider]){ // if dropoff exist
					if(easyparcel_dropoff_list[i][shipping_provider].length > 0){ // check records
						var label = '<label for="drop_off">Drop Off Point:</label><br/>';
						var dropoff_select = '<select id="drop_off" name="drop_off" class="chosen_select drop_off_dropdown" style="width:100%;">';
						dropoff_select += '<option value="">[Optional] Select Drop Off Point</option>';
						for(let j = 0; j < easyparcel_dropoff_list[i][shipping_provider].length; j++){
							var selected = ( easyparcel_dropoff_list[i][shipping_provider][j]['point_id'] == selected_easyparcel_dropoff ) ? 'selected' : '';
							dropoff_select += '<option value="'+easyparcel_dropoff_list[i][shipping_provider][j]['point_id']+'" '+selected+'>'+easyparcel_dropoff_list[i][shipping_provider][j]['point_name']+'</option>';
						}
						dropoff_select += '</select>';

						if(!$('#tracking_number').length){
							$('.drop_off_field').html(label+dropoff_select);
							jQuery('#drop_off').select2({
								matcher: modelMatcher
							});
						}
						
					}
				}
				
			}

		}
	}

	easyparcel_shipping_fulfillment.init();
} );
jQuery(document).on("click", ".update-dismiss", function(){	
	jQuery('#order_updated_message').fadeOut();
});
function showerror(element){
	element.css("border-color","red");
}
function hideerror(element){
	element.css("border-color","");
}
jQuery(document).ready(function() {
	jQuery('#shipping_provider').select2({
		matcher: modelMatcher
	});
});

function modelMatcher (params, data) {				
	data.parentText = data.parentText || "";
	
	// Always return the object if there is nothing to compare
	if (jQuery.trim(params.term) === '') {
		return data;
	}
	
	// Do a recursive check for options with children
	if (data.children && data.children.length > 0) {
		// Clone the data object if there are children
		// This is required as we modify the object to remove any non-matches
		var match = jQuery.extend(true, {}, data);
	
		// Check each child of the option
		for (var c = data.children.length - 1; c >= 0; c--) {
		var child = data.children[c];
		child.parentText += data.parentText + " " + data.text;
	
		var matches = modelMatcher(params, child);
	
		// If there wasn't a match, remove the object in the array
		if (matches == null) {
			match.children.splice(c, 1);
		}
		}
	
		// If any children matched, return the new object
		if (match.children.length > 0) {
		return match;
		}
	
		// If there were no matching children, check just the plain object
		return modelMatcher(params, match);
	}
	
	// If the typed-in term matches the text of this term, or the text from any
	// parent term, then it's a match.
	var original = (data.parentText + ' ' + data.text).toUpperCase();
	var term = params.term.toUpperCase();
	
	
	// Check if the text contains the term
	if (original.indexOf(term) > -1) {
		return data;
	}
	
	// If it doesn't contain the term, don't return anything
	return null;
}

function showerror(element){
	element.css("border","1px solid red");
}
function hideerror(element){
	element.css("border","1px solid #ddd");
}
