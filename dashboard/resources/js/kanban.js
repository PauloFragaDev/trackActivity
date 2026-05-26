/**
 * Tablero Kanban: alta/edición de tareas en modal, drag & drop entre
 * columnas (SortableJS) y subtareas gestionadas por AJAX desde el modal.
 */
import Sortable from 'sortablejs';

export function initKanban() {
    const newModal  = document.getElementById('task-new');
    const editModal = document.getElementById('task-edit');
    const csrf      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    /** Estado del modal de edición — ver renderSubtasks / renderComments. */
    let edit = null;   // { card, taskId, checkboxes: [...], comments: [...] }

    // ── helpers ──────────────────────────────────────────────
    const escape = (s) => {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    };

    /** POST con _method spoofing + CSRF — mismo patrón que el move del DnD. */
    const send = (url, method, params = {}) =>
        fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams({ _token: csrf, _method: method, ...params }),
        });

    // ── Subtareas (modal de edición) ─────────────────────────
    const renderSubtasks = () => {
        if (!edit || !editModal) return;
        const list     = editModal.querySelector('[data-subtasks-list]');
        const progress = editModal.querySelector('[data-subtasks-progress]');
        if (!list) return;

        const items = edit.checkboxes;
        const done  = items.filter((c) => c.checked).length;
        if (progress) progress.textContent = items.length ? `${done} / ${items.length}` : '';

        list.innerHTML = items.map((c) => `
            <li class="flex items-center gap-2 group">
                <input type="checkbox" class="cursor-pointer" data-subtask-toggle data-id="${c.id}" ${c.checked ? 'checked' : ''}>
                <span class="flex-1 ${c.checked ? 'line-through text-muted' : ''}">${escape(c.title)}</span>
                <button type="button" class="btn-ghost text-xs text-rose-500 opacity-0 group-hover:opacity-100"
                        data-subtask-delete data-id="${c.id}" aria-label="Borrar subtarea">×</button>
            </li>
        `).join('');

        syncCardBadge();
    };

    /** Mantiene la tarjeta del tablero al día con el estado actual del modal. */
    const syncCardBadge = () => {
        if (!edit?.card) return;
        const items = edit.checkboxes;
        edit.card.dataset.checkboxes = JSON.stringify(
            items.map((c) => ({ id: c.id, title: c.title, checked: c.checked }))
        );

        const badge   = edit.card.querySelector('[data-card-subtasks-badge]');
        const chipRow = edit.card.querySelector('.flex.flex-wrap.items-center');
        if (items.length === 0) {
            badge?.remove();
            return;
        }
        const done = items.filter((c) => c.checked).length;
        const text = `☑ ${done}/${items.length}`;
        const cls  = `chip ${done === items.length ? 'text-emerald-600 dark:text-emerald-400' : ''}`;
        if (badge) {
            badge.textContent = text;
            badge.className = cls;
        } else if (chipRow) {
            const span = document.createElement('span');
            span.setAttribute('data-card-subtasks-badge', '');
            span.title = 'Subtareas';
            span.className = cls;
            span.textContent = text;
            chipRow.appendChild(span);
        }
        // Si no había chipRow (tarjeta sin metadatos previos), el badge
        // aparecerá al refrescar la página — caso minoritario, aceptable.
    };

    const addSubtask = async (title) => {
        if (!edit) return;
        const res = await send(`/tasks/${edit.taskId}/checkboxes`, 'POST', { title });
        if (! res.ok) return;
        const item = await res.json();
        edit.checkboxes.push({ id: item.id, title: item.title, checked: !!item.checked });
        renderSubtasks();
    };

    const toggleSubtask = async (id, checked) => {
        if (!edit) return;
        const it = edit.checkboxes.find((c) => c.id == id);
        if (!it) return;
        it.checked = checked;
        renderSubtasks();   // optimista
        await send(`/tasks/${edit.taskId}/checkboxes/${id}`, 'PATCH', { checked: checked ? '1' : '0' });
    };

    const deleteSubtask = async (id) => {
        if (!edit) return;
        edit.checkboxes = edit.checkboxes.filter((c) => c.id != id);
        renderSubtasks();   // optimista
        await send(`/tasks/${edit.taskId}/checkboxes/${id}`, 'DELETE');
    };

    // ── Comentarios (modal de edición) ───────────────────────
    const renderComments = () => {
        if (!edit || !editModal) return;
        const list = editModal.querySelector('[data-comments-list]');
        if (!list) return;

        list.innerHTML = edit.comments.map((c) => {
            const when = c.created_at ? new Date(c.created_at).toLocaleString('es') : '';
            return `
                <li class="card p-2 group">
                    <div class="flex items-start justify-between gap-2">
                        <p class="whitespace-pre-wrap leading-relaxed flex-1">${escape(c.body)}</p>
                        <button type="button" class="btn-ghost text-xs text-rose-500 opacity-0 group-hover:opacity-100"
                                data-comment-delete data-id="${c.id}" aria-label="Borrar comentario">×</button>
                    </div>
                    <div class="text-xs text-faint mt-1">${escape(when)}</div>
                </li>`;
        }).join('');

        syncCommentsBadge();
    };

    const syncCommentsBadge = () => {
        if (!edit?.card) return;
        edit.card.dataset.comments = JSON.stringify(edit.comments);
        const badge = edit.card.querySelector('[data-card-comments-badge]');
        const chipRow = edit.card.querySelector('.flex.flex-wrap.items-center');
        if (edit.comments.length === 0) {
            badge?.remove();
            return;
        }
        const text = `💬 ${edit.comments.length}`;
        if (badge) {
            badge.textContent = text;
        } else if (chipRow) {
            const span = document.createElement('span');
            span.setAttribute('data-card-comments-badge', '');
            span.title = 'Comentarios';
            span.className = 'chip';
            span.textContent = text;
            chipRow.appendChild(span);
        }
    };

    const addComment = async (body) => {
        if (!edit) return;
        const res = await send(`/tasks/${edit.taskId}/comments`, 'POST', { body });
        if (! res.ok) return;
        const c = await res.json();
        edit.comments.push(c);
        renderComments();
    };

    const deleteComment = async (id) => {
        if (!edit) return;
        edit.comments = edit.comments.filter((c) => c.id != id);
        renderComments();
        await send(`/tasks/${edit.taskId}/comments/${id}`, 'DELETE');
    };

    if (editModal) {
        // Delegación: los handlers se mantienen aunque el contenido del <ul>
        // se reescriba en cada render.
        const list = editModal.querySelector('[data-subtasks-list]');
        list?.addEventListener('change', (e) => {
            if (e.target.matches('[data-subtask-toggle]')) {
                toggleSubtask(e.target.dataset.id, e.target.checked);
            }
        });
        list?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-subtask-delete]');
            if (btn) deleteSubtask(btn.dataset.id);
        });

        const addForm = editModal.querySelector('[data-subtasks-add]');
        addForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const input = addForm.querySelector('input[name="title"]');
            const title = (input?.value || '').trim();
            if (! title) return;
            addSubtask(title);
            input.value = '';
        });

        // Comentarios: delegación delete + submit del añadir.
        const commentsList = editModal.querySelector('[data-comments-list]');
        commentsList?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-comment-delete]');
            if (btn) deleteComment(btn.dataset.id);
        });
        const addComment_ = editModal.querySelector('[data-comments-add]');
        addComment_?.addEventListener('submit', (e) => {
            e.preventDefault();
            const ta = addComment_.querySelector('textarea[name="body"]');
            const body = (ta?.value || '').trim();
            if (! body) return;
            addComment(body);
            ta.value = '';
        });
    }

    // ── "+" de cada columna preselecciona esa columna en el modal de alta ──
    document.querySelectorAll('[data-add-status]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const select = newModal?.querySelector('[name="status"]');
            if (select) select.value = btn.dataset.addStatus;
        });
    });

    // ── ✎ de cada tarjeta: rellena y abre el modal de edición ──
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

                let labelIds = [];
                try { labelIds = JSON.parse(card.dataset.labels || '[]'); } catch {}
                editForm.querySelectorAll('input[name="label_ids[]"]').forEach((cb) => {
                    cb.checked = labelIds.includes(parseInt(cb.value, 10));
                });

                let checkboxes = [];
                let comments   = [];
                try { checkboxes = JSON.parse(card.dataset.checkboxes || '[]'); } catch {}
                try { comments   = JSON.parse(card.dataset.comments   || '[]'); } catch {}
                edit = { card, taskId: card.dataset.taskId, checkboxes, comments };
                renderSubtasks();
                renderComments();

                if (typeof editModal.showModal === 'function') editModal.showModal();
            });
        });
    }

    // ── Drag & drop entre columnas ───────────────────────────
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

                card.dataset.status = status;
            },
        });
    });
}
