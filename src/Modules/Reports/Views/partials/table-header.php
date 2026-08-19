<?php
$currentSort = $_GET['sort_by'] ?? '';
$currentDir  = $_GET['sort_dir'] ?? 'asc';

function sortLink($column, $label)
{
    $dir = ($_GET['sort_by'] ?? '') === $column && ($_GET['sort_dir'] ?? '') === 'asc'
        ? 'desc'
        : 'asc';

    $query = $_GET;
    $query['sort_by']  = $column;
    $query['sort_dir'] = $dir;

    $url = '?' . http_build_query($query);

    echo "<th><a href='{$url}'>{$label}</a></th>";
}


?>