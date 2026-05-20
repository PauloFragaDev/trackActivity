<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Project;
use App\Models\ProjectMapping;
use App\Models\ScoringRule;
use App\Services\Scoring\MappingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MappingResolver: convierte un ActivityEvent en contribuciones
 * (project_id, signal_kind, weight) según los mappings activos.
 */
class MappingResolverTest extends TestCase
{
    use RefreshDatabase;

    private function rule(string $kind, int $weight): void
    {
        ScoringRule::create(['signal_kind' => $kind, 'weight' => $weight, 'enabled' => true]);
    }

    private function mapping(int $projectId, string $type, string $pattern, bool $regex = false, int $bonus = 0): void
    {
        ProjectMapping::create([
            'project_id'   => $projectId,
            'type'         => $type,
            'pattern'      => $pattern,
            'is_regex'     => $regex,
            'weight_bonus' => $bonus,
            'enabled'      => true,
        ]);
    }

    private function project(string $code = 'TRACK'): Project
    {
        return Project::create(['code' => $code, 'name' => $code]);
    }

    private function event(array $attrs): ActivityEvent
    {
        return ActivityEvent::create(array_merge([
            'occurred_at' => '2026-05-19 10:00:00',
            'source'      => ActivityEvent::SOURCE_WINDOW,
        ], $attrs));
    }

    public function test_code_app_in_matching_repo_scores_vscode_in_repo(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->mapping($p->id, 'repository', 'trackActivity');

        $contribs = (new MappingResolver())->contributionsFor(
            $this->event(['app' => 'code', 'repo_name' => 'trackActivity'])
        );

        $this->assertCount(1, $contribs);
        $this->assertSame($p->id, $contribs[0]['project_id']);
        $this->assertSame(ScoringRule::KIND_VSCODE_IN_REPO, $contribs[0]['signal_kind']);
        $this->assertSame(5, $contribs[0]['weight']);
    }

    public function test_terminal_app_in_matching_repo_scores_terminal_in_repo(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_TERMINAL_IN_REPO, 4);
        $this->mapping($p->id, 'repository', 'trackActivity');

        $contribs = (new MappingResolver())->contributionsFor(
            $this->event(['app' => 'ghostty', 'repo_name' => 'trackActivity'])
        );

        $this->assertCount(1, $contribs);
        $this->assertSame(ScoringRule::KIND_TERMINAL_IN_REPO, $contribs[0]['signal_kind']);
        $this->assertSame(4, $contribs[0]['weight']);
    }

    public function test_git_event_with_modified_files_scores_git_modified(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_GIT_MODIFIED, 5);
        $this->mapping($p->id, 'repository', 'trackActivity');

        $contribs = (new MappingResolver())->contributionsFor($this->event([
            'source'         => ActivityEvent::SOURCE_GIT,
            'repo_name'      => 'trackActivity',
            'modified_files' => 3,
        ]));

        $this->assertCount(1, $contribs);
        $this->assertSame(ScoringRule::KIND_GIT_MODIFIED, $contribs[0]['signal_kind']);
    }

    public function test_git_event_without_modified_files_scores_commit_recent(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_GIT_COMMIT_RECENT, 4);
        $this->mapping($p->id, 'repository', 'trackActivity');

        $contribs = (new MappingResolver())->contributionsFor($this->event([
            'source'         => ActivityEvent::SOURCE_GIT,
            'repo_name'      => 'trackActivity',
            'modified_files' => 0,
        ]));

        $this->assertCount(1, $contribs);
        $this->assertSame(ScoringRule::KIND_GIT_COMMIT_RECENT, $contribs[0]['signal_kind']);
    }

    public function test_browser_event_matching_url_pattern_scores_url_match(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_URL_MATCH, 3);
        $this->mapping($p->id, 'url_pattern', 'github.com/acme');

        $contribs = (new MappingResolver())->contributionsFor($this->event([
            'source' => ActivityEvent::SOURCE_BROWSER,
            'url'    => 'https://github.com/acme/repo/pull/12',
        ]));

        $this->assertCount(1, $contribs);
        $this->assertSame(ScoringRule::KIND_URL_MATCH, $contribs[0]['signal_kind']);
        $this->assertSame(3, $contribs[0]['weight']);
    }

    public function test_weight_bonus_is_added_to_the_rule_weight(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->mapping($p->id, 'repository', 'trackActivity', bonus: 2);

        $contribs = (new MappingResolver())->contributionsFor(
            $this->event(['app' => 'code', 'repo_name' => 'trackActivity'])
        );

        $this->assertSame(7, $contribs[0]['weight']);
    }

    public function test_regex_mapping_matches_with_anchors(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        $this->mapping($p->id, 'repository', '^ywl-', regex: true);
        $resolver = new MappingResolver();

        $this->assertCount(1, $resolver->contributionsFor(
            $this->event(['app' => 'code', 'repo_name' => 'ywl-admin'])
        ));
        $this->assertCount(0, $resolver->contributionsFor(
            $this->event(['app' => 'code', 'repo_name' => 'admin-ywl'])
        ));
    }

    public function test_disabled_mapping_produces_no_contribution(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        ProjectMapping::create([
            'project_id'   => $p->id,
            'type'         => 'repository',
            'pattern'      => 'trackActivity',
            'is_regex'     => false,
            'weight_bonus' => 0,
            'enabled'      => false,
        ]);

        $contribs = (new MappingResolver())->contributionsFor(
            $this->event(['app' => 'code', 'repo_name' => 'trackActivity'])
        );

        $this->assertCount(0, $contribs);
    }

    public function test_idle_event_yields_no_contributions(): void
    {
        $this->project();
        $contribs = (new MappingResolver())->contributionsFor(
            $this->event(['source' => ActivityEvent::SOURCE_IDLE])
        );

        $this->assertSame([], $contribs);
    }

    public function test_multiple_mappings_same_project_keep_best_weight_only(): void
    {
        $p = $this->project();
        $this->rule(ScoringRule::KIND_VSCODE_IN_REPO, 5);
        // Dos repository mappings del MISMO proyecto que matchean el mismo repo.
        $this->mapping($p->id, 'repository', 'track');
        $this->mapping($p->id, 'repository', 'trackActivity', bonus: 3);

        $contribs = (new MappingResolver())->contributionsFor(
            $this->event(['app' => 'code', 'repo_name' => 'trackActivity'])
        );

        // dedupeBest: una sola contribución por proyecto, la de mayor peso.
        $this->assertCount(1, $contribs);
        $this->assertSame(8, $contribs[0]['weight']);
    }
}
