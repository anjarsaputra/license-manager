<?php
// Hook: order selesai/completed di WooCommerce
add_action('woocommerce_order_status_completed', 'alm_create_license_after_order');

function alm_create_license_after_order($order_id) {
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        // 1. Cek apakah produk ini adalah produk lisensi (bisa via kategori, tag, atau custom field)
        if ( ! alm_is_license_product($product) ) continue;

        // 2. Ambil parameter dari VARIASI jika ada, jika tidak fallback ke produk utama
        $activation_limit = 1;
        if ($item->get_variation_id()) {
            $activation_limit = get_post_meta($item->get_variation_id(), '_activation_limit', true);
        }
        if (!$activation_limit) {
            $activation_limit = get_post_meta($product->get_id(), '_activation_limit', true);
        }
        if (!$activation_limit) $activation_limit = 1;

        // Semua masa aktif 1 tahun (hardcode, tidak perlu ambil dari DB)
        $expiry_date = date('Y-m-d', strtotime('+1 year'));

        // 3. Data lain
        $license_key = alm_generate_license_key();
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name();
        $product_name = $product->get_name();
        $status = 'active';

        // 4. Simpan lisensi ke database plugin kamu
        alm_save_license([
            'product_name'      => $product_name,
            'license_key'       => $license_key,
            'customer_email'    => $customer_email,
            'activation_limit'  => $activation_limit,
            'expiry_date'       => $expiry_date,
            'status'            => $status,
            'order_id'          => $order_id
        ]);

        // 5. Kirim email lisensi (jika perlu)
        alm_send_license_email($customer_email, $license_key, $expiry_date, $product_name, $activation_limit);
    }
}
// Contoh fungsi cek produk lisensi
function alm_is_license_product($product) {
    // Contoh: cek tag "lisensi"
    return has_term('lisensi', 'product_tag', $product->get_id());
}

// ** Contoh fungsi generate, simpan, & kirim email lisensi **
// Silakan sesuaikan dengan plugin lisensi kamu!
function alm_generate_license_key() {
    return strtoupper(uniqid('LIC-'));
}
function alm_save_license($args = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'your_license_table';
    $wpdb->insert($table, [
        'product_name'      => $args['product_name'],
        'license_key'       => $args['license_key'],
        'customer_email'    => $args['customer_email'],
        'activation_limit'  => $args['activation_limit'],
        'expiry_date'       => $args['expiry_date'],
        'status'            => $args['status'],
        'order_id'          => $args['order_id'],
        'created_at'        => current_time('mysql')
    ]);
}
function alm_send_license_email($to, $license_key, $expiry_date, $product_name, $activation_limit) {
    $subject = "Lisensi Produk {$product_name} Anda";
    $body = "<strong>Kode Lisensi:</strong> {$license_key}<br>
            <strong>Berlaku sampai:</strong> ".($expiry_date ?: 'Selamanya')."<br>
            <strong>Activation Limit:</strong> {$activation_limit} website<br>
            <strong>Terima kasih telah membeli produk kami!</strong>";
    wp_mail($to, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
}

// Tambahkan field di halaman produk (tab General)
add_action('woocommerce_product_options_general_product_data', function() {
    error_log('Field custom WooCommerce lisensi dijalankan!');
    woocommerce_wp_text_input([
        'id' => '_activation_limit',
        'label' => __('Activation Limit', 'tlm'),
        'type' => 'number',
        'desc_tip' => true,
        'description' => __('Berapa jumlah website yang bisa diaktivasi dengan lisensi ini.', 'tlm'),
        'custom_attributes' => [
            'min' => '1',
            'step' => '1'
        ]
    ]);add_action('woocommerce_order_status_completed', 'alm_create_license_after_order');

    woocommerce_wp_text_input([
        'id' => '_license_duration',
        'label' => __('License Duration', 'tlm'),
        'type' => 'text',
        'desc_tip' => true,
        'description' => __('Isi "1y" untuk 1 tahun, "lifetime" untuk selamanya.', 'tlm'),
    ]);
});

// Simpan field custom saat produk diupdate
add_action('woocommerce_process_product_meta', function($post_id) {
    if (isset($_POST['_activation_limit'])) {
        update_post_meta($post_id, '_activation_limit', sanitize_text_field($_POST['_activation_limit']));
    }
    if (isset($_POST['_license_duration'])) {
        update_post_meta($post_id, '_license_duration', sanitize_text_field($_POST['_license_duration']));
    }
});


// Tampilkan field di tiap variasi produk
add_action('woocommerce_variation_options_pricing', function($loop, $variation_data, $variation) {
    woocommerce_wp_text_input([
        'id' => "activation_limit_var[$loop]",
        'label' => __('Activation Limit', 'tlm'),
        'desc_tip' => true,
        'description' => __('Jumlah website yang bisa diaktivasi untuk variasi ini.', 'tlm'),
        'value' => get_post_meta($variation->ID, '_activation_limit', true)
    ]);
}, 10, 3);

// Simpan nilai activation_limit per variasi
add_action('woocommerce_save_product_variation', function($variation_id, $i) {
    if (isset($_POST['activation_limit_var'][$i])) {
        update_post_meta($variation_id, '_activation_limit', sanitize_text_field($_POST['activation_limit_var'][$i]));
    }
}, 10, 2);

