<?php

return [
    'dashboard' => ['access'],

    'gatepass' => [
        'create', 'view', 'view_all', 'update', 'delete',
        'approve', 'checkin', 'checkout', 'print'
    ],

    'visitors' => [
        'create', 'view', 'view_all', 'update', 'update_all',
        'delete', 'blacklist', 'manage', 'issue_badge'
    ],

    'visits' => [
        'create', 'view', 'view_all', 'checkin', 'checkout'
    ],

    'badges' => ['view', 'issue', 'return'],

    'gates' => ['view', 'create', 'update', 'disable'],

    'devices' => ['view', 'approve', 'revoke', 'assign'],

    'scans' => ['view', 'scan', 'export'],

    'users' => ['create', 'view', 'update', 'disable'],

    'roles' => ['view', 'create', 'assign', 'update'],

    'settings' => ['view', 'update'],

    'reports' => ['view', 'export'],

    'audit' => ['view', 'export'],

    'delegation' => ['view', 'manage'],

    'approval' => ['view', 'approve', 'reject'],
];
