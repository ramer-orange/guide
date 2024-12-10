<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Souvenir extends Model
{
    protected $fillable = [
        'travel_id',
        'souvenir_name',
        'souvenir_is_checked'
    ];

    public function travelOvervire()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }
}
