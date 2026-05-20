# 15 · Plan de desarrollo — Módulo TODO / Kanban

Plan del primer módulo nuevo posterior al tracking: un tablero de tareas
personales, opcionalmente vinculadas a proyectos. Es **100 % lado
dashboard** (Laravel + Blade) — no toca el daemon Python.

> Documento de plan; los hitos se marcan a medida que se completan, igual
> que en [`14-mvp-roadmap.md`](14-mvp-roadmap.md).

---

## 1. Objetivo y alcance

**Es** un gestor de tareas personal con vista Kanban. Cada tarea puede
(opcionalmente) vincularse a un proyecto existente, de modo que el tablero
y el tracking comparten el catálogo de `projects`.

**No es** un gestor de proyectos de equipo: sin asignación a personas, sin
permisos, sin colaboración (la app es de un solo usuario).

---

## 2. Modelo de datos

Tabla nueva `tasks`:

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint | |
| `project_id` | FK `projects` nullable | Vínculo opcional; `nullOnDelete` |
| `title` | string | Requerido |
| `description` | text nullable | Texto/Markdown corto opcional |
| `status` | string (enum `TaskStatus`) | Columna del tablero |
| `priority` | string (enum `TaskPriority`) nullable | |
| `due_date` | date nullable | |
| `position` | integer | Orden dentro de la columna |
| `completed_at` | datetime nullable (UTC) | Se rellena al pasar a `Done` |
| `created_at` / `updated_at` | datetime | |

Enums nuevos (en `app/Enums/`, según convención del proyecto):

- `TaskStatus`: `Backlog`, `Todo`, `Doing`, `Done`.
- `TaskPriority`: `Low`, `Normal`, `High`.

---

## 3. Decisiones de diseño

- **Columnas fijas** vía enum `TaskStatus`. Columnas personalizables
  (tabla `board_columns`) quedan para una v2 — multiplican la complejidad
  y aportan poco en un tablero personal.
- **Un único tablero global**. Cada tarjeta muestra un chip con su
  proyecto; el tablero se **filtra por proyecto**. No hay un tablero por
  proyecto (sería solo una vista filtrada).
- **`position` entero**, reindexado dentro de la columna al mover. El
  número de tareas por columna es pequeño: reindexar es trivial.
- `completed_at` se fija/limpia automáticamente al entrar/salir de `Done`
  (útil para un futuro "tareas cerradas esta semana").

---

## 4. Rutas (`web.php`, sección nueva)

| Método | Ruta | Acción |
|--------|------|--------|
| GET | `/tasks` | Vista del tablero |
| POST | `/tasks` | Crear tarea |
| PATCH | `/tasks/{task}` | Editar (título, descripción, proyecto, prioridad, fecha) |
| PATCH | `/tasks/{task}/move` | Mover de columna / reordenar — **endpoint AJAX** del drag & drop |
| DELETE | `/tasks/{task}` | Borrar |

`TaskController` con esos métodos. El `move` responde JSON; el resto
redirige con flash, como el resto de la app.

---

## 5. UI

- **Vista tablero** (`resources/views/tasks/board.blade.php`): columnas =
  valores de `TaskStatus`, tarjetas dentro.
- **Drag & drop** con **SortableJS** (vanilla, ligera, bundlada vía npm —
  coherente con SweetAlert/Toastify). Al soltar, un `fetch` PATCH a
  `/tasks/{task}/move` persiste columna + posición.
- **Alta/edición en modal `<dialog>`**, reutilizando el patrón existente
  (`data-modal-open`).
- **Tarjeta**: título, chip de proyecto (con su color), prioridad y fecha
  de vencimiento (resaltada si está vencida).
- **Filtro por proyecto** sobre el tablero.
- Ítem nuevo en el nav del layout: **Tareas**.
- Confirmación de borrado con SweetAlert (`data-confirm`); feedback con
  toast (`->with('status', …)`).

---

## 6. Dependencia nueva

- **SortableJS** (`npm i sortablejs`) — drag & drop. Sin CDN
  (offline-first), bundlada por Vite.

---

## 7. Hitos

| Hito | Contenido | Aceptación |
|------|-----------|------------|
| **K1** | Migración `tasks` + enums + modelo + `TaskController` CRUD + vista de lista simple. | Crear/editar/borrar tareas funciona. |
| **K2** | Vista tablero con columnas; mover de columna vía `<select>` (sin DnD). | El tablero muestra y mueve tareas sin JS. |
| **K3** | Drag & drop con SortableJS + endpoint `move` + posiciones. | Arrastrar reordena y persiste. |
| **K4** | Filtro por proyecto, prioridad, fechas; pulido + tests + docs. | Tablero usable a diario; suite en verde. |

Cada hito debe poder usarse antes de pasar al siguiente.

---

## 8. Fuera de alcance (v1)

- Columnas personalizables / múltiples tableros.
- Subtareas y checklists dentro de la tarea.
- Etiquetas, adjuntos, recurrencia, recordatorios.
- Integración con el tiempo del tracker (ver §10).

---

## 9. Tests

`tests/Feature/TaskControllerTest`: CRUD, validación, endpoint `move`,
transición a `Done` que rellena `completed_at`. El drag & drop en sí
(JS) no lleva test automático, como el resto del JS del proyecto.

---

## 10. Integración futura con el tracking

La tarea conoce su proyecto y el tracker sabe cuánto tiempo se dedicó a
ese proyecto. En una iteración posterior, la ficha de un proyecto podría
mostrar "tareas abiertas + tiempo registrado". **No entra en v1** — solo
se deja preparado el vínculo `task → project`.

---

## 11. Rama

Rama propia partiendo de `main` ya actualizado (tras mergear
`paulo-development-002`). Sugerencia de nombre: `paulo-development-NNN` o
`feature/kanban`. El orden recomendado de los dos módulos está en
[`16-notes-plan.md`](16-notes-plan.md) §11.

---

## 12. Definition of Done

1. Migración + modelo + enums.
2. CRUD completo y tablero con drag & drop funcional.
3. Vínculo opcional a proyecto y filtro por proyecto.
4. Tests Feature en verde.
5. Sección en `/help` y en `USER-GUIDE.md`; entrada en el nav.
