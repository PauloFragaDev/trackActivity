/**
 * Helpers DOM mínimos para construir markup sin innerHTML y sin
 * recurrir a una librería de templates. Suficiente para el combobox.
 */

/**
 * Crea un elemento. Atributos como objeto: keys camelCase para event
 * listeners (`onClick`) y kebab-case se mantienen tal cual para HTML
 * attributes (`aria-label`, `data-foo`). Hijos: string → textNode,
 * array → cada uno individual, falsy → ignorado.
 *
 * @param {string} tag
 * @param {Record<string,unknown>} [attrs]
 * @param {...(Node|string|null|false)} children
 * @returns {HTMLElement}
 */
export function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') {
            node.className = value;
        } else if (key === 'style' && typeof value === 'object') {
            Object.assign(node.style, value);
        } else if (key.startsWith('on') && typeof value === 'function') {
            node.addEventListener(key.slice(2).toLowerCase(), value);
        } else if (key === 'dataset' && typeof value === 'object') {
            Object.assign(node.dataset, value);
        } else {
            node.setAttribute(key, value === true ? '' : String(value));
        }
    }
    appendChildren(node, children);
    return node;
}

/** Acepta strings, Nodes, arrays anidados y valores falsy (ignorados). */
function appendChildren(parent, children) {
    for (const child of children) {
        if (child === null || child === undefined || child === false) continue;
        if (Array.isArray(child)) {
            appendChildren(parent, child);
        } else if (child instanceof Node) {
            parent.appendChild(child);
        } else {
            parent.appendChild(document.createTextNode(String(child)));
        }
    }
}
