jQuery(document).on("click", ".popupclose", function(){
	jQuery('.add_fulfillment_popup').hide();	
});

jQuery(document).on("click", ".popup_close_icon", function(){
	jQuery('.add_fulfillment_popup').hide();
});

jQuery(document).on("click", "#doaction", function(e){
	if(document.getElementById('bulk-action-selector-top').value == 'order_fulfillment'){
		e.preventDefault();
		var x = document.getElementsByName('post[]');
		var order_ids = [];
		for (i = 0; i < x.length; i++) {
			if (x[i].type == "checkbox" && x[i].checked) {
				order_ids.push(x[i].value);
			}
		}
		
		var ajax_data = {
			action: 'easyparcel_bulk_fulfillment_popup',
			order_ids: order_ids,	
			security: easyparcel_orders_params.order_nonce,	
		};
		
		jQuery.ajax({
			url: ajaxurl,		
			data: ajax_data,
			type: 'POST',						
			success: function(response) {
				jQuery( ".add_fulfillment_popup" ).remove();
				jQuery("body").append(response);				
				jQuery('.add_fulfillment_popup').show();	
				jQuery('.shipping_provider_dropdown').select2();
				var selected_provider = jQuery("#shipping_provider").val();
				jQuery( '.ast-date-picker-field' ).datepicker({
					dateFormat: 'yy-mm-dd'
				});
			},
			error: function(response) {			
				jQuery('.add_fulfillment_popup').hide();					
			}
		});	
	}
});

function showerror(element){
	element.css("border-color","red");
}
function hideerror(element){
	element.css("border-color","");
}

jQuery( function( $ ) {
	var easyparcel_shipping_fulfillment = {
		init: function() {
			jQuery(document).on( 'click', 'input.button-save-form', this.save_form );
			jQuery(document).on( 'change', 'select#shipping_provider', this.shipping_provider );
			// jQuery(document).ready(this.shipping_provider); //pre-load get dropoff list
		},

		save_form: function () {
			var error;
			var shipping_provider = jQuery("#shipping_provider");
			var pick_up_date = jQuery("#date_shipped");
			
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

			$( '#easyparcel_fulfillment_popout' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			var data = {
				action:                   'wc_shipment_tracking_save_form_bulk',
				order_id:                 $( '#order_id' ).val(),
				shipping_provider:        $( '#shipping_provider' ).val(),
				courier_name:   		  $( '#shipping_provider' ).find('option:selected').text(),
				drop_off_point:			  $( '#drop_off' ).val(),
				pick_up_date:			  pick_up_date.val(),
				security:                 easyparcel_orders_params.order_nonce,
			};

			jQuery.ajax({
				url: woocommerce_admin_meta_boxes.ajax_url,		
				data: data,
				type: 'POST',				
				success: function(response) {
					$( '#easyparcel_fulfillment_popout' ).unblock();
					if ( response == 'success' ) {
						
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
			$('.drop_off_field').html('');

			var easyparcel_dropoff_list = JSON.parse(easyparcel_dropoff);

			for(let i = 0; i < easyparcel_dropoff_list.length; i++){
				if(easyparcel_dropoff_list[i][shipping_provider]){ // if dropoff exist
					if(easyparcel_dropoff_list[i][shipping_provider].length > 0){ // check records
						var label = '<label for="drop_off">Drop Off Point:</label><br/>';
						var dropoff_select = '<select id="drop_off" name="drop_off" class="chosen_select drop_off_dropdown" style="width:100%;">';
						dropoff_select += '<option value="">[Optional] Select Drop Off Point</option>';
						for(let j = 0; j < easyparcel_dropoff_list[i][shipping_provider].length; j++){
							dropoff_select += '<option value="'+easyparcel_dropoff_list[i][shipping_provider][j]['point_id']+'">'+easyparcel_dropoff_list[i][shipping_provider][j]['point_name']+'</option>';
						}
						dropoff_select += '</select>';

						$('.drop_off_field').html(label+dropoff_select);
						jQuery('#drop_off').select2({
							matcher: modelMatcher
						});		
						
					}
				}
				
			}

		}
	}
	easyparcel_shipping_fulfillment.init();
} );

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