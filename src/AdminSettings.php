<?php
/**
 * Admin Settings for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

use DarbAssabil\get_config;
use Exception;

/**
 * Class to handle admin settings for the plugin
 */
class AdminSettings {
	/**
	 * Instance of this class
	 *
	 * @var AdminSettings
	 */
	private static $instance = null;

	/**
	 * Option group name for settings
	 *
	 * @var string
	 */
	private $option_group = 'darb_assabil_settings';

	/**
	 * Option name for settings
	 *
	 * @var string
	 */
	private $option_name = 'darb_assabil_options';

	/**
	 * Get the singleton instance of this class
	 *
	 * @return AdminSettings The instance
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action('admin_init', array($this, 'check_and_save_token')); // Hook to check and save the token
		add_action('init', array($this, 'register_webhook_endpoint'));
		add_action('parse_request', array($this, 'handle_webhook_request'));
		add_action('wp_ajax_retry_darb_assabil_order', array($this, 'handle_retry_order'));
		add_action('wp_ajax_save_darb_assabil_payload', array($this, 'handle_save_payload'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

		add_action('admin_head', array($this, 'custom_admin_footer_css'));
		add_filter('admin_footer_text', array($this, 'custom_admin_footer_text'), 20);
		add_filter('update_footer', array($this, 'custom_admin_footer_version'), 20);
	}

	/**
	 * Add admin menu for plugin settings
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Darb Assabil Settings', 'darb-assabil' ),
			__( 'Darb Assabil', 'darb-assabil' ),
			'manage_woocommerce',
			'darb-assabil-settings',
			array( $this, 'render_settings_page' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( plugin_dir_path( __FILE__ ) . '../assets/img/DarbAssabil-Logo.svg' ) ),
		);
	}

	/**
	 * Register settings for the plugin
	 */
	public function register_settings() {
	    // Register individual options with sanitization
	    register_setting('darb_assabil_options', 'darb_assabil_service_id', [
	        'type' => 'string',
	        'sanitize_callback' => 'sanitize_text_field',
	        'default' => ''
	    ]);
	    register_setting('darb_assabil_options', 'darb_assabil_use_city_dropdown', [
	        'type' => 'boolean',
	        'sanitize_callback' => 'rest_sanitize_boolean',
	        'default' => true,
	    ]);
	    register_setting('darb_assabil_options', 'darb_assabil_payment_done_by_receiver', [
	        'type' => 'boolean',
	        'sanitize_callback' => 'rest_sanitize_boolean',
	        'default' => true,
	    ]);
	    register_setting('darb_assabil_options', 'darb_assabil_include_product_payment', [
	        'type' => 'boolean',
	        'sanitize_callback' => 'rest_sanitize_boolean',
	        'default' => true,
	    ]);
	    register_setting('darb_assabil_options', 'darb_assabil_webhook_secret', [
	        'type' => 'string',
	        'sanitize_callback' => 'sanitize_text_field',
	        'default' => wp_generate_password(32, false),
	    ]);

	    add_settings_section(
	        'darb_assabil_general',
	        __( 'General Settings', 'darb-assabil' ),
	        array( $this, 'render_section' ),
	        'darb-assabil-settings'
	    );

	    // add_settings_field(
	    //     'debug_mode',
	    //     __( 'Debug Mode', 'darb-assabil' ),
	    //     array( $this, 'render_debug_mode_field' ),
	    //     'darb-assabil-settings',
	    //     'darb_assabil_general'
	    // );

	    add_settings_field(
	        'use_city_dropdown',
	        __( 'City Field Type', 'darb-assabil' ),
	        array( $this, 'render_city_dropdown_field' ),
	        'darb-assabil-settings',
	        'darb_assabil_general'
	    );

	    add_settings_field(
	        'payment_done_by_receiver',
	        __( 'Payment Done by Receiver', 'darb-assabil' ),
	        array( $this, 'render_payment_done_by_receiver_field' ),
	        'darb-assabil-settings',
	        'darb_assabil_general'
	    );

	    add_settings_field(
	        'include_product_payment',
	        __( 'Include Product Payment', 'darb-assabil' ),
	        array( $this, 'render_include_product_payment_field' ),
	        'darb-assabil-settings',
	        'darb_assabil_general'
	    );

	    // add_settings_section(
	    //     'darb_assabil_api_section',
	    //     __('API Settings', 'darb-assabil'),
	    //     array($this, 'api_section_callback'),
	    //     'darb-assabil-settings'
	    // );

	    // add_settings_field(
	    //     'darb_assabil_bearer_token',
	    //     __('Bearer Token', 'darb-assabil'),
	    //     array($this, 'bearer_token_callback'),
	    //     'darb-assabil-settings',
	    //     'darb_assabil_api_section'
	    // );

	    add_settings_field(
	        'darb_assabil_service_id',
	        __('Default Service', 'darb-assabil'),
	        array($this, 'service_dropdown_callback'),
	        'darb-assabil-settings',
	        'darb_assabil_general'
	    );

	    // Register webhook related options without adding fields
	    register_setting('darb_assabil_options', 'darb_assabil_webhook_secret', array(
	        'type' => 'string',
	        'sanitize_callback' => 'sanitize_text_field',
	        'default' => wp_generate_password(32, false),
	    ));
	}

