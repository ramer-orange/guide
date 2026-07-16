<?php

namespace App\Http\Controllers;

use App\Models\TravelMember;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ItinerariesController extends Controller
{
    public function index()
    {
        $overviews = TravelOverview::query()
            ->where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhereHas('travelMembers', function ($query) {
                        $query->where('user_id', auth()->id());
                    });
            })
            ->with('travelMembers')
            ->latest()
            ->get();

        return view('itineraries.index', compact('overviews'));
    }

    public function create(Request $request)
    {
        return view('itineraries.create');
    }

    public function edit(TravelOverview $overview)
    {
        if (! Gate::allows('view', $overview)) {
            if ($this->hasSharedPassword($overview)) {
                return redirect()->route('shared-access.show', ['id' => $overview->id]);
            }

            abort(403);
        }

        $overview->load(['plans.planFiles', 'souvenirs', 'additionalComments']);
        $overview->load(['packingItems' => function ($query) {
            $query->where('user_id', auth()->id());
        }]);
        $overview->load(['travelMembers.user']);

        if (! Gate::allows('update', $overview)) {
            return view('itineraries.show', [
                'overview' => $overview,
            ]);
        }

        return view('itineraries.edit', [
            'overview' => $overview,
            'isOwner' => Gate::allows('manageMembers', $overview),
        ]);
    }

    public function destroy(TravelOverview $overview)
    {
        Gate::authorize('delete', $overview);

        $overview->delete();

        return redirect()->route('itineraries.index');
    }

    public function storeMember(Request $request, TravelOverview $overview)
    {
        Gate::authorize('manageMembers', $overview);

        $validated = $request->validate([
            'email' => [
                'required',
                'not_regex:/[\r\n]/',
                'email',
                Rule::exists('users', 'email'),
            ],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        if ($user->id === $overview->user_id) {
            return back()->withErrors([
                'email' => '作成者はすでにメンバーです。',
            ]);
        }

        $overview->travelMembers()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => TravelMember::ROLE_MEMBER]
        );

        return back()->with('status', 'メンバーを追加しました。');
    }

    public function destroyMember(TravelOverview $overview, TravelMember $member)
    {
        Gate::authorize('manageMembers', $overview);
        abort_unless($member->travel_id === $overview->id, 404);
        abort_if($member->user_id === $overview->user_id, 403);

        $member->delete();

        return back()->with('status', 'メンバーを削除しました。');
    }

    private function hasSharedPassword(TravelOverview $overview): bool
    {
        return $overview->sharedPasswords?->isActive() ?? false;
    }
}
