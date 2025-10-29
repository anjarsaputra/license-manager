<?php
/**
 * Transfer Control System - Database Migration
 * Version: 1.0
 * Run ONCE to add transfer tracking columns
 * 
 * @package License Manager - Transfer Control
 */

if (!defined('ABSPATH')) exit;

/**
 * Add transfer control columns to licenses table
 */
function alm_transfer_control_migrate_database() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'alm_licenses';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        return new WP_Error('table_not_found', 'License table not found');
    }
    
    // Add columns one by one to avoid errors
    $columns_to_add = [
        'transfer_count' => "ADD COLUMN transfer_count INT DEFAULT 0 AFTER failed_attempts",
        'last_transfer_date' => "ADD COLUMN last_transfer_date DATETIME NULL AFTER transfer_count",
        'transfer_limit' => "ADD COLUMN transfer_limit INT DEFAULT 2 AFTER last_transfer_date",
        'domain_locked' => "ADD COLUMN domain_locked TINYINT(1) DEFAULT 0 AFTER transfer_limit",
        'order_id' => "ADD COLUMN order_id BIGINT NULL AFTER domain_locked"
    ];
    
    $results = [];
    
    foreach ($columns_to_add as $column => $sql) {
        // Check if column already exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = %s",
                DB_NAME,
                $table,
                $column
            )
        );
        
        if (empty($column_exists)) {
            $result = $wpdb->query("ALTER TABLE $table $sql");
            
            if ($result === false) {
                $results[$column] = [
                    'success' => false,
                    'error' => $wpdb->last_error
                ];
            } else {
                $results[$column] = [
                    'success' => true,
                    'message' => 'Column added successfully'
                ];
            }
        } else {
            $results[$column] = [
                'success' => true,
                'message' => 'Column already exists'
            ];
        }
    }
    
    // Log migration
    if (function_exists('alm_insert_log')) {
        alm_insert_log(
            'SYSTEM',
            'database_migration',
            'Transfer control columns migration: ' . json_encode($results),
            ''
        );
    }
    
    return $results;
}

/**
 * Admin page to run migration
 */
function alm_transfer_control_migration_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $migrated = false;
    $results = null;
    
    if (isset($_POST['run_migration']) && check_admin_referer('alm_migration_nonce')) {
        $results = alm_transfer_control_migrate_database();
        $migrated = true;
    }
    
    ?>
    <div class="wrap">
        <h1>🔧 Transfer Control - Database Migration</h1>
        
        <?php if ($migrated): ?>
            <div class="notice notice-success">
                <p><strong>✅ Migration Completed!</strong></p>
                <pre><?php print_r($results); ?></pre>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Database Migration</h2>
            <p>This will add the following columns to <code>wp_alm_licenses</code> table:</p>
            <ul>
                <li><code>transfer_count</code> - Track number of transfers</li>
                <li><code>last_transfer_date</code> - Last transfer timestamp</li>
                <li><code>transfer_limit</code> - Maximum allowed transfers (default: 2)</li>
                <li><code>domain_locked</code> - Lock status (0 = unlocked, 1 = locked)</li>
                <li><code>order_id</code> - WooCommerce order reference</li>
            </ul>
            
            <form method="post">
                <?php wp_nonce_field('alm_migration_nonce'); ?>
                <button type="submit" name="run_migration" class="button button-primary button-large">
                    🚀 Run Migration
                </button>
            </form>
            
            <p><em>⚠️ Safe to run multiple times - will skip existing columns</em></p>
        </div>
    </div>
    <?php
}

// ============================================
// MIGRATION MENU - DISABLED
// Database already migrated manually via SQL
// ============================================
/*
add_action('admin_menu', function() {
    add_submenu_page(
        'alm-dashboard',
        'Transfer Control Migration',
        '🔧 Migration',
        'manage_options',
        'alm-transfer-migration',
        'alm_transfer_control_migration_page'
    );
});
*/
