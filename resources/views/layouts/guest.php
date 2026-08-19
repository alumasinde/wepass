<?php 
$content = $content ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'Guest' ?></title>
<link rel="stylesheet" href="/assets/css/login.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/login.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

    <?= $content ?>

</body>
</html>
