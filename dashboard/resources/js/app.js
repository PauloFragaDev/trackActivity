import './bootstrap';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

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
// SweetAlert: confirmaciones y toasts.
// ──────────────────────────────────────────────

/** Colores de SweetAlert adaptados al tema activo. */
function swalTheme() {
    return document.documentElement.classList.contains('dark')
        ? { background: '#1e293b', color: '#e2e8f0' }
        : {};
}

/** Toast de feedback en la parte inferior-centro. */
window.toast = (message, icon = 'success') => {
    Swal.fire({
        toast: true,
        position: 'bottom',
        icon,
        title: message,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        ...swalTheme(),
    });
};

window.addEventListener('DOMContentLoaded', () => {
    // Confirmaciones: <form data-confirm="mensaje"> pide confirmación con
    // SweetAlert en vez del confirm() nativo del navegador.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmed === '1') return;   // ya confirmado: deja pasar
            e.preventDefault();
            Swal.fire({
                title: form.dataset.confirmTitle || '¿Eliminar?',
                text: form.dataset.confirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                ...swalTheme(),
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit();
                }
            });
        });
    });

    // Toast de guardado: el layout deja el mensaje flash en #flash-data.
    const flash = document.getElementById('flash-data');
    if (flash && flash.dataset.message) {
        window.toast(flash.dataset.message);
    }
});
