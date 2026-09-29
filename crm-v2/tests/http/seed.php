<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('ci_http') || ! str_ends_with(config('database.connections.mysql.database'), '_ci')) {
    throw new RuntimeException('HTTP fixture requires ci_http and isolated _ci database.');
}
App\Models\User::forceCreate([
    'name'=>'HTTP Test', 'username'=>'http_fixture', 'email'=>'http@example.test',
    'password'=>'Disposable-http-fixture-782!', 'role'=>'representative', 'active'=>true,
]);
