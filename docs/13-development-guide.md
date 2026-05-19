# 13 · Guía de desarrollo

Convenciones, flujo de trabajo y cómo extender la aplicación.

---

## Setup local rápido

Asumiendo que ya seguiste [`03-installation.md`](03-installation.md) hasta el paso 5:

```bash
# Daemon en modo desarrollo (no como servicio)
cd tracker
source .venv/bin/activate
python -m tracker.cli run --foreground --log-level=DEBUG

# Dashboard
cd ../dashboard
php artisan serve
npm run dev      # hot reload de Tailwind/Vite

# Scheduler de Laravel (rebuilds y resúmenes periódicos)
php artisan schedule:work
```

Tres terminales, tres procesos. Cuando todo funcione localmente, mueve el daemon a systemd.

---

## Estructura del repositorio

```
trackActivity/
├── README.md
├── LICENSE
├── docs/                  # documentación (este directorio)
├── tracker/               # daemon Python
│   ├── src/tracker/
│   └── tests/
└── dashboard/             # app Laravel
    ├── app/
    └── tests/
```

Cada subproyecto tiene su propio `README.md` con instrucciones específicas.

---

## Convenciones de código

### Python (`tracker/`)

- **Versión**: 3.11+.
- **Estilo**: `black` + `isort` (line length 100).
- **Linter**: `ruff`.
- **Typing**: anotaciones obligatorias en funciones públicas. `mypy --strict` deseable.
- **Docstrings**: estilo Google, solo en funciones/clases públicas. No en getters triviales.
- **Imports**: agrupados (stdlib, third-party, local). `isort` lo aplica.

Estructura típica de un módulo collector:

```python
"""Collector para ventana activa."""
from __future__ import annotations

import logging
from typing import Iterable

from tracker.collectors.base import Collector
from tracker.models import Signal
from tracker.utils.x11 import get_active_window

logger = logging.getLogger(__name__)


class WindowCollector(Collector):
    name = "window"

    def __init__(self, interval_seconds: int, backend: str = "xdotool"):
        self.interval_seconds = interval_seconds
        self.backend = backend
        self._last_signature: tuple[str, str] | None = None

    def collect(self) -> Iterable[Signal]:
        win = get_active_window(backend=self.backend)
        sig = (win.app, win.title)
        if sig == self._last_signature:
            return []
        self._last_signature = sig
        return [Signal(source="window", app=win.app, title=win.title, ...)]
```

### PHP (`dashboard/`)

- **Versión**: PHP 8.2+, Laravel 11.
- **Estilo**: `pint` (preset Laravel).
- **Static analysis**: `phpstan` nivel 6+.
- **Naming**:
  - Modelos: singular `PascalCase` (`TimeBlock`).
  - Servicios: PascalCase + sufijo descriptivo (`SummaryGenerator`, `Exporter`).
  - Comandos: kebab-case con namespace `tracker:*`.
- **Sin Facades en servicios**: inyectar dependencias por constructor.
- **Eloquent**: relaciones tipadas; no usar `__get` mágico en lógica crítica.

---

## Testing

### Python

- Framework: `pytest`.
- Coverage objetivo: 70% (foco en `collectors`, `buffer`, `storage`).
- Mocking: `pytest-mock` para `subprocess` y para Xlib.
- BBDD: `:memory:`.

```bash
cd tracker
pytest -q
pytest --cov=tracker --cov-report=term-missing
```

### PHP

- Framework: `pest` o `phpunit` (preferencia: Pest).
- Cobertura objetivo: 70% en servicios (`Aggregator`, `Scorer`, `SummaryGenerator`, `Exporter`).
- Helpers de fixtures en `tests/Helpers/SignalFactory.php`.

```bash
cd dashboard
php artisan test
php artisan test --coverage --min=70
```

### Datos sintéticos

Para probar end-to-end sin tracker real, hay un seeder:

```bash
php artisan db:seed --class=SyntheticDaySeeder
```

Genera ~500 `activity_events` de un día simulado con dos proyectos.

---

## Flujo Git

- Branch `main` siempre estable.
- Branches feature: `feat/<nombre>`, `fix/<nombre>`, `docs/<nombre>`.
- Commits convencionales (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `test:`).
- Tag de versiones con `v0.1.0`, `v0.2.0`, etc.

