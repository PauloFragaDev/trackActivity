/**
 * Markdown editor con tabs Editar / Vista previa, estilo GitHub Issues.
 *
 * Markup esperado en el HTML (form-fields.blade.php produce esto):
 *
 *   <div class="markdown-editor" data-markdown-editor>
 *     <div class="markdown-editor__tabs" role="tablist">
 *       <button data-tab="edit"    class="markdown-editor__tab is-active">Editar</button>
 *       <button data-tab="preview" class="markdown-editor__tab">Vista previa</button>
 *     </div>
 *     <div class="markdown-editor__panel" data-panel="edit">
 *       <textarea name="description" ...></textarea>
 *     </div>
 *     <div class="markdown-editor__panel" data-panel="preview" hidden>
 *       <div data-markdown-preview class="markdown-editor__preview"></div>
 *     </div>
 *   </div>
 *
 * El textarea sigue siendo la "fuente de la verdad" para el form; el
 * panel de preview se rellena ON DEMAND (cuando se hace click en su tab)
 * para no parsear markdown en cada keystroke.
 *
 * Renderiza con `marked`. Sin sanitizer porque la app es single-user
 * (el usuario escribe su propio markdown, no hay XSS desde fuera).
 */
import { marked } from 'marked';

marked.setOptions({
    breaks: true,                  // soft line-break = <br> (más natural para tasks)
    gfm:    true,                  // tablas, strikethrough, autolinks
});

const EMPTY_PREVIEW_HTML = '<em class="markdown-editor__empty">Sin descripción.</em>';

function activate(editor, panelName) {
    editor.querySelectorAll('[data-tab]').forEach((tab) => {
        const active = tab.dataset.tab === panelName;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', String(active));
        tab.tabIndex = active ? 0 : -1;
    });
    editor.querySelectorAll('[data-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.panel !== panelName;
    });

    if (panelName === 'preview') {
        const textarea = editor.querySelector('textarea');
        const preview  = editor.querySelector('[data-markdown-preview]');
        if (textarea && preview) {
            const raw = textarea.value.trim();
            preview.innerHTML = raw ? marked.parse(raw) : EMPTY_PREVIEW_HTML;
        }
    }
}

function wire(editor) {
    if (editor.dataset.mdInitialized === '1') return;
    editor.dataset.mdInitialized = '1';

    editor.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('click', () => activate(editor, tab.dataset.tab));
    });

    // Teclado: flechas ←→ entre tabs cuando alguno tiene foco.
    editor.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            const tabs = [...editor.querySelectorAll('[data-tab]')];
            const i = tabs.indexOf(tab);
            const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
            next.focus();
            activate(editor, next.dataset.tab);
            e.preventDefault();
        });
    });
}

function scan(root) {
    (root.querySelectorAll?.('[data-markdown-editor]') ?? []).forEach(wire);
}

export function initMarkdownEditors() {
    scan(document);

    // Modales inyectan los editores tarde — observador.
    new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1) return;
                if (n.matches?.('[data-markdown-editor]')) wire(n);
                scan(n);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}
