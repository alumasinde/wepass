<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;

class AuditReportRepository extends BaseRepository
{
    protected string $table = 'audit_logs';
    protected string $alias = 'a';

    protected array $searchable = [
        'a.action',
        'a.entity_type',
        'u.first_name',
        'u.last_name',
        'a.message'
    ];

    protected array $filterable = [
        'a.entity_type',
        'a.entity_id',
        'a.user_id'
    ];

    protected array $sortable = [
        'a.created_at',
        'u.first_name',
        'a.action'
    ];

    /**
     * ✅ JOIN users to replace user_id with name
     */
    protected function baseQuery(): string
    {
        return "
            FROM audit_logs a

            LEFT JOIN users u
                ON u.id = a.user_id
        ";
    }

    /**
     * ✅ Select readable fields
     */
    protected function selectColumns(): string
    {
        return "
            SELECT
                a.*,

                -- User info
                u.first_name,
                u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
        ";
    }
}