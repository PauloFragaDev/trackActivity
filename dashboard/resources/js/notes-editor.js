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

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    /** Sube un archivo de imagen al servidor y devuelve la URL persistente. */
    const uploadImage = async (file) => {
        const body = new FormData();
        body.append('image', file);
        body.append('_token', csrf);
        const res = await fetch('/notes/images', { method: 'POST', body });
        if (!res.ok) throw new Error('Upload failed');
        const data = await res.json();
        return data.url;
    };

    try {
        await loadCrepeTheme();
        crepe = new Crepe({
            root: mount,
            defaultValue: textarea.value || '',
            featureConfigs: {
                [Crepe.Feature.ImageBlock]: {
                    onUpload: uploadImage,
                    blockUploadButton: 'Subir imagen',
                    blockUploadPlaceholderText: 'o pega la URL',
                    inlineUploadButton: 'Subir',
                    inlineUploadPlaceholderText: 'o pega la URL',
                },
            },
        });

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
    // After an image loads, the ImageViewer component sets img.style.height from
    // the image's natural dimensions (layout shift). ProseMirror doesn't know
    // about the change, so cursor coordinates for paragraphs below become stale.
    // Dispatching a resize event triggers ProseMirror's requestMeasure(), which
    // recalculates all positions and re-renders the cursor in the right place.
    const remeasure = () => window.dispatchEvent(new Event('resize'));

    const watchImgs = (root) => {
        const imgs = root.nodeName === 'IMG' ? [root] : [...root.querySelectorAll('img')];
        for (const img of imgs) {
            if (!img.complete) {
                // Load fires after Vue's onImageLoad (style.height already set)
                img.addEventListener('load', remeasure, { once: true });
            } else {
                // Already loaded (cache): Vue's handler already ran — re-measure now
                setTimeout(remeasure, 0);
            }
        }
    };

    // Images already in the editor when it initialized (opening an existing note)
    watchImgs(mount);

    // Images inserted later (after upload or paste)
    new MutationObserver((mutations) => {
        for (const { addedNodes } of mutations) {
            for (const node of addedNodes) {
                if (node.nodeType === 1) watchImgs(node);
            }
        }
    }).observe(mount, { childList: true, subtree: true });

    form.querySelector('input[name="title"]')?.addEventListener('input', scheduleSave);

    // Guardado manual: sincroniza el textarea antes del submit.
    form.addEventListener('submit', () => {
        if (crepe) try { textarea.value = crepe.getMarkdown(); } catch { /* noop */ }
    });
}
