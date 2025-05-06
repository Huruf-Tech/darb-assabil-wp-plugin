<?php
/**
 * Shipping Method for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

/**
 * Custom shipping method for Darb Assabil
 */
class ShippingMethod extends \WC_Shipping_Method {
	/**
	 * Constructor for shipping method
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct($instance_id = 0) {
		$this->id = 'darb_assabil_shipping';
		$this->instance_id = absint($instance_id);
		$this->method_title = __('Darb Assabil Shipping', 'darb-assabil');
		$this->method_description = __('Darb Assabil shipping method with fixed rates', 'darb-assabil');
		$this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
		$this->enabled = 'yes';
		
		$this->init();
	}

	/**
	 * Initialize shipping method settings
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();
		
		$this->enabled = $this->get_option('enabled', 'yes');
		$this->title = $this->get_option('title', $this->method_title);
		$this->base_cost = $this->get_option('base_cost', 0);
		
		add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
	}

	/**
	 * Initialize form fields for the shipping method
	 */
	public function init_form_fields() {
		$this->instance_form_fields = [
			'enabled' => array(
				'title'   => __('Enable/Disable', 'darb-assabil'),
				'type'    => 'checkbox',
				'label'   => __('Enable this shipping method', 'darb-assabil'),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __('Method Title', 'darb-assabil'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'darb-assabil'),
				'default'     => __('Darb Assabil Shipping', 'darb-assabil'),
				'desc_tip'    => true,
			),
			'base_cost' => array(
				'title'       => __('Base Cost', 'darb-assabil'),
				'type'        => 'price',
				'description' => __('Base cost for shipping.', 'darb-assabil'),
				'default'     => '0',
				'desc_tip'    => true,
			),
			'use_api' => array(
				'title'   => __('Use API Rates', 'darb-assabil'),
				'type'    => 'checkbox',
				'label'   => __('Use API for real-time shipping rates', 'darb-assabil'),
				'default' => 'no',
			),
		];
	}

	/**
	 * Calculate shipping cost
	 *
	 * @param array $package Package information.
	 */
	public function calculate_shipping($package = []) {
		$use_api = $this->get_option('use_api', 'no') === 'yes';
		$service_id = get_option('darb_assabil_service_id', '');
		$this->log('Using API for shipping rates. Service ID: ' . $service_id . ' | Use API: ' . ($use_api ? 'Yes' : 'No'));
	
		$rate = [
			'id' => $this->get_rate_id(),
			'label' => $this->title,
			'cost' => $this->base_cost,
			'calc_tax' => 'per_order'
		];

		if ($use_api ) {
			$this->log('Using API for shipping rates. Service ID: ' . $service_id);

			// Prepare payload for the API request
			$payload = [
				'service' => $service_id,
				'products' => [
					[
						'quantity' => 1,
						'widthCM' => 40,
						'heightCM' => 40,
						'lengthCM' => 50,
						'allowInspection' => false,
						'allowTesting' => true,
						'isFragile' => false,
						'amount' => 0,
						'currency' => 'lyd',
						'isChargeable' => true
					]
				],
				'paymentBy' => 'receiver',
				'to' => [
					'countryCode' => 'lby',
					'city' => 'رأس لانوف',
					'area' => 'بن جواد',
					'address' => 'بن جواد',
					'location' => [
						'lat' => 1,
						'long' => 1
					]
				]
			];

			// Call the external API
			// $response = $this->call_shipping_api($payload);
			$rate['cost'] = 89;
			$rate['label'] = $response['label'] ?? $this->title;
			// if (!is_wp_error($response) && isset($response['cost'])) {
			// 	$rate['cost'] = $response['cost'];
			// 	$rate['label'] = $response['label'] ?? $this->title;
			// } else {
			// 	$this->log('Darb Assabil API Error: ' . (is_wp_error($response) ? $response->get_error_message() : 'Invalid response'));
			// }
		}

		// Add the calculated rate to WooCommerce
		$this->add_rate($rate);
	}

	private function call_shipping_api($payload) {
		$api_url = 'https://v2-staging.sabil.ly/api/local/shipments/calculate/shipping';
		$bearer_token = get_option('darb_assabil_bearer_token', '');

		$args = [
			'method' => 'POST',
			'timeout' => 30,
			'sslverify' => false,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $bearer_token
			],
			'body' => wp_json_encode($payload)
		];

		$this->log('Calling API: ' . $api_url);
		$this->log('Payload: ' . wp_json_encode($payload));

		$response = wp_remote_post($api_url, $args);

		if (is_wp_error($response)) {
			$this->log('API Request Error: ' . $response->get_error_message());
			return $response;
		}

		$body = wp_remote_retrieve_body($response);
		$this->log('API Response: ' . $body);

		$data = json_decode($body, true);

		return $data ?? [];
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