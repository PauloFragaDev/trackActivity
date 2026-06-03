# Insights / resumen automático — diseño

Fecha: 2026-06-03
Rama: `paulo-insights`
Estado: aprobado en brainstorm.

## Objetivo

Convertir el dato ya capturado por el tracker en insights accionables: una
página **Insights** (módulo activable) con resumen narrativo, reparto por
proyecto, métricas de foco y tiempo inactivo, además de tendencias por proyecto;
y un **digest compacto en el Home** con lo más importante del día.

Hoy solo existen resúmenes **por bloque** (`SummaryGenerator`) y un único gráfico
"minutos por día" en `/reports`. No hay rollup de día/semana ni métricas de foco.

## Decisiones (brainstorm)

- **Las 4 familias de insights** en el v1: narrativa, reparto por proyecto, foco
  vs fragmentación, idle; más tendencias por proyecto.
- **Página Insights nueva**, módulo **activable** vía `ModuleVisibility` (como
  Notas/Pomodoro) + **digest** en el Home (solo lo global/importante).
- **Narrativa por plantilla** (computada de los números); el motor LLM
  (`engine=llm`, ya previsto) se podrá enchufar después sin rehacer.
- **Cálculo en vivo** (sin persistir): el dataset personal es pequeño.
- Umbral de "tramo de foco" (deep-work): **25 min fijo** en v1.
- Ventana de tendencias: **8 semanas**.

## Arquitectura

`App\Services\InsightsService` (sin estado, inyectable) calcula desde
`time_blocks` (+ `project`) y, donde haga falta, `activity_events`:

- `forDay(CarbonImmutable $day): array`
- `forWeek(CarbonImmutable $monday): array`
- `projectTrend(int $weeks = 8): array`

Cada método devuelve un array plano y testeable. Reusa la convención de TZ del
proyecto (`tracker.display_timezone`, datos en UTC) y la idea de
`SessionBuilder` para detectar sesiones/idle.

### Métricas (definiciones)

Sobre los `time_blocks` del rango (idle = `status = idle`; no-idle el resto):

- **Reparto por proyecto**: minutos por `dominant_project_id` (no-idle), ordenado.
- **Idle**: minutos idle; ratio activo/idle; total de jornada (activo+idle).
- **Cambios de contexto**: nº de transiciones entre bloques no-idle **consecutivos
  en el tiempo** cuyo `dominant_project_id` difiere (los idle no rompen ni cuentan).
- **Racha de foco más larga**: minutos del tramo contiguo más largo del mismo
  proyecto (bloques no-idle adyacentes, mismo proyecto).
- **Deep-work %**: minutos en tramos contiguos de un mismo proyecto de **≥ 25 min**
  / minutos no-idle totales.
- **Resumen narrativo (plantilla)**: frase compuesta de lo anterior, p.ej.
  *"Hoy: sobre todo Groundline (4h), algo de Day (1h); 45m inactivo; 8 cambios de
  contexto."* Variante de día y de semana. Helper `NarrativeComposer` (función
  pura: array de métricas → string) para poder testearlo aislado.

### Tendencias

`projectTrend(8)`: para cada una de las últimas 8 semanas (ISO), minutos por
proyecto. Se pinta con Chart.js en un chunk nuevo `insights.js` (mismo patrón que
`reports.js`): barras apiladas por semana, una serie por proyecto.

## Componentes

- `app/Services/InsightsService.php` — cálculo (forDay/forWeek/projectTrend).
- `app/Services/Insights/NarrativeComposer.php` — métricas → frase (puro).
- `app/Http/Controllers/InsightsController.php` — `index` (día/semana según query
  `?date=` / `?week=`), pasa datos a la vista; expone el JSON de tendencias para
  Chart.js (como hace ReportsController).
- `resources/views/insights/index.blade.php` — las 4 secciones + gráfica.
- `resources/js/insights.js` — Chart.js de tendencias (lazy-import en `app.js`
  cuando exista `#insights-data`).
- `ModuleVisibility::MODULES` — nueva entrada `insights` (label "Insights",
  icono `sparkles`).
- Sidebar (`layouts/app.blade.php`) — entrada "Insights" gateada por
  `$modules['insights']['enabled']`.
- Home (`DashboardController@index` + `dashboard/index.blade.php`) — digest del
  día (narrativa + tiempo activo + racha de foco + idle), solo si el módulo está
  activo; enlace a Insights.
- Rutas: `GET /insights` (`insights.index`).

## Toggle del módulo

Igual que los demás: `insights` en `MODULES`; el form de Configuración › General
ya guarda `modules[...]` (no hay que tocar `saveGeneral`). La ruta permanece viva
aunque se oculte (no se rompe ningún bookmark). El digest del Home y la entrada de
sidebar se condicionan a `$modules['insights']['enabled']`.

## Testing

- `InsightsService::forDay/forWeek` con bloques de prueba: reparto por proyecto,
  idle, cambios de contexto, racha más larga, deep-work %.
- `projectTrend`: agrega por semana y proyecto correctamente.
- `NarrativeComposer`: dado un set de métricas produce la frase esperada
  (incluido el caso "sin actividad").
- Toggle/ruta: `/insights` responde 200 con el módulo activo; el digest del Home
  aparece/desaparece según el módulo (patrón de los tests de visibilidad de
  módulos existentes).

## Fuera de alcance

- Narrativa por LLM (queda detrás del `engine=llm` existente, futuro).
- Umbral de deep-work configurable (fijo 25 min en v1).
- Persistir insights / históricos materializados (se calcula en vivo).
- Export de insights (el módulo de export ya existe aparte).
