<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\TravelOverview;
use Illuminate\Http\Request;

class ItinerariesController extends Controller
{
    public function index()
    {
        $overviews  = TravelOverview::where('user_id', auth()->id())->get();

        return view('itineraries.index', compact('overviews'));
    }
    public function create(Request $request)
    {
        return view('itineraries.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required | string | max:255',
            'overview' => 'nullable | string',
            'date' => 'nullable | date',
            'time' => 'nullable | date_format:H:i',
            'plans_title' => 'nullable | string | max:255',
            'content' => 'nullable | string',
        ]);

        $overview = TravelOverview::create([
            'user_id' => auth()->id(),
            'title' => $validatedData['title'],
            'overview' => $validatedData['overview'],
        ]);

        $plans = Plan::create([
            'travel_id' => $overview->id,
            'date' => $validatedData['date'],
            'time' => $validatedData['time'],
            'plans_title' => $validatedData['plans_title'],
            'content' => $validatedData['content'],
        ]);

        return redirect()->route('itineraries.edit', [$overview->id, $plans->id]);
    }

    public function edit(TravelOverview $overview)
    {
        $overview = TravelOverview::with(['plans'])->findOrFail($overview->id);
        return view('itineraries.edit', compact('overview'));
    }

    public function update(Request $request, TravelOverview $overview)
    {
        $validatedData = $request->validate([
            'title' => 'required | string | max:255',
            'overview' => 'nullable | string',
            'plans' => 'required | array',
            'plans.*.date' => 'nullable | date',
            'plans.*.time' => 'nullable | date_format:H:i',
            'plans.*.plans_title' => 'nullable | string | max:255',
            'plans.*.content' => 'nullable | string',
        ]);

        // TravelOverviewの更新
        $overview->update([
            'title' => $validatedData['title'],
            'overview' => $validatedData['overview'],
        ]);

        // 各プランの更新
        foreach ($validatedData['plans'] as $planId => $planData) {
            // プランが存在し、関連付けられていることを確認
            $plan = $overview->plans()->findOrFail($planId);
            $plan->update([
                'date' => $planData['date'],
                'time' => $planData['time'],
                'plans_title' => $planData['plans_title'],
                'content' => $planData['content'],
            ]);
        }

        return redirect()->route('itineraries.edit', $overview->id);
    }


    public function destroy(TravelOverview $overview)
    {
        $overview->delete();

        return redirect()->route('itineraries.index');
    }
}
