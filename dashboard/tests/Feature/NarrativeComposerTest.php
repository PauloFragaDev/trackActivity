<?php

namespace Tests\Feature;

use App\Services\Insights\NarrativeComposer;
use Tests\TestCase;

class NarrativeComposerTest extends TestCase
{
    private function metrics(array $over = []): array
    {
        return array_merge([
            'active_minutes'   => 75,
            'idle_minutes'     => 15,
            'context_switches' => 2,
            'by_project'       => [
                ['project_name' => 'GDR', 'minutes' => 60],
                ['project_name' => 'DAY', 'minutes' => 15],
            ],
        ], $over);
    }

    public function test_day_sentence(): void
    {
        $s = NarrativeComposer::compose('day', $this->metrics());

        $this->assertSame(
            'Hoy: sobre todo GDR (1h), algo de DAY (15m); 15m inactivo; 2 cambios de contexto.',
            $s,
        );
    }

    public function test_week_prefix(): void
    {
        $this->assertStringStartsWith('Esta semana:', NarrativeComposer::compose('week', $this->metrics()));
    }

    public function test_no_activity(): void
    {
        $this->assertSame(
            'Sin actividad registrada hoy.',
            NarrativeComposer::compose('day', $this->metrics(['active_minutes' => 0])),
        );
    }

    public function test_singular_context_switch(): void
    {
        $s = NarrativeComposer::compose('day', $this->metrics(['context_switches' => 1]));
        $this->assertStringContainsString('1 cambio de contexto.', $s);
    }
}
