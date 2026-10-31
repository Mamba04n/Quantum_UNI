<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialOpportunity extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];

    public function prospect()
    {
        return $this->belongsTo(CrmProspect::class, 'prospect_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
