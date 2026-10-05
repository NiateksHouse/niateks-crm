<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'koza:create-user {username} {email} {--name=} {--admin} {--all-finance}';

    protected $description = 'Create a new CRM account using hidden password prompts; never replace an existing account.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Parola gizli ve etkileşimli olarak girilmelidir.');

            return self::FAILURE;
        }

        $data = [
            'username' => strtolower(trim($this->argument('username'))),
            'email' => mb_strtolower(trim($this->argument('email'))),
            'name' => trim((string) $this->option('name')),
        ];
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        if ($validator->fails() || ($this->option('all-finance') && ! $this->option('admin'))) {
            $this->error('Kullanıcı bilgilerini kontrol edin: ad, geçerli ve benzersiz kullanıcı adı/e-posta gereklidir. Genel finans yetkisi yalnız yönetici için seçilebilir.');

            return self::FAILURE;
        }

        $password = $this->secret('Yeni parola');
        $confirmation = $this->secret('Parolayı tekrar girin');
        $valid = Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(14)->mixedCase()->numbers()->symbols()],
        ])->passes();
        if (! $valid || ! is_string($password) || strlen($password) > 72 || $password !== $confirmation) {
            $this->error('Parolalar eşleşmeli; en az 14 karakter, büyük/küçük harf, sayı ve simge içermeli, 72 baytı aşmamalıdır.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($data, $password) {
                User::forceCreate($data + [
                    'password' => $password,
                    'role' => $this->option('admin') ? 'admin' : 'representative',
                    'active' => true,
                    'can_view_all_finance' => (bool) $this->option('all-finance'),
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->error('Bu kullanıcı adı veya e-posta zaten kayıtlı. Mevcut hesap değiştirilmedi.');

            return self::FAILURE;
        }

        $this->info('Yeni hesap oluşturuldu. Mevcut hesaplar ve parolalar değiştirilmedi.');

        return self::SUCCESS;
    }
}
