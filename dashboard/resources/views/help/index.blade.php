@extends('layouts.app')

@section('title', 'Guía de uso')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">Guía de uso</h1>
        <p class="text-sm text-muted mt-2 max-w-2xl">
            trackActivity reconstruye automáticamente lo que has trabajado durante el día
            agrupando señales pasivas (ventana activa, repos Git, idle). Esta guía cubre
            instalación, uso diario, configuración y resolución de problemas.
        </p>
    </div>

    {{-- Indice --}}
    <nav class="card p-4 mb-8 text-sm">
        <p class="text-xs uppercase tracking-wider text-muted mb-2">Contenido</p>
        <ol class="grid grid-cols-1 sm:grid-cols-2 gap-1">
            <li><a class="underline hover:opacity-80" href="#que-es">1. ¿Qué es y qué no es?</a></li>
            <li><a class="underline hover:opacity-80" href="#arquitectura">2. Arquitectura en 30 segundos</a></li>
            <li><a class="underline hover:opacity-80" href="#instalacion">3. Instalación (Ubuntu)</a></li>
            <li><a class="underline hover:opacity-80" href="#arranque">4. Arranque diario</a></li>
            <li><a class="underline hover:opacity-80" href="#vistas">5. Las vistas del dashboard</a></li>
            <li><a class="underline hover:opacity-80" href="#editar">6. Editar sesiones a mano</a></li>
            <li><a class="underline hover:opacity-80" href="#proyectos">7. Proyectos y mappings</a></li>
            <li><a class="underline hover:opacity-80" href="#export">8. Exportar al timesheet</a></li>
            <li><a class="underline hover:opacity-80" href="#scheduler">9. Auto-actualización</a></li>
            <li><a class="underline hover:opacity-80" href="#troubleshooting">10. Resolución de problemas</a></li>
            <li><a class="underline hover:opacity-80" href="#privacidad">11. Privacidad</a></li>
            <li><a class="underline hover:opacity-80" href="#notas">12. Notas</a></li>
            <li><a class="underline hover:opacity-80" href="#tareas">13. Tareas</a></li>
            <li><a class="underline hover:opacity-80" href="#inicio">14. Inicio y atajos</a></li>
            <li><a class="underline hover:opacity-80" href="#datos">15. Copias de seguridad y datos</a></li>
        </ol>
    </nav>

    {{-- 1 --}}
    <section id="que-es" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">1. ¿Qué es y qué no es?</h2>
        <p class="text-sm mb-3"><strong>Es</strong> un sistema de reconstrucción de contexto de trabajo: captura
        pistas pasivas (ventana activa, repos Git locales, idle) y deduce qué proyecto te ha
        ocupado cada bloque de 15 min. Pensado para rellenar timesheets a posteriori.</p>
        <p class="text-sm"><strong>No es</strong> un time tracker exacto, ni vigilancia (no hace screenshots ni
        keystrokes), ni gestor de tareas, ni SaaS. Todo se queda en tu disco.</p>
    </section>

    {{-- 2 --}}
    <section id="arquitectura" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">2. Arquitectura en 30 segundos</h2>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><strong>Daemon Python</strong> (<code class="chip">tracker</code>) corre en segundo plano y escribe
                señales en SQLite.</li>
            <li><strong>SQLite</strong> en <code class="chip">{{ $dbPath }}</code> hace de cola y de
                fuente de verdad.</li>
            <li><strong>Dashboard Laravel</strong> (esta web) lee la BBDD, agrupa eventos en bloques de
                <code class="chip">{{ $blockMin }} min</code>, asigna proyecto dominante y muestra el timeline.</li>
        </ul>
    </section>

    {{-- 3 --}}
    <section id="instalacion" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">3. Instalación (Ubuntu/Debian)</h2>
        <h3 class="text-sm font-semibold mt-3 mb-1">3.1 Dependencias del SO</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>sudo apt install -y xdotool x11-utils python3.11 python3.11-venv \
    php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-intl composer sqlite3 git</code></pre>

        <h3 class="text-sm font-semibold mt-4 mb-1">3.2 Dashboard Laravel</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>cd dashboard
