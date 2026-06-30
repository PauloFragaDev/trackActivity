import Swal from 'sweetalert2';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function jsonFetch(url, body, method = 'POST') {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error ?? 'Error al procesar la solicitud');
    }
    return res.json();
}

// ─────────────────── Menú contextual ───────────────────

let activeMenu = null;

function closeMenu() {
    activeMenu?.remove();
    activeMenu = null;
}

function showMenu(items, clientX, clientY) {
    closeMenu();

    const menu = document.createElement('div');
    menu.className = 'ctx-menu';
    menu.style.visibility = 'hidden';

    for (const item of items) {
        if (item === 'sep') {
            const sep = document.createElement('div');
            sep.className = 'ctx-sep';
            menu.appendChild(sep);
            continue;
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ctx-item' + (item.danger ? ' ctx-danger' : '');
        btn.textContent = item.label;
        btn.addEventListener('mousedown', (e) => {
            e.preventDefault();
            closeMenu();
            item.action();
        });
        menu.appendChild(btn);
    }

    document.body.appendChild(menu);
    activeMenu = menu;

    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const w  = menu.offsetWidth;
    const h  = menu.offsetHeight;
    menu.style.left       = Math.min(clientX, vw - w - 8) + 'px';
    menu.style.top        = Math.min(clientY, vh - h - 8) + 'px';
    menu.style.visibility = '';
}

// ─────────────────── Acciones ───────────────────

async function moveNote(id, title, currentFolderId) {
    const folders = window.__NOTE_FOLDERS ?? [];
    const opts    = [
        `<option value="">Sin carpeta (raíz)${!currentFolderId ? ' — actual' : ''}</option>`,
        ...folders.map(f =>
            `<option value="${f.id}" ${f.id == currentFolderId ? 'selected' : ''}>${escHtml(f.name)}</option>`
        ),
    ].join('');

    const { value, isConfirmed } = await Swal.fire({
        title: `Mover «${escHtml(title)}»`,
        html: `<label class="block text-left text-sm mb-1">Carpeta destino</label>
               <select id="swal-folder" class="swal2-select w-full">${opts}</select>`,
        showCancelButton: true,
        confirmButtonText: 'Mover',
        cancelButtonText: 'Cancelar',
        preConfirm() {
            const v = document.getElementById('swal-folder').value;
            return v === '' ? null : parseInt(v, 10);
        },
    });
    if (!isConfirmed) return;

    try {
        await jsonFetch(`/notes/${id}/move`, { folder_id: value });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }
}

async function deleteNote(id, title) {
    const result = await Swal.fire({
        title: `¿Eliminar «${escHtml(title)}»?`,
        text: 'La nota pasará a la papelera.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
    });
    if (!result.isConfirmed) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/notes/${id}`;
    form.innerHTML = `<input type="hidden" name="_token" value="${csrf()}">
                      <input type="hidden" name="_method" value="DELETE">`;
    document.body.appendChild(form);
    form.submit();
}

async function moveFolder(id, name, currentParentId) {
    const all    = window.__NOTE_FOLDERS ?? [];
    const folders = all.filter(f => f.id !== id);
    const opts   = [
        `<option value="">Sin carpeta (raíz)${!currentParentId ? ' — actual' : ''}</option>`,
        ...folders.map(f =>
            `<option value="${f.id}" ${f.id == currentParentId ? 'selected' : ''}>${escHtml(f.name)}</option>`
        ),
    ].join('');

    const { value, isConfirmed } = await Swal.fire({
        title: `Mover carpeta «${escHtml(name)}»`,
        html: `<label class="block text-left text-sm mb-1">Carpeta destino</label>
               <select id="swal-folder" class="swal2-select w-full">${opts}</select>`,
        showCancelButton: true,
        confirmButtonText: 'Mover',
        cancelButtonText: 'Cancelar',
        preConfirm() {
            const v = document.getElementById('swal-folder').value;
            return v === '' ? null : parseInt(v, 10);
        },
    });
    if (!isConfirmed) return;

    try {
        await jsonFetch(`/note-folders/${id}/move`, { parent_id: value });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }
}

async function renameFolderViaPrompt(id, currentName, nameSpan) {
    const { value: newName, isConfirmed } = await Swal.fire({
        title: 'Renombrar carpeta',
        input: 'text',
        inputValue: currentName,
        inputLabel: 'Nombre',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        inputValidator: (v) => !v.trim() ? 'El nombre no puede estar vacío.' : null,
    });
    if (!isConfirmed || !newName.trim()) return;

    try {
        const res = await jsonFetch(`/note-folders/${id}`, { name: newName.trim() }, 'PATCH');
        if (nameSpan) nameSpan.textContent = res.name;
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }
}

async function deleteFolderFromList(id, name) {
    const result = await Swal.fire({
        title: `¿Eliminar «${escHtml(name)}»?`,
        text: 'Sus notas y subcarpetas pasarán a la raíz.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
    });
    if (!result.isConfirmed) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/note-folders/${id}`;
    form.innerHTML = `<input type="hidden" name="_token" value="${csrf()}">
                      <input type="hidden" name="_method" value="DELETE">`;
    document.body.appendChild(form);
    form.submit();
}

// ─────────────────── Rename inline en cabecera ───────────────────

function attachInlineRename(span) {
    span.addEventListener('dblclick', () => {
        const folderId   = span.dataset.folderId;
        const origName   = span.textContent.trim();

        const input = document.createElement('input');
        input.type      = 'text';
        input.value     = origName;
        input.className = 'folder-title-input';
        input.maxLength = 120;

        span.replaceWith(input);
        input.focus();
        input.select();

        const restore = () => {
            const newSpan = span.cloneNode(true);
            input.replaceWith(newSpan);
            attachInlineRename(newSpan);
        };

        const save = async () => {
            const newName = input.value.trim();
            if (!newName || newName === origName) { restore(); return; }

            try {
                const res = await jsonFetch(`/note-folders/${folderId}`, { name: newName }, 'PATCH');
                span.textContent = res.name;
                // also update page title if it reflects the folder name
                document.title = document.title.replace(origName, res.name);
                input.replaceWith(span);
                attachInlineRename(span);
            } catch (err) {
                restore();
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        };

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter')  { e.preventDefault(); save(); }
            if (e.key === 'Escape') { restore(); }
        });
        input.addEventListener('blur', save);
    });
}

