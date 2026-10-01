<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KozaLink extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function target()
    {
        return $this->belongsTo(KozaRecord::class, 'target_id');
    }
}
