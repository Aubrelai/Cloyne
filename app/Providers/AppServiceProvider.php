<?php

namespace App\Providers;

use App\Models\Draft;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Roles: use @can('edit') / @can('manage-team') in views and can:… on routes.
        Gate::define('edit', fn (User $user) => $user->canEdit());
        Gate::define('manage-team', fn (User $user) => $user->isOwner());

        // Online, every link uses https (the site address in APP_URL).
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Values the Owner changed in Settings override config/cloyne.php and .env.
        Setting::applyToConfig();

        // Drafts waiting for review: shown in the menu badge and the browser tab title.
        View::composer(['layouts.app', 'partials.sidebar', 'screens.dashboard'], function ($view) {
            $request = request();
            if (! $request->attributes->has('cloyne.pending')) {
                try {
                    $count = auth()->check() ? Draft::where('status', 'pending')->count() : 0;
                } catch (Throwable) {
                    $count = 0;
                }
                $request->attributes->set('cloyne.pending', $count);
            }
            $view->with('pendingDrafts', $request->attributes->get('cloyne.pending'));

            // Unread replies from people (version 2.0): the Inbox badge.
            if (! $request->attributes->has('cloyne.unread')) {
                try {
                    $unread = auth()->check() ? \App\Models\Reply::whereNull('read_at')->where('is_auto_reply', false)->count() : 0;
                } catch (Throwable) {
                    $unread = 0;
                }
                $request->attributes->set('cloyne.unread', $unread);
            }
            $view->with('unreadReplies', $request->attributes->get('cloyne.unread'));
        });
    }
}
