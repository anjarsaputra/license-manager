<?php
/**
 * Portal Address Customization
 *
 * @package WC_Customer_Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCP_Portal_Address {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Contoh: custom output di endpoint address WooCommerce
        add_action('woocommerce_account_edit-address_endpoint', array($this, 'render_address_card'), 12);
        // Bisa tambah filter/hook lain sesuai kebutuhan
    }

    public function render_address_card() {
        if (!is_account_page()) return;

        $user_id = get_current_user_id();
        $address = get_user_meta($user_id, 'billing_address_1', true);
        $city    = get_user_meta($user_id, 'billing_city', true);
        $postcode= get_user_meta($user_id, 'billing_postcode', true);
        $country = get_user_meta($user_id, 'billing_country', true);

        ?>
        <div class="wcp-address-card">
            <h2><?php esc_html_e('Your Billing Address', 'wc-customer-portal'); ?></h2>
            <div class="wcp-address-detail">
                <span class="wcp-address-row"><?php echo esc_html($address); ?></span>
                <span class="wcp-address-row"><?php echo esc_html($city); ?>, <?php echo esc_html($postcode); ?></span>
                <span class="wcp-address-row"><?php echo esc_html($country); ?></span>
            </div>
            <a href="<?php echo esc_url( wc_get_endpoint_url('edit-address', '', wc_get_page_permalink('myaccount')) ); ?>" class="wcp-address-edit-btn">
                <?php esc_html_e('Edit Address', 'wc-customer-portal'); ?>
            </a>
        </div>
        <style>
        .wcp-address-card {
            background: linear-gradient(90deg,#e0e7ff 0%, #f1f5f9 100%);
            border-radius: 20px;
            box-shadow: 0 2px 16px rgba(59,130,246,0.10);
            padding: 34px 38px;
            margin-bottom: 32px;
            margin-top: 12px;
            max-width: 600px;
        }
        .wcp-address-card h2 {
            font-size: 1.25rem;
            margin-bottom: 18px;
            color: #312e81;
            font-weight: 700;
        }
        .wcp-address-detail {
            margin-bottom: 18px;
            color: #374151;
            font-size: 1.05rem;
        }
        .wcp-address-row {
            display: block;
            margin-bottom: 6px;
        }
        .wcp-address-edit-btn {
            background: #3b82f6;
            color: #fff;
            padding: 11px 24px;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.22s;
            display: inline-block;
        }
        .wcp-address-edit-btn:hover {
            background: #2563eb;
        }
        </style>
        <?php
    }
}
?>