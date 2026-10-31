<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectingCampaign extends Model
{
    protected $fillable = ['name', 'name_fingerprint', 'description', 'created_by', 'submission_token'];

    public function prospects()
    {
        return $this->hasMany(CrmProspect::class, 'campaign_id');
    }
}
