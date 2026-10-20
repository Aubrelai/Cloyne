<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Owner-only: add people, change roles, reset passwords, remove people.
 * New people get a temporary password that the Owner shares with them;
 * they must change it the first time they sign in.
 */
class TeamController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('team', [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ], ['email.unique' => 'Someone on the team already uses that email.']);

        $password = User::temporaryPassword();
        $user = User::create($data + ['password' => $password, 'must_change_password' => true]);

        return redirect()->route('settings')
            ->with('temp_password', ['name' => $user->name, 'email' => $user->email, 'password' => $password])
            ->with('status', "{$user->name} added as {$user->roleLabel()}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validateWithBag('team', ['role' => ['required', Rule::in(array_keys(User::ROLES))]]);

        if ($user->isOwner() && $data['role'] !== 'owner' && User::ownerCount() <= 1) {
            return back()->withErrors(['role' => 'Cloyne needs at least one Owner. Make someone else Owner first.'], 'team');
        }

        $user->update($data);

        return back()->with('status', "{$user->name} is now {$user->roleLabel()}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['role' => 'Change your own password on your Account page.'], 'team');
        }

        $password = User::temporaryPassword();
        $user->update(['password' => $password, 'must_change_password' => true]);

        // Sign them out everywhere so the old password stops working right away.
        $this->endSessions($user);

        return redirect()->route('settings')
            ->with('temp_password', ['name' => $user->name, 'email' => $user->email, 'password' => $password])
            ->with('status', "New temporary password made for {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['role' => 'You can’t remove yourself.'], 'team');
        }

        if ($user->isOwner() && User::ownerCount() <= 1) {
            return back()->withErrors(['role' => 'Cloyne needs at least one Owner.'], 'team');
        }

        $this->endSessions($user);
        $user->delete();

        return back()->with('status', "{$user->name} removed. They can’t sign in any more.");
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->forceFill(['remember_token' => null])->save();
    }
}
