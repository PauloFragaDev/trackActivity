# Identidad de miembro + Transferencia de tareas al equipo — Spec de diseño

**Fecha:** 2026-06-25
**Estado:** aprobado por el usuario

---

## Contexto

El Kanban de equipo (Supabase) no sabe quién es cada usuario. Cualquiera puede
crear o mover tarjetas sin dejar rastro de autoría. Además, no hay forma de
pasar trabajo del Kanban personal al equipo cuando alguien se va de vacaciones.

Este spec cubre dos funcionalidades relacionadas:

1. **Identidad de miembro** — saber qué miembro del equipo está usando la app
   en cada dispositivo, sin login.
2. **Transferencia personal → equipo** — copiar una tarea personal (con
   subtareas, comentarios y etiquetas) al board del equipo, archivando el
   original.

---

## Feature 1 — Identidad de miembro del equipo

### Flujo general

Cuando un usuario abre el board del equipo sin identidad establecida, aparece
un modal overlay "¿Quién eres tú?" con la lista de miembros del equipo (nombre
+ círculo de color con iniciales). Al seleccionar uno:

1. JS hace `POST /team/identity` con `{ member_id: N }`.
2. El servidor guarda `team_member_id` en la sesión PHP.
3. JS guarda el mismo ID en `localStorage` (clave `team_member_id`).
4. El modal se cierra y el board está listo.

Si `localStorage` ya tiene el ID al cargar la página, el modal no aparece pero
el servidor confirma la sesión en cada carga vía el controlador de identidad.

Si no hay miembros configurados en Settings → Integraciones, el modal no
aparece y el board funciona en modo anónimo (igual que antes).

### Header del board en modo equipo

Se añade una pastilla junto al toggle Personal/Equipo:

```
[Personal] [Equipo]   · Paulo Fraga  Cambiar
```

- Iniciales con el color del miembro + nombre
- Botón "Cambiar" → abre de nuevo el modal de selección (borra sesión y
  localStorage antes de mostrar la lista)

### Toggle para deshabilitar la integración de equipo

En Settings → Integraciones se añade un switch **"Integración de equipo activa"**
guardado en `Setting::get('team.enabled', true)` (SQLite local,
por instalación). Cuando está **OFF**:

- El toggle Personal/Equipo desaparece del board.
- Las rutas `/team/*` redirigen a `/tasks`.
- El modal de identidad no aparece.
- Útil para quien clone el repo y no quiera configurar Supabase.

El switch aparece siempre en Settings, independientemente de si
`SUPABASE_DB_HOST` está configurado.

### Autoría en tareas y comentarios

**Tareas creadas en modo equipo:**
- Nueva columna `created_by_id` (FK → team_members, nullable) en la tabla
  `tasks` de Supabase.
- `TeamTaskController::store()` lee `session('team_member_id')` y lo guarda
  en `created_by_id`.
- La card muestra un segundo avatar pequeño "creado por" si es distinto del
  asignado.

**Comentarios en modo equipo:**
- Los campos `author_name` y `author_token` ya existen en `task_comments`.
- `TeamTaskCommentController::store()` (nuevo) lee la sesión y rellena
  `author_name` = nombre del miembro, `author_token` = ID del miembro (string).
- El JS usa `window.TEAM_MEMBER_ID` (inyectado desde sesión en la vista) para
  comparar con `author_token` y alinear los mensajes propios a la derecha.

### Settings → Integraciones — carga desde Supabase

La lista de miembros en la página de Settings ya usa el modelo `TeamMember`
(conexión supabase). El renderizado falla silenciosamente si Supabase no está
configurado. La página debe manejar este caso mostrando el estado "Sin
configurar" y ocultando la sección de miembros, lo que ya está implementado
con el guard `env('SUPABASE_DB_HOST')`.

---

## Feature 2 — Transferencia de tareas personal → equipo

### Dónde se activa

En el modal de edición de una tarea **personal**, aparece el botón
"Transferir al equipo" en el footer del modal. Condiciones para que aparezca:

- El modo actual es `personal`
- `Setting::get('team.enabled')` es `true`
- `env('SUPABASE_DB_HOST')` está configurado
- Hay una identidad de equipo activa en la sesión (`session('team_member_id')`)

### Flujo de confirmación (SweetAlert)

Al pulsar el botón, se abre un SweetAlert que muestra:

```
Transferir "Título de la tarea" al equipo

Proyecto: CÓDIGO — [✓ existe en el equipo | ⚠ se creará en el equipo]

Al confirmar:
- La tarea (con subtareas, comentarios y etiquetas) se copiará al equipo
- La tarea personal quedará archivada

[Cancelar]  [Transferir]
```

