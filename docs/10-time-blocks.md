# 10 · Bloques de tiempo y sesiones

El sistema agrupa señales crudas en **bloques de tiempo** de tamaño fijo (por defecto 15 min). En la UI, los bloques contiguos del mismo proyecto se presentan fusionados como **sesiones**.

> El bloque es una unidad de cómputo. La sesión es una unidad de presentación.

---

## Por qué 15 minutos

| Razón | Detalle |
|-------|---------|
| Granularidad práctica del timesheet | La mayoría de timesheets corporativos aceptan 0.25h como unidad mínima. |
| Suficiente para que se acumule contexto | Tiempo razonable para que VSCode + Terminal + Git produzcan varias señales. |
| Pequeño para detectar cambios reales de proyecto | Cambios de proyecto rara vez son < 15 min sostenidos. |
| Alineable a un grid | 00, 15, 30, 45 simplifica la UI y los exports. |

Configurable vía `TRACKER_BLOCK_MINUTES`. Se recomienda no bajar de 5.

---

## Alineación al grid

Los bloques se alinean a múltiplos del intervalo dentro de la hora, en UTC para el almacenamiento y en zona local para la visualización.

```
09:00:00 → 09:15:00   block #1
09:15:00 → 09:30:00   block #2
09:30:00 → 09:45:00   block #3
```

Esto da bloques **estables y reproducibles**: dos rebuilds sobre la misma ventana producen los mismos `time_blocks.starts_at`.

---

## Algoritmo de agregación

Implementado en `App\Services\Aggregator`.

```text
function rebuildRange(start, end):
    align start and end to block grid
    for each block_start in range(start, end, BLOCK_MINUTES):
        block_end = block_start + BLOCK_MINUTES
        events    = ActivityEvent::between(block_start, block_end)

        if events is empty AND no idle signal:
            ensure_empty_block(block_start, block_end, status='auto', project=null)
            continue

        if block_is_idle(events):
            upsert_block(block_start, block_end, status='idle')
            continue

        existing = TimeBlock::find_by_start(block_start)
        if existing AND existing.status IN ('edited', 'merged', 'split'):
            if NOT force_edited: skip
            else: replace

        result = scorer.scoreBlock(events)
        upsert_block(block_start, block_end,
                     dominant_project_id = result.dominantProjectId,
                     confidence          = result.confidence,
                     scoring_snapshot    = result.perProjectScores,
                     status              = 'auto')
        write_evidence(block, result.evidence)
```

### Reglas

1. **Idempotente**: ejecutar varias veces produce el mismo resultado.
2. **No destructivo** sobre bloques editados manualmente (`status` ∈ {`edited`, `merged`, `split`}).
3. **Auditoría**: cada rebuild registra la evidencia en `time_block_evidence` y un snapshot en `time_blocks.scoring_snapshot`.

---

## Sesiones (vista de presentación)

Una **sesión** es la unión visual de N bloques contiguos con el mismo `dominant_project_id`. Se calcula en lectura, no se persiste.

### Reglas de fusión visual

- Mismo `dominant_project_id`.
- `next.starts_at == current.ends_at`.
- `next.status != 'idle'`.
- Tolera un único hueco de hasta `TRACKER_IDLE_GAP_MINUTES` (default 5) sin romper la sesión (caso: micro-pausa de café).

### Ejemplo

```
09:00–09:15   JASPER   conf=0.78
09:15–09:30   JASPER   conf=0.82
09:30–09:45   JASPER   conf=0.71
09:45–10:00   IDLE
10:00–10:15   JASPER   conf=0.68
10:15–10:30   YWL      conf=0.55
```

Sesiones presentadas:

```
Sesión A — JASPER — 09:00 → 09:45 (conf media 0.77)
[hueco idle 15 min — supera gap, rompe sesión]
Sesión B — JASPER — 10:00 → 10:15 (conf 0.68)
Sesión C — YWL    — 10:15 → 10:30 (conf 0.55)
```

Si el hueco hubiese sido de 5 min, A absorbería 09:45–09:50 y B se uniría a A.

---

## Operaciones de usuario sobre bloques

### Merge

Une N bloques contiguos en uno. Requisitos:

- Bloques son contiguos.
- Pertenecen al mismo proyecto (o el usuario está aceptando reasignar todo el rango).

Efecto:

- Se borran los bloques originales.
- Se crea un nuevo `time_block` con `starts_at = min(starts)`, `ends_at = max(ends)`, `status = 'merged'`.
- La evidencia se reasigna al nuevo bloque.
- El resumen se regenera (a menos que el usuario lo edite explícitamente después).

### Split

Divide un bloque en dos por un timestamp.

- Crea dos bloques `[starts_at, split_at]` y `[split_at, ends_at]`.
- Redistribuye `time_block_evidence` según `occurred_at` del event subyacente.
- Status = `split` en ambos.
- Cada uno re-puntuado individualmente.

### Reasignar proyecto

Solo cambia `dominant_project_id`. Status pasa a `edited`. No se altera evidencia.

### Editar resumen

Setea `generated_summaries.edited_by_user = true`. Inmune a regeneración automática.

---

## Bloques vacíos

Hay dos casos:

| Caso | `status` | `project_id` | Visualización |
|------|----------|--------------|---------------|
| Sin eventos, sin idle (PC apagada) | `auto` | `NULL` | Bloque tenue, etiqueta "sin actividad" |
| Idle dominante | `idle` | `NULL` | Bloque gris, etiqueta "idle" |

No se persisten bloques completamente vacíos por defecto (ahorran espacio). Solo se materializan cuando hay evidencia o cuando se entra en un día con actividad.

---

## Rebuilds programados

```php
// routes/console.php
Schedule::command('tracker:rebuild-blocks --since="2 hours ago"')->everyFifteenMinutes();
```

- Cada 15 min, se recomputan los bloques de las últimas 2 horas.
- Margen amplio para absorber señales que llegan con retraso desde el daemon (buffer + flush).
- Bloques cerrados (más de 2h atrás) no se tocan salvo rebuild manual.

Rebuild manual completo:

```bash
php artisan tracker:rebuild-blocks --since="2026-05-01" --until="2026-05-19"
```

Con `--force-edited` también re-procesa los marcados como editados (raro; típicamente se usa al ajustar mappings y querer recomputar todo).

---

## Performance

- Una jornada (~8h) = 32 bloques × ~50 evidencias = ~1.600 rows en `time_block_evidence`.
- Una semana = ~11k rows. Manejable sin índices exóticos.
- El rebuild de 1 día tarda < 1s sobre 10k eventos en HW modesto.