Como es uso personal, no hay PR review formal; aún así se recomienda commits atómicos y mensajes descriptivos.

---

## Cómo añadir...

### ...un nuevo collector

1. Crear `tracker/src/tracker/collectors/<nombre>.py` heredando `Collector`.
2. Registrarlo en `tracker/src/tracker/scheduler.py`.
3. Añadirlo a `config.example.yml` con `enabled: false` por defecto.
4. Si emite campos nuevos: migración en `dashboard/database/migrations/`.
5. Documentar en [`08-activity-signals.md`](08-activity-signals.md).
6. Tests unitarios mínimos.

### ...una nueva regla de scoring

1. Insertar fila en `scoring_rules` (vía seeder o Filament).
2. Implementar detección en `MappingResolver::resolveContributions()`.
3. Documentar en [`09-context-scoring.md`](09-context-scoring.md).
4. Rebuild de prueba: `php artisan tracker:rebuild-blocks --since="today"`.

### ...un nuevo tipo de mapping

1. Añadir valor al CHECK constraint de `project_mappings.type` (migración nueva, no editar la histórica).
2. Implementar matching en `MappingResolver`.
3. Añadir resource en Filament si existe (`ProjectMappingResource`).
4. Documentar en [`04-configuration.md`](04-configuration.md).

### ...un nuevo formato de export

1. Crear renderer en `app/Services/Export/Renderers/<Formato>Renderer.php` implementando una interfaz `Renderer`.
2. Registrarlo en `Exporter::render()` (switch o map).
3. Documentar en [`12-export-system.md`](12-export-system.md).

---

## Versionado del esquema

El esquema vive en migraciones de Laravel. Cualquier cambio:

1. Migración nueva (nunca editar una existente que ya esté commiteada).
2. Bump opcional de constante `SCHEMA_VERSION` en `tracker/src/tracker/storage.py`.
3. El comando `tracker doctor` valida que el daemon es compatible con el schema actual antes de arrancar.
4. Anotar el cambio en `CHANGELOG.md`.

---

## Debugging

### El daemon no captura nada

```bash
# Test puntual sin escribir BBDD
python -m tracker.cli collect window --once --dry-run
python -m tracker.cli collect git --once --dry-run

# Verificar dependencias y schema
python -m tracker.cli doctor
```

### El dashboard no muestra datos recientes

```bash
# ¿Hay events en BBDD?
sqlite3 ~/.local/share/trackActivity/activity.db \
    "SELECT source, COUNT(*) FROM activity_events WHERE occurred_at > datetime('now','-1 hour') GROUP BY source;"

# Forzar rebuild
php artisan tracker:rebuild-blocks --since="2 hours ago"

# Mirar bloques crudos
sqlite3 ~/.local/share/trackActivity/activity.db \
    "SELECT starts_at, dominant_project_id, confidence, status FROM time_blocks ORDER BY starts_at DESC LIMIT 20;"
```

### Logs

| Componente | Ubicación |
|------------|-----------|
| Daemon (systemd) | `journalctl --user -u trackactivity.service -f` |
| Daemon (foreground) | stdout + `~/.local/share/trackActivity/tracker.log` |
| Laravel | `dashboard/storage/logs/laravel.log` |
| Scheduler | salida de `php artisan schedule:work` |

---

## Performance budget

Si alguno de estos límites se rompe consistentemente, considéralo un bug.

| Componente | Métrica | Límite |
|------------|---------|--------|
| Daemon | CPU promedio | < 1% de un core |
| Daemon | RSS | < 50 MB |
| Daemon | Latencia de captura ventana | < 50 ms |
| Dashboard | Tiempo de render `/day/today` | < 300 ms con 1k events del día |
| Rebuild | 1 día (~3k events) | < 1 s |
| BBDD | Tamaño / día con uso típico | < 5 MB |

---

## Dependencias externas

Lista mínima y justificada. Antes de añadir una:

1. ¿Está en stdlib?
2. ¿Una utilidad CLI ya disponible en el SO la sustituye?
3. ¿Justifica el coste de mantenimiento y la superficie de seguridad?

Una dependencia descartada es siempre más barata que una mantenida.
