<?php
/**
 * My Licenses Tab Template - IMPROVED
 * Display customer licenses in WooCommerce My Account
 * 
 * @package License Manager
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get data via Helper
$customer_id = get_current_user_id();
$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
$user_name = $current_user->display_name;

// Get licenses using Helper
$licenses = WCP_Portal_Helpers::get_customer_licenses($customer_id);
$stats = WCP_Portal_Helpers::get_license_stats($customer_id);

// Check if user has orders with license products
global $wpdb;
$has_license_orders = false;
$customer_orders = WCP_Portal_Helpers::get_customer_orders($customer_id);
if (!empty($customer_orders)) {
    $has_license_orders = true;
}

// Process licenses data - add activation sites
foreach ($licenses as &$license) {
    $license['active_sites'] = WCP_Portal_Helpers::get_license_activations($license['id']);
}
unset($license);
?>

<div class="alm-licenses-wrapper">
    
    <!-- Header -->
    <div class="alm-licenses-header">
        <h2><?php _e('Lisensi Saya', 'alm'); ?></h2>
        <p class="alm-licenses-description">
            <?php _e('Kelola lisensi tema Anda, lihat unduhan, dan kontrol aktivasi situs.', 'alm'); ?>
        </p>
    </div>

 <div class="alm-licenses-stats-grid">
    <div class="alm-stat-card orders">
        <div class="alm-stat-icon-bg orders-bg">
            <!-- Plus Icon -->
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <rect x="0" y="0" width="32" height="32" rx="10" fill="#3b82f6"/>
                <path d="M16 10v12M10 16h12" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div>
            <div class="alm-stat-number"><?php echo isset($stats['orders']) ? $stats['orders'] : 0; ?></div>
            <div class="alm-stat-label-main"><?php _e('Jumlah Pesanan', 'alm'); ?></div>
            <div class="alm-stat-label-desc">Semua Waktu</div>
        </div>
    </div>
    <div class="alm-stat-card licenses">
        <div class="alm-stat-icon-bg licenses-bg">
    <!-- Shield Check Icon -->
    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <rect x="0" y="0" width="32" height="32" rx="10" fill="#10b981"/>
        <!-- Shield shape -->
        <path d="M16 7c3.5 0 7 1.4 7 3v5.5c0 5.3-5.1 8.3-7 8.5-1.9-0.2-7-3.2-7-8.5V10c0-1.6 3.5-3 7-3z" fill="#fff"/>
        <!-- Check mark -->
        <path d="M13.5 16.5l2 2 3-3" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    </svg>
</div>
        <div>
            <div class="alm-stat-number"><?php echo isset($stats['active']) ? $stats['active'] : 0; ?></div>
            <div class="alm-stat-label-main"><?php _e('Lisensi Aktif', 'alm'); ?></div>
            <div class="alm-stat-label-desc"><?php echo $stats['active']; ?> total</div>
        </div>
    </div>
    <div class="alm-stat-card downloads">
        <div class="alm-stat-icon-bg downloads-bg">
            <!-- Download Icon -->
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <rect x="0" y="0" width="32" height="32" rx="10" fill="#f59e0b"/>
                <path d="M16 10v12m0 0l-4-4m4 4l4-4" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div>
            <div class="alm-stat-number"><?php echo isset($stats['downloads']) ? $stats['downloads'] : 0; ?></div>
            <div class="alm-stat-label-main"><?php _e('Unduh', 'alm'); ?></div>
            <div class="alm-stat-label-desc">Tersedia sekarang</div>
        </div>
    </div>
    <div class="alm-stat-card spent">
        <div class="alm-stat-icon-bg spent-bg">
            <!-- Dollar Icon -->
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <rect x="0" y="0" width="32" height="32" rx="10" fill="#8b5cf6"/>
                <text x="16" y="22" text-anchor="middle" fill="#fff" font-size="18" font-family="Arial" font-weight="bold">$</text>
            </svg>
        </div>
        <div>
            <div class="alm-stat-number"><?php echo isset($stats['spent']) ? 'Rp' . $stats['spent'] : 'Rp0'; ?></div>
            <div class="alm-stat-label-main"><?php _e('Yang dibelanjakan', 'alm'); ?></div>
            <div class="alm-stat-label-desc">Selamanya</div>
        </div>
    </div>
</div>


    <?php if (empty($licenses)) : ?>
        
        <!-- No Licenses Found -->
        <div class="alm-no-licenses">
            <div class="alm-no-licenses-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h3><?php _e('Belum ada lisensi', 'alm'); ?></h3>
            <p><?php _e('Anda belum membeli produk berlisensi apa pun.', 'alm'); ?></p>
            
            <?php if ($has_license_orders) : ?>
                <div class="alm-notice alm-notice-info">
                    <strong><?php _e('Note:', 'alm'); ?></strong>
                    <?php _e('Anda memiliki pesanan dengan produk berlisensi. Lisensi akan muncul di sini setelah pesanan Anda selesai.', 'alm'); ?>
                </div>
            <?php endif; ?>
            
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button alm-btn-primary">
                <?php _e('Beli Produk', 'alm'); ?>
            </a>
        </div>

    <?php else : ?>

        <!-- Licenses Grid -->
        <div class="alm-licenses-grid">
            
            <?php foreach ($licenses as $license) : 
                
                // Parse license data
                $license_key = $license['key'];
                $product_name = $license['product'];
                $status = $license['status'];
                $activations = $license['activations_used'];
                $activation_limit = $license['activations_max'];
                $expires = $license['expires'];
                $created_at = $license['created_at'];
                $active_sites = isset($license['active_sites']) ? $license['active_sites'] : array();
                
                // Status class
                $status_class = 'alm-status-' . strtolower($status);
                $status_label = ucfirst($status);
                
                // Expiration check
                $is_expired = ($status === 'expired');
                $expires_formatted = $expires;
                
                if (!empty($license['expires_at']) && $license['expires_at'] !== '0000-00-00 00:00:00') {
                    $expires_timestamp = strtotime($license['expires_at']);
                    $now = current_time('timestamp');
                    
                    if ($expires_timestamp < $now) {
                        $is_expired = true;
                        $expires_formatted = sprintf(
                            __('Expired on %s', 'alm'),
                            date_i18n(get_option('date_format'), $expires_timestamp)
                        );
                    } else {
                        // Days left warning
                        $days_left = floor(($expires_timestamp - $now) / DAY_IN_SECONDS);
                        if ($days_left <= 30 && $days_left > 0) {
                            $expires_formatted .= sprintf(
                                ' <span class="alm-expiring-soon">(%d %s)</span>',
                                $days_left,
                                _n('day left', 'days left', $days_left, 'alm')
                            );
                        }
                    }
                }
                
                // Purchase date
                $purchase_date = '';
                if (!empty($created_at)) {
                    $purchase_date = date_i18n(get_option('date_format'), strtotime($created_at));
                }
                
                // Get product for download link
                $product = null;
                global $wpdb;
                $product_name_clean = $product_name;
                
                // Try to get product by name
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'product' LIMIT 1",
                    $product_name
                ));
                
                if ($product_id) {
                    $product = wc_get_product($product_id);
                }
                
            ?>
            
            <!-- License Card -->
            <div class="alm-license-card <?php echo esc_attr($status_class); ?>" data-license-id="<?php echo esc_attr($license['id']); ?>">
                
                <!-- Card Header -->
                <div class="alm-license-header">
                    <div class="alm-license-product">
                        <h3><?php echo esc_html($product_name); ?></h3>
                        <?php if ($purchase_date) : ?>
                            <span class="alm-purchase-date">
                                <?php printf(__('Purchased %s', 'alm'), $purchase_date); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="alm-status-badge <?php echo esc_attr($status_class); ?>">
                        <?php echo esc_html($status_label); ?>
                    </span>
                </div>

                <!-- License Key -->
                <div class="alm-license-key-wrapper">
                    <label><?php _e('License Key:', 'alm'); ?></label>
                    <div class="alm-license-key-group">
                        <input 
                            type="text" 
                            class="alm-license-key-input" 
                            value="<?php echo esc_attr($license_key); ?>" 
                            readonly
                            id="license-key-<?php echo $license['id']; ?>"
                        />
                        <button 
                            type="button" 
                            class="alm-copy-btn" 
                            data-target="#license-key-<?php echo $license['id']; ?>"
                            title="<?php esc_attr_e('Copy to clipboard', 'alm'); ?>"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span><?php _e('Copy', 'alm'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- License Details Grid -->
                <div class="alm-license-details">
                    
                    <!-- Activations -->
                    <div class="alm-detail-item">
                        <span class="alm-detail-label"><?php _e('Activations:', 'alm'); ?></span>
                        <span class="alm-detail-value">
                            <strong><?php echo $activations; ?></strong> / <?php echo $activation_limit; ?>
                            <?php if ($activations >= $activation_limit) : ?>
                                <span class="alm-limit-reached"><?php _e('(Limit reached)', 'alm'); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Expiration -->
                    <div class="alm-detail-item">
                        <span class="alm-detail-label"><?php _e('Expires:', 'alm'); ?></span>
                        <span class="alm-detail-value <?php echo $is_expired ? 'alm-expired' : ''; ?>">
                            <?php echo $expires_formatted; ?>
                        </span>
                    </div>
                    
                </div>

                <!-- Activation Progress -->
                <?php if ($activation_limit > 0) : 
                    $progress_percent = min(100, ($activations / $activation_limit) * 100);
                ?>
                <div class="alm-activation-progress">
                    <div class="alm-progress-bar">
                        <div class="alm-progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                    <span class="alm-progress-text">
                        <?php printf(
                            __('%d of %d activations used', 'alm'),
                            $activations,
                            $activation_limit
                        ); ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Active Sites -->
                <?php if (!empty($active_sites)) : ?>
                    <div class="alm-active-sites">
                        <h4><?php _e('Active Sites', 'alm'); ?> (<?php echo count($active_sites); ?>)</h4>
                        <ul class="alm-sites-list">
                            <?php foreach ($active_sites as $site) : 
                                // Get site URL and activation date
                                $site_url = isset($site->site_url) ? $site->site_url : (isset($site->domain) ? $site->domain : 'Unknown');
                                $activated_at = isset($site->activated_at) ? $site->activated_at : (isset($site->created_at) ? $site->created_at : '');
                                $site_id = isset($site->id) ? $site->id : 0;
                            ?>
                                <li class="alm-site-item">
                                    <div class="alm-site-info">
                                        <span class="alm-site-url">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                                            </svg>
                                            <?php echo esc_html($site_url); ?>
                                        </span>
                                        <?php if ($activated_at) : ?>
                                        <span class="alm-site-date">
                                            <?php 
                                            printf(
                                                __('Activated %s ago', 'alm'),
                                                human_time_diff(strtotime($activated_at), current_time('timestamp'))
                                            ); 
                                            ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($site_id > 0) : ?>
                                    <button 
    type="button" 
    class="alm-deactivate-btn" 
    data-license-key="<?php echo esc_attr($license_key); ?>"
    data-site-id="<?php echo esc_attr($site_id); ?>"
    data-site-url="<?php echo esc_attr($site_url); ?>"
>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y2="18"/>
        <line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
    <?php _e('Deactivate', 'alm'); ?>
</button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="alm-license-actions">
                    <?php if ($product && $product->is_downloadable()) : ?>
                    <a href="<?php echo esc_url(wc_get_endpoint_url('downloads', '', wc_get_page_permalink('myaccount'))); ?>" class="alm-btn alm-btn-download">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <?php _e('Download Theme', 'alm'); ?>
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo esc_url(home_url('/documentation')); ?>" class="alm-btn alm-btn-docs" target="_blank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <?php _e('Documentation', 'alm'); ?>
                    </a>
                </div>

            </div>
            <!-- End License Card -->

            <?php endforeach; ?>
            
        </div>
        <!-- End Licenses Grid -->

    <?php endif; ?>

    <!-- Help Section -->
    <div class="alm-help-section">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div class="alm-help-content">
            <h3><?php _e('Butuh Bantuan?', 'alm'); ?></h3>
            <p><?php _e('Jika Anda memiliki pertanyaan tentang lisensi Anda atau memerlukan dukungan teknis, tim kami siap membantu.', 'alm'); ?></p>
            <a href="<?php echo esc_url(home_url('/contact')); ?>" class="button alm-btn-support">
                <?php _e('Kontak dukungan', 'alm'); ?>
            </a>
        </div>
    </div>

</div>

<style>
    
.alm-licenses-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    width: 100%;
}
.alm-stat-card {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(59,130,246,0.04);
    display: flex;
    align-items: center;
    gap: 20px;
    min-height: 110px;
    padding: 22px 28px;
    transition: box-shadow 0.2s, border-color 0.2s;
}
.alm-stat-card:hover {
    box-shadow: 0 4px 18px rgba(59,130,246,0.12);
    border-color: #3b82f6;
}
.alm-stat-icon-bg {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-right: 6px;
}
.orders-bg { background: #3b82f6; }
.licenses-bg { background: #10b981; }
.downloads-bg { background: #f59e0b; }
.spent-bg { background: #8b5cf6; }
.alm-stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}
.alm-stat-label-main {
    font-size: 1.2rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: 4px;
}
.alm-stat-label-desc {
    font-size: 1rem;
    font-weight: 500;
    color: #6b7280;
}
@media (max-width: 700px) {
    .alm-licenses-stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .alm-stat-card {
        min-height: 72px;
        padding: 12px 14px;
        gap: 12px;
    }
    .alm-stat-icon-bg {
        width: 36px;
        height: 36px;
        border-radius: 8px;
    }
    .alm-stat-number {
        font-size: 1.25rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Copy license key
    $('.alm-copy-btn').on('click', function() {
        var target = $(this).data('target');
        var $input = $(target);
        
        $input.select();
        document.execCommand('copy');
        
        var $btn = $(this);
        var originalHtml = $btn.html();
        
        $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><span>Copied!</span>');
        $btn.addClass('copied');
        
        setTimeout(function() {
            $btn.html(originalHtml);
            $btn.removeClass('copied');
        }, 2000);
    });
    
    // Deactivate site - COMPLETELY FIXED
    $('.alm-deactivate-btn').on('click', function() {
        var $btn = $(this);
        var licenseKey = $btn.data('license-key');
        var siteId = $btn.data('site-id');
        var siteUrl = $btn.data('site-url');
        
        // Store original button HTML
        var originalButtonHtml = $btn.html();
        
        console.log('Deactivate clicked:', {
            licenseKey: licenseKey,
            siteId: siteId,
            siteUrl: siteUrl
        });
        
        // Validate data
        if (!licenseKey || !siteId) {
            alert('Error: Missing license key or site ID');
            console.error('Missing data:', {licenseKey, siteId});
            return;
        }
        
        if (!confirm('<?php _e('Are you sure you want to deactivate this site?', 'alm'); ?>\n\n' + siteUrl)) {
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Deactivating...', 'alm'); ?>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'alm_deactivate_site',
                license_key: licenseKey,
                site_id: siteId,
                site_url: siteUrl,
                nonce: '<?php echo wp_create_nonce('alm_license_action'); ?>'
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                
                if (response && response.success) {
                    // Success - show message and reload
                    var successMsg = 'Site deactivated successfully!';
                    
                    if (response.data && response.data.message) {
                        successMsg = response.data.message;
                    }
                    
                    alert(successMsg);
                    location.reload();
                    
                } else {
                    // Error - parse error message safely
                    var errorMsg = '<?php _e('Failed to deactivate site', 'alm'); ?>';
                    
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                        }
                    }
                    
                    alert(errorMsg);
                    
                    // Restore button
                    $btn.prop('disabled', false).html(originalButtonHtml);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', {
                    status: textStatus,
                    error: errorThrown,
                    response: jqXHR.responseText
                });
                
                alert('<?php _e('Connection error. Please try again.', 'alm'); ?>');
                
                // Restore button
                $btn.prop('disabled', false).html(originalButtonHtml);
            }
        });
    });
    
});
</script>