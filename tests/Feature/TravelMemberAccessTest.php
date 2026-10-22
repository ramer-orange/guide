<?php

use App\Models\SharedPassword;
use App\Models\TravelOverview;
use App\Models\User;
use App\Support\SharedAccess;
use Illuminate\Support\Facades\Hash;

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

function shareVerificationPath(TravelOverview $overview): string
{
    return "/api/v1/itineraries/{$overview->id}/shared-access";
}

it('rejects line breaks in member invitation email addresses through the API', function () {
    $owner = User::factory()->create();
    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Safe invitations',
        'overviewText' => null,
    ]);
    $overview->travelMembers()->create(['user_id' => $owner->id, 'role' => 'owner']);

    $this->actingAs($owner)
        ->postJson("/api/v1/itineraries/{$overview->id}/members", [
            'email' => "attacker@example.com\r\nBcc: victim@example.com",
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($overview->travelMembers()->count())->toBe(1);
});

it('stores the share record id and version after API password verification', function () {
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

    $this->postJson(shareVerificationPath($overview), [
        'shared_password' => 'correct-password',
    ])->assertOk()->assertJsonPath('permissions.can_edit', false);

    $sessionAccess = session(SharedAccess::sessionKey($overview->id));
    expect($sessionAccess['shared_password_id'])->toBe($share->id)
        ->and($sessionAccess['access_version'])->toBe(4);
});

it('invalidates shared API access immediately after version change revocation or expiry', function (string $change) {
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

    match ($change) {
        'version' => $share->update(['access_version' => 2]),
        'revocation' => $share->update(['disabled_at' => now()]),
        'expiry' => $share->update(['expires_at' => now()->subSecond()]),
    };

    $this->withSession(sharedAccessSession($overview, $share, 1))
        ->getJson("/api/v1/itineraries/{$overview->id}")
        ->assertForbidden();

    $this->assertFalse(session()->has(SharedAccess::sessionKey($overview->id)));
})->with(['version', 'revocation', 'expiry']);

it('rejects a session for an older share even when the latest share resets to version one', function () {
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
        ->getJson("/api/v1/itineraries/{$overview->id}")
        ->assertForbidden();
});

it('reissues an elapsed 180 day share lifecycle through the API', function () {
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

    $this->actingAs($owner)
        ->putJson("/api/v1/itineraries/{$overview->id}/viewer-share", [
            'shared_password' => 'replacement-password',
            'shared_password_confirmation' => 'replacement-password',
            'expires_at' => null,
        ])
        ->assertOk();

    $latestShare = $overview->fresh()->sharedPasswords;
    expect(SharedPassword::where('travel_id', $overview->id)->count())->toBe(2)
        ->and($latestShare->id)->not->toBe($oldShare->id)
        ->and($latestShare->access_version)->toBe(1)
        ->and(Hash::check('replacement-password', $latestShare->shared_password))->toBeTrue()
        ->and($latestShare->expires_at->timestamp)
        ->toBeBetween(now()->addDays(30)->subMinute()->timestamp, now()->addDays(30)->addMinute()->timestamp);
});

it('blocks after five failures across user agent changes for the same IP', function () {
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

    foreach (range(1, 4) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', "Browser {$attempt}")
            ->postJson(shareVerificationPath($overview), ['shared_password' => 'wrong-password'])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'Browser 5')
        ->postJson(shareVerificationPath($overview), ['shared_password' => 'wrong-password'])
        ->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'Another Browser')
        ->postJson(shareVerificationPath($overview), ['shared_password' => 'correct-password'])
        ->assertTooManyRequests();

    $this->assertFalse(session()->has(SharedAccess::sessionKey($overview->id)));
});

it('escalates a second completed abuse window to one hour through the API', function () {
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
        ->postJson(shareVerificationPath($overview), ['shared_password' => $password]);

    foreach (range(1, 4) as $_) {
        $postPassword('wrong-password')->assertUnprocessable();
    }
    $postPassword('wrong-password')->assertTooManyRequests();

    $this->travel(16)->minutes();

    foreach (range(1, 4) as $_) {
        $postPassword('wrong-password')->assertUnprocessable();
    }
    $postPassword('wrong-password')->assertTooManyRequests();

    $this->travel(16)->minutes();
    $postPassword('correct-password')->assertTooManyRequests();

    $this->travel(45)->minutes();
    $postPassword('correct-password')->assertOk();
});

it('clears completed abuse counters after successful API verification', function () {
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
        ->postJson(shareVerificationPath($overview), ['shared_password' => $password]);

    foreach (range(1, 4) as $_) {
        $postPassword('wrong-password')->assertUnprocessable();
    }
    $postPassword('wrong-password')->assertTooManyRequests();

    $this->travel(16)->minutes();
    $postPassword('correct-password')->assertOk();

    foreach (range(1, 4) as $_) {
        $postPassword('wrong-password')->assertUnprocessable();
    }
    $postPassword('wrong-password')->assertTooManyRequests();

    $this->travel(16)->minutes();
    $postPassword('correct-password')->assertOk();
});
