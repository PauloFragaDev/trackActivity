import './bootstrap';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import Toastify from 'toastify-js';
import 'toastify-js/src/toastify.css';

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

/** Toast de feedback: entra y sale deslizándose desde abajo. */
window.toast = (message) => {
    Toastify({
        text: message,
        duration: 3200,
        gravity: 'bottom',
        position: 'center',
        className: 'app-toast',
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

    // Paneles plegables de Notas (carpetas y lista).
    document.querySelectorAll('[data-panel-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.panelToggle;   // 'folders' | 'list'
            const collapsed = document.documentElement.classList.toggle(`notes-${key}-collapsed`);
            localStorage.setItem(`notes-${key}`, collapsed ? 'collapsed' : 'expanded');
        });
    });

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
});
