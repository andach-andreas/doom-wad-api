<?php

namespace App\Providers;

use App\Models\Wad;
use App\Observers\WadObserver;
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
        Wad::observe(WadObserver::class);
    }
}
