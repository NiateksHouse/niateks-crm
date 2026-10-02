<?php

namespace Tests\Feature;

use App\Models\AccountInvitation;
use App\Models\User;
use App\Services\KozaAccess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = sys_get_temp_dir().'/koza-invitations-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->temporaryStorage.'/app/private', 0700);
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function invitation(string $code, array $changes = []): AccountInvitation
    {
        return AccountInvitation::create(array_replace([
            'username' => 'invited', 'email' => 'invited@example.test', 'name' => 'Invited',
            'role' => 'representative', 'can_view_all_finance' => false,
            'token_hash' => hash('sha256', $code), 'expires_at' => now()->addHours(24),
        ], $changes));
    }

    private function submission(string $code): array
    {
        return ['invitation_code' => $code, 'password' => 'Fixture-only-Activation-872!', 'password_confirmation' => 'Fixture-only-Activation-872!'];
    }

    public function test_private_bootstrap_is_noninteractive_idempotent_and_stores_only_token_hashes(): void
    {
        $users = [
            ['username' => 'first', 'email' => 'first@example.test', 'name' => 'First', 'role' => 'admin', 'can_view_all_finance' => true],
            ['username' => 'second', 'email' => 'second@example.test', 'name' => 'Second', 'role' => 'admin', 'can_view_all_finance' => false],
        ];
        file_put_contents(storage_path('app/private/bootstrap-users.json'), json_encode($users));
        $this->artisan('koza:bootstrap-invitations', ['--no-interaction' => true])->assertSuccessful();
        $path = storage_path('app/private/bootstrap-invitations.json');
        $initial = file_get_contents($path);
        $codes = json_decode($initial, true);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertCount(2, $codes);
        $this->assertSame(64, strlen($codes[0]['code']));
        $this->assertDatabaseHas('account_invitations', ['token_hash' => hash('sha256', $codes[0]['code'])]);
        $this->assertDatabaseMissing('account_invitations', ['token_hash' => $codes[0]['code']]);
        $this->assertDatabaseCount('users', 0);
        $this->artisan('koza:bootstrap-invitations', ['--no-interaction' => true])->assertSuccessful();
        $this->assertSame($initial, file_get_contents($path));
        $this->assertDatabaseCount('account_invitations', 2);
        $this->assertDatabaseCount('onboarding_events', 2);
    }

    public function test_acceptance_uses_server_permissions_and_cannot_replay(): void
    {
        $code = str_repeat('a', 64);
        $invitation = $this->invitation($code);
        $this->post('/activate', $this->submission($code) + ['role' => 'admin', 'can_view_all_finance' => true, 'username' => 'attacker'])->assertRedirect('/login');
        $user = User::firstOrFail();
        $this->assertSame('invited', $user->username);
        $this->assertSame('representative', $user->role);
        $this->assertFalse($user->can_view_all_finance);
        $this->assertTrue(Hash::check('Fixture-only-Activation-872!', $user->password));
        $this->assertNotNull($invitation->fresh()->consumed_at);
        $this->assertGuest();
        $this->post('/activate', $this->submission($code))->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('onboarding_events', 1);
    }

    public function test_expired_and_revoked_codes_cannot_create_users(): void
    {
        $code = str_repeat('b', 64);
        $invitation = $this->invitation($code, ['expires_at' => now()]);
        $this->post('/activate', $this->submission($code))->assertSessionHasErrors('invitation_code');
        $invitation->update(['expires_at' => now()->addHour(), 'revoked_at' => now()]);
        $this->post('/activate', $this->submission($code))->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('onboarding_events', 0);
    }

    public function test_password_validation_does_not_flash_secrets_or_consume_code(): void
    {
        $code = str_repeat('c', 64);
        $invitation = $this->invitation($code);
        $this->from('/activate')->post('/activate', array_replace($this->submission($code), ['password_confirmation' => 'Wrong']))->assertSessionHasErrors('password');
        $this->assertNull(session()->getOldInput('invitation_code'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->assertNull($invitation->fresh()->consumed_at);
        $this->post('/activate', array_replace($this->submission($code), ['password' => ['malformed']]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_account_is_never_reset_by_invitation(): void
    {
        $original = $this->user('invited');
        $password = $original->password;
        $code = str_repeat('d', 64);
        $invitation = $this->invitation($code);
        $this->post('/activate', $this->submission($code))->assertSessionHasErrors('invitation_code');
        $this->assertSame($password, $original->fresh()->password);
        $this->assertNull($invitation->fresh()->consumed_at);
        $this->assertDatabaseCount('onboarding_events', 0);
    }

    public function test_activation_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/activate', $this->submission(str_repeat('e', 64)));
        }
        $this->post('/activate', $this->submission(str_repeat('e', 64)))->assertStatus(429);
    }

    public function test_bootstrap_does_not_expand_an_existing_system(): void
    {
        $this->user();
        $this->artisan('koza:bootstrap-invitations')->assertFailed();
        $this->assertDatabaseCount('account_invitations', 0);
        $this->assertFalse(File::exists(storage_path('app/private/bootstrap-invitations.json')));
    }

    public function test_team_invitation_applies_only_preapproved_domains_on_activation(): void
    {
        $issuer = $this->user('owner', 'admin');
        $issuer->forceFill(['can_view_all_finance' => true])->save();
        $this->artisan('koza:invite-member', ['username' => 'newmember', 'email' => 'member@example.test', '--name' => 'Test member', '--domain' => ['market', 'operations'], '--issuer' => $issuer->email])->assertSuccessful();
        $path = storage_path('app/private/team-invitations/newmember.json');
        $private = json_decode(file_get_contents($path), true);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('koza_access', 0);
        $this->post('/activate', $this->submission($private['code']) + ['domains' => ['commercial', 'system'], 'read_cost' => true])->assertRedirect('/login');
        $member = User::where('username', 'newmember')->firstOrFail();
        $access = app(KozaAccess::class);
        $this->assertSame(['market', 'operations'], $access->domains($member));
        $this->assertFalse($access->money($member));
        $this->assertFalse($access->money($member, 'cost'));
        $this->assertSame('representative', $member->role);
        $this->assertFalse($member->can_view_all_finance);
        $this->post('/activate', $this->submission($private['code']))->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_team_invitation_never_replaces_identity_or_issues_commercial_rights(): void
    {
        $issuer = $this->user('owner', 'admin');
        $issuer->forceFill(['can_view_all_finance' => true])->save();
        $args = ['username' => 'newmember', 'email' => 'member@example.test', '--name' => 'Test member', '--domain' => ['market'], '--issuer' => $issuer->email];
        $this->artisan('koza:invite-member', array_replace($args, ['--domain' => ['commercial']]))->assertFailed();
        $this->artisan('koza:invite-member', array_replace($args, ['username' => 'owner']))->assertFailed();
        $this->assertDatabaseCount('account_invitations', 0);
        $this->artisan('koza:invite-member', $args)->assertSuccessful();
        $path = storage_path('app/private/team-invitations/newmember.json');
        $original = file_get_contents($path);
        $this->artisan('koza:invite-member', $args)->assertFailed();
        $this->assertSame($original, file_get_contents($path));
        $this->assertDatabaseCount('account_invitations', 1);
    }

    public function test_team_activation_fails_when_issuer_authority_is_revoked(): void
    {
        $issuer = $this->user('owner', 'admin');
        $issuer->forceFill(['can_view_all_finance' => true])->save();
        $this->artisan('koza:invite-member', ['username' => 'newmember', 'email' => 'member@example.test', '--name' => 'Test member', '--domain' => ['market'], '--issuer' => $issuer->email])->assertSuccessful();
        $private = json_decode(file_get_contents(storage_path('app/private/team-invitations/newmember.json')), true);
        $issuer->forceFill(['active' => false])->save();
        $this->post('/activate', $this->submission($private['code']))->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('koza_access', 0);
        $this->assertNull(AccountInvitation::firstOrFail()->consumed_at);
    }
}
