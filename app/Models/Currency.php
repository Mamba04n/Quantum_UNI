<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'automatic_updates_enabled' => 'boolean',
        'is_fixed' => 'boolean',
    ];
}
