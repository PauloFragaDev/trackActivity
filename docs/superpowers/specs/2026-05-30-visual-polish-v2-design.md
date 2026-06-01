# Pulido visual v2 — diseño

Fecha: 2026-05-30
Rama: `paulo-visual-polish-v2`
Estado: aprobado, pendiente de plan de implementación.

## Contexto

trackActivity ya tiene una base de diseño sólida tras varias tandas recientes
(motion, tipografía JetBrains Mono, unificación de iconos SVG, y fidelidad de
temas — las superficies en claro ya siguen `var(--paper)`). Esta tanda cierra la
cola de pulido visual: coherencia de acento por tema, profundidad de superficies,
estados seleccionados legibles, atmósfera del Pomodoro, textura del tema paper,
microjerarquía en datos y voz en los estados vacíos.

Es trabajo **100% visual**. No toca modelo de datos, rutas ni lógica de negocio.
Reusa variables que los temas ya definen (`--paper`, `--paper-warm`, `--paper-deep`,
`--accent`, `--accent-soft`, escala `--ink-*`); no se introduce paleta nueva.

Enfoque elegido: **híbrido (C)** — introducir tokens semánticos solo donde se
pagan por reuso (elevación y estados de acento, usados en decenas de sitios), y
cablear directo lo puntual (Pomodoro, grano, copys, microjerarquía).

## Fundación: tokens

Añadir a cada bloque de tema (`:root[data-theme=X]` claro y `html.dark[data-theme=X]`
oscuro) en `dashboard/resources/css/app.css`:

### Elevación
- `--surface` = `var(--paper)` — superficie de contenido (cards, modales, inputs,
  toasts, SweetAlert). Alias semántico; las superficies ya usan `--paper` tras #35.
- `--surface-rail` — sidebar y toolbars sticky. Una capa neutra distinta del
  contenido (lo pide `product.md`). En claro: `var(--paper-warm)`. En oscuro: un
  tono ligeramente separado de las cards (p. ej. `--paper-warm` del bloque dark).
- Los overlays (popover de iconos, command palette, modales, dropdowns) se elevan
  con **sombra más marcada** sobre `--surface`, no con un token de color nuevo.
- La página sigue en `ink-50` (claro) / `ink-950` (oscuro), ambos ya temáticos.

Jerarquía resultante: `página (ink-50) < rail (paper-warm) < card (paper) < overlay (paper + sombra)`.

### Acento
- `--ring` = `var(--accent)` — color del anillo de foco.
- `--selected` = `var(--accent-soft)` — fondo de estado seleccionado/activo.

## Piezas

### 1. Acento temático
Hoy el verde emerald está hardcodeado en varios sitios; debe seguir al tema.
- **Toast de éxito**: la franja/acento toma `var(--accent)` en vez de `#10b981`.
  Warn/error/info mantienen su color semántico (amber/rose/blue).
- **Anillos de foco**: `.input/.select/.textarea` (`focus:ring-emerald-500/50`) y
  los `ring-emerald-*` inline (p. ej. "hoy" en el dashboard, theme-card) pasan a
  `--ring`. Centralizar en el componente CSS donde sea posible.
- **`meta theme-color`**: actualmente estático (light/dark). Debe reflejar el acento
  del tema activo. Mecanismo: mapa de acento por tema; aplicarlo en el bootstrap
  inline de `layouts/app.blade.php` y en `theme-picker.js` al cambiar de tema.

### 2. Pomodoro atmosférico
Wash de fondo muy tenue tras el timer, según la fase activa:
- Foco: frío/neutro. Pausa corta/larga: cálido (ámbar).
- Conducido por el atributo `data-phase` que `pomodoro.js` ya marca; CSS por
  selector de atributo en `.pomodoro-shell` (o el contenedor de página).
- Transición de color con las curvas/duraciones existentes (`--ease-out`).
- Respeta `prefers-reduced-motion` (solo color, sin movimiento) — ya cubierto por
  el bloque global.

