# Kanban multi-usuario con Supabase — Spec de diseño

**Fecha:** 2026-06-25
**Estado:** aprobado por el usuario

---

## Contexto y problema

trackActivity es actualmente una app single-user. El equipo necesita un Kanban
compartido donde asignar tareas entre developers. Cada developer además tiene
proyectos y tareas personales que no deben ser visibles para el resto.

Problema adicional: el equipo usaba Base44 (CRM de empresa) para el Kanban, pero
Base44 tiene problemas de latencia y actualización en tiempo real. trackActivity
debe reemplazarlo como herramienta principal de gestión del Kanban, con Base44
quedando como fuente de importación de proyectos y clientes (cuando su API esté
lista).

---

## Decisiones de arquitectura

### Dos Kanbans en la misma interfaz

El board de tareas tendrá un toggle **Personal / Equipo** en el header:

- **Personal**: proyectos y tareas propios, viven en SQLite local. Solo los ve
  el propietario de esa instalación. Funciona sin conexión.
- **Equipo**: proyectos y tareas compartidos, viven en Supabase PostgreSQL. Los
  ven todos los members del equipo en tiempo real.

### División de bases de datos

| SQLite local (personal) | Supabase PostgreSQL (equipo) |
|---|---|
| time_blocks, activity_events | projects (equipo) |
| manual_entries, notes | tasks (equipo) |
| generated_summaries | task_comments |
| settings, repositories | task_checkboxes |
| scoring_rules, note_folders | task_labels + task_label_task |
| projects (personal) | **team_members** (nuevo) |
| tasks (personal) | project_mappings (Base44 sync) |

Ambas conexiones coexisten en la misma instancia de Laravel. Los modelos del
equipo usan `protected $connection = 'supabase'`; los personales usan la
conexión por defecto (`sqlite`).

### Real-time vía Supabase Realtime

Cuando cualquier cliente (app local o Render) escribe en Supabase, el cambio
se broadcast automáticamente a todos los browsers suscritos al canal del equipo.
No se necesita Laravel Reverb ni polling.

### Render como acceso web opcional

Se despliega una instancia de trackActivity en Render (tier gratuito). Sirve
para acceder al Kanban del equipo desde el navegador sin instalar la app
localmente. Solo tiene activo el módulo Kanban (tracker deshabilitado).
Un cron de GitHub Actions hace un ping cada 10 minutos para evitar que Render
duerma durante el horario de trabajo.

### Base44 como fuente de importación

Cuando el jefe genere la API REST de Base44 (contrato en
`docs/crm-base44-api-contract.md`), el comando `crm:sync` importará proyectos
y tareas al Supabase del equipo. La URL y el token son configurables desde
la página de settings de la app (no hardcodeados).

---

## Componentes a construir

### 1. Supabase setup

- Crear proyecto en Supabase (free tier).
- Ejecutar las migraciones de las tablas del equipo sobre la conexión PostgreSQL
  de Supabase.
- Configurar Row Level Security (RLS) básico: acceso autenticado con la
  `service_role` key desde Laravel; acceso anon desde el frontend para Realtime.

### 2. Conexión multi-DB en Laravel

- Añadir la conexión `supabase` en `config/database.php` con driver `pgsql`.
- Variables de entorno:
  - `SUPABASE_URL` (URL del proyecto, pública)
  - `SUPABASE_DB_*` (host, port, db, user, password — para la conexión pgsql de Laravel)
  - `SUPABASE_ANON_KEY` (clave pública, se expone al frontend JS para Realtime)
  - `SUPABASE_SERVICE_ROLE_KEY` (clave privada, solo Laravel server-side para escrituras — nunca al frontend)
- Los modelos de equipo extienden un `TeamModel` base con
  `$connection = 'supabase'`.
- Si `SUPABASE_URL` no está definido, el toggle Equipo no aparece en la UI.

### 3. team_members (tabla nueva en Supabase)

```
team_members
  id            bigint PK
  name          string
  color         string   (hex, para el avatar)
  position      int
  timestamps
```

- CRUD en la página de Settings (misma sección que los settings de Supabase).
- Los miembros del equipo se crean manualmente (no hay auth de usuario).

### 4. assignee en tasks del equipo

