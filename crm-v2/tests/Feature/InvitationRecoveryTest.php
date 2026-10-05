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

    public function test_authorized_owner_can_replace_code_without_expanding_access(): void
    {
        $owner = $this->owner();
        $oldCode = str_repeat('a', 64);
        $invitation = $this->invitation($oldCode);

        $this->artisan('koza:reissue-invitation', [
            'username' => 'bartutest',
            '--issuer' => $owner->email,
        ])->assertSuccessful();

        $fresh = $invitation->fresh();
        $this->assertDatabaseCount('account_invitations', 1);
        $this->assertSame('representative', $fresh->role);
        $this->assertFalse($fresh->can_view_all_finance);
        $this->assertSame(['market', 'operations'], $fresh->domains);
        $this->assertTrue($fresh->expires_at->isFuture());
        $this->assertNull($fresh->revoked_at);

        $files = File::files(storage_path('app/private/team-invitations'));
        $this->assertCount(1, $files);
        $this->assertSame(0600, fileperms($files[0]->getPathname()) & 0777);
        $private = json_decode(file_get_contents($files[0]->getPathname()), true);
        $this->assertSame(64, strlen($private['code']));
        $this->assertSame(hash('sha256', $private['code']), $fresh->token_hash);

        $this->post('/activate', $this->submission($oldCode))->assertSessionHasErrors('invitation_code');
        $this->post('/activate', $this->submission($private['code']))->assertRedirect('/login');
        $member = User::where('username', 'bartutest')->firstOrFail();
        $this->assertSame('representative', $member->role);
        $this->assertFalse($member->can_view_all_finance);
        $this->assertDatabaseHas('onboarding_events', ['invitation_id' => $invitation->id, 'action' => 'team_invitation_code_replaced']);
        $this->assertDatabaseHas('onboarding_events', ['invitation_id' => $invitation->id, 'action' => 'team_invitation_reissued']);
    }

    public function test_reissue_fails_closed_for_unauthorized_or_unsafe_identity(): void
    {
        $owner = $this->owner();
        $outsider = $this->user('outsider');
        $invitation = $this->invitation(str_repeat('b', 64));

        $this->artisan('koza:reissue-invitation', ['username' => 'bartutest', '--issuer' => $outsider->email])->assertFailed();
        $originalHash = $invitation->fresh()->token_hash;

        $invitation->update(['can_view_all_finance' => true]);
        $this->artisan('koza:reissue-invitation', ['username' => 'bartutest', '--issuer' => $owner->email])->assertFailed();
        $this->assertSame($originalHash, $invitation->fresh()->token_hash);
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
