

<body class="auth-page">
<div class="auth-wrapper">

    <form method="POST" action="/login" class="auth-card" autocomplete="off">
        <h2 class="auth-title">Welcome Back</h2>

        <!-- CSRF Token -->
        <input 
            type="hidden" 
            name="csrf_token" 
            value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >

        <?php if (!empty($flash['message'])): ?>
            <div class="alert-error">
                <i class="fa fa-exclamation-circle"></i>
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="email">Email</label>
            <div class="input-icon">
                <i class="fa fa-envelope"></i>
                <input 
                    id="email"
                    type="email" 
                    name="email" 
                    placeholder="Enter your email" 
                    required
                    autocomplete="username"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon">
                <i class="fa fa-lock"></i>
                <input 
                    id="password"
                    type="password" 
                    name="password" 
                    placeholder="Enter your password" 
                    required
                    autocomplete="current-password"
                >
            </div>
        </div>

        <button type="submit" class="auth-button">
            <i class="fa fa-sign-in-alt"></i> Login
        </button>

        <div class="extra-links">
            <a href="/forgot-password">Forgot password?</a>
        </div>

    </form>

</div>
</body>