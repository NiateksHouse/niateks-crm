<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
