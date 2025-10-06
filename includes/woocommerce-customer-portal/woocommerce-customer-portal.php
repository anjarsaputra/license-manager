<?php
/**
 * Plugin Name: WooCommerce Customer Portal
 * Description: Custom My Account portal with ALM license management integration
 * Version: 2.0.0
 * Author: Anjar Saputra
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