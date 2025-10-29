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
        // Custom output di endpoint edit-address WooCommerce
        add_action('woocommerce_account_edit-address_endpoint', array($this, 'render_edit_address_header'), 12);
    }

    /**
     * Custom header & info di halaman edit-address WooCommerce
     */
    public function render_edit_address_header() {
         error_log('WCP_Portal_Address: render_edit_address_header RUNNING!');
    echo '<div style="background:orange;padding:10px;">DEBUG: Class WCP_Portal_Address Aktif!</div>';
        // Bisa tambahkan logic untuk membedakan billing/shipping
        $address_type = isset($_GET['address']) ? $_GET['address'] : 'billing';
        $label = $address_type === 'shipping' ? 'Alamat Pengiriman' : 'Alamat Tagihan';
        ?>
        <div class="wcp-edit-address-card">
            <h2>
                <span style="font-size:2rem;vertical-align:-5px;">🏠</span>
                <?php echo esc_html($label); ?>
            </h2>
            <p class="wcp-edit-address-desc">
                Silakan isi atau ubah data alamat <?php echo strtolower($label); ?> Anda. Data ini digunakan untuk pengiriman dan tagihan pesanan.
            </p>
            <!-- Bisa tambahkan info, banner, atau instruksi lain di sini -->
        </div>
        <style>
        .wcp-edit-address-card {
            background: linear-gradient(90deg,#f8fafc 0%, #f1f5f9 100%);
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(59,130,246,0.06);
            padding: 30px 34px;
            margin-bottom: 28px;
            margin-top: 7px;
            max-width: 640px;
        }
        .wcp-edit-address-card h2 {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #2563eb;
        }
        .wcp-edit-address-desc {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        @media (max-width: 500px) {
            .wcp-edit-address-card { padding: 18px 10px; }
            .wcp-edit-address-card h2 { font-size:1.2rem;}
        }
        </style>
        <?php
    }
}
?>