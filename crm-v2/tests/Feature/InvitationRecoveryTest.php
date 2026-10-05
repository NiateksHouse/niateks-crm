<?php

namespace Tests\Feature;

use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InvitationRecoveryTest extends TestCase
{
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = sys_get_temp_dir().'/koza-invitation-recovery-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->temporaryStorage.'/app/private', 0700);
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function owner(): User
    {
        $owner = $this->user('owner', 'admin');
        $owner->forceFill(['can_view_all_finance' => true])->save();

        return $owner;
    }

    private function invitation(string $code, array $changes = []): AccountInvitation
    {
        return AccountInvitation::create(array_replace([
            'username' => 'bartutest',
            'email' => 'bartu@example.test',
            'name' => 'Bartu Test',
            'domains' => ['market', 'operations'],
            'role' => 'representative',
            'can_view_all_finance' => false,
            'token_hash' => hash('sha256', $code),
            'expires_at' => now()->subMinute(),
        ], $changes));
    }

    private function submission(string $code): array
    {
        return [
            'invitation_code' => $code,
            'password' => 'Fixture-only-Activation-872!',
            'password_confirmation' => 'Fixture-only-Activation-872!',
        ];
    }

    public function test_authorized_owner_can_reissue_without_expanding_access(): void
    {
        $owner = $this->owner();
        $oldCode = str_repeat('a', 64);
        $old = $this->invitation($oldCode);

        $this->artisan('koza:reissue-invitation', [
            'username' => 'bartutest',
            '--issuer' => $owner->email,
        ])->assertSuccessful();

        $this->assertNotNull($old->fresh()->revoked_at);
        $new = AccountInvitation::latest('id')->firstOrFail();
        $this->assertSame('representative', $new->role);
        $this->assertFalse($new->can_view_all_finance);
        $this->assertSame(['market', 'operations'], $new->domains);
        $this->assertTrue($new->expires_at->isFuture());

        $files = File::files(storage_path('app/private/team-invitations'));
        $this->assertCount(1, $files);
        $this->assertSame(0600, fileperms($files[0]->getPathname()) & 0777);
        $private = json_decode(file_get_contents($files[0]->getPathname()), true);
        $this->assertSame(64, strlen($private['code']));
        $this->assertSame(hash('sha256', $private['code']), $new->token_hash);

        $this->post('/activate', $this->submission($oldCode))->assertSessionHasErrors('invitation_code');
        $this->post('/activate', $this->submission($private['code']))->assertRedirect('/login');
        $member = User::where('username', 'bartutest')->firstOrFail();
        $this->assertSame('representative', $member->role);
        $this->assertFalse($member->can_view_all_finance);
        $this->assertDatabaseHas('onboarding_events', ['invitation_id' => $old->id, 'action' => 'team_invitation_revoked_for_reissue']);
        $this->assertDatabaseHas('onboarding_events', ['invitation_id' => $new->id, 'action' => 'team_invitation_reissued']);
    }

    public function test_reissue_fails_closed_for_unauthorized_or_unsafe_identity(): void
    {
        $owner = $this->owner();
        $outsider = $this->user('outsider');
        $invitation = $this->invitation(str_repeat('b', 64));

        $this->artisan('koza:reissue-invitation', ['username' => 'bartutest', '--issuer' => $outsider->email])->assertFailed();
        $this->assertNull($invitation->fresh()->revoked_at);

        $invitation->update(['can_view_all_finance' => true]);
        $this->artisan('koza:reissue-invitation', ['username' => 'bartutest', '--issuer' => $owner->email])->assertFailed();
        $this->assertNull($invitation->fresh()->revoked_at);
        $this->assertDatabaseCount('account_invitations', 1);
        $this->assertEmpty(File::files(storage_path('app/private/team-invitations')));
    }

    public function test_activation_form_allows_server_to_explain_invalid_values(): void
    {
        $response = $this->get('/activate')->assertOk();
        $response->assertDontSee('minlength="64"', false);
        $response->assertDontSee('minlength="14"', false);
        $response->assertSee('64 karakterlik kodu', false);

        $this->post('/activate', [
            'invitation_code' => 'short',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['invitation_code', 'password']);
    }
}
