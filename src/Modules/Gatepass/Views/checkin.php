<?php /** @var array $gatepass */ ?>

<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Check In Gatepass</h5>
        </div>

        <div class="card-body">

            <div class="mb-3">
                <strong>Gatepass No:</strong>
                <?= htmlspecialchars($gatepass['gatepass_number']) ?>
            </div>

            <div class="mb-3">
                <strong>Type:</strong>
                <?= htmlspecialchars($gatepass['gatepass_type_name']) ?>
            </div>

            <div class="mb-3">
                <strong>Visitor:</strong>
                <?= htmlspecialchars($gatepass['visitor_name'] ?? '-') ?>
            </div>

            <div class="mb-3">
                <strong>Purpose:</strong>
                <?= htmlspecialchars($gatepass['purpose']) ?>
            </div>

            <?php if (!empty($gatepass['actual_in'])): ?>
                <div class="alert alert-warning">
                    This gatepass was already checked in at
                    <?= htmlspecialchars($gatepass['actual_in']) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Are you sure you want to check this gatepass in?
                </div>

                <form method="POST"
                      action="/gatepasses/<?= (int)$gatepass['id'] ?>/checkin">
                    <?= csrf_field() ?>

                    <button type="submit" class="btn btn-success">
                        Confirm Check In
                    </button>

                    <a href="/gatepasses"
                       class="btn btn-secondary">
                        Cancel
                    </a>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>