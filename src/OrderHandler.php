<?php
/**
 * Order Handler for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

/**
 * Class to handle WooCommerce order processing
 */
class OrderHandler {
	/**
	 * Instance of this class
	 *
	 * @var OrderHandler
	 */
	private static $instance = null;

	/**
	 * Settings instance
	 *
	 * @var AdminSettings
	 */
	private $settings;

	/**
	 * Get the singleton instance of this class
	 *
	 * @return OrderHandler The instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Log a message using WordPress debug system
	 *
	 * @param string $message The message to log
	 */
	private function log($message) {
		if (defined('WP_DEBUG') && WP_DEBUG === true) {
			error_log("[Darb Assabil] " . $message);
		}
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->settings = AdminSettings::get_instance();
		add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 20, 2 );
	}

	/**
	 * Handle new WooCommerce orders
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order object.
	 */
	public function handle_new_order( $order_id, $order ) {
		$this->log("Processing new order #{$order_id}");
		
		if ( wp_doing_ajax() || get_post_meta( $order_id, '_darb_assabil_processed', true ) ) {
			$this->log("Order #{$order_id} already processed or AJAX request");
			return;
		}

		try {
			if ( ! $order instanceof \WC_Order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order ) {
				throw new \Exception( "Failed to fetch order with ID: {$order_id}" );
			}

			$order_data = $this->prepare_order_data( $order );
			$this->log("Prepared order data for #{$order_id}: " . print_r($order_data, true));
			
			$this->send_to_api( $order_data );
			$this->log("Successfully sent order #{$order_id} to API");

			update_post_meta( $order_id, '_darb_assabil_processed', true );
		} catch ( \Exception $e ) {
			$this->log('Order Error: ' . $e->getMessage());
			if ( $this->settings->get_option( 'debug_mode' ) ) {
				$this->log('Debug: ' . $e->getTraceAsString());
			}
		}
	}

	/**
	 * Prepare order data for API
	 *
	 * @param \WC_Order $order Order object.
	 * @return array    Prepared order data
	 */
	private function prepare_order_data( $order ) {
		return array(
			'order_id'       => $order->get_id(),
			'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customer_email' => $order->get_billing_email(),
			'customer_phone' => $order->get_billing_phone(),
			'total'          => $order->get_total(),
			'status'         => $order->get_status(),
			'date_created'   => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
			'billing_address' => array(
				'address_1' => $order->get_billing_address_1(),
				'address_2' => $order->get_billing_address_2(),
				'city'      => $order->get_billing_city(),
				'area'      => $order->get_meta('_billing_area'),
				'state'     => $order->get_billing_state(),
				'postcode'  => $order->get_billing_postcode(),
				'country'   => $order->get_billing_country(),
			),
			'shipping_address' => array(
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city'      => $order->get_shipping_city(),
				'area'      => $order->get_meta('_shipping_area'),
				'state'     => $order->get_shipping_state(),
				'postcode'  => $order->get_shipping_postcode(),
				'country'   => $order->get_shipping_country(),
			),
			'items' => array_map( function( $item ) {
				$product = $item->get_product();
				return array(
					'name'         => $item->get_name(),
					'quantity'     => $item->get_quantity(),
					'total'        => $item->get_total(),
					'product_id'   => $item->get_product_id(),
					'variation_id' => $item->get_variation_id(),
					'sku'          => $product ? $product->get_sku() : '',
					'price'        => $item->get_subtotal() / $item->get_quantity(),
				);
			}, $order->get_items() ),
		);
	}

	/**
	 * Send order data to external API
	 *
	 * @param array $order_data Order data to send.
	 * @throws \Exception If API request fails.
	 */
	private function send_to_api( $order_data ) {
		// Get API settings
		$options = get_option('darb_assabil_options', array());
		$api_endpoint = isset($options['api_endpoint']) ? $options['api_endpoint'] : '';
		
		$this->log("API Settings - Endpoint: {$api_endpoint}");
		
		if (empty($api_endpoint)) {
			throw new \Exception('API endpoint is not configured');
		}
		
		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
			),
			'body'        => json_encode( $order_data ),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout'     => 15,
		);

		$this->log("API request args: " . print_r($args, true));

		$response = wp_remote_post( $api_endpoint, $args );

		if ( is_wp_error( $response ) ) {
			$this->log("API Error: " . $response->get_error_message());
			throw new \Exception( 'API Error: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		$this->log("API Response Code: {$response_code}");
		$this->log("API Response Body: {$response_body}");

		if ( $response_code !== 200 ) {
			throw new \Exception( "API returned non-200 status code: {$response_code}" );
		}
	}
} 