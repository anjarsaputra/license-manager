<?php
/**
 * File untuk fungsi-fungsi umum Plugin License Manager.
 * Version: 9.2 - Production Ready (Debug Disabled)
 * Last Updated: 2025-10-24
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =====================================================
 * DEBUG FUNCTIONS - RESTRICTED FOR PRODUCTION
 * =====================================================
 */

/**
 * PRODUCTION: Debug hanya untuk super admin dengan query string
 */
add_action('init', function() {
    // ✅ PRODUCTION: Require admin + explicit query string
    if (!current_user_can('manage_options') || !isset($_GET['alm_debug'])) {
        return;
    }
    
    // Check activation table
    if (isset($_GET['check_activation_table'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'alm_license_activations';
        
        echo '<pre style="background:#000;color:#0f0;padding:20px;font-family:monospace;">';
        echo "=== ACTIVATION TABLE STRUCTURE ===\n\n";
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            echo "❌ Table NOT FOUND: {$table}\n";
            echo '</pre>';
            exit;
        }
        
        echo "✓ Table: {$table}\n\n";
        
        $columns = $wpdb->get_results("DESCRIBE {$table}");
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo "  - {$col->Field} ({$col->Type})\n";
        }
        
        echo "\nTotal activations: " . $wpdb->get_var("SELECT COUNT(*) FROM {$table}") . "\n";
        echo '</pre>';
        exit;
    }
    
    // Check license table
    if (isset($_GET['check_alm_table'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'alm_licenses';
        
        echo '<pre style="background:#000;color:#0f0;padding:20px;font-family:monospace;">';
        echo "=== ALM LICENSE TABLE STRUCTURE ===\n\n";
        
        $columns = $wpdb->get_results("DESCRIBE {$table}");
        echo "Table: {$table}\n\nColumns:\n";
        foreach ($columns as $col) {
            echo "  - {$col->Field} ({$col->Type})\n";
        }
        
        echo "\nTotal licenses: " . $wpdb->get_var("SELECT COUNT(*) FROM {$table}") . "\n";
        echo '</pre>';
        exit;
    }
});

/**
 * =====================================================
 * CUSTOMER PORTAL SETUP
 * =====================================================
 */

// Define constants
if (!defined('WCP_VERSION')) define('WCP_VERSION', '2.0.0');
if (!defined('WCP_PLUGIN_DIR')) define('WCP_PLUGIN_DIR', plugin_dir_path(__FILE__) . 'includes/woocommerce-customer-portal/');
if (!defined('WCP_PLUGIN_URL')) define('WCP_PLUGIN_URL', plugin_dir_url(__FILE__) . 'includes/woocommerce-customer-portal/');
if (!defined('WCP_BASE_DIR')) define('WCP_BASE_DIR', WCP_PLUGIN_DIR);
if (!defined('WCP_INC_DIR')) define('WCP_INC_DIR', WCP_PLUGIN_DIR . 'inc/');
if (!defined('WCP_ASSETS_URL')) define('WCP_ASSETS_URL', WCP_PLUGIN_URL . 'assets/');
if (!defined('WCP_TEMPLATES_DIR')) define('WCP_TEMPLATES_DIR', WCP_PLUGIN_DIR . 'templates/');

/**
 * Load portal files
 */
function load_customer_portal_files() {
    $files = [
        'class-portal-helpers.php',
        'class-portal-assets.php',
        'class-portal-navigation.php',
        'class-portal-init.php',
        'class-portal-dashboard.php',
        'class-portal-orders.php',
        'class-portal-downloads.php',
    ];
    
    foreach ($files as $file) {
        $file_path = WCP_INC_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    // Initialize classes
    if (class_exists('WCP_Portal_Init')) WCP_Portal_Init::instance();
    if (class_exists('WCP_Portal_Assets')) WCP_Portal_Assets::instance();
    if (class_exists('WCP_Portal_Navigation')) WCP_Portal_Navigation::instance();
    if (class_exists('WCP_Portal_Dashboard')) WCP_Portal_Dashboard::instance();
    if (class_exists('WCP_Portal_Orders')) WCP_Portal_Orders::instance();
    if (class_exists('WCP_Portal_Downloads')) WCP_Portal_Downloads::instance();
}
add_action('plugins_loaded', 'load_customer_portal_files', 20);

/**
 * Register endpoint
 */
function register_licenses_endpoint() {
    add_rewrite_endpoint('my-licenses', EP_ROOT | EP_PAGES);
}
add_action('init', 'register_licenses_endpoint');

/**
 * ✅ PRODUCTION: Debug footer - hanya untuk admin + query string
 */
add_action('wp_footer', function() {
    // ✅ Require admin + explicit enable
    if (!current_user_can('manage_options') || !isset($_GET['wcp_debug'])) {
        return;
    }
    
    echo '<div style="position:fixed;bottom:10px;right:10px;background:#1e293b;color:#10b981;padding:15px;z-index:99999;font-family:monospace;font-size:11px;max-width:350px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);">';
    echo '<strong style="color:#60a5fa;">🔍 MCP DEBUG:</strong><br>';
    echo 'Version: ' . (defined('WCP_VERSION') ? '<span style="color:#10b981;">✓ ' . WCP_VERSION . '</span>' : '<span style="color:#ef4444;">✗</span>') . '<br>';
    echo 'Plugin Dir: ' . (defined('WCP_PLUGIN_DIR') && is_dir(WCP_PLUGIN_DIR) ? '<span style="color:#10b981;">✓</span>' : '<span style="color:#ef4444;">✗</span>') . '<br>';
    echo 'Templates Dir: ' . (defined('WCP_TEMPLATES_DIR') && is_dir(WCP_TEMPLATES_DIR) ? '<span style="color:#10b981;">✓</span>' : '<span style="color:#ef4444;">✗</span>') . '<br>';
    echo '<hr style="border:1px solid #334155;margin:8px 0;">';
    echo 'Helpers: ' . (class_exists('WCP_Portal_Helpers') ? '<span style="color:#10b981;">✓</span>' : '<span style="color:#ef4444;">✗</span>') . '<br>';
    echo 'Dashboard: ' . (class_exists('WCP_Portal_Dashboard') ? '<span style="color:#10b981;">✓</span>' : '<span style="color:#ef4444;">✗</span>') . '<br>';
    echo '</div>';
});

/**
 * =====================================================
 * SECURITY HELPER FUNCTIONS
 * =====================================================
 */

if (!function_exists('alm_sanitize_ip')) {
    function alm_sanitize_ip($ip = null) {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        $validated_ip = filter_var($ip, FILTER_VALIDATE_IP);
        
        if ($validated_ip === false) {
            alm_error_log('Invalid IP address detected: ' . $ip);
            return '0.0.0.0';
        }
        
        return sanitize_text_field($validated_ip);
    }
}

if (!function_exists('alm_validate_license_key')) {
    function alm_validate_license_key($license_key) {
        if (empty($license_key)) {
            return false;
        }
        
        if (!preg_match('/^[A-Z0-9\-]{20,}$/i', $license_key)) {
            alm_error_log('Invalid license key format: ' . substr($license_key, 0, 10) . '...');
            return false;
        }
        
        return true;
    }
}

if (!function_exists('alm_validate_domain')) {
    function alm_validate_domain($site_url) {
        if (empty($site_url)) {
            return false;
        }
        
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
        
        if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
            alm_error_log('Invalid URL format: ' . $site_url);
            return false;
        }
        
        return true;
    }
}

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
 * HELPER FUNCTIONS
 * =====================================================
 */

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

if (!function_exists('alm_error_log')) {
    function alm_error_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ALM] ' . $message);
        }
    }
}

