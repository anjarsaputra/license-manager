<?php
// File: includes/license-generator.php

if (!defined('ABSPATH')) {
    exit;
}

class ALM_License_Generator {
    private static $instance = null;
    private $length;
    private $segments;
    private $separator;

    private function __construct() {
        $this->length = 16;     // Total karakter
        $this->segments = 4;    // Jumlah segmen
        $this->separator = '-';  // Pemisah
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate secure license key
     * Format: XXXX-XXXX-XXXX-XXXX
     */
    public function generate_license_key() {
        try {
            // Generate 16 bytes random
            $bytes = random_bytes(8); // 8 bytes = 16 hex chars
            
            // Convert ke hex dan uppercase
            $hex = strtoupper(bin2hex($bytes));
            
            // Split ke 4 segmen
            $segments = str_split($hex, 4);
            
            // Gabung dengan separator
            $license_key = implode($this->separator, $segments);
            
            // Cek duplikasi
            if ($this->is_license_key_exists($license_key)) {
                return $this->generate_license_key(); // Generate ulang jika duplikat
            }

            // Generate dan simpan checksum
            $checksum = $this->generate_checksum($license_key);
            $this->store_checksum($license_key, $checksum);

            return $license_key;

        } catch (Exception $e) {
            error_log('License Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cek apakah license key sudah ada
     */
    private function is_license_key_exists($license_key) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}alm_licenses WHERE license_key = %s",
            $license_key
        ));
        return $exists > 0;
    }

    /**
     * Generate checksum untuk license key
     */
    private function generate_checksum($license_key) {
        $clean_key = str_replace($this->separator, '', $license_key);
        $salt = wp_salt('auth');
        return hash_hmac('sha256', $clean_key, $salt);
    }

    /**
     * Simpan checksum di database
     */
    private function store_checksum($license_key, $checksum) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'alm_license_checksums',
            [
                'license_key' => $license_key,
                'checksum' => $checksum,
                'created_at' => current_time('mysql', true)
            ]
        );
    }

    /**
     * Validasi license key lengkap
     */
    public function validate_license_key($license_key) {
        // Validasi format
        if (!$this->validate_format($license_key)) {
            return new WP_Error('invalid_format', 'Format lisensi tidak valid');
        }

        // Validasi checksum
        if (!$this->verify_checksum($license_key)) {
            return new WP_Error('invalid_checksum', 'Lisensi tidak valid atau telah dimodifikasi');
        }

        return true;
    }

    /**
     * Validasi format license key
     */
    private function validate_format($license_key) {
        return (bool) preg_match('/^[A-F0-9]{4}[\-][A-F0-9]{4}[\-][A-F0-9]{4}[\-][A-F0-9]{4}$/', $license_key);
    }

    /**
     * Verifikasi checksum
     */
    private function verify_checksum($license_key) {
        global $wpdb;
        $stored_checksum = $wpdb->get_var($wpdb->prepare(
            "SELECT checksum FROM {$wpdb->prefix}alm_license_checksums WHERE license_key = %s",
            $license_key
        ));

        if (!$stored_checksum) {
            return false;
        }

        $current_checksum = $this->generate_checksum($license_key);
        return hash_equals($stored_checksum, $current_checksum);
    }
}