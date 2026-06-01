# App de escritorio (Tauri) — diseño

Fecha: 2026-06-01
Rama: `paulo-desktop-app`
Estado: aprobado, pendiente de plan de implementación.
Spike previo: `docs/desktop-app-spike.md` (PR #42, mergeado).

## Objetivo

Una app de escritorio para Linux que sea el **punto de entrada único** de
trackActivity: al abrirla arranca todo el stack (servidor web + daemon del
tracker + scheduler) y al **Salir** lo para. Cerrar la ventana **minimiza a la
bandeja** y sigue trackeando. Muestra el dashboard web actual en una ventana
nativa, sin cambios en la web.

## Decisiones tomadas (brainstorm)

- **Cerrar ventana → bandeja** (sigue trackeando). Solo *Salir* explícito para el stack.
- **La app gestiona TODO el stack**: `php artisan serve` + daemon + scheduler.
- **Autostart configurable** (toggle en la app), por defecto **desactivado**.
- **Base: Tauri** (la necesidad de bandeja descartó el MVP de Chromium `--app`).
- **Orquestación vía artisan** (enfoque A): reusar `TrackerManager`/`SchedulerManager`
  mediante comandos artisan; Tauri es una cáscara fina. **Sin doble fuente de
  verdad**: el botón web y la app llaman a lo mismo.
- Plataforma: **Linux/X11**, single-user. Wayland en standby. Windows/macOS fuera.

## Arquitectura

```
desktop/ (app Tauri, Rust)
  └─ ventana → http://localhost:<puerto>  (dashboard actual)
  └─ tray: Mostrar/Ocultar · Pausar/Reanudar tracker · Iniciar al encender · Salir
  └─ al abrir:   spawn `php artisan serve` (hijo propio)
                 + `php artisan tracker:start`   (→ TrackerManager)
                 + `php artisan scheduler:start` (→ SchedulerManager)
  └─ al Salir:   `tracker:stop` + `scheduler:stop` + kill del hijo serve
```

- El **daemon** y el **scheduler** los siguen gestionando sus managers (pid,
  detección de huérfanos, env X11 `DISPLAY`/`XAUTHORITY`). La app solo invoca los
  comandos artisan.
- **`serve`** no tiene manager; Tauri lo posee como proceso hijo y lo mata al Salir.
- Las llamadas `php artisan …` funcionan sin el server (son CLI; bootea el
  framework). `serve` es solo para mostrar el dashboard.
- Tauri corre en la sesión gráfica del usuario → `DISPLAY` disponible → el daemon
  (que lo necesita) arranca bien.

## Componentes

### 1. Comandos artisan (PHP — la lógica testeable)
- `tracker:start` / `tracker:stop` — wrapper fino sobre `TrackerManager::start()/stop()`.
  Idempotentes (start no duplica si ya corre; stop no falla si ya parado).
- `scheduler:start` / `scheduler:stop` — wrapper fino sobre `SchedulerManager`.
- Salida con código 0 y un mensaje de estado; pensados para invocarse desde Tauri.

### 2. App Tauri (`desktop/`)
- **Ventana**: carga `http://localhost:<puerto>`; recuerda tamaño/posición
  (`tauri-plugin-window-state`). Splash/loading hasta que `serve` responde.
- **Bandeja (tray)**: icono (reusa `icon.svg`) + menú:
  - *Mostrar/Ocultar ventana*
  - *Pausar / Reanudar tracker* (invoca tracker:stop/start)
  - *Iniciar al encender* (toggle autostart)
  - *Salir* (apaga el stack y cierra la app)
- **Ciclo de vida**:
  - Al lanzar: arranca el stack (orden arriba), espera a `serve`, muestra ventana.
  - Cerrar ventana (botón X): se intercepta → oculta a bandeja (no sale).
  - *Salir*: para tracker + scheduler + serve, luego cierra.
- **Autostart**: `tauri-plugin-autostart`; el toggle escribe/borra la entrada en
  `~/.config/autostart`. Por defecto desactivado.
- **Empaquetado**: build de Tauri a **AppImage + `.deb`** (Linux). Instalación con `.deb`.

## Detalles resueltos

- **Puerto**: fijo configurable (por defecto 8000). Si está ocupado por un proceso
  ajeno, la app lo avisa en la ventana en lugar de fallar en silencio (detecta si
  el `serve` que responde es trackActivity; si no, muestra error).
- **Idempotencia**: si el daemon/scheduler ya corrían (arrancados desde la web),
  `*:start` no los duplica; al *Salir*, la app los para igualmente.
- **Icono**: reusar `dashboard/public/icon.svg`.

## Testing

- **Feature tests** de los comandos artisan: `tracker:start/stop` y
  `scheduler:start/stop` arrancan/paran y son idempotentes (reusan managers ya
  cubiertos por tests).
- La cáscara Tauri (tray, ocultar-a-bandeja, autostart, orquestación) se
  **verifica a mano**: no hay test automatizado de UI nativa. Se documentará una
  checklist de verificación manual.

## Fuera de alcance (YAGNI)

- Windows / macOS (Linux/X11 primero).
- Auto-actualización del binario, notificaciones nativas, multi-ventana.
- Wayland (en standby en el roadmap).

## División en PRs

1. **PR1 — Comandos artisan (PHP, testeable):** `tracker:start/stop` +
   `scheduler:start/stop` sobre los managers, con feature tests. Útil por sí solo
   (control del stack por CLI) y deja la base lista para la app.
2. **PR2 — App Tauri (`desktop/`):** ventana, tray, ciclo de vida (cerrar→bandeja,
   Salir→apaga), autostart configurable, empaquetado AppImage/.deb, y checklist de
   verificación manual.

## Verificación

- `php artisan test` en verde (incluye los tests de los comandos nuevos).
- Build de Tauri produce AppImage/.deb sin error.
- Checklist manual: abrir→stack arriba y dashboard visible; cerrar→sigue en
  bandeja y trackeando; Salir→todo parado; toggle autostart escribe/borra la
  entrada; reabrir recuerda tamaño/posición.
