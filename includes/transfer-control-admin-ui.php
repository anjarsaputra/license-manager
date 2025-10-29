<?php
/**
 * Transfer Control System - Admin UI Enhancement
 * Version: 1.0
 * Adds transfer management to admin license list
 * 
 * @package License Manager - Transfer Control
 */

if (!defined('ABSPATH')) exit;

/**
 * Add Transfer Columns to License List Table
 */
function alm_transfer_add_admin_columns() {
    ?>
    <style>
    .alm-transfer-info {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }
    .alm-transfer-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 11px;
    }
    .alm-transfer-badge.success {
        background: #d1fae5;
        color: #065f46;
    }
    .alm-transfer-badge.warning {
        background: #fef3c7;
        color: #92400e;
    }
    .alm-transfer-badge.danger {
        background: #fee2e2;
        color: #991b1b;
    }
    .alm-transfer-badge.locked {
        background: #dbeafe;
        color: #1e40af;
    }
    .alm-transfer-actions {
        display: flex;
        gap: 4px;
        margin-top: 4px;
    }
    .alm-btn-small {
        padding: 2px 8px;
        font-size: 11px;
        cursor: pointer;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        
        // Reset Transfer Count
        window.almResetTransfer = function(licenseKey) {
            if (!confirm('Reset transfer count untuk lisensi ini?\n\nTransfer count akan di-reset ke 0 dan cooldown dihapus.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'alm_admin_reset_transfer',
                    license_key: licenseKey,
                    nonce: '<?php echo wp_create_nonce("alm_transfer_admin"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('❌ Network error');
                }
            });
        };
        
        // Lock Domain
        window.almLockDomain = function(licenseKey) {
            if (!confirm('Lock domain untuk lisensi ini?\n\nUser tidak akan bisa deactivate/transfer domain.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'alm_admin_toggle_lock',
                    license_key: licenseKey,
                    lock: 1,
                    nonce: '<?php echo wp_create_nonce("alm_transfer_admin"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('🔒 ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('❌ Network error');
                }
            });
        };
        
        // Unlock Domain
        window.almUnlockDomain = function(licenseKey) {
            if (!confirm('Unlock domain untuk lisensi ini?\n\nUser akan bisa deactivate/transfer domain kembali.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'alm_admin_toggle_lock',
                    license_key: licenseKey,
                    lock: 0,
                    nonce: '<?php echo wp_create_nonce("alm_transfer_admin"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('🔓 ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('❌ Network error');
                }
            });
        };
        
        // Edit Transfer Limit
        window.almEditTransferLimit = function(licenseKey, currentLimit) {
            const newLimit = prompt('Transfer limit baru untuk lisensi ini:', currentLimit);
            
            if (newLimit === null) return;
            
            const limit = parseInt(newLimit);
            if (isNaN(limit) || limit < 0) {
                alert('❌ Transfer limit harus angka positif');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'alm_admin_edit_transfer_limit',
                    license_key: licenseKey,
                    transfer_limit: limit,
                    nonce: '<?php echo wp_create_nonce("alm_transfer_admin"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('❌ Network error');
                }
            });
        };
    });
    </script>
    <?php
}
add_action('admin_head', 'alm_transfer_add_admin_columns');

/**
 * Display Transfer Info in License List
 * Hook this into your existing license list display
 */
function alm_display_transfer_column($license) {
    $transfer_control = ALM_Transfer_Control::get_instance();
    $info = $transfer_control->get_transfer_info($license->license_key);
    
    if (!$info) {
        echo '<span style="color:#999;">N/A</span>';
        return;
    }
    
    $used = $info['transfer_count'];
    $limit = $info['transfer_limit'];
    $remaining = $info['remaining_transfers'];
    
    // Badge color logic
    if ($info['domain_locked']) {
        $badge_class = 'locked';
        $badge_text = '🔒 Locked';
    } elseif ($remaining === 0) {
        $badge_class = 'danger';
        $badge_text = '⛔ Limit';
    } elseif ($remaining === 1) {
        $badge_class = 'warning';
        $badge_text = '⚠️ Last';
    } else {
        $badge_class = 'success';
        $badge_text = '✓ OK';
    }
    
    ?>
    <div class="alm-transfer-info">
        <span class="alm-transfer-badge <?php echo esc_attr($badge_class); ?>">
            <?php echo esc_html($badge_text); ?>
        </span>
        <span style="font-weight:600;">
            <?php echo esc_html($used); ?> / <?php echo esc_html($limit); ?>
        </span>
        <span style="color:#666;">
            (<?php echo esc_html($remaining); ?> left)
        </span>
    </div>
    
    <div class="alm-transfer-actions">
        <?php if ($used > 0): ?>
            <button 
                class="button button-small alm-btn-small" 
                onclick="almResetTransfer('<?php echo esc_js($license->license_key); ?>')">
                🔄 Reset
            </button>
        <?php endif; ?>
        
        <button 
            class="button button-small alm-btn-small" 
            onclick="almEditTransferLimit('<?php echo esc_js($license->license_key); ?>', <?php echo esc_js($limit); ?>)">
            ✏️ Edit Limit
        </button>
        
        <?php if ($info['domain_locked']): ?>
            <button 
                class="button button-small alm-btn-small" 
                onclick="almUnlockDomain('<?php echo esc_js($license->license_key); ?>')">
                🔓 Unlock
            </button>
        <?php else: ?>
            <button 
                class="button button-small alm-btn-small" 
                onclick="almLockDomain('<?php echo esc_js($license->license_key); ?>')">
                🔒 Lock
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($info['last_transfer_date'])): ?>
        <div style="margin-top:4px; font-size:11px; color:#666;">
            Last: <?php echo esc_html(date('d M Y', strtotime($info['last_transfer_date']))); ?>
            <?php if ($info['next_transfer_available']): ?>
                | Next: <?php echo esc_html($info['next_transfer_available']); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * AJAX Handlers for Admin Actions
 */

