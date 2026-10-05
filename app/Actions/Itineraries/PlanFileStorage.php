<?php

namespace App\Actions\Itineraries;

use App\Models\PlanFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PlanFileStorage
{
    /** @return array{path: string, disk: string} */
    public function store(UploadedFile $upload): array
    {
        $disk = (string) config('filesystems.uploads');
        $path = $upload->store('files', $disk);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Unable to store uploaded attachment.');
        }

        return ['path' => $path, 'disk' => $disk];
    }

    public function diskName(PlanFile $file): string
    {
        return $file->disk ?: (string) config('filesystems.legacy_uploads', 'public');
    }

    public function exists(PlanFile $file): bool
    {
        return Storage::disk($this->diskName($file))->exists($file->path);
    }

    public function download(PlanFile $file): StreamedResponse
    {
        return Storage::disk($this->diskName($file))->download($file->path, $file->file_name);
    }

    /** @param array{disk: string, path: string} $object */
    public function delete(array $object): void
    {
        Storage::disk($object['disk'])->delete($object['path']);
    }

    public function deleteFile(PlanFile $file): void
    {
        $this->delete(['disk' => $this->diskName($file), 'path' => $file->path]);
    }

    /** @param iterable<array{disk: string, path: string}> $objects */
    public function deleteBestEffort(iterable $objects): void
    {
        foreach ($objects as $object) {
            try {
                $this->delete($object);
            } catch (Throwable $error) {
                report($error);
            }
        }
    }
}
