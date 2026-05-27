# Sincronización con code-kanban — endpoint `/api/sync/kanban`

> Cómo la extensión code-kanban (fork) habla con trackActivity para mantener
> los kanbans por repositorio sincronizados con la BBDD global. Ver el
> análisis de fondo en [`26-code-kanban-integration.md`](26-code-kanban-integration.md)
> y la auth de la API en [`27-api-rest.md`](27-api-rest.md).

## Idea

Cada workspace abierto en VS Code tiene su `.todo.kanban`. Cuando el usuario
guarda cambios (la extensión re-escribe el archivo), también envía su
contenido al endpoint `POST /api/sync/kanban`. El servidor hace **merge
bidireccional por timestamp** y responde con el estado resuelto; la
extensión reescribe el archivo con la respuesta.

- **Direccionalidad**: bidireccional (cualquier lado puede editar).
- **Resolución de conflictos**: last-writer-wins **por tarjeta** según el
  `updated_at` de cada card.
- **Identidad estable**: cada tarjeta tiene un `id` (UUID generado por la
  extensión). En trackActivity vive en `tasks.kanban_card_id`.

## Identificación del proyecto

El cliente envía `workspace_path` (path absoluto del workspace abierto).
El servidor lo resuelve a un proyecto del catálogo:

1. Busca un `ProjectMapping` con `type='folder'` cuyo `pattern` sea
   prefijo/sufijo del `workspace_path` (case-sensitive).
2. Si no, busca un mapping `type='repository'` cuyo `pattern` (case-insensitive)
   coincida con el `basename` del `workspace_path`.
3. Si tampoco → **422** `no_project_mapping`. El usuario debe crear el
   mapping desde `/projects` antes de volver a sincronizar.

## Endpoint

```http
POST /api/sync/kanban
Authorization: Bearer <token>
Content-Type: application/json
```

### Request

```json
{
  "workspace_path": "/home/paulo/dev/myapp",
  "client_updated_at": "2026-05-27T11:30:00Z",
  "lists": [
    {
      "title": "Blocked",
      "cards": []
    },
    {
      "title": "Backlog",
      "cards": [
        {
          "id": "uuid-1",
          "title": "Refactor scoring",
          "description": "Markdown opcional",
          "due_date": "2026-06-15",
          "labels": [
            { "title": "tech-debt", "color": "#f59e0b" }
          ],
          "updated_at": "2026-05-27T09:14:33Z"
        }
      ]
    },
    { "title": "To Do",    "cards": [] },
    { "title": "Doing",    "cards": [...] },
    { "title": "Stand By", "cards": [] },
    { "title": "Done",     "cards": [...] }
  ]
}
```

Notas:
- El título de la columna se compara case-insensitive y tolera la
  ausencia de espacios (`"standby"` ≈ `"Stand By"`).
- Si una columna no se reconoce, sus cards se ignoran y se añade un
  string al array `errors` de la respuesta.
- Las cards sin `id` se ignoran (con un error en la respuesta).
- `client_updated_at` es la marca global del archivo; cada card lleva
  su propio `updated_at` (opcional) que prevalece para el conflicto.

### Response (200)

```json
{
  "project": {
    "id": 5,
    "code": "MYAPP",
    "name": "Mi App",
    "color": "#10b981"
  },
  "applied_at": "2026-05-27T11:30:01+00:00",
  "lists": [
    {"title": "Blocked",  "cards": []},
    {"title": "Backlog",  "cards": [...]},
    {"title": "To Do",    "cards": [...]},
    {"title": "Doing",    "cards": [...]},
    {"title": "Stand By", "cards": [...]},
    {"title": "Done",     "cards": [...]}
  ],
  "errors": [],
  "stats": {
    "created":       3,
    "updated_local": 1,
    "kept_server":   2,
    "archived":      0
  }
}
```

Cada card en la respuesta tiene la forma:

```json
{
  "id": "uuid-1",
  "title": "Refactor scoring",
  "description": "...",
  "due_date": "2026-06-15",
  "labels": [{ "title": "tech-debt", "color": "#f59e0b" }],
  "updated_at": "2026-05-27T11:30:00+00:00"
}
```

La respuesta **siempre incluye 6 listas** en el orden fijo de columnas,
aunque alguna esté vacía. El cliente debe reescribir su `.todo.kanban`
con esta foto.

### Response (422)

Workspace sin mapping resoluble:

```json
{
  "error": "no_project_mapping",
  "message": "No hay ProjectMapping (type folder/repository) para «/x». Configura un mapping en /projects antes de sincronizar."
}
```

## Algoritmo de merge

Para cada card del cliente, por orden de columnas y posición:

1. **¿Existe** una task con `kanban_card_id` igual? Si **no** → crear
   (status, project, position, labels…). `stats.created++`.
2. Si **sí**:
   - Si `client.card.updated_at > server.task.updated_at` → actualizar
     server con los valores del cliente. `stats.updated_local++`.
   - Si no → no tocar; la versión server se devolverá en `lists`.
     `stats.kept_server++`.
3. Tras procesar todas las cards: las tasks del **mismo proyecto** con
   `kanban_card_id` que **no aparezcan** en el payload se **archivan**
   (soft delete). `stats.archived++`. Si fueron un error de la extensión,
   el usuario puede restaurarlas desde `/tasks/archived`.

## Labels

- Match por `title` case-insensitive. Si no existe en el catálogo
  global, se **crea** con el `color` recibido o `#9CA3AF` si falta.
- La asignación `tasks ↔ labels` se sustituye por completo según el
  payload (sync, no merge).

## Garantías

- **Transaccional**: todo el merge ocurre en una transacción SQL — si
  algo falla a mitad, no queda parcialmente aplicado.
- **Idempotente**: enviar el mismo payload dos veces produce el mismo
  estado.
- **Sin pérdida silenciosa**: cualquier tarjeta que el cliente "olvide"
  pasa a archivado, no se borra de verdad.

## Próximo paso (cliente)

El [fork de code-kanban](https://github.com/PauloFragaDev/code-kanban)
añadirá:

- Setting `code-kanban.sync.url` (URL base de trackActivity).
- Setting `code-kanban.sync.token` (Bearer token).
- Hook al guardar `.todo.kanban`: POST a `/api/sync/kanban`, reescribir
  el archivo con la respuesta.
- Comando `code-kanban: sync now` para forzar pull.
