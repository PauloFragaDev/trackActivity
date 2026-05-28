# Combobox

Reemplazo visual accesible de `<select>` nativos. Sin dependencias.

## Cómo se usa

Cualquier `<select>` del DOM se migra automáticamente al cargar la
página (y cualquiera inyectado por un modal después, vía
`MutationObserver`). No hace falta marcar nada explícitamente.

```html
<!-- Esto: -->
<select name="project_id">
    <option value="">— Sin proyecto —</option>
    <option value="1">JASPER</option>
    <option value="2">TDS</option>
</select>

<!-- Se renderiza como un botón con popover/listbox: -->
<div class="combobox" role="combobox" aria-haspopup="listbox" aria-expanded="false">
    <button class="combobox__trigger">
        <span class="combobox__value">— Sin proyecto —</span>
        <span class="combobox__chevron">▾</span>
    </button>
    <div class="combobox__popover" hidden>
        <input class="combobox__search" placeholder="Buscar…" />
        <ul class="combobox__list" role="listbox">
            <li role="option">— Sin proyecto —</li>
            ...
        </ul>
    </div>
</div>
<select name="project_id" hidden class="combobox__source">…</select>
```

El `<select>` original se mantiene en el DOM con `hidden` y `tabindex="-1"`.
Eso garantiza que cualquier handler de formulario (submit, validación,
sync con backend) siga funcionando sin cambios. El componente lo trata
como su "modelo": al elegir una opción se actualiza el `value` y se
dispara un `change` event.

## Excluir un select

Para mantener el nativo (por ejemplo en `<select native>` que abre
opciones del SO en mobile), añade el atributo:

```html
<select data-no-combobox> …
```

## API programática

```js
import { comboboxFor } from './combobox';

const cb = comboboxFor(document.querySelector('select[name="project_id"]'));
cb?.open();          // abre
cb?.close();         // cierra
cb?.destroy();       // restaura el <select> a estado normal y limpia
```

Si código externo cambia `select.value = …`, sólo necesita disparar el
evento `change`:

```js
select.value = '3';
select.dispatchEvent(new Event('change', { bubbles: true }));
// El combobox refresca el trigger y el aria-selected automáticamente.
```

## Teclado

| Tecla | Donde | Acción |
|---|---|---|
| `Enter`, `Space`, `↓` | Trigger | Abre el popover |
| `↑` / `↓` | Popover | Mueve la opción activa |
| `Enter` | Popover | Selecciona la opción activa, cierra |
| `Esc` | Popover | Cierra sin cambios, vuelve al trigger |
| `Tab` | Popover | Cierra y deja navegar al siguiente control |
| Letras | Search input | Filtra la lista |

## Estructura del módulo

- `Combobox.js` — clase, todo el ciclo de vida (mount, open, close, commit, destroy).
- `dom.js` — helper `el()` para construir nodos sin `innerHTML` ni JSX.
- `index.js` — `initComboboxes()` + `comboboxFor()` + observer.
- `README.md` — este archivo.

CSS en `resources/css/app.css` bajo el bloque `/* ─── Combobox ─── */`.
