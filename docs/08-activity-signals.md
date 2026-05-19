# 08 · Señales de actividad

Las **señales** (`activity_events`) son la materia prima del sistema. Cada una representa una observación puntual del SO en un instante dado. Son **inmutables** y se interpretan posteriormente por capas superiores.

> Un cambio de ventana no es una tarea, es una **señal**. La tarea se infiere por agregación.

---

## Catálogo de fuentes (`source`)

| Source | Collector | Frecuencia típica | Obligatorio en MVP |
|--------|-----------|-------------------|--------------------|
| `window` | `WindowCollector` | 10–20 s | ✅ |
| `git` | `GitCollector` | 3–5 min | ✅ |
| `browser` | `BrowserCollector` | 30 s | ⚠️ opcional |
| `thunderbird` | `ThunderbirdCollector` | 60 s | ⚠️ opcional |
| `idle` | `IdleCollector` | 30 s (solo transiciones) | ✅ |

---

## 1. `window` — Ventana activa

### Qué captura

El título y la clase de la ventana actualmente enfocada.

### Frecuencia

- Activo: cada **15 s** por defecto (rango 10–20 s).
- Idle: el `Scheduler` baja la frecuencia (×4) para reducir ruido.

### Backend técnico

- X11 (`xdotool getactivewindow` + `xdotool getwindowname <wid>` para el título; `xprop -id <wid> WM_CLASS` para la clase de aplicación — más portable que `xdotool getwindowclassname`, que no está en todas las versiones).
- `wmctrl -a :ACTIVE:` (fallback, no implementado en M1).

### Apps detectables por `wm_class`

| App | `wm_class` típico |
|-----|-------------------|
| Visual Studio Code | `code.Code` |
| Cursor | `Cursor.Cursor` |
| GNOME Terminal | `gnome-terminal-server.Gnome-terminal` |
| Tilix / Alacritty / Kitty | `tilix.Tilix` / `Alacritty.Alacritty` / `kitty.kitty` |
| Google Chrome | `google-chrome.Google-chrome` |
| Mozilla Thunderbird | `thunderbird.Thunderbird` |
| Mozilla Firefox | `firefox.firefox` |
| GitHub Desktop | `github-desktop.GitHub Desktop` |

### Inferencia de `repo_name` desde ventana

Cuando el título contiene patrones reconocibles (`nombre-repo`, `... — nombre-repo - Visual Studio Code`), el collector intenta extraer el repo. Si no, queda en `NULL` y el matching se resuelve más tarde por mappings de título.

### Ejemplos

```json
{
  "source": "window",
  "app": "code",
  "title": "api.py — jasper-api - Visual Studio Code",
  "repo_name": "jasper-api",
  "metadata": {"wm_class": "code.Code"}
}
```

```json
{
  "source": "window",
  "app": "gnome-terminal",
  "title": "paulo@host: ~/Projects/tds-platform",
  "repo_name": "tds-platform",
  "metadata": {"cwd_hint": "~/Projects/tds-platform"}
}
```

### Deduplicación

Si el tick `t` produce un signal idéntico al tick `t-1` (misma `app` + `title`), **no** se persiste. Esto colapsa muchos segundos en una sola señal y reduce el ruido sin perder información: el dashboard interpreta la ausencia como continuidad.

---

## 2. `git` — Repositorios

### Qué captura

Estado de cada repositorio Git encontrado bajo los paths configurados.

### Frecuencia

Cada 3–5 min. No tiene sentido más fino: las ramas y commits no cambian cada segundo.

### Información capturada por repo

- `repo_name` (directorio).
- `branch` actual.
- `modified_files` (porcelain status).
- `metadata.latest_commit`: `{hash, message, ts, author}`.
- `metadata.ahead`, `metadata.behind` respecto a `origin/<branch>`.

### Ejemplo

```json
{
  "source": "git",
  "repo_name": "jasper-api",
  "branch": "fix/dashboard-permissions",
  "modified_files": 7,
  "metadata": {
    "latest_commit": {
      "hash": "a1b2c3d",
      "message": "Fix CRM access permissions",
      "ts": 1716100000
    },
    "ahead": 2,
    "behind": 0
  }
}
```

### Efecto secundario

