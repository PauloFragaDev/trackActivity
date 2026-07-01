/**
 * Tablero Kanban v2: alta/edición en modal, drag & drop entre columnas
 * (SortableJS), subtareas + comentarios AJAX, búsqueda libre, filtro por
 * labels, inline-add por columna, colapsar columnas, auto-sort A-Z, y
 * un sub-link "Archivadas". La descripción es un textarea Markdown plano
 * (coherente con la extensión code-kanban).
 *
 * Toda la UX nueva (búsqueda, filtros, colapso, sort) es client-side con
 * persistencia en localStorage — no toca BBDD.
 *
 * Organización: `initKanban()` es un orquestador fino que cablea cada
 * sección (setup*). El estado compartido (la tarea en edición, refs del
 * DOM, helpers) vive a nivel de módulo; cada sección es una función
 * enfocada y testeable a ojo por separado.
 */
import Sortable from 'sortablejs';
import Swal from 'sweetalert2';
import { setSelectValue } from './select.js';

// ─── localStorage helpers ──────────────────────────────
const LS_SEARCH    = 'kanban-search';
const LS_LABELS    = 'kanban-label-filters';
const LS_COLLAPSED = 'kanban-collapsed-cols';
const LS_SORTED    = 'kanban-sorted-cols';

const readJson = (key, fallback) => {
    try { return JSON.parse(localStorage.getItem(key) || '') ?? fallback; }
    catch { return fallback; }
};
const writeJson = (key, value) => {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* ignore */ }
};

// SVG inline para las badges que se reconstruyen aqui (subtareas, comentarios).
// Mismos paths que el componente Blade <x-icon> — la card mezclaba emoji
// (☑/💬) con el set SVG del resto de la UI; esto las unifica.
const ICON = {
    check: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-3 h-3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>',
    chat:  '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3 h-3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>',
};

// ─── Estado y refs compartidos (una instancia por página) ──
let edit = null;            // { card, taskId, checkboxes, comments } | null
let board = null;
let newModal = null;
let editModal = null;
let csrf = '';
let MY_TOKEN = '';          // token del usuario para distinguir comentarios propios

const escape = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
};

// Escapa HTML y luego resalta @menciones conocidas en azul.
const renderBody = (s) => {
    let html = escape(s);
    const members = window.TEAM_MEMBERS || [];
    // Ordenar por longitud descendente para que "@Paulo Fraga" no sea solapado
    // por un hipotético "@Paulo" más corto antes de que se pueda encontrar.
    [...members]
        .sort((a, b) => b.name.length - a.name.length)
        .forEach(m => {
            const safeName = m.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            html = html.replace(
                new RegExp('@' + safeName, 'g'),
                `<span class="font-semibold text-sky-600 dark:text-sky-400">@${escape(m.name)}</span>`,
            );
        });
    return html;
};

const send = (url, method, params = {}) => {
    // Marca de mutación propia: el polling JS la usa para no recargar
    // por un cambio que YO mismo acabo de hacer (drag, subtask toggle,
    // comentario, etc.). Ver `initLivePolling`.
    window.__taskMutationAt = Date.now();
    return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new URLSearchParams({ _token: csrf, _method: method, ...params }),
    });
};

// ─── Subtareas ────────────────────────────────────────────
function renderSubtasks() {
    if (! edit || ! editModal) return;
    const list     = editModal.querySelector('[data-subtasks-list]');
    const progress = editModal.querySelector('[data-subtasks-progress]');
    if (! list) return;

    const items = edit.checkboxes;
    const done  = items.filter((c) => c.checked).length;
    if (progress) progress.textContent = items.length ? `${done} / ${items.length}` : '';

    list.innerHTML = items.map((c) => `
        <li class="flex items-start gap-2 group py-0.5">
            <input type="checkbox" class="cursor-pointer shrink-0 mt-0.5" data-subtask-toggle data-id="${c.id}" ${c.checked ? 'checked' : ''}>
            <span class="flex-1 min-w-0 break-words ${c.checked ? 'line-through text-muted' : ''}">${escape(c.title)}</span>
            <button type="button" class="shrink-0 mt-0.5 leading-none text-rose-500 hover:text-rose-700 dark:text-rose-400 opacity-0 group-hover:opacity-100 rounded px-0.5"
                    data-subtask-delete data-id="${c.id}" aria-label="Borrar subtarea">×</button>
        </li>
    `).join('');
    syncCardBadge();
}