if (!function_exists('alm_safe_str_replace')) {
    function alm_safe_str_replace($search, $replace, $subject) {
        if (!is_string($subject) && !is_array($subject)) {
            $subject = (string) $subject;
        }
        return str_replace($search, $replace, $subject);
    }
}

if (!function_exists('normalize_domain')) {
    function normalize_domain($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $url = 'http://' . $url;
        }
        
        $host = parse_url(trim($url), PHP_URL_HOST) ?: trim($url);
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        
        return sanitize_text_field($host);
    }
}

if (!function_exists('alm_api_permission_check')) {
    function alm_api_permission_check(WP_REST_Request $request) {
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
 * RATE LIMITING & SECURITY
 * =====================================================
 */

if (!function_exists('alm_check_rate_limit')) {
    function alm_check_rate_limit($license_key, $check_ip = true) {
        if (empty($license_key)) {
            return false;
        }
        
        $cache_key_license = 'alm_rate_license_' . md5($license_key);
        $attempts_license = get_transient($cache_key_license);
        
        if ($attempts_license === false) {
            $attempts_license = 0;
        }
        
        if ($attempts_license >= 10) {
            alm_error_log('Rate limit exceeded for license: ' . substr($license_key, 0, 10) . '...');
            return false;
        }
        
        if ($check_ip) {
            $ip = alm_sanitize_ip();
            $cache_key_ip = 'alm_rate_ip_' . md5($ip);
            $attempts_ip = get_transient($cache_key_ip);
            
            if ($attempts_ip === false) {
                $attempts_ip = 0;
            }
            
            if ($attempts_ip >= 20) {
                alm_error_log('Rate limit exceeded for IP: ' . $ip);
                return false;
            }
            
            set_transient($cache_key_ip, $attempts_ip + 1, MINUTE_IN_SECONDS);
        }
        
        set_transient($cache_key_license, $attempts_license + 1, MINUTE_IN_SECONDS);
        return true;
    }
}

if (!function_exists('alm_check_activation_spam')) {
    function alm_check_activation_spam($license_key) {
        global $wpdb;
        
        $ip = alm_sanitize_ip();
        $now = current_time('mysql');
        $limit = 10;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}alm_license_activations 
             WHERE site_url = %s 
             AND activated_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
             $ip, $now
        ));
        
        if ($count > $limit) {
            $wpdb->insert(
                "{$wpdb->prefix}alm_logs", 
                array(
                    'log_time' => $now,
                    'action' => 'alert',
                    'license_key' => sanitize_text_field($license_key),
                    'ip_address' => $ip,
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
}

/**
 * =====================================================
 * UTILITY FUNCTIONS
 * =====================================================
 */

if (!function_exists('get_license_manager_info')) {
    function get_license_manager_info() {
        $date_wib = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        
        return array(
            'datetime_wib' => $date_wib->format('Y-m-d H:i:s'),
            'user_login' => wp_get_current_user()->user_login
        );
    }
}

if (!function_exists('alm_generate_hwid')) {
    function alm_generate_hwid($site_url) {
        $components = array(
            $site_url,
            php_uname('n'),
            $_SERVER['SERVER_SOFTWARE'] ?? '',
            phpversion()
        );
        
        return hash('sha256', implode('|', $components));
    }
}


/**
 * =====================================================
 * SEND TEST LICENSE EMAIL
 * URL: yoursite.com/?alm_test_email=your@email.com
 * =====================================================
 */
add_action('init', 'alm_send_test_license_email');

function alm_send_test_license_email() {
    // Security: Only for admin
    if (!isset($_GET['alm_test_email']) || !current_user_can('manage_options')) {
        return;
    }
    
    $test_email = sanitize_email($_GET['alm_test_email']);
    
    if (!is_email($test_email)) {
        wp_die('❌ Invalid email address. <br><a href="' . admin_url() . '">Back to Dashboard</a>');
    }
    
    // Test data
    $license_key = 'TEST-' . strtoupper(substr(md5(time()), 0, 16));
    $expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));
    $product_name = 'Mediman Pro Theme (TEST EMAIL)';
    $activation_limit = 1;
    $order_id = 99999;
    
    // Send email using your function
    $sent = alm_send_license_email($test_email, $license_key, $expiry_date, $product_name, $activation_limit, $order_id);
    
    if ($sent) {
        wp_die('
            <div style="font-family: -apple-system, sans-serif; max-width: 600px; margin: 50px auto; padding: 30px; background: #f0f9ff; border-radius: 10px; border-left: 5px solid #0ea5e9;">
                <h2 style="color: #0c4a6e; margin: 0 0 15px;">✅ Test Email Sent Successfully!</h2>
                <p style="color: #334155; margin: 0 0 15px;">Email telah dikirim ke: <strong>' . esc_html($test_email) . '</strong></p>
                <p style="color: #64748b; font-size: 14px; margin: 0 0 20px;">License Key: <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">' . esc_html($license_key) . '</code></p>
                <a href="' . admin_url() . '" style="display: inline-block; background: #0ea5e9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Back to Dashboard</a>
            </div>
        ');
    } else {
        wp_die('
            <div style="font-family: -apple-system, sans-serif; max-width: 600px; margin: 50px auto; padding: 30px; background: #fef2f2; border-radius: 10px; border-left: 5px solid #ef4444;">
                <h2 style="color: #7f1d1d; margin: 0 0 15px;">❌ Failed to Send Email</h2>
                <p style="color: #334155;">Check your email configuration.</p>
                <a href="' . admin_url() . '" style="display: inline-block; background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px;">Back to Dashboard</a>
            </div>
        ');
    }
}

// Shortcode utama: Tampilkan login/register jika belum login, dashboard jika sudah login

// Shortcode untuk login custom member-area
add_shortcode('wcp_login_form', function() {
    ob_start();
    include WCP_TEMPLATES_DIR . 'form-login.php';
    return ob_get_clean();
});

// Shortcode untuk register custom member-area
add_shortcode('wcp_register_form', function() {
    ob_start();
    include WCP_TEMPLATES_DIR . 'form-register.php';
    return ob_get_clean();
});

// Shortcode utama: Tampilkan login/register jika belum login, dashboard jika sudah login
add_shortcode('wcp_member_area', function() {
    ob_start();
    if (!is_user_logged_in()) {
        echo do_shortcode('[wcp_login_form]');
        echo do_shortcode('[wcp_register_form]');
    } else {
        include WCP_TEMPLATES_DIR . 'dashboard.php';
    }
    return ob_get_clean();
});


