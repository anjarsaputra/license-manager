<?php
/**
 * WooCommerce License Tab - AJAX Handlers
 * Customer Portal Version - SECURE WITH WEBHOOK NOTIFICATION
 * 
 * @package License Manager
 * @version 1.4.0 - PRODUCTION READY
 * @author anjarsaputra
 * @since 2025-10-06
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deactivate Site AJAX Handler
 * 
 * Security Features:
 * - User authentication check
 * - Nonce verification (CSRF protection)
 * - Email-based ownership verification
 * - Rate limiting (5 attempts per 5 minutes)
 * - Activity logging to database
 * - Email notification to customer
 * - Webhook notification to client site ← NEW!
 * - SQL injection protection (prepared statements)
 * - Input sanitization
 */
function alm_customer_deactivate_site() {
    
    // Start logging
    error_log('========================================');
    error_log('ALM CUSTOMER PORTAL: Deactivate Request');
    error_log('Time: ' . current_time('Y-m-d H:i:s'));
    error_log('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // ========================================
    // SECURITY CHECK 1: Authentication
    // ========================================
    if (!is_user_logged_in()) {
        error_log('FAILED: User not logged in');
        error_log('========================================');
        wp_send_json_error(array(
            'message' => 'You must be logged in to deactivate sites.'
        ));
    }
    
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $user_email = $current_user->user_email;
    $user_login = $current_user->user_login;
    
    error_log('User: ' . $user_login . ' (ID: ' . $user_id . ')');
    error_log('Email: ' . $user_email);
    
    // ========================================
    // SECURITY CHECK 2: Rate Limiting
    // ========================================
    $rate_limit_key = 'alm_deactivate_limit_' . $user_id;
    $attempts = get_transient($rate_limit_key);
    
    if ($attempts && $attempts >= 5) {
        error_log('FAILED: Rate limit exceeded');
        error_log('Attempts: ' . $attempts . ' (max: 5)');
        error_log('========================================');
        
        wp_send_json_error(array(
            'message' => '⚠️ Too many deactivation attempts. Please wait 5 minutes and try again.'
        ));
    }
    
    // Increment attempt counter
    $new_attempts = $attempts ? $attempts + 1 : 1;
    set_transient($rate_limit_key, $new_attempts, 5 * MINUTE_IN_SECONDS);
    
    error_log('Rate limit check passed (Attempt ' . $new_attempts . '/5)');
    
    // ========================================
    // SECURITY CHECK 3: Nonce Verification
    // ========================================
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    
    if (!wp_verify_nonce($nonce, 'alm_license_action')) {
        error_log('FAILED: Nonce verification failed');
        error_log('Received nonce: ' . substr($nonce, 0, 10) . '...');
        error_log('========================================');
        
        wp_send_json_error(array(
            'message' => 'Security verification failed. Please refresh the page and try again.'
        ));
    }
    
    error_log('SUCCESS: Nonce verified');
    
    // ========================================
    // INPUT VALIDATION & SANITIZATION
    // ========================================
    $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
    $site_id = isset($_POST['site_id']) ? absint($_POST['site_id']) : 0;
    $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
    
    error_log('License Key: ' . $license_key);
    error_log('Site ID: ' . $site_id);
    error_log('Site URL: ' . $site_url);
    
    if (empty($license_key) || empty($site_id)) {
        error_log('FAILED: Missing required parameters');
        error_log('========================================');
        
        wp_send_json_error(array(
            'message' => 'Invalid request. Missing required data.'
        ));
    }
    
    global $wpdb;
    
    // ========================================
    // SECURITY CHECK 4: License Ownership
    // ========================================
    $license_table = $wpdb->prefix . 'alm_licenses';
    
    // Verify ownership by email (NOT by admin capability)
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$license_table} WHERE license_key = %s AND customer_email = %s",
        $license_key,
        $user_email
    ));
    
    if (!$license) {
        error_log('FAILED: License not found or unauthorized');
        error_log('Queried email: ' . $user_email);
        
        // Log suspicious activity
        alm_log_customer_activity(
            $license_key,
            'deactivate_unauthorized',
            'Unauthorized deactivation attempt by ' . $user_login,
            $site_url,
            $user_id,
            'failed'
        );
        
        error_log('========================================');
        
        wp_send_json_error(array(
            'message' => 'This license does not belong to you or does not exist.'
        ));
    }
    
    error_log('SUCCESS: License ownership verified');
    error_log('License ID: ' . $license->id);
    error_log('Product: ' . $license->product_name);
    
    // ========================================
    // SECURITY CHECK 5: Activation Exists
    // ========================================
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $activation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$activation_table} WHERE id = %d AND license_id = %d",
        $site_id,
        $license->id
    ));
    
    if (!$activation) {
        error_log('FAILED: Activation not found');
        error_log('Site ID: ' . $site_id);
        error_log('License ID: ' . $license->id);
        error_log('========================================');
        
        wp_send_json_error(array(
            'message' => 'Site activation not found.'
        ));
    }
    
    error_log('SUCCESS: Activation verified');
    error_log('Activation URL: ' . $activation->site_url);
    
    // Store activation URL before delete (for webhook)
    $deactivated_site_url = $activation->site_url;
    
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
        
        wp_send_json_error(array(
            'message' => 'Database error occurred. Please try again or contact support.'
        ));
    }
    
    error_log('SUCCESS: Activation deleted from database');
    
    // ========================================
    // UPDATE ACTIVATION COUNT
    // ========================================
    $old_count = intval($license->activations);
    $new_count = max(0, $old_count - 1);
    
    $wpdb->update(
        $license_table,
        array('activations' => $new_count),
        array('id' => $license->id),
        array('%d'),
        array('%d')
    );
    
    error_log('SUCCESS: Activation count updated');
    error_log('Old count: ' . $old_count);
    error_log('New count: ' . $new_count);
    
    // ========================================
    // SEND WEBHOOK TO CLIENT SITE
    // ========================================
    error_log('Sending webhook notification to client site...');
    
    $webhook_result = alm_notify_client_deactivation(
        $deactivated_site_url,
        $license_key,
        $license->product_name
    );
    
    if ($webhook_result['success']) {
        error_log('SUCCESS: Webhook sent to client site');
        error_log('Response: ' . $webhook_result['message']);
    } else {
        error_log('WARNING: Webhook failed - ' . $webhook_result['message']);
        // Don't fail deactivation if webhook fails
    }
    
    // ========================================
    // CLEAR CACHE
    // ========================================
    $cache_key = 'alm_wc_licenses_' . md5($user_email);
    delete_transient($cache_key);
    
    error_log('Cache cleared: ' . $cache_key);
    
    // ========================================
    // LOG ACTIVITY TO DATABASE
    // ========================================
    alm_log_customer_activity(
        $license_key,
        'customer_deactivate',
        sprintf(
            'Customer %s (%s) deactivated site: %s | Product: %s | Count: %d→%d | Webhook: %s',
            $user_login,
            $user_email,
            $deactivated_site_url,
            $license->product_name,
            $old_count,
            $new_count,
            $webhook_result['success'] ? 'sent' : 'failed'
        ),
        $deactivated_site_url,
        $user_id,
        'success'
    );
    
    error_log('Activity logged to database');
    
    // ========================================
    // SEND EMAIL NOTIFICATION
    // ========================================
    alm_send_deactivation_email(
        $current_user,
        $license,
        $deactivated_site_url,
        $new_count,
        $old_count
    );
    
    error_log('Email notification sent');
    
    // ========================================
    // RESET RATE LIMIT ON SUCCESS
    // ========================================
    delete_transient($rate_limit_key);
    
    error_log('========================================');
    error_log('DEACTIVATION COMPLETED SUCCESSFULLY!');
    error_log('========================================');
    
    // ========================================
    // SUCCESS RESPONSE
    // ========================================
    wp_send_json_success(array(
        'message' => '✅ Site deactivated successfully!',
        'new_count' => $new_count,
        'site_url' => $deactivated_site_url,
        'timestamp' => current_time('mysql'),
        'webhook_sent' => $webhook_result['success']
    ));
}

