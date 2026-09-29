<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    public function test_admin_creation_does_not_implicitly_grant_finance_and_password_is_hashed(): void
    {
        $this->artisan('koza:create-user', ['username' => 'manager', 'email' => 'manager@example.test', '--name' => 'Manager', '--admin' => true])
            ->expectsQuestion('Yeni parola', 'Fixture-only-Password-928!')
            ->expectsQuestion('Parolayı tekrar girin', 'Fixture-only-Password-928!')
            ->assertSuccessful();
        $user = User::firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertFalse($user->can_view_all_finance);
        $this->assertTrue(Hash::check('Fixture-only-Password-928!', $user->password));
        $this->assertNotSame('Fixture-only-Password-928!', $user->password);
    }

    public function test_explicit_finance_admin_and_login(): void
    {
        $this->artisan('koza:create-user', ['username' => 'finance', 'email' => 'finance@example.test', '--name' => 'Finance', '--admin' => true, '--all-finance' => true])
            ->expectsQuestion('Yeni parola', 'Fixture-only-Password-928!')
            ->expectsQuestion('Parolayı tekrar girin', 'Fixture-only-Password-928!')
            ->assertSuccessful();
        $this->assertTrue(User::firstOrFail()->can_view_all_finance);
        $this->post('/login', ['username' => 'finance', 'password' => 'Fixture-only-Password-928!'])->assertRedirect('/companies');
        $this->assertAuthenticated();
    }

    public function test_duplicate_account_is_not_reset(): void
    {
        $original = $this->user('existing');
        $hash = $original->password;
        $this->artisan('koza:create-user', ['username' => 'existing', 'email' => 'new@example.test', '--name' => 'New name', '--admin' => true])->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $original->fresh()->password);
        $this->assertSame('representative', $original->fresh()->role);
    }

    public function test_noninteractive_creation_and_finance_for_representative_are_rejected(): void
    {
        $args = ['username' => 'new', 'email' => 'new@example.test', '--name' => 'New'];
        $this->artisan('koza:create-user', $args + ['--no-interaction' => true])->assertFailed();
        $this->artisan('koza:create-user', $args + ['--all-finance' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_mismatch_and_weak_password_do_not_create_accounts(): void
    {
        foreach ([['short', 'short'], ['Fixture-only-Password-928!', 'Different-Password-928!']] as [$password, $confirmation]) {
            $this->artisan('koza:create-user', ['username' => 'new', 'email' => 'new@example.test', '--name' => 'New'])
                ->expectsQuestion('Yeni parola', $password)
                ->expectsQuestion('Parolayı tekrar girin', $confirmation)
                ->assertFailed();
        }
        $this->assertDatabaseCount('users', 0);
    }
}