composer install
cp .env.example .env
php artisan key:generate
mkdir -p ../storage
php artisan migrate --seed</code></pre>

        <h3 class="text-sm font-semibold mt-4 mb-1">3.3 Daemon Python</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code># bash/zsh
cd tracker
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml

# fish
source .venv/bin/activate.fish</code></pre>

        <h3 class="text-sm font-semibold mt-4 mb-1">3.4 Frontend (Tailwind/Vite)</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>cd dashboard
npm install
npm run build</code></pre>
    </section>

    {{-- 4 --}}
    <section id="arranque" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">4. Arranque diario</h2>
        <ol class="text-sm space-y-2 list-decimal pl-5">
            <li>
                <strong>Daemon</strong>. Lo más cómodo: el botón
                <span class="chip">● Tracker</span> del menú lateral lo arranca y lo para
                con un clic (logs en <code class="chip">storage/logs/tracker.log</code>).
                Como servicio:
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>systemctl --user enable --now trackactivity.service</code></pre>
                O en primer plano para debugging:
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>tracker run --foreground --log-level=DEBUG</code></pre>
            </li>
            <li>
                <strong>Dashboard</strong>:
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>cd dashboard
php artisan serve   # http://127.0.0.1:8000</code></pre>
            </li>
            <li>
                <strong>Comprobar todo</strong>:
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>tracker doctor         # daemon side
php artisan tracker:doctor    # dashboard side</code></pre>
            </li>
        </ol>
    </section>

    {{-- 5 --}}
    <section id="vistas" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">5. Las vistas del dashboard</h2>
        <dl class="text-sm space-y-3">
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('timeline.today') }}">Hoy</a> / Día</dt>
                <dd class="text-muted">Sesiones del día con su proyecto dominante, badge de confianza (Alta/Media/Baja) y resumen generado. Click en "expandir" para ver la evidencia bruta (cada signal del daemon que contribuyó); "editar sesión" permite corregir el proyecto o el resumen a mano (ver <a class="underline" href="#editar">§6</a>).</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('timeline.this_week') }}">Semana</a></dt>
                <dd class="text-muted">Las 7 columnas del lunes a domingo con totales por proyecto. Click en una celda → vista de día.</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('calendar.current') }}">Calendario</a></dt>
                <dd class="text-muted">Grid mensual con top-3 proyectos por día y totales. Sirve para ver patrones de uso del mes.</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('export.form') }}">Export</a></dt>
                <dd class="text-muted">Formulario para descargar el timeline a TXT/Markdown/CSV con filtros (rango, proyectos, confianza mínima, agrupación).</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('projects.index') }}">Proyectos</a></dt>
                <dd class="text-muted">CRUD de proyectos y de los mappings asociados a cada uno.</dd>
            </div>
        </dl>
        <p class="text-xs text-muted mt-3">Zonas: BBDD en UTC, vistas en <code class="chip">{{ $tz }}</code>. El toggle ☾/☀ del header cambia el tema y persiste en localStorage.</p>
    </section>

    {{-- 6 --}}
    <section id="editar" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">6. Editar sesiones a mano</h2>
        <p class="text-sm mb-3">
            El scoring acierta la mayoría de las veces, pero no siempre. Cuando una sesión
            tiene mal el proyecto o un resumen pobre, corrígela desde la vista de
            <strong>Día</strong>: despliega <code class="chip">editar sesión</code> debajo de la sesión.
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><strong>Proyecto</strong> — reasigna la sesión a otro proyecto (o "sin proyecto").</li>
            <li><strong>Resumen</strong> — texto opcional que sobrescribe el resumen generado.
                Déjalo vacío para conservar el actual.</li>
        </ul>
        <p class="text-sm mt-3">
            Al guardar, <strong>todos los bloques</strong> de la sesión pasan a estado
            <code class="chip">editado</code> con confianza 1.0 y la sesión muestra un badge azul
            <code class="chip">editado</code>. Un bloque editado queda <strong>congelado</strong>:
            los rebuilds automáticos (también los del scheduler) ya no lo recalculan, así no
            pierdes la corrección.
        </p>
        <p class="text-sm mt-3">
            <strong>Volver a automático</strong>: en una sesión ya editada aparece ese botón,
            que la devuelve a estado <code class="chip">auto</code> y libera el resumen. El
            siguiente rebuild la recalcula desde cero.
        </p>
        <p class="text-xs text-muted mt-3">
            Bloques contiguos del mismo proyecto se muestran como una sola sesión aunque unos
            sean <code class="chip">auto</code> y otros <code class="chip">editado</code>. Para
            recalcular a la fuerza incluso los bloques editados:
            <code class="chip">php artisan tracker:rebuild-blocks --day=… --force-edited</code>.
        </p>

        <h3 class="text-sm font-semibold mt-5 mb-1">Entradas manuales (reuniones, correcciones)</h3>
        <p class="text-sm mb-3">
            El tracking automático no capta todo: reuniones, llamadas o ratos sin el editor
            delante. Para esos huecos añade una <strong>entrada manual</strong> — un tramo con
            hora de inicio/fin, proyecto, tipo y título — desde el botón
            <code class="chip">+ Añadir entrada manual</code> al final de la vista de
            <strong>Día</strong> o del <strong>Calendario</strong>.
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>Capa independiente del tracking: el daemon y los rebuilds nunca las tocan.</li>
            <li>Tipo <code class="chip">Reunión</code>, <code class="chip">Trabajo</code> u
                <code class="chip">Otro</code>, cada uno con su color en el timeline.</li>
            <li>Editables y borrables cuando quieras (<em>editar entrada</em> bajo cada una).</li>
            <li>Suman en los totales por proyecto del día y del calendario.</li>
            <li>Si el horario pisa otra entrada manual o tiempo ya registrado, se te pregunta
                si <strong>reemplazarlo</strong> antes de guardar.</li>
        </ul>
    </section>

    {{-- 7 --}}
    <section id="proyectos" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">7. Proyectos y mappings</h2>
        <p class="text-sm mb-3">
            Un <strong>proyecto</strong> es una entidad lógica (ej. "JASPER"). Un <strong>mapping</strong>
            es una regla que conecta una pista del SO con ese proyecto. Tipos:
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><code class="chip">repository</code> — coincide con el nombre del repo Git
                (ej. patrón <code class="chip">ywl-</code> matchea <code class="chip">ywl-admin</code>, <code class="chip">ywl-api</code>…).</li>
            <li><code class="chip">folder</code> — coincide con la ruta de la cwd de un terminal
                (ej. <code class="chip">/var/www/html/jasper-api</code>).</li>
            <li><code class="chip">url_pattern</code> — coincide en la URL/title de Chrome
                (ej. <code class="chip">github.com/company/jasper</code>).</li>
            <li><code class="chip">email_subject</code> — coincide en el asunto de Thunderbird.</li>
            <li><code class="chip">window_title</code> — coincide en el title de cualquier ventana.</li>
        </ul>
        <p class="text-sm mt-3">
            Por defecto el matching es <strong>substring case-insensitive</strong>. Marca "regex" si
            necesitas precisión (anchors <code class="chip">^</code>/<code class="chip">$</code>, alternancia, etc.).
        </p>
        <p class="text-sm mt-3">
            El <strong>scoring</strong> aplica un peso distinto según la señal: VSCode en repo (+5),
            terminal en repo (+4), git con cambios (+5), URL match (+3), email (+2), title genérico (+2).
            Puedes añadir un bonus por mapping si una pista es especialmente fuerte.
        </p>
        <p class="text-sm mt-3">
            <strong>Tras cambiar mappings</strong>, recomputa los bloques afectados:
        </p>
        <pre class="surface-soft text-xs rounded p-3 mt-2"><code>php artisan tracker:rebuild-blocks --day=$(date +%F)</code></pre>
    </section>

    {{-- 8 --}}
    <section id="export" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">8. Exportar al timesheet</h2>
        <p class="text-sm mb-3">
            Desde <a class="underline" href="{{ route('export.form') }}">Export</a> o por CLI:
        </p>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>php artisan tracker:export --from=2026-05-13 --to=2026-05-19 \
    --project=JASPER --format=md --min-confidence=medium \
    --output=~/Documents/timesheets/week.md</code></pre>
        <ul class="text-sm space-y-1 list-disc pl-5 mt-3">
            <li><strong>TXT</strong> para pegar directamente en formularios.</li>
            <li><strong>Markdown</strong> con secciones por día y detalles colapsables de evidencia.</li>
            <li><strong>CSV</strong> con BOM UTF-8 (abre bien en Excel) y columnas estándar.</li>
        </ul>
        <p class="text-sm mt-3">
            El export incluye tanto las sesiones reconstruidas como las
            <strong>entradas manuales</strong> (reuniones), marcadas como
            <code class="chip">manual · …</code>, y sus minutos cuentan en los totales.
        </p>
        <p class="text-sm mt-3">
            Agrupación <code class="chip">project-day</code>: un único resumen por (proyecto, día);
            útil cuando el timesheet solo acepta totales diarios.
        </p>
    </section>

    {{-- 9 --}}
    <section id="scheduler" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">9. Auto-actualización</h2>
        <p class="text-sm mb-3">
            Para que la UI refleje tu actividad sin ejecutar comandos a mano, deja corriendo:
        </p>
        <pre class="surface-soft text-xs rounded p-3"><code>cd dashboard
