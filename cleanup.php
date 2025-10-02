<?php
// File: cleanup.php
// Version: 9.1 - Security Enhanced
// Last Updated: 2025-10-02

if (!defined('ABSPATH')) {
    exit;
}

// Setup cleanup schedule
function alm_setup_log_cleanup_schedule() {
    if (!wp_next_scheduled('alm_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'alm_cleanup_logs');
    }
}
add_action('wp', 'alm_setup_log_cleanup_schedule');

// Cleanup old logs
function alm_cleanup_old_logs() {
    global $wpdb;
    $days_to_keep = get_option('alm_log_retention_days', 30); // Default 30 hari
    
    // Get current user info for logging
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    
    // Get UTC time
    $utc_time = current_time('mysql', true);
    
    // Log the cleanup action
    $cleanup_message = sprintf(
        'Log cleanup executed by %s at %s. Deleted logs older than %d days.',
        $username,
        $utc_time,
        $days_to_keep
    );
    
    // Delete old logs
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}alm_logs WHERE log_time < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days_to_keep
    ));
    
    // Update cleanup message with results
    $cleanup_message .= sprintf(' %d records deleted.', $deleted);
    
    // SECURITY FIX: Sanitize IP address before inserting to database
    $safe_ip = alm_sanitize_ip();
    
    // Insert cleanup log
    $wpdb->insert(
        $wpdb->prefix . 'alm_logs',
        array(
            'license_key' => 'SYSTEM',
            'action'      => 'cleanup',
            'message'     => $cleanup_message,
            'site_url'    => get_site_url(),
            'ip_address'  => $safe_ip, // FIXED: Now using sanitized IP
            'log_time'    => $utc_time
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );
}
add_action('alm_cleanup_logs', 'alm_cleanup_old_logs');

// Get formatted cleanup info (renamed from alm_get_system_info)
function alm_get_cleanup_info() {
    // Get current user
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    
    // Get UTC time
    $utc_time = current_time('mysql', true);
    
    return array(
        'current_time' => $utc_time,
        'current_user' => $username
    );
}

// Display cleanup info in admin (renamed from alm_display_system_info)
function alm_display_cleanup_info() {
    $info = alm_get_cleanup_info();
    ?>
    <div class="alm-system-info-panel">
        <div class="alm-system-info-item">
            <div class="alm-info-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="alm-info-content">
                <div class="alm-info-label">LAST CLEANUP TIME (UTC)</div>
                <div class="alm-info-value"><?php echo esc_html($info['current_time']); ?></div>
            </div>
        </div>
        <div class="alm-system-info-item">
            <div class="alm-info-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="alm-info-content">
                <div class="alm-info-label">CLEANUP EXECUTED BY</div>
                <div class="alm-info-value"><?php echo esc_html($info['current_user']); ?></div>
            </div>
        </div>
    </div>
    <?php
}

// Add cleanup settings to the plugin settings page
function alm_add_cleanup_settings($settings_array) {
    $settings_array['cleanup'] = array(
        'title' => 'Log Cleanup Settings',
        'fields' => array(
            'alm_log_retention_days' => array(
                'title' => 'Log Retention Period',
                'type' => 'select',
                'options' => array(
                    '7' => '7 days',
                    '14' => '14 days',
                    '30' => '30 days',
                    '60' => '60 days',
                    '90' => '90 days'
                ),
                'default' => '30',
                'description' => 'Logs older than this will be automatically deleted.'
            )
        )
    );
    return $settings_array;
}
add_filter('alm_settings', 'alm_add_cleanup_settings');

// Add manual cleanup action - SECURITY: WITH NONCE VERIFICATION
function alm_manual_cleanup() {
    // SECURITY: Check user capability
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    // SECURITY: Verify nonce
    if (isset($_POST['alm_manual_cleanup']) && 
        isset($_POST['_wpnonce']) &&
        wp_verify_nonce($_POST['_wpnonce'], 'alm_manual_cleanup')) {
        
        alm_cleanup_old_logs();
        
        wp_redirect(add_query_arg('cleanup', 'success', wp_get_referer()));
        exit;
    } else {
        wp_die('Security check failed');
    }
}
add_action('admin_post_alm_manual_cleanup', 'alm_manual_cleanup');

// Add cleanup button to activity log page
function alm_add_cleanup_button() {
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
        <input type="hidden" name="action" value="alm_manual_cleanup">
        <?php wp_nonce_field('alm_manual_cleanup'); ?>
        <button type="submit" name="alm_manual_cleanup" class="button" 
                onclick="return confirm('Are you sure you want to run the cleanup now?');">
            Run Cleanup Now
        </button>
    </form>
    <?php
}

// Add cleanup styles
function alm_add_cleanup_styles() {
    ?>
    <style>
        .alm-system-info-panel {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            background: #1c2834;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .alm-system-info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 20px;
            background: #2a3543;
            border-radius: 8px;
            flex: 1;
        }

        .alm-info-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .alm-info-icon .dashicons {
            color: #ffffff;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        .alm-info-content {
            flex: 1;
        }

        .alm-info-label {
            color: #8b95a5;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .alm-info-value {
            color: #ffffff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: 500;
        }

        @media screen and (max-width: 782px) {
            .alm-system-info-panel {
                flex-direction: column;
                gap: 10px;
            }
            .alm-system-info-item {
                width: 100%;
            }
        }
    </style>
    <?php
}
add_action('admin_head', 'alm_add_cleanup_styles');

// Handler untuk export logs - SECURITY: WITH NONCE VERIFICATION
function handle_alm_export_logs() {
    try {
        // SECURITY: Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'alm_export_logs')) {
            wp_die('Invalid nonce - Security check failed');
        }

        // SECURITY: Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        global $wpdb;
        $log_table = $wpdb->prefix . 'alm_logs';

        // Get all logs
        $logs = $wpdb->get_results("SELECT * FROM $log_table ORDER BY log_time DESC");

        // Ensure no output has been sent
        if (headers_sent()) {
            wp_die('Headers already sent');
        }

        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Generate filename
        $filename = 'activity-logs-' . date('Y-m-d-His') . '.csv';

        // Set headers for CSV download
        nocache_headers(); // WordPress function to set no-cache headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add CSV headers
        fputcsv($output, array(
            'Time (UTC)',
            'Action',
            'License Key',
            'Site URL',
            'IP Address',
            'Message'
        ));

        // Add data rows
        if ($logs) {
            foreach ($logs as $log) {
                fputcsv($output, array(
                    $log->log_time,
                    $log->action,
                    $log->license_key,
                    $log->site_url,
                    $log->ip_address,
                    $log->message
                ));
            }
        }

        // Close the output stream
        fclose($output);
        exit();

    } catch (Exception $e) {
        alm_error_log('Export logs error: ' . $e->getMessage());
        wp_die('Error exporting logs: ' . esc_html($e->getMessage()));
    }
}

// Register the action
add_action('admin_post_alm_export_logs', 'handle_alm_export_logs');