function syncCardBadge() {
    if (! edit?.card) return;
    const items = edit.checkboxes;
    edit.card.dataset.checkboxes = JSON.stringify(
        items.map((c) => ({ id: c.id, title: c.title, checked: c.checked }))
    );
    const badge   = edit.card.querySelector('[data-card-subtasks-badge]');
    const chipRow = edit.card.querySelector('.flex.flex-wrap.items-center');
    if (items.length === 0) { badge?.remove(); return; }
    const done = items.filter((c) => c.checked).length;
    const html = `${ICON.check}${done}/${items.length}`;
    const cls  = `chip ${done === items.length ? 'text-emerald-600 dark:text-emerald-400' : ''}`;
    if (badge) { badge.innerHTML = html; badge.className = cls; }
    else if (chipRow) {
        const span = document.createElement('span');
        span.setAttribute('data-card-subtasks-badge', '');
        span.title = 'Subtareas';
        span.className = cls;
        span.innerHTML = html;
        chipRow.appendChild(span);
    }
}

const checkboxBase = () => (window.KANBAN_ROUTES && window.KANBAN_ROUTES.checkboxStore) || '/tasks';
const addSubtask = async (title) => {
    if (! edit) return;
    const res = await send(`${checkboxBase()}/${edit.taskId}/checkboxes`, 'POST', { title });
    if (! res.ok) return;
    const item = await res.json();
    edit.checkboxes.push({ id: item.id, title: item.title, checked: !! item.checked });
    renderSubtasks();
};
const toggleSubtask = async (id, checked) => {
    if (! edit) return;
    const it = edit.checkboxes.find((c) => c.id == id);
    if (! it) return;
    it.checked = checked;
    renderSubtasks();
    await send(`${checkboxBase()}/${edit.taskId}/checkboxes/${id}`, 'PATCH', { checked: checked ? '1' : '0' });
};
const deleteSubtask = async (id) => {
    if (! edit) return;
    const { isConfirmed } = await Swal.fire({
        buttonsStyling: false,
        reverseButtons: true,
        customClass: { popup: 'app-swal', confirmButton: 'btn-danger', cancelButton: 'btn-ghost' },
        title: '¿Eliminar subtarea?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    });
    if (! isConfirmed) return;

    edit.checkboxes = edit.checkboxes.filter((c) => c.id != id);
    renderSubtasks();
    await send(`${checkboxBase()}/${edit.taskId}/checkboxes/${id}`, 'DELETE');
};

// ─── Comentarios (panel lateral tipo chat) ─────────────────
// Token de esta instalación: los comentarios cuyo author_token coincide
// son "míos" y se alinean a la derecha.
function renderComments() {
    if (! edit || ! editModal) return;
    const list = editModal.querySelector('[data-comments-list]');
    if (! list) return;
    list.innerHTML = edit.comments.map((c) => {
        const when = c.created_at ? new Date(c.created_at).toLocaleString('es') : '';
        const mine = c.author_token && c.author_token === MY_TOKEN;
        const who  = escape(c.author_name || 'Sin nombre');
        return `
            <li class="task-chat__msg ${mine ? 'task-chat__msg--mine' : ''} group">
                <div class="task-chat__bubble">
                    <div class="task-chat__head">
                        <span class="task-chat__who">${who}</span>
                        <span class="task-chat__when">${escape(when)}</span>
                        <button type="button" class="task-chat__del opacity-0 group-hover:opacity-100"
                                data-comment-delete data-id="${c.id}" aria-label="Borrar comentario">×</button>
                    </div>
                    <p class="task-chat__text">${renderBody(c.body)}</p>
                </div>
            </li>`;
    }).join('');
    list.scrollTop = list.scrollHeight;
    syncCommentsBadge();
}
function syncCommentsBadge() {
    if (! edit?.card) return;
    edit.card.dataset.comments = JSON.stringify(edit.comments);
    const badge = edit.card.querySelector('[data-card-comments-badge]');
    const chipRow = edit.card.querySelector('.flex.flex-wrap.items-center');
    if (edit.comments.length === 0) { badge?.remove(); return; }
    const html = `${ICON.chat}${edit.comments.length}`;
    if (badge) badge.innerHTML = html;
    else if (chipRow) {
        const span = document.createElement('span');
        span.setAttribute('data-card-comments-badge', '');
        span.title = 'Comentarios';
        span.className = 'chip';
        span.innerHTML = html;
        chipRow.appendChild(span);
    }
}
const commentBase = () => (window.KANBAN_ROUTES && window.KANBAN_ROUTES.commentStore) || '/tasks';
const addComment = async (body) => {
    if (! edit) return;
    const res = await send(`${commentBase()}/${edit.taskId}/comments`, 'POST', { body });
    if (! res.ok) return;
    const c = await res.json();
    edit.comments.push(c);
    renderComments();
};
const deleteComment = async (id) => {
    if (! edit) return;
    const { isConfirmed } = await Swal.fire({
        buttonsStyling: false,
        reverseButtons: true,
        customClass: { popup: 'app-swal', confirmButton: 'btn-danger', cancelButton: 'btn-ghost' },
        title: '¿Eliminar comentario?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    });
    if (! isConfirmed) return;

    edit.comments = edit.comments.filter((c) => c.id != id);
    renderComments();
    await send(`${commentBase()}/${edit.taskId}/comments/${id}`, 'DELETE');
};

// ─── Conteo / colapso / orden de columnas (compartidos con el drag) ──
function updateColumnCounts() {
    document.querySelectorAll('[data-task-column]').forEach((col) => {
        const total   = col.querySelectorAll('.task-card').length;
        const hidden  = col.querySelectorAll('.task-card.is-filtered-out').length;
        const counter = col.querySelector('[data-column-count]');
        if (counter) counter.textContent = total - hidden;
    });
}

function setCollapsed(col, isCollapsed) {
    col.classList.toggle('task-column--collapsed', isCollapsed);
    const status = col.dataset.taskColumn;
    const set = new Set(readJson(LS_COLLAPSED, []));
    if (isCollapsed) set.add(status); else set.delete(status);
    writeJson(LS_COLLAPSED, [...set]);
}

function setSorted(col, isSorted) {
    col.classList.toggle('task-column--sorted', isSorted);
    const status = col.dataset.taskColumn;
    const set = new Set(readJson(LS_SORTED, []));
    if (isSorted) set.add(status); else set.delete(status);
    writeJson(LS_SORTED, [...set]);
    if (isSorted) reorderColumnAlpha(col);
}
function reorderColumnAlpha(col) {
    const list = col.querySelector('[data-task-list]');
    if (! list) return;
    const cards = [...list.querySelectorAll('.task-card')];
    cards.sort((a, b) => (a.dataset.title || '').localeCompare(b.dataset.title || '', 'es', { sensitivity: 'base' }));
    cards.forEach((c) => list.appendChild(c));
}

// ─── Modal de edición: handlers de subtareas y comentarios ────
function setupModalForms() {
    if (! editModal) return;

    const mainForm = editModal.querySelector('[data-task-edit-form]');
    mainForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (! edit) return;
        const saveBtn = document.getElementById('btn-modal-save');
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Guardando…'; }

        const params = new URLSearchParams({ _token: csrf, _method: 'PATCH' });
        new FormData(mainForm).forEach((v, k) => params.append(k, v));

        try {
            const res  = await fetch(mainForm.action, {
                method:  'POST',
                headers: { Accept: 'application/json' },
                body:    params,
            });
            const data = await res.json();
            if (data.ok && data.html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = data.html.trim();
                const newCard = tmp.firstElementChild;
                if (newCard) {
                    const newStatus = newCard.dataset.status;
                    edit.card.remove();
                    insertCard(data.html, newStatus);
                }
            }
            editModal.close();
            window.toast?.('Tarea actualizada.');
        } catch {
            // fall through — native submit would have worked anyway
        } finally {
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Guardar'; }
        }
    });

    const list = editModal.querySelector('[data-subtasks-list]');
    list?.addEventListener('change', (e) => {
        if (e.target.matches('[data-subtask-toggle]')) toggleSubtask(e.target.dataset.id, e.target.checked);
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
    // Enter envía (como un chat); Shift+Enter inserta salto de línea.
    addComment_?.querySelector('textarea[name="body"]')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && ! e.shiftKey) {
            e.preventDefault();
            addComment_.requestSubmit();
        }
    });
}

// ─── "+" de columna: preselecciona el status ────────────────────
function setupColumnAddButtons() {
    document.querySelectorAll('[data-add-status]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const select = newModal?.querySelector('[name="status"]');
            if (select) select.value = btn.dataset.addStatus;
            const textarea = newModal?.querySelector('textarea[name="description"]');
            if (textarea) textarea.value = '';
        });
    });
}

