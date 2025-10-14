<?php
/**
 * Portal Helper Functions - ALM Integration
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Helpers {
    
    /**
     * Get customer licenses from ALM table (by email)
     */
    public static function get_customer_licenses($customer_id) {
        $licenses = [];
        global $wpdb;
        
        $user = get_userdata($customer_id);
        if (!$user) {
            return [];
        }
        
        $license_table = $wpdb->prefix . 'alm_licenses';
        
        // Check table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$license_table}'") != $license_table) {
            error_log('WCP: ALM licenses table not found');
            return [];
        }
        
        // Get licenses by customer email
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$license_table} WHERE customer_email = %s ORDER BY created_at DESC",
            $user->user_email
        ));
        
        if (empty($results)) {
            return [];
        }
        
        foreach ($results as $license) {
            $licenses[] = self::format_alm_license($license);
        }
        
        return $licenses;
    }
    
    /**
     * Format ALM license data
     */
    private static function format_alm_license($license) {
        
        $product_name = !empty($license->product_name) ? $license->product_name : 'Unknown Product';
        $activations = !empty($license->activations) ? intval($license->activations) : 0;
        $max_activations = !empty($license->activation_limit) ? intval($license->activation_limit) : 1;
        $status = !empty($license->status) ? strtolower($license->status) : 'active';
        
        // Handle expiration
        $expires = 'Lifetime';
        $expires_at = null;
        
        if (!empty($license->expires) && $license->expires != '0000-00-00 00:00:00') {
            $expires_at = $license->expires;
            $expires_timestamp = strtotime($expires_at);
            
            if ($expires_timestamp) {
                $expires = date('d M Y', $expires_timestamp);
                
                if ($expires_timestamp < time()) {
                    $status = 'expired';
                }
            }
        }
        
        return [
            'id' => $license->id,
            'key' => $license->license_key,
            'product' => $product_name,
            'status' => $status,
            'activations' => $activations . '/' . $max_activations,
            'activations_used' => $activations,
            'activations_max' => $max_activations,
            'expires' => $expires,
            'expires_at' => $expires_at,
            'created_at' => $license->created_at,
            'order_id' => 0,
        ];
    }
    
    /**
     * Get license statistics
     */
    public static function get_license_stats($customer_id) {
        $licenses = self::get_customer_licenses($customer_id);
        
        return [
            'total' => count($licenses),
            'active' => count(array_filter($licenses, fn($l) => $l['status'] === 'active')),
            'expired' => count(array_filter($licenses, fn($l) => $l['status'] === 'expired')),
            'disabled' => count(array_filter($licenses, fn($l) => $l['status'] === 'disabled')),
        ];
    }
    
    /**
     * Get customer orders
     */
    public static function get_customer_orders($customer_id, $limit = -1) {
        return wc_get_orders([
            'customer_id' => $customer_id,
            'status' => ['wc-completed', 'wc-processing'],
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }
    
    /**
     * Get customer downloads
     */
    public static function get_customer_downloads($customer_id) {
        if (function_exists('wc_get_customer_available_downloads')) {
            return wc_get_customer_available_downloads($customer_id);
        }
        return [];
    }
    
    /**
     * Calculate total spent
     */
    public static function get_total_spent($customer_id) {
        $customer = new WC_Customer($customer_id);
        
        if (method_exists($customer, 'get_total_spent')) {
            return $customer->get_total_spent();
        }
        
        return 0;
    }
    
    /**
     * Get greeting based on time
     */
    public static function get_greeting() {
        $hour = current_time('H');
        
        if ($hour < 12) {
            return __('Selamat Pagi', 'wc-customer-portal');
        } elseif ($hour < 18) {
            return __('Selamat Siang', 'wc-customer-portal');
        } else {
            return __('Selamat Malam', 'wc-customer-portal');
        }
    }
    
    /**
     * Get license activations (active sites)
     */
    public static function get_license_activations($license_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'alm_license_activations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return [];
        }
        
        // Simple query - no status column in ALM table
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE license_id = %d ORDER BY activated_at DESC",
            $license_id
        ));
    }
}