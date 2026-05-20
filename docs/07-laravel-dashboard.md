# 07 · Dashboard Laravel (`dashboard/`)

Aplicación Laravel 11 que lee la BBDD SQLite escrita por el daemon, agrega los eventos en bloques, los puntúa, genera resúmenes y los presenta en una UI minimalista (Blade + Tailwind), con CRUD propio de proyectos y mappings.

---

## Estructura propuesta

Sigue la convención estándar de Laravel, con las siguientes adiciones específicas:

```
dashboard/
├── composer.json
├── .env.example
├── phpunit.xml
├── app/
│   ├── Console/Commands/
│   │   ├── RebuildBlocksCommand.php        # tracker:rebuild-blocks
│   │   ├── GenerateSummariesCommand.php    # tracker:generate-summaries
│   │   ├── ExportCommand.php               # tracker:export
│   │   ├── PruneEventsCommand.php          # tracker:prune-events
│   │   └── DoctorCommand.php               # tracker:doctor
│   ├── Http/Controllers/
│   │   ├── TimelineController.php          # day / week
│   │   ├── CalendarController.php
│   │   ├── TimeBlockController.php         # edición manual de sesiones
│   │   ├── ProjectController.php           # CRUD proyectos + mappings
│   │   ├── ExportController.php
│   │   └── HelpController.php
│   ├── Models/
│   │   ├── Project.php
│   │   ├── Repository.php
│   │   ├── ProjectMapping.php
│   │   ├── ScoringRule.php
│   │   ├── ActivityEvent.php
│   │   ├── TimeBlock.php
│   │   ├── TimeBlockEvidence.php
│   │   └── GeneratedSummary.php
│   ├── Providers/AppServiceProvider.php
│   └── Services/
│       ├── Aggregator.php                  # eventos → time_blocks
│       ├── SessionBuilder.php              # time_blocks → sesiones (UI)
│       ├── Scoring/
│       │   ├── Scorer.php
│       │   ├── MappingResolver.php
│       │   └── ScoringResult.php
│       ├── Summaries/
│       │   ├── SummaryGenerator.php
│       │   └── EvidenceExtractor.php
│       └── Export/
│           ├── Exporter.php
│           ├── ExportQuery.php
│           ├── Report.php
│           └── Renderers/                  # Txt / Markdown / Csv
├── database/
│   ├── migrations/                         # 9 tablas (2026_01_01_0000NN_*)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── ProjectsSeeder.php
│       ├── ScoringRulesSeeder.php
│       └── MappingsSeeder.php
├── resources/
│   ├── views/
│   │   ├── layouts/app.blade.php
│   │   ├── timeline/{day,week}.blade.php
│   │   ├── calendar/index.blade.php
│   │   ├── projects/{index,edit}.blade.php
│   │   ├── export/form.blade.php
│   │   └── help/index.blade.php
│   ├── css/app.css
│   └── js/app.js
├── routes/
│   ├── web.php
│   └── console.php
└── tests/
    ├── Feature/                            # SessionBuilder, TimeBlockController, scoring…
    └── Unit/
```

---

## Modelos Eloquent

Todos los modelos mapean directamente a las tablas de [`05-database-schema.md`](05-database-schema.md).

Convenciones:

- **Sin timestamps** en `activity_events` (usar `occurred_at`).
- `metadata`/`scoring_snapshot` como `casts => 'array'`.
- Relaciones explícitas en cada modelo (`hasMany`, `belongsTo`).

Ejemplo `TimeBlock`:

```php
class TimeBlock extends Model
{
    protected $fillable = ['starts_at','ends_at','dominant_project_id','confidence','status','scoring_snapshot'];
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'confidence'=> 'float',
        'scoring_snapshot' => 'array',
    ];
    public function project(): BelongsTo { return $this->belongsTo(Project::class, 'dominant_project_id'); }
    public function summary(): HasOne    { return $this->hasOne(GeneratedSummary::class); }
    public function evidence(): HasMany  { return $this->hasMany(TimeBlockEvidence::class); }
}
```

---

## Servicios

### `Aggregator`

Agrupa `activity_events` en `time_blocks` de 15 minutos. Detalle en [`10-time-blocks.md`](10-time-blocks.md).

