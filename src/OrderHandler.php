<?php
/**
 * Order Handler for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

use DarbAssabil\get_config;
use DarbAssabil\extract_city_and_area;

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
	public function handle_new_order($order_id, $order) {
		try {
			if (!$order instanceof \WC_Order) {
				$order = wc_get_order($order_id);
			}

			if (!$order) {
				throw new \Exception("Failed to fetch order with ID: {$order_id}");
			}

			// Early check for Libya orders
			$shipping_country = $order->get_shipping_country();
			if ($shipping_country !== 'LY') {
				return;
			}

			// Check if order was already processed
			$processed = get_post_meta($order_id, '_darb_assabil_processed', true);
			if ($processed) {
				return;
			}
			$order_data = $this->prepare_order_data($order);
			$this->create_order($order_data);

			// Save processed status
			update_post_meta($order_id, '_darb_assabil_processed', 'yes');

			// Save additional metadata for tracking
			$order->update_meta_data('_darb_assabil_processed_date', current_time('mysql'));
			$order->update_meta_data('_darb_assabil_processed_by', get_current_user_id());
			$order->save();

		} catch (\Exception $e) {
			// Save error state
			update_post_meta($order_id, '_darb_assabil_error', $e->getMessage());
			update_post_meta($order_id, '_darb_assabil_error_date', current_time('mysql'));
		}
	}

	/**
	 * Prepare order data for API
	 *
	 * @param \WC_Order $order Order object.
	 * @return array    Prepared order data
	 */
	private function prepare_order_data( $order ) {
		$include_product_payment = get_plugin_option()['include_product_payment'];

		$city_area = extract_city_and_area($order->get_billing_city());

		return array(
			'order' => array(
				'service'       => get_plugin_option()['service'],
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
				'paymentBy'    => get_plugin_option()['payment_done_by_receiver'] === true ? 'receiver' : 'sender',
				'to' => array(
					'countryCode'   => 'lby',
					'city'      => $city_area['city'],
					'area'      => $city_area['area'],
					'address'  => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
				),
				'metadata' => array(
					'order_id' => (string) $order->get_id(),
					'customer_id' => (string) $order->get_customer_id(),
				),
			),
			'token' => get_access_token(),
		);
	}

	/**
	 * Send order data to external API
	 *
	 * @param array $order_data Order data to send.
	 * @throws \Exception If API request fails.
	 */
	public function create_order( $order_data ) {
		$api_url = get_config('server_base_url') . '/api/darb/assabil/order/create';
		
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

		$response = wp_remote_post($api_url, $args );
		if ( is_wp_error( $response ) ) {
			// translators: %s: API error message
            throw new \Exception( sprintf( esc_html__( 'API Error: %s', 'darb-assabil' ), esc_html( $response->get_error_message() ) ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode($response_body, true);

		// Store the API response status
		$order_id = $order_data['order']['metadata']['order_id'];
		$order = wc_get_order($order_id);
		
		if ($response_data && isset($response_data['status'])) {
			$order->update_meta_data('darb_assabil_api_payload', $order_data);
			$order->update_meta_data('darb_assabil_api_status', $response_data['status'] ? 'success' : 'failed');
			if (isset($response_data['data']['reference'])) {
				$order->update_meta_data('darb_assabil_tracking_number', $response_data['data']['reference']);
			}
			if (isset($response_data['message'])) {
				$order->update_meta_data('darb_assabil_api_message', $response_data['message']);
			}
			$order->save();
		}

		$order->update_meta_data('darb_assabil_api_response', $response_body);
		$order->save();

		if ($response_code !== 200 ) {
			// translators: %d: HTTP response code
            throw new \Exception( sprintf( esc_html__( 'Order creation failed with status code: %d', 'darb-assabil' ), intval( $response_code ) ) );
		}
	}
}