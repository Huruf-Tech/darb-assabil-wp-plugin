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
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( plugin_dir_path( __FILE__ ) . '../assets/DarbAssabil-Logo.svg' ) ),
		);
	}

	/**
	 * Register settings for the plugin
	 */
	public function register_settings() {
	    // Register individual options
	    // register_setting('darb_assabil_options', 'darb_assabil_bearer_token');
	    register_setting('darb_assabil_options', 'darb_assabil_service_id');
	    // register_setting('darb_assabil_options', 'darb_assabil_debug_mode', array(
	    //     'type' => 'boolean',
	    //     'sanitize_callback' => 'rest_sanitize_boolean',
	    //     'default' => false,
	    // ));
	    register_setting('darb_assabil_options', 'darb_assabil_use_city_dropdown', array(
	        'type' => 'boolean',
	        'sanitize_callback' => 'rest_sanitize_boolean',
	        'default' => true,
	    ));
	    register_setting('darb_assabil_options', 'darb_assabil_payment_done_by_receiver', array(
	        'type' => 'boolean',
	        'sanitize_callback' => 'rest_sanitize_boolean',
	        'default' => true, // Set default value to true
	    ));
	    register_setting('darb_assabil_options', 'darb_assabil_include_product_payment', array(
	        'type' => 'boolean',
	        'sanitize_callback' => 'rest_sanitize_boolean',
	        'default' => true, // Set default value to true
	    ));

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
	    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'orders'; // Changed default to orders

	    ?>
	    <div class="wrap">
	        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
	        
	        <!-- Tabs -->
	        <h2 class="nav-tab-wrapper">
	            <a href="?page=darb-assabil-settings&tab=orders" class="nav-tab <?php echo $current_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
	                <?php esc_html_e('Orders', 'darb-assabil'); ?>
	            </a>
	            <a href="?page=darb-assabil-settings&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
	                <?php esc_html_e('Settings', 'darb-assabil'); ?>
	            </a>
	            <a href="?page=darb-assabil-settings&tab=integration" class="nav-tab <?php echo $current_tab === 'integration' ? 'nav-tab-active' : ''; ?>">
	                <?php esc_html_e('Integration', 'darb-assabil'); ?>
	            </a>
	            <a href="?page=darb-assabil-settings&tab=webhooks" class="nav-tab <?php echo $current_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
	                <?php esc_html_e('Webhooks', 'darb-assabil'); ?>
	            </a>
	        </h2>

	        <!-- Tab Content -->
	        <?php if ($current_tab === 'webhooks'): ?>
	            <?php $this->render_webhook_tab(); ?>
	        <?php elseif ($current_tab === 'orders'): ?>
	            <?php $this->render_orders_tab(); ?>
	        <?php else: ?>
	            <form action="options.php" method="post">
	                <?php
	                if ($current_tab === 'settings') {
	                    settings_fields('darb_assabil_options');
	                    do_settings_sections('darb-assabil-settings');
	                    submit_button();
	                } elseif ($current_tab === 'integration') {
	                    $this->render_integrate_tab();
	                }
	                ?>
	            </form>
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
		$this->log('Orders retrieved with meta query: ' . print_r($orders, true));
	    ?>
	    <div class="darb-assabil-orders wrap">
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
	                    <th><?php esc_html_e('Tracking Number', 'darb-assabil'); ?></th>
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
	                        <tr>
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
	                                <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
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
									$this->log('order Payload: ' . print_r($payload, true));
									if ($payload) {
										// Ensure proper JSON encoding and escaping
										$json_payload = json_encode($payload, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
										?>
										<button class="button view-data" data-type="payload" data-content="<?php echo esc_attr($json_payload); ?>">
											<?php esc_html_e('View Payload', 'darb-assabil'); ?>
										</button>
									<?php } ?>
								</td>
								<td>
									<?php
									$response = $order->get_meta('darb_assabil_api_response');
									$this->log('order Response: ' . print_r($response, true));
									if ($response) {
										// Ensure proper JSON encoding and escaping
										$json_response = is_string($response) ? $response : json_encode($response, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
										?>
										<button class="button view-data" data-type="response" data-content="<?php echo esc_attr($json_response); ?>">
											<?php esc_html_e('View Response', 'darb-assabil'); ?>
										</button>
									<?php } ?>
								</td>
	                            <td>
	                                <?php if ($darb_status === 'failed' || !$darb_status) : ?>
	                                    <button class="button retry-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
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

	    <!-- Modal for displaying JSON data -->
	    <div id="json-modal" class="modal">
	        <div class="modal-content">
	            <div class="modal-header">
	                <span class="close">&times;</span>
	                <h3 class="modal-title"></h3>
	            </div>
	            <div class="modal-body">
	                <pre id="json-content"></pre>
	            </div>
	        </div>
	    </div>

	    <style>
	        /* ... existing styles ... */

	        .modal {
	            display: none;
	            position: fixed;
	            z-index: 1000;
	            left: 0;
	            top: 0;
	            width: 100%;
	            height: 100%;
	            background-color: rgba(0,0,0,0.4);
	        }

	        .modal-content {
	            background-color: #fefefe;
	            margin: 15% auto;
	            padding: 20px;
	            border: 1px solid #888;
	            width: 80%;
	            max-height: 70vh;
	            overflow-y: auto;
	        }

	        .close {
	            color: #aaa;
	            float: right;
	            font-size: 28px;
	            font-weight: bold;
	            cursor: pointer;
	        }

	        pre {
	            white-space: pre-wrap;
	            word-wrap: break-word;
	        }

	        /* Order status colors */
		    .order-status {
		        display: inline-block;
		        padding: 4px 8px;
		        border-radius: 3px;
		        font-weight: 600;
		    }
		    .status-processing { background: #c6e1c6; color: #5b841b; }
		    .status-completed { background: #c8d7e1; color: #2e4453; }
		    .status-on-hold { background: #f8dda7; color: #94660c; }
		    .status-failed { background: #eba3a3; color: #761919; }
		    .status-cancelled { background: #e5e5e5; color: #777; }
		    
		    /* Darb status colors */
		    .darb-status {
		        display: inline-block;
		        padding: 4px 8px;
		        border-radius: 3px;
		        font-weight: 600;
		    }
		    .darb-status.success { background: #c6e1c6; color: #5b841b; }
		    .darb-status.failed { background: #eba3a3; color: #761919; }
		    .darb-status.unknown { background: #e5e5e5; color: #777; }

	        .modal-header {
	            padding: 10px 20px;
	            border-bottom: 1px solid #ddd;
	        }
	        .modal-header h3 {
	            margin: 0;
	            display: inline-block;
	        }
	        .modal-body {
	            padding: 20px;
	        }
	        .modal-content {
	            max-width: 800px;
	        }
	        pre#json-content {
	            background: #f5f5f5;
	            padding: 15px;
	            border: 1px solid #ddd;
	            border-radius: 4px;
	        }
	    </style>

	    <script>
	    jQuery(document).ready(function($) {
	        // Bulk action handling
	        $('#doaction').on('click', function(e) {
	            e.preventDefault();
	            var action = $('select[name="bulk-action"]').val();
	            var selectedOrders = $('input[name="order[]"]:checked').map(function() {
	                return $(this).val();
	            }).get();

	            if (action === 'retry' && selectedOrders.length > 0) {
	                retryOrders(selectedOrders);
	            }
	        });

	        // Select all checkbox
	        $('#cb-select-all-1').on('change', function() {
	            $('input[name="order[]"]').prop('checked', $(this).prop('checked'));
	        });

	        // Modal handling
	        var modal = $('#json-modal');
	        var span = $('.close');

	        $('.view-data').on('click', function() {
	            var content = JSON.parse($(this).data('content'));
	            $('#json-content').text(JSON.stringify(content, null, 2));
	            modal.show();
	        });

	        span.on('click', function() {
	            modal.hide();
	        });

	        $(window).on('click', function(e) {
	            if ($(e.target).is(modal)) {
	                modal.hide();
	            }
	        });

	        // ... existing retry-order click handler ...

	        function retryOrders(orderIds) {
	            orderIds.forEach(function(orderId) {
	                $.ajax({
	                    url: ajaxurl,
	                    type: 'POST',
	                    data: {
	                        action: 'retry_darb_assabil_order',
	                        order_id: orderId,
	                        nonce: '<?php echo wp_create_nonce('retry-darb-assabil-order'); ?>'
	                    },
	                    success: function(response) {
	                        if (response.success) {
	                            location.reload();
	                        }
	                    }
	                });
	            });
	        }
	    });
	    </script>
	    <script>
jQuery(document).ready(function($) {
    // Existing code...

    // Updated modal handling
    $('.view-data').on('click', function() {
        var content = $(this).data('content');
        var type = $(this).data('type');
        
        try {
            // Parse the content if it's a string
            if (typeof content === 'string') {
                content = JSON.parse(content);
            }
            
            // Format the JSON with proper indentation
            var formattedContent = JSON.stringify(content, null, 2);
            
            // Update modal title based on type
            var title = type === 'payload' ? 'API Payload' : 'API Response';
            $('#json-modal .modal-title').text(title);
            
            // Update content
            $('#json-content').text(formattedContent);
            $('#json-modal').show();
        } catch (e) {
            console.error('Error parsing JSON:', e);
            alert('Error displaying data. Please check console for details.');
        }
    });

    // Existing code...
});
</script>
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
	            <?php submit_button(__('Logout from Darb Assabil', 'darb-assabil'), 'primary'); ?>
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

	    $this->log('API login request: ' . print_r($args, true));
	    $this->log('API login response: ' . print_r($response, true));

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
	        <a href="<?php echo $login_url; ?>" class="button button-primary">
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
		$this->log('Selected service ID: ' . $selected_service);
		$services = $this->get_services();

		$this->log('Available services: ' . print_r($services, true));
		
		echo '<select name="darb_assabil_service_id" id="darb_assabil_service_id">';
		echo '<option value="">' . __('Select a service', 'darb-assabil') . '</option>';
		
		foreach ($services as $service) {
			$selected = selected($selected_service, $service['id'], false);
			echo '<option value="' . esc_attr($service['id']) . '" ' . $selected . '>';
			echo esc_html($service['service']);
			echo '</option>';
		}
		
		echo '</select>';
		echo '<p class="description">' . __('Select the default service for shipping', 'darb-assabil') . '</p>';
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
		$this->log('API response: ' . print_r($response, true));
	    if (is_wp_error($response)) {
	        error_log('Darb Assabil API Error: ' . $response->get_error_message());
	        return array();
	    }

	    $body = wp_remote_retrieve_body($response);
	    $data = json_decode($body, true);

	    $this->log('API Response: ' . print_r($data, true));

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
	 * Handle webhook request with X-Darb-Signature verification
	 */
	public function handle_webhook_request($wp) {
	    if (!isset($wp->query_vars['darb_assabil_webhook'])) {
	        return;
	    }

	    // Get payload and headers
	    $payload = file_get_contents('php://input');
	    $headers = getallheaders();
	    $signature = isset($headers['X-Darb-Signature']) ? $headers['X-Darb-Signature'] : '';
	    
	    // Verify signature
	    if (!$this->verify_webhook_signature($payload, $signature)) {
	        $this->log('Invalid webhook signature');
	        wp_send_json_error('Invalid signature', 403);
	        exit;
	    }

	    // Parse payload
	    $data = json_decode($payload, true);
	    if (json_last_error() !== JSON_ERROR_NONE || empty($data['event'])) {
	        $this->log('Invalid webhook payload: ' . json_last_error_msg());
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

	        $this->log('Webhook processing error: ' . $e->getMessage());
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

	    $this->log('Processing webhook: ' . $event . ' for request: ' . $data['requestId']);

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
	            return $this->process_shipment_status_change($data, 'released', 'processing');
	            
	        case 'localShipments.returning':
	            return $this->process_shipment_status_change($data, 'returning', 'on-hold');
	            
	        case 'localShipments.returned':
	            return $this->process_shipment_status_change($data, 'returned', 'failed');
	            
	        default:
	            $this->log('Unhandled webhook event: ' . $event);
	            return false;
	    }
	}

	/**
	 * Process shipment status changes
	 */
	private function process_shipment_status_change($data, $darb_status, $wc_status) {
	    $payload = $data['payload'];
	    
	    if (empty($payload['orderId'])) {
	        throw new Exception('Missing order ID in payload');
	    }

	    $order = wc_get_order($payload['orderId']);
	    if (!$order) {
	        throw new Exception('Order not found: ' . $payload['orderId']);
	    }

	    // Update order status and metadata
	    $order->update_status(
	        $wc_status,
	        sprintf(
	            __('Darb Assabil shipment status changed to: %s (Request ID: %s)', 'darb-assabil'),
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
	        $order->update_status('processing', __('Order created in Darb Assabil', 'darb-assabil'));
	        $order->update_meta_data('darb_assabil_tracking_id', $data['tracking_id']);
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_order_created', $data);
	    $this->log('Order created: ' . $data['order_id']);
	}

	/**
	 * Process order update webhook
	 */
	private function process_webhook_order_updated($data) {
	    if (empty($data['order_id'])) {
	        throw new Exception('Missing order ID');
	    }
	    
	    // Add your order update logic here
	    $this->log('Processing order update: ' . $data['order_id']);
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
	        $order->update_status('cancelled', __('Order cancelled in Darb Assabil', 'darb-assabil'));
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_order_cancelled', $data);
	    $this->log('Order cancelled: ' . $data['order_id']);
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
	        $order->update_status('processing', __('Shipment created in Darb Assabil', 'darb-assabil'));
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_shipment_created', $data);
	    $this->log('Shipment created: ' . $data['shipment_id']);
	}

	/**
	 * Process shipment status webhook
	 */
	private function process_webhook_shipment_status($data) {
	    if (empty($data['shipment_id']) || empty($data['status'])) {
	        throw new Exception('Missing shipment data');
	    }
	    
	    // Add your shipment status update logic here
	    $this->log('Processing shipment status: ' . $data['shipment_id'] . ' - ' . $data['status']);
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
	        $order->update_status('completed', __('Order delivered by Darb Assabil', 'darb-assabil'));
	        $order->update_meta_data('darb_assabil_delivered_at', current_time('mysql'));
	        $order->save();
	    }
	    
	    do_action('darb_assabil_webhook_shipment_delivered', $data);
	    $this->log('Shipment delivered: ' . $data['shipment_id']);
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
	            sprintf(
	                __('Payment of %s received via Darb Assabil', 'darb-assabil'),
	                wc_price($data['amount'])
	            )
	        );
	    }
	    
	    do_action('darb_assabil_webhook_payment_received', $data);
	    $this->log('Payment received for order: ' . $data['order_id']);
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
	        $order->update_status('failed', __('Payment failed in Darb Assabil', 'darb-assabil'));
	    }
	    
	    do_action('darb_assabil_webhook_payment_failed', $data);
	    $this->log('Payment failed for order: ' . $data['order_id']);
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
	                                    $status_text = $response_status === 'success' ? __('Success', 'darb-assabil') : __('Failed', 'darb-assabil');
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
	    
	    <style>
	        /* Add status colors */
	        .status-pending { color: #f0ad4e; }
	        .status-booked { color: #5bc0de; }
	        .status-processing { color: #0073aa; }
	        .status-completed { color: #5cb85c; }
	        .status-cancelled { color: #d9534f; }
	        .status-delayed { color: #f0ad4e; }
	        .status-returned { color: #d9534f; }
	        .webhook-details { margin-top: 10px; }
	        .response-status {
		        display: inline-block;
		        padding: 4px 8px;
		        border-radius: 3px;
		        font-weight: 600;
		    }
		    
		    .status-success {
		        background-color: #dff0d8;
		        color: #3c763d;
		        border: 1px solid #d6e9c6;
		    }
		    
		    .status-error {
		        background-color: #f2dede;
		        color: #a94442;
		        border: 1px solid #ebccd1;
		    }
		    
		    .response-message {
		        max-width: 200px;
		        overflow: hidden;
		        text-overflow: ellipsis;
		        white-space: nowrap;
		    }
	    </style>
	    
	    <script>
	    function generateSecret() {
	        // Generate a random string of 32 characters
	        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	        let secret = '';
	        for (let i = 0; i < 32; i++) {
	            secret += chars.charAt(Math.floor(Math.random() * chars.length));
	        }
	        document.querySelector('input[name="darb_assabil_webhook_secret"]').value = secret;
	    }

	    function toggleDetails(button) {
	        const details = button.nextElementSibling;
	        if (details.style.display === 'none') {
	            details.style.display = 'block';
	            button.textContent = '<?php esc_html_e('Hide Details', 'darb-assabil'); ?>';
	        } else {
	            details.style.display = 'none';
	            button.textContent = '<?php esc_html_e('View Details', 'darb-assabil'); ?>';
	        }
	    }
	    </script>
	    <?php
	}

	private function verify_webhook_signature($payload, $received_signature) {
	    // Get API token
	    $api_token = get_access_token(); // Your stored API token
		$this->log('API token configured ======' . $api_token);
	    if (empty($api_token)) {
	        $this->log('API token not configured');
	    }
	    
	    // Calculate signature
	    $expected_signature = hash_hmac('sha256', $payload, $api_token, false);
	    
	    // Log for debugging
	    $this->log('Payload: ' . $payload);
	    $this->log('API Token: ' . substr($api_token, 0, 8) . '...');  // Log partial token for security
	    $this->log('Received signature: ' . $received_signature);
	    $this->log('Expected signature: ' . $expected_signature);
	    
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
	    if (!$order_id) {
	        wp_send_json_error('Invalid order ID');
	    }

	    try {
	        // Get the order handler instance
	        $order_handler = \DarbAssabil\OrderHandler::get_instance();
	        
	        // Process the order
	        $order_handler->handle_new_order($order_id, null);
	        
	        wp_send_json_success();
	    } catch (Exception $e) {
	        wp_send_json_error($e->getMessage());
	    }
	}
}