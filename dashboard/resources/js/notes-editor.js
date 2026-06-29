import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Markdown } from 'tiptap-markdown';

export async function initNoteEditor() {
    const form     = document.querySelector('[data-note-form]');
    const mount    = document.querySelector('[data-note-editor]');
    const textarea = form?.querySelector('textarea[name="body"]');
    if (!form || !mount || !textarea) return;

    const status   = document.querySelector('[data-autosave-status]');
    const setStatus = (txt) => { if (status) status.textContent = txt; };

    let timer;
    const scheduleSave = () => {
        setStatus('Sin guardar…');
        clearTimeout(timer);
        timer = setTimeout(save, 1200);
    };

    const save = async () => {
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

    const uploadImage = async (file) => {
        const body = new FormData();
        body.append('image', file);
        body.append('_token', csrf);
        const res = await fetch('/notes/images', { method: 'POST', body });
        if (!res.ok) throw new Error('Upload failed');
        const { url } = await res.json();
        return url;
    };

    // Hidden file input for picking images from disk
    const fileInput = document.createElement('input');
    fileInput.type    = 'file';
    fileInput.accept  = 'image/jpeg,image/png,image/gif,image/webp';
    fileInput.style.display = 'none';
    mount.appendChild(fileInput);

    // Toolbar (rendered before the editor div so it sits on top)
    const toolbar = buildToolbar();
    mount.appendChild(toolbar);

    let editor;
    try {
        textarea.classList.add('hidden');
        mount.removeAttribute('hidden');

        editor = new Editor({
            element: mount,
            extensions: [
                StarterKit,
                Image.configure({ inline: false, allowBase64: false }),
                Markdown.configure({ html: false, transformPastedText: true }),
            ],
            content: textarea.value || '',
            editorProps: {
                handlePaste(_view, event) {
                    const items = [...(event.clipboardData?.items ?? [])];
                    const img   = items.find(i => i.type.startsWith('image/'));
                    if (!img) return false;
                    event.preventDefault();
                    const file = img.getAsFile();
                    if (!file) return true;
                    uploadImage(file)
                        .then(url => editor.chain().focus().setImage({ src: url }).run())
                        .catch(() => {});
                    return true;
                },
                handleDrop(_view, event) {
                    const files = [...(event.dataTransfer?.files ?? [])].filter(f => f.type.startsWith('image/'));
                    if (!files.length) return false;
                    event.preventDefault();
                    files.forEach(file => {
                        uploadImage(file)
                            .then(url => editor.chain().focus().setImage({ src: url }).run())
                            .catch(() => {});
                    });
                    return true;
                },
            },
            onUpdate({ editor }) {
                textarea.value = editor.storage.markdown.getMarkdown();
                scheduleSave();
            },
        });
    } catch (err) {
        console.error('Notas: no se pudo iniciar Tiptap; se usa el textarea.', err);
        textarea.classList.remove('hidden');
        mount.setAttribute('hidden', '');
        return;
    }

    // Toolbar: mousedown (not click) to avoid blurring the editor
    toolbar.addEventListener('mousedown', (e) => {
        const btn = e.target.closest('[data-cmd]');
        if (!btn) return;
        e.preventDefault();
        switch (btn.dataset.cmd) {
            case 'bold':       editor.chain().focus().toggleBold().run();               break;
            case 'italic':     editor.chain().focus().toggleItalic().run();             break;
            case 'strike':     editor.chain().focus().toggleStrike().run();             break;
            case 'h1':         editor.chain().focus().toggleHeading({ level: 1 }).run(); break;
            case 'h2':         editor.chain().focus().toggleHeading({ level: 2 }).run(); break;
            case 'h3':         editor.chain().focus().toggleHeading({ level: 3 }).run(); break;
            case 'bullet':     editor.chain().focus().toggleBulletList().run();         break;
            case 'ordered':    editor.chain().focus().toggleOrderedList().run();        break;
            case 'blockquote': editor.chain().focus().toggleBlockquote().run();         break;
            case 'code':       editor.chain().focus().toggleCodeBlock().run();          break;
            case 'hr':         editor.chain().focus().setHorizontalRule().run();        break;
            case 'image':      fileInput.click();                                       break;
        }
    });

    // Highlight active toolbar buttons on every state change
    editor.on('transaction', () => syncToolbar(toolbar, editor));

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        if (!file) return;
        fileInput.value = '';
        try {
            const url = await uploadImage(file);
            editor.chain().focus().setImage({ src: url }).run();
        } catch { /* silently ignore */ }
    });

    form.querySelector('input[name="title"]')?.addEventListener('input', scheduleSave);

    form.addEventListener('submit', () => {
        textarea.value = editor.storage.markdown.getMarkdown();
    });
}

function buildToolbar() {
    const bar = document.createElement('div');
    bar.className = 'note-toolbar';

    const imgIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
        <circle cx="9" cy="9" r="2"/>
        <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
    </svg>`;

    bar.innerHTML = `
        <button type="button" data-cmd="bold"       class="ntb" title="Negrita"><strong>B</strong></button>
        <button type="button" data-cmd="italic"     class="ntb" title="Cursiva"><em>I</em></button>
        <button type="button" data-cmd="strike"     class="ntb" title="Tachado"><s>S</s></button>
        <span class="ntb-sep"></span>
        <button type="button" data-cmd="h1"         class="ntb" title="Título 1">H1</button>
        <button type="button" data-cmd="h2"         class="ntb" title="Título 2">H2</button>
        <button type="button" data-cmd="h3"         class="ntb" title="Título 3">H3</button>
        <span class="ntb-sep"></span>
        <button type="button" data-cmd="bullet"     class="ntb" title="Lista">&#8226;&#8212;</button>
        <button type="button" data-cmd="ordered"    class="ntb" title="Lista numerada">1.</button>
        <button type="button" data-cmd="blockquote" class="ntb" title="Cita">&#10077;</button>
        <button type="button" data-cmd="code"       class="ntb" title="Bloque de código">&lt;/&gt;</button>
        <button type="button" data-cmd="hr"         class="ntb" title="Separador">&#8212;</button>
        <span class="ntb-sep"></span>
        <button type="button" data-cmd="image"      class="ntb" title="Insertar imagen">${imgIcon}</button>
    `;
    return bar;
}

function syncToolbar(toolbar, editor) {
    const map = {
        bold:       () => editor.isActive('bold'),
        italic:     () => editor.isActive('italic'),
        strike:     () => editor.isActive('strike'),
        h1:         () => editor.isActive('heading', { level: 1 }),
        h2:         () => editor.isActive('heading', { level: 2 }),
        h3:         () => editor.isActive('heading', { level: 3 }),
        bullet:     () => editor.isActive('bulletList'),
        ordered:    () => editor.isActive('orderedList'),
        blockquote: () => editor.isActive('blockquote'),
        code:       () => editor.isActive('codeBlock'),
    };
    for (const [cmd, isActive] of Object.entries(map)) {
        toolbar.querySelector(`[data-cmd="${cmd}"]`)?.classList.toggle('ntb--active', isActive());
    }
}
