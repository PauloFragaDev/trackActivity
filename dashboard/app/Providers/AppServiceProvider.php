<?php

namespace App\Providers;

use App\Services\Aggregator;
use App\Services\Scoring\MappingResolver;
use App\Services\Scoring\Scorer;
use App\Services\SessionBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MappingResolver::class);
        $this->app->singleton(Scorer::class, fn ($app) => new Scorer($app->make(MappingResolver::class)));
        $this->app->singleton(Aggregator::class, fn ($app) => Aggregator::fromConfig($app->make(Scorer::class)));
        $this->app->singleton(SessionBuilder::class, fn () => SessionBuilder::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
