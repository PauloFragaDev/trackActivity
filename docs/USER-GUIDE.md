# Guía de usuario · trackActivity

Reconstruye automáticamente lo que has trabajado durante el día agrupando
señales pasivas (ventana activa, repos Git locales, idle) para facilitar
el reporte en timesheets. Esta guía cubre instalación, uso diario,
configuración y resolución de problemas, en una sola página.

> Versión web equivalente disponible en `/help` cuando el dashboard está
> corriendo.

---

## Índice

1. [¿Qué es y qué no es?](#1-qué-es-y-qué-no-es)
2. [Arquitectura en 30 segundos](#2-arquitectura-en-30-segundos)
3. [Instalación (Ubuntu/Debian)](#3-instalación-ubuntudebian)
4. [Arranque diario](#4-arranque-diario)
5. [Las vistas del dashboard](#5-las-vistas-del-dashboard)
6. [Editar sesiones a mano](#6-editar-sesiones-a-mano)
7. [Proyectos y mappings](#7-proyectos-y-mappings)
8. [Exportar al timesheet](#8-exportar-al-timesheet)
9. [Auto-actualización (scheduler)](#9-auto-actualización-scheduler)
10. [Resolución de problemas](#10-resolución-de-problemas)
11. [Privacidad y retención](#11-privacidad-y-retención)

---

## 1. ¿Qué es y qué no es?

**Es** un sistema de *reconstrucción de contexto de trabajo*. Captura
pistas pasivas que tu sistema ya genera (ventana enfocada, ramas y
commits de tus repos, periodos de inactividad), las agrupa en bloques de
15 minutos y deduce el proyecto dominante de cada bloque mediante un
sistema de scoring ponderado.

**No es:**
- Un time tracker exacto al segundo.
- Vigilancia: no hace screenshots, no captura keystrokes, no manda nada a
  ningún servidor.
- Un gestor de tareas (no reemplaza Jira/Linear).
- Una SaaS: vive entero en tu máquina.

---

## 2. Arquitectura en 30 segundos

```
┌──────────────────┐    SQLite    ┌──────────────────┐
│  Daemon Python   │ ───────────▶ │ Dashboard Laravel│
│  (tracker)       │ ◀─────────── │ (Blade+Tailwind) │
│                  │   leer/agg   │                  │
│  - WindowColl.   │              │ - Aggregator     │
│  - GitColl.      │              │ - Scorer         │
│  - IdleColl.     │              │ - SummaryGen.    │
└──────────────────┘              │ - Exporter       │
                                  └──────────────────┘
```

- El **daemon** captura señales cada 15–240 s según el collector y las
  escribe en SQLite (`storage/activity.db`).
- El **dashboard** lee esa BBDD, agrupa los eventos en bloques de
  15 min, aplica scoring, genera resúmenes y los muestra/exporta.
- Ambos procesos son independientes: cada uno puede arrancar/parar/
  reiniciar sin afectar al otro.

Detalle técnico en [`docs/02-architecture.md`](02-architecture.md) y
[`docs/05-database-schema.md`](05-database-schema.md).

---

## 3. Instalación (Ubuntu/Debian)

### 3.1 Dependencias del SO

```bash
sudo apt install -y \
    xdotool x11-utils \
    python3.11 python3.11-venv \
    php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-intl composer sqlite3 git
```

> Si tu PHP es 8.2/8.3, ajusta los paquetes `php8.X-*`.

### 3.2 Dashboard Laravel

```bash
cd dashboard
composer install
cp .env.example .env
php artisan key:generate
mkdir -p ../storage
php artisan migrate --seed
```

El `.env` ya viene preconfigurado con:
- `APP_TIMEZONE=UTC` (no lo cambies; ver [Resolución de problemas](#10-resolución-de-problemas))
- `TRACKER_DISPLAY_TIMEZONE=Europe/Madrid` (ajústalo a tu zona si es otra)
- `DB_DATABASE=/var/www/html/trackActivity/storage/activity.db` (ajustala a la tuya)

### 3.3 Daemon Python

**bash / zsh:**
```bash
cd tracker
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml
```

**fish:**
```fish
cd tracker
python3.11 -m venv .venv
source .venv/bin/activate.fish
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml
```

> `pip install -e .` es **necesario** para que el ejecutable `tracker`
> esté disponible y no necesites `PYTHONPATH=src python -m tracker.cli`.

Edita `tracker/config.yml` para ajustar `repositories_paths` a las raíces
donde tienes tus repos (por defecto `/var/www/html`).

### 3.4 Frontend (Tailwind + Vite)

```bash
cd dashboard
npm install
npm run build
```

> Tras tocar **cualquier template Blade que use clases nuevas de
> Tailwind**, recompila con `npm run build`. Si no, las clases no estarán
> en el CSS purgado y verás layouts rotos (el síntoma típico es un grid
> de 7 columnas que se colapsa a 1).

### 3.5 Servicio systemd (opcional, recomendado)

```bash
mkdir -p ~/.config/systemd/user
cp tracker/scripts/trackactivity.service ~/.config/systemd/user/
# Edita el path si no estás en /var/www/html/trackActivity

systemctl --user daemon-reload
systemctl --user enable --now trackactivity.service

# Para que arranque sin sesión gráfica abierta:
sudo loginctl enable-linger $USER
```

---

## 4. Arranque diario

```bash
# (1) Daemon — si lo tienes como servicio, ya está corriendo
systemctl --user status trackactivity.service

#     o foreground para debug
cd tracker && tracker run --foreground --log-level=DEBUG

# (2) Dashboard
cd dashboard && php artisan serve   # http://127.0.0.1:8000

# (3) Scheduler (para que la UI se actualice sola)
cd dashboard && php artisan schedule:work
```

Diagnóstico rápido:

```bash
tracker doctor                # daemon side: xdotool, pygit2, schema, repos
php artisan tracker:doctor    # dashboard side: BBDD, schema, datos, datos recientes
```

---

## 5. Las vistas del dashboard

| Ruta | Qué muestra |
|------|-------------|
| `/` o `/day/{YYYY-MM-DD}` | **Día**: lista de sesiones con proyecto, confianza, summary, evidencia desplegable y edición inline ([§6](#6-editar-sesiones-a-mano)). |
| `/week` o `/week/{YYYY-Www}` | **Semana**: 7 columnas L–D con totales por proyecto. |
| `/calendar` o `/calendar/{YYYY-MM}` | **Mes**: grid 6×7 con top-3 proyectos por día. |
| `/projects` | **CRUD de proyectos** + edición inline de mappings. |
| `/export` | Formulario de descarga (TXT/MD/CSV). |
| `/help` | Esta guía. |

Toggle ☾/☀ del header cambia entre tema claro y oscuro; la preferencia
queda en `localStorage`.

---

## 6. Editar sesiones a mano

El scoring acierta la mayoría de las veces, pero no siempre. Cuando una
sesión tiene el proyecto equivocado o un resumen pobre, corrígela desde
la vista de **Día**: despliega `editar sesión` debajo de la sesión.

| Campo | Efecto |
|-------|--------|
| Proyecto | Reasigna la sesión a otro proyecto (o "sin proyecto"). |
| Resumen | Texto opcional que **sobrescribe** el resumen generado. Vacío = conserva el actual. |

Al guardar, **todos los bloques** de la sesión pasan a estado `editado`
con confianza `1.0`, y la sesión muestra un badge azul `editado` en
lugar del de confianza. Un bloque `editado` queda **congelado**: los
rebuilds automáticos (también los del scheduler) ya no lo recalculan, de
modo que tu corrección no se pierde.

**Volver a automático**: en una sesión ya editada aparece el botón
*Volver a automático*, que la devuelve a estado `auto` y libera el
resumen (`edited_by_user = false`). El siguiente `rebuild-blocks` la
recalcula desde cero.

> Bloques contiguos del mismo proyecto se muestran como **una sola
> sesión** aunque unos sean `auto` y otros `editado`. La edición se
> aplica a todos los bloques de la sesión a la vez.

Para forzar el recálculo incluso de bloques editados:

```bash
php artisan tracker:rebuild-blocks --day=$(date +%F) --force-edited
```

### Entradas manuales (reuniones, correcciones)

El tracking automático no capta todo: reuniones, llamadas o ratos sin el
editor delante. Para esos huecos añade una **entrada manual** — un tramo
con hora de inicio/fin, proyecto, tipo y título — desde el botón
`+ Añadir entrada manual` al final de la vista de **Día** o del
**Calendario**.

- Son una capa independiente del tracking automático: el daemon y los
  rebuilds nunca las tocan; su inicio/fin son libres (fuera del grid de
  15 min).
- Tipo `Reunión` / `Trabajo` / `Otro`, cada uno con su color.
- Editables y borrables en cualquier momento.
- Suman en los totales por proyecto del día y del calendario.
- Si el horario se solapa con otra entrada manual o con tiempo ya
  registrado por el tracker, se te pregunta si reemplazarlo antes de
  guardar (el tiempo solapado se borra). Un tramo cubierto por una
  entrada manual deja de auto-generarse en los rebuilds.

---

## 7. Proyectos y mappings

### Conceptos

- **Proyecto**: entidad lógica con `code` único (MAYÚSCULAS), `name`,
  `color` y `description`. Se crean/editan desde `/projects`.
- **Mapping**: regla que asocia una pista del SO con un proyecto.

### Tipos de mapping

| Tipo | Coincide con… | Ejemplo |
|------|---------------|---------|
| `repository` | nombre del repo Git | `ywl-` → matchea `ywl-admin`, `ywl-api`… |
| `folder` | ruta de cwd (terminal o repo Git) | `/var/www/html/jasper-api` |
| `url_pattern` | URL/title del navegador | `github.com/company/jasper` |
| `email_subject` | asunto de Thunderbird | `JASPER` |
| `window_title` | title de cualquier ventana | `trackActivity` |

Matching por defecto: **substring case-insensitive**. Marca `is_regex`
para usar expresión regular (case-insensitive también, anclas `^`/`$`
disponibles).

### Pesos por defecto (scoring rules)

| Señal | Peso |
|-------|------|
| VSCode/Cursor en repo del proyecto | +5 |
| Terminal con cwd en repo | +4 |
| Git con archivos modificados | +5 |
| Git con commit reciente (sin mods) | +4 |
| URL match | +3 |
| Email match | +2 |
| Window title match | +2 |

Cada mapping puede añadir un `weight_bonus` adicional (entero, -10 a +10)
si quieres reforzar/debilitar reglas concretas.

### Tras tocar mappings

Los bloques **ya generados** no se recalculan solos. Para verlo aplicado:

```bash
php artisan tracker:rebuild-blocks --day=$(date +%F)
# o un rango:
php artisan tracker:rebuild-blocks --since="7 days ago"
```

Si quieres forzar recompute incluso de bloques editados a mano:

```bash
php artisan tracker:rebuild-blocks --day=$(date +%F) --force-edited
```

---

## 8. Exportar al timesheet

### Desde la UI

Ve a `/export`, escoge rango, proyectos (vacío = todos), confianza
mínima, agrupación (`session` / `project-day`) y formato (TXT/MD/CSV).

### Desde CLI

```bash
php artisan tracker:export \
    --from=2026-05-13 --to=2026-05-19 \
    --project=JASPER --project=YWL \
    --min-confidence=medium \
    --format=md \
    --output=~/Documents/timesheets/week-20.md
```

Sin `--output` escribe a stdout (útil para pipes).

### Formatos

- **TXT**: pegar directamente en formularios web.
- **Markdown**: encabezados por día, evidencia colapsable en `<details>`,
  tabla final de totales. Bueno para wikis y PR descriptions.
- **CSV**: BOM UTF-8 (compatible con Excel en Windows), columnas
  estándar `date,start,end,duration_minutes,project_code,project_name,confidence,summary,evidence`.

El export incluye tanto las sesiones reconstruidas como las **entradas
manuales** (reuniones, correcciones): aparecen en orden, marcadas como
`manual · <tipo>` en la columna de confianza, y sus minutos suman en los
totales.

---

## 9. Auto-actualización (scheduler)

Para no estar lanzando `rebuild-blocks` y `generate-summaries` a mano:

```bash
cd dashboard
php artisan schedule:work
```

Esto ejecuta cada minuto el scheduler de Laravel. Las tareas registradas:

| Cuándo | Comando |
|--------|---------|
| Cada 15 min | `tracker:rebuild-blocks --since="2 hours ago"` |
| Cada 15 min | `tracker:generate-summaries --since="2 hours ago"` |
| 03:00 diario | `tracker:prune-events --older-than="90 days"` |

Para producción usa cron en lugar de `schedule:work`:

```cron
* * * * * cd /var/www/html/trackActivity/dashboard && php artisan schedule:run >> /dev/null 2>&1
```

---

## 10. Resolución de problemas

### "No veo actividad reciente"

```bash
systemctl --user status trackactivity.service
journalctl --user -u trackactivity.service -f
php artisan tracker:doctor    # avisa si último event > 2h
php artisan tracker:rebuild-blocks --day=$(date +%F)
```

### "Todas las sesiones son 'sin proyecto'"

Faltan mappings. Ve a `/projects/{id}/edit` y añade `repository` con el
nombre (o substring) de tus repos reales. Tras añadirlos,
`rebuild-blocks` para re-puntuar.

### "El calendario o la semana se ven rotos (todo en una columna)"

Los assets de Tailwind no están al día. Recompila:

```bash
cd dashboard && npm run build
```

### "Las horas están desplazadas 1–2 h"

La convención del proyecto es **UTC en BBDD, conversión en la vista**.
Verifica:

```bash
grep -E "^APP_TIMEZONE|^TRACKER_DISPLAY_TIMEZONE" dashboard/.env
```

Debe ser `APP_TIMEZONE=UTC` y `TRACKER_DISPLAY_TIMEZONE` con tu zona
local (ej. `Europe/Madrid`). Si lo cambiaste a otra cosa, vuelve a UTC y
reinicia.

### "Wayland: el daemon no capta ventanas"

`xdotool` requiere X11. Selecciona "Ubuntu on Xorg" en gdm3 al iniciar
sesión. Soporte Wayland fuera de v1.

### "`xdotool getwindowclassname` no existe"

Versiones de xdotool en Debian/Ubuntu estable no tienen ese subcomando.
El daemon ya usa `xprop WM_CLASS` como alternativa portable.
Asegúrate de tener `x11-utils` instalado.

### "`database is locked`"

```bash
sqlite3 storage/activity.db "PRAGMA journal_mode;"
```

Debe devolver `wal`. Si devuelve `delete`, fuerza WAL:

```bash
sqlite3 storage/activity.db "PRAGMA journal_mode=WAL;"
```

### "`pip install -e .` falla"

Asegúrate de tener `python3.11-venv` y de estar **dentro** del venv
activo (`which python` debe apuntar a `tracker/.venv/bin/python`).

### "Cambié el .env y no se aplica"

Laravel cachea config. Tras cualquier cambio en `.env`:

```bash
php artisan config:clear
```

---

## 11. Privacidad y retención

**Todo es local.** No hay login, telemetría ni nube. La BBDD es un solo
archivo SQLite en tu disco.

**Qué se almacena:**
- Título de la ventana activa y clase de aplicación.
- Para terminales: ruta de cwd del shell (vía `/proc/<pid>/cwd`).
- Para repos Git: nombre, ruta, branch, número de archivos modificados,
  hash y mensaje del último commit.
- Eventos de entrada/salida de idle.
- (Opcional) Pista de URL en title de Chrome cuando es GitHub/Jira.

**Qué NO se almacena:**
- Capturas de pantalla.
- Pulsaciones de teclado.
- Contenido de archivos.
- Cookies, contraseñas, formularios.
- Nada del navegador más allá del title de su ventana.

**Retención:** por defecto el job programado borra `activity_events` más
antiguos de 90 días (`tracker:prune-events --older-than="90 days"`).
Los `time_blocks` y `generated_summaries` se conservan indefinidamente
(son ligeros).

---

## Referencias

- Diseño técnico: [`docs/02-architecture.md`](02-architecture.md), [`docs/05-database-schema.md`](05-database-schema.md)
- Algoritmo de scoring: [`docs/09-context-scoring.md`](09-context-scoring.md)
- Detalle de las vistas: [`docs/07-laravel-dashboard.md`](07-laravel-dashboard.md)
- Catálogo de señales: [`docs/08-activity-signals.md`](08-activity-signals.md)
- Roadmap del MVP: [`docs/14-mvp-roadmap.md`](14-mvp-roadmap.md)
