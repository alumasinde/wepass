<form method="GET" class="report-filters" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">

    <!-- 🔍 Search -->
    <div>
        <label style="display:block; font-size:12px;">Search</label>
        <input 
            type="text" 
            name="search" 
            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
            placeholder="Search..."
        >
    </div>

    <!-- 🔽 Dynamic Filters -->
    <?php if (!empty($filters ?? [])): ?>
        <?php foreach ($filters as $name => $options): ?>
            <div>
                <label style="display:block; font-size:12px;">
                    <?= ucfirst(str_replace('_', ' ', $name)) ?>
                </label>

                <select name="filters[<?= $name ?>]">
                    <option value="">All</option>

                    <?php foreach ($options as $value => $label): ?>
                        <option 
                            value="<?= htmlspecialchars($value) ?>"
                            <?= ($_GET['filters'][$name] ?? '') == $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ⚡ Actions -->
    <div style="display:flex; gap:6px;">
        <button type="submit" class="btn btn-primary">
            Apply
        </button>

        <!-- 🔄 Clear Filters -->
        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="btn btn-secondary">
            Clear
        </a>
    </div>

</form>