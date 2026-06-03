<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Services\BlockRuleSuggester;
use Tests\TestCase;

class BlockRuleSuggesterTest extends TestCase
{
    public function test_suggests_repo_folder_url_deduped_and_ranked(): void
    {
        $events = collect([
            new ActivityEvent(['repo_name' => 'day', 'metadata' => ['cwd_hint' => '/var/www/html/day']]),
            new ActivityEvent(['repo_name' => 'day']),
            new ActivityEvent(['url' => 'https://github.com/x/y']),
        ]);

        $out = BlockRuleSuggester::suggest($events);

        // repo 'day' aparece 2 veces → es el más frecuente, va primero.
        $this->assertSame('repository', $out[0]['type']);
        $this->assertSame('day', $out[0]['pattern']);

        $byType = collect($out)->keyBy('type');
        $this->assertSame('day', $byType['folder']['pattern']);          // basename de cwd_hint
        $this->assertSame('github.com', $byType['url_pattern']['pattern']); // host de la url
    }

    public function test_ignores_events_without_signal(): void
    {
        $events = collect([new ActivityEvent(['title' => 'algo', 'app' => 'code'])]);

        $this->assertSame([], BlockRuleSuggester::suggest($events));
    }
}
