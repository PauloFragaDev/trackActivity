<?php

namespace Tests\Support;

use App\Services\GitHub\ProjectClient;

/**
 * Doble del cliente de GitHub para los tests: devuelve items predefinidos
 * y registra las operaciones de escritura, sin tocar la red.
 */
class FakeProjectClient implements ProjectClient
{
    /** @var list<array{id:string,contentId:?string,updatedAt:string,title:string,body:string,status:?string,isDraft:bool}> */
    public array $items = [];

    /** @var array<string,string> */
    public array $statusOptions = [
        'Backlog'     => 'o-backlog',
        'Todo'        => 'o-todo',
        'In Progress' => 'o-doing',
        'Done'        => 'o-done',
    ];

    /** Operaciones de escritura registradas (para asserts en los tests). */
    public array $created   = [];
    public array $updated   = [];
    public array $statusSet = [];
    public array $deleted   = [];

    private int $seq = 0;

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

    public function createDraftItem(string $projectId, string $title, string $body): string
    {
        $id = 'NEW-ITEM-' . (++$this->seq);
        $this->created[] = ['id' => $id, 'title' => $title, 'body' => $body];

        return $id;
    }

    public function updateDraftItem(string $draftId, string $title, string $body): void
    {
        $this->updated[] = ['draftId' => $draftId, 'title' => $title, 'body' => $body];
    }

    public function setItemStatus(string $projectId, string $itemId, string $fieldId, string $optionId): void
    {
        $this->statusSet[] = ['itemId' => $itemId, 'optionId' => $optionId];
    }

    public function deleteItem(string $projectId, string $itemId): void
    {
        $this->deleted[] = $itemId;
    }
}
