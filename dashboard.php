<?php
// File: dashboard.php

if (!defined('ABSPATH')) {
    exit;
}

// ONLY load in admin area
if (!is_admin()) {
    return; // ← FIX: Stop execution if not admin area
}

if ( ! is_user_logged_in() ) {
    return;
}

if ( ! current_user_can('manage_options') ) {
    wp_die('Hanya admin yang boleh mengakses halaman ini.');
}

// ... kode dashboard ...
// Selanjutnya barulah kode dashboard, function, dsb...

global $wpdb;
$alerts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}alm_logs WHERE action = 'alert' ORDER BY log_time DESC LIMIT 3");
if ($alerts) : ?>
    <div style="background:#fef2f2;color:#991b1b;padding:16px;border-radius:8px;margin-bottom:16px;">
        <strong>Activity Alert:</strong>
        <ul style="margin:0 0 0 20px;">
            <?php foreach($alerts as $a): ?>
                <li><b><?php echo esc_html($a->log_time); ?></b> &mdash; <?php echo esc_html($a->message); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; 

// Statistik utama dashboard
function alm_get_dashboard_stats() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    $activation_table = $wpdb->prefix . 'alm_license_activations';

    return array(
        'total_licenses' => $wpdb->get_var("SELECT COUNT(*) FROM $license_table") ?? '0',
        'active_licenses' => $wpdb->get_var("SELECT COUNT(*) FROM $license_table WHERE status = 'active'") ?? '0',
        'expired_licenses' => $wpdb->get_var("SELECT COUNT(*) FROM $license_table WHERE status = 'expired'") ?? '0',
        'total_activations' => $wpdb->get_var("SELECT COUNT(*) FROM $activation_table") ?? '0',
    );
}

// Grafik tren aktivasi 12 bulan terakhir
function alm_get_license_trend_data() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_license_activations';
    $trend = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $license_table WHERE DATE_FORMAT(activated_at, '%%Y-%%m') = %s", $month)
        );
        $trend[] = [
            'month' => date('M Y', strtotime($month.'-01')),
            'count' => intval($count)
        ];
    }
    return $trend;
}

// 5 lisensi terbaru
function alm_get_latest_licenses() {
    global $wpdb;
    $license_table = $wpdb->prefix . 'alm_licenses';
    return $wpdb->get_results("SELECT * FROM $license_table ORDER BY created_at DESC LIMIT 5");
}

// Export log ke CSV
function alm_export_log_csv() {
    if (!current_user_can('manage_options') || !isset($_GET['alm_export_log'])) return;
    global $wpdb;
    $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}alm_logs ORDER BY log_time DESC");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=\"license-log.csv\"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time', 'Action', 'License Key', 'Message']);
    foreach ($logs as $log) {
        fputcsv($out, [$log->log_time, $log->action, $log->license_key, $log->message]);
    }
    fclose($out);
    exit;
}
alm_export_log_csv();

