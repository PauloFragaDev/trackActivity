/**
 * Paleta de comandos (Ctrl/Cmd+K): navega a cualquier sección o salta a
 * una nota desde cualquier página. Los comandos de navegación son fijos;
 * la lista de notas se trae una vez por carga vía /notes/quick.
 */

const COMMANDS = [
    { icon: '🏠', label: 'Inicio',     href: '/dashboard' },
    { icon: '🕘', label: 'Hoy',        href: '/' },
    { icon: '📅', label: 'Semana',     href: '/week' },
    { icon: '🗓', label: 'Mes',        href: '/calendar' },
    { icon: '📝', label: 'Notas',      href: '/notes' },
    { icon: '✅', label: 'Tareas',     href: '/tasks' },
    { icon: '🗑', label: 'Papelera',   href: '/notes?trash=1' },
    { icon: '📁', label: 'Proyectos',  href: '/projects' },
    { icon: '💾', label: 'Datos',      href: '/data' },
    { icon: '❓', label: 'Ayuda',      href: '/help' },
];

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
            el.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', els[active].id);
        els[active].scrollIntoView({ block: 'nearest' });
    };

    const render = () => {
        const q = input.value.trim().toLowerCase();

        const rows = [
            ...COMMANDS
                .filter((c) => q === '' || c.label.toLowerCase().includes(q))
                .map((c) => ({ href: c.href, icon: c.icon, label: c.label, meta: 'Ir a' })),
            ...(notes || [])
                .filter((n) => q === '' || (n.title || '').toLowerCase().includes(q))
                .slice(0, 30)
                .map((n) => ({
                    href: `/notes?note=${n.id}`,
                    icon: n.icon || '📄',
                    label: n.title || '(sin título)',
                    meta: n.folder || 'Nota',
                })),
        ];

        if (rows.length === 0) {
            list.innerHTML = '<li role="presentation" class="px-3 py-2 text-sm text-muted">Sin resultados</li>';
            input.removeAttribute('aria-activedescendant');
            return;
        }
        list.innerHTML = rows.map((r, i) => `
            <li role="presentation">
                <a href="${r.href}" data-qs-item role="option" id="qs-opt-${i}" aria-selected="false"
                   class="flex items-center gap-2 px-3 py-2 rounded text-sm">
                    <span class="shrink-0">${escape(r.icon)}</span>
                    <span class="flex-1 truncate">${escape(r.label)}</span>
                    <span class="shrink-0 text-faint text-xs">${escape(r.meta)}</span>
                </a>
            </li>`).join('');
        setActive(0);
    };

    const open = async () => {
        if (dlg.open) return;
        dlg.showModal();
        input.value = '';
        render();          // los comandos se muestran al instante
        input.focus();

        if (notes === null) {
            try {
                const res = await fetch('/notes/quick', { headers: { Accept: 'application/json' } });
                notes = res.ok ? await res.json() : [];
            } catch {
                notes = [];
            }
            render();      // re-render incorporando las notas
        }
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