// ─── ✎ de cada tarjeta: rellena y abre modal de edición ────────
function setupEditModalOpen() {
    if (! editModal) return;
    const editForm = editModal.querySelector('[data-task-edit-form]');
    const delForm  = editModal.querySelector('[data-task-delete-form]');

    // Delegación en el board para que las cards creadas por AJAX también funcionen.
    board.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-task-edit]');
        if (! btn || ! editForm) return;

        const card = btn.closest('[data-task-id]');
        if (! card) return;

        const taskBase = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.update) || '/tasks';
        editForm.action = `${taskBase}/${card.dataset.taskId}`;
        if (delForm) delForm.action = `${taskBase}/${card.dataset.taskId}`;
        const transferBtn = document.getElementById('btn-transfer-to-team');
        if (transferBtn) transferBtn.dataset.taskId = card.dataset.taskId;

        // Para <select> controlados por Choices.js, asignar .value
        // no actualiza la UI de la librería (sigue mostrando el
        // placeholder). setSelectValue conoce la instancia y la
        // sincroniza vía API oficial.
        const set = (name, value) => {
            const field = editForm.querySelector(`[name="${name}"]`);
            if (! field) return;
            if (field instanceof HTMLSelectElement) {
                setSelectValue(field, value);
            } else {
                field.value = value ?? '';
            }
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
        setModalViewMode();
        renderModalCreator(card);

        if (typeof editModal.showModal === 'function') editModal.showModal();
    });
}

