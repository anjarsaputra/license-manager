<?php
/**
 * WooCommerce License Tab - Customer Portal
 * 
 * Add "Licenses" tab to WooCommerce My Account
 * for customers to manage their licenses
 * 
 * @package License Manager
 * @version 1.0.0
 * @author anjarsaputra
 * @since 2025-10-02
 */

// Security: Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class ALM_WC_License_Tab {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Tab slug
     */
    private $tab_slug = 'licenses';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Setup hooks
     */
    private function __construct() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Register hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add "Licenses" tab to My Account menu
        add_filter('woocommerce_account_menu_items', array($this, 'add_licenses_tab'), 40);
        
        // Register endpoint
        add_action('init', array($this, 'add_licenses_endpoint'));
        
        // Display tab content
        add_action('woocommerce_account_licenses_endpoint', array($this, 'render_licenses_content'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Admin notice if WooCommerce not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('License Manager - Customer Portal:', 'alm'); ?></strong>
                <?php _e('WooCommerce is required for the customer portal feature. Please install and activate WooCommerce.', 'alm'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add "Licenses" tab to WooCommerce My Account
     * 
     * @param array $items Menu items
     * @return array Modified items
     */
    public function add_licenses_tab($items) {
        // Insert after "Orders"
        $new_items = array();
        
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            // Add after orders
            if ($key === 'orders') {
                $new_items[$this->tab_slug] = __('My Licenses', 'alm');
            }
        }
        
        return $new_items;
    }
    
    /**
     * Register custom endpoint
     * 
     * Creates URL: /my-account/licenses/
     */
    public function add_licenses_endpoint() {
        add_rewrite_endpoint($this->tab_slug, EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = $this->tab_slug;
        return $vars;
    }
    
    /**
     * Render licenses tab content
     */
    public function render_licenses_content() {
        // Get current user
        $current_user = wp_get_current_user();
        $user_email   = $current_user->user_email;
        $user_name    = $current_user->display_name;
        
        // Fetch licenses from database
        $licenses = $this->get_user_licenses($user_email);
        
        // Check if user has orders with license products
        $has_license_orders = $this->user_has_license_orders($current_user->ID);
        
        // Load template
        $template_path = plugin_dir_path(__FILE__) . 'tab-template.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="woocommerce-info">';
            echo '<p>' . __('License template not found. Please contact support.', 'alm') . '</p>';
            echo '<p><small>Path: ' . esc_html($template_path) . '</small></p>';
            echo '</div>';
        }
    }
    
    /**
     * Enqueue CSS and JS assets for customer portal
     */
    public function enqueue_assets() {
        // Only load on My Account pages
        if (!is_account_page()) {
            return;
        }
        
        // Get assets URL (relative to this file's location)
        $assets_url = plugin_dir_url(__FILE__) . 'assets/';
        
        // Get version (use constant if available, otherwise use file time)
        $version = defined('ALM_VERSION') ? ALM_VERSION : filemtime(plugin_dir_path(__FILE__) . 'class-wc-license-tab.php');
        
        // CSS path
        $css_path = plugin_dir_path(__FILE__) . 'assets/css/wc-license-tab.css';
        $js_path = plugin_dir_path(__FILE__) . 'assets/js/wc-license-tab.js';
        
        // Enqueue CSS (only if file exists)
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'alm-wc-license-tab',
                $assets_url . 'css/wc-license-tab.css',
                array(),
                $version
            );
        }
        
        // Enqueue JS (only if file exists)
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'alm-wc-license-tab',
                $assets_url . 'js/wc-license-tab.js',
                array('jquery'),
                $version,
                true
            );
            
            // Get current user data safely
            $current_user = wp_get_current_user();
            
            // Localize script for AJAX
            // Localize script for AJAX
