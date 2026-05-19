<?php

namespace App\Services\Export;

use Carbon\CarbonImmutable;

/**
 * Parametros inmutables de una peticion de export.
 * Fechas siempre en la zona display (Europe/Madrid por defecto).
 */
final class ExportQuery
{
    public const GROUP_SESSION     = 'session';
    public const GROUP_PROJECT_DAY = 'project-day';

    public const CONF_LOW    = 'low';
    public const CONF_MEDIUM = 'medium';
    public const CONF_HIGH   = 'high';

    public const FORMAT_TXT = 'txt';
    public const FORMAT_MD  = 'md';
    public const FORMAT_CSV = 'csv';

    /**
     * @param list<string> $projectCodes  vacio = todos los proyectos
     */
    public function __construct(
        public readonly CarbonImmutable $fromLocal,
        public readonly CarbonImmutable $toLocal,           // exclusivo
        public readonly array $projectCodes = [],
        public readonly string $minConfidence = self::CONF_LOW,
        public readonly bool $includeIdle = false,
        public readonly string $groupBy = self::GROUP_SESSION,
        public readonly string $format = self::FORMAT_TXT,
        public readonly string $locale = 'es',
    ) {}

    public function minConfidenceValue(): float
    {
        return match ($this->minConfidence) {
            self::CONF_HIGH   => (float) config('tracker.confidence.high', 0.65),
            self::CONF_MEDIUM => (float) config('tracker.confidence.medium', 0.35),
            default            => 0.0,
        };
    }

    public function filename(): string
    {
        $from = $this->fromLocal->format('Ymd');
        $to   = $this->toLocal->copy()->subDay()->format('Ymd');
        $proj = empty($this->projectCodes) ? 'all' : implode('-', $this->projectCodes);
        return "trackactivity-{$from}-to-{$to}-{$proj}.{$this->format}";
    }
}
