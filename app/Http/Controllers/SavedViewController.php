<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Save the current Leads filters under a name, or remove a saved view. Personal to each person. */
class SavedViewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']], ['name.required' => 'Give the view a name.']);
        $filters = array_filter($request->only([...Business::FILTERS, 'sort', 'dir', 'per']), fn ($v) => $v !== null && $v !== '');

        if (! $filters) {
            return back()->withErrors(['ids' => 'Pick some filters first, then save them as a view.']);
        }

        $view = $request->user()->savedViews()->updateOrCreate(['name' => $data['name']], ['filters' => $filters]);

        return redirect($view->url())->with('status', "Saved the view \"{$view->name}\".");
    }

    public function destroy(Request $request, SavedView $view): RedirectResponse
    {
        abort_unless($view->user_id === $request->user()->id, 403);
        $view->delete();

        return redirect()->route('leads', ['clear' => 1])->with('status', "Removed the view \"{$view->name}\".");
    }
}
