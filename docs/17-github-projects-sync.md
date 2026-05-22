# 17 · Plan — Sincronización de Tareas con GitHub Projects

Integración del módulo de Tareas (Kanban) con un **GitHub Project (v2)**
para que un equipo de programadores comparta el mismo tablero.

> Es la primera funcionalidad que sale del modelo 100 % offline/local, y
> **solo afecta a Tareas** — el tracking y las notas siguen locales.

---

## 1. Objetivo

Sincronización en **dos sentidos** entre las `tasks` locales y los *items*
de un GitHub Project. Cada miembro trabaja en su propia instancia de la
app y todas reflejan el mismo tablero.

---

## 2. Decisiones tomadas

- **API**: GraphQL de GitHub (los Projects v2 no tienen API REST).
- **Project**: en la cuenta personal del dueño; los compañeros se añaden
  como colaboradores con rol *Write*.
- **Auth**: un **PAT *classic*** por persona (scope `project`), en el `.env`
  de cada instancia. Cada cambio queda atribuido a su autor.
- **Sin webhooks** (la app no tiene URL pública) → sincronización por
  *polling*: un comando `tasks:sync` periódico + un botón "Sincronizar".
- **Items**: las tareas creadas desde la app son *draft issues* del
  Project. Los items que sean Issues/PR reales se leen, pero su título no
  se reescribe desde la app.
- **Campos sincronizados**: título, descripción y columna (campo *Status*).
  Prioridad, fecha, posición y los vínculos a proyecto/tiempo son locales.

---

## 3. Modelo de datos

`tasks` gana dos columnas:

| Columna | Tipo | Notas |
|---------|------|-------|
| `github_item_id` | string, nullable | node-id del item en el Project. |
| `github_synced_at` | datetime, nullable | Última sincronización correcta. |

Una tarea tiene **cambios locales por subir** si `updated_at > github_synced_at`.

---

## 4. Configuración (`config/github.php` + `.env`)

- `GITHUB_TOKEN` — el PAT *classic*.
- `GITHUB_PROJECT` — `owner/número` del Project (ej. `PauloFragaDev/3`).
- `status_map` — mapa `TaskStatus` local ↔ nombre de opción del campo
  *Status* del Project.

---

## 5. Algoritmo de sincronización (`tasks:sync`)

1. Resolver el Project: node-id, id del campo *Status* y sus opciones.
2. Traer todos los items remotos (id, `updatedAt`, título, body, Status).
3. Por cada tarea local con `github_item_id`:
   - `localChanged` = `updated_at > github_synced_at`
   - `remoteChanged` = `item.updatedAt > github_synced_at`
   - **ambos → conflicto: gana GitHub** (se aplica lo remoto).
   - solo local → se sube (*push*).
   - solo remoto → se aplica lo remoto.
4. Tareas locales sin `github_item_id` → crear *draft issue* remoto.
5. Items remotos sin tarea local → crear tarea local.
6. Tareas cuyo item ya no existe en remoto → borrar la tarea local.
7. `github_synced_at = now()` en todo lo sincronizado.

---

## 6. UI

- Botón **"Sincronizar"** en el tablero, con indicador de la última sync.
- Aviso claro si falta configuración o el token falla.
- El comando `tasks:sync` también en el scheduler (cada ~10 min).

---

## 7. Arquitectura del código

- `GitHubProjectClient` — cliente GraphQL (HTTP). Detrás de una interfaz
  para poder probar la lógica de sync con un cliente falso.
- `TaskSyncService` — el algoritmo de §5; recibe el cliente por inyección.
- El comando `tasks:sync` y `TaskController::sync` (botón) usan el servicio.

---

## 8. Hitos

| Hito | Contenido | Aceptación |
|------|-----------|------------|
| **G1** | Config + cliente GraphQL + migración + *pull* (una vía). | Conectar y traer el tablero a las tareas locales. |
| **G2** | *Push*: crear/actualizar/borrar items remotos. | Doble sentido funcionando. |
| **G3** | Conflictos, scheduler, botón + estado de sync, docs. | Sync usable y programada. |

---

## 9. Fuera de alcance (v1)

- *Custom fields* de GitHub (prioridad/fecha como campos del Project).
- Tiempo real (es *polling*).
- Sincronizar la posición/orden dentro de la columna.
- Items que son Issues/PR reales: solo se lee su contenido.

---

## 10. Verificación

La capa que habla con la API de GitHub **no se puede probar sin un token
real**. La lógica de sincronización se testea con un **cliente falso**
(tests con un *fake* que implementa la interfaz); la conexión real la
verifica el usuario con su PAT.

---

## 11. Rama

`paulo-kanban-002`, partiendo de `main`.
