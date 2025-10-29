<?php
/**
 * WooCommerce Integration for License Manager
 * Version: 2.2 - Production Ready (Final - Email Template Fixed)
 * Last Updated: 2025-10-24
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

        // ✅ DEBUG LOG (Temporary - remove after testing)
        error_log('===== LICENSE DEBUG START =====');
        error_log('Order ID: ' . $order_id);
        error_log('Product ID: ' . $product->get_id());
        error_log('Product Name: ' . $product->get_name());
        
        // 2. Get activation limit from variation or product
        $activation_limit = 1;
        $variation_id = $item->get_variation_id();
        
        error_log('Variation ID: ' . ($variation_id ? $variation_id : 'NULL (Simple Product)'));
        
        if ($variation_id) {
            $activation_limit = get_post_meta($variation_id, '_activation_limit', true);
            error_log('Activation Limit from Variation: ' . ($activation_limit ? $activation_limit : 'NOT SET'));
        }
        
        if (empty($activation_limit)) {
            $activation_limit = get_post_meta($product->get_id(), '_activation_limit', true);
            error_log('Activation Limit from Product: ' . ($activation_limit ? $activation_limit : 'NOT SET'));
        }
        
        // Validate & sanitize activation limit
        $activation_limit = absint($activation_limit);
        if ($activation_limit < 1) {
            $activation_limit = 1;
        }
        
        error_log('FINAL Activation Limit: ' . $activation_limit);

        // 3. Calculate expiry date
        $expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));

        // 4. Generate secure license key
        $license_generator = ALM_License_Generator::get_instance();
        $license_key = $license_generator->generate_license_key();
        
        if (!$license_key) {
            alm_error_log('Failed to generate license key for order: ' . $order_id);
            error_log('===== LICENSE DEBUG END (FAILED - No Key) =====');
            continue;
        }
        
        error_log('Generated License Key: ' . $license_key);

        // 5. Sanitize customer data
        $customer_email = sanitize_email($order->get_billing_email());
        $customer_name = sanitize_text_field($order->get_billing_first_name());
        $product_name = sanitize_text_field($product->get_name());

        // Validate email
        if (!is_email($customer_email)) {
            alm_error_log('Invalid email for order: ' . $order_id);
            error_log('===== LICENSE DEBUG END (FAILED - Invalid Email) =====');
            continue;
        }

        // ✅ 6. Save license to database (WITH transfer_limit)
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
                'transfer_limit'   => 1,  // ✅ FIXED: Always 1 transfer per year
                'transfer_count'   => 0,  // ✅ FIXED: Start at 0
                'created_at'       => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s']
        );

        if ($result === false) {
            alm_error_log('Failed to save license to database: ' . $wpdb->last_error);
            error_log('Database Error: ' . $wpdb->last_error);
            error_log('===== LICENSE DEBUG END (FAILED - DB Insert) =====');
            continue;
        }
        
        error_log('License saved to database successfully!');
        error_log('===== LICENSE DEBUG END (SUCCESS) =====');

        // 7. Add order note
        $order->add_order_note(
            sprintf(
                'License created: %s | Activation Limit: %d | Transfer: 1/year | Expires: %s',
                $license_key,
                $activation_limit,
                date_i18n(get_option('date_format'), strtotime($expiry_date))
            )
        );

        // 8. Log activity
        alm_insert_log(
            $license_key,
            'license_created',
            sprintf('License created from WooCommerce order #%d for %s', $order_id, $customer_email),
            ''
        );

        // 9. Send email to customer
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
    
    // ✅ FORCE CHECK - Bypass tag/category
    // Product ID 72 = Your Mediman Theme product
    $product_id = $product->get_id();
    
    // Get parent ID if this is a variation
    if ($product->is_type('variation')) {
        $parent_id = $product->get_parent_id();
        if (in_array($parent_id, [72])) {
            return true;
        }
    }
    
    // Check direct product ID
    if (in_array($product_id, [72])) {
        return true;
    }
    
    // Method 1: Check product tag "lisensi"
    if (has_term('lisensi', 'product_tag', $product_id)) {
        return true;
    }
    
    // Method 2: Check custom field
    $is_license = get_post_meta($product_id, '_is_license_product', true);
    if ($is_license === 'yes') {
        return true;
    }
    
    // Method 3: Check product category
    if (has_term('license', 'product_cat', $product_id)) {
        return true;
    }
    
    return false;
}


/**
 * =====================================================
 * SEND LICENSE EMAIL - FIXED & PRODUCTION READY
 * =====================================================
 */
