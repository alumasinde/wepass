<?php /** @var array|null $current */ ?>
<?php /** @var array $users */ ?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-user-clock"></i>
                My Delegate
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings/users/profile" class="btn btn-secondary btn-sm">Back to Profile</a>
        </div>
    </div>

    <div class="card-body">

        <div class="alert alert-info">
            If you're eligible to approve gatepasses (by role, department, or being specifically tagged on a
            step) and you'll be away, name a backup here. While your delegation window is active, they'll
            receive your approval requests instead of you.
        </div>

        <?php if ($current): ?>
            <div class="alert alert-success">
                <strong><?= htmlspecialchars($current['first_name'] . ' ' . $current['last_name']) ?></strong>
                (<?= htmlspecialchars($current['email']) ?>) is covering for you from
                <strong><?= htmlspecialchars($current['starts_at']) ?></strong> to
                <strong><?= htmlspecialchars($current['ends_at']) ?></strong>.
            </div>

            <form method="POST" action="/settings/delegation/clear" data-confirm="Remove this delegate?" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Remove Delegate</button>
            </form>

            <div class="section-divider"></div>
        <?php endif; ?>

        <form method="POST" action="/settings/delegation">
            <?= csrf_field() ?>

            <div class="form-grid">

                <div class="form-group">
                    <label for="delegate_user_id">Delegate To</label>
                    <select id="delegate_user_id" name="delegate_user_id" class="form-control" required>
                        <option value="">Select a user</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="starts_at">From</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="ends_at">Until</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" required>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $current ? 'Update Delegate' : 'Set Delegate' ?>
                </button>
            </div>

        </form>

    </div>
</div>
