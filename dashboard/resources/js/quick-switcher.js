/**
 * Command Palette (Ctrl/Cmd+K).
 *
 * Tres registros se mezclan en el resultado, en este orden:
 *
 *   1. ACCIONES contextuales — comandos que ejecutan algo en la página
 *      actual (cambiar tema, fijar nota, mover a papelera, etc.). Cada
 *      acción tiene un `available(ctx)` que decide si aparece.
 *
 *   2. NAVEGACIÓN — links fijos a las secciones de la app.
 *
 *   3. NOTAS — cargadas una vez por sesión desde /notes/quick.
 *
 * El usuario filtra por texto libre. Enter ejecuta la fila resaltada
 * (ya sea navegar o llamar a `run`).
 */

/* ─────── Catálogos ─────── */

const NAV = [
    { icon: '🏠', label: 'Inicio',         href: '/dashboard' },
    { icon: '🕘', label: 'Hoy',            href: '/' },
    { icon: '📅', label: 'Semana',         href: '/week' },
    { icon: '🗓', label: 'Mes',            href: '/calendar' },
    { icon: '📊', label: 'Informes',       href: '/reports' },
    { icon: '📝', label: 'Notas',          href: '/notes' },
    { icon: '✅', label: 'Tareas',         href: '/tasks' },
    { icon: '🍅', label: 'Pomodoro',       href: '/pomodoro' },
    { icon: '🗑', label: 'Papelera notas', href: '/notes?trash=1' },
    { icon: '📁', label: 'Proyectos',      href: '/projects' },
    { icon: '⚙️', label: 'Configuración',  href: '/settings' },
    { icon: '💾', label: 'Datos',          href: '/data' },
    { icon: '❓', label: 'Ayuda',          href: '/help' },
];

/** Cada acción declara cuándo aparece (`available`) y qué hace (`run`). */
const ACTIONS = [
    {
        icon: '🎨', label: 'Cambiar tema (claro/oscuro)',
        available: () => true,
        run: () => document.getElementById('theme-toggle')?.click(),
    },
    {
        icon: '🍅', label: 'Empezar foco ahora',
        available: () => true,
        run: () => {
            // Disparamos un foco arrancando el state en localStorage y
            // navegamos a la página principal. pomodoro.js leerá y pintará.
            try {
                const cur = JSON.parse(localStorage.getItem('pomodoro.state') || '{}');
                const next = {
                    phase: 'focus',
                    startedAt: Date.now(),
                    pausedAt: null,
                    pausedOffset: 0,
                    cycle: cur.cycle ?? 0,
                };
                localStorage.setItem('pomodoro.state', JSON.stringify(next));
            } catch {}
            window.location.href = '/pomodoro';
        },
    },
    {
        icon: '🆕', label: 'Nueva tarea',
        available: () => true,
        run: () => { window.location.href = '/tasks?new=1'; },
    },
    {
        icon: '📌', label: 'Fijar / desfijar nota actual',
        available: (ctx) => ctx.path === '/notes' && ctx.currentNoteId,
        run: (ctx) => postForm(`/notes/${ctx.currentNoteId}/pin`, 'PATCH'),
    },
    {
        icon: '🔗', label: 'Copiar enlace a esta nota',
        available: (ctx) => ctx.path === '/notes' && ctx.currentNoteId,
        run: async (ctx) => {
            const url = `${window.location.origin}/notes?note=${ctx.currentNoteId}`;
            try { await navigator.clipboard.writeText(url); } catch {}
            toast(`Enlace copiado: ${url}`);
        },
    },
    {
        icon: '🗑', label: 'Mover nota actual a papelera',
        available: (ctx) => ctx.path === '/notes' && ctx.currentNoteId,
        run: (ctx) => {
            if (! window.confirm('¿Mover esta nota a la papelera?')) return;
            postForm(`/notes/${ctx.currentNoteId}`, 'DELETE');
        },
    },
];

/* ─────── Helpers ─────── */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/** POST con _method spoofing — sobrevive a la convención de Laravel. */
function postForm(url, method = 'POST') {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.innerHTML = `
        <input type="hidden" name="_token" value="${csrfToken()}">
        <input type="hidden" name="_method" value="${method}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function toast(msg) {
    // window.toast lo expone app.js (Toastify wrapper). Si por lo que sea
    // no está cargado, no rompemos la acción — solo logueamos.
    if (typeof window.toast === 'function') window.toast(msg);
    else console.info('[qs]', msg);
}

function ctxFromLocation() {
    const url = new URL(window.location.href);
    return {
        path: url.pathname,
        currentNoteId: Number(url.searchParams.get('note')) || null,
    };
}

function escape(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

/* ─────── Init ─────── */

export function initQuickSwitcher() {
    const dlg = document.getElementById('quick-switcher');
    if (! dlg) return;

    const input = dlg.querySelector('[data-qs-input]');
    const list  = dlg.querySelector('[data-qs-results]');

    let notes  = null;
    let active = 0;
    let rows   = [];

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
        const q   = input.value.trim().toLowerCase();
        const ctx = ctxFromLocation();
        const match = (label) => q === '' || label.toLowerCase().includes(q);

        rows = [
            ...ACTIONS
                .filter((a) => a.available(ctx) && match(a.label))
                .map((a) => ({ kind: 'action', meta: 'Acción', icon: a.icon, label: a.label, run: () => a.run(ctx) })),
            ...NAV
                .filter((c) => match(c.label))
                .map((c) => ({ kind: 'nav', meta: 'Ir a', icon: c.icon, label: c.label, href: c.href })),
            ...(notes || [])
                .filter((n) => match(n.title || ''))
                .slice(0, 30)
                .map((n) => ({
                    kind:  'note',
                    meta:  n.folder || 'Nota',
                    icon:  n.icon || '📄',
                    label: n.title || '(sin título)',
                    href:  `/notes?note=${n.id}`,
                })),
        ];

        if (rows.length === 0) {
            list.innerHTML = '<li role="presentation" class="px-3 py-2 text-sm text-muted">Sin resultados</li>';
            input.removeAttribute('aria-activedescendant');
            return;
        }
        list.innerHTML = rows.map((r, i) => `
            <li role="presentation">
                <button type="button" data-qs-item data-qs-index="${i}" role="option" id="qs-opt-${i}" aria-selected="false"
                        class="w-full text-left flex items-center gap-2 px-3 py-2 rounded text-sm">
                    <span class="shrink-0">${escape(r.icon)}</span>
                    <span class="flex-1 truncate">${escape(r.label)}</span>
                    <span class="shrink-0 text-faint text-xs">${escape(r.meta)}</span>
                </button>
            </li>`).join('');
        setActive(0);
    };

    const execute = (idx) => {
        const r = rows[idx];
        if (! r) return;
        dlg.close();
        if (r.kind === 'action') r.run();
        else if (r.href)         window.location.href = r.href;
    };

    const open = async () => {
        if (dlg.open) return;
        dlg.showModal();
        input.value = '';
        render();
        input.focus();

        if (notes === null) {
            try {
                const res = await fetch('/notes/quick', { headers: { Accept: 'application/json' } });
                notes = res.ok ? await res.json() : [];
            } catch { notes = []; }
            render();
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
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); setActive(active - 1); }
        else if (e.key === 'Enter')     { e.preventDefault(); execute(active); }
    });

    // Click directo en una fila (ratón).
    list.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-qs-item]');
        if (! btn) return;
        execute(Number(btn.dataset.qsIndex));
    });
}
