<?php

namespace App\Console\Commands;

use App\Models\ActivityEvent;
use App\Models\GeneratedSummary;
use App\Models\Project;
use App\Models\ProjectMapping;
use App\Models\Repository;
use App\Models\ScoringRule;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DoctorCommand extends Command
{
    protected $signature = 'tracker:doctor';
    protected $description = 'Diagnostico del dashboard: BBDD, schema, datos y configuracion.';

    public function handle(): int
    {
        $ok = true;

        $this->line('=== Configuracion ===');
        $dbPath = config('database.connections.sqlite.database');
        $tz     = config('tracker.display_timezone');
        $appTz  = config('app.timezone');
        $this->line("BBDD:          {$dbPath}");
        $this->line("APP_TIMEZONE:  {$appTz}  (esperado: UTC)");
        $this->line("Display TZ:    {$tz}");

        if ($appTz !== 'UTC') {
            $this->warn('  ⚠ APP_TIMEZONE no es UTC. La convencion del proyecto es UTC en storage; el display ya respeta tracker.display_timezone.');
        }

        if (! file_exists($dbPath)) {
            $this->error("  ✗ Fichero de BBDD no existe");
            return self::FAILURE;
        }
        $this->info('  ✔ BBDD existe (' . $this->humanBytes((int) filesize($dbPath)) . ')');

        $this->line('');
        $this->line('=== Schema ===');
        foreach ([
            'activity_events', 'time_blocks', 'time_block_evidence',
            'generated_summaries', 'projects', 'project_mappings',
            'scoring_rules', 'repositories',
        ] as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->info("  ✔ {$table} (" . number_format($count) . ' filas)');
            } else {
                $this->error("  ✗ falta tabla {$table}");
                $ok = false;
            }
        }

        $this->line('');
        $this->line('=== Datos ===');
        $latest = ActivityEvent::query()->orderByDesc('occurred_at')->first();
        if ($latest) {
            $latestUtc = $latest->occurred_at;
            $latestLoc = $latestUtc->copy()->setTimezone($tz);
            $age = $latestUtc->diffForHumans();
            $this->line("Ultimo event: {$latestLoc->format('Y-m-d H:i:s')} ({$tz}) · {$age}");
            if ($latestUtc->lt(CarbonImmutable::now('UTC')->subHours(2))) {
                $this->warn('  ⚠ El ultimo event tiene mas de 2h. ¿El daemon esta corriendo?');
            }
        } else {
            $this->warn('Sin events. Arranca el daemon y vuelve a ejecutar.');
        }

        $projectsCount    = Project::count();
        $mappingsActive   = ProjectMapping::enabled()->count();
        $scoringActive    = ScoringRule::where('enabled', true)->count();
        $reposSeen        = Repository::count();
        $blocksToday      = TimeBlock::query()
            ->where('starts_at', '>=', CarbonImmutable::now($tz)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->count();
        $summariesToday   = GeneratedSummary::query()
            ->whereHas('timeBlock', fn ($q) => $q->where('starts_at', '>=', CarbonImmutable::now($tz)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s')))
            ->count();

        $this->line("Proyectos:         {$projectsCount}");
        $this->line("Mappings activos:  {$mappingsActive}");
        $this->line("Scoring rules:     {$scoringActive}");
        $this->line("Repos vistos:      {$reposSeen}");
        $this->line("Bloques hoy:       {$blocksToday}");
        $this->line("Summaries hoy:     {$summariesToday}");

        if ($projectsCount === 0)   { $this->warn('  ⚠ Sin proyectos. Ejecuta `php artisan db:seed`'); $ok = false; }
        if ($mappingsActive === 0)  { $this->warn('  ⚠ Sin mappings. Ejecuta `php artisan db:seed`'); $ok = false; }
        if ($scoringActive === 0)   { $this->warn('  ⚠ Sin scoring rules. Ejecuta `php artisan db:seed`'); $ok = false; }

        $this->line('');
        $this->line($ok ? '✔ Todo en orden' : '✗ Hay avisos arriba');
        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function humanBytes(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return number_format($bytes, 1) . ' ' . $u[$i];
    }
}
