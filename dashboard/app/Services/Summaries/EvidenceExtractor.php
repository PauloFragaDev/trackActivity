<?php

namespace App\Services\Summaries;

use App\Models\ActivityEvent;
use Illuminate\Support\Collection;

/**
 * Extrae datos estructurados de una coleccion de ActivityEvent para que el
 * SummaryGenerator pueda producir un texto. Detecta branches, repos,
 * mensajes de commit, issues de GitHub y tickets de Jira.
 */
class EvidenceExtractor
{
    /** @param Collection<int,ActivityEvent> $events */
    public function extract(Collection $events): array
    {
        $branches        = collect();
        $repos           = collect();
        $commitMessages  = collect();
        $githubIssues    = collect();
        $githubPrs       = collect();
        $jiraTickets     = collect();
        $emailSubjects   = collect();

        foreach ($events as $event) {
            if ($event->branch) {
                $branches->push($event->branch);
            }
            if ($event->repo_name) {
                $repos->push($event->repo_name);
            }

            $commitMessage = data_get($event->metadata, 'latest_commit.message');
            if ($commitMessage) {
                $commitMessages->push(trim((string) $commitMessage));
            }

            $title = (string) $event->title;
            $url   = (string) $event->url;

            // GitHub PR #NNN — solo cuando aparece "Pull request" en el title/url
            if ($title !== '' || $url !== '') {
                $haystack = $title . ' ' . $url;
                if (stripos($haystack, 'pull request') !== false || stripos($haystack, '/pull/') !== false) {
                    foreach ($this->numbersAfter('#', $haystack) as $n) {
                        $githubPrs->push("#{$n}");
                    }
                }
                if (stripos($haystack, 'issue') !== false || stripos($haystack, '/issues/') !== false) {
                    foreach ($this->numbersAfter('#', $haystack) as $n) {
                        $githubIssues->push("#{$n}");
                    }
                }
            }

            // Jira: token tipo PROJ-123 dentro de title/subject/url
            $bag = $title . ' ' . (string) $event->subject . ' ' . $url;
            if ($bag !== '   ' && preg_match_all('/\b([A-Z][A-Z0-9]+-\d+)\b/', $bag, $m)) {
                foreach ($m[1] as $t) {
                    $jiraTickets->push($t);
                }
            }

            if ($event->subject) {
                $emailSubjects->push((string) $event->subject);
            }
        }

        return [
            'branches'        => $branches->unique()->values()->all(),
            'repos'           => $repos->unique()->values()->all(),
            'commit_messages' => $commitMessages->unique()->values()->all(),
            'github_prs'      => $githubPrs->unique()->values()->all(),
            'github_issues'   => $githubIssues->unique()->values()->all(),
            'jira_tickets'    => $jiraTickets->unique()->values()->all(),
            'email_subjects'  => $emailSubjects->unique()->values()->all(),
        ];
    }

    /** Devuelve numeros que aparecen tras `$prefix` (ej. "#" -> "123"). */
    private function numbersAfter(string $prefix, string $haystack): array
    {
        if (! preg_match_all('/' . preg_quote($prefix, '/') . '(\d{1,6})\b/', $haystack, $m)) {
            return [];
        }
        return array_unique($m[1]);
    }
}
