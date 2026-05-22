<?php

namespace App\Services\GitHub;

/**
 * Contrato del cliente de un GitHub Project (v2). Permite sustituirlo por
 * un doble en los tests, ya que la API real necesita un token.
 */
interface ProjectClient
{
    /** ¿Hay token y project configurados? */
    public function isConfigured(): bool;

    /**
     * Resuelve el Project configurado.
     *
     * @return array{id:string,statusFieldId:?string,statusOptions:array<string,string>}
     *         statusOptions: nombre de la opción => id de la opción.
     */
    public function resolveProject(): array;

    /**
     * Items del Project.
     *
     * @return list<array{id:string,contentId:?string,updatedAt:string,title:string,body:string,status:?string,isDraft:bool}>
     */
    public function listItems(string $projectId): array;

    /** Crea un draft issue en el Project; devuelve el id del item. */
    public function createDraftItem(string $projectId, string $title, string $body): string;

    /** Actualiza el título y el cuerpo de un draft issue (por su id de contenido). */
    public function updateDraftItem(string $draftId, string $title, string $body): void;

    /** Fija el valor del campo Status (single-select) de un item. */
    public function setItemStatus(string $projectId, string $itemId, string $fieldId, string $optionId): void;

    /** Elimina un item del Project. */
    public function deleteItem(string $projectId, string $itemId): void;
}
