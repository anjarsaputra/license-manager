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
    $address_type = isset($_GET['address']) ? $_GET['address'] : 'billing';
    $label = $address_type === 'shipping' ? 'Alamat Pengiriman' : 'Alamat Tagihan';
    ?>
    <div class="wcp-edit-address-card-modern">
        <div class="edit-address-card-icon">
            <?php if ($address_type === 'shipping'): ?>
                <span>📦</span>
            <?php else: ?>
                <span>🏠</span>
            <?php endif; ?>
        </div>
        <div class="edit-address-card-content">
            <h2 class="edit-address-card-title"><?php echo esc_html($label); ?></h2>
            <p class="edit-address-card-desc">
                Silakan perbarui alamat <b><?php echo strtolower($label); ?></b> Anda.<br>
                Data ini dipakai untuk pengiriman & tagihan pesanan.
            </p>
        </div>
    </div>
    <style>
    .wcp-edit-address-card-modern {
        display: flex;
        align-items: center;
        gap: 24px;
        background: #fff;
        box-shadow: 0 5px 24px rgba(59,130,246,0.10);
        border-radius: 20px;
        padding: 28px 34px;
        margin: 20px 0 30px 0;
        border: 1.5px solid #eff4fd;
        max-width: 630px;
        transition: box-shadow 0.15s;
    }
    .wcp-edit-address-card-modern:hover {
        box-shadow: 0 7px 30px rgba(59,130,246,0.13);
        border-color: #2563eb33;
    }
    .edit-address-card-icon {
        width: 66px;
        height: 66px;
        background: linear-gradient(135deg,#eff6ff 0%,#e0e7ff 80%);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 2.25rem;
        box-shadow: 0 4px 18px rgba(59,130,246,0.08);
        color: #2563eb;
        flex-shrink: 0;
    }
    .edit-address-card-content { flex: 1; }
    .edit-address-card-title {
        font-size: 1.39rem;
        font-weight: 700;
        color: #2563eb;
        margin-bottom: 6px;
        margin-top: 2px;
    }
    .edit-address-card-desc {
        color: #566280;
        font-size: 1.06rem;
        margin-bottom: 0;
        line-height: 1.45;
        letter-spacing: 0.01em;
        font-weight: 400;
    }
    @media (max-width:500px) {
        .wcp-edit-address-card-modern {flex-direction:column;gap:12px;padding: 16px 12px;}
        .edit-address-card-icon {width:44px;height:44px;font-size:1.4rem;}
        .edit-address-card-title {font-size:1.04rem;}
        .edit-address-card-desc {font-size:0.98rem;}
    }
    </style>
    <?php
}

}
?>