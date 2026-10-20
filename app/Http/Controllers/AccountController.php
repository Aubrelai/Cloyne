<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Your own account: name and password. Every role can use this.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('screens.account', ['user' => $request->user()]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $request->user()->update($data);

        return back()->with('status', 'Name saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(User::MIN_PASSWORD)],
        ], [
            'current_password.current_password' => 'Your current password isn’t right.',
            'password.different' => 'Pick a new password, not the one you have now.',
        ]);

        $request->user()->update([
            'password' => $request->input('password'),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('status', 'Password changed.');
    }
}
