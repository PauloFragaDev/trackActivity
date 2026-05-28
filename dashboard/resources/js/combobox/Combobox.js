import { el } from './dom.js';

/**
 * Combobox accesible que reemplaza visualmente a un <select> nativo.
 *
 * Estructura de DOM producida:
 *
 *   <div class="combobox" role="combobox" aria-haspopup="listbox" aria-expanded="...">
 *     <button class="combobox__trigger" type="button" aria-controls="...">
 *       <span class="combobox__value">Seleccionado</span>
 *       <span class="combobox__chevron" aria-hidden="true">▾</span>
 *     </button>
 *     <div class="combobox__popover" hidden>
 *       <input class="combobox__search" placeholder="Buscar..." />
 *       <ul class="combobox__list" role="listbox" id="...">
 *         <li role="option" aria-selected="...">Texto</li>
 *         ...
 *       </ul>
 *     </div>
 *   </div>
 *   <select hidden>...</select>   <!-- original, no se mueve del form -->
 *
 * El <select> original queda OCULTO pero presente y sincronizado con el
 * value elegido, así el form se envía sin cambios server-side.
 *
 * Contrato esperado del exterior:
 *   - Si el JS de la app cambia `select.value` y dispara `change` event,
 *     el combobox refresca su display automáticamente (ver `_onSelectChange`).
 *   - Al destruir el combobox (`destroy()`), restaura el <select> a estado
 *     normal y elimina los nodos creados.
 *
 * Teclado:
 *   - Trigger:  Enter / Space / ↓  → abre.
 *   - Popover:  ↑↓  navega · Enter elige · Esc cierra · Tab cierra y salta.
 *   - Letras:   focus en el input → filtra; sin focus → se enfoca el input.
 */
export class Combobox {
    static _idCounter = 0;

    constructor(select) {
        this.select = select;
        this.options = this._readOptions();
        this.activeIndex = this._initialIndex();
        this.isOpen = false;
        this._id = `cb-${++Combobox._idCounter}`;

        this._mount();
        this._wireEvents();
        // Refresca cuando alguien externo cambia el value del <select>.
        this._onSelectChange = this._onSelectChange.bind(this);
        this.select.addEventListener('change', this._onSelectChange);
    }

    // ─── Construcción del DOM ──────────────────────────────────

    _mount() {
        // Componentes (los referenciamos para usar después).
        this.valueEl  = el('span', { class: 'combobox__value' }, this._currentLabel());
        this.chevron  = el('span', { class: 'combobox__chevron', 'aria-hidden': 'true' });
        this.trigger  = el('button', {
            type:            'button',
            class:           'combobox__trigger',
            'aria-controls': this._id,
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
        }, this.valueEl, this.chevron);

        this.searchEl = el('input', {
            type:           'text',
            class:          'combobox__search',
            placeholder:    'Buscar…',
            autocomplete:   'off',
            spellcheck:     'false',
            'aria-controls': this._id,
        });
        // Si hay pocas opciones la búsqueda no aporta — la escondemos.
        if (this.options.length <= 5) {
            this.searchEl.style.display = 'none';
        }

        this.listEl = el('ul', { class: 'combobox__list', role: 'listbox', id: this._id });
        this.popover = el('div', { class: 'combobox__popover', hidden: '' },
            this.searchEl, this.listEl);

        this.root = el('div', { class: 'combobox', role: 'combobox' },
            this.trigger, this.popover);

        // Inyectar el wrapper inmediatamente antes del <select>, ocultar el original.
        this.select.parentNode.insertBefore(this.root, this.select);
        this.select.setAttribute('hidden', '');
        this.select.setAttribute('tabindex', '-1');
        this.select.classList.add('combobox__source');

        this._renderList();
    }

    _renderList() {
        const query = this.searchEl.value.trim().toLowerCase();
        this.listEl.innerHTML = '';
        const matches = this.options
            .map((opt, i) => ({ ...opt, originalIndex: i }))
            .filter((opt) => ! query || opt.text.toLowerCase().includes(query));

        if (matches.length === 0) {
            this.listEl.appendChild(el('li', { class: 'combobox__empty' }, 'Sin resultados'));
            this.activeIndex = -1;
            return;
        }

        // Si el activeIndex actual cae fuera del filtrado, lo anclamos al primero.
        const activeStillVisible = matches.some((m) => m.originalIndex === this.activeIndex);
        if (! activeStillVisible) this.activeIndex = matches[0].originalIndex;

        matches.forEach((opt) => {
            const isActive   = opt.originalIndex === this.activeIndex;
            const isSelected = opt.value === this.select.value;
            const li = el('li', {
                class:          'combobox__option' +
                                (isActive   ? ' is-active'   : '') +
                                (isSelected ? ' is-selected' : ''),
                role:           'option',
                'aria-selected': String(isSelected),
                'data-value':    opt.value,
                'data-index':    String(opt.originalIndex),
                onClick:        () => this._commit(opt.originalIndex),
                onMouseenter:   () => this._setActive(opt.originalIndex, /* scrollIntoView */ false),
            }, opt.text);
            this.listEl.appendChild(li);
        });
    }

