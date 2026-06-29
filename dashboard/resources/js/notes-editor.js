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

    const status = document.querySelector('[data-autosave-status]');
    const setStatus = (txt) => { if (status) status.textContent = txt; };

    let crepe;
    let timer;

    const scheduleSave = () => {
        setStatus('Sin guardar…');
        clearTimeout(timer);
        timer = setTimeout(save, 1200);
    };

    const save = async () => {
        if (crepe) try { textarea.value = crepe.getMarkdown(); } catch { /* noop */ }
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

    try {
        await loadCrepeTheme();
        crepe = new Crepe({ root: mount, defaultValue: textarea.value || '' });

        // markdownUpdated fires on every editor change — must register before create()
        crepe.on((api) => {
            api.markdownUpdated((_ctx, markdown) => {
                textarea.value = markdown;
                scheduleSave();
            });
        });

        // Show the editor div before create() so ProseMirror can measure its layout.
        // If create() fails, we restore visibility below.
        textarea.classList.add('hidden');
        mount.removeAttribute('hidden');

        await crepe.create();
    } catch (err) {
        console.error('Notas: no se pudo iniciar el editor; se usa el textarea.', err);
        textarea.classList.remove('hidden');
        mount.setAttribute('hidden', '');
        return;
    }

    // Cambios en el título también disparan el autosave.
    form.querySelector('input[name="title"]')?.addEventListener('input', scheduleSave);

    // Guardado manual: sincroniza el textarea antes del submit.
    form.addEventListener('submit', () => {
        if (crepe) try { textarea.value = crepe.getMarkdown(); } catch { /* noop */ }
    });
}
