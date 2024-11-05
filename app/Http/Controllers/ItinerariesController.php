<?php

namespace App\Http\Controllers;

use App\Models\TravelOverview;
use Illuminate\Http\Request;

class ItinerariesController extends Controller
{
    public function create(Request $request)
    {
        return view('itineraries.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required | string | max:255',
            'overview' => 'nullable | string',
        ]);

        $overview = TravelOverview::create([
            'user_id' => auth()->id(),
            'title' => $validatedData['title'],
            'overview' => $validatedData['overview'],
        ]);

        return redirect()->route('itineraries.edit', $overview->id);
    }

    public function edit(TravelOverview $overview)
    {
        return view('itineraries.edit', compact('overview'));
    }

    public function update(Request $request, TravelOverview $overview)
    {
        $validatedData = $request->validate([
            'title' => 'required | string | max:255',
            'overview' => 'nullable | string',
        ]);

        $overview->update($validatedData);
        return redirect()->route('itineraries.edit', $overview);
    }
}
