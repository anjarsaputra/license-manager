<?php
/**
 * API Keys Management - Premium Design
 * Modern Dashboard with Gradient & Animations
 * 
 * @package License Manager
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function alm_render_api_keys_management_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'alm_api_keys';
    
    // Handle generate new key
    if (isset($_POST['generate_new_key']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'alm_api_keys_action')) {
        
        $new_key = 'sk_' . bin2hex(random_bytes(24));
        $label = sanitize_text_field($_POST['key_label'] ?? '');
        $label = !empty($label) ? $label : 'API Key';
        
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (api_key, label, status, created_at, created_by, total_requests) 
            VALUES (%s, %s, 'active', %s, %d, 0)",
            $new_key,
            $label,
            current_time('mysql'),
            get_current_user_id()
        );
        
        $result = $wpdb->query($sql);
        
        if ($result === false || $result === 0) {
            echo '<div class="alm-notice alm-notice-error">';
            echo '<div class="alm-notice-icon">❌</div>';
            echo '<div class="alm-notice-content">';
            echo '<strong>Failed to generate API key</strong>';
            echo '<p>' . esc_html($wpdb->last_error) . '</p>';
            echo '</div></div>';
        } else {
            echo '<div class="alm-notice alm-notice-success">';
            echo '<div class="alm-notice-icon">✅</div>';
            echo '<div class="alm-notice-content">';
            echo '<strong>New API key generated successfully!</strong>';
            echo '<div class="alm-new-key-display">';
            echo '<code>' . esc_html($new_key) . '</code>';
            echo '<button class="alm-copy-new-key" data-key="' . esc_attr($new_key) . '">Copy</button>';
            echo '</div></div></div>';
        }
    }
    
    // Handle key actions
    if (isset($_GET['action']) && isset($_GET['key_id']) && isset($_GET['_wpnonce'])) {
        $key_id = intval($_GET['key_id']);
        
        if (wp_verify_nonce($_GET['_wpnonce'], 'alm_key_action_' . $key_id)) {
            $action_messages = array(
                'disable' => '🔒 API key disabled successfully',
                'enable' => '🔓 API key enabled successfully',
                'delete' => '🗑️ API key deleted successfully'
            );
            
            switch ($_GET['action']) {
                case 'disable':
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET status = 'disabled', disabled_at = %s, disabled_by = %d WHERE id = %d",
                        current_time('mysql'), get_current_user_id(), $key_id
                    ));
                    break;
                case 'enable':
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET status = 'active', disabled_at = NULL, disabled_by = NULL WHERE id = %d",
                        $key_id
                    ));
                    break;
                case 'delete':
                    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id = %d", $key_id));
                    break;
            }
            
            if (isset($action_messages[$_GET['action']])) {
                echo '<div class="alm-notice alm-notice-success">';
                echo '<div class="alm-notice-icon">✅</div>';
                echo '<div class="alm-notice-content">' . $action_messages[$_GET['action']] . '</div>';
                echo '</div>';
            }
        }
    }
    
    // Get all keys
    $keys = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    $active_count = count(array_filter($keys, function($k) { return $k->status === 'active'; }));
    $total_requests = array_sum(array_column($keys, 'total_requests'));
    
    ?>
    <div class="alm-premium-wrap">
        
        <!-- Hero Header -->
        <div class="alm-hero-header">
            <div class="alm-hero-content">
                <div class="alm-hero-icon">🔐</div>
                <div>
                    <h1>API Secret Keys</h1>
                    <p>Secure authentication keys for theme integration</p>
                </div>
            </div>
            <div class="alm-hero-stats">
                <div class="alm-stat-card">
                    <div class="alm-stat-number"><?php echo $active_count; ?></div>
                    <div class="alm-stat-label">Active Keys</div>
                </div>
                <div class="alm-stat-card">
                    <div class="alm-stat-number"><?php echo count($keys); ?></div>
                    <div class="alm-stat-label">Total Keys</div>
                </div>
                <div class="alm-stat-card">
                    <div class="alm-stat-number"><?php echo number_format($total_requests); ?></div>
                    <div class="alm-stat-label">Requests</div>
                </div>
            </div>
        </div>
        
        <!-- Generate Key Section -->
        <div class="alm-section">
            <div class="alm-card alm-generate-card">
                <div class="alm-card-icon">✨</div>
                <div class="alm-card-content">
                    <h2>Generate New API Key</h2>
                    <p class="alm-card-description">Create a new authentication key for your theme integration</p>
                    
                    <form method="post" action="" class="alm-generate-form">
                        <?php wp_nonce_field('alm_api_keys_action'); ?>
                        
                        <div class="alm-input-group">
                            <label for="key_label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Key Label
                            </label>
                            <input type="text" 
                                   id="key_label" 
                                   name="key_label" 
                                   placeholder="e.g., Production, Staging, Development"
                                   class="alm-input">
                            <span class="alm-input-hint">Optional: Add a friendly name to identify this key</span>
                        </div>
                        
                        <button type="submit" name="generate_new_key" value="1" class="alm-btn-primary alm-btn-large">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Generate New Key
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Keys List -->
        <div class="alm-section">
            <div class="alm-section-header">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Your API Keys
                </h2>
                <span class="alm-badge"><?php echo count($keys); ?> Total</span>
            </div>
            
            <?php if (empty($keys)) : ?>
                <div class="alm-empty-state">
                    <div class="alm-empty-icon">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <h3>No API Keys Yet</h3>
                    <p>Generate your first API key to start authenticating your theme requests</p>
                    <button onclick="document.getElementById('key_label').focus();" class="alm-btn-primary">
                        Create Your First Key
                    </button>
                </div>
            <?php else : ?>
                
                <div class="alm-keys-grid">
                    <?php foreach ($keys as $key) : ?>
                        <?php
                        $is_active = $key->status === 'active';
                        $last_used = $key->last_used_at 
                            ? '<span class="alm-time-recent">'.human_time_diff(strtotime($key->last_used_at)).' ago</span>' 
                            : '<span class="alm-time-never">Never used</span>';
                        ?>
                        
                        <div class="alm-key-card <?php echo $is_active ? 'active' : 'disabled'; ?>">
                            <!-- Card Header -->
                            <div class="alm-key-card-header">
                                <div class="alm-key-meta">
                                    <div class="alm-key-status">
                                        <span class="alm-status-indicator <?php echo $is_active ? 'active' : 'disabled'; ?>"></span>
                                        <span class="alm-status-label"><?php echo $is_active ? 'Active' : 'Disabled'; ?></span>
                                    </div>
                                    <span class="alm-key-id">ID: #<?php echo $key->id; ?></span>
                                </div>
                                <?php if ($key->label) : ?>
                                    <div class="alm-key-badge"><?php echo esc_html($key->label); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- API Key Display -->
                            <div class="alm-key-value">
                                <div class="alm-key-code-wrapper">
                                    <code class="alm-key-code"><?php echo esc_html($key->api_key); ?></code>
                                </div>
                                <button class="alm-btn-copy" data-key="<?php echo esc_attr($key->api_key); ?>" title="Copy to clipboard">
                                    <svg class="copy-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <svg class="check-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Stats Grid -->
                            <div class="alm-key-stats-grid">
                                <div class="alm-key-stat">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    <div>
                                        <span class="alm-stat-label">Created</span>
                                        <span class="alm-stat-value"><?php echo date('M j, Y', strtotime($key->created_at)); ?></span>
                                    </div>
                                </div>
                                
                                <div class="alm-key-stat">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                    </svg>
                                    <div>
                                        <span class="alm-stat-label">Last Used</span>
                                        <span class="alm-stat-value"><?php echo $last_used; ?></span>
                                    </div>
                                </div>
                                
                                <div class="alm-key-stat">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"/>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    <div>
                                        <span class="alm-stat-label">Requests</span>
                                        <span class="alm-stat-value"><?php echo number_format($key->total_requests); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!$is_active && $key->disabled_at) : ?>
                                <div class="alm-key-warning">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>
                                    Disabled on <?php echo date('M j, Y', strtotime($key->disabled_at)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Actions -->
                            <div class="alm-key-actions">
                                <?php if ($is_active) : ?>
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=alm-settings&tab=api_keys&action=disable&key_id=' . $key->id),
                                        'alm_key_action_' . $key->id
                                    ); ?>" 
                                    class="alm-btn-action alm-btn-warning"
                                    onclick="return confirm('⚠️ Disable this API key?\n\nApplications using this key will stop working immediately.');">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        Disable
                                    </a>
                                <?php else : ?>
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=alm-settings&tab=api_keys&action=enable&key_id=' . $key->id),
                                        'alm_key_action_' . $key->id
                                    ); ?>" 
                                    class="alm-btn-action alm-btn-success">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                                        </svg>
                                        Enable
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin.php?page=alm-settings&tab=api_keys&action=delete&key_id=' . $key->id),
                                    'alm_key_action_' . $key->id
                                ); ?>" 
                                class="alm-btn-action alm-btn-danger"
                                onclick="return confirm('🗑️ Delete this API key permanently?\n\nThis action cannot be undone!');">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Copy button handler
        $('.alm-btn-copy, .alm-copy-new-key').on('click', function(e) {
            e.preventDefault();
            const key = $(this).data('key');
            const $btn = $(this);
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(key).then(function() {
                    // Swap icons
                    $btn.find('.copy-icon').hide();
                    $btn.find('.check-icon').show();
                    $btn.addClass('copied');
                    
                    // For new key copy button
                    if ($btn.hasClass('alm-copy-new-key')) {
                        $btn.text('✅ Copied!');
                    }
                    
                    setTimeout(function() {
                        $btn.find('.copy-icon').show();
                        $btn.find('.check-icon').hide();
                        $btn.removeClass('copied');
                        
                        if ($btn.hasClass('alm-copy-new-key')) {
                            $btn.text('Copy');
                        }
                    }, 2000);
                });
            } else {
                prompt('Copy this API key:', key);
            }
        });
    });
    </script>
    
    <style>
    /* Premium Design System */
    :root {
        --alm-primary: #6366f1;
        --alm-primary-hover: #4f46e5;
        --alm-primary-light: #eef2ff;
        --alm-success: #10b981;
        --alm-success-light: #d1fae5;
        --alm-warning: #f59e0b;
        --alm-warning-light: #fef3c7;
        --alm-danger: #ef4444;
        --alm-danger-light: #fee2e2;
        --alm-gray-50: #f9fafb;
        --alm-gray-100: #f3f4f6;
        --alm-gray-200: #e5e7eb;
        --alm-gray-300: #d1d5db;
        --alm-gray-400: #9ca3af;
        --alm-gray-500: #6b7280;
        --alm-gray-600: #4b5563;
        --alm-gray-700: #374151;
        --alm-gray-800: #1f2937;
        --alm-gray-900: #111827;
        --alm-radius: 12px;
        --alm-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --alm-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --alm-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    .alm-premium-wrap {
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    /* Hero Header */
    .alm-hero-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: var(--alm-radius);
        padding: 40px;
        margin-bottom: 30px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--alm-shadow-lg);
        position: relative;
        overflow: hidden;
    }
    
    .alm-hero-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }
    
    .alm-hero-content {
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
        z-index: 1;
    }
    
    .alm-hero-icon {
        font-size: 48px;
        line-height: 1;
    }
    
    .alm-hero-content h1 {
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
        color: white;
    }
    
    .alm-hero-content p {
        margin: 0;
        opacity: 0.95;
        font-size: 16px;
    }
    
    .alm-hero-stats {
        display: flex;
        gap: 20px;
        position: relative;
        z-index: 1;
    }
    
    .alm-stat-card {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        padding: 20px 30px;
        border-radius: 10px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .alm-stat-number {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .alm-stat-label {
        font-size: 13px;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Notices */
    .alm-notice {
        display: flex;
        align-items: start;
        gap: 15px;
        padding: 16px 20px;
        border-radius: var(--alm-radius);
        margin-bottom: 20px;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .alm-notice-success {
        background: var(--alm-success-light);
        border-left: 4px solid var(--alm-success);
    }
    
    .alm-notice-error {
        background: var(--alm-danger-light);
        border-left: 4px solid var(--alm-danger);
    }
    
    .alm-notice-icon {
        font-size: 24px;
        line-height: 1;
    }
    
    .alm-notice-content {
        flex: 1;
    }
    
    .alm-notice-content strong {
        display: block;
        font-size: 15px;
        color: var(--alm-gray-900);
        margin-bottom: 5px;
    }
    
    .alm-notice-content p {
        margin: 0;
        color: var(--alm-gray-600);
        font-size: 14px;
    }
    
    .alm-new-key-display {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
        padding: 12px;
        background: var(--alm-gray-900);
        border-radius: 8px;
    }
    
    .alm-new-key-display code {
        flex: 1;
        color: #22c55e;
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: 13px;
        word-break: break-all;
    }
    
    .alm-copy-new-key {
        padding: 6px 12px;
        background: var(--alm-success);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .alm-copy-new-key:hover {
        background: #059669;
    }
    
    /* Section */
    .alm-section {
        margin-bottom: 30px;
    }
    
    .alm-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    
    .alm-section-header h2 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 20px;
        font-weight: 600;
        color: var(--alm-gray-900);
        margin: 0;
    }
    
    .alm-badge {
        background: var(--alm-primary-light);
        color: var(--alm-primary);
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    
    /* Generate Card */
    .alm-generate-card {
        background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%);
        border: 2px dashed var(--alm-gray-300);
        border-radius: var(--alm-radius);
        padding: 30px;
        display: flex;
        align-items: start;
        gap: 20px;
        transition: all 0.3s;
    }
    
    .alm-generate-card:hover {
        border-color: var(--alm-primary);
        background: linear-gradient(135deg, #eef2ff 0%, #ffffff 100%);
    }
    
    .alm-card-icon {
        font-size: 40px;
        line-height: 1;
    }
    
    .alm-card-content {
        flex: 1;
    }
    
    .alm-card-content h2 {
        font-size: 20px;
        font-weight: 600;
        color: var(--alm-gray-900);
        margin: 0 0 8px 0;
    }
    
    .alm-card-description {
        color: var(--alm-gray-600);
        margin: 0 0 24px 0;
        font-size: 14px;
    }
    
    .alm-generate-form {
        max-width: 600px;
    }
    
    .alm-input-group {
        margin-bottom: 20px;
    }
    
    .alm-input-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: var(--alm-gray-700);
        margin-bottom: 8px;
    }
    
    .alm-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--alm-gray-200);
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.2s;
        font-family: inherit;
    }
    
    .alm-input:focus {
        outline: none;
        border-color: var(--alm-primary);
        box-shadow: 0 0 0 4px var(--alm-primary-light);
    }
    
    .alm-input-hint {
        display: block;
        font-size: 13px;
        color: var(--alm-gray-500);
        margin-top: 6px;
    }
    
    /* Buttons */
    .alm-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 24px;
        background: var(--alm-primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: var(--alm-shadow);
    }
    
    .alm-btn-primary:hover {
        background: var(--alm-primary-hover);
        transform: translateY(-2px);
        box-shadow: var(--alm-shadow-lg);
    }
    
    .alm-btn-large {
        padding: 14px 28px;
        font-size: 16px;
    }
    
    /* Keys Grid */
    .alm-keys-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
        gap: 20px;
    }
    
    .alm-key-card {
        background: white;
        border: 2px solid var(--alm-gray-200);
        border-radius: var(--alm-radius);
        padding: 24px;
        transition: all 0.3s;
        position: relative;
    }
    
    .alm-key-card:hover {
        border-color: var(--alm-primary);
        box-shadow: var(--alm-shadow-lg);
        transform: translateY(-2px);
    }
    
    .alm-key-card.active {
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        border-color: var(--alm-success);
    }
    
    .alm-key-card.disabled {
        background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
        border-color: var(--alm-warning);
    }
    
    .alm-key-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 16px;
    }
    
    .alm-key-meta {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .alm-key-status {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .alm-status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        position: relative;
    }
    
    .alm-status-indicator.active {
        background: var(--alm-success);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
    }
    
    .alm-status-indicator.disabled {
        background: var(--alm-warning);
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
    }
    
    .alm-status-label {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .alm-key-id {
        font-size: 12px;
        color: var(--alm-gray-500);
        font-weight: 600;
    }
    
    .alm-key-badge {
        background: var(--alm-gray-100);
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--alm-gray-700);
    }
    
    .alm-key-value {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .alm-key-code-wrapper {
        flex: 1;
        background: var(--alm-gray-900);
        padding: 14px 16px;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .alm-key-code {
        color: #22c55e;
        font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
        font-size: 13px;
        word-break: break-all;
        display: block;
    }
    
    .alm-btn-copy {
        padding: 12px;
        background: white;
        border: 2px solid var(--alm-gray-200);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .alm-btn-copy:hover {
        border-color: var(--alm-primary);
        background: var(--alm-primary-light);
    }
    
    .alm-btn-copy.copied {
        border-color: var(--alm-success);
        background: var(--alm-success-light);
    }
    
    .alm-key-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    
    .alm-key-stat {
        display: flex;
        align-items: start;
        gap: 10px;
    }
    
    .alm-key-stat svg {
        color: var(--alm-gray-400);
        flex-shrink: 0;
        margin-top: 2px;
    }
    
    .alm-key-stat div {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .alm-stat-label {
        font-size: 12px;
        color: white;
        font-weight: 500;
    }
    
    .alm-stat-value {
        font-size: 14px;
        color: var(--alm-gray-800);
        font-weight: 600;
    }
    
    .alm-time-recent {
        color: var(--alm-success);
    }
    
    .alm-time-never {
        color: var(--alm-gray-400);
    }
    
    .alm-key-warning {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        background: rgba(245, 158, 11, 0.1);
        border-left: 3px solid var(--alm-warning);
        border-radius: 6px;
        font-size: 13px;
        color: #92400e;
        margin-bottom: 16px;
    }
    
    .alm-key-actions {
        display: flex;
        gap: 10px;
    }
    
    .alm-btn-action {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 16px;
        border: 2px solid;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .alm-btn-success {
        background: var(--alm-success);
        border-color: var(--alm-success);
        color: white;
    }
    
    .alm-btn-success:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .alm-btn-warning {
        background: var(--alm-warning);
        border-color: var(--alm-warning);
        color: white;
    }
    
    .alm-btn-warning:hover {
        background: #d97706;
        transform: translateY(-1px);
    }
    
    .alm-btn-danger {
        background: white;
        border-color: var(--alm-danger);
        color: var(--alm-danger);
    }
    
    .alm-btn-danger:hover {
        background: var(--alm-danger);
        color: white;
        transform: translateY(-1px);
    }
    
    /* Empty State */
    .alm-empty-state {
        text-align: center;
        padding: 80px 40px;
    }
    
    .alm-empty-icon {
        margin-bottom: 24px;
        opacity: 0.3;
    }
    
    .alm-empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        color: var(--alm-gray-900);
        margin: 0 0 8px 0;
    }
    
    .alm-empty-state p {
        color: var(--alm-gray-600);
        margin: 0 0 24px 0;
        font-size: 15px;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .alm-keys-grid {
            grid-template-columns: 1fr;
        }
        
        .alm-hero-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 24px;
        }
        
        .alm-hero-stats {
            width: 100%;
            justify-content: space-between;
        }
    }
    
    @media (max-width: 768px) {
        .alm-stat-card {
            padding: 15px 20px;
        }
        
        .alm-stat-number {
            font-size: 24px;
        }
        
        .alm-key-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .alm-key-actions {
            flex-direction: column;
        }
        
        .alm-generate-card {
            flex-direction: column;
        }
    }
    </style>
    <?php
}