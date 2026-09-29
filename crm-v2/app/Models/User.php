<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable
{
    protected $hidden = ['password', 'remember_token'];
    protected $guarded = ['id', 'role', 'active', 'can_view_all_finance'];
    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'can_view_all_finance' => 'boolean'];
    }
    public function isAdmin(): bool { return $this->active && $this->role === 'admin'; }
}
