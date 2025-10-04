<?php
/**
 * Plugin Name: Advanced License Manager
 * Description: Plugin manajemen lisensi dan update tema yang lengkap untuk server Anda.
 * Version: 9.0 - Pagination
 * Author: Aradev
 */

if (!defined('ABSPATH')) {
    exit;
}


// Safe require file
$function_file = plugin_dir_path(__FILE__) . 'function.php';
$api_file = plugin_dir_path(__FILE__) . 'api/license-api.php';
require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once plugin_dir_path(__FILE__) . 'cleanup.php';
require_once plugin_dir_path(__FILE__) . 'includes/logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/license-generator.php';
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        require_once plugin_dir_path(__FILE__) . 'includes/integrasi-woocommerce.php';
        require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-customer-portal/class-wc-license-tab.php';
    require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-customer-portal/tab-ajax.php';
        require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-customer-portal/rest-api-deactivate.php';

    }
});
// Di bagian atas setelah plugin header
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
// Safe require files
// Load admin dashboard (ONLY in admin area)
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'dashboard.php';
}

function alm_create_tables_on_activation() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $table_prefix = $wpdb->prefix . 'alm_';
    
   // Tabel Checksums untuk validasi lisensi
    $checksum_table = $table_prefix . 'license_checksums';
    $checksum_sql = "CREATE TABLE IF NOT EXISTS {$checksum_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        license_key varchar(255) NOT NULL,
        checksum varchar(64) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY license_key (license_key),
        KEY checksum (checksum)
    ) $charset_collate;";
    dbDelta($checksum_sql);
    
    // Tabel Lisensi
    $license_table = $table_prefix . 'licenses';
    $license_sql = "CREATE TABLE IF NOT EXISTS {$license_table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        license_key varchar(255) NOT NULL,
        product_name varchar(255) NOT NULL,
        customer_email varchar(255),
        activations int DEFAULT 0,
        activation_limit int DEFAULT 1,
        expires datetime DEFAULT NULL,
        status varchar(50) DEFAULT 'inactive',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY license_key (license_key)
    ) $charset_collate;";
    dbDelta($license_sql);

    // Tabel Aktivasi
    $activation_table = $table_prefix . 'license_activations';
    $activation_sql = "CREATE TABLE IF NOT EXISTS {$activation_table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        license_id mediumint(9) NOT NULL,
        site_url varchar(255) NOT NULL,
        activated_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_check datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(50) DEFAULT 'active',
        PRIMARY KEY (id),
        UNIQUE KEY unique_activation (license_id, site_url),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($activation_sql);

    // Tabel Log
    $log_table = $table_prefix . 'logs';
    $log_sql = "CREATE TABLE IF NOT EXISTS {$log_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        license_key varchar(255),
        action varchar(50),
        message text,
        site_url varchar(255),
        ip_address varchar(100),
        user_agent varchar(255),
        log_time datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY license_key (license_key),
        KEY action (action),
        KEY log_time (log_time)
    ) $charset_collate;";
    dbDelta($log_sql);

    // Tabel Checksum
    $checksum_table = $table_prefix . 'license_checksums';
    $checksum_sql = "CREATE TABLE IF NOT EXISTS {$checksum_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        license_key varchar(255) NOT NULL,
        checksum varchar(64) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY license_key (license_key),
        KEY checksum (checksum)
    ) $charset_collate;";
    dbDelta($checksum_sql);

    // Tabel Security Events
    $security_table = $wpdb->prefix . 'alm_security_events';
    $security_sql = "CREATE TABLE IF NOT EXISTS {$security_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_type varchar(50) NOT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent varchar(255),
        user_id bigint(20),
        attempt_time datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) NOT NULL,
        details text,
        PRIMARY KEY (id),
        KEY ip_address (ip_address),
        KEY event_type (event_type),
        KEY attempt_time (attempt_time)
    ) $charset_collate;";
    dbDelta($security_sql);
}

// Setelah require files, tambahkan ini
register_activation_hook(__FILE__, function() {
    global $wpdb;
    
    // Buat tabel
    alm_create_tables_on_activation();
    
    // Inisialisasi instances
    ALM_Security::get_instance();
    ALM_Logger::get_instance()->init_log_table();
    
    // Set default timezone
    update_option('timezone_string', 'Asia/Jakarta');
    
    // Bersihkan cache
    wp_cache_flush();
});

function alm_initialize_tables() {
    global $wpdb;
    
    // Daftar semua tabel yang diperlukan
    $required_tables = [
        $wpdb->prefix . 'alm_licenses',
        $wpdb->prefix . 'alm_license_activations',
        $wpdb->prefix . 'alm_logs',
        $wpdb->prefix . 'alm_license_checksums',
        $wpdb->prefix . 'alm_security_events'
    ];
    
    // Cek setiap tabel
    $missing_tables = [];
    foreach ($required_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $missing_tables[] = $table;
        }
    }
    
    // Jika ada tabel yang hilang, coba buat
    if (!empty($missing_tables)) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        alm_create_tables_on_activation();
        
        // Cek lagi setelah mencoba membuat
        foreach ($missing_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false; // Masih ada tabel yang hilang
            }
        }
    }
    
    return true;
}

// Panggil saat plugin diload
add_action('plugins_loaded', function() {
    if (!alm_initialize_tables()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Advanced License Manager Error:</strong> 
                Tabel database tidak dapat dibuat secara otomatis. 
                Silakan <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(__FILE__)), 'deactivate-plugin_' . plugin_basename(__FILE__)); ?>">deaktifkan plugin</a> 
                dan aktifkan kembali. Jika masalah berlanjut, hubungi administrator database Anda.</p>
            </div>
            <?php
        });
    }
});

function alm_check_tables_exist() {
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'alm_licenses',
        $wpdb->prefix . 'alm_license_activations',
        $wpdb->prefix . 'alm_logs',
        $wpdb->prefix . 'alm_license_checksums', // Tambahkan tabel checksum
        $wpdb->prefix . 'alm_security_events'
    ];
    
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }
    }
    return true;
}

// Hanya tampilkan pesan error jika tabel masih belum ada setelah mencoba membuatnya
add_action('admin_init', function() {
    if (!alm_check_tables_exist()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Advanced License Manager Error:</strong> Tabel database tidak ditemukan. Silakan <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(__FILE__)), 'deactivate-plugin_' . plugin_basename(__FILE__)); ?>">deaktifkan plugin</a> dan aktifkan kembali.</p>
            </div>
            <?php
        });
    }
});

// Cek dan buat tabel jika belum ada
function alm_maybe_create_tables() {
    if (!alm_check_tables_exist()) {
        alm_create_tables_on_activation();
    }
}
add_action('plugins_loaded', 'alm_maybe_create_tables');

// Fungsi untuk mendapatkan informasi sistem
function alm_get_system_info() {
    static $info = null;
    
    if ($info === null) {
        try {
            // Format waktu dengan UTC
            $current_time = gmdate('Y-m-d H:i:s');
            
            $info = array(
                'current_time' => $current_time,
                'current_user' => ''
            );
            
            // Pastikan WordPress sudah dimuat dan user sudah login
            if (function_exists('wp_get_current_user') && is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $info['current_user'] = $current_user instanceof WP_User ? $current_user->user_login : 'Unknown';
            }
        } catch (Exception $e) {
            error_log('ALM System Info Error: ' . $e->getMessage());
            $info = array(
                'current_time' => 'Error getting time',
                'current_user' => 'Error getting user'
            );
        }
    }
    
    return $info;
}




