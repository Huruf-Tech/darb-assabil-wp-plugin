<?php
/**
 * Shipping Method for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;
use DarbAssabil\get_config;
use DarbAssabil\extract_city_and_area;

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

		// Extract product details from the package
		$products = [];
		foreach ($package['contents'] as $item) {
			$products[] = [
				'sku' => $item['data']->get_sku() ?? '',
				'title' => $item['data']->get_name() ?? '',
				'quantity' => $item['quantity'],
				'widthCM' => intval($item['data']->get_width()),
				'heightCM' => intval($item['data']->get_height()),
				'lengthCM' => intval($item['data']->get_length()),
				'amount' => floatval(0),
				'currency' => strtolower(get_woocommerce_currency()),
				'isChargeable' => get_plugin_option()['include_product_payment'] ? true : false,
			];
		}

		$rate = [
			'id' => $this->get_rate_id(),
			'label' => $this->title,
			'cost' => $this->base_cost,
			'calc_tax' => 'per_order'
		];

		if ($use_api) {
			$city_area = extract_city_and_area($package['destination']['city']);
		// Prepare payload for the API request
		$payload = [
			'service' => get_plugin_option()['service'],
			'products' => $products,

			'paymentBy' => get_plugin_option()['payment_done_by_receiver'] === true ? 'receiver' : 'sender',
			'to' => [
				'countryCode' => 'lby',
				'city' => $city_area['city'],
				'area' => $city_area['area'],
				'address' => $package['destination']['address'] ?? '',
			],
			'isPickup' => true,
			'token' => get_access_token(),
		];

			// Call the external API
			$response = $this->call_shipping_api($payload);
			if (!is_wp_error($response) && isset($response['data']['amount'])) {
				$rate['cost'] = $response['data']['amount'];
				$rate['label'] = $response['label'] ?? $this->title;
			}
		}

		// Add the calculated rate to WooCommerce
		$this->add_rate($rate);
	}

	private function call_shipping_api($payload) {
		$api_url = get_config('server_base_url') . '/api/darb/assabil/order/cost';

		$args = [
			'method' => 'POST',
			'timeout' => 30,
			'sslverify' => false,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'body' => wp_json_encode($payload)
		];

		$response = wp_remote_post($api_url, $args);
		if (is_wp_error($response)) {
			return $response;
		}

		$body = wp_remote_retrieve_body($response);

		$data = json_decode($body, true);

		return $data ?? [];
	}
}
