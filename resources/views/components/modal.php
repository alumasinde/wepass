<div class="modal" id="<?= $id ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= $title ?></h3>
            <button type="button" class="modal-close-btn" data-modal-target="<?= $id ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <?= $content ?>
        </div>
    </div>
</div>
