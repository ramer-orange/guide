<?php

use App\Livewire\EditPlansForm;
use App\Models\SharedPassword;
use App\Models\TravelOverview;
use App\Models\User;
use App\Support\SharedAccess;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function sharedAccessSession(TravelOverview $overview, ?SharedPassword $share = null, ?int $version = null): array
{
    $share ??= $overview->sharedPasswords;

    return [
        SharedAccess::sessionKey($overview->id) => [
            'shared_password_id' => $share->id,
            'access_version' => $version ?? $share->access_version,
            'authorized_at' => now()->toIso8601String(),
        ],
    ];
}

function fillRequiredEditState($component)
{
    return $component
        ->set('plans', [[
            'id' => null,
            'date' => null,
            'time' => null,
            'plans_title' => 'Dinner',
            'content' => null,
            'planFiles' => [],
            'order' => 0,
        ]])
        ->set('packingItems', [[
            'id' => null,
            'packing_name' => 'Passport',
            'packing_is_checked' => false,
            'order' => 0,
        ]])
        ->set('souvenirs', [[
            'id' => null,
            'souvenir_name' => 'Gift',
            'souvenir_is_checked' => false,
            'order' => 0,
        ]])
        ->set('additionalComments', [[
            'id' => null,
            'additionalComment_title' => 'Memo',
            'additionalComment_text' => 'Bring cash',
            'order' => 0,
        ]]);
}

it('rejects line breaks in member invitation email addresses', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Safe invitations',
        'overviewText' => null,
    ]);
    $overview->travelMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $this->actingAs($owner)
        ->post(route('itineraries.members.store', $overview), [
            'email' => "attacker@example.com\r\nBcc: victim@example.com",
        ])
        ->assertSessionHasErrors('email');

    expect($overview->travelMembers()->count())->toBe(1);
});

it('shows shared itineraries on a member dashboard without owner actions', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Shared trip',
        'overviewText' => 'Editable together',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $response = $this->actingAs($member)->get(route('itineraries.index'));

    $response->assertOk();
    $response->assertSee('Shared trip');
    $response->assertSee('共有されたしおり');
    $response->assertDontSee(route('itineraries.index.destroy', $overview), false);
});

it('does not let a member change the shared password from the edit form', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Shared trip',
        'overviewText' => 'Editable together',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $sharedPassword = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('old-password'),
        'expires_at' => now()->addDays(30),
    ]);

    Livewire::actingAs($member)
        ->test(EditPlansForm::class, ['overview' => $overview])
        ->set('plans', [[
            'id' => null,
            'date' => null,
            'time' => null,
            'plans_title' => 'Dinner',
            'content' => null,
            'planFiles' => [],
            'order' => 0,
        ]])
        ->set('packingItems', [[
            'id' => null,
            'packing_name' => 'Passport',
            'packing_is_checked' => false,
            'order' => 0,
        ]])
        ->set('souvenirs', [[
            'id' => null,
            'souvenir_name' => 'Gift',
            'souvenir_is_checked' => false,
            'order' => 0,
        ]])
        ->set('additionalComments', [[
            'id' => null,
            'additionalComment_title' => 'Memo',
            'additionalComment_text' => 'Bring cash',
            'order' => 0,
        ]])
        ->set('shared_password', 'new-password')
        ->set('shared_password_confirmation', 'new-password')
        ->call('submit')
        ->assertRedirect(route('itineraries.edit', $overview));

    expect(Hash::check('old-password', $sharedPassword->fresh()->shared_password))->toBeTrue();
});

it('lets shared password visitors view but not edit an itinerary', function () {
    $owner = User::factory()->create();

    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Read only trip',
        'overviewText' => 'Shared by password',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $sharedPassword = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('view-password'),
        'expires_at' => now()->addDays(30),
    ]);

    $overview->setRelation('sharedPasswords', $sharedPassword);

    $this->withSession(sharedAccessSession($overview))
        ->get(route('itineraries.edit', $overview))
        ->assertOk()
        ->assertSee('閲覧用共有リンクで表示中です。')
        ->assertDontSee('更新する');

    expect($overview->fresh()->title)->toBe('Read only trip');
});

it('redirects direct edit links to shared password entry when a password exists', function () {
    $owner = User::factory()->create();

    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Password protected trip',
        'overviewText' => 'Open by password',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('view-password'),
        'expires_at' => now()->addDays(30),
    ]);

    $this->get(route('itineraries.edit', $overview))
        ->assertRedirect(route('shared-access.show', ['id' => $overview->id]));
});

it('stores the share record id and version after password verification', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Shared trip',
        'overviewText' => null,
    ]);
    $share = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('correct-password'),
        'expires_at' => now()->addDays(30),
        'access_version' => 4,
    ]);

    $this->post(route('shared-access.verify', $overview->id), [
        'shared_password' => 'correct-password',
    ])->assertRedirect(route('itineraries.edit', $overview));

    $sessionAccess = session(SharedAccess::sessionKey($overview->id));

    expect($sessionAccess['shared_password_id'])->toBe($share->id);
    expect($sessionAccess['access_version'])->toBe(4);
});

