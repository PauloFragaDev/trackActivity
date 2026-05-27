import './bootstrap';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import Toastify from 'toastify-js';
import 'toastify-js/src/toastify.css';
import { initQuickSwitcher } from './quick-switcher.js';

// ──────────────────────────────────────────────
// Theme toggle (claro/oscuro) con persistencia.
// El init de la clase 'dark' en <html> se hace inline en el layout
// para evitar flash; este modulo solo cablea el boton.
// ──────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;

    const sync = () => {
        const isDark = document.documentElement.classList.contains('dark');
        btn.setAttribute('aria-pressed', String(isDark));
        btn.title = isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
        const sun  = btn.querySelector('[data-icon-sun]');
        const moon = btn.querySelector('[data-icon-moon]');
        if (sun)  sun.classList.toggle('hidden', !isDark);
        if (moon) moon.classList.toggle('hidden',  isDark);
    };

    btn.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        sync();
    });

    sync();
});

// ──────────────────────────────────────────────
// Confirmaciones (SweetAlert2) y toasts (Toastify-js).
// ──────────────────────────────────────────────

/**
 * Configuración común de SweetAlert, alineada con el diseño de la app.
 * El aspecto del popup y de los botones se define en app.css
 * (.app-swal + las clases .btn-* de la app, gracias a buttonsStyling:false).
 */
const SWAL_BASE = {
    buttonsStyling: false,
    reverseButtons: true,
    customClass: {
        popup: 'app-swal',
        confirmButton: 'btn-danger',
        cancelButton: 'btn-ghost',
    },
};

/**
 * Toast de feedback. `variant` puede ser 'success' (por defecto), 'warn',
 * 'error' o 'info' — cambia el color de la barra izquierda.
 */
window.toast = (message, variant = 'success') => {
    const variantClass = variant === 'success' ? '' : ` app-toast--${variant}`;
    Toastify({
        text: message,
        ariaLive: 'polite',
        duration: 3200,
        gravity: 'bottom',
        position: 'center',
        className: 'app-toast' + variantClass,
        close: false,
        stopOnFocus: true,
        offset: { x: 0, y: 24 },
    }).showToast();
};

/** Abre un <dialog> modal por selector, si existe. */
function openModal(selector) {
    const dlg = document.querySelector(selector);
    if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
}

