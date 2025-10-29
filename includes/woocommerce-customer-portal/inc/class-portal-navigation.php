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
    
    public function customize_menu($items) {
    // Pastikan label lain juga bisa override jika ingin
    if (isset($items['dashboard'])) {
        $items['dashboard'] = __('Beranda', 'wc-customer-portal');
    }
    if (isset($items['orders'])) {
        $items['orders'] = __('Pesanan Saya', 'wc-customer-portal');
    }
    if (isset($items['licenses'])) {
        $items['licenses'] = __('Lisensi Saya', 'wc-customer-portal');
    }
    if (isset($items['downloads'])) {
        $items['downloads'] = __('Unduhan', 'wc-customer-portal');
    }
    if (isset($items['addresses'])) {
        $items['addresses'] = __('Alamat', 'wc-customer-portal');
    }
    if (isset($items['edit-account'])) {
        $items['edit-account'] = __('Detail Akun', 'wc-customer-portal');
    }
    if (isset($items['customer-logout'])) {
        $items['customer-logout'] = __('Keluar', 'wc-customer-portal');
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
?>