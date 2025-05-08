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
		
		// if ( wp_doing_ajax() || get_post_meta( $order_id, '_darb_assabil_processed', true ) ) {
		// 	$this->log("Order #{$order_id} already processed or AJAX request");
		// 	return;
		// }

		try {
			if ( ! $order instanceof \WC_Order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order ) {
				throw new \Exception( "Failed to fetch order with ID: {$order_id}" );
			}

			$order_data = $this->prepare_order_data( $order );
			$this->log("Prepared order data for #{$order_id}: " . print_r($order_data, true));
			
			$this->create_order( $order_data );
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
		$service_id = get_option('darb_assabil_service_id', '');
		$value = get_option('darb_assabil_payment_done_by_receiver', '');
		$payment_by_receiver = $value === true ? 'receiver' : 'sender';
		$include_product_payment = get_option('darb_assabil_include_product_payment', '');
		$access_token = get_option('darb_assabil_access_token', '');

		$this->log("Service ID: {$service_id}");
		$this->log("Payment by receiver: {$payment_by_receiver}");
		$this->log("Include product payment: {$include_product_payment}");
		$this->log("Access token: {$access_token}");

		$city_full = $order->get_billing_city() ?? '';
			$city_parts = explode('::', $city_full);
			$city = $city_parts[0] ?? ''; // Extract the city
			$area = $city_parts[1] ?? ''; // Extract the area

			$this->log('City: ' . $city);
			$this->log('Area: ' . $area);

		return array(
			'order' => array(
				'service'       => $service_id,
				'notes'         => $order->get_customer_note(),
				'contacts' => array(
					array(
						'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'phone'  => $order->get_billing_phone(),
					),
				),
				'products' => (function() use ($order, $include_product_payment) {
					$products = [];
					foreach ( $order->get_items() as $item ) {
						$product = $item->get_product();
						$products[] = array(
							'sku'          => $product ? $product->get_sku() : '',
							'title'        => $item->get_name(),
							'quantity'     => $item->get_quantity(),
							'widthCM'      => intval( $product ? $product->get_width() : 0 ),
							'heightCM'     => intval( $product ? $product->get_height() : 0 ),
							'lengthCM'     => intval( $product ? $product->get_length() : 0 ),
							'amount'       => $include_product_payment ? floatval( $item->get_total() ) : 0,
							'currency'     => strtolower(get_woocommerce_currency()),
							'isChargeable' => $include_product_payment ? true : false,
						);
					}
					return $products;
				})(),
				// 'allowSplitting' => false,
				'paymentBy'    => $payment_by_receiver,
				'to' => array(
					'countryCode'   => 'lby',
					'city'      => $city,
					'area'      => $area,
					'address'  => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
				),
				// 'isPickup' => false,
				// 'allowCardPayment' => false,
				// 'cardFeePaymentBy' => 'receiver',
				// 'suggestedDeliveryTime' => array(
				// 	'fromHour' => '08:00',
				// 	'fromMinute' => '00',
				// 	'toHour' => '17:00',
				// 	'toMinute' => '00',
				// ),
				// 'tags' => array(
				// ),
				'metadata' => array(
					'order_id' => (string) $order->get_id(),
					'customer_id' => (string) $order->get_customer_id(),
				),
			),
			'token' => $access_token,
		);
	}

	/**
	 * Send order data to external API
	 *
	 * @param array $order_data Order data to send.
	 * @throws \Exception If API request fails.
	 */
	private function create_order( $order_data ) {

		$config = include plugin_dir_path(__FILE__) . '../config.php';
		$api_url = $config['middleware_server_base_url'] . '/api/darb/assabil/order/create';

		// // Get API settings
		// $options = get_option('darb_assabil_options', array());
		// $api_endpoint = isset($options['api_endpoint']) ? $options['api_endpoint'] : '';
		
		$this->log("API Settings - Endpoint: {$api_url}");
		
		if (empty($api_url)) {
			throw new \Exception('API endpoint is not configured');
		}
		
		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $order_data ),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout'     => 15,
		);

		$this->log("API request args: " . print_r($args, true));

		$response = wp_remote_post($api_url, $args );

		$this->log("API Response: " . print_r($response, true));

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

	/**
	 * Log messages for debugging
	 *
	 * @param string $message The message to log.
	 */
	private function log($message) {
		$log_file = plugin_dir_path(__FILE__) . '../debug-plugin.log'; // Path to the debug-plugin.log file
		$timestamp = date('Y-m-d H:i:s'); // Add a timestamp to each log entry
		$formatted_message = "[{$timestamp}] {$message}" . PHP_EOL;

		// Write the log message to the file
		file_put_contents($log_file, $formatted_message, FILE_APPEND);
	}
} 