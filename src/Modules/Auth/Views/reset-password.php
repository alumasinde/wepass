<?php
$token = $_GET['token'] ?? '';
?>
<body class="auth-page">
<div class="auth-wrapper">
<form method="POST" action="/reset-password" class="auth-card">
    <?= csrf_field() ?>

    <input type="hidden" name="token" value="<?= $token ?>">

    <h2 class="auth-title">Reset Password</h2>

    <?php if (!empty($error)): ?>
        <div style="color:red"><?= $error ?></div>
    <?php endif; ?>
<div class="form-group">
        <label for="password">New Password</label>
        <div class="input-icon">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" id="password" placeholder="Enter your new password" required>
        </div>
    </div>

    <button type="submit" class="auth-button">Reset</button>

</form>
