<?php

namespace App\Core;

/**
 * QueryBuilder
 *
 * Stateless SQL fragment builder. All methods are pure functions — they receive
 * a SQL string + bindings array (by reference) and return the augmented SQL.
 *
 * Filter input is accepted in TWO shapes so callers never have to pre-format:
 *
 *   Flat (simple equality)
 *     ['status' => 'active', 'type' => 'visitor']
 *
 *   Structured (explicit operator)
 *     ['status' => ['value' => 'active', 'operator' => '=']]
 *     ['created_at' => ['value' => ['2024-01-01','2024-12-31'], 'operator' => 'BETWEEN']]
 *     ['role_id' => ['value' => [1,2,3], 'operator' => 'IN']]
 */
final class QueryBuilder
{
    // -------------------------------------------------------------------------
    // SEARCH
    // -------------------------------------------------------------------------

    /**
     * Multi-term LIKE search across whitelisted columns.
     *
     * Each whitespace-separated word must match at least one column (AND across
     * terms, OR across columns), so searching "john doe" finds rows that contain
     * both words somewhere in the searchable fields.
     *
     * @param string   $sql              Base SQL fragment (FROM … WHERE …)
     * @param string[] $searchableColumns Fully-qualified column names, e.g. ['t.name','t.email']
     * @param array    $bindings         PDO bindings array (passed by reference)
     * @param ?string  $searchQuery      Raw search string from the request
     * @param string   $paramPrefix      Unique prefix to avoid collisions when called multiple times
     */
    public static function applySearch(
        string $sql,
        array $searchableColumns,
        array &$bindings,
        ?string $searchQuery,
        string $paramPrefix = 'srch'
    ): string {
        $search = trim((string) $searchQuery);

        if ($search === '' || empty($searchableColumns)) {
            return $sql;
        }

        // Split on whitespace; filter empty tokens
        $terms = array_values(array_filter(preg_split('/\s+/', $search)));

        if (empty($terms)) {
            return $sql;
        }

        $termConditions = [];

        foreach ($terms as $tIndex => $term) {
            $columnConditions = [];

            foreach ($searchableColumns as $cIndex => $column) {
                // Unique param per (prefix, term, column) — safe for multiple calls
                $param = ":{$paramPrefix}_t{$tIndex}_c{$cIndex}";
                $columnConditions[] = "{$column} LIKE {$param}";
                $bindings[$param]   = "%{$term}%";
            }

            if (!empty($columnConditions)) {
                $termConditions[] = '(' . implode(' OR ', $columnConditions) . ')';
            }
        }

        if (empty($termConditions)) {
            return $sql;
        }

        $condition = '(' . implode(' AND ', $termConditions) . ')';

        return self::addWhereOrAnd($sql, $condition);
    }

    // -------------------------------------------------------------------------
    // FILTERS
    // -------------------------------------------------------------------------

    /**
     * Apply structured filters to the SQL fragment.
     *
     * Accepts a mixed filter array — each entry may be:
     *   - A scalar  →  treated as simple equality  { value: scalar, operator: '=' }
     *   - An array  →  expected to have 'value' key; 'operator' defaults to '='
     *
     * Supported operators: =  !=  >  >=  <  <=  LIKE  NOT LIKE  IN  BETWEEN  IS NULL  IS NOT NULL
     *
     * @param string   $sql            Base SQL fragment
     * @param array    $filters        Mixed filter array (flat or structured)
     * @param array    $bindings       PDO bindings (by reference)
     * @param string[] $allowedColumns Optional whitelist; if empty, all keys are allowed
     */
    public static function applyFilters(
        string $sql,
        array $filters,
        array &$bindings,
        array $allowedColumns = []
    ): string {
        foreach ($filters as $column => $raw) {

            // Security: column whitelist
            if (!empty($allowedColumns) && !in_array($column, $allowedColumns, true)) {
                continue;
            }

            // Normalise to structured shape
            [$value, $operator] = self::normaliseFilterEntry($raw);

            $operator = strtoupper($operator);

            // Safe param base (dots, dashes → underscores)
            $paramBase = ':f_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $column);

            $condition = self::buildFilterCondition(
                $column,
                $value,
                $operator,
                $paramBase,
                $bindings
            );

            if ($condition !== null) {
                $sql = self::addWhereOrAnd($sql, $condition);
            }
        }

        return $sql;
    }

