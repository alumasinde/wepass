<h1 class="gp-heading">Create Gatepass</h1>

<?php if (!empty($error)): ?>
    <div class="gp-alert gp-alert--danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="gp-card">
    <form method="POST" action="/gatepasses">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="department_id" value="<?= htmlspecialchars($user['department_id'] ?? '') ?>">

        <!-- VISIT -->
        <div class="gp-field">
            <label class="gp-label">Visit</label>
            <select class="gp-select" name="visit_id">
                <option value="">— Select Visit —</option>
                <?php foreach ($visits ?? [] as $visit): ?>
                    <option value="<?= $visit['id'] ?>"
                        <?= (($_POST['visit_id'] ?? '') == $visit['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($visit['visitor_name'] . ' | ' . $visit['id_number']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- GATEPASS TYPE -->
        <div class="gp-field">
            <label class="gp-label">Gatepass Type <span class="gp-required">*</span></label>
            <select class="gp-select" name="gatepass_type_id" required>
                <option value="">— Select Type —</option>
                <?php foreach (($types ?? []) as $type): ?>
                    <option value="<?= $type['id'] ?>"
                        <?= (($_POST['gatepass_type_id'] ?? '') == $type['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="gp-hint">Whether this needs approval, and from whom, is determined by the type
                you select — not a choice you make here.</small>
        </div>

        <!-- PURPOSE -->
        <div class="gp-field">
            <label class="gp-label">Purpose <span class="gp-required">*</span></label>
            <textarea class="gp-textarea" name="purpose" required><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
        </div>

        <!-- RETURN DATE -->
        <div class="gp-field">
            <label class="gp-label">Expected Return Date <span class="gp-hint">(Optional)</span></label>
            <input class="gp-input" type="date" name="expected_return_date"
                   value="<?= htmlspecialchars($_POST['expected_return_date'] ?? '') ?>">
        </div>

        <!-- OPTIONS -->
        <div class="gp-options">
            <input type="hidden" name="is_returnable" value="0">
            <label class="gp-checkbox">
                <input type="checkbox" name="is_returnable" value="1"
                    <?= isset($_POST['is_returnable']) && $_POST['is_returnable'] == 1 ? 'checked' : '' ?>>
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Is Returnable <span class="gp-hint">(Gatepass Level)</span></span>
            </label>
        </div>

        <div class="gp-divider"></div>

        <!-- ITEMS -->
        <div class="gp-section-header">
            <h3 class="gp-section-title">Items</h3>
            <button type="button" class="gp-btn gp-btn--outline" id="gpAddItemBtn">
                <i class="fa-solid fa-plus"></i> Add Item
            </button>
        </div>

        <div id="items-wrapper">
            <?php
            $oldItems = $_POST['items'] ?? [
                ['item_name' => '', 'description' => '', 'quantity' => 1, 'serial_number' => '', 'is_returnable' => 0]
            ];
            ?>
            <?php foreach ($oldItems as $index => $item): ?>
                <div class="gp-item">
                    <div class="gp-item__fields">
                        <div class="gp-field gp-field--grow-2">
                            <label class="gp-label">Item Name <span class="gp-required">*</span></label>
                            <input class="gp-input" type="text" name="items[<?= $index ?>][item_name]"
                                   value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" required>
                        </div>

                        <div class="gp-field gp-field--grow-2">
                            <label class="gp-label">Description</label>
                            <input class="gp-input" type="text" name="items[<?= $index ?>][description]"
                                   value="<?= htmlspecialchars($item['description'] ?? '') ?>">
                        </div>

                        <div class="gp-field gp-field--shrink">
                            <label class="gp-label">Qty</label>
                            <input class="gp-input" type="number" name="items[<?= $index ?>][quantity]"
                                   value="<?= htmlspecialchars($item['quantity'] ?? 1) ?>" min="1">
                        </div>

                        <div class="gp-field gp-field--grow-1">
                            <label class="gp-label">Serial No.</label>
                            <input class="gp-input" type="text" name="items[<?= $index ?>][serial_number]"
                                   value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="gp-item__footer">
                        <input type="hidden" name="items[<?= $index ?>][is_returnable]" value="0">
                        <label class="gp-checkbox">
                            <input type="checkbox" name="items[<?= $index ?>][is_returnable]" value="1"
                                <?= isset($item['is_returnable']) && $item['is_returnable'] ? 'checked' : '' ?>>
                            <span class="gp-checkbox__box"></span>
                            <span class="gp-checkbox__label">Returnable Item</span>
                        </label>

                        <button type="button" class="gp-btn gp-btn--danger gp-btn--sm gp-item-remove-btn">
                            <i class="fa-solid fa-trash-can"></i> Remove
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="gp-divider"></div>

        <!-- ACTIONS -->
        <div class="gp-actions">
            <button type="submit" class="gp-btn gp-btn--primary">
                <i class="fa-solid fa-save"></i> Save Gatepass
            </button>
            <a href="/gatepasses" class="gp-btn gp-btn--secondary">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
        </div>

    </form>
</div>

<script nonce="<?= csp_nonce() ?>">
let itemIndex = <?= count($oldItems) ?>;

function addItem() {
    const wrapper = document.getElementById('items-wrapper');
    const row = document.createElement('div');
    row.classList.add('gp-item');
    row.innerHTML = `
        <div class="gp-item__fields">
            <div class="gp-field gp-field--grow-2">
                <label class="gp-label">Item Name <span class="gp-required">*</span></label>
                <input class="gp-input" type="text" name="items[${itemIndex}][item_name]" required>
            </div>
            <div class="gp-field gp-field--grow-2">
                <label class="gp-label">Description</label>
                <input class="gp-input" type="text" name="items[${itemIndex}][description]">
            </div>
            <div class="gp-field gp-field--shrink">
                <label class="gp-label">Qty</label>
                <input class="gp-input" type="number" name="items[${itemIndex}][quantity]" value="1" min="1">
            </div>
            <div class="gp-field gp-field--grow-1">
                <label class="gp-label">Serial No.</label>
                <input class="gp-input" type="text" name="items[${itemIndex}][serial_number]">
            </div>
        </div>
        <div class="gp-item__footer">
            <input type="hidden" name="items[${itemIndex}][is_returnable]" value="0">
            <label class="gp-checkbox">
                <input type="checkbox" name="items[${itemIndex}][is_returnable]" value="1">
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Returnable Item</span>
            </label>
            <button type="button" class="gp-btn gp-btn--danger gp-btn--sm gp-item-remove-btn">
                <i class="fa-solid fa-trash-can"></i> Remove
            </button>
        </div>
    `;
    wrapper.appendChild(row);
    itemIndex++;
}

function removeItem(button) {
    button.closest('.gp-item').remove();
}

document.getElementById('gpAddItemBtn')?.addEventListener('click', addItem);

// FIX: Remove buttons previously used inline onclick="removeItem(this)"
// — both on server-rendered rows AND baked into the JS template string
// above (inline attributes are blocked by this app's CSP regardless of
// whether they were server-rendered or injected via innerHTML). Event
// delegation on the wrapper handles both existing and future rows with
// one listener, instead of needing to (re-)bind each button individually.
document.getElementById('items-wrapper')?.addEventListener('click', function (event) {
    const btn = event.target.closest('.gp-item-remove-btn');
    if (btn) {
        removeItem(btn);
    }
});
</script>
