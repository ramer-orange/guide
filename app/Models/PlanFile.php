<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFile extends Model
{
    protected $fillable = ['plan_id', 'file_name', 'path', 'disk'];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function url(): string
    {
        return route('api.v1.itineraries.files.show', [$this->plan->travel_id, $this->id]);
    }
}
