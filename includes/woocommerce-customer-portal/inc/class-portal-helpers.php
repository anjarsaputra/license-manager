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
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE license_id = %d ORDER BY activated_at DESC",
            $license_id
        ));
    }

        /**
     * AUTO GENERATE LICENSE AFTER PURCHASE (1-year validity)
     */
    /**
 * Generate license automatically when order is completed
 */
public static function generate_license_on_order($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $email = $order->get_billing_email();
    $user_id = $order->get_user_id();

    global $wpdb;
    $table = $wpdb->prefix . 'alm_licenses';

    // Load generator
    if (!class_exists('ALM_License_Generator')) {
        require_once WP_PLUGIN_DIR . '/license-manager/includes/license-generator.php';
    }

    $generator = ALM_License_Generator::get_instance();

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $product_name = $item->get_name();

        // Hindari duplikat lisensi untuk produk yang sama
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE product_name = %s AND customer_email = %s",
            $product_name,
            $email
        ));
        if ($exists > 0) continue;

        // ✅ Generate license key pakai sistem kamu
        $license_key = $generator->generate_license_key();

        // Hitung expired date (1 tahun dari sekarang)
        $expires = date('Y-m-d H:i:s', strtotime('+1 year'));

        // Simpan ke database
        $wpdb->insert($table, [
            'license_key'     => $license_key,
            'product_name'    => $product_name,
            'customer_email'  => $email,
            'status'          => 'active',
            'activations'     => 0,
            'activation_limit'=> 1,
            'created_at'      => current_time('mysql'),
            'expires'         => $expires,
        ]);
    }
}

    
        /**
     * CRON: Auto mark expired licenses in database
     */
        /**
     * CRON: Auto mark expired licenses in database
     */
    public static function check_and_expire_licenses() {
        global $wpdb;
        $table = $wpdb->prefix . 'alm_licenses';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            return;
        }

        $now = current_time('mysql');

        // Update semua lisensi yang sudah lewat tanggal expired
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = 'expired'
                 WHERE expires IS NOT NULL
                 AND expires != '0000-00-00 00:00:00'
                 AND expires < %s
                 AND status != 'expired'",
                $now
            )
        );

        if ($updated) {
            error_log("WCP: {$updated} license(s) marked as expired.");
        }
    }



}

// === Hook: auto generate license saat order selesai ===
add_action('woocommerce_order_status_completed', ['WCP_Portal_Helpers', 'generate_license_on_order']);
add_action('woocommerce_order_status_processing', ['WCP_Portal_Helpers', 'generate_license_on_order']);

// === Custom interval: 6 hours ===
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['six_hours'])) {
        $schedules['six_hours'] = [
            'interval' => 6 * 60 * 60, // 6 jam dalam detik
            'display'  => __('Every 6 Hours', 'wc-customer-portal')
        ];
    }
    return $schedules;
});

// === Schedule event: check lisensi kadaluarsa ===
if (!wp_next_scheduled('wcp_expire_licenses_event')) {
    wp_schedule_event(time(), 'six_hours', 'wcp_expire_licenses_event');
}
add_action('wcp_expire_licenses_event', ['WCP_Portal_Helpers', 'check_and_expire_licenses']);
