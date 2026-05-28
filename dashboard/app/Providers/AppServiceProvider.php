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
use App\Services\ModuleVisibility;
use App\Services\SchedulerManager;
use App\Services\TrackerManager;
use Illuminate\Support\Facades\View;
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
        // El layout muestra un mini-pill con el estado del tracking. El pill
        // se enciende si está vivo cualquiera de los dos procesos (daemon
        // o scheduler) — un clic en el botón los gestiona conjuntamente.
        View::composer('layouts.app', function ($view) {
            $tracker   = app(TrackerManager::class)->status()['running'];
            $scheduler = app(SchedulerManager::class)->status()['running'];
            $view->with('trackerRunning', $tracker || $scheduler);
        });

        // Visibilidad de módulos. Composer (lazy) en lugar de View::share
        // para no tocar BD durante boot — los tests crean tablas tarde y
        // ::share dispara consultas en cada boot del kernel.
        View::composer(['layouts.app', 'layouts.settings'], function ($view) {
            $view->with('modules', ModuleVisibility::all());
        });
    }
}
