<?php

namespace App\Services\Scoring;

use App\Models\ActivityEvent;
use App\Models\ProjectMapping;
use App\Models\ScoringRule;

/**
 * Convierte un ActivityEvent en una lista de "contribuciones" (project_id,
 * signal_kind, weight, note) basandose en mappings activos.
 *
 * Las reglas se interpretan por (source, app, type_de_match):
 *   - source=window + app=code      → matchea repos por title  →  vscode_in_repo
 *   - source=window + app=terminal* → matchea repos por title  →  terminal_in_repo
 *   - source=window + cualquier app → mappings type=window_title → window_title_match
 *   - source=git    + modified>0    → matchea repos por repo_name → git_modified
 *   - source=browser                → mappings type=url_pattern → url_match
 *   - source=thunderbird            → mappings type=email_subject → email_match
 */
class MappingResolver
{
    /** Apps consideradas editores de codigo. */
    public const CODE_APPS = ['code', 'cursor', 'codium', 'vscodium'];

    /** Apps consideradas terminales. */
    public const TERMINAL_APPS = [
        'gnome-terminal',
        'com.mitchellh.ghostty',
        'ghostty',
        'alacritty',
        'kitty',
        'tilix',
        'xterm',
        'konsole',
    ];

    /** @var array<string,int> signal_kind => weight (cache por instancia) */
    private array $weights = [];

    /** @var array<string,list<ProjectMapping>> type => mappings (cache por instancia) */
    private array $mappingsByType = [];

    public function __construct()
    {
        $this->reload();
    }

    /** Refresca los caches (util para tests). */
    public function reload(): void
    {
        $this->weights = ScoringRule::query()
            ->where('enabled', true)
            ->pluck('weight', 'signal_kind')
            ->all();

        $this->mappingsByType = ProjectMapping::enabled()
            ->get()
            ->groupBy('type')
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }

    /**
     * @return list<array{project_id:int,signal_kind:string,weight:int,note:string}>
     */
    public function contributionsFor(ActivityEvent $event): array
    {
        return match ($event->source) {
            ActivityEvent::SOURCE_WINDOW      => $this->forWindow($event),
            ActivityEvent::SOURCE_GIT         => $this->forGit($event),
            ActivityEvent::SOURCE_BROWSER     => $this->forBrowser($event),
            ActivityEvent::SOURCE_THUNDERBIRD => $this->forThunderbird($event),
            default                            => [],
        };
    }

    // ──────────────────────────────────────────────
    // Per-source resolvers
    // ──────────────────────────────────────────────

    private function forWindow(ActivityEvent $event): array
    {
        $out = [];
        $app   = strtolower((string) $event->app);
        $title = (string) $event->title;
        $repo  = (string) $event->repo_name;          // poblado por el window collector cuando puede

        $isCode     = in_array($app, self::CODE_APPS, true);
        $isTerminal = in_array($app, self::TERMINAL_APPS, true);

        $kindForApp = $isCode
            ? ScoringRule::KIND_VSCODE_IN_REPO
            : ($isTerminal
                ? ScoringRule::KIND_TERMINAL_IN_REPO
                : ScoringRule::KIND_WINDOW_TITLE_MATCH);

        // Repository mappings: preferimos repo_name (extraido del titulo o cwd
        // por el collector) si esta poblado; si no, caemos al title como antes.
        $repoHaystack = $repo !== '' ? $repo : $title;
        if ($repoHaystack !== '') {
            foreach ($this->mappings('repository') as $m) {
                if (! $this->matches($m, $repoHaystack)) {
                    continue;
                }
                $note = $repo !== ''
                    ? "repo '{$repo}' matchea '{$m->pattern}'"
                    : "title contiene '{$m->pattern}'";
                $out[] = $this->contribution($m, $kindForApp, $note);
            }
        }

        // Folder mappings: cwd_hint del collector (terminales y a veces editores)
        $cwd = data_get($event->metadata, 'cwd_hint');
        if ($cwd) {
            $folderKind = $isCode
                ? ScoringRule::KIND_VSCODE_IN_REPO
                : ScoringRule::KIND_TERMINAL_IN_REPO;
            foreach ($this->mappings('folder') as $m) {
                if ($this->matches($m, (string) $cwd)) {
                    $out[] = $this->contribution(
                        $m,
                        $folderKind,
                        "cwd '{$cwd}' matchea folder '{$m->pattern}'"
                    );
                }
            }
        }

        // Window-title mappings (fallback generico, suma evidencia aunque ya
        // haya disparado repository).
        if ($title !== '') {
            foreach ($this->mappings('window_title') as $m) {
                if ($this->matches($m, $title)) {
                    $out[] = $this->contribution(
                        $m,
                        ScoringRule::KIND_WINDOW_TITLE_MATCH,
                        "title matchea window_title '{$m->pattern}'"
                    );
                }
            }
        }

        return $this->dedupeBest($out);
    }