// ─────────────────── Bootstrap ───────────────────

export function initNotesUI() {
    const list = document.getElementById('notes-list');
    if (!list) return;

    // Cerrar menú con click fuera o Escape
    document.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

    // Inline rename en título de carpeta de la cabecera
    const titleSpan = list.querySelector('.folder-title-inline');
    if (titleSpan) attachInlineRename(titleSpan);

    // Menú contextual en ítems de nota
    list.addEventListener('contextmenu', (e) => {
        // ¿Nota?
        const noteEl = e.target.closest('[data-note-id]');
        if (noteEl) {
            e.preventDefault();
            const id       = noteEl.dataset.noteId;
            const title    = noteEl.dataset.noteTitle;
            const folderId = noteEl.dataset.noteFolder || null;
            const url      = noteEl.querySelector('a')?.href ?? `/notes?note=${id}`;

            showMenu([
                { label: 'Abrir',     action: () => { window.location.href = url; } },
                { label: 'Mover a…',  action: () => moveNote(id, title, folderId) },
                'sep',
                { label: 'Eliminar',  action: () => deleteNote(id, title), danger: true },
            ], e.clientX, e.clientY);
            return;
        }

        // ¿Subcarpeta?
        const sfEl = e.target.closest('[data-subfolder-id]');
        if (sfEl) {
            e.preventDefault();
            const id       = sfEl.dataset.subfolderId;
            const name     = sfEl.dataset.subfolderName;
            const parentId = sfEl.dataset.subfolderParent || null;
            const nameSpan = sfEl.querySelector('.subfolder-name-text');

            showMenu([
                { label: 'Abrir',      action: () => { window.location.href = sfEl.href; } },
                { label: 'Renombrar',  action: () => renameFolderViaPrompt(id, name, nameSpan) },
                { label: 'Mover a…',   action: () => moveFolder(id, name, parentId) },
                'sep',
                { label: 'Eliminar',   action: () => deleteFolderFromList(id, name), danger: true },
            ], e.clientX, e.clientY);
        }
    });
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