function alm_display_system_info() {
    // Get Logger instance
    $logger = ALM_Logger::get_instance();
    $security = ALM_Security::get_instance();
    
    // Get system info
    $logger_info = $logger->get_system_info();
    $security_info = $security->get_security_status();
    
    // Dapatkan waktu UTC
    $utc_datetime = new DateTime('now', new DateTimeZone('UTC'));
    // Konversi ke WIB
    $wib_datetime = clone $utc_datetime;
    $wib_datetime->setTimezone(new DateTimeZone('Asia/Jakarta'));
    
    // Format waktu untuk WIB
    $current_time_wib = $wib_datetime->format('Y-m-d H:i:s');
    
    // Pastikan current user selalu tersedia
    $current_user = wp_get_current_user()->user_login;
    ?>
    <div class="alm-system-info-panel">
        <!-- Time Information -->
        <div class="alm-system-info-item">
            <div class="alm-info-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="alm-info-content">
                <div class="alm-info-label">CURRENT DATE AND TIME (WIB - YYYY-MM-DD HH:MM:SS)</div>
                <div class="alm-info-value" id="alm-current-time"><?php echo esc_html($current_time_wib); ?></div>
            </div>
        </div>
        
        <!-- User Information -->
        <div class="alm-system-info-item">
            <div class="alm-info-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="alm-info-content">
                <div class="alm-info-label">CURRENT USER'S LOGIN</div>
                <div class="alm-info-value"><?php echo esc_html($current_user); ?></div>
            </div>
        </div>

        <!-- Security Status -->
        <div class="alm-system-info-item">
            <div class="alm-info-icon">
                <span class="dashicons dashicons-shield<?php echo ($security_info['blocked_ips'] > 0 ? '-alt' : ''); ?>"></span>
            </div>
            <div class="alm-info-content">
                <div class="alm-info-label">SECURITY STATUS (LAST HOUR)</div>
                <div class="alm-info-value">
                    <div class="security-stats">
                        <span class="stat-item">
                            <strong><?php echo esc_html($security_info['blocked_ips']); ?></strong> Blocked IPs
                        </span>
                        <span class="stat-item">
                            <strong><?php echo esc_html($security_info['failed_attempts']); ?></strong> Failed Attempts
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Update waktu setiap detik dengan waktu WIB
    function updateTime() {
        const timeElement = document.getElementById('alm-current-time');
        if (timeElement) {
            const now = new Date();
            // Tambah 7 jam untuk WIB
            now.setHours(now.getHours() + 7);
            const wibString = now.getUTCFullYear() + '-' + 
                            String(now.getUTCMonth() + 1).padStart(2, '0') + '-' +
                            String(now.getUTCDate()).padStart(2, '0') + ' ' +
                            String(now.getUTCHours()).padStart(2, '0') + ':' +
                            String(now.getUTCMinutes()).padStart(2, '0') + ':' +
                            String(now.getUTCSeconds()).padStart(2, '0');
            timeElement.textContent = wibString;
        }
    }
    
    // Update setiap detik
    setInterval(updateTime, 1000);
    updateTime(); // Panggil sekali saat load
    </script>

    <style>
    .security-stats {
        display: flex;
        gap: 15px;
    }
    .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .stat-item strong {
        color: #e11d48;
    }
    </style>
    <?php
}

// Fungsi inisialisasi untuk system info
function alm_init_system_info() {
    // Daftar halaman yang diizinkan
    $allowed_pages = array(
        'alm-dashboard',
        'advanced-license-manager', // Tetap gunakan ini untuk kompatibilitas
        'alm-add-license',
        'alm-edit-license',
        'alm-activity-log',
        'alm-theme-update',
        'alm-settings'
    );

    // Cek apakah ini halaman yang diizinkan
    if (isset($_GET['page']) && in_array($_GET['page'], $allowed_pages)) {
        // Hook untuk menampilkan system info
        add_action('admin_notices', 'alm_display_system_info');
        
        // Hook untuk CSS
        add_action('admin_head', function() {
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
        });
    }
}

// Pastikan timezone WordPress diset dengan benar
add_action('init', function() {
    // Set default timezone ke Asia/Jakarta
    if (get_option('timezone_string') !== 'Asia/Jakarta') {
        update_option('timezone_string', 'Asia/Jakarta');
    }
});

// Hook utama untuk inisialisasi
add_action('admin_init', 'alm_init_system_info');


// Di bagian atas file, setelah semua require
function alm_check_and_create_missing_tables() {
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'alm_licenses',
        $wpdb->prefix . 'alm_license_activations',
        $wpdb->prefix . 'alm_logs',
        $wpdb->prefix . 'alm_license_checksums', // Tambahkan tabel checksum
        $wpdb->prefix . 'alm_security_events'
    ];

    $missing_tables = [];
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $missing_tables[] = $table;
        }
    }

    // Jika ada tabel yang hilang, jalankan create tables
    if (!empty($missing_tables)) {
        alm_create_tables_on_activation();
    }
}

// Jalankan pengecekan saat plugin diload
add_action('plugins_loaded', 'alm_check_and_create_missing_tables');


if (file_exists($api_file)) require_once $api_file;
else error_log('File api/license-api.php tidak ditemukan!');
if (file_exists($function_file)) require_once $function_file;
else error_log('File function.php tidak ditemukan!');





// DEBUG MODE (opsional aktifkan dari Settings)
$debug_mode = get_option('alm_debug_mode', false);
if ($debug_mode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}


// =============================================================================
// CEK TABLE DATABASE PLUGIN
// =============================================================================


// Panggil sebelum HOOKS UTAMA
if (!alm_check_tables_exist()) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>Advanced License Manager:</strong> 
            Mencoba membuat tabel yang hilang... 
            Silakan refresh halaman ini setelah beberapa saat.</p>
        </div>
        <?php
        alm_initialize_tables();
    });
    return;
}



// =============================================================================
// HOOKS UTAMA PLUGIN
// =============================================================================

add_action('admin_menu', 'alm_register_admin_menus');
add_action('admin_init', 'alm_handle_actions');
add_action('admin_post_alm_save_update_settings', 'alm_handle_update_upload_form');
add_action('admin_enqueue_scripts', 'alm_enqueue_admin_styles'); // Hook untuk CSS

// =============================================================================
// FUNGSI LOGIKA INTI
// =============================================================================



