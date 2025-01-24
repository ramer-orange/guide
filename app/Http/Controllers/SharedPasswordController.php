<?php

namespace App\Http\Controllers;

use App\Models\TravelOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

class SharedPasswordController extends Controller
{
    public function show($id)
    {
        $travelOverview = TravelOverview::findOrFail($id);
        return view('itineraries.shared-access', compact('travelOverview'));
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'shared_password' => 'required|string'
        ]);

        $overview = TravelOverview::findOrFail($id);
        if (! $overview->sharedPasswords || ! Hash::check($request->input('shared_password'), $overview->sharedPasswords->shared_password)) {
            return back()->withErrors([
                'shared_password' => ['共有パスワードが一致しません']
            ]);
        }

        $request->session()->passwordConfirmed();

        // セッションで共有パスワードからの認証を管理
        session()->put("access_granted_$id", true);

        return redirect()->route('itineraries.edit', ['overview' => $id]);
    }
}