- Migración: `tasks.assignee_id → team_members` (nullable, nullOnDelete).
- UI: selector de miembro en el modal de tarea del Equipo.
- Board: avatar con iniciales del asignado en la esquina de la card.
- Filtro: chip por asignado en el header del board (igual que el filtro de
  proyecto existente).

### 5. Toggle Personal / Equipo en el Kanban

- Switch en el header del board junto al título.
- Cambia el `mode` en el estado del JS (personal | team).
- En modo personal: el board carga tareas de la conexión SQLite (comportamiento
  actual).
- En modo equipo: el board carga tareas de la conexión Supabase.
- La preferencia de modo se persiste en `localStorage`.

### 6. Supabase Realtime en el frontend

- El JS del board importa el cliente JS de Supabase (`@supabase/supabase-js`).
- En modo equipo, se suscribe al canal `kanban:team` y escucha eventos
  `INSERT`, `UPDATE`, `DELETE` sobre la tabla `tasks`.
- Al recibir un evento se actualiza el estado local del board sin recargar.
- Al cambiar a modo personal se cancela la suscripción.

### 7. Settings: nuevos inputs

Sección nueva "Conexión de equipo" en la página de configuración:

- `SUPABASE_URL` (URL del proyecto Supabase)
- `SUPABASE_KEY` (anon key pública)
- Estado: conectado / sin configurar

Sección "CRM (Base44)":

- `BASE44_URL` (URL base de la API)
- `BASE44_TOKEN` (Bearer token)
- Botón "Sincronizar ahora" (dispara `crm:sync` vía queue)

Ambas secciones se guardan en la tabla `settings` (clave/valor ya existente).

### 8. Render deployment

- `render.yaml` en la raíz del repo, apuntando al subdirectorio `./dashboard`
  como `rootDir` del web service (la app Laravel vive en `/dashboard`, no en `/`).
- Variables de entorno en Render: las mismas de Supabase + `APP_ENV=production`.
- Módulo tracker deshabilitado en la instancia de Render mediante variable
  de entorno.
- GitHub Actions workflow `.github/workflows/keep-alive.yml`: cron
  `*/10 * * * *` que hace `curl` al endpoint de Render durante el horario
  laboral (8h–20h UTC).

---

## Flujo de datos: mover una tarjeta (modo Equipo)

```
1. Usuario arrastra la card en el board (JS)
2. JS actualiza estado local inmediatamente (optimistic update)
3. JS hace PATCH /tasks/{id} → Laravel controller
4. Controller escribe en Supabase PostgreSQL (conexión 'supabase')
5. Supabase detecta el cambio y emite evento Realtime
6. Todos los browsers suscritos reciben el evento
7. Cada browser actualiza su board con el nuevo estado
```

---

## Fuera de alcance (en este spec)

- Autenticación de usuarios: no hay login. El equipo accede por URL. Los
  `team_members` son perfiles estáticos, no cuentas.
- Sincronización bidireccional con Base44: trackActivity importa de Base44,
  no escribe de vuelta.
- Vinculación de time_blocks personales con tareas del equipo: opcional, queda
  para una fase posterior.
- Multi-tenancy: un solo equipo por instalación.

---

## Orden de implementación

```
Fase 1 — Cimientos
  1a. Crear proyecto Supabase + ejecutar migraciones de tablas de equipo
  1b. Conexión 'supabase' en Laravel + split de modelos

Fase 2 — Equipo básico
  2a. team_members: tabla + CRUD en settings
  2b. assignee en tasks: migración + UI en card + avatar en board

Fase 3 — Kanban dual
  3a. Toggle Personal/Equipo en el header del board
  3b. Carga de tareas según conexión activa

Fase 4 — Real-time
  4a. Supabase Realtime en el frontend JS
  4b. Optimistic updates en drag & drop

Fase 5 — Settings y configuración
  5a. Inputs Supabase en settings
  5b. Inputs Base44 + botón sync en settings

Fase 6 — Deployment
  6a. render.yaml + variables de entorno
  6b. GitHub Actions keep-alive cron
```

---

## Dependencias externas

| Dependencia | Estado | Bloqueante |
|---|---|---|
| Cuenta Supabase (free) | Por crear | Sí, para Fase 1 |
| API Base44 (jefe) | Pendiente | No (el sync es opcional) |
| Cuenta Render (free) | Por crear | No hasta Fase 6 |