```php
interface Aggregator
{
    public function rebuildRange(CarbonPeriod $period): int;  // nº de bloques generados
    public function rebuildDay(Carbon $day): int;
}
```

### `Scorer`

Aplica `scoring_rules` y `project_mappings` a la evidencia de un bloque para determinar el proyecto dominante. Detalle en [`09-context-scoring.md`](09-context-scoring.md).

```php
interface Scorer
{
    public function scoreBlock(TimeBlock $block): ScoringResult;
}

final class ScoringResult
{
    public function __construct(
        public readonly ?int $dominantProjectId,
        public readonly float $confidence,
        public readonly array $perProjectScores,  // ['JASPER' => 12, 'YWL' => 3]
        public readonly array $evidence,          // [['event_id'=>1,'weight'=>5,'note'=>'...']]
    ) {}
}
```

### `MappingResolver`

Dado un `ActivityEvent`, devuelve los proyectos candidatos según los `project_mappings` activos. Implementa cache por request.

### `SummaryGenerator`

Convierte la evidencia de un bloque en un texto humano. Engine `template` para MVP (ver [`11-summary-generation.md`](11-summary-generation.md)).

### `Exporter`

Exporta sesiones (bloques agrupados) en TXT / Markdown / CSV. Ver [`12-export-system.md`](12-export-system.md).

---

## Rutas (`routes/web.php`)

```php
Route::get('/',                    [TimelineController::class, 'today'])->name('today');
Route::get('/day/{date}',          [TimelineController::class, 'day'])->name('day');
Route::get('/week/{week}',         [TimelineController::class, 'week'])->name('week');
Route::get('/calendar',            [CalendarController::class, 'index'])->name('calendar');

Route::patch('/blocks/{block}',    [TimeBlockController::class, 'update'])->name('blocks.update');
Route::post('/blocks/merge',       [TimeBlockController::class, 'merge'])->name('blocks.merge');
Route::post('/blocks/{block}/split', [TimeBlockController::class, 'split'])->name('blocks.split');
Route::post('/blocks/{block}/regenerate-summary',
                                   [TimeBlockController::class, 'regenerateSummary']);

Route::get('/export',              [ExportController::class, 'form'])->name('export');
Route::post('/export',             [ExportController::class, 'download']);
```

> No hay rutas API públicas. Todo es interno y single-user.

---

## Comandos artisan

| Comando | Propósito |
|---------|-----------|
| `tracker:rebuild-blocks --since="..." --until="..."` | Re-agrega eventos en bloques. |
| `tracker:generate-summaries --since="..."` | Regenera resúmenes (respeta `edited_by_user`). |
| `tracker:prune-events --older-than="90 days"` | Borra events viejos. |
| `tracker:export --from=... --to=... --format=md` | Export por CLI. |
| `tracker:mapping:add --project=... --type=... --pattern=...` | Añade un mapping rápido. |
| `tracker:doctor` | Verifica conexión BBDD, integridad de schema, ruta a SQLite. |

Programación en `routes/console.php`:

```php
Schedule::command('tracker:rebuild-blocks --since="2 hours ago"')->everyFifteenMinutes();
Schedule::command('tracker:generate-summaries --since="2 hours ago"')->everyFifteenMinutes();
Schedule::command('tracker:prune-events --older-than="90 days"')->dailyAt('03:00');
```

Activar el scheduler (cron o `php artisan schedule:work` en dev).

---

## Vistas y UI

Stack: **Blade + Tailwind + Alpine.js** (lo que ya viene con Laravel Breeze sin React/Vue).

### Pantallas principales

1. **Today / Day view** (`/`, `/day/{date}`)
   - Lista vertical de sesiones (bloques fusionados por proyecto contiguo).
   - Cada sesión: hora inicio–fin, badge de proyecto (color), confianza, summary, botón "ver evidencia".
   - Botones inline: editar proyecto, regenerar resumen, dividir, fusionar.
2. **Week view** (`/week/{week}`)
   - Grid 7×24h con bloques coloreados por proyecto.
3. **Calendar** (`/calendar`)
   - Vista mensual con totales por proyecto/día.
4. **Export** (`/export`)
   - Formulario con rango de fechas, proyecto, formato.

### Estilo