// ─── Drag & drop entre columnas ────────────────────────────
function setupDragAndDrop() {
    const clearDropTargets = () => {
        document.querySelectorAll('.task-list').forEach((l) => l.classList.remove('is-drop-target'));
    };
    document.querySelectorAll('[data-task-list]').forEach((list) => {
        new Sortable(list, {
            group:     'kanban',
            animation: 200,
            // SortableJS easing por defecto es lineal — la misma curva
            // fuerte (`--ease-out`) que el resto de UI hace que las
            // tarjetas reordenándose se sientan parte del sistema.
            easing:    'cubic-bezier(0.23, 1, 0.32, 1)',
            draggable: '.task-card',
            ghostClass: 'is-dragging-ghost',
            onMove: (evt) => {
                clearDropTargets();
                // Si la columna destino está colapsada, la auto-expandimos.
                const col = evt.to.closest('[data-task-column]');
                if (col?.classList.contains('task-column--collapsed')) {
                    setCollapsed(col, false);
                }
                evt.to.classList.add('is-drop-target');
                return true;
            },
            onEnd: (evt) => {
                clearDropTargets();
                if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
                const card   = evt.item;
                const status = evt.to.dataset.taskList;

                window.__taskMutationAt = Date.now();
                const moveUrl = `${(window.KANBAN_ROUTES && window.KANBAN_ROUTES.move) || '/tasks'}/${card.dataset.taskId}/move`;
                fetch(moveUrl, {
                    method: 'POST',
                    body: new URLSearchParams({
                        _token: csrf, _method: 'PATCH', status, position: String(evt.newIndex),
                    }),
                }).catch(() => {});
                card.dataset.status = status;

                // Mover desactiva el auto-sort de la columna destino (acción manual).
                const targetCol = evt.to.closest('[data-task-column]');
                if (targetCol?.classList.contains('task-column--sorted')) {
                    setSorted(targetCol, false);
                }
                updateColumnCounts();
            },
        });
    });
}

// ─── Búsqueda libre + filtro por labels ────────────────────
function setupFilters() {
    const searchInput     = document.querySelector('[data-task-search]');
    const searchClear     = document.querySelector('[data-task-search-clear]');
    // Wrapper del botón de limpiar (lo escondemos a él, no al botón):
    // es el <span class="input-group__suffix"> que envuelve el botón.
    const searchClearWrap = document.querySelector('[data-task-search-clear-wrap]')
                         ?? searchClear;
    const labelChips      = document.querySelectorAll('[data-label-filter]');
    const labelsClear     = document.querySelector('[data-label-filters-clear]');
    const summary         = document.querySelector('[data-filter-summary]');

    let activeLabels = new Set(readJson(LS_LABELS, []));
    const restoredSearch = localStorage.getItem(LS_SEARCH) || '';

    if (searchInput) searchInput.value = restoredSearch;
    if (searchClearWrap) searchClearWrap.classList.toggle('hidden', ! restoredSearch);
    labelChips.forEach((c) => {
        if (activeLabels.has(parseInt(c.dataset.labelFilter, 10))) c.classList.add('is-active');
    });
    if (labelsClear) labelsClear.classList.toggle('hidden', activeLabels.size === 0);

    function applyFilters() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        const hasQ = q.length > 0;
        const hasLabels = activeLabels.size > 0;
        let visible = 0, hidden = 0;

        document.querySelectorAll('.task-card').forEach((card) => {
            let show = true;
            if (hasQ) {
                const title = (card.dataset.title || '').toLowerCase();
                show = title.includes(q);
            }
            if (show && hasLabels) {
                let ids = [];
                try { ids = JSON.parse(card.dataset.labels || '[]'); } catch {}
                // OR entre labels: con que tenga una de las activas, basta.
                show = ids.some((id) => activeLabels.has(id));
            }
            card.classList.toggle('is-filtered-out', ! show);
            if (show) visible++; else hidden++;
        });

        if (summary) {
            summary.textContent = (hasQ || hasLabels)
                ? `${visible} visible${visible === 1 ? '' : 's'} · ${hidden} oculta${hidden === 1 ? '' : 's'}`
                : '';
        }
        updateColumnCounts();
    }

    searchInput?.addEventListener('input', () => {
        try { localStorage.setItem(LS_SEARCH, searchInput.value); } catch {}
        searchClearWrap?.classList.toggle('hidden', searchInput.value.length === 0);
        applyFilters();
    });
    searchClear?.addEventListener('click', () => {
        if (! searchInput) return;
        searchInput.value = '';
        try { localStorage.removeItem(LS_SEARCH); } catch {}
        searchClearWrap?.classList.add('hidden');
        applyFilters();
        searchInput.focus();
    });
    labelChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            const id = parseInt(chip.dataset.labelFilter, 10);
            if (activeLabels.has(id)) activeLabels.delete(id);
            else activeLabels.add(id);
            chip.classList.toggle('is-active', activeLabels.has(id));
            writeJson(LS_LABELS, [...activeLabels]);
            labelsClear?.classList.toggle('hidden', activeLabels.size === 0);
            applyFilters();
        });
    });
    labelsClear?.addEventListener('click', () => {
        activeLabels.clear();
        labelChips.forEach((c) => c.classList.remove('is-active'));
        writeJson(LS_LABELS, []);
        labelsClear.classList.add('hidden');
        applyFilters();
    });

    applyFilters();
}

