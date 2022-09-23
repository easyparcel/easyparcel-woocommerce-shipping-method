/* global shippingZoneMethodsLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
	$( function() {
		var $table          = $( '.wc-shipping-zone-methods' ),
			$tbody          = $( '.wc-shipping-zone-method-rows' ),
			$save_button    = $( '.wc-shipping-zone-method-save' ),
			$row_template   = wp.template( 'wc-shipping-zone-method-row' ),
			$blank_template = wp.template( 'wc-shipping-zone-method-row-blank' ),
			
			// Backbone model
			ShippingMethod       = Backbone.Model.extend({
				changes: {},
				logChanges: function( changedRows ) {
					var changes = this.changes || {};

					_.each( changedRows.methods, function( row, id ) {
						changes.methods = changes.methods || { methods : {} };
						changes.methods[ id ] = _.extend( changes.methods[ id ] || { instance_id : id }, row );
					} );

					if ( typeof changedRows.zone_name !== 'undefined' ) {
						changes.zone_name = changedRows.zone_name;
					}

					if ( typeof changedRows.zone_locations !== 'undefined' ) {
						changes.zone_locations = changedRows.zone_locations;
					}

					if ( typeof changedRows.zone_postcodes !== 'undefined' ) {
						changes.zone_postcodes = changedRows.zone_postcodes;
					}

					this.changes = changes;
					this.trigger( 'change:methods' );
				},
				save: function() {
					$.post(
						ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=ep_shipping_zone_methods_save_changes',
						{
							wc_shipping_zones_nonce : data.wc_shipping_zones_nonce,
							changes                 : this.changes,
							zone_id                 : data.zone_id
						},
						this.onSaveResponse,
						'json'
					);
				},
				onSaveResponse: function( response, textStatus ) {
					if ( 'success' === textStatus ) {
						if ( response.success ) {
							if ( response.data.zone_id !== data.zone_id ) {
								data.zone_id = response.data.zone_id;
								if ( window.history.pushState ) {
									window.history.pushState(
										{},
										'',
										'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=' + response.data.zone_id
									);
								}
							}
							if(response.data.methods.length == 0){
								$('#add_courier_option').prop("disabled",false);
							}
							shippingMethod.set( 'methods', response.data.methods );
							shippingMethod.trigger( 'change:methods' );
							shippingMethod.changes = {};
							shippingMethod.trigger( 'saved:methods' );

							// Overrides the onbeforeunload callback added by settings.js.
							window.onbeforeunload = null;
						} else {
							window.alert( data.strings.save_failed );
						}
					}
				}
			} ),

			// Backbone view
			ShippingMethodView = Backbone.View.extend({
				rowTemplate: $row_template,
				initialize: function() {
					this.listenTo( this.model, 'change:methods', this.setUnloadConfirmation );
					this.listenTo( this.model, 'saved:methods', this.clearUnloadConfirmation );
					this.listenTo( this.model, 'saved:methods', this.render );
					$tbody.on( 'change', { view: this }, this.updateModelOnChange );
					$tbody.on( 'sortupdate', { view: this }, this.updateModelOnSort );
					$( window ).on( 'beforeunload', { view: this }, this.unloadConfirmation );
					$save_button.on( 'click', { view: this }, this.onSubmit );

					$( document.body ).on(
						'input change',
						'#zone_name, #zone_locations, #zone_postcodes',
						{ view: this },
						this.onUpdateZone
					);
					$( document.body ).on( 'click', '.wc-shipping-zone-method-settings', { view: this }, this.onConfigureShippingMethod );
					$( document.body ).on( 'click', '.wc-shipping-zone-add-method', { view: this }, this.onAddShippingMethod );
					$( document.body ).on( 'wc_backbone_modal_response', this.onConfigureShippingMethodSubmitted );
					$( document.body ).on( 'wc_backbone_modal_response', this.onAddShippingMethodSubmitted );
					$( document.body ).on( 'change', '.wc-shipping-zone-method-selector select', this.onChangeShippingMethodSelector );
					$( document.body ).on( 'click', '.wc-shipping-zone-postcodes-toggle', this.onTogglePostcodes );

					if(data.add_courier_option){
						$('#add_courier_option').prop("disabled",true);
					}
				},
				onUpdateZone: function( event ) {
					var view      = event.data.view,
						model     = view.model,
						value     = $( this ).val(),
						$target   = $( event.target ),
						attribute = $target.data( 'attribute' ),
						changes   = {};

					event.preventDefault();

					changes[ attribute ] = value;
					model.set( attribute, value );
					model.logChanges( changes );
					view.render();
				},
				block: function() {
					$( this.el ).block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
				},
				unblock: function() {
					$( this.el ).unblock();
				},
				render: function() {
					var methods     = _.indexBy( this.model.get( 'methods' ), 'courier_order' ),
						zone_name   = this.model.get( 'zone_name' ),
						view        = this;

					// Set name.
					$('.wc-shipping-zone-name').text( zone_name ? zone_name : data.strings.default_zone_name );

					// Blank out the contents.
					this.$el.empty();
					this.unblock();

					if ( _.size( methods ) ) {
						// Sort methods
						methods = _.sortBy( methods, function( method ) {
							return parseInt( method.method_order, 10 );
						} );

						// Populate $tbody with the current methods
						$.each( methods, function( id, rowData ) {
							if ( 'yes' === rowData.enabled ) {
								rowData.enabled_icon = '<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled">' +
									data.strings.yes +
									'</span>';
							} else {
								rowData.enabled_icon = '<span class="woocommerce-input-toggle woocommerce-input-toggle--disabled">' +
									data.strings.no +
									'</span>';
							}

							view.$el.append( view.rowTemplate( rowData ) );

							var $tr = view.$el.find( 'tr[data-id="' + rowData.instance_id + '"]');

							if ( ! rowData.has_settings ) {
								$tr
									.find( '.wc-shipping-zone-method-title > a' )
									.replaceWith('<span>' + $tr.find( '.wc-shipping-zone-method-title > a' ).text() + '</span>' );
								var $del = $tr.find( '.wc-shipping-zone-method-delete' );
								$tr.find( '.wc-shipping-zone-method-title .row-actions' ).empty().html($del);
							}
						} );

						// Make the rows function
						this.$el.find( '.wc-shipping-zone-method-delete' ).on( 'click', { view: this }, this.onDeleteRow );
						this.$el.find( '.wc-shipping-zone-method-enabled a').on( 'click', { view: this }, this.onToggleEnabled );
					} else {
						view.$el.append( $blank_template );
					}

					this.initTooltips();
				},
				initTooltips: function() {
					$( '#tiptip_holder' ).removeAttr( 'style' );
					$( '#tiptip_arrow' ).removeAttr( 'style' );
					$( '.tips' ).tipTip({ 'attribute': 'data-tip', 'fadeIn': 50, 'fadeOut': 50, 'delay': 50 });
				},
				onSubmit: function( event ) {
					event.data.view.block();
					event.data.view.model.save();
					event.preventDefault();
				},
				onDeleteRow: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						methods   = _.indexBy( model.get( 'methods' ), 'instance_id' ),
						changes = {},
						instance_id = $( this ).closest('tr').data('id');

					event.preventDefault();

					delete methods[ instance_id ];
					changes.methods = changes.methods || { methods : {} };
					changes.methods[ instance_id ] = _.extend( changes.methods[ instance_id ] || {}, { deleted : 'deleted' } );
					model.set( 'methods', methods );
					model.logChanges( changes );
					view.render();
				},
				onToggleEnabled: function( event ) {
					var view        = event.data.view,
						$target     = $( event.target ),
						model       = view.model,
						methods     = _.indexBy( model.get( 'methods' ), 'instance_id' ),
						instance_id = $target.closest( 'tr' ).data( 'id' ),
						enabled     = $target.closest( 'tr' ).data( 'enabled' ) === 'yes' ? 'no' : 'yes',
						changes     = {};

					event.preventDefault();
					methods[ instance_id ].enabled = enabled;
					changes.methods = changes.methods || { methods : {} };
					changes.methods[ instance_id ] = _.extend( changes.methods[ instance_id ] || {}, { enabled : enabled } );
					model.set( 'methods', methods );
					model.logChanges( changes );
					view.render();
				},
				setUnloadConfirmation: function() {
					this.needsUnloadConfirm = true;
					$save_button.prop( 'disabled', false );
				},
				clearUnloadConfirmation: function() {
					this.needsUnloadConfirm = false;
					$save_button.attr( 'disabled', 'disabled' );
				},
				unloadConfirmation: function( event ) {
					if ( event.data.view.needsUnloadConfirm ) {
						event.returnValue = data.strings.unload_confirmation_msg;
						window.event.returnValue = data.strings.unload_confirmation_msg;
						return data.strings.unload_confirmation_msg;
					}
				},
				updateModelOnChange: function( event ) {
					var model     = event.data.view.model,
						$target   = $( event.target ),
						instance_id   = $target.closest( 'tr' ).data( 'id' ),
						attribute = $target.data( 'attribute' ),
						value     = $target.val(),
						methods   = _.indexBy( model.get( 'methods' ), 'instance_id' ),
						changes = {};

					if ( methods[ instance_id ][ attribute ] !== value ) {
						changes.methods[ instance_id ] = {};
						changes.methods[ instance_id ][ attribute ] = value;
						methods[ instance_id ][ attribute ]   = value;
					}

					model.logChanges( changes );
				},
				updateModelOnSort: function( event ) {
					var view         = event.data.view,
						model        = view.model,
						methods        = _.indexBy( model.get( 'methods' ), 'instance_id' ),
						changes      = {};

					_.each( methods, function( method ) {
						var old_position = parseInt( method.method_order, 10 );
						var new_position = parseInt( $table.find( 'tr[data-id="' + method.instance_id + '"]').index() + 1, 10 );

						if ( old_position !== new_position ) {
							methods[ method.instance_id ].method_order = new_position;
							changes.methods = changes.methods || { methods : {} };
							changes.methods[ method.instance_id ] = _.extend(
								changes.methods[ method.instance_id ] || {}, { method_order : new_position }
							);
						}
					} );

					if ( _.size( changes ) ) {
						model.logChanges( changes );
					}
				},
				onConfigureShippingMethod: function( event ) {
					$('#wpbody').append('<div id="loading" style="position: fixed;display: flex;justify-content: center;align-items: center;width: 100%;height: 100%;top: 0;left: 0;opacity: 0.7;background-color: #fff;z-index: 99;"><img height="30" width="30" style="position: absolute;z-index: 100;" src="data:image/gif;base64,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" /></div>')
					var instance_id = $( this ).closest( 'tr' ).data( 'id' ),
						model       = event.data.view.model,
						methods     = _.indexBy( model.get( 'methods' ), 'instance_id' ),
						method      = methods[ instance_id ];

					// Only load modal if supported
					if ( ! method.settings_html ) {
						return true;
					}

					event.preventDefault();

					$( this ).WCBackboneModal({
						template : 'wc-modal-shipping-method-settings',
						variable : {
							instance_id : instance_id,
							method      : method
						},
						data : {
							instance_id : instance_id,
							method      : method
						}
					});

					$( document.body ).trigger( 'init_tooltips' );
				},
				onConfigureShippingMethodSubmitted: function( event, target, posted_data ) {
					if ( 'wc-modal-shipping-method-settings' === target ) {
						shippingMethodView.block();

						// Save method settings via ajax call
						$.post(
							ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=ep_shipping_zone_methods_save_settings',
							{
								wc_shipping_zones_nonce : data.wc_shipping_zones_nonce,
								instance_id             : posted_data.instance_id,
								data                    : posted_data
							},
							function( response, textStatus ) {
								if ( 'success' === textStatus && response.success ) {
									$( 'table.wc-shipping-zone-methods' ).parent().find( '#woocommerce_errors' ).remove();

									// If there were errors, prepend the form.
									if ( response.data.errors.length > 0 ) {
										shippingMethodView.showErrors( response.data.errors );
									}

									// Method was saved. Re-render.
									if ( _.size( shippingMethodView.model.changes ) ) {
										shippingMethodView.model.save();
									} else {
										shippingMethodView.model.onSaveResponse( response, textStatus );
									}
								} else {
									window.alert( data.strings.save_failed );
									shippingMethodView.unblock();
								}
							},
							'json'
						);
					}
				},
				showErrors: function( errors ) {
					var error_html = '<div id="woocommerce_errors" class="error notice is-dismissible">';

					$( errors ).each( function( index, value ) {
						error_html = error_html + '<p>' + value + '</p>';
					} );
					error_html = error_html + '</div>';

					$( 'table.wc-shipping-zone-methods' ).before( error_html );
				},
				onAddShippingMethod: function( event ) {
					event.preventDefault();
					var changes = shippingMethodView.model.changes;
					var locations = $('#zone_locations').val();
					var hehe = data.zone_id;
					if(!jQuery.isEmptyObject(changes)){
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=ep_shipping_zone_methods_save_changes',
						{
							wc_shipping_zones_nonce : data.wc_shipping_zones_nonce,
							changes                 : changes,
							zone_id                 : data.zone_id
						},function( response, textStatus ) {
							if ( 'success' === textStatus && response.success ) {
								if ( response.data.zone_id !== data.zone_id ) {
									data.zone_id = response.data.zone_id;
									// perform save then go to another page
									$(window).off('beforeunload');
									window.onbeforeunload = null;
									window.location.href = 'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=' + data.zone_id+'&perform=add_courier';
									$(window).off('beforeunload');
								}
							}
						});
						$('#wpbody').append('<div id="loading" style="position: fixed;display: flex;justify-content: center;align-items: center;width: 100%;height: 100%;top: 0;left: 0;opacity: 0.7;background-color: #fff;z-index: 99;"><img height="30" width="30" style="position: absolute;z-index: 100;" src="data:image/gif;base64,R0lGODlhHgAeAOMAAJyenNTS1Ozq7PT29KyurOTi5PTy9Pz+/LS2tKSmpNza3Ozu7Pz6/LSytOTm5P///yH/C05FVFNDQVBFMi4wAwEAAAAh+QQJCQAPACwAAAAAHgAeAAAE7/DJSWchrdTNJVuMNCQAkAwe2FVDYzrPYpbGU5ANukpBCSCPgwvQODwQvsCONwM+GApF6DhTLlsmQUeAm24GgQDqYPByGOUHWDxhDIvLirvUmMpmizjF4APknyQmZnEjJQleDggIWnoUAoqMjZIiaZMVByo3P4OSDEgJFz4KlhMKPg1DAKOkDwGBGSQInI1zoE+VrLezuRuJi6S+MB6Bh5MDBIZ2fTWSdzQeb0adb14MYSFoOh0fRtYBu1gJkRUOyDlxPSVOUFISSCVWO+k/QUOyVPCELuIxMwA1DnBo24FmSiFBHgTsilOgAQENcSIAACH5BAkJABUALAAAAAAeAB4AhAQCBJyanNTS1Ozq7KyurPT29OTi5CwuLKSmpNza3PTy9LS2tPz+/BQSFJyenNTW1Ozu7LSytPz6/OTm5Dw6PP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX+YCWO5BgdR1SurChBkjg0ANBMLtyWReQgOEGtJqhMEI5IYTcSOJ6LioRSoywXP0eRWXEioxVFIAARYZ9bZu83aA2QkRirIBAsGQp5S5Kv0O0jEj5JDFwlgk9xIhBPCGSGJApPT2VSSD96kAWXCHoTCwttkCQDoKKjqCIFfaklDDoGX5mpElgIBgSTCa0jD5MRgw67vBUJv7EOC7OotT8GUqzE0MvSLJ+hvNc4LpzUOwW5mIuTDgqpjJPmUoMRhczsehJ1MXxLOy+F8gLeawinJRNyKTHkBIqLBAnknNFCcFIUBoOUVViY5psPfxUYOSpnBI49LnzkbGokR8IAb4YGDAAbxiUEACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5MgVBVeurHhVlzgsALBkYiUYbWlwGgJOUKsJPBGaBdcTCTTQg+fyqD1imGKg6QxqpJ6GQlERSbRczy84aEU2AEubZRDsPJBGrFdh8Op3LkAaHBBpJBCDHHsVUARkhyMNUFBlUwSOe5EGmEGaGQcHc5EyoaOkpAZ6qCsQMB4JmAearBcHQQkOlAysIxSUHIMavL0eDMCxX7SotrhTq8Uu0NFcoKK91kyXmagGup45lBoNqI2U5FOKhqQXipoXdjEXDTw9L4bwAssjawSnJBl0cajX5EkUFwwY7LkF5QgXg1/wDJrlgaEGh036tWn0aJyHDJgGHpq3hxO3KQMI9kVKEIwYlxAAIfkECQkAHgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakLC4s9Pb0tLa0PDo85OLknJqcFBIU3Nrc9PL0rK6sNDY0/P78vL68REJEjIqM1NbU7O7sNDI0/Pr8PD485ObknJ6cFBYUtLK0xMLE////AAAABf6gJ47kyBUFV66seFWXOCwAsGRiJRhtaXAaAk5Qqwk8EZoF1xMJNNCD5/KoPWKYYqDpDGqknoZCURFJtFzPLzhoRTYAS5tlEOw8kEasV2HwBhMBc1NAGhwQaSQXBTUFPB4VUARkiSMURQBHUwSSe5UVcDZlIhkHB4OVHh0YGBGpryIGerAlEDAeCZwHnrAXB0EJDlAaDLQiFMMchcTGHgzJuV+8r77AU7PN19PZLKWntN5Mm52vBsJBe5HDDa/qUOyEUIfUhRyeF3YxFw2PLS+I+ARsiwWEACoSGYRx6NfjSRQXDBjs+QVFUxOHX/AU2uWBogaLPdYYhBREgQZ2GRQ4LUy0b48BTuhcDBiYKIGyYmlCAAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuTIFQVXrqx4VZc4LACwZGIlGG1pcBoCTlCrCTwRmgXXEwk00IPn8qg9YphioOkMaqSehkJREUm0XM8vOGhFNgBLm2UQ7DyQRqxXYfAGEwFzU0AaHBBpJBcFNQU8HhVQBGSJIxRFAEdTBJJ7lRVwNmUiGQcHg5UeHRgYEamvIgZ6sD4UbQmcB56wDVkLHA5QGgy0IgpFKcLExceNuF+7rxUINg5Ts8WxAqjZPaWntN9Mm52vBsFBe5HCDdLCGu2EUIevF4Ucnhd2MRcNjy0vEOkTEG3EGgLcRmQIxuFfjydRXDBgsOeAME1NIH7BU0iXB4tQMPY42CbSJHgeFjJwapio3x4DnNK5GFCwUgIOHJZxCQEAIfkECQkAHgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakLC4s9Pb0tLa0PDo85OLknJqcFBIU3Nrc9PL0rK6sNDY0/P78vL68REJEjIqM1NbU7O7sNDI0/Pr8PD485ObknJ6cFBYUtLK0xMLE////AAAABf6gJ47kyBUFV66seFWXOCwAsGRiJRhtaXAaAk5Qqwk8EZoF1xMJNNCD5/KoPWKYYqDpDGqknoZCURFJtFzPLzhoRTYAS5tlEOw8kEasV2HwBhMBc1NAGhwQaSQXBTUFPB4VUARkiSMURQBHUwSSe5UVcDZlIhkHB4OVHh0YGBGpryIGerA+FG0JnAeesA1ZCxwOUBoMtCIKRSnCxMXHjbhfu68VCDYOU7PFsQKo2T2lp7QdEq0jF5xB0VygNQujkcINr5dFmheFh6+LjY9TdjEXDfixeBFjQIAJ3EasIZCQVDAOAls8ieKCAYM9B4RpajLxC55CujxkhLKxx8I2kRomaYiXgRPERAD3GDhHYM+FAekqJeDAYRmXEAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuTIFQVXrqx4VZc4LACwZGIlGG1pcBoCTlCrCTwRmgXXEwk00IPn8qg9YphioOkMaqSehkJREUm0XM8vOGhFNgBLm2UQ7DyQRqxXYfAGEwFzU0AaHBBpJBcFNQU8HhVQBGSJIxRFAEdTBJJ7lRVwNmUiGQcHg5UeHRgYEamvIgZ6sD4UbQmcB56wDVkLHA5QGgy0IgpFKcLExceNuF+7rxUINg5Ts8WxAqjZPaWntB0SrSMXnEHRXKA1C6ORwg2vl0WaF4WHr4uNj1N2MRcN+LFoQCHGgACCWqwhwG1EBzhy0jyJ4oIBgz1ZamzhMvELnkK6PGQEsLHJwjaRGiZpiJcEQIGGLADuMXCOwJ4Bd4ol4MBhGZcQACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5MgVBVeurHhVlzgsALBkYiUYbWlwGgJOUKsJPBGaBdcTCTTQg+fyqD1imGKg6QxqpJ6GQlERSbRczy84aEU2AEubZRDsPJBGrFdh8AYTAXNTQBocEGkkFwU1BTweFVAEZIkjFEUAR1MEknuVFXA2ZSIZBweDlR4dGBgRqa8iBnqwPhRtCZwHnrANWQscDlAaDLQiCkUpwsTFx424X7uvFQg2DlOzxbECqNk9pae0HRKtIxecQdFcoDULo5HCDa+XRZoXhYevi42PU3YxFw34sWhAIcaAAIJarCHAbUQHOHLSPIkiQoyCeB6y1NjCZeKXKdQAIOChEQDHJgsZ28yrQQEJjQINWQDcM4MdEx0CXzlAYS1NCAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuTIFQVXrqx4VZc4LACwZGIlGG1pcBoCTlCrCTwRmgXXEwk00IPn8qg9YphioOkMaqSehkJREUm0XM8vOGhFNgBLm2UQ7DyQRqxXYfAGEwFzU0AaHBBpJBcFNQU8HhVQBGSJIxRFAEdTBJJ7lRVwNmUiGQcHg5UeHRgYEamvIgZ6sD4UbQmcB56wDVkLHA5QGgy0IgpFKcLExceNuF+7rxUINg5Ts8WxAqjZPaWntB0SrSMXnEHRXKA1C6ORwg2vl0WaF4WHr4uNj1N2MRcU4vHx4wGQoBYDLADY0KFFBzhy0gQogkGEGAUCs9TYwmVijYoXqAFAwEMjAI5NHQYwWuBqXg0KSGgU4NaizpwZ7Jjo4AfLAQpraUIAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5MgVBVeurHhVlzgsALBkYiUYbWlwGgJOUKsJPBGaBdcTCTTQg+fyqD1imGKg6QxqpJ6GQlERSbRczy84aEU2AEubZRDsPJBGrFdh8AYTAXNTQBocEGkkFwU1BTweFVAEZIkjFEUAR1MEknuVFXA2ZSIZBweDlR4dGBgRqa8iBnqwPhRtCZwHnrANWQscDlAaDLQiCkUpwsTFx424X7uvFQg2DlOzxbECqNk9ERgSHbQdEq0jFTQAG6OfoQujREUUr5dFmgaMcY+VFxaN+wMCTGhjgAE7Fg0oxMgQiNuIAVU2iGPRAY6cNAGKYBAhRkEDEVlqbOGSscbGC9QjACDgERLAyCYDGC1wVa/GvCQACjiks01Guhs57hRzgMJamhAAIfkECQkAHAAsAAAAAB4AHgCEBAIEhIaE1NLU7OrsLC4spKak9Pb0PDo8tLa0nJqc5ObkFBIU3Nrc9PL0NDY0/P78REJEvL68jIqM1NbU7O7sNDI0tLK0/Pr8PD48nJ6cFBYUxMLE////AAAAAAAAAAAABf4gJ47kaBGEVa6seFGXOCwAsCgiJRhtaViZAk5Qqwk4EVoF1xMJMlAE5+KoOWKYYqDpDGaknEYiQRFBtFzOLzhoRTSASptlEOw4j0asR2HwBhIBc1NAGRYPaSQXBDUEPBwUUAVkiSMTRQBHUwWSe5UUcDZlIgoICIOVHBsYGBGprzITnrAiBhNtFjQHDbQiDVkLJ0UJvRwJRSjDxceNuQAOvL0UVcEcCrLFtRNM2U0RGBAbtBsQrSMUNAAao5+hC6NERROvl0WaBoxxj5UXFY37AwJIaGOAATsWDbApCISKxIAqGsSx2ABHTpoARTD4GhMtS40tXDDW0HjhQI0DPCA8AgDZZACjBa7q1ZiXBACBhi3qzJlR40aOO71OpEgUAgAh+QQJCQAcACwAAAAAHgAeAIQEAgSEhoTU0tTs6uwsLiykpqT09vQ8Ojy0trScmpzk5uQUEhTc2tz08vQ0NjT8/vxEQkS8vryMiozU1tTs7uw0MjS0srT8+vw8PjycnpwUFhTEwsT///8AAAAAAAAAAAAF/iAnjuRoEYRVrqx4UZc4LACwKCIlGG1pWJkCTlCrCTgRWgXXEwkyUATn4qg5YphioOkMZqScRiJBEUG0XM4vOGhFNIBKmzUISNqPRqxHYfAGEgFzahU1FTxpIxeFAASIREUTiSMTRQBHHDM1GmWTHBRwNp1IGBARniMbGBinqKgDE3uuIwYTbRY0Bw2zIg1ZCydFCbwcCUUowsTGNSk0Dru8FFXAHAqxxCK1TNhNEaUbsxsQrCMUNACcqKA1C52QNZKelUWYBgSGiJOLzPl1d2oMRrFocE1BoEElBlTRAI7FBjhy0gQogqHXGGhZamzhMrFGxQsHahzgkRHAxiYDGu4tODUPHhIaBBD2MCBgjiYbTHTkc3UiRaIQACH5BAkJABcALAAAAAAeAB4AhAQCBISGhMTCxCwuLOzq7Dw6PLSytPT29NTS1BQSFJyanDQ2NPTy9ERCRNza3IyKjDQyNOzu7Dw+PLy+vPz6/NTW1BQWFP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX+4CWO5GgMg1GurHggkUgkAJAQYoQcbUkMtskFUashLhMaBNcTBYqSy6FQW1AukmKgKXpAcwpF7NLQci8ECMAiaE0sgGWLEHjgDhVGM1K5Eh4BTFJqcTxnIweEA4ZERRWHIxVFAEdoNGtjkBFwNpkTEg1CkCMCEhKio6kEfakreDgGNAV6rSIMWQknRQq1IgpFKLu9F781KTQLtLURCzYqBDvDIhQIgtI9nw1trQINpyMRlxaZh5s1CWONNY+jkkWVB0CFqRSKhmh1dw7kKwysdIHmNGPTQgAcOVye1IhygUEYWllqbEn4hQIVAAV4RAQwscmPIBfcrUNCY4C1Hi8MmMw4pzJarxMpDoUAACH5BAkJABwALAAAAAAeAB4AhAQCBISGhNTS1Ozq7CwuLKSmpPT29Dw6PLS2tJyanOTi5BQSFNza3PTy9DQ2NPz+/ERCRLy+vIyKjNTW1Ozu7DQyNLSytPz6/Dw+PJyenBQWFMTCxP///wAAAAAAAAAAAAX+ICeO5KhYllKurGgIlFtkWWGIlHC35EAAiwiHQqM1OJEFoDLgjQKAKIZzQdAQFw4mCgg4RRLulMpgZDkQrvc7qAA0m1ZEs2yyBgFJ0zA58igTWQMSAXYcBm5LO18jiFEEOwJcABOMJBOTAiIDSm8xljh0QJ9IGBBCoCMbGBioqa8Dga8rfE0WSgd+sxwNWwsWP1EJuyIJXATBAMPExo+3AA66sxQOQBYcAzrEIxcChttOEaZxsxsQrSMUnRqklhSiC5+SXJWpmFyah8EVi5YXiZBG4NFziEG7FQ1k4SnUYkA1OC020GHCCIoUEQ0SJPCzJcoaJxYBTLlwIMqBGx0euzDyAUTIvSiVkgAg8I3HCzucoizAqY0YMALXGIUAACH5BAkJAB0ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7CwuLKSmpPT29Dw6POTi5LS2tJyanBQSFNza3PTy9DQ2NPz+/ERCRLy+vIyKjNTW1Ozu7DQyNLSytPz6/Dw+POTm5JyenBQWFMTCxP///wAAAAAAAAX+YCeO5IhYFlKurGgIlFtoWmGIF3W15UAAi0iHQqM1OoiZ5cYTBQBQTOeSoCV21ZmgKZJAAdIpg7HrVGlb7qAC2HBaA2V5NQhIBh3D5Mi7NHYDEgF4LmwAFUxcIxeGBEwCXwATiiQTkWkDC1AbMZQiFBtQC50dERgQQp4jHBgYqaqwAxNzsCN6eBaaB3y1HQ0YQBY/UAq9IgpfBMMAxcbIUAS5AA68tRQOwR0Zs8a2ExndlKYQb7UcEK4jFJptpJSgop2QX5Oqll9pBsOIsIzQiXXu5GHgbkUDbhkEEaKDzU0LDqEqLOTxJIqIBgoU8AEGJYCiimCmHIBy4AZHAB4d1fwI0uEelEkRNBGYyOMFoUyiwA0RkKiWsGiUQgAAIfkECQkAHQAsAAAAAB4AHgCEBAIEhIaE1NLU7OrsLC4spKak9Pb0PDo85OLktLa0nJqcFBIU3Nrc9PL0NDY0/P78REJEvL68jIqM1NbU7O7sNDI0tLK0/Pr8PD485ObknJ6cFBYUxMLE////AAAAAAAABf5gJ47kiFgWUq6saAiUW2haYYgXdbXlQACLSIdCozU6iJnlxhMFAFBM55KgJXbVmaApkkAB0imDsetUaVvuoALYcFoDZXk1CEgGHcPkyLs0di8CTHlsABWDXCIPFjQWZQJfABOJJA1FGjEdAwtQG5mUUzM1cxEYEEKgIwMJCXipr5oTc7AuE3gWnAd8tA0YQBY/UAq0IgpfBMEAw8TGUAS4AA67sBQOvx0ZssQjehnblKUQb7AcEBioQ5xtn5QUG1ALmZBfk6kTkWkGwYevF4UEg+rcycOA3YoG2jJICOCKjjU3LTi8q9CQx5MoIhooUMDHF5QAiS6CmXIAyoEbHh4BgFTzI0iHe/Q6ROBEoCKPF642wfM2RNA2YM8ohQAAIfkECQkAHgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakLC4s9Pb0tLa0PDo85OLknJqcFBIU3Nrc9PL0rK6sNDY0/P78vL68REJEjIqM1NbU7O7sNDI0/Pr8PD485ObknJ6cFBYUtLK0xMLE////AAAABf6gJ47kmDhcUq6saAiVS2gaYYhXdbXlUACLiKdBozU8iRnnxhMFAFCM53KocSCeKk3QFE2gAOmUwdhlaxpud2ABbDqtgdK8GgQmA4+BcuRdGjsvAkx6bQAWhF0iEBw0HGYCYAAUiiQVRRoxHgMLUBualVMzNXQRGBJCoSMDBwd5qrCbFHSxODoeHJ0IfbUGVQQJP1AKtSIMRRzCAMTFFKMcDp0PvLEGjcCbg8UjEIDblaYScLEdEhipHhWdbqCVFRtQC5qRYJSqFJJqBsKIsBeGBQjZwaOHQbsVDWZ5yDAhwKs6D9yMW9EBnoWHPJ5EEdFAgYI+GMAEUKQxzBQEUCUQ3AgJZeSaH0E84KvnIUKnAhh5vHjFKV4GERW0FXNQoICDSiEAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5Jg4XFKurGgIlUtoGmGIV3W15VAAi4inQaM1PIkZ58YTBQBQjOdyqHEgnipN0BRNoADplMHYZWsabndgAWw6rYHSvBoEJgOPgXLkXRo7LwJMem0AFoRdIhAcNBxmAmAAFIokFUUaMR4DC1AbmpVTMzV0ERgSQqEjAwcHeaqwmxR0sTg6HhydCH21BlUECT9QCrUiDEUcwgDExRSjHA6dD7yxBo3Am4PFIxCA25WmEnCxGREcGSMVnW6glRejBJCSlKqXRX0GwoiwjI50dnj0MGi3osGsKQIE0CIx4IGbcSs6bDj0qsmTKCIaKFDQBwOYAIouhpmCAAqCGx4ioYBc8yOIBwrzPEToVKBikxevOEFZgM5DBW3FHBQo4KBSCAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuSYOFxSrqxoCJVLaBphiFd1teVQAIuIp0GjNTyJGefGEwUAUIzncqhxIJ4qTdAUTaAA6ZTB2GVrGm53YAFsOq2B0rwaBCYDj4Fy5F0aOy8CTHptABaEXSIQHDQcZgJgABSKJBVFGjEeAwtQG5qVUzM1dBEYEkKhIwMHB3mqsJsUdLE4Oh4cnQh9tQZVBAk/UAq1IgxFHMIAxMUUoxwOnQ+8sQaNwJuDxSMQgNuVphJwsRkRHBkjFZ1uoJUXowSQkpSql0V9BsKIsIyOdHZ49DBotyIHlgsCBNAiMeCBm3F1CBBYouhJFBENFCjooyVNRTBSLiCAguBGRzVNHXwAEUJh3iYliXi8eMUJygJ0UwYsVOWgQAEHlUIAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5Jg4XFKurGgIlUtoGmGIV3W15VAAi4inQaM1PIkZ58YTBQBQjOdyqHEgnipN0BRNoADplMHYZWsabndgAWw6rYHSvBoEJgOPgXLkXRo7LwJMem0AFoRdIhAcNBxmAmAAFIokFUUaMR4DC1AbmpVTMzV0ERgSQqEjAwcHeaqwmxR0sTg6HhydCH21BlUECT9QCrUiDEUcwgDExRSjHA6dD7yxBo3Am4PFIxCA25WmEnCxGREcGSMVnW6glRejBJCSlKqXRX0GwoiwjI50dnj0MGi3IgeWCwIE0CIx4IGbcXUIEFii6EkUHGTMaElTEYyUfhoO7NiopokPIEIZKozScCSDkkQ8XrwyAM/MhQELVSXgkKJSCAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuSYOFxSrqxoCJVLaBphiFd1teVQAIuIp0GjNTyJGefGEwUAUIzncqhxIJ4qTdAUTaAA6ZTB2GVrGm53YAFsOq2B0rwaBCYDj4Fy5F0aOy8CTHptABaEXSIQHDQcZgJgABSKJBVFGjEeAwtQG5qVUzM1dBEYEkKhIwMHB3mqsJsUdLE4Oh4cnQh9tQZVBAk/UAq1IgxFHMIAxMUUoxwOnQ+8sQaNwJuDxSMQgNuVphJwsRkRHBkjFZ1uoJUXowSQkpSql0V9BsKIsIyOdHZ4PEC45acClgsCBNAiYa3GqxUZCBBYokhADQUHcJAxoyVNRQ0KNGTsJ3JHRzVNHRoSyHOJQMgjETVQVPSHybuQ8XAMWKgqATQVikIAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5Jg4XFKurGgIlUtoGmGIV3W15VAAi4inQaM1PIkZ58YTBQBQjOdyqHEgnipN0BRNoADplMHYZWsabndgAWw6rYHSvBoEJgOPgXLkXRo7LwJMem0AFoRdIhAcNBxmAmAAFIokFUUaMR4DC1AbmpVTMzV0ERgSQqEjAwcHeaqwmxR0sTg6HhydCH21BlUECT9QCrUiDEUcwgDExRSjHA6dD7yxBo3Am4PFIxCA25UZra+w4QcZIxejBLSKBg406yKXRdSK80Y4jRpXsBf6j+gECNjxJ9GKHFguCGQ3wlqNcSUyvFuiSECRAzjImNGSpuJFD4xoHNjBUU0ThwQd8lwioEDDkQxKDLb4Y8aAOjMXBjAMlYADBwaVQgAAIfkECQkAGgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakNDI09Pb0tLa05OLkPD48nJ6c3Nrc9PL0rK6s/P78vL68FBYUjIqM1NbU7O7sNDY0/Pr85ObkREJEtLK0xMLE////AAAAAAAAAAAAAAAAAAAABf6gJo7kiDQYUq6saAiTSygKYYjVVLXlUACQh2ZCozE0iBnmxhMFANCEpnKoYRyaKk3QdEIB0uliscvWFNyuD5hpDZTl1SAQGWgMkiOvwti9BEx3PwAFgV0iDhg0GGUCXwAShyRERTEaAxBQEJaSUzM1cQ8JF0KdIwMHB3amrHd9rSUOOkgzB3GtVDUnRQuwIhJFGIo0vb4LwUkKtr5TVQQqfLew0cySFqmrrNcHFiMVnwTSXQYNNOEilEas6Qp6FcNXrO+LcRUCAjt8his5WPb4LQwoIpCthIVySw4JKHIAx5gyWtAoZKghEY1lEdM0EVjDDpFPRywo2deC2h1wZQwqDBDXCQGGBioOhQAAIfkECQkAGQAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakNDI09Pb0tLa05OLkPD48nJ6c9PL0rK6s/P78vL68FBYUjIqM3Nrc7O7sNDY0/Pr85ObkREJEtLK0xMLE////AAAAAAAAAAAAAAAAAAAAAAAABfpgJo7kiDAXUq6saAiSSygKYYiURLXlUAAPR0ZCoy0yiNnlxhMFANBEhnKoXRqZKk3QdEIB0mkkssvWFNyuD4hpDZTl1UtwM0RiPMpiN2dOLzRXXSUUgAoXZURneIMiC0UKeBQzNXGNBpQEcRUHBwONPZ2foKQjBnulKw06SDMHlqVUNSdFEakjEUUXhgq2txkClCmusKSFs1OovzjKy12cnrfQFSOTNJqlBgzXiZBHpIpGOIaCxuRxFAICO3p+LDlY6estBoAEoysV20uDAkUHOMaU0YKm378MDQy9MrNlUL0an4hQOlJBibs8yjBxwzGgGCgEFxioGBQCADs=" /></div>')
					}else if (locations != ''){
						window.location.href = 'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=' + data.zone_id+'&perform=add_courier';
						$('#wpbody').append('<div id="loading" style="position: fixed;display: flex;justify-content: center;align-items: center;width: 100%;height: 100%;top: 0;left: 0;opacity: 0.7;background-color: #fff;z-index: 99;"><img height="30" width="30" style="position: absolute;z-index: 100;" src="data:image/gif;base64,R0lGODlhHgAeAOMAAJyenNTS1Ozq7PT29KyurOTi5PTy9Pz+/LS2tKSmpNza3Ozu7Pz6/LSytOTm5P///yH/C05FVFNDQVBFMi4wAwEAAAAh+QQJCQAPACwAAAAAHgAeAAAE7/DJSWchrdTNJVuMNCQAkAwe2FVDYzrPYpbGU5ANukpBCSCPgwvQODwQvsCONwM+GApF6DhTLlsmQUeAm24GgQDqYPByGOUHWDxhDIvLirvUmMpmizjF4APknyQmZnEjJQleDggIWnoUAoqMjZIiaZMVByo3P4OSDEgJFz4KlhMKPg1DAKOkDwGBGSQInI1zoE+VrLezuRuJi6S+MB6Bh5MDBIZ2fTWSdzQeb0adb14MYSFoOh0fRtYBu1gJkRUOyDlxPSVOUFISSCVWO+k/QUOyVPCELuIxMwA1DnBo24FmSiFBHgTsilOgAQENcSIAACH5BAkJABUALAAAAAAeAB4AhAQCBJyanNTS1Ozq7KyurPT29OTi5CwuLKSmpNza3PTy9LS2tPz+/BQSFJyenNTW1Ozu7LSytPz6/OTm5Dw6PP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX+YCWO5BgdR1SurChBkjg0ANBMLtyWReQgOEGtJqhMEI5IYTcSOJ6LioRSoywXP0eRWXEioxVFIAARYZ9bZu83aA2QkRirIBAsGQp5S5Kv0O0jEj5JDFwlgk9xIhBPCGSGJApPT2VSSD96kAWXCHoTCwttkCQDoKKjqCIFfaklDDoGX5mpElgIBgSTCa0jD5MRgw67vBUJv7EOC7OotT8GUqzE0MvSLJ+hvNc4LpzUOwW5mIuTDgqpjJPmUoMRhczsehJ1MXxLOy+F8gLeawinJRNyKTHkBIqLBAnknNFCcFIUBoOUVViY5psPfxUYOSpnBI49LnzkbGokR8IAb4YGDAAbxiUEACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5MgVBVeurHhVlzgsALBkYiUYbWlwGgJOUKsJPBGaBdcTCTTQg+fyqD1imGKg6QxqpJ6GQlERSbRczy84aEU2AEubZRDsPJBGrFdh8Op3LkAaHBBpJBCDHHsVUARkhyMNUFBlUwSOe5EGmEGaGQcHc5EyoaOkpAZ6qCsQMB4JmAearBcHQQkOlAysIxSUHIMavL0eDMCxX7SotrhTq8Uu0NFcoKK91kyXmagGup45lBoNqI2U5FOKhqQXipoXdjEXDTw9L4bwAssjawSnJBl0cajX5EkUFwwY7LkF5QgXg1/wDJrlgaEGh036tWn0aJyHDJgGHpq3hxO3KQMI9kVKEIwYlxAAIfkECQkAHgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakLC4s9Pb0tLa0PDo85OLknJqcFBIU3Nrc9PL0rK6sNDY0/P78vL68REJEjIqM1NbU7O7sNDI0/Pr8PD485ObknJ6cFBYUtLK0xMLE////AAAABf6gJ47kyBUFV66seFWXOCwAsGRiJRhtaXAaAk5Qqwk8EZoF1xMJNNCD5/KoPWKYYqDpDGqknoZCURFJtFzPLzhoRTYAS5tlEOw8kEasV2HwBhMBc1NAGhwQaSQXBTUFPB4VUARkiSMURQBHUwSSe5UVcDZlIhkHB4OVHh0YGBGpryIGerAlEDAeCZwHnrAXB0EJDlAaDLQiFMMchcTGHgzJuV+8r77AU7PN19PZLKWntN5Mm52vBsJBe5HDDa/qUOyEUIfUhRyeF3YxFw2PLS+I+ARsiwWEACoSGYRx6NfjSRQXDBjs+QVFUxOHX/AU2uWBogaLPdYYhBREgQZ2GRQ4LUy0b48BTuhcDBiYKIGyYmlCAAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuTIFQVXrqx4VZc4LACwZGIlGG1pcBoCTlCrCTwRmgXXEwk00IPn8qg9YphioOkMaqSehkJREUm0XM8vOGhFNgBLm2UQ7DyQRqxXYfAGEwFzU0AaHBBpJBcFNQU8HhVQBGSJIxRFAEdTBJJ7lRVwNmUiGQcHg5UeHRgYEamvIgZ6sD4UbQmcB56wDVkLHA5QGgy0IgpFKcLExceNuF+7rxUINg5Ts8WxAqjZPaWntN9Mm52vBsFBe5HCDdLCGu2EUIevF4Ucnhd2MRcNjy0vEOkTEG3EGgLcRmQIxuFfjydRXDBgsOeAME1NIH7BU0iXB4tQMPY42CbSJHgeFjJwapio3x4DnNK5GFCwUgIOHJZxCQEAIfkECQkAHgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakLC4s9Pb0tLa0PDo85OLknJqcFBIU3Nrc9PL0rK6sNDY0/P78vL68REJEjIqM1NbU7O7sNDI0/Pr8PD485ObknJ6cFBYUtLK0xMLE////AAAABf6gJ47kyBUFV66seFWXOCwAsGRiJRhtaXAaAk5Qqwk8EZoF1xMJNNCD5/KoPWKYYqDpDGqknoZCURFJtFzPLzhoRTYAS5tlEOw8kEasV2HwBhMBc1NAGhwQaSQXBTUFPB4VUARkiSMURQBHUwSSe5UVcDZlIhkHB4OVHh0YGBGpryIGerA+FG0JnAeesA1ZCxwOUBoMtCIKRSnCxMXHjbhfu68VCDYOU7PFsQKo2T2lp7QdEq0jF5xB0VygNQujkcINr5dFmheFh6+LjY9TdjEXDfixeBFjQIAJ3EasIZCQVDAOAls8ieKCAYM9B4RpajLxC55CujxkhLKxx8I2kRomaYiXgRPERAD3GDhHYM+FAekqJeDAYRmXEAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuTIFQVXrqx4VZc4LACwZGIlGG1pcBoCTlCrCTwRmgXXEwk00IPn8qg9YphioOkMaqSehkJREUm0XM8vOGhFNgBLm2UQ7DyQRqxXYfAGEwFzU0AaHBBpJBcFNQU8HhVQBGSJIxRFAEdTBJJ7lRVwNmUiGQcHg5UeHRgYEamvIgZ6sD4UbQmcB56wDVkLHA5QGgy0IgpFKcLExceNuF+7rxUINg5Ts8WxAqjZPaWntB0SrSMXnEHRXKA1C6ORwg2vl0WaF4WHr4uNj1N2MRcN+LFoQCHGgACCWqwhwG1EBzhy0jyJ4oIBgz1ZamzhMvELnkK6PGQEsLHJwjaRGiZpiJcEQIGGLADuMXCOwJ4Bd4ol4MBhGZcQACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5MgVBVeurHhVlzgsALBkYiUYbWlwGgJOUKsJPBGaBdcTCTTQg+fyqD1imGKg6QxqpJ6GQlERSbRczy84aEU2AEubZRDsPJBGrFdh8AYTAXNTQBocEGkkFwU1BTweFVAEZIkjFEUAR1MEknuVFXA2ZSIZBweDlR4dGBgRqa8iBnqwPhRtCZwHnrANWQscDlAaDLQiCkUpwsTFx424X7uvFQg2DlOzxbECqNk9pae0HRKtIxecQdFcoDULo5HCDa+XRZoXhYevi42PU3YxFw34sWhAIcaAAIJarCHAbUQHOHLSPIkiQoyCeB6y1NjCZeKXKdQAIOChEQDHJgsZ28yrQQEJjQINWQDcM4MdEx0CXzlAYS1NCAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuTIFQVXrqx4VZc4LACwZGIlGG1pcBoCTlCrCTwRmgXXEwk00IPn8qg9YphioOkMaqSehkJREUm0XM8vOGhFNgBLm2UQ7DyQRqxXYfAGEwFzU0AaHBBpJBcFNQU8HhVQBGSJIxRFAEdTBJJ7lRVwNmUiGQcHg5UeHRgYEamvIgZ6sD4UbQmcB56wDVkLHA5QGgy0IgpFKcLExceNuF+7rxUINg5Ts8WxAqjZPaWntB0SrSMXnEHRXKA1C6ORwg2vl0WaF4WHr4uNj1N2MRcU4vHx4wGQoBYDLADY0KFFBzhy0gQogkGEGAUCs9TYwmVijYoXqAFAwEMjAI5NHQYwWuBqXg0KSGgU4NaizpwZ7Jjo4AfLAQpraUIAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5MgVBVeurHhVlzgsALBkYiUYbWlwGgJOUKsJPBGaBdcTCTTQg+fyqD1imGKg6QxqpJ6GQlERSbRczy84aEU2AEubZRDsPJBGrFdh8AYTAXNTQBocEGkkFwU1BTweFVAEZIkjFEUAR1MEknuVFXA2ZSIZBweDlR4dGBgRqa8iBnqwPhRtCZwHnrANWQscDlAaDLQiCkUpwsTFx424X7uvFQg2DlOzxbECqNk9ERgSHbQdEq0jFTQAG6OfoQujREUUr5dFmgaMcY+VFxaN+wMCTGhjgAE7Fg0oxMgQiNuIAVU2iGPRAY6cNAGKYBAhRkEDEVlqbOGSscbGC9QjACDgERLAyCYDGC1wVa/GvCQACjiks01Guhs57hRzgMJamhAAIfkECQkAHAAsAAAAAB4AHgCEBAIEhIaE1NLU7OrsLC4spKak9Pb0PDo8tLa0nJqc5ObkFBIU3Nrc9PL0NDY0/P78REJEvL68jIqM1NbU7O7sNDI0tLK0/Pr8PD48nJ6cFBYUxMLE////AAAAAAAAAAAABf4gJ47kaBGEVa6seFGXOCwAsCgiJRhtaViZAk5Qqwk4EVoF1xMJMlAE5+KoOWKYYqDpDGaknEYiQRFBtFzOLzhoRTSASptlEOw4j0asR2HwBhIBc1NAGRYPaSQXBDUEPBwUUAVkiSMTRQBHUwWSe5UUcDZlIgoICIOVHBsYGBGprzITnrAiBhNtFjQHDbQiDVkLJ0UJvRwJRSjDxceNuQAOvL0UVcEcCrLFtRNM2U0RGBAbtBsQrSMUNAAao5+hC6NERROvl0WaBoxxj5UXFY37AwJIaGOAATsWDbApCISKxIAqGsSx2ABHTpoARTD4GhMtS40tXDDW0HjhQI0DPCA8AgDZZACjBa7q1ZiXBACBhi3qzJlR40aOO71OpEgUAgAh+QQJCQAcACwAAAAAHgAeAIQEAgSEhoTU0tTs6uwsLiykpqT09vQ8Ojy0trScmpzk5uQUEhTc2tz08vQ0NjT8/vxEQkS8vryMiozU1tTs7uw0MjS0srT8+vw8PjycnpwUFhTEwsT///8AAAAAAAAAAAAF/iAnjuRoEYRVrqx4UZc4LACwKCIlGG1pWJkCTlCrCTgRWgXXEwkyUATn4qg5YphioOkMZqScRiJBEUG0XM4vOGhFNIBKmzUISNqPRqxHYfAGEgFzahU1FTxpIxeFAASIREUTiSMTRQBHHDM1GmWTHBRwNp1IGBARniMbGBinqKgDE3uuIwYTbRY0Bw2zIg1ZCydFCbwcCUUowsTGNSk0Dru8FFXAHAqxxCK1TNhNEaUbsxsQrCMUNACcqKA1C52QNZKelUWYBgSGiJOLzPl1d2oMRrFocE1BoEElBlTRAI7FBjhy0gQogqHXGGhZamzhMrFGxQsHahzgkRHAxiYDGu4tODUPHhIaBBD2MCBgjiYbTHTkc3UiRaIQACH5BAkJABcALAAAAAAeAB4AhAQCBISGhMTCxCwuLOzq7Dw6PLSytPT29NTS1BQSFJyanDQ2NPTy9ERCRNza3IyKjDQyNOzu7Dw+PLy+vPz6/NTW1BQWFP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX+4CWO5GgMg1GurHggkUgkAJAQYoQcbUkMtskFUashLhMaBNcTBYqSy6FQW1AukmKgKXpAcwpF7NLQci8ECMAiaE0sgGWLEHjgDhVGM1K5Eh4BTFJqcTxnIweEA4ZERRWHIxVFAEdoNGtjkBFwNpkTEg1CkCMCEhKio6kEfakreDgGNAV6rSIMWQknRQq1IgpFKLu9F781KTQLtLURCzYqBDvDIhQIgtI9nw1trQINpyMRlxaZh5s1CWONNY+jkkWVB0CFqRSKhmh1dw7kKwysdIHmNGPTQgAcOVye1IhygUEYWllqbEn4hQIVAAV4RAQwscmPIBfcrUNCY4C1Hi8MmMw4pzJarxMpDoUAACH5BAkJABwALAAAAAAeAB4AhAQCBISGhNTS1Ozq7CwuLKSmpPT29Dw6PLS2tJyanOTi5BQSFNza3PTy9DQ2NPz+/ERCRLy+vIyKjNTW1Ozu7DQyNLSytPz6/Dw+PJyenBQWFMTCxP///wAAAAAAAAAAAAX+ICeO5KhYllKurGgIlFtkWWGIlHC35EAAiwiHQqM1OJEFoDLgjQKAKIZzQdAQFw4mCgg4RRLulMpgZDkQrvc7qAA0m1ZEs2yyBgFJ0zA58igTWQMSAXYcBm5LO18jiFEEOwJcABOMJBOTAiIDSm8xljh0QJ9IGBBCoCMbGBioqa8Dga8rfE0WSgd+sxwNWwsWP1EJuyIJXATBAMPExo+3AA66sxQOQBYcAzrEIxcChttOEaZxsxsQrSMUnRqklhSiC5+SXJWpmFyah8EVi5YXiZBG4NFziEG7FQ1k4SnUYkA1OC020GHCCIoUEQ0SJPCzJcoaJxYBTLlwIMqBGx0euzDyAUTIvSiVkgAg8I3HCzucoizAqY0YMALXGIUAACH5BAkJAB0ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7CwuLKSmpPT29Dw6POTi5LS2tJyanBQSFNza3PTy9DQ2NPz+/ERCRLy+vIyKjNTW1Ozu7DQyNLSytPz6/Dw+POTm5JyenBQWFMTCxP///wAAAAAAAAX+YCeO5IhYFlKurGgIlFtoWmGIF3W15UAAi0iHQqM1OoiZ5cYTBQBQTOeSoCV21ZmgKZJAAdIpg7HrVGlb7qAC2HBaA2V5NQhIBh3D5Mi7NHYDEgF4LmwAFUxcIxeGBEwCXwATiiQTkWkDC1AbMZQiFBtQC50dERgQQp4jHBgYqaqwAxNzsCN6eBaaB3y1HQ0YQBY/UAq9IgpfBMMAxcbIUAS5AA68tRQOwR0Zs8a2ExndlKYQb7UcEK4jFJptpJSgop2QX5Oqll9pBsOIsIzQiXXu5GHgbkUDbhkEEaKDzU0LDqEqLOTxJIqIBgoU8AEGJYCiimCmHIBy4AZHAB4d1fwI0uEelEkRNBGYyOMFoUyiwA0RkKiWsGiUQgAAIfkECQkAHQAsAAAAAB4AHgCEBAIEhIaE1NLU7OrsLC4spKak9Pb0PDo85OLktLa0nJqcFBIU3Nrc9PL0NDY0/P78REJEvL68jIqM1NbU7O7sNDI0tLK0/Pr8PD485ObknJ6cFBYUxMLE////AAAAAAAABf5gJ47kiFgWUq6saAiUW2haYYgXdbXlQACLSIdCozU6iJnlxhMFAFBM55KgJXbVmaApkkAB0imDsetUaVvuoALYcFoDZXk1CEgGHcPkyLs0di8CTHlsABWDXCIPFjQWZQJfABOJJA1FGjEdAwtQG5mUUzM1cxEYEEKgIwMJCXipr5oTc7AuE3gWnAd8tA0YQBY/UAq0IgpfBMEAw8TGUAS4AA67sBQOvx0ZssQjehnblKUQb7AcEBioQ5xtn5QUG1ALmZBfk6kTkWkGwYevF4UEg+rcycOA3YoG2jJICOCKjjU3LTi8q9CQx5MoIhooUMDHF5QAiS6CmXIAyoEbHh4BgFTzI0iHe/Q6ROBEoCKPF642wfM2RNA2YM8ohQAAIfkECQkAHgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakLC4s9Pb0tLa0PDo85OLknJqcFBIU3Nrc9PL0rK6sNDY0/P78vL68REJEjIqM1NbU7O7sNDI0/Pr8PD485ObknJ6cFBYUtLK0xMLE////AAAABf6gJ47kmDhcUq6saAiVS2gaYYhXdbXlUACLiKdBozU8iRnnxhMFAFCM53KocSCeKk3QFE2gAOmUwdhlaxpud2ABbDqtgdK8GgQmA4+BcuRdGjsvAkx6bQAWhF0iEBw0HGYCYAAUiiQVRRoxHgMLUBualVMzNXQRGBJCoSMDBwd5qrCbFHSxODoeHJ0IfbUGVQQJP1AKtSIMRRzCAMTFFKMcDp0PvLEGjcCbg8UjEIDblaYScLEdEhipHhWdbqCVFRtQC5qRYJSqFJJqBsKIsBeGBQjZwaOHQbsVDWZ5yDAhwKs6D9yMW9EBnoWHPJ5EEdFAgYI+GMAEUKQxzBQEUCUQ3AgJZeSaH0E84KvnIUKnAhh5vHjFKV4GERW0FXNQoICDSiEAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5Jg4XFKurGgIlUtoGmGIV3W15VAAi4inQaM1PIkZ58YTBQBQjOdyqHEgnipN0BRNoADplMHYZWsabndgAWw6rYHSvBoEJgOPgXLkXRo7LwJMem0AFoRdIhAcNBxmAmAAFIokFUUaMR4DC1AbmpVTMzV0ERgSQqEjAwcHeaqwmxR0sTg6HhydCH21BlUECT9QCrUiDEUcwgDExRSjHA6dD7yxBo3Am4PFIxCA25WmEnCxGREcGSMVnW6glRejBJCSlKqXRX0GwoiwjI50dnj0MGi3osGsKQIE0CIx4IGbcSs6bDj0qsmTKCIaKFDQBwOYAIouhpmCAAqCGx4ioYBc8yOIBwrzPEToVKBikxevOEFZgM5DBW3FHBQo4KBSCAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuSYOFxSrqxoCJVLaBphiFd1teVQAIuIp0GjNTyJGefGEwUAUIzncqhxIJ4qTdAUTaAA6ZTB2GVrGm53YAFsOq2B0rwaBCYDj4Fy5F0aOy8CTHptABaEXSIQHDQcZgJgABSKJBVFGjEeAwtQG5qVUzM1dBEYEkKhIwMHB3mqsJsUdLE4Oh4cnQh9tQZVBAk/UAq1IgxFHMIAxMUUoxwOnQ+8sQaNwJuDxSMQgNuVphJwsRkRHBkjFZ1uoJUXowSQkpSql0V9BsKIsIyOdHZ49DBotyIHlgsCBNAiMeCBm3F1CBBYouhJFBENFCjooyVNRTBSLiCAguBGRzVNHXwAEUJh3iYliXi8eMUJygJ0UwYsVOWgQAEHlUIAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5Jg4XFKurGgIlUtoGmGIV3W15VAAi4inQaM1PIkZ58YTBQBQjOdyqHEgnipN0BRNoADplMHYZWsabndgAWw6rYHSvBoEJgOPgXLkXRo7LwJMem0AFoRdIhAcNBxmAmAAFIokFUUaMR4DC1AbmpVTMzV0ERgSQqEjAwcHeaqwmxR0sTg6HhydCH21BlUECT9QCrUiDEUcwgDExRSjHA6dD7yxBo3Am4PFIxCA25WmEnCxGREcGSMVnW6glRejBJCSlKqXRX0GwoiwjI50dnj0MGi3IgeWCwIE0CIx4IGbcXUIEFii6EkUHGTMaElTEYyUfhoO7NiopokPIEIZKozScCSDkkQ8XrwyAM/MhQELVSXgkKJSCAAh+QQJCQAeACwAAAAAHgAeAIQEAgSEhoTU0tTs6uykpqQsLiz09vS0trQ8Ojzk4uScmpwUEhTc2tz08vSsrqw0NjT8/vy8vrxEQkSMiozU1tTs7uw0MjT8+vw8Pjzk5uScnpwUFhS0srTEwsT///8AAAAF/qAnjuSYOFxSrqxoCJVLaBphiFd1teVQAIuIp0GjNTyJGefGEwUAUIzncqhxIJ4qTdAUTaAA6ZTB2GVrGm53YAFsOq2B0rwaBCYDj4Fy5F0aOy8CTHptABaEXSIQHDQcZgJgABSKJBVFGjEeAwtQG5qVUzM1dBEYEkKhIwMHB3mqsJsUdLE4Oh4cnQh9tQZVBAk/UAq1IgxFHMIAxMUUoxwOnQ+8sQaNwJuDxSMQgNuVphJwsRkRHBkjFZ1uoJUXowSQkpSql0V9BsKIsIyOdHZ4PEC45acClgsCBNAiYa3GqxUZCBBYokhADQUHcJAxoyVNRQ0KNGTsJ3JHRzVNHRoSyHOJQMgjETVQVPSHybuQ8XAMWKgqATQVikIAACH5BAkJAB4ALAAAAAAeAB4AhAQCBISGhNTS1Ozq7KSmpCwuLPT29LS2tDw6POTi5JyanBQSFNza3PTy9KyurDQ2NPz+/Ly+vERCRIyKjNTW1Ozu7DQyNPz6/Dw+POTm5JyenBQWFLSytMTCxP///wAAAAX+oCeO5Jg4XFKurGgIlUtoGmGIV3W15VAAi4inQaM1PIkZ58YTBQBQjOdyqHEgnipN0BRNoADplMHYZWsabndgAWw6rYHSvBoEJgOPgXLkXRo7LwJMem0AFoRdIhAcNBxmAmAAFIokFUUaMR4DC1AbmpVTMzV0ERgSQqEjAwcHeaqwmxR0sTg6HhydCH21BlUECT9QCrUiDEUcwgDExRSjHA6dD7yxBo3Am4PFIxCA25UZra+w4QcZIxejBLSKBg406yKXRdSK80Y4jRpXsBf6j+gECNjxJ9GKHFguCGQ3wlqNcSUyvFuiSECRAzjImNGSpuJFD4xoHNjBUU0ThwQd8lwioEDDkQxKDLb4Y8aAOjMXBjAMlYADBwaVQgAAIfkECQkAGgAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakNDI09Pb0tLa05OLkPD48nJ6c3Nrc9PL0rK6s/P78vL68FBYUjIqM1NbU7O7sNDY0/Pr85ObkREJEtLK0xMLE////AAAAAAAAAAAAAAAAAAAABf6gJo7kiDQYUq6saAiTSygKYYjVVLXlUACQh2ZCozE0iBnmxhMFANCEpnKoYRyaKk3QdEIB0uliscvWFNyuD5hpDZTl1SAQGWgMkiOvwti9BEx3PwAFgV0iDhg0GGUCXwAShyRERTEaAxBQEJaSUzM1cQ8JF0KdIwMHB3amrHd9rSUOOkgzB3GtVDUnRQuwIhJFGIo0vb4LwUkKtr5TVQQqfLew0cySFqmrrNcHFiMVnwTSXQYNNOEilEas6Qp6FcNXrO+LcRUCAjt8his5WPb4LQwoIpCthIVySw4JKHIAx5gyWtAoZKghEY1lEdM0EVjDDpFPRywo2deC2h1wZQwqDBDXCQGGBioOhQAAIfkECQkAGQAsAAAAAB4AHgCEBAIEhIaE1NLU7OrspKakNDI09Pb0tLa05OLkPD48nJ6c9PL0rK6s/P78vL68FBYUjIqM3Nrc7O7sNDY0/Pr85ObkREJEtLK0xMLE////AAAAAAAAAAAAAAAAAAAAAAAABfpgJo7kiDAXUq6saAiSSygKYYiURLXlUAAPR0ZCoy0yiNnlxhMFANBEhnKoXRqZKk3QdEIB0mkkssvWFNyuD4hpDZTl1UtwM0RiPMpiN2dOLzRXXSUUgAoXZURneIMiC0UKeBQzNXGNBpQEcRUHBwONPZ2foKQjBnulKw06SDMHlqVUNSdFEakjEUUXhgq2txkClCmusKSFs1OovzjKy12cnrfQFSOTNJqlBgzXiZBHpIpGOIaCxuRxFAICO3p+LDlY6estBoAEoysV20uDAkUHOMaU0YKm378MDQy9MrNlUL0an4hQOlJBibs8yjBxwzGgGCgEFxioGBQCADs=" /></div>')
					}else{
						alert('Kindly choose your Zone Region before add courier service.');
					}
				},
				onAddShippingMethodSubmitted: function( event, target, posted_data ) {
					if ( 'wc-modal-add-shipping-method' === target ) {
						shippingMethodView.block();

						// Add method to zone via ajax call
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=woocommerce_shipping_zone_add_method', {
							wc_shipping_zones_nonce : data.wc_shipping_zones_nonce,
							method_id               : posted_data.add_method_id,
							zone_id                 : data.zone_id
						}, function( response, textStatus ) {
							if ( 'success' === textStatus && response.success ) {
								if ( response.data.zone_id !== data.zone_id ) {
									data.zone_id = response.data.zone_id;
									if ( window.history.pushState ) {
										window.history.pushState(
											{},
											'',
											'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=4' + response.data.zone_id
										);
									}
								}
								// Trigger save if there are changes, or just re-render
								if ( _.size( shippingMethodView.model.changes ) ) {
									shippingMethodView.model.save();
								} else {
									shippingMethodView.model.set( 'methods', response.data.methods );
									shippingMethodView.model.trigger( 'change:methods' );
									shippingMethodView.model.changes = {};
									shippingMethodView.model.trigger( 'saved:methods' );
								}
							}
							shippingMethodView.unblock();
						}, 'json' );
					}
				},
				onChangeShippingMethodSelector: function() {
					var description = $( this ).find( 'option:selected' ).data( 'description' );
					$( this ).parent().find( '.wc-shipping-zone-method-description' ).remove();
					$( this ).after( '<div class="wc-shipping-zone-method-description">' + description + '</div>' );
					$( this ).closest( 'article' ).height( $( this ).parent().height() );
				},
				onTogglePostcodes: function( event ) {
					event.preventDefault();
					var $tr = $( this ).closest( 'tr');
					$tr.find( '.wc-shipping-zone-postcodes' ).show();
					$tr.find( '.wc-shipping-zone-postcodes-toggle' ).hide();
				}
			} ),
			shippingMethod = new ShippingMethod({
				methods: data.methods,
				zone_name: data.zone_name
			} ),
			shippingMethodView = new ShippingMethodView({
				model:    shippingMethod,
				el:       $tbody
			} );

		shippingMethodView.render();

		$tbody.sortable({
			items: 'tr',
			cursor: 'move',
			axis: 'y',
			handle: 'td.wc-shipping-zone-method-sort',
			scrollSensitivity: 40
		});
	});
})( jQuery, shippingZoneMethodsLocalizeScript, wp, ajaxurl );