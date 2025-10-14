<?php
/**
 * Portal Navigation
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Navigation {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('woocommerce_account_menu_items', array($this, 'customize_menu'), 999);
        add_action('template_redirect', array($this, 'redirect_duplicate_endpoint'));
    }
    
    /**
     * Customize menu - Remove duplicate licenses
     */
    public function customize_menu($items) {
        
        // Remove duplicate "my-licenses" if exists
        if (isset($items['my-licenses'])) {
            unset($items['my-licenses']);
        }
        
        // Ensure "licenses" menu has correct label
        if (isset($items['licenses'])) {
            $items['licenses'] = __('Lisensi Saya', 'wc-customer-portal');
        }
        
        return $items;
    }
    
    /**
     * Redirect duplicate endpoint to correct one
     */
    public function redirect_duplicate_endpoint() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['my-licenses'])) {
            wp_safe_redirect(wc_get_account_endpoint_url('licenses'));
            exit;
        }
    }
}