// ─── Inline-add por columna (AJAX + card optimista) ────────
function setupInlineAdd() {
    document.querySelectorAll('[data-task-inline-add]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = form.querySelector('input[name="title"]');
            const title = (input?.value || '').trim();
            if (! title) return;
            const status = form.dataset.status;

            // Card fantasma mientras persiste en servidor
            const list = document.querySelector(`[data-task-list="${status}"]`);
            const ghost = document.createElement('div');
            ghost.className = 'task-card card p-2.5 opacity-40 pointer-events-none';
            ghost.innerHTML = `<p class="text-sm font-medium leading-snug">${escape(title)}</p>`;
            list?.appendChild(ghost);
            input.value = '';
            updateColumnCounts();

            try {
                const fd = new FormData(form);
                fd.set('title', title);
                window.__taskMutationAt = Date.now();
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: fd,
                });
                if (! res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                if (data.ok && data.html) {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = data.html.trim();
                    const card = tmp.firstElementChild;
                    if (card) ghost.replaceWith(card);
                    else ghost.remove();
                } else {
                    ghost.remove();
                }
            } catch {
                ghost.remove();
                input.value = title;
            }
            updateColumnCounts();
        });
    });
}

// ─── Modal nueva tarea (AJAX) ───────────────────────────────
function setupNewTask() {
    if (! newModal) return;
    const form = newModal.querySelector('form');
    if (! form) return;
    const submitBtn = form.querySelector('[type="submit"]');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const title = (form.querySelector('[name="title"]')?.value || '').trim();
        if (! title) return;
        const status = form.querySelector('[name="status"]')?.value || 'todo';

        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creando…'; }

        try {
            const fd = new FormData(form);
            window.__taskMutationAt = Date.now();
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: fd,
            });
            if (! res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (data.ok && data.html) {
                insertCard(data.html, status);
                form.reset();
                newModal.close();
            }
        } catch {
            // En caso de error, submit nativo como fallback
            window.location.reload();
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Crear'; }
        }
    });
}

// ─── Archivar tarea desde el modal (AJAX) ──────────────────
function setupDeleteTask() {
    if (! editModal) return;
    const archiveBtn = document.getElementById('btn-modal-archive');
    if (! archiveBtn) return;

    archiveBtn.addEventListener('click', async () => {
        const taskBase = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.update) || '/tasks';
        const taskId   = edit?.taskId;
        const card     = edit?.card;
        if (! taskId) return;

        // Cerrar antes del Swal para evitar z-index issues con el top-layer del <dialog>
        editModal.close();

        const { isConfirmed } = await Swal.fire({
            buttonsStyling: false,
            reverseButtons: true,
            customClass: { popup: 'app-swal', confirmButton: 'btn-danger', cancelButton: 'btn-ghost' },
            title: '¿Archivar tarea?',
            text: 'La podrás restaurar más adelante.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, archivar',
            cancelButtonText: 'Cancelar',
        });

        if (! isConfirmed) return;

        try {
            window.__taskMutationAt = Date.now();
            const res = await fetch(`${taskBase}/${taskId}`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: new URLSearchParams({ _token: csrf, _method: 'DELETE' }),
            });
            if (! res.ok) throw new Error();
            card?.remove();
            updateColumnCounts();
            edit = null;
            window.toast?.('Tarea archivada.');
        } catch {
            window.location.reload();
        }
    });
}

function insertCard(html, status) {
    const list = document.querySelector(`[data-task-list="${status}"]`);
    if (! list) return;
    const tmp = document.createElement('div');
    tmp.innerHTML = html.trim();
    const card = tmp.firstElementChild;
    if (card) list.appendChild(card);
    updateColumnCounts();
}

// ─── Vista / edición del modal ─────────────────────────────
function setModalViewMode() {
    if (! editModal) return;
    const form      = editModal.querySelector('[data-task-edit-form]');
    const subtasks  = editModal.querySelector('[data-task-subtasks]');
    const btnEdit   = document.getElementById('btn-modal-edit');
    const btnCancel = document.getElementById('btn-modal-cancel-edit');
    const btnSave   = document.getElementById('btn-modal-save');

    if (form)    { form.style.pointerEvents = 'none'; form.style.opacity = '0.65'; }
    if (subtasks){ subtasks.style.pointerEvents = 'none'; subtasks.style.opacity = '0.65'; }
    if (btnEdit)   btnEdit.classList.remove('hidden');
    if (btnCancel) btnCancel.classList.add('hidden');
    if (btnSave)   btnSave.classList.add('hidden');
}

