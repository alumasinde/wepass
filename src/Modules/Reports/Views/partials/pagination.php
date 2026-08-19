<?php if ($data['meta']['last_page'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $data['meta']['last_page']; $i++): ?>
        <?php
        $query = $_GET;
        $query['page'] = $i;
        ?>
        <a 
            href="?<?= http_build_query($query) ?>"
            class="<?= $i == $data['meta']['current_page'] ? 'active' : '' ?>"
        >
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>