function alm_register_admin_menus() {
    // Dashboard sebagai menu utama
    add_menu_page(
        'License Manager', 
        'License Manager', 
        'manage_options', 
        'alm-dashboard', // Slug utama
        'alm_render_dashboard_page',
        'dashicons-shield-alt'
    );

    // Submenu Dashboard
    add_submenu_page(
        'alm-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'alm-dashboard', // Sama dengan parent
        'alm_render_dashboard_page'
    );

    // Submenu Licenses
    add_submenu_page(
        'alm-dashboard',
        'All Licenses',
        'All Licenses',
        'manage_options',
        'advanced-license-manager', // Tetap gunakan slug ini untuk kompatibilitas
        'alm_render_licenses_page'
    );

    // Submenu Add New
    add_submenu_page(
        'alm-dashboard',
        'Add New License',
        'Add New',
        'manage_options',
        'alm-add-license',
        'alm_render_add_license_page'
    );

    // Submenu Activity Log
    add_submenu_page(
        'alm-dashboard',
        'Activity Log',
        'Activity Log',
        'manage_options',
        'alm-activity-log',
        'alm_render_activity_log_page'
    );

    // Submenu Theme Update
    add_submenu_page(
        'alm-dashboard',
        'Theme Update',
        'Theme Update',
        'manage_options',
        'alm-theme-update',
        'alm_render_update_settings_page'
    );

    // Submenu Settings
    add_submenu_page(
        'alm-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'alm-settings',
        'alm_render_settings_page'
    );

    // Hidden submenu untuk Edit License
    add_submenu_page(
        null,
        'Edit License',
        null,
        'manage_options',
        'alm-edit-license',
        'alm_render_edit_license_page'
    );
}



/**
 * Menangani semua aksi form (Tambah, Edit, Hapus, dll).
 * Dijalankan pada hook 'admin_init' sebelum header dikirim.
 */
