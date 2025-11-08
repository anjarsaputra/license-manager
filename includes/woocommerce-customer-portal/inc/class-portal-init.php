<?php
/**
 * Portal Initialization
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Init {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_endpoints'));
        add_action('admin_init', array($this, 'redirect_customers'));
        add_action('after_setup_theme', array($this, 'hide_admin_bar'));
    }
    
    /**
     * Register custom endpoints
     */
    public function register_endpoints() {
        // My Licenses endpoint (if not already registered)
        add_rewrite_endpoint('my-licenses', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Redirect customers from wp-admin to My Account
     */
    public function redirect_customers() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        if (is_admin() && !current_user_can('edit_posts')) {
            
            exit;
        }
    }
    
    /**
     * Hide admin bar for customers
     */
    public function hide_admin_bar() {
        if (is_user_logged_in() && !current_user_can('edit_posts')) {
            show_admin_bar(false);
        }
    }
}