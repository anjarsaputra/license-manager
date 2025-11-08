<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
  $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
  $product_id = $cart_item['product_id'];
  if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 ) :
?>
  <div class="lm-product-card" style="display:flex;align-items:center;gap:16px;margin-bottom:22px;padding:18px 18px 12px 18px;background:#fff;border-radius:13px;box-shadow:0 2px 20px #c9dde252;">
    <div class="lm-product-img" style="flex:0 0 72px;">
      <?php echo $_product->get_image('woocommerce_thumbnail', array('style'=>'border-radius:8px;box-shadow:0 1px 10px #ddd8;')); ?>
    </div>
    <div style="flex:1;">
      <div style="font-weight:600;font-size:1.08em;"><?php echo $_product->get_name(); ?></div>
      <div style="color:#98a1bb;font-size:.97em;"><?php echo $_product->get_short_description(); ?></div>
      <form class="cart-form" method="post" style="display:flex;align-items:center;gap:13px;margin-top:7px;">
        <input type="number" min="1" max="<?php echo esc_attr( $_product->get_max_purchase_quantity() ); ?>"
         value="<?php echo esc_attr( $cart_item['quantity'] ); ?>" name="cart[<?php echo $cart_item_key; ?>][qty]" style="width:46px;border-radius:7px;border:1px #e3eaf4 solid;">
        <button type="submit" class="button" name="update_cart" value="Update">Save</button>
        <a href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>"
          style="color:#e74c3c;text-decoration:none;"><i class="fas fa-trash"></i> Remove</a>
      </form>
      <div style="font-size:1em;margin-top:8px;">Price: <b><?php echo wc_price($_product->get_price()); ?></b></div>
    </div>
  </div>
<?php endif; endforeach; ?>
