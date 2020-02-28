jQuery( function( $ ) {

	// wc_checkout_params is required to continue, ensure the object exists
	if ( typeof wc_checkout_params === 'undefined' ) {
		return false;
	}

	var dhp_get_url = function( endpoint ) {
		var url = wc_checkout_params.wc_ajax_url.toString();
		url = url.replace('wc-ajax', 'dhp-ajax');
		return url.replace('%%endpoint%%', endpoint);
	};

	if(typeof(is_blocked) == "undefined"){
		/**
		 * Check if a node is blocked for processing.
		 *
		 * @param {JQuery Object} $node
		 * @return {bool} True if the DOM Element is UI Blocked, false if not.
		 */
		var is_blocked = function( $node ) {
			return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
		};
	}

	if(typeof(block) == "undefined"){
		/**
		 * Block a node visually for processing.
		 *
		 * @param {JQuery Object} $node
		 */
		var block = function( $node ) {
			if ( ! is_blocked( $node ) ) {
				$node.addClass( 'processing' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		};
	}

	if(typeof(unblock) == "undefined"){
		/**
		 * Unblock a node after processing is complete.
		 *
		 * @param {JQuery Object} $node
		 */
		var unblock = function( $node ) {
			$node.removeClass( 'processing' ).unblock();
		};
	}

	/**
	 * Object to handle cart UI.
	 */
	var dhpPay = {
		/**
		 * Initialize cart UI events.
		 */
		init: function() {
			this.embedClicked = this.embedClicked.bind( this );
			this.expressClicked = this.expressClicked.bind( this );

			$( document ).on('click', '.dhp_ebch a', this.embedClicked);
			$( document ).on('click', '.dhp_exch a', this.expressClicked);
		},

		embedClicked: function() {
			dhpPay.submit();
		},

		expressClicked: function() {
			dhpPay.submit(true);
		},

		$order_review: $( '#order_review' ),
		$checkout_form: $( 'form.checkout' ),

		get_payment_method: function() {
			return dhpPay.$checkout_form.find( 'input[name="payment_method"]:checked' ).val();
		},
		blockOnSubmit: function( $form ) {
			/*
			var form_data = $form.data();

			if ( 1 !== form_data['blockUI.isBlocked'] ) {
				$form.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
			*/
		},
		submit: function(express) {
			express = typeof(express) == "undefined" || express !== true ? false: true;

			var $form = $('#order_review');

			var valid = true;
			if(valid){
				if ( $form.is( '.processing' ) ) {
					return false;
				}

				$form.addClass( 'processing' );
				dhpPay.blockOnSubmit( $form );

				// ajaxSetup is global, but we use it to ensure JSON is valid once returned.
				$.ajaxSetup( {
					dataFilter: function( raw_response, dataType ) {
						// We only want to work with JSON
						if ( 'json' !== dataType ) {
							return raw_response;
						}

						if ( dhpPay.is_valid_json( raw_response ) ) {
							return raw_response;
						} else {
							// Attempt to fix the malformed JSON
							var maybe_valid_json = raw_response.match( /{"result.*}/ );

							if ( null === maybe_valid_json ) {
								console.log( 'Unable to fix malformed JSON' );
							} else if ( dhpPay.is_valid_json( maybe_valid_json[0] ) ) {
								console.log( 'Fixed malformed JSON. Original:' );
								console.log( raw_response );
								raw_response = maybe_valid_json[0];
							} else {
								console.log( 'Unable to fix malformed JSON' );
							}
						}

						return raw_response;
					}
				} );

				var url = express ? dhp_get_url('express_pay') : dhp_get_url('embed_pay');

				window.location.href = url + "&key=" + dhpPay.getParameter('key') + "&" + $form.serialize();
			}

			return false;
		},
		submit_error: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			dhpPay.$checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			dhpPay.$checkout_form.removeClass( 'processing' ).unblock();
			dhpPay.$checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			dhpPay.scroll_to_notices();
			$( document.body ).trigger( 'checkout_error' );
		},
		scroll_to_notices: function() {
			var scrollElement           = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

			if ( ! scrollElement.length ) {
				scrollElement = $( '.form.checkout' );
			}
			$.scroll_to_notices( scrollElement );
		},
		is_valid_json: function( raw_json ) {
			try {
				var json = $.parseJSON( raw_json );

				return ( json && 'object' === typeof json );
			} catch ( e ) {
				return false;
			}
		},
		getParameter: function( k ) {
			var queryString = window.location.search;
			var urlParams = new URLSearchParams(queryString);
			var value = urlParams.get(k);
			return value;
		}
	};

	dhpPay.init();
} );