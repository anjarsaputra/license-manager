<?php
/**
 * WooCommerce Integration for License Manager
 * Version: 2.0 - Security Enhanced
 * Last Updated: 2025-10-02
 * 
 * @package License Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =====================================================
 * AUTO-CREATE LICENSE AFTER ORDER COMPLETED
 * =====================================================
 */
add_action('woocommerce_order_status_completed', 'alm_create_license_after_order');

function alm_create_license_after_order($order_id) {
    // Validate order ID
    if (empty($order_id) || !is_numeric($order_id)) {
        alm_error_log('Invalid order ID: ' . $order_id);
        return;
    }
    
    $order = wc_get_order($order_id);
    
    // Validate order object
    if (!$order || !is_a($order, 'WC_Order')) {
        alm_error_log('Order not found or invalid: ' . $order_id);
        return;
    }
    
    // Check if license already created for this order (prevent duplicate)
    global $wpdb;
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}alm_licenses WHERE order_id = %d",
        $order_id
    ));
    
    if ($existing > 0) {
        alm_error_log('License already created for order: ' . $order_id);
        return;
    }

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        
        // Validate product
        if (!$product || !is_a($product, 'WC_Product')) {
            continue;
        }

        // 1. Check if this is a license product
        if (!alm_is_license_product($product)) {
            continue;
        }

        // 2. Get activation limit from variation or product
        $activation_limit = 1;
        
        if ($item->get_variation_id()) {
            $activation_limit = get_post_meta($item->get_variation_id(), '_activation_limit', true);
        }
        
        if (empty($activation_limit)) {
            $activation_limit = get_post_meta($product->get_id(), '_activation_limit', true);
        }
        
        // Validate & sanitize activation limit
        $activation_limit = absint($activation_limit);
        if ($activation_limit < 1) {
            $activation_limit = 1;
        }

        // 3. Calculate expiry date
        $expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));

        // 4. Generate secure license key (use existing secure function)
        $license_generator = ALM_License_Generator::get_instance();
        $license_key = $license_generator->generate_license_key();
        
        if (!$license_key) {
            alm_error_log('Failed to generate license key for order: ' . $order_id);
            continue;
        }

        // 5. Sanitize customer data
        $customer_email = sanitize_email($order->get_billing_email());
        $customer_name = sanitize_text_field($order->get_billing_first_name());
        $product_name = sanitize_text_field($product->get_name());

        // Validate email
        if (!is_email($customer_email)) {
            alm_error_log('Invalid email for order: ' . $order_id);
            continue;
        }

        // 6. Save license to database (use prepared statements)
        $result = $wpdb->insert(
            $wpdb->prefix . 'alm_licenses',
            [
                'license_key'      => $license_key,
                'product_name'     => $product_name,
                'customer_email'   => $customer_email,
                'activation_limit' => $activation_limit,
                'activations'      => 0,
                'expires'          => $expiry_date,
                'status'           => 'active',
                'order_id'         => $order_id,
                'created_at'       => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            alm_error_log('Failed to save license to database: ' . $wpdb->last_error);
            continue;
        }

        // 7. Log activity
        alm_insert_log(
            $license_key,
            'license_created',
            sprintf('License created from WooCommerce order #%d for %s', $order_id, $customer_email),
            ''
        );

        // 8. Send email to customer
        alm_send_license_email($customer_email, $license_key, $expiry_date, $product_name, $activation_limit, $order_id);
    }
}

/**
 * =====================================================
 * CHECK IF PRODUCT IS LICENSE PRODUCT
 * =====================================================
 */
function alm_is_license_product($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }
    
    // Method 1: Check product tag "lisensi"
    if (has_term('lisensi', 'product_tag', $product->get_id())) {
        return true;
    }
    
    // Method 2: Check custom field
    $is_license = get_post_meta($product->get_id(), '_is_license_product', true);
    if ($is_license === 'yes') {
        return true;
    }
    
    // Method 3: Check product category
    if (has_term('license', 'product_cat', $product->get_id())) {
        return true;
    }
    
    return false;
}

/**
 * =====================================================
 * SEND LICENSE EMAIL TO CUSTOMER
 * =====================================================
 */
