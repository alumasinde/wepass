<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;

class GatepassReportRepository extends BaseRepository
{
    protected string $table = 'gatepasses';
    protected string $alias = 'g';

    protected array $searchable = [
        'g.gatepass_number',
        'g.purpose',
        'gs.name',
        'u.first_name',
        'u.last_name',
        'd.name'
    ];
    

    protected array $filterable = [
        'g.status_id',
        'g.department_id',
        'g.gatepass_type_id',
        'g.created_by'
    ];

    protected array $sortable = [
        'g.created_at',
        'g.gatepass_number',
        'gs.name',
        'u.first_name',
        'u.last_name',
        'd.name'
    ];

    protected function baseQuery(): string
    {
        return "
            FROM gatepasses g

            LEFT JOIN gatepass_statuses gs
                ON gs.id = g.status_id

            LEFT JOIN users u
                ON u.id = g.created_by

            LEFT JOIN departments d
                ON d.id = g.department_id
        ";
    }

   protected function selectColumns(): string
{
    return "
        SELECT
            g.*,
            gs.name AS status_name,
            u.first_name,
            u.last_name,
            d.name AS department_name
    ";
}
}