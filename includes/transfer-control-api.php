<?php
if (!defined('ABSPATH')) exit;





add_action('rest_api_init', function() {
    register_rest_route('alm/v1', '/deactivate-controlled', [
        'methods' => 'POST',
        'callback' => 'alm_api_deactivate_with_transfer_control',
        'permission_callback' => '__return_true'
    ]);
});

// REGISTER ROUTE UNTUK API TRANSFER SLOT
add_action('rest_api_init', function() {
    register_rest_route('alm/v1', '/transfer-slot', [
        'methods' => 'POST',
        'callback' => 'alm_rest_transfer_slot',
        'permission_callback' => '__return_true'
    ]);
}); 

function alm_api_deactivate_with_transfer_control(WP_REST_Request $request) {
    global $wpdb;

    $license_key = sanitize_text_field($request->get_param('license_key'));
    $domain = sanitize_text_field($request->get_param('domain'));

    // --- Signature Validasi ---
    $webhook_secret = get_option('mediman_webhook_secret', '');
    $received_signature = $request->get_header('x-alm-signature') ?: $request->get_param('signature');
    $payload_for_signature = [
        'license_key' => $license_key,
        'domain' => $domain,
    ];
    $expected_signature = hash_hmac('sha256', json_encode($payload_for_signature, JSON_UNESCAPED_SLASHES), $webhook_secret);

    if (!$received_signature || !hash_equals($expected_signature, $received_signature)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Forbidden: Invalid signature'
        ], 403);
    }

    if (empty($license_key) || empty($domain)) {
        return new WP_REST_Response([
            'success' => false, 'message' => 'Missing required parameters'
        ], 400);
    }
    


    if (empty($license_key) || empty($domain)) {
        return new WP_REST_Response([
            'success' => false, 'message' => 'Missing required parameters'
        ], 400);
    }

    $transfer_control = ALM_Transfer_Control::get_instance();
    $eligibility = $transfer_control->check_transfer_eligibility($license_key, $domain);
    if (is_wp_error($eligibility)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $eligibility->get_error_message(),
            'error_code' => $eligibility->get_error_code(),
            'data' => $eligibility->get_error_data()
        ], 403);
    }

    $license_table = $wpdb->prefix . 'alm_licenses';
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $license_table WHERE license_key = %s",
        $license_key
    ));
    if (!$license) {
        return new WP_REST_Response([
            'success' => false, 'message' => 'License not found'
        ], 404);
    }

    $activation_table = $wpdb->prefix . 'alm_license_activations';
    $activation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $activation_table WHERE license_id = %d AND site_url = %s",
        $license->id, $domain
    ));
    if (!$activation) {
        return new WP_REST_Response([
            'success' => false, 'message' => 'Domain not found in activations'
        ], 404);
    }

    // Remove activation
    $deleted = $wpdb->delete(
        $activation_table, ['id' => $activation->id], ['%d']
    );
    if ($deleted === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to deactivate: ' . $wpdb->last_error
        ], 500);
    }

    // Update count slot di master (optional, kalau ada field activations total)
    $wpdb->update(
        $license_table,
        ['activations' => max(0, $license->activations - 1)],
        ['id' => $license->id],
        ['%d'], ['%d']
    );

    // Record transfer
    $transfer_info = $transfer_control->record_transfer($license_key, $domain);

    return new WP_REST_Response([
        'success' => true,
        'message' => '✅ Deactivation successful',
        'transfer_info' => [
            'transfers_used' => $transfer_info['transfer_count'],
            'transfers_limit' => $transfer_info['transfer_limit'],
            'transfers_remaining' => $transfer_info['remaining_transfers'],
            'next_transfer_available' => $transfer_info['next_transfer_available'],
            'cooldown_message' => sprintf('Anda dapat transfer lagi setelah %s', $transfer_info['next_transfer_available'])
        ]
    ], 200);
}

// HANDLER TRANSFER SLOT
function alm_rest_transfer_slot(WP_REST_Request $request) {
    $license_key = sanitize_text_field($request->get_param('license_key'));
    $old_domain  = sanitize_text_field($request->get_param('old_domain'));
    $new_domain  = sanitize_text_field($request->get_param('new_domain'));

    // panggil logic transfer atomic
    $transfer_ctrl = ALM_Transfer_Control::get_instance();
    $result = $transfer_ctrl->transfer_slot($license_key, $old_domain, $new_domain);

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result->get_error_message()
        ], 400);
    }
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Transfer slot sukses!',
        'data' => $result
    ], 200);
}
