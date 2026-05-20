<?php

namespace App\Services\Summaries;

use App\Enums\SummaryEngine;
use App\Models\ActivityEvent;
use App\Models\GeneratedSummary;
use App\Models\Project;
use App\Models\TimeBlock;
use Illuminate\Support\Collection;

/**
 * Genera resumenes textuales para `time_blocks` con engine `template`.
 *
 * Mantiene la convencion del proyecto: una sola frase corta, profesional,
 * lista para pegar en un timesheet. M5 v1 solo template; M5+ aniadira LLM
 * opcional. Idempotente y respeta `edited_by_user=true`.
 */
class SummaryGenerator
{
    public const MAX_LENGTH = 240;
    public const MAX_BRANCHES_INLINE = 3;
    public const MAX_COMMITS_INLINE = 3;
    public const COMMIT_TRUNCATE = 80;

    public function __construct(
        private readonly EvidenceExtractor $extractor,
    ) {}

    /**
     * Persiste el resumen del bloque (idempotente). Si existe y
     * edited_by_user=true, no lo toca salvo $force=true.
     */
    public function syncForBlock(TimeBlock $block, bool $force = false): GeneratedSummary
    {
        $existing = $block->summary;
        if ($existing && $existing->edited_by_user && ! $force) {
            return $existing;
        }

        $block->loadMissing(['project', 'evidence.activityEvent']);
        $events = $block->evidence
            ->map(fn ($ev) => $ev->activityEvent)
            ->filter()
            ->unique('id');

        $text = $this->renderText($block->project, $events);

        if ($existing) {
            $existing->update([
                'text'           => $text,
                'engine'         => SummaryEngine::Template,
                'edited_by_user' => $force ? false : $existing->edited_by_user,
            ]);
            return $existing->fresh();
        }

        return GeneratedSummary::create([
            'time_block_id'  => $block->id,
            'text'           => $text,
            'engine'         => SummaryEngine::Template,
            'edited_by_user' => false,
            'generated_at'   => now('UTC'),
        ]);
    }

    /**
     * Variante no persistida util para mostrar una sesion (conjunto de
     * bloques contiguos del mismo proyecto). Sintetiza un solo texto.
     *
     * @param Collection<int,ActivityEvent> $events
     */
    public function renderText(?Project $project, Collection $events): string
    {
        $data = $this->extractor->extract($events);

        if ($project === null) {
            // Sin proyecto: actividad sin atribucion.
            return 'Actividad sin proyecto asignado en este intervalo.';
        }

        $parts = [];
        $parts[] = 'Trabajo en ' . $project->name;

        // Branches (truncadas si son muchas)
        if (! empty($data['branches'])) {
            $parts[count($parts) - 1] .= ' sobre ' . $this->listJoin(
                array_slice($data['branches'], 0, self::MAX_BRANCHES_INLINE),
                count($data['branches']) > self::MAX_BRANCHES_INLINE ? (count($data['branches']) - self::MAX_BRANCHES_INLINE) : 0,
            );
        } elseif (! empty($data['repos'])) {
            // Sin branches pero con repos: incluye repos
            $parts[count($parts) - 1] .= ' (' . $this->listJoin(
                array_slice($data['repos'], 0, 3),
                count($data['repos']) > 3 ? (count($data['repos']) - 3) : 0,
            ) . ')';
        }

        // Commit messages
        if (! empty($data['commit_messages'])) {
            $commits = array_map(
                fn ($m) => $this->truncate($m, self::COMMIT_TRUNCATE),
                array_slice($data['commit_messages'], 0, self::MAX_COMMITS_INLINE),
            );
            $sentence = 'Cambios principales: ' . implode('; ', $commits);
            $parts[] = $sentence;
        }

        // Issues / PRs / Jira (mostramos al menos un grupo si existe)
        $refs = [];
        if (! empty($data['github_prs'])) {
            $refs[] = 'PRs ' . implode(', ', $data['github_prs']);
        }
        if (! empty($data['github_issues'])) {
            $refs[] = 'issues ' . implode(', ', $data['github_issues']);
        }
        if (! empty($data['jira_tickets'])) {
            $refs[] = 'tickets ' . implode(', ', $data['jira_tickets']);
        }
        if (! empty($refs)) {
            $parts[] = 'Referencias: ' . implode(' · ', $refs);
        }

        $text = implode('. ', $parts);
        $text = rtrim($text, '. ') . '.';

        return $this->truncate($text, self::MAX_LENGTH);
    }

    // ──────────────────────────────────────────────

    /** "a, b y c (+N mas)" */
    private function listJoin(array $items, int $extra = 0): string
    {
        $count = count($items);
        if ($count === 0) return '';
        if ($count === 1) return $items[0] . ($extra ? " (+{$extra} mas)" : '');

        $last = array_pop($items);
        $base = implode(', ', $items) . ' y ' . $last;
        return $extra ? "{$base} (+{$extra} mas)" : $base;
    }

    private function truncate(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) return $s;
        return rtrim(mb_substr($s, 0, $max - 3)) . '...';
    }
}
