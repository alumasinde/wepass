<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | TuPass</title>
    <link rel="stylesheet" href="/assets/css/errors.css?v=<?= is_file($_SERVER['DOCUMENT_ROOT'].'/assets/css/errors.css') ? filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/errors.css') : time() ?>">
</head>
<body>

<div class="error-page">
    <div class="container">
        <div class="code">
            404
            <span>Oops!</span>
        </div>

        <h2>Page Not Found</h2>

        <p>
            The page you are looking for might have been removed,
            renamed, or is temporarily unavailable.
        </p>

        <?php if (!empty($errorMessage) && $errorMessage !== 'Not Found'): ?>
            <p class="error-detail"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <div class="buttons">
            <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
            <a href="/" class="btn btn-secondary">Home</a>
        </div>

        <footer>
            © <?= date('Y') ?> TuPass — Gatepass Management System
        </footer>
    </div>
</div>

</body>
</html>