window.addEventListener('DOMContentLoaded', () => {
    // Sidebar plegable: alterna el estado y lo persiste.
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded');
    });

    // Sidebar móvil: hamburger + cierre al pulsar el overlay o un enlace.
    const mobileOpen = () => document.documentElement.classList.add('sidebar-mobile-open');
    const mobileClose = () => document.documentElement.classList.remove('sidebar-mobile-open');
    document.getElementById('mobile-menu-btn')?.addEventListener('click', mobileOpen);
    document.getElementById('mobile-sidebar-overlay')?.addEventListener('click', mobileClose);
    document.querySelectorAll('#sidebar a, #sidebar [data-modal-open]').forEach((el) => {
        el.addEventListener('click', mobileClose);
    });

    // Estado de carga en formularios marcados con data-loading-form: al
    // enviarse, deshabilita y reescribe el texto de los submit buttons que
    // lleven data-loading-label.
    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"][data-loading-label]').forEach((btn) => {
                btn.disabled = true;
                btn.dataset.originalText = btn.innerHTML;
                btn.innerHTML = btn.dataset.loadingLabel + ' …';
            });
        });
    });

    // Paneles plegables de Notas (carpetas y lista).
    document.querySelectorAll('[data-panel-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.panelToggle;   // 'folders' | 'list'
            const collapsed = document.documentElement.classList.toggle(`notes-${key}-collapsed`);
            localStorage.setItem(`notes-${key}`, collapsed ? 'collapsed' : 'expanded');
        });
    });

    // Quick switcher (Ctrl/Cmd+K).
    initQuickSwitcher();

    // Selector de icono (emoji): los presets rellenan el input[name=icon];
    // si hay un <details> alrededor, se actualiza el icono que muestra.
    document.querySelectorAll('[data-icon-field]').forEach((field) => {
        const input = field.querySelector('input[name="icon"]');
        if (!input) return;
        const summary = field.closest('details')?.querySelector('summary');
        const sync = () => { if (summary) summary.textContent = input.value.trim() || '📄'; };
        field.querySelectorAll('[data-icon-set]').forEach((btn) => {
            btn.addEventListener('click', () => { input.value = btn.dataset.iconValue; sync(); });
        });
        input.addEventListener('input', sync);
    });

    // Copiar el enlace de una nota al portapapeles.
    document.querySelectorAll('[data-copy-link]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(btn.dataset.url);
                window.toast('Enlace copiado');
            } catch {
                window.toast('No se pudo copiar el enlace');
            }
        });
    });

    // Confirmaciones: <form data-confirm="mensaje"> pide confirmación con
    // SweetAlert en vez del confirm() nativo del navegador.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmed === '1') return;   // ya confirmado: deja pasar
            e.preventDefault();
            Swal.fire({
                ...SWAL_BASE,
                title: form.dataset.confirmTitle || '¿Eliminar?',
                text: form.dataset.confirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit();
                }
            });
        });
    });

    // Modales <dialog>: [data-modal-open="#id"] abre, [data-modal-close] cierra.
    document.querySelectorAll('[data-modal-open]').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
    });
    document.querySelectorAll('dialog.modal').forEach((dlg) => {
        dlg.querySelectorAll('[data-modal-close]').forEach((btn) => {
            btn.addEventListener('click', () => dlg.close());
        });
        // Click en el backdrop (fuera del contenido) cierra el modal.
        dlg.addEventListener('click', (e) => {
            if (e.target === dlg) dlg.close();
        });
    });

    // Toast de guardado: el layout deja el mensaje flash en #flash-data.
    const flash = document.getElementById('flash-data');
    if (flash && flash.dataset.message) {
        window.toast(flash.dataset.message);
    }

    // Solapamiento de entrada manual: el server devolvió un aviso. Si el
    // usuario confirma, se reenvía el formulario con confirm_replace=1.
    const overlapEl = document.getElementById('overlap-data');
    if (overlapEl) {
        const overlap = JSON.parse(overlapEl.textContent);
        Swal.fire({
            ...SWAL_BASE,
            title: 'Solapamiento de horario',
            text: overlap.message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Reemplazar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = overlap.action;

                const add = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value ?? '';
                    form.appendChild(input);
                };

                for (const [key, value] of Object.entries(overlap.fields || {})) {
                    add(key, value);
                }
                if (overlap.method && overlap.method !== 'POST') {
                    add('_method', overlap.method);
                }
                add('confirm_replace', '1');

                document.body.appendChild(form);
                form.submit();
                return;
            }
            // Cancelar: reabrir el modal correspondiente para ajustar los datos.
            const match = overlap.action.match(/manual-entries(?:\/(\d+))?$/);
            openModal(match && match[1] ? `#manual-edit-${match[1]}` : '#manual-add');
        });
    } else if (document.getElementById('form-errors')) {
        // Errores de validación: reabre el modal de alta con los datos.
        openModal('#manual-add');
    }

    // Editor de notas: Crepe se carga de forma diferida solo en /notes.
    if (document.querySelector('[data-note-editor]')) {
        import('./notes-editor.js').then((m) => m.initNoteEditor());
    }

    // Tablero Kanban: se carga de forma diferida solo en /tasks.
    if (document.querySelector('[data-task-board]')) {
        import('./kanban.js').then((m) => m.initKanban());
    }

    // Edición manual de un activity_event (modal del timeline).
    if (document.querySelector('[data-event-edit-modal]')) {
        initEventEdit();
    }

    // Vista de informes: Chart.js se carga de forma diferida solo en /reports.
    if (document.getElementById('reports-data')) {
        import('./reports.js').then((m) => m.initReports());
    }
});

function initEventEdit() {
    const modal  = document.getElementById('event-edit');
    const form   = modal?.querySelector('[data-event-edit-form]');
    const select = form?.querySelector('[name="project_id"]');
    const csrf   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let currentId = null;

    const setText = (sel, val) => {
        const el = modal.querySelector(sel);
        if (el) el.textContent = val || '—';
    };
    const setRowVisible = (sel, visible) => {
        modal.querySelector(sel)?.classList.toggle('hidden', !visible);
    };

    document.querySelectorAll('[data-event-edit]').forEach((btn) => {
        btn.addEventListener('click', () => {
            currentId = btn.dataset.id;
            setText('[data-event-time]',   btn.dataset.time);
            setText('[data-event-source]', btn.dataset.source);
            setText('[data-event-app]',    btn.dataset.app);
            setText('[data-event-title]',  btn.dataset.title);
            setText('[data-event-cwd]',    btn.dataset.cwd);
            setText('[data-event-cmd]',    btn.dataset.cmd);
            setRowVisible('[data-event-cwd-row]', !!btn.dataset.cwd);
            setRowVisible('[data-event-cmd-row]', !!btn.dataset.cmd);
            if (select) select.value = btn.dataset.projectId || '';
            if (typeof modal.showModal === 'function') modal.showModal();
        });
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentId) return;
        const body = new URLSearchParams({
            _token: csrf,
            _method: 'PATCH',
            project_id: select?.value || '',
        });
        try {
            const res = await fetch(`/activity-events/${currentId}`, { method: 'POST', body });
            if (res.ok) window.location.reload();   // refresh para que el bloque muestre la nueva atribución
        } catch {}
    });
}
