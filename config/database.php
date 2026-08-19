<?php

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [

            'dsn' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                config('DB_HOST', '127.0.0.1'),
                config('DB_PORT', '3306'),
                config('DB_DATABASE', 'test'),
                config('DB_CHARSET', 'utf8mb4')
            ),

            'username' => config('DB_USERNAME', ''),
            'password' => config('DB_PASSWORD', ''),

            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],

    ],

];