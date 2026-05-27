# API REST de trackActivity

> Pieza preparatoria para que clientes externos (extensión code-kanban
> fork, CLI propio, etc.) consuman y muten el modelo. Single-user,
> autenticación por **Bearer token estático**.

## Setup

1. Genera un token largo:
   ```bash
   php artisan tinker --execute='echo bin2hex(random_bytes(32));'
   ```
2. Añádelo a `.env`:
   ```env
   API_TOKEN=<el-token-generado>
   ```
3. Reinicia el servidor (`php artisan config:clear` si la app está sirviendo).

## Auth

Todas las rutas bajo `/api/*` (excepto `/up` que es health-check
sin token) exigen el header:

```
Authorization: Bearer <token>
```

- Sin `API_TOKEN` configurado → **503** (`API_TOKEN no configurado…`).
- Sin header o header mal → **401** (`Unauthenticated.`).
- OK → la ruta sigue su curso.

La comparación se hace con `hash_equals` para resistir timing attacks.

## Endpoints

### Ping (utilidad para clientes)

```http
GET /api/ping
→ 200 { "ok": true, "service": "trackActivity" }
```

### Tasks

| Método | Ruta | Descripción |
|---|---|---|
| `GET`  | `/api/tasks` | Lista tareas. Filtros: `project`, `status`, `since`, `include_archived`. |
| `POST` | `/api/tasks` | Crea tarea. **201** + recurso. |
| `GET`  | `/api/tasks/{id}` | Detalle. |
| `PATCH`| `/api/tasks/{id}` | Actualiza (campos opcionales). Marca `github_dirty`. |
| `DELETE`| `/api/tasks/{id}` | Archiva (soft delete). **204** sin cuerpo. |
| `POST` | `/api/tasks/{id}/restore` | Restaura una archivada. |
| `DELETE`| `/api/tasks/{id}/force` | Borra definitivamente (solo si archivada). **404** si no está archivada. |

#### Campos aceptados en `store` / `update`

| Campo | Tipo | Notas |
|---|---|---|
| `title` | string ≤200 | requerido en store |
| `description` | string \| null | Markdown opcional |
| `status` | enum | requerido en store; valores: `blocked`, `backlog`, `todo`, `doing`, `standby`, `done` |
| `priority` | enum \| null | `low`, `normal`, `high` |
| `project_id` | int \| null | debe existir |
| `due_date` | date (`YYYY-MM-DD`) \| null | |
| `label_ids[]` | int[] \| null | IDs de labels, deben existir |

#### Forma del recurso Task

```json
{
  "data": {
    "id": 12,
    "title": "Refactor SyncService",
    "description": "Markdown opcional",
    "status": "doing",
    "priority": "high",
    "due_date": "2026-06-01",
    "position": 0,
    "project_id": 3,
    "project": { "id": 3, "code": "PRJ", "name": "Proyecto", "color": "#10b981" },
    "labels": [{ "id": 1, "title": "urgent", "color": "#e11d48" }],
    "checkboxes": [
      { "id": 4, "title": "Sub 1", "checked": true,  "position": 0 },
      { "id": 5, "title": "Sub 2", "checked": false, "position": 1 }
    ],
    "comments": [
      { "id": 9, "body": "ojo con el lock", "created_at": "2026-05-27T10:00:00+00:00" }
    ],
    "completed_at": null,
    "created_at": "2026-05-20T10:00:00+00:00",
    "updated_at": "2026-05-27T11:00:00+00:00",
    "archived_at": null
  }
}
```

`checkboxes` y `comments` aparecen solo si se eager-loadean (en `show` y
`store/update`). En el listado por defecto incluimos todas las relaciones
porque el cliente típico (code-kanban) necesita la foto completa.

### Catálogos (solo lectura)

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/projects` | Catálogo de proyectos. |
| `GET` | `/api/task-labels` | Catálogo global de labels. |

Forma de un proyecto:
```json
{ "id": 1, "code": "PRJ", "name": "Proyecto", "color": "#10b981" }
```

Forma de un label:
```json
{ "id": 1, "title": "urgent", "color": "#e11d48" }
```

## Errores

| Código | Cuándo |
|---|---|
| `401` | Token ausente o incorrecto. |
| `404` | Recurso no encontrado (incluye intentos de `force-destroy` sobre una task no archivada). |
| `422` | Validación: el cuerpo no respeta el esquema. Respuesta estándar de Laravel (`{errors: {...}}`). |
| `503` | `API_TOKEN` no configurado en el servidor. |

## Ejemplos curl

```bash
TOKEN=...

# Listado de tareas activas del proyecto 3 en doing
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8000/api/tasks?project=3&status=doing" | jq

# Crear una tarea
curl -s -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -X POST http://127.0.0.1:8000/api/tasks \
  -d '{"title":"Pruebas API","status":"todo","priority":"normal"}' | jq

# Archivar
curl -s -H "Authorization: Bearer $TOKEN" \
  -X DELETE http://127.0.0.1:8000/api/tasks/42

# Restaurar
curl -s -H "Authorization: Bearer $TOKEN" \
  -X POST http://127.0.0.1:8000/api/tasks/42/restore | jq
```

## Próximos pasos

Esta API sirve como cimiento. La sincronización efectiva con la extensión
code-kanban (canal en vivo, mapeo de tarjetas ↔ tasks, resolución por
timestamp) cae en un PR posterior, junto con el fork. Ver
[`26-code-kanban-integration.md`](26-code-kanban-integration.md).
