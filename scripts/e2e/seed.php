<?php

declare(strict_types=1);

use App\Models\AdditionalComment;
use App\Models\PackingItem;
use App\Models\Plan;
use App\Models\PlanFile;
use App\Models\SharedPassword;
use App\Models\Souvenir;
use App\Models\TravelMember;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.sqlite.database');
if (app()->environment('production') || config('database.default') !== 'sqlite' || ! str_contains($database, 'e2e.sqlite')) {
    fwrite(STDERR, "E2E fixtures require a non-production SQLite database named e2e.sqlite.\n");
    exit(2);
}

if (DB::table('users')->exists()) {
    fwrite(STDERR, "The E2E database must be freshly migrated before seeding.\n");
    exit(2);
}

$owner = User::create([
    'name' => 'E2E Owner',
    'email' => 'owner@example.test',
    'google_id' => 'e2e-owner',
    'avatar' => null,
    'email_verified_at' => now(),
]);
$member = User::create([
    'name' => 'E2E Member',
    'email' => 'member@example.test',
    'google_id' => 'e2e-member',
    'avatar' => null,
    'email_verified_at' => now(),
]);
$invite = User::create([
    'name' => 'E2E Invite',
    'email' => 'invite@example.test',
    'google_id' => 'e2e-invite',
    'avatar' => null,
    'email_verified_at' => now(),
]);

$overview = TravelOverview::create([
    'user_id' => $owner->id,
    'title' => 'E2E Trip',
    'overviewText' => 'Seeded browser scenario',
]);
TravelMember::create(['travel_id' => $overview->id, 'user_id' => $owner->id, 'role' => TravelMember::ROLE_OWNER]);
TravelMember::create(['travel_id' => $overview->id, 'user_id' => $member->id, 'role' => TravelMember::ROLE_MEMBER]);

$firstPlan = Plan::create([
    'travel_id' => $overview->id,
    'date' => now()->toDateString(),
    'time' => '09:00',
    'plans_title' => 'E2E first stop',
    'content' => 'First seeded activity',
    'order' => 0,
]);
Plan::create([
    'travel_id' => $overview->id,
    'date' => now()->toDateString(),
    'time' => '12:00',
    'plans_title' => 'E2E second stop',
    'content' => 'Second seeded activity',
    'order' => 1,
]);

PackingItem::create([
    'travel_id' => $overview->id,
    'user_id' => $owner->id,
    'packing_name' => 'Owner passport',
    'packing_is_checked' => false,
    'order' => 0,
]);
PackingItem::create([
    'travel_id' => $overview->id,
    'user_id' => $member->id,
    'packing_name' => 'Member medicine',
    'packing_is_checked' => false,
    'order' => 0,
]);
Souvenir::create([
    'travel_id' => $overview->id,
    'souvenir_name' => 'E2E souvenir',
    'souvenir_is_checked' => false,
    'order' => 0,
]);
AdditionalComment::create([
    'travel_id' => $overview->id,
    'additionalComment_title' => 'E2E note',
    'additionalComment_text' => 'Seeded note',
    'order' => 0,
]);

$uploadDisk = (string) config('filesystems.uploads', 'public');
$attachmentPath = "e2e/{$overview->id}/fixture.txt";
Storage::disk($uploadDisk)->put($attachmentPath, 'E2E attachment content');
PlanFile::create(['plan_id' => $firstPlan->id, 'file_name' => 'fixture.txt', 'path' => $attachmentPath]);
$previewPdfPath = "e2e/{$overview->id}/fixture.pdf";
$previewPdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
Storage::disk($uploadDisk)->put($previewPdfPath, $previewPdf);
PlanFile::create(['plan_id' => $firstPlan->id, 'file_name' => 'fixture.pdf', 'path' => $previewPdfPath]);
$previewPngPath = "e2e/{$overview->id}/fixture.png";
$previewPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pXcAAAAASUVORK5CYII=', true);
Storage::disk($uploadDisk)->put($previewPngPath, $previewPng);
PlanFile::create(['plan_id' => $firstPlan->id, 'file_name' => 'fixture.png', 'path' => $previewPngPath]);

SharedPassword::create([
    'travel_id' => $overview->id,
    'shared_password' => Hash::make('viewer-pass-123'),
    'expires_at' => now()->addDays(3),
    'disabled_at' => null,
    'access_version' => 1,
]);

$cookieName = (string) config('session.cookie');
$makeSessionCookie = static function (User $user) use ($cookieName): array {
    Auth::forgetGuards();
    $session = app('session')->driver();
    $session->setId(Str::random(40));
    $session->start();
    $session->flush();
    Auth::guard('web')->login($user);
    $session->save();
    $sessionId = $session->getId();
    $encryptionKey = app('encrypter')->getKey();

    return [
        'name' => $cookieName,
        'value' => Crypt::encryptString(CookieValuePrefix::create($cookieName, $encryptionKey).$sessionId),
        'domain' => '127.0.0.1',
        'path' => '/',
        'httpOnly' => true,
        'secure' => false,
        'sameSite' => 'Lax',
    ];
};

echo json_encode([
    'travelId' => $overview->id,
    'ownerCookie' => $makeSessionCookie($owner),
    'memberCookie' => $makeSessionCookie($member),
    'inviteEmail' => $invite->email,
    'viewerPassword' => 'viewer-pass-123',
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
