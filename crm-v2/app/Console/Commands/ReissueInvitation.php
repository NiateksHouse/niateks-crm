<?php

namespace App\Console\Commands;

use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReissueInvitation extends Command
{
    protected $signature = 'koza:reissue-invitation {username} {--issuer=}';

    protected $description = 'Replace an unconsumed team invitation code with a fresh private code.';

    public function handle(): int
    {
        $username = strtolower(trim((string) $this->argument('username')));
        $issuer = User::where('email', $this->option('issuer'))
            ->where('active', true)
            ->where('can_view_all_finance', true)
            ->first();

        if (! $issuer || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $username)) {
            $this->error('Yetkili karar sahibi ve geçerli kullanıcı adı gerekir.');

            return self::FAILURE;
        }

        $directory = storage_path('app/private/team-invitations');
        if ((! is_dir($directory) && ! mkdir($directory, 0700, true)) || is_link($directory)) {
            $this->error('Özel çıktı dizini kullanılamıyor.');

            return self::FAILURE;
        }

        $path = $directory.'/'.$username.'-reissued-'.now()->utc()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.json';
        $handle = null;

        try {
            DB::transaction(function () use ($username, $issuer, $path, &$handle) {
                User::whereKey($issuer->id)
                    ->where('active', true)
                    ->where('can_view_all_finance', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                $invitation = AccountInvitation::where('username', $username)->lockForUpdate()->firstOrFail();
                $domains = $invitation->domains;
                $validDomains = is_array($domains)
                    && count($domains) >= 1
                    && count($domains) <= 2
                    && count($domains) === count(array_unique($domains))
                    && count(array_diff($domains, ['market', 'operations'])) === 0;

                if ($invitation->consumed_at
                    || $invitation->role !== 'representative'
                    || $invitation->can_view_all_finance
                    || ! $validDomains
                    || User::where('username', $invitation->username)->orWhere('email', $invitation->email)->exists()) {
                    throw new \RuntimeException('Invitation cannot be reissued safely.');
                }

                $mask = umask(0077);
                try {
                    $handle = fopen($path, 'x');
                } finally {
                    umask($mask);
                }
                if (! $handle) {
                    throw new \RuntimeException('Private output unavailable.');
                }

                $now = now();
                $code = bin2hex(random_bytes(32));
                $invitation->forceFill([
                    'token_hash' => hash('sha256', $code),
                    'granted_by' => $issuer->id,
                    'expires_at' => $now->copy()->addHours(24),
                    'revoked_at' => null,
                ])->save();

                DB::table('onboarding_events')->insert([
                    'invitation_id' => $invitation->id,
                    'user_id' => $issuer->id,
                    'action' => 'team_invitation_code_replaced',
                    'created_at' => $now,
                ]);
                DB::table('onboarding_events')->insert([
                    'invitation_id' => $invitation->id,
                    'user_id' => $issuer->id,
                    'action' => 'team_invitation_reissued',
                    'created_at' => $now,
                ]);

                $json = json_encode([
                    'name' => $invitation->name,
                    'email' => $invitation->email,
                    'username' => $invitation->username,
                    'domains' => $domains,
                    'activation_url' => url('/activate'),
                    'code' => $code,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                    throw new \RuntimeException('Private output failed.');
                }
            });
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($path)) {
                unlink($path);
            }
            $this->error('Davet yenilenemedi; kimlik, yetki, kapsam veya özel çıktı durumunu kontrol edin. Mevcut kayıtlar değiştirilmedi.');

            return self::FAILURE;
        }

        fclose($handle);
        $this->info('Yeni davet özel dosyada hazır. Önceki kod geçersiz; yeni kod 24 saat geçerli, e-posta gönderilmedi.');

        return self::SUCCESS;
    }
}
