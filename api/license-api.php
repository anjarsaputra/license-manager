<?php
/**
 * license-api.php
 * REST API untuk aktivasi & manajemen lisensi.
 * Version: 2025-10-24 - PRODUCTION VERSION (Clean)
 */

if (!defined('ABSPATH')) exit;

/**
 * Fungsi helper untuk logging dan permission
 */
function alm_insert_log($license_key, $action, $message, $site_url = '') {
    global $wpdb;
    $log_table = $wpdb->prefix . 'alm_logs';
    
    $current_time_utc = gmdate('Y-m-d H:i:s');
    $current_user = '';
    if (function_exists('wp_get_current_user')) {
        $current_user = wp_get_current_user();
        $current_user = $current_user->user_login;
    }

    return $wpdb->insert(
        $log_table,
        array(
            'license_key' => $license_key,
            'action' => $action,
            'message' => sprintf('[%s] %s - %s', $current_time_utc, $current_user, $message),
            'site_url' => $site_url,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'log_time' => $current_time_utc
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );
}

function alm_send_webhook_license_deactivated($webhook_url, $payload, $webhook_secret) {
    if (isset($payload['signature'])) unset($payload['signature']);
    error_log('SERVER SECRET: ' . $webhook_secret);
   error_log('SERVER JSON ENCODE: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));


    $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), $webhook_secret);
    $payload['signature'] = $signature;

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // LOGGING DEBUG
    error_log('WEBHOOK POST TO: '.$webhook_url);
    error_log('WEBHOOK PAYLOAD: '.json_encode($payload));
    error_log('WEBHOOK HTTP CODE: '.$http_code);
    error_log('WEBHOOK CURL ERROR: '.$err);
    error_log('WEBHOOK RESPONSE: '.$result);

    curl_close($ch);
}


function log_license_api_activity($action, $status) {
    alm_insert_log('SYSTEM', $action, sprintf('[%s] %s - %s', 
        gmdate('Y-m-d H:i:s'),
        function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : 'system',
        $status
    ));
}

function alm_api_permission_check(WP_REST_Request $request) {
    return ALM_Security::get_instance()->check_api_auth($request);
}

/**
 * Endpoint validasi license dengan logic expired yang benar
 */
