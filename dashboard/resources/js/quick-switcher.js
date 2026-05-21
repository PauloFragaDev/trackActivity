/**
 * Quick switcher (Ctrl/Cmd+K): busca y salta a una nota desde cualquier
 * página. La lista de notas se trae una vez por carga de página desde
 * /notes/quick y se filtra en cliente.
 */
export function initQuickSwitcher() {
    const dlg = document.getElementById('quick-switcher');
    if (!dlg) return;

    const input = dlg.querySelector('[data-qs-input]');
    const list  = dlg.querySelector('[data-qs-results]');

    let notes  = null;   // caché de la lista de notas
    let active = 0;

    const escape = (s) => {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    };

    const itemEls = () => [...list.querySelectorAll('[data-qs-item]')];

    const setActive = (idx) => {
        const els = itemEls();
        if (els.length === 0) return;
        active = (idx + els.length) % els.length;
        els.forEach((el, i) => {
            const on = i === active;
            el.classList.toggle('bg-ink-100', on);
            el.classList.toggle('dark:bg-ink-800', on);
        });
        els[active].scrollIntoView({ block: 'nearest' });
    };

    const render = () => {
        const q = input.value.trim().toLowerCase();
        const matched = (notes || [])
            .filter((n) => q === '' || (n.title || '').toLowerCase().includes(q))
            .slice(0, 40);

        if (matched.length === 0) {
            list.innerHTML = '<li class="px-3 py-2 text-sm text-muted">Sin resultados</li>';
            return;
        }
        list.innerHTML = matched.map((n) => `
            <li>
                <a href="/notes?note=${n.id}" data-qs-item
                   class="block px-3 py-2 rounded text-sm truncate">
                    ${n.icon ? escape(n.icon) + ' ' : ''}<span class="font-medium">${escape(n.title || '(sin título)')}</span>${
                        n.folder ? `<span class="text-faint"> · ${escape(n.folder)}</span>` : ''
                    }
                </a>
            </li>`).join('');
        setActive(0);
    };

    const open = async () => {
        if (dlg.open) return;
        dlg.showModal();
        input.value = '';
        if (notes === null) {
            list.innerHTML = '<li class="px-3 py-2 text-sm text-muted">Cargando…</li>';
            try {
                const res = await fetch('/notes/quick', { headers: { Accept: 'application/json' } });
                notes = res.ok ? await res.json() : [];
            } catch {
                notes = [];
            }
        }
        render();
        input.focus();
    };

    // Atajo global Ctrl/Cmd+K.
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            dlg.open ? dlg.close() : open();
        }
    });

    // Botón "Buscar" del sidebar.
    document.querySelectorAll('[data-qs-open]').forEach((btn) => {
        btn.addEventListener('click', () => open());
    });

    input.addEventListener('input', render);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(active + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(active - 1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const el = itemEls()[active];
            if (el) window.location.href = el.getAttribute('href');
        }
    });
}
