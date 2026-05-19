<?php

namespace App\Console\Commands;

use App\Services\Export\ExportQuery;
use App\Services\Export\Exporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'tracker:export
                            {--from=         : Fecha desde (YYYY-MM-DD o expresion strtotime)}
                            {--to=           : Fecha hasta (idem). Inclusivo. Default: hoy}
                            {--project=*     : Filtra por codigo de proyecto (multiple)}
                            {--min-confidence=low : low|medium|high}
                            {--include-idle  : Incluye bloques idle}
                            {--group-by=session : session|project-day}
                            {--format=txt    : txt|md|csv}
                            {--output=       : Ruta de salida (default: stdout)}';

    protected $description = 'Exporta el timeline a TXT/Markdown/CSV.';

    public function handle(Exporter $exporter): int
    {
        $tz = config('tracker.display_timezone', 'UTC');

        $fromOpt = $this->option('from') ?? CarbonImmutable::now($tz)->subDays(7)->toDateString();
        $toOpt   = $this->option('to')   ?? CarbonImmutable::now($tz)->toDateString();

        $from = CarbonImmutable::parse($fromOpt, $tz)->startOfDay();
        $to   = CarbonImmutable::parse($toOpt,   $tz)->startOfDay()->addDay(); // exclusivo

        if ($to->lte($from)) {
            $this->error('to debe ser >= from');
            return self::FAILURE;
        }

        $query = new ExportQuery(
            fromLocal:     $from,
            toLocal:       $to,
            projectCodes:  array_values(array_filter((array) $this->option('project'))),
            minConfidence: (string) $this->option('min-confidence'),
            includeIdle:   (bool)   $this->option('include-idle'),
            groupBy:       (string) $this->option('group-by'),
            format:        (string) $this->option('format'),
        );

        $body = $exporter->render($exporter->buildReport($query));

        if ($out = $this->option('output')) {
            file_put_contents($out, $body);
            $this->info("→ Escrito en {$out} (" . strlen($body) . " bytes)");
        } else {
            $this->getOutput()->write($body);
        }
        return self::SUCCESS;
    }
}
