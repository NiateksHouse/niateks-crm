<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public const VISIBILITIES = ['private' => 'Özel', 'internal' => 'CRM içi genel', 'users' => 'Seçili kullanıcılar', 'roles' => 'Seçili roller'];

    protected function casts(): array
    {
        return ['allowed_users' => 'array', 'allowed_roles' => 'array', 'document_date' => 'date', 'expires_at' => 'date', 'revision' => 'integer'];
    }

    public static function permits(User $user, string $ability): bool
    {
        return $user->active && in_array($ability, config('documents.permissions.'.$user->role, []), true);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if (! self::permits($user, 'view')) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function ($q) use ($user) {
            $q->where('visibility', 'internal')->orWhere(function ($q) use ($user) {
                if (! self::permits($user, 'view_private')) {
                    $q->where('visibility', '!=', 'private');
                }
                $q->where(function ($q) use ($user) {
                    $q->where('owner_id', $user->id)
                        ->orWhere(function ($q) use ($user) {
                            $q->whereIn('visibility', ['private', 'users'])->whereJsonContains('allowed_users', $user->id);
                        })->orWhere(function ($q) use ($user) {
                            $q->whereIn('visibility', ['private', 'roles'])->whereJsonContains('allowed_roles', $user->role);
                        });
                });
            });
        });
    }

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('number');
    }
}
