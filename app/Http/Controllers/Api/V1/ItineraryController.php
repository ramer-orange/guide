<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Itineraries\PlanFileStorage;
use App\Actions\Itineraries\SaveItinerary;
use App\Http\Requests\Api\V1\SaveItineraryRequest;
use App\Http\Resources\Api\V1\ItineraryResource;
use App\Models\PlanFile;
use App\Models\TravelOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItineraryController
{
    public function index(): JsonResponse
    {
        $user = request()->user();
        $items = TravelOverview::query()->where(fn ($query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('travelMembers', fn ($members) => $members->where('user_id', $user->id)))
            ->latest()->get();

        return response()->json(['itineraries' => $items->map(fn ($item) => [
            'id' => $item->id, 'title' => $item->title, 'overview_text' => $item->overviewText,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'permissions' => ['can_edit' => true, 'can_delete' => (int) $item->user_id === (int) $user->id],
        ])->values()])->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveItineraryRequest $request, SaveItinerary $save): JsonResponse
    {
        $data = $request->payloadData();
        $overview = $save->handle($request->user(), $data, $request->uploads());

        return response()->json((new ItineraryResource($overview))->resolve($request), 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(TravelOverview $itinerary): JsonResponse
    {
        Gate::authorize('view', $itinerary);

        return response()->json((new ItineraryResource($itinerary))->resolve(request()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(SaveItineraryRequest $request, TravelOverview $itinerary, SaveItinerary $save): JsonResponse
    {
        Gate::authorize('update', $itinerary);
        $data = $request->payloadData();
        unset($data['shared_password'], $data['shared_password_confirmation'], $data['viewer_share_expires_at']);
        $overview = $save->handle($request->user(), $data, $request->uploads(), $itinerary);

        return response()->json((new ItineraryResource($overview))->resolve($request))
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(TravelOverview $itinerary, PlanFileStorage $fileStorage): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $itinerary);
        $files = $itinerary->plans()->with('planFiles')->get()->flatMap(fn ($plan) => $plan->planFiles);
        $objects = $files->map(fn (PlanFile $file) => ['disk' => $fileStorage->diskName($file), 'path' => $file->path]);
        $itinerary->delete();
        $fileStorage->deleteBestEffort($objects);

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    public function file(TravelOverview $itinerary, int $fileId, PlanFileStorage $fileStorage): StreamedResponse
    {
        Gate::authorize('view', $itinerary);
        $file = PlanFile::query()->whereHas('plan', fn ($query) => $query->where('travel_id', $itinerary->id))
            ->whereKey($fileId)->firstOrFail();
        abort_unless($fileStorage->exists($file), 404);
        $response = $fileStorage->download($file);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