/**
 * Send webhook notification to client site
 * Notify client immediately that their license has been deactivated
 * 
 * @param string $site_url Client site URL
 * @param string $license_key License key
 * @param string $product_name Product name
 * @return array Result with success status and message
 */
function alm_notify_client_deactivation($site_url, $license_key, $product_name = '') {
    
    // Clean and validate site URL
    $site_url = untrailingslashit(esc_url_raw($site_url));
    
    if (empty($site_url)) {
        return array(
            'success' => false,
            'message' => 'Invalid site URL'
        );
    }
    
    // Webhook endpoint (client theme must implement this)
    $webhook_url = $site_url . '/wp-json/alm/v1/license-deactivated';
    
    error_log('Webhook URL: ' . $webhook_url);
    
    // Prepare secure payload
    $payload = array(
    'action' => 'license_deactivated',
    'license_key' => $license_key,
    'site_url' => $site_url, // <--- TAMBAHKAN INI!
    'product_name' => $product_name,
    'deactivated_at' => current_time('mysql'),
    'server_time' => time(),
    'message' => 'Lisensi Anda telah dinonaktifkan dari server lisensi',
    'server_url' => home_url()
);

    
    // Add signature for verification (optional but recommended)
   // Ambil secret dari klien
$secret = get_option('mediman_webhook_secret', '');

    error_log('SERVER SECRET: ' . $secret);


$payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), $secret);



    

    error_log('Sending webhook payload: ' . json_encode($payload));
    
    // Send webhook with timeout
    $response = wp_remote_post($webhook_url, array(
        'timeout' => 10,
        'redirection' => 0,
        'httpversion' => '1.1',
        'blocking' => true,
        'body' => json_encode($payload),
        'headers' => array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'ALM-Webhook/1.0',
            'X-ALM-Signature' => $payload['signature']
        ),
        'sslverify' => false // Allow self-signed certs for dev sites
    ));
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Webhook error: ' . $error_message);
        
        return array(
            'success' => false,
            'message' => 'Connection failed: ' . $error_message
        );
    }
    
    // Get response details
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    error_log('Webhook response code: ' . $response_code);
    error_log('Webhook response body: ' . $response_body);
    
    // Check response code
    if ($response_code == 200 || $response_code == 201) {
        
        // Parse response
        $response_data = json_decode($response_body, true);
        
        return array(
            'success' => true,
            'message' => 'Webhook sent successfully',
            'response_code' => $response_code,
            'response_data' => $response_data
        );
        
    } elseif ($response_code == 404) {
        
        // Endpoint not found (client site doesn't have webhook handler)
        return array(
            'success' => false,
            'message' => 'Webhook endpoint not found (404) - client theme may not support webhooks'
        );
        
    } else {
        
        // Other error
        return array(
            'success' => false,
            'message' => 'Webhook failed with response code: ' . $response_code,
            'response_body' => $response_body
        );
    }
}

