<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;

class VisitorReportRepository extends BaseRepository
{
    protected string $table = 'visitors';
    protected string $alias = 'v';

    protected array $searchable = [
        'v.first_name',
        'v.last_name',
        'v.phone',
        'v.email'
    ];

    protected array $filterable = [
        'company_id',
        'blacklisted'
    ];

    protected array $sortable = [
        'created_at',
        'first_name'
    ];
}