El collector **upserta** la fila de `repositories` (`name`, `path`, `last_seen_at`). Esto permite a Laravel listar repos conocidos sin escanear el disco.

---

## 3. `browser` — Pestaña activa (opcional)

### Qué captura

El título de la ventana de Chrome (que incluye el título de la tab activa) y, cuando parseable, una URL aproximada.

### Frecuencia

Cada 30 s.

### Estrategia

MVP: **solo título de ventana**, sin extensión. Patrones útiles:

- `"Title · Issue #123 · org/repo · GitHub - Google Chrome"`
- `"PROJ-456: ... - Jira - Google Chrome"`
- `"... - Confluence - Google Chrome"`

Si el título matchea uno de los `url_patterns` configurados, se persiste con `url` deducida (best-effort).

### Ejemplo

```json
{
  "source": "browser",
  "app": "chrome",
  "title": "Fix permissions · Issue #123 · company/jasper-api · GitHub - Google Chrome",
  "url": "github.com/company/jasper-api/issues/123",
  "metadata": {"matched_pattern": "github.com"}
}
```

### Privacidad

No se hace scraping. No se leen cookies, historial ni contenido de tabs. Solo el texto que ya es visible en la barra de título.

---

## 4. `thunderbird` — Asunto de correo (opcional)

### Qué captura

Asunto del correo abierto (o de la composición) leído del título de ventana de Thunderbird.

### Frecuencia

Cada 60 s.

### Ejemplo

```json
{
  "source": "thunderbird",
  "app": "thunderbird",
  "title": "Re: JASPER permissions - Mozilla Thunderbird",
  "subject": "Re: JASPER permissions"
}
```

### Uso

Evidencia secundaria. Útil para reconocer bloques donde se respondieron correos relacionados a un proyecto, aunque no hubo actividad de código.

---

## 5. `idle` — Inactividad

### Qué captura

Transiciones de actividad → inactividad y viceversa.

### Frecuencia

Polling cada 30 s, pero **solo emite señal en transiciones** (entrar o salir de idle), no en cada tick.

### Backend

`Xlib.ext.screensaver.XScreenSaverQueryInfo()` → `idle` en ms.

### Ejemplos

```json
{"source": "idle", "metadata": {"state": "enter", "idle_seconds": 184}}
{"source": "idle", "metadata": {"state": "exit",  "idle_seconds": 1230}}
```

### Uso por el aggregator

Los bloques que contienen un periodo continuo de idle > umbral se marcan con `status = 'idle'` y `dominant_project_id = NULL`. No aportan al timeline reportable.

---

## Formato canónico en BBDD

Todas las señales viven en `activity_events`. Mapeo:

| Campo BBDD | window | git | browser | thunderbird | idle |
|------------|--------|-----|---------|-------------|------|
| `source` | `window` | `git` | `browser` | `thunderbird` | `idle` |
| `app` | ✅ | — | `chrome` | `thunderbird` | — |
| `title` | ✅ | — | ✅ | ✅ | — |
| `repo_name` | inferido | ✅ | — | — | — |
| `branch` | — | ✅ | — | — | — |
| `modified_files` | — | ✅ | — | — | — |
| `url` | — | — | ✅ | — | — |
| `subject` | — | — | — | ✅ | — |
| `metadata` | wm_class, cwd | commit, ahead/behind | matched_pattern | — | state, idle_seconds |
| `occurred_at` | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Política de captura

- Una señal **vive para siempre** hasta que el job de retención la borra (default 90 días).
- Nunca se modifican señales existentes. Si el daemon detecta un error, escribe una nueva.
- Las señales son la **fuente única de verdad** observada. Los bloques y resúmenes son derivados y re-generables.

---

## Añadir un nuevo tipo de señal

1. Definir su `source` y los campos relevantes.
2. Si requiere columnas nuevas en `activity_events`, crear migración en Laravel.
3. Implementar collector en `tracker/collectors/<nombre>.py`, heredando `Collector`.
4. Registrarlo en `tracker/scheduler.py` con su intervalo.
5. Añadirlo a `config.example.yml` con `enabled: false` por defecto.
6. Documentar aquí.
7. Añadir reglas de scoring si aplica (ver [`09-context-scoring.md`](09-context-scoring.md)).
