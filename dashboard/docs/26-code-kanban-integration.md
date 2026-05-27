# Integración con code-kanban (extensión de VS Code)

> Análisis previo a la implementación de la sincronización. Hecho mayo 2026
> a partir del repo [marcover9000/code-kanban](https://github.com/marcover9000/code-kanban).

## 1. Qué es code-kanban

Extensión de VS Code, **MIT**, escrita en TypeScript (~97% TS, 3% CSS/JS).
Tablero personal de tareas por workspace, sin cuentas ni sincronización
en la nube. La extensión guarda los datos en un único archivo en la raíz
del workspace.

- Repo upstream: <https://github.com/marcover9000/code-kanban>
- Atajo principal: `Ctrl+Alt+K` / `Cmd+Alt+K` para abrir/cerrar el tablero.
- Modos: `panel` (sidebar vertical) o `shortcut` (abre archivo en editor).

## 2. Modelo de datos

### Archivo y formato

- **Nombre**: `.todo.kanban`.
- **Ubicación**: raíz del workspace abierto.
- **Formato**: JSON plano.

### Esquema (a alto nivel)

```json
{
  "lists": [
    {
      "id": "uuid",
      "title": "Backlog",
      "color": "#9CA3AF",
      "cards": [
        {
          "id": "uuid",
          "title": "Tarjeta",
          "description": "Markdown opcional",
          "labels": [...],
          "due": "YYYY-MM-DD?",
          "checkboxes": [{ "title": "...", "checked": false }],
          "comments": [...]
        }
      ]
    }
  ],
  "archive": { "lists": [], "cards": [] },
  "settings": { "labels": [...] }
}
```

El README upstream no documenta el esquema completo de `cards`. Para
una implementación que escriba el archivo, hay que inspeccionar `src/`
de la extensión y/o un archivo `.todo.kanban` real generado por ella.

### Identificación de "proyecto" / repo

code-kanban **no** identifica por `git remote` ni por nombre de repo.
Identifica por **path local del workspace abierto**. Cada workspace
tiene su propio `.todo.kanban`.

En trackActivity esto encaja con `ProjectMapping`, que ya guarda hints
de `cwd` (carpeta local del repo). Para vincular un proyecto de
trackActivity con un `.todo.kanban` necesitamos un campo nuevo o reusar
el `cwd_hint` del primer mapping del proyecto.

## 3. Configuración de la extensión (sin fork)

- `code-kanban.default-lists`: array de títulos de columnas por defecto
  para tableros nuevos. **Las 6 columnas pedidas (Blocked, Backlog,
  To Do, Doing, Stand By, Done) se configuran aquí** — no hace falta
  modificar el código.
- `code-kanban.activity-bar-mode`: `"shortcut"` o `"panel"`.
- `code-kanban.gitignore-todo`: añadir `*.kanban` al `.gitignore` automáticamente.
- `code-kanban.theme`: `"light"`, `"dark"`, `"system"`.
- `show-description`, `show-task-list`, `show-labels`, `show-due-date`,
  `show-checkbox-count`: densidad visual de las tarjetas.

## 4. Lo que la extensión NO expone

- **No hay API HTTP** ni endpoints.
- **No publica eventos** que trackActivity pueda escuchar en remoto.
- **No tiene comandos VS Code** para "exportar" o "sincronizar con un
  servidor externo".

Toda integración pasa por el archivo JSON. Es la única superficie útil
para hablar entre las dos apps sin tocar el código de la extensión.

## 5. Caminos de integración

### A · Sin fork — sync por archivo

**Idea**: trackActivity reconoce el `.todo.kanban` de cada repo (por
`cwd_hint` del `ProjectMapping`) y sincroniza las tarjetas con la
tabla `tasks`.

- **Pull**: comando/cron que lee cada `.todo.kanban` de los repos
  registrados y crea/actualiza/borra tareas en trackActivity.
- **Push**: al editar una tarea en trackActivity, se reescribe el
  `.todo.kanban` del repo correspondiente.

**Pros**
- Cero cambios en la extensión, cero mantenimiento de fork.
- Robusto frente a evolución de upstream — el JSON tiene un esquema
  estable.
- Funciona en background (file watcher o cron cada N segundos).

**Contras**
- Sincronización **diferida** (no en vivo): el usuario edita en VS Code,
  guarda → trackActivity lo refleja al siguiente tick.
- Conflictos posibles: si ambos lados editan a la vez, hay que decidir
  estrategia (última escritura gana, merge por timestamp por tarjeta,
  etc.).
- Mapeo de campos no triviales: `code-kanban` tiene `comments` con su
  propio formato, `labels` propias por workspace, etc. Hay que decidir
  qué se sincroniza y qué no.

**Esfuerzo estimado**: ~200-300 LOC en trackActivity (Service + comando
artisan + un test de ida/vuelta).

### B · Fork con sync remoto

**Idea**: extender code-kanban para que, además del archivo, publique
cada cambio a una API REST de trackActivity (`POST /api/sync/kanban`).

**Pros**
- Sincronización **en vivo**.
- Menos riesgo de conflictos (el cambio se propaga al instante).
- code-kanban puede ofrecer columnas fijas (Blocked/Backlog/.../Done)
  por configuración del fork, sin depender de que el usuario configure
  `default-lists` en cada workspace.

**Contras**
- **Mantenimiento del fork**: cada upstream release toca rebasear.
- Requiere primero **API REST en trackActivity** (punto 5 del roadmap
  general). Sin esa API, no hay a quién publicar.
- Posible UX confusa: ¿qué pasa cuando el servidor de trackActivity
  está parado? ¿se cae la extensión, queda offline, encola?

**Esfuerzo estimado**: API REST en trackActivity (~1 semana de PRs) +
fork de la extensión con sync remoto (~2-3 días) + tests E2E.

### C · Fork + PR upstream

Mismo que B, pero con la intención de devolver los cambios a upstream.
Si marcover9000 los acepta (mensaje de sync remoto opcional, columnas
configurables sin lista cerrada), nos quitamos el fork. Si no, B.

## 6. Recomendación

**Empezar por A** (sin fork). El formato `.todo.kanban` es JSON simple
y estable; trackActivity puede leerlo/escribirlo sin tocar la extensión.
Las 6 columnas se establecen con un setting de VS Code.

Si tras vivir con A descubrimos limitaciones concretas (sincronización
en vivo crítica, campos que el formato no soporta, etc.), **entonces**
evaluamos B/C con motivos reales — no preventivamente.

### Detalle de la implementación A propuesta

1. Añadir `kanban_file_path` (nullable) a `projects` o a `project_mappings`
   — apunta al `.todo.kanban` del repo. Por defecto, derivado de `cwd_hint`.
2. Servicio `App\Services\CodeKanban\KanbanSyncService` con:
   - `pull(Project $project)`: lee el `.todo.kanban`, hace upsert de
     tareas. Estrategia: por `id` de tarjeta (UUID) ↔ campo nuevo
     `tasks.kanban_card_id`. Las que no estén en el archivo y tenían
     `kanban_card_id` → archivadas.
   - `push(Project $project)`: reescribe el `.todo.kanban` desde las
     tareas de trackActivity para ese proyecto.
   - `syncAll()`: itera todos los proyectos con `kanban_file_path`.
3. Comando artisan `kanban:sync` + setting para encadenarlo al scheduler.
4. Botón "Sincronizar code-kanban" en `/tasks` (similar al de GitHub
   Project).
5. Conflictos: estrategia "última escritura gana por tarjeta", con
   `kanban_card_synced_at` para detectar drift.

## 7. Lo que NO entra en esta primera versión

- Sincronización de `comments` (formato distinto en ambos lados).
- Labels: code-kanban guarda labels por workspace; trackActivity tiene
  catálogo global. Mapear por título (case-insensitive) y crear las
  que falten.
- Watch en vivo del archivo (inotify): cae en la versión "en vivo" — A
  con file watcher es posible pero alarga.

---

**Estado**: análisis aprobado pendiente de decisión sobre **A vs fork**.
Si se elige A, la implementación cae en otro PR.
