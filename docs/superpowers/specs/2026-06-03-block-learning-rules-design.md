# Bucle de aprendizaje: corregir un bloque crea una regla — diseño

Fecha: 2026-06-03
Rama: `paulo-block-learning-rules`
Estado: aprobado en brainstorm.

## Objetivo

Cuando el usuario reasigna un bloque de tiempo a otro proyecto (porque el
tracker lo atribuyó mal), ofrecer en el mismo formulario **crear una regla de
mapeo** para que esa señal deje de atribuirse mal en el futuro, con opción de
**reprocesar** los bloques pasados. Cierra de raíz el dolor (p.ej. trabajo en
Groundline marcado como Day) en vez de corregir bloque a bloque.

Lo que ya existe (no se rehace): reasignar un bloque (`TimeBlockController@update`,
estado `edited`, sobrevive a rebuilds) y editar mapeos a mano en el editor de
proyectos. Lo que falta: que la corrección **genere** el mapeo.

## Decisiones (brainstorm)

- La regla se ofrece **inline en el formulario de reasignar** (un solo POST), con
  patrones candidatos marcables.
- Retroactividad: **a futuro + opción de reprocesar** bloques `auto` de un rango
  (7/30 días); nunca toca los `edited`.
- `weight_bonus` de la regla creada: **alto fijo (5)**, simple.
- Se añade una columna **`origin`** (nullable) a `project_mappings` para marcar
  las reglas creadas desde una corrección (transparencia en el editor de proyectos).

## Derivación de patrones candidatos (servidor)

De los `activity_events` de la sesión (ya disponibles como `evidence`) se extraen
valores distintos y se mapean a los tipos de `project_mappings`:

| Señal del evento            | type de mapping | etiqueta mostrada            |
|-----------------------------|-----------------|------------------------------|
| `repo_name`                 | `repository`    | repo «day»                   |
| carpeta de `metadata.cwd_hint` (basename) | `folder` | carpeta «day»          |
| token del título de ventana | `window_title`  | título contiene «…»          |
| host de `url`               | `url_pattern`   | url «github.com»             |

Se deduplican, se ordenan por frecuencia/peso contribuido y se muestran los más
distintivos. Se premarca el más representativo al elegir proyecto. La lógica vive
en un helper testeable (`BlockRuleSuggester`) que recibe los eventos y devuelve
una lista de `{type, pattern, label, count}`.

## UI

En `resources/views/timeline/day.blade.php`, dentro del form de reasignar de cada
sesión (debajo del `<select name="project_id">`):

- Sección plegable **"Crear regla para que no se repita"** con un checkbox por
  patrón candidato (`name="create_mappings[]"` valor `type:pattern`).
- Checkbox **"Reprocesar bloques automáticos de los últimos N días"**
  (`name="reprocess_days"`, valores 0/7/30; 0 = no reprocesar).
- Si la sesión no tiene patrones derivables (p.ej. solo idle), la sección no
  aparece.

## Backend

`TimeBlockController@update` se amplía (mismo endpoint `blocks.update`):

- Nuevas reglas de validación:
  - `create_mappings` => `array`, `create_mappings.*` => `string` (formato `type:pattern`).
  - `reprocess_days` => `nullable`, `integer`, `in:0,7,30`.
- Tras reasignar (lógica actual intacta), por cada `create_mappings[]` con un
  `project_id` destino no nulo: `ProjectMapping::firstOrCreate` con
  `['project_id'=>$projectId,'type'=>$type,'pattern'=>$pattern]` y, en creación,
  `['is_regex'=>false,'enabled'=>true,'weight_bonus'=>5,'origin'=>'block_correction']`.
- Si `reprocess_days > 0`: `Aggregator::rebuildRange(now-Ndías, now, forceEdited=false)`
  para reatribuir los bloques `auto` recientes con la regla ya activa.
- Si `project_id` es nulo (se quita proyecto), se ignoran `create_mappings`
  (no tiene sentido mapear "a ninguno").

## Datos

- Migración: `project_mappings.origin` string nullable (sin índice; valores libres
  tipo `block_correction`). Las reglas manuales quedan con `origin = null`.
- Sin más cambios de esquema (reutiliza `project_mappings` y el `Aggregator`).

## Nota sobre la fuerza de la regla

`weight_bonus = 5` busca que el proyecto mapeado gane frente a la competencia
habitual. Si una señal global domina (caso `git_commit_recent` que ya ajustaste a
0), eso se resuelve con el **editor de pesos de scoring** (feature aparte). Aquí no
se intenta ganar a un peso global desorbitado.

## Testing

- `BlockRuleSuggester`: con un set de eventos (repo/cwd/título/url) devuelve los
  candidatos correctos, deduplicados y ordenados; ignora eventos sin señal.
- `update` con `create_mappings`: crea el/los `ProjectMapping` con `project_id`,
  `weight_bonus=5`, `origin='block_correction'`; idempotente (no duplica).
- `update` con `project_id` nulo + `create_mappings`: no crea mapeos.
- `update` con `reprocess_days=7`: invoca el rebuild del rango (mock del Aggregator)
  y no toca bloques `edited`.
- Render: el form de día muestra los chips candidatos cuando hay evidencia.

## Fuera de alcance

- Editor de pesos de scoring (feature separada).
- Sugerir reglas fuera del flujo de corrección (p.ej. proactivamente).
- Regex en las reglas creadas (siempre `is_regex=false`; el editor de proyectos
  sigue permitiendo regex a mano).
