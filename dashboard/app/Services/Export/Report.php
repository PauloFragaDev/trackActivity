<?php

namespace App\Services\Export;

use Carbon\CarbonImmutable;

/**
 * Estructura en memoria que un renderer transforma a TXT/MD/CSV.
 *
 * `days` es un array ordenado por fecha local con la forma:
 *   [
 *     'YYYY-MM-DD' => [
 *       'date'      => CarbonImmutable,
 *       'sessions'  => list<array{...}>,     // sesiones del dia (filtradas)
 *       'totals'    => list<array{project_code,project_name,minutes}>,
 *     ],
 *     ...
 *   ]
 */
final class Report
{
    public function __construct(
        public readonly ExportQuery $query,
        public readonly array $days,            // ver shape arriba
        public readonly array $grandTotals,     // [['project_code','project_name','minutes'], ...]
        public readonly int   $totalMinutes,
    ) {}
}
