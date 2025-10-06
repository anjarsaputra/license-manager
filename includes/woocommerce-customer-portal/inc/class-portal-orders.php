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
        // Customize orders table
        add_filter('woocommerce_my_account_my_orders_columns', array($this, 'customize_orders_columns'));
        
        // Add custom actions to orders
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_custom_actions'), 10, 2);
        
        // Add order meta display
        add_action('woocommerce_order_details_after_order_table', array($this, 'add_order_details'));
    }
    
    /**
     * Customize orders table columns
     */
    public function customize_orders_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            
            // Add licenses column after order number
            if ($key === 'order-number') {
                $new_columns['order-licenses'] = __('Licenses', 'wc-customer-portal');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Add custom actions to orders
     */
    public function add_custom_actions($actions, $order) {
        // Add download invoice action
        $actions['invoice'] = array(
            'url' => wp_nonce_url(
                add_query_arg(
                    array(
                        'action' => 'wcp_download_invoice',
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
     * Add order details section
     */
    public function add_order_details($order) {
        $order_id = $order->get_id();
        $licenses = $this->get_order_licenses($order_id);
        
        if (empty($licenses)) {
            return;
        }
        
        ?>
        <section class="wcp-order-licenses">
            <h2><?php _e('License Keys', 'wc-customer-portal'); ?></h2>
            <div class="order-licenses-grid">
                <?php foreach ($licenses as $license) : ?>
                <div class="order-license-item">
                    <div class="license-product-name">
                        <strong><?php echo esc_html($license['product']); ?></strong>
                    </div>
                    <div class="license-key-display">
                        <code><?php echo esc_html($license['key']); ?></code>
                        <button 
                            class="copy-btn-mini" 
                            data-target="#order-license-<?php echo $license['id']; ?>"
                            title="<?php _e('Copy License Key', 'wc-customer-portal'); ?>"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                            </svg>
                        </button>
                        <input 
                            type="text" 
                            value="<?php echo esc_attr($license['key']); ?>" 
                            readonly 
                            id="order-license-<?php echo $license['id']; ?>" 
                            style="position:absolute;left:-9999px;"
                        >
                    </div>
                    <div class="license-status-display">
                        <span class="license-status status-<?php echo esc_attr($license['status']); ?>">
                            <?php echo esc_html(ucfirst($license['status'])); ?>
                        </span>
                        <span class="license-expires">
                            <?php printf(__('Expires: %s', 'wc-customer-portal'), esc_html($license['expires'])); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <style>
        .wcp-order-licenses {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e5e7eb;
        }
        
        .wcp-order-licenses h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 20px;
        }
        
        .order-licenses-grid {
            display: grid;
            gap: 16px;
        }
        
        .order-license-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
        }
        
        .license-product-name {
            margin-bottom: 12px;
        }
        
        .license-product-name strong {
            font-size: 15px;
            color: #111827;
        }
        
        .license-key-display {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .license-key-display code {
            flex: 1;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 14px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .license-status-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
        }
        
        .license-expires {
            color: #6b7280;
        }
        </style>
        <?php
    }
    
    /**
     * Get licenses for an order
     */
    private function get_order_licenses($order_id) {
        $licenses = [];
        
        if (class_exists('LicenseManagerForWooCommerce\Models\Resources\License')) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'lmfwc_licenses';
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, p.post_title as product_name 
                 FROM {$table_name} l
                 LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
                 WHERE l.order_id = %d
                 ORDER BY l.created_at DESC",
                $order_id
            ));
            
            if ($results) {
                foreach ($results as $license) {
                    $status = 'active';
                    if ($license->status == 3) {
                        $status = 'expired';
                    } elseif ($license->status == 4) {
                        $status = 'disabled';
                    }
                    
                    $expires = 'Lifetime';
                    if ($license->expires_at) {
                        $expires = date('d M Y', strtotime($license->expires_at));
                    }
                    
                    $licenses[] = [
                        'id' => $license->id,
                        'key' => $license->license_key,
                        'product' => $license->product_name ?: 'Unknown Product',
                        'status' => $status,
                        'expires' => $expires,
                    ];
                }
            }
        }
        
        return $licenses;
    }
}