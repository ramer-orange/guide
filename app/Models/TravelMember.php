<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelMember extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'travel_id',
        'user_id',
        'role',
    ];

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