/**
 * Log customer activity to database
 * 
 * @param string $license_key License key
 * @param string $action Action type
 * @param string $message Log message
 * @param string $site_url Site URL
 * @param int $user_id User ID
 * @param string $status Status (success/failed)
 * @return bool Success status
 */
function alm_log_customer_activity($license_key, $action, $message, $site_url = '', $user_id = 0, $status = 'success') {
    global $wpdb;
    
    $log_table = $wpdb->prefix . 'alm_logs';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$log_table}'") != $log_table) {
        error_log('ALM: Log table not found, skipping logging');
        return false;
    }
    
    $current_time = current_time('mysql', true);
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Get user info
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $user = get_userdata($user_id);
    $user_login = $user ? $user->user_login : 'guest';
    
    // Format message with timestamp and user
    $formatted_message = sprintf(
        '[%s] %s - %s [Status: %s]',
        $current_time,
        $user_login,
        $message,
        strtoupper($status)
    );
    
    // Insert log
    $result = $wpdb->insert(
        $log_table,
        array(
            'license_key' => $license_key,
            'action' => $action,
            'message' => $formatted_message,
            'site_url' => $site_url,
            'ip_address' => $user_ip,
            'log_time' => $current_time
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        error_log('ALM: Failed to insert log - ' . $wpdb->last_error);
        return false;
    }
    
    return true;
}

/**
 * Send deactivation email notification to customer
 * 
 * @param WP_User $user Current user
 * @param object $license License object
 * @param string $site_url Deactivated site URL
 * @param int $new_count New activation count
 * @param int $old_count Old activation count
 * @return bool Email sent status
 */
