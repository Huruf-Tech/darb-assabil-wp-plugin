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

		// Modify the phone fields' required property
		if (isset($fields['billing']['billing_phone'])) {
			$fields['billing']['billing_phone']['required'] = true;
		}
	
		if (isset($fields['shipping']['shipping_phone'])) {
			$fields['shipping']['shipping_phone']['required'] = true; 
		}

		// Modify the email field's required property
		if (isset($fields['billing']['billing_email'])) {
			$fields['billing']['billing_email']['required'] = false; // Set to false to make it optional
		}

		// Modify the email field's required property
		if (isset($fields['billing']['shipping_email'])) {
			$fields['billing']['shipping_email']['required'] = false; // Set to false to make it optional
		}

		return $fields;
	}

	/**
	 * Get a list of Libyan cities for dropdown
	 *
	 * @return array City options for select dropdown
	 */
	private function get_libyan_cities() {

		$config = include plugin_dir_path(__DIR__) . 'config.php';

		$cities = array(
			'' => __( 'Select your city', 'darb-assabil' )
		);

		$branches_url = $config['middleware_server_base_url'] . '/api/darb/assabil/order/branch/list';
		$access_token = get_option('darb_assabil_access_token', '');
		$args = array(
			'timeout' => 30,
			'sslverify' => false,
			'headers' => array(
				'Accept' => 'application/json'
			),
			'body' => wp_json_encode(
				array('token' => $access_token)
			),
		);
		$response = wp_remote_post($branches_url, $args);
		if (!is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			if (!empty($data['data'])) {
				foreach ($data['data'] as $branch) {
					$city = isset($branch['city']) ? $branch['city'] : '';
					if (!empty($city) && !empty($branch['areas']) && is_array($branch['areas'])) {
						foreach ($branch['areas'] as $area) {
							if (!empty($area)) {
								$label = $city . ' - ' . $area;
								$value = $city . '::' . $area;
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

	function update_shipping_rate() {
		if (!isset($_POST['city'])) {
			wp_send_json_error('City or area not provided');
		}
		$city = sanitize_text_field($_POST['city']);
		
		// Use a standalone logging function
		// $this->log('City: ' . $city);
	
		// Optionally, store the city in the session for later use
		WC()->session->set('selected_city', $city);

		// $this->log(print_r(WC()->cart));
	
		// Trigger WooCommerce to recalculate shipping rates

		// ShippingMethod::get_instance()->calculate_shipping();
		// WC()->cart->calculate_shipping();
		// WC()->cart->calculate_totals();
	
		wp_send_json_success('Shipping rate updated');
	}
}

add_action('wp_enqueue_scripts', function () {
    if (is_checkout()) {
        wp_enqueue_script(
            'darb-assabil-checkout',
            plugin_dir_url(__FILE__) . '../assets/js/checkout.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('darb-assabil-checkout', 'darbAssabilAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
});
add_action('wp_ajax_update_shipping_rate', [Checkout::get_instance(), 'update_shipping_rate']);
add_action('wp_ajax_nopriv_update_shipping_rate', [Checkout::get_instance(), 'update_shipping_rate']);