function setModalEditMode() {
    if (! editModal) return;
    const form      = editModal.querySelector('[data-task-edit-form]');
    const subtasks  = editModal.querySelector('[data-task-subtasks]');
    const btnEdit   = document.getElementById('btn-modal-edit');
    const btnCancel = document.getElementById('btn-modal-cancel-edit');
    const btnSave   = document.getElementById('btn-modal-save');

    if (form)    { form.style.pointerEvents = ''; form.style.opacity = ''; }
    if (subtasks){ subtasks.style.pointerEvents = ''; subtasks.style.opacity = ''; }
    if (btnEdit)   btnEdit.classList.add('hidden');
    if (btnCancel) btnCancel.classList.remove('hidden');
    if (btnSave)   btnSave.classList.remove('hidden');
}

function renderModalCreator(card) {
    const infoEl = document.getElementById('modal-creator-info');
    if (! infoEl) return;
    const createdById = parseInt(card.dataset.createdBy, 10);
    if (! createdById || ! Array.isArray(window.TEAM_MEMBERS)) {
        infoEl.innerHTML = '';
        return;
    }
    const member = window.TEAM_MEMBERS.find((m) => m.id === createdById);
    if (! member) { infoEl.innerHTML = ''; return; }
    infoEl.innerHTML = `
        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white select-none flex-shrink-0"
              style="background-color:${escape(member.color)}"
              title="${escape(member.name)}">${escape(member.initials)}</span>
        <span>Creada por <strong class="font-medium">${escape(member.name)}</strong></span>`;
}

function setupViewEditToggle() {
    if (! editModal) return;
    const btnEdit   = document.getElementById('btn-modal-edit');
    const btnCancel = document.getElementById('btn-modal-cancel-edit');

    btnEdit?.addEventListener('click', () => setModalEditMode());
    btnCancel?.addEventListener('click', () => setModalViewMode());
    editModal.addEventListener('close', () => setModalViewMode());
}

// ─── Colapsar columnas ──────────────────────────────────────
function setupCollapse() {
    const initialCollapsed = new Set(readJson(LS_COLLAPSED, []));
    document.querySelectorAll('[data-task-column]').forEach((col) => {
        if (initialCollapsed.has(col.dataset.taskColumn)) {
            col.classList.add('task-column--collapsed');
        }
        col.querySelector('[data-task-column-toggle]')?.addEventListener('click', (e) => {
            // No colapsar si el click vino de un botón del header (sort, +).
            if (e.target.closest('button[onclick]')) return;
            setCollapsed(col, ! col.classList.contains('task-column--collapsed'));
        });
    });
}

// ─── Auto-sort A↓Z por columna ──────────────────────────────
function setupSort() {
    const initialSorted = new Set(readJson(LS_SORTED, []));
    document.querySelectorAll('[data-task-column]').forEach((col) => {
        if (initialSorted.has(col.dataset.taskColumn)) {
            col.classList.add('task-column--sorted');
            reorderColumnAlpha(col);
        }
        col.querySelector('[data-task-column-sort]')?.addEventListener('click', () => {
            setSorted(col, ! col.classList.contains('task-column--sorted'));
        });
    });
}

// ─── Live refresh vía polling ligero ──────────────────────────────
//
// Polling JS cada 5s a /tasks/peek (devuelve sólo { latest }). Si el
// timestamp difiere del anterior, hacemos reload condicional.
//
// ¿Por qué polling y no SSE? `php artisan serve` tiene workers
// limitados; una conexión SSE bloquea 1 worker permanente. Si además
// la extensión code-kanban tiene su SSE abierto, los PATCH del board
// se quedan sin workers libres. Polling termina cada request en ms y
// libera el worker — coexiste sin problema con el SSE de la extensión.
//
// Para no interrumpir al usuario, NO recargamos si está en mitad de
// algo (dialog abierto, input con texto, focus en textarea). En esos
// casos queda pendiente y se aplica al "soltarse".
function initLivePolling() {
    const projectFilter = new URLSearchParams(window.location.search).get('project');
    const peekBase = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.peek) || '/tasks/peek';
    const url = projectFilter
        ? `${peekBase}?project=${encodeURIComponent(projectFilter)}`
        : peekBase;

    let pendingReload = false;
    let lastSeen = null;
    // Ventana de gracia tras una mutación propia (drag, AJAX) para que
    // el polling no haga reload por un cambio que YO mismo acabo de
    // hacer (perdería el contexto de UI que esté manipulando ahora).
    // Cualquier mutador del board actualiza este timestamp.
    window.__taskMutationAt = 0;

    const isUserBusy = () => {
        // Dialog abierto cuenta como "ocupado" — no recargar mientras
        // el usuario edita en el modal.
        if (document.querySelector('dialog[open]')) return true;
        // Inputs con TEXTO en marcha: no perder lo que está escribiendo.
        // El foco sin texto no bloquea (sería casi siempre verdad y
        // bloquearía cualquier reload aunque hiciera falta).
        const active = document.activeElement;
        if (active?.matches?.('input[type="search"], input[type="text"], textarea')) {
            return (active.value || '').trim() !== '';
        }
        return false;
    };

    const doReload = () => {
        if (isUserBusy()) { pendingReload = true; return; }
        window.location.reload();
    };

    document.addEventListener('focusout', () => {
        if (pendingReload && ! isUserBusy()) setTimeout(doReload, 200);
    });
    document.addEventListener('close', () => {
        if (pendingReload && ! isUserBusy()) setTimeout(doReload, 200);
    }, true);

    const tick = async () => {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (! res.ok) return;
            const { latest } = await res.json();
            if (lastSeen === null) {
                lastSeen = latest;
                return;
            }
            if (latest && latest !== lastSeen) {
                // Si el cambio coincide con una mutación que el propio
                // cliente acaba de hacer (≤3 s atrás), no recargar:
                // adoptamos el timestamp y seguimos.
                if (Date.now() - window.__taskMutationAt < 3000) {
                    lastSeen = latest;
                    return;
                }
                lastSeen = latest;
                doReload();
            }
        } catch {
            // Red caída/server reiniciando — silencioso; siguiente tick reintenta.
        }
    };

    tick();
    setInterval(tick, 5000);
    window.addEventListener('focus', () => { tick(); });
}

