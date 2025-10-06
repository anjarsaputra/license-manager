<?php
/**
 * Customer API Endpoints
 * 
 * REST API endpoints for customer portal
 * 
 * @package License Manager
 * @version 1.0.0
 * @author anjarsaputra
 * @since 2025-10-02
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register customer API endpoints
 */
add_action('rest_api_init', 'alm_register_customer_api_endpoints');

function alm_register_customer_api_endpoints() {
    
    /**
     * Get customer licenses
     * 
     * Endpoint: /wp-json/alm/v1/customer/licenses
     * Method: POST
     */
    register_rest_route('alm/v1', '/customer/licenses', array(
        'methods' => 'POST',
        'callback' => 'alm_api_get_customer_licenses',
        'permission_callback' => 'alm_api_permission_check',
        'args' => array(
            'email' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => 'is_email'
            ),
            'source' => array(
                'required' => false,
                'type' => 'string',
                'default' => 'woocommerce'
            )
        )
    ));
    
    /**
     * Get single license details
     * 
     * Endpoint: /wp-json/alm/v1/customer/license/{license_key}
     * Method: GET
     */
    register_rest_route('alm/v1', '/customer/license/(?P<license_key>[a-zA-Z0-9\-]+)', array(
        'methods' => 'GET',
        'callback' => 'alm_api_get_single_license',
        'permission_callback' => 'alm_api_permission_check',
        'args' => array(
            'license_key' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
}

/**
 * Get customer licenses
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response
 */
function alm_api_get_customer_licenses(WP_REST_Request $request) {
    global $wpdb;
    
    // Get parameters
    $email = $request->get_param('email');
    $source = $request->get_param('source');
    
    // Validate email
    if (!is_email($email)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid email address'
        ), 400);
    }
    
    // Check rate limit
    if (!alm_check_rate_limit('customer_api_' . md5($email))) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Too many requests. Please try again later.'
        ), 429);
    }
    
    // Query licenses
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $licenses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$license_table} 
        WHERE customer_email = %s 
        ORDER BY created_at DESC",
        $email
    ), ARRAY_A);
    
    if (empty($licenses)) {
        return new WP_REST_Response(array(
            'success' => true,
            'licenses' => array(),
            'count' => 0,
            'message' => 'No licenses found for this email'
        ), 200);
    }
    
    // Enhance licenses with activation data
    foreach ($licenses as &$license) {
        // Get active sites
        $active_sites = $wpdb->get_results($wpdb->prepare(
            "SELECT site_url, activated_at, last_check, status 
            FROM {$activation_table} 
            WHERE license_id = %d 
            ORDER BY activated_at DESC",
            $license['id']
        ), ARRAY_A);
        
        $license['active_sites'] = $active_sites;
        
        // Calculate days until expiry
        if (!empty($license['expires'])) {
            $expires_timestamp = strtotime($license['expires']);
            $now = time();
            $days_until_expiry = floor(($expires_timestamp - $now) / DAY_IN_SECONDS);
            $license['days_until_expiry'] = $days_until_expiry;
            $license['is_expired'] = $days_until_expiry < 0;
        } else {
            $license['days_until_expiry'] = null;
            $license['is_expired'] = false;
        }
        
        // Add download URL (if you store theme files)
        // $license['download_url'] = alm_get_download_url($license['product_name']);
        
        // Add latest version info
        // $license['version'] = alm_get_latest_version($license['product_name']);
        
        // Remove sensitive data
        unset($license['id']);
    }
    
    // Log API access
    alm_insert_log(
        'CUSTOMER_API',
        'licenses_fetched',
        sprintf('Customer %s fetched %d licenses', $email, count($licenses)),
        ''
    );
    
    return new WP_REST_Response(array(
        'success' => true,
        'licenses' => $licenses,
        'count' => count($licenses),
        'source' => $source,
        'timestamp' => current_time('mysql', true)
    ), 200);
}

/**
 * Get single license details
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response
 */
function alm_api_get_single_license(WP_REST_Request $request) {
    global $wpdb;
    
    // Get license key
    $license_key = $request->get_param('license_key');
    
    // Validate format
    if (!preg_match('/^[A-Z0-9\-]{10,}$/i', $license_key)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid license key format'
        ), 400);
    }
    
    // Check rate limit
    if (!alm_check_rate_limit('license_api_' . md5($license_key))) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Too many requests'
        ), 429);
    }
    
    // Query license
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';
    
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$license_table} WHERE license_key = %s",
        $license_key
    ), ARRAY_A);
    
    if (!$license) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'License not found'
        ), 404);
    }
    
    // Get activations
    $activations = $wpdb->get_results($wpdb->prepare(
        "SELECT site_url, activated_at, last_check, status 
        FROM {$activation_table} 
        WHERE license_id = %d 
        ORDER BY activated_at DESC",
        $license['id']
    ), ARRAY_A);
    
    $license['active_sites'] = $activations;
    
    // Calculate expiry info
    if (!empty($license['expires'])) {
        $expires_timestamp = strtotime($license['expires']);
        $license['days_until_expiry'] = floor(($expires_timestamp - time()) / DAY_IN_SECONDS);
        $license['is_expired'] = $license['days_until_expiry'] < 0;
    }
    
    // Remove sensitive internal data
    unset($license['id']);
    
    // Log access
    alm_insert_log(
        $license_key,
        'license_viewed',
        'License details accessed via customer API',
        ''
    );
    
    return new WP_REST_Response(array(
        'success' => true,
        'license' => $license,
        'timestamp' => current_time('mysql', true)
    ), 200);
}