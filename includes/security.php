<?php
// File: includes/security.php

if (!defined('ABSPATH')) {
    exit;
}

class ALM_Security {
    private static $instance = null;
    private $auth_log_table;
    
    private function __construct() {
        global $wpdb;
        $this->auth_log_table = $wpdb->prefix . 'alm_auth_logs';
        $this->init_auth_table();
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_auth_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->auth_log_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255),
            attempt_time datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL,
            details text,
            PRIMARY KEY  (id),
            KEY ip_address (ip_address),
            KEY event_type (event_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function check_api_auth(WP_REST_Request $request) {
        $ip = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Check IP-based rate limiting first
        if ($this->is_ip_blocked($ip)) {
            $this->log_auth_event('auth_blocked', $ip, $user_agent, 'blocked', 'IP temporarily blocked due to too many attempts');
            return new WP_Error(
                'rest_forbidden',
                'Access temporarily blocked. Please try again later.',
                ['status' => 429]
            );
        }

        // Get API key from header
        $sent_secret = $request->get_header('X-Alm-Secret');
        
        if (empty($sent_secret)) {
            $this->log_failed_attempt($ip, $user_agent, 'Missing API key');
            return new WP_Error(
                'rest_forbidden',
                'Missing API key',
                ['status' => 401]
            );
        }

        // ✅ Verify key from database (not options)
        $key_data = $this->verify_api_key($sent_secret);
        
        if (!$key_data) {
            $this->log_failed_attempt($ip, $user_agent, 'Invalid API key');
            return new WP_Error(
                'rest_forbidden',
                'Invalid API key',
                ['status' => 401]
            );
        }
        
        // ✅ Check if key is active
        if ($key_data->status !== 'active') {
            $this->log_auth_event(
                'auth_failed', 
                $ip, 
                $user_agent, 
                'failed', 
                'API key disabled (ID: ' . $key_data->id . ', Label: ' . $key_data->label . ')'
            );
            return new WP_Error(
                'rest_forbidden', 
                'API key has been disabled', 
                ['status' => 401]
            );
        }

        // ✅ Update last used & increment counter
        $this->update_key_usage($key_data->id);

        // Success - reset failed attempts
        $this->reset_failed_attempts($ip);
        $this->log_auth_event(
            'auth_success', 
            $ip, 
            $user_agent, 
            'success', 
            'Authenticated with key ID: ' . $key_data->id . ' (' . $key_data->label . ')'
        );

        return true;
    }
    
    /**
     * Verify API Key from Database
     */
    private function verify_api_key($api_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'alm_api_keys';
        
        // Get all keys and use hash_equals for timing-attack safe comparison
        $all_keys = $wpdb->get_results("SELECT * FROM {$table}");
        
        foreach ($all_keys as $key_data) {
            if (hash_equals($key_data->api_key, $api_key)) {
                return $key_data;
            }
        }
        
        return false;
    }
    
    /**
     * Update Key Usage Stats
     */
    private function update_key_usage($key_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'alm_api_keys';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} 
            SET last_used_at = %s, 
                total_requests = total_requests + 1 
            WHERE id = %d",
            current_time('mysql'),
            $key_id
        ));
    }

    private function is_ip_blocked($ip) {
        $failed_attempts = $this->get_recent_failed_attempts($ip);
        return $failed_attempts >= 5;
    }

    private function get_recent_failed_attempts($ip) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->auth_log_table}
            WHERE ip_address = %s
            AND event_type = 'auth_failed'
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip
        ));

        return (int)$count;
    }

    private function log_failed_attempt($ip, $user_agent, $reason = '') {
        $this->log_auth_event('auth_failed', $ip, $user_agent, 'failed', 'Authentication failed: ' . $reason);
    }

    private function reset_failed_attempts($ip) {
        global $wpdb;
        
        $wpdb->delete(
            $this->auth_log_table,
            ['ip_address' => $ip, 'event_type' => 'auth_failed'],
            ['%s', '%s']
        );
    }

    public function log_auth_event($event_type, $ip, $user_agent, $status, $details = '') {
        global $wpdb;
        
        $wpdb->insert(
            $this->auth_log_table,
            [
                'event_type' => $event_type,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'status' => $status,
                'details' => $details,
                'attempt_time' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    private function get_client_ip() {
        if (function_exists('alm_sanitize_ip')) {
            return alm_sanitize_ip();
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
    
    public function get_security_status() {
        global $wpdb;
        
        $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $current_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_attempts,
                COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_attempts,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_attempts
            FROM {$this->auth_log_table}
            WHERE attempt_time > %s",
            $last_hour
        ));

        $blocked_ips = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
            FROM {$this->auth_log_table}
            WHERE status = 'blocked'
            AND attempt_time > %s",
            $last_hour
        ));

        return array(
            'failed_attempts' => $current_stats->failed_attempts ?? 0,
            'blocked_ips' => $blocked_ips ?? 0,
            'successful_auth' => $current_stats->successful_attempts ?? 0,
            'last_hour_total' => ($current_stats->failed_attempts ?? 0) + 
                                ($current_stats->blocked_attempts ?? 0) + 
                                ($current_stats->successful_attempts ?? 0)
        );
    }
}