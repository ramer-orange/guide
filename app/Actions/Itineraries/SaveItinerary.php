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
                    $expectedRevision = (int) ($data['revision'] ?? 0);
                    $locked = TravelOverview::query()->whereKey($overview->getKey())->lockForUpdate()->firstOrFail();
                    if ((int) $locked->revision !== $expectedRevision) {
                        abort(409, '他のメンバーが先にしおりを更新しました。入力内容をコピーしてから再読み込みしてください。');
                    }

                    // The conditional update also protects databases where lockForUpdate is advisory or unsupported.
                    $claimed = TravelOverview::query()->whereKey($locked->getKey())
                        ->where('revision', $expectedRevision)
                        ->update(['revision' => $expectedRevision + 1]);
                    if ($claimed !== 1) {
                        abort(409, '他のメンバーが先にしおりを更新しました。入力内容をコピーしてから再読み込みしてください。');
                    }

                    $overview = $locked;
                    $overview->revision = $expectedRevision + 1;
                    $overview->update(['title' => $data['title'], 'overviewText' => $data['overview_text'] ?? null]);
                } else {
                    $overview = TravelOverview::create([
                        'user_id' => $user->id,
                        'title' => $data['title'],
                        'overviewText' => $data['overview_text'] ?? null,
                    ]);
                    $overview->travelMembers()->create(['user_id' => $user->id, 'role' => 'owner']);
                }

                $planIds = [];
                foreach ($data['plans'] as $index => $row) {
                    $plan = ! empty($row['id'])
                        ? $overview->plans()->whereKey($row['id'])->firstOrFail()
                        : $overview->plans()->make();
                    $plan->fill([
                        'date' => filled($row['date'] ?? null) ? $row['date'] : null,
                        'time' => filled($row['time'] ?? null) ? $row['time'] : null,
                        'plans_title' => $row['title'] ?? null,
                        'content' => $row['content'] ?? null,
                        'order' => $row['order'] ?? $index,
                    ])->save();
                    $planIds[] = $plan->id;
                    $keepIds = $row['existing_file_ids'] ?? [];
                    $files = $plan->planFiles()->get();
                    foreach ($files as $file) {
                        if (! in_array($file->id, $keepIds)) {
                            $oldObjects[] = ['disk' => $fileStorage->diskName($file), 'path' => $file->path];
                            $file->delete();
                        }
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
                $overview->plans()->whereNotIn('id', $planIds ?: [0])->with('planFiles')->get()->each(function (Plan $plan) use (&$oldObjects, $fileStorage) {
                    foreach ($plan->planFiles as $file) {
                        $oldObjects[] = ['disk' => $fileStorage->diskName($file), 'path' => $file->path];
                    }
                    $plan->delete();
                });

                $packingIds = [];
                foreach ($data['packing_items'] as $index => $row) {
                    $item = ! empty($row['id'])
                        ? $overview->packingItems()->where('user_id', $user->id)->whereKey($row['id'])->firstOrFail()
                        : new PackingItem(['travel_id' => $overview->id, 'user_id' => $user->id]);
                    $item->fill([
                        'packing_name' => $row['name'] ?? null,
                        'packing_is_checked' => $row['is_checked'] ?? false,
                        'order' => $row['order'] ?? $index,
                    ]);
                    $item->save();
                    $packingIds[] = $item->id;
                }
                $overview->packingItems()->where('user_id', $user->id)->whereNotIn('id', $packingIds ?: [0])->delete();

                $souvenirIds = [];
                foreach ($data['souvenirs'] as $index => $row) {
                    $item = ! empty($row['id'])
                        ? $overview->souvenirs()->whereKey($row['id'])->firstOrFail()
                        : $overview->souvenirs()->make();
                    $item->fill([
                        'souvenir_name' => $row['name'] ?? null,
                        'souvenir_is_checked' => $row['is_checked'] ?? false,
                        'order' => $row['order'] ?? $index,
                    ]);
                    $item->save();
                    $souvenirIds[] = $item->id;
                }
                $overview->souvenirs()->whereNotIn('id', $souvenirIds ?: [0])->delete();

                $noteIds = [];
                foreach ($data['notes'] as $index => $row) {
                    $item = ! empty($row['id'])
                        ? $overview->additionalComments()->whereKey($row['id'])->firstOrFail()
                        : $overview->additionalComments()->make();
                    $item->fill([
                        'additionalComment_title' => $row['title'] ?? null,
                        'additionalComment_text' => $row['text'] ?? null,
                        'order' => $row['order'] ?? $index,
                    ]);
                    $item->save();
                    $noteIds[] = $item->id;
                }
                $overview->additionalComments()->whereNotIn('id', $noteIds ?: [0])->delete();

                $template = $overview->templateType()->first();
                if (filled($data['template_type'] ?? null)) {
                    if ($template) {
                        $template->update(['template_name' => $data['template_type']]);
                    } else {
                        $overview->templateType()->create(['template_name' => $data['template_type']]);
                    }
                } else {
                    $template?->delete();
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
