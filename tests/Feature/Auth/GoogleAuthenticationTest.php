<?php

use App\Models\User;
use Database\Seeders\ProductionDemoSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function mockGoogleCallback(array $attributes, array $raw = []): void
{
    $attributes = array_merge([
        'id' => 'google-subject-1',
        'name' => 'Google User',
        'email' => 'user@example.com',
        'avatar' => 'https://example.com/avatar.png',
    ], $attributes);

    $provider = Mockery::mock();
    $provider->shouldReceive('user')
        ->once()
        ->andReturn((new SocialiteUser)->setRaw(array_merge([
            'sub' => $attributes['id'],
            'email_verified' => true,
        ], $raw))->map($attributes));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);
}

test('login redirects to google oauth', function () {
    $provider = Mockery::mock();
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://accounts.google.com/o/oauth2/v2/auth'));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $this->get(route('login'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth');
});

test('local registration and password authentication routes are unavailable', function () {
    $this->get('/register')->assertRedirect('/login');

    expect(Route::has('password.request'))->toBeFalse();
    expect(Route::has('password.reset'))->toBeFalse();
    expect(Route::has('password.update'))->toBeFalse();
    expect(Route::has('password.confirm'))->toBeFalse();
});

test('google callback links a verified legacy user once without creating a duplicate', function () {
    $legacyUser = User::factory()->create([
        'google_id' => null,
        'email' => 'legacy@example.com',
        'name' => 'Legacy Name',
    ]);

    mockGoogleCallback([
        'id' => 'new-google-subject',
        'name' => 'Google Name',
        'email' => 'LEGACY@example.com',
    ]);

    $this->get(route('google.callback'))
        ->assertRedirect(route('itineraries.index', absolute: false));

    $this->assertAuthenticatedAs($legacyUser->fresh());
    expect(User::count())->toBe(1);
    expect($legacyUser->fresh())
        ->google_id->toBe('new-google-subject')
        ->email->toBe('legacy@example.com');
});

test('google callback authenticates by immutable provider subject after linking', function () {
    $user = User::factory()->create([
        'google_id' => 'google-subject-1',
        'email' => 'old@example.com',
        'name' => 'Old Name',
    ]);

    mockGoogleCallback([
        'id' => 'google-subject-1',
        'name' => 'Updated Name',
        'email' => 'new@example.com',
    ]);

    $this->get(route('google.callback'))
        ->assertRedirect(route('itineraries.index', absolute: false));

    $this->assertAuthenticatedAs($user->fresh());
    expect(User::count())->toBe(1);
    expect($user->fresh())
        ->google_id->toBe('google-subject-1')
        ->email->toBe('new@example.com');
});

test('google callback rejects missing or unverified provider email data', function (array $attributes, array $raw) {
    mockGoogleCallback($attributes, $raw);

    $this->get(route('google.callback'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('auth_error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    'missing email' => [['email' => null], []],
    'missing verification claim' => [[], ['email_verified' => null]],
    'unverified email' => [[], ['email_verified' => false]],
    'non-boolean verification claim' => [[], ['email_verified' => 'true']],
]);

test('google callback rejects an email already linked to another provider subject', function () {
    User::factory()->create([
        'google_id' => 'existing-subject',
        'email' => 'claimed@example.com',
    ]);

    mockGoogleCallback([
        'id' => 'different-subject',
        'email' => 'claimed@example.com',
    ]);

    $this->get(route('google.callback'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('auth_error');

    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('google callback rejects when a provider subject changes to another users email', function () {
    User::factory()->create([
        'google_id' => 'google-subject-1',
        'email' => 'subject@example.com',
    ]);
    User::factory()->create([
        'google_id' => 'google-subject-2',
        'email' => 'other@example.com',
    ]);

    mockGoogleCallback([
        'id' => 'google-subject-1',
        'email' => 'other@example.com',
    ]);

    $this->get(route('google.callback'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('auth_error');

    $this->assertGuest();
    expect(User::where('google_id', 'google-subject-1')->value('email'))->toBe('subject@example.com');
});

test('users table no longer stores local passwords', function () {
    expect(Schema::hasColumn('users', 'password'))->toBeFalse();
    expect(Schema::hasColumn('users', 'google_id'))->toBeTrue();
});

test('production demo seeding leaves new users available for verified google linking', function () {
    $this->seed(ProductionDemoSeeder::class);

    expect(User::where('email', 'ra.mer.web1111@gmail.com')->value('google_id'))->toBeNull();
    expect(User::where('email', 'test@test.com')->value('google_id'))->toBeNull();
});

test('production demo seeding does not overwrite an existing google identity', function () {
    User::factory()->create([
        'email' => 'test@test.com',
        'google_id' => 'real-google-subject',
    ]);

    $this->seed(ProductionDemoSeeder::class);

    expect(User::where('email', 'test@test.com')->value('google_id'))->toBe('real-google-subject');
});
