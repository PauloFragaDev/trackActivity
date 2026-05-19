# dashboard · aplicación Laravel

UI web local que lee los eventos capturados por el [`tracker`](../tracker/README.md), los agrega en bloques, los puntúa y los presenta. Forma parte de [`trackActivity`](../README.md).

> Esta carpeta aún **no contiene código**, solo la documentación del componente. La implementación se desarrollará siguiendo el roadmap de [`../docs/14-mvp-roadmap.md`](../docs/14-mvp-roadmap.md).

---

## Documentación relacionada

| Tema | Documento |
|------|-----------|
| Arquitectura general | [`../docs/02-architecture.md`](../docs/02-architecture.md) |
| Diseño del dashboard | [`../docs/07-laravel-dashboard.md`](../docs/07-laravel-dashboard.md) |
| Esquema BBDD | [`../docs/05-database-schema.md`](../docs/05-database-schema.md) |
| Agregación en bloques | [`../docs/10-time-blocks.md`](../docs/10-time-blocks.md) |
| Algoritmo de scoring | [`../docs/09-context-scoring.md`](../docs/09-context-scoring.md) |
| Generación de resúmenes | [`../docs/11-summary-generation.md`](../docs/11-summary-generation.md) |
| Exportación | [`../docs/12-export-system.md`](../docs/12-export-system.md) |
| Configuración (`.env`) | [`../docs/04-configuration.md`](../docs/04-configuration.md) |
| Convenciones de código | [`../docs/13-development-guide.md`](../docs/13-development-guide.md) |
| Instalación | [`../docs/03-installation.md`](../docs/03-installation.md) |

---

## Estructura prevista

```
dashboard/
├── README.md                       # este archivo
├── composer.json
├── .env.example
├── app/
│   ├── Console/Commands/
│   ├── Filament/Resources/         # opcional
│   ├── Http/Controllers/
│   ├── Models/
│   └── Services/
│       ├── Aggregator.php
│       ├── Scorer.php
│       ├── MappingResolver.php
│       ├── SummaryGenerator.php
│       └── Exporter.php
├── database/
│   ├── migrations/                 # source of truth del schema
│   └── seeders/
├── resources/
│   └── views/
└── tests/
```

---

## Quick start (cuando el código exista)

```bash
composer install
cp .env.example .env
php artisan key:generate

# Editar .env y apuntar DB_DATABASE a la BBDD compartida con el tracker:
# DB_DATABASE=/home/<usuario>/.local/share/trackActivity/activity.db

php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000

# En otra terminal, scheduler (rebuilds + summaries periódicos):
php artisan schedule:work
```

---

## Comandos artisan principales

| Comando | Propósito |
|---------|-----------|
| `tracker:rebuild-blocks --since=...` | Re-agrega eventos en bloques. |
| `tracker:generate-summaries --since=...` | Regenera resúmenes. |
| `tracker:prune-events --older-than=...` | Limpia eventos antiguos. |
| `tracker:export --from=... --to=... --format=md` | Exporta. |
| `tracker:mapping:add --project=... --type=... --pattern=...` | Añade mapping. |
| `tracker:doctor` | Diagnóstico (BBDD, schema). |

Detalle en [`../docs/07-laravel-dashboard.md`](../docs/07-laravel-dashboard.md).
