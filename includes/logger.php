<?php
// File: includes/logger.php

if (!defined('ABSPATH')) {
    exit;
}

class ALM_Logger {
    private static $instance = null;
    private $log_table;
    private $default_timezone;

    private function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'alm_logs';
        $this->default_timezone = 'Asia/Jakarta';
        $this->ensure_timezone();
    }

   public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensure_timezone() {
        if (date_default_timezone_get() != $this->default_timezone) {
            date_default_timezone_set($this->default_timezone);
        }
    }

    public function get_formatted_time() {
        // Menggunakan current_time dari WordPress untuk waktu yang lebih akurat
        return current_time('Y-m-d H:i:s', false); // false untuk waktu lokal (WIB)
    }

    public function get_current_user() {
        $user = wp_get_current_user();
        return $user->exists() ? $user->user_login : 'system';
    }

    public function log($license_key, $action, $message, $site_url = '') {
        global $wpdb;

        $data = array(
            'license_key' => $license_key,
            'action' => $action,
            'message' => $this->format_message($message),
            'site_url' => $site_url,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'log_time' => $this->get_formatted_time(),
            'user' => $this->get_current_user()
        );

        $result = $wpdb->insert(
            $this->log_table,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ALM Logger Error: ' . $wpdb->last_error);
        }

        return $result;
    }

    private function format_message($message) {
        $user = $this->get_current_user();
        $time = $this->get_formatted_time();
        return sprintf('[%s] %s - %s', $time, $user, $message);
    }

    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

  public function get_system_info() {
        // Dapatkan waktu server dalam WIB
        date_default_timezone_set('Asia/Jakarta');
        $wib_time = date('Y-m-d H:i:s');
        
        // Format untuk display
        $formatted_wib = $wib_time . ' WIB';
        
        // Konversi ke UTC untuk datetime_utc
        $utc_time = gmdate('Y-m-d H:i:s');
        
        return array(
            'datetime_utc' => $utc_time,
            'datetime_wib' => $formatted_wib,
            'user_login' => $this->get_current_user()
        );
    }

    public function init_log_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_key varchar(255),
            action varchar(50),
            message text,
            site_url varchar(255),
            ip_address varchar(45),
            user_agent varchar(255),
            user varchar(60),
            log_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY license_key (license_key),
            KEY action (action),
            KEY log_time (log_time)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function get_formatted_time_wib() {
    // Set timezone ke Asia/Jakarta
    date_default_timezone_set('Asia/Jakarta');
    $wib_time = date('Y-m-d H:i:s') . ' WIB';
    
    // Format waktu WIB
    $wib_time = date('Y-m-d H:i:s');
    
    // Kembalikan ke timezone default (UTC)
    date_default_timezone_set($this->default_timezone);
    
    return $wib_time;
}



}

