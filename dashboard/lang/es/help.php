<?php
return [
    'title'       => 'Ayuda',

    // intro
    'intro'       => 'trackActivity reconstruye automáticamente lo que has trabajado durante el día agrupando señales pasivas (ventana activa, repos Git, idle). Esta guía cubre instalación, uso diario, configuración y resolución de problemas.',

    // TOC
    'toc_heading' => 'Contenido',
    'toc_1'       => '¿Qué es y qué no es?',
    'toc_2'       => 'Arquitectura en 30 segundos',
    'toc_3'       => 'Instalación (Ubuntu)',
    'toc_4'       => 'Arranque diario',
    'toc_5'       => 'Las vistas del dashboard',
    'toc_6'       => 'Editar sesiones a mano',
    'toc_7'       => 'Proyectos y mappings',
    'toc_8'       => 'Exportar al timesheet',
    'toc_9'       => 'Auto-actualización',
    'toc_10'      => 'Resolución de problemas',
    'toc_11'      => 'Privacidad',
    'toc_12'      => 'Notas',
    'toc_13'      => 'Tareas',
    'toc_14'      => 'Inicio y atajos',
    'toc_15'      => 'Copias de seguridad y datos',

    // Section 1
    's1_title'        => '1. ¿Qué es y qué no es?',
    's1_is_label'     => 'Es',
    's1_is'           => 'un sistema de reconstrucción de contexto de trabajo: captura pistas pasivas (ventana activa, repos Git locales, idle) y deduce qué proyecto te ha ocupado cada bloque de 15 min. Pensado para rellenar timesheets a posteriori.',
    's1_isnot_label'  => 'No es',
    's1_isnot'        => 'un time tracker exacto, ni vigilancia (no hace screenshots ni keystrokes), ni gestor de tareas, ni SaaS. Todo se queda en tu disco.',

    // Section 2
    's2_title'        => '2. Arquitectura en 30 segundos',
    's2_daemon'       => 'Daemon Python (<code class="chip">tracker</code>) corre en segundo plano y escribe señales en SQLite.',
    's2_sqlite'       => 'SQLite en <code class="chip">:path</code> hace de cola y de fuente de verdad.',
    's2_dashboard'    => 'Dashboard Laravel (esta web) lee la BBDD, agrupa eventos en bloques de <code class="chip">:min min</code>, asigna proyecto dominante y muestra el timeline.',

    // Section 3
    's3_title'        => '3. Instalación (Ubuntu/Debian)',
    's3_1_title'      => '3.1 Dependencias del SO',
    's3_2_title'      => '3.2 Dashboard Laravel',
    's3_3_title'      => '3.3 Daemon Python',
    's3_4_title'      => '3.4 Frontend (Tailwind/Vite)',

    // Section 4
    's4_title'        => '4. Arranque diario',
    's4_daemon_label' => 'Daemon',
    's4_daemon_hint'  => 'Lo más cómodo: el botón <span class="chip">● Tracker</span> del menú lateral lo arranca y lo para con un clic (logs en <code class="chip">storage/logs/tracker.log</code>). Como servicio:',
    's4_foreground'   => 'O en primer plano para debugging:',
    's4_dash_label'   => 'Dashboard',
    's4_check_label'  => 'Comprobar todo',

    // Section 5
    's5_title'        => '5. Las vistas del dashboard',
    's5_today'        => 'Sesiones del día con su proyecto dominante, badge de confianza (Alta/Media/Baja) y resumen generado. Click en "expandir" para ver la evidencia bruta (cada signal del daemon que contribuyó); "editar sesión" permite corregir el proyecto o el resumen a mano (ver §6).',
    's5_week'         => 'Las 7 columnas del lunes a domingo con totales por proyecto. Click en una celda → vista de día.',
    's5_calendar'     => 'Grid mensual con top-3 proyectos por día y totales. Sirve para ver patrones de uso del mes.',
    's5_export'       => 'Formulario para descargar el timeline a TXT/Markdown/CSV con filtros (rango, proyectos, confianza mínima, agrupación).',
    's5_projects'     => 'CRUD de proyectos y de los mappings asociados a cada uno.',
    's5_tz_note'      => 'Zonas: BBDD en UTC, vistas en <code class="chip">:tz</code>. El toggle ☾/☀ del header cambia el tema y persiste en localStorage.',

    // Section 6
    's6_title'        => '6. Editar sesiones a mano',
    's6_intro'        => 'El scoring acierta la mayoría de las veces, pero no siempre. Cuando una sesión tiene mal el proyecto o un resumen pobre, corrígela desde la vista de <strong>Día</strong>: despliega <code class="chip">editar sesión</code> debajo de la sesión.',
    's6_project_lbl'  => 'Proyecto',
    's6_project'      => 'reasigna la sesión a otro proyecto (o "sin proyecto").',
    's6_summary_lbl'  => 'Resumen',
    's6_summary'      => 'texto opcional que sobrescribe el resumen generado. Déjalo vacío para conservar el actual.',
    's6_saved'        => 'Al guardar, <strong>todos los bloques</strong> de la sesión pasan a estado <code class="chip">editado</code> con confianza 1.0 y la sesión muestra un badge azul <code class="chip">editado</code>. Un bloque editado queda <strong>congelado</strong>: los rebuilds automáticos (también los del scheduler) ya no lo recalculan, así no pierdes la corrección.',
    's6_revert'       => '<strong>Volver a automático</strong>: en una sesión ya editada aparece ese botón, que la devuelve a estado <code class="chip">auto</code> y libera el resumen. El siguiente rebuild la recalcula desde cero.',
    's6_force_note'   => 'Bloques contiguos del mismo proyecto se muestran como una sola sesión aunque unos sean <code class="chip">auto</code> y otros <code class="chip">editado</code>. Para recalcular a la fuerza incluso los bloques editados: <code class="chip">php artisan tracker:rebuild-blocks --day=… --force-edited</code>.',
    's6_manual_title' => 'Entradas manuales (reuniones, correcciones)',
    's6_manual_intro' => 'El tracking automático no capta todo: reuniones, llamadas o ratos sin el editor delante. Para esos huecos añade una <strong>entrada manual</strong> — un tramo con hora de inicio/fin, proyecto, tipo y título — desde el botón <code class="chip">+ Añadir entrada manual</code> al final de la vista de <strong>Día</strong> o del <strong>Calendario</strong>.',
    's6_manual_1'     => 'Capa independiente del tracking: el daemon y los rebuilds nunca las tocan.',
    's6_manual_2'     => 'Tipo <code class="chip">Reunión</code>, <code class="chip">Trabajo</code> u <code class="chip">Otro</code>, cada uno con su color en el timeline.',
    's6_manual_3'     => 'Editables y borrables cuando quieras (<em>editar entrada</em> bajo cada una).',
    's6_manual_4'     => 'Suman en los totales por proyecto del día y del calendario.',
    's6_manual_5'     => 'Si el horario pisa otra entrada manual o tiempo ya registrado, se te pregunta si <strong>reemplazarlo</strong> antes de guardar.',

    // Section 7
    's7_title'        => '7. Proyectos y mappings',
    's7_intro'        => 'Un <strong>proyecto</strong> es una entidad lógica (ej. "JASPER"). Un <strong>mapping</strong> es una regla que conecta una pista del SO con ese proyecto. Tipos:',
    's7_repo'         => 'coincide con el nombre del repo Git (ej. patrón <code class="chip">ywl-</code> matchea <code class="chip">ywl-admin</code>, <code class="chip">ywl-api</code>…).',
    's7_folder'       => 'coincide con la ruta de la cwd de un terminal (ej. <code class="chip">/var/www/html/jasper-api</code>).',
    's7_url'          => 'coincide en la URL/title de Chrome (ej. <code class="chip">github.com/company/jasper</code>).',
    's7_email'        => 'coincide en el asunto de Thunderbird.',
    's7_window'       => 'coincide en el title de cualquier ventana.',
    's7_matching'     => 'Por defecto el matching es <strong>substring case-insensitive</strong>. Marca "regex" si necesitas precisión (anchors <code class="chip">^</code>/<code class="chip">$</code>, alternancia, etc.).',
    's7_scoring'      => 'El <strong>scoring</strong> aplica un peso distinto según la señal: VSCode en repo (+5), terminal en repo (+4), git con cambios (+5), URL match (+3), email (+2), title genérico (+2). Puedes añadir un bonus por mapping si una pista es especialmente fuerte.',
    's7_rebuild'      => '<strong>Tras cambiar mappings</strong>, recomputa los bloques afectados:',

    // Section 8
    's8_title'        => '8. Exportar al timesheet',
    's8_intro'        => 'Desde <a class="underline" href=":url">Export</a> o por CLI:',
    's8_txt'          => '<strong>TXT</strong> para pegar directamente en formularios.',
    's8_md'           => '<strong>Markdown</strong> con secciones por día y detalles colapsables de evidencia.',
    's8_csv'          => '<strong>CSV</strong> con BOM UTF-8 (abre bien en Excel) y columnas estándar.',
    's8_manual'       => 'El export incluye tanto las sesiones reconstruidas como las <strong>entradas manuales</strong> (reuniones), marcadas como <code class="chip">manual · …</code>, y sus minutos cuentan en los totales.',
    's8_groupby'      => 'Agrupación <code class="chip">project-day</code>: un único resumen por (proyecto, día); útil cuando el timesheet solo acepta totales diarios.',

    // Section 9
    's9_title'        => '9. Auto-actualización',
    's9_intro'        => 'Para que la UI refleje tu actividad sin ejecutar comandos a mano, deja corriendo:',
    's9_schedule'     => 'Cada 15 min reconstruye los bloques y los resúmenes de las últimas 2 horas. A las 03:00 limpia eventos con > 90 días (configurable).',
    's9_cron'         => 'Alternativa robusta (cron del SO):',

    // Section 10
    's10_title'       => '10. Resolución de problemas',
    's10_q1'          => '"No veo actividad reciente en el dashboard"',
    's10_a1'          => 'Probable: daemon parado. <code class="chip">systemctl --user status trackactivity.service</code>. Si está corriendo: <code class="chip">php artisan tracker:doctor</code> avisa si el último event tiene > 2h. Después: <code class="chip">php artisan tracker:rebuild-blocks --day=$(date +%F)</code>.',
    's10_q2'          => '"Todas las sesiones son \'sin proyecto\'"',
    's10_a2'          => 'Faltan mappings que matcheen tus repos. Ve a <a class="underline" href=":url">Proyectos</a> y añade <code class="chip">repository</code> con el nombre (o substring) de tus repos.',
    's10_q3'          => '"El calendario o la semana se ven rotos"',
    's10_a3'          => 'Recompila los assets: <code class="chip">npm run build</code> en <code class="chip">dashboard/</code>.',
    's10_q4'          => '"Las horas están desplazadas 2h"',
    's10_a4'          => 'Convención: SQLite en UTC, vista en <code class="chip">tracker.display_timezone</code>. Verifica que <code class="chip">APP_TIMEZONE=UTC</code> en <code class="chip">.env</code> y que <code class="chip">TRACKER_DISPLAY_TIMEZONE</code> es tu zona local.',
    's10_q5'          => '"Wayland: el daemon no captura ventanas"',
    's10_a5'          => 'El collector de ventana usa X11 (xdotool/xprop). Si tu sesión es Wayland, cambia a "Ubuntu on Xorg" en el selector de gdm3.',
    's10_q6'          => '"<code class=\"chip\">database is locked</code> en logs"',
    's10_a6'          => 'Verifica WAL: <code class="chip">sqlite3 storage/activity.db "PRAGMA journal_mode;"</code> debe devolver <code class="chip">wal</code>.',

    // Section 11
    's11_title'       => '11. Privacidad',
    's11_local'       => '<strong>Todo es local.</strong> Sin login, sin telemetría, sin cuenta. La BBDD es un solo fichero en tu disco. No se hacen screenshots ni se capturan keystrokes. Solo se almacena: título de ventana, clase de aplicación, ruta de cwd de terminales, metadatos de repos Git (branch, archivos modificados, hash de último commit) y eventos de idle (entrar/salir).',
    's11_prune'       => 'Para retener menos histórico, ajusta <code class="chip">--older-than</code> en el scheduler de <code class="chip">tracker:prune-events</code>.',

    // Section 12
    's12_title'       => '12. Notas',
    's12_intro'       => 'Un bloc de notas personal, independiente del tracking. Se abre desde <a class="underline" href=":url">Notas</a> en el menú lateral. Vista de tres paneles: <strong>carpetas</strong> · <strong>lista de notas</strong> · <strong>editor</strong>.',
    's12_folders'     => '<strong>Carpetas</strong> anidables para organizar las notas; una nota puede estar en una carpeta o suelta en la raíz. Al borrar una carpeta, sus notas y subcarpetas pasan a la raíz — no se pierde nada.',
    's12_editor'      => '<strong>Editor</strong> WYSIWYG sobre Markdown: formato en vivo, menú <code class="chip">/</code> para insertar bloques y arrastre de bloques. El contenido se guarda como Markdown, con <strong>autoguardado</strong>.',
    's12_search'      => '<strong>Buscar</strong> por título o contenido desde el cuadro de búsqueda de la lista; la búsqueda recorre todas las carpetas.',
    's12_pin'         => '<strong>Fijar</strong> una nota (★) la mantiene arriba de su lista.',

    // Section 13
    's13_title'       => '13. Tareas',
    's13_intro'       => 'Un tablero Kanban de tareas personales. Se abre desde <a class="underline" href=":url">Tareas</a> en el menú lateral. Cuatro columnas: <strong>Backlog</strong>, <strong>Por hacer</strong>, <strong>En curso</strong> y <strong>Hecho</strong>.',
    's13_modal'       => 'Crea y edita tareas en un modal: título, descripción, columna, prioridad, fecha de vencimiento y un <strong>proyecto</strong> opcional.',
    's13_drag'        => '<strong>Arrastra</strong> las tarjetas entre columnas y dentro de cada una; el orden se guarda solo.',
    's13_card'        => 'Cada tarjeta muestra su proyecto, prioridad y fecha (resaltada si está vencida).',
    's13_filter'      => 'Filtra el tablero por proyecto y por prioridad.',
    's13_wip'         => 'Las tareas <strong>En curso</strong> aparecen también en el Inicio.',

    // Section 14
    's14_title'       => '14. Inicio y atajos',
    's14_intro'       => 'La página de <a class="underline" href=":url">Inicio</a> resume tu actividad de un vistazo.',
    's14_week'        => 'La <strong>semana actual</strong> con las horas trackeadas de cada día.',
    's14_heatmap'     => 'Un <strong>heatmap</strong> de actividad del último año (clic en un día → su timeline).',
    's14_now'         => '<strong>Ahora mismo</strong>: lo último que registró el tracker.',
    's14_recent'      => 'Las últimas notas editadas y las tareas en curso.',
    's14_alert'       => 'Un aviso si el tracker lleva tiempo sin registrar actividad.',
    's14_palette'     => '<strong>Paleta de comandos</strong>: pulsa Ctrl K / ⌘ K (o «Buscar» en el menú lateral) para saltar a cualquier sección o nota al instante.',

    // Section 15
    's15_title'       => '15. Copias de seguridad y datos',
    's15_intro'       => 'En <a class="underline" href=":url">Datos</a> (menú Configuración) gestionas las copias y la exportación.',
    's15_backup'      => '<strong>Copias de seguridad</strong>: se crea una automática a diario y se conservan las 14 últimas. Puedes crear, descargar o restaurar copias a mano.',
    's15_restore'     => '<strong>Restaurar</strong> sobrescribe la base de datos actual — guarda una copia previa por seguridad. Conviene hacerlo con el tracker detenido.',
    's15_export'      => '<strong>Exportar</strong>: las notas a Markdown (un <code>.zip</code> por carpetas) y todos los datos a JSON.',
];