	/**
	 * Render the settings page with tabs
	 */
	public function render_settings_page() {
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'orders';

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=darb-assabil-settings&amp;tab=orders" class="nav-tab <?php echo esc_attr($current_tab === 'orders' ? 'nav-tab-active' : ''); ?>">
                <?php esc_html_e('Orders', 'darb-assabil'); ?>
            </a>
            <a href="?page=darb-assabil-settings&amp;tab=settings" class="nav-tab <?php echo esc_attr($current_tab === 'settings' ? 'nav-tab-active' : ''); ?>">
                <?php esc_html_e('Settings', 'darb-assabil'); ?>
            </a>
            <a href="?page=darb-assabil-settings&amp;tab=integration" class="nav-tab <?php echo esc_attr($current_tab === 'integration' ? 'nav-tab-active' : ''); ?>">
                <?php esc_html_e('Integration', 'darb-assabil'); ?>
            </a>
            <a href="?page=darb-assabil-settings&amp;tab=webhooks" class="nav-tab <?php echo esc_attr($current_tab === 'webhooks' ? 'nav-tab-active' : ''); ?>">
                <?php esc_html_e('Webhooks', 'darb-assabil'); ?>
            </a>
            <a href="?page=darb-assabil-settings&amp;tab=shortcode" class="nav-tab <?php echo esc_attr($current_tab === 'shortcode' ? 'nav-tab-active' : ''); ?>">
                <?php esc_html_e('Short Code', 'darb-assabil'); ?>
            </a>
        </h2>
        <!-- Tab Content -->
        <?php if ($current_tab === 'webhooks'): ?>
            <?php $this->render_webhook_tab(); ?>
        <?php elseif ($current_tab === 'orders'): ?>
            <?php $this->render_orders_tab(); ?>
        <?php elseif ($current_tab === 'integration'): ?>
            <?php $this->render_integrate_tab(); ?>
        <?php elseif ($current_tab === 'settings'): ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('darb_assabil_options');
                do_settings_sections('darb-assabil-settings');
                submit_button();
                ?>
            </form>
        <?php elseif ($current_tab === 'shortcode'): ?>
            <?php $this->render_shortcode_tab(); ?>
        <?php endif; ?>
    </div>
    <?php
	}

