<?php

use App\Models\TravelOverview;
use App\Models\User;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('configures R2 uploads without sending unsupported ACL headers', function () {
    $requests = [];
    config([
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'private-files',
        'filesystems.disks.r2.endpoint' => 'https://account.r2.example.test',
        'filesystems.disks.r2.http_handler' => function ($request) use (&$requests) {
            $requests[] = $request;

            return Create::promiseFor(new Response(200, ['ETag' => '"test-etag"']));
        },
    ]);
    Storage::forgetDisk('r2');

    Storage::disk('r2')->put('files/test.txt', 'private content');

    expect($requests)->toHaveCount(1)
        ->and($requests[0]->getHeaderLine('x-amz-acl'))->toBe('')
        ->and($requests[0]->getHeaderLine('x-amz-sdk-checksum-algorithm'))->toBe('')
        ->and((string) $requests[0]->getUri())->toContain('private-files.account.r2.example.test/files/test.txt')
        ->and($requests[0]->getHeaderLine('Authorization'))->toContain('/auto/s3/aws4_request');
});

it('uses recorded storage for old and new files and does not commit a trip when R2 upload fails', function () {
    Storage::fake('public');
    Storage::fake('r2');
    config(['filesystems.uploads' => 'r2', 'filesystems.legacy_uploads' => 'public']);
    $owner = User::factory()->create();
    $trip = TravelOverview::create(['user_id' => $owner->id, 'title' => 'Existing']);
    $trip->travelMembers()->create(['user_id' => $owner->id, 'role' => 'owner']);
    $plan = $trip->plans()->create(['plans_title' => 'Old', 'order' => 0]);
    Storage::disk('public')->put('files/old.pdf', 'legacy');
    $legacy = $plan->planFiles()->create(['file_name' => 'old.pdf', 'path' => 'files/old.pdf']);

    expect(app(\App\Actions\Itineraries\PlanFileStorage::class)->diskName($legacy))->toBe('public');

    $fileStorage = Mockery::mock(\App\Actions\Itineraries\PlanFileStorage::class);
    $fileStorage->shouldReceive('store')->once()->andThrow(new RuntimeException('R2 unavailable'));
    $fileStorage->shouldReceive('deleteBestEffort')->once()->with([]);
    $payload = [
        'title' => 'Must Roll Back', 'overview_text' => null, 'plans' => [[
            'client_id' => 'plan-one', 'order' => 0,
        ]], 'packing_items' => [], 'souvenirs' => [], 'notes' => [],
    ];
    expect(fn () => app(\App\Actions\Itineraries\SaveItinerary::class)->handle(
        $owner,
        $payload,
        ['plan-one' => [UploadedFile::fake()->create('failure.pdf', 10, 'application/pdf')]],
        null,
        $fileStorage,
    ))->toThrow(RuntimeException::class, 'R2 unavailable');

    expect(TravelOverview::where('title', 'Must Roll Back')->exists())->toBeFalse()
        ->and($legacy->fresh())->not->toBeNull()
        ->and(Storage::disk('public')->exists('files/old.pdf'))->toBeTrue();
});

it('refuses to roll back storage metadata while files use explicit disks', function () {
    $owner = User::factory()->create();
    $trip = TravelOverview::create(['user_id' => $owner->id, 'title' => 'R2 trip']);
    $plan = $trip->plans()->create(['plans_title' => 'R2 file', 'order' => 0]);
    $file = $plan->planFiles()->create(['file_name' => 'private.pdf', 'path' => 'files/private.pdf', 'disk' => 'r2']);
    $migration = require database_path('migrations/2026_10_06_000001_add_disk_to_plan_files_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cannot remove plan_files.disk');
    expect($file->fresh()->disk)->toBe('r2');
});