// Reset Transfer Count
add_action('wp_ajax_alm_admin_reset_transfer', function() {
    check_ajax_referer('alm_transfer_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $license_key = sanitize_text_field($_POST['license_key']);
    
    $transfer_control = ALM_Transfer_Control::get_instance();
    $result = $transfer_control->admin_reset_transfer_count($license_key);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success(['message' => 'Transfer count berhasil di-reset']);
});

// Toggle Domain Lock
add_action('wp_ajax_alm_admin_toggle_lock', function() {
    check_ajax_referer('alm_transfer_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $license_key = sanitize_text_field($_POST['license_key']);
    $lock = absint($_POST['lock']);
    
    $transfer_control = ALM_Transfer_Control::get_instance();
    $result = $transfer_control->admin_toggle_domain_lock($license_key, $lock);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    $message = $lock ? 'Domain berhasil di-lock' : 'Domain berhasil di-unlock';
    wp_send_json_success(['message' => $message]);
});

// Edit Transfer Limit
add_action('wp_ajax_alm_admin_edit_transfer_limit', function() {
    check_ajax_referer('alm_transfer_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $license_key = sanitize_text_field($_POST['license_key']);
    $transfer_limit = absint($_POST['transfer_limit']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'alm_licenses';
    
    $updated = $wpdb->update(
        $table,
        ['transfer_limit' => $transfer_limit],
        ['license_key' => $license_key],
        ['%d'],
        ['%s']
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to update transfer limit']);
    }
    
    alm_insert_log($license_key, 'transfer_limit_changed', sprintf('Transfer limit changed to %d by admin', $transfer_limit), '');
    
    wp_send_json_success(['message' => 'Transfer limit berhasil diubah']);
});

/**
 * Add Transfer Info to License Details Modal/Page
 */
function alm_transfer_license_details_section($license) {
    $transfer_control = ALM_Transfer_Control::get_instance();
    $info = $transfer_control->get_transfer_info($license->license_key);
    
    if (!$info) return;
    
    ?>
    <div class="alm-license-detail-section" style="margin-top:20px; padding:15px; background:#f9f9f9; border-radius:8px;">
        <h3 style="margin-top:0;">🔄 Transfer Control</h3>
        
        <table class="widefat" style="background:#fff;">
            <tr>
                <td width="40%"><strong>Transfer Used:</strong></td>
                <td><?php echo esc_html($info['transfer_count']); ?> of <?php echo esc_html($info['transfer_limit']); ?></td>
            </tr>
            <tr>
                <td><strong>Remaining Transfers:</strong></td>
                <td>
                    <?php if ($info['remaining_transfers'] > 0): ?>
                        <span style="color:#059669; font-weight:600;">
                            ✅ <?php echo esc_html($info['remaining_transfers']); ?> transfers available
                        </span>
                    <?php else: ?>
                        <span style="color:#dc2626; font-weight:600;">
                            ⛔ Transfer limit reached
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Last Transfer:</strong></td>
                <td>
                    <?php if (!empty($info['last_transfer_date'])): ?>
                        <?php echo esc_html(date('d F Y, H:i', strtotime($info['last_transfer_date']))); ?>
                    <?php else: ?>
                        <em>Never transferred</em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Next Transfer Available:</strong></td>
                <td>
                    <?php if ($info['next_transfer_available']): ?>
                        <?php echo esc_html($info['next_transfer_available']); ?>
                    <?php else: ?>
                        <em>Anytime</em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Domain Lock Status:</strong></td>
                <td>
                    <?php if ($info['domain_locked']): ?>
                        <span style="color:#1e40af; font-weight:600;">🔒 Locked</span>
                    <?php else: ?>
                        <span style="color:#059669; font-weight:600;">🔓 Unlocked</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