    // -------------------------------------------------------------------------
    // SORT
    // -------------------------------------------------------------------------

    /**
     * Append ORDER BY safely.
     *
     * @param string   $sql            SQL fragment (no ORDER BY yet)
     * @param ?string  $sort           Column name to sort by
     * @param ?string  $dir            'asc' or 'desc'
     * @param string[] $allowedColumns Whitelist; falls back to created_at if not in list
     * @param string   $fallback       Default sort column
     */
    public static function applySort(
        string $sql,
        ?string $sort,
        ?string $dir,
        array $allowedColumns = [],
        string $fallback = 'created_at'
    ): string {
        if (
            empty($sort) ||
            (!empty($allowedColumns) && !in_array($sort, $allowedColumns, true))
        ) {
            $sort = $fallback;
        }

        $direction = strtolower((string) $dir) === 'asc' ? 'ASC' : 'DESC';

        return $sql . " ORDER BY {$sort} {$direction}";
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    /**
     * Normalise a filter entry (flat scalar OR structured array) into
     * a [value, operator] pair.
     */
    private static function normaliseFilterEntry(mixed $raw): array
    {
        // Flat scalar  →  simple equality
        if (!is_array($raw)) {
            return [$raw, '='];
        }

        // Structured array  →  must have 'value' key
        $value    = $raw['value']    ?? null;
        $operator = $raw['operator'] ?? '=';

        return [$value, $operator];
    }

    /**
     * Build a single SQL condition string and register bindings.
     * Returns null when the entry should be skipped.
     */
    private static function buildFilterCondition(
        string $column,
        mixed $value,
        string $operator,
        string $paramBase,
        array &$bindings
    ): ?string {
        switch ($operator) {

            // ── Null checks (no binding needed) ──────────────────────────────
            case 'IS NULL':
                return "{$column} IS NULL";

            case 'IS NOT NULL':
                return "{$column} IS NOT NULL";

            // ── IN list ───────────────────────────────────────────────────────
            case 'IN':
            case 'NOT IN':
                if (!is_array($value) || empty($value)) {
                    return null;
                }
                $placeholders = [];
                foreach (array_values($value) as $i => $v) {
                    $p = "{$paramBase}_{$i}";
                    $placeholders[] = $p;
                    $bindings[$p]   = $v;
                }
                return "{$column} {$operator} (" . implode(', ', $placeholders) . ")";

            // ── BETWEEN range ─────────────────────────────────────────────────
            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (!is_array($value) || count($value) !== 2) {
                    return null;
                }
                $p1 = "{$paramBase}_from";
                $p2 = "{$paramBase}_to";
                $bindings[$p1] = $value[0];
                $bindings[$p2] = $value[1];
                return "{$column} {$operator} {$p1} AND {$p2}";

            // ── LIKE / NOT LIKE ───────────────────────────────────────────────
            case 'LIKE':
            case 'NOT LIKE':
                if ($value === null || $value === '') {
                    return null;
                }
                // Auto-wrap with % if caller didn't
                $wrapped = str_contains((string) $value, '%') ? $value : "%{$value}%";
                $bindings[$paramBase] = $wrapped;
                return "{$column} {$operator} {$paramBase}";

            // ── Scalar comparisons ────────────────────────────────────────────
            default:
                $allowed = ['=', '!=', '<>', '>', '>=', '<', '<='];
                if (!in_array($operator, $allowed, true)) {
                    $operator = '=';
                }
                if ($value === null || $value === '') {
                    return null;
                }
                $bindings[$paramBase] = $value;
                return "{$column} {$operator} {$paramBase}";
        }
    }

    /**
     * Append a condition using WHERE if none exists yet, or AND otherwise.
     */
    public static function addWhereOrAnd(string $sql, string $condition): string
    {
        $sql = rtrim($sql);

        if (preg_match('/\bWHERE\b/i', $sql)) {
            return $sql . " AND {$condition}";
        }

        return $sql . " WHERE {$condition}";
    }
}