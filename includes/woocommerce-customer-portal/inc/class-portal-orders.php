<?php
/**
 * Portal Orders Customization
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Orders {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        // Hapus tampilan tabel order default WooCommerce
        remove_action('woocommerce_account_orders_endpoint', 'woocommerce_account_orders', 10);

        // Kolom custom (jika ingin menambah kolom order)
        add_filter('woocommerce_account_orders_columns', array($this, 'customize_orders_columns'));

        // Tambah aksi kustom seperti Invoice
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_custom_actions'), 10, 2);

        // Tampilkan detail lisensi di halaman detail order
        add_action('woocommerce_order_details_after_order_table', array($this, 'add_order_details'));

        // Ganti tampilan order list jadi grid card modern
        add_action('woocommerce_account_orders_endpoint', array($this, 'render_orders_as_cards'), 5);
    }

    /**
     * === CUSTOM RENDER: GRID CARD ORDERS ===
     */
    public function render_orders_as_cards() {
        if (!is_account_page()) return;

        echo '<style>.woocommerce table.my_account_orders, .woocommerce .woocommerce-orders-table { display:none!important; }</style>';

        $customer_id = get_current_user_id();
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit'       => 10,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ));

        /**
         * ==== TAMPILAN SAAT BELUM ADA ORDER ====
         */
        if (empty($orders)) {
            ?>
            <div class="wc-empty-orders">
                <div class="wc-empty-orders-inner">
                    <div class="wc-empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l1.5 9h9l1.5-9z"/>
                            <path d="M9 9V4h6v5"/>
                        </svg>
                    </div>
                    <h3><?php _e('Belum ada pesanan', 'woocommerce'); ?></h3>
                    <p><?php _e('Sepertinya Anda belum melakukan pembelian apa pun.', 'woocommerce'); ?></p>
                    <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="wc-browse-btn">
                        <?php _e('Beli produk', 'woocommerce'); ?>
                    </a>
                </div>
            </div>

            <style>



            .wc-empty-orders {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 300px;
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 14px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                margin-top: 20px;
            }
            .wc-empty-orders-inner {
                text-align: center;
                padding: 40px 20px;
                color: #374151;
            }
            .wc-empty-icon {
                display: inline-flex;
                justify-content: center;
                align-items: center;
                background: #e0f2fe;
                border-radius: 50%;
                width: 80px;
                height: 80px;
                margin-bottom: 20px;
            }
            .wc-empty-icon svg {
                width: 40px;
                height: 40px;
            }
            .wc-empty-orders-inner h3 {
                font-size: 1.4rem;
                font-weight: 700;
                color: #111827;
                margin-bottom: 8px;
            }
            .wc-empty-orders-inner p {
                color: #6b7280;
                font-size: 0.95rem;
                margin-bottom: 20px;
            }
            .wc-browse-btn {
                background: #2563eb;
                color: #fff;
                padding: 10px 22px;
                border-radius: 8px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.25s ease;
            }
            .wc-browse-btn:hover {
                background: #1d4ed8;
                box-shadow: 0 3px 10px rgba(37,99,235,0.25);
            }
            </style>
            <?php
            return;
        }

        /**
         * ==== TAMPILAN GRID ORDER ====
         */
        ?>
        <div class="wc-orders-card-grid">
            <?php foreach ($orders as $order) :
                $order_id     = $order->get_id();
                $order_status = $order->get_status();
                $actions      = wc_get_account_orders_actions($order);
            ?>
            <div class="wc-order-card status-<?php echo esc_attr($order_status); ?>" onclick="this.classList.toggle('expanded')">
                <div class="wc-order-card-header">
                    <div class="wc-order-meta">
                        <h3 class="wc-order-number">#<?php echo esc_html($order->get_order_number()); ?></h3>
                        <p class="wc-order-date"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></p>
                    </div>
                    <span class="wc-order-status badge-<?php echo esc_attr($order_status); ?>">
                        <?php echo esc_html(wc_get_order_status_name($order_status)); ?>
                    </span>
                </div>

                <div class="wc-order-card-body">
                    <div class="wc-order-summary">
                        <span class="wc-order-total"><?php echo $order->get_formatted_order_total(); ?></span>
                        <span class="wc-order-items"><?php echo $order->get_item_count(); ?> produk</span>

                    </div>

                    <div class="wc-order-actions">
                        <?php foreach ($actions as $key => $action) :
                            $icons = [
                                'bayar'     => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14m7-7H5"/></svg>',
                                'lihat'    => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M2.05 12a9.94 9.94 0 0 1 19.9 0 9.94 9.94 0 0 1-19.9 0z"/></svg>',
                                'batal'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                                'invoice' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="20" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>',
                            ];
                            $class = $key;
                        ?>
                            <a href="<?php echo esc_url($action['url']); ?>" class="wc-order-action-btn <?php echo esc_attr($class); ?>">
                                <?php echo $icons[$key] ?? ''; ?> <span><?php echo esc_html($action['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="wc-order-details-expand">
                    <p><strong>ID Pesanan:</strong> <?php echo $order_id; ?></p>
                    <p><strong>Status:</strong> <?php echo esc_html(wc_get_order_status_name($order_status)); ?></p>
                    <p><strong>Total:</strong> <?php echo $order->get_formatted_order_total(); ?></p>
                    <p><strong>Metode Pembayaran:</strong> <?php echo esc_html($order->get_payment_method_title()); ?></p>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <style>
        .wc-orders-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .wc-order-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            cursor: pointer;
            transition: all .25s ease;
        }
        .wc-order-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 14px rgba(37,99,235,0.08);
        }
        .wc-order-card.expanded {
            background: #f8fafc;
        }
        .wc-order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .wc-order-number {font-weight:700;color:#111827;font-size:1.05rem;margin:0;}
        .wc-order-date {font-size:0.9rem;color:#6b7280;}
        .wc-order-status {padding:6px 12px;border-radius:999px;font-weight:600;font-size:0.85rem;text-transform:capitalize;}
        .badge-completed{background:#d1fae5;color:#065f46;}
        .badge-processing{background:#dbeafe;color:#1e40af;}
        .badge-pending{background:#fef3c7;color:#92400e;}
        .badge-cancelled{background:#fee2e2;color:#991b1b;}
        .wc-order-card-body{margin-top:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
        .wc-order-total{font-weight:700;color:#1e293b;font-size:1.1rem;}
        .wc-order-items{color:#64748b;font-size:0.9rem;}
        .wc-order-actions{display:flex;gap:8px;flex-wrap:wrap;}
        .wc-order-action-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;transition:all .25s ease;border:1px solid transparent;}
        .wc-order-action-btn svg{width:16px;height:16px;}
        .wc-order-action-btn.pay{background:#16a34a;color:#fff;}
        .wc-order-action-btn.pay:hover{background:#15803d;}
        .wc-order-action-btn.view{background:#2563eb;color:#fff;}
        .wc-order-action-btn.view:hover{background:#1d4ed8;}
        .wc-order-action-btn.cancel{background:#ef4444;color:#fff;}
        .wc-order-action-btn.cancel:hover{background:#dc2626;}
        .wc-order-action-btn.invoice{background:#f3f4f6;color:#111827;border:1px solid #d1d5db;}
        .wc-order-action-btn.invoice:hover{background:#e5e7eb;}
        .wc-order-details-expand{display:none;margin-top:16px;font-size:0.9rem;color:#374151;border-top:1px solid #e5e7eb;padding-top:10px;line-height:1.6;}
        .wc-order-card.expanded .wc-order-details-expand{display:block;animation:fadeIn .25s ease;}
        @keyframes fadeIn{from{opacity:0;transform:translateY(-3px);}to{opacity:1;transform:translateY(0);}}
        @media(max-width:768px){.wc-orders-card-grid{grid-template-columns:1fr;}}
        </style>
        <?php
    }

    /**
     * === CUSTOM COLUMNS (optional) ===
     */
    public function customize_orders_columns($columns) {
        return $columns;
    }

    /**
     * === ADD CUSTOM ACTIONS (INVOICE) ===
     */
    public function add_custom_actions($actions, $order) {
        $actions['invoice'] = array(
            'url'  => wp_nonce_url(
                add_query_arg(
                    array(
                        'action'   => 'wcp_download_invoice',
                        'order_id' => $order->get_id(),
                    ),
                    admin_url('admin-ajax.php')
                ),
                'wcp_download_invoice'
            ),
            'name' => __('Invoice', 'wc-customer-portal'),
        );
        return $actions;
    }

    /**
     * === LICENSE DETAILS (optional) ===
     */
    public function add_order_details($order) {
        $order_id = $order->get_id();
        $licenses = $this->get_order_licenses($order_id);
        if (empty($licenses)) return;
        ?>
        <section class="wcp-order-licenses">
            <h2>Kunci Lisensi</h2>
            <div class="order-licenses-grid">
                <?php foreach ($licenses as $license) : ?>
                    <div class="order-license-item">
                        <div class="license-product-name"><strong><?php echo esc_html($license['product']); ?></strong></div>
                        <div class="license-key-display"><code><?php echo esc_html($license['key']); ?></code></div>
                        <div class="license-status-display">
                            <span class="license-status status-<?php echo esc_attr($license['status']); ?>">
    <?php echo esc_html($status); ?>
</span>

                            <span class="license-expires">
    <?php printf('Kadaluarsa: %s', esc_html($license['expires'])); ?>
</span>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <style>
        .wcp-order-licenses{margin-top:30px;padding-top:30px;border-top:2px solid #e5e7eb;}
        .order-licenses-grid{display:grid;gap:16px;}
        .order-license-item{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px;}
        .license-key-display code{background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:8px 12px;display:inline-block;}
        .license-status-display{display:flex;justify-content:space-between;margin-top:10px;font-size:13px;}
        .license-expires{color:#6b7280;}
        </style>
        <?php
    }

    private function get_order_licenses($order_id) {
        $licenses = [];
        if (class_exists('LicenseManagerForWooCommerce\Models\Resources\License')) {
            global $wpdb;
            $table = $wpdb->prefix . 'lmfwc_licenses';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, p.post_title as product_name 
                 FROM {$table} l
                 LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
                 WHERE l.order_id = %d ORDER BY l.created_at DESC",
                $order_id
            ));
            foreach ($results as $license) {
                if ($license->status == 3) {
    $status = 'Kadaluwarsa';
} elseif ($license->status == 4) {
    $status = 'Dinonaktifkan';
} else {
    $status = 'Aktif';
}


                $expires = $license->expires_at ? date('d M Y', strtotime($license->expires_at)) : 'Lifetime';
                $licenses[] = [
                    'id'      => $license->id,
                    'key'     => $license->license_key,
                    'product' => $license->product_name ?: 'Unknown Product',
                    'status'  => $status,
                    'expires' => $expires,
                ];
            }
        }
        return $licenses;
    }
}
?>