	/**
	 * Render the Orders tab content
	 */
	private function render_orders_tab() {
	    // Get all orders with Darb Assabil metadata
		$args = array(
			'post_type' => 'shop_order',
			'posts_per_page' => -1,
			'post_status' => 'any',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_darb_assabil_processed',
					'compare' => 'EXISTS',
				),
				array(
					'key' => 'darb_assabil_api_status',
					'compare' => 'EXISTS',
				)
			),
		);

	    $orders = wc_get_orders($args);
	    ?>
	    <div class="darb-assabil-admin wrap">
	        <h2><?php esc_html_e('Darb Assabil Orders', 'darb-assabil'); ?></h2>
	        
	        <div class="tablenav top">
	            <div class="alignleft actions bulkactions">
	                <select name="bulk-action">
	                    <option value="-1"><?php esc_html_e('Bulk Actions', 'darb-assabil'); ?></option>
	                    <option value="retry"><?php esc_html_e('Retry Selected', 'darb-assabil'); ?></option>
	                </select>
	                <input type="submit" class="button action" id="doaction" value="<?php esc_html_e('Apply', 'darb-assabil'); ?>">
	            </div>
	        </div>

	        <table class="widefat fixed striped">
	            <thead>
	                <tr>
	                    <td class="manage-column column-cb check-column">
	                        <input type="checkbox" id="cb-select-all-1">
	                    </td>
	                    <th><?php esc_html_e('Order ID', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('Date', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('Status', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('Darb Status', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('Reference Number', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('API Payload', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('API Response', 'darb-assabil'); ?></th>
	                    <th><?php esc_html_e('Actions', 'darb-assabil'); ?></th>
	                </tr>
	            </thead>
	            <tbody>
	                <?php if (empty($orders)) : ?>
	                    <tr>
	                        <td colspan="10"><?php esc_html_e('No orders found.', 'darb-assabil'); ?></td>
	                    </tr>
	                <?php else : ?>
	                    <?php foreach ($orders as $order) : 
						    $darb_status = $order->get_meta('darb_assabil_api_status');
						    $show_checkbox = ($darb_status !== 'success');
						?>
	                        <tr data-order-id="<?php echo esc_attr($order->get_id()); ?>">
	                            <th scope="row" class="check-column">
	                                <?php if ($show_checkbox) : ?>
	                                    <input type="checkbox" name="order[]" value="<?php echo esc_attr($order->get_id()); ?>">
	                                <?php endif; ?>
	                            </th>
	                            <td>
	                                <a href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">
	                                    #<?php echo esc_html($order->get_order_number()); ?>
	                                </a>
	                            </td>
	                            <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></td>
	                            <td>
	                                <span class="darb-order-status darb-status-<?php echo esc_attr($order->get_status()); ?>">
	                                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
	                                </span>
	                            </td>
	                            <td>
	                                <?php
	                                $darb_status = $order->get_meta('darb_assabil_api_status');
	                                $status_class = $darb_status === 'success' ? 'success' : ($darb_status === 'failed' ? 'failed' : 'unknown');
	                                ?>
	                                <span class="darb-status <?php echo esc_attr($status_class); ?>">
	                                    <?php echo $darb_status ? esc_html($darb_status) : esc_html__('Not Processed', 'darb-assabil'); ?>
	                                </span>
	                            </td>
	                            <td>
	                                <?php
	                                $tracking_number = $order->get_meta('darb_assabil_tracking_number');
	                                echo $tracking_number ? esc_html($tracking_number) : '-';
	                                ?>
	                            </td>
	                            <td>
									<?php
									$payload = $order->get_meta('darb_assabil_api_payload');
									if ($payload) {
										// Ensure proper JSON encoding
										$json_payload = is_array($payload) ? json_encode($payload) : $payload;
										?>
										<button class="button view-data" data-type="payload" data-content="<?php echo esc_attr($json_payload); ?>">
											<?php esc_html_e('View Payload', 'darb-assabil'); ?>
										</button>
									<?php } ?>
								</td>
								<td>
									<?php
									$response = $order->get_meta('darb_assabil_api_response');
									if ($response) {
										// Ensure proper JSON encoding
										$json_response = is_string($response) ? $response : json_encode($response);
										?>
										<button class="button view-data" data-type="response" data-content="<?php echo esc_attr($json_response); ?>">
											<?php esc_html_e('View Response', 'darb-assabil'); ?>
										</button>
									<?php } ?>
								</td>
	                            <td>
	                                <?php if ($darb_status === 'failed' || !$darb_status) : ?>
	                                    <button type="button" class="button retry-order" 
	                                            data-order-id="<?php echo esc_attr($order->get_id()); ?>"
	                                            data-nonce="<?php echo esc_attr(wp_create_nonce('retry-darb-assabil-order')); ?>">
	                                        <?php esc_html_e('Retry', 'darb-assabil'); ?>
	                                    </button>
	                                <?php endif; ?>
	                            </td>
	                        </tr>
	                    <?php endforeach; ?>
	                <?php endif; ?>
	            </tbody>
	        </table>
	    </div>
	    <!-- Add this modal structure at the end -->
	    <div id="json-modal" class="darb-modal" style="display:none;">
	        <div class="darb-modal-content">
	            <div class="darb-modal-header">
	                <span class="darb-close">&times;</span>
	                <h3 class="darb-modal-title">API Data</h3>
	            </div>
	            <div class="darb-modal-body">
	                <textarea id="json-content" class="darb-json-editor"></textarea>
	            </div>
	            <div class="darb-modal-footer">
	                <button type="button" class="button save-json">
	                    <?php esc_html_e('Save Changes', 'darb-assabil'); ?>
	                </button>
	            </div>
	        </div>
	    </div>
	    <!-- Add this after your existing modal -->
	    <div class="darb-loader-overlay">
	        <div class="darb-loader">
	            <div class="darb-loader-spinner"></div>
	            <div class="darb-loader-text">Processing orders...</div>
	            <div class="darb-loader-progress"></div>
	        </div>
	    </div>
	    <?php
	}

	/**
	 * Render the content for the "Integrate" tab
	 */
	public function render_integrate_tab() {
	    // Include the configuration file
	    $config = include plugin_dir_path(__DIR__) . 'config.php';

	    // Retrieve the saved token
	    $access_token = get_access_token();

	    // If the token exists, skip the API call and show the logout button
	    if (!empty($access_token)) {
	        ?>
	        <h2><?php esc_html_e('Integration Settings', 'darb-assabil'); ?></h2>
	        <p><?php esc_html_e('You are already logged in to Darb Assabil.', 'darb-assabil'); ?></p>

	        <form method="post">
	            <input type="hidden" name="darb_assabil_logout" value="1">
	            <?php submit_button( esc_html__('Logout from Darb Assabil', 'darb-assabil'), 'primary' ); ?>
	        </form>

	        <?php
	        return;
	    }

	    // API endpoint
	    $api_url = get_config('server_base_url') . '/api/darb/assabil/auth/login';

	    // Initialize variables
	    $login_url = '';
	    $error_message = '';

	    // Call the API
	    $args = array(
	        'method' => 'POST',
	        'timeout' => 30,
	        'body' => wp_json_encode(array(
	            'origin' => home_url(add_query_arg(array('page' => 'darb-assabil-settings', 'tab' => 'integration'), 'wp-admin/admin.php')),
	        )),
	    );
	    $response = wp_remote_post($api_url, $args);

	    if (is_wp_error($response)) {
	        $error_message = __('Failed to connect to the API. Please try again later.', 'darb-assabil');
	    } else {
	        $body = wp_remote_retrieve_body($response);
	        $data = json_decode($body, true);

	        if (!empty($data['status']) && $data['status'] === true && !empty($data['data']['url'])) {
	            $login_url = esc_url($data['data']['url']);
	        } else {
	            $error_message = __('Invalid API response. Please contact support.', 'darb-assabil');
	        }
	    }

	    ?>
	    <h2><?php esc_html_e('Integration Settings', 'darb-assabil'); ?></h2>
	    <p><?php esc_html_e('To integrate with Darb Assabil, please log in using the button below.', 'darb-assabil'); ?></p>

	    <?php if (!empty($error_message)) : ?>
	        <div class="notice notice-error">
	            <p><?php echo esc_html($error_message); ?></p>
	        </div>
	    <?php endif; ?>

	    <?php if (!empty($login_url)) : ?>
	        <a href="<?php echo esc_url($login_url); ?>" class="button button-primary">
	            <?php esc_html_e('Login in Darb Assabil', 'darb-assabil'); ?>
	        </a>
	    <?php endif; ?>
	    <?php
	}

	/**
	 * Render the settings section
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Configure your Darb Assabil settings below.', 'darb-assabil' ) . '</p>';
	}

	/**
	 * Render the debug mode field
	 */
	public function render_debug_mode_field() {
	    $value = get_option('darb_assabil_debug_mode', false);
	    ?>
	    <label>
	        <input type="checkbox" 
	               name="darb_assabil_debug_mode" 
	               value="1" 
	               <?php checked($value, true); ?>>
	        <?php esc_html_e('Enable debug logging', 'darb-assabil'); ?>
	    </label>
	    <p class="description"><?php esc_html_e('When enabled, debug information will be logged to the WordPress debug log.', 'darb-assabil'); ?></p>
	    <?php
	}

	/**
	 * Render the city dropdown toggle field
	 */
	public function render_city_dropdown_field() {
	    $value = get_option('darb_assabil_use_city_dropdown', true);
	    ?>
	    <label>
	        <input type="checkbox" 
	               name="darb_assabil_use_city_dropdown" 
	               value="1" 
	               <?php checked($value, true); ?>>
	        <?php esc_html_e('Use dropdown for city fields', 'darb-assabil'); ?>
	    </label>
	    <p class="description"><?php esc_html_e('When enabled, city fields will use a dropdown with Libyan cities provided by Darb Assabil. When disabled, standard text input will be used.', 'darb-assabil'); ?></p>
	    <?php
	}

	/**
	 * Render the "Payment Done by Receiver" field
	 */
	public function render_payment_done_by_receiver_field() {
	    $value = get_option('darb_assabil_payment_done_by_receiver', true); // Default to true
	    ?>
	    <label>
	        <input type="checkbox" 
	               name="darb_assabil_payment_done_by_receiver" 
	               value="1" 
	               <?php checked($value, true); ?>>
	        <?php esc_html_e('Enable payment done by receiver', 'darb-assabil'); ?>
	    </label>
	    <p class="description"><?php esc_html_e('When enabled, the payment will be handled by the receiver.', 'darb-assabil'); ?></p>
	    <?php
	}

	/**
	 * Render the "Include Product Payment" field
	 */
	public function render_include_product_payment_field() {
	    $value = get_option('darb_assabil_include_product_payment', true); // Default to true
	    ?>
	    <label>
	        <input type="checkbox" 
	               name="darb_assabil_include_product_payment" 
	               value="1" 
	               <?php checked($value, true); ?>>
	        <?php esc_html_e('Enable inclusion of product payment', 'darb-assabil'); ?>
	    </label>
	    <p class="description"><?php esc_html_e('When enabled, the product payment will be included in the order.', 'darb-assabil'); ?></p>
	    <?php
	}

	// /**
	//  * Section callback
	//  */
	// public function api_section_callback() {
	// 	echo '<p>' . __('Configure your Darb Assabil API settings.', 'darb-assabil') . '</p>';
	// }

	// /**
	//  * Bearer token field callback
	//  */
	// public function bearer_token_callback() {
	// 	$token = get_option('darb_assabil_bearer_token');
	// 	echo '<input type="text" name="darb_assabil_bearer_token" value="' . esc_attr($token) . '" class="regular-text" />';
	// 	echo '<p class="description">' . __('Enter your API bearer token for authentication.', 'darb-assabil') . '</p>';
	// }

	/**
	 * Service dropdown callback
	 */
	public function service_dropdown_callback() {
		$selected_service = get_option('darb_assabil_service_id', '');
		$services = $this->get_services();
		
		echo '<select name="darb_assabil_service_id" id="darb_assabil_service_id">';
		echo '<option value="">' . esc_html__('Select a service', 'darb-assabil') . '</option>';
		
		foreach ($services as $service) {
			$selected = selected($selected_service, $service['id'], false);
			echo '<option value="' . esc_attr($service['id']) . '" ' . esc_attr($selected) . '>';
			echo esc_html($service['service']);
			echo '</option>';
		}
		
		echo '</select>';
		echo '<p class="description">' . esc_html__('Select the default service for shipping', 'darb-assabil') . '</p>';
	}

	/**
	 * Get services
	 */
	private function get_services() {
		$api_url = get_config('server_base_url') . '/api/darb/assabil/order/service/list';

	    $args = array(
	        'timeout' => 30,
	        'sslverify' => false,
	        'headers' => array(
	            'Accept' => 'application/json'
			),
			'body' => wp_json_encode(
				array('token' => get_access_token())
			),
	    );

		$response = wp_remote_post($api_url, $args);
	    if (is_wp_error($response)) {
	        return array();
	    }

	    $body = wp_remote_retrieve_body($response);
	    $data = json_decode($body, true);

	    if (!empty($data['data'])) {
	        return $data['data'];
	    }

	    return array();
	}

	/**
	 * Sanitize the options before saving
	 *
	 * @param array $input Options to sanitize
	 * @return array Sanitized options
	 */
	public function sanitize_options( $input ) {
	    $output = get_option( $this->option_name, array() );

	    if ( isset( $input['api_endpoint'] ) ) {
	        $output['api_endpoint'] = esc_url_raw( $input['api_endpoint'] );
	    }

	    if ( isset( $input['api_token'] ) ) {
	        $output['api_token'] = sanitize_text_field( $input['api_token'] );
	    }

	    // Handle checkboxes explicitly
	    $output['debug_mode'] = isset( $input['debug_mode'] ) ? true : false;
	    $output['use_city_dropdown'] = isset( $input['use_city_dropdown'] ) ? true : false;

	    return $output;
	}

	/**
	 * Get a specific option value
	 *
	 * @param string $key     The option key.
	 * @param mixed  $default Default value if option is not set.
	 * @return mixed Option value
	 */
	public function get_option( $key, $default = '' ) {
		$options = get_option( $this->option_name );
		return isset( $options[$key] ) ? $key : $default;
	}

	/**
	 * Check for the token in the URL and save it to the database
	 */
	public function check_and_save_token() {
	    // Handle logout
	    if (isset($_POST['darb_assabil_logout']) && $_POST['darb_assabil_logout'] === '1') {
	        delete_option('darb_assabil_access_token'); // Remove the token from the database
	        add_action('admin_notices', function() {
	            echo '<div class="notice notice-success is-dismissible"><p>' . __('Successfully logged out from Darb Assabil.', 'darb-assabil') . '</p></div>';
	        });

	        // Redirect to the same page to show the login functionality
	        wp_redirect(admin_url('admin.php?page=darb-assabil-settings&tab=integration'));
	        exit;
	    }

	    // Check if the current page is the plugin settings page and the token is present
	    if (isset($_GET['page']) && $_GET['page'] === 'darb-assabil-settings' && isset($_GET['token'])) {
	        $token = sanitize_text_field($_GET['token']); // Sanitize the token
	        update_option('darb_assabil_access_token', $token); // Save the token in the database
	    }
	}

	public function register_webhook_endpoint() {
		add_rewrite_rule('^darb-assabil-webhook/?$', 'index.php?darb_assabil_webhook=1', 'top');
		add_rewrite_tag('%darb_assabil_webhook%', '1');
		
		// Flush rewrite rules only if they haven't been flushed
		if (get_option('darb_assabil_flush_rewrite_rules', false) === false) {
		    flush_rewrite_rules();
		    update_option('darb_assabil_flush_rewrite_rules', true);
		}
	}

	/**
	 * Handle webhook request with X-Payload-Signature verification
	 */
	public function handle_webhook_request($wp) {
	    if (!isset($wp->query_vars['darb_assabil_webhook'])) {
	        return;
	    }
	    // Get payload and headers
	    $payload = file_get_contents('php://input');
	    $headers = getallheaders();
	    $signature = isset($headers['X-Payload-Signature']) ? $headers['X-Payload-Signature'] : '';
	    
	    $data = json_decode($payload, true);
	    // Verify signature
	    if (!$this->verify_webhook_signature($payload, $signature)) {
	        wp_send_json_error('Invalid signature', 403);
	        exit;
	    }

	    // Parse payload
	    if (json_last_error() !== JSON_ERROR_NONE || empty($data['event'])) {
	        wp_send_json_error('Invalid payload', 400);
	        exit;
	    }

	    // Process webhook event
	    try {
	        $result = $this->handle_webhook_event($data['event'], $data);
	        
	        // Log successful webhook
	        $webhook_data = array(
	            'timestamp' => current_time('mysql'),
	            'event' => $data['event'],
	            'data' => $data,
	            'signature' => $signature,
	            'headers' => $headers,
	            'response' => array(
	                'status' => 'success',
	                'message' => 'Webhook processed successfully'
	            )
	        );

	        $webhook_logs = get_option('darb_assabil_webhook_logs', array());
	        array_unshift($webhook_logs, $webhook_data);
	        $webhook_logs = array_slice($webhook_logs, 0, 50);
	        update_option('darb_assabil_webhook_logs', $webhook_logs);

	        wp_send_json_success([
	            'message' => 'Webhook processed successfully',
	            'event' => $data['event']
	        ]);

	    } catch (Exception $e) {
	        // Log failed webhook - only log once
	        $webhook_data = array(
	            'timestamp' => current_time('mysql'),
	            'event' => $data['event'],
	            'data' => $data,
	            'signature' => $signature,
	            'headers' => $headers,
	            'response' => array(
	                'status' => 'error',
	                'message' => $e->getMessage()
	            )
	        );

	        // Save to webhook logs
	        $webhook_logs = get_option('darb_assabil_webhook_logs', array());
	        array_unshift($webhook_logs, $webhook_data);
	        $webhook_logs = array_slice($webhook_logs, 0, 50);
	        update_option('darb_assabil_webhook_logs', $webhook_logs);
	        wp_send_json_error($e->getMessage(), 500);
	    }
	    exit;
	}

	/**
	 * Handle different webhook event types
	 */
	private function handle_webhook_event($event, $data) {
	    if (empty($data['requestId']) || empty($data['webhookId']) || empty($data['account'])) {
	        throw new Exception('Invalid webhook data structure');
	    }
	    switch ($event) {
	        case 'localShipments.pending':
	            return $this->process_shipment_status_change($data, 'pending', 'on-hold');
	            
	        case 'localShipments.booked':
	            return $this->process_shipment_status_change($data, 'booked', 'processing');
	            
	        case 'localShipments.processing':
	            return $this->process_shipment_status_change($data, 'processing', 'processing');
	            
	        case 'localShipments.on-branch':
	            return $this->process_shipment_status_change($data, 'on-branch', 'processing');
	            
	        case 'localShipments.completed':
	            return $this->process_shipment_status_change($data, 'completed', 'completed');
	            
	        case 'localShipments.cancelled':
	            return $this->process_shipment_status_change($data, 'cancelled', 'cancelled');
	            
	        case 'localShipments.resent':
	            return $this->process_shipment_status_change($data, 'resent', 'processing');
	            
	        case 'localShipments.delayed':
	            return $this->process_shipment_status_change($data, 'delayed', 'on-hold');
	            
	        case 'localShipments.released':
	            return $this->process_shipment_status_change($data, 'released', 'cancelled');
	            
	        case 'localShipments.returning':
	            return $this->process_shipment_status_change($data, 'returning', 'cancelled');
	            
	        case 'localShipments.returned':
	            return $this->process_shipment_status_change($data, 'returned', 'cancelled');
	            
	        default:
	            return false;
	    }
	}

	/**
	 * Process shipment status changes
	 */
	private function process_shipment_status_change($data, $darb_status, $wc_status) {
	    $payload = $data['payload'];
		$wc_order_id = $payload['metadata']['order_id'];
	    
	    if (empty($wc_order_id)) {
	        throw new Exception('Missing order ID in payload');
	    }

	    $order = wc_get_order($wc_order_id);
	    if (!$order) {
	        throw new Exception( esc_html__('Order not found', 'darb-assabil') );
	    }

	    // Update order status and metadata
	    // translators: 1: Darb Assabil shipment status, 2: Request ID
        $order->update_status(
            $wc_status,
            sprintf(
	        __('Darb Assabil shipment status changed to: %1$s (Request ID: %2$s)', 'darb-assabil'),
                $darb_status,
                $data['requestId']
            )
	    );

	    // Store Darb Assabil metadata
	    $order->update_meta_data('darb_assabil_status', $darb_status);
	    $order->update_meta_data('darb_assabil_request_id', $data['requestId']);
	    $order->update_meta_data('darb_assabil_webhook_id', $data['webhookId']);
	    $order->update_meta_data('darb_assabil_account', $data['account']);
	    
	    if (!empty($payload['trackingNumber'])) {
	        $order->update_meta_data('darb_assabil_tracking_number', $payload['trackingNumber']);
	    }
	    
	    $order->save();

	    do_action('darb_assabil_shipment_status_changed', array(
	        'order' => $order,
	        'darb_status' => $darb_status,
	        'wc_status' => $wc_status,
	        'request_id' => $data['requestId'],
	        'webhook_id' => $data['webhookId'],
	        'account' => $data['account'],
	        'payload' => $payload
	    ));

	    return true;
	}

	/**
	 * Process new order webhook
	 */
	private function process_webhook_order_created($data) {
	    if (empty($data['order_id'])) {
	        throw new Exception('Missing order ID');
	    }
	    
	    // Update WooCommerce order status
	    $order = wc_get_order($data['order_id']);
	    if ($order) {
	        $order->update_status('processing', 
                // translators: Order created in Darb Assabil
                __('Order created in Darb Assabil', 'darb-assabil')
            );
	        $order->update_meta_data('darb_assabil_tracking_id', $data['tracking_id']);
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_order_created', $data);
	}

	/**
	 * Process order update webhook
	 */
	private function process_webhook_order_updated($data) {
	    if (empty($data['order_id'])) {
	        throw new Exception('Missing order ID');
	    }
	    
	    // Add your order update logic here
	    do_action('darb_assabil_webhook_order_updated', $data);
	}

	/**
	 * Process order cancellation webhook
	 */
	private function process_webhook_order_cancelled($data) {
	    if (empty($data['order_id'])) {
	        throw new Exception('Missing order ID');
	    }
	    
	    $order = wc_get_order($data['order_id']);
	    if ($order) {
	        $order->update_status('cancelled', 
                // translators: Order cancelled in Darb Assabil
                __('Order cancelled in Darb Assabil', 'darb-assabil')
            );
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_order_cancelled', $data);
	}

	/**
	 * Process shipment creation webhook
	 */
	private function process_webhook_shipment_created($data) {
	    if (empty($data['shipment_id']) || empty($data['order_id'])) {
	        throw new Exception('Missing shipment data');
	    }
	    
	    $order = wc_get_order($data['order_id']);
	    if ($order) {
	        $order->update_meta_data('darb_assabil_shipment_id', $data['shipment_id']);
	        $order->update_status('processing', 
                // translators: Shipment created in Darb Assabil
                __('Shipment created in Darb Assabil', 'darb-assabil')
            );
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_shipment_created', $data);
	}

	/**
	 * Process shipment status webhook
	 */
	private function process_webhook_shipment_status($data) {
	    if (empty($data['shipment_id']) || empty($data['status'])) {
	        throw new Exception('Missing shipment data');
	    }
	    
	    // Add your shipment status update logic here
		do_action('darb_assabil_webhook_shipment_status', $data);
	}

	/**
	 * Process shipment delivery webhook
	 */
	private function process_webhook_shipment_delivered($data) {
	    if (empty($data['shipment_id']) || empty($data['order_id'])) {
	        throw new Exception('Missing shipment data');
	    }
	    
	    $order = wc_get_order($data['order_id']);
	    if ($order) {
	        $order->update_status('completed', 
                // translators: Order delivered by Darb Assabil
                __('Order delivered by Darb Assabil', 'darb-assabil')
            );
	        $order->update_meta_data('darb_assabil_delivered_at', current_time('mysql'));
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_shipment_delivered', $data);
	}

	/**
	 * Process payment received webhook
	 */
	private function process_webhook_payment_received($data) {
	    if (empty($data['order_id']) || empty($data['amount'])) {
	        throw new Exception('Missing payment data');
	    }
	    
	    $order = wc_get_order($data['order_id']);
	    if ($order) {
	        $order->payment_complete();
	        $order->add_order_note(
	        // translators: %s: Amount received
	            sprintf(
	            __('Payment of %s received via Darb Assabil', 'darb-assabil'),
	            wc_price($data['amount'])
	            )
	        );
	    }
	    
	    do_action('darb_assabil_webhook_payment_received', $data);
	}

	/**
	 * Process payment failed webhook
	 */
	private function process_webhook_payment_failed($data) {
	    if (empty($data['order_id'])) {
	        throw new Exception('Missing payment data');
	    }
	    
	    $order = wc_get_order($data['order_id']);
	    if ($order) {
	        $order->update_status('failed', 
                // translators: Payment failed in Darb Assabil
                __('Payment failed in Darb Assabil', 'darb-assabil')
            );
	    }
	    
	    do_action('darb_assabil_webhook_payment_failed', $data);
	}

	/**
	 * Render webhook tab content
	 */
	public function render_webhook_tab() {
	    $webhook_url = home_url('darb-assabil-webhook');
	    $webhook_secret = get_option('darb_assabil_webhook_secret');
	    $webhook_logs = get_option('darb_assabil_webhook_logs', array());
	    ?>
	    <div class="webhook-settings wrap">
	        <h2><?php esc_html_e('Webhook Configuration', 'darb-assabil'); ?></h2>
	        
	        <form method="post" action="options.php">
	            <?php settings_fields('darb_assabil_options'); ?>
	            
	            <div class="webhook-config">
	                <h3><?php esc_html_e('Webhook URL', 'darb-assabil'); ?></h3>
	                <input type="text" readonly value="<?php echo esc_url($webhook_url); ?>" 
	                       class="regular-text" onclick="this.select()">
	                
	                <h3><?php esc_html_e('Webhook Secret', 'darb-assabil'); ?></h3>
	                <div class="secret-field">
	                    <input type="text" name="darb_assabil_webhook_secret" 
	                           value="<?php echo esc_attr($webhook_secret); ?>" 
	                           class="regular-text">
	                    <button type="button" class="button" onclick="generateSecret()">
	                        <?php esc_html_e('Generate New Secret', 'darb-assabil'); ?>
	                    </button>
	                </div>
	                <p class="description">
	                    <?php esc_html_e('Use this secret to verify webhook signatures. Keep it safe!', 'darb-assabil'); ?>
	                </p>
	            </div>
	            
	            <?php submit_button(__('Save Webhook Settings', 'darb-assabil')); ?>
	        </form>

	        <div class="webhook-logs">
	            <h3><?php esc_html_e('Recent Webhook Events', 'darb-assabil'); ?></h3>
	            <p class="description">
	                <?php esc_html_e('Below are the last 50 webhook events received:', 'darb-assabil'); ?>
	            </p>
	            
	            <?php if (empty($webhook_logs)): ?>
	                <p><?php esc_html_e('No webhook events received yet.', 'darb-assabil'); ?></p>
	            <?php else: ?>
	                <table class="widefat">
	                    <thead>
	                        <tr>
	                            <th><?php esc_html_e('Timestamp', 'darb-assabil'); ?></th>
	                            <th><?php esc_html_e('Event', 'darb-assabil'); ?></th>
	                            <th><?php esc_html_e('Signature', 'darb-assabil'); ?></th>
	                            <th><?php esc_html_e('Status', 'darb-assabil'); ?></th>
	                            <th><?php esc_html_e('Response Status', 'darb-assabil'); ?></th>
	                            <th><?php esc_html_e('Message', 'darb-assabil'); ?></th>
	                            <th><?php esc_html_e('Details', 'darb-assabil'); ?></th>
	                        </tr>
	                    </thead>
	                    <tbody>
	                        <?php foreach ($webhook_logs as $log): ?>
	                            <tr>
	                                <td><?php echo esc_html($log['timestamp']); ?></td>
	                                <td><?php echo esc_html($log['event']); ?></td>
	                                <td>
	                                    <code class="signature-hash"> 
	                                        <?php echo esc_html(substr($log['signature'], 0, 8) . '...'); ?>
	                                    </code>
	                                </td>
	                                <td>
	                                    <?php
	                                    $status = str_replace('localShipments.', '', $log['event']);
	                                    echo '<span class="status-' . esc_attr($status) . '">' 
	                                        . esc_html(ucfirst($status)) . '</span>';
	                                    ?>
	                                </td>
	                                <td>
	                                    <?php
	                                    $response_status = isset($log['response']['status']) ? $log['response']['status'] : 'unknown';
	                                    $status_class = $response_status === 'success' ? 'status-success' : 'status-error';
	                                    $status_text = $response_status === 'success'
	                                        ? esc_html__('Success', 'darb-assabil')
	                                        : esc_html__('Failed', 'darb-assabil');
	                                    ?>
	                                    <span class="response-status <?php echo esc_attr($status_class); ?>">
	                                        <?php echo esc_html($status_text); ?>
	                                    </span>
	                                </td>
	                                <td class="response-message">
	                                    <?php 
	                                    $message = isset($log['response']['message']) ? $log['response']['message'] : '';
	                                    echo esc_html($message);
	                                    ?>
	                                </td>
	                                <td>
	                                    <button type="button" class="button" onclick="toggleDetails(this)">
	                                        <?php esc_html_e('View Details', 'darb-assabil'); ?>
	                                    </button>
	                                    <pre class="webhook-details" style="display:none;">
	                                        <?php echo esc_html(json_encode($log['data'], JSON_PRETTY_PRINT)); ?>
	                                    </pre>
	                                </td>
	                            </tr>
	                        <?php endforeach; ?>
	                    </tbody>
	                </table>
	            <?php endif; ?>
	        </div>
	    </div>
	    <?php
	}

	private function verify_webhook_signature($payload, $received_signature) {
	    // Get webhook secret from WordPress options
	    $webhook_secret = get_plugin_option()['darb_assabil_webhook_secret'];
	    
	    if (empty($webhook_secret)) {
	        return false;
	    }
	    
	    // Calculate signature using webhook secret
	    $expected_signature = hash('sha256', $payload . ":" . $webhook_secret);
	    
	    // Compare signatures using hash_equals to prevent timing attacks
	    return hash_equals($expected_signature, $received_signature);
	}

	/**
	 * Handle retry order AJAX request
	 */
	public function handle_retry_order() {
	    check_ajax_referer('retry-darb-assabil-order', 'nonce');

	    if (!current_user_can('manage_woocommerce')) {
	        wp_send_json_error('Permission denied');
	    }

	    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
	    $is_bulk = isset($_POST['is_bulk']) ? (bool)$_POST['is_bulk'] : false;

	    if (!$order_id) {
	        wp_send_json_error('Invalid order ID');
	    }

	    try {
	        // Get the order
	        $order = wc_get_order($order_id);
	        if (!$order) {
	            throw new Exception( esc_html__('Order not found', 'darb-assabil') );
	        }

	        // Get saved payload
	        $saved_payload = $order->get_meta('darb_assabil_api_payload');

	        // Reset processed flags
	        delete_post_meta($order_id, '_darb_assabil_processed');
	        delete_post_meta($order_id, '_darb_assabil_error');
	        delete_post_meta($order_id, 'darb_assabil_api_status');
	        delete_post_meta($order_id, 'darb_assabil_api_response');
	        
	        // Get the order handler instance
	        $order_handler = \DarbAssabil\OrderHandler::get_instance();
	        
	        if ($saved_payload) {
	            // Use saved payload if available
	            $order_handler->create_order($saved_payload);
	        } else {
	            // Fallback to generating new payload
	            $order_handler->handle_new_order($order_id, $order);
	        }

	        // Check if the API call was successful
	        $api_status = $order->get_meta('darb_assabil_api_status');
	        
	        if ($api_status === 'failed') {
	            $api_message = $order->get_meta('darb_assabil_api_message');
	            throw new Exception('API call failed: ' . $api_message);
	        }

	        if ($is_bulk) {
	            wp_send_json_success("Order #{$order_id} retried successfully");
	        } else {
	            wp_send_json_success('Order successfully retried');
	        }
	    } catch (Exception $e) {
	        wp_send_json_error('Order retry failed: ' . $e->getMessage());
	    }
	}

	public function handle_save_payload() {
	    check_ajax_referer('save-darb-assabil-payload', 'nonce');

	    if (!current_user_can('manage_woocommerce')) {
	        wp_send_json_error('Permission denied');
	    }

	    // Get the raw POST data instead of using $_POST
	    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
	    $payload = isset($_POST['payload']) ? stripslashes($_POST['payload']) : '';

	    if (!$order_id) {
	        wp_send_json_error('Invalid order ID');
	    }

	    if (empty($payload)) {
	        wp_send_json_error('Empty payload');
	    }

	    try {
	        $order = wc_get_order($order_id);
	        if (!$order) {
	            throw new Exception( esc_html__('Order not found', 'darb-assabil') );
	        }

	        // Validate JSON
	        $decoded_payload = json_decode($payload, true);
	        if (json_last_error() !== JSON_ERROR_NONE) {
	            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
	        }

	        // Update the payload
	        $order->update_meta_data('darb_assabil_api_payload', $decoded_payload);
	        $order->save();

	        wp_send_json_success('Payload saved successfully');
	    } catch (Exception $e) {
	        wp_send_json_error($e->getMessage());
	    }
	}

	/**
	 * Enqueue admin styles and scripts
	 */
	public function enqueue_admin_styles() {
	    // Only load on our plugin pages
	    $screen = get_current_screen();
	    if (!$screen || $screen->id !== 'toplevel_page_darb-assabil-settings') {
	        return;
	    }

	    // Enqueue CSS
	    wp_enqueue_style(
	        'darb-assabil-admin',
	        plugin_dir_url(__DIR__) . 'assets/css/style.css',
	        array(),
	        filemtime(plugin_dir_path(__DIR__) . 'assets/css/style.css')
	    );

	    // Enqueue JavaScript
	    wp_enqueue_script(
	        'darb-assabil-admin',
	        plugin_dir_url(__DIR__) . 'assets/js/admin.js',
	        array('jquery'),
	        filemtime(plugin_dir_path(__DIR__) . 'assets/js/admin.js'),
	        true
	    );

	    // Localize script
	    wp_localize_script(
	        'darb-assabil-admin',
	        'darbAssabilAdmin',
	        array(
	            'payloadNonce' => wp_create_nonce('save-darb-assabil-payload'),
	            'retryNonce' => wp_create_nonce('retry-darb-assabil-order'),
	            'hideDetailsText' => esc_html__('Hide Details', 'darb-assabil'),
	            'viewDetailsText' => esc_html__('View Details', 'darb-assabil')
	        )
	    );
	}

	/**
	 * Render the Shortcode tab content
	 */
	public function render_shortcode_tab() {
    ?>
    <div class="darb-assabil-shortcode-tab">
        <h2><?php esc_html_e('Tracking Shortcode', 'darb-assabil'); ?></h2>
        <p>
            <?php esc_html_e('You can allow your customers to track their shipments directly from your website using the following shortcode:', 'darb-assabil'); ?>
        </p>
        <pre style="background:#f8f8f8;padding:10px 15px;border-radius:5px;border:1px solid #eee;font-size:16px;">[darb_assabil_tracking]</pre>
        <p>
            <?php esc_html_e('How to use:', 'darb-assabil'); ?>
        </p>
        <ol>
            <li><?php esc_html_e('Copy the shortcode above.', 'darb-assabil'); ?></li>
            <li><?php esc_html_e('Paste it into any page, post, or widget where you want to display the shipment tracking form.', 'darb-assabil'); ?></li>
            <li><?php esc_html_e('Publish or update the page. Your customers will now be able to enter their tracking number and see the shipment status.', 'darb-assabil'); ?></li>
        </ol>
        <p>
            <?php esc_html_e('Example:', 'darb-assabil'); ?><br>
            <code>[darb_assabil_tracking]</code>
        </p>
        <p>
            <?php esc_html_e('This will display a tracking form and timeline styled according to your theme.', 'darb-assabil'); ?>
        </p>
    </div>
    <?php
}

public function custom_admin_footer_css() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_darb-assabil-settings') {
        echo '<style>#wpfooter {display:block !important;}</style>';
    }
}
public function custom_admin_footer_text($text) {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_darb-assabil-settings') {
        return '<span id="darb-credit"><img src="' . esc_url(plugins_url('assets/img/huruf-logo.png', dirname(__FILE__))) . '" alt="Darb Assabil" style="height:22px;vertical-align:middle;margin-right:8px;"><span>Powered by <a href="https://huruftech.com/" target="_blank" style="color:inherit;text-decoration:underline;">Huruf Tech</a></span></span>';
    }
    return $text;
}
public function custom_admin_footer_version($text) {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_darb-assabil-settings') {
        return '<span>v1.0.0</span> <span>&copy; ' . gmdate('Y') . ' HurufTech</span>';
    }
    return $text;
}
}