<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PlanFile;
use App\Models\SharedPassword;
use App\Models\TravelOverview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveItineraryRequest extends FormRequest
{
    protected array $payload = [];

    protected bool $payloadIsValidJson = false;

    public function authorize(): bool
    {
        $itinerary = $this->route('itinerary');

        return $itinerary instanceof TravelOverview
            ? Gate::allows('update', $itinerary)
            : $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $decoded = json_decode((string) $this->input('payload'), true);
        $this->payload = is_array($decoded) ? $decoded : [];
        $this->payloadIsValidJson = json_last_error() === JSON_ERROR_NONE && is_object(json_decode((string) $this->input('payload')));
        if (isset($this->payload['plans']) && is_array($this->payload['plans'])) {
            foreach ($this->payload['plans'] as &$plan) {
                if (! is_array($plan)) {
                    continue;
                }
                foreach (['date', 'time'] as $field) {
                    if (($plan[$field] ?? null) === '') {
                        $plan[$field] = null;
                    }
                }
            }
            unset($plan);
        }
        foreach (['shared_password', 'shared_password_confirmation'] as $field) {
            if (($this->payload[$field] ?? null) === '') {
                $this->payload[$field] = null;
            }
        }
        $this->merge($this->payload);
    }

    public function rules(): array
    {
        $updating = $this->route('itinerary') instanceof TravelOverview;

        return [
            'payload' => ['required', 'string'],
            'title' => [$updating ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'overview_text' => [$updating ? 'sometimes' : 'present', 'nullable', 'string'],
            'template_type' => [$updating ? 'sometimes' : 'present', 'nullable', 'string', 'max:255'],
            'plans' => [$updating ? 'sometimes' : 'present', 'array'],
            'plans.*' => ['array'],
            'plans.*.id' => ['nullable', 'integer', 'min:1'],
            'plans.*.client_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,100}$/'],
            'plans.*.date' => ['nullable', 'date'],
            'plans.*.time' => ['nullable', 'date_format:H:i'],
            'plans.*.title' => ['nullable', 'string', 'max:255'],
            'plans.*.content' => ['nullable', 'string'],
            'plans.*.order' => [$updating ? 'required_without:plans.*.id' : 'required', 'integer', 'min:0'],
            'plans.*.existing_file_ids' => [$updating ? 'prohibited' : 'present', 'array'],
            'plans.*.removed_file_ids' => [$updating ? 'sometimes' : 'prohibited', 'array'],
            'plans.*.removed_file_ids.*' => ['integer', 'min:1', 'distinct'],
            'plans.*.existing_file_ids.*' => ['integer', 'min:1'],
            'packing_items' => [$updating ? 'sometimes' : 'present', 'array'],
            'packing_items.*' => ['array'],
            'packing_items.*.id' => ['nullable', 'integer', 'min:1'],
            'packing_items.*.name' => ['nullable', 'string', 'max:255'],
            'packing_items.*.is_checked' => [$updating ? 'required_without:packing_items.*.id' : 'present', 'boolean'],
            'packing_items.*.order' => [$updating ? 'required_without:packing_items.*.id' : 'required', 'integer', 'min:0'],
            'souvenirs' => [$updating ? 'sometimes' : 'present', 'array'],
            'souvenirs.*' => ['array'],
            'souvenirs.*.id' => ['nullable', 'integer', 'min:1'],
            'souvenirs.*.name' => ['nullable', 'string', 'max:255'],
            'souvenirs.*.is_checked' => [$updating ? 'required_without:souvenirs.*.id' : 'present', 'boolean'],
            'souvenirs.*.order' => [$updating ? 'required_without:souvenirs.*.id' : 'required', 'integer', 'min:0'],
            'notes' => [$updating ? 'sometimes' : 'present', 'array'],
            'notes.*' => ['array'],
            'notes.*.id' => ['nullable', 'integer', 'min:1'],
            'notes.*.title' => ['nullable', 'string', 'max:255'],
            'notes.*.text' => ['nullable', 'string'],
            'notes.*.order' => [$updating ? 'required_without:notes.*.id' : 'required', 'integer', 'min:0'],
            'deleted_plan_ids' => [$updating ? 'sometimes' : 'prohibited', 'array'],
            'deleted_plan_ids.*' => ['integer', 'min:1', 'distinct'],
            'deleted_packing_item_ids' => [$updating ? 'sometimes' : 'prohibited', 'array'],
            'deleted_packing_item_ids.*' => ['integer', 'min:1', 'distinct'],
            'deleted_souvenir_ids' => [$updating ? 'sometimes' : 'prohibited', 'array'],
            'deleted_souvenir_ids.*' => ['integer', 'min:1', 'distinct'],
            'deleted_note_ids' => [$updating ? 'sometimes' : 'prohibited', 'array'],
            'deleted_note_ids.*' => ['integer', 'min:1', 'distinct'],
            'files' => ['sometimes', 'array'],
            'files.*' => ['array'],
            'files.*.*' => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:10240'],
            'shared_password' => ['nullable', 'string', 'min:8', 'max:32', 'confirmed'],
            'viewer_share_expires_at' => ['nullable', 'date', 'after:now', function ($attribute, $value, $fail) {
                if ($value && Carbon::parse($value)->greaterThan(now()->addDays(SharedPassword::MAX_LIFETIME_DAYS))) {
                    $fail('The viewer share expiry exceeds the allowed lifetime.');
                }
            }],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->payloadIsValidJson) {
                $validator->errors()->add('payload', 'The payload field must contain a JSON object.');

                return;
            }

            foreach (['plans', 'packing_items', 'souvenirs', 'notes'] as $collection) {
                if (isset($this->payload[$collection]) && ! is_array($this->payload[$collection])) {
                    return;
                }
                foreach ($this->payload[$collection] ?? [] as $row) {
                    if (! is_array($row)) {
                        return;
                    }
                }
            }

            $clientIds = [];
            foreach ($this->payload['plans'] ?? [] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $clientId = $row['client_id'] ?? null;
                if (is_string($clientId)) {
                    if (in_array($clientId, $clientIds, true)) {
                        $validator->errors()->add("plans.{$index}.client_id", 'Plan client IDs must be unique.');
                    }
                    $clientIds[] = $clientId;
                }
            }
            foreach (array_keys($this->file('files', [])) as $clientId) {
                if (! in_array((string) $clientId, $clientIds, true)) {
                    $validator->errors()->add('files', 'An uploaded file references an unknown plan.');
                }
            }
            $uploadBytes = collect($this->allFiles()['files'] ?? [])->flatten(1)->sum(
                fn (UploadedFile $file) => $file->getSize() ?: 0,
            );
            if ($uploadBytes > 18 * 1024 * 1024) {
                $validator->errors()->add('files', '添付ファイル全体は18MB以下にしてください。');
            }

            $overview = $this->route('itinerary');
            if (! $overview instanceof TravelOverview) {
                foreach (['plans', 'packing_items', 'souvenirs', 'notes'] as $collection) {
                    foreach ($this->payload[$collection] ?? [] as $index => $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        if (! empty($row['id'])) {
                            $validator->errors()->add("{$collection}.{$index}.id", 'IDs are not accepted when creating an itinerary.');
                        }
                        if ($collection === 'plans' && ! empty($row['existing_file_ids'])) {
                            $validator->errors()->add("{$collection}.{$index}.existing_file_ids", 'Attachments cannot be retained when creating an itinerary.');
                        }
                    }
                }

                return;
            }
            if (filled($this->payload['shared_password'] ?? null)
                || filled($this->payload['viewer_share_expires_at'] ?? null)) {
                $validator->errors()->add('shared_password', 'Viewer sharing must be changed through the viewer-share endpoint.');
            }
            $planIds = [];
            foreach ($this->payload['plans'] ?? [] as $index => $row) {
                if (! empty($row['id'])) {
                    if (in_array((int) $row['id'], $planIds, true)
                        || ! $overview->plans()->whereKey($row['id'])->exists()) {
                        $validator->errors()->add("plans.{$index}.id", 'この予定は削除されたか、編集できません。入力内容を控えて再読み込みしてください。');
                    }
                    $planIds[] = (int) $row['id'];
                }
                $validFileIds = ! empty($row['id'])
                    ? $overview->plans()->whereKey($row['id'])->first()?->planFiles()->pluck('id')->all() ?? []
                    : [];
                foreach (is_array($row['removed_file_ids'] ?? null) ? $row['removed_file_ids'] : [] as $fileId) {
                    // Already removed attachments are an idempotent deletion.
                    if (! in_array((int) $fileId, $validFileIds) && PlanFile::whereKey($fileId)->exists()) {
                        $validator->errors()->add("plans.{$index}.removed_file_ids", 'An attachment does not belong to this plan.');
                    }
                }
            }
            foreach (['plans' => 'deleted_plan_ids', 'packing_items' => 'deleted_packing_item_ids', 'souvenirs' => 'deleted_souvenir_ids', 'notes' => 'deleted_note_ids'] as $collection => $deletedKey) {
                $deleted = $this->payload[$deletedKey] ?? [];
                if (! is_array($deleted)) {
                    continue;
                }
                foreach ($this->payload[$collection] ?? [] as $index => $row) {
                    if (! empty($row['id']) && in_array((int) $row['id'], $deleted)) {
                        $validator->errors()->add("{$collection}.{$index}.id", 'An item cannot be updated and deleted in the same request.');
                    }
                }
            }
            $this->checkScopedIds($validator, 'packing_items', fn ($id) => $overview->packingItems()
                ->where('user_id', $this->user()->id)->whereKey($id)->exists());
            $this->checkScopedIds($validator, 'souvenirs', fn ($id) => $overview->souvenirs()->whereKey($id)->exists());
            $this->checkScopedIds($validator, 'notes', fn ($id) => $overview->additionalComments()->whereKey($id)->exists());
        });
    }

    /** @return array<string, mixed> */
    public function payloadData(): array
    {
        $validated = $this->validated();
        $keys = ['deleted_plan_ids', 'deleted_packing_item_ids', 'deleted_souvenir_ids', 'deleted_note_ids', 'title', 'overview_text', 'template_type', 'plans', 'packing_items', 'souvenirs', 'notes', 'shared_password', 'shared_password_confirmation', 'viewer_share_expires_at'];

        return array_intersect_key($validated, array_flip($keys));
    }

    /** @return array<string, array<UploadedFile>> */
    public function uploads(): array
    {
        $uploads = [];
        foreach ($this->file('files', []) as $clientId => $files) {
            $uploads[$clientId] = array_values(array_filter(is_array($files) ? $files : [$files]));
        }

        return $uploads;
    }

    private function checkScopedIds(Validator $validator, string $collection, callable $exists): void
    {
        $seen = [];
        foreach ($this->payload[$collection] ?? [] as $index => $row) {
            if (empty($row['id'])) {
                continue;
            }
            $id = (int) $row['id'];
            if (in_array($id, $seen, true) || ! $exists($id)) {
                $validator->errors()->add("{$collection}.{$index}.id", 'この項目は削除されたか、編集できません。入力内容を控えて再読み込みしてください。');
            }
            $seen[] = $id;
        }
    }
}
