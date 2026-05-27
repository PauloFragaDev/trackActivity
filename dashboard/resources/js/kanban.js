/**
 * Tablero Kanban v2: alta/edición en modal con Markdown (Crepe), drag & drop
 * entre columnas (SortableJS), subtareas + comentarios AJAX, búsqueda libre,
 * filtro por labels, inline-add por columna, colapsar columnas, auto-sort A-Z,
 * y un sub-link "Archivadas".
 *
 * Toda la UX nueva (búsqueda, filtros, colapso, sort) es client-side con
 * persistencia en localStorage — no toca BBDD.
 */
import Sortable from 'sortablejs';

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

export function initKanban() {
    const board     = document.querySelector('[data-task-board]');
    if (! board) return;
    const newModal  = document.getElementById('task-new');
    const editModal = document.getElementById('task-edit');
    const csrf      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let edit = null;
    let currentDescEditor = null; // instancia activa de Crepe (modal abierto)

    const escape = (s) => {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    };

    const send = (url, method, params = {}) =>
        fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams({ _token: csrf, _method: method, ...params }),
        });

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
        const text = `☑ ${done}/${items.length}`;
        const cls  = `chip ${done === items.length ? 'text-emerald-600 dark:text-emerald-400' : ''}`;
        if (badge) { badge.textContent = text; badge.className = cls; }
        else if (chipRow) {
            const span = document.createElement('span');
            span.setAttribute('data-card-subtasks-badge', '');
            span.title = 'Subtareas';
            span.className = cls;
            span.textContent = text;
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

    // ─── Comentarios ──────────────────────────────────────────
    const renderComments = () => {
        if (! edit || ! editModal) return;
        const list = editModal.querySelector('[data-comments-list]');
        if (! list) return;
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
        if (! edit?.card) return;
        edit.card.dataset.comments = JSON.stringify(edit.comments);
        const badge = edit.card.querySelector('[data-card-comments-badge]');
        const chipRow = edit.card.querySelector('.flex.flex-wrap.items-center');
        if (edit.comments.length === 0) { badge?.remove(); return; }
        const text = `💬 ${edit.comments.length}`;
        if (badge) badge.textContent = text;
        else if (chipRow) {
            const span = document.createElement('span');
            span.setAttribute('data-card-comments-badge', '');
            span.title = 'Comentarios';
            span.className = 'chip';
            span.textContent = text;
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
    }

    // ─── Crepe en descripción (montar al abrir / destruir al cerrar) ───
    /**
     * Monta Crepe sobre el div del modal usando el valor actual del textarea
     * de descripción. Lazy-import del módulo (compartido con notes-editor.js).
     */
    async function mountDescEditor(modal) {
        const mount    = modal?.querySelector('[data-task-desc-editor]');
        const textarea = modal?.querySelector('textarea[name="description"]');
        if (! mount || ! textarea) return;
        // Si ya hay una instancia (modal reabierto sin cerrar bien), la destruimos.
        await destroyDescEditor();
        // Limpiar el div por si quedó algo de un montaje previo.
        mount.innerHTML = '';
        mount.removeAttribute('hidden');

        try {
            const [{ Crepe }] = await Promise.all([
                import('@milkdown/crepe'),
                import('@milkdown/crepe/theme/common/style.css'),
                document.documentElement.classList.contains('dark')
                    ? import('@milkdown/crepe/theme/frame-dark.css')
                    : import('@milkdown/crepe/theme/frame.css'),
            ]);
            currentDescEditor = new Crepe({ root: mount, defaultValue: textarea.value || '' });
            await currentDescEditor.create();
            textarea.classList.add('hidden');
            // Sincronización en cambio: mantenemos el textarea al día para el submit.
            mount.addEventListener('input', syncDescTextarea);
        } catch (err) {
            console.error('Kanban: editor de descripción no disponible, se usa textarea.', err);
            currentDescEditor = null;
            mount.setAttribute('hidden', 'hidden');
            textarea.classList.remove('hidden');
        }
    }

    function syncDescTextarea() {
        if (! currentDescEditor) return;
        const textarea = (newModal?.contains(document.activeElement) ? newModal : editModal)
            ?.querySelector('textarea[name="description"]')
            // fallback: cualquier textarea con name=description visible en pantalla
            ?? document.querySelector('dialog[open] textarea[name="description"]');
        if (! textarea) return;
        try { textarea.value = currentDescEditor.getMarkdown(); } catch { /* noop */ }
    }

    async function destroyDescEditor() {
        if (! currentDescEditor) return;
        try { await currentDescEditor.destroy(); } catch { /* ignore */ }
        currentDescEditor = null;
    }

    // Cablear apertura/cierre para los dos modales.
    [newModal, editModal].filter(Boolean).forEach((modal) => {
        modal.addEventListener('close', () => {
            destroyDescEditor();
            // Limpiar el mount para volver al estado de partida.
            const mount = modal.querySelector('[data-task-desc-editor]');
            const textarea = modal.querySelector('textarea[name="description"]');
            mount?.setAttribute('hidden', 'hidden');
            mount && (mount.innerHTML = '');
            textarea?.classList.remove('hidden');
        });
        // Antes de submit, sincronizar para que el form lleve el markdown.
        modal.querySelector('form')?.addEventListener('submit', syncDescTextarea);
    });

    // ─── "+" de columna: preselecciona status y monta Crepe ────────
    document.querySelectorAll('[data-add-status]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const select = newModal?.querySelector('[name="status"]');
            if (select) select.value = btn.dataset.addStatus;
            const textarea = newModal?.querySelector('textarea[name="description"]');
            if (textarea) textarea.value = '';
            // Montar editor tras un tick para que el dialog ya esté en DOM.
            requestAnimationFrame(() => mountDescEditor(newModal));
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
                requestAnimationFrame(() => mountDescEditor(editModal));
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
            group: 'kanban',
            animation: 150,
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
    const searchInput  = document.querySelector('[data-task-search]');
    const searchClear  = document.querySelector('[data-task-search-clear]');
    const labelChips   = document.querySelectorAll('[data-label-filter]');
    const labelsClear  = document.querySelector('[data-label-filters-clear]');
    const summary      = document.querySelector('[data-filter-summary]');

    let activeLabels = new Set(readJson(LS_LABELS, []));
    const restoredSearch = localStorage.getItem(LS_SEARCH) || '';

    if (searchInput) searchInput.value = restoredSearch;
    if (searchClear) searchClear.classList.toggle('hidden', ! restoredSearch);
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
        searchClear?.classList.toggle('hidden', searchInput.value.length === 0);
        applyFilters();
    });
    searchClear?.addEventListener('click', () => {
        if (! searchInput) return;
        searchInput.value = '';
        try { localStorage.removeItem(LS_SEARCH); } catch {}
        searchClear.classList.add('hidden');
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

    // ─── Live refresh vía SSE ─────────────────────────────────────────
    //
    // Mantenemos abierta una conexión a /tasks/stream. Cuando el server
    // emite `change` (algo cambió en BBDD: la extensión code-kanban hizo
    // sync, otra pestaña editó, etc.), recargamos el board.
    //
    // Para no interrumpir al usuario en mitad de algo, NO recargamos si:
    //   - hay un <dialog> abierto (editar tarea, nueva tarea, etc.).
    //   - el foco está dentro del inline-add o de la búsqueda con texto.
    // En esos casos, marcamos un flag y recargamos cuando el usuario
    // cierre el dialog / pierda el foco.
    initLiveRefresh();

    function initLiveRefresh() {
        if (typeof window.EventSource !== 'function') return;
        const projectFilter = new URLSearchParams(window.location.search).get('project');
        const url = projectFilter
            ? `/tasks/stream?project=${encodeURIComponent(projectFilter)}`
            : '/tasks/stream';

        let pendingReload = false;
        let initialLatest = null;

        const isUserBusy = () => {
            // Dialog abierto: no recargar.
            if (document.querySelector('dialog[open]')) return true;
            // Inline-add con texto o focus: no recargar.
            const active = document.activeElement;
            if (active?.matches?.('input[type="search"], input[type="text"], textarea')) {
                return (active.value || '').trim() !== '' || active.matches(':focus');
            }
            return false;
        };

        const doReload = () => {
            if (isUserBusy()) {
                pendingReload = true;
                return;
            }
            window.location.reload();
        };

        // Cuando el usuario "se libera" (cierra modal, sale del input),
        // aplicamos el reload pendiente.
        document.addEventListener('focusout', () => {
            if (pendingReload && ! isUserBusy()) setTimeout(doReload, 200);
        });
        document.addEventListener('close', () => {
            if (pendingReload && ! isUserBusy()) setTimeout(doReload, 200);
        }, true);

        const openStream = () => {
            const es = new EventSource(url);
            es.addEventListener('hello', (e) => {
                try { initialLatest = JSON.parse(e.data).latest; } catch {}
            });
            es.addEventListener('change', (e) => {
                let latest = null;
                try { latest = JSON.parse(e.data).latest; } catch {}
                // Evita recargar si el evento llega con el mismo `latest`
                // que recibimos en el hello (no debería pasar, defensa).
                if (latest && latest !== initialLatest) doReload();
            });
            es.addEventListener('rotate', () => {
                es.close();
                setTimeout(openStream, 200);
            });
            es.onerror = () => {
                es.close();
                setTimeout(openStream, 3000);
            };
        };

        openStream();
    }
}
