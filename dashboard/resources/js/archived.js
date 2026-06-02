/**
 * Selección en lote de la vista /tasks/archived.
 *
 * - Checkbox por fila (`[data-row-check]`) + "seleccionar todas"
 *   (`[data-select-all]`, con estado indeterminado).
 * - La barra de acciones (`[data-bulk-actions]`) aparece sólo cuando hay al
 *   menos una fila marcada; el contador refleja cuántas.
 * - Los forms en lote (#bulk-restore-form / #bulk-force-form) van vacíos
 *   desde el server: aquí inyectamos los <input name="ids[]"> seleccionados
 *   justo antes de enviar. El de borrado pasa por el confirm genérico
 *   (data-confirm en app.js), así que el listener de inyección es idempotente
 *   y se vuelve a ejecutar tras confirmar.
 */
export function initArchived() {
    const root = document.querySelector('[data-archived]');
    if (! root) return;

    const selectAll  = root.querySelector('[data-select-all]');
    const countLabel = root.querySelector('[data-bulk-count]');
    const actions    = root.querySelector('[data-bulk-actions]');
    const rowChecks  = () => Array.from(root.querySelectorAll('[data-row-check]'));
    const selected   = () => rowChecks().filter((c) => c.checked);

    function sync() {
        const checks = rowChecks();
        const marked = checks.filter((c) => c.checked);
        const n = marked.length;

        countLabel.textContent = n === 0
            ? 'Seleccionar todas'
            : (n === 1 ? '1 seleccionada' : `${n} seleccionadas`);

        actions.classList.toggle('hidden', n === 0);

        // Estado del "seleccionar todas": vacío / lleno / indeterminado.
        selectAll.checked = n > 0 && n === checks.length;
        selectAll.indeterminate = n > 0 && n < checks.length;
    }

    selectAll?.addEventListener('change', () => {
        rowChecks().forEach((c) => { c.checked = selectAll.checked; });
        sync();
    });

    rowChecks().forEach((c) => c.addEventListener('change', sync));

    // Inyecta los ids seleccionados en un form en lote antes de enviarlo.
    function injectIds(form) {
        form.querySelectorAll('input[data-bulk-id]').forEach((el) => el.remove());
        selected().forEach((c) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = c.value;
            input.setAttribute('data-bulk-id', '');
            form.appendChild(input);
        });
    }

    ['bulk-restore-form', 'bulk-force-form'].forEach((id) => {
        const form = document.getElementById(id);
        if (! form) return;
        form.addEventListener('submit', (e) => {
            if (selected().length === 0) {
                e.preventDefault();
                window.toast?.('No hay tareas seleccionadas', 'warn');
                return;
            }
            injectIds(form);
        });
    });

    sync();
}
