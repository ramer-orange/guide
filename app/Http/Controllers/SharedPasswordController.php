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
        abort_unless($travelOverview->sharedPasswords?->isActive(), 404);

        return view('itineraries.shared-access', compact('travelOverview'));
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'shared_password' => 'required|string'
        ]);

        $overview = TravelOverview::findOrFail($id);
        if (! $overview->sharedPasswords?->isActive()
            || ! Hash::check($request->input('shared_password'), $overview->sharedPasswords->shared_password)) {
            return back()->withErrors([
                'shared_password' => ['閲覧用パスワードが一致しないか、共有が無効です']
            ]);
        }

        $request->session()->passwordConfirmed();

        // セッションで閲覧用パスワードからの認証を管理
        session()->put("access_granted_$id", true);

        return redirect()->route('itineraries.edit', ['overview' => $id]);
    }
}
