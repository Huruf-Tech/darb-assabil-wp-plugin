<?php
namespace DarbAssabil;

// Register the shortcode on init
add_action('init', function() {
    add_shortcode('darb_assabil_tracking', __NAMESPACE__ . '\\darb_assabil_tracking_shortcode');
    add_action('wp_enqueue_scripts', function() {
        wp_enqueue_style('darb-assabil-tracking', plugin_dir_url(__DIR__) . 'assets/css/tracking.css');
        wp_enqueue_script('darb-assabil-tracking', plugin_dir_url(__DIR__) . 'assets/js/tracking.js', ['jquery'], null, true);
        wp_localize_script('darb-assabil-tracking', 'darbAssabilTracking', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('darb_assabil_tracking')
        ]);
    });
    add_action('wp_ajax_darb_assabil_tracking', __NAMESPACE__ . '\\darb_assabil_tracking_ajax');
    add_action('wp_ajax_nopriv_darb_assabil_tracking', __NAMESPACE__ . '\\darb_assabil_tracking_ajax');
});

function darb_assabil_tracking_shortcode($atts) {
    ob_start(); ?>
    <div class="darb-assabil-tracking-box">
        <form class="darb-assabil-tracking-form">
            <div class="darb-assabil-tracking-row">
                <input 
                    type="text" 
                    id="darb-assabil-tracking-input" 
                    class="darb-assabil-tracking-input input" 
                    placeholder="<?php esc_attr_e('Enter tracking number', 'darb-assabil'); ?>" 
                />
                <button
                    type="button"
                    id="darb-assabil-tracking-btn"
                    class="darb-assabil-tracking-btn button"
                >
                    <?php esc_html_e('Track', 'darb-assabil'); ?>
                </button>
            </div>
        </form>
        <h3 class="darb-assabil-tracking-title"><?php esc_html_e('FOR ORDER STATUS INQUIRY', 'darb-assabil'); ?></h3>
        <div id="darb-assabil-tracking-result"></div>
    </div>
    <?php
    return ob_get_clean();
}

