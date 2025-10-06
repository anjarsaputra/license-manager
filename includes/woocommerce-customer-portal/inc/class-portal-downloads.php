<?php
/**
 * Portal Downloads Customization
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Downloads {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Customize downloads display
        add_action('woocommerce_account_downloads_endpoint', array($this, 'render_downloads'), 5);
        
        // Add download tracking
        add_action('woocommerce_download_product', array($this, 'track_download'), 10, 6);
    }
    
    /**
     * Render custom downloads page
     */
    public function render_downloads() {
        // Remove default content
        remove_action('woocommerce_account_downloads_endpoint', 'woocommerce_account_downloads');
        
        $downloads = WCP_Portal_Helpers::get_customer_downloads(get_current_user_id());
        
        if (empty($downloads)) {
            $this->render_empty_state();
            return;
        }
        
        $this->render_downloads_grid($downloads);
    }
    
    /**
     * Render downloads grid
     */
    private function render_downloads_grid($downloads) {
        ?>
        <div class="wcp-downloads-page">
            <div class="downloads-header">
                <h2><?php _e('Available Downloads', 'wc-customer-portal'); ?></h2>
                <p class="downloads-description">
                    <?php _e('Download your purchased products. All files are available for download.', 'wc-customer-portal'); ?>
                </p>
            </div>
            
            <div class="downloads-grid">
                <?php foreach ($downloads as $download) : ?>
                <div class="download-card">
                    <div class="download-card-header">
                        <div class="download-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="download-info">
                            <h3 class="download-name"><?php echo esc_html($download['download_name']); ?></h3>
                            <p class="download-product"><?php echo esc_html($download['product_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="download-meta">
                        <?php if (!empty($download['file']['version'])) : ?>
                        <div class="meta-item">
                            <span class="meta-label"><?php _e('Version:', 'wc-customer-portal'); ?></span>
                            <span class="meta-value"><?php echo esc_html($download['file']['version']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($download['downloads_remaining'])) : ?>
                        <div class="meta-item">
                            <span class="meta-label"><?php _e('Downloads:', 'wc-customer-portal'); ?></span>
                            <span class="meta-value">
                                <?php 
                                echo $download['downloads_remaining'] === '' 
                                    ? __('Unlimited', 'wc-customer-portal') 
                                    : esc_html($download['downloads_remaining']); 
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($download['access_expires'])) : ?>
                        <div class="meta-item">
                            <span class="meta-label"><?php _e('Expires:', 'wc-customer-portal'); ?></span>
                            <span class="meta-value">
                                <?php 
                                echo $download['access_expires'] === 'Never' 
                                    ? __('Never', 'wc-customer-portal')
                                    : esc_html(date_i18n(get_option('date_format'), strtotime($download['access_expires']))); 
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="download-actions">
                        <a 
                            href="<?php echo esc_url($download['download_url']); ?>" 
                            class="btn-download-primary"
                            download
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            <?php _e('Download', 'wc-customer-portal'); ?>
                        </a>
                        
                        <?php if (!empty($download['order_id'])) : ?>
                        <a 
                            href="<?php echo esc_url(wc_get_endpoint_url('view-order', $download['order_id'], wc_get_page_permalink('myaccount'))); ?>" 
                            class="btn-view-order"
                        >
                            <?php _e('View Order', 'wc-customer-portal'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .wcp-downloads-page {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .downloads-header {
            margin-bottom: 30px;
        }
        
        .downloads-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px;
        }
        
        .downloads-description {
            color: #6b7280;
            font-size: 15px;
            margin: 0;
        }
        
        .downloads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .download-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            transition: all 0.2s;
        }
        
        .download-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        
        .download-card-header {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .download-icon {
            width: 56px;
            height: 56px;
            background: #eff6ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .download-icon svg {
            stroke: #3b82f6;
        }
        
        .download-info {
            flex: 1;
            min-width: 0;
        }
        
        .download-name {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 4px;
        }
        
        .download-product {
            font-size: 13px;
            color: #6b7280;
            margin: 0;
        }
        
        .download-meta {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 10px;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .meta-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
        }
        
        .meta-value {
            font-size: 13px;
            color: #111827;
            font-weight: 700;
        }
        
        .download-actions {
            display: grid;
            gap: 10px;
        }
        
        .btn-download-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-download-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-download-primary svg {
            stroke: currentColor;
        }
        
        .btn-view-order {
            display: block;
            text-align: center;
            padding: 10px 16px;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .btn-view-order:hover {
            background: #e5e7eb;
        }
        
        @media (max-width: 768px) {
            .downloads-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render empty state
     */
    private function render_empty_state() {
        ?>
        <div class="wcp-downloads-empty">
            <div class="empty-state">
                <svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <h3><?php _e('No Downloads Available', 'wc-customer-portal'); ?></h3>
                <p><?php _e('You don\'t have any downloadable products yet. Purchase a product to get started!', 'wc-customer-portal'); ?></p>
                <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="btn-primary">
                    <?php _e('Browse Products', 'wc-customer-portal'); ?>
                </a>
            </div>
        </div>
        
        <style>
        .wcp-downloads-empty {
            max-width: 600px;
            margin: 60px auto;
        }
        </style>
        <?php
    }
    
    /**
     * Track download
     */
    public function track_download($email, $order_key, $product_id, $user_id, $download_id, $order_id) {
        // Log download activity
        if ($user_id) {
            update_user_meta(
                $user_id,
                '_wcp_last_download_' . $product_id,
                current_time('timestamp')
            );
        }
    }
}