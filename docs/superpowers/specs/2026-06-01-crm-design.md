# CRM (Clientes) — diseño

Fecha: 2026-06-01
Rama: `paulo-crm`
Estado: aprobado, pendiente de plan de implementación.

## Contexto

trackActivity es una herramienta personal mono-usuario (Laravel/Blade/Tailwind +
daemon Python) que reconstruye el día de trabajo en pasivo. Ya tiene módulos de
Proyectos, Tareas (Kanban), Notas, Pomodoro, Timeline e Informes. El módulo
`Project` es el eje: tiene `code/name/color/description` y relaciones a
`timeBlocks` (vía `dominant_project_id`), `tasks`, `notes`, `mappings` y
`repositories`. El tiempo ya se agrega por proyecto.

La integración con GitHub Projects se eliminó (PR #39), así que la definición
previa del CRM ("proyectos como categorías + Kanban CRM ↔ GitHub") queda obsoleta
y este diseño la reemplaza desde cero.

## Objetivo

Una capa de **Clientes** por encima de los proyectos que combine **ficha de
contacto** y **analítica de tiempo agregado**: un cliente agrupa varios proyectos
y su valor es ver el tiempo/actividad sumados sobre ellos, además de tener sus
datos de contacto, tareas y notas reunidos en un sitio.

Decisiones tomadas en el brainstorm:
- Propósito: ficha de cliente **+** tiempo agregado por cliente (no pipeline de ventas).
- Granularidad: **cliente plano** (una ficha con campos de contacto; sin contactos múltiples).
- Enfoque de datos: **A** — entidad `Client` nueva + `projects.client_id` (1 cliente → N proyectos).
- Ficha **completa** (contacto + proyectos + tiempo con desglose + tareas + notas).
- Sección propia "Clientes" en la nav (Proyectos sigue en Settings como clasificación de bajo nivel).

## Modelo de datos

### Tabla `clients`
- `id`
- `name` — string, requerido.
- `company` — string, nullable.
- `email` — string, nullable.
- `phone` — string, nullable.
- `website` — string, nullable.
- `notes` — text, nullable.
- `color` — string (hex), nullable (dot visual, como Project).
- `deleted_at` — soft delete (archivar, mismo patrón que Task).
- timestamps.

### Migración aditiva
- `projects.client_id` — unsignedBigInteger nullable, FK → `clients.id` con
  `nullOnDelete`. Los proyectos actuales quedan con `client_id = null`.

### Relaciones
- `Client hasMany Project` (`client_id`).
- `Project belongsTo Client`.
- Tareas/notas/tiempo del cliente se obtienen **a través de sus proyectos**
  (`whereIn('project_id', $client->projects->pluck('id'))`).

## Agregación de tiempo

`ClientService` (servicio dedicado, no lógica en el controlador) calcula el
tiempo de un cliente por periodo, reutilizando el patrón ya usado en Informes y
en el heatmap del dashboard:

- Suma sobre `time_blocks` (`status != 'idle'`) + `manual_entries` cuyos
  `project_id` (o `dominant_project_id` en time_blocks) pertenecen a los proyectos
  del cliente, en el rango del periodo.
- Fórmula de minutos: `SUM((julianday(ends_at) - julianday(starts_at)) * 1440)`.
- Devuelve: total del periodo y desglose por proyecto.
- Periodos: semana / mes / 30 días (mismos que Informes).

## UI y rutas

### Navegación
- Nuevo ítem **"Clientes"** en el sidebar (`layouts/app.blade.php`), gateado por
  el flag de módulo `clients`. Requiere **un icono nuevo** en el componente
  `x-icon` (el set actual no tiene "users/empresa"); añadir uno de Heroicons
  (p. ej. `users`).

### Rutas (`ClientController`)
- `GET  /clients` — index.
- `GET  /clients/create` · `POST /clients` — alta.
- `GET  /clients/{client}` — ficha (detalle rico).
- `GET  /clients/{client}/edit` · `PATCH /clients/{client}` — edición.
- `DELETE /clients/{client}` — archivar (soft delete), con confirmación SweetAlert.

### Index (`/clients`)
- Tarjetas/lista de clientes: dot de color, nombre + empresa, nº de proyectos y
  **tiempo de este periodo**. Empty state con voz (CTA "Nuevo cliente").

### Ficha (`/clients/{client}`)
- Cabecera: nombre, empresa, contacto (email/teléfono/web como enlaces), notas.
- **Tiempo agregado**: selector de periodo (semana/mes/30d) + total + desglose
  por proyecto (barras, reutilizando el patrón visual de Informes).
- **Proyectos** del cliente (lista con enlace a editar el proyecto).
- **Tareas** del cliente (las de sus proyectos, en formato compacto).
- **Notas** del cliente (las de sus proyectos).

### Asignación
- En `projects/edit.blade.php`: añadir un `<select>` "Cliente" (opcional) para
  asignar el proyecto a un cliente. Procesar en `ProjectController::update` (y
  `store` si aplica) añadiendo `client_id` a la validación/fillable.

## Integración / visibilidad

- `ModuleVisibility`: nueva entrada `clients` (label/description/icon) → toggle en
  `/settings/general`. Nav e índice gateados por `$modules['clients']['enabled']`.
- `Project::$fillable` gana `client_id`.
- (Opcional) columna "Cliente" en la tabla de Proyectos de Settings.

## Testing

Feature tests siguiendo los patrones actuales (TaskControllerTest, etc.):
- CRUD de cliente (crear, editar, archivar) y validación de `name` requerido.
- Asignación cliente ↔ proyecto (asignar y desasignar desde el proyecto).
- **La ficha agrega bien el tiempo**: crear cliente con 2 proyectos, sembrar
  `time_blocks`/`manual_entries`, y assertar el total y el desglose por proyecto.
- Roll-up de tareas/notas en la ficha (solo las de los proyectos del cliente).
- Gate de visibilidad: con el módulo desactivado, la nav y `/clients` no rinden.
- `nullOnDelete`: archivar un cliente deja sus proyectos sin cliente, no los borra.

## Fuera de alcance (YAGNI)

- Contactos múltiples por cliente (cliente plano).
- Pipeline / oportunidades / deals.
- Vista "por cliente" en el Informe global (la analítica vive en la ficha).
- Tiempo trackeado directamente a un cliente sin proyecto.

## División en PRs (a confirmar en el plan)

1. **Fundación**: tabla `clients` + modelo + `projects.client_id` + relaciones +
   `ClientController` CRUD + index + nav + icono + flag de módulo + asignación en
   el form de proyecto + tests del CRUD/asignación.
2. **Ficha rica**: `ClientService` (agregación de tiempo) + la ficha del cliente
   (tiempo con desglose, proyectos, tareas, notas) + tests de agregación/roll-up.

## Verificación

- `npm run build` sin errores; `php artisan migrate` aplica la migración aditiva.
- `php artisan test` en verde (los nuevos tests incluidos).
- Repaso visual del index y la ficha en los 4 temas × 2 modos.
