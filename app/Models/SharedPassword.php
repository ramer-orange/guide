<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedPassword extends Model
{
    protected $fillable = [
        'travel_id',
        'shared_password',
    ];

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }
}
