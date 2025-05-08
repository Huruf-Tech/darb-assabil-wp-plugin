<?php
/**
 * Darb Assabil Plugin
 *
 * @package   Darb_Assabil
 * @author    Huruf Tech
 * @copyright Huruf Tech
 * @license   GPL v2 or later
 * @link      https://huruftech.com
 *
 * Plugin Name:     Darb Assabil Plugin
 * Plugin URI:      https://sabil.ly/
 * Description:     A WordPress plugin for Darb Assabil
 * Version:         1.0.0
 * Author:          Huruf Tech
 * Author URI:      https://huruftech.com
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     darb-assabil
 * Domain Path:     /languages
 * Requires PHP:    7.1
 * Requires WP:     5.5.0
 * Requires Plugins: woocommerce
 * Namespace:       DarbAssabil
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce is active
 * 
 * @return bool True if WooCommerce is active, false otherwise
 */
function darb_assabil_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			?>
			<div class="error">
				<p>
					<?php 
					printf(
						esc_html__( 'Darb Assabil requires WooCommerce to be installed and active. You can download %s here.', 'darb-assabil' ),
						'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
					);
					?>
				</p>
			</div>
			<?php
		} );
		return false;
	}
	return true;
}

/**
 * Autoloader for Darb Assabil plugin classes
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'DarbAssabil\\';
	$base_dir = __DIR__ . '/src/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Initialize the plugin when WordPress is loaded
 */
add_action( 'plugins_loaded', function() {
	if ( darb_assabil_check_woocommerce() ) {
		DarbAssabil\Checkout::get_instance();
		DarbAssabil\OrderHandler::get_instance();
		DarbAssabil\AdminSettings::get_instance();
		DarbAssabil\CartField::get_instance();

		// Register shipping method
		add_action( 'woocommerce_shipping_init', function() {
			require_once __DIR__ . '/src/ShippingMethod.php';
		} );

		// Add shipping method to WooCommerce
		add_filter( 'woocommerce_shipping_methods', function( $methods ) {
			$methods['darb_assabil_shipping'] = 'DarbAssabil\\ShippingMethod';
			return $methods;
		});
	}
} );

/**
 * Save default values in the database on plugin activation
 */
function darb_assabil_activate_plugin() {
    // Set default values for the options
    if (empty(get_option('darb_assabil_use_city_dropdown'))) {
        update_option('darb_assabil_use_city_dropdown', true);
    }

    if (empty(get_option('darb_assabil_payment_done_by_receiver'))) {
        update_option('darb_assabil_payment_done_by_receiver', true);
    }

    if (empty(get_option('darb_assabil_include_product_payment'))) {
        update_option('darb_assabil_include_product_payment', true);
    }
}
register_activation_hook(__FILE__, 'darb_assabil_activate_plugin');