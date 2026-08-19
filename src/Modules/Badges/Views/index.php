<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Visits</h2>
</div>

<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<table class="table table-bordered table-hover">
    <thead class="table-light">
        <tr>
            <th>Visitor</th>
            <th>Company</th>
            <th>Purpose</th>
            <th>Check-In</th>
            <th>Check-Out</th>
            <th>Badge</th>
            <th width="260">Actions</th>
        </tr>
    </thead>
    <tbody>

    <?php if (!empty($visits)): ?>
        <?php foreach ($visits as $visit): ?>
            <tr>

                <td><?= htmlspecialchars($visit['visitor_name']) ?></td>

                <td><?= htmlspecialchars($visit['company_name'] ?? '-') ?></td>

                <td><?= htmlspecialchars($visit['purpose'] ?? '-') ?></td>

                <td>
                    <?= $visit['checkin_time']
                        ? htmlspecialchars($visit['checkin_time'])
                        : '<span class="text-muted">Not Checked In</span>' ?>
                </td>

                <td>
                    <?= $visit['checkout_time']
                        ? htmlspecialchars($visit['checkout_time'])
                        : '<span class="text-muted">Not Checked Out</span>' ?>
                </td>

                <td>
                    <?php if (!empty($visit['badge_code'])): ?>

                        <?php if (!empty($visit['badge_returned_at'])): ?>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($visit['badge_code']) ?> (Returned)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-info text-dark">
                                <?= htmlspecialchars($visit['badge_code']) ?>
                            </span>
                        <?php endif; ?>

                    <?php else: ?>
                        <span class="text-muted">No Badge</span>
                    <?php endif; ?>
                </td>

                <td>
                    <div class="d-flex flex-wrap gap-2">

                        <!-- CHECK IN -->
                        <?php if (!$visit['checkin_time'] && !$visit['checkout_time']): ?>
                            <form method="POST" action="/visits/<?= $visit['id'] ?>/checkin">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-primary">
                                    Check In
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- ISSUE BADGE -->
                        <?php if ($visit['checkin_time'] 
                                  && !$visit['checkout_time'] 
                                  && empty($visit['badge_code'])): ?>
                            <form method="POST" action="/badges/<?= $visit['id'] ?>/issue">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-secondary">
                                    Issue Badge
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- RETURN BADGE -->
                        <?php if (!empty($visit['badge_code']) 
                                  && empty($visit['badge_returned_at'])): ?>
                            <form method="POST" action="/badges/<?= $visit['id'] ?>/return">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-warning">
                                    Return Badge
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- CHECK OUT -->
                        <?php if ($visit['checkin_time'] 
                                  && !$visit['checkout_time']): ?>
                            <form method="POST" action="/visits/<?= $visit['id'] ?>/checkout">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-danger">
                                    Check Out
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                </td>

            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" class="text-center text-muted">
                No visits found.
            </td>
        </tr>
    <?php endif; ?>

    </tbody>
</table>