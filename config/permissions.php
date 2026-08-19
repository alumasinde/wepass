<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Blueprint
    |--------------------------------------------------------------------------
    |
    | module => actions[]
    |
    | These are used ONLY for seeding the database.
    |
    */

    'dashboard' => [
        'access'
    ],

    'gatepass' => [
        'create',
        'view',
        'view_all', // see/act on every department's gatepasses, not just your own — replaces the old hardcoded role-name check ('admin'/'General Manager'/'superadmin') in GatepassService::list()
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

    'users' => [
        'create',
        'view',
        'update',
        'disable'
    ],

    'roles' => [
        'create',
        'assign',
        'update'
    ],

    'settings' => [
        'update'
    ],

    'audit' => [
        'view'
    ]

];
