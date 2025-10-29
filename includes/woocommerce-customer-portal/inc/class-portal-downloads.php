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
        remove_action('woocommerce_account_downloads_endpoint', 'woocommerce_account_downloads');
        add_action('woocommerce_account_downloads_endpoint', array($this, 'render_downloads'), 5);
        add_action('woocommerce_download_product', array($this, 'track_download'), 10, 6);
    }

    public function render_downloads() {
        $downloads = WCP_Portal_Helpers::get_customer_downloads(get_current_user_id());
        if (empty($downloads)) {
            $this->render_empty_state();
            return;
        }
        $this->render_downloads_grid($downloads);
    }

    private function render_downloads_grid($downloads) {
        ?>
        <div class="wcp-downloads-page">
            <div class="downloads-header">
                <h2>Unduhan Anda</h2>
<p>Akses semua file yang sudah Anda beli langsung di halaman ini.</p>

            </div>

            <div class="downloads-grid">
                <?php foreach ($downloads as $download) : ?>
                <div class="download-card">
                    <div class="download-card-header">
                        <div class="download-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="#2563eb" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="download-info">
                            <h3 class="download-name"><?php echo esc_html($download['download_name']); ?></h3>
                            <p class="download-product"><?php echo esc_html($download['product_name']); ?></p>
                        </div>
                    </div>

                    <div class="download-meta">
                        <?php if (!empty($download['downloads_remaining'])) : ?>
                        <p><strong>Jumlah Unduhan:</strong>
    <?php echo $download['downloads_remaining'] === '' ? 'Tak terbatas' : esc_html($download['downloads_remaining']); ?>
</p>
                        <?php endif; ?>

                        <?php if (!empty($download['access_expires'])) : ?>
                        <p><strong>Kedaluwarsa:</strong>
    <?php echo $download['access_expires'] === 'Never' ? 'Tidak pernah' : esc_html(date_i18n(get_option('date_format'), strtotime($download['access_expires']))); ?>
</p>
                        <?php endif; ?>
                    </div>

                    <div class="download-actions">
                        <a href="<?php echo esc_url($download['download_url']); ?>" class="btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            <?php _e('Download', 'wc-customer-portal'); ?>
                        </a>

                        <?php if (!empty($download['order_id'])) : ?>
                        <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $download['order_id'], wc_get_page_permalink('myaccount'))); ?>" class="btn-secondary">
    Lihat Pesanan
</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
        .wcp-downloads-page {
            margin-top: 10px;
        }
        
        .btn-primary,
.btn-secondary,
a.btn-primary,
a.btn-secondary,
.btn-primary:hover,
.btn-secondary:hover,
a.btn-primary:hover,
a.btn-secondary:hover,
.btn-primary:focus,
.btn-secondary:focus,
a.btn-primary:focus,
a.btn-secondary:focus,
.btn-primary:active,
.btn-secondary:active,
a.btn-primary:active,
a.btn-secondary:active {
    text-decoration: none !important;
}

        .downloads-header h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
        }
        .downloads-header p {
            color: #6b7280;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
        .downloads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
        }
        .download-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            transition: all 0.25s ease;
        }
        .download-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 14px rgba(37,99,235,0.08);
        }
        .download-card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }
        .download-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .download-info h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 2px;
        }
        .download-info p {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }
        .download-meta {
            background: #f9fafb;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .download-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            transition: all 0.25s ease;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 3px 10px rgba(37,99,235,0.25);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #111827;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.25s ease;
        }
        .btn-secondary:hover {
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

    private function render_empty_state() {
    ?>
    <div class="wcp-downloads-empty">
    <div class="empty-card">
        <div class="empty-icon">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <!-- Versi baru, sudah dirapikan viewBox-nya agar center -->
        <circle cx="24" cy="24" r="22" fill="url(#bg)" stroke="none"></circle>
        <polyline points="16 22 24 30 32 22"></polyline>
        <line x1="24" y1="30" x2="24" y2="12"></line>
        <defs>
            <radialGradient id="bg" cx="30%" cy="30%" r="70%">
                <stop offset="0%" stop-color="#e0f2fe"/>
                <stop offset="100%" stop-color="#f5f9ff"/>
            </radialGradient>
        </defs>
    </svg>
</div>

        <h3>Tidak Ada Unduhan Tersedia</h3>
<p>Anda belum membeli produk digital atau pesanan Anda masih dalam proses. Silakan shop untuk membeli produk download.</p>
<a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="btn-primary">
    Beli Produk
</a>

    </div>
</div>

<style>
.wcp-downloads-empty {
    width: 100%;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    padding: 40px 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 12px;
}



.empty-card {
    text-align: center;
    background: #f9fafb;
    border-radius: 14px;
    padding: 70px 40px;
    width: 100%;
    max-width: 700px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.03);
    position: relative;
}

.empty-icon {
    width: 92px;
    height: 92px;
    margin: 0 auto 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: radial-gradient(circle at 50% 50%, #e0f2fe, #f5f9ff);
    box-shadow: inset 0 0 10px rgba(37,99,235,0.06);
}

.empty-icon svg {
    display: block;
    stroke: #2563eb;
    opacity: 0.95;
    transform: scale(0.95);
}


.empty-card h3 {
    color: #111827;
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.empty-card p {
    color: #6b7280;
    font-size: 0.95rem;
    margin-bottom: 24px;
}

.btn-primary {
    display: inline-block;
    background: #2563eb;
    color: #fff;
    font-weight: 600;
    padding: 12px 26px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.25s ease;
}
.btn-primary:hover {
    background: #1d4ed8;
    box-shadow: 0 3px 10px rgba(37,99,235,0.25);
}



@media (max-width: 768px) {
    .wcp-downloads-empty {
        padding: 20px;
    }
    .empty-card {
        padding: 50px 24px;
    }
}
</style>

    <?php
}


    public function track_download($email, $order_key, $product_id, $user_id, $download_id, $order_id) {
        if ($user_id) {
            update_user_meta($user_id, '_wcp_last_download_' . $product_id, current_time('timestamp'));
        }
    }
}
?>
