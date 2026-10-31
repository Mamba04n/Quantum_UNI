<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMoveAttachment extends Model
{
    protected $guarded = [];

    public function stockMove(): BelongsTo
    {
        return $this->belongsTo(StockMove::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
