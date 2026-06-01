# CRM (Base44) → trackActivity · Contrato de API REST

Documento para definir la **API REST** que el CRM de empresa (hecho en Base44)
debe exponer para que **trackActivity** consuma sus datos. trackActivity es un
**consumidor de solo lectura**: lee clientes, proyectos y tareas del CRM y les
superpone el **tiempo trackeado** (su valor añadido). No escribe en el CRM.

Decisión de modelo (junio 2026): **el CRM manda**. Sus proyectos/tareas son la
verdad; trackActivity **empareja sus proyectos locales con los del CRM por
`code`** para que el tracker siga puntuando tiempo sobre el proyecto correcto.

## Autenticación

- **API key estática** por cabecera: `Authorization: Bearer <token>` (o `X-API-Key`).
- Single-user. No hace falta OAuth ni login de usuario.

## Formato

- JSON en todas las respuestas.
- Deseable: **paginación** (`?page=` / `?limit=`).
- Muy deseable: filtro incremental **`?updated_since=<ISO8601>`** para no traer
  todo en cada sincronización.

## Endpoints (GET, solo lectura)

### 1. `GET /api/clients`
```json
[
  {
    "id": "c_123",
    "name": "Acme S.L.",
    "company": "Acme",
    "email": "hola@acme.com",
    "phone": "+34 600 000 000",
    "website": "https://acme.com",
    "notes": "…",
    "color": "#10b981",
    "updated_at": "2026-06-01T10:00:00Z"
  }
]
```
(`company`, `email`, `phone`, `website`, `notes`, `color` son opcionales.)

### 2. `GET /api/projects`
```json
[
  {
    "id": "p_456",
    "code": "JASPER",
    "name": "Jasper API",
    "client_id": "c_123",
    "color": "#3b82f6",
    "archived": false,
    "updated_at": "2026-06-01T10:00:00Z"
  }
]
```
- **`code` es el campo CLAVE**: debe ser **estable y único**. Es lo que empareja
  cada proyecto del CRM con el proyecto local de trackActivity (donde ya se
  acumula el tiempo). Sin un `code` fiable, el mapeo no funciona.
- `client_id` referencia al cliente dueño del proyecto.

### 3. `GET /api/tasks`
```json
[
  {
    "id": "t_789",
    "project_id": "p_456",
    "title": "Implementar login",
    "description": "…",
    "status": "in_progress",
    "priority": "high",
    "due_date": "2026-06-10",
    "position": 0,
    "updated_at": "2026-06-01T10:00:00Z"
  }
]
```
- Necesitamos la **lista cerrada de valores posibles** de `status` y `priority`
  (para mapearlos a los estados del Kanban de trackActivity).

## Reglas que pedimos garantizar

- **IDs estables** por entidad (no cambian entre lecturas) → upsert idempotente.
- **`code` de proyecto** único y estable → clave de emparejamiento.
- **`updated_at`** en cada entidad → sincronización incremental.
- Relaciones por id: `projects.client_id`, `tasks.project_id`.

## Fuera de alcance (para mantenerlo simple)

- Escritura desde trackActivity (no hace falta): el tiempo trackeado se queda en
  trackActivity.
- Webhooks/push: opcional a futuro. De momento trackActivity hará **polling**
  (un comando `crm:sync` programado cada X minutos).

## Lado trackActivity (cuando la API exista)

- Comando `crm:sync` que:
  1. Hace **upsert de clientes** (por `id` del CRM).
  2. **Empareja proyectos por `code`** (crea/vincula el proyecto local y le asigna
     el cliente); el tracker sigue puntuando tiempo sobre ese proyecto.
  3. Refleja las **tareas** (mapeando `status`/`priority` a los enums del Kanban).
- La ficha de cliente muestra el **tiempo agregado** sobre sus proyectos (ya
  diseñado en el módulo Clientes).

> Estado: pendiente de que el CRM (Base44) genere esta API. El consumo en
> trackActivity se diseña/implementa cuando los endpoints existan.
