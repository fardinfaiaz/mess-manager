<?php

namespace App\Providers;

use App\Models\EmergencyAlert;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Feeds the emergency-alert banner shown at the top of every page (F14)
        View::composer('layouts.app', function ($view) {
            $membership = auth()->user()?->activeMembership;

            $view->with('bannerAlerts', $membership
                ? EmergencyAlert::bannerFor($membership)
                : collect());
        });
    }
}
