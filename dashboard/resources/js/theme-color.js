/**
 * Mantiene <meta name="theme-color"> en sintonia con el acento del tema
 * activo (cambia por paleta y por modo claro/oscuro). Lee el valor
 * computado de --accent, que ya esta resuelto cuando corre app.js
 * (script diferido, CSS aplicado). Sin mapa de colores que mantener.
 */
export function syncThemeColor() {
    const accent = getComputedStyle(document.documentElement)
        .getPropertyValue('--accent').trim();
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta && accent) meta.setAttribute('content', accent);
}
