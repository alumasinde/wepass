<?php
/*
Expected variables:
$currentPage (int)
$totalPages (int)
$baseUrl (string)
$queryParams (array) optional
*/

if (($totalPages ?? 1) <= 1) {
    return;
}

$currentPage = (int)($currentPage ?? 1);
$totalPages  = (int)($totalPages ?? 1);
$baseUrl     = $baseUrl ?? '';
$queryParams = $queryParams ?? [];

function buildPageUrl($baseUrl, $page, $queryParams = [])
{
    $queryParams['page'] = $page;
    return $baseUrl . '?' . http_build_query($queryParams);
}
?>

<nav class="pagination-wrapper">

    <!-- Previous -->
    <?php if ($currentPage > 1): ?>
        <a href="<?= buildPageUrl($baseUrl, $currentPage - 1, $queryParams) ?>"
           class="page-link">
            &laquo;
        </a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

        <a href="<?= buildPageUrl($baseUrl, $i, $queryParams) ?>"
           class="page-link <?= $i === $currentPage ? 'active' : '' ?>">
            <?= $i ?>
        </a>

    <?php endfor; ?>

    <!-- Next -->
    <?php if ($currentPage < $totalPages): ?>
        <a href="<?= buildPageUrl($baseUrl, $currentPage + 1, $queryParams) ?>"
           class="page-link">
            &raquo;
        </a>
    <?php endif; ?>

</nav>
