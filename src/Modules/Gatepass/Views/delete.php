<h1>Delete Gatepass</h1>

<div class="form-card">

    <p>
        Are you sure you want to delete 
        <strong>Gatepass #<?= htmlspecialchars($gatepass['gatepass_number']) ?></strong>?
    </p>

    <form method="POST" action="/gatepasses/<?= $gatepass['id'] ?>/delete">
        <?= csrf_field() ?>

        <button type="submit" class="btn btn-danger">
            Yes, Delete
        </button>

        <a href="/gatepasses/<?= $gatepass['id'] ?>" class="btn btn-secondary">
            Cancel
        </a>

    </form>

</div>