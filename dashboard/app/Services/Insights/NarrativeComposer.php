<?php

namespace App\Services\Insights;

/**
 * Compone la frase narrativa de un día/semana a partir de las métricas ya
 * calculadas por InsightsService. Función pura (array → string) para poder
 * testearla aislada. El motor LLM (engine=llm) podrá sustituir esto más
 * adelante sin tocar el resto.
 */
class NarrativeComposer
{
    /**
     * @param  'day'|'week'  $period
     * @param  array{active_minutes:int,idle_minutes:int,context_switches:int,by_project:list<array<string,mixed>>}  $m
     */
    public static function compose(string $period, array $m): string
    {
        $whenLower = $period === 'week' ? 'esta semana' : 'hoy';

        if ((int) ($m['active_minutes'] ?? 0) <= 0) {
            return 'Sin actividad registrada ' . $whenLower . '.';
        }

        $byProject = array_values(array_filter(
            $m['by_project'] ?? [],
            fn ($p) => (int) ($p['minutes'] ?? 0) > 0,
        ));

        $parts = [];
        if (isset($byProject[0])) {
            $parts[] = 'sobre todo ' . $byProject[0]['project_name'] . ' (' . self::fmt((int) $byProject[0]['minutes']) . ')';
        }
        if (isset($byProject[1])) {
            $parts[] = 'algo de ' . $byProject[1]['project_name'] . ' (' . self::fmt((int) $byProject[1]['minutes']) . ')';
        }

        $sentence = ($period === 'week' ? 'Esta semana' : 'Hoy') . ': ' . implode(', ', $parts);

        $extras = [];
        if ((int) ($m['idle_minutes'] ?? 0) > 0) {
            $extras[] = self::fmt((int) $m['idle_minutes']) . ' inactivo';
        }
        $cs = (int) ($m['context_switches'] ?? 0);
        $extras[] = $cs . ' ' . ($cs === 1 ? 'cambio de contexto' : 'cambios de contexto');

        return $sentence . '; ' . implode('; ', $extras) . '.';
    }

    private static function fmt(int $minutes): string
    {
        if ($minutes >= 60) {
            $h = intdiv($minutes, 60);
            $r = $minutes % 60;
            return $r > 0 ? "{$h}h {$r}m" : "{$h}h";
        }
        return "{$minutes}m";
    }
}
