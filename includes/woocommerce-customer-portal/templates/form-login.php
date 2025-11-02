<?php
if ( is_user_logged_in() ) {
    echo '<div class="wcp-login-success">Anda sudah login ke member-area.</div>';
    return;
}
?>

<form method="post" class="wcp-login-form" action="">
    <h2 style="text-align:center;margin-bottom:28px;">Login Member Area</h2>

    <p>
        <label for="wcp_username">Username atau Email</label><br>
        <input type="text" name="wcp_username" id="wcp_username" required style="width:100%;padding:10px;">
    </p>

    <p>
        <label for="wcp_password">Password</label><br>
        <input type="password" name="wcp_password" id="wcp_password" required style="width:100%;padding:10px;">
    </p>

    <?php wp_nonce_field('wcp_custom_login','wcp_custom_login_nonce'); ?>
    <p style="text-align:center;">
        <button type="submit" style="padding:12px 36px;background:#2563eb;color:#fff;border-radius:5px;border:none;cursor:pointer;">Masuk</button>
    </p>
    <p style="text-align:center;margin-top:16px;">
        <a href="<?php echo wp_lostpassword_url(); ?>">Lupa password?</a>
    </p>
</form>
<style>
.wcp-login-form {
    max-width:350px;
    margin:36px auto;
    background:#f8fafc;
    border-radius:16px;
    box-shadow:0 2px 14px rgba(59,130,246,0.08);
    padding:36px;
}
.wcp-login-form label { font-weight:500;color:#2563eb }
.wcp-login-form input { border:1px solid #dbeafe;border-radius:6px;margin-bottom:17px; }
.wcp-login-form button { font-weight:700;font-size:1.12rem }
</style>
