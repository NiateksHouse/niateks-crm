<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = ['id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->whereHas('company')->when(! $user->isAdmin(), fn ($q) => $q->where('created_by', $user->id));
    }
}
