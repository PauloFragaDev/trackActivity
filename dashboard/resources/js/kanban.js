/**
 * Tablero Kanban: alta/edición de tareas en modal y drag & drop entre
 * columnas (SortableJS), que persiste vía PATCH /tasks/{id}/move.
 */
import Sortable from 'sortablejs';

export function initKanban() {
    const newModal  = document.getElementById('task-new');
    const editModal = document.getElementById('task-edit');
    const csrf      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

    // Drag & drop entre columnas.
    document.querySelectorAll('[data-task-list]').forEach((list) => {
        new Sortable(list, {
            group: 'kanban',
            animation: 150,
            draggable: '.task-card',
            ghostClass: 'opacity-50',
            onEnd: (evt) => {
                if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;

                const card   = evt.item;
                const status = evt.to.dataset.taskList;

                fetch(`/tasks/${card.dataset.taskId}/move`, {
                    method: 'POST',
                    body: new URLSearchParams({
                        _token: csrf,
                        _method: 'PATCH',
                        status,
                        position: String(evt.newIndex),
                    }),
                }).catch(() => {});

                card.dataset.status = status;   // mantener el dato sincronizado
            },
        });
    });
}
