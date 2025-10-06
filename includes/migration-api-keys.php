<?php
/**
 * Migrate API Keys from Options to Database Table
 * 
 * @package License Manager
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate existing API keys to database table
 */
function alm_migrate_api_keys_to_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'alm_api_keys';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        // Table doesn't exist yet, will be created on next activation
        return;
    }
    
    // Check if migration already done
    $migration_done = get_option('alm_api_keys_migrated', false);
    if ($migration_done) {
        return; // Already migrated
    }
    
    // Check if already have keys in table
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    if ($count > 0) {
        update_option('alm_api_keys_migrated', true);
        return; // Already have keys
    }
    
    // Get old keys from options
    $old_keys = get_option('alm_secret_keys', []);
    
    if (empty($old_keys) || !is_array($old_keys)) {
        update_option('alm_api_keys_migrated', true);
        return; // No keys to migrate
    }
    
    // Migrate keys to table
    $migrated_count = 0;
    foreach ($old_keys as $index => $key) {
        if (empty($key)) {
            continue;
        }
        
        $result = $wpdb->insert($table, array(
            'api_key' => $key,
            'label' => 'Migrated Key #' . ($index + 1),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'created_by' => 1, // Assume admin user ID 1
            'last_used_at' => null,
            'total_requests' => 0
        ), array('%s', '%s', '%s', '%s', '%d', '%s', '%d'));
        
        if ($result) {
            $migrated_count++;
        }
    }
    
    // Mark migration as done
    update_option('alm_api_keys_migrated', true);
    
    // Log migration
    error_log(sprintf(
        'ALM: Successfully migrated %d API keys from options to database table at %s',
        $migrated_count,
        current_time('mysql')
    ));
    
    // Add admin notice
    add_action('admin_notices', function() use ($migrated_count) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>License Manager:</strong> 
                Successfully migrated <?php echo $migrated_count; ?> API key(s) to the new management system.
                You can now manage them in Settings → API Secret Keys.
            </p>
        </div>
        <?php
    });
}

// Run migration on admin init
add_action('admin_init', 'alm_migrate_api_keys_to_table');