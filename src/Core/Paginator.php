<?php

namespace App\Core;

use PDO;

class Paginator
{
    private PDO $db;
    private string $baseSql;
    private array $bindings;
    private int $page;
    private int $perPage;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        PDO $db,
        string $baseSql,   // FULL SELECT WITHOUT LIMIT/OFFSET
        array $bindings,
        int $page = 1,
        int $perPage = 20
    ) {
        $this->db = $db;
        $this->baseSql = trim($baseSql);
        $this->bindings = $bindings;
        $this->page = max(1, $page);
        $this->perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Execute paginated query
     */
    public function paginate(string $dataSql): array
    {
        $total = $this->getTotal();
        $offset = ($this->page - 1) * $this->perPage;

        $sql = $dataSql . " LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $this->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $lastPage = $total > 0
            ? (int) ceil($total / $this->perPage)
            : 1;

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'total' => $total,
                'per_page' => $this->perPage,
                'current_page' => $this->page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $this->perPage, $total),
            ],
        ];
    }

    // ---------------------------------------------------------------------

    /**
     * FIXED: Proper COUNT using subquery wrapper
     */
    private function getTotal(): int
    {
        $sql = trim($this->baseSql);

        // Ensure it's a valid SELECT before wrapping
        if (!str_starts_with(strtoupper(ltrim($sql)), 'SELECT')) {
            throw new \RuntimeException(
                "Paginator baseSql must start with SELECT. Got: " . substr($sql, 0, 50)
            );
        }

        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM (
            {$sql}
        ) AS count_table
    ");

        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}