function alm_send_license_email($to, $license_key, $expiry_date, $product_name, $activation_limit, $order_id) {
    if (!is_email($to)) {
        alm_error_log('Invalid email for license notification: ' . $to);
        return false;
    }
    
    $custom_logo_url = 'https://aratheme.id/wp-content/uploads/2025/10/logo-aratheme-warna-with-text.png';
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $logo_url = $custom_logo_url;
    
    if (empty($logo_url)) {
        if (has_custom_logo()) {
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }
        if (empty($logo_url) && has_site_icon()) {
            $logo_url = get_site_icon_url(200);
        }
    }
    
    $subject = sprintf('Lisensi %s - Kode Aktivasi Anda', $product_name);
    
    $body = sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#ffffff; font-family:\'SF Pro Display\',-apple-system,sans-serif;">
    
    <table role="presentation" width="100%%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding:60px 20px;">
                
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0">
                    
                    <!-- LOGO/HEADER -->
                    <tr>
                        <td style="padding:0 0 40px; text-align:center;">
                            %s
                            <h1 style="margin:0; font-size:28px; font-weight:300; color:#000000; letter-spacing:-1px;">
                                %s
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- GREETING -->
                    <tr>
                        <td style="padding:0 0 30px;">
                            <p style="margin:0; font-size:18px; color:#000000; line-height:1.6;">
                                Terima kasih telah membeli<br>
                                <strong style="font-weight:600;">%s</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- LICENSE KEY with VISUAL BUTTON -->
                    <tr>
                        <td style="padding:0 0 40px;">
                            <div style="background:linear-gradient(135deg, #f0f9ff 0%%, #e0f2fe 100%%); padding:32px; border-radius:8px; border-left:4px solid #0ea5e9;">
                                <p style="margin:0 0 12px; font-size:11px; color:#0369a1; text-transform:uppercase; letter-spacing:1px; font-weight:600;">
                                    ✓ KODE LISENSI ANDA
                                </p>
                                
                                <table width="100%%" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="padding:0;">
                                            <div style="background:#ffffff; border:2px solid #0ea5e9; border-radius:6px; padding:16px;">
                                                <p style="margin:0; font-size:18px; font-family:monospace; color:#0c4a6e; letter-spacing:2px; word-break:break-all; font-weight:600; text-align:center; user-select:all; -webkit-user-select:all; -moz-user-select:all; -ms-user-select:all;">
                                                    %s
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                </table>
                                
                                <p style="margin:16px 0 0; font-size:12px; color:#0369a1; text-align:center; line-height:1.6;">
                                    <strong>Cara Copy:</strong><br>
                                    1. Klik/tap kode di atas untuk select<br>
                                    2. Tekan <strong>Ctrl+C</strong> (Windows) atau <strong>Cmd+C</strong> (Mac)<br>
                                    3. Paste di form aktivasi tema Anda
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- INFO -->
                    <tr>
                        <td style="padding:0 0 40px;">
                            <table role="presentation" width="100%%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="40%%" style="padding:12px 0; border-bottom:1px solid #eeeeee; color:#666666; font-size:14px;">Aktivasi</td>
                                    <td width="60%%" style="padding:12px 0; border-bottom:1px solid #eeeeee; color:#000000; font-size:14px; font-weight:500; text-align:right;">%d website</td>
                                </tr>
                                <tr>
                                    <td width="40%%" style="padding:12px 0; border-bottom:1px solid #eeeeee; color:#666666; font-size:14px;">Berlaku Hingga</td>
                                    <td width="60%%" style="padding:12px 0; border-bottom:1px solid #eeeeee; color:#000000; font-size:14px; font-weight:500; text-align:right;">%s</td>
                                </tr>
                                <tr>
                                    <td width="40%%" style="padding:12px 0; color:#666666; font-size:14px;">Order ID</td>
                                    <td width="60%%" style="padding:12px 0; color:#000000; font-size:14px; font-weight:500; text-align:right;">#%d</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- STEPS -->
                    <tr>
                        <td style="padding:0 0 40px;">
                            <p style="margin:0 0 20px; font-size:16px; color:#000000; font-weight:500;">
                                Cara Aktivasi:
                            </p>
                            <ol style="margin:0; padding-left:20px; color:#666666; font-size:15px; line-height:2;">
                                <li>Login ke WordPress admin website Anda</li>
                                <li>Buka <strong>Menu Mediman → Tab Lisensi</strong></li>
                                <li>Copy kode lisensi di atas (klik kode, lalu Ctrl+C / Cmd+C)</li>
                                <li>Paste di form aktivasi</li>
                                <li>Klik <strong>"Aktivasi"</strong></li>
                            </ol>
                        </td>
                    </tr>
                    
                    <!-- SUPPORT INFO -->
                    <tr>
                        <td style="padding:30px 0 0; border-top:1px solid #eeeeee;">
                            <table width="100%%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="text-align:center; padding:0 0 10px;">
                                        <p style="margin:0; font-size:14px; color:#666666;">
                                            Butuh bantuan?
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align:center;">
                                        <p style="margin:0;">
                                            <a href="mailto:support@aratheme.id" style="color:#0ea5e9; text-decoration:none; font-weight:500;">
                                                support@aratheme.id
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- FOOTER -->
                    <tr>
                        <td style="padding:40px 0 0; border-top:1px solid #eeeeee; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#999999;">
                                %s • %d
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
    ',
        !empty($logo_url) ? sprintf('<img src="%s" alt="%s" style="max-width:250px; height:auto; margin:0 0 20px;">', esc_url($logo_url), esc_attr($site_name)) : '',
        esc_html($site_name),
        esc_html($product_name),
        esc_html($license_key),
        $activation_limit,
        date_i18n('d F Y', strtotime($expiry_date)),
        $order_id,
        esc_html($site_name),
        date('Y')
    );
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
    ];
    
    $sent = wp_mail($to, $subject, $body, $headers);
    
    if ($sent) {
        alm_insert_log($license_key, 'email_sent', sprintf('License email sent to %s', $to), '');
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
    
    woocommerce_wp_checkbox([
        'id'          => '_is_license_product',
        'label'       => __('License Product', 'alm'),
        'description' => __('Centang jika produk ini adalah lisensi tema/plugin', 'alm')
    ]);
    
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
    if (isset($_POST['_activation_limit'])) {
        $activation_limit = absint($_POST['_activation_limit']);
        if ($activation_limit < 1) {
            $activation_limit = 1;
        }
        update_post_meta($post_id, '_activation_limit', $activation_limit);
    }
    
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
