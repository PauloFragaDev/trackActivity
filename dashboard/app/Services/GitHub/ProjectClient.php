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
     * @return list<array{id:string,updatedAt:string,title:string,body:string,status:?string,isDraft:bool}>
     */
    public function listItems(string $projectId): array;
}
