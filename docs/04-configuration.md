# 04 · Configuración

`trackActivity` tiene dos archivos de configuración:

| Archivo | Componente | Propósito |
|---------|------------|-----------|
| `tracker/config.yml` | Daemon Python | Intervalos, paths, collectors activos |
| `dashboard/.env` | Laravel | BBDD, servidor, features opcionales |

Los **mappings de proyecto** y los **pesos de scoring** viven en la base de datos (gestionados desde Filament o por seeders), no en archivos planos. Esto permite editarlos en caliente sin reiniciar el daemon.

---

## 1. `tracker/config.yml`

Formato YAML. Las rutas con `~` se expanden al `$HOME` del usuario.

### Ejemplo completo

```yaml
# ──────────────────────────────────────────────
# Base de datos compartida con Laravel
# ──────────────────────────────────────────────
database:
  path: ~/.local/share/trackActivity/activity.db
  wal_mode: true
  busy_timeout_ms: 5000

# ──────────────────────────────────────────────
# Buffer / batch de escritura
# ──────────────────────────────────────────────
buffer:
  flush_every_seconds: 30
  max_pending_signals: 200

# ──────────────────────────────────────────────
# Logging
# ──────────────────────────────────────────────
logging:
  level: INFO          # DEBUG | INFO | WARNING | ERROR
  file: ~/.local/share/trackActivity/tracker.log
  rotate_mb: 10
  rotate_keep: 5

# ──────────────────────────────────────────────
# Collectors
# ──────────────────────────────────────────────
collectors:

  window:
    enabled: true
    interval_seconds: 15
    backend: xdotool       # xdotool | wmctrl
    capture_title: true
    capture_app_name: true

  git:
    enabled: true
    interval_seconds: 240   # 4 min
    repositories_paths:
      - ~/Projects
      - ~/Work
    max_depth: 3
    capture:
      branch: true
      modified_files_count: true
      latest_commit: true
      latest_commit_age_seconds: true

  browser:
    enabled: false
    interval_seconds: 30
    backend: chrome_window_title   # solo título de ventana, no scraping
    url_patterns:
      - "github.com"
      - "atlassian.net"

  thunderbird:
    enabled: false
    interval_seconds: 60
    capture_subject_in_title: true

  idle:
    enabled: true
    interval_seconds: 30
    threshold_seconds: 180   # >3 min sin input = idle
```

### Opciones por sección

#### `database`

| Clave | Default | Descripción |
|-------|---------|-------------|
| `path` | `~/.local/share/trackActivity/activity.db` | Ruta absoluta a la BBDD SQLite. Debe coincidir con `DB_DATABASE` del `.env` de Laravel. |
| `wal_mode` | `true` | Habilita `PRAGMA journal_mode=WAL`. Imprescindible para lectura concurrente. |
| `busy_timeout_ms` | `5000` | Espera ante locks transitorios. |

#### `buffer`

| Clave | Default | Descripción |
|-------|---------|-------------|
| `flush_every_seconds` | `30` | Periodicidad de descarga del buffer a SQLite. |
| `max_pending_signals` | `200` | Si se alcanza antes del intervalo, fuerza un flush. |

#### `logging`

Logging rotado a archivo + stdout cuando se ejecuta con `--foreground`.

#### `collectors.<nombre>`

Cada collector tiene **al menos** las claves `enabled` (bool) e `interval_seconds` (int). Opciones específicas se describen en [`08-activity-signals.md`](08-activity-signals.md) y [`06-python-daemon.md`](06-python-daemon.md).

---

## 2. `dashboard/.env`

Variables relevantes (las no listadas son las habituales de Laravel).

```env
APP_NAME=trackActivity
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Base de datos: misma que el daemon
DB_CONNECTION=sqlite
DB_DATABASE=/home/<usuario>/.local/share/trackActivity/activity.db
DB_FOREIGN_KEYS=true

# Bloques de tiempo
TRACKER_BLOCK_MINUTES=15
TRACKER_IDLE_GAP_MINUTES=5

# Scoring
TRACKER_CONFIDENCE_HIGH=0.65
TRACKER_CONFIDENCE_MEDIUM=0.35

# Summary
TRACKER_SUMMARY_ENGINE=template   # template | llm
TRACKER_SUMMARY_LOCALE=es

# Filament admin (opcional)
FILAMENT_ENABLED=true
```

---

## 3. Mappings de proyecto (en BBDD)

Los mappings asocian artefactos del SO con un proyecto lógico. Tipos soportados:

| Tipo | Coincide con | Ejemplo |
|------|--------------|---------|
| `repository` | Nombre exacto de repo Git | `jasper-api` → `JASPER` |
| `folder` | Ruta absoluta o prefijo | `~/Projects/jasper-api` → `JASPER` |
| `url_pattern` | Substring/regex en URL | `github.com/company/jasper` → `JASPER` |
| `email_subject` | Substring/regex en asunto | contiene `JASPER` → `JASPER` |
| `window_title` | Substring en título de ventana | contiene `jasper-api` → `JASPER` |

Esquema de la tabla `project_mappings` en [`05-database-schema.md`](05-database-schema.md).

### Edición

- **Desde Filament**: panel `Project mappings`.
- **Desde CLI Laravel**:

```bash
php artisan tracker:mapping:add \
    --project=JASPER \
    --type=repository \
    --pattern=jasper-api
```

- **Desde seeders** (`database/seeders/MappingsSeeder.php`):

```php
ProjectMapping::create([
    'project_id' => $jasper->id,
    'type'       => 'repository',
    'pattern'    => 'jasper-api',
    'weight_bonus' => 0,
]);
```

---

## 4. Pesos de scoring (en BBDD)

La tabla `scoring_rules` define los pesos. Ejemplo de carga inicial:

| Señal | Peso |
|-------|------|
| VSCode dentro de repo del proyecto | +5 |
| Terminal dentro de repo del proyecto | +4 |
| Git: archivos modificados | +5 |
| Browser: URL que matchea | +3 |
| Thunderbird: asunto que matchea | +2 |
| Ventana con título que matchea | +2 |

Detalle del algoritmo en [`09-context-scoring.md`](09-context-scoring.md).

---

## 5. Recargas y reinicios

| Cambio | Requiere reiniciar daemon | Requiere reiniciar Laravel |
|--------|--------------------------|---------------------------|
| `tracker/config.yml` | ✅ Sí (`systemctl --user restart trackactivity`) | No |
| Mappings en BBDD | No | No (lectura en cada request) |
| `scoring_rules` en BBDD | No | No |
| `dashboard/.env` | No | ✅ Sí (`php artisan config:clear`) |
| Migraciones | No (esquema compatible) | ✅ Sí (`php artisan migrate`) |

---

## 6. Convención de paths XDG

| Tipo | Ubicación |
|------|-----------|
| Configuración del daemon | `tracker/config.yml` (junto al código) o `~/.config/trackActivity/config.yml` |
| Base de datos | `~/.local/share/trackActivity/activity.db` |
| Logs | `~/.local/share/trackActivity/tracker.log` |
| Servicio systemd | `~/.config/systemd/user/trackactivity.service` |
