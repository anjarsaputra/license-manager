<?php



add_action('rest_api_init', function() {
  register_rest_route('alm/v1', '/webhook-secret', array(
    'methods' => 'GET',
    'callback' => function(WP_REST_Request $request) {
      // SECRET GLOBAL - atur di option, rotate jika butuh
      $secret = get_option('alm_global_webhook_secret');
      if (!$secret) {
        $secret = wp_generate_password(32, true, true);
        update_option('alm_global_webhook_secret', $secret);
      }
      return new WP_REST_Response([
        'success' => true,
        'webhook_secret' => $secret,
      ]);
    },
    'permission_callback' => '__return_true', // Boleh diganti permission/validate jika ingin lebih aman
  ));
});



?>