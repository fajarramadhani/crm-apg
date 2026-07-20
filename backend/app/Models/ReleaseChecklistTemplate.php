<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseChecklistTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_active' => 'boolean'];
    }
}
