<?php
/**
 * Dashboard Template
 *
 * @package WC_Customer_Portal
 * @var WP_User $current_user
 * @var int $customer_id
 * @var array $licenses
 * @var array $orders
 * @var array $downloads
 * @var array $stats
 * @var float $total_spent
 */

if (!defined('ABSPATH')) {
    exit;
}

// Proteksi variabel agar selalu array (anti error null)
$orders    = is_array($orders)    ? $orders    : [];
$licenses  = is_array($licenses)  ? $licenses  : [];
$downloads = is_array($downloads) ? $downloads : [];
$stats     = is_array($stats)     ? $stats     : ['expired'=>0, 'active'=>0, 'total'=>0];

$greeting = class_exists('WCP_Portal_Helpers') ? WCP_Portal_Helpers::get_greeting() : 'Hai';
$recent_orders    = array_slice($orders,    0, 3);
$recent_licenses  = array_slice($licenses,  0, 3);
$recent_downloads = array_slice($downloads, 0, 3);
?>


<div class="portal-account-dashboard">

    <!-- Welcome Card -->
    <div class="dashboard-user-card">
        <div class="user-avatar">
            <?php
            $avatar_url = get_avatar_url($customer_id, ['size' => 80]);
            if ($avatar_url) {
                echo '<img src="' . esc_url($avatar_url) . '" alt="Avatar">';
            } else {
                $initials = strtoupper(substr($current_user->display_name, 0, 1));
                echo '<span class="avatar-initial">' . esc_html($initials) . '</span>';
            }
            ?>
        </div>
        <div class="user-meta">
            <h2 class="dashboard-title">
                <?php echo esc_html($greeting); ?>, <?php echo esc_html($current_user->display_name); ?>!
            </h2>
            <p class="dashboard-login">
                <?php
                printf(
                    __('Terakhir login: %s', 'wc-customer-portal'),
                    date_i18n('d F Y, H:i', current_time('timestamp'))
                );
                ?>
                <span class="user-role">• <?php echo !empty($current_user->roles) ? ucfirst($current_user->roles[0]) : ''; ?></span>
            </p>
        </div>
        <div class="user-actions">
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>" class="btn-icon" title="<?php _e('Pengaturan', 'wc-customer-portal'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                </svg>
            </a>
        </div>
    </div>

    <!-- Alert if expired licenses -->
    <?php if ($stats['expired'] > 0) : ?>
    <div class="dashboard-alert alert-warning">
        <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div class="alert-content">
            <strong><?php _e('Tindakan Diperlukan', 'wc-customer-portal'); ?></strong>
            <?php
            printf(
                __('Anda memiliki %d lisensi kedaluwarsa.', 'wc-customer-portal'),
                $stats['expired']
            );
            ?>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-licenses')); ?>">
                <?php _e('Perpanjang Sekarang', 'wc-customer-portal'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="dashboard-stats-grid">
        <!-- Orders -->
        <div class="stat-card stat-orders">
            <div class="stat-icon">
                <!-- SVG... -->
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($orders); ?></div>
                <div class="stat-label"><?php _e('Total Pesanan', 'wc-customer-portal'); ?></div>
                <div class="stat-trend"><?php _e('Semua Waktu', 'wc-customer-portal'); ?></div>
            </div>
        </div>
        <!-- Licenses -->
        <div class="stat-card stat-licenses">
            <div class="stat-icon">
                <!-- SVG... -->
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['active']; ?></div>
                <div class="stat-label"><?php _e('Lisensi Aktif', 'wc-customer-portal'); ?></div>
                <div class="stat-trend"><?php echo $stats['total']; ?> <?php _e('total', 'wc-customer-portal'); ?></div>
            </div>
        </div>
        <!-- Downloads -->
        <div class="stat-card stat-products">
            <div class="stat-icon">
                <!-- SVG... -->
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($downloads); ?></div>
                <div class="stat-label"><?php _e('Unduh', 'wc-customer-portal'); ?></div>
                <div class="stat-trend"><?php _e('Tersedia Sekarang', 'wc-customer-portal'); ?></div>
            </div>
        </div>
        <!-- Spent -->
        <div class="stat-card stat-spent">
            <div class="stat-icon">
                <!-- SVG... -->
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo wc_price($total_spent); ?></div>
                <div class="stat-label"><?php _e('Yang Dibelanjakan', 'wc-customer-portal'); ?></div>
                <div class="stat-trend"><?php _e('Selamanya', 'wc-customer-portal'); ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dashboard-section">
        <h3 class="section-title"><?php _e('Tindakan Cepat', 'wc-customer-portal'); ?></h3>
        <div class="dashboard-actions">
            
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-licenses')); ?>" class="action-btn btn-blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                <span class="btn-text"><?php _e('Lisensi Saya', 'wc-customer-portal'); ?></span>
                <?php if ($stats['active'] > 0) : ?>
                <span class="btn-badge"><?php echo $stats['active']; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('lihat-pesanan')); ?>" class="action-btn btn-green">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                </svg>
                <span class="btn-text"><?php _e('Pesanan', 'wc-customer-portal'); ?></span>
                <?php if (count($orders) > 0) : ?>
                <span class="btn-badge"><?php echo count($orders); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('unduh')); ?>" class="action-btn btn-orange">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span class="btn-text"><?php _e('Unduh', 'wc-customer-portal'); ?></span>
                <?php if (count($downloads) > 0) : ?>
                <span class="btn-badge"><?php echo count($downloads); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="action-btn btn-purple">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>
                </svg>
                <span class="btn-text"><?php _e('Belanja', 'wc-customer-portal'); ?></span>
            </a>
            
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="dashboard-grid">
        
        <!-- Left Column -->
        <div class="dashboard-col">
            
            <!-- Recent Licenses -->
            <div class="dashboard-section">
                <h3 class="section-title">
                    <?php _e('Lisensi Terbaru', 'wc-customer-portal'); ?>
                    <?php if (count($licenses) > 3) : ?>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-licenses')); ?>" class="view-all-link">
                        <?php printf(__('View All (%d) →', 'wc-customer-portal'), count($licenses)); ?>
                    </a>
                    <?php endif; ?>
                </h3>
                
                <?php if ($recent_licenses) : ?>
                <div class="licenses-preview">
                    <?php foreach ($recent_licenses as $idx => $license) : ?>
                    <div class="license-card-mini">
                        <div class="license-header">
                            <span class="license-product"><?php echo esc_html($license['product']); ?></span>
                            <span class="license-status status-<?php echo esc_attr($license['status']); ?>">
                                <?php echo esc_html(ucfirst($license['status'])); ?>
                            </span>
                        </div>
                        <div class="license-key-row">
                            <code class="license-key"><?php echo esc_html($license['key']); ?></code>
                            <button class="copy-btn-mini" data-target="#lic-prev-<?php echo $idx; ?>" title="<?php _e('Copy', 'wc-customer-portal'); ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                                </svg>
                            </button>
                            <input type="text" value="<?php echo esc_attr($license['key']); ?>" readonly id="lic-prev-<?php echo $idx; ?>" style="position:absolute;left:-9999px;">
                        </div>
                        <div class="license-meta-row">
                            <span><?php _e('Aktivasi:', 'wc-customer-portal'); ?> <strong><?php echo esc_html($license['activations']); ?></strong></span>
                            <span><?php _e('Kadaluarsa:', 'wc-customer-portal'); ?> <strong><?php echo esc_html($license['expires']); ?></strong></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($licenses) > 0) : ?>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-licenses')); ?>" class="btn-view-all-licenses">
                    <?php _e('View All Licenses →', 'wc-customer-portal'); ?>
                </a>
                <?php endif; ?>
                
                <?php else : ?>
                <div class="empty-state">
                    <svg class="empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                    <p><?php _e('Belum ada lisensi', 'wc-customer-portal'); ?></p>
                    <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="btn-primary-small">
                        <?php _e('Beli Produk', 'wc-customer-portal'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Downloads -->
            <div class="dashboard-section">
                <h3 class="section-title"><?php _e('Unduhan Terbaru', 'wc-customer-portal'); ?></h3>
                <?php if ($recent_downloads) : ?>
                <ul class="downloads-list">
                    <?php foreach ($recent_downloads as $download) : ?>
                    <li>
                        <svg class="download-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        <div class="download-info">
                            <strong><?php echo esc_html($download['download_name']); ?></strong>
                            <span class="download-product"><?php echo esc_html($download['product_name']); ?></span>
                        </div>
                        <a href="<?php echo esc_url($download['download_url']); ?>" class="btn-download">
                            <?php _e('Download', 'wc-customer-portal'); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else : ?>
                <div class="empty-state-small">
                    <svg class="empty-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    <span><?php _e('Tidak ada unduhan yang tersedia', 'wc-customer-portal'); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- Right Column -->
        <div class="dashboard-col">
            
            <!-- Recent Orders -->
            <div class="dashboard-section">
                <h3 class="section-title">
                    <?php _e('Pesanan Terbaru', 'wc-customer-portal'); ?>
                    <?php if (count($orders) > 3) : ?>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="view-all-link">
                        <?php _e('View All →', 'wc-customer-portal'); ?>
                    </a>
                    <?php endif; ?>
                </h3>
                
                <?php if ($recent_orders) : ?>
                <ul class="orders-list">
                    <?php if ($recent_orders && is_array($recent_orders)) : ?>
                        <?php foreach ($recent_orders as $order) : ?>
                        <li class="order-item">
                        <div class="order-header">
                            <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="order-number">
                                #<?php echo $order->get_order_number(); ?>
                            </a>
                            <span class="order-status status-<?php echo $order->get_status(); ?>">
                                <?php echo wc_get_order_status_name($order->get_status()); ?>
                            </span>
                        </div>
                        <div class="order-meta">
                            <span class="order-date">
                                <?php echo wc_format_datetime($order->get_date_created(), 'd M Y'); ?>
                            </span>
                            <span class="order-total">
                                <?php echo $order->get_formatted_order_total(); ?>
                            </span>
                        </div>
                        <div class="order-items-count">
                            <?php echo $order->get_item_count(); ?> <?php _e('item(s)', 'wc-customer-portal'); ?>
                        </div>
                    </li>
                   <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <?php else : ?>
                <div class="empty-state-small">
                    <svg class="empty-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                    </svg>
                    <span><?php _e('Belum ada pesanan', 'wc-customer-portal'); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Account Summary -->
            <div class="dashboard-section account-summary">
                <h3 class="section-title"><?php _e('Ringkasan Akun', 'wc-customer-portal'); ?></h3>
                <div class="summary-content">
                    <div class="summary-item">
                        <span class="summary-label"><?php _e('Email', 'wc-customer-portal'); ?></span>
                        <span class="summary-value"><?php echo esc_html($current_user->user_email); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><?php _e('Anggota Sejak', 'wc-customer-portal'); ?></span>
                        <span class="summary-value">
                            <?php echo date_i18n('F Y', strtotime($current_user->user_registered)); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><?php _e('Total Belanja', 'wc-customer-portal'); ?></span>
                        <span class="summary-value"><?php echo wc_price($total_spent); ?></span>
                    </div>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-akun')); ?>" class="btn-edit-account">
                        <?php _e('Edit Akun', 'wc-customer-portal'); ?>
                    </a>
                </div>
            </div>
            
        </div>
        
    </div>
    
    
            <style>
        /* HILANGKAN GARIS BAWAH SEMUA BUTTON/LINK */
        a, .action-btn, .btn-view-all-licenses, .btn-download, .btn-edit-account, .view-all-link, .btn-primary-small {
            text-decoration: none !important;
        }
        
        
        /* Hilangkan garis bawah pada semua link & button */
        a, .action-btn, .btn-view-all-licenses, .btn-download, .btn-edit-account, .view-all-link, .btn-primary-small {
            text-decoration: none !important;
        }
        
        /* Hover: tampilkan background abu terang, warna text tetap hitam, tanpa garis bawah */
        a:hover,
        .action-btn:hover,
        .btn-view-all-licenses:hover,
        .btn-download:hover,
        .btn-edit-account:hover,
        .view-all-link:hover,
        .btn-primary-small:hover {
            text-decoration: none !important;
            background: #f3f4f6 !important; /* abu terang */
            color: #1f2937 !important;       /* hitam (Tailwind gray-800) */
            border-color: inherit !important;
            box-shadow: none !important;
        }
        
        a, .action-btn, .btn-view-all-licenses, .btn-download, .btn-edit-account, .view-all-link, .btn-primary-small {
                text-decoration: none !important;
            }
            a:hover,
            .action-btn:hover,
            .btn-view-all-licenses:hover,
            .btn-download:hover,
            .btn-edit-account:hover,
            .view-all-link:hover,
            .btn-primary-small:hover {
                text-decoration: none !important;
                background: #f3f4f6 !important;
                color: #1f2937 !important;
                border-color: inherit !important;
                box-shadow: none !important;
            }
        </style>
</div>
