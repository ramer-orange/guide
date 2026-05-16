<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedPassword extends Model
{
    protected $fillable = [
        'travel_id',
        'shared_password',
        'expires_at',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function travelOverview()
    {
        return $this->belongsTo(TravelOverview::class, 'travel_id');
    }

    public function isActive(): bool
    {
        return $this->shared_password !== null
            && $this->disabled_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
