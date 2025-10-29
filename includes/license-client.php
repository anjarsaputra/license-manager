<?php
/**
 * License Client Library
 * Untuk TEMA yang dijual ke pembeli
 */

class Theme_License_Client {
    
    private $server_url = 'https://aratheme.id'; // Ganti dengan URL server kamu
    private $product_id = 'namatema'; // ID tema kamu
    
    /**
     * Activate License
     */
    public function activate($license_key) {
        $site_url = get_site_url();
        
        $response = wp_remote_post($this->server_url . '/wp-json/alm/v1/activate', [
            'headers' => [
                'X-Alm-Secret' => $this->get_secret_key()
            ],
            'body' => [
                'license_key' => sanitize_text_field($license_key),
                'site_url' => $site_url,
                'product_id' => $this->product_id
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Tidak dapat terhubung ke server lisensi: ' . $response->get_error_message()
            ];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body['success']) {
            // Simpan lisensi yang aktif
            update_option('namatema_license_key', $license_key);
            update_option('namatema_license_status', 'active');
            update_option('namatema_license_activated_at', current_time('mysql'));
        }
        
        return $body;
    }
    
    /**
     * Deactivate License
     */
    public function deactivate($license_key) {
        $site_url = get_site_url();
        
        $response = wp_remote_post($this->server_url . '/wp-json/alm/v1/deactivate', [
            'headers' => [
                'X-Alm-Secret' => $this->get_secret_key()
            ],
            'body' => [
                'license_key' => sanitize_text_field($license_key),
                'site_url' => $site_url
            ],
            'timeout' => 15
        ]);
        
        if (!is_wp_error($response)) {
            // Hapus lisensi lokal
            delete_option('namatema_license_key');
            delete_option('namatema_license_status');
            delete_option('namatema_license_activated_at');
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Check License Status (Heartbeat)
     */
    public function check_license() {
        $license_key = get_option('namatema_license_key');
        
        if (empty($license_key)) {
            return ['success' => false, 'message' => 'No license key found'];
        }
        
        $response = wp_remote_post($this->server_url . '/wp-json/alm/v1/check', [
            'headers' => [
                'X-Alm-Secret' => $this->get_secret_key()
            ],
            'body' => [
                'license_key' => $license_key,
                'site_url' => get_site_url()
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Get Secret Key
     */
    private function get_secret_key() {
        // GANTI dengan secret key dari server lisensi kamu
        return 'your-secret-key-here';
    }
    
    /**
     * Check if theme is licensed
     */
    public function is_licensed() {
        $status = get_option('namatema_license_status');
        return ($status === 'active');
    }
}

// Schedule daily license check (heartbeat)
add_action('wp', 'namatema_schedule_license_check');
function namatema_schedule_license_check() {
    if (!wp_next_scheduled('namatema_check_license_daily')) {
        wp_schedule_event(time(), 'daily', 'namatema_check_license_daily');
    }
}

add_action('namatema_check_license_daily', 'namatema_do_license_check');
function namatema_do_license_check() {
    $client = new Theme_License_Client();
    $result = $client->check_license();
    
    if (!$result['success']) {
        // Update status jadi inactive
        update_option('namatema_license_status', 'inactive');
    }
}