<?php

namespace App\Console\Commands;

use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BootstrapInvitations extends Command
{
    protected $signature = 'koza:bootstrap-invitations';

    protected $description = 'Issue the initial invitation batch once from a private manifest; never print secret codes.';

    public function handle(): int
    {
        $directory = storage_path('app/private');
        $input = $directory.'/bootstrap-users.json';
        $output = $directory.'/bootstrap-invitations.json';
        if (DB::table('onboarding_runs')->where('id', 'initial-users')->exists()) {
            $this->info('İlk davet grubu daha önce hazırlandı; tekrar üretilmedi.');

            return self::SUCCESS;
        }
        if (User::exists() || AccountInvitation::exists() || ! is_file($input) || is_link($input) || file_exists($output)) {
            $this->error('İlk kurulum koşulları sağlanmadı. Mevcut kayıtlar değiştirilmedi.');

            return self::FAILURE;
        }
        $users = json_decode(file_get_contents($input), true);
        $validator = Validator::make(['users' => $users], [
            'users' => ['required', 'array', 'min:1', 'max:10'],
            'users.*.username' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/', 'distinct'],
            'users.*.email' => ['required', 'email:rfc', 'max:190', 'distinct:ignore_case'],
            'users.*.name' => ['required', 'string', 'max:120'],
            'users.*.role' => ['required', 'in:admin,representative'],
            'users.*.can_view_all_finance' => ['required', 'boolean'],
        ]);
        if ($validator->fails() || ! collect($users)->contains('role', 'admin') || collect($users)->contains(fn ($u) => ! empty($u['can_view_all_finance']) && $u['role'] !== 'admin')) {
            $this->error('Özel kurulum dosyasındaki kullanıcı ve yetki bilgilerini kontrol edin.');

            return self::FAILURE;
        }
        $handle = null;
        try {
            DB::transaction(function () use ($users, $output, &$handle) {
                DB::table('onboarding_runs')->insert(['id' => 'initial-users', 'created_at' => now()]);
                $oldMask = umask(0077);
                try {
                    $handle = fopen($output, 'x');
                } finally {
                    umask($oldMask);
                }
                if ($handle === false) {
                    throw new \RuntimeException('Private output unavailable');
                }
                $codes = [];
                foreach ($users as $user) {
                    $code = bin2hex(random_bytes(32));
                    $invitation = AccountInvitation::create([
                        'username' => $user['username'], 'email' => mb_strtolower($user['email']),
                        'name' => $user['name'], 'role' => $user['role'],
                        'can_view_all_finance' => (bool) $user['can_view_all_finance'],
                        'token_hash' => hash('sha256', $code), 'expires_at' => now()->addHours(24),
                    ]);
                    DB::table('onboarding_events')->insert(['invitation_id' => $invitation->id, 'action' => 'issued_by_private_bootstrap', 'created_at' => now()]);
                    $codes[] = ['username' => $user['username'], 'email' => $user['email'], 'code' => $code, 'expires_at' => $invitation->expires_at->toIso8601String()];
                }
                $json = json_encode($codes, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                    throw new \RuntimeException('Private output write failed');
                }
            });
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
                unlink($output);
            }
            $this->error('Davet hazırlığı tamamlanamadı. Gizli kodlar günlüğe yazılmadı; kurulum durumunu kontrol edin.');

            return self::FAILURE;
        }
        fclose($handle);
        $this->info('İlk davetler özel kurulum dosyasına kaydedildi. Kodlar 24 saat geçerlidir.');

        return self::SUCCESS;
    }
}
