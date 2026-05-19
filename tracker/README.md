# tracker · daemon Python

Componente que captura señales de actividad del SO y las persiste en SQLite. Forma parte de [`trackActivity`](../README.md).

> Esta carpeta aún **no contiene código**, solo la documentación del componente. La implementación se desarrollará siguiendo el roadmap de [`../docs/14-mvp-roadmap.md`](../docs/14-mvp-roadmap.md).

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

## Estructura prevista

```
tracker/
├── README.md                  # este archivo
├── pyproject.toml
├── requirements.txt
├── config.example.yml
├── scripts/
│   └── trackactivity.service  # unit systemd
└── src/tracker/
    ├── cli.py
    ├── config.py
    ├── scheduler.py
    ├── buffer.py
    ├── storage.py
    ├── models.py
    ├── logging_setup.py
    ├── collectors/
    │   ├── base.py
    │   ├── window.py
    │   ├── git.py
    │   ├── browser.py
    │   ├── thunderbird.py
    │   └── idle.py
    └── utils/
        ├── x11.py
        ├── git_utils.py
        └── paths.py
```

---

## Quick start (cuando el código exista)

```bash
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp config.example.yml config.yml
# Editar config.yml (paths de repos, BBDD, etc.)
python -m tracker.cli doctor
python -m tracker.cli run --foreground --log-level=DEBUG
```

Para servicio systemd, ver [`../docs/03-installation.md`](../docs/03-installation.md).
