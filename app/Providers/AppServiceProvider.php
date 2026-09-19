<?php

namespace App\Providers;

use App\Support\ShopSettingsStore;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ShopSettingsStore::class, fn () => new ShopSettingsStore());
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if ($user->hasRole('owner')) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        View::composer('layouts.app', function ($view): void {
            $view->with('shop', app(ShopSettingsStore::class)->get());
        });
    }
}
