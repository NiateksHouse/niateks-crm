<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'country_code', 'city', 'email', 'phone', 'website', 'tax_number'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'created_by' => 'integer', 'roles' => 'array'];
    }

    public function supplyCategories()
    {
        return $this->belongsToMany(SupplyCategory::class);
    }

    public function revisions()
    {
        return $this->hasMany(CompanyRevision::class);
    }

    protected $hidden = ['identity_key'];
}