function alm_handle_actions() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';

    // Tambah Lisensi Baru
    if (
        isset($_POST['add_license']) &&
        isset($_POST['_wpnonce']) &&
        wp_verify_nonce($_POST['_wpnonce'], 'alm_add_new_nonce')
    ) {
        $wpdb->insert($license_table, [
            'license_key' => sanitize_text_field($_POST['license_key']),
            'product_name' => sanitize_text_field($_POST['product_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'activation_limit' => intval($_POST['activation_limit']),
            'expires' => empty($_POST['expires']) ? null : sanitize_text_field($_POST['expires']),
            'status' => sanitize_text_field($_POST['status'])
        ]);
        wp_safe_redirect(admin_url('admin.php?page=advanced-license-manager&add_success=true'));
        exit;
    }
// Edit Lisensi
if (
    isset($_POST['update_license']) &&
    isset($_POST['license_id']) &&
    isset($_POST['_wpnonce']) &&
    wp_verify_nonce($_POST['_wpnonce'], 'alm_edit_nonce_' . $_POST['license_id'])
) {
    $license_id = intval($_POST['license_id']);
    $new_status = sanitize_text_field($_POST['status']);
    
    // Cek apakah status diubah ke revoked
    if ($new_status === 'revoked') {
        // Hapus semua aktivasi untuk lisensi ini
        $wpdb->delete($activation_table, ['license_id' => $license_id]);
        
        // Update lisensi dengan aktivasi = 0
        $wpdb->update($license_table, [
            'license_key' => sanitize_text_field($_POST['license_key']),
            'product_name' => sanitize_text_field($_POST['product_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'activation_limit' => intval($_POST['activation_limit']),
            'expires' => empty($_POST['expires']) ? null : sanitize_text_field($_POST['expires']),
            'status' => $new_status,
            'activations' => 0 // Reset aktivasi ke 0
        ], ['id' => $license_id]);

        // Log aktivitas revoke via edit
        if (function_exists('alm_insert_log')) {
            alm_insert_log(
                sanitize_text_field($_POST['license_key']),
                'revoke',
                'Lisensi dinonaktifkan (revoked) melalui edit. Semua aktivasi dihapus.',
                ''
            );
        }
    } else {
        // Update normal tanpa menghapus aktivasi
        $wpdb->update($license_table, [
            'license_key' => sanitize_text_field($_POST['license_key']),
            'product_name' => sanitize_text_field($_POST['product_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'activation_limit' => intval($_POST['activation_limit']),
            'expires' => empty($_POST['expires']) ? null : sanitize_text_field($_POST['expires']),
            'status' => $new_status
        ], ['id' => $license_id]);
    }

    wp_safe_redirect(admin_url('admin.php?page=alm-edit-license&id=' . $license_id . '&update_success=true'));
    exit;
}

    // Revoke Lisensi
   if (
    isset($_POST['revoke_license']) &&
    isset($_POST['license_id']) &&
    isset($_POST['_wpnonce']) &&
    wp_verify_nonce($_POST['_wpnonce'], 'alm_revoke_license_' . $_POST['license_id'])
) {
    $license_id = intval($_POST['license_id']);
    $license = $wpdb->get_row($wpdb->prepare("SELECT * FROM $license_table WHERE id = %d", $license_id));
    if (!$license) {
        wp_die('License not found.');
    }
    // Hapus semua aktivasi untuk lisensi ini (PASTIKAN BENAR-BENAR HILANG)
    $wpdb->query($wpdb->prepare("DELETE FROM $activation_table WHERE license_id = %d", $license_id));
    // Reset aktivasi dan set status revoked
    $wpdb->update(
        $license_table,
        ['status' => 'revoked', 'activations' => 0],
        ['id' => $license_id]
    );
    // Log aktivitas
    if (function_exists('alm_insert_log')) {
        alm_insert_log(
            $license->license_key,
            'revoke',
            'Lisensi dicabut oleh admin. Semua aktivasi dihapus.',
            ''
        );
    }
    wp_safe_redirect(admin_url('admin.php?page=alm-edit-license&id=' . $license_id . '&revoke_success=true'));
    exit;
}

    // Delete Lisensi
    if (
        isset($_GET['delete']) &&
        isset($_GET['_wpnonce']) &&
        wp_verify_nonce($_GET['_wpnonce'], 'alm_delete_license_' . $_GET['delete'])
    ) {
        $license_id = intval($_GET['delete']);
        $wpdb->delete($activation_table, ['license_id' => $license_id]);
        $wpdb->delete($license_table, ['id' => $license_id]);
        wp_safe_redirect(admin_url('admin.php?page=advanced-license-manager&delete_success=true'));
        exit;
    }

    // Remote Deactivate
    if (
        isset($_GET['action']) && $_GET['action'] === 'remote_deactivate' &&
        isset($_GET['activation_id']) &&
        isset($_GET['_wpnonce']) &&
        wp_verify_nonce($_GET['_wpnonce'], 'alm_remote_deactivate_' . $_GET['activation_id'])
    ) {
        $activation_id = intval($_GET['activation_id']);
        $activation = $wpdb->get_row($wpdb->prepare("SELECT license_id FROM $activation_table WHERE id = %d", $activation_id));
        if ($activation) {
            $wpdb->delete($activation_table, ['id' => $activation_id]);
            $wpdb->query($wpdb->prepare("UPDATE $license_table SET activations = GREATEST(0, activations - 1) WHERE id = %d", $activation->license_id));
            wp_safe_redirect(admin_url('admin.php?page=alm-edit-license&id=' . $activation->license_id . '&deactivate_success=true'));
            exit;
        }
    }

    // Simpan Pengaturan
    if (
        isset($_POST['alm_action']) && $_POST['alm_action'] === 'save_settings' &&
        isset($_POST['_wpnonce']) &&
        wp_verify_nonce($_POST['_wpnonce'], 'alm_save_settings_nonce')
    ) {
        $keys_string = sanitize_textarea_field($_POST['alm_secret_keys']);
        $keys_array = array_filter(array_map('trim', explode("\n", $keys_string)));
        if (isset($_POST['generate_new_key'])) {
            $new_key = 'sk_' . bin2hex(random_bytes(24));
            array_unshift($keys_array, $new_key);
        }
        update_option('alm_secret_keys', $keys_array);
        $debug_mode = isset($_POST['alm_debug_mode']) ? true : false;
        update_option('alm_debug_mode', $debug_mode);
        wp_safe_redirect(admin_url('admin.php?page=alm-settings&settings-updated=true'));
        exit;
    }
}

function alm_handle_update_upload_form() {
    if (!current_user_can('manage_options') || !check_admin_referer('alm_update_settings_nonce')) { wp_die('Izin ditolak.'); }
    $theme_slug = 'mediman';
    $option_name = 'alm_theme_update_info_' . $theme_slug;
    $current_info = get_option($option_name, []);
    $info = [
        'new_version' => sanitize_text_field($_POST['new_version']),
        'url'         => esc_url_raw($_POST['theme_url']),
        'changelog'   => wp_kses_post($_POST['changelog']),
        'package'     => $current_info['package'] ?? '',
    ];
    if (isset($_FILES['theme_zip_file']) && !empty($_FILES['theme_zip_file']['name']) && $_FILES['theme_zip_file']['error'] === UPLOAD_ERR_OK) {
        add_filter('upload_mimes', function($mimes) { $mimes['zip'] = 'application/zip'; return $mimes; });
        $uploaded_file = wp_handle_upload($_FILES['theme_zip_file'], ['test_form' => false]);
        if ($uploaded_file && !isset($uploaded_file['error'])) {
            $info['package'] = $uploaded_file['url'];
        } else {
            wp_safe_redirect(admin_url('admin.php?page=alm-theme-update&upload_error=' . urlencode($uploaded_file['error'])));
            exit;
        }
    }
    update_option($option_name, $info);
    wp_safe_redirect(admin_url('admin.php?page=alm-theme-update&settings-updated=true'));
    exit;
}

// =============================================================================
// CSS & JS KUSTOM UNTUK TAMPILAN MODERN
// =============================================================================

function alm_enqueue_admin_styles($hook) {
    if (!is_string($hook) || $hook === '') {
        return;
    }
    if (strpos($hook, 'advanced-license-manager') === false && strpos($hook, 'alm-') === false) {
        return;
    }
    add_action('admin_head', 'alm_custom_admin_css');
}

function alm_custom_admin_css() {
    ?>
    <style>
        :root {
            --alm-bg-main: #f8f9fa;
            --alm-bg-card: #ffffff;
            --alm-text-primary: #344767;
            --alm-text-secondary: #67748e;
            --alm-border-color: #e9ecef;
            --alm-primary-blue: #2563eb;
            --alm-primary-blue-hover: #1d4ed8;
            --alm-red: #dc2626;
            --alm-red-hover: #b91c1c;
            --alm-green: #16a34a;
            --alm-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --alm-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);
        }
        
        #wpbody-content {
            background-color: var(--alm-bg-main);
        }

        .alm-wrap {
            font-family: var(--alm-font-family);
            background-color: transparent;
            margin-left: 0;
            padding: 24px 20px; /* Added horizontal padding */
            min-height: calc(100vh - 32px);
        }

        .alm-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            background: var(--alm-bg-card);
            padding: 16px 24px;
            border-radius: 0.75rem;
            box-shadow: var(--alm-shadow);
        }
        .alm-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: var(--alm-text-primary);
            margin: 0;
        }
        .alm-card {
            background: var(--alm-bg-card);
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--alm-shadow);
            padding: 0; /* Changed padding to 0 for table */
        }
        .alm-card-content {
            padding: 24px;
        }
        .alm-form-table th { padding-left: 0; font-weight: 500; }
        .alm-form-table td { padding-right: 0; }
        .alm-form-table .description { font-size: 13px; color: var(--alm-text-secondary); }
        .alm-settings-card { max-width: 800px; padding: 24px; }

        /* Filter & Search Styles */
        .alm-filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            align-items: center;
        }
        .alm-search-box {
            display: flex;
            gap: inherit;
            max-width: 500px;
        }
        .alm-filters input[type="search"],
        .alm-filters select {
            background-color: #fff;
            border: 1px solid #dcdcde;
            padding: 0 12px;
            height: 42px;
            font-size: 14px;
            color: var(--alm-text-primary);
            box-shadow: none;
            transition: border-color 0.2s ease;
        }
        .alm-filters input[type="search"]:focus,
        .alm-filters select:focus {
            border-color: var(--alm-primary-blue);
            outline: none;
            box-shadow: 0 0 0 1px var(--alm-primary-blue);
        }
        .alm-search-box input[type="search"] {
             border-radius: 0.5rem;
             width:450px;
            margin: 0;
        }
        .alm-search-box .button {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            box-shadow: none !important;
            height: 42px;
            margin: 0;
            padding: 10px 20px;
        }
        .alm-filters select {
            min-width: 150px;
            border-radius: 0.5rem;
        }

        /* Table Styles */
        .alm-table-container {
            overflow-x: auto;
        }
        .alm-table {
            width: 100%;
            border-collapse: collapse;
        }
        .alm-table th, .alm-table td {
            padding: 14px 24px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid var(--alm-border-color);
        }
        .alm-table th {
            font-size: 12px;
            font-weight: 600;
            color: var(--alm-text-secondary);
            text-transform: uppercase;
        }
        .alm-table td {
            font-size: 14px;
            color: var(--alm-text-primary);
        }
        .alm-table tbody tr:last-child td {
            border-bottom: none;
        }
        .alm-author-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alm-author-cell img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        .alm-author-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .alm-author-info strong {
            font-weight: 600;
            color: var(--alm-text-primary);
        }
        .alm-author-info span {
            font-size: 12px;
            color: var(--alm-text-secondary);
        }
        .alm-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .alm-status-active { background-color: #dcfce7; color: #166534; }
        .alm-status-inactive { background-color: #f1f5f9; color: #334155; }
        .alm-action-links a {
            text-decoration: none;
            font-weight: 600;
            color: var(--alm-text-secondary);
        }
        .alm-action-links a:hover {
            color: var(--alm-primary-blue);
        }
        .alm-action-links .delete-link {
            color: var(--alm-red);
        }
        .alm-action-links .delete-link:hover {
            color: var(--alm-red-hover);
        }

        /* [NEW] Pagination Styles */
        .alm-table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-top: 1px solid var(--alm-border-color);
        }
        .alm-per-page-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--alm-text-secondary);
            font-size: 14px;
        }
        .alm-per-page-selector select {
            height: 36px;
            min-width: 80px;
            border-radius: 0.5rem;
        }
        .alm-pagination .page-numbers {
            display: inline-block;
            padding: 8px 14px;
            border: 1px solid var(--alm-border-color);
            background: var(--alm-bg-card);
            color: var(--alm-text-secondary);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .alm-pagination .page-numbers:hover {
            background: #f1f5f9;
            border-color: #d1d5db;
        }
        .alm-pagination .page-numbers.current {
            background: var(--alm-primary-blue);
            border-color: var(--alm-primary-blue);
            color: #fff;
        }

        /* Modern Button Styles */
        .alm-wrap .button,
        .alm-wrap .page-title-action,
        .alm-wrap .submit .button {
            display: inline-block;
            text-decoration: none;
            font-size: 14px;
            line-height: 1;
            height: auto;
            margin: 0;
            padding: 10px 20px;
            cursor: pointer;
            border-width: 1px;
            border-style: solid;
            -webkit-appearance: none;
            border-radius: 0.5rem;
            white-space: nowrap;
            box-sizing: border-box;
            transition: all 0.2s ease-in-out;
            font-weight: 600;
            box-shadow: var(--alm-shadow);
        }

        .alm-wrap .button-primary,
        .alm-wrap .page-title-action {
            background: var(--alm-primary-blue);
            border-color: var(--alm-primary-blue);
            color: #fff;
        }

        .alm-wrap .button-primary:hover,
        .alm-wrap .page-title-action:hover {
            background: var(--alm-primary-blue-hover);
            border-color: var(--alm-primary-blue-hover);
            color: #fff;
            transform: translateY(-1px);
        }
        
        .alm-wrap .button {
            background: var(--alm-primary-blue);
            border-color: var(--alm-border-color);
            color: #fff;
        }

        .alm-wrap .button:hover {
            background: #f8f9fa;
            border-color: #d1d5db;
            color: var(--alm-text-primary);
        }
        
        // Tambahkan di function alm_custom_admin_css()
.alm-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.alm-stat-card {
    background: var(--alm-bg-card);
    padding: 20px;
    border-radius: 8px;
    box-shadow: var(--alm-shadow);
    text-align: center;
}

.alm-stat-card .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: var(--alm-primary-blue);
}

