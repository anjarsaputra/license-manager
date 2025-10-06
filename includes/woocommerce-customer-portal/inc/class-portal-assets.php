<?php
/**
 * Portal Assets Management
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Assets {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        if (!is_account_page()) {
            return;
        }
        
        // EXISTING: License tab styles (keep for license functionality)
        wp_enqueue_style(
            'wc-license-tab',
            WCP_ASSETS_URL . 'css/wc-license-tab.css',
            [],
            WCP_VERSION
        );
        
        // NEW: Portal custom styles (for dashboard, orders, etc)
        wp_enqueue_style(
            'wcp-portal-styles',
            WCP_ASSETS_URL . 'css/portal-styles.css',
            ['wc-license-tab'], // Load after license tab styles
            WCP_VERSION
        );
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_account_page()) {
            return;
        }
        
        // EXISTING: License tab scripts (keep for license functionality)
        wp_enqueue_script(
            'wc-license-tab',
            WCP_ASSETS_URL . 'js/wc-license-tab.js',
            ['jquery'],
            WCP_VERSION,
            true
        );
        
        // NEW: Portal custom scripts (for dashboard, orders, etc)
        wp_enqueue_script(
            'wcp-portal-scripts',
            WCP_ASSETS_URL . 'js/portal-scripts.js',
            ['jquery', 'wc-license-tab'], // Load after license tab scripts
            WCP_VERSION,
            true
        );
        
        // Localize for AJAX
        wp_localize_script('wcp-portal-scripts', 'wcpPortal', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcp_portal'),
            'i18n' => [
                'copied' => __('Copied!', 'wc-customer-portal'),
                'copyFailed' => __('Failed to copy', 'wc-customer-portal'),
                'confirmDeactivate' => __('Are you sure you want to deactivate this site?', 'wc-customer-portal'),
            ]
        ]);
    }
}