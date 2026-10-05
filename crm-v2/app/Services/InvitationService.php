<?php

namespace App\Services;

use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function accept(string $code, string $password): void
    {
        try {
            DB::transaction(function () use ($code, $password) {
                $invitation = AccountInvitation::where('token_hash', hash('sha256', $code))->lockForUpdate()->first();
                if (! $invitation || $invitation->consumed_at || $invitation->revoked_at || $invitation->expires_at->lte(now())) {
                    throw ValidationException::withMessages(['invitation_code' => 'Davet kodu geçersiz veya süresi dolmuş. Yöneticinizle görüşün.']);
                }
                $domains = $invitation->domains ?? [];
                if ($domains) {
                    $issuer = User::whereKey($invitation->granted_by)->where('active', true)->where('can_view_all_finance', true)->lockForUpdate()->first();
                    if (! $issuer || $invitation->role !== 'representative' || $invitation->can_view_all_finance || ! is_array($domains) || array_diff($domains, ['market', 'operations'])) {
                        throw ValidationException::withMessages(['invitation_code' => 'Davet yetkisi geçerli değil. Yöneticinizle görüşün.']);
                    }
                }
                $user = User::forceCreate([
                    'username' => $invitation->username,
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'role' => $invitation->role,
                    'can_view_all_finance' => $invitation->can_view_all_finance,
                    'password' => $password,
                    'active' => true,
                ]);
                if ($domains) {
                    DB::table('koza_access')->insert(['user_id' => $user->id, 'domains' => json_encode(array_values(array_unique($domains))), 'read_cost' => false, 'read_finance' => false, 'granted_by' => $invitation->granted_by, 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('onboarding_events')->insert(['invitation_id' => $invitation->id, 'user_id' => $user->id, 'action' => 'invited_domains_applied', 'created_at' => now()]);
                }
                $invitation->consumed_at = now();
                $invitation->save();
                DB::table('onboarding_events')->insert(['invitation_id' => $invitation->id, 'user_id' => $user->id, 'action' => 'accepted', 'created_at' => now()]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['invitation_code' => 'Hesap oluşturulamadı. Yöneticinizle görüşün.']);
        }
    }
}
