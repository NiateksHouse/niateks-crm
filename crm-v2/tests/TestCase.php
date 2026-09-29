<?php
namespace Tests;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fail before any destructive migration unless the dedicated CI DB is selected.
        $database = config('database.connections.mysql.database');
        if (! is_string($database) || ! str_ends_with($database, '_ci') || config('database.default') !== 'mysql') {
            throw new \RuntimeException('Tests require a dedicated MySQL database ending in _ci. Never use koza_test or live.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
    protected function user(string $username = 'rep', string $role = 'representative', bool $active = true): User
    {
        return User::forceCreate(['name' => $username, 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'Test-only-Password-782!', 'role' => $role, 'active' => $active]);
    }
    protected function companyData(): array
    {
        return ['roles'=>['customer'], 'name' => 'Example Textile', 'country_code' => 'GB', 'city' => 'London',
            'email' => 'hello@example.test', 'phone' => '+442012345678'];
    }
}
