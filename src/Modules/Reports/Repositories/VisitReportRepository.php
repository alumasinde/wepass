<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;

class VisitReportRepository extends BaseRepository
{
    protected string $table = 'visits';
    protected string $alias = 'vs';

    protected array $searchable = [
        'vs.purpose',
        'v.first_name',
        'v.last_name',
        'u.first_name',
        'u.last_name',
        'd.name',
        'vst.name',
        'vt.name'
    ];

    protected array $filterable = [
        'vs.visit_status_id',
        'vs.department_id',
        'vs.visit_type_id'
    ];

    protected array $sortable = [
        'vs.checkin_time',
        'vs.created_at',
        'v.first_name',
        'u.first_name',
        'd.name',
        'vst.name'
    ];

    /**
     * ✅ JOIN all related tables
     */
    protected function baseQuery(): string
    {
        return "
            FROM visits vs

            LEFT JOIN visitors v
                ON v.id = vs.visitor_id

            LEFT JOIN users u
                ON u.id = vs.host_user_id

            LEFT JOIN departments d
                ON d.id = vs.department_id

            LEFT JOIN visit_statuses vst
                ON vst.id = vs.visit_status_id

            LEFT JOIN visit_types vt
                ON vt.id = vs.visit_type_id
        ";
    }

    /**
     * ✅ Select readable names
     */
    protected function selectColumns(): string
    {
        return "
            SELECT
                vs.*,

                -- Visitor
                v.first_name AS visitor_first_name,
                v.last_name AS visitor_last_name,
                CONCAT(v.first_name, ' ', v.last_name) AS visitor_name,

                -- Host
                u.first_name AS host_first_name,
                u.last_name AS host_last_name,
                CONCAT(u.first_name, ' ', u.last_name) AS host_name,

                -- Department
                d.name AS department_name,

                -- Status
                vst.name AS status_name,

                -- Type
                vt.name AS visit_type_name
        ";
    }
}