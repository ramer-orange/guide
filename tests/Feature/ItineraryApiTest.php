<?php

use App\Models\AdditionalComment;
use App\Models\PackingItem;
use App\Models\Plan;
use App\Models\PlanFile;
use App\Models\Souvenir;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function itineraryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'title' => 'Tokyo',
        'overview_text' => null,
        'template_type' => null,
        'plans' => [],
        'packing_items' => [],
        'souvenirs' => [],
        'notes' => [],
    ], $overrides);
}

function createOwnedItinerary(User $owner, string $title = 'Trip'): TravelOverview
{
    $overview = TravelOverview::create(['user_id' => $owner->id, 'title' => $title]);
    $overview->travelMembers()->create(['user_id' => $owner->id, 'role' => 'owner']);

    return $overview;
}

it('reports guest and authenticated session state without requiring authentication', function () {
    $this->getJson('/api/v1/session')->assertOk()->assertExactJson([
        'authenticated' => false, 'user' => null, 'auth_error' => null,
    ])->assertHeader('Cache-Control', 'no-store, private');

    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/session')->assertOk()
        ->assertJsonPath('authenticated', true)->assertJsonPath('user.email', $user->email);
});

it('creates and returns a trip with its canonical DTO and permits empty full-replacement collections', function () {
    $user = User::factory()->create();
    Storage::fake('public');
    $payload = itineraryPayload([
        'title' => 'Tokyo',
        'overview_text' => 'Rail trip',
        'template_type' => 'domestic',
        'plans' => [[
            'client_id' => 'plan-one', 'date' => '2026-10-05', 'time' => '09:30',
            'title' => 'Asakusa', 'content' => 'Temple', 'order' => 0, 'existing_file_ids' => [],
        ]],
        'packing_items' => [['name' => 'Passport', 'is_checked' => false, 'order' => 0]],
        'souvenirs' => [['name' => 'Tea', 'is_checked' => true, 'order' => 0]],
        'notes' => [['title' => 'Hotel', 'text' => 'Check in', 'order' => 0]],
    ]);

    $response = $this->actingAs($user)->post('/api/v1/itineraries', [
        'payload' => json_encode($payload),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()->assertJsonPath('title', 'Tokyo')
        ->assertJsonPath('template_type', 'domestic')
        ->assertJsonPath('plans.0.time', '09:30')
        ->assertJsonPath('packing_items.0.name', 'Passport')
        ->assertJsonPath('permissions.can_edit', true)
        ->assertHeader('Cache-Control', 'no-store, private');
    $id = $response->json('id');

    $updatePayload = itineraryPayload(['title' => 'Cleared', 'revision' => $response->json('revision')]);
    $this->actingAs($user)->putJson("/api/v1/itineraries/{$id}", [
        'payload' => json_encode($updatePayload),
    ])->assertOk()->assertJsonPath('plans', [])->assertJsonPath('packing_items', [])
        ->assertJsonPath('souvenirs', [])->assertJsonPath('notes', []);

    expect(TravelOverview::findOrFail($id)->title)->toBe('Cleared')
        ->and(Plan::where('travel_id', $id)->count())->toBe(0)
        ->and(PackingItem::where('travel_id', $id)->count())->toBe(0)
        ->and(Souvenir::where('travel_id', $id)->count())->toBe(0)
        ->and(AdditionalComment::where('travel_id', $id)->count())->toBe(0);
});

it('normalizes blank plan date and time values inside the JSON multipart payload', function () {
    $user = User::factory()->create();
    $payload = itineraryPayload([
        'plans' => [[
            'client_id' => 'blank-date', 'date' => '', 'time' => '', 'title' => '',
            'content' => '', 'order' => 0, 'existing_file_ids' => [],
        ]],
    ]);
    $response = $this->actingAs($user)->postJson('/api/v1/itineraries', [
        'payload' => json_encode($payload),
    ])->assertCreated();
    $plan = TravelOverview::findOrFail($response->json('id'))->plans()->firstOrFail();
    expect($plan->date)->toBeNull()->and($plan->time)->toBeNull();
});

it('maps uploaded files to the stable plan client ID and removes attachments omitted on save', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $payload = itineraryPayload([
        'plans' => [[
            'client_id' => 'plan-one', 'date' => null, 'time' => null, 'title' => 'Ticket',
            'content' => null, 'order' => 0, 'existing_file_ids' => [],
        ]],
    ]);
    $response = $this->actingAs($user)->post('/api/v1/itineraries', [
        'payload' => json_encode($payload),
        'files' => ['plan-one' => [UploadedFile::fake()->image('ticket.png')]],
    ], ['Accept' => 'application/json'])->assertCreated();
    $tripId = $response->json('id');
    $file = PlanFile::query()->whereHas('plan', fn ($query) => $query->where('travel_id', $tripId))->firstOrFail();
    Storage::disk('public')->assertExists($file->path);
    expect($response->json('plans.0.files.0.file_name'))->toBe('ticket.png');

    $payload['plans'][0]['id'] = $response->json('plans.0.id');
    $payload['plans'][0]['existing_file_ids'] = [];
    $payload['revision'] = $response->json('revision');
    $this->actingAs($user)->post("/api/v1/itineraries/{$tripId}", [
        '_method' => 'PUT', 'payload' => json_encode($payload),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('plans.0.files', []);
    Storage::disk('public')->assertMissing($file->path);
    expect($file->fresh())->toBeNull();
});

it('stores each attachment disk and serves private R2 files through the authorized endpoint', function () {
    Storage::fake('public');
    Storage::fake('r2');
    config(['filesystems.uploads' => 'r2', 'filesystems.legacy_uploads' => 'public']);
    $owner = User::factory()->create();
    $payload = itineraryPayload(['plans' => [[
        'client_id' => 'r2-plan', 'title' => 'Receipt', 'order' => 0, 'existing_file_ids' => [],
    ]]]);
    $response = $this->actingAs($owner)->post('/api/v1/itineraries', [
        'payload' => json_encode($payload),
        'files' => ['r2-plan' => [UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf')]],
    ], ['Accept' => 'application/json'])->assertCreated();

    $trip = TravelOverview::findOrFail($response->json('id'));
    $file = $trip->plans()->firstOrFail()->planFiles()->firstOrFail();
    expect($file->disk)->toBe('r2')
        ->and($response->json('plans.0.files.0.url'))->toContain("/api/v1/itineraries/{$trip->id}/files/{$file->id}");
    Storage::disk('r2')->assertExists($file->path);
    Storage::disk('public')->assertMissing($file->path);
    $this->actingAs($owner)->get($response->json('plans.0.files.0.url'))->assertOk();

    $payload['plans'][0]['id'] = $trip->plans()->firstOrFail()->id;
    $payload['plans'][0]['existing_file_ids'] = [];
    $payload['revision'] = $trip->revision;
    $this->actingAs($owner)->put("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode($payload),
    ], ['Accept' => 'application/json'])->assertOk();
    Storage::disk('r2')->assertMissing($file->path);
});

it('uses the configured legacy disk for existing attachments without disk metadata', function () {
    Storage::fake('public');
    Storage::fake('r2');
    config(['filesystems.uploads' => 'r2', 'filesystems.legacy_uploads' => 'public']);
    $owner = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $plan = $trip->plans()->create(['plans_title' => 'Legacy file', 'order' => 0]);
    Storage::disk('public')->put('files/legacy.pdf', 'legacy');
    $file = $plan->planFiles()->create(['file_name' => 'legacy.pdf', 'path' => 'files/legacy.pdf']);

    expect($file->fresh()->disk)->toBeNull();
    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$file->id}")->assertOk();

    $this->actingAs($owner)->deleteJson("/api/v1/itineraries/{$trip->id}")->assertNoContent();
    Storage::disk('public')->assertMissing('files/legacy.pdf');
    Storage::disk('r2')->assertMissing('files/legacy.pdf');
});

it('returns validation errors for malformed payloads and database IDs on create', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/v1/itineraries', ['payload' => '{bad json'])->assertUnprocessable();
    $this->actingAs($user)->postJson('/api/v1/itineraries', ['payload' => '{bad json'])
        ->assertHeader('Cache-Control', 'no-store, private');
    $malformedRow = itineraryPayload(['plans' => ['not-an-object']]);
    $this->actingAs($user)->postJson('/api/v1/itineraries', ['payload' => json_encode($malformedRow)])->assertUnprocessable();

    $existingTrip = createOwnedItinerary($user, 'Existing');
    $existingPlan = $existingTrip->plans()->create(['plans_title' => 'Existing', 'order' => 0]);
    $payload = itineraryPayload([
        'plans' => [[
            'id' => $existingPlan->id, 'client_id' => 'plan-one', 'order' => 0, 'existing_file_ids' => [],
        ]],
    ]);
    $this->actingAs($user)->postJson('/api/v1/itineraries', ['payload' => json_encode($payload)])
        ->assertUnprocessable()->assertJsonValidationErrors('plans.0.id');
});

it('rejects retaining an attachment from a different plan in the same itinerary', function () {
    $owner = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $firstPlan = $trip->plans()->create(['plans_title' => 'First', 'order' => 0]);
    $secondPlan = $trip->plans()->create(['plans_title' => 'Second', 'order' => 1]);
    $file = $secondPlan->planFiles()->create(['file_name' => 'second.pdf', 'path' => 'files/second.pdf']);
    $payload = itineraryPayload([
        'revision' => $trip->revision,
        'plans' => [[
            'id' => $firstPlan->id, 'client_id' => 'first-plan', 'order' => 0,
            'existing_file_ids' => [$file->id],
        ], [
            'id' => $secondPlan->id, 'client_id' => 'second-plan', 'order' => 1,
            'existing_file_ids' => [],
        ]],
    ]);
    $this->actingAs($owner)->putJson("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode($payload),
    ])->assertUnprocessable()->assertJsonValidationErrors('plans.0.existing_file_ids');
    expect($firstPlan->fresh())->not->toBeNull()->and($file->fresh())->not->toBeNull();
});

it('allows multiple itineraries to use the same packing template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $payload = itineraryPayload(['template_type' => 'domestic']);
    $first = $this->postJson('/api/v1/itineraries', ['payload' => json_encode($payload)])->assertCreated();
    $second = $this->postJson('/api/v1/itineraries', ['payload' => json_encode($payload)])->assertCreated();
    expect($first->json('id'))->not->toBe($second->json('id'));
});

it('treats a blank optional initial viewer password as no viewer share', function () {
    $user = User::factory()->create();
    $payload = itineraryPayload([
        'shared_password' => '',
        'shared_password_confirmation' => '',
    ]);
    $response = $this->actingAs($user)->postJson('/api/v1/itineraries', [
        'payload' => json_encode($payload),
    ])->assertCreated();
    expect(TravelOverview::findOrFail($response->json('id'))->sharedPasswords)->toBeNull();
});

it('rejects foreign nested IDs and preserves another member personal packing list', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $trip->travelMembers()->create(['user_id' => $member->id, 'role' => 'member']);
    $foreignTrip = createOwnedItinerary($other, 'Other');
    $foreignPlan = $foreignTrip->plans()->create(['plans_title' => 'Private', 'order' => 0]);
    $personal = $trip->packingItems()->create([
        'user_id' => $owner->id, 'packing_name' => 'Owner bag', 'packing_is_checked' => false, 'order' => 0,
    ]);

    $this->actingAs($member)->getJson("/api/v1/itineraries/{$trip->id}")
        ->assertOk()->assertJsonPath('packing_items', []);

    $payload = itineraryPayload([
        'revision' => $trip->revision,
        'title' => 'Member edit',
        'plans' => [[
            'id' => $foreignPlan->id, 'client_id' => 'plan-one', 'order' => 0,
            'existing_file_ids' => [],
        ]],
    ]);
    $this->actingAs($member)->putJson("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode($payload),
    ])->assertUnprocessable();

    $payload['plans'] = [];
    $payload['packing_items'] = [['id' => $personal->id, 'name' => 'Steal', 'is_checked' => true, 'order' => 0]];
    $this->actingAs($member)->putJson("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode($payload),
    ])->assertUnprocessable();
    expect($personal->fresh()->packing_name)->toBe('Owner bag');
});

