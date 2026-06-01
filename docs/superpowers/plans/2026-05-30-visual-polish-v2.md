# Pulido visual v2 — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar la cola de pulido visual de trackActivity: coherencia de acento por tema, profundidad de superficies, estados seleccionados, atmósfera del Pomodoro, textura del tema paper, microjerarquía de datos y voz en estados vacíos.

**Architecture:** Enfoque híbrido. Se añade un set corto de tokens semánticos (`--surface`, `--surface-rail`, `--ring`, `--selected`) declarados UNA vez en `:root`, derivados con `var()` de las variables que cada tema ya redefine (`--paper`, `--paper-warm`, `--accent`, `--accent-soft`); la cascada los resuelve contra el tema activo. Las 7 piezas consumen esos tokens o se cablean directo. No se introduce paleta nueva ni se toca lógica/datos.

**Tech Stack:** Laravel Blade, Tailwind (config con `darkMode: 'class'` y `hoverOnlyWhenSupported`), CSS custom properties por tema, Vite, JS vanilla (ESM).

**Verificación (esto es trabajo 100% visual — no hay tests unitarios de apariencia):**
Los pasos "test" del formato TDD se sustituyen por estas puertas, idénticas en cada tarea salvo donde se indique:
- `npm run build` (en `dashboard/`) termina sin errores.
- `php artisan test --compact` (en `dashboard/`) mantiene **210 passed**.
- Repaso visual de los **4 temas × 2 modos** (default/paper/notion/mono × claro/oscuro) en la(s) pantalla(s) afectada(s).

