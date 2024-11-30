<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFile extends Model
{
    protected $fillable = ['plan_id', 'file_name', 'path'];

    public function plan()
    {
        return $this->belongsTo(Plan::class,'plan_id');
    }
}