it('keeps a member packing list untouched when another member saves an empty list', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $trip->travelMembers()->create(['user_id' => $member->id, 'role' => 'member']);
    $personal = $trip->packingItems()->create([
        'user_id' => $owner->id, 'packing_name' => 'Owner bag', 'packing_is_checked' => false, 'order' => 0,
    ]);
    $payload = itineraryPayload(['revision' => $trip->revision, 'title' => 'Edited by member']);

    $this->actingAs($member)->putJson("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode($payload),
    ])->assertOk();
    expect($personal->fresh()->packing_name)->toBe('Owner bag');
});

it('rejects stale full-snapshot saves without deleting another member additions', function () {
    $owner = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $staleRevision = $trip->revision;

    $this->actingAs($owner)->putJson("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode(itineraryPayload([
            'revision' => $staleRevision,
            'plans' => [[
                'client_id' => 'member-added', 'title' => 'New shared plan', 'order' => 0,
                'existing_file_ids' => [],
            ]],
        ])),
    ])->assertOk()->assertJsonPath('revision', $staleRevision + 1);
    $addedPlanId = $trip->plans()->firstOrFail()->id;

    $this->actingAs($owner)->putJson("/api/v1/itineraries/{$trip->id}", [
        'payload' => json_encode(itineraryPayload([
            'revision' => $staleRevision,
            'title' => 'Stale editor save',
        ])),
    ])->assertStatus(409)->assertJsonPath(
        'message',
        '他のメンバーが先にしおりを更新しました。入力内容をコピーしてから再読み込みしてください。',
    );

    expect($trip->fresh()->title)->toBe('Tokyo')
        ->and($trip->plans()->whereKey($addedPlanId)->exists())->toBeTrue();
});