// ─── Drag de columnas (reordenar) ───────────────────────────────────
function setupColumnDrag() {
    const container = document.querySelector('[data-columns-container]');
    if (!container || !window.KANBAN_ROUTES?.updateColumns) return;

    Sortable.create(container, {
        animation: 150,
        handle: '[data-column-handle]',
        ghostClass: 'opacity-30',
        onEnd() {
            const order = [...container.querySelectorAll('[data-column]')]
                .map((el) => el.dataset.column);
            fetch(window.KANBAN_ROUTES.updateColumns, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body:    JSON.stringify({ columns: order }),
            }).catch(() => {});
        },
    });
}

export function initKanban() {
    board = document.querySelector('[data-task-board]');
    if (! board) return;
    newModal  = document.getElementById('task-new');
    editModal = document.getElementById('task-edit');
    csrf      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    MY_TOKEN  = document.querySelector('meta[name="user-token"]')?.content || '';

    setupModalForms();
    setupColumnAddButtons();
    setupEditModalOpen();
    setupViewEditToggle();
    setupDragAndDrop();
    setupFilters();
    setupInlineAdd();
    setupNewTask();
    setupDeleteTask();
    setupCollapse();
    setupSort();
    setupColumnDrag();
    initLivePolling();
    initIdentity();
    initTransfer();
    initTabSkeleton();
}

function initIdentity() {
    const modal = document.getElementById('identity-modal');

    const identityStore = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.identityStore) || '/team/identity';
    const identityClear = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.identityClear) || '/team/identity';

    // El wiring del modal (selector de identidad) solo tiene sentido si el
    // modal existe en el DOM — en modo `team_only` no se renderiza y la
    // identidad ya viene resuelta por el middleware, así que este bloque
    // se salta entero.
    if (modal) {
        modal.querySelectorAll('.identity-option').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const memberId   = btn.dataset.memberId;
                const memberName = btn.dataset.memberName;

                try {
                    await fetch(identityStore, {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body:    JSON.stringify({ member_id: memberId }),
                    });
                } catch {}

                localStorage.setItem('team_member_id',   memberId);
                localStorage.setItem('team_member_name', memberName);
                window.TEAM_MEMBER_ID   = memberId;
                window.TEAM_MEMBER_NAME = memberName;
                MY_TOKEN = String(memberId);

                // Actualizar estado visual de los botones del modal
                modal.querySelectorAll('.identity-option').forEach((b) => {
                    const active = b.dataset.memberId === memberId;
                    b.classList.toggle('bg-ink-100', active);
                    b.classList.toggle('dark:bg-ink-800', active);
                    b.classList.toggle('ring-2', active);
                    b.classList.toggle('ring-inset', active);
                    b.classList.toggle('ring-ink-300', active);
                    b.classList.toggle('dark:ring-ink-600', active);
                    b.classList.toggle('hover:bg-ink-100', !active);
                    b.classList.toggle('dark:hover:bg-ink-800', !active);
                    b.querySelector('.tu-badge')?.remove();
                    if (active) {
                        const badge = document.createElement('span');
                        badge.className = 'tu-badge text-xs font-semibold px-2 py-0.5 rounded-full text-white';
                        const avatar = b.querySelector('span[style]');
                        badge.style.backgroundColor = avatar?.style.backgroundColor || '#666';
                        badge.textContent = 'Tú';
                        b.appendChild(badge);
                    }
                });

                renderPastilla(memberId);
                modal.close();
            });
        });

        // "Cambiar" — delegación sobre el contenedor de la pastilla
        document.getElementById('identity-pastilla')?.addEventListener('click', (e) => {
            if (! e.target.closest('#btn-change-identity')) return;
            clearIdentity(identityClear, modal);
        });
    }

    // ── Determinar el estado inicial de la pastilla ───────────────
    // Esto debe ejecutarse siempre en modo 'team', exista o no el modal
    // (en team_only no existe, pero la pastilla igualmente debe pintarse
    // con el botón de "Cerrar sesión").
    if (window.KANBAN_MODE !== 'team') return;

    if (window.TEAM_MEMBER_ID) {
        // Sesión activa: usar directamente
        MY_TOKEN = String(window.TEAM_MEMBER_ID);
        renderPastilla(window.TEAM_MEMBER_ID);
        return;
    }

    modal?.showModal();
}

