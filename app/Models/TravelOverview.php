<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TravelOverview extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id',
        'title',
        'overviewText'
    ];

    public function plans()
    {
        return $this->hasMany(Plan::class, 'travel_id');
    }

    public function packingItems()
    {
        return $this->hasMany(PackingItem::class, 'travel_id');
    }

    public function souvenirs()
    {
        return $this->hasMany(Souvenir::class, 'travel_id');
    }


    public function additionalComments()
    {
        return $this->hasMany(AdditionalComment::class, 'travel_id');
    }

    public function sharedPasswords()
    {
        return $this->hasOne(SharedPassword::class, 'travel_id');
    }
}
