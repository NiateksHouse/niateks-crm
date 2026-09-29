<?php

namespace Tests;

use App\Models\User;
use App\Support\DisposableTestDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DisposableTestDatabase::assertSafe();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function user(string $username = 'rep', string $role = 'representative', bool $active = true): User
    {
        return User::forceCreate(['name' => $username, 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'Test-only-Password-782!', 'role' => $role, 'active' => $active]);
    }

    protected function companyData(): array
    {
        return ['roles' => ['customer'], 'name' => 'Example Textile', 'country_code' => 'GB', 'city' => 'London',
            'email' => 'hello@example.test', 'phone' => '+442012345678'];
    }
}