### 3. Grano del tema paper
- `background-image` de ruido SVG (data-URI con `feTurbulence`) a opacidad muy
  baja, `background-attachment: fixed`, **solo** bajo `[data-theme="paper"]`.
- CSS puro, sin assets ni JS.

### 4. Copys de estados vacíos
Reescribir con voz por contexto los usos de `<x-empty-state>` (y los empty inline
equivalentes):
- Board sin tareas, vista Archivadas vacía, Informes sin datos, Notas sin notas,
  Papelera vacía, "Tareas en curso" vacío, "Últimas notas" vacío.
- Sin cambio estructural del componente; copy con personalidad y, donde aporte,
  una CTA en el slot de acciones (p. ej. "Nueva tarea").

### 5. Elevación de superficies
- Aplicar `--surface-rail` al `#sidebar` (y, si existe, a barras sticky).
- Overlays (command palette, popover de iconos, dropdowns) con sombra más marcada
  para separarse del contenido.
- Cards/contenido en `--surface`.
- Verificar visualmente que `página < rail < card` se distingue en los 4 temas.

### 6. Selección con acento
Estados "actual/seleccionado" pasan de `ink-100`/`ink-800` neutro a `--selected`:
- Ítem de nav activo (helper `$navItem` en `layouts/app.blade.php`).
- Botón de periodo activo en Informes.
- Chips de label activos en el board.
- "Hoy" (tira de la semana + celda del heatmap) y la nota actual en el sidebar.
- El texto del elemento seleccionado puede reforzarse con el color de acento.

### 7. Microjerarquía de datos
- **Informes**: romper la grilla de 4 tarjetas idénticas (anti-patrón "hero-metric").
  "Total trackeado" como héroe (mayor peso/tamaño); las otras 3 (proyectos, días,
  media) secundarias y más compactas.
- **Heatmap**: añadir leyenda "menos → más" (5 swatches) al estilo GitHub.

## Componentes/archivos afectados (orientativo)

- `dashboard/resources/css/app.css` — tokens, toast, focus, elevación, grano,
  Pomodoro wash, selección.
- `dashboard/resources/js/theme-picker.js` — actualizar `meta theme-color` al cambiar.
- `dashboard/resources/views/layouts/app.blade.php` — bootstrap `theme-color`,
  `--surface-rail` en sidebar, helper `$navItem` (selección).
- `dashboard/resources/js/pomodoro.js` — solo si hace falta exponer la fase (ya la marca).
- `dashboard/resources/views/reports/index.blade.php` — microjerarquía + leyenda heatmap.
- `dashboard/resources/views/dashboard/index.blade.php` — "hoy", heatmap legend.
- `dashboard/resources/views/components/empty-state.blade.php` y vistas que lo usan — copys.
- Varias vistas con `ring-emerald-*` / estados activos inline.

## División en PRs (a confirmar en el plan)

1. **Fundación + transversal**: tokens (elevación + acento), acento temático
   (toast, focus, theme-color), elevación de superficies, selección con acento.
2. **Deleite de superficie**: Pomodoro atmosférico, grano paper, copys de estados
   vacíos, microjerarquía de datos.

## Verificación

- `npm run build` sin errores.
- `php artisan test` — mantener 210 tests en verde (esta tanda no debería tocar tests).
- Repaso visual de los **4 temas × 2 modos** (claro/oscuro) antes de mergear, dado
  que es trabajo puramente visual. Mirar especialmente: contraste del toast con
  acento, legibilidad del estado seleccionado, que la elevación se distinga sin
  romper el tema mono (gris) ni paper (crema).

## Fuera de alcance

- Cambios de paleta o de los valores de tema existentes.
- Lógica, datos, rutas, modelo.
- Ilustraciones para los estados vacíos (solo copy + iconos del set actual).
- Reestructurar el toast más allá de tematizar su acento (la posible eliminación
  del borde lateral de 3px es una decisión aparte).
