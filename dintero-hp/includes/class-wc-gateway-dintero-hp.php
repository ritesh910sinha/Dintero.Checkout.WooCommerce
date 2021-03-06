<?php
/**
 * The file that defines the custom gateway class.
 *
 * A class definition that includes core functions of the custom payment gateway.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */

/**
 * The custom gateway class.
 *
 * This is used to define the core functions of the custom payment gateway.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */
class WC_Gateway_Dintero_HP extends WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'dintero-hp'; // payment gateway plugin ID
		$this->has_fields         = false;
		$this->method_title       = __( 'Dintero' );
		$this->method_description = __( 'Redirect customers to Dintero hosted page.' ); // will be displayed on the options page
		$this->init_form_fields();
		$this->init_settings();
		$this->supports                         = array(
			'products',
			'refunds'
		);
		$this->title                            = $this->get_option( 'title' );
		$this->description                      = $this->get_option( 'description' );
		$this->enabled                          = $this->get_option( 'enabled' );
		$this->test_mode                        = 'yes' === $this->get_option( 'test_mode' );
		$this->callback_verification            = 'yes' === $this->get_option( 'callback_verification' );
		$this->account_id                       = $this->get_option( 'account_id' );
		$this->client_id                        = $this->test_mode ? $this->get_option( 'test_client_id' ) : $this->get_option( 'production_client_id' );
		$this->client_secret                    = $this->test_mode ? $this->get_option( 'test_client_secret' ) : $this->get_option( 'production_client_secret' );
		$this->profile_id                       = $this->test_mode ? $this->get_option( 'test_profile_id' ) : $this->get_option( 'production_profile_id' );
		$this->checkout_logo_width              = $this->get_option( 'checkout_logo_width' ) ? $this->get_option( 'checkout_logo_width' ) : 600;
		$this->default_order_status             = $this->get_option('default_order_status') ? $this->get_option('default_order_status') : 'wc-processing';
		$this->manual_capture_status            = str_replace( 'wc-', '',
			$this->get_option( 'manual_capture_status' ) );
		$this->additional_manual_capture_status = str_replace( 'wc-', '',
			$this->get_option( 'additional_manual_capture_status' ) );
		$this->additional_cancel_status         = str_replace( 'wc-', '',
			$this->get_option( 'additional_cancel_status' ) );
		$this->additional_refund_status         = str_replace( 'wc-', '',
			$this->get_option( 'additional_refund_status' ) );
		$this->api_endpoint                     = 'https://api.dintero.com/v1';
		$this->checkout_endpoint                = 'https://checkout.dintero.com/v1';
		$environment_character                  = $this->test_mode ? 'T' : 'P';
		$this->oid                              = $environment_character . $this->get_option( 'account_id' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		if ( $this->callback_verification ) {
			//Enable callback server-to-server verification
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'callback' ) );
		} else {
			//Use thank you page to check for transactions, only if callbacks are unavailable
			add_action( 'woocommerce_thankyou', array( $this, 'callback' ), 1, 1 );
		}

		add_action( 'woocommerce_order_status_changed', array( $this, 'check_status' ), 10, 3 );
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_url  = 'https://checkout.dintero.com/v1/branding/profiles/' . $this->profile_id . '/variant/colors/color/cecece/width/' . $this->checkout_logo_width . '/dintero_left_frame.svg';
		$icon_html = '<img src="' . esc_attr( $icon_url ) . '" alt="Dintero Logo" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Plugin options.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                          => array(
				'title'       => __( 'Enable/Disable' ),
				'label'       => __( 'Enable Dintero Hosted Page Gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'                            => array(
				'title'       => __( 'Title' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.' ),
				'default'     => __( 'Dintero' ),
				'desc_tip'    => true,
			),
			'description'                      => array(
				'title'       => __( 'Description' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.' ),
				'default'     => __( 'Pay through Dintero gateway.' ),
				'desc_tip'    => true,
			),
			'account_id'                       => array(
				'title'       => __( 'Account ID' ),
				'type'        => 'text',
				'description' => __( 'Found under (SETTINGS >> Account) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_test_credentials'          => array(
				'title'       => __( 'Client Test' ),
				'type'        => 'title',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' )
			),
			'test_client_id'                   => array(
				'title'       => __( 'Test Client ID' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_client_secret'               => array(
				'title'       => __( 'Test Client Secret' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_profile_id'                  => array(
				'title'       => __( 'Test Payment Profile ID' ),
				'type'        => 'text',
				'description' => __( 'Test payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_production_credentials'    => array(
				'title'       => __( 'Client Production' ),
				'type'        => 'title',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
			),
			'production_client_id'             => array(
				'title'       => __( 'Production Client ID' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'production_client_secret'         => array(
				'title'       => __( 'Production Client Secret' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'production_profile_id'            => array(
				'title'       => __( 'Production Payment Profile ID' ),
				'type'        => 'text',
				'description' => __( 'Production payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'checkout_settings'                => array(
				'title'       => __( 'Checkout' ),
				'type'        => 'title',
				'description' => __( 'Checkout settings.' )
			),
			'test_mode'                        => array(
				'title'       => __( 'Test mode' ),
				'label'       => __( 'Enable Test Mode' ),
				'type'        => 'checkbox',
				'description' => __( 'Put the payment gateway in test mode using client test credentials.' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'callback_verification'            => array(
				'title'       => __( 'Callback URL Verification' ),
				'label'       => __( 'Enable Callback URL Server-to-Server Verification' ),
				'type'        => 'checkbox',
				'description' => __( 'Enabling this will send callback URL to the API and verify the transaction when a callback request received. Disabling this will verify the transaction using parameters returned to the return page.' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'checkout_logo_width'              => array(
				'title'       => __( 'Dintero Checkout Logo Width (in pixels)' ),
				'type'        => 'number',
				'description' => __( 'The width of Dintero\'s logo on the checkout page in pixels.' ),
				'default'     => 600,
				'desc_tip'    => true,
			),
			'capture_settings'                 => array(
				'title'       => __( 'Payment Capture' ),
				'type'        => 'title',
				'description' => __( 'Payment Capture settings.' )
			),
			'default_order_status' => array(
				'title'       => __( 'Default Order Status' ),
				'type'        => 'select',
				'options'     => array(
					'wc-processing' => _x( 'Processing', 'Order status' ),
					'wc-on-hold'    => _x( 'On hold', 'Order status' ),
				),
				'default'     => 'wc-processing',
				'description' => __( 'When payment Authorized.' ),
				'desc_tip'    => true
			),
			'manual_capture_settings' => array(
				'title'       => __( 'Capture order when:' ),
				'type'        => 'title',
			),
			'manual_capture_status'            => array(
				'title'       => __( 'Order status is changed to: ' ),
				'type'        => 'select',
				'options'     => wc_get_order_statuses(),
				'default'     => 'wc-completed',
				'description' => __( 'Select a status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true
			),
			'additional_manual_capture_status' => array(
				'title'       => __( 'Order status is changed to (additional): ' ),
				'type'        => 'select',
				'options'     => ( array(
					                   - 1 => '--- Disable Additional Manual Capture Order Status ---'
				                   ) + wc_get_order_statuses() ),
				'default'     => -1,
				'description' => __( 'Select an additional status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true
			),
			'cancel_refund_settings'           => array(
				'title'       => __( 'Cancel or refund order when:' ),
				'type'        => 'title'
			),
			'additional_cancel_status'         => array(
				'title'       => __( 'Order status is changed to:' ),
				'type'        => 'select',
				'options'     => ( array(
					                   - 1 => '--- Disable Additional Cancellation Order Status ---'
				                   ) + wc_get_order_statuses() ),
				'default'     => - 1,
				'description' => __( 'Select an additional status that will be used to cancel the order. Status "Cancelled" will be always used to cancel the order.' ),
				'desc_tip'    => true
			),
			'additional_refund_status'         => array(
				'title'       => __( 'Order status is changed to (additional): ' ),
				'type'        => 'select',
				'options'     => ( array(
					                   - 1 => '--- Disable Additional Refund Order Status ---'
				                   ) + wc_get_order_statuses() ),
				'default'     => - 1,
				'description' => __( 'Select an additional status that will be used to refund the order payment. Status "Refunded" will be always used to refund the order payment.' ),
				'desc_tip'    => true
			)
		);
	}

	/**
	 * Requesting access token
	 */
	private function get_access_token() {
		$api_endpoint = $this->api_endpoint . '/accounts';

		$headers = array(
			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret )
		);

		$payload = array(
			'grant_type' => 'client_credentials',
			'audience'   => $api_endpoint . '/' . $this->oid
		);

		$response = wp_remote_post( $api_endpoint . '/' . $this->oid . '/auth/token', array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $payload ),
			'timeout'   => 90,
			'sslverify' => false
		) );

		// Retrieve the body's response if no errors found
		$response_body  = wp_remote_retrieve_body( $response );
		$response_array = json_decode( $response_body, true );

		if ( ! array_key_exists( 'access_token', $response_array ) ) {
			return false;
		}
		$access_token = $response_array['access_token'];

		return $access_token;
	}

	/**
	 * Creating checkout session and requesting payment page URL
	 */
	private function get_payment_page_url( $order ) {
		if ( ! empty( $order ) AND $order instanceof WC_Order ) {
			$order_id     = $order->get_id();
			$access_token = $this->get_access_token();
			$api_endpoint = $this->checkout_endpoint . '/sessions-profile';

			$return_url   = $this->get_return_url( $order );
			$callback_url = WC()->api_request_url( strtolower( get_class( $this ) ) );

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
			$order_tax_amount   = absint( strval( floatval( $order->get_total_tax() ) * 100 ) );

			$items = array();

			$counter = 0;
			foreach ( $order->get_items() as $order_item ) {
				$counter ++;
				$line_id                = strval( $counter );
				$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
				$item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
				$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
						true ) ) * 100 ) );
				$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
						2 ) * 100 ) : 0;
				$item                   = array(
					'id'          => 'item_' . $counter,
					'description' => $order_item->get_name(),
					'quantity'    => $order_item->get_quantity(),
					'vat_amount'  => $item_tax_amount,
					'vat'         => $item_tax_percentage,
					'amount'      => $item_line_total_amount,
					'line_id'     => $line_id
				);
				array_push( $items, $item );
			}

			if ( count( $order->get_shipping_methods() ) > 0 ) {
				$counter ++;
				$line_id                = strval( $counter );
				$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
				$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
				$item_line_total_amount = $item_total_amount + $item_tax_amount;
				$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
						2 ) * 100 ) : 0;

				$item = array(
					'id'          => 'shipping',
					'description' => 'Shipping: ' . $order->get_shipping_method(),
					'quantity'    => 1,
					'vat_amount'  => $item_tax_amount,
					'vat'         => $item_tax_percentage,
					'amount'      => $item_line_total_amount,
					'line_id'     => $line_id
				);
				array_push( $items, $item );
			}

			$headers = array(
				'Content-type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $access_token
			);

			$payload = array(
				'url'        => array(
					'return_url'   => $return_url,
					'callback_url' => $callback_url
				),
				'customer'   => array(
					'email'        => $order->get_billing_email(),
					'phone_number' => $order->get_billing_phone()
				),
				'order'      => array(
					'amount'             => $order_total_amount,
					'vat_amount'         => $order_tax_amount,
					'currency'           => $order->get_currency(),
					'merchant_reference' => strval( $order_id ),
					'shipping_address'   => array(
						'first_name'   => $order->get_shipping_first_name(),
						'last_name'    => $order->get_shipping_last_name(),
						'address_line' => $order->get_shipping_address_1(),
						'postal_code'  => $order->get_shipping_postcode(),
						'postal_place' => $order->get_shipping_city(),
						'country'      => $order->get_shipping_country()
					),
					'billing_address'    => array(
						'first_name'   => $order->get_billing_first_name(),
						'last_name'    => $order->get_billing_last_name(),
						'address_line' => $order->get_billing_address_1(),
						'postal_code'  => $order->get_billing_postcode(),
						'postal_place' => $order->get_billing_city(),
						'country'      => $order->get_billing_country()
					),
					'items'              => $items
				),
				'profile_id' => $this->profile_id
			);

			$response = wp_remote_post( $api_endpoint, array(
				'method'    => 'POST',
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 90,
				'sslverify' => false
			) );

			// Retrieve the body's response if no errors found
			$response_body  = wp_remote_retrieve_body( $response );
			$response_array = json_decode( $response_body, true );

			if ( ! array_key_exists( 'url', $response_array ) ) {
				return false;
			}
			$payment_page_url = $response_array['url'];

			return $payment_page_url;
		}
	}


	/**
	 * We're processing the payment here.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order ) AND $order instanceof WC_Order ) {
			$payment_page_url = $this->get_payment_page_url( $order );

			return array(
				'result'   => 'success',
				'redirect' => $payment_page_url
			);
		}
	}

	/**
	 * Get transaction by ID.
	 */
	private function get_transaction( $transaction_id ) {
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/transactions';

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token
		);

		$response = wp_remote_get( $api_endpoint . '/' . $transaction_id, array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 90,
			'sslverify' => false
		) );

		// Retrieve the body's response if no errors found
		$response_body = wp_remote_retrieve_body( $response );
		$transaction   = json_decode( $response_body, true );

		return $transaction;
	}

	/**
	 * Creating order receipt.
	 */
	private function create_receipt( $order ) {
		if ( ! empty( $order ) AND $order instanceof WC_Order ) {
			$order_id     = $order->get_id();
			$access_token = $this->get_access_token();
			$api_endpoint = $this->api_endpoint . '/accounts';

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
			$order_tax_amount   = absint( strval( floatval( $order->get_total_tax() ) * 100 ) );
			$order_net_amount   = $order_total_amount - $order_tax_amount;
			$purchase_date      = strval( $order->get_date_paid() );
			$currency           = $order->get_currency();
			$transaction_id     = $order->get_transaction_id();

			$store_name  = get_bloginfo( 'name' );
			$store_email = get_bloginfo( 'admin_email' );

			$items = array();

			$counter = 0;
			foreach ( $order->get_items() as $order_item ) {
				$counter ++;
				$line_id                = $counter;
				$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
				$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
						true ) ) * 100 ) );

				$item = array(
					'id'           => 'item_' . $counter,
					'description'  => $order_item->get_name(),
					'quantity'     => $order_item->get_quantity(),
					'gross_amount' => $item_line_total_amount,
					'net_amount'   => $item_total_amount,
					'line_id'      => $line_id
				);
				array_push( $items, $item );
			}

			if ( count( $order->get_shipping_methods() ) > 0 ) {
				$counter ++;
				$line_id                = $counter;
				$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
				$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
				$item_line_total_amount = $item_total_amount + $item_tax_amount;

				$item = array(
					'id'           => 'shipping',
					'description'  => 'Shipping: ' . $order->get_shipping_method(),
					'quantity'     => 1,
					'gross_amount' => $item_line_total_amount,
					'net_amount'   => $item_total_amount,
					'line_id'      => $line_id
				);
				array_push( $items, $item );
			}

			$headers = array(
				'Content-type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $access_token
			);

			$payload = array(
				array(
					'store'          => array(
						'id'    => $store_name,
						'name'  => $store_name,
						'email' => $store_email,
					),
					'receipt_id'     => strval( $order_id ),
					'purchase_at'    => $purchase_date,
					'items'          => $items,
					'gross_amount'   => $order_total_amount,
					'net_amount'     => $order_net_amount,
					'currency'       => $currency,
					'order_number'   => strval( $order_id ),
					'transaction_id' => $transaction_id
				)
			);

			$response = wp_remote_post( $api_endpoint . '/' . $this->oid . '/receipts', array(
				'method'    => 'POST',
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 90,
				'sslverify' => false
			) );

			// Retrieve the body's response if no errors found
			$response_body  = wp_remote_retrieve_body( $response );
			$response_array = json_decode( $response_body, true );

			if ( array_key_exists( 'receipts', $response_array ) AND
			     count( $response_array['receipts'] ) AND
			     array_key_exists( 'id', $response_array['receipts'][0] ) ) {

				$receipt_id = $response_array['receipts'][0]['id'];
				$order->update_meta_data( 'receipt_id', $receipt_id );
				$order->save();

				$note = 'Payment receipt created via Dintero. Receipt ID: ' . $receipt_id;
				$order->add_order_note( $note );

				return true;
			}

			return false;
		}
	}

	/**
	 * Complete order, add transaction ID and note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $note Payment note.
	 */
	private function payment_complete( $order, $transaction_id = '', $note = '' ) {
		$order->add_order_note( $note );
		$order->payment_complete( $transaction_id );
		wc_reduce_stock_levels( $order->get_id() );
		$this->create_receipt( $order );
	}

	/**
	 * Hold order and add note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $reason Reason why the payment is on hold.
	 */
	private function process_authorization( $order, $transaction_id = '', $reason = '' ) {
		$order->set_transaction_id( $transaction_id );
		$order->update_status( $this->get_option('default_order_status'), $reason );
	}

	/**
	 * Check order status when it is changed and call the right action
	 *
	 * @param int $order_id Order ID.
	 */
	public function check_status( $order_id, $previous_status, $current_status ) {

		if ( $current_status === $this->manual_capture_status OR
		     $current_status === $this->additional_manual_capture_status ) {

			$this->check_capture( $order_id );
		} else {
			if ( $current_status === 'cancelled' OR
			     $current_status === $this->additional_cancel_status ) {

				$this->cancel( $order_id );
			}

			if ( $current_status === 'refunded' OR
			     $current_status === $this->additional_refund_status ) {

				$this->process_refund( $order_id );
			}
		}
	}

	/**
	 * Cancel Order
	 *
	 * @param int $order_id Order ID.
	 */
	private function cancel( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) AND
		     $order instanceof WC_Order AND
		     $order->get_transaction_id() AND
		     'dintero-hp' === $order->get_payment_method() ) {


			$transaction_id = $order->get_transaction_id();
			$transaction    = $this->get_transaction( $transaction_id );
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}
			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );

			if ( $transaction_order_id === $order_id AND
			     array_key_exists( 'status', $transaction ) AND
			     $transaction['status'] === 'AUTHORIZED' ) {

				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token
				);

				$response = wp_remote_post( $api_endpoint . '/' . $transaction_id . '/void', array(
					'method'    => 'POST',
					'headers'   => $headers,
					'timeout'   => 90,
					'sslverify' => false
				) );

				// Retrieve the body's response if no errors found
				$response_body  = wp_remote_retrieve_body( $response );
				$response_array = json_decode( $response_body, true );

				if ( array_key_exists( 'status', $response_array ) AND
				     $response_array['status'] === 'AUTHORIZATION_VOIDED' ) {

					$note = __( 'Transaction cancelled via Dintero. Transaction ID: ' ) . $transaction_id;
					$order->add_order_note( $note );
					wc_increase_stock_levels( $order_id );
				}
			}
		}
	}

	/**
	 * Process a refund if supported.
	 *
	 * @param int $order_id Order ID.
	 * @param float $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) AND
		     $order instanceof WC_Order AND
		     $order->get_transaction_id() AND
		     'dintero-hp' === $order->get_payment_method() ) {

			$transaction_id = $order->get_transaction_id();
			$transaction    = $this->get_transaction( $transaction_id );
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}
			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );

			if ( $transaction_order_id === $order_id AND
			     array_key_exists( 'status', $transaction ) AND
			     array_key_exists( 'amount', $transaction ) AND
			     ( $transaction['status'] === 'CAPTURED' OR $transaction['status'] === 'PARTIALLY_REFUNDED' ) ) {

				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				if ( empty( $amount ) ) {
					$amount = $transaction['amount'];
				} else {
					$amount = ( floatval( $amount ) * 100 );
				}

				$amount = absint( strval( $amount ) );

				$items = array(
					array(
						'amount'  => $amount,
						'line_id' => '1'
					)
				);

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token
				);

				$payload = array(
					'amount' => $amount,
					'reason' => $reason,
					'items'  => $items
				);

				$response = wp_remote_post( $api_endpoint . '/' . $transaction_id . '/refund', array(
					'method'    => 'POST',
					'headers'   => $headers,
					'body'      => wp_json_encode( $payload ),
					'timeout'   => 90,
					'sslverify' => false
				) );

				// Retrieve the body's response if no errors found
				$response_body  = wp_remote_retrieve_body( $response );
				$response_array = json_decode( $response_body, true );

				if ( array_key_exists( 'status', $response_array ) ) {

					$note = '';
					if ( $response_array['status'] === 'REFUNDED' ) {
						$note = __( 'Payment refunded via Dintero. Transaction ID: ' ) . $transaction_id;
						wc_increase_stock_levels( $order_id );
					} elseif ( $response_array['status'] === 'PARTIALLY_REFUNDED' ) {
						$note = ( $amount / 100 ) . ' ' . __( $order->get_currency() . ' refunded via Dintero. Transaction ID: ' ) . $transaction_id;
					}

					$order->add_order_note( $note );

					return true;
				}

				return false;
			}
		}
	}

	/**
	 * check if payment capture is possible when the order is changed from on-hold to complete or processing
	 *
	 * @param int $order_id Order ID.
	 */
	private function check_capture( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) AND
		     $order instanceof WC_Order AND
		     $order->get_transaction_id() AND
		     'dintero-hp' === $order->get_payment_method() ) {

			$transaction_id = $order->get_transaction_id();
			$transaction    = $this->get_transaction( $transaction_id );
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}
			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );
			if ( $transaction_order_id === $order_id ) {
				$this->capture( $order, $transaction );
			}
		}
	}

	/**
	 * Capture Payment.
	 */
	private function capture( $order, $transaction = null ) {
		if ( ! empty( $order ) AND
		     $order instanceof WC_Order AND
		     $order->get_transaction_id() ) {

			$order_id = $order->get_id();

			$transaction_id = $order->get_transaction_id();
			if ( empty( $transaction ) ) {
				$transaction = $this->get_transaction( $transaction_id );
			}

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );

			if ( array_key_exists( 'status', $transaction ) AND
			     array_key_exists( 'amount', $transaction ) AND
			     $transaction['status'] === 'AUTHORIZED' AND
			     $transaction['amount'] >= $order_total_amount ) {
				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				$items = array();

				$counter = 0;
				foreach ( $order->get_items() as $order_item ) {
					$counter ++;
					$line_id                = strval( $counter );
					$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
					$item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
					$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
							true ) ) * 100 ) );
					$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
							2 ) * 100 ) : 0;
					$item                   = array(
						'id'          => 'item_' . $counter,
						'description' => $order_item->get_name(),
						'quantity'    => $order_item->get_quantity(),
						'vat_amount'  => $item_tax_amount,
						'vat'         => $item_tax_percentage,
						'amount'      => $item_line_total_amount,
						'line_id'     => $line_id
					);
					array_push( $items, $item );
				}

				if ( count( $order->get_shipping_methods() ) > 0 ) {
					$counter ++;
					$line_id                = strval( $counter );
					$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
					$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
					$item_line_total_amount = $item_total_amount + $item_tax_amount;
					$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
							2 ) * 100 ) : 0;

					$item = array(
						'id'          => 'shipping',
						'description' => 'Shipping: ' . $order->get_shipping_method(),
						'quantity'    => 1,
						'vat_amount'  => $item_tax_amount,
						'vat'         => $item_tax_percentage,
						'amount'      => $item_line_total_amount,
						'line_id'     => $line_id
					);
					array_push( $items, $item );
				}

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token
				);

				$payload = array(
					'amount'            => $order_total_amount,
					'capture_reference' => strval( $order_id ),
					'items'             => $items
				);

				$response = wp_remote_post( $api_endpoint . '/' . $transaction_id . '/capture', array(
					'method'    => 'POST',
					'headers'   => $headers,
					'body'      => wp_json_encode( $payload ),
					'timeout'   => 90,
					'sslverify' => false
				) );

				// Retrieve the body's response if no errors found
				$response_body  = wp_remote_retrieve_body( $response );
				$response_array = json_decode( $response_body, true );

				if ( array_key_exists( 'status', $response_array ) AND
				     $response_array['status'] === 'CAPTURED' ) {

					$note = __( 'Payment captured via Dintero. Transaction ID: ' ) . $transaction_id;
					$this->payment_complete( $order, $transaction_id, $note );
				}
			}
		}
	}

	/**
	 * Notification handler.
	 */
	public function callback( $return_page = false ) {
		if ( ! empty( $_GET['transaction_id'] ) ) {
			$transaction_id = $_GET['transaction_id'];

			$transaction = $this->get_transaction( $transaction_id );
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}

			$transaction_order_id = $transaction['merchant_reference'];
			$order                = wc_get_order( $transaction_order_id );

			if ( ! empty( $order ) AND $order instanceof WC_Order ) {
				$amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
				if ( array_key_exists( 'status', $transaction ) AND
				     array_key_exists( 'amount', $transaction ) AND
				     $transaction['amount'] === $amount ) {

					if ( $transaction['status'] === 'AUTHORIZED' ) {

						$hold_reason = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
						$this->process_authorization( $order, $transaction_id, $hold_reason );
					} elseif ( $transaction['status'] === 'CAPTURED' ) {

						$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
						$this->payment_complete( $order, $transaction_id, $note );
					}
				}
			}

			if ( ! $return_page ) {
				exit;
			}
		}
	}
}
