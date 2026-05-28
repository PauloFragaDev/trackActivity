/**
 * Convierte TODOS los <select> de la página en un combobox con búsqueda
 * en línea via Tom Select. Patrón estándar (mismo principio que usan
 * apps como Linear o GitHub: el control de selección es siempre el
 * mismo, con o sin búsqueda activa según el número de opciones).
 *
 * El CSS base de Tom Select se importa en resources/css/app.css. Aquí
 * sólo añadimos la lógica de inicialización + observador para selects
 * inyectados por modales tras el load.
 *
 * Selects excluidos:
 *   - Los que tengan atributo `data-no-search` (cuando el desarrollador
 *     quiera mantener el select nativo).
 *   - Los `multiple` (no estamos usando ninguno hoy; si aparece, se
 *     añadiría plugins.remove_button al config).
 *   - Los que ya estén dentro de un Tom Select (.ts-wrapper).
 */
import TomSelect from 'tom-select';

const applyTo = (select) => {
    if (select.tomselect) return;                    // ya inicializado
    if (! (select instanceof HTMLSelectElement)) return;
    if (select.hasAttribute('data-no-search')) return;
    if (select.closest('.ts-wrapper')) return;        // dentro de un control ya migrado
    if (select.multiple) return;                      // por ahora sólo single

    try {
        // eslint-disable-next-line no-new
        new TomSelect(select, {
            allowEmptyOption: true,
            create: false,
            maxOptions: 500,
            searchField: ['text', 'value'],
            selectOnTab: true,
            // Si hay menos de 5 opciones, no mostramos el input de búsqueda
            // (sería ruido visual sin valor para listas cortas).
            controlInput: select.options.length > 5 ? '<input>' : null,
        });
    } catch (err) {
        console.warn('TomSelect failed for', select, err);
    }
};

const scan = (root) => {
    (root.querySelectorAll?.('select') ?? []).forEach(applyTo);
};

export function initSearchableSelects() {
    scan(document);

    // Observador: si un modal inyecta un select nuevo (ej. tras open),
    // lo migramos automáticamente.
    const obs = new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1 /* ELEMENT */) return;
                if (n instanceof HTMLSelectElement) applyTo(n);
                scan(n);
            });
        }
    });
    obs.observe(document.body, { childList: true, subtree: true });
}
