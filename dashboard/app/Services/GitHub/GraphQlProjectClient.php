<?php

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente del GitHub Project vía la API GraphQL. Asume un Project de una
 * cuenta de usuario (no de organización). Ver docs/17-github-projects-sync.md.
 */
class GraphQlProjectClient implements ProjectClient
{
    public function isConfigured(): bool
    {
        return filled(config('github.token')) && filled(config('github.project'));
    }

    public function resolveProject(): array
    {
        [$owner, $number] = $this->ownerAndNumber();

        $data = $this->query(<<<'GQL'
            query ($owner: String!, $number: Int!) {
                user(login: $owner) {
                    projectV2(number: $number) {
                        id
                        field(name: "Status") {
                            ... on ProjectV2SingleSelectField {
                                id
                                options { id name }
                            }
                        }
                    }
                }
            }
        GQL, ['owner' => $owner, 'number' => $number]);

        $project = $data['user']['projectV2'] ?? null;
        if ($project === null) {
            throw new RuntimeException("No se encontró el Project «{$owner}/{$number}».");
        }

        $options = [];
        foreach ($project['field']['options'] ?? [] as $opt) {
            $options[$opt['name']] = $opt['id'];
        }

        return [
            'id'            => $project['id'],
            'statusFieldId' => $project['field']['id'] ?? null,
            'statusOptions' => $options,
        ];
    }

    public function listItems(string $projectId): array
    {
        $items  = [];
        $cursor = null;

        do {
            $data = $this->query(<<<'GQL'
                query ($projectId: ID!, $cursor: String) {
                    node(id: $projectId) {
                        ... on ProjectV2 {
                            items(first: 100, after: $cursor) {
                                pageInfo { hasNextPage endCursor }
                                nodes {
                                    id
                                    updatedAt
                                    content {
                                        __typename
                                        ... on DraftIssue { title body }
                                        ... on Issue { title body }
                                        ... on PullRequest { title body }
                                    }
                                    fieldValueByName(name: "Status") {
                                        ... on ProjectV2ItemFieldSingleSelectValue { name }
                                    }
                                }
                            }
                        }
                    }
                }
            GQL, ['projectId' => $projectId, 'cursor' => $cursor]);

            $page = $data['node']['items'] ?? ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]];

            foreach ($page['nodes'] as $node) {
                $content = $node['content'] ?? [];
                $items[] = [
                    'id'        => $node['id'],
                    'updatedAt' => $node['updatedAt'] ?? '',
                    'title'     => $content['title'] ?? '(sin título)',
                    'body'      => $content['body'] ?? '',
                    'status'    => $node['fieldValueByName']['name'] ?? null,
                    'isDraft'   => ($content['__typename'] ?? '') === 'DraftIssue',
                ];
            }

            $cursor = ($page['pageInfo']['hasNextPage'] ?? false) ? $page['pageInfo']['endCursor'] : null;
        } while ($cursor !== null);

        return $items;
    }

    /** @return array{0:string,1:int} owner y número del Project. */
    private function ownerAndNumber(): array
    {
        $project = (string) config('github.project');
        if (! str_contains($project, '/')) {
            throw new RuntimeException('GITHUB_PROJECT debe tener el formato «owner/numero».');
        }
        [$owner, $number] = explode('/', $project, 2);

        return [$owner, (int) $number];
    }

    /**
     * Ejecuta una operación GraphQL y devuelve el nodo `data`.
     *
     * @return array<string,mixed>
     */
    private function query(string $query, array $variables = []): array
    {
        $response = Http::withToken((string) config('github.token'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'trackActivity'])
            ->post((string) config('github.api'), [
                'query'     => $query,
                'variables' => $variables,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('GitHub respondió con HTTP ' . $response->status() . '.');
        }

        $json = $response->json();
        if (! empty($json['errors'])) {
            throw new RuntimeException('GraphQL: ' . ($json['errors'][0]['message'] ?? 'error desconocido'));
        }

        return $json['data'] ?? [];
    }
}