it('streams attachments only through an authorized itinerary-scoped endpoint', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $plan = $trip->plans()->create(['plans_title' => 'File', 'order' => 0]);
    $file = UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf');
    $path = $file->store('files', 'public');
    $record = $plan->planFiles()->create(['file_name' => 'ticket.pdf', 'path' => $path]);

    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$record->id}")
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($other)->getJson("/api/v1/itineraries/{$trip->id}/files/{$record->id}")->assertForbidden();
    $this->actingAs($owner)->getJson('/api/v1/itineraries/'.createOwnedItinerary($other, 'Different')->id.'/files/'.$record->id)
        ->assertForbidden();
});

it('previews safe image and PDF attachments inline through the authorized endpoint', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $plan = $trip->plans()->create(['plans_title' => 'Files', 'order' => 0]);

    Storage::disk('public')->put('files/preview.pdf', "%PDF-1.4\nPreview fixture\n%%EOF");
    Storage::disk('public')->put('files/preview.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p6kAAAAASUVORK5CYII='));
    Storage::disk('public')->put('files/spoofed.pdf', '<html><script>alert(1)</script></html>');
    $pdf = $plan->planFiles()->create(['file_name' => 'preview.pdf', 'path' => 'files/preview.pdf']);
    $png = $plan->planFiles()->create(['file_name' => 'preview.png', 'path' => 'files/preview.png']);
    $docx = $plan->planFiles()->create(['file_name' => 'preview.docx', 'path' => 'files/preview.pdf']);
    $spoofed = $plan->planFiles()->create(['file_name' => 'spoofed.pdf', 'path' => 'files/spoofed.pdf']);

    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$pdf->id}/preview")
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=preview.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertStreamedContent("%PDF-1.4\nPreview fixture\n%%EOF");
    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$png->id}/preview")
        ->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename=preview.png');
    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$docx->id}/preview")
        ->assertStatus(415);
    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$spoofed->id}/preview")
        ->assertStatus(415);
    $this->actingAs($other)->getJson("/api/v1/itineraries/{$trip->id}/files/{$pdf->id}/preview")
        ->assertForbidden();
    $this->actingAs($owner)->get("/api/v1/itineraries/{$trip->id}/files/{$pdf->id}")
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=preview.pdf');
});

