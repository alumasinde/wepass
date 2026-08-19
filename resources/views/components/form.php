<?php
/*
Expected variables:
$action (string)
$method (string) default POST
$errors (array) optional
$content (string) form fields
*/

$action = $action ?? '';
$method = strtoupper($method ?? 'POST');
$errors = $errors ?? [];
?>

<form action="<?= htmlspecialchars($action) ?>"
      method="<?= $method ?>"
      class="app-form">

    <?php if (!empty($errors)): ?>
        <div class="form-errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?= $content ?? '' ?>

</form>