async function clearIdentity(identityClear, modal) {
    try {
        await fetch(identityClear, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
    } catch {}
    localStorage.removeItem('team_member_id');
    localStorage.removeItem('team_member_name');
    window.TEAM_MEMBER_ID   = '';
    window.TEAM_MEMBER_NAME = '';
    MY_TOKEN = document.querySelector('meta[name="user-token"]')?.content || '';

    // Limpiar estado visual del modal
    document.getElementById('identity-modal')?.querySelectorAll('.identity-option').forEach((b) => {
        b.classList.remove('bg-ink-100', 'dark:bg-ink-800', 'ring-2', 'ring-inset', 'ring-ink-300', 'dark:ring-ink-600');
        b.classList.add('hover:bg-ink-100', 'dark:hover:bg-ink-800');
        b.querySelector('.tu-badge')?.remove();
    });

    renderPastilla(null);
    modal.showModal();
}

function renderPastilla(memberId) {
    const el = document.getElementById('identity-pastilla');
    if (! el) return;
    if (! memberId) { el.innerHTML = ''; return; }

    const members = window.TEAM_MEMBERS || [];
    const m = members.find((x) => String(x.id) === String(memberId));
    if (! m) return;

    const changeAction = window.KANBAN_TEAM_ONLY
        ? `<form method="POST" action="${window.KANBAN_ROUTES.logout}" class="inline">
               <input type="hidden" name="_token" value="${csrf}">
               <button type="submit" class="text-xs text-faint hover:underline">Cerrar sesión</button>
           </form>`
        : `<button type="button" id="btn-change-identity" class="text-xs text-faint hover:underline">Cambiar</button>`;

    el.innerHTML = `
        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white select-none"
              style="background-color:${escape(m.color)}">${escape(m.initials)}</span>
        <span class="text-faint">${escape(m.name)}</span>
        ${changeAction}
    `;
}

// ─── Skeleton en cambio de pestaña Personal ↔ Equipo ───────
function initTabSkeleton() {
    document.querySelectorAll('[data-tab-link]').forEach((link) => {
        link.addEventListener('click', () => {
            const b = document.querySelector('[data-task-board]');
            if (b) {
                b.style.transition = 'opacity 0.12s ease-out';
                b.style.opacity    = '0.25';
                b.style.pointerEvents = 'none';
            }
            link.style.opacity = '0.5';
        });
    });
}

function initTransfer() {
    const btn = document.getElementById('btn-transfer-to-team');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        const taskId = btn.dataset.taskId;
        if (!taskId) return;

        const base = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.transferPreview) || '/tasks';

        // Fetch preview
        let preview;
        try {
            const res = await fetch(`${base}/${taskId}/transfer-preview`, {
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            preview = await res.json();
        } catch {
            Swal.fire('Error', 'No se pudo conectar con el equipo.', 'error');
            return;
        }

        const projectLine = preview.project
            ? `<p class="text-sm mt-2">Proyecto: <strong>${preview.project.code}</strong> — ${preview.project.exists ? '✓ ya existe en el equipo' : '⚠ se creará en el equipo'}</p>`
            : '';

        const { isConfirmed } = await Swal.fire({
            title:             'Transferir al equipo',
            html:              `<p class="text-sm">La tarea se copiará al board del equipo con subtareas, comentarios y etiquetas. La tarea personal quedará archivada.</p>${projectLine}`,
            icon:              'question',
            showCancelButton:  true,
            confirmButtonText: 'Transferir',
            cancelButtonText:  'Cancelar',
        });

        if (!isConfirmed) return;

        const transferBase = (window.KANBAN_ROUTES && window.KANBAN_ROUTES.transfer) || '/tasks';
        const res = await fetch(`${transferBase}/${taskId}/transfer-to-team`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });

        if (res.ok) {
            window.__taskMutationAt = Date.now();
            window.location.reload();
        } else {
            Swal.fire('Error', 'No se pudo transferir la tarea.', 'error');
        }
    });
}
