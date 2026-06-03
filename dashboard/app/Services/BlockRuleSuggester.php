<?php

namespace App\Services;

use App\Models\ActivityEvent;
use Illuminate\Support\Collection;

/**
 * A partir de la evidencia (activity_events) de una sesión, sugiere patrones
 * de mapeo candidatos para "aprender" del proyecto al que el usuario reasigna.
 *
 * Cada candidato mapea 1:1 con un tipo de `project_mappings`:
 *   - repo_name             → repository
 *   - basename de cwd_hint  → folder
 *   - host de la url        → url_pattern
 *
 * No se sugiere `window_title`: los títulos de ventana son demasiado ruidosos
 * para una regla automática (el editor de proyectos sigue permitiéndolo a mano).
 */
class BlockRuleSuggester
{
    /**
     * @param  Collection<int,ActivityEvent>  $events
     * @return list<array{type:string,pattern:string,label:string,count:int}>
     */
    public static function suggest(Collection $events): array
    {
        $tally = [];

        foreach ($events as $event) {
            foreach (self::candidatesFor($event) as [$type, $pattern, $label]) {
                if ($pattern === '') {
                    continue;
                }
                $key = $type . '|' . $pattern;
                $tally[$key] ??= ['type' => $type, 'pattern' => $pattern, 'label' => $label, 'count' => 0];
                $tally[$key]['count']++;
            }
        }

        $list = array_values($tally);
        usort($list, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['pattern'], $b['pattern']));

        return array_slice($list, 0, 4);
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private static function candidatesFor(ActivityEvent $event): array
    {
        $out = [];

        $repo = trim((string) ($event->repo_name ?? ''));
        if ($repo !== '') {
            $out[] = ['repository', $repo, "repo «{$repo}»"];
        }

        $cwd = trim((string) (($event->metadata['cwd_hint'] ?? '')));
        if ($cwd !== '') {
            $folder = basename(rtrim($cwd, '/'));
            if ($folder !== '' && $folder !== '/') {
                $out[] = ['folder', $folder, "carpeta «{$folder}»"];
            }
        }

        $url = trim((string) ($event->url ?? ''));
        if ($url !== '') {
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            if ($host !== '') {
                $out[] = ['url_pattern', $host, "url «{$host}»"];
            }
        }

        return $out;
    }
}
