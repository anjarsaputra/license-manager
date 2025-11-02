<?php
/**
 * Plugin Name: WooCommerce Customer Portal
 * Description: Custom My Account portal with ALM license management integration
 * Version: 2.0.0
 * Author: ARAdev
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WCP_VERSION', '2.0.0');
define('WCP_PLUGIN_FILE', __FILE__);
define('WCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCP_INC_DIR', WCP_PLUGIN_DIR . 'inc/');
define('WCP_ASSETS_URL', WCP_PLUGIN_URL . 'assets/');
define('WCP_TEMPLATES_DIR', WCP_PLUGIN_DIR . 'templates/');

/**
 * Check requirements
 */
function wcp_check_requirements() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>WooCommerce Customer Portal</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Load all portal files
 */
function wcp_load_files() {
    
    if (!wcp_check_requirements()) {
        return;
    }
    
    $inc_dir = WCP_INC_DIR;
    
    // Core files - Load in order
    $core_files = [
        'class-portal-helpers.php',      // Helper functions (ALM integration)
        'class-portal-init.php',         // Initialize endpoints
        'class-portal-assets.php',       // CSS/JS loading
        'class-portal-navigation.php',   // Menu customization
        'class-portal-dashboard.php',    // Dashboard page
        'class-portal-orders.php',       // Custom order cards
        'class-portal-downloads.php',    // Custom downloads UI
        'class-portal-address.php',      // 🆕 Modern address & edit form
    ];
    
    foreach ($core_files as $file) {
        $path = $inc_dir . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
    
    // Original License Tab files (already working)
    $license_files = [
        'class-wc-license-tab.php',
        'tab-ajax.php',
        //'rest-api-deactivate.php',
    ];
    
    foreach ($license_files as $file) {
        $path = WCP_PLUGIN_DIR . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
    
    // Initialize components
    if (class_exists('WCP_Portal_Init')) {
        WCP_Portal_Init::instance();
    }
    
    if (class_exists('WCP_Portal_Assets')) {
        WCP_Portal_Assets::instance();
    }
    
    if (class_exists('WCP_Portal_Navigation')) {
        WCP_Portal_Navigation::instance();
    }
    
    if (class_exists('WCP_Portal_Dashboard')) {
        WCP_Portal_Dashboard::instance();
    }
    
    if (class_exists('WC_License_Tab')) {
        WC_License_Tab::instance();
    }
    
    if (class_exists('WCP_Portal_Orders')) {
        WCP_Portal_Orders::instance();
    }
    
    if (class_exists('WCP_Portal_Downloads')) {
        WCP_Portal_Downloads::instance();
    }
    
    if (class_exists('WCP_Portal_Address')) {
        WCP_Portal_Address::instance();
    }
}
add_action('plugins_loaded', 'wcp_load_files', 20);

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

/**
 * === CUSTOM CARD FOR EDIT ADDRESS ===
 */

// Card custom untuk halaman edit Billing Address
add_action('woocommerce_before_edit_address_form_billing', function() {
    ?>
    <div class="wcp-edit-address-card">
        <h2>
            <span style="font-size:2rem;vertical-align:-5px;">🏠</span>
            Alamat Tagihan
        </h2>
        <p class="wcp-edit-address-desc">
            Silakan isi atau ubah data alamat tagihan Anda. Data ini digunakan untuk tagihan pesanan.
        </p>
    </div>
    <style>
    .wcp-edit-address-card {
        background: linear-gradient(90deg,#f8fafc 0%, #f1f5f9 100%);
        border-radius: 18px;
        box-shadow: 0 2px 16px rgba(59,130,246,0.06);
        padding: 30px 34px;
        margin-bottom: 28px;
        margin-top: 7px;
        max-width: 640px;
    }
    .wcp-edit-address-card h2 {
        font-size: 1.7rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: #2563eb;
    }
    .wcp-edit-address-desc {
        color: #64748b;
        font-size: 1.1rem;
        margin-bottom: 0;
    }
    @media (max-width: 500px) {
        .wcp-edit-address-card { padding: 18px 10px; }
        .wcp-edit-address-card h2 { font-size:1.2rem;}
    }
    </style>
    <?php
});

// Card custom untuk halaman edit Shipping Address
add_action('woocommerce_before_edit_address_form_shipping', function() {
    ?>
    <div class="wcp-edit-address-card">
        <h2>
            <span style="font-size:2rem;vertical-align:-5px;">📦</span>
            Alamat Pengiriman
        </h2>
        <p class="wcp-edit-address-desc">
            Silakan isi atau ubah data alamat pengiriman Anda. Data ini digunakan untuk pengiriman pesanan.
        </p>
    </div>
    <style>
    .wcp-edit-address-card {
        background: linear-gradient(90deg,#e0e7ff 0%, #f1f5f9 100%);
        border-radius: 18px;
        box-shadow: 0 2px 16px rgba(59,130,246,0.06);
        padding: 30px 34px;
        margin-bottom: 28px;
        margin-top: 7px;
        max-width: 640px;
    }
    .wcp-edit-address-card h2 {
        font-size: 1.7rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: #312e81;
    }
    .wcp-edit-address-desc {
        color: #64748b;
        font-size: 1.1rem;
        margin-bottom: 0;
    }
    @media (max-width: 500px) {
        .wcp-edit-address-card { padding: 18px 10px; }
        .wcp-edit-address-card h2 { font-size:1.2rem;}
    }
    </style>
    <?php
});



//custom member area login
add_action('wcp_custom_login_render', function() {
    if (!is_user_logged_in()) {
        include WCP_TEMPLATES_DIR . 'form-login.php';
    } else {
        echo '<div class="wcp-login-msg">Anda sudah login ke member-area.</div>';
    }
});
do_action('wcp_custom_login_render');

add_action('init', function() {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['wcp_custom_login_nonce'])
        && wp_verify_nonce($_POST['wcp_custom_login_nonce'], 'wcp_custom_login')
    ) {
        $credentials = [
            'user_login'    => sanitize_text_field($_POST['wcp_username']),
            'user_password' => $_POST['wcp_password'],
            'remember'      => true,
        ];
        $user = wp_signon($credentials, false);

        if (is_wp_error($user)) {
            // Tampilkan error login
            add_filter('wcp_custom_login_error', function() use ($user) {
                return '<div class="wcp-login-error" style="color: #e53e3e; margin:10px 0;">' . esc_html($user->get_error_message()) . '</div>';
            });
        } else {
            wp_redirect(home_url('/member-area/'));
            exit;
        }
    }
});


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