php artisan schedule:work</code></pre>
        <p class="text-sm mt-3">
            Cada 15 min reconstruye los bloques y los resúmenes de las últimas 2 horas.
            A las 03:00 limpia eventos con &gt; 90 días (configurable).
        </p>
        <p class="text-sm mt-3">Alternativa robusta (cron del SO):</p>
        <pre class="surface-soft text-xs rounded p-3"><code>* * * * * cd /var/www/html/trackActivity/dashboard && \
    php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>
    </section>

    {{-- 10 --}}
    <section id="troubleshooting" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">10. Resolución de problemas</h2>
        <dl class="text-sm space-y-3">
            <div>
                <dt class="font-semibold">"No veo actividad reciente en el dashboard"</dt>
                <dd class="text-muted">
                    Probable: daemon parado. <code class="chip">systemctl --user status trackactivity.service</code>.
                    Si está corriendo: <code class="chip">php artisan tracker:doctor</code> avisa si el último event
                    tiene &gt; 2h. Después: <code class="chip">php artisan tracker:rebuild-blocks --day=$(date +%F)</code>.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">"Todas las sesiones son 'sin proyecto'"</dt>
                <dd class="text-muted">
                    Faltan mappings que matcheen tus repos. Ve a
                    <a class="underline" href="{{ route('projects.index') }}">Proyectos</a> y añade
                    <code class="chip">repository</code> con el nombre (o substring) de tus repos.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">"El calendario o la semana se ven rotos"</dt>
                <dd class="text-muted">
                    Recompila los assets: <code class="chip">npm run build</code> en <code class="chip">dashboard/</code>.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">"Las horas están desplazadas 2h"</dt>
                <dd class="text-muted">
                    Convención: SQLite en UTC, vista en <code class="chip">tracker.display_timezone</code>. Verifica que
                    <code class="chip">APP_TIMEZONE=UTC</code> en <code class="chip">.env</code> y que
                    <code class="chip">TRACKER_DISPLAY_TIMEZONE</code> es tu zona local.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">"Wayland: el daemon no captura ventanas"</dt>
                <dd class="text-muted">
                    El collector de ventana usa X11 (xdotool/xprop). Si tu sesión es Wayland, cambia a
                    "Ubuntu on Xorg" en el selector de gdm3.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">"<code class="chip">database is locked</code> en logs"</dt>
                <dd class="text-muted">
                    Verifica WAL: <code class="chip">sqlite3 storage/activity.db "PRAGMA journal_mode;"</code> debe devolver <code class="chip">wal</code>.
                </dd>
            </div>
        </dl>
    </section>

    {{-- 11 --}}
    <section id="privacidad" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">11. Privacidad</h2>
        <p class="text-sm">
            <strong>Todo es local.</strong> Sin login, sin telemetría, sin cuenta. La BBDD
            es un solo fichero en tu disco. No se hacen screenshots ni se capturan keystrokes.
            Solo se almacena: título de ventana, clase de aplicación, ruta de cwd de terminales,
            metadatos de repos Git (branch, archivos modificados, hash de último commit) y eventos
            de idle (entrar/salir).
        </p>
        <p class="text-sm mt-2">
            Para retener menos histórico, ajusta <code class="chip">--older-than</code> en el
            scheduler de <code class="chip">tracker:prune-events</code>.
        </p>
    </section>

    {{-- 12 --}}
    <section id="notas" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">12. Notas</h2>
        <p class="text-sm mb-3">
            Un bloc de notas personal, independiente del tracking. Se abre desde
            <a class="underline" href="{{ route('notes.index') }}">Notas</a> en el menú lateral.
            Vista de tres paneles: <strong>carpetas</strong> · <strong>lista de notas</strong> ·
            <strong>editor</strong>.
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><strong>Carpetas</strong> anidables para organizar las notas; una nota puede
                estar en una carpeta o suelta en la raíz. Al borrar una carpeta, sus notas y
                subcarpetas pasan a la raíz — no se pierde nada.</li>
            <li><strong>Editor</strong> WYSIWYG sobre Markdown: formato en vivo, menú
                <code class="chip">/</code> para insertar bloques y arrastre de bloques. El
                contenido se guarda como Markdown, con <strong>autoguardado</strong>.</li>
            <li><strong>Buscar</strong> por título o contenido desde el cuadro de búsqueda de la
                lista; la búsqueda recorre todas las carpetas.</li>
            <li><strong>Fijar</strong> una nota (★) la mantiene arriba de su lista.</li>
        </ul>
    </section>

    {{-- 13 --}}
    <section id="tareas" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">13. Tareas</h2>
        <p class="text-sm mb-3">
            Un tablero Kanban de tareas personales. Se abre desde
            <a class="underline" href="{{ route('tasks.index') }}">Tareas</a> en el menú lateral.
            Cuatro columnas: <strong>Backlog</strong>, <strong>Por hacer</strong>,
            <strong>En curso</strong> y <strong>Hecho</strong>.
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>Crea y edita tareas en un modal: título, descripción, columna, prioridad,
                fecha de vencimiento y un <strong>proyecto</strong> opcional.</li>
            <li><strong>Arrastra</strong> las tarjetas entre columnas y dentro de cada una;
                el orden se guarda solo.</li>
            <li>Cada tarjeta muestra su proyecto, prioridad y fecha (resaltada si está vencida).</li>
            <li>Filtra el tablero por proyecto y por prioridad.</li>
            <li>Las tareas <strong>En curso</strong> aparecen también en el Inicio.</li>
        </ul>
    </section>

    {{-- 14 --}}
    <section id="inicio" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">14. Inicio y atajos</h2>
        <p class="text-sm mb-3">
            La página de <a class="underline" href="{{ route('dashboard') }}">Inicio</a> resume tu
            actividad de un vistazo.
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>La <strong>semana actual</strong> con las horas trackeadas de cada día.</li>
            <li>Un <strong>heatmap</strong> de actividad del último año (clic en un día → su timeline).</li>
            <li><strong>Ahora mismo</strong>: lo último que registró el tracker.</li>
            <li>Las últimas notas editadas y las tareas en curso.</li>
            <li>Un aviso si el tracker lleva tiempo sin registrar actividad.</li>
        </ul>
        <p class="text-sm mt-3">
            <strong>Paleta de comandos</strong>: pulsa <x-kbd>Ctrl K</x-kbd> / <x-kbd>⌘ K</x-kbd> (o
            «Buscar» en el menú lateral) para saltar a cualquier sección o nota al instante.
        </p>
    </section>

    {{-- 15 --}}
    <section id="datos" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">15. Copias de seguridad y datos</h2>
        <p class="text-sm mb-3">
            En <a class="underline" href="{{ route('data.index') }}">Datos</a> (menú Configuración)
            gestionas las copias y la exportación.
        </p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><strong>Copias de seguridad</strong>: se crea una automática a diario y se conservan
                las 14 últimas. Puedes crear, descargar o restaurar copias a mano.</li>
            <li><strong>Restaurar</strong> sobrescribe la base de datos actual — guarda una copia
                previa por seguridad. Conviene hacerlo con el tracker detenido.</li>
            <li><strong>Exportar</strong>: las notas a Markdown (un `.zip` por carpetas) y todos los
                datos a JSON.</li>
        </ul>
    </section>
@endsection
