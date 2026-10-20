<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
