<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true],
        'documents' => ['driver' => 'local', 'root' => storage_path('app/private/documents'), 'visibility' => 'private', 'throw' => true, 'permissions' => ['file' => ['public' => 0600, 'private' => 0600], 'dir' => ['public' => 0700, 'private' => 0700]]],
    ],
];
