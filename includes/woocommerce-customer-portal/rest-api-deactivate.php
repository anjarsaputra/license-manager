<?php
/**
 * Custom REST API for License Deactivation
 * 
 * Features:
 * - Customer self-deactivate
 * - Rate limiting (uses global alm_check_rate_limit)
 * - Activity logging (uses global alm_log_activity)
 * - Email notifications
 * 
 * @package License Manager
 * @version 1.0.1
 * @author anjarsaputra
 * @since 2025-10-04
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom REST API endpoints
 */
add_action('rest_api_init', function() {
    // Deactivate site endpoint
    register_rest_route('alm/v1', '/deactivate-site', array(
        'methods' => 'POST',
        'callback' => 'alm_rest_deactivate_site',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ));
});

/**
 * REST API: Deactivate Site
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function alm_rest_deactivate_site($request) {
    global $wpdb;
    
    // Get params
    $params = $request->get_json_params();
    $nonce = $params['nonce'] ?? '';
    $license_key = sanitize_text_field($params['license_key'] ?? '');
    $site_id = absint($params['site_id'] ?? 0);
    $site_url = esc_url_raw($params['site_url'] ?? '');
    
    $current_user = wp_get_current_user();
    
    // Get user IP (use global function if exists)
    $user_ip = function_exists('alm_get_user_ip') 
        ? alm_get_user_ip() 
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    
    // Log request
    error_log('========================================');
    error_log('ALM REST API: Deactivate Site Request');
    error_log('Time: ' . current_time('Y-m-d H:i:s'));
    error_log('User: ' . $current_user->user_login . ' (ID: ' . $current_user->ID . ')');
    error_log('Email: ' . $current_user->user_email);
    error_log('IP: ' . $user_ip);
    error_log('License Key: ' . $license_key);
    error_log('Site ID: ' . $site_id);
    error_log('Site URL: ' . $site_url);
    
    // ========================================
    // SECURITY CHECK 1: Verify Nonce
    // ========================================
    if (!wp_verify_nonce($nonce, 'alm_license_action')) {
        error_log('FAILED: Invalid nonce');
        error_log('========================================');
        
        return new WP_Error(
            'invalid_nonce',
            __('Security verification failed. Please refresh the page and try again.', 'alm'),
            array('status' => 403)
        );
    }
    
    // ========================================
    // SECURITY CHECK 2: Rate Limiting
    // ========================================
    if (function_exists('alm_check_rate_limit')) {
        $rate_limit_result = alm_check_rate_limit($current_user->ID, 'deactivate', 5, 300);
        
        if (is_wp_error($rate_limit_result)) {
            error_log('FAILED: Rate limit exceeded');
            error_log('========================================');
            
            return $rate_limit_result;
        }
    }
    
    // ========================================
    // VALIDATION: Required Parameters
    // ========================================
    if (empty($license_key) || empty($site_id)) {
        error_log('FAILED: Missing parameters');
        error_log('========================================');
        
        return new WP_Error(
            'missing_params',
            __('Missing required parameters.', 'alm'),
            array('status' => 400)
        );
    }
    
    // ========================================
    // SECURITY CHECK 3: Verify License Ownership
    // ========================================
    $license_table = $wpdb->prefix . 'alm_licenses';
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$license_table} 
        WHERE license_key = %s AND customer_email = %s",
        $license_key,
        $current_user->user_email
    ));
    
    if (!$license) {
        error_log('FAILED: License not found or unauthorized');
        error_log('Queried email: ' . $current_user->user_email);
        error_log('========================================');
        
        // Log suspicious activity
        if (function_exists('alm_log_activity')) {
            alm_log_activity(array(
                'user_id' => $current_user->ID,
                'action' => 'deactivate_failed',
                'license_key' => $license_key,
                'reason' => 'Unauthorized - License not owned by user',
                'ip_address' => $user_ip,
                'status' => 'failed'
            ));
        }
        
        return new WP_Error(
            'unauthorized',
            __('This license does not belong to you or does not exist.', 'alm'),
            array('status' => 403)
        );
    }
    
    error_log('SUCCESS: License verified (ID: ' . $license->id . ')');
    
    // ========================================
    // SECURITY CHECK 4: Verify Activation Exists
    // ========================================
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    $activation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$activation_table} 
        WHERE id = %d AND license_id = %d",
        $site_id,
        $license->id
    ));
    
    if (!$activation) {
        error_log('FAILED: Activation not found');
        error_log('Site ID: ' . $site_id);
        error_log('License ID: ' . $license->id);
        error_log('========================================');
        
        return new WP_Error(
            'not_found',
            __('Site activation not found.', 'alm'),
            array('status' => 404)
        );
    }
    
    error_log('SUCCESS: Activation verified');
    
    // ========================================
    // DELETE ACTIVATION
    // ========================================
    $deleted = $wpdb->delete(
        $activation_table,
        array('id' => $site_id),
        array('%d')
    );
    
    if ($deleted === false) {
        error_log('FAILED: Database delete error');
        error_log('Error: ' . $wpdb->last_error);
        error_log('========================================');
        
        return new WP_Error(
            'db_error',
            __('Database error occurred. Please try again or contact support.', 'alm'),
            array('status' => 500)
        );
    }
    
    // === Kirim webhook ke klien ===
$webhook_url = rtrim($site_url, '/').'/wp-json/alm/v1/license-deactivated';
$webhook_secret = 'mediman_webhook_2760ee05bbac6c3a069d540ab3ed50c4';

// Payload WAJIB urut dan sama dengan ekspektasi klien!
$payload = [
    'action'        => 'license_deactivated',
    'license_key'   => $license_key,
    'site_url'      => $site_url,
    'deactivated_at'=> gmdate('Y-m-d H:i:s'),
    'product_name'  => $license->product_name ?? 'Mediman',
    'server_url'    => home_url(),
    'server_time'   => time(),
    'message'       => 'Dinonaktifkan dari user portal Akun'
];

// Signature HMAC
error_log('SERVER SECRET: ' . $webhook_secret);
$signature = hash_hmac('sha256', json_encode($payload), $webhook_secret);
$payload['signature'] = $signature;

// Kirim POST pakai wp_remote_post agar tetap di WordPress:
$response = wp_remote_post($webhook_url, [
    'method'    => 'POST',
    'body'      => json_encode($payload),
    'headers'   => [
        'Content-Type' => 'application/json',
    ],
    'timeout'   => 10,
    'data_format' => 'body'
]);
    
    error_log('SUCCESS: Activation deleted from database');
    
    // ========================================
    // UPDATE ACTIVATION COUNT
    // ========================================
    $new_count = max(0, intval($license->activations) - 1);
    
    $updated = $wpdb->update(
        $license_table,
        array('activations' => $new_count),
        array('id' => $license->id),
        array('%d'),
        array('%d')
    );
    
    error_log('SUCCESS: Activation count updated to ' . $new_count);
    
    // ========================================
    // CLEAR CACHE
    // ========================================
    $cache_key = 'alm_wc_licenses_' . md5($current_user->user_email);
    delete_transient($cache_key);
    
    error_log('Cache cleared: ' . $cache_key);
    
    // ========================================
    // LOG ACTIVITY
    // ========================================
    if (function_exists('alm_log_activity')) {
        alm_log_activity(array(
            'user_id' => $current_user->ID,
            'action' => 'deactivate_site',
            'license_id' => $license->id,
            'license_key' => $license_key,
            'site_url' => $site_url,
            'site_id' => $site_id,
            'ip_address' => $user_ip,
            'status' => 'success',
            'metadata' => array(
                'old_count' => $license->activations,
                'new_count' => $new_count
            )
        ));
        
        error_log('Activity logged');
    }
    
    // ========================================
    // SEND EMAIL NOTIFICATION (Optional)
    // ========================================
    if (apply_filters('alm_send_deactivation_email', true)) {
        alm_rest_send_deactivation_email(array(
            'user' => $current_user,
            'license' => $license,
            'site_url' => $site_url,
            'new_count' => $new_count
        ));
        
        error_log('Email notification sent');
    }
    
    error_log('========================================');
    error_log('DEACTIVATION COMPLETED SUCCESSFULLY');
    error_log('========================================');
    
    // ========================================
    // RETURN SUCCESS RESPONSE
    // ========================================
    return new WP_REST_Response(array(
        'success' => true,
        'message' => __('Site deactivated successfully!', 'alm'),
        'data' => array(
            'new_count' => $new_count,
            'site_url' => $site_url,
            'license_key' => $license_key,
            'timestamp' => current_time('mysql')
        )
    ), 200);
}

/**
 * Send Deactivation Email Notification
 * (Unique name to avoid conflict)
 * 
 * @param array $args Email arguments
 */
