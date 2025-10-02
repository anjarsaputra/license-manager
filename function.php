<?php
/**
 * File untuk fungsi-fungsi umum Plugin License Manager.
 * Version: 9.1 - Security Enhanced
 * Last Updated: 2025-10-02
 */

// Exit if accessed directly.
if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * =====================================================
 * SECURITY HELPER FUNCTIONS - NEW!
 * =====================================================
 */

/**
 * Sanitize IP Address - SECURITY FIX
 * Mencegah SQL injection dari IP address
 */
if (!function_exists('alm_sanitize_ip')) {
    function alm_sanitize_ip($ip = null) {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        // Validate IP address format
        $validated_ip = filter_var($ip, FILTER_VALIDATE_IP);
        
        if ($validated_ip === false) {
            alm_error_log('Invalid IP address detected: ' . $ip);
            return '0.0.0.0';
        }
        
        return sanitize_text_field($validated_ip);
    }
}

/**
 * Validate License Key Format - SECURITY FIX
 * Memastikan license key sesuai format yang diizinkan
 */
if (!function_exists('alm_validate_license_key')) {
    function alm_validate_license_key($license_key) {
        if (empty($license_key)) {
            return false;
        }
        
        // License key harus alphanumeric dan dash, minimal 20 karakter
        if (!preg_match('/^[A-Z0-9\-]{20,}$/i', $license_key)) {
            alm_error_log('Invalid license key format: ' . substr($license_key, 0, 10) . '...');
            return false;
        }
        
        return true;
    }
}

/**
 * Validate Domain/Site URL - SECURITY FIX
 * Block localhost dan development domains untuk produksi
 */
if (!function_exists('alm_validate_domain')) {
    function alm_validate_domain($site_url) {
        if (empty($site_url)) {
            return false;
        }
        
        // List domain yang diblokir (development environments)
        $blocked_domains = array(
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            '.test',
            '.dev',
            '.example',
            '192.168.',
            '10.0.',
            '172.16.'
        );
        
        $site_url_lower = strtolower($site_url);
        
        foreach ($blocked_domains as $blocked) {
            if (stripos($site_url_lower, $blocked) !== false) {
                alm_error_log('Blocked domain detected: ' . $site_url);
                return false;
            }
        }
        
        // Validate URL format
        if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
            alm_error_log('Invalid URL format: ' . $site_url);
            return false;
        }
        
        return true;
    }
}

/**
 * Validate Email Address - SECURITY FIX
 */
if (!function_exists('alm_validate_email')) {
    function alm_validate_email($email) {
        if (empty($email)) {
            return false;
        }
        
        $sanitized = sanitize_email($email);
        
        if (!is_email($sanitized)) {
            alm_error_log('Invalid email format: ' . $email);
            return false;
        }
        
        return $sanitized;
    }
}

/**
 * Verify Nonce Wrapper - SECURITY FIX
 */
if (!function_exists('alm_verify_nonce')) {
    function alm_verify_nonce($nonce, $action) {
        if (empty($nonce)) {
            alm_error_log('Missing nonce for action: ' . $action);
            return false;
        }
        
        $verified = wp_verify_nonce($nonce, $action);
        
        if (!$verified) {
            alm_error_log('Invalid nonce for action: ' . $action);
        }
        
        return $verified;
    }
}

/**
 * =====================================================
 * EXISTING HELPER FUNCTIONS - ENHANCED
 * =====================================================
 */

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
        // SECURITY: Validate URL first
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $url = 'http://' . $url; // Try adding protocol
        }
        
        $host = parse_url(trim($url), PHP_URL_HOST) ?: trim($url);
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        
        return sanitize_text_field($host);
    }
}

