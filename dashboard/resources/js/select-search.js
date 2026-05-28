/**
 * Convierte cualquier <select data-searchable> en un combobox con búsqueda
 * en línea via Tom Select. Lazy-import: este módulo solo se carga cuando
 * hay al menos un select buscable en la página.
 *
 * Detalles:
 *   - Aplica también a selects DENTRO de <dialog> abiertos más tarde
 *     (se observa el DOM con MutationObserver).
 *   - El estilo vive en .ts-control / .ts-dropdown del CSS de la app
 *     (tema light/dark, .input look).
 *   - Conserva el value original del <select> para que el form lo lea
 *     al submit sin cambios server-side.
 */
import TomSelect from 'tom-select';

const applyTo = (select) => {
    if (select.tomselect) return;          // ya migrado
    if (! (select instanceof HTMLSelectElement)) return;
    if (select.multiple) return;            // por ahora sólo single
    try {
        // eslint-disable-next-line no-new
        new TomSelect(select, {
            allowEmptyOption: true,
            create: false,
            maxOptions: 200,
            // Buscamos por texto visible del option y por su value.
            searchField: ['text', 'value'],
            // Para que enter no submit el form al elegir.
            selectOnTab: true,
        });
    } catch (err) {
        console.warn('TomSelect failed for', select, err);
    }
};

const scan = (root) => {
    (root.querySelectorAll?.('select[data-searchable]') ?? []).forEach(applyTo);
};

export function initSearchableSelects() {
    scan(document);

    // Observador: si un modal inyecta un select nuevo (ej. tras open),
    // lo migramos automáticamente. Limitado al body para no espamear.
    const obs = new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach((n) => {
                if (n.nodeType === 1 /* ELEMENT */) {
                    if (n.matches?.('select[data-searchable]')) applyTo(n);
                    scan(n);
                }
            });
        }
    });
    obs.observe(document.body, { childList: true, subtree: true });
}
