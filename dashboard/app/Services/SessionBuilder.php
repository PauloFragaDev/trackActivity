<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\Project;
use App\Models\ProjectMapping;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Construye una vista en "sesiones" a partir de activity_events crudos.
 *
 * Una sesión = secuencia de eventos contiguos del mismo (proyecto, app),
 * separados por menos de IDLE_GAP_MINUTES. Útil para M4 mientras no exista
 * el Aggregator/Scorer real (M3). En cuanto M3 esté, esta clase pasará a
 * leer `time_blocks` en lugar de events crudos.
 */
class SessionBuilder
{
    public function __construct(
        private readonly int $idleGapMinutes,
    ) {}

    public static function fromConfig(): self
    {
        return new self(idleGapMinutes: (int) config('tracker.idle_gap_minutes', 5));
    }

    /**
     * Devuelve un array de sesiones para el día dado (local TZ).
     * Cada sesión es un array asociativo con starts_at, ends_at,
     * project (Project|null), app, evidence (Collection<ActivityEvent>).
     */
    public function buildForDay(CarbonImmutable $localDay): array
    {
        $tz       = $this->displayTz();
        $startLoc = $localDay->setTimezone($tz)->startOfDay();
        $endLoc   = $startLoc->addDay();

        // En BBDD todo es UTC sin offset. Convertimos los bordes a UTC para el WHERE.
        $startUtc = $startLoc->setTimezone('UTC');
        $endUtc   = $endLoc->setTimezone('UTC');

        $events = ActivityEvent::query()
            ->where('occurred_at', '>=', $startUtc->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<',  $endUtc->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->get();

        if ($events->isEmpty()) {
            return [];
        }

        $mappings = $this->loadMappings();
        $projects = Project::all()->keyBy('id');

        $sessions = [];
        $current  = null;

        foreach ($events as $event) {
            if ($event->source === ActivityEvent::SOURCE_IDLE) {
                continue;   // M4: ignoramos idle (M3 lo manejará)
            }

            $projectId = $this->resolveProjectId($event, $mappings);
            $appKey    = $event->app ?? 'unknown';

            $occurred = Carbon::parse($event->occurred_at)->setTimezone('UTC');

            $sameSession = $current
                && $current['project_id'] === $projectId
                && $current['app']        === $appKey
                && $occurred->diffInMinutes($current['ends_at']) <= $this->idleGapMinutes;

            if (! $sameSession) {
                if ($current) {
                    $sessions[] = $this->finalize($current, $projects, $tz);
                }
                $current = [
                    'project_id' => $projectId,
                    'app'        => $appKey,
                    'starts_at'  => $occurred->copy(),
                    'ends_at'    => $occurred->copy(),
                    'evidence'   => collect([$event]),
                ];
                continue;
            }

            $current['ends_at'] = $occurred->copy();
            $current['evidence']->push($event);
        }

        if ($current) {
            $sessions[] = $this->finalize($current, $projects, $tz);
        }

        return $sessions;
    }

    private function finalize(array $current, Collection $projects, string $tz): array
    {
        $start = $current['starts_at']->copy();
        $end   = $current['ends_at']->copy();

        // Dar un mínimo de 1 minuto para sesiones muy cortas (un sólo evento).
        if ($end->equalTo($start)) {
            $end = $start->copy()->addMinute();
        }

        return [
            'project'           => $current['project_id']
                ? $projects->get($current['project_id'])
                : null,
            'app'               => $current['app'],
            'starts_at_local'   => $start->setTimezone($tz),
            'ends_at_local'     => $end->setTimezone($tz),
            'duration_minutes'  => max(1, (int) $start->diffInMinutes($end)),
            'evidence'          => $current['evidence'],
        ];
    }

    /**
     * Resuelve un proyecto candidato a partir de los mappings activos.
     * Implementación mínima para M4: substring case-insensitive sobre repo_name,
     * url, subject y title según el tipo de mapping. M3 lo reemplaza con el
     * Scorer ponderado.
     */
    private function resolveProjectId(ActivityEvent $event, array $mappingsByType): ?int
    {
        foreach (['repository', 'folder', 'url_pattern', 'email_subject', 'window_title'] as $type) {
            $mappings = $mappingsByType[$type] ?? [];

            foreach ($mappings as $mapping) {
                $haystack = match ($type) {
                    'repository'    => $event->repo_name,
                    'folder'        => $event->repo_name ?? data_get($event->metadata, 'cwd_hint'),
                    'url_pattern'   => $event->url ?? $event->title,
                    'email_subject' => $event->subject,
                    'window_title'  => $event->title,
                };

                if ($haystack === null || $haystack === '') {
                    continue;
                }
                if (stripos($haystack, $mapping->pattern) !== false) {
                    return $mapping->project_id;
                }
            }
        }
        return null;
    }

    /** @return array<string,array<ProjectMapping>> */
    private function loadMappings(): array
    {
        return ProjectMapping::enabled()
            ->get()
            ->groupBy('type')
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }

    private function displayTz(): string
    {
        return config('tracker.display_timezone', 'UTC');
    }
}
