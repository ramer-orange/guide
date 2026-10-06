<?php

use App\Actions\Itineraries\SaveItinerary;
use App\Livewire\EditPlansForm;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('rejects a stale legacy Livewire snapshot without deleting a concurrent plan or file', function () {
    Storage::fake('public');
    config(['filesystems.uploads' => 'public']);
    $owner = User::factory()->create();
    $trip = TravelOverview::create(['user_id' => $owner->id, 'title' => 'Original']);
    $trip->travelMembers()->create(['user_id' => $owner->id, 'role' => 'owner']);

    $legacyEditor = Livewire::actingAs($owner)
        ->test(EditPlansForm::class, ['overview' => $trip])
        ->set('title', 'Stale legacy title');

    app(SaveItinerary::class)->handle($owner, [
        'revision' => $trip->revision,
        'title' => 'Concurrent update',
        'overview_text' => null,
        'plans' => [[
            'client_id' => 'concurrent-plan',
            'title' => 'Concurrent plan',
            'order' => 0,
            'existing_file_ids' => [],
        ]],
        'packing_items' => [],
        'souvenirs' => [],
        'notes' => [],
    ], ['concurrent-plan' => [UploadedFile::fake()->image('concurrent.png')]], $trip);

    $plan = $trip->plans()->firstOrFail();
    $file = $plan->planFiles()->firstOrFail();
    $legacyEditor->call('submit')->assertNoRedirect();

    expect($trip->fresh()->title)->toBe('Concurrent update')
        ->and($trip->plans()->whereKey($plan->id)->exists())->toBeTrue()
        ->and($file->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists($file->path);
});
