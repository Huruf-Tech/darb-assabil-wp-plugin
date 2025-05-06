<?php
/**
 * Checkout handler for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

/**
 * Checkout class for customizing WooCommerce checkout fields
 */
class Checkout {

	/**
	 * Instance of this class
	 *
	 * @var Checkout
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
	 * @return Checkout The instance
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
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 20 );
	}

	/**
	 * Customize the checkout fields
	 *
	 * @param array $fields WooCommerce checkout fields.
	 * @return array Modified checkout fields
	 */
	public function customize_checkout_fields( $fields ) {
		// Get country from billing and shipping fields
		$billing_country = isset($fields['billing']['billing_country']['default']) 
			? $fields['billing']['billing_country']['default'] 
			: '';
			
		$shipping_country = isset($fields['shipping']['shipping_country']['default']) 
			? $fields['shipping']['shipping_country']['default'] 
			: '';
		
		// Get country from session or POST data if not in fields
		if (empty($billing_country) && function_exists('WC')) {
			$customer = WC()->customer;
			if ($customer) {
				$billing_country = $customer->get_billing_country();
				$shipping_country = $customer->get_shipping_country();
			}
		}
		
		// Try to get country from POST data during checkout
		if (empty($billing_country) && isset($_POST['billing_country'])) {
			$billing_country = sanitize_text_field($_POST['billing_country']);
		}
		
		if (empty($shipping_country) && isset($_POST['shipping_country'])) {
			$shipping_country = sanitize_text_field($_POST['shipping_country']);
		}

		// Only modify cities if either country is Libya
		if ($billing_country !== 'LY' && $shipping_country !== 'LY') {
			return $fields;
		}
		
		// Check if we should use dropdown
		if ( ! $this->settings->get_option( 'use_city_dropdown', true ) ) {
			return $fields;
		}
		
		$libyan_cities = $this->get_libyan_cities();

		// Force override shipping city if shipping country is Libya
		if ( isset( $fields['shipping']['shipping_city'] ) && ($shipping_country === 'LY' || empty($shipping_country) && $billing_country === 'LY')) {
			$fields['shipping']['shipping_city']['type'] = 'select';
			$fields['shipping']['shipping_city']['options'] = $libyan_cities;
		}
		
		// Force override billing city if billing country is Libya
		if ( isset( $fields['billing']['billing_city'] ) && $billing_country === 'LY') {
			$fields['billing']['billing_city']['type'] = 'select';
			$fields['billing']['billing_city']['options'] = $libyan_cities;
		}

		return $fields;
	}

	/**
	 * Get a list of Libyan cities for dropdown
	 *
	 * @return array City options for select dropdown
	 */
	private function get_libyan_cities() {
		$cities = array(
			'' => __( 'Select your city', 'darb-assabil' )
		);

		$branches_url = 'https://v2-staging.sabil.ly/api/local/branches/public';
		$bearer_token = get_option('darb_assabil_bearer_token', 'eyJhbGciOiJIUzI1NiJ9.eyJ2ZXJzaW9uIjoxLCJzZXNzaW9uSWQiOiI2ODBhMGEwMjY4N2I2ZThmNGZhNjRlYjEiLCJyZWZyZXNoYWJsZSI6ZmFsc2UsInVubGltaXRlZCI6ZmFsc2UsInN1YiI6Im9hdXRoX2FjY2Vzc190b2tlbiIsImlzcyI6IkRhcmIgQXNzYWJpbCIsImF1ZCI6IkRhcmIgQXNzYWJpbCIsImlhdCI6MTc0NTQ4ODM4NiwiZXhwIjoxNzUyNzQ1OTg2Ljg0fQ.HMBmtiEYInuM9SqfhZJUxtbMHDlKYbFEfbE1vofwtnc');
		$args = array(
			'timeout' => 30,
			'sslverify' => false,
			'headers' => array(
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . $bearer_token
			)
		);
		$response = wp_remote_get($branches_url, $args);
		if (!is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			if (!empty($data['data']['results'])) {
				foreach ($data['data']['results'] as $branch) {
					$city = isset($branch['city']) ? $branch['city'] : '';
					if (!empty($city) && !empty($branch['areas']) && is_array($branch['areas'])) {
						foreach ($branch['areas'] as $area) {
							if (!empty($area['area'])) {
								$label = $city . ' - ' . $area['area'];
								$value = $area['area'];
								$cities[$value] = $label;
							}
						}
					}
				}
			}
		}
		asort($cities);
		return $cities;
	}
}

