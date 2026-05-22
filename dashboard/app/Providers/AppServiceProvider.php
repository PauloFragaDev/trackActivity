<?php

namespace App\Providers;

use App\Services\Aggregator;
use App\Services\Export\Exporter;
use App\Services\GitHub\GraphQlProjectClient;
use App\Services\GitHub\ProjectClient;
use App\Services\Scoring\MappingResolver;
use App\Services\Scoring\Scorer;
use App\Services\SessionBuilder;
use App\Services\Summaries\EvidenceExtractor;
use App\Services\Summaries\SummaryGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MappingResolver::class);
        $this->app->singleton(Scorer::class, fn ($app) => new Scorer($app->make(MappingResolver::class)));
        $this->app->singleton(Aggregator::class, fn ($app) => Aggregator::fromConfig($app->make(Scorer::class)));
        $this->app->singleton(EvidenceExtractor::class);
        $this->app->singleton(SummaryGenerator::class, fn ($app) => new SummaryGenerator($app->make(EvidenceExtractor::class)));
        $this->app->singleton(SessionBuilder::class, fn ($app) => SessionBuilder::fromConfig($app->make(SummaryGenerator::class)));
        $this->app->singleton(Exporter::class, fn ($app) => new Exporter(
            $app->make(SessionBuilder::class),
            $app->make(SummaryGenerator::class),
        ));

        // Cliente del GitHub Project (sincronización del Kanban).
        $this->app->bind(ProjectClient::class, GraphQlProjectClient::class);
    }

    public function boot(): void
    {
        //
    }
}
