<table class="custom-table">

    <thead>
    <tr>
        <?php foreach ($headers as $header): ?>
            <th><?= htmlspecialchars($header) ?></th>
        <?php endforeach; ?>
    </tr>
    </thead>

    <tbody>
    <?= $rows ?>
    </tbody>

</table>