**Rama:** `paulo-visual-polish-v2` (ya creada desde `main`, con #32–#35 mergeados). Split en 2 PRs:
- **PR1 (Fundación + transversal):** Tareas 1–5.
- **PR2 (Deleite de superficie):** Tareas 6–9.

Todas las rutas son relativas a `/var/www/html/trackActivity/`.

---

## PR1 — Fundación + transversal

### Task 1: Tokens semánticos (fundación)

**Files:**
- Modify: `dashboard/resources/css/app.css` (bloque `:root { --ease-out... }`, ~línea 226)

- [ ] **Step 1: Añadir los aliases al bloque `:root` de motion**

En el bloque que empieza `:root {` y define `--ease-out`/`--dur-*`, justo antes de su `}`, añadir:

```css
        /* Tokens semanticos derivados (pulido visual v2). Se declaran una
           sola vez: var() los resuelve contra el --accent/--paper/--paper-warm
           del tema activo, asi siguen al tema sin repetir por bloque. */
        --surface:      var(--paper);
        --surface-rail: var(--paper-warm);
        --ring:         var(--accent);
        --selected:     var(--accent-soft);
```

- [ ] **Step 2: Verificar** — `npm run build` sin errores. (No hay cambio visible aún; los tokens se consumen en tareas siguientes.)

- [ ] **Step 3: Commit**

```bash
git add dashboard/resources/css/app.css
git commit -m "feat(themes): tokens semanticos de superficie y acento"
```

---

### Task 2: Anillos de foco siguen el acento

**Files:**
- Modify: `dashboard/resources/css/app.css` (bloque `.input, .select, .textarea`, ~líneas 360-365)
- Modify: `dashboard/resources/views/dashboard/index.blade.php:64`

- [ ] **Step 1: Cambiar el foco de los inputs a `--accent`**

Sustituir estas dos líneas del bloque `.input, .select, .textarea`:

```css
        @apply focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500;
        @apply dark:bg-ink-800 dark:border-ink-700 dark:text-ink-100 dark:placeholder:text-ink-500;
        @apply dark:hover:border-ink-600 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/40;
```

por (se quita el ring/border emerald hardcodeado y los dark:focus:* duplicados; el foco pasa a CSS con `--accent`):

```css
        @apply focus:outline-none;
        @apply dark:bg-ink-800 dark:border-ink-700 dark:text-ink-100 dark:placeholder:text-ink-500;
        @apply dark:hover:border-ink-600;
```

Y añadir, inmediatamente después del cierre `}` del bloque `.input, .select, .textarea`:

```css
    .input:focus, .select:focus, .textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 45%, transparent);
    }
```

- [ ] **Step 2: Cambiar el ring "hoy" del dashboard**

En `dashboard/resources/views/dashboard/index.blade.php:64`, sustituir:

```blade
                          {{ $d['is_today'] ? 'ring-2 ring-emerald-400' : '' }}">
```

por:

```blade
                          {{ $d['is_today'] ? 'ring-2 ring-[var(--ring)]' : '' }}">
```

- [ ] **Step 3: Verificar** — build sin errores; `php artisan test` 210 verdes; en notion (azul) y mono (gris) el foco de un input y el borde de "hoy" salen del color del tema, no verde.

- [ ] **Step 4: Commit**

```bash
git add dashboard/resources/css/app.css dashboard/resources/views/dashboard/index.blade.php
git commit -m "feat(themes): anillos de foco siguen var(--accent)"
```

---

### Task 3: Toast de éxito sigue el acento

**Files:**
- Modify: `dashboard/resources/css/app.css` (bloque `.toastify.app-toast`, ~línea 557)

- [ ] **Step 1: Tematizar la franja de éxito**

Sustituir:

```css
        border-left: 3px solid #10b981;
```

por:

```css
        border-left: 3px solid var(--accent);
```

(Las variantes `--warn`/`--error`/`--info` mantienen su color semántico; no se tocan.)

- [ ] **Step 2: Verificar** — build sin errores; en mono/notion un toast de éxito muestra la franja del color del tema. (Disparar un toast: cualquier acción con `session('status')`.)

- [ ] **Step 3: Commit**

```bash
git add dashboard/resources/css/app.css
git commit -m "feat(themes): toast de exito usa var(--accent)"
```

---

### Task 4: `theme-color` del navegador sigue el tema

**Files:**
- Create: `dashboard/resources/js/theme-color.js`
- Modify: `dashboard/resources/views/layouts/app.blade.php:14-16` (metas)
- Modify: `dashboard/resources/js/app.js` (init + handler del toggle claro/oscuro)
- Modify: `dashboard/resources/js/theme-picker.js` (tras aplicar el tema)

- [ ] **Step 1: Crear el helper que sincroniza la meta con `--accent` computado**

Crear `dashboard/resources/js/theme-color.js`:

```js
/**
 * Mantiene <meta name="theme-color"> en sintonia con el acento del tema
 * activo (cambia por paleta y por modo claro/oscuro). Lee el valor
 * computado de --accent, que ya esta resuelto cuando corre app.js
 * (script diferido, CSS aplicado). Sin mapa de colores que mantener.
 */
export function syncThemeColor() {
    const accent = getComputedStyle(document.documentElement)
        .getPropertyValue('--accent').trim();
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta && accent) meta.setAttribute('content', accent);
}
```

- [ ] **Step 2: Reemplazar las dos metas estáticas por una sola**

En `dashboard/resources/views/layouts/app.blade.php`, sustituir:

```blade
    {{-- Color del chrome del navegador: emerald acento en claro, slate en oscuro. --}}
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#10b981">
    <meta name="theme-color" media="(prefers-color-scheme: dark)"  content="#0f172a">
```

por:

```blade
    {{-- Color del chrome del navegador: lo fija theme-color.js con el
         acento del tema activo (ver resources/js/theme-color.js). --}}
    <meta name="theme-color" content="#10b981">
```

- [ ] **Step 3: Llamar a `syncThemeColor` en app.js**

En `dashboard/resources/js/app.js`, añadir el import al principio (junto a los demás imports):

```js
import { syncThemeColor } from './theme-color.js';
```

Llamarlo una vez al cargar (dentro del bloque de inicialización principal del módulo, junto al resto de inits) y dentro del manejador del click de `#theme-toggle`, justo después de que se alterne la clase `dark`. Localizar el handler de `#theme-toggle` y añadir, tras el toggle de la clase:

```js
        syncThemeColor();
```

Y al final de la inicialización del módulo (donde se llaman los demás init), añadir:

```js
    syncThemeColor();
```

- [ ] **Step 4: Llamar a `syncThemeColor` al cambiar de paleta**

En `dashboard/resources/js/theme-picker.js`, importar el helper al principio:

```js
import { syncThemeColor } from './theme-color.js';
```

Y dentro del handler, justo después de `localStorage.setItem('themeId', id);` (línea 33), añadir:

```js
        syncThemeColor();
```

- [ ] **Step 5: Verificar** — build sin errores. En un navegador con barra coloreable (o DevTools), cambiar de tema y de modo claro/oscuro y comprobar que la meta `theme-color` cambia (Elements → `<meta name="theme-color">`).

- [ ] **Step 6: Commit**

```bash
git add dashboard/resources/js/theme-color.js dashboard/resources/js/app.js dashboard/resources/js/theme-picker.js dashboard/resources/views/layouts/app.blade.php
git commit -m "feat(themes): meta theme-color sigue el acento del tema"
```

---

### Task 5: Elevación del sidebar + estado seleccionado con acento

**Files:**
- Modify: `dashboard/resources/views/layouts/app.blade.php` (sidebar `bg`, helper `$navItem`, highlight de nota fijada)
- Modify: `dashboard/resources/views/layouts/partials/sidebar-folder.blade.php` (highlight de nota actual, si aplica)
- Modify: `dashboard/resources/views/reports/index.blade.php:28` (botón de periodo activo)

- [ ] **Step 1: Sidebar como capa "rail"**

En `dashboard/resources/views/layouts/app.blade.php`, en el `<aside id="sidebar" ...>`, sustituir:

```blade
                      bg-[var(--paper)] dark:bg-ink-900 border-r divider">
```

por:

```blade
                      bg-[var(--surface-rail)] border-r divider">
```

(`--surface-rail` es temático en claro y oscuro, así que se elimina el `dark:bg-ink-900`.)

- [ ] **Step 2: Ítem de nav activo con `--selected`**

En el mismo archivo, sustituir el helper `$navItem`:

```blade
        $navItem = fn (array $routes) => request()->routeIs(...$routes)
            ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium'
            : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800';
```

por:

```blade
        $navItem = fn (array $routes) => request()->routeIs(...$routes)
            ? 'bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium'
            : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800';
```

- [ ] **Step 3: Nota fijada/actual activa con `--selected`**

En `dashboard/resources/views/layouts/app.blade.php`, en el bloque de favoritas (`@foreach ($sidebarPinned as $fav)`), sustituir la clase activa:

```blade
                                      {{ (int) request()->query('note') === $fav->id ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}"
```

por:

```blade
                                      {{ (int) request()->query('note') === $fav->id ? 'bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}"
```

Después abrir `dashboard/resources/views/layouts/partials/sidebar-folder.blade.php` y, si contiene la misma cadena `bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium` para marcar la nota/carpeta activa, sustituir ese fragmento por `bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium` (solo la parte del estado activo; dejar intacto el estado inactivo con su `hover:`).

- [ ] **Step 4: Botón de periodo activo en Informes**

En `dashboard/resources/views/reports/index.blade.php:28`, sustituir:

```blade
                   class="btn-ghost text-sm {{ $period === $key ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : '' }}">
```

por:

```blade
                   class="btn-ghost text-sm {{ $period === $key ? 'bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium' : '' }}">
```

- [ ] **Step 5: Verificar** — build; `php artisan test` 210 verdes; en los 4 temas: el sidebar se distingue de las cards (capa rail), y el ítem de nav activo / nota fijada activa / periodo activo se ven como "seleccionado" con tinte de acento (azul en notion, verde en default, etc.), no gris.

- [ ] **Step 6: Commit**

```bash
git add dashboard/resources/views/layouts/app.blade.php dashboard/resources/views/layouts/partials/sidebar-folder.blade.php dashboard/resources/views/reports/index.blade.php
git commit -m "feat(themes): sidebar como capa rail y seleccion con acento"
```

- [ ] **Step 7: Push + PR1**

```bash
git push -u origin paulo-visual-polish-v2
gh pr create --base main --head paulo-visual-polish-v2 \
  --title "feat(themes): acento, elevacion y seleccion temáticas" \
  --body "## Summary
- Tokens semanticos (--surface/--surface-rail/--ring/--selected) derivados del tema activo.
- Anillos de foco, toast de exito y meta theme-color siguen var(--accent).
- Sidebar como capa rail; estado seleccionado (nav, nota fijada, periodo) con tinte de acento."
```

---

## PR2 — Deleite de superficie

> Estas tareas pueden hacerse en la misma rama tras mergear PR1, o en una rama nueva desde main si PR1 ya está mergeado. Decidir al llegar (ver "finishing-a-development-branch").

### Task 6: Pomodoro atmosférico (glow por fase)

**Files:**
- Modify: `dashboard/resources/css/app.css` (zona Pomodoro, tras `.pomodoro-card`, ~línea 797)

- [ ] **Step 1: Añadir glow ambiental por fase**

Tras la regla `.pomodoro-card { ... }`, añadir:

```css
    /* Atmosfera por fase: un glow ambiental suave alrededor del timer.
       Solo color/sombra (sin layout, sin movimiento) — ok con
       prefers-reduced-motion, que ademas neutraliza la duracion. */
    .pomodoro-card {
        transition: box-shadow var(--dur-drawer) var(--ease-out),
                    background-color var(--dur-drawer) var(--ease-out);
    }
    .pomodoro-shell[data-phase="focus"]          .pomodoro-card,
    .pomodoro-shell[data-phase="awaiting_focus"] .pomodoro-card {
        box-shadow: 0 0 90px -24px rgb(16 185 129 / 0.20);
    }
    .pomodoro-shell[data-phase="short"]          .pomodoro-card,
    .pomodoro-shell[data-phase="awaiting_break"] .pomodoro-card {
        box-shadow: 0 0 90px -24px rgb(245 158 11 / 0.24);
    }
    .pomodoro-shell[data-phase="long"]           .pomodoro-card {
        box-shadow: 0 0 90px -24px rgb(56 189 248 / 0.22);
    }
```

- [ ] **Step 2: Verificar** — build; abrir `/pomodoro`, iniciar foco (glow verde), saltar a pausa (glow ámbar), comprobar que la transición de color es suave y que en `prefers-reduced-motion` no hay parpadeo brusco.

- [ ] **Step 3: Commit**

```bash
git add dashboard/resources/css/app.css
git commit -m "feat(pomodoro): glow ambiental por fase"
```

---

### Task 7: Grano del tema paper

**Files:**
- Modify: `dashboard/resources/css/app.css` (tras el bloque `body { ... }` del `@layer base`, ~línea 36)

- [ ] **Step 1: Añadir textura de ruido solo en el tema paper**

Tras la regla `body { ... }` del `@layer base`, añadir:

```css
    /* Textura de papel sutil, solo en el tema "paper". data-URI SVG con
       feTurbulence; opacidad bajisima y fixed para que no haga bandas al
       hacer scroll. No afecta a otros temas. */
    html[data-theme="paper"] body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        opacity: 0.5;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
    }
```

- [ ] **Step 2: Verificar** — build; en tema **paper** claro se aprecia una textura granulada muy sutil sobre el fondo; en default/notion/mono NO aparece. Comprobar que no genera bandas al hacer scroll.

- [ ] **Step 3: Commit**

```bash
git add dashboard/resources/css/app.css
git commit -m "feat(themes): textura de papel sutil en el tema paper"
```

---

### Task 8: Copys de estados vacíos con voz

**Files:**
- Modify: `dashboard/resources/views/reports/index.blade.php:58-61`
- Modify: `dashboard/resources/views/tasks/archived.blade.php:15` (alrededor)
- Modify: `dashboard/resources/views/dashboard/index.blade.php:122,142`

- [ ] **Step 1: Informes sin datos**

En `dashboard/resources/views/reports/index.blade.php`, sustituir:

```blade
        <x-empty-state
            icon="clock"
            title="Sin datos para este periodo"
            text="Asegúrate de que el tracker está en marcha o cambia de periodo." />
```

por:

```blade
        <x-empty-state
            icon="clock"
            title="Nada que medir todavía"
            text="Cuando el tracker registre actividad en este periodo, aquí verás el desglose. ¿Está en marcha?" />
```

- [ ] **Step 2: Archivadas vacío**

Abrir `dashboard/resources/views/tasks/archived.blade.php`, localizar el `<x-empty-state ... />` (~línea 15) y dejar su copy así (ajustar `icon` al que ya use):

```blade
        <x-empty-state
            icon="inbox"
            title="No has archivado nada"
            text="Las tareas que archives desde el tablero aparecerán aquí, listas para restaurar o borrar." />
```

- [ ] **Step 3: Dashboard — notas y tareas vacías**

En `dashboard/resources/views/dashboard/index.blade.php`, sustituir la línea 122:

```blade
                    <p class="text-sm text-muted">Aún no hay notas.</p>
```

por:

```blade
                    <p class="text-sm text-muted">Tus notas recientes aparecerán aquí. Crea la primera desde Notas.</p>
```

Y la línea 142:

```blade
                    <p class="text-sm text-muted">No hay tareas en curso.</p>
```

por:

```blade
                    <p class="text-sm text-muted">Nada en curso ahora mismo. Mueve una tarea a “En curso” en el tablero.</p>
```

- [ ] **Step 4: Verificar** — build; `php artisan test` 210 verdes; revisar `/reports` (periodo sin datos), `/tasks/archived` (vacío) y `/dashboard` (sin notas/tareas) muestran los copys nuevos.

- [ ] **Step 5: Commit**

```bash
git add dashboard/resources/views/reports/index.blade.php dashboard/resources/views/tasks/archived.blade.php dashboard/resources/views/dashboard/index.blade.php
git commit -m "feat(ui): copys de estados vacios con voz por contexto"
```

---

### Task 9: Microjerarquía de datos (Informes + heatmap)

**Files:**
- Modify: `dashboard/resources/views/reports/index.blade.php:35-55` (tarjetas resumen)
- Modify: `dashboard/resources/views/dashboard/index.blade.php` (tras el heatmap, ~línea 106)

- [ ] **Step 1: Romper la grilla "hero-metric" de Informes**

En `dashboard/resources/views/reports/index.blade.php`, sustituir el bloque de tarjetas resumen:

```blade
    {{-- Tarjetas resumen --}}
    <div class="grid gap-3 md:grid-cols-4 mb-6">
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Total trackeado</div>
            <div class="text-2xl font-medium mt-1 font-mono tabular-nums">{{ $fmt($totalMinutes) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Proyectos activos</div>
            <div class="text-2xl font-medium mt-1 font-mono tabular-nums">{{ $projectCount }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Días con actividad</div>
            <div class="text-2xl font-medium mt-1 font-mono tabular-nums">
                {{ $daysActive }}<span class="text-sm font-normal text-muted"> / {{ count($byDay) }}</span>
            </div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Media diaria</div>
            <div class="text-2xl font-medium mt-1 font-mono tabular-nums">{{ $fmt($avgDaily) }}</div>
        </div>
    </div>
```

por (Total como héroe a la izquierda; los otros 3 secundarios y compactos a la derecha):

```blade
    {{-- Resumen: el total es el heroe; el resto, secundario y compacto. --}}
    <div class="grid gap-3 md:grid-cols-3 mb-6">
        <div class="card p-5 md:row-span-1 flex flex-col justify-center">
            <div class="text-xs text-muted uppercase tracking-wider">Total trackeado</div>
            <div class="text-4xl font-medium mt-1 font-mono tabular-nums">{{ $fmt($totalMinutes) }}</div>
        </div>
        <div class="md:col-span-2 grid gap-3 sm:grid-cols-3">
            <div class="card p-4">
                <div class="text-[11px] text-muted uppercase tracking-wider">Proyectos</div>
                <div class="text-xl font-medium mt-1 font-mono tabular-nums">{{ $projectCount }}</div>
            </div>
            <div class="card p-4">
                <div class="text-[11px] text-muted uppercase tracking-wider">Días activos</div>
                <div class="text-xl font-medium mt-1 font-mono tabular-nums">
                    {{ $daysActive }}<span class="text-sm font-normal text-muted"> / {{ count($byDay) }}</span>
                </div>
            </div>
            <div class="card p-4">
                <div class="text-[11px] text-muted uppercase tracking-wider">Media diaria</div>
                <div class="text-xl font-medium mt-1 font-mono tabular-nums">{{ $fmt($avgDaily) }}</div>
            </div>
        </div>
    </div>
```

- [ ] **Step 2: Leyenda del heatmap**

En `dashboard/resources/views/dashboard/index.blade.php`, justo después del `</div>` que cierra `<div class="card p-3 overflow-x-auto">` del heatmap (cierre de la `<section>` del heatmap, ~línea 106), y antes de `</section>`, añadir una leyenda usando el mismo `$heatClass` ya definido en la vista:

```blade
            <div class="flex items-center justify-end gap-1.5 mt-2 text-[11px] text-faint">
                <span>menos</span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[0] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[1] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[2] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[3] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[4] }}"></span>
                <span>más</span>
            </div>
```

- [ ] **Step 3: Verificar** — build; `php artisan test` 210 verdes; en `/reports` el Total destaca sobre los otros 3; en `/dashboard` el heatmap tiene leyenda "menos → más" alineada a la derecha. Revisar responsive (móvil: las tarjetas secundarias se apilan).

- [ ] **Step 4: Commit**

```bash
git add dashboard/resources/views/reports/index.blade.php dashboard/resources/views/dashboard/index.blade.php
git commit -m "feat(ui): jerarquia en tarjetas de Informes y leyenda del heatmap"
```

- [ ] **Step 5: Push + PR2**

```bash
git push origin paulo-visual-polish-v2
gh pr create --base main --head paulo-visual-polish-v2 \
  --title "feat(ui): Pomodoro atmosferico, grano paper, copys y jerarquia de datos" \
  --body "## Summary
- Pomodoro con glow ambiental por fase (foco/pausa).
- Textura de papel sutil solo en el tema paper.
- Copys de estados vacios con voz por contexto.
- Jerarquia en las tarjetas de Informes y leyenda del heatmap."
```

(Si PR1 y PR2 van en la misma rama, será un único PR con todo; ajustar título/cuerpo.)

---

## Notas de implementación

- **No tocar el modo oscuro** salvo donde se indica (`--surface-rail` en sidebar). Las superficies en oscuro ya siguen el tema vía la escala `ink-*`.
- **`color-mix`** ya se usa en el proyecto (chips de label), así que el target lo soporta.
- Si al revisar `sidebar-folder.blade.php` (Task 5, Step 3) la cadena del estado activo difiere, aplicar el mismo criterio: solo el fragmento del estado seleccionado pasa a `bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium`.
- Si `tasks/archived.blade.php` ya trae un `icon` distinto en su `<x-empty-state>`, conservarlo; solo cambian `title` y `text`.
- **Elevación de overlays:** modales (`.modal` con `shadow-2xl`) y popover de iconos (`shadow-lg`) ya se separan del contenido por sombra; no requieren token nuevo. La elevación de esta tanda se centra en el sidebar (capa rail).
- **Selección — fuera de alcance a propósito:** los chips de label activos mantienen su color de label propio (no pasan a `--selected`); el heatmap no tiene marca especial de "hoy". La selección con acento aplica a nav, nota fijada/actual y botón de periodo.
- **Estado inválido de inputs:** la regla nueva `.input:focus` (box-shadow con `--accent`) tiene menor especificidad que `.input.is-invalid:focus` (anillo rosa), así que el foco de error sigue ganando. Sin regresión.