.alm-stat-card h3 {
    margin: 10px 0 5px;
    font-size: 24px;
    color: var(--alm-text-primary);
}

.alm-stat-card p {
    margin: 0;
    color: var(--alm-text-secondary);
}
    </style>
    <?php
}

// =============================================================================
// FUNGSI RENDER HALAMAN (Tampilan Tabel Modern)
// =============================================================================

function alm_render_licenses_page() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';

    // [NEW] Pagination logic
    $items_per_page_options = [10, 22, 50];
    $items_per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $items_per_page_options) ? (int)$_GET['per_page'] : 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Logic for Search and Filter
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

    $where_clauses = [];
    $prepare_args = [];

    if (!empty($search_term)) {
        $where_clauses[] = "(license_key LIKE %s OR product_name LIKE %s OR customer_email LIKE %s)";
        $like_term = '%' . $wpdb->esc_like($search_term) . '%';
        $prepare_args[] = $like_term;
        $prepare_args[] = $like_term;
        $prepare_args[] = $like_term;
    }

    if (!empty($status_filter)) {
        $where_clauses[] = "status = %s";
        $prepare_args[] = $status_filter;
    }

    // Get total items for pagination
    $sql_count = "SELECT COUNT(*) FROM $license_table";
    if (!empty($where_clauses)) {
        $sql_count .= " WHERE " . implode(' AND ', $where_clauses);
    }
    if (!empty($prepare_args)) {
        $total_items = $wpdb->get_var($wpdb->prepare($sql_count, $prepare_args));
    } else {
        $total_items = $wpdb->get_var($sql_count);
    }

    // Get items for the current page
    $sql = "SELECT * FROM $license_table";
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $prepare_args_items = $prepare_args; // copy
    $prepare_args_items[] = $items_per_page;
    $prepare_args_items[] = $offset;

    if (!empty($prepare_args)) {
        $licenses = $wpdb->get_results($wpdb->prepare($sql, $prepare_args_items));
    } else {
        $licenses = $wpdb->get_results($wpdb->prepare($sql, [$items_per_page, $offset]));
    }
    ?>
    <div class="wrap alm-wrap">
        <div class="alm-header">
            <h1>Licenses Table</h1>
            <a href="?page=alm-add-license" class="page-title-action">Add New License</a>
        </div>

        <?php if (isset($_GET['delete_success']) || isset($_GET['add_success'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Aksi berhasil dieksekusi.</p></div>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <form method="get" class="alm-filters">
            <input type="hidden" name="page" value="advanced-license-manager" />
            <div class="alm-search-box">
                <label for="license-search-input" class="screen-reader-text">Search Licenses:</label>
                <input type="search" id="license-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by key, product, or email...">
                <input type="submit" id="search-submit" class="button" value="Search">
            </div>
            <select name="status_filter" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
            </select>
        </form>

        <div class="alm-card">
            <div class="alm-table-container">
                <table class="alm-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Author</th>
                            <th>License Key</th>
                            <th>Domains</th>
                            <th>Status</th>
                            <th>Expired</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($licenses)) {
                            echo '<tr><td colspan="7">No licenses found matching your criteria.</td></tr>';
                        } else {
                            $i = $offset + 1;
                            foreach ($licenses as $license) {
                                $status_class = 'alm-status-' . esc_attr($license->status);
                                $avatar_url = get_avatar_url($license->customer_email, ['size' => 80]);
                                ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td>
                                        <div class="alm-author-cell">
                                            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($license->customer_email); ?>">
                                            <div class="alm-author-info">
                                                <strong><?php echo esc_html($license->product_name); ?></strong>
                                                <span><?php echo esc_html($license->customer_email); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html($license->license_key); ?></code>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($license->activations) . ' / ' . esc_html($license->activation_limit); ?></strong>
                                    </td>
                                    <td>
                                        <span class="alm-status-badge <?php echo $status_class; ?>"><?php echo esc_html($license->status); ?></span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($license->expires ? date_i18n('d/m/y', strtotime($license->expires)) : 'Never'); ?>
                                    </td>
                                    <td class="alm-action-links">
                                        <a href="?page=alm-edit-license&id=<?php echo esc_attr($license->id); ?>">Edit</a> |
                                        <a href="<?php echo esc_url(wp_nonce_url('?page=advanced-license-manager&delete=' . $license->id, 'alm_delete_license_' . $license->id)); ?>" class="delete-link" onclick="return confirm('Anda yakin ingin menghapus lisensi ini secara permanen?')">Delete</a>
                                    </td>
                                </tr>
                                <?php
                                $i++;
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- [NEW] Pagination Display -->
            <div class="alm-table-footer">
                <form method="get" class="alm-per-page-selector">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
                    <?php if (!empty($search_term)) : ?>
                        <input type="hidden" name="s" value="<?php echo esc_attr($search_term); ?>">
                    <?php endif; ?>
                    <?php if (!empty($status_filter)) : ?>
                        <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                    <?php endif; ?>
                    <label for="per-page-select">Show:</label>
                    <select id="per-page-select" name="per_page" onchange="this.form.submit()">
                        <option value="10" <?php selected($items_per_page, 10); ?>>10</option>
                        <option value="22" <?php selected($items_per_page, 22); ?>>22</option>
                        <option value="50" <?php selected($items_per_page, 50); ?>>50</option>
                    </select>
                </form>
                <?php
                $total_pages = ceil($total_items / $items_per_page);
                if ($total_pages > 1) {
                    echo '<div class="alm-pagination">';
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '?paged=%#%',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ]);
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

function alm_render_add_license_page() {
    $generator = ALM_License_Generator::get_instance();
    $new_generated_key = $generator->generate_license_key();
    if (!$new_generated_key) {
        // Handle error
        wp_die('Error generating license key. Please try again.');
    }
    ?>
    <div class="wrap alm-wrap">
        <div class="alm-header">
            <h1>Add New License</h1>
            <a href="?page=advanced-license-manager" class="button">Back to List</a>
        </div>
        <div class="alm-card alm-settings-card">
            <form method="post">
                <?php wp_nonce_field('alm_add_new_nonce'); ?>
                <table class="form-table alm-form-table"><tbody>
                    <tr><th scope="row"><label for="alm-product-name">Product Name</label></th><td><input type="text" id="alm-product-name" name="product_name" value="Mediman" required class="regular-text"></td></tr>
                    <tr><th scope="row"><label for="alm-license-key">License Key</label></th><td><input type="text" id="alm-license-key" name="license_key" value="<?php echo esc_attr($new_generated_key); ?>" required class="regular-text" onfocus="this.select()"></td></tr>
                    <tr><th scope="row"><label for="alm-customer-email">Customer Email</label></th><td><input type="email" id="alm-customer-email" name="customer_email" class="regular-text"></td></tr>
                    <tr><th scope="row"><label for="alm-activation-limit">Activation Limit</label></th><td><input type="number" id="alm-activation-limit" name="activation_limit" min="1" value="1" class="small-text"></td></tr>
                    <tr><th scope="row"><label for="alm-expires">Expires</label></th><td><input type="date" id="alm-expires" name="expires"><p class="description">Biarkan kosong jika tidak ada masa kedaluwarsa.</p></td></tr>
                    <tr><th scope="row"><label for="alm-status">Status</label></th><td><select id="alm-status" name="status"><option value="active" selected>Active</option><option value="inactive">Inactive</option><option value="revoked">Revoked</option></select></td></tr>
                </tbody></table>
                <p class="submit"><button type="submit" name="add_license" class="button button-primary">Add License</button></p>
            </form>
        </div>
    </div>
    <?php
}

function alm_render_edit_license_page(){
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    $license_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$license_id) { echo '<div class="wrap alm-wrap"><h1>Invalid License ID</h1></div>'; return; }

    $license = $wpdb->get_row($wpdb->prepare("SELECT * FROM $license_table WHERE id = %d", $license_id));
    if (!$license) { echo '<div class="wrap alm-wrap"><h1>License not found</h1></div>'; return; }
    
    $activations = $wpdb->get_results($wpdb->prepare("SELECT id, site_url, activated_at FROM $activation_table WHERE license_id = %d", $license_id));
    ?>
    <div class="wrap alm-wrap">
        <div class="alm-header">
            <h1>Edit License #<?php echo esc_html($license->id); ?></h1>
            <a href="?page=advanced-license-manager" class="button">Back to List</a>
        </div>
        
       <?php if (isset($_GET['update_success']) || isset($_GET['deactivate_success']) || isset($_GET['revoke_success'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Aksi berhasil dieksekusi.</p></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            <div class="alm-card alm-card-content">
                <form method="post">
                    <input type="hidden" name="license_id" value="<?php echo esc_attr($license_id); ?>">
                    <?php wp_nonce_field('alm_edit_nonce_' . $license_id); ?>
                    <table class="form-table alm-form-table"><tbody>
                        <tr><th scope="row"><label for="product_name">Product Name</label></th><td><input type="text" id="product_name" name="product_name" value="<?php echo esc_attr($license->product_name); ?>" required class="regular-text"></td></tr>
                        <tr><th scope="row"><label for="license_key">License Key</label></th><td><input type="text" id="license_key" name="license_key" value="<?php echo esc_attr($license->license_key); ?>" required class="regular-text"></td></tr>
                        <tr><th scope="row"><label for="customer_email">Customer Email</label></th><td><input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($license->customer_email); ?>" class="regular-text"></td></tr>
                        <tr><th scope="row"><label for="activation_limit">Activation Limit</label></th><td><input type="number" id="activation_limit" name="activation_limit" value="<?php echo esc_attr($license->activation_limit); ?>" min="0" class="small-text"></td></tr>
                        <tr><th scope="row"><label for="expires">Expires</label></th><td><input type="date" id="expires" name="expires" value="<?php echo esc_attr($license->expires ? date('Y-m-d', strtotime($license->expires)) : ''); ?>"></td></tr>
                        <tr>
    <th scope="row"><label for="status">Status</label></th>
    <td>
        <select id="status" name="status">
            <?php foreach (['active', 'inactive', 'expired', 'revoked'] as $status) {
                echo '<option value="' . esc_attr($status) . '" ' . selected($license->status, $status, false) . '>' . ucfirst($status) . '</option>';
            } ?>
        </select>
    </td>
</tr>
                    </tbody></table>
                    <p class="submit"><button type="submit" name="update_license" class="button button-primary">Update License</button></p>
                    
                </form>
                
                <form method="post" style="display:inline;">
                    <input type="hidden" name="revoke_license" value="1">
                    <input type="hidden" name="license_id" value="<?php echo esc_attr($license_id); ?>">
                    <?php wp_nonce_field('alm_revoke_license_' . $license_id); ?>
                    
                </form>
            </div>
            <div class="alm-card alm-card-content">
                <h2 style="font-size: 16px; margin-top: 0; border-bottom: 1px solid var(--alm-border-color); padding-bottom: 12px;">Active Sites</h2>
                <?php if (!empty($activations)) : ?>
                    <ul style="margin: 0; padding: 0; list-style: none;">
                        <?php foreach ($activations as $act) : ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <div>
                                    <strong><?php echo esc_html($act->site_url); ?></strong><br>
                                    <small>Activated: <?php echo date_i18n('j M Y', strtotime($act->activated_at)); ?></small>
                                </div>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=alm-edit-license&id=' . $license_id . '&action=remote_deactivate&activation_id=' . $act->id, 'alm_remote_deactivate_' . $act->id)); ?>" class="button button-small button-link-delete" onclick="return confirm('Anda yakin ingin menonaktifkan situs ini?')">Deactivate</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p>No sites have been activated with this license yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <script>
document.getElementById('status').addEventListener('change', function() {
    if (this.value === 'revoked') {
        if (!confirm('Mengubah status ke revoked akan menghapus SEMUA aktivasi domain untuk lisensi ini.\n\nLanjutkan?')) {
            // Jika user klik Cancel, kembalikan ke status sebelumnya
            this.value = '<?php echo esc_js($license->status); ?>';
        }
    }
});
</script>
    </div>
    <?php
}

function alm_render_activity_log_page() {
    global $wpdb;
    $log_table = $wpdb->prefix . 'alm_logs';
    $retention_days = get_option('alm_log_retention_days', 30);
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get total logs for pagination
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
    $total_pages = ceil($total_logs / $per_page);
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $log_table ORDER BY log_time DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    ?>
    <div class="wrap alm-wrap">
        <div class="alm-header">
            <h1>Activity Log</h1>
            <div class="alm-header-actions">
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="alm-action-form">
        <input type="hidden" name="action" value="alm_manual_cleanup">
        <?php wp_nonce_field('alm_manual_cleanup'); ?>
        <button type="submit" name="alm_manual_cleanup" class="button button-primary" 
                onclick="return confirm('Are you sure you want to delete logs older than <?php echo $retention_days; ?> days?');">
            <span class="dashicons dashicons-trash" style="margin: 6px 5px 0 -3px;"></span>
            Clean Old Logs
        </button>
    </form>
    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=alm_export_logs'), 'alm_export_logs'); ?>" 
       class="button">
        <span class="dashicons dashicons-download" style="margin: 6px 5px 0 -3px;"></span>
        Export Log
    </a>
</div>
        </div>
        
        <?php
// Fungsi untuk konversi ke WIB - letakkan di functions.php atau di awal file
function format_to_wib($datetime) {
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return $date->format('j F Y, H:i:s');
}

// Untuk bagian info di atas
$date_wib = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
echo "Current Date and Time (WIB): " . $date_wib->format('Y-m-d H:i:s') . "\n";
echo "Current User's Login: " . wp_get_current_user()->user_login . "\n";
?>

        <div class="alm-card">
            <div class="alm-table-container">
                <table class="alm-table">
                    <thead>
                        <tr>
                            <th>WAKTU</th>
                            <th>AKSI</th>
                            <th>KUNCI LISENSI</th>
                            <th>URL SITUS</th>
                            <th>ALAMAT IP</th>
                            <th>PESAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)) : ?>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo format_to_wib($log->log_time); ?></td>
                                    <td>
                                        <span class="alm-badge <?php echo esc_attr($log->action); ?>">
                                            <?php echo esc_html($log->action); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo esc_html($log->license_key); ?></code></td>
                                    <td><?php echo esc_html($log->site_url); ?></td>
                                    <td><?php echo esc_html($log->ip_address); ?></td>
                                    <td><?php echo esc_html($log->message); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="alm-no-data">No activity logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="alm-pagination">
                    <?php
                    $big = 999999999;
                    echo paginate_links(array(
                        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                        'format' => '?paged=%#%',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'type' => 'list'
                    ));
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .alm-wrap {
        margin: 20px 20px 0 0;
    }

    .alm-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .button .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
    vertical-align: text-bottom;
}

.button {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}

    .alm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.alm-header-title h1 {
    margin: 0;
    font-size: 23px;
    line-height: 1.3;
    color: #1d2327;
    font-weight: 400;
    padding: 0;
}

.alm-header-actions {
    display: flex;
    gap: 8px;
}

.alm-action-form {
    margin: 0;
}

.button {
    height: 30px;
    line-height: 28px;
    padding: 0 12px;
    font-size: 13px;
}

    .alm-header h1 {
        margin: 0;
        font-size: 23px;
        font-weight: 400;
    }

    .alm-header-actions {
        display: flex;
        gap: 10px;
    }

    .alm-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .alm-table {
        width: 100%;
        border-collapse: collapse;
    }

    .alm-table th,
    .alm-table td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .alm-table th {
        background: #f8fafc;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .alm-table td {
        font-size: 14px;
        color: #1e293b;
    }

    .alm-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .alm-badge.validate_success { background: #dcfce7; color: #166534; }
    .alm-badge.rate_limit { background: #fee2e2; color: #991b1b; }
    .alm-badge.cleanup { background: #e0f2fe; color: #0369a1; }

    .alm-pagination {
    padding: 20px;
    text-align: center;
}

.alm-pagination ul {
    display: inline-flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 5px;
}

.alm-pagination .page-numbers {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 35px;
    height: 35px;
    padding: 0 5px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #333;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.alm-pagination .page-numbers.current {
    background: #2271b1;
    border-color: #2271b1;
    color: #fff;
    font-weight: 500;
}

.alm-pagination .page-numbers:hover:not(.current) {
    background: #f0f0f1;
    border-color: #c3c4c7;
}

.alm-pagination .page-numbers.dots {
    border: none;
    background: transparent;
}

.alm-pagination .prev.page-numbers,
.alm-pagination .next.page-numbers {
    padding: 0 10px;
}


    </style>
    <?php
}

function alm_render_update_settings_page() {
    $theme_slug = 'mediman';
    $option_name = 'alm_theme_update_info_' . $theme_slug;
    $update_info = get_option($option_name, ['new_version' => '', 'url' => '', 'package' => '', 'changelog' => '']);
    ?>
    <div class="wrap alm-wrap">
        <div class="alm-header">
            <h1>Theme Update Settings</h1>
        </div>
        <div class="alm-card alm-settings-card">
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible" style="margin-bottom: 20px;"><p>Pengaturan update berhasil disimpan.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['upload_error'])) : ?>
                <div class="notice notice-error is-dismissible" style="margin-bottom: 20px;"><p><strong>Upload Gagal:</strong> <?php echo esc_html(urldecode($_GET['upload_error'])); ?></p></div>
            <?php endif; ?>
            <p>Isi informasi di bawah ini untuk merilis versi baru dari tema '<?php echo esc_html($theme_slug); ?>'.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="alm_save_update_settings">
                <?php wp_nonce_field('alm_update_settings_nonce'); ?>
                <table class="form-table alm-form-table"><tbody>
                    <tr><th scope="row"><label for="new_version">New Version</label></th><td><input name="new_version" type="text" id="new_version" value="<?php echo esc_attr($update_info['new_version']); ?>" class="regular-text" placeholder="e.g., 2.1.0" required></td></tr>
                    <tr><th scope="row"><label for="theme_url">Theme Info URL</label></th><td><input name="theme_url" type="url" id="theme_url" value="<?php echo esc_attr($update_info['url']); ?>" class="regular-text" placeholder="https://aradevweb.com/mediman/"></td></tr>
                    <tr><th scope="row"><label for="theme_zip_file">Upload New Theme .zip</label></th><td><input name="theme_zip_file" type="file" id="theme_zip_file" accept=".zip"><p class="description">Unggah file ZIP versi baru. Jika dikosongkan, URL unduhan lama akan tetap digunakan.</p><?php if (!empty($update_info['package'])) : ?><p><strong>Current URL:</strong> <code><?php echo esc_url($update_info['package']); ?></code></p><?php endif; ?></td></tr>
                    <tr><th scope="row"><label for="changelog">Changelog</label></th><td><textarea name="changelog" id="changelog" rows="5" class="large-text"><?php echo esc_textarea($update_info['changelog']); ?></textarea><p class="description">Anda bisa menggunakan tag HTML dasar seperti `<h3>`, `<ul>`, `<li>`.</p></td></tr>
                </tbody></table>
                <?php submit_button('Save Update Info'); ?>
            </form>
        </div>
    </div>
    <?php
}


function alm_render_settings_page() {
    $secret_keys = get_option('alm_secret_keys', []);
    $log_retention = get_option('alm_log_retention_days', 7);

    // Email options
    $email_subject = get_option('alm_email_subject', '⚠️ Lisensi Anda Akan Berakhir dalam {days_left} Hari');
    $email_body = get_option('alm_email_body', '<h2>Hai, {user_name}!</h2><p>Lisensi Anda (<b>{license_key}</b>) akan <b>berakhir pada {expiry_date}</b>.<br>Sisa waktu: <b>{days_left} hari lagi</b>.</p><a href="{renewal_link}" style="background:#2d8cff;color:#fff;padding:12px 25px;text-decoration:none;border-radius:6px;display:inline-block;margin-top:18px;">Perpanjang Lisensi</a>');
    $email_sender = get_option('alm_email_sender', get_option('admin_email'));
    $email_replyto = get_option('alm_email_replyto', get_option('admin_email'));
    $email_reminder_days = get_option('alm_email_reminder_days', [7,3,1]);
    $email_after_expired = get_option('alm_email_after_expired', 1);
    $email_enable = get_option('alm_email_enable', 1);

    // Tab navigation
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api_secret';
    ?>
    <div class="wrap alm-wrap">
        <div class="alm-header">
            <h1>License Manager Settings</h1>
        </div>
        <h2 class="nav-tab-wrapper">
            <a href="?page=alm-settings&tab=api_secret" class="nav-tab <?php echo ($active_tab == 'api_secret') ? 'nav-tab-active' : ''; ?>">API Secret Keys</a>
            <a href="?page=alm-settings&tab=email" class="nav-tab <?php echo ($active_tab == 'email') ? 'nav-tab-active' : ''; ?>">Email Settings</a>
        </h2>
        <div class="alm-card alm-settings-card">
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible" style="margin-bottom: 20px;"><p>Pengaturan berhasil disimpan.</p></div>
            <?php endif; ?>

            <?php if ($active_tab == 'api_secret') : ?>
                <form method="post">
                    <input type="hidden" name="alm_action" value="save_settings">
                    <?php wp_nonce_field('alm_save_settings_nonce'); ?>
                    <table class="form-table alm-form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="alm_secret_keys">API Secret Keys</label></th>
                                <td>
                                    <textarea name="alm_secret_keys" id="alm_secret_keys" class="large-text" rows="5" placeholder="Satu kunci per baris..."><?php echo esc_textarea(implode("\n", $secret_keys)); ?></textarea>
                                    <p class="description">Masukkan satu secret key per baris. Klien dapat menggunakan kunci mana pun dari daftar ini untuk autentikasi.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="alm_log_retention">Log Retention Period</label></th>
                                <td>
                                    <select name="alm_log_retention_days" id="alm_log_retention">
                                        <option value="7" <?php selected($log_retention, 7); ?>>7 days</option>
                                        <option value="14" <?php selected($log_retention, 14); ?>>14 days</option>
                                        <option value="30" <?php selected($log_retention, 30); ?>>30 days</option>
                                        <option value="60" <?php selected($log_retention, 60); ?>>60 days</option>
                                        <option value="90" <?php selected($log_retention, 90); ?>>90 days</option>
                                    </select>
                                    <p class="description">Logs older than this will be automatically deleted.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <button type="submit" name="generate_new_key" class="button" value="1">Generate New & Save</button>
                        <button type="submit" name="save_settings" class="button button-primary">Save Settings</button>
                    </p>
                </form>
            <?php elseif ($active_tab == 'email') : ?>
                <form method="post" id="alm-email-settings-form">
                    <input type="hidden" name="alm_action" value="save_email_settings">
                    <?php wp_nonce_field('alm_save_email_settings_nonce'); ?>
                    <table class="form-table alm-form-table">
                        <tbody>
                            <tr>
                                <th scope="row">Aktifkan Notifikasi Email</th>
                                <td>
                                    <label><input type="checkbox" name="alm_email_enable" value="1" <?php checked($email_enable, 1); ?>> Enable Email Notification</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Jadwal Reminder Expired</th>
                                <td>
                                    <label><input type="checkbox" name="alm_email_reminder_days[]" value="7" <?php if(in_array(7, (array)$email_reminder_days)) echo 'checked'; ?>> 7 hari sebelum expired</label><br>
                                    <label><input type="checkbox" name="alm_email_reminder_days[]" value="3" <?php if(in_array(3, (array)$email_reminder_days)) echo 'checked'; ?>> 3 hari sebelum expired</label><br>
                                    <label><input type="checkbox" name="alm_email_reminder_days[]" value="1" <?php if(in_array(1, (array)$email_reminder_days)) echo 'checked'; ?>> 1 hari sebelum expired</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Notifikasi Setelah Expired</th>
                                <td>
                                    <label><input type="checkbox" name="alm_email_after_expired" value="1" <?php checked($email_after_expired, 1); ?>> Kirim email setelah expired</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Email Pengirim</th>
                                <td>
                                    <input type="text" name="alm_email_sender" class="regular-text" value="<?php echo esc_attr($email_sender); ?>">
                                    <p class="description">Alamat yang tampil sebagai pengirim email.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Reply-to</th>
                                <td>
                                    <input type="text" name="alm_email_replyto" class="regular-text" value="<?php echo esc_attr($email_replyto); ?>">
                                    <p class="description">Email untuk balasan.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Subjek Email</th>
                                <td>
                                    <input type="text" name="alm_email_subject" class="large-text" value="<?php echo esc_attr($email_subject); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Isi Email (HTML)</th>
                                <td>
                                    <textarea name="alm_email_body" class="large-text" rows="8"><?php echo esc_textarea($email_body); ?></textarea>
                                    <p class="description">
                                        Variabel yang bisa digunakan: <br>
                                        <code>{user_name}</code>, <code>{license_key}</code>, <code>{expiry_date}</code>, <code>{days_left}</code>, <code>{renewal_link}</code>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Preview Email</th>
                                <td>
                                    <button type="button" class="button" id="alm-preview-email-btn">Preview Email</button>
                                    <button type="button" class="button" id="alm-test-email-btn">Kirim Tes Email ke Admin</button>
                                    <div id="alm-preview-email" style="margin-top:18px;border:1px solid #ddd;padding:18px;max-width:520px;display:none;background:#fafbfc"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Email Settings</button>
                    </p>
                </form>
                <script>
                // Simple preview logic (client-side only for demo purpose)
                document.getElementById('alm-preview-email-btn').onclick = function() {
                    var body = document.querySelector('[name="alm_email_body"]').value;
                    body = body.replace(/{user_name}/g, 'Andi')
                               .replace(/{license_key}/g, 'LIC-1234-XXXX')
                               .replace(/{expiry_date}/g, '2025-10-01')
                               .replace(/{days_left}/g, '7')
                               .replace(/{renewal_link}/g, 'https://yourplugin.com/renew');
                    var preview = document.getElementById('alm-preview-email');
                    preview.innerHTML = body;
                    preview.style.display = 'block';
                };
                document.getElementById('alm-test-email-btn').onclick = function() {
                    alert('Fitur kirim tes email perlu implementasi AJAX di backend.');
                };
                </script>
            <?php endif; ?>
        </div>
    </div>
    <style>
        .nav-tab-wrapper { margin-bottom:20px; }
        .alm-card { max-width:800px; }
        .form-table th { width:210px; }
        #alm-preview-email { border-radius:8px; }
    </style>
    <?php
}
?>




