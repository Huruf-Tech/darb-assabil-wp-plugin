<?php

namespace DarbAssabil;

/**
 * Get a configuration value from the config.php file.
 *
 * @param string $key The configuration key to retrieve.
 * @param mixed $default The default value to return if the key is not found.
 * @return mixed The configuration value or the default value.
 */
function get_config($key, $default = null) {
    static $config = null;

    if ($config === null) {
        $config = include plugin_dir_path(__DIR__) . 'config.php';
    }

    return $config[$key] ?? $default;
}

/**
 * Extract city and area from a formatted string.
 *
 * @param string $city_full The full city string in the format "city::area".
 * @return array An associative array with 'city' and 'area' keys.
 */
function extract_city_and_area($city_full) {
    $city_parts = explode('::', $city_full);
    return [
        'city' => $city_parts[0] ?? '', // Extract the city
        'area' => $city_parts[1] ?? '', // Extract the area
    ];
}

/**
 * Get the Darb Assabil access token from the WordPress options.
 *
 * @return string The access token or an empty string if not set.
 */
function get_access_token() {
    return get_option('darb_assabil_access_token', '');
}

/**
 * Get a Darb Assabil plugin option from the WordPress options.
 *
 * @param string $key The option key to retrieve.
 * @param mixed $default The default value to return if the option is not set.
 * @return mixed The option value or the default value.
 */
function get_plugin_option() {
    return [
        'service' => get_option('darb_assabil_service_id', ''),
        'include_product_payment' => get_option('darb_assabil_include_product_payment', ''),
        'payment_done_by_receiver' => get_option('darb_assabil_payment_done_by_receiver', '')
    ];
}



