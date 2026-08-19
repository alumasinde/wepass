<?php
/**
 * Global Search Partial
 *
 * Usage:
 *   include 'global-search.php';
 *
 * Variables (all optional — sane defaults apply):
 *   $action      string  Form action URL. Defaults to current URL without query string.
 *   $placeholder string  Input placeholder text. Defaults to 'Search…'.
 *   $searchKey   string  GET param name. Defaults to 'q' (matches Repository->list()).
 */

$actionUrl   = htmlspecialchars($action ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), ENT_QUOTES, 'UTF-8');
$placeholder = htmlspecialchars($placeholder ?? 'Search…', ENT_QUOTES, 'UTF-8');
$searchKey   = preg_replace('/[^a-zA-Z0-9_]/', '', $searchKey ?? 'q');

$currentQuery = trim($_GET[$searchKey] ?? '');

// Preserve all existing GET params except the search key (page too — reset on new search)
$carry = $_GET ?? [];
unset($carry[$searchKey], $carry['page']);

/** Recursively emit hidden inputs (supports nested arrays) */
function renderHiddenInputs(array $params, string $prefix = ''): void
{
    foreach ($params as $key => $value) {
        $name = $prefix ? "{$prefix}[{$key}]" : $key;
        if (is_array($value)) {
            renderHiddenInputs($value, $name);
        } elseif (is_scalar($value)) {
            echo '<input type="hidden"'
               . ' name="'  . htmlspecialchars($name,  ENT_QUOTES, 'UTF-8') . '"'
               . ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
}

$clearUrl = $actionUrl . ($carry ? '?' . http_build_query($carry) : '');
?>

<form method="GET" action="<?= $actionUrl ?>" class="global-search" role="search">

    <?php renderHiddenInputs($carry); ?>

    <div class="search-group">
        <input
            type="search"
            name="<?= $searchKey ?>"
            value="<?= htmlspecialchars($currentQuery, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="<?= $placeholder ?>"
            class="search-input"
            autocomplete="off"
            aria-label="<?= $placeholder ?>"
        >

        <button type="submit" class="search-btn" aria-label="Submit search">
            Search
        </button>

        <?php if ($currentQuery !== ''): ?>
            <a href="<?= htmlspecialchars($clearUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="clear-btn"
               aria-label="Clear search">
                Clear
            </a>
        <?php endif; ?>
    </div>

</form>