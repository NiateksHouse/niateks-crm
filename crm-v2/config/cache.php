<?php

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'database' => ['driver' => 'database', 'connection' => null, 'table' => 'cache', 'lock_table' => 'cache_locks'],
        'array' => ['driver' => 'array', 'serialize' => false],
    ],
    'prefix' => 'koza_'.env('APP_ENV', 'production').'_cache_',
];