function alm_rest_send_deactivation_email($args) {
    $user = $args['user'];
    $license = $args['license'];
    $site_url = $args['site_url'];
    $new_count = $args['new_count'];
    
    $to = $user->user_email;
    $subject = sprintf(
        __('[%s] Site Deactivated from License', 'alm'),
        get_bloginfo('name')
    );
    
    $message = sprintf(
        __("Hello %s,\n\nA site has been deactivated from your license.\n\nDetails:\n- Product: %s\n- License Key: %s\n- Site URL: %s\n- Remaining Activations: %d / %d\n- Time: %s\n\nIf this wasn't you, please contact support immediately.\n\nThank you!", 'alm'),
        $user->display_name,
        $license->product_name,
        $license->license_key,
        $site_url,
        $new_count,
        $license->activation_limit,
        current_time('Y-m-d H:i:s')
    );
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    wp_mail($to, $subject, $message, $headers);
}

/**
 * Create Activity Log Table (if not exists)
 */
function alm_rest_create_activity_log_table() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'alm_activity_log';
    
    // Check if table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
        return; // Already exists
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned DEFAULT NULL,
        action varchar(50) NOT NULL,
        license_id bigint(20) unsigned DEFAULT NULL,
        license_key varchar(100) DEFAULT NULL,
        site_url varchar(255) DEFAULT NULL,
        site_id bigint(20) unsigned DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        status varchar(20) DEFAULT 'success',
        reason text DEFAULT NULL,
        metadata longtext DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY license_id (license_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log('Activity log table created: ' . $table);
}

// Create table on plugin activation
register_activation_hook(__FILE__, 'alm_rest_create_activity_log_table');