function alm_rest_api_license_validate(WP_REST_Request $request) {
    global $wpdb;
    
    $license_key = sanitize_text_field($request->get_param('license_key'));
    $site_url = sanitize_text_field($request->get_param('site_url'));
    $normalized_site_url = normalize_domain($site_url);
    
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';

    // Cek rate limit
    if (!alm_check_rate_limit($license_key)) {
        alm_insert_log($license_key, 'rate_limit', 'Too many requests', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Terlalu banyak request. Coba lagi dalam 1 menit.'
        ], 429);
    }

    // 1. CEK LICENSE KEY EXISTS
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

    // 2. CEK STATUS (hanya block revoked/inactive, allow expired)
    if ($license->status !== 'active' && $license->status !== 'expired') {
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

    // 3. CEK APAKAH SUDAH PERNAH DIAKTIVASI (PRIORITAS UTAMA)
    $already_activated = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$activation_table` WHERE `license_id` = %d AND `site_url` = %s",
        $license->id,
        $normalized_site_url
    ));

    // 4. JIKA SUDAH AKTIF - CEK EXPIRED STATUS (TAPI TETAP IZINKAN)
    if ((int)$already_activated > 0) {
        $is_expired = false;
        $can_update = true;
        $message = 'Lisensi sudah diaktivasi untuk situs ini.';
        
        // Cek apakah expired
        if (!empty($license->expires) && strtotime($license->expires) < current_time('timestamp')) {
            $is_expired = true;
            $can_update = false;
            $message = 'Lisensi sudah diaktivasi tapi sudah kedaluwarsa. Update tema tidak tersedia.';
        }
        
        alm_insert_log(
            $license_key, 
            'validate_success', 
            $is_expired ? 'Already activated (expired).' : 'Already activated (valid).', 
            $normalized_site_url
        );
        
        return new WP_REST_Response([
            'success' => true,
            'already_activated' => true,
            'is_expired' => $is_expired,
            'can_update' => $can_update,
            'update_allowed' => $can_update,
            'message' => $message,
            'expires' => $license->expires,
            'status' => $is_expired ? 'expired' : 'active'
        ], 200);
    }

    // 5. UNTUK AKTIVASI BARU - CEK EXPIRED (IZINKAN TAPI BERI FLAG EXPIRED)
    $is_expired_new = false;
    $can_update_new = true;

    if (!empty($license->expires) && strtotime($license->expires) < current_time('timestamp')) {
        $is_expired_new = true;
        $can_update_new = false;
        
        alm_insert_log($license_key, 'validate_warning', 'License expired but activation allowed.', $normalized_site_url);
    }

    // 6. CEK ACTIVATION LIMIT (UNTUK AKTIVASI BARU)
    if ($license->activations >= $license->activation_limit) {
        alm_insert_log($license_key, 'validate_fail', 'Activation limit reached.', $normalized_site_url);
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Batas aktivasi lisensi sudah tercapai.'
        ], 403);
    }


// CEK: Apakah site_url sudah aktif di lisensi lain?
$duplicate_domain = $wpdb->get_row($wpdb->prepare(
    "SELECT la.id, la.license_id, l.license_key 
     FROM `$activation_table` la
     JOIN `$license_table` l ON la.license_id = l.id
     WHERE la.site_url = %s AND la.license_id != %d
     LIMIT 1",
    $normalized_site_url,
    $license->id
));

if ($duplicate_domain) {
    return new WP_REST_Response([
        'success' => false,
        'message' => 'Domain/website ini sudah terdaftar pada lisensi lain: '
            . $duplicate_domain->license_key . '. Nonaktifkan dari lisensi itu terlebih dahulu.',
        'duplicate_license' => $duplicate_domain->license_key
    ], 409); // 409 Conflict
}

    // 7. AKTIVASI BARU - TRANSACTION
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
        
        alm_insert_log(
            $license_key, 
            'validate_success', 
            $is_expired_new ? 'New activation successful (expired).' : 'New activation successful.', 
            $normalized_site_url
        );

        return new WP_REST_Response([
            'success' => true,
            'message' => $is_expired_new 
                ? 'Lisensi diaktivasi (expired). Theme dapat digunakan tapi update tidak tersedia.' 
                : 'Lisensi berhasil diaktivasi.',
            'expires' => $license->expires,
            'can_update' => $can_update_new,
            'update_allowed' => $can_update_new,
            'is_expired' => $is_expired_new,
            'status' => $is_expired_new ? 'expired' : 'active',
            'already_activated' => false
        ], 200);

    } catch (Exception $e) {
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

        // === Kirim webhook ke klien
        $webhook_url = rtrim($site_url, '/').'/wp-json/alm/v1/license-deactivated';
        $webhook_secret = get_option('mediman_webhook_secret', '');

        $payload = [
            'action'        => 'license_deactivated',
            'license_key'   => $license_key,
            'site_url'      => $site_url,
            'deactivated_at'=> gmdate('Y-m-d H:i:s'),
            'product_name'  => 'Mediman',
            'server_url'    => home_url(),
            'server_time'   => time(),
            'message'       => 'Dinonaktifkan dari server oleh user/admin'
        ];
        alm_send_webhook_license_deactivated($webhook_url, $payload, $webhook_secret);
        // ==============

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
 * Endpoint khusus untuk re-check license (site yang sudah aktif)
 */
function alm_api_recheck_license(WP_REST_Request $request) {
    global $wpdb;
    
    $license_key = sanitize_text_field($request->get_param('license_key'));
    $site_url = sanitize_text_field($request->get_param('site_url'));
    $normalized_site_url = normalize_domain($site_url);
    
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$license_table` WHERE `license_key` = %s",
        $license_key
    ));
    
    if (!$license) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Lisensi tidak ditemukan.'
        ], 404);
    }
    
    // Cek apakah sudah pernah diaktivasi
    $activation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$activation_table` WHERE `license_id` = %d AND `site_url` = %s",
        $license->id,
        $normalized_site_url
    ));
    
    if (!$activation) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Situs ini belum pernah diaktivasi.'
        ], 404);
    }
    
    // Cek expired
    $is_expired = false;
    $can_update = true;
    if (!empty($license->expires) && strtotime($license->expires) < current_time('timestamp')) {
        $is_expired = true;
        $can_update = false;
    }
    
    // Update last_check
    $wpdb->update(
        $activation_table,
        ['last_check' => current_time('mysql')],
        ['id' => $activation->id]
    );
    
    // Tetap return success meski expired
    return new WP_REST_Response([
        'success' => true,
        'is_active' => true,
        'is_expired' => $is_expired,
        'expires' => $license->expires,
        'can_update' => $can_update,
        'update_allowed' => $can_update,
        'status' => ($license->status === 'active' && !$is_expired) ? 'active' : 'expired',
        'message' => $is_expired ? 'Lisensi aktif tapi expired. Update tidak tersedia.' : 'Lisensi aktif dan valid.'
    ], 200);
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
    register_rest_route('alm/v1', '/recheck', [
        'methods' => 'POST',
        'callback' => 'alm_api_recheck_license',
        'permission_callback' => 'alm_api_permission_check'
    ]);
    register_rest_route('theme-update/v1', '/info/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods' => 'GET',
        'callback' => 'alm_api_get_theme_info',
        'permission_callback' => '__return_true'
    ]);
});
