<?php

namespace App\Core;

use PDO;

/**
 * Repository — per-database data layer.
 *
 * Replaces: BaseRepository, QueryBuilder, SearchBuilder, Paginator.
 *
 * Per-database isolation: tenant_id column filtering has been removed.
 * Each tenant has their own database (configured in config.ini [database]),
 * so every query on this connection is already scoped to the correct tenant.
 *
 * ┌─────────────────────────────────────────────┐
 * │  HOW TO USE IN A SUBCLASS                   │
 * ├─────────────────────────────────────────────┤
 * │  class GatepassRepository extends Repository│
 * │  {                                          │
 * │    protected string $table = 'gatepasses';  │
 * │    protected string $alias = 'g';           │
 * │                                             │
 * │    protected array $searchable = [          │
 * │        'g.reference_no', 'g.visitor_name',  │
 * │    ];                                       │
 * │                                             │
 * │    protected array $filterable = [          │
 * │        'status',           // indexed       │
 * │        'type' => 'g.type', // explicit col  │
 * │    ];                                       │
 * │                                             │
 * │    protected array $sortable = [            │
 * │        'created_at', 'visitor_name',        │
 * │    ];                                       │
 * │                                             │
 * │    // Optional overrides:                   │
 * │    protected function baseQuery(): string { │
 * │        return "FROM gatepasses g            │
 * │                JOIN departments d           │
 * │                  ON d.id = g.dept_id";      │
 * │    }                                        │
 * │                                             │
 * │    protected function selectColumns(): string { │
 * │        return "SELECT g.*, d.name AS dept"; │
 * │    }                                        │
 * │  }                                          │
 * └─────────────────────────────────────────────┘
 *
 * Then call:
 *   $repo->list($request->all());
 *
 * Expected $params keys:
 *   q / search   — full-text search string (both accepted)
 *   page         — page number (default 1)
 *   per_page     — rows per page (default 10, hard-capped at 100)
 *   sort_by      — column name (whitelisted against $sortable)
 *   sort_dir     — 'asc' or 'desc'
 *   filters      — associative array of filter values, flat or structured:
 *                    flat:       ['status' => 'active']
 *                    structured: ['status' => ['value'=>'active','operator'=>'=']]
 *                    range:      ['created_at' => ['value'=>['2024-01-01','2024-12-31'],'operator'=>'BETWEEN']]
 *                    list:       ['role_id' => ['value'=>[1,2,3],'operator'=>'IN']]
 */
abstract class Repository
{
    // ── Connection ───────────────────────────────────────────────────────────

    protected PDO $db;

    // ── Subclass configuration ───────────────────────────────────────────────

    /** Primary table name */
    protected string $table;

    /** SQL alias used in queries */
    protected string $alias = 't';

    /**
     * Fully-qualified columns for LIKE search.
     * @var string[]   e.g. ['t.name', 't.email']
     */
    protected array $searchable = [];

    /**
     * Whitelisted filter keys.
     *
     *   Indexed:      ['status', 'type']
     *                 → request key = SQL column with alias prefix (t.status, t.type)
     *
     *   Associative:  ['dept' => 'd.id']
     *                 → request key 'dept' maps to SQL column 'd.id'
     *
     * @var array<int|string, string>
     */
    protected array $filterable = [];

    /**
     * Whitelisted sort columns (bare name, no alias).
     * @var string[]
     */
    protected array $sortable = [];

    /** Fallback sort column when $sortBy is not whitelisted */
    protected string $defaultSort = 'created_at';

    /** Hard cap on per_page */
    private const MAX_PER_PAGE = 100;