    private function forGit(ActivityEvent $event): array
    {
        $out = [];
        $repo = (string) $event->repo_name;
        if ($repo === '') {
            return [];
        }

        // git_modified solo si hay archivos modificados; si no, signal mas debil.
        $hasMods = (int) ($event->modified_files ?? 0) > 0;

        foreach ($this->mappings('repository') as $m) {
            if (! $this->matches($m, $repo)) {
                continue;
            }
            $kind = $hasMods
                ? ScoringRule::KIND_GIT_MODIFIED
                : ScoringRule::KIND_GIT_COMMIT_RECENT;
            $out[] = $this->contribution(
                $m,
                $kind,
                "repo '{$repo}' matchea '{$m->pattern}'" . ($hasMods ? " (modified={$event->modified_files})" : ''),
            );
        }

        // Folder mappings sobre la ruta absoluta del repo
        $path = (string) data_get($event->metadata, 'path');
        if ($path !== '') {
            foreach ($this->mappings('folder') as $m) {
                if ($this->matches($m, $path)) {
                    $out[] = $this->contribution(
                        $m,
                        $hasMods ? ScoringRule::KIND_GIT_MODIFIED : ScoringRule::KIND_GIT_COMMIT_RECENT,
                        "path '{$path}' matchea folder '{$m->pattern}'"
                    );
                }
            }
        }

        return $this->dedupeBest($out);
    }

    private function forBrowser(ActivityEvent $event): array
    {
        $out = [];
        $url   = (string) $event->url;
        $title = (string) $event->title;
        $needle = $url !== '' ? $url : $title;
        if ($needle === '') return [];

        foreach ($this->mappings('url_pattern') as $m) {
            if ($this->matches($m, $needle)) {
                $out[] = $this->contribution(
                    $m,
                    ScoringRule::KIND_URL_MATCH,
                    "url/title matchea '{$m->pattern}'"
                );
            }
        }
        return $this->dedupeBest($out);
    }

    private function forThunderbird(ActivityEvent $event): array
    {
        $out = [];
        $subject = (string) $event->subject;
        if ($subject === '') return [];

        foreach ($this->mappings('email_subject') as $m) {
            if ($this->matches($m, $subject)) {
                $out[] = $this->contribution(
                    $m,
                    ScoringRule::KIND_EMAIL_MATCH,
                    "subject matchea '{$m->pattern}'"
                );
            }
        }
        return $this->dedupeBest($out);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** @return list<ProjectMapping> */
    private function mappings(string $type): array
    {
        return $this->mappingsByType[$type] ?? [];
    }

    private function matches(ProjectMapping $m, string $haystack): bool
    {
        if ($m->is_regex) {
            return @preg_match('/' . str_replace('/', '\/', $m->pattern) . '/i', $haystack) === 1;
        }
        return stripos($haystack, $m->pattern) !== false;
    }

    private function weight(string $kind): int
    {
        return (int) ($this->weights[$kind] ?? 0);
    }

    private function contribution(ProjectMapping $m, string $kind, string $note): array
    {
        $weight = $this->weight($kind) + (int) $m->weight_bonus;
        return [
            'project_id' => (int) $m->project_id,
            'signal_kind' => $kind,
            'weight'     => $weight,
            'note'       => $note,
        ];
    }

    /**
     * Si el mismo (project_id) recibe varias contribuciones del mismo evento,
     * nos quedamos con la de mayor peso para no inflar artificialmente.
     *
     * @param list<array{project_id:int,signal_kind:string,weight:int,note:string}> $contributions
     * @return list<array{project_id:int,signal_kind:string,weight:int,note:string}>
     */
    private function dedupeBest(array $contributions): array
    {
        $best = [];
        foreach ($contributions as $c) {
            $key = $c['project_id'];
            if (! isset($best[$key]) || $c['weight'] > $best[$key]['weight']) {
                $best[$key] = $c;
            }
        }
        return array_values($best);
    }
}
