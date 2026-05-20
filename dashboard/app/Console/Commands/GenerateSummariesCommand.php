<?php

namespace App\Console\Commands;

use App\Enums\BlockStatus;
use App\Models\TimeBlock;
use App\Services\Summaries\SummaryGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateSummariesCommand extends Command
{
    protected $signature = 'tracker:generate-summaries
                            {--since= : Inicio (cualquier formato strtotime)}
                            {--until= : Fin (idem). Por defecto: now}
                            {--day=   : Atajo: solo el dia indicado (YYYY-MM-DD)}
                            {--force  : Sobrescribe summaries editados manualmente}';

    protected $description = 'Genera (o regenera) los resumenes textuales para los time_blocks del rango.';

    public function handle(SummaryGenerator $generator): int
    {
        $tz = config('tracker.display_timezone', 'UTC');

        if ($day = $this->option('day')) {
            $localDay  = CarbonImmutable::parse($day, $tz)->setTimezone($tz);
            $startUtc  = $localDay->startOfDay()->setTimezone('UTC');
            $endUtc    = $startUtc->copy()->addDay();
        } else {
            $since = $this->option('since') ?? '24 hours ago';
            $until = $this->option('until') ?? 'now';
            $startUtc = CarbonImmutable::parse($since, $tz)->setTimezone('UTC');
            $endUtc   = CarbonImmutable::parse($until, $tz)->setTimezone('UTC');
        }

        $this->info("Resumenes UTC [{$startUtc->toDateTimeString()} → {$endUtc->toDateTimeString()})");

        $blocks = TimeBlock::query()
            ->where('starts_at', '>=', $startUtc->format('Y-m-d H:i:s'))
            ->where('starts_at', '<',  $endUtc->format('Y-m-d H:i:s'))
            ->where('status', '!=', BlockStatus::Idle->value)
            ->whereNotNull('dominant_project_id')
            ->orderBy('starts_at')
            ->get();

        $force = (bool) $this->option('force');
        $touched = 0;
        $skipped = 0;

        foreach ($blocks as $block) {
            $existing = $block->summary;
            if ($existing && $existing->edited_by_user && ! $force) {
                $skipped++;
                continue;
            }
            $generator->syncForBlock($block, $force);
            $touched++;
        }

        $this->info("→ {$touched} resumenes generados, {$skipped} respetados (editados por usuario)");
        return self::SUCCESS;
    }
}
