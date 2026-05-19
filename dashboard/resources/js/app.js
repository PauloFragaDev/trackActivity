import './bootstrap';

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