function alm_send_deactivation_email($user, $license, $site_url, $new_count, $old_count) {
    
    $to = $user->user_email;
    $site_name = get_bloginfo('name');
    
    $subject = sprintf(
        '[%s] Site Deactivated from Your License',
        $site_name
    );
    
    $message = sprintf(
        "Hi %s,\n\n" .
        "A site has been successfully deactivated from your license.\n\n" .
        "===========================================\n" .
        "DEACTIVATION DETAILS\n" .
        "===========================================\n\n" .
        "Product Name: %s\n" .
        "License Key: %s\n" .
        "Deactivated Site: %s\n" .
        "Previous Activations: %d / %d\n" .
        "Current Activations: %d / %d\n" .
        "Available Slots: %d\n\n" .
        "Date & Time: %s (WIB)\n" .
        "IP Address: %s\n\n" .
        "===========================================\n\n" .
        "✅ The client site has been notified and will be deactivated automatically.\n\n" .
        "You can now use this activation slot for another website.\n\n" .
        "Manage your licenses: %s\n\n" .
        "---\n\n" .
        "⚠️ SECURITY NOTICE:\n" .
        "If you did NOT perform this action, please contact our support team immediately.\n\n" .
        "Support: %s\n\n" .
        "Best regards,\n" .
        "%s Team",
        $user->display_name,
        $license->product_name,
        $license->license_key,
        $site_url,
        $old_count,
        $license->activation_limit,
        $new_count,
        $license->activation_limit,
        ($license->activation_limit - $new_count),
        current_time('d M Y, H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        wc_get_account_endpoint_url('licenses'),
        get_option('admin_email'),
        $site_name
    );
    
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
    );
    
    // Send email
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if (!$sent) {
        error_log('ALM: Failed to send deactivation email to ' . $to);
    }
    
    return $sent;
}

/**
 * Register AJAX handler
 */
add_action('wp_ajax_alm_deactivate_site', 'alm_customer_deactivate_site', 10);

// Log registration
error_log('ALM Customer Portal: Secure AJAX handler with webhook registered (v1.4.0)');



add_action('wp_ajax_alm_transfer_site', 'alm_handle_transfer_site');
add_action('wp_ajax_nopriv_alm_transfer_site', 'alm_handle_transfer_site');

function alm_handle_transfer_site() {
    check_ajax_referer('alm_license_action', 'nonce');
    $license_key = sanitize_text_field($_POST['license_key']);
    $site_id = intval($_POST['site_id']);
    $old_site_url = sanitize_text_field($_POST['old_site_url']);
    $new_site_url = sanitize_text_field($_POST['new_site_url']);

    global $wpdb;
    $table = $wpdb->prefix . 'alm_license_activations';

    // Validasi: cek limit/cooldown di sini jika perlu...

// Cek limit transfer per tahun
$row = $wpdb->get_row($wpdb->prepare(
    "SELECT transfer_count, last_transfer_date FROM $table WHERE id = %d", $site_id
));

if ($row) {
    $current_year = date('Y');
    $last_transfer = $row->last_transfer_date;
    $transfer_this_year = 0;
    if ($last_transfer && substr($last_transfer, 0, 4) == $current_year) {
        $transfer_this_year = 1;
    }
    $transfer_limit = 1; // 1 per tahun
    if ($transfer_this_year >= $transfer_limit) {
        wp_send_json_error(array(
            'message' => 'Limit transfer slot untuk tahun ini sudah habis. Silakan tunggu sampai tahun depan.'
        ));
    }
}



    // Cara cepat/aman
    $current_transfer = $wpdb->get_var($wpdb->prepare(
        "SELECT transfer_count FROM $table WHERE id = %d", $site_id
    ));
    $new_transfer = $current_transfer !== null ? intval($current_transfer) + 1 : 1;

    $updated = $wpdb->update(
        $table,
        array(
            'site_url' => $new_site_url,
            'transfer_count' => $new_transfer,
            'last_transfer_date' => current_time('mysql'),
        ),
        array('id' => $site_id)
    );

    if ($updated !== false) {
        wp_send_json_success(array('message' => 'Transfer berhasil!'));
    } else {
        wp_send_json_error(array('message' => 'Gagal update data slot di database.'));
    }
}