El estado del proyecto (existe/se creará) se obtiene con una llamada AJAX
previa a `GET /tasks/{task}/transfer-preview` que devuelve JSON.

### Endpoint de transferencia

`POST /tasks/{task}/transfer-to-team`

**Pasos en orden:**

1. **Proyecto:** si la tarea tiene `project_id`:
   - Cargar el `Project` personal.
   - Buscar `TeamProject::where('code', $project->code)->first()`.
   - Si no existe → crear `TeamProject` con `code`, `name`, `color` del
     proyecto personal.
   - Si existe → usar el existente.
   - Si la tarea no tiene proyecto → `team_project_id = null`.

2. **Tarea:** crear `TeamTask` con:
   - `project_id` → ID del TeamProject (o null)
   - `title`, `description`, `status`, `priority`, `due_date` → copiados
   - `created_by_id` → `session('team_member_id')`
   - `position` → al final de su columna (MAX + 1)

3. **Subtareas:** copiar cada `TaskCheckbox` de la tarea personal como
   `TeamTaskCheckbox`, preservando `title`, `checked`, `position`.

4. **Comentarios:** copiar cada `TaskComment` como `TeamTaskComment`,
   preservando `body`, `author_name`, `created_at`. El `author_token` se
   establece al ID del miembro que transfiere (para que sus comentarios
   propios aparezcan alineados a la derecha).

5. **Etiquetas:** para cada `TaskLabel` de la tarea personal:
   - Buscar `TeamTaskLabel::where('title', $label->title)->first()`.
   - Si no existe → crear con `title` y `color` de la etiqueta personal.
   - Asociar a la `TeamTask`.

6. **Archivar original:** `$task->delete()` (soft delete) sobre la tarea
   personal.

7. **Respuesta:** JSON `{ ok: true, team_task_id: N }`. El JS muestra un
   toast "Tarea transferida al equipo" y recarga el board personal.

**Lo que NO se transfiere:**
- `kanban_card_id` / `kanban_synced_at` (sync con code-kanban, irrelevante
  en equipo)
- `manual_entries` (tiempo personal, queda en local)

### Restricciones

- Solo transferible si la integración de equipo está habilitada y hay
  identidad de equipo activa.
- Si la transferencia falla (error de red con Supabase), la tarea personal
  NO se archiva — primero crear en equipo, luego archivar.
- No hay transferencia en sentido inverso (equipo → personal) en este spec.

---

## Componentes a construir

| Componente | Tipo | Descripción |
|---|---|---|
| `team_identity` migration | Migración Supabase | Añadir `created_by_id` a `tasks` |
| `POST /team/identity` | Ruta + acción en controlador nuevo | Guarda miembro en sesión |
| `TeamIdentityController` | Controlador | Gestiona sesión de identidad |
| Modal "¿Quién eres tú?" | Blade + JS | Overlay en board del equipo |
| Pastilla de identidad en header | Blade | Muestra miembro activo + "Cambiar" |
| Setting `team.enabled` | Blade + SettingsController | Toggle en Integraciones |
| Guard en rutas `/team/*` | `routes/web.php` | Redirige si team.enabled = false |
| `created_by_id` en TeamTask | Modelo + fillable | Nueva relación `createdBy()` |
| `TeamTaskCommentController` | Controlador | Crear/borrar comentarios en equipo |
| `window.TEAM_MEMBER_ID` | Blade | Inyectar ID de sesión al JS |
| `GET /tasks/{task}/transfer-preview` | Ruta + TeamTransferController | Devuelve estado del proyecto |
| `POST /tasks/{task}/transfer-to-team` | Ruta + TeamTransferController | Ejecuta la transferencia |
| Botón "Transferir" en modal edición | Blade | Solo en modo personal |
| SweetAlert de confirmación | JS (kanban.js) | Muestra preview + confirma |

---

## Orden de implementación

```
1. Migración: created_by_id en tasks (Supabase)
2. TeamIdentityController + rutas + modal "¿Quién eres tú?" + pastilla header
3. Setting team.enabled + guard en rutas /team/*
4. created_by_id en TeamTaskController::store() + avatar "creado por" en card
5. TeamTaskCommentController (para equipo)
6. TeamTransferController (preview + transfer) + botón en modal edición
```

---

## Fuera de alcance

- Transferencia en sentido inverso (equipo → personal)
- Historial de movimientos (quién movió qué tarjeta y cuándo)
- Notificaciones push al equipo cuando alguien transfiere
- Multi-tenancy (varios equipos por instancia)
