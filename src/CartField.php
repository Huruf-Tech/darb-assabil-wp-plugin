<?php
/**
 * Cart Field customization for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

/**
 * Class to handle cart field customization and scripts
 */
class CartField {
    /**
     * Instance of this class
     *
     * @var CartField
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class
     *
     * @return CartField The instance
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
        // Enqueue scripts for cart page
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on cart page
        if ( is_cart() || is_checkout() ) {
            // Enqueue jQuery (although it's typically already included in WordPress)
            wp_enqueue_script( 'jquery' );
            
            // Enqueue our custom script with jQuery dependency
            wp_enqueue_script(
                'darb-assabil-cart',
                plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/cart.js',
                array( 'jquery' ),
                '1.0.0',
                true
            );
        }
    }
}
