<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlanFile extends Model
{
    protected $fillable = ['plan_id', 'file_name', 'path'];

    public function plan()
    {
        return $this->belongsTo(Plan::class,'plan_id');
    }

    public function url(): string
    {
        $diskName = config('filesystems.uploads');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 's3') {
            return $disk->temporaryUrl(
                $this->path,
                now()->addMinutes((int) config('filesystems.temporary_url_ttl'))
            );
        }

        return $disk->url($this->path);
    }
}
