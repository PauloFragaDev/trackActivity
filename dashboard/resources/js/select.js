/**
 * Convierte todos los <select> de la página en un combobox accesible
 * usando Choices.js (https://github.com/Choices-js/Choices).
 *
 * Por qué Choices.js
 *   - ~2M descargas/semana, mantenida activamente.
 *   - CSS oficial completo (importado en app.css) — no hace falta
 *     reescribir layout ni positioning.
 *   - "Just works": `new Choices(select, { ... })` y listo.
 *
 * Lo único custom es la integración con la app:
 *   - data-no-search → mantiene el <select> nativo (mobile, casos raros).
 *   - Si hay <5 opciones, el campo de búsqueda se oculta (clutter para
 *     listas cortas).
 *   - MutationObserver para selects inyectados en modales después del load.
 */
import Choices from 'choices.js';

const INSTANCES = new WeakMap();

function applyTo(select) {
    if (! (select instanceof HTMLSelectElement)) return;
    if (select.multiple) return;
    if (select.hasAttribute('data-no-search')) return;
    if (INSTANCES.has(select)) return;
    // Choices marca el <select> con .choices__input cuando ya lo ha
    // procesado; nos saltamos también ese caso (defensa extra).
    if (select.classList.contains('choices__input')) return;

    try {
        const instance = new Choices(select, {
            searchEnabled:    select.options.length > 5,
            searchResultLimit: 50,
            shouldSort:       false,
            allowHTML:        false,
            itemSelectText:   '',                  // sin "Press to select"
            placeholderValue: null,
            noResultsText:    'Sin resultados',
            noChoicesText:    'Sin opciones',
        });
        INSTANCES.set(select, instance);
    } catch (err) {
        console.warn('Choices: no se pudo aplicar a', select, err);
    }
}

function scan(root) {
    (root.querySelectorAll?.('select') ?? []).forEach(applyTo);
}

/**
 * Asigna un valor a un <select> sincronizando Choices.js si está activo.
 *
 * Asignar `select.value = X` solo actualiza el atributo del DOM; Choices.js
 * no se entera y sigue mostrando el item antiguo (típicamente el placeholder)
 * en su control visual. Esta función usa la API oficial cuando hay instancia.
 *
 * Acepta '' / null / undefined para "sin valor" → vuelve al placeholder.
 */
export function setSelectValue(select, value) {
    if (! (select instanceof HTMLSelectElement)) return;
    const v = value == null ? '' : String(value);
    const instance = INSTANCES.get(select);
    if (instance) {
        // removeActiveItems() limpia el item actual (Choices respeta el
        // placeholder). setChoiceByValue('') es no-op, así que solo lo
        // llamamos si hay valor real.
        instance.removeActiveItems();
        if (v !== '') instance.setChoiceByValue(v);
    } else {
        select.value = v;
    }
}

export function initSelects() {
    scan(document);

    // Modales pueden inyectar selects tras el load. Observador del body.
    new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1 /* ELEMENT */) return;
                if (n instanceof HTMLSelectElement) applyTo(n);
                scan(n);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}
