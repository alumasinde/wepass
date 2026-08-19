<?php
/** @var array $visitor */
/** @var array $visit */
/** @var string|null $error */
?>

<div class="container mt-4">

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-0">Issue Visitor Badge</h4>
        </div>

        <div class="card-body">

            <!-- Visitor Summary -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2">Visitor Information</h5>

                <div class="row">
                    <div class="col-md-6">
                        <strong>Name:</strong>
                        <?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?>
                    </div>

                    <div class="col-md-6">
                        <strong>ID Number:</strong>
                        <?= htmlspecialchars($visitor['id_number'] ?? 'N/A') ?>
                    </div>

                    <div class="col-md-6 mt-2">
                        <strong>Company:</strong>
                        <?= htmlspecialchars($visitor['company_name'] ?? 'N/A') ?>
                    </div>

                    <div class="col-md-6 mt-2">
                        <strong>Risk Score:</strong>
                        <span class="badge bg-<?= $visitor['risk_score'] > 50 ? 'danger' : 'success' ?>">
                            <?= (int) $visitor['risk_score'] ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Visit Info -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2">Visit Details</h5>

                <div class="row">
                    <div class="col-md-6">
                        <strong>Purpose:</strong>
                        <?= htmlspecialchars($visit['purpose'] ?? 'N/A') ?>
                    </div>

                    <div class="col-md-6">
                        <strong>Host:</strong>
                        <?= htmlspecialchars($visit['host_name'] ?? 'N/A') ?>
                    </div>

                    <div class="col-md-6 mt-2">
                        <strong>Check-In:</strong>
                        <?= htmlspecialchars($visit['checked_in_at'] ?? 'Not checked in') ?>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Issue Badge Form -->
            <form method="POST">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label">Badge Code</label>
                    <input 
                        type="text"
                        name="badge_code"
                        class="form-control"
                        placeholder="Enter or generate badge code"
                        required>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" 
                            class="btn btn-secondary"
                            id="generateBadgeBtn">
                        Auto Generate
                    </button>

                    <button type="submit" class="btn btn-success">
                        Issue Badge
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<script nonce="<?= csp_nonce() ?>">
function generateBadge() {
    const random = 'BDG-' + Math.floor(Math.random() * 1000000);
    document.querySelector('[name="badge_code"]').value = random;
}
// FIX: previously relied solely on an inline onclick="" attribute,
// silently blocked by this app's CSP — the button never actually did
// anything when clicked.
document.getElementById('generateBadgeBtn')?.addEventListener('click', generateBadge);
</script>