    // ── Boot ─────────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Paginated, searchable, filterable listing.
     *
     * Per-database isolation: no tenantId parameter needed.
     * The active DB connection is already scoped to the correct tenant.
     *
     * @param  array $params   {q, search, page, per_page, sort_by, sort_dir, filters}
     * @return array{data: array, meta: array}
     */
    public function list(array $params = []): array
    {
        // ── Parameters ───────────────────────────────────────────────────────
        $search  = trim((string) ($params['q'] ?? $params['search'] ?? ''));
        $filters = $params['filters'] ?? [];
        $sortBy  = $params['sort_by'] ?? $this->defaultSort;
        $sortDir = strtolower($params['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = max(1, min((int) ($params['per_page'] ?? 10), self::MAX_PER_PAGE));

        // ── Base SQL ─────────────────────────────────────────────────────────
        $bindings = [];
        $sql      = "{$this->selectColumns()} {$this->baseQuery()}";

        // ── Search ───────────────────────────────────────────────────────────
        if ($search !== '' && !empty($this->searchable)) {
            $sql = $this->applySearch($sql, $bindings, $search);
        }

        // ── Filters ──────────────────────────────────────────────────────────
        if (!empty($filters)) {
            $sql = $this->applyFilters($sql, $bindings, $filters);
        }

        // ── Sort ─────────────────────────────────────────────────────────────
        if (!in_array($sortBy, $this->sortable, true)) {
            $sortBy = $this->defaultSort;
        }
        $orderBy = " ORDER BY {$this->alias}.{$sortBy} {$sortDir}";

        // ── Count (reuse same FROM+WHERE, strip SELECT) ───────────────────────
        $fromWhere = $this->stripSelect($sql);
        $total     = $this->queryCount($fromWhere, $bindings);

        // ── Data ─────────────────────────────────────────────────────────────
        $offset  = ($page - 1) * $perPage;
        $dataSql = $sql . $orderBy . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($dataSql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Simple total row count (no filters applied).
     *
     * Per-database isolation: no tenantId parameter needed.
     */
    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // HOOKS — override in subclasses
    // =========================================================================

    /**
     * FROM … fragment. Override to add JOINs or extra base conditions.
     * Do NOT include WHERE here.
     */
    protected function baseQuery(): string
    {
        return "FROM {$this->table} {$this->alias}";
    }

    /**
     * SELECT projection. Override to add computed fields, joined columns, etc.
     */
    protected function selectColumns(): string
    {
        return "SELECT {$this->alias}.*";
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    public function begin(): void    { $this->db->beginTransaction(); }
    public function commit(): void   { $this->db->commit(); }
    public function rollback(): void { if ($this->db->inTransaction()) $this->db->rollBack(); }

    // =========================================================================
    // INTERNAL — search, filter, count
    // =========================================================================

    /**
     * Append multi-term LIKE conditions.
     * Each word must match at least one searchable column (AND terms, OR columns).
     */
    private function applySearch(string $sql, array &$bindings, string $search): string
    {
        $terms = array_values(array_filter(preg_split('/\s+/', $search)));
        if (empty($terms)) return $sql;

        $termClauses = [];
        foreach ($terms as $ti => $term) {
            $colClauses = [];
            foreach ($this->searchable as $ci => $col) {
                $param            = ":srch_t{$ti}_c{$ci}";
                $colClauses[]     = "{$col} LIKE {$param}";
                $bindings[$param] = "%{$term}%";
            }
            if ($colClauses) {
                $termClauses[] = '(' . implode(' OR ', $colClauses) . ')';
            }
        }

        return empty($termClauses)
            ? $sql
            : $this->and($sql, '(' . implode(' AND ', $termClauses) . ')');
    }

    /**
     * Append whitelisted filter conditions.
     *
     * Supported operators: =  !=  <>  >  >=  <  <=  LIKE  NOT LIKE
     *                      IN  NOT IN  BETWEEN  NOT BETWEEN  IS NULL  IS NOT NULL
     */
    private function applyFilters(string $sql, array &$bindings, array $rawFilters): string
    {
        // Build requestKey → sqlColumn map
        $colMap = [];
        foreach ($this->filterable as $k => $v) {
            if (is_int($k)) {
                $colMap[$v] = "{$this->alias}.{$v}"; // indexed
            } else {
                $colMap[$k] = $v;                    // associative
            }
        }

        foreach ($rawFilters as $key => $raw) {
            if (!array_key_exists($key, $colMap)) continue;

            $col       = $colMap[$key];
            $paramBase = ':f_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $col);

            if (!is_array($raw) || !array_key_exists('value', $raw)) {
                [$value, $op] = [$raw, '='];
            } else {
                $value = $raw['value'];
                $op    = strtoupper($raw['operator'] ?? '=');
            }

            $clause = $this->buildClause($col, $value, $op, $paramBase, $bindings);
            if ($clause !== null) {
                $sql = $this->and($sql, $clause);
            }
        }

        return $sql;
    }

    /**
     * Build a single SQL condition and register its bindings.
     * Returns null when the entry should be skipped.
     */
    private function buildClause(
        string $col,
        mixed  $value,
        string $op,
        string $base,
        array  &$bindings
    ): ?string {
        switch ($op) {
            case 'IS NULL':     return "{$col} IS NULL";
            case 'IS NOT NULL': return "{$col} IS NOT NULL";

            case 'IN':
            case 'NOT IN':
                if (!is_array($value) || empty($value)) return null;
                $phs = [];
                foreach (array_values($value) as $i => $v) {
                    $p             = "{$base}_{$i}";
                    $phs[]         = $p;
                    $bindings[$p]  = $v;
                }
                return "{$col} {$op} (" . implode(', ', $phs) . ")";

            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (!is_array($value) || count($value) !== 2) return null;
                $bindings["{$base}_from"] = $value[0];
                $bindings["{$base}_to"]   = $value[1];
                return "{$col} {$op} {$base}_from AND {$base}_to";

            case 'LIKE':
            case 'NOT LIKE':
                if ($value === null || $value === '') return null;
                $wrapped         = str_contains((string) $value, '%') ? $value : "%{$value}%";
                $bindings[$base] = $wrapped;
                return "{$col} {$op} {$base}";

            default:
                if (!in_array($op, ['=', '!=', '<>', '>', '>=', '<', '<='], true)) $op = '=';
                if ($value === null || $value === '') return null;
                $bindings[$base] = $value;
                return "{$col} {$op} {$base}";
        }
    }

    /** Append a condition with WHERE or AND as appropriate. */
    private function and(string $sql, string $condition): string
    {
        $sql = rtrim($sql);
        return preg_match('/\bWHERE\b/i', $sql)
            ? "{$sql} AND {$condition}"
            : "{$sql} WHERE {$condition}";
    }

    /**
     * Strip the SELECT clause so we can wrap the rest in COUNT(*).
     */
    private function stripSelect(string $sql): string
    {
        return preg_replace('/^\s*SELECT\b.+?\bFROM\b/is', 'FROM', $sql);
    }

    /** Run a COUNT(*) against the FROM+WHERE fragment. */
    private function queryCount(string $fromWhere, array $bindings): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) {$fromWhere}");
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
