<?php /** @var array $theme */ ?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-palette"></i>
                Theme
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings" class="btn btn-secondary btn-sm">
                Back
            </a>
        </div>
    </div>

    <div class="card-body">

        <div class="alert alert-info">
            Configure your organisation's branding. These colours are applied across the application for every user in this tenant. System colours such as Success, Warning and Danger remain consistent for usability.
        </div>

        <form method="POST" action="/settings/theme">

            <?= csrf_field() ?>

            <div class="form-grid">

                <?php
                $fields = [

                    [
                        'key' => 'primary_color',
                        'label' => 'Primary Colour',
                        'help' => 'Buttons, links, active navigation and focus states.'
                    ],

                    [
                        'key' => 'secondary_color',
                        'label' => 'Secondary Colour',
                        'help' => 'Secondary buttons and supporting UI elements.'
                    ],

                    [
                        'key' => 'header_bg',
                        'label' => 'Header Background',
                        'help' => 'Top navigation bar.'
                    ],

                    [
                        'key' => 'sidebar_bg',
                        'label' => 'Sidebar Background',
                        'help' => 'Left navigation panel.'
                    ],

                    [
                        'key' => 'sidebar_text',
                        'label' => 'Sidebar Text',
                        'help' => 'Sidebar text and icons.'
                    ],

                    [
                        'key' => 'page_bg',
                        'label' => 'Page Background',
                        'help' => 'Main application background.'
                    ],

                ];
                ?>

                <?php foreach ($fields as $field): ?>

                    <?php
                    $value = htmlspecialchars($theme[$field['key']] ?? '');
                    $id = $field['key'];
                    ?>

                    <div class="form-group">

                        <label for="<?= $id ?>">
                            <?= $field['label'] ?>
                        </label>

                        <small class="form-text text-muted">
                            <?= $field['help'] ?>
                        </small>

                        <div class="color-field">

                            <input
                                type="color"
                                id="<?= $id ?>"
                                name="<?= $id ?>"
                                value="<?= $value ?>"
                                oninput="document.getElementById('<?= $id ?>_hex').value=this.value"
                            >

                            <input
                                type="text"
                                id="<?= $id ?>_hex"
                                class="form-control"
                                value="<?= $value ?>"
                                maxlength="7"
                                oninput="document.getElementById('<?= $id ?>').value=this.value"
                            >

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    Save Theme
                </button>
            </div>

        </form>

        <div class="section-divider"></div>

        <form
            method="POST"
            action="/settings/theme/reset"
            data-confirm="Reset all theme settings to the system defaults?"
        >

            <?= csrf_field() ?>

            <button class="btn btn-secondary" type="submit">
                Reset to Defaults
            </button>

        </form>

    </div>

</div>

<style>
.color-field{
    display:flex;
    align-items:center;
    gap:var(--space-2,8px);
}

.color-field input[type="color"]{
    width:46px;
    height:40px;
    padding:2px;
    border:1px solid var(--color-input-border,#CBD5E1);
    border-radius:var(--radius-sm,6px);
    background:#fff;
    cursor:pointer;
}

.color-field input[type="text"]{
    flex:1;
    text-transform:uppercase;
}
</style>