it('verifies guest shared access in its session and invalidates it when revoked', function () {
    $owner = User::factory()->create();
    $trip = createOwnedItinerary($owner, 'Shared');
    $share = $trip->sharedPasswordHistory()->create([
        'shared_password' => Hash::make('viewer-pass'), 'expires_at' => now()->addDays(7), 'access_version' => 1,
    ]);

    $this->postJson("/api/v1/itineraries/{$trip->id}/shared-access", ['shared_password' => 'wrong-pass'])
        ->assertUnprocessable();
    $this->postJson("/api/v1/itineraries/{$trip->id}/shared-access", ['shared_password' => 'viewer-pass'])
        ->assertOk()->assertJsonPath('title', 'Shared')->assertJsonPath('permissions.can_edit', false)
        ->assertJsonPath('packing_items', []);
    $this->getJson("/api/v1/itineraries/{$trip->id}")->assertOk();

    $this->actingAs($owner)->deleteJson("/api/v1/itineraries/{$trip->id}/viewer-share")->assertOk();
    expect($share->fresh()->access_version)->toBe(2);
});

it('lets the owner extend an active viewer share without changing its password', function () {
    $owner = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $share = $trip->sharedPasswordHistory()->create([
        'shared_password' => Hash::make('viewer-pass'), 'expires_at' => now()->addDays(14), 'access_version' => 3,
    ]);
    $this->actingAs($owner)->putJson("/api/v1/itineraries/{$trip->id}/viewer-share", [
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ])->assertOk()->assertJsonPath('viewer_share.active', true);
    expect($share->fresh()->access_version)->toBe(4)
        ->and(Hash::check('viewer-pass', $share->fresh()->shared_password))->toBeTrue();
});

