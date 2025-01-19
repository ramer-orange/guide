<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Plan extends Model
{
    protected $fillable = [
        'travel_id',
        'date',
        'time',
        'plans_title',
        'content',
        'order',
    ];

    public function getTimeAttribute($value)
    {
        // Carbonインスタンスを生成して、formatで整形して返す
        return Carbon::createFromFormat('H:i:s', $value)->format('H:i');
    }

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }

    public function planFiles()
    {
        return $this->hasMany(PlanFile::class);
    }
}
