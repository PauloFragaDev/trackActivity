# 16 · Plan de desarrollo — Módulo Notas

Plan del segundo módulo nuevo: un gestor de notas con organización en
carpetas (estilo Mac Notes) y edición estilo Notion. Es **100 % lado
dashboard** (Laravel + Blade) — no toca el daemon Python.

> Documento de plan; los hitos se marcan a medida que se completan.

---

## 1. Objetivo y alcance

**Es** un bloc de notas personal: notas escritas en un editor cómodo,
organizadas en carpetas anidables. Independiente del tracking y de los
proyectos — las notas **no** se vinculan a proyectos.

**No es** un clon de Notion: sin bases de datos, sin páginas-wiki
enlazadas, sin colaboración. Se replica la *experiencia de escritura* y la
*organización en carpetas*, no el producto entero.

---

## 2. Modelo de datos

Dos tablas nuevas.

`note_folders`:

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint | |
| `name` | string | |
| `parent_id` | FK self nullable | Carpetas anidadas; `nullOnDelete` |
| `position` | integer | Orden entre hermanas |
| `created_at` / `updated_at` | datetime | |

`notes`:

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint | |
| `folder_id` | FK `note_folders` nullable | `null` = sin carpeta (raíz); `nullOnDelete` |
| `title` | string | |
| `body` | text | **Contenido en Markdown** |
| `pinned` | boolean (default `false`) | Notas fijadas arriba |
| `position` | integer | Orden dentro de la carpeta |
| `created_at` / `updated_at` | datetime | |

Al borrar una carpeta: confirmación, y sus notas y subcarpetas pasan a la
raíz (`nullOnDelete`) — no se pierde contenido.

---

## 3. El editor — decisión tomada

Editor **WYSIWYG sobre Markdown** (acordado con el usuario):

- El contenido se **guarda como Markdown plano** en `notes.body` →
  portable, se busca con un `LIKE`, se exporta/pega en cualquier sitio, y
  encaja con un proyecto que ya es Markdown por todas partes.
- Experiencia de escritura tipo Notion: **formato en vivo**, **menú `/`**
  para insertar bloques (encabezado, lista, checklist, cita, código…) y
  **atajos Markdown** (`#`, `-`, `[]`, `>`).
- **Reordenar bloques arrastrando**: sí. Los editores WYSIWYG-Markdown
  sobre ProseMirror (p. ej. **Milkdown** y su editor *Crepe*) admiten un
  *block handle* para arrastrar bloques. Los "bloques" son nodos Markdown
  (párrafos, encabezados, ítems): el arrastre funciona y la serialización
  sigue siendo Markdown.
- Lo único que **no** mapea limpio a Markdown son los *toggles*
  colapsables de Notion. v1 sin toggles; si se quieren, se aproximan con
  `<details>` HTML en una v2.
- Render para *ver* una nota y para extractos en la lista: `league/commonmark`
  (dependencia PHP).

---

## 4. Decisiones de diseño

- **Carpetas anidadas** (`parent_id`) desde el esquema; la UI puede empezar
  mostrando 1–2 niveles e iterar.
- **Autosave**: el editor guarda con *debounce* vía AJAX
  (`PATCH /notes/{note}`) — comportamiento esperado en un gestor de notas.
- **Búsqueda** con `LIKE` sobre `title` + `body` (el Markdown plano lo hace
  trivial).
- Notas **fijadas** (`pinned`) ordenadas arriba de su lista.

---

## 5. Rutas (`web.php`, sección nueva)

| Método | Ruta | Acción |
|--------|------|--------|
| GET | `/notes` | Vista de 3 paneles (carpetas · lista · editor) |
| GET | `/notes/{note}` | Cargar una nota (directo o vía AJAX dentro de `/notes`) |
| POST · PATCH · DELETE | `/notes` · `/notes/{note}` | CRUD de notas (`PATCH` = autosave) |
| POST · PATCH · DELETE | `/note-folders` · `/note-folders/{folder}` | CRUD de carpetas |
| GET | `/notes/search?q=` | Búsqueda |

---

## 6. UI

Disposición de **3 paneles**, estilo Mac Notes:

```
┌──────────┬────────────────┬─────────────────────────┐
│ Carpetas │ Notas de la    │ Editor de la nota       │
│ (árbol)  │ carpeta        │ (WYSIWYG-Markdown)      │
└──────────┴────────────────┴─────────────────────────┘
```

- Sidebar de carpetas (árbol, plegable).
- Lista de notas de la carpeta seleccionada (fijadas primero).
- Editor WYSIWYG-Markdown a la derecha, con autosave.
- Búsqueda en la cabecera del módulo.
- Ítem nuevo en el nav del layout: **Notas**.

---

## 7. Dependencias nuevas

- **Editor JS WYSIWYG-Markdown** (p. ej. **Milkdown / Crepe**, ProseMirror)
  — bundlado vía npm, sin CDN. Será la dependencia JS más pesada del
  proyecto: salto de peso consciente.
- **`league/commonmark`** (Composer) — render Markdown → HTML para ver
  notas y generar extractos.

---

## 8. Hitos

| Hito | Contenido | Aceptación |
|------|-----------|------------|
| **N1** | Migraciones (`note_folders`, `notes`) + modelos + CRUD de carpetas y notas con un `textarea` plano. | Crear/editar/borrar carpetas y notas funciona. |
| **N2** | Vista de 3 paneles + navegación carpetas ↔ notas. | Se navega y selecciona como en Mac Notes. |
| **N3** | Integrar el editor WYSIWYG-Markdown + arrastre de bloques + autosave. | Escribir se siente como en Notion; guarda Markdown. |
| **N4** | Búsqueda, notas fijadas, pulido + tests + docs. | Módulo usable a diario; suite en verde. |

---

## 9. Fuera de alcance (v1)

- Toggles colapsables, tablas complejas, imágenes/adjuntos embebidos.
- Enlaces entre notas (backlinks), etiquetas.
- Vincular notas a proyectos o tareas (las notas van aparte).
- Plantillas, papelera con undo, exportación masiva.

---

## 10. Tests

`tests/Feature/`: `NoteControllerTest` y `NoteFolderControllerTest` (CRUD,
validación, búsqueda, borrado de carpeta que reubica notas). El editor JS
no lleva test automático, como el resto del JS del proyecto.

---

## 11. Ramas y orden de los dos módulos

Ambos módulos son lado dashboard y secuenciales (un solo desarrollador):
conviene terminar y mergear uno antes de empezar el otro, para no acumular
conflictos en `web.php`, el layout y el nav.

1. Mergear primero `paulo-development-002` a `main` (PR pendiente).
2. Una rama por módulo, partiendo de `main` ya actualizado.
3. **Orden sugerido: Notas → Kanban.** Notas es CRUD + carpetas (arranque
   más suave); Kanban añade el drag & drop con endpoint AJAX. Si el Kanban
   es lo que más vas a usar, al revés — son independientes.

---

## 12. Definition of Done

1. Migraciones + modelos.
2. Vista de 3 paneles + editor WYSIWYG-Markdown + autosave + búsqueda.
3. Carpetas anidadas con borrado seguro.
4. Tests Feature en verde.
5. Sección en `/help` y en `USER-GUIDE.md`; entrada en el nav.
