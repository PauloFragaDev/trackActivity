<?php

namespace App\Providers;

use App\Services\SessionBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionBuilder::class, fn () => SessionBuilder::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
