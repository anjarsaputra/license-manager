<?php
/**
 * File untuk fungsi-fungsi umum Plugin License Manager.
 */

// Exit if accessed directly.
if ( ! defined('ABSPATH') ) {
    exit;
}




// Helper untuk validasi post/get
if (!function_exists('alm_safe_post')) {
    function alm_safe_post($key, $default = '') {
        return isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : $default;
    }
}
if (!function_exists('alm_safe_get')) {
    function alm_safe_get($key, $default = '') {
        return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $default;
    }
}

// Helper error log
if (!function_exists('alm_error_log')) {
    function alm_error_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ALM] ' . $message);
        }
    }
}

function alm_safe_str_replace($search, $replace, $subject) {
    if (!is_string($subject) && !is_array($subject)) {
        $subject = (string) $subject;
    }
    return str_replace($search, $replace, $subject);
}




if ( ! function_exists('normalize_domain') ) {
    /**
     * Menormalkan URL menjadi domain dasar.
     */
    function normalize_domain( $url ) {
        $host = parse_url(trim($url), PHP_URL_HOST) ?: trim($url);
        $host = strtolower($host);
        return preg_replace('/^www\./', '', $host);
    }
}

if ( ! function_exists('alm_api_permission_check') ) {
    /**
     * Memeriksa izin request API berdasarkan daftar secret key yang valid.
     */
    function alm_api_permission_check( WP_REST_Request $request ) {
        $valid_keys = get_option('alm_secret_keys', []);
        if (empty($valid_keys) || !is_array($valid_keys)) {
            return new WP_Error('rest_forbidden', 'Authentication not configured.', ['status' => 500]);
        }
        
        $sent_secret_key = $request->get_header('X-Alm-Secret');

        if ( in_array($sent_secret_key, $valid_keys) ) {
            return true;
        }

        return new WP_Error('rest_forbidden', 'Authentication Failed.', ['status' => 401]);
    }
}


/**
 * Helper untuk rate limiting
 */
function alm_check_rate_limit($license_key) {
    if (empty($license_key)) return false;
    
    $cache_key = 'alm_rate_' . md5($license_key);
    $attempts = get_transient($cache_key);
    
    if ($attempts === false) {
        $attempts = 0;
    }
    
    if ($attempts >= 10) {
        return false;
    }
    
    set_transient($cache_key, $attempts + 1, MINUTE_IN_SECONDS);
    return true;
}

function get_license_manager_info() {
    // Buat objek DateTime dengan timezone Asia/Jakarta (WIB)
    $date_wib = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    
    return array(
        'datetime_wib' => $date_wib->format('Y-m-d H:i:s'), // Format WIB
        'user_login' => wp_get_current_user()->user_login
    );
}

function alm_check_activation_spam($license_key) {
    global $wpdb;
    $ip = $_SERVER['REMOTE_ADDR'];
    $now = current_time('mysql');
    $limit = 10; // max 10 aktivasi per jam per IP

    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}alm_license_activations 
         WHERE site_url = %s 
         AND activated_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
         $ip, $now
    ));
    if($count > $limit) {
        // Catat di log
        $wpdb->insert("{$wpdb->prefix}alm_logs", [
            'log_time' => $now,
            'action' => 'alert',
            'license_key' => $license_key,
            'message' => 'Aktivasi mencurigakan: ' . $count . ' kali dalam 1 jam dari IP ' . $ip
        ]);
        // Bisa juga: blokir/return error/notify admin via email
        return false;
    }
    return true;
}