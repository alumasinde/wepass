<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Blueprint
    |--------------------------------------------------------------------------
    |
    | module => actions[]
    |
    | These keys are the canonical authorization vocabulary. The
    | database stores module/action separately; application checks use
    | the normalized "module.action" form.
    |
    */

    'dashboard' => [
        'access'
    ],

    'gatepass' => [
        'create',
        'view',
        'view_all',
        'update',
        'delete',
        'approve',
        'checkin',
        'checkout',
        'print'
    ],

    'visitors' => [
        'create',
        'view',
        'update',
        'blacklist'
    ],

    'visits' => [
        'create',
        'view',
        'view_all',
        'checkin',
        'checkout'
    ],

    'badges' => [
        'view',
        'issue',
        'return'
    ],

    'users' => [
        'create',
        'view',
        'update',
        'disable'
    ],

    'roles' => [
        'view',
        'create',
        'assign',
        'update'
    ],

    'settings' => [
        'view',
        'update'
    ],

    'reports' => [
        'view',
        'export'
    ],

    'audit' => [
        'view',
        'export'
    ],

    'delegation' => [
        'view',
        'manage'
    ]

];
