# tracker · daemon Python

Componente que captura señales de actividad del SO y las persiste en SQLite. Forma parte de [`trackActivity`](../README.md).

---

## Documentación relacionada

| Tema | Documento |
|------|-----------|
| Arquitectura general | [`../docs/02-architecture.md`](../docs/02-architecture.md) |
| Diseño del daemon | [`../docs/06-python-daemon.md`](../docs/06-python-daemon.md) |
| Catálogo de señales | [`../docs/08-activity-signals.md`](../docs/08-activity-signals.md) |
| Configuración | [`../docs/04-configuration.md`](../docs/04-configuration.md) |
| Esquema BBDD | [`../docs/05-database-schema.md`](../docs/05-database-schema.md) |
| Convenciones de código | [`../docs/13-development-guide.md`](../docs/13-development-guide.md) |
| Instalación | [`../docs/03-installation.md`](../docs/03-installation.md) |

---

## Estructura

```
tracker/
├── README.md
├── pyproject.toml
├── requirements.txt
├── config.example.yml
├── scripts/
│   └── trackactivity.service
├── src/tracker/
│   ├── cli.py            # entrypoint typer
│   ├── config.py         # carga y valida config.yml (pydantic)
│   ├── scheduler.py      # APScheduler + manejo de fallos por collector
│   ├── buffer.py         # deque + flush por threshold/timer
│   ├── storage.py        # SQLite (WAL), INSERT batch, upsert repos
│   ├── models.py         # Signal, WindowInfo
│   ├── logging_setup.py
│   ├── collectors/
│   │   ├── base.py       # contrato Collector
│   │   ├── window.py     # M1: ventana activa con dedupe
│   │   └── idle.py       # M1: transiciones de inactividad
│   └── utils/
│       ├── x11.py        # xdotool + Xlib screensaver
│       └── paths.py      # XDG
└── tests/
    └── test_buffer.py
```

> Collectors `git`, `browser` y `thunderbird` se añaden en los hitos M2+ (ver [`../docs/14-mvp-roadmap.md`](../docs/14-mvp-roadmap.md)).

---

## Quick start

### Prerequisitos del SO

```bash
sudo apt install -y xdotool python3.11 python3.11-venv
```

### Setup (fish shell)

```fish
cd /var/www/html/trackActivity/tracker

python3.11 -m venv .venv
source .venv/bin/activate.fish

pip install -r requirements.txt
pip install -e .                       # instala el paquete tracker editable

cp config.example.yml config.yml
# Editar config.yml: ajustar database.path a tu ruta real
```

### Setup (bash/zsh)

```bash
cd /var/www/html/trackActivity/tracker

python3.11 -m venv .venv
source .venv/bin/activate

pip install -r requirements.txt
pip install -e .

cp config.example.yml config.yml
```

### Ejecutar

Tras `pip install -e .` tienes dos formas equivalentes:

```fish
# Forma corta (entry point declarado en pyproject.toml)
tracker doctor
tracker run --foreground --log-level=DEBUG

# Forma larga (siempre funciona)
python -m tracker.cli doctor
python -m tracker.cli run --foreground --log-level=DEBUG
```

Si por alguna razón **no instalas el paquete** (`pip install -e .`), el módulo no es importable y necesitas el workaround:

```fish
env PYTHONPATH=src python -m tracker.cli doctor
```

> Recomendado: hacer el `pip install -e .` y olvidarte del `PYTHONPATH`.

---

## Comandos disponibles

| Comando | Descripción |
|---------|-------------|
| `tracker run [--foreground] [--log-level=DEBUG] [--config PATH]` | Arranca el daemon. |
| `tracker doctor [--config PATH]` | Verifica xdotool, ruta y schema de la BBDD. |
| `tracker collect <window\|idle> [--once] [--dry-run]` | Ejecuta un collector aislado para debugging. |
| `tracker version` | Versión instalada. |

---

## Servicio systemd

Cuando el setup en foreground funcione, registra como servicio de usuario:

```fish
mkdir -p ~/.config/systemd/user
cp scripts/trackactivity.service ~/.config/systemd/user/
# Editar el unit file si tu ruta a python o al repo difiere

systemctl --user daemon-reload
systemctl --user enable --now trackactivity.service
systemctl --user status trackactivity.service
journalctl --user -u trackactivity.service -f
```

Detalle completo en [`../docs/03-installation.md`](../docs/03-installation.md).

---

## Tests

```fish
pip install -e ".[dev]"
pytest -q
```

`tests/test_buffer.py` es un smoke test de buffer + storage en SQLite `:memory:`, no requiere X11.
