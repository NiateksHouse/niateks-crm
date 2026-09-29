<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $guarded = ['id'];
    public const KINDS = ['note'=>'Not', 'call'=>'Telefon görüşmesi', 'meeting'=>'Toplantı', 'email_in'=>'Gelen e-posta', 'email_out'=>'Giden e-posta'];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('company')->when(! $user->isAdmin(), fn ($q) => $q->where('owner_id', $user->id));
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function revisions() { return $this->hasMany(ActivityRevision::class); }
    public function latestRevision() { return $this->hasOne(ActivityRevision::class)->latestOfMany('version'); }
}
