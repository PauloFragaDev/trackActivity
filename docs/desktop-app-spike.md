# Spike · App de escritorio que controla el tracker

Estudio previo (no implementación) para decidir el enfoque de una **app de
escritorio** que, al **abrirla arranque el tracker** y al **cerrarla lo pare**.
Sustituye al punto "PWA + offline" del roadmap.

## Objetivo y semántica

- Abrir la app → el daemon del tracker arranca; cerrar la app → se para.
- La app muestra el dashboard (la web actual) en una ventana propia.
- La app vive en el ordenador del usuario (Linux, sesión gráfica X11).

**Decisión de comportamiento a confirmar:** atar el daemon a la ventana significa
que **solo se trackea mientras la app está abierta**. Hoy el daemon puede correr
todo el día (botón del dashboard + scheduler). Con este modelo, cerrar la ventana
= dejar de trackear. Puede ser justo lo que se quiere (la app = interruptor de
"estoy trabajando"), pero hay que asumirlo conscientemente.

## Restricciones reales (del código actual)

- **El daemon necesita la sesión gráfica:** `TrackerManager::start()` lo lanza con
  `env DISPLAY=… XAUTHORITY=… tracker run --foreground` (captura de ventana activa
  bajo X11). Una app de escritorio lanzada en la sesión del usuario tiene `DISPLAY`
  de forma natural → encaja.
- **El ciclo de vida del daemon ya está resuelto** en `TrackerManager` (spawn con
  `nohup` + PID file, detección de huérfanos vía `/proc`). La app de escritorio NO
  debe duplicar eso: debe **reutilizarlo** (ver "Arquitectura").
- **El dashboard se sirve localmente** (`php artisan serve`, single-user). La app
  de escritorio tendrá que asegurarse de que el servidor esté en marcha (o
  arrancarlo ella).
- **Ya existe `manifest.json`** (PWA), pero una PWA está aislada: **no puede
  arrancar/parar un proceso local** (el daemon). Por sí sola no cumple el objetivo.
- Plataforma: Linux/Debian, X11 (Wayland quedó en standby en el roadmap).

## Opciones

| Opción | Footprint | Control del daemon | Esfuerzo | Encaje |
|---|---|---|---|---|
| **Tauri** (Rust + WebView del SO) | Muy bajo (binario ~3-10 MB, poca RAM; usa WebKitGTK en Linux) | Nativo (spawn/kill procesos desde Rust; hooks de ventana) | Medio (toolchain Rust nuevo) | **El mejor** para "app real que controla el daemon" |
| **Electron** (Node + Chromium) | Alto (~150 MB, RAM elevada) | Fácil (`child_process`) | Bajo-medio (ecosistema conocido) | Sobredimensionado para una herramienta personal |
| **Launcher ligero** (script + Chromium `--app=`) | Mínimo (sin toolchain) | Manual: el script arranca serve+daemon y abre una ventana `--app`; al cerrarse, para todo | Bajo | Buen **MVP**, pero detectar "ventana cerrada" es más frágil |
| **PWA instalada** (ya hay manifest) | Nulo extra | **Ninguno** (no puede tocar el daemon) | Nulo | No cumple el objetivo por sí sola |

## Recomendación

**Tauri** para la versión "de verdad": ventana nativa ligera que envuelve el
dashboard local y gobierna el ciclo de vida del daemon. Footprint mínimo (clave en
una herramienta personal que está siempre abierta), control de procesos nativo, y
encaja con Linux/X11.

**MVP rápido sin toolchain nuevo** (si se quiere algo en una tarde antes de
comprometerse a Tauri): un **script lanzador** + Chromium/Chrome en modo
`--app=http://localhost:8000` (ventana sin barra, aspecto de app). El script:
1. arranca `php artisan serve` si no corre,
2. arranca el daemon (vía el comando artisan de abajo),
3. abre la ventana `--app`,
4. al cerrarse la ventana (el script espera al proceso del navegador), para el daemon.

Validar el MVP primero; si convence, portarlo a Tauri.

## Arquitectura propuesta (independiente de Tauri vs launcher)

- **No duplicar el spawn del daemon.** Exponer el control que ya hace
  `TrackerManager` como **comandos artisan** reutilizables, p. ej.
  `php artisan tracker:start` / `tracker:stop` (si no existen, añadirlos como
  wrapper fino sobre `TrackerManager`). La app de escritorio solo invoca esos
  comandos en los hooks de su ventana (abrir → start, cerrar → stop).
- **Servidor:** la app asegura `php artisan serve` (arrancarlo si no responde en
  el puerto; pararlo al salir si lo arrancó ella).
- **Apuntar la ventana** a `http://localhost:<puerto>` (el dashboard actual, sin
  cambios en la web).

## Riesgos / preguntas abiertas

- **"Cerrar ventana = dejar de trackear"** (ver arriba) — confirmar que es el
  comportamiento deseado.
- **Relación con el scheduler** (`schedule:work` que reconstruye bloques/resúmenes):
  ¿lo gobierna también la app, o sigue aparte? Hoy `SchedulerManager` es otro
  proceso independiente.
- **Arranque al iniciar sesión** (autostart): ¿se quiere que la app se abra sola
  al encender el PC?
- **Wayland**: el daemon asume X11; si algún día se migra a Wayland, esto cambia
  (fuera de alcance ahora).

## Siguiente paso

Si el enfoque (Tauri, con MVP launcher para validar) convence, se arranca un
brainstorm/spec del build. Mientras, esto queda como decisión de arquitectura
documentada; no toca la app web todavía.
