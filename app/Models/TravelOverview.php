<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelOverview extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'overview'
    ];
}
