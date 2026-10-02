<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountInvitation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['domains' => 'array', 'can_view_all_finance' => 'boolean', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
