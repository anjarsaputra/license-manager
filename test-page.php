<?php
/**
 * Security Test Page for License Manager Plugin
 * Hanya untuk SERVER LISENSI
 */

// Hook ke admin menu
add_action('admin_menu', 'alm_add_test_menu');

function alm_add_test_menu() {
    add_submenu_page(
        null, // Hidden from menu
        'Security Test',
        'Security Test',
        'manage_options',
        'alm-security-test',
        'alm_render_test_page'
    );
}

function alm_render_test_page() {
    ?>
    <div class="wrap">
        <h1>🔒 License Manager Security Test</h1>
        <p><strong>Server:</strong> <?php echo get_site_url(); ?></p>
        <p><strong>Time:</strong> <?php echo current_time('Y-m-d H:i:s'); ?></p>
        <hr>

        <!-- Test 1 -->
        <div class="card">
            <h2>Test 1: IP Sanitization</h2>
            <?php
            $malicious_ip = "'; DROP TABLE users; --";
            $safe_ip = alm_sanitize_ip($malicious_ip);
            ?>
            <p><strong>Input:</strong> <code><?php echo htmlspecialchars($malicious_ip); ?></code></p>
            <p><strong>Output:</strong> <code><?php echo $safe_ip; ?></code></p>
            <p><strong>Result:</strong> 
                <?php echo ($safe_ip == "0.0.0.0") ? 
                    '<span style="color:green;font-weight:bold;">✅ AMAN</span>' : 
                    '<span style="color:red;font-weight:bold;">❌ GAGAL</span>'; ?>
            </p>
        </div>

        <!-- Test 2 -->
        <div class="card">
            <h2>Test 2: License Validation</h2>
            <?php
            $valid = alm_validate_license_key("ABC123-DEF456-GHI789-JKL012");
            $invalid = alm_validate_license_key("'; DROP--");
            ?>
            <p>Valid Key: <?php echo $valid ? '<span style="color:green;">✅ PASS</span>' : '<span style="color:red;">❌ FAIL</span>'; ?></p>
            <p>Invalid Key: <?php echo !$invalid ? '<span style="color:green;">✅ BLOCKED</span>' : '<span style="color:red;">❌ ACCEPTED</span>'; ?></p>
        </div>

        <!-- Test 3 -->
        <div class="card">
            <h2>Test 3: Domain Validation</h2>
            <?php
            $prod = alm_validate_domain("https://example.com");
            $local = alm_validate_domain("http://localhost");
            ?>
            <p>Production: <?php echo $prod ? '<span style="color:green;">✅ OK</span>' : '<span style="color:red;">❌ BLOCKED</span>'; ?></p>
            <p>Localhost: <?php echo !$local ? '<span style="color:green;">✅ BLOCKED</span>' : '<span style="color:red;">❌ ACCEPTED</span>'; ?></p>
        </div>

        <!-- Test 4 -->
        <div class="card">
            <h2>Test 4: Rate Limiting</h2>
            <?php
            $test_key = "TEST-" . time();
            echo "<pre>";
            for ($i = 1; $i <= 12; $i++) {
                $ok = alm_check_rate_limit($test_key, true);
                echo "Attempt $i: " . ($ok ? "✅ OK\n" : "❌ BLOCKED\n");
            }
            echo "</pre>";
            ?>
        </div>

        <!-- Summary -->
        <div class="notice notice-success">
            <p><strong>✅ Jika semua test menunjukkan hasil hijau, security fixes berhasil!</strong></p>
        </div>
    </div>

    <style>
        .card { background: #fff; border-left: 4px solid #0073aa; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { margin-top: 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
    <?php
}