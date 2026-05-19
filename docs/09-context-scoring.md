# 09 · Sistema de scoring de contexto

El sistema **no** decide el proyecto por la ventana activa: pondera múltiples señales dentro de un bloque y asigna el proyecto con mayor puntaje.

> Sin scoring, un Alt+Tab a Chrome para mirar Stack Overflow rompería la atribución. El scoring tolera ese ruido.

---

## Modelo conceptual

1. Cada `activity_event` es evaluada y, si es **relevante**, genera 0..N **contribuciones de puntaje** a uno o más proyectos.
2. Una contribución = (`project_id`, `weight`, `note`).
3. Por cada `time_block`, se suman las contribuciones agrupadas por proyecto.
4. El proyecto con mayor suma es el **dominante**.
5. La **confianza** mide cuán claro fue el resultado frente a los demás.

```mermaid
flowchart LR
    E[ActivityEvent] --> M{¿Match con<br/>algún mapping?}
    M -- sí --> R[Lookup signal_kind<br/>en scoring_rules]
    R --> C[Contribución:<br/>{project, weight, note}]
    M -- no --> X[Ignorada]
    C --> A[Aggregator suma<br/>por proyecto en el bloque]
    A --> D[Proyecto dominante<br/>+ confianza]
```

---

## `signal_kind` y pesos sugeridos

Cargados en la tabla `scoring_rules` (ver [`05-database-schema.md`](05-database-schema.md)).

| `signal_kind` | Detección | Peso sugerido |
|---------------|-----------|---------------|
| `vscode_in_repo` | `source=window` AND `app=code` AND título o repo matchea | **+5** |
| `terminal_in_repo` | `source=window` AND app es terminal AND título contiene path/repo | **+4** |
| `git_modified` | `source=git` AND `modified_files > 0` | **+5** |
| `git_commit_recent` | `source=git` AND `latest_commit.ts` dentro del bloque | **+4** |
| `url_match` | `source=browser` AND `url` o título matchea `url_pattern` | **+3** |
| `email_match` | `source=thunderbird` AND `subject` matchea `email_subject` | **+2** |
| `window_title_match` | `source=window` AND título matchea `window_title` mapping | **+2** |
| `idle_penalty` | `source=idle` AND estado `enter` dentro del bloque | **-∞** (ver abajo) |

Los pesos son enteros para evitar imprecisión flotante.

---

## Bonus por mapping

Un mapping individual puede añadir un `weight_bonus` (columna en `project_mappings`). Útil para reforzar coincidencias específicas:

- mapping `email_subject` con pattern `"URGENT JASPER"` y `weight_bonus = +3` → un correo así suma 5 (2 base + 3 bonus).

---

## Algoritmo de scoring por bloque

Pseudocódigo:

```text
function scoreBlock(block):
    events = activity_events WHERE occurred_at BETWEEN block.starts_at AND block.ends_at
    scores = {}        # project_id -> int
    evidence = []      # [{event_id, weight, project_id, rule, note}]

    idle_seconds_in_block = sum(events where source=idle)
    if idle_seconds_in_block > BLOCK_DURATION * 0.8:
        return {project=null, confidence=0, status='idle'}

    for event in events:
        if event.source == 'idle': continue

        for (project_id, signal_kind, weight, note) in resolveContributions(event):
            scores[project_id] += weight
            evidence.append({event.id, weight, project_id, signal_kind, note})

    if scores is empty:
        return {project=null, confidence=0, status='auto'}

    dominant = argmax(scores)
    confidence = computeConfidence(scores, dominant)

    return {project=dominant, confidence, scoring_snapshot=top_n(scores, 5), evidence}
```

### `resolveContributions(event)`

Devuelve la lista de tuplas `(project_id, signal_kind, weight, note)` aplicables. Reglas:

1. Si `event.source = 'window'` y `event.app = 'code'`:
    - Buscar mapping de tipo `repository` cuyo pattern coincida con `event.repo_name`. Si match → contribución con `signal_kind = vscode_in_repo`.
    - Si no, buscar mapping `window_title` que matchee. Si match → `signal_kind = window_title_match`.
