<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** The signed-in user's own password. Required after an admin creates or resets the account. */
class AccountController extends Controller
{
    public function editPassword(Request $request)
    {
        return view('account.password', ['forced' => $request->user()->must_change_password]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'current_password.current_password' => 'The current password is not correct.',
            'password.different' => 'Choose a password different from the current one.',
        ]);

        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();

        // Other devices signed in with the old password are signed out.
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Your password has been changed.');
    }
}
