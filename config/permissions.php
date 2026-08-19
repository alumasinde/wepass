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

    'users' => ['create', 'view', 'update', 'disable'],

    'roles' => ['view', 'create', 'assign', 'update'],

    'settings' => ['view', 'update'],

    'reports' => ['view', 'export'],

    'audit' => ['view', 'export'],

    'delegation' => ['view', 'manage'],

    // ApprovalPolicy already relies on these normalized capabilities.
    'approval' => ['view', 'approve', 'reject'],
];
