<style>
.lm-cart-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 46px;
  align-items: flex-start;
  justify-content: flex-start;
  max-width: 1170px;
  margin: 3vw auto;
  background: transparent;
  box-shadow: none;
}
.lm-cart-main-card {
  flex: 2.5;
  min-width: 380px;
  background: #fff; /* Card sendiri */
  border-radius: 17px;
  box-shadow: 0 6px 38px #dbeaf275;
  padding: 34px 34px 26px 34px;
  margin-right: 0;
}

.lm-cart-right {
  flex: 1.2;
  min-width: 330px;
  max-width: 370px;
  background: #fafbfc; /* Card sendiri */
  border-radius: 16px;
  box-shadow: 0 4px 32px #b7d1ec33;
  padding: 28px 24px 20px 24px;
}
@media (max-width: 1020px){
  .lm-cart-wrap { flex-direction: column; gap:18px;}
  .lm-cart-main-card, .lm-cart-right { max-width: 100%; width:100%; margin:0 !important; }
  .lm-cart-main-card { margin-bottom:28px; }
}
</style>

<div class="lm-cart-wrap">
  <div class="lm-cart-main-card">
    <h2 style="font-size:2rem;font-weight:700;">Your Shopping Cart</h2>
    <p style="color:#6d759f;">Make sure everything looks right before checkout.</p>
    <?php
    if ( file_exists(__DIR__ . '/cart-items.php') ) {
      include __DIR__ . '/cart-items.php';
    } else {
      echo '<div style="color:red;">Cart items file not found!</div>';
    }
    ?>
    <!-- Tombol Clear di luar form cart-list agar tidak bentrok submit! -->
    <div style="text-align:right; margin-top:18px;">
      <?php if ( function_exists('WC') && WC()->cart && method_exists(WC()->cart, 'get_cart_contents_count')): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="lm_clear_cart" value="yes">
          <button type="submit" class="lm-cart-btn" style="background:#232626;">Clear</button>
        </form>
      <?php else: ?>
        <div style="color:#aaa;">Cart unavailable</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CARD KANAN: Order Summary -->
  <div class="lm-cart-right">
    <h3 style="font-weight:700;font-size:1.2rem;margin-bottom:20px;">Order summary</h3>
    <div style="margin-bottom:14px;">
      <?php if (function_exists('WC') && WC()->cart): ?>
        <div style="margin-bottom:8px;">Items: <b><?php echo WC()->cart->get_cart_contents_count(); ?></b></div>
        <div>Subtotal: <b><?php echo wc_price( WC()->cart->get_subtotal() ); ?></b></div>
        <div>Shipping: <b><?php echo WC()->cart->get_cart_shipping_total(); ?></b></div>
        <div>Tax: <b><?php echo wc_price( WC()->cart->get_taxes_total() ); ?></b></div>
        <?php if ( WC()->cart->get_coupon_discount_totals() ) : ?>
        <div>Coupon: <b style="color:#11c492;">
          -<?php echo wc_price( WC()->cart->get_coupon_discount_totals() ); ?></b>
        </div>
        <?php endif; ?>
        <div style="margin-bottom:22px;font-size:1.2em;">
          <b>Total: <?php echo wc_price( WC()->cart->get_total( 'edit' ) ); ?></b>
        </div>
        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="lm-cart-btn" style="width:100%;border-radius:9px;background:#212529;font-size:1.17em;font-weight:bold;">Checkout</a>
      <?php else: ?>
        <div style="color:#aaa;">Cart unavailable or not initialized.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
