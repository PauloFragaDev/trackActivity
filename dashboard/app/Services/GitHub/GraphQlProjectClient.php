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

        $data = $this->graphql(<<<'GQL'
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
            $data = $this->graphql(<<<'GQL'
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
                                        ... on DraftIssue { id title body }
                                        ... on Issue { id title body }
                                        ... on PullRequest { id title body }
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
                    'contentId' => $content['id'] ?? null,
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

    public function createDraftItem(string $projectId, string $title, string $body): string
    {
        $data = $this->graphql(<<<'GQL'
            mutation ($projectId: ID!, $title: String!, $body: String!) {
                addProjectV2DraftIssue(input: {projectId: $projectId, title: $title, body: $body}) {
                    projectItem { id }
                }
            }
        GQL, ['projectId' => $projectId, 'title' => $title, 'body' => $body]);

        $id = $data['addProjectV2DraftIssue']['projectItem']['id'] ?? null;
        if ($id === null) {
            throw new RuntimeException('GitHub no devolvió el id del item creado.');
        }

        return $id;
    }

    public function updateDraftItem(string $draftId, string $title, string $body): void
    {
        $this->graphql(<<<'GQL'
            mutation ($draftId: ID!, $title: String!, $body: String!) {
                updateProjectV2DraftIssue(input: {draftIssueId: $draftId, title: $title, body: $body}) {
                    draftIssue { id }
                }
            }
        GQL, ['draftId' => $draftId, 'title' => $title, 'body' => $body]);
    }

    public function setItemStatus(string $projectId, string $itemId, string $fieldId, string $optionId): void
    {
        $this->graphql(<<<'GQL'
            mutation ($projectId: ID!, $itemId: ID!, $fieldId: ID!, $optionId: String!) {
                updateProjectV2ItemFieldValue(input: {
                    projectId: $projectId, itemId: $itemId, fieldId: $fieldId,
                    value: {singleSelectOptionId: $optionId}
                }) {
                    projectV2Item { id }
                }
            }
        GQL, compact('projectId', 'itemId', 'fieldId', 'optionId'));
    }

    public function deleteItem(string $projectId, string $itemId): void
    {
        $this->graphql(<<<'GQL'
            mutation ($projectId: ID!, $itemId: ID!) {
                deleteProjectV2Item(input: {projectId: $projectId, itemId: $itemId}) {
                    deletedItemId
                }
            }
        GQL, compact('projectId', 'itemId'));
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
     * Ejecuta una operación GraphQL (query o mutation) y devuelve el nodo `data`.
     *
     * @return array<string,mixed>
     */
    private function graphql(string $operation, array $variables = []): array
    {
        $response = Http::withToken((string) config('github.token'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'trackActivity'])
            ->post((string) config('github.api'), [
                'query'     => $operation,
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
