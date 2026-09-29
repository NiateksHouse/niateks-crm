<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $guarded = ['id'];

    public const STAGES = ['opened' => 'Proje Açıldı', 'discussing' => 'Görüşülüyor', 'waiting' => 'Bilgi Bekleniyor'];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('company')->when(! $user->isAdmin(), fn ($q) => $q->where('owner_id', $user->id));
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function revisions() { return $this->hasMany(ProjectRevision::class); }
}
