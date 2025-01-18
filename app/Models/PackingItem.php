<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingItem extends Model
{
    protected $fillable = [
        'travel_id',
        'packing_name',
        'packing_is_checked',
//        'order',
    ];

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }
}
