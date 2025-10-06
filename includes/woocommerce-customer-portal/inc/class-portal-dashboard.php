<?php
/**
 * Portal Dashboard
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Dashboard {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('woocommerce_account_dashboard', array($this, 'render_dashboard'), 5);
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        $customer_id = $current_user->ID;
        
        // Get data
        $licenses = WCP_Portal_Helpers::get_customer_licenses($customer_id);
        $orders = WCP_Portal_Helpers::get_customer_orders($customer_id);
        $downloads = WCP_Portal_Helpers::get_customer_downloads($customer_id);
        $stats = WCP_Portal_Helpers::get_license_stats($customer_id);
        $total_spent = WCP_Portal_Helpers::get_total_spent($customer_id);
        
        // Load template
        include WCP_PLUGIN_DIR . 'templates/dashboard.php';
    }
}