- Tema dark por defecto, light opcional vía `prefers-color-scheme`.
- Tipografía mono para datos técnicos (hashes, ramas, rutas).
- Densidad alta, sin ornamento. Inspiración: GitHub timeline + plain text editor.

### Componentes Blade clave

- `<x-timeline.day :date="..." />`
- `<x-timeline.block-card :block="..." />`
- `<x-timeline.evidence-list :events="..." />`
- `<x-calendar.week :start="..." />`
- `<x-project-badge :project="..." />`

---

## Gestión del catálogo (proyectos y mappings)

El catálogo se administra con un CRUD propio en Blade, sin dependencias
de terceros:

| Ruta | Acciones |
|------|----------|
| `/projects` | Lista de proyectos con su número de mappings. |
| `/projects/create`, `/projects/{p}/edit` | Alta y edición de proyecto (code, name, color, description). |
| `/projects/{p}/edit` | Gestión inline de los mappings del proyecto: alta, baja y toggle activo. |

Las `scoring_rules` (pesos) no tienen UI: se cargan vía `ScoringRulesSeeder`
y se ajustan por SQL si hace falta. Un panel admin (p. ej. Filament) para
editarlas queda fuera del MVP — ver [`14-mvp-roadmap.md`](14-mvp-roadmap.md).

---

## Edición manual de sesiones

La edición se hace a nivel de **sesión** (el grupo de `time_blocks`
contiguos que la vista de Día muestra como una sola fila), no de bloque
individual. `TimeBlockController` opera sobre el array de `block_ids`
que `SessionBuilder` adjunta a cada sesión.

### Reasignar proyecto y/o resumen

`PATCH /blocks` (`blocks.update`) con:

| Campo | Tipo | Notas |
|-------|------|-------|
| `block_ids[]` | int[] | IDs de todos los bloques de la sesión. Requerido. |
| `project_id` | int\|null | Proyecto destino; `null` = sin proyecto. |
| `summary_text` | string\|null | Máx. 500 chars. Vacío = no toca el resumen. |
| `date` | `Y-m-d` | Día al que redirige tras guardar. |

Efecto sobre cada bloque no-idle de la sesión: `status = 'edited'`,
`confidence = 1.0`, `dominant_project_id` reasignado. Si llega
`summary_text`, se hace `updateOrCreate` del `generated_summary` con
`edited_by_user = true` (conservando el `engine` original, o
`manual` si no existía). Los bloques `idle` no se reasignan a proyecto.

Un bloque `edited` **no** se recalcula en los rebuilds salvo
`tracker:rebuild-blocks --force-edited`.

### Volver a automático

`PATCH /blocks/reset` (`blocks.reset`) con `block_ids[]` y `date`.
Devuelve los bloques a `status = 'auto'` y marca sus resúmenes con
`edited_by_user = false`. El siguiente rebuild los recalcula desde cero.

### Fusionar / dividir bloques

Fuera del alcance del MVP. Las constantes `STATUS_MERGED` / `STATUS_SPLIT`
están reservadas en el modelo y el `Aggregator` ya las trata como
no-recalculables, pero no hay endpoints `merge` / `split`. La fusión
*visual* de bloques contiguos del mismo proyecto ya la hace
`SessionBuilder` al agrupar en sesiones.

---

## Concurrencia con el daemon

El daemon escribe en `activity_events`. Laravel **solo lee** esa tabla (los rebuilds de bloques son lecturas + escrituras en `time_blocks`, no en `activity_events`). Con WAL no hay bloqueos.

Si por algún motivo el dashboard quiere borrar eventos viejos (`PruneEventsCommand`), lo hace en transacciones cortas (chunks de 1000 filas).

---

## Testing

- **Feature tests**: rutas principales (smoke), edición de bloques, export.
- **Unit tests**: `Aggregator`, `Scorer`, `MappingResolver`, `SummaryGenerator`.
- Base de datos de test: SQLite en memoria.
- Fixtures: helpers en `tests/Helpers/SignalFactory.php` para fabricar `ActivityEvent` realistas.

---

## Lo que NO incluye el dashboard

- ❌ Sistema de login (single-user).
- ❌ Multi-tenant.
- ❌ Notificaciones, websockets.
- ❌ Integración con Jira/GitHub vía API (queda fuera de MVP; los datos vienen del título de ventana del navegador).
