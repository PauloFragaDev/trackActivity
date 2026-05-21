/**
 * Editor WYSIWYG de notas (Milkdown Crepe) — escritura sobre Markdown.
 *
 * Mejora progresiva: monta Crepe sobre el <textarea> de la nota. Si algo
 * falla (o no hay JS), el textarea queda visible y la nota se sigue
 * editando y guardando con el botón Guardar.
 *
 * Este módulo se carga de forma diferida (import dinámico) solo en la
 * página de notas — Crepe es pesado y no debe entrar en el bundle base.
 */
import { Crepe } from '@milkdown/crepe';
import '@milkdown/crepe/theme/common/style.css';

/** Carga el tema de Crepe acorde al modo claro/oscuro de la app. */
async function loadCrepeTheme() {
    if (document.documentElement.classList.contains('dark')) {
        await import('@milkdown/crepe/theme/frame-dark.css');
    } else {
        await import('@milkdown/crepe/theme/frame.css');
    }
}

export async function initNoteEditor() {
    const form     = document.querySelector('[data-note-form]');
    const mount    = document.querySelector('[data-note-editor]');
    const textarea = form?.querySelector('textarea[name="body"]');
    if (!form || !mount || !textarea) return;

    let crepe;
    try {
        await loadCrepeTheme();
        crepe = new Crepe({ root: mount, defaultValue: textarea.value || '' });
        await crepe.create();
    } catch (err) {
        // Fallback: el textarea sigue visible y usable.
        console.error('Notas: no se pudo iniciar el editor; se usa el textarea.', err);
        return;
    }

    // Editor montado: el textarea pasa a ser el campo oculto del formulario.
    textarea.classList.add('hidden');
    mount.removeAttribute('hidden');

    const status = document.querySelector('[data-autosave-status]');
    const setStatus = (txt) => { if (status) status.textContent = txt; };

    const syncTextarea = () => {
        try { textarea.value = crepe.getMarkdown(); } catch { /* noop */ }
    };

    // ── Autosave con debounce ──
    let timer;
    const scheduleSave = () => {
        syncTextarea();
        setStatus('Sin guardar…');
        clearTimeout(timer);
        timer = setTimeout(save, 1200);
    };

    const save = async () => {
        syncTextarea();
        setStatus('Guardando…');
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            setStatus(res.ok ? 'Guardado ✓' : 'Error al guardar');
        } catch {
            setStatus('Error al guardar');
        }
    };

    // Cambios en el editor y en el título disparan el autosave.
    mount.addEventListener('input', scheduleSave);
    form.querySelector('input[name="title"]')?.addEventListener('input', scheduleSave);

    // Guardado manual (botón Guardar): asegura el textarea sincronizado.
    form.addEventListener('submit', syncTextarea);
}
