<div class="lm-cart-wrap" style="display:flex;flex-wrap:wrap;gap:2.5rem;max-width:1120px;margin:0 auto;padding:32px 10px;">
  <!-- Keranjang kiri -->
  <div class="lm-cart-left" style="flex:2;min-width:320px;">
    <h2 style="font-size:2rem;font-weight:700;">Your Shopping Cart</h2>
    <p style="color:#6d759f;">Make sure everything looks right before checkout.</p>
    <?php
      // FINAL, PASTIKAN LOADER & PATH AMAN
      include __DIR__ . '/cart-items.php';
    ?>
    <div style="text-align:right; margin-top:18px;">
      <form method="post">
        <button type="submit" class="button" name="update_cart" value="Update cart">Save cart</button>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>?empty-cart" class="button" style="margin-left:12px;">Clear</a>
      </form>
    </div>
  </div>
  <!-- Summary kanan -->
  <div class="lm-cart-right" style="flex:1;min-width:300px;max-width:370px;background:#fafbfc;border-radius:16px;box-shadow:0 4px 32px #b7d1ec33;padding:28px 24px 20px 24px;">
    <h3 style="font-weight:700;font-size:1.2rem;margin-bottom:20px;">Order summary</h3>
    <div style="margin-bottom:14px;">
      <div style="margin-bottom:8px;">Items: <b><?php echo WC()->cart->get_cart_contents_count(); ?></b></div>
      <div>Subtotal: <b><?php echo wc_price( WC()->cart->get_subtotal() ); ?></b></div>
      <div>Shipping: <b><?php echo WC()->cart->get_cart_shipping_total(); ?></b></div>
      <div>Tax: <b><?php echo wc_price( WC()->cart->get_taxes_total() ); ?></b></div>
      <?php if ( WC()->cart->get_coupon_discount_totals() ) : ?>
      <div>Coupon: <b style="color:#11c492;">
        -<?php echo wc_price( WC()->cart->get_coupon_discount_totals() ); ?></b>
      </div>
      <?php endif; ?>
    </div>
    <div style="margin-bottom:22px;font-size:1.2em;">
      <b>Total: <?php echo wc_price( WC()->cart->get_total('edit') ); ?></b>
    </div>
    <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button" style="width:100%;border-radius:9px;background:#212529;font-size:1.17em;font-weight:bold;">Checkout</a>
  </div>
</div>