2. Si `event.source = 'window'` y `event.app` es terminal:
    - Mapping `repository` por `repo_name` inferido, `signal_kind = terminal_in_repo`.
    - Mapping `folder` por `metadata.cwd_hint`.
3. Si `event.source = 'git'`:
    - Mapping `repository` por `repo_name`. Si `modified_files > 0` → `git_modified`. Si `latest_commit.ts` dentro del bloque → adicional `git_commit_recent`.
4. Si `event.source = 'browser'`:
    - Mappings `url_pattern` aplicados sobre `url` o `title`. `signal_kind = url_match`.
5. Si `event.source = 'thunderbird'`:
    - Mappings `email_subject` sobre `subject`. `signal_kind = email_match`.

El peso final = `scoring_rules.weight` + `project_mappings.weight_bonus`.

---

## Confianza

```
confidence = (top_score - second_score) / top_score
```

Si solo hay un proyecto puntuado: `confidence = 1.0`.

Mapeo a niveles legibles (configurable vía `.env`):

| Valor | Etiqueta |
|-------|----------|
| ≥ 0.65 | **Alta** |
| 0.35–0.65 | **Media** |
| < 0.35 | **Baja** |

Bloques con confianza baja se marcan en la UI con un ícono de duda y se priorizan para revisión manual.

---

## Idle

Un bloque con `idle_seconds_in_block > 0.8 * duración_del_bloque` se marca `status = 'idle'` y `dominant_project_id = NULL`. No se incluye en exports por defecto.

---

## Snapshot de scoring

Se persiste en `time_blocks.scoring_snapshot` como JSON, para auditoría y debugging:

```json
{
  "winner": {"project_id": 3, "code": "JASPER", "score": 17},
  "runners_up": [
    {"project_id": 1, "code": "YWL", "score": 4},
    {"project_id": 5, "code": "TDS", "score": 2}
  ],
  "rules_fired": {
    "vscode_in_repo": 1,
    "terminal_in_repo": 2,
    "git_modified": 1,
    "url_match": 1
  },
  "computed_at": "2026-05-19T09:15:00Z"
}
```

Esto permite responder *"¿por qué este bloque quedó como JASPER?"* desde la UI sin recomputar.

---

## Re-scoring

Como el scoring vive en Laravel (lectura), basta con:

```bash
php artisan tracker:rebuild-blocks --since="7 days ago" --force
```

para reaplicar reglas y mappings actualizados sobre el histórico. Los bloques con `status = 'edited'` no se sobrescriben **salvo** que se pase `--force-edited`.

---

## Reglas implícitas

- **Window-only sin mapping = ruido.** Un Alt+Tab a Slack sin mapping no contribuye. No se inventa atribución.
- **Git es muy fuerte.** Cambios en archivos suelen indicar trabajo real; por eso pesan más que un título de ventana.
- **Pequeñas señales sumadas vencen a grandes señales aisladas.** 3 señales débiles de un proyecto > 1 señal media de otro.
- **El usuario tiene la última palabra.** Cualquier edición manual prevalece y se respeta en re-runs.

---

## Casos edge

| Caso | Comportamiento |
|------|----------------|
| Bloque sin ningún evento | `status='auto'`, `project=null`, `confidence=0`. UI muestra "Sin actividad". |
| Empate exacto entre dos proyectos | Gana el que tiene mayor número de señales distintas; si persiste, el alfabéticamente menor. Confianza = 0. |
| Solo señales de browser sin código | Se atribuye si hay match de `url_pattern`. Confianza ≤ media. |
| Commit detectado en un bloque sin ventana del repo | Aporta `git_commit_recent` igualmente. Sirve para capturar trabajo en remote pairing. |
| Múltiples repos del mismo proyecto activos | Sus contribuciones se suman al mismo `project_id`. |

---

## Extensibilidad

Para añadir una nueva regla:

1. Insertar fila en `scoring_rules` (`signal_kind`, `weight`).
2. Implementar la lógica de detección en `MappingResolver` (Laravel).
3. Actualizar este documento.

Para experimentar con pesos: editar desde Filament, ejecutar `tracker:rebuild-blocks --since="..."` y comparar.
