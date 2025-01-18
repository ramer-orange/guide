<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalComment extends Model
{
    protected $fillable = [
        'travel_id',
        'additionalComment_title',
        'additionalComment_text',
        'order',
    ];

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }
}
