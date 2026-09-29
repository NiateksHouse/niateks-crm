<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('ci_http') || ! str_ends_with(config('database.connections.mysql.database'), '_ci')) {
    throw new RuntimeException('HTTP fixture requires ci_http and isolated _ci database.');
}
User::forceCreate([
    'name' => 'HTTP Test', 'username' => 'http_fixture', 'email' => 'http@example.test',
    'password' => 'Disposable-http-fixture-782!', 'role' => 'representative', 'active' => true,
]);

App\Models\AccountInvitation::create([
    'username' => 'http_invited', 'email' => 'http-invited@example.test', 'name' => 'HTTP Invited',
    'role' => 'representative', 'can_view_all_finance' => false,
    'token_hash' => hash('sha256', str_repeat('f', 64)), 'expires_at' => now()->addHour(),
]);
