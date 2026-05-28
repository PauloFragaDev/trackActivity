/**
 * Selector de tema en /settings/appearance.
 *
 * Click en una card:
 *   1. Aplica `data-theme` al <html> y persiste en localStorage al
 *      instante — feedback inmediato, sin esperar el round-trip.
 *   2. Marca la card como activa, actualiza el estado de las demás.
 *   3. POST async a /settings/appearance para que el servidor lo
 *      recuerde en el próximo reload (y en otros navegadores).
 *
 * Reutiliza la clase .theme-transition que app.js usa para el toggle
 * claro/oscuro: así el cambio de paleta también lleva crossfade suave.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function initThemePicker() {
    const grid = document.querySelector('[data-theme-grid]');
    if (! grid) return;

    const url = grid.dataset.saveUrl;

    grid.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-theme-id]');
        if (! card) return;
        const id   = card.dataset.themeId;
        const root = document.documentElement;

        // 1) Aplicar al instante con crossfade.
        root.classList.add('theme-transition');
        root.setAttribute('data-theme', id);
        localStorage.setItem('themeId', id);
        window.setTimeout(() => root.classList.remove('theme-transition'), 400);

        // 2) Marcar la card activa.
        grid.querySelectorAll('[data-theme-id]').forEach((c) => {
            c.classList.toggle('theme-card--active', c === card);
        });
        grid.dataset.current = id;

        // 3) Persistir en backend (best-effort). Si falla, el cambio queda
        //    en localStorage; el siguiente reload lo restaura desde aquí.
        try {
            await fetch(url, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':  csrf(),
                    'Accept':        'application/json',
                },
                body: JSON.stringify({ theme_id: id }),
            });
        } catch {}
    });
}
