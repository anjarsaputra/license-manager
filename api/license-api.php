<?php
/**
 * license-api.php
 * REST API untuk aktivasi & manajemen lisensi.
 * Version: 2025-08-06 14:52:22
 */

if (!defined('ABSPATH')) exit;

/**
 * Fungsi helper untuk logging dan permission
 */
function alm_insert_log($license_key, $action, $message, $site_url = '') {
    global $wpdb;
    $log_table = $wpdb->prefix . 'alm_logs';
    
    // Get current info
    $current_time_utc = gmdate('Y-m-d H:i:s');
    $current_user = '';
    if (function_exists('wp_get_current_user')) {
        $current_user = wp_get_current_user();
        $current_user = $current_user->user_login;
    }

    // Insert log dengan format yang konsisten
    return $wpdb->insert(
        $log_table,
        array(
            'license_key' => $license_key,
            'action' => $action,
            'message' => sprintf('[%s] %s - %s', $current_time_utc, $current_user, $message),
            'site_url' => $site_url,
            'ip_address' => alm_sanitize_ip(),
            'log_time' => $current_time_utc
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );
}

function log_license_api_activity($action, $status) {
    // Log aktivitas dengan format yang konsisten
    alm_insert_log('SYSTEM', $action, sprintf('[%s] %s - %s', 
        gmdate('Y-m-d H:i:s'),
        function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : 'system',
        $status
    ));
}

function alm_api_permission_check(WP_REST_Request $request) {
    return ALM_Security::get_instance()->check_api_auth($request);
}

function alm_rest_api_license_validate(WP_REST_Request $request) {
    global $wpdb;
    
    $license_key = sanitize_text_field($request->get_param('license_key'));
    $site_url = sanitize_text_field($request->get_param('site_url'));
    $normalized_site_url = normalize_domain($site_url);

    // Cek rate limit
    if (!alm_check_rate_limit($license_key)) {
        alm_insert_log($license_key, 'rate_limit', 'Too many requests', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Terlalu banyak request. Coba lagi dalam 1 menit.'
        ], 429);
    }

    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';

    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$license_table` WHERE `license_key` = %s",
        $license_key
    ));

    if (!$license) {
        alm_insert_log($license_key, 'validate_fail', 'License not found.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Lisensi tidak ditemukan.'
        ], 404);
    }

    if ($license->status !== 'active') {
        $message = '';
        switch($license->status) {
            case 'revoked':
                $message = 'Lisensi telah dicabut oleh server dan tidak dapat digunakan.';
                break;
            case 'inactive':
                $message = 'Lisensi tidak aktif.';
                break;
            default:
                $message = 'Status lisensi tidak valid.';
        }
        
        alm_insert_log($license_key, 'validate_fail', "License {$license->status}.", $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'license_' . $license->status,
            'message' => $message
        ], 403);
    }

    if (!empty($license->expires) && strtotime($license->expires) < current_time('timestamp')) {
        alm_insert_log($license_key, 'validate_fail', 'License expired.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Lisensi sudah kedaluwarsa.'
        ], 403);
    }

    $already = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$activation_table` WHERE `license_id` = %d AND `site_url` = %s",
        $license->id,
        $normalized_site_url
    ));

    if ((int)$already > 0) {
        alm_insert_log($license_key, 'validate_success', 'Already activated.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => true,
            'already_activated' => true,
            'message' => 'Lisensi sudah diaktivasi untuk situs ini.',
            'expires' => $license->expires,
            'current_secret' => $current_primary_key
        ], 200);
    }

    if ($license->activations >= $license->activation_limit) {
        alm_insert_log($license_key, 'validate_fail', 'Activation limit reached.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Batas aktivasi lisensi sudah tercapai.'
        ], 403);
    }

    $wpdb->query('START TRANSACTION');
    
    try {
        $insert_data = [
            'license_id'   => $license->id,
            'site_url'     => $normalized_site_url,
            'activated_at' => current_time('mysql')
        ];
        
        $insert_result = $wpdb->insert($activation_table, $insert_data);

        if ($insert_result === false) {
            throw new Exception('Gagal menambah aktivasi');
        }

        $update_result = $wpdb->update(
            $license_table,
            ['activations' => $license->activations + 1],
            ['id' => $license->id]
        );

        if ($update_result === false) {
            throw new Exception('Gagal update jumlah aktivasi');
        }

        $wpdb->query('COMMIT');
        
        alm_insert_log($license_key, 'validate_success', 'Activation successful.', $normalized_site_url);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Lisensi berhasil diaktivasi.',
            'expires' => $license->expires,
            'current_secret' => $current_primary_key
        ], 200);

    } catch (Exception $e) {
    if (function_exists('alm_error_log')) {
        alm_error_log($e->getMessage());
    }
    $wpdb->query('ROLLBACK');
    return new WP_REST_Response([
        'success' => false,
        'message' => 'Gagal mengaktivasi lisensi: ' . $e->getMessage()
    ], 500);
}
}

