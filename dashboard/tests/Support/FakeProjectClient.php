<?php

namespace Tests\Support;

use App\Services\GitHub\ProjectClient;

/**
 * Doble del cliente de GitHub para los tests: devuelve items predefinidos
 * sin tocar la red.
 */
class FakeProjectClient implements ProjectClient
{
    /** @var list<array{id:string,updatedAt:string,title:string,body:string,status:?string,isDraft:bool}> */
    public array $items = [];

    /** @var array<string,string> */
    public array $statusOptions = [
        'Backlog'     => 'o-backlog',
        'Todo'        => 'o-todo',
        'In Progress' => 'o-doing',
        'Done'        => 'o-done',
    ];

    public function isConfigured(): bool
    {
        return true;
    }

    public function resolveProject(): array
    {
        return [
            'id'            => 'PROJECT',
            'statusFieldId' => 'STATUS_FIELD',
            'statusOptions' => $this->statusOptions,
        ];
    }

    public function listItems(string $projectId): array
    {
        return $this->items;
    }
}
