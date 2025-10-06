<?php
/**
 * WooCommerce License Tab - AJAX Handlers
 * Customer Portal Version - NO ADMIN RESTRICTIONS
 * 
 * @package License Manager
 * @version 1.1.0
 * @author anjarsaputra
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove ALL other alm_deactivate_site handlers
 * This ensures ONLY our customer portal handler runs
 */
add_action('plugins_loaded', function() {
    // Remove any conflicting handlers
    remove_all_actions('wp_ajax_alm_deactivate_site');
    remove_all_actions('wp_ajax_nopriv_alm_deactivate_site');
    
    error_log('ALM: Removed all conflicting deactivate handlers');
}, 999);

// Then register our handler with lower priority (runs after removal)
add_action('wp_ajax_alm_deactivate_site', 'alm_customer_deactivate_site', 1000);

/**
 * Deactivate Site AJAX Handler
 * Allows logged-in customers to deactivate their own licenses
 */
function alm_customer_deactivate_site() {
    
    error_log('========================================');
    error_log('ALM CUSTOMER PORTAL: Deactivate Handler');
    error_log('Time: ' . current_time('Y-m-d H:i:s'));
    
    // Verify user logged in
    if (!is_user_logged_in()) {
        error_log('FAILED: User not logged in');
        error_log('========================================');
        wp_send_json_error(array(
            'message' => 'You must be logged in to deactivate sites.'
        ));
        exit;
    }
    
    $current_user = wp_get_current_user();
    error_log('User: ' . $current_user->user_login . ' (ID: ' . $current_user->ID . ')');
    error_log('Email: ' . $current_user->user_email);
    
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    error_log('Nonce received: ' . substr($nonce, 0, 10) . '...');
    
    if (!wp_verify_nonce($nonce, 'alm_license_action')) {
        error_log('FAILED: Nonce verification failed');
        error_log('Expected action: alm_license_action');
        error_log('========================================');
        wp_send_json_error(array(
            'message' => 'Security verification failed. Please refresh the page.'
        ));
        exit;
    }
    
    error_log('SUCCESS: Nonce verified');
    
    // Get POST data
    $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
    $site_id = isset($_POST['site_id']) ? absint($_POST['site_id']) : 0;
    $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
    
    error_log('License Key: ' . $license_key);
    error_log('Site ID: ' . $site_id);
    error_log('Site URL: ' . $site_url);
    
    // Validate input
    if (empty($license_key) || empty($site_id)) {
        error_log('FAILED: Missing parameters');
        error_log('========================================');
        wp_send_json_error(array(
            'message' => 'Invalid request. Missing license key or site ID.'
        ));
        exit;
    }
    
    global $wpdb;
    
    // Check license ownership BY EMAIL (not by admin capability)
    $license_table = $wpdb->prefix . 'alm_licenses';
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$license_table} WHERE license_key = %s AND customer_email = %s",
        $license_key,
        $current_user->user_email
    ));
    
    if (!$license) {
        error_log('FAILED: License not found or unauthorized');
        error_log('Queried email: ' . $current_user->user_email);
        
        // Debug: check if license exists
        $license_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_email FROM {$license_table} WHERE license_key = %s",
            $license_key
        ));
        
        if ($license_exists) {
            error_log('License exists but email mismatch');
            error_log('DB email: ' . $license_exists->customer_email);
            error_log('User email: ' . $current_user->user_email);
        } else {
            error_log('License does not exist in database');
        }
        
        error_log('========================================');
        wp_send_json_error(array(
            'message' => 'This license does not belong to you or does not exist.'
        ));
        exit;
    }
    
    error_log('SUCCESS: License ownership verified');
    error_log('License ID: ' . $license->id);
    error_log('Product: ' . $license->product_name);
    
    // Check activation exists
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
        exit;
    }
    
    error_log('SUCCESS: Activation verified');
    error_log('Activation URL: ' . $activation->site_url);
    
    // Delete activation
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
            'message' => 'Database error. Could not deactivate site.'
        ));
        exit;
    }
    
    error_log('SUCCESS: Activation deleted');
    error_log('Rows deleted: ' . $deleted);
    
    // Update activation count
    $new_count = max(0, intval($license->activations) - 1);
    
    $wpdb->update(
        $license_table,
        array('activations' => $new_count),
        array('id' => $license->id),
        array('%d'),
        array('%d')
    );
    
    error_log('SUCCESS: Activation count updated');
    error_log('Old count: ' . $license->activations);
    error_log('New count: ' . $new_count);
    
    // Clear cache
    $cache_key = 'alm_wc_licenses_' . md5($current_user->user_email);
    delete_transient($cache_key);
    error_log('Cache cleared: ' . $cache_key);
    
    error_log('========================================');
    error_log('DEACTIVATION COMPLETED SUCCESSFULLY!');
    error_log('========================================');
    
    // Success response
    wp_send_json_success(array(
        'message' => 'Site deactivated successfully!',
        'new_count' => $new_count,
        'site_url' => $site_url
    ));
    exit;
}

/**
 * Register AJAX action - HIGHEST PRIORITY
 * This ensures it runs before any other handlers
 */
add_action('wp_ajax_alm_deactivate_site', 'alm_customer_deactivate_site', 1);

// Log registration
error_log('ALM Customer Portal: AJAX handler registered with priority 1');