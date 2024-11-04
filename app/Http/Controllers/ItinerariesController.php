<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ItinerariesController extends Controller
{
    public function create(Request $request)
    {
        return view('itineraries.create');
    }
}
