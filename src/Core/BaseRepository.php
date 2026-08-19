<?php

namespace App\Core;

use PDO;

/**
 * BaseRepository — per-database isolation model.
 *
 * tenant_id column filtering has been removed from all methods.
 * Isolation is guaranteed at the database connection level (each tenant
 * has their own database configured in config.ini [database]).
 *
 * Subclasses declare:
 *   protected string $table   — primary table name
 *   protected string $alias   — SQL alias (default 't')
 *   protected array  $searchable  — fully-qualified columns for LIKE search
 *   protected array  $filterable  — whitelisted filter keys
 *   protected array  $sortable    — whitelisted sort column names
 *   protected string $defaultSort — fallback ORDER BY column
 *
 * Override baseQuery() and selectColumns() for JOINs / computed fields.
 */
abstract class BaseRepository
{
    protected PDO    $db;
    protected string $table;
    protected string $alias       = 't';
    protected array  $searchable  = [];
    protected array  $filterable  = [];
    protected array  $sortable    = [];
    protected string $defaultSort = 'created_at';

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
     * $params keys:
     *   search / q  — search string
     *   filters     — associative filter array (flat or structured)
     *   sort_by     — whitelisted column name
     *   sort_dir    — 'asc' | 'desc'
     *   page        — page number (default 1)
     *   per_page    — rows per page (default 10, max 100)
     */
    public function list(array $params = []): array
    {
        $search  = $params['search'] ?? $params['q'] ?? null;
        $filters = $params['filters'] ?? [];
        $sortBy  = $params['sort_by'] ?? $this->defaultSort;
        $sortDir = strtolower($params['sort_dir'] ?? 'desc');
        $page    = (int) ($params['page']     ?? 1);
        $perPage = (int) ($params['per_page'] ?? 10);

        $bindings = [];

        $sql = "{$this->selectColumns()} {$this->baseQuery()}";

        // ── Search ────────────────────────────────────────────────────────────
        if (!empty($this->searchable) && !empty($search)) {
            $sql = QueryBuilder::applySearch($sql, $this->searchable, $bindings, $search);
        }

        // ── Filters ───────────────────────────────────────────────────────────
        $normalizedFilters = $this->normaliseFilters($filters);
        if (!empty($normalizedFilters)) {
            $sql = QueryBuilder::applyFilters($sql, $normalizedFilters, $bindings);
        }

        // ── Sort ──────────────────────────────────────────────────────────────
        if (!in_array($sortBy, $this->sortable, true)) {
            $sortBy = $this->defaultSort;
        }

        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';
        $dataSql = $sql . " ORDER BY {$sortBy} {$sortDir}";

        // ── Paginate ──────────────────────────────────────────────────────────
        $paginator = new Paginator($this->db, $sql, $bindings, $page, $perPage);

        return $paginator->paginate($dataSql);
    }

    /**
     * Simple total row count for the table (no filters applied).
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

    protected function selectColumns(): string
    {
        return "SELECT {$this->alias}.*";
    }

    protected function baseQuery(): string
    {
        return "FROM {$this->table} {$this->alias}";
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    public function begin(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // =========================================================================
    // FILTER NORMALISATION
    // =========================================================================

    private function normaliseFilters(array $rawFilters): array
    {
        if (empty($rawFilters) || empty($this->filterable)) {
            return [];
        }

        // Build requestKey → sqlColumn map
        $columnMap = [];
        foreach ($this->filterable as $key => $value) {
            if (is_int($key)) {
                $columnMap[$value] = $value; // indexed: key = column name
            } else {
                $columnMap[$key] = $value;   // associative: key → explicit column
            }
        }

        $result = [];
        foreach ($rawFilters as $requestKey => $entry) {
            if (!array_key_exists($requestKey, $columnMap)) {
                continue;
            }

            $sqlColumn = $columnMap[$requestKey];

            if (!is_array($entry) || !array_key_exists('value', $entry)) {
                $result[$sqlColumn] = ['value' => $entry, 'operator' => '='];
            } else {
                $result[$sqlColumn] = $entry;
            }
        }

        return $result;
    }
}
