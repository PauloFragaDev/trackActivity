# Markdown editor

Toggle "Editar / Vista previa" sobre un `<textarea>`, estilo GitHub
Issues. La fuente de la verdad sigue siendo el textarea — el preview
se renderiza on-demand (solo cuando el usuario pulsa el tab) para no
parsear Markdown en cada keystroke.

## Markup

El partial Blade `tasks/partials/form-fields.blade.php` ya genera este
markup. Para usarlo en otra vista:

```blade
<div class="markdown-editor" data-markdown-editor>
    <div class="markdown-editor__tabs" role="tablist">
        <button type="button" data-tab="edit"    class="markdown-editor__tab is-active"
                role="tab" aria-selected="true"  tabindex="0">Editar</button>
        <button type="button" data-tab="preview" class="markdown-editor__tab"
                role="tab" aria-selected="false" tabindex="-1">Vista previa</button>
    </div>
    <div class="markdown-editor__panel" data-panel="edit">
        <textarea name="description" rows="5"
                  class="textarea font-mono text-[13px]"
                  placeholder="Markdown opcional…"></textarea>
    </div>
    <div class="markdown-editor__panel" data-panel="preview" hidden>
        <div data-markdown-preview class="markdown-editor__preview"></div>
    </div>
</div>
```

`initMarkdownEditors()` (lazy-imported desde `app.js` si la página tiene
algún `[data-markdown-editor]`) cablea click en tabs + flechas ← → entre
ellos para navegación por teclado.

## Por qué no live preview

Renderizar en cada keystroke parece más fluido pero:

- gasta CPU si la descripción es larga.
- escribir Markdown crudo (lo que pidió el usuario) requiere ver el
  texto fuente, no su render mientras lo tecleas.

El patrón "two tabs" es exactamente lo que usa GitHub Issues, GitLab,
Linear (todos los apps técnicas serias). Es lo esperado.

## Sin sanitizer

`marked` genera HTML sin sanitizar. La app es single-user — el propio
usuario escribe su Markdown, no hay vector XSS desde fuera. Si en
algún momento se permite que otra fuente meta descripciones (sync con
GitHub Projects con comentarios de otros, por ejemplo), añadir
[DOMPurify](https://github.com/cure53/DOMPurify) entre `parse()` y
`innerHTML`.

## Estilos

CSS en `resources/css/app.css` bajo el bloque
`/* ─── Markdown editor ─── */`. Cubre tabs, panel, preview con tipos
de Markdown (h1-h6, código, listas, blockquote, tablas, enlaces).

## API programática

Por ahora ninguna. Si hace falta forzar el panel preview desde fuera:

```js
const editor = document.querySelector('[data-markdown-editor]');
editor.querySelector('[data-tab="preview"]').click();
```