wp_localize_script('alm-wc-license-tab', 'almLicenseData', array(
    'ajax_url'   => admin_url('admin-ajax.php'),
    'rest_url'   => rest_url(), // ✅ REST API URL
    'nonce'      => wp_create_nonce('alm_license_action'),
    'rest_nonce' => wp_create_nonce('wp_rest'), // ✅ REST nonce
    'user_id'    => get_current_user_id(),
    'user_email' => $current_user->user_email,
    'strings'    => array(
        'confirm_deactivate' => __('Are you sure you want to deactivate this site?', 'alm'),
        'deactivating'       => __('Deactivating...', 'alm'),
        'deactivated'        => __('✅ Site deactivated successfully!', 'alm'),
        'copied'             => __('✅ Copied to clipboard!', 'alm'),
        'copy_failed'        => __('❌ Failed to copy. Please copy manually.', 'alm'),
        'error'              => __('❌ Error occurred. Please try again.', 'alm'),
        'loading'            => __('Loading...', 'alm'),
        'rate_limit'         => __('⚠️ Too many attempts. Please wait a few minutes.', 'alm'),
    )
));
        }
    }
    
    /**
     * Get user's licenses from database
     * 
     * @param string $email User email
     * @return array Licenses
     */
    private function get_user_licenses($email) {
        if (empty($email) || !is_email($email)) {
            return array();
        }
        
        global $wpdb;
        
        // Check cache first (5 minutes)
        $cache_key = 'alm_wc_licenses_' . md5($email);
        $cached = get_transient($cache_key);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
        
        // Query licenses table
        $license_table = $wpdb->prefix . 'alm_licenses';
        $activation_table = $wpdb->prefix . 'alm_license_activations';
        
        $licenses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$license_table} 
            WHERE customer_email = %s 
            ORDER BY created_at DESC",
            $email
        ), ARRAY_A);
        
        if (empty($licenses)) {
            return array();
        }
        
        // Enhance with activation data
        // ✅ FIX: Tambahkan & untuk by reference
        foreach ($licenses as &$license) {
            // Get active sites for this license
            // ✅ FIX: Hapus 'AND status = active' dan tambahkan 'id' di SELECT
            $active_sites = $wpdb->get_results($wpdb->prepare(
                "SELECT id, site_url, activated_at 
                FROM {$activation_table} 
                WHERE license_id = %d
                ORDER BY activated_at DESC",
                $license['id']
            ), ARRAY_A);
            
            $license['active_sites'] = $active_sites ? $active_sites : array();
            
            // Get WooCommerce order data if order_id exists
            if (!empty($license['order_id']) && function_exists('wc_get_order')) {
                $order = wc_get_order($license['order_id']);
                
                if ($order) {
                    $license['order_data'] = array(
                        'order_number' => $order->get_order_number(),
                        'order_date'   => $order->get_date_created()->date('Y-m-d H:i:s'),
                        'order_url'    => $order->get_view_order_url(),
                        'order_total'  => $order->get_formatted_order_total(),
                        'order_status' => $order->get_status(),
                    );
                }
            }
        }
        
        // ✅ FIX: Unset reference setelah loop
        unset($license);
        
        // Cache for 5 minutes
        set_transient($cache_key, $licenses, 5 * MINUTE_IN_SECONDS);
        
        return $licenses;
    }
    
    /**
     * Check if user has orders with license products
     * 
     * @param int $user_id User ID
     * @return bool
     */
    private function user_has_license_orders($user_id) {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status'      => array('wc-completed', 'wc-processing'),
            'limit'       => -1,
        ));
        
        if (empty($orders)) {
            return false;
        }
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                
                if (!$product) {
                    continue;
                }
                
                // Check if product is license product
                $is_license = get_post_meta($product->get_id(), '_is_license_product', true);
                
                if ($is_license === 'yes') {
                    return true;
                }
                
                // Check product tag
                if (has_term('lisensi', 'product_tag', $product->get_id())) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Clear license cache
     * 
     * @param string $email User email
     */
    public static function clear_cache($email = '') {
        if (empty($email) && is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;
        }
        
        if (!empty($email)) {
            $cache_key = 'alm_wc_licenses_' . md5($email);
            delete_transient($cache_key);
        }
    }
}

// Initialize class when file is loaded
// (Only if WooCommerce is active)
if (class_exists('WooCommerce')) {
    ALM_WC_License_Tab::get_instance();
}