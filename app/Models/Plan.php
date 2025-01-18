<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'travel_id',
        'date',
        'time',
        'plans_title',
        'content',
//        'order',
    ];

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }

    public function planFiles()
    {
        return $this->hasMany(PlanFile::class);
    }
}
