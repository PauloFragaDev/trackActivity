import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Markdown } from 'tiptap-markdown';

// Image extension extended with width + align attributes.
// Both are stored as data-* attributes on the <img> element so CSS handles
// the visual rules — no inline style.height, no layout shift, cursor stays correct.
const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: null,
                parseHTML: el => el.getAttribute('data-width'),
                renderHTML: attrs => attrs.width ? { 'data-width': attrs.width } : {},
            },
            align: {
                default: 'center',
                parseHTML: el => el.getAttribute('data-align') || 'center',
                renderHTML: attrs => ({ 'data-align': attrs.align || 'center' }),
            },
        };
    },
});

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

    const fileInput = document.createElement('input');
    fileInput.type   = 'file';
    fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
    fileInput.style.display = 'none';
    mount.appendChild(fileInput);

    const toolbar = buildToolbar();
    mount.appendChild(toolbar);

    const rawContent = textarea.value || '';
    const isHtml = rawContent.trim().startsWith('<');

    let editor;
    try {
        textarea.classList.add('hidden');
        mount.removeAttribute('hidden');

        editor = new Editor({
            element: mount,
            extensions: [
                StarterKit,
                ResizableImage.configure({ inline: false, allowBase64: false }),
                Markdown.configure({ html: true, transformPastedText: true }),
            ],
            content: isHtml ? '' : rawContent,
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
                textarea.value = editor.getHTML();
                scheduleSave();
            },
        });

        // Si el contenido es HTML, cargarlo después de init usando el parser DOM nativo
        if (isHtml && rawContent) {
            editor.commands.setContent(rawContent, false);
        }
    } catch (err) {
        console.error('Notas: no se pudo iniciar Tiptap; se usa el textarea.', err);
        textarea.classList.remove('hidden');
        mount.setAttribute('hidden', '');
        return;
    }

    toolbar.addEventListener('mousedown', (e) => {
        const btn = e.target.closest('[data-cmd]');
        if (!btn) return;
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        switch (cmd) {
            case 'bold':       editor.chain().focus().toggleBold().run();                break;
            case 'italic':     editor.chain().focus().toggleItalic().run();              break;
            case 'strike':     editor.chain().focus().toggleStrike().run();              break;
            case 'h1':         editor.chain().focus().toggleHeading({ level: 1 }).run(); break;
            case 'h2':         editor.chain().focus().toggleHeading({ level: 2 }).run(); break;
            case 'h3':         editor.chain().focus().toggleHeading({ level: 3 }).run(); break;
            case 'bullet':     editor.chain().focus().toggleBulletList().run();          break;
            case 'ordered':    editor.chain().focus().toggleOrderedList().run();         break;
            case 'blockquote': editor.chain().focus().toggleBlockquote().run();          break;
            case 'code':       editor.chain().focus().toggleCodeBlock().run();           break;
            case 'hr':         editor.chain().focus().setHorizontalRule().run();         break;
            case 'image':      fileInput.click();                                        break;
            // Image width presets
            case 'img-w-25':   editor.chain().focus().updateAttributes('image', { width: '25%' }).run();  break;
            case 'img-w-50':   editor.chain().focus().updateAttributes('image', { width: '50%' }).run();  break;
            case 'img-w-75':   editor.chain().focus().updateAttributes('image', { width: '75%' }).run();  break;
            case 'img-w-100':  editor.chain().focus().updateAttributes('image', { width: null  }).run();  break;
            // Image alignment
            case 'img-a-left':   editor.chain().focus().updateAttributes('image', { align: 'left'   }).run(); break;
            case 'img-a-center': editor.chain().focus().updateAttributes('image', { align: 'center' }).run(); break;
            case 'img-a-right':  editor.chain().focus().updateAttributes('image', { align: 'right'  }).run(); break;
        }
    });

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
        textarea.value = editor.getHTML();
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

        <span class="ntb-image-controls">
            <span class="ntb-sep"></span>
            <span class="ntb-label">Ancho</span>
            <button type="button" data-cmd="img-w-25"  class="ntb" title="25%">¼</button>
            <button type="button" data-cmd="img-w-50"  class="ntb" title="50%">½</button>
            <button type="button" data-cmd="img-w-75"  class="ntb" title="75%">¾</button>
            <button type="button" data-cmd="img-w-100" class="ntb" title="Ancho completo">↔</button>
            <span class="ntb-sep"></span>
            <span class="ntb-label">Alinear</span>
            <button type="button" data-cmd="img-a-left"   class="ntb" title="Izquierda">&#8676;</button>
            <button type="button" data-cmd="img-a-center" class="ntb" title="Centrar">&#8596;</button>
            <button type="button" data-cmd="img-a-right"  class="ntb" title="Derecha">&#8677;</button>
        </span>
    `;
    return bar;
}

function syncToolbar(toolbar, editor) {
    const isImage = editor.isActive('image');
    toolbar.classList.toggle('has-image-active', isImage);

    const imgAttrs = isImage ? editor.getAttributes('image') : {};

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
        // Image width active state
        'img-w-25':  () => imgAttrs.width === '25%',
        'img-w-50':  () => imgAttrs.width === '50%',
        'img-w-75':  () => imgAttrs.width === '75%',
        'img-w-100': () => !imgAttrs.width,
        // Image align active state
        'img-a-left':   () => imgAttrs.align === 'left',
        'img-a-center': () => !imgAttrs.align || imgAttrs.align === 'center',
        'img-a-right':  () => imgAttrs.align === 'right',
    };
    for (const [cmd, isActive] of Object.entries(map)) {
        toolbar.querySelector(`[data-cmd="${cmd}"]`)?.classList.toggle('ntb--active', isActive());
    }
}