it('invalidates shared access immediately after version change revocation or expiry', function (string $change) {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Protected trip',
        'overviewText' => null,
    ]);
    $share = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('correct-password'),
        'expires_at' => now()->addDays(30),
        'access_version' => 1,
    ]);
    $session = sharedAccessSession($overview, $share);

    match ($change) {
        'version' => $share->update(['access_version' => 2]),
        'revocation' => $share->update(['disabled_at' => now()]),
        'expiry' => $share->update(['expires_at' => now()->subSecond()]),
    };

    $response = $this->withSession($session)
        ->get(route('itineraries.edit', $overview));

    if ($change === 'version') {
        $response->assertRedirect(route('shared-access.show', ['id' => $overview->id]));
    } else {
        $response->assertForbidden();
    }

    $this->assertFalse(session()->has(SharedAccess::sessionKey($overview->id)));
})->with(['version', 'revocation', 'expiry']);

it('rejects a session for an older share even when the latest version resets to one', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Reissued trip',
        'overviewText' => null,
    ]);
    $oldShare = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('old-password'),
        'expires_at' => now()->addDays(30),
        'access_version' => 1,
    ]);
    $oldShare->forceFill(['created_at' => now()->subDay()])->save();

    $newShare = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('new-password'),
        'expires_at' => now()->addDays(30),
        'access_version' => 1,
    ]);

    expect($overview->fresh()->sharedPasswords->is($newShare))->toBeTrue();

    $this->withSession(sharedAccessSession($overview, $oldShare))
        ->get(route('itineraries.edit', $overview))
        ->assertRedirect(route('shared-access.show', ['id' => $overview->id]));
});

it('reissues an elapsed 180 day lifecycle with a new id and configured default expiry', function () {
    config(['shared-access.default_lifetime_days' => 30]);

    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Long-lived trip',
        'overviewText' => null,
    ]);
    $oldShare = SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('old-password'),
        'expires_at' => now()->subDay(),
        'access_version' => 8,
    ]);
    $oldShare->forceFill([
        'created_at' => now()->subDays(181),
        'updated_at' => now()->subDays(181),
    ])->save();

    $component = Livewire::actingAs($owner)
        ->test(EditPlansForm::class, ['overview' => $overview])
        ->call('showPasswordFields')
        ->set('shared_password', 'replacement-password')
        ->set('shared_password_confirmation', 'replacement-password')
        ->set('viewer_share_expires_at', null);

    fillRequiredEditState($component)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('itineraries.edit', $overview));

    $latestShare = $overview->fresh()->sharedPasswords;

    expect(SharedPassword::where('travel_id', $overview->id)->count())->toBe(2);
    expect($latestShare->id)->not->toBe($oldShare->id);
    expect($latestShare->access_version)->toBe(1);
    expect(Hash::check('replacement-password', $latestShare->shared_password))->toBeTrue();
    expect($latestShare->expires_at->timestamp)
        ->toBeBetween(now()->addDays(30)->subMinute()->timestamp, now()->addDays(30)->addMinute()->timestamp);
});

it('blocks after exactly five failures across user agent changes for the same ip', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Throttled trip',
        'overviewText' => null,
    ]);
    SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('correct-password'),
        'expires_at' => now()->addDays(30),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', "Browser {$attempt}")
            ->post(route('shared-access.verify', $overview->id), [
                'shared_password' => 'wrong-password',
            ])->assertSessionHasErrors('shared_password');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'Another Browser')
        ->post(route('shared-access.verify', $overview->id), [
            'shared_password' => 'correct-password',
        ])->assertSessionHasErrors('shared_password');

    $this->assertFalse(session()->has(SharedAccess::sessionKey($overview->id)));
});

it('escalates a second completed abuse window to one hour', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Repeated abuse trip',
        'overviewText' => null,
    ]);
    SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('correct-password'),
        'expires_at' => now()->addDays(30),
    ]);
    $postPassword = fn (string $password) => $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->post(route('shared-access.verify', $overview->id), ['shared_password' => $password]);

    foreach (range(1, 5) as $_) {
        $postPassword('wrong-password');
    }

    $this->travel(16)->minutes();

    foreach (range(1, 5) as $_) {
        $postPassword('wrong-password');
    }

    $this->travel(16)->minutes();
    $postPassword('correct-password')->assertSessionHasErrors('shared_password');

    $this->travel(45)->minutes();
    $postPassword('correct-password')->assertRedirect(route('itineraries.edit', $overview));
});

it('clears completed abuse counters after a successful verification', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Recovered trip',
        'overviewText' => null,
    ]);
    SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('correct-password'),
        'expires_at' => now()->addDays(30),
    ]);
    $postPassword = fn (string $password) => $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
        ->post(route('shared-access.verify', $overview->id), ['shared_password' => $password]);

    foreach (range(1, 5) as $_) {
        $postPassword('wrong-password');
    }

    $this->travel(16)->minutes();
    $postPassword('correct-password')->assertRedirect(route('itineraries.edit', $overview));

    foreach (range(1, 5) as $_) {
        $postPassword('wrong-password');
    }

    $this->travel(16)->minutes();
    $postPassword('correct-password')->assertRedirect(route('itineraries.edit', $overview));
});
