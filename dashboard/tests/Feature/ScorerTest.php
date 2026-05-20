<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Project;
use App\Models\ProjectMapping;
use App\Models\ScoringRule;
use App\Services\Scoring\MappingResolver;
use App\Services\Scoring\Scorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Scorer: suma contribuciones de los eventos de un bloque y decide el
 * proyecto dominante, la confianza y el estado idle.
 */
class ScorerTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $code): Project
    {
        return Project::create(['code' => $code, 'name' => $code]);
    }

    private function rule(string $kind, int $weight): void
    {
        ScoringRule::create(['signal_kind' => $kind, 'weight' => $weight, 'enabled' => true]);
    }

    private function repoMapping(int $projectId, string $pattern): void
    {
        ProjectMapping::create([
            'project_id'   => $projectId,
            'type'         => 'repository',
            'pattern'      => $pattern,
            'is_regex'     => false,
            'weight_bonus' => 0,
            'enabled'      => true,
        ]);
    }

    private function codeEvent(string $repo): ActivityEvent
    {
        return ActivityEvent::create([
            'occurred_at' => '2026-05-19 10:00:00',
            'source'      => ActivityEvent::SOURCE_WINDOW,
            'app'         => 'code',
            'repo_name'   => $repo,
        ]);
    }

    private function idleEvent(string $state = 'enter'): ActivityEvent
    {
        return ActivityEvent::create([
            'occurred_at' => '2026-05-19 10:00:00',
            'source'      => ActivityEvent::SOURCE_IDLE,
            'metadata'    => ['state' => $state],
        ]);
    }

    private function scorer(): Scorer
    {
        return new Scorer(new MappingResolver());
    }

    public function test_empty_events_yield_empty_result(): void
    {
        $result = $this->scorer()->score(new Collection());

        $this->assertNull($result->dominantProjectId);
        $this->assertFalse($result->isIdle);
        $this->assertSame(0.0, $result->confidence);
    }

    public function test_block_full_of_idle_events_is_marked_idle(): void
    {
        $result = $this->scorer()->score(collect([$this->idleEvent('enter')]));

        $this->assertTrue($result->isIdle);
        $this->assertNull($result->dominantProjectId);
    }

    public function test_single_project_wins_with_full_confidence(): void
    {
        $p = $this->project('TRACK');
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->repoMapping($p->id, 'trackActivity');

        $result = $this->scorer()->score(collect([$this->codeEvent('trackActivity')]));

        $this->assertSame($p->id, $result->dominantProjectId);
        // Sin rival, confianza máxima.
        $this->assertSame(1.0, $result->confidence);
    }

    public function test_higher_scoring_project_wins_with_partial_confidence(): void
    {
        $a = $this->project('ALPHA');
        $b = $this->project('BETA');
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->repoMapping($a->id, 'alpha');
        $this->repoMapping($b->id, 'beta');

        // ALPHA: 2 eventos (5+5=10). BETA: 1 evento (5).
        $result = $this->scorer()->score(collect([
            $this->codeEvent('alpha'),
            $this->codeEvent('alpha'),
            $this->codeEvent('beta'),
        ]));

        $this->assertSame($a->id, $result->dominantProjectId);
        // confianza = (top - second) / top = (10 - 5) / 10
        $this->assertEqualsWithDelta(0.5, $result->confidence, 0.0001);
    }

    public function test_tie_is_broken_by_lowest_project_id(): void
    {
        $a = $this->project('ALPHA');   // id menor
        $b = $this->project('BETA');
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        // Ambos mappings matchean el mismo repo: empate exacto.
        $this->repoMapping($a->id, 'shared');
        $this->repoMapping($b->id, 'shared');

        $result = $this->scorer()->score(collect([$this->codeEvent('shared')]));

        $this->assertSame($a->id, $result->dominantProjectId);
        // Empate perfecto → confianza 0.
        $this->assertSame(0.0, $result->confidence);
    }

    public function test_idle_events_do_not_contribute_but_do_not_block_scoring(): void
    {
        $p = $this->project('TRACK');
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->repoMapping($p->id, 'trackActivity');

        // Mezcla: un idle + un evento real → el bloque NO es idle.
        $result = $this->scorer()->score(collect([
            $this->idleEvent('enter'),
            $this->codeEvent('trackActivity'),
        ]));

        $this->assertFalse($result->isIdle);
        $this->assertSame($p->id, $result->dominantProjectId);
    }

    public function test_evidence_traces_the_contributing_events(): void
    {
        $p = $this->project('TRACK');
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->repoMapping($p->id, 'trackActivity');
        $e1 = $this->codeEvent('trackActivity');
        $e2 = $this->codeEvent('trackActivity');

        $result = $this->scorer()->score(collect([$e1, $e2]));

        $eventIds = array_column($result->evidence, 'event_id');
        sort($eventIds);
        $this->assertSame([$e1->id, $e2->id], $eventIds);
    }
}