// Halaman dashboard utama
function alm_render_dashboard_page() {
    $stats = alm_get_dashboard_stats();
    $trendData = alm_get_license_trend_data();
    $latestLicenses = alm_get_latest_licenses();
    $trendLabels = json_encode(array_column($trendData, 'month'));
    $trendCounts = json_encode(array_column($trendData, 'count'));
    ?>
    <div class="wrap alm-wrap">
        <!-- Header -->
        <div class="alm-dashboard-header">
            <div class="alm-header-content">
                <h1>License Manager</h1>
                <p class="alm-header-description">Manage your licenses and monitor activations</p>
            </div>
            <div class="alm-header-actions">
                <a href="?page=alm-add-license" class="alm-button alm-button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Add New License
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="alm-stats-grid">
            <div class="alm-stat-card">
                <div class="alm-stat-icon">
                    <span class="dashicons dashicons-admin-network"></span>
                </div>
                <div class="alm-stat-content">
                    <h3><?php echo esc_html($stats['total_licenses']); ?></h3>
                    <p>Total Licenses</p>
                </div>
            </div>
            <div class="alm-stat-card">
                <div class="alm-stat-icon active">
                    <span class="dashicons dashicons-shield-alt"></span>
                </div>
                <div class="alm-stat-content">
                    <h3><?php echo esc_html($stats['active_licenses']); ?></h3>
                    <p>Active Licenses</p>
                </div>
            </div>
            <div class="alm-stat-card">
                <div class="alm-stat-icon warning">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="alm-stat-content">
                    <h3><?php echo esc_html($stats['expired_licenses']); ?></h3>
                    <p>Expired Licenses</p>
                </div>
            </div>
            <div class="alm-stat-card">
                <div class="alm-stat-icon info">
                    <span class="dashicons dashicons-desktop"></span>
                </div>
                <div class="alm-stat-content">
                    <h3><?php echo esc_html($stats['total_activations']); ?></h3>
                    <p>Total Activations</p>
                </div>
            </div>
        </div>
        
        <!-- Grafik Tren Aktivasi Lisensi -->
        <div class="alm-card" style="margin-bottom:30px;">
            <div class="alm-card-header">
                <div class="alm-card-title">
                    <span class="dashicons dashicons-chart-line"></span>
                    <h2>Tren Aktivasi Lisensi</h2>
                </div>
            </div>
            <div class="alm-card-content">
                <canvas id="almLicenseTrendChart" height="60"></canvas>
            </div>
        </div>

        <!-- Grid Main -->
        <div class="alm-dashboard-grid">
            <!-- Recent Activity -->
            <div class="alm-card">
                <div class="alm-card-header">
                    <div class="alm-card-title">
                        <span class="dashicons dashicons-backup"></span>
                        <h2>Recent Activity</h2>
                    </div>
                    <form method="get" action="" style="display:inline-flex;gap:8px;">
                        <input type="hidden" name="page" value="alm-dashboard">
                        <input type="text" name="log_search" placeholder="Cari aksi atau lisensi..." value="<?php echo esc_attr($_GET['log_search']??''); ?>" style="padding:4px 8px;border-radius:4px;border:1px solid #eee;">
                        <button type="submit" class="alm-button alm-button-small">Cari</button>
                        <a href="?alm_export_log=1" class="alm-button alm-button-small" style="margin-left:8px;">Export CSV</a>
                    </form>
                </div>
                <div class="alm-table-container">
                    <?php
                    global $wpdb;
                    $log_search = $_GET['log_search'] ?? '';
                    $where = '';
                    if ($log_search) {
                        $like = '%' . $wpdb->esc_like($log_search) . '%';
                        $where = $wpdb->prepare("WHERE action LIKE %s OR license_key LIKE %s OR message LIKE %s", $like, $like, $like);
                    }
                    $logs = $wpdb->get_results("
                        SELECT * FROM {$wpdb->prefix}alm_logs
                        $where
                        ORDER BY log_time DESC 
                        LIMIT 5
                    ");
                    if (!empty($logs)) : ?>
                        <table class="alm-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                    <th>License Key</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log) : ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log->log_time)); ?></td>
                                        <td>
                                            <span class="alm-badge <?php echo esc_attr($log->action); ?>">
                                                <?php echo esc_html($log->action); ?>
                                            </span>
                                        </td>
                                        <td><code><?php echo esc_html($log->license_key); ?></code></td>
                                        <td><?php echo esc_html($log->message); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="alm-empty-state">
                            <span class="dashicons dashicons-marker"></span>
                            <p>No recent activity to show</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="alm-sidebar">
                <!-- License Terbaru -->
                <div class="alm-card">
                    <div class="alm-card-header">
                        <div class="alm-card-title">
                            <span class="dashicons dashicons-admin-users"></span>
                            <h2>Lisensi Terbaru</h2>
                        </div>
                    </div>
                    <div class="alm-card-content">
                        <?php if($latestLicenses): ?>
                            <ul style="margin:0;padding-left:0;list-style:none;">
                            <?php foreach($latestLicenses as $lic): ?>
                                <li style="margin-bottom:10px;">
                                    <span class="dashicons dashicons-shield" style="color:#2563eb;vertical-align:middle;"></span>
                                    <code><?php echo esc_html($lic->license_key); ?></code>
                                    <span class="alm-badge <?php echo esc_attr($lic->status); ?>"><?php echo esc_html($lic->status); ?></span>
                                    <small style="color:#64748b;"><?php echo date('Y-m-d', strtotime($lic->created_at)); ?></small>
                                </li>
                            <?php endforeach;?>
                            </ul>
                        <?php else: ?>
                            <div class="alm-empty-state"><p>Belum ada lisensi.</p></div>
                        <?php endif;?>
                    </div>
                </div>
                <!-- Quick Actions -->
                <div class="alm-card">
                    <div class="alm-card-header">
                        <div class="alm-card-title">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <h2>Quick Actions</h2>
                        </div>
                    </div>
                    <div class="alm-card-content">
                        <div class="alm-quick-actions">
                            <a href="?page=alm-add-license" class="alm-action-button">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <div class="alm-action-content">
                                    <strong>Add New License</strong>
                                    <span>Create a new license key</span>
                                </div>
                            </a>
                            <a href="?page=alm-activity-log" class="alm-action-button">
                                <span class="dashicons dashicons-list-view"></span>
                                <div class="alm-action-content">
                                    <strong>Activity Log</strong>
                                    <span>View all activities</span>
                                </div>
                            </a>
                            <a href="?page=alm-theme-update" class="alm-action-button">
                                <span class="dashicons dashicons-update"></span>
                                <div class="alm-action-content">
                                    <strong>Update Settings</strong>
                                    <span>Manage theme updates</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- System Status -->
                <div class="alm-card">
                    <div class="alm-card-header">
                        <div class="alm-card-title">
                            <span class="dashicons dashicons-dashboard"></span>
                            <h2>System Status</h2>
                        </div>
                    </div>
                    <div class="alm-card-content">
                        <div class="alm-status-list">
                            <div class="alm-status-item">
                                <span class="status-dot active"></span>
                                <div class="status-info">
                                    <strong>License API</strong>
                                    <span>System is operational</span>
                                </div>
                            </div>
                            <div class="alm-status-item">
                                <span class="status-dot active"></span>
                                <div class="status-info">
                                    <strong>Update Server</strong>
                                    <span>Connected and running</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- FAQ/Bantuan -->
                <div class="alm-card">
                    <div class="alm-card-header">
                        <div class="alm-card-title">
                            <span class="dashicons dashicons-editor-help"></span>
                            <h2>Bantuan Singkat</h2>
                        </div>
                    </div>
                    <div class="alm-card-content" style="font-size:14px;">
                        <ul style="margin:0 0 10px 0;padding-left:18px;">
                            <li>Bagaimana cara generate lisensi baru? <br><b>Buka "Add New License"</b> dan isi data.</li>
                            <li>Bagaimana export data log? <br><b>Gunakan tombol "Export CSV" di atas log.</b></li>
                            <li>Lisensi tidak aktif? <br>Pastikan status <b>active</b> dan belum expired.</li>
                            <li>Lainnya: <a href="https://aradevweb.com/dokumentasi/license-manager" target="_blank">Lihat dokumentasi lengkap</a></li>
                        </ul>
                    </div>
                </div>
            </div><!-- end sidebar -->
        </div>
    </div>
    <!-- Chart.js CDN (bisa dipindah ke enqueue di plugin utama) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        var ctx = document.getElementById('almLicenseTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $trendLabels; ?>,
                datasets: [{
                    label: 'Aktivasi Lisensi',
                    data: <?php echo $trendCounts; ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.15)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: 3
                }]
            },
            options: {
                plugins: {
                    legend: { display: false }
                },
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
    </script>
    <style>
    .alm-wrap {
        margin: 20px 20px 0 0;
    }
    .alm-dashboard-header {
        background: linear-gradient(135deg, #2563eb, #1e40af);
        padding: 40px;
        border-radius: 16px;
        margin-bottom: 24px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .alm-header-content h1 {
        color: white;
        font-size: 24px;
        margin: 0 0 8px 0;
    }
    .alm-header-description {
        margin: 0;
        opacity: 0.8;
    }
    .alm-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }
    .alm-stat-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .alm-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e0e7ff;
    }
    .alm-stat-icon.active { background: #dcfce7; }
    .alm-stat-icon.warning { background: #fef9c3; }
    .alm-stat-icon.info { background: #e0f2fe; }
    .alm-stat-icon .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        color: #2563eb;
    }
    .alm-stat-icon.active .dashicons { color: #16a34a; }
    .alm-stat-icon.warning .dashicons { color: #ca8a04; }
    .alm-stat-icon.info .dashicons { color: #0284c7; }
    .alm-stat-content h3 {
        margin: 0 0 4px 0;
        font-size: 24px;
        line-height: 1;
    }
    .alm-stat-content p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }
    .alm-dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }
    .alm-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 24px;
    }
    .alm-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .alm-card-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alm-card-title h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }
    .alm-card-title .dashicons {
        color: #2563eb;
        font-size: 20px;
        width: 20px;
        height: 20px;
    }
    .alm-card-content {
        padding: 24px;
    }
    .alm-quick-actions {
        display: grid;
        gap: 12px;
    }
    .alm-action-button {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }
    .alm-action-button:hover {
        background: #f1f5f9;
        transform: translateY(-1px);
    }
    .alm-action-button .dashicons {
        color: #2563eb;
        font-size: 20px;
        width: 20px;
        height: 20px;
    }
    .alm-action-content strong {
        display: block;
        font-size: 14px;
        color: #0f172a;
    }
    .alm-action-content span {
        font-size: 12px;
        color: #64748b;
    }
    .alm-status-list {
        display: grid;
        gap: 16px;
    }
    .alm-status-item {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #dc2626;
    }
    .status-dot.active {
        background: #16a34a;
    }
    .status-info strong {
        display: block;
        font-size: 14px;
        color: #0f172a;
    }
    .status-info span {
        font-size: 12px;
        color: #64748b;
    }
    .alm-table {
        width: 100%;
        border-collapse: collapse;
    }
    .alm-table th,
    .alm-table td {
        padding: 12px 24px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    .alm-table th {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .alm-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        text-transform: capitalize;
    }
    .alm-badge.activate { background: #dcfce7; color: #166534; }
    .alm-badge.deactivate { background: #fee2e2; color: #991b1b; }
    .alm-badge.revoke { background: #fef9c3; color: #854d0e; }
    .alm-empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #64748b;
    }
    .alm-empty-state .dashicons {
        font-size: 32px;
        width: 32px;
        height: 32px;
        margin-bottom: 8px;
    }
    .alm-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .alm-button-primary {
        background: white;
        color: #2563eb;
    }
    .alm-button-primary:hover {
        background: rgba(255,255,255,0.9);
        color: #1e40af;
    }
    .alm-button-small {
        padding: 6px 12px;
        font-size: 12px;
        background: #f8fafc;
        color: #64748b;
    }
    .alm-button-small:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    @media screen and (max-width: 1200px) {
        .alm-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media screen and (max-width: 782px) {
        .alm-dashboard-grid {
            grid-template-columns: 1fr;
        }
        .alm-dashboard-header {
            flex-direction: column;
            gap: 16px;
            text-align: center;
        }
    }
    </style>
    <?php
}
?>