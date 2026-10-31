<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceDeviceEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'code_hash',
        'expires_at',
        'consumed_at',
        'approved_by',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
