<?php
if (!defined('ABSPATH')) exit;

class ALM_Transfer_Control {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function __construct() {}

    // Cek transfer eligibility per slot/domain
    public function check_transfer_eligibility($licensekey, $site_url) {
        global $wpdb;
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}alm_licenses WHERE license_key = %s LIMIT 1", $licensekey
        ));
        if (!$license) return new WP_Error('licensenotfound', 'License Not Found');
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alm_license_activations WHERE license_id = %d AND site_url = %s",
            $license->id, $site_url
        ));
        if (!$activation) return new WP_Error('activationnotfound', 'Slot/domain tidak ditemukan.');

        $count = (int)$activation->transfer_count;
        $last = $activation->last_transfer_date;
        if ($count >= 1 && $last && strtotime($last) > strtotime('-1 year')) {
            return new WP_Error('transferlimitexceeded', 'Transfer domain untuk slot ini hanya boleh 1x per tahun.');
        }
        return true;
    }

    // Update slot transfer setelah transfer sukses
    public function record_transfer($licensekey, $site_url) {
        global $wpdb;
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}alm_licenses WHERE license_key = %s LIMIT 1", $licensekey
        ));
        if (!$license) return false;
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alm_license_activations WHERE license_id = %d AND site_url = %s",
            $license->id, $site_url
        ));
        if (!$activation) return false;

        $new_transfer_count = (int)$activation->transfer_count + 1;
        $wpdb->update(
            "{$wpdb->prefix}alm_license_activations",
            [
                'transfer_count' => $new_transfer_count,
                'last_transfer_date' => current_time('mysql')
            ],
            ['id' => $activation->id]
        );
        return $this->get_transfer_info($licensekey, $site_url);
    }

    // Info sisa transfer slot
    public function get_transfer_info($licensekey, $site_url) {
        global $wpdb;
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}alm_licenses WHERE license_key = %s LIMIT 1", $licensekey
        ));
        if (!$license) return null;
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alm_license_activations WHERE license_id = %d AND site_url = %s",
            $license->id, $site_url
        ));
        if (!$activation) return null;

        $limit = 1;
        $sisa = max(0, $limit - (int)$activation->transfer_count);
        $next = !empty($activation->last_transfer_date) ? date('d F Y', strtotime($activation->last_transfer_date . ' +1 year')) : null;
        return [
            'transfer_count' => (int)$activation->transfer_count,
            'transfer_limit' => $limit,
            'remaining_transfers' => $sisa,
            'last_transfer_date' => $activation->last_transfer_date,
            'next_transfer_available' => $next,
            'can_transfer' => $sisa > 0
        ];
    }

    // Reset slot transfer (admin)
    public function admin_reset_transfer_count($activation_id) {
        if (!current_user_can('manage_options')) return new WP_Error('unauthorized', 'Unauthorized');
        global $wpdb;
        $table = "{$wpdb->prefix}alm_license_activations";
        $updated = $wpdb->update(
            $table,
            ['transfer_count' => 0, 'last_transfer_date' => null],
            ['id' => $activation_id],
            ['%d', '%s'],
            ['%d']
        );
        return $updated !== false ? true : new WP_Error('update_failed', 'Failed to reset transfer count');
    }
    
    // Transfer slot dari domain lama ke domain baru
public function transfer_slot($licensekey, $old_domain, $new_domain) {
    global $wpdb;
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}alm_licenses WHERE license_key = %s LIMIT 1", $licensekey
    ));
    if (!$license) return new WP_Error('licensenotfound', 'License Not Found');

    // 1. Dapatkan slot lama, lalu hapus
    $activation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}alm_license_activations WHERE license_id = %d AND site_url = %s",
        $license->id, $old_domain
    ));
    if (!$activation) return new WP_Error('activationnotfound', 'Slot/domain tidak ditemukan.');

    // Cek domain baru sudah terdaftar?
    $cek_baru = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}alm_license_activations WHERE license_id = %d AND site_url = %s",
        $license->id, $new_domain
    ));
    if ($cek_baru) return new WP_Error('domainexist', 'Domain tujuan sudah pernah digunakan di lisensi ini.');

    $wpdb->query('START TRANSACTION');

    // Hapus slot lama
    $wpdb->delete("{$wpdb->prefix}alm_license_activations", ['id' => $activation->id]);

    // Insert slot baru
    $wpdb->insert("{$wpdb->prefix}alm_license_activations", [
        'license_id' => $license->id,
        'site_url' => $new_domain,
        'activated_at' => current_time('mysql'),
        'transfer_count' => 0,
        'last_transfer_date' => current_time('mysql')
    ]);

    // Hitung slot aktif dan update kolom activations
    $total_activations = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}alm_license_activations WHERE license_id = %d", $license->id
    ));
    $wpdb->update("{$wpdb->prefix}alm_licenses",
        ['activations' => $total_activations],
        ['id' => $license->id]
    );

    $wpdb->query('COMMIT');

    return ['success' => true, 'message' => 'Transfer sukses ke domain baru'];
}


}
ALM_Transfer_Control::get_instance();
