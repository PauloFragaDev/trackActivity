/**
 * Tablero Kanban: alta/edición de tareas en modal.
 * (El drag & drop se añade en el hito K3.)
 */
export function initKanban() {
    const newModal  = document.getElementById('task-new');
    const editModal = document.getElementById('task-edit');

    // El "+" de cada columna preselecciona esa columna en el modal de alta.
    document.querySelectorAll('[data-add-status]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const select = newModal?.querySelector('[name="status"]');
            if (select) select.value = btn.dataset.addStatus;
        });
    });

    // El ✎ de cada tarjeta rellena y abre el modal de edición.
    if (editModal) {
        const editForm = editModal.querySelector('[data-task-edit-form]');
        const delForm  = editModal.querySelector('[data-task-delete-form]');

        document.querySelectorAll('[data-task-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const card = btn.closest('[data-task-id]');
                if (! card || ! editForm) return;

                editForm.action = `/tasks/${card.dataset.taskId}`;
                if (delForm) delForm.action = `/tasks/${card.dataset.taskId}`;

                const set = (name, value) => {
                    const field = editForm.querySelector(`[name="${name}"]`);
                    if (field) field.value = value ?? '';
                };
                set('title', card.dataset.title);
                set('description', card.dataset.description);
                set('status', card.dataset.status);
                set('priority', card.dataset.priority);
                set('project_id', card.dataset.project);
                set('due_date', card.dataset.due);

                if (typeof editModal.showModal === 'function') editModal.showModal();
            });
        });
    }
}