/**
 * Endpoint: Nonaktifkan lisensi
 */
function alm_api_deactivate_license(WP_REST_Request $request) {
    global $wpdb;

    $license_key = sanitize_text_field($request->get_param('license_key'));
    $site_url = sanitize_text_field($request->get_param('site_url'));
    $normalized_site_url = normalize_domain($site_url);

    alm_insert_log($license_key, 'deactivate_attempt', 'Client trying to deactivate.', $normalized_site_url);

    if (!alm_check_rate_limit($license_key)) {
        alm_insert_log($license_key, 'rate_limit', 'Too many deactivation requests', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Terlalu banyak request. Coba lagi dalam 1 menit.'
        ], 429);
    }

    if (empty($license_key) || empty($site_url)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'license_key dan site_url wajib diisi.'
        ], 400);
    }

    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';

    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$license_table` WHERE `license_key` = %s",
        $license_key
    ));

    if (!$license) {
        alm_insert_log($license_key, 'deactivate_fail', 'License not found.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Lisensi tidak ditemukan.'
        ], 404);
    }

    $deleted = $wpdb->delete($activation_table, [
        'license_id' => $license->id,
        'site_url'   => $normalized_site_url
    ]);

    if ($deleted) {
        $new_total = max(0, $license->activations - 1);
        $wpdb->update($license_table, ['activations' => $new_total], ['id' => $license->id]);

        alm_insert_log($license_key, 'deactivate_success', 'Deactivation successful.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Lisensi berhasil dinonaktifkan.'
        ], 200);
    }

    alm_insert_log($license_key, 'deactivate_fail', 'No activation found.', $normalized_site_url);
    return new WP_REST_Response([
        'success' => false,
        'message' => 'Situs tidak ditemukan dalam aktivasi.'
    ], 404);
}

/**
 * Endpoint: Info update tema
 */
function alm_api_get_theme_info(WP_REST_Request $request) {
    $theme_slug = sanitize_text_field($request->get_param('slug'));
    if (empty($theme_slug)) {
        return new WP_REST_Response(['message' => 'Slug tema diperlukan.'], 400);
    }

    $option_name = 'alm_theme_update_info_' . strtolower($theme_slug);
    $info = get_option($option_name, []);

    if (!empty($info['new_version'])) {
        return new WP_REST_Response([
            'name'         => 'Mediman Theme',
            'version'      => $info['new_version'],
            'details_url'  => $info['url'],
            'download_url' => $info['package'],
            'author'       => 'Aradev',
            'sections'     => [
                'changelog' => wp_kses_post($info['changelog'] ?? 'Tidak ada changelog.')
            ]
        ], 200);
    }

    return new WP_REST_Response(['message' => 'Informasi tema tidak ditemukan.'], 404);
}

/**
 * Endpoint: Revoke/Global Nonaktifkan Lisensi
 */
function alm_api_revoke_license(WP_REST_Request $request) {
    global $wpdb;
    
    $license_key = sanitize_text_field($request->get_param('license_key'));

    if (empty($license_key)) {
        alm_insert_log($license_key, 'revoke_fail', 'Empty license key', '');
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Kode lisensi wajib diisi.'
        ], 400);
    }

    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';

    $wpdb->query('START TRANSACTION');

    try {
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$license_table` WHERE `license_key` = %s", 
            $license_key
        ));

        if (!$license) {
            throw new Exception('Lisensi tidak ditemukan.');
        }

        $update_result = $wpdb->update(
            $license_table, 
            [
                'status' => 'revoked',
                'activations' => 0
            ],
            ['id' => $license->id]
        );

        if ($update_result === false) {
            throw new Exception('Gagal update status lisensi');
        }

        $wpdb->delete($activation_table, ['license_id' => $license->id]);

        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$activation_table` WHERE `license_id` = %d",
            $license->id
        ));

        if ($remaining > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM `$activation_table` WHERE `license_id` = %d",
                $license->id
            ));
        }

        $wpdb->query('COMMIT');

        alm_insert_log(
            $license_key, 
            'revoke_success', 
            'Lisensi dicabut oleh admin. Semua aktivasi dihapus.',
            ''
        );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Lisensi telah dicabut dan semua aktivasi dihapus.'
        ], 200);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        
        alm_insert_log(
            $license_key, 
            'revoke_fail', 
            'Revoke failed: ' . $e->getMessage(),
            ''
        );

        return new WP_REST_Response([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Daftarkan semua endpoint API
 */
add_action('rest_api_init', function () {
    register_rest_route('alm/v1', '/validate', [
        'methods' => 'POST',
        'callback' => 'alm_rest_api_license_validate',
        'permission_callback' => 'alm_api_permission_check'
    ]);
    register_rest_route('alm/v1', '/deactivate', [
        'methods' => 'POST',
        'callback' => 'alm_api_deactivate_license',
        'permission_callback' => 'alm_api_permission_check'
    ]);
    register_rest_route('alm/v1', '/revoke', [
        'methods' => 'POST',
        'callback' => 'alm_api_revoke_license',
        'permission_callback' => 'alm_api_permission_check'
    ]);
    register_rest_route('theme-update/v1', '/info/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods' => 'GET',
        'callback' => 'alm_api_get_theme_info',
        'permission_callback' => '__return_true'
    ]);
});





// Endpoint aktivasi
function handle_activate_license() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
    $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
    
    if (empty($license_key) || empty($site_url)) {
        alm_log_license_activity($license_key, 'activate', 'Activation failed - Missing required fields', $site_url);
        wp_send_json_error('Missing required fields');
        return;
    }

    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $license_table WHERE license_key = %s",
        $license_key
    ));

    if (!$license) {
        alm_log_license_activity($license_key, 'activate', 'Activation failed - Invalid license key', $site_url);
        wp_send_json_error('Invalid license key');
        return;
    }

    if ($license->status !== 'active') {
        alm_log_license_activity($license_key, 'activate', 'Activation failed - License not active', $site_url);
        wp_send_json_error('License is not active');
        return;
    }

    if ($license->activations >= $license->activation_limit) {
        alm_log_license_activity($license_key, 'activate', 'Activation failed - Activation limit reached', $site_url);
        wp_send_json_error('Activation limit reached');
        return;
    }

    // Check if site is already activated
    $existing_activation = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $activation_table WHERE license_id = %d AND site_url = %s",
        $license->id,
        $site_url
    ));

    if ($existing_activation) {
        alm_log_license_activity($license_key, 'activate', 'Activation failed - Site already activated', $site_url);
        wp_send_json_error('Site already activated');
        return;
    }

    // Add new activation
    $wpdb->insert(
        $activation_table,
        array(
            'license_id' => $license->id,
            'site_url' => $site_url,
            'activated_at' => gmdate('Y-m-d H:i:s')
        ),
        array('%d', '%s', '%s')
    );

    // Update activation count
    $wpdb->query($wpdb->prepare(
        "UPDATE $license_table SET activations = activations + 1 WHERE id = %d",
        $license->id
    ));

    alm_log_license_activity($license_key, 'activate', 'License activated successfully', $site_url);
    wp_send_json_success('License activated successfully');
}

// Endpoint deaktivasi
function handle_deactivate_license() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
    $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
    
    if (empty($license_key) || empty($site_url)) {
        alm_log_license_activity($license_key, 'deactivate', 'Deactivation failed - Missing required fields', $site_url);
        wp_send_json_error('Missing required fields');
        return;
    }

    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $license_table WHERE license_key = %s",
        $license_key
    ));

    if (!$license) {
        alm_log_license_activity($license_key, 'deactivate', 'Deactivation failed - Invalid license key', $site_url);
        wp_send_json_error('Invalid license key');
        return;
    }

    // Remove activation
    $result = $wpdb->delete(
        $activation_table,
        array(
            'license_id' => $license->id,
            'site_url' => $site_url
        ),
        array('%d', '%s')
    );

    if ($result === false) {
        alm_log_license_activity($license_key, 'deactivate', 'Deactivation failed - Database error', $site_url);
        wp_send_json_error('Failed to deactivate license');
        return;
    }

    // Update activation count
    $wpdb->query($wpdb->prepare(
        "UPDATE $license_table SET activations = GREATEST(0, activations - 1) WHERE id = %d",
        $license->id
    ));

    alm_log_license_activity($license_key, 'deactivate', 'License deactivated successfully', $site_url);
    wp_send_json_success('License deactivated successfully');
}

// Endpoint verifikasi
function handle_verify_license() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
    $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
    
    if (empty($license_key) || empty($site_url)) {
        alm_log_license_activity($license_key, 'verify', 'Verification failed - Missing required fields', $site_url);
        wp_send_json_error('Missing required fields');
        return;
    }

    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $license_table WHERE license_key = %s",
        $license_key
    ));

    if (!$license) {
        alm_log_license_activity($license_key, 'verify', 'Verification failed - Invalid license key', $site_url);
        wp_send_json_error('Invalid license key');
        return;
    }

    // Check if license is active
    if ($license->status !== 'active') {
        alm_log_license_activity($license_key, 'verify', 'Verification failed - License not active', $site_url);
        wp_send_json_error('License is not active');
        return;
    }

    // Check if site is activated
    $is_activated = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $activation_table WHERE license_id = %d AND site_url = %s",
        $license->id,
        $site_url
    ));

    if (!$is_activated) {
        alm_log_license_activity($license_key, 'verify', 'Verification failed - Site not activated', $site_url);
        wp_send_json_error('Site not activated');
        return;
    }

    alm_log_license_activity($license_key, 'verify', 'License verified successfully', $site_url);
    wp_send_json_success(array(
        'status' => 'active',
        'expires' => $license->expires,
        'message' => 'License is valid and active'
    ));
}


class License_Server_Handler {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_endpoints'));
    }

    // Daftarkan endpoint API
    public function register_endpoints() {
        register_rest_route('mediman/v1', '/license/check', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_license_check'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }

    // Cek permission
    public function check_permission() {
        // Implementasi validasi API key atau token
        $api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
        return $this->validate_api_key($api_key);
    }

    // Handle pengecekan lisensi
    public function handle_license_check($request) {
        global $wpdb;

        // Get client site URL
        $client_site = $request->get_header('X-Client-Site');
        
        // Rate limiting
        if ($this->is_rate_limited($client_site)) {
            return new WP_REST_Response(array(
                'status' => 429,
                'message' => 'Too Many Requests',
                'retry_after' => 3600 // 1 jam
            ), 429);
        }

        // Cek di database
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}licenses 
            WHERE site_url = %s AND status = 'active'",
            esc_url_raw($client_site)
        ));

        if (!$license) {
            return array(
                'is_valid' => false,
                'expires_date' => null,
                'status' => 'invalid',
                'message' => 'License not found or inactive'
            );
        }

        // Format response
        return array(
            'is_valid' => true,
            'expires_date' => $license->expires_at,
            'status' => 'active',
            'message' => 'License is active'
        );
    }

    // Rate limiting implementation
    private function is_rate_limited($client_site) {
        $cache_key = 'license_check_' . md5($client_site);
        $attempts = get_transient($cache_key);
        
        if (false === $attempts) {
            set_transient($cache_key, 1, HOUR_IN_SECONDS);
            return false;
        }

        if ($attempts > 5) {
            return true;
        }

        set_transient($cache_key, $attempts + 1, HOUR_IN_SECONDS);
        return false;
    }

    // Validate API key
    private function validate_api_key($key) {
        // Implementasi validasi API key Anda
        $valid_key = get_option('mediman_license_api_key');
        return hash_equals($valid_key, $key);
    }
}

// Initialize
new License_Server_Handler();

// Tambahkan tabel jika belum ada
function create_license_table() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'licenses';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        license_key varchar(255) NOT NULL,
        site_url varchar(255) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime DEFAULT NULL,
        last_check datetime DEFAULT NULL,
        check_count int DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY license_key (license_key),
        KEY site_url (site_url)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'create_license_table');


add_action('wp_ajax_alm_activate_license', 'handle_activate_license');
add_action('wp_ajax_nopriv_alm_activate_license', 'handle_activate_license');
add_action('wp_ajax_alm_deactivate_license', 'handle_deactivate_license');
add_action('wp_ajax_nopriv_alm_deactivate_license', 'handle_deactivate_license');
add_action('wp_ajax_alm_verify_license', 'handle_verify_license');
add_action('wp_ajax_nopriv_alm_verify_license', 'handle_verify_license');



