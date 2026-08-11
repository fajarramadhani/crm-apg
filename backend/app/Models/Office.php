<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'office_type'])]
class Office extends Model
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopePusat($query)
    {
        return $query->where('office_type', 'pusat');
    }

    public function scopeCabang($query)
    {
        return $query->where('office_type', 'cabang');
    }
}
