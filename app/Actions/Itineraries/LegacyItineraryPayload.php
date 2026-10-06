<?php

namespace App\Actions\Itineraries;

use Illuminate\Http\UploadedFile;

class LegacyItineraryPayload
{
    /** @return array{data: array<string, mixed>, uploads: array<string, array<UploadedFile>>} */
    public static function fromLivewire(object $form): array
    {
        $uploads = [];
        $plans = [];
        foreach ($form->plans as $index => $row) {
            $clientId = 'livewire-plan-'.$index;
            $plans[] = [
                'id' => self::databaseId($row['id'] ?? null),
                'client_id' => $clientId,
                'date' => $row['date'] ?? null,
                'time' => $row['time'] ?? null,
                'title' => $row['plans_title'] ?? null,
                'content' => $row['content'] ?? null,
                'order' => $index,
                'existing_file_ids' => array_values(array_filter(array_map(
                    fn ($file) => self::databaseId($file['id'] ?? null),
                    $row['existing_planFiles'] ?? [],
                ))),
            ];
            $files = array_values(array_filter($row['planFiles'] ?? [], fn ($file) => $file instanceof UploadedFile));
            if ($files) {
                $uploads[$clientId] = $files;
            }
        }

        $packing = array_map(fn ($index, $row) => [
            'id' => self::databaseId($row['id'] ?? null),
            'name' => $row['packing_name'] ?? null,
            'is_checked' => (bool) ($row['packing_is_checked'] ?? false),
            'order' => $index,
        ], array_keys($form->packingItems), array_values($form->packingItems));
        $souvenirs = array_map(fn ($index, $row) => [
            'id' => self::databaseId($row['id'] ?? null),
            'name' => $row['souvenir_name'] ?? null,
            'is_checked' => (bool) ($row['souvenir_is_checked'] ?? false),
            'order' => $index,
        ], array_keys($form->souvenirs), array_values($form->souvenirs));
        $notes = array_map(fn ($index, $row) => [
            'id' => self::databaseId($row['id'] ?? null),
            'title' => $row['additionalComment_title'] ?? null,
            'text' => $row['additionalComment_text'] ?? null,
            'order' => $index,
        ], array_keys($form->additionalComments), array_values($form->additionalComments));

        $data = [
            'title' => $form->title,
            'overview_text' => $form->overviewText,
            'template_type' => $form->template_type,
            'plans' => $plans,
            'packing_items' => $packing,
            'souvenirs' => $souvenirs,
            'notes' => $notes,
        ];
        if (isset($form->revision) && (int) $form->revision > 0) {
            $data['revision'] = (int) $form->revision;
        }
        if (filled($form->shared_password ?? null)) {
            $data['shared_password'] = $form->shared_password;
            $data['viewer_share_expires_at'] = $form->viewer_share_expires_at;
        }

        return ['data' => $data, 'uploads' => $uploads];
    }

    private static function databaseId(mixed $id): ?int
    {
        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
