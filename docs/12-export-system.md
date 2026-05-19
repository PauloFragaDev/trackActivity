# 12 · Sistema de exportación

Permite extraer el trabajo reconstruido para pegarlo en el timesheet corporativo o archivarlo.

---

## Formatos soportados

| Formato | Extensión | Uso típico |
|---------|-----------|-----------|
| Texto plano | `.txt` | Pegar directamente en formularios web |
| Markdown | `.md` | Notas, wikis, PR descriptions |
| CSV | `.csv` | Importar a Excel, herramientas internas |

---

## Acceso

### Desde la UI

Pantalla `/export` con:

- Rango de fechas (today / yesterday / this week / last week / custom).
- Filtro por proyecto (opcional, multi-select).
- Nivel mínimo de confianza (descartar bloques `low`).
- Incluir/excluir bloques `idle`.
- Formato de salida.

Acción: descarga directa del archivo.

### Desde CLI

```bash
php artisan tracker:export \
    --from="2026-05-13" \
    --to="2026-05-19" \
    --project=JASPER,YWL \
    --format=md \
    --min-confidence=medium \
    --output=~/Documents/timesheets/week-20.md
```

Si se omite `--output`, escribe a stdout.

---

## Estructura por formato

### TXT

Por sesión:

```
2026-05-19  09:00 - 10:30  JASPER  [Alta]
  Trabajo en JASPER sobre fix/dashboard-permissions. Cambios principales:
  Fix CRM access permissions. Issues relacionadas: #123.

2026-05-19  10:45 - 12:00  YWL  [Media]
  Mantenimiento del módulo de notificaciones.

...

— Totales —
JASPER:  4h 30m
YWL:     2h 15m
TDS:     0h 45m
TOTAL:   7h 30m
```

### Markdown

```markdown
# Timesheet · 2026-05-13 → 2026-05-19

## Lunes 2026-05-13

### 09:00 – 10:30 · `JASPER` _(Alta)_
Trabajo en JASPER sobre `fix/dashboard-permissions`. Cambios principales:
Fix CRM access permissions. Issues relacionadas: #123.

**Evidencia:**
- VSCode en `jasper-api`
- Git: 7 archivos modificados
- GitHub Issue #123

### 10:45 – 12:00 · `YWL` _(Media)_
Mantenimiento del módulo de notificaciones.

---

## Totales

| Proyecto | Horas |
|----------|-------|
| JASPER   | 4h 30m |
| YWL      | 2h 15m |
| TDS      | 0h 45m |
| **TOTAL**| **7h 30m** |
```

### CSV

Columnas:

```
date,start,end,duration_minutes,project_code,project_name,confidence,summary,evidence
2026-05-19,09:00,10:30,90,JASPER,Jasper,High,"Trabajo en JASPER ...","VSCode jasper-api; git; #123"
2026-05-19,10:45,12:00,75,YWL,YourWebLogic,Medium,"Mantenimiento ...","..."
```

- Delimitador: coma.
- Encoding: UTF-8 con BOM (para abrir bien en Excel en Windows).
- Línea de encabezado obligatoria.

---

## Parámetros comunes

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `from` / `to` | fecha (YYYY-MM-DD) | hoy | Rango inclusivo. |
| `project` | string o lista | todos | Filtra por `projects.code`. |
| `min-confidence` | `low`/`medium`/`high` | `low` | Excluye bloques bajo este umbral. |
| `include-idle` | bool | `false` | Si `true`, incluye bloques idle como huecos. |
| `group-by` | `session`/`block`/`project-day` | `session` | Nivel de agrupación. |
| `locale` | `es`/`en` | `TRACKER_SUMMARY_LOCALE` | Para encabezados. |
| `format` | `txt`/`md`/`csv` | `txt` | |
| `output` | path | stdout | Solo CLI. |

---

## Agrupación

### `session` (default)

Bloques contiguos del mismo proyecto se fusionan en una sesión, con un único resumen consolidado.

### `block`

Una línea por bloque de 15 min. Útil para análisis de granularidad fina.

### `project-day`

Un resumen agregado por (proyecto, día). Útil para timesheets que solo aceptan totales diarios:

```
2026-05-19  JASPER  4h 30m
  Síntesis del día: trabajo sobre fix/dashboard-permissions, ajustes de permisos CRM, revisión de Issue #123.

2026-05-19  YWL     2h 15m
  Mantenimiento del módulo de notificaciones; bump de dependencias.
```

La síntesis a nivel día se genera fusionando los summaries de las sesiones del proyecto en ese día (engine `template` o `llm`).

---

## Servicio interno

```php
interface Exporter
{
    public function buildReport(ExportQuery $query): Report;
    public function render(Report $report, string $format): string;
}

final class ExportQuery
{
    public function __construct(
        public CarbonPeriod $period,
        public array $projectCodes = [],
        public string $minConfidence = 'low',
        public bool $includeIdle = false,
        public string $groupBy = 'session',
        public string $locale = 'es',
    ) {}
}
```

---

## Casos típicos

### "Pegar en el timesheet del viernes"

```bash
php artisan tracker:export --from=$(date -d "monday" +%F) --to=$(date +%F) --format=txt
```

### "Pasar la semana pasada a Markdown"

UI → rango "Última semana" → formato Markdown → descargar.

### "Resumen mensual a Excel"

```bash
php artisan tracker:export --from=2026-05-01 --to=2026-05-31 --format=csv --group-by=project-day --output=mayo-2026.csv
```

---

## Lo que NO hace el exporter

- ❌ Subir a sistemas externos automáticamente.
- ❌ Editar bloques.
- ❌ Generar resúmenes que no existan previamente (los pide al `SummaryGenerator`).
- ❌ Re-puntuar (solo lee).

---

## Privacidad en exports

Los exports son archivos locales. El usuario decide qué hacer con ellos. No se envía nada a terceros automáticamente. Si un export se va a compartir, el usuario debe revisar manualmente que no contenga datos sensibles (asuntos de email, mensajes de commit, etc.).

Opción futura: flag `--redact-emails` y `--redact-commits` para sanitizar antes de exportar.
