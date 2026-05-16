<?php

use App\Livewire\EditPlansForm;
use App\Models\SharedPassword;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

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
    $viewer = User::factory()->create();

    $overview = TravelOverview::create([
        'user_id' => $owner->id,
        'title' => 'Read only trip',
        'overviewText' => 'Shared by password',
    ]);

    $overview->travelMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    SharedPassword::create([
        'travel_id' => $overview->id,
        'shared_password' => Hash::make('view-password'),
    ]);

    $this->withSession(["access_granted_{$overview->id}" => true])
        ->get(route('itineraries.edit', $overview))
        ->assertOk()
        ->assertSee('閲覧用共有リンクで表示中です。')
        ->assertDontSee('更新する');

    session()->put("access_granted_{$overview->id}", true);

    Livewire::actingAs($viewer)
        ->test(EditPlansForm::class, ['overview' => $overview])
        ->assertSet('canEdit', false)
        ->set('title', 'Changed title')
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
        ->call('submit')
        ->assertForbidden();

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
    ]);

    $this->get(route('itineraries.edit', $overview))
        ->assertRedirect(route('shared-access.show', ['id' => $overview->id]));
});
