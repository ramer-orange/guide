<?php

namespace App\Actions\Itineraries;

use App\Models\PackingItem;
use App\Models\Plan;
use App\Models\SharedPassword;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveItinerary
{
    /** @param array<string, mixed> $data @param array<string, array<UploadedFile>> $uploads */
    public function handle(User $user, array $data, array $uploads = [], ?TravelOverview $overview = null, ?PlanFileStorage $fileStorage = null): TravelOverview
    {
        $fileStorage ??= app(PlanFileStorage::class);
        $newObjects = [];
        $oldObjects = [];

        try {
            $overview = DB::transaction(function () use ($user, $data, $uploads, $overview, $fileStorage, &$newObjects, &$oldObjects) {
                if ($overview) {
                    // Serialize persistence, while accepting changes from any loaded version.
                    $overview = TravelOverview::query()->whereKey($overview->getKey())->lockForUpdate()->firstOrFail();
                    $overview->fill($this->attributes($data, ['title' => 'title', 'overview_text' => 'overviewText']));
                    $overview->touch();
                } else {
                    $overview = TravelOverview::create([
                        'user_id' => $user->id,
                        'title' => $data['title'],
                        'overviewText' => $data['overview_text'] ?? null,
                    ]);
                    $overview->travelMembers()->create(['user_id' => $user->id, 'role' => 'owner']);
                }

                foreach ($data['plans'] ?? [] as $index => $row) {
                    $plan = ! empty($row['id'])
                        ? $overview->plans()->whereKey($row['id'])->firstOrFail()
                        : $overview->plans()->make();
                    $plan->fill($this->attributes($row, [
                        'date' => 'date', 'time' => 'time', 'title' => 'plans_title',
                        'content' => 'content', 'order' => 'order',
                    ]))->save();
                    foreach ($plan->planFiles()->whereIn('id', $row['removed_file_ids'] ?? [])->get() as $file) {
                        $oldObjects[] = ['disk' => $fileStorage->diskName($file), 'path' => $file->path];
                        $file->delete();
                    }
                    foreach ($uploads[$row['client_id'] ?? "plan-{$index}"] ?? [] as $upload) {
                        if (! $upload instanceof UploadedFile) {
                            continue;
                        }
                        $object = $fileStorage->store($upload);
                        $newObjects[] = $object;
                        $plan->planFiles()->create([
                            'path' => $object['path'],
                            'disk' => $object['disk'],
                            'file_name' => $upload->getClientOriginalName(),
                        ]);
                    }
                }
                $overview->plans()->whereIn('id', $data['deleted_plan_ids'] ?? [])->with('planFiles')->get()->each(function (Plan $plan) use (&$oldObjects, $fileStorage) {
                    foreach ($plan->planFiles as $file) {
                        $oldObjects[] = ['disk' => $fileStorage->diskName($file), 'path' => $file->path];
                    }
                    $plan->delete();
                });

                foreach ($data['packing_items'] ?? [] as $row) {
                    $item = ! empty($row['id'])
                        ? $overview->packingItems()->where('user_id', $user->id)->whereKey($row['id'])->firstOrFail()
                        : new PackingItem(['travel_id' => $overview->id, 'user_id' => $user->id]);
                    $item->fill($this->attributes($row, [
                        'name' => 'packing_name', 'is_checked' => 'packing_is_checked', 'order' => 'order',
                    ]))->save();
                }
                $overview->packingItems()->where('user_id', $user->id)->whereIn('id', $data['deleted_packing_item_ids'] ?? [])->delete();

                foreach ($data['souvenirs'] ?? [] as $row) {
                    $item = ! empty($row['id'])
                        ? $overview->souvenirs()->whereKey($row['id'])->firstOrFail()
                        : $overview->souvenirs()->make();
                    $item->fill($this->attributes($row, [
                        'name' => 'souvenir_name', 'is_checked' => 'souvenir_is_checked', 'order' => 'order',
                    ]))->save();
                }
                $overview->souvenirs()->whereIn('id', $data['deleted_souvenir_ids'] ?? [])->delete();

                foreach ($data['notes'] ?? [] as $row) {
                    $item = ! empty($row['id'])
                        ? $overview->additionalComments()->whereKey($row['id'])->firstOrFail()
                        : $overview->additionalComments()->make();
                    $item->fill($this->attributes($row, [
                        'title' => 'additionalComment_title', 'text' => 'additionalComment_text', 'order' => 'order',
                    ]))->save();
                }
                $overview->additionalComments()->whereIn('id', $data['deleted_note_ids'] ?? [])->delete();

                if (array_key_exists('template_type', $data)) {
                    $template = $overview->templateType()->first();
                    if (filled($data['template_type'])) {
                        if ($template) {
                            $template->update(['template_name' => $data['template_type']]);
                        } else {
                            $overview->templateType()->create(['template_name' => $data['template_type']]);
                        }
                    } else {
                        $template?->delete();
                    }
                }

                if (filled($data['shared_password'] ?? null)) {
                    $this->saveInitialShare($overview, $data);
                }

                return $overview;
            });
        } catch (Throwable $error) {
            $fileStorage->deleteBestEffort($newObjects);
            throw $error;
        }

        $fileStorage->deleteBestEffort($this->uniqueObjects($oldObjects));

        return $overview->refresh();
    }

    /** Map only supplied fields; omitted fields preserve the current database value. */
    private function attributes(array $data, array $mapping): array
    {
        $attributes = [];
        foreach ($mapping as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        return $attributes;
    }

    /** @param array<array{disk: string, path: string}> $objects
     * @return array<array{disk: string, path: string}>
     */
    private function uniqueObjects(array $objects): array
    {
        $unique = [];
        foreach ($objects as $object) {
            $unique[$object['disk'].'/'.$object['path']] = $object;
        }

        return array_values($unique);
    }

    private function saveInitialShare(TravelOverview $overview, array $data): void
    {
        if ($overview->sharedPasswords()->exists()) {
            throw ValidationException::withMessages(['shared_password' => '閲覧共有は作成後に設定してください。']);
        }
        $expiresAt = $data['viewer_share_expires_at'] ?? SharedPassword::defaultExpiresAt();
        $overview->sharedPasswordHistory()->create([
            'shared_password' => Hash::make($data['shared_password']),
            'expires_at' => $expiresAt,
            'access_version' => 1,
        ]);
    }
}
