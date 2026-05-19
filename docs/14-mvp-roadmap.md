# 14 · Roadmap MVP

Define exactamente **qué entra en la v1** y qué queda fuera. El criterio rector es: *"¿el usuario podría reconstruir y reportar su semana sin esto?"*. Si la respuesta es sí, queda fuera de v1.

---

## Versión 1.0 — Alcance

### Tracker (Python)

- [x] Daemon ejecutable en foreground y como servicio de usuario systemd.
- [x] CLI con: `run`, `doctor`, `collect <kind> --once --dry-run`, `version`.
- [x] Configuración por `config.yml` (validada con pydantic).
- [x] Buffer + flush batch a SQLite.
- [x] Logging rotado.
- [x] Collectors obligatorios:
  - [x] `window` (xdotool + xprop WM_CLASS).
  - [x] `git` (pygit2) — upserta `repositories`, dedupe por (branch, modified, last_commit).
  - [x] `idle` (Xlib screensaver) — solo transiciones.
- [x] Deduplicación entre ticks consecutivos (window, git).
- [x] Validación de schema al arrancar.

### Dashboard (Laravel)

- [x] Migraciones de las 8 tablas del esquema.
- [x] Seeders iniciales: proyectos demo, `scoring_rules`, mappings de ejemplo.
- [x] Modelos Eloquent + relaciones.
- [ ] Servicios `Aggregator`, `Scorer`, `MappingResolver`, `SummaryGenerator` (engine `template`), `Exporter`.
- [ ] Comandos artisan: `tracker:rebuild-blocks`, `tracker:generate-summaries`, `tracker:export`, `tracker:prune-events`, `tracker:doctor`.
- [ ] Scheduler con rebuild + summary cada 15 min, prune diario.
- [ ] UI:
  - [x] Day view (`/`, `/day/{date}`).
  - [ ] Week view (`/week/{week}`).
  - [ ] Calendar mensual (`/calendar`).
  - [ ] Export form (`/export`).
- [ ] Edición de bloques: reasignar proyecto, editar resumen.
- [ ] Tema oscuro por defecto.
- [ ] Export TXT / Markdown / CSV con agrupaciones `session` y `project-day`.

### Mappings y scoring

- [ ] Tipos de mapping: `repository`, `folder`, `url_pattern`, `email_subject`, `window_title`.
- [ ] Pesos cargados por seeder (editables vía SQL o Filament).
- [ ] Snapshot de scoring por bloque (`time_blocks.scoring_snapshot`).
- [ ] Confianza con tres niveles (Alta / Media / Baja).

### Documentación

- [x] Conjunto completo en `/docs` (este repositorio).

---

## Versión 1.0 — Fuera de alcance

Funcionalidades **intencionalmente excluidas** para mantener el MVP enfocado:

- ❌ Engine LLM para summaries. (Solo `template` en v1.)
- ❌ Soporte Wayland.
- ❌ Collector de Chrome vía extensión. (Solo título de ventana.)
- ❌ Integración API con GitHub/Jira. (Solo lo que se vea en navegador.)
- ❌ Panel Filament. (Editor avanzado opcional; mappings se cargan vía seeder o CLI.)
- ❌ Merge / Split visual de bloques. (CLI permite reasignar; merge/split llegan en v1.1.)
- ❌ Notificaciones, websockets, hot updates en la UI.
- ❌ Soporte multi-usuario, login, RBAC.
- ❌ Sincronización entre máquinas.
- ❌ Apps móviles / web extension.
- ❌ Plugin nativo de VSCode / JetBrains.

---

## Hitos sugeridos

| Hito | Contenido | Criterio de aceptación |
|------|-----------|------------------------|
| **M1 — Schema + Tracker mínimo** ✅ | Migraciones, modelos, `WindowCollector`, `IdleCollector`, storage, CLI `run`. | Tras 30 min de uso, hay > 100 filas en `activity_events`. |
| **M4 — UI day view** ✅ (adelantado) | Layout, badges, evidencia (edición inline pendiente para M3). | Usuario ve su día reconstruido desde el navegador. |
| **M2 — Git collector + repos** ✅ | `GitCollector`, upsert de `repositories`. | Cambiar de rama y guardar archivos se refleja en BBDD en < 5 min. |
| **M3 — Aggregator + Scorer** | Servicios + `tracker:rebuild-blocks` + scoring ponderado real. | Reconstrucción de 1 día genera bloques con proyecto dominante razonable. |
| **M5 — Summary template** | `SummaryGenerator` engine `template`, regeneración programada. | > 80% de bloques no-idle tienen summary no vacío. |
| **M6 — Export** | TXT, Markdown, CSV; CLI + UI. | Usuario puede pegar export de un día en el timesheet sin retocar. |
| **M7 — Week + Calendar** | `/week`, `/calendar`. | Vista semanal navegable, totales por proyecto. |
| **M8 — Servicio systemd + pulido** | Unit file, `tracker doctor`, retención (`prune-events`), README final. | Daemon corre durante 1 semana sin intervención, BBDD < 50 MB. |

Cada hito debe poder usarse antes de pasar al siguiente: nada queda a medias.

---

## Versión 1.1 (post-MVP, prioridades sugeridas)

1. Merge / Split visual de bloques en UI.
2. Filament admin para mappings y scoring.
3. Browser collector mejorado (vía extensión local mínima).
4. Estadísticas: dashboards de horas por proyecto/mes.
5. Multi-locale: inglés.

---

## Versión 2.0 (visión)

- Engine `llm` con Ollama local.
- Plugin VSCode opcional para capturar archivos abiertos exactos.
- Integración con GitHub API (opcional, opt-in) para PRs/Issues precisos.
- Soporte Wayland.
- Sincronización opcional entre máquinas del mismo usuario vía `rsync`/`syncthing` del SQLite.

---

## Criterios de "Definition of Done" para v1

El MVP se considera completo cuando:

1. El usuario puede instalar el sistema en una máquina Ubuntu nueva siguiendo `03-installation.md` en < 30 min.
2. Tras 1 semana de uso normal, el dashboard reconstruye los días con confianza ≥ media en ≥ 70% de los bloques no-idle.
3. El export en Markdown de una semana se puede pegar directamente en el timesheet con < 10% de correcciones manuales.
4. El daemon corre sin reinicios durante ≥ 7 días.
5. El consumo de recursos respeta el budget de [`13-development-guide.md`](13-development-guide.md).
6. Toda la documentación de `/docs` está al día con el código.

---

## Anti-objetivos

Cosas que el MVP **no intenta** hacer y que confundirían si se intentan:

- No es un Pomodoro / focus timer.
- No bloquea sitios web ni aplicaciones.
- No produce reportes "de productividad" (líneas/hora, etc.).
- No infiere intención emocional, contexto personal o "calidad" del trabajo.
- No reemplaza el timesheet corporativo; alimenta su rellenado.
