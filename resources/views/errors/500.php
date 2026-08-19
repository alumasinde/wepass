<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>500 - Server Error | TuPass</title>
    <link rel="stylesheet" href="/assets/css/errors.css?v=<?= is_file($_SERVER['DOCUMENT_ROOT'].'/assets/css/errors.css') ? filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/errors.css') : time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="error-page">
<div class="container">
    <div class="code">
        500
        <span>Server Error</span>
    </div>

    <h2>Something Went Wrong</h2>

    <p>
        An unexpected error occurred on the server.
        Please try again later or contact support if the problem persists.
    </p>

    <?php if (!empty($errorMessage) && $errorMessage !== 'Server Error' && $errorMessage !== 'Internal Server Error'): ?>
        <p class="error-detail"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="buttons">
        <a href="/dashboard" class="btn btn-primary">Back to Dashboard</a>
    </div>

    <footer>
        © <?= date('Y') ?> TuPass — Gatepass Management System
    </footer>
</div>
</div>

</body>
</html>