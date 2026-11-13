<?php

use App\Models\PlanFile;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || config('database.connections.pgsql.host') !== 'guide-db'
    || config('database.default') !== 'pgsql' || config('filesystems.uploads') !== 's3'
    || config('filesystems.disks.s3.endpoint') !== 'https://127.0.0.1:9297') {
    throw new RuntimeException('The attachment check requires the isolated AWS Emulator environment.');
}

function expectResult(bool $result, string $message): void
{
    if (! $result) {
        throw new RuntimeException($message);
    }
}

$owner = User::create(['name' => 'Emulator Owner', 'email' => 'owner@example.test', 'google_id' => 'emulator-owner']);
$session = app('session')->driver();
$session->start();
Auth::guard('web')->login($owner);
$session->save();
$cookieName = config('session.cookie');
$cookie = Crypt::encryptString(CookieValuePrefix::create($cookieName, app('encrypter')->getKey()).$session->getId());
$headers = [
    'Cookie' => $cookieName.'='.rawurlencode($cookie),
    'X-CSRF-TOKEN' => $session->token(),
];

$api = 'http://127.0.0.1/api/v1';
$authenticated = Http::acceptJson()->timeout(10)->withHeaders($headers)->get("$api/session")->throw();
expectResult($authenticated->json('user.email') === $owner->email, 'The owner session did not authenticate.');

$pdf = "%PDF-1.4\nGuide emulator smoke test\n%%EOF\n";
$plan = ['client_id' => 'ticket', 'title' => 'Ticket', 'order' => 0, 'existing_file_ids' => []];
$payload = [
    'title' => 'AWS Emulator smoke test',
    'overview_text' => null,
    'template_type' => null,
    'plans' => [$plan],
    'packing_items' => [],
    'souvenirs' => [],
    'notes' => [],
];
$created = Http::acceptJson()->timeout(10)->withHeaders($headers)
    ->attach('files[ticket][]', $pdf, 'ticket.pdf', ['Content-Type' => 'application/pdf'])
    ->post("$api/itineraries", ['payload' => json_encode($payload, JSON_THROW_ON_ERROR)])->throw();
expectResult($created->created(), 'Creating an itinerary with a PDF must return HTTP 201.');
$file = PlanFile::findOrFail($created->json('plans.0.files.0.id'));
expectResult($file->disk === 's3' && Storage::disk('s3')->exists($file->path), 'The PDF did not reach S3.');

$trip = "$api/itineraries/".$created->json('id');
$attachment = "$trip/files/{$file->id}";
$download = Http::acceptJson()->timeout(10)->withHeaders($headers)->get($attachment)->throw();
expectResult($download->ok() && $download->body() === $pdf, 'Downloading the PDF must return its original bytes.');
$preview = Http::acceptJson()->timeout(10)->withHeaders($headers)->get("$attachment/preview")->throw();
expectResult($preview->ok() && $preview->body() === $pdf && $preview->header('Content-Type') === 'application/pdf'
    && str_starts_with($preview->header('Content-Disposition'), 'inline'), 'Preview must serve the original PDF inline.');
$guest = Http::acceptJson()->timeout(10)->get($attachment);
expectResult($guest->forbidden(), 'A guest must receive HTTP 403.');

unset($plan['existing_file_ids']);
$plan['id'] = $file->plan_id;
$plan['removed_file_ids'] = [$file->id];
$updated = Http::acceptJson()->timeout(10)->withHeaders($headers)->put($trip, [
    'payload' => json_encode(['plans' => [$plan]], JSON_THROW_ON_ERROR),
])->throw();
expectResult($updated->ok() && $updated->json('plans.0.files') === [], 'The itinerary must report the removed PDF.');
expectResult(! Storage::disk('s3')->exists($file->path), 'The removed PDF must disappear from S3.');
$deleted = Http::acceptJson()->timeout(10)->withHeaders($headers)->get($attachment);
expectResult($deleted->notFound(), 'The removed PDF must return HTTP 404.');

echo "PDF upload, preview, download, guest authorization and deletion passed.\n";
