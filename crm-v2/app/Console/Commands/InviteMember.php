<?php

namespace App\Console\Commands;

use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class InviteMember extends Command
{
    protected $signature = 'koza:invite-member {username} {email} {--name=} {--domain=*} {--issuer=}';

    protected $description = 'Prepare a private team invitation without setting a password or sending mail.';

    public function handle(): int
    {
        $issuer = User::where('email', $this->option('issuer'))->where('active', true)->where('can_view_all_finance', true)->first();
        $data = ['username' => strtolower(trim($this->argument('username'))), 'email' => mb_strtolower(trim($this->argument('email'))), 'name' => trim((string) $this->option('name')), 'domains' => $this->option('domain')];
        $valid = Validator::make($data, ['username' => ['required', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/'], 'email' => ['required', 'email:rfc', 'max:190'], 'name' => ['required', 'max:120'], 'domains' => ['required', 'array', 'min:1', 'max:2'], 'domains.*' => ['required', 'distinct', 'in:market,operations']])->passes();
        if (! $issuer || ! $valid) {
            $this->error('Yetkili karar sahibi ve geçerli pazar/operasyon kapsamı gerekir.');

            return self::FAILURE;
        }
        $directory = storage_path('app/private/team-invitations');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            return self::FAILURE;
        }
        if (is_link($directory)) {
            return self::FAILURE;
        }
        $path = $directory.'/'.$data['username'].'.json';
        $handle = null;
        try {
            DB::transaction(function () use ($data, $issuer, $path, &$handle) {
                User::whereKey($issuer->id)->lockForUpdate()->firstOrFail();
                if (User::where('username', $data['username'])->orWhere('email', $data['email'])->exists()
                    || AccountInvitation::where('username', $data['username'])->orWhere('email', $data['email'])->exists()) {
                    throw new \RuntimeException('Existing identity');
                }
                $mask = umask(0077);
                try {
                    $handle = fopen($path, 'x');
                } finally {
                    umask($mask);
                }
                if (! $handle) {
                    throw new \RuntimeException('Private output unavailable');
                }
                $code = bin2hex(random_bytes(32));
                $invitation = AccountInvitation::create($data + ['role' => 'representative', 'can_view_all_finance' => false, 'granted_by' => $issuer->id, 'token_hash' => hash('sha256', $code), 'expires_at' => now()->addHours(24)]);
                DB::table('onboarding_events')->insert(['invitation_id' => $invitation->id, 'user_id' => $issuer->id, 'action' => 'team_invitation_prepared', 'created_at' => now()]);
                $json = json_encode(['name' => $data['name'], 'email' => $data['email'], 'username' => $data['username'], 'domains' => $data['domains'], 'activation_url' => url('/activate'), 'code' => $code, 'expires_at' => $invitation->expires_at->toIso8601String()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                    throw new \RuntimeException('Private output failed');
                }
            });
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
                unlink($path);
            }
            $this->error('Davet hazırlanamadı; mevcut hesap/davet veya özel çıktı durumunu kontrol edin. Mevcut kayıtlar değiştirilmedi.');

            return self::FAILURE;
        }
        fclose($handle);
        $this->info('Davet özel dosyada hazır. Kod 24 saat geçerli; parola atanmadı, e-posta gönderilmedi.');

        return self::SUCCESS;
    }
}
