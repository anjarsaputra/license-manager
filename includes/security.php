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

        // Validate API key
        $sent_secret = $request->get_header('X-Alm-Secret');
        $valid_keys = get_option('alm_secret_keys', []);

        if (empty($valid_keys) || !is_array($valid_keys) || empty($sent_secret)) {
            $this->log_failed_attempt($ip, $user_agent);
            return new WP_Error(
                'rest_forbidden',
                'Invalid authentication credentials',
                ['status' => 401]
            );
        }

        // Secure key comparison
        $authenticated = false;
        foreach ($valid_keys as $valid_key) {
            if (hash_equals($valid_key, $sent_secret)) {
                $authenticated = true;
                break;
            }
        }

        if (!$authenticated) {
            $this->log_failed_attempt($ip, $user_agent);
            return new WP_Error(
                'rest_forbidden',
                'Authentication failed',
                ['status' => 401]
            );
        }

        // Success - reset failed attempts
        $this->reset_failed_attempts($ip);
        $this->log_auth_event('auth_success', $ip, $user_agent, 'success', 'Authentication successful');

        return true;
    }

    private function is_ip_blocked($ip) {
        $failed_attempts = $this->get_recent_failed_attempts($ip);
        return $failed_attempts >= 5; // Block after 5 failed attempts
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

    private function log_failed_attempt($ip, $user_agent) {
        $this->log_auth_event('auth_failed', $ip, $user_agent, 'failed', 'Authentication failed');
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
        $ip = '';
        
        // Check for CloudFlare IP
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // Check for proxy forwarded IP
        elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
        }
        // Standard remote address
        elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
    
    
    public function get_security_status() {
    global $wpdb;
    
    // Get current hour stats
    $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $current_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_attempts,
            COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_attempts,
            COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_attempts
        FROM {$this->auth_log_table}
        WHERE attempt_time > %s",
        $last_hour
    ));

    // Get unique blocked IPs
    $blocked_ips = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT ip_address) 
        FROM {$this->auth_log_table}
        WHERE status = 'blocked'
        AND attempt_time > %s",
        $last_hour
    ));

    return array(
        'failed_attempts' => $current_stats[0]->failed_attempts,
        'blocked_ips' => $blocked_ips,
        'successful_auth' => $current_stats[0]->successful_attempts,
        'last_hour_total' => $current_stats[0]->failed_attempts + 
                            $current_stats[0]->blocked_attempts + 
                            $current_stats[0]->successful_attempts
    );
}
}