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
                $user = User::forceCreate([
                    'username' => $invitation->username,
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'role' => $invitation->role,
                    'can_view_all_finance' => $invitation->can_view_all_finance,
                    'password' => $password,
                    'active' => true,
                ]);
                $invitation->consumed_at = now();
                $invitation->save();
                DB::table('onboarding_events')->insert(['invitation_id' => $invitation->id, 'user_id' => $user->id, 'action' => 'accepted', 'created_at' => now()]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['invitation_code' => 'Hesap oluşturulamadı. Yöneticinizle görüşün.']);
        }
    }
}