function darb_assabil_tracking_ajax() {
    check_ajax_referer('darb_assabil_tracking', 'nonce');
    $ref = isset($_POST['reference']) ? sanitize_text_field(wp_unslash($_POST['reference'])) : '';
    if (!$ref) {
        wp_send_json_error('Reference number required.');
    }

    // Call your API (adjust endpoint as needed)
    $api_url = get_config('server_base_url') . '/api/darb/assabil/order/shipment/timeline';
    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['reference' => $ref, 'token' => get_access_token()]),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Could not connect to tracking service.');
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($data['status']) || empty($data['data'])) {
        wp_send_json_error('Tracking info not found.');
    }

    // Build tracking header
    $output = '<div class="tracking-container">';
    $output .= '<div class="header">';
    $output .= '<h3>' . esc_html__('Track #', 'darb-assabil') . esc_html($ref) . '</h3>';
    $output .= '<button class="close-btn" type="button" aria-label="Close">';
    $output .= '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" style="color: #6b7280;">';
    $output .= '<path opacity=".4" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z" fill="currentColor"></path>';
    $output .= '<path d="m13.06 12 2.3-2.3c.29-.29.29-.77 0-1.06a.754.754 0 0 0-1.06 0l-2.3 2.3-2.3-2.3a.754.754 0 0 0-1.06 0c-.29.29-.29.77 0 1.06l2.3 2.3-2.3 2.3c-.29.29-.29.77 0 1.06.15.15.34.22.53.22s.38-.07.53-.22l2.3-2.3 2.3 2.3c.15.15.34.22.53.22s.38-.07.53-.22c.29-.29.29-.77 0-1.06l-2.3-2.3Z" fill="currentColor"></path>';
    $output .= '</svg>';
    $output .= '</button>';
    $output .= '</div>'; // .header

    $output .= '<div class="content"><div class="timeline">';

    if (!empty($data['data']['timeline'])) {
        foreach ($data['data']['timeline'] as $event) {
            // Prepare values
            $created_by = '';
            if (!empty($event['createdBy']['fname']) || !empty($event['createdBy']['lname'])) {
                $created_by = trim(($event['createdBy']['fname'] ?? '') . ' ' . ($event['createdBy']['lname'] ?? ''));
            }
            // Avatar
            $avatar_url = $event['createdBy']['avatar']['url'] ?? '';
            $initials = '';
            if ($created_by) {
                $words = explode(' ', $created_by);
                foreach ($words as $w) {
                    $initials .= mb_substr($w, 0, 1);
                }
            }
            $avatar_html = $avatar_url
                ? '<img class="avatar-img" src="' . esc_url($avatar_url) . '" alt="' . esc_attr($created_by) . '">'
                : '<div class="avatar-initials">' . esc_html($initials ?: '?') . '</div>';

            // Status badge
            $status_type = strtolower($event['type'] ?? '');
            $status_class = 'status-badge status-' . $status_type;
            $status_html = '<span class="' . esc_attr($status_class) . '">' . esc_html($event['type'] ?? '') . '</span>';

            // Date
            $date = !empty($event['timestamp']) 
                ? gmdate('M j, Y g:i A', strtotime($event['timestamp'])) 
                : '';

            // Description
            $desc = !empty($event['description']['en']) ? esc_html($event['description']['en']) : '';

            // Remarks
            $remarks = !empty($event['remarks']) ? '<p class="timeline-remarks">Remarks: ' . esc_html($event['remarks']) . '</p>' : '';

            // Branch (handlerAccount)
            $branch_html = '';
            if (!empty($event['handlerAccount']['name'])) {
                $branch_name = esc_html($event['handlerAccount']['name']);
                $branch_initials = '';
                $words = explode(' ', $branch_name);
                foreach ($words as $w) {
                    $branch_initials .= mb_substr($w, 0, 1);
                }
                $branch_html = '<div class="branch-badge"><div class="branch-avatar"><div class="branch-avatar-initials">' . esc_html($branch_initials ?: '?') . '</div></div>' . $branch_name . '</div>';
            }

            // Modified badge (for events with metadata.modified)
            $modified_html = '';
            if (!empty($event['metadata']['modified'])) {
                $modified_html = '<div class="modified-items"><p class="modified-label">Modified</p>';
                if (!empty($event['metadata']['modifications']['notes'])) {
                    $modified_html .= '<div class="modified-badge">' . esc_html($event['metadata']['modifications']['notes']) . '</div>';
                }
                $modified_html .= '</div>';
            }

            // Timeline item
            $output .= '<div class="timeline-item">';
            $output .= '<hr class="timeline-line">';
            $output .= '<div class="timeline-content">';
            $output .= '<div class="avatar-container">';
            $output .= '<div class="avatar">' . $avatar_html . '</div>';
            $output .= '<div class="timeline-details">';
            $output .= '<h3>' . esc_html($created_by) . ' ' . $status_html . '</h3>';
            $output .= '<time class="timeline-time">' . esc_html($date) . '</time>';
            if ($desc) {
                $output .= '<p class="timeline-message">' . $desc . '</p>';
            }
            if ($remarks) {
                $output .= $remarks;
            }
            if ($modified_html) {
                $output .= $modified_html;
            }
            if ($branch_html) {
                $output .= '<div class="flex items-center gap-2 pt-2">' . $branch_html . '</div>';
            } else {
                $output .= '<div class="flex items-center gap-2 pt-2"></div>';
            }
            $output .= '</div>'; // .timeline-details
            $output .= '</div>'; // .avatar-container
            $output .= '</div>'; // .timeline-content
            $output .= '</div>'; // .timeline-item
        }
    }

    $output .= '</div>'; // .timeline

    // $output .= '<div class="footer"><button class="dismiss-btn">' . esc_html__('Dismiss', 'darb-assabil') . '</button></div>';
    $output .= '</div>'; // .content
    $output .= '</div>'; // .tracking-container

    wp_send_json_success($output);
}

// // Log messages for debugging
// /**
//  * Log messages for debugging
//  *
//  * @param string $message The message to log.
//  */
// function darb_assabil_log($message) {
//     $log_file = plugin_dir_path(__FILE__) . '../debug-plugin.log'; // Path to the debug-plugin.log file
//     $timestamp = gmdate('Y-m-d H:i:s'); // Add a timestamp to each log entry
//     $formatted_message = "[{$timestamp}] {$message}" . PHP_EOL;
//
//     // Write the log message to the file
//     file_put_contents($log_file, $formatted_message, FILE_APPEND);
// }
