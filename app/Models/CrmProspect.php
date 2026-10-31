<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmProspect extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['stage' => 'prospectos', 'version' => 0];

    protected $casts = ['context' => 'array', 'classification_criteria' => 'array', 'contacts' => 'array', 'machines' => 'array', 'last_contact_at' => 'datetime', 'next_contact_at' => 'date', 'discarded_at' => 'datetime', 'version' => 'integer'];

    public function campaign()
    {
        return $this->belongsTo(ProspectingCampaign::class, 'campaign_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function opportunity()
    {
        return $this->hasOne(CommercialOpportunity::class, 'prospect_id');
    }
}
