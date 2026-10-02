<?php

return [
    'max_kb' => 10240,
    'roles' => ['admin' => 'Yönetici', 'representative' => 'Temsilci'],
    'permissions' => [
        'admin' => ['view', 'create', 'update', 'delete', 'download', 'manage_permissions', 'manage_categories', 'view_private', 'manage_versions'],
        'representative' => ['view', 'download', 'view_private'],
    ],
    'retention' => 'Arşivlenen belgeler ve eski sürümler özel depoda süresiz korunur. Otomatik fiziksel silme yapılmaz.',
];
