<body class="auth-page">
<div class="auth-wrapper">
<form method="POST" action="/forgot-password" class="auth-card">
    <?= csrf_field() ?>

    <h2 class="auth-title">Forgot Password</h2>

    <?php if (!empty($success)): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
<div class="form-group">
        <label for="email">Email</label>
        <div class="input-icon">
            <i class="fa fa-envelope"></i>
        <input type="email" name="email" id="email" placeholder="Enter your email" required>
    </div>
    </div>

    <button type="submit" class="auth-button">Send Reset Link</button>

</form>
</div>
</body>