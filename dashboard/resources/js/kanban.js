/**
 * Tablero Kanban v2: alta/edición en modal, drag & drop entre columnas
 * (SortableJS), subtareas + comentarios AJAX, búsqueda libre, filtro por
 * labels, inline-add por columna, colapsar columnas, auto-sort A-Z, y
 * un sub-link "Archivadas". La descripción es un textarea Markdown plano
 * (coherente con la extensión code-kanban).
 *
 * Toda la UX nueva (búsqueda, filtros, colapso, sort) es client-side con
 * persistencia en localStorage — no toca BBDD.
 */
import Sortable from 'sortablejs';
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

export function initKanban() {
    const board     = document.querySelector('[data-task-board]');
    if (! board) return;
    const newModal  = document.getElementById('task-new');
    const editModal = document.getElementById('task-edit');
    const csrf      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let edit = null;

    const escape = (s) => {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
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
    const renderSubtasks = () => {
        if (! edit || ! editModal) return;
        const list     = editModal.querySelector('[data-subtasks-list]');
        const progress = editModal.querySelector('[data-subtasks-progress]');
        if (! list) return;

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

    const syncCardBadge = () => {
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
    };

    const addSubtask = async (title) => {
        if (! edit) return;
        const res = await send(`/tasks/${edit.taskId}/checkboxes`, 'POST', { title });
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
        await send(`/tasks/${edit.taskId}/checkboxes/${id}`, 'PATCH', { checked: checked ? '1' : '0' });
    };
    const deleteSubtask = async (id) => {
        if (! edit) return;
        edit.checkboxes = edit.checkboxes.filter((c) => c.id != id);
        renderSubtasks();
        await send(`/tasks/${edit.taskId}/checkboxes/${id}`, 'DELETE');
    };

    // ─── Comentarios (panel lateral tipo chat) ─────────────────
    // Token de esta instalación: los comentarios cuyo author_token coincide
    // son "míos" y se alinean a la derecha.
    const MY_TOKEN = document.querySelector('meta[name="user-token"]')?.content || '';
    const renderComments = () => {
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
                        <p class="task-chat__text">${escape(c.body)}</p>
                    </div>
                </li>`;
        }).join('');
        list.scrollTop = list.scrollHeight;
        syncCommentsBadge();
    };
    const syncCommentsBadge = () => {
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
    };
    const addComment = async (body) => {
        if (! edit) return;
        const res = await send(`/tasks/${edit.taskId}/comments`, 'POST', { body });
        if (! res.ok) return;
        const c = await res.json();
        edit.comments.push(c);
        renderComments();
    };
    const deleteComment = async (id) => {
        if (! edit) return;
        edit.comments = edit.comments.filter((c) => c.id != id);
        renderComments();
        await send(`/tasks/${edit.taskId}/comments/${id}`, 'DELETE');
    };

    // ─── Modal de edición: handlers de subtareas y comentarios ────
    if (editModal) {
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
    document.querySelectorAll('[data-add-status]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const select = newModal?.querySelector('[name="status"]');
            if (select) select.value = btn.dataset.addStatus;
            const textarea = newModal?.querySelector('textarea[name="description"]');
            if (textarea) textarea.value = '';
        });
    });

    // ─── ✎ de cada tarjeta: rellena y abre modal de edición ────────
    if (editModal) {
        const editForm = editModal.querySelector('[data-task-edit-form]');
        const delForm  = editModal.querySelector('[data-task-delete-form]');

        const wireEditButton = (btn) => {
            btn.addEventListener('click', () => {
                const card = btn.closest('[data-task-id]');
                if (! card || ! editForm) return;

                editForm.action = `/tasks/${card.dataset.taskId}`;
                if (delForm) delForm.action = `/tasks/${card.dataset.taskId}`;

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

                if (typeof editModal.showModal === 'function') editModal.showModal();
            });
        };

        document.querySelectorAll('[data-task-edit]').forEach(wireEditButton);
    }

    // ─── Drag & drop entre columnas ────────────────────────────
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
                fetch(`/tasks/${card.dataset.taskId}/move`, {
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

    // ─── Búsqueda libre + filtro por labels ────────────────────
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

    function updateColumnCounts() {
        document.querySelectorAll('[data-task-column]').forEach((col) => {
            const total   = col.querySelectorAll('.task-card').length;
            const hidden  = col.querySelectorAll('.task-card.is-filtered-out').length;
            const counter = col.querySelector('[data-column-count]');
            if (counter) counter.textContent = total - hidden;
        });
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

    // ─── Inline-add por columna ────────────────────────────────
    document.querySelectorAll('[data-task-inline-add]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const title = form.querySelector('input[name="title"]')?.value.trim();
            if (! title) { e.preventDefault(); return; }
            // Submit normal — el redirect del controller recarga el board.
            // Para evitar perder filtros, ya está persistido en localStorage.
        });
    });

    // ─── Colapsar columnas ──────────────────────────────────────
    function setCollapsed(col, isCollapsed) {
        col.classList.toggle('task-column--collapsed', isCollapsed);
        const status = col.dataset.taskColumn;
        const set = new Set(readJson(LS_COLLAPSED, []));
        if (isCollapsed) set.add(status); else set.delete(status);
        writeJson(LS_COLLAPSED, [...set]);
    }
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

    // ─── Auto-sort A↓Z por columna ──────────────────────────────
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
    initLivePolling();

    function initLivePolling() {
        const projectFilter = new URLSearchParams(window.location.search).get('project');
        const url = projectFilter
            ? `/tasks/peek?project=${encodeURIComponent(projectFilter)}`
            : '/tasks/peek';

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
}
