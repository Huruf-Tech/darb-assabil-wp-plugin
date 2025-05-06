<?php
/**
 * Admin Settings for Darb Assabil plugin
 *
 * @package Darb_Assabil
 */

namespace DarbAssabil;

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
		register_setting( 
			$this->option_group, 
			$this->option_name,
			array( $this, 'sanitize_options' )
		);

		// Register individual options
		register_setting('darb_assabil_options', 'darb_assabil_bearer_token');
		register_setting('darb_assabil_options', 'darb_assabil_service_id'); // Ensure this is registered
		register_setting('darb_assabil_options', 'darb_assabil_integration_option');

		add_settings_section(
			'darb_assabil_general',
			__( 'General Settings', 'darb-assabil' ),
			array( $this, 'render_section' ),
			'darb-assabil-settings'
		);

		add_settings_field(
			'api_endpoint',
			__( 'API Endpoint', 'darb-assabil' ),
			array( $this, 'render_api_endpoint_field' ),
			'darb-assabil-settings',
			'darb_assabil_general'
		);

		add_settings_field(
			'api_token',
			__( 'API Token', 'darb-assabil' ),
			array( $this, 'render_api_token_field' ),
			'darb-assabil-settings',
			'darb_assabil_general'
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'darb-assabil' ),
			array( $this, 'render_debug_mode_field' ),
			'darb-assabil-settings',
			'darb_assabil_general'
		);
		
		add_settings_field(
			'use_city_dropdown',
			__( 'City Field Type', 'darb-assabil' ),
			array( $this, 'render_city_dropdown_field' ),
			'darb-assabil-settings',
			'darb_assabil_general'
		);
		
		add_settings_section(
			'darb_assabil_api_section',
			__('API Settings', 'darb-assabil'),
			array($this, 'api_section_callback'),
			'darb-assabil-settings'
		);

		add_settings_field(
			'darb_assabil_bearer_token',
			__('Bearer Token', 'darb-assabil'),
			array($this, 'bearer_token_callback'),
			'darb-assabil-settings',
			'darb_assabil_api_section'
		);

		add_settings_field(
			'darb_assabil_service_id',
			__('Default Service', 'darb-assabil'),
			array($this, 'service_dropdown_callback'),
			'darb-assabil-settings',
			'darb_assabil_general'
		);
	}

	/**
	 * Render the settings page with tabs
	 */
	public function render_settings_page() {
		$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings'; // Default to 'settings'

		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			
			<!-- Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="?page=darb-assabil-settings&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Settings', 'darb-assabil'); ?>
				</a>
				<a href="?page=darb-assabil-settings&tab=integration" class="nav-tab <?php echo $current_tab === 'integration' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Integration', 'darb-assabil'); ?>
				</a>
			</h2>

			<!-- Tab Content -->
			<form action="options.php" method="post">
				<?php
				if ($current_tab === 'settings') {
					settings_fields('darb_assabil_options');
					do_settings_sections('darb-assabil-settings');
					submit_button();
				} elseif ($current_tab === 'integration') { // Ensure lowercase 'integration'
					$this->render_integrate_tab();
				}
				?>
			</form>
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
	    $saved_token = get_option('darb_assabil_access_token', '');

	    // If the token exists, skip the API call and show the logout button
	    if (!empty($saved_token)) {
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
	    $api_url = $config['middleware_server_base_url'] . '/api/darb/assabil/auth/GetLoginUrl';

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
	        <a href="<?php echo $login_url; ?>" class="button button-primary" target="_blank">
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
	 * Render the API endpoint field
	 */
	public function render_api_endpoint_field() {
		$options = get_option( $this->option_name );
		$value = isset( $options['api_endpoint'] ) ? $options['api_endpoint'] : 'http://localhost:3005/store-data';
		?>
		<input type="url" 
			   name="<?php echo esc_attr( $this->option_name ); ?>[api_endpoint]" 
			   value="<?php echo esc_url( $value ); ?>" 
			   class="regular-text">
		<p class="description"><?php esc_html_e( 'Enter the API endpoint URL for order processing.', 'darb-assabil' ); ?></p>
		<?php
	}

	/**
	 * Render the API token field
	 */
	public function render_api_token_field() {
		$options = get_option( $this->option_name );
		$value = isset( $options['api_token'] ) ? $options['api_token'] : '';
		?>
		<input type="password" 
			   name="<?php echo esc_attr( $this->option_name ); ?>[api_token]" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="regular-text">
		<p class="description"><?php esc_html_e( 'Enter your API authentication token.', 'darb-assabil' ); ?></p>
		<?php
	}

	/**
	 * Render the debug mode field
	 */
	public function render_debug_mode_field() {
		$options = get_option( $this->option_name );
		$value = isset( $options['debug_mode'] ) ? $options['debug_mode'] : false;
		?>
		<label>
			<input type="checkbox" 
				   name="<?php echo esc_attr( $this->option_name ); ?>[debug_mode]" 
				   value="1" 
				   <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Enable debug logging', 'darb-assabil' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, debug information will be logged to the WordPress debug log.', 'darb-assabil' ); ?></p>
		<?php
	}

	/**
	 * Render the city dropdown toggle field
	 */
	public function render_city_dropdown_field() {
		$options = get_option( $this->option_name );
		$value = isset( $options['use_city_dropdown'] ) ? $options['use_city_dropdown'] : true;
		?>
		<label>
			<input type="checkbox" 
				   name="<?php echo esc_attr( $this->option_name ); ?>[use_city_dropdown]" 
				   value="1" 
				   <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Use dropdown for city fields', 'darb-assabil' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, city fields will use a dropdown with Libyan cities provided by Darb Assabil. When disabled, standard text input will be used.', 'darb-assabil' ); ?></p>
		<?php
	}

	/**
	 * Section callback
	 */
	public function api_section_callback() {
		echo '<p>' . __('Configure your Darb Assabil API settings.', 'darb-assabil') . '</p>';
	}

	/**
	 * Bearer token field callback
	 */
	public function bearer_token_callback() {
		$token = get_option('darb_assabil_bearer_token');
		echo '<input type="text" name="darb_assabil_bearer_token" value="' . esc_attr($token) . '" class="regular-text" />';
		echo '<p class="description">' . __('Enter your API bearer token for authentication.', 'darb-assabil') . '</p>';
	}

	/**
	 * Service dropdown callback
	 */
	public function service_dropdown_callback() {
		$selected_service = get_option('darb_assabil_service_id', '');
		$this->log('Selected service ID: ' . $selected_service);
		$services = $this->get_services();
		
		echo '<select name="darb_assabil_service_id" id="darb_assabil_service_id">';
		echo '<option value="">' . __('Select a service', 'darb-assabil') . '</option>';
		
		foreach ($services as $service) {
			$selected = selected($selected_service, $service['_id'], false);
			echo '<option value="' . esc_attr($service['_id']) . '" ' . $selected . '>';
			echo esc_html($service['title'] . ' (' . $service['amount'] . ' LYD)');
			echo '</option>';
		}
		
		echo '</select>';
		echo '<p class="description">' . __('Select the default service for shipping', 'darb-assabil') . '</p>';
	}

	/**
	 * Get services
	 */
	private function get_services() {
	    // Include the configuration file
	    $config = include plugin_dir_path(__DIR__) . 'config.php';

	    $bearer_token = get_option('darb_assabil_bearer_token', '');
	    $api_url = $config['darb_assabil_api_base_url'] . '/api/local/service/rates/public/';

	    $args = array(
	        'timeout' => 30,
	        'sslverify' => false,
	        'headers' => array(
	            'Accept' => 'application/json',
	            'Authorization' => 'Bearer ' . $bearer_token
	        )
	    );

	    $response = wp_remote_get($api_url, $args);

	    if (is_wp_error($response)) {
	        error_log('Darb Assabil API Error: ' . $response->get_error_message());
	        return array();
	    }

	    $body = wp_remote_retrieve_body($response);
	    $data = json_decode($body, true);

	    $this->log('API Response: ' . print_r($data, true));

	    if (!empty($data['data']['results'])) {
	        return $data['data']['results'];
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
		
		// Checkboxes need special handling since they don't send a value when unchecked
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
		return isset( $options[$key] ) ? $options[$key] : $default;
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
}