it('rate limits repeated failed API shared-password verification', function () {
    $owner = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $trip->sharedPasswordHistory()->create([
        'shared_password' => Hash::make('viewer-pass'), 'expires_at' => now()->addDays(7),
    ]);
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->postJson("/api/v1/itineraries/{$trip->id}/shared-access", ['shared_password' => 'wrong-pass'])
            ->assertUnprocessable();
    }
    $this->postJson("/api/v1/itineraries/{$trip->id}/shared-access", ['shared_password' => 'wrong-pass'])
        ->assertTooManyRequests();
});

it('lets only the owner add or remove itinerary members', function () {
    $owner = User::factory()->create();
    $invite = User::factory()->create();
    $otherOwner = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $otherTrip = createOwnedItinerary($otherOwner);
    $otherMember = $otherTrip->travelMembers()->create(['user_id' => User::factory()->create()->id, 'role' => 'member']);

    $memberId = $this->actingAs($owner)->postJson("/api/v1/itineraries/{$trip->id}/members", [
        'email' => $invite->email,
    ])->assertCreated()->assertJsonPath('member.user.email', $invite->email)->json('member.id');
    $this->actingAs($owner)->deleteJson("/api/v1/itineraries/{$trip->id}/members/{$memberId}")->assertNoContent();
    $this->actingAs($owner)->deleteJson("/api/v1/itineraries/{$trip->id}/members/{$otherMember->id}")->assertNotFound();
    $this->actingAs($invite)->postJson("/api/v1/itineraries/{$trip->id}/members", ['email' => $otherOwner->email])
        ->assertForbidden();
});

it('rejects viewer-share expiry beyond 180 days and forbids members from owner actions', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $trip->travelMembers()->create(['user_id' => $member->id, 'role' => 'member']);
    $this->actingAs($owner)->postJson('/api/v1/itineraries', [
        'payload' => json_encode(itineraryPayload([
            'shared_password' => 'viewer-pass',
            'shared_password_confirmation' => 'viewer-pass',
            'viewer_share_expires_at' => now()->addDays(181)->toIso8601String(),
        ])),
    ])->assertUnprocessable();

    $this->actingAs($member)->putJson("/api/v1/itineraries/{$trip->id}/viewer-share", [
        'shared_password' => 'new-viewer-pass',
        'shared_password_confirmation' => 'new-viewer-pass',
    ])->assertForbidden();
    $this->actingAs($member)->deleteJson("/api/v1/itineraries/{$trip->id}")->assertForbidden();
});

it('requires authentication for trip collections and creator-only mutations', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $trip = createOwnedItinerary($owner);
    $this->getJson('/api/v1/itineraries')->assertUnauthorized();
    $this->actingAs($other)->deleteJson("/api/v1/itineraries/{$trip->id}")->assertForbidden();
    $this->actingAs($owner)->deleteJson("/api/v1/itineraries/{$trip->id}")->assertNoContent();
});