function alm_send_license_email($to, $license_key, $expiry_date, $product_name, $activation_limit, $order_id) {
    // Validate email
    if (!is_email($to)) {
        alm_error_log('Invalid email for license notification: ' . $to);
        return false;
    }
    
    // Email subject
    $subject = sprintf('[%s] Lisensi %s Anda', get_bloginfo('name'), $product_name);
    
    // Email body
    $body = sprintf('
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .license-box { background: white; padding: 15px; border: 2px solid #0073aa; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                code { background: #f0f0f0; padding: 5px 10px; border-radius: 3px; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Terima Kasih Atas Pembelian Anda!</h1>
                </div>
                <div class="content">
                    <p>Halo,</p>
                    <p>Terima kasih telah membeli <strong>%s</strong>.</p>
                    
                    <div class="license-box">
                        <h3>📋 Informasi Lisensi Anda:</h3>
                        <p><strong>Kode Lisensi:</strong><br><code>%s</code></p>
                        <p><strong>Produk:</strong> %s</p>
                        <p><strong>Batas Aktivasi:</strong> %d website</p>
                        <p><strong>Berlaku Hingga:</strong> %s</p>
                        <p><strong>Order ID:</strong> #%d</p>
                    </div>
                    
                    <h3>🔧 Cara Aktivasi:</h3>
                    <ol>
                        <li>Login ke WordPress admin website Anda</li>
                        <li>Buka menu <strong>Appearance → Aktivasi Lisensi</strong></li>
                        <li>Masukkan kode lisensi di atas</li>
                        <li>Klik "Aktivasi"</li>
                    </ol>
                    
                    <p><strong>⚠️ Penting:</strong> Simpan email ini dengan baik. Anda akan membutuhkan kode lisensi untuk aktivasi dan update tema.</p>
                </div>
                <div class="footer">
                    <p>Email ini dikirim otomatis dari %s</p>
                    <p>Jika ada pertanyaan, silakan hubungi support kami.</p>
                </div>
            </div>
        </body>
        </html>
    ',
        esc_html($product_name),
        esc_html($license_key),
        esc_html($product_name),
        $activation_limit,
        date_i18n(get_option('date_format'), strtotime($expiry_date)),
        $order_id,
        get_bloginfo('name')
    );
    
    // Email headers
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    ];
    
    // Send email
    $sent = wp_mail($to, $subject, $body, $headers);
    
    if ($sent) {
        alm_insert_log(
            $license_key,
            'email_sent',
            sprintf('License email sent to %s', $to),
            ''
        );
    } else {
        alm_error_log('Failed to send license email to: ' . $to);
    }
    
    return $sent;
}

/**
 * =====================================================
 * ADD CUSTOM FIELDS TO PRODUCT (GENERAL TAB)
 * =====================================================
 */
add_action('woocommerce_product_options_general_product_data', 'alm_add_license_product_fields');

function alm_add_license_product_fields() {
    global $post;
    
    echo '<div class="options_group">';
    
    // Checkbox: Is this a license product?
    woocommerce_wp_checkbox([
        'id'          => '_is_license_product',
        'label'       => __('License Product', 'alm'),
        'description' => __('Centang jika produk ini adalah lisensi tema/plugin', 'alm')
    ]);
    
    // Activation Limit
    woocommerce_wp_text_input([
        'id'                => '_activation_limit',
        'label'             => __('Activation Limit', 'alm'),
        'type'              => 'number',
        'desc_tip'          => true,
        'description'       => __('Jumlah website yang bisa diaktivasi dengan lisensi ini.', 'alm'),
        'custom_attributes' => [
            'min'  => '1',
            'step' => '1'
        ],
        'value'             => get_post_meta($post->ID, '_activation_limit', true) ?: 1
    ]);
    
    echo '</div>';
}

/**
 * =====================================================
 * SAVE CUSTOM FIELDS
 * =====================================================
 */
add_action('woocommerce_process_product_meta', 'alm_save_license_product_fields');

function alm_save_license_product_fields($post_id) {
    // Validate & sanitize activation limit
    if (isset($_POST['_activation_limit'])) {
        $activation_limit = absint($_POST['_activation_limit']);
        if ($activation_limit < 1) {
            $activation_limit = 1;
        }
        update_post_meta($post_id, '_activation_limit', $activation_limit);
    }
    
    // Save checkbox
    $is_license = isset($_POST['_is_license_product']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_license_product', $is_license);
}

/**
 * =====================================================
 * ADD CUSTOM FIELDS TO VARIATIONS
 * =====================================================
 */
add_action('woocommerce_variation_options_pricing', 'alm_add_variation_license_fields', 10, 3);

function alm_add_variation_license_fields($loop, $variation_data, $variation) {
    woocommerce_wp_text_input([
        'id'                => "activation_limit_var[{$loop}]",
        'name'              => "activation_limit_var[{$loop}]",
        'label'             => __('Activation Limit', 'alm'),
        'desc_tip'          => true,
        'description'       => __('Jumlah website untuk variasi ini.', 'alm'),
        'value'             => get_post_meta($variation->ID, '_activation_limit', true) ?: 1,
        'type'              => 'number',
        'custom_attributes' => [
            'min'  => '1',
            'step' => '1'
        ]
    ]);
}

/**
 * =====================================================
 * SAVE VARIATION FIELDS
 * =====================================================
 */
add_action('woocommerce_save_product_variation', 'alm_save_variation_license_fields', 10, 2);

function alm_save_variation_license_fields($variation_id, $i) {
    if (isset($_POST['activation_limit_var'][$i])) {
        $activation_limit = absint($_POST['activation_limit_var'][$i]);
        if ($activation_limit < 1) {
            $activation_limit = 1;
        }
        update_post_meta($variation_id, '_activation_limit', $activation_limit);
    }
}