    // ─── Estado y modelo ───────────────────────────────────────

    _readOptions() {
        return [...this.select.options].map((o) => ({
            value: o.value,
            text:  o.textContent.trim(),
        }));
    }

    _currentLabel() {
        const opt = [...this.select.options].find((o) => o.value === this.select.value);
        return opt ? opt.textContent.trim() : (this.select.options[0]?.textContent.trim() ?? '');
    }

    _initialIndex() {
        const idx = [...this.select.options].findIndex((o) => o.value === this.select.value);
        return idx >= 0 ? idx : 0;
    }

    /** Llamado cuando el <select> cambia desde fuera (JS de la app). */
    _onSelectChange() {
        this.valueEl.textContent = this._currentLabel();
        this.activeIndex = this._initialIndex();
        if (this.isOpen) this._renderList();
    }

    // ─── Control de apertura ───────────────────────────────────

    open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.popover.hidden = false;
        this.root.classList.add('is-open');
        this.trigger.setAttribute('aria-expanded', 'true');
        this.searchEl.value = '';
        this._renderList();
        // Focus al input de búsqueda (si está visible) o a la lista.
        if (this.searchEl.style.display !== 'none') {
            this.searchEl.focus();
        }
        this._scrollActiveIntoView();
        document.addEventListener('mousedown', this._onDocMouseDown, true);
    }

    close() {
        if (! this.isOpen) return;
        this.isOpen = false;
        this.popover.hidden = true;
        this.root.classList.remove('is-open');
        this.trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('mousedown', this._onDocMouseDown, true);
    }

    _setActive(index, scrollIntoView = true) {
        this.activeIndex = index;
        this.listEl.querySelectorAll('.combobox__option').forEach((li) => {
            const isActive = Number(li.dataset.index) === index;
            li.classList.toggle('is-active', isActive);
        });
        if (scrollIntoView) this._scrollActiveIntoView();
    }

    _scrollActiveIntoView() {
        const active = this.listEl.querySelector('.combobox__option.is-active');
        if (active) active.scrollIntoView({ block: 'nearest' });
    }

    /** Aplica la opción `index` al <select>, dispara `change`, cierra. */
    _commit(index) {
        const opt = this.options[index];
        if (! opt) return;
        const prev = this.select.value;
        this.select.value = opt.value;
        this.valueEl.textContent = opt.text;
        if (prev !== opt.value) {
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
            this.select.dispatchEvent(new Event('input',  { bubbles: true }));
        }
        this.close();
        this.trigger.focus();
    }

    // ─── Eventos ───────────────────────────────────────────────

    _wireEvents() {
        this.trigger.addEventListener('click', () => {
            this.isOpen ? this.close() : this.open();
        });
        this.trigger.addEventListener('keydown', (e) => {
            if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
                e.preventDefault();
                this.open();
            }
        });

        this.searchEl.addEventListener('input', () => this._renderList());
        this.searchEl.addEventListener('keydown', (e) => this._handleKey(e));
        this.listEl.addEventListener('keydown',  (e) => this._handleKey(e));

        // Listener de "click fuera" inyectado en open()/close() para que
        // no esté siempre escuchando.
        this._onDocMouseDown = (e) => {
            if (! this.root.contains(e.target)) this.close();
        };
    }

    _handleKey(e) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this._moveActive(+1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this._moveActive(-1);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.activeIndex >= 0) this._commit(this.activeIndex);
                break;
            case 'Escape':
                e.preventDefault();
                this.close();
                this.trigger.focus();
                break;
            case 'Tab':
                this.close();
                break;
        }
    }

    _moveActive(delta) {
        const visibleIndices = [...this.listEl.querySelectorAll('.combobox__option')]
            .map((li) => Number(li.dataset.index));
        if (visibleIndices.length === 0) return;
        const pos = visibleIndices.indexOf(this.activeIndex);
        const next = visibleIndices[(pos + delta + visibleIndices.length) % visibleIndices.length];
        this._setActive(next);
    }

    // ─── Limpieza ──────────────────────────────────────────────

    destroy() {
        this.select.removeEventListener('change', this._onSelectChange);
        document.removeEventListener('mousedown', this._onDocMouseDown, true);
        this.root.remove();
        this.select.removeAttribute('hidden');
        this.select.removeAttribute('tabindex');
        this.select.classList.remove('combobox__source');
    }
}
