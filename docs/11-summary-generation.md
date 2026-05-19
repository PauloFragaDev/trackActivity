# 11 · Generación de resúmenes

Cada `time_block` (o sesión) puede tener un **resumen textual** generado a partir de su evidencia. El objetivo es producir un texto **conciso, profesional y reutilizable** para pegar directamente en el sistema de timesheet corporativo.

> El resumen no es prosa: es un microtexto operativo.

---

## Engines

| Engine | Disponibilidad | Descripción |
|--------|----------------|-------------|
| `template` | ✅ MVP | Plantillas con interpolación de variables extraídas de la evidencia. Sin dependencias externas. |
| `llm` | 🔜 futuro | Opcional, vía API local (Ollama) o remota (Claude/OpenAI). Activado con `TRACKER_SUMMARY_ENGINE=llm`. |

El engine se elige por bloque (no global) en `generated_summaries.engine`. Esto permite migrar al usuario hacia LLM sin perder el histórico.

---

## Engine `template` (MVP)

### Inputs disponibles

Desde la evidencia del bloque/sesión:

| Variable | Origen |
|----------|--------|
| `{{project.code}}` | `projects.code` (`JASPER`) |
| `{{project.name}}` | `projects.name` |
| `{{branches}}` | lista de `branch` únicas de `git` events |
| `{{repos}}` | lista de `repo_name` únicos |
| `{{commit_messages}}` | mensajes recientes (truncados) |
| `{{urls.github_issues}}` | issues de GitHub detectadas |
| `{{urls.github_prs}}` | PRs de GitHub detectados |
| `{{urls.jira}}` | tickets Jira detectados (regex `[A-Z]+-\d+`) |
| `{{email_subjects}}` | asuntos únicos de Thunderbird |
| `{{duration_minutes}}` | duración total |
| `{{start_time}}` / `{{end_time}}` | hora local |

### Plantilla por defecto (es)

```mustache
Trabajo en {{project.name}}{{#branches.size}} sobre {{#branches}}{{.}}{{^last}}, {{/last}}{{/branches}}{{/branches.size}}{{#commit_messages.size}}. Cambios principales: {{#commit_messages}}{{.}}{{^last}}; {{/last}}{{/commit_messages}}{{/commit_messages.size}}{{#urls.github_issues.size}}. Issues relacionadas: {{#urls.github_issues}}#{{number}}{{^last}}, {{/last}}{{/urls.github_issues}}{{/urls.github_issues.size}}.
```

Ejemplos de salida:

> *"Trabajo en JASPER sobre fix/dashboard-permissions. Cambios principales: Fix CRM access permissions. Issues relacionadas: #123."*

> *"Trabajo en YWL sobre main. Cambios principales: Update notification queue; Bump dependencies."*

### Reglas de redacción

- Una sola frase preferiblemente.
- Máximo 240 caracteres.
- Sin emojis, sin signos exclamativos.
- Capitalización de inicio + punto final.
- Si solo hay evidencia débil: *"Actividad relacionada con {{project.name}}."*

### Heurísticas de extracción

- **Mensajes de commit**: dedupe + recorte a 80 chars + máximo 3 mensajes.
- **Branches**: si hay > 3, mostrar las primeras 2 + "y N más".
- **Issues GitHub**: regex `#(\d+)` sobre títulos y URLs.
- **Tickets Jira**: regex `[A-Z][A-Z0-9]+-\d+` sobre títulos.

### Localización

Plantillas separadas por locale: `resources/lang/{es,en}/summary.php`. Default: `TRACKER_SUMMARY_LOCALE=es`.

---

## Engine `llm` (post-MVP)

### Cuándo merece la pena

Cuando el usuario tiene proyectos con commits poco descriptivos, branches genéricas (`feature/x`), y necesita parafraseo más natural.

### Diseño

- **Por defecto offline**: usar Ollama local (`llama3:8b-instruct`, `qwen2:7b-instruct`).
- Prompt fijo + serialización de la evidencia como JSON.
- Respuesta limitada (max 200 tokens).
- Fallback al engine `template` si el LLM no está disponible o tarda > 5s.

### Prompt sugerido

```
Eres un asistente que ayuda a redactar entradas de timesheet.
Dada la siguiente evidencia de trabajo, produce UNA SOLA FRASE en español,
máximo 240 caracteres, en tono profesional, describiendo qué se hizo.
No inventes detalles que no estén en la evidencia.

Evidencia:
{evidence_json}
```

### Privacidad

- El engine `llm` con backend en la nube envía evidencia fuera del equipo. Esto **rompe el principio offline-first**.
- Por eso permanece desactivado por defecto y requiere acción explícita del usuario (`TRACKER_SUMMARY_ENGINE=llm` y `TRACKER_LLM_BACKEND=...`).

---

## Regeneración

### Cuándo se regenera automáticamente

- Al ejecutar `tracker:generate-summaries` programado (cada 15 min sobre las 2 últimas horas).
- Tras un rebuild de bloques (`tracker:rebuild-blocks`) que modifique evidencia.

### Cuándo NO se regenera

- Si `edited_by_user = true`.
- Si el bloque tiene `status = 'merged'` o `'split'` y el usuario ya ha editado el texto.

Para forzar regeneración:

```bash
php artisan tracker:generate-summaries --force --since="today"
```

---

## Edición manual

Inline en el dashboard. Al guardar:

- `text` se actualiza.
- `edited_by_user = true`.
- `engine` se mantiene (no se sobrescribe a `manual`; se respeta el origen original para auditoría).

---

## API interna

```php
interface SummaryGenerator
{
    public function generateFor(TimeBlock $block): GeneratedSummary;
    public function generateForSession(Collection $blocks): string; // sesiones agrupadas
}
```

El generador es **idempotente**: misma evidencia + misma plantilla → mismo texto.

---

## Calidad esperada

| Métrica | Objetivo |
|---------|----------|
| % bloques con resumen no vacío | > 80% (los demás suelen ser idle o de baja confianza) |
| Longitud media | 120–180 caracteres |
| Tasa de edición manual | < 30% en régimen estable |

Si la tasa de edición es alta, suele indicar que faltan mappings de scoring o que el commit-message es pobre. La mejora se hace ajustando mappings, no la plantilla.

---

## Roadmap

- **v1**: solo `template`, en español, con extracción básica.
- **v1.1**: inglés + mejora de extracción de Jira/GitHub.
- **v2**: engine `llm` con Ollama local.
- **v2.1**: aprendizaje pasivo: si el usuario edita un resumen, sugerirlo como template para casos similares.
