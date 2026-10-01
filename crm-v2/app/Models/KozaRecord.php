<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KozaRecord extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'version' => 'integer', 'edition' => 'integer', 'immutable' => 'boolean', 'demo' => 'boolean', 'verified_at' => 'datetime', 'due_at' => 'date'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function links()
    {
        return $this->hasMany(KozaLink::class, 'record_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
