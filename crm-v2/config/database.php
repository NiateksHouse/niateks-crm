<?php

return [
    'default' => 'mysql',
    'connections' => ['mysql' => [
        'driver' => 'mysql', 'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'), 'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD'),
        'unix_socket' => env('DB_SOCKET', ''), 'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'prefix_indexes' => true,
        'strict' => true, 'engine' => 'InnoDB',
    ]],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
