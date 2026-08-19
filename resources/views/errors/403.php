<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 - Forbidden | TuPass</title>
    <link rel="stylesheet" href="/assets/css/errors.css?v=<?= is_file($_SERVER['DOCUMENT_ROOT'].'/assets/css/errors.css') ? filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/errors.css') : time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="error-page">
<div class="container">
    <div class="code">
        403
        <span>Access Denied</span>
    </div>

    <h2>Forbidden</h2>

    <p>
        You do not have permission to access this resource.
        Please contact your administrator if you believe this is an error.
    </p>

    <?php if (!empty($errorMessage) && $errorMessage !== 'Forbidden'): ?>
        <p class="error-detail"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="buttons">
        <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
    </div>

    <footer>
        © <?= date('Y') ?> TuPass — Gatepass Management System
    </footer>
</div>
</div>

</body>
</html>