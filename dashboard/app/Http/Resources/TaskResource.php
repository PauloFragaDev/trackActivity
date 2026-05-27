<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma JSON de una Task para la API REST. Carga relaciones cuando ya
 * están eager-loaded (no fuerza queries N+1) y devuelve timestamps en
 * ISO 8601 UTC.
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status?->value,
            'priority'    => $this->priority?->value,
            'due_date'    => $this->due_date?->format('Y-m-d'),
            'position'    => $this->position,
            'project_id'  => $this->project_id,
            'project'     => $this->whenLoaded('project', fn () => $this->project ? [
                'id'    => $this->project->id,
                'code'  => $this->project->code,
                'name'  => $this->project->name,
                'color' => $this->project->color,
            ] : null),
            'labels'      => TaskLabelResource::collection($this->whenLoaded('labels')),
            'checkboxes'  => $this->whenLoaded('checkboxes', fn () =>
                $this->checkboxes->map(fn ($c) => [
                    'id'       => $c->id,
                    'title'    => $c->title,
                    'checked'  => (bool) $c->checked,
                    'position' => $c->position,
                ])->values()
            ),
            'comments'    => $this->whenLoaded('comments', fn () =>
                $this->comments->map(fn ($c) => [
                    'id'         => $c->id,
                    'body'       => $c->body,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()
            ),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
            'archived_at'  => $this->deleted_at?->toIso8601String(),
        ];
    }
}
