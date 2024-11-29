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
    public function edit(TravelOverview $overview)
    {
        $overview->load('plans');
        return view('itineraries.edit', compact('overview'));
    }

    public function destroy(TravelOverview $overview)
    {
        $overview->delete();

        return redirect()->route('itineraries.index');
    }
}
