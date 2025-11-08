<style>
.lm-cart-item-list { margin-top: 3px;}
.lm-product-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 2px 13px #d9e6fd34;
  display: grid;
  grid-template-columns: 86px 1fr 110px;
  grid-template-rows: 42px 38px 26px;
  align-items: center;
  padding: 18px 28px 16px 18px;
  margin-bottom: 18px;
  position: relative;
  column-gap: 16px;
  row-gap: 2px;
}
.lm-product-img { grid-row: 1/4; grid-column: 1/2;}
.lm-product-img img {
  border-radius: 10px !important;
  box-shadow: 0 2px 9px #dbeaf244;
  width: 72px;
  height: 72px;
  object-fit: cover;
  background: #f6f9ff;
}
.lm-cart-info-main {
  grid-row: 1/2; grid-column: 2/3;
  display: flex; flex-direction: column; justify-content: center;
}
.lm-cart-product-name {
  font-weight: 700;
  color: #202d54;
  font-size: 1.11rem;
}
.lm-cart-product-desc {
  color: #9ba3b5;
  font-size: 0.96em;
  margin-top: 3px;
  font-weight: 500;
}
.lm-cart-row-actions {
  grid-row: 2/3; grid-column: 2/4;
  display: flex;
  align-items: center;
  gap: 22px;
}
.lm-qty-control-group {
  display: flex;
  align-items: center;
  background: #f7f9fd;
  border-radius: 7px;
  padding: 2px 4px;
  gap: 0;
}
.lm-qty-btn {
  background: #edf6fe;
  color: #2b4acb;
  border: none;
  border-radius: 7px;
  font-weight: 700;
  font-size: 1.11em;
  width: 34px;
  height: 34px;
  cursor: pointer;
  transition: .13s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.lm-qty-input-center {
  width: 38px;
  border: none;
  background: transparent;
  text-align: center;
  color: #222c42;
  font-size: 1.11em;
  font-weight: bold;
  outline: none;
  margin: 0 2px;
}
.lm-cart-icon-link, .lm-cart-bookmark {
  color: #929bb4;
  text-decoration: none;
  font-size: 1.05em;
  margin-right: 10px;
  margin-left: 3px;
}
.lm-cart-icon-link:hover { color: #e74c3c; }
.lm-cart-bookmark:hover { color: #4764ea; }
.lm-cart-product-price-single {
  grid-row: 1/2; grid-column: 3/4;
  font-weight: 700;
  font-size: 1.08em;
  color: #272948;
  text-align: right;
}
.lm-cart-product-price-total {
  grid-row: 3/4; grid-column: 3/4;
  font-weight: 700;
  font-size: 1.12em;
  color: #212549;
  text-align: right;
  margin-top: 3px;
}
@media (max-width: 700px) {
  .lm-product-card {
    grid-template-columns: 72px 1fr;
    grid-template-rows: 38px 33px 26px 31px;
    row-gap: 6px;
    column-gap: 10px;
    padding: 10px 6px 11px 8px;
  }
  .lm-cart-product-price-single, .lm-cart-product-price-total {
    grid-column: 2/3;
    text-align: left;
    margin-top: 7px;
  }
}
</style>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST') { echo '<pre>'; print_r($_POST); echo '</pre>'; } ?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  echo '<pre>'; print_r($_POST); echo '</pre>';
  echo '<pre>'; print_r(WC()->cart->get_cart()); echo '</pre>';
}
?>

<form method="post" action="" class="lm-cart-item-list" style="margin-bottom:0;">
  <input type="hidden" name="update_cart" value="Update cart">
<?php
if ( function_exists('WC') && WC()->cart && function_exists('wc_get_cart_remove_url') ) :
  foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
    $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
    $product_id = $cart_item['product_id'];
    if ($_product && $_product->exists() && $cart_item['quantity'] > 0) :
      $qty = $cart_item['quantity'];
?>
  <div class="lm-product-card">
    <div class="lm-product-img">
      <?php echo $_product->get_image('woocommerce_thumbnail'); ?>
    </div>
    <div class="lm-cart-info-main">
      <div class="lm-cart-product-name">
        <?php echo $_product->get_name(); ?>
        <?php if ($qty > 1) echo " - $qty"; ?>
      </div>
      <div class="lm-cart-product-desc">
        <?php echo $_product->get_short_description(); ?>
      </div>
    </div>
    <div class="lm-cart-product-price-single">
      <?php echo wc_price($_product->get_price()); ?>
    </div>
    <div class="lm-cart-row-actions">
      <div class="lm-qty-control-group">
        <button class="lm-qty-btn" type="button" data-key="<?php echo $cart_item_key ?>" data-op="down" tabindex="-1">−</button>
        <input class="lm-qty-input-center" type="number" min="1"
          value="<?php echo esc_attr($qty); ?>"
          name="cart[<?php echo $cart_item_key; ?>][qty]">
        <button class="lm-qty-btn" type="button" data-key="<?php echo $cart_item_key ?>" data-op="up" tabindex="-1">+</button>
      </div>
      <a class="lm-cart-icon-link" href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" title="Remove"><i class="fas fa-trash"></i> Remove</a>
      <a class="lm-cart-bookmark" href="#" title="Bookmark"><i class="far fa-bookmark"></i></a>
    </div>
    <div class="lm-cart-product-price-total">
      <?php echo wc_price($_product->get_price() * $qty); ?>
    </div>
  </div>
<?php
    endif;
  endforeach;
else:
  echo '<div style="color:#aaa;">Cart unavailable or empty.</div>';
endif;
?>
  <button type="submit" class="lm-cart-btn" style="margin-top:24px;min-width:110px;font-size:1.04em;border-radius:9px;">Save Cart</button>
</form>

<script>
document.querySelectorAll('.lm-qty-btn').forEach(function(btn){
  btn.addEventListener('click', function(e){
    e.preventDefault();
    var key = btn.getAttribute('data-key');
    var inp = document.querySelector('input[name="cart['+key+'][qty]"]');
    var form = btn.closest('form');
    if(inp && form){
      var val = parseInt(inp.value) || 1;
      if (btn.getAttribute('data-op')==="up") inp.value = val+1;
      if (btn.getAttribute('data-op')==="down" && val>1) inp.value = val-1;
      setTimeout(()=>{form.submit()},10);
    }
  });
});
</script>
