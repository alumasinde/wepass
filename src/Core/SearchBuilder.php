<?php

namespace App\Core;

/**
 * SearchBuilder  (thin façade — kept for backward compatibility)
 *
 * Previously read directly from $_GET, making it untestable and bypassed
 * by BaseRepository. It is now a clean, stateless façade over QueryBuilder.
 *
 * Prefer calling QueryBuilder::applySearch() directly in new code.
 * This class exists so any legacy call sites continue to compile.
 */
final class SearchBuilder
{
    /**
     * Apply a full-text LIKE search to an existing SQL fragment.
     *
     * Each word in $searchQuery must match at least one of the given columns
     * (AND across terms, OR across columns).
     *
     * @param string   $sql              SQL fragment (FROM … WHERE …)
     * @param string[] $searchableColumns Fully-qualified column names
     * @param array    $bindings         PDO bindings (passed by reference)
     * @param ?string  $searchQuery      The search string (pass $request->input('q'))
     * @param string   $paramPrefix      Unique prefix; change when calling twice in one query
     */
    public static function apply(
        string $sql,
        array $searchableColumns,
        array &$bindings,
        ?string $searchQuery = null,
        string $paramPrefix = 'srch'
    ): string {
        // Fall back to the GET param only when no explicit query is passed,
        // and only as a last resort — callers should pass the value explicitly.
        if ($searchQuery === null) {
            $searchQuery = $_GET['q'] ?? null;
        }

        return QueryBuilder::applySearch(
            $sql,
            $searchableColumns,
            $bindings,
            $searchQuery,
            $paramPrefix
        );
    }
}