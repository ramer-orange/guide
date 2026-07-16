<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedPassword extends Model
{
    public const MAX_LIFETIME_DAYS = 180;

    protected $attributes = [
        'access_version' => 1,
    ];

    protected $fillable = [
        'travel_id',
        'shared_password',
        'expires_at',
        'disabled_at',
        'access_version',
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
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->expires_at->lessThanOrEqualTo($this->maximumExpiresAt());
    }

    public function maximumExpiresAt()
    {
        return $this->created_at?->copy()->addDays(self::MAX_LIFETIME_DAYS);
    }

    public function nextVersion(): int
    {
        return ((int) $this->access_version) + 1;
    }

    public function lifecycleElapsed(): bool
    {
        return $this->maximumExpiresAt()?->isPast() ?? false;
    }

    public static function defaultExpiresAt()
    {
        $days = min(
            max((int) config('shared-access.default_lifetime_days', 30), 1),
            self::MAX_LIFETIME_DAYS,
        );

        return now()->addDays($days);
    }
}