if ( ! function_exists('alm_api_permission_check') ) {
    /**
     * Memeriksa izin request API berdasarkan daftar secret key yang valid.
     */
    function alm_api_permission_check( WP_REST_Request $request ) {
        $valid_keys = get_option('alm_secret_keys', []);
        if (empty($valid_keys) || !is_array($valid_keys)) {
            alm_error_log('API authentication not configured');
            return new WP_Error('rest_forbidden', 'Authentication not configured.', ['status' => 500]);
        }
        
        $sent_secret_key = $request->get_header('X-Alm-Secret');
        
        if (empty($sent_secret_key)) {
            alm_error_log('Missing X-Alm-Secret header');
            return new WP_Error('rest_forbidden', 'Missing authentication header.', ['status' => 401]);
        }

        // SECURITY: Use timing-safe comparison
        $is_valid = false;
        foreach ($valid_keys as $valid_key) {
            if (hash_equals($valid_key, $sent_secret_key)) {
                $is_valid = true;
                break;
            }
        }

        if ($is_valid) {
            return true;
        }

        alm_error_log('Authentication failed - Invalid secret key');
        return new WP_Error('rest_forbidden', 'Authentication Failed.', ['status' => 401]);
    }
}

/**
 * =====================================================
 * RATE LIMITING - ENHANCED SECURITY
 * =====================================================
 */

/**
 * Enhanced Rate Limiting - SECURITY FIX
 * Check both license key AND IP address
 */
function alm_check_rate_limit($license_key, $check_ip = true) {
    if (empty($license_key)) {
        return false;
    }
    
    // Rate limit per license key
    $cache_key_license = 'alm_rate_license_' . md5($license_key);
    $attempts_license = get_transient($cache_key_license);
    
    if ($attempts_license === false) {
        $attempts_license = 0;
    }
    
    // 10 requests per minute per license
    if ($attempts_license >= 10) {
        alm_error_log('Rate limit exceeded for license: ' . substr($license_key, 0, 10) . '...');
        return false;
    }
    
    // Rate limit per IP (if enabled)
    if ($check_ip) {
        $ip = alm_sanitize_ip();
        $cache_key_ip = 'alm_rate_ip_' . md5($ip);
        $attempts_ip = get_transient($cache_key_ip);
        
        if ($attempts_ip === false) {
            $attempts_ip = 0;
        }
        
        // 20 requests per minute per IP (lebih tinggi karena bisa multiple licenses)
        if ($attempts_ip >= 20) {
            alm_error_log('Rate limit exceeded for IP: ' . $ip);
            return false;
        }
        
        set_transient($cache_key_ip, $attempts_ip + 1, MINUTE_IN_SECONDS);
    }
    
    set_transient($cache_key_license, $attempts_license + 1, MINUTE_IN_SECONDS);
    return true;
}

/**
 * =====================================================
 * ANTI-SPAM & FRAUD DETECTION
 * =====================================================
 */

/**
 * Check Activation Spam - SECURITY FIX
 * Improved with proper IP sanitization
 */
function alm_check_activation_spam($license_key) {
    global $wpdb;
    
    // SECURITY FIX: Sanitize IP address
    $ip = alm_sanitize_ip();
    $now = current_time('mysql');
    $limit = 10; // max 10 aktivasi per jam per IP

    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}alm_license_activations 
         WHERE site_url = %s 
         AND activated_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
         $ip, $now
    ));
    
    if($count > $limit) {
        // Catat di log dengan IP yang sudah di-sanitize
        $wpdb->insert(
            "{$wpdb->prefix}alm_logs", 
            array(
                'log_time' => $now,
                'action' => 'alert',
                'license_key' => sanitize_text_field($license_key),
                'ip_address' => $ip, // Already sanitized
                'site_url' => '',
                'message' => sprintf(
                    'Suspicious activation pattern: %d attempts in 1 hour from IP %s',
                    intval($count),
                    $ip
                )
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        alm_error_log('Activation spam detected from IP: ' . $ip);
        return false;
    }
    
    return true;
}

/**
 * =====================================================
 * UTILITY FUNCTIONS
 * =====================================================
 */

function get_license_manager_info() {
    // Buat objek DateTime dengan timezone Asia/Jakarta (WIB)
    $date_wib = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    
    return array(
        'datetime_wib' => $date_wib->format('Y-m-d H:i:s'), // Format WIB
        'user_login' => wp_get_current_user()->user_login
    );
}

/**
 * Generate Hardware ID untuk binding
 * Mencegah license di-copy ke server lain
 */
if (!function_exists('alm_generate_hwid')) {
    function alm_generate_hwid($site_url) {
        $components = array(
            $site_url,
            php_uname('n'), // Hostname
            $_SERVER['SERVER_SOFTWARE'] ?? '',
            phpversion()
        );
        
        return hash('sha256', implode('|', $components));
    }
}