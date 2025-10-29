<?php
/**
 * Email Notification Handler for License Manager
 * Handles automatic email notifications for license expiry
 * 
 * @package Theme License Manager
 * @version 1.2 - PRODUCTION (Real License Data)
 */

if (!defined('ABSPATH')) exit;

class ALM_Email_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Schedule cron job untuk check expiring licenses
        add_action('init', array($this, 'schedule_email_check'));
        add_action('alm_check_license_expiry', array($this, 'check_and_send_emails'));
        
        // AJAX handler untuk test email
        add_action('wp_ajax_alm_send_test_email', array($this, 'send_test_email'));
        
        // AJAX handler untuk test with REAL license
        add_action('wp_ajax_alm_send_real_test_email', array($this, 'send_real_test_email'));
    }
    
    /**
     * Schedule daily check untuk expiring licenses
     */
    public function schedule_email_check() {
        if (!wp_next_scheduled('alm_check_license_expiry')) {
            wp_schedule_event(time(), 'daily', 'alm_check_license_expiry');
        }
    }
    
    /**
     * Main function: Check licenses dan send emails
     */
    public function check_and_send_emails() {
        global $wpdb;
        
        // Check if email notifications enabled
        if (!get_option('alm_email_enable', 1)) {
            if (function_exists('alm_insert_log')) {
                alm_insert_log('SYSTEM', 'email_cron_skip', 'Email notifications disabled in settings', '');
            }
            return;
        }
        
        $license_table = $wpdb->prefix . 'alm_licenses';
        $reminder_days = get_option('alm_email_reminder_days', array(7, 3, 1));
        
        if (!is_array($reminder_days)) {
            $reminder_days = array(7, 3, 1);
        }
        
        $today = current_time('Y-m-d');
        $sent_count = 0;
        $skip_count = 0;
        
        // Check each reminder day
        foreach ($reminder_days as $days) {
            $check_date = date('Y-m-d', strtotime("+{$days} days"));
            
            // ✅ Get REAL licenses expiring in X days
            $licenses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$license_table} 
                 WHERE status = 'active' 
                 AND DATE(expires) = %s
                 AND customer_email IS NOT NULL
                 AND customer_email != ''
                 AND customer_email NOT LIKE '%example.com%'
                 AND customer_email NOT LIKE '%test%'
                 AND (last_reminder_sent IS NULL OR last_reminder_sent != %s)",
                $check_date,
                $today
            ));
            
            foreach ($licenses as $license) {
                if ($this->send_expiring_email($license, $days)) {
                    // Mark as sent
                    $wpdb->update(
                        $license_table,
                        array('last_reminder_sent' => $today),
                        array('id' => $license->id),
                        array('%s'),
                        array('%d')
                    );
                    $sent_count++;
                } else {
                    $skip_count++;
                }
            }
        }
        
        // Check for newly expired licenses (post-expiry notification)
        if (get_option('alm_email_after_expired', 1)) {
            $expired_licenses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$license_table} 
                 WHERE status = 'active' 
                 AND DATE(expires) < %s
                 AND customer_email IS NOT NULL
                 AND customer_email != ''
                 AND customer_email NOT LIKE '%example.com%'
                 AND customer_email NOT LIKE '%test%'
                 AND (expired_email_sent IS NULL OR expired_email_sent = 0)",
                $today
            ));
            
            foreach ($expired_licenses as $license) {
                if ($this->send_expired_email($license)) {
                    // Mark as sent and update status
                    $wpdb->update(
                        $license_table,
                        array(
                            'expired_email_sent' => 1,
                            'status' => 'expired'
                        ),
                        array('id' => $license->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                    $sent_count++;
                } else {
                    $skip_count++;
                }
            }
        }
        
        // Log cron execution
        if (function_exists('alm_insert_log')) {
            alm_insert_log('SYSTEM', 'email_cron_executed', "Email cron completed. Sent: {$sent_count}, Skipped: {$skip_count}", '');
        }
    }
    
    /**
     * Send expiring soon email
     */
    private function send_expiring_email($license, $days_left) {
        $to = $license->customer_email;
        
        // Validate email
        if (empty($to) || !is_email($to)) {
            if (function_exists('alm_insert_log')) {
                alm_insert_log($license->license_key, 'email_skip', 'Invalid email address: ' . $to, '');
            }
            return false;
        }
        
        $subject = $this->get_email_subject($days_left);
        $body = $this->get_email_body($license, $days_left);
        
        return $this->send_email($to, $subject, $body, $license->license_key);
    }
    
    /**
     * Send expired email
     */
    private function send_expired_email($license) {
        $to = $license->customer_email;
        
        // Validate email
        if (empty($to) || !is_email($to)) {
            if (function_exists('alm_insert_log')) {
                alm_insert_log($license->license_key, 'email_skip', 'Invalid email address: ' . $to, '');
            }
            return false;
        }
        
        $subject = 'License Expired - Action Required';
        $body = $this->get_expired_email_body($license);
        
        return $this->send_email($to, $subject, $body, $license->license_key);
    }
    
    /**
     * Get email subject with variables replaced
     */
    private function get_email_subject($days_left) {
        $subject = get_option('alm_email_subject', 'Lisensi Anda Akan Berakhir dalam {daysleft} Hari');
        $subject = str_replace('{daysleft}', $days_left, $subject);
        return sanitize_text_field($subject);
    }
    
    /**
     * Get email body with variables replaced
     */
    private function get_email_body($license, $days_left) {
        $body = get_option('alm_email_body', $this->get_default_template());
        
        // ✅ Get REAL customer name (sanitized)
        $email_parts = explode('@', $license->customer_email);
        $username = isset($email_parts[0]) ? $email_parts[0] : 'Customer';
        $username = ucfirst(sanitize_text_field($username));
        
        // Renewal link
        $renewal_link = esc_url(home_url('/my-account/'));
        
        // ✅ Replace with REAL license data
        $replacements = array(
            '{username}' => esc_html($username),
            '{licensekey}' => esc_html($license->license_key), // REAL license key
            '{expirydate}' => date_i18n('d F Y', strtotime($license->expires)), // REAL expiry date
            '{daysleft}' => absint($days_left),
            '{renewallink}' => $renewal_link
        );
        
        foreach ($replacements as $var => $value) {
            $body = str_replace($var, $value, $body);
        }
        
        return $this->wrap_html_template($body, 'warning');
    }
    
    /**
     * PROFESSIONAL expired email body
     */
    private function get_expired_email_body($license) {
        $email_parts = explode('@', $license->customer_email);
        $username = isset($email_parts[0]) ? $email_parts[0] : 'Customer';
        $username = ucfirst(sanitize_text_field($username));
        $renewal_link = esc_url(home_url('/my-account/'));
        $expire_date = date_i18n('F d, Y', strtotime($license->expires));
        
        $body = "
        <div style='text-align:center;margin-bottom:24px;'>
            <div style='display:inline-block;width:80px;height:80px;background:linear-gradient(135deg,#dc2626,#ef4444);border-radius:50%;line-height:80px;'>
                <span style='font-size:40px;color:#fff;'>⏰</span>
            </div>
        </div>
        
        <h1 style='color:#1f2937;font-size:28px;font-weight:700;margin:0 0 16px 0;text-align:center;'>
            Your License Has Expired
        </h1>
        
        <p style='color:#6b7280;font-size:16px;line-height:1.6;text-align:center;margin:0 0 32px 0;'>
            Hi <strong style='color:#1f2937;'>" . esc_html($username) . "</strong>, we noticed your license needs attention.
        </p>
        
        <div style='background:#fef2f2;border-left:4px solid #dc2626;padding:20px;margin:0 0 32px 0;border-radius:8px;'>
            <p style='margin:0;color:#991b1b;font-size:14px;line-height:1.5;'>
                <strong style='display:block;margin-bottom:8px;font-size:16px;'>⚠️ License Details</strong>
                Your license key <code style='background:#fff;padding:4px 8px;border-radius:4px;color:#dc2626;font-family:monospace;'>" . esc_html($license->license_key) . "</code> expired on <strong>" . esc_html($expire_date) . "</strong>
            </p>
        </div>
        
        <div style='background:#f9fafb;border-radius:12px;padding:24px;margin:0 0 32px 0;'>
            <h3 style='color:#1f2937;font-size:18px;font-weight:600;margin:0 0 16px 0;'>What This Means:</h3>
            <ul style='margin:0;padding:0;list-style:none;'>
                <li style='padding:8px 0;color:#4b5563;font-size:14px;border-bottom:1px solid #e5e7eb;'>
                    <span style='color:#10b981;font-weight:700;'>✓</span> Theme continues to work normally
                </li>
                <li style='padding:8px 0;color:#4b5563;font-size:14px;border-bottom:1px solid #e5e7eb;'>
                    <span style='color:#ef4444;font-weight:700;'>✗</span> No automatic updates
                </li>
                <li style='padding:8px 0;color:#4b5563;font-size:14px;'>
                    <span style='color:#ef4444;font-weight:700;'>✗</span> No premium support
                </li>
            </ul>
        </div>
        
        <div style='text-align:center;margin:0 0 32px 0;'>
            <a href='" . $renewal_link . "' style='display:inline-block;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;padding:16px 40px;text-decoration:none;border-radius:50px;font-weight:600;font-size:16px;box-shadow:0 4px 14px rgba(220,38,38,0.4);'>
                🔄 Renew License Now
            </a>
        </div>
        
        <div style='background:#f0f9ff;border-radius:8px;padding:20px;text-align:center;'>
            <p style='margin:0;color:#075985;font-size:14px;line-height:1.5;'>
                <strong>💡 Pro Tip:</strong> Renew within 30 days to maintain your activation history and avoid re-installation.
            </p>
        </div>
        ";
        
        return $this->wrap_html_template($body, 'danger');
    }
    
    /**
     * PROFESSIONAL default template (expiring soon)
     */
    private function get_default_template() {
        return "
        <div style='text-align:center;margin-bottom:32px;'>
            <div style='display:inline-block;width:80px;height:80px;background:linear-gradient(135deg,#f59e0b,#fbbf24);border-radius:50%;line-height:80px;'>
                <span style='font-size:40px;'>⏳</span>
            </div>
        </div>
        
        <h1 style='color:#1f2937;font-size:28px;font-weight:700;margin:0 0 16px 0;text-align:center;'>
            Your License is Expiring Soon!
        </h1>
        
        <p style='color:#6b7280;font-size:16px;line-height:1.6;text-align:center;margin:0 0 32px 0;'>
            Hi <strong style='color:#1f2937;'>{username}</strong>, your license will expire in <strong style='color:#dc2626;font-size:20px;'>{daysleft} days</strong>.
        </p>
        
        <div style='background:#fffbeb;border-left:4px solid #f59e0b;padding:20px;margin:0 0 32px 0;border-radius:8px;'>
            <p style='margin:0;color:#92400e;font-size:14px;line-height:1.5;'>
                <strong style='display:block;margin-bottom:8px;font-size:16px;'>📋 License Information</strong>
                <span style='display:block;margin:4px 0;'>License Key: <code style='background:#fff;padding:4px 8px;border-radius:4px;color:#f59e0b;font-family:monospace;'>{licensekey}</code></span>
                <span style='display:block;margin:4px 0;'>Expires On: <strong>{expirydate}</strong></span>
            </p>
        </div>
        
        <div style='background:#f0fdf4;border-radius:12px;padding:24px;margin:0 0 32px 0;'>
            <h3 style='color:#065f46;font-size:18px;font-weight:600;margin:0 0 16px 0;text-align:center;'>🎯 Why Renew?</h3>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td width='33%' align='center' style='padding:10px;'>
                        <div style='font-size:32px;margin-bottom:8px;'>🔄</div>
                        <div style='color:#065f46;font-size:14px;font-weight:600;'>Automatic Updates</div>
                    </td>
                    <td width='33%' align='center' style='padding:10px;'>
                        <div style='font-size:32px;margin-bottom:8px;'>🛡️</div>
                        <div style='color:#065f46;font-size:14px;font-weight:600;'>Security Patches</div>
                    </td>
                    <td width='33%' align='center' style='padding:10px;'>
                        <div style='font-size:32px;margin-bottom:8px;'>💬</div>
                        <div style='color:#065f46;font-size:14px;font-weight:600;'>Priority Support</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style='text-align:center;margin:0 0 32px 0;'>
            <a href='{renewallink}' style='display:inline-block;background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;padding:16px 40px;text-decoration:none;border-radius:50px;font-weight:600;font-size:16px;box-shadow:0 4px 14px rgba(37,99,235,0.4);'>
                ⚡ Renew Now & Save
            </a>
        </div>
        
        <p style='color:#9ca3af;font-size:13px;text-align:center;margin:0;'>
            Renew early to avoid service interruption and get exclusive renewal discounts!
        </p>
        ";
    }
    
    /**
     * PROFESSIONAL HTML wrapper with modern design
     */
    private function wrap_html_template($content, $type = 'default') {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $current_year = date('Y');
        
        // Color scheme based on email type
        $colors = array(
            'default' => '#2563eb',
            'warning' => '#f59e0b',
            'danger' => '#dc2626',
            'success' => '#10b981'
        );
        
        $accent_color = isset($colors[$type]) ? $colors[$type] : $colors['default'];
        
        return '
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <title>License Notification</title>
        </head>
        <body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif;">
            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;max-width:600px;">
                            <tr>
                                <td style="background:' . esc_attr($accent_color) . ';height:6px;"></td>
                            </tr>
                            <tr>
                                <td style="padding:32px 40px 0 40px;text-align:center;">
                                    <h2 style="margin:0;color:#1f2937;font-size:24px;font-weight:700;">
                                        ' . esc_html($site_name) . '
                                    </h2>
                                    <p style="margin:8px 0 0 0;color:#9ca3af;font-size:12px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">
                                        Informasi Lisensi
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:40px;">
                                    ' . $content . '
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 40px;">
                                    <div style="height:1px;background:#e5e7eb;"></div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px 40px;background-color:#f9fafb;">
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <a href="' . esc_url($site_url) . '" style="display:inline-block;margin:0 8px;color:#6b7280;text-decoration:none;font-size:12px;font-weight:500;">Website</a>
                                        <span style="color:#d1d5db;">•</span>
                                        <a href="' . esc_url($site_url) . '/my-account" style="display:inline-block;margin:0 8px;color:#6b7280;text-decoration:none;font-size:12px;font-weight:500;">My Account</a>
                                    </div>
                                    <p style="margin:0;text-align:center;color:#9ca3af;font-size:12px;line-height:1.6;">
                                        &copy; ' . esc_html($current_year) . ' <strong style="color:#6b7280;">' . esc_html($site_name) . '</strong><br>
                                        All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 0 0;text-align:center;color:#9ca3af;font-size:12px;">
                            Need help? Contact Support
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
    
    /**
     * Send email via wp_mail
     */
    private function send_email($to, $subject, $body, $license_key = '') {
        if (!is_email($to)) {
            return false;
        }
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Send email
        $sent = wp_mail($to, $subject, $body, $headers);
        
        // Log result
        if (function_exists('alm_insert_log')) {
            $status = $sent ? 'email_sent' : 'email_failed';
            $log_key = !empty($license_key) ? $license_key : 'SYSTEM';
            alm_insert_log($log_key, $status, "Email to: {$to} - Subject: {$subject}", '');
        }
        
        return $sent;
    }
    
    /**
     * AJAX: Send test email (dummy for preview)
     */
    public function send_test_email() {
        check_ajax_referer('alm_test_email_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $admin_email = get_option('admin_email');
        
        // Create dummy license object FOR PREVIEW ONLY
        $dummy_license = (object) array(
            'license_key' => 'PREVIEW-LICENSE-KEY',
            'customer_email' => $admin_email,
            'expires' => date('Y-m-d', strtotime('+7 days'))
        );
        
        $subject = '[TEST] License Expiring Soon';
        $body = $this->get_email_body($dummy_license, 7);
        
        $sent = $this->send_email($admin_email, $subject, $body, 'TEST');
        
        if ($sent) {
            wp_send_json_success('✅ Test email sent to ' . $admin_email . '. This is a PREVIEW with dummy data. Real emails will use actual license data.');
        } else {
            wp_send_json_error('❌ Failed to send email. Check SMTP settings.');
        }
    }
    
    /**
     * ✅ NEW: AJAX Test with REAL license from database
     */
    public function send_real_test_email() {
        check_ajax_referer('alm_real_test_email_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        $license_table = $wpdb->prefix . 'alm_licenses';
        
        // ✅ Get REAL license expiring soon
        $real_license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$license_table} 
             WHERE status = 'active' 
             AND expires > %s
             AND customer_email IS NOT NULL
             AND customer_email != ''
             ORDER BY expires ASC
             LIMIT 1",
            current_time('Y-m-d')
        ));
        
        if (!$real_license) {
            wp_send_json_error('❌ No active license found in database for testing.');
            return;
        }
        
        // Calculate days left
        $days_left = ceil((strtotime($real_license->expires) - current_time('timestamp')) / DAY_IN_SECONDS);
        
        // Use admin email but with REAL license data
        $admin_email = get_option('admin_email');
        $subject = '[TEST with REAL DATA] License Expiring in ' . $days_left . ' days';
        
        // Create test object with REAL data but admin email
        $test_license = clone $real_license;
        $test_license->customer_email = $admin_email; // Send to admin for testing
        
        $body = $this->get_email_body($test_license, $days_left);
        
        $sent = $this->send_email($admin_email, $subject, $body, 'REAL_TEST');
        
        if ($sent) {
            wp_send_json_success('✅ Test email sent with REAL license data! License Key: ' . $real_license->license_key . ', Expires: ' . $real_license->expires);
        } else {
            wp_send_json_error('❌ Failed to send test email.');
        }
    }
}

// Initialize
ALM_Email_Handler::get_instance();
