<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TravelMember;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MemberController
{
    public function store(Request $request, TravelOverview $itinerary): JsonResponse
    {
        Gate::authorize('manageMembers', $itinerary);
        $data = $request->validate(['email' => ['required', 'not_regex:/[\r\n]/', 'email', Rule::exists('users', 'email')]]);
        $user = User::where('email', $data['email'])->firstOrFail();
        if ((int) $user->id === (int) $itinerary->user_id) {
            return response()->json(['message' => 'The itinerary owner is already a member.', 'errors' => ['email' => ['The itinerary owner is already a member.']]], 422);
        }
        $member = $itinerary->travelMembers()->firstOrCreate(
            ['user_id' => $user->id], ['role' => TravelMember::ROLE_MEMBER]
        );

        return response()->json(['member' => [
            'id' => $member->id,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'role' => $member->role,
        ]], 201);
    }

    public function destroy(TravelOverview $itinerary, TravelMember $member): \Illuminate\Http\Response
    {
        Gate::authorize('manageMembers', $itinerary);
        abort_unless((string) $member->travel_id === (string) $itinerary->id, 404);
        abort_if($member->user_id === $itinerary->user_id, 403);
        $member->delete();

        return response()->noContent();
    }
}
