import { Combobox } from './Combobox.js';

/**
 * Punto de entrada del componente Combobox.
 *
 * `initComboboxes()` escanea el DOM y reemplaza visualmente cada
 * <select> que cumpla los requisitos por una instancia de Combobox.
 * Un MutationObserver mantiene viva esa migración para selects
 * inyectados por modales o vistas que cargan después.
 *
 * Reglas de exclusión:
 *   - `data-no-combobox`: el desarrollador quiere mantener el <select> nativo.
 *   - `multiple`: por ahora no soportado; se queda como <select> nativo.
 *   - Ya ha sido migrado (clase combobox__source en el <select>).
 */

const INSTANCES = new WeakMap();

function applyTo(select) {
    if (! (select instanceof HTMLSelectElement)) return;
    if (select.multiple) return;
    if (select.hasAttribute('data-no-combobox')) return;
    if (INSTANCES.has(select)) return;
    if (select.classList.contains('combobox__source')) return;

    try {
        INSTANCES.set(select, new Combobox(select));
    } catch (err) {
        console.warn('Combobox: no se pudo aplicar a', select, err);
    }
}

function scan(root) {
    (root.querySelectorAll?.('select') ?? []).forEach(applyTo);
}

export function initComboboxes() {
    scan(document);

    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1 /* ELEMENT */) return;
                if (n instanceof HTMLSelectElement) applyTo(n);
                scan(n);
            });
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
}

// Expuesto para que código de la app (kanban.js, etc.) pueda obtener la
// instancia y disparar refresh manual cuando lo necesite.
export function comboboxFor(select) {
    return INSTANCES.get(select) ?? null;
}
