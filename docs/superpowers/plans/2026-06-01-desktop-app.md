# App de escritorio (Tauri) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** App de escritorio Linux (Tauri) que es el punto de entrada único de trackActivity: al abrir arranca serve+daemon+scheduler, cerrar la ventana minimiza a bandeja (sigue trackeando), Salir para todo.

**Architecture:** Enfoque A — Tauri es una cáscara fina que orquesta el stack invocando comandos artisan que reusan `TrackerManager`/`SchedulerManager` (sin doble fuente de verdad). La lógica testeable vive en PHP (los comandos); la cáscara Tauri se verifica a mano.

**Tech Stack:** Laravel 11 (comandos artisan, Mockery para tests), Tauri v2 (Rust), plugins `tauri-plugin-window-state` y `tauri-plugin-autostart`. Linux/X11.

**Verificación:** PR1 con `php artisan test` (TDD, managers mockeados). PR2 **sin tests automatizados** (UI nativa) → requiere toolchain Rust (`rustup`, `cargo`, `tauri-cli`) y **checklist de verificación manual**.

**Rama:** `paulo-desktop-app` (desde main). Split:
- **PR1 (Tasks 1–3):** comandos artisan (PHP, testeable).
- **PR2 (Tasks 4–9):** app Tauri en `desktop/` (build + verificación manual).

Comandos desde `dashboard/` salvo PR2 (desde `desktop/`).

---

## File Structure

- `dashboard/app/Console/Commands/TrackerStartCommand.php` · `TrackerStopCommand.php`
- `dashboard/app/Console/Commands/SchedulerStartCommand.php` · `SchedulerStopCommand.php`
- `dashboard/tests/Feature/StackCommandsTest.php` — tests de los 4 comandos (managers mockeados).
- `desktop/` — app Tauri: `src-tauri/Cargo.toml`, `src-tauri/tauri.conf.json`, `src-tauri/src/main.rs`, `src-tauri/icons/`.
- `desktop/README.md` — cómo construir/ejecutar + checklist de verificación manual.

---

# PR1 — Comandos artisan (PHP, TDD)

> Los managers (`TrackerManager`, `SchedulerManager`) exponen `status(): array` (`['running'=>bool]`), `start(): void`, `stop(): void`. Los comandos son wrappers finos; los tests **mockean el manager** para no spawnear procesos reales.

## Task 1: Comandos `tracker:start` / `tracker:stop`

**Files:**
- Create: `dashboard/app/Console/Commands/TrackerStartCommand.php`
- Create: `dashboard/app/Console/Commands/TrackerStopCommand.php`
- Test: `dashboard/tests/Feature/StackCommandsTest.php`

- [ ] **Step 1: Escribir los tests (fallan primero)**

Crear `dashboard/tests/Feature/StackCommandsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\TrackerManager;
use Tests\TestCase;

class StackCommandsTest extends TestCase
{
    public function test_tracker_start_invokes_manager(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => false]);
        $mock->shouldReceive('start')->once();

        $this->artisan('tracker:start')->assertExitCode(0);
    }

    public function test_tracker_start_is_noop_when_already_running(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => true]);
        $mock->shouldNotReceive('start');

        $this->artisan('tracker:start')->assertExitCode(0);
    }

    public function test_tracker_start_reports_failure_gracefully(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => false]);
        $mock->shouldReceive('start')->andThrow(new \RuntimeException('sin binario'));

        $this->artisan('tracker:start')->assertExitCode(1);
    }

    public function test_tracker_stop_invokes_manager(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('stop')->once();

        $this->artisan('tracker:stop')->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Ejecutar (deben fallar)**

Run: `php artisan test --filter=StackCommandsTest`
Expected: FAIL (comandos `tracker:start`/`tracker:stop` no existen).

- [ ] **Step 3: Crear `TrackerStartCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Services\TrackerManager;
use Illuminate\Console\Command;

class TrackerStartCommand extends Command
{
    protected $signature = 'tracker:start';
    protected $description = 'Arranca el daemon del tracker (reusa TrackerManager).';

    public function handle(TrackerManager $tracker): int
    {
        if ($tracker->status()['running']) {
            $this->info('El tracker ya estaba en marcha.');
            return self::SUCCESS;
        }
        try {
            $tracker->start();
        } catch (\Throwable $e) {
            $this->error('No se pudo arrancar el tracker: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Tracker arrancado.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Crear `TrackerStopCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Services\TrackerManager;
use Illuminate\Console\Command;

class TrackerStopCommand extends Command
{
    protected $signature = 'tracker:stop';
    protected $description = 'Para el daemon del tracker (reusa TrackerManager).';

    public function handle(TrackerManager $tracker): int
    {
        try {
            $tracker->stop();
        } catch (\Throwable $e) {
            $this->error('No se pudo parar el tracker: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Tracker parado.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Ejecutar (deben pasar)**

Run: `php artisan test --filter=StackCommandsTest`
Expected: PASS (los 4 tests de tracker).
(Laravel 11 auto-descubre comandos en `app/Console/Commands` — no hay que registrarlos.)

- [ ] **Step 6: Commit**

```bash
git add dashboard/app/Console/Commands/TrackerStartCommand.php dashboard/app/Console/Commands/TrackerStopCommand.php dashboard/tests/Feature/StackCommandsTest.php
git commit -m "feat(stack): comandos tracker:start / tracker:stop"
```

---

## Task 2: Comandos `scheduler:start` / `scheduler:stop`

**Files:**
- Create: `dashboard/app/Console/Commands/SchedulerStartCommand.php`
- Create: `dashboard/app/Console/Commands/SchedulerStopCommand.php`
- Test: `dashboard/tests/Feature/StackCommandsTest.php` (añadir)

- [ ] **Step 1: Añadir tests (fallan primero)**

Añadir a `StackCommandsTest` (y el import al principio: `use App\Services\SchedulerManager;`):

```php
    public function test_scheduler_start_invokes_manager(): void
    {
        $mock = $this->mock(SchedulerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => false]);
        $mock->shouldReceive('start')->once();

        $this->artisan('scheduler:start')->assertExitCode(0);
    }

    public function test_scheduler_start_is_noop_when_already_running(): void
    {
        $mock = $this->mock(SchedulerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => true]);
        $mock->shouldNotReceive('start');

        $this->artisan('scheduler:start')->assertExitCode(0);
    }

    public function test_scheduler_stop_invokes_manager(): void
    {
        $mock = $this->mock(SchedulerManager::class);
        $mock->shouldReceive('stop')->once();

        $this->artisan('scheduler:stop')->assertExitCode(0);
    }
```

- [ ] **Step 2: Ejecutar (deben fallar)**

Run: `php artisan test --filter=StackCommandsTest`
Expected: FAIL (comandos de scheduler no existen).

- [ ] **Step 3: Crear `SchedulerStartCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Services\SchedulerManager;
use Illuminate\Console\Command;

class SchedulerStartCommand extends Command
{
    protected $signature = 'scheduler:start';
    protected $description = 'Arranca el scheduler (reusa SchedulerManager).';

    public function handle(SchedulerManager $scheduler): int
    {
        if ($scheduler->status()['running']) {
            $this->info('El scheduler ya estaba en marcha.');
            return self::SUCCESS;
        }
        try {
            $scheduler->start();
        } catch (\Throwable $e) {
            $this->error('No se pudo arrancar el scheduler: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Scheduler arrancado.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Crear `SchedulerStopCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Services\SchedulerManager;
use Illuminate\Console\Command;

class SchedulerStopCommand extends Command
{
    protected $signature = 'scheduler:stop';
    protected $description = 'Para el scheduler (reusa SchedulerManager).';

    public function handle(SchedulerManager $scheduler): int
    {
        try {
            $scheduler->stop();
        } catch (\Throwable $e) {
            $this->error('No se pudo parar el scheduler: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Scheduler parado.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Ejecutar (deben pasar)**

Run: `php artisan test --filter=StackCommandsTest`
Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
git add dashboard/app/Console/Commands/SchedulerStartCommand.php dashboard/app/Console/Commands/SchedulerStopCommand.php dashboard/tests/Feature/StackCommandsTest.php
git commit -m "feat(stack): comandos scheduler:start / scheduler:stop"
```

---

## Task 3: Cierre PR1

- [ ] **Step 1: Suite completa**

Run: `php artisan test`
Expected: todos verdes.

- [ ] **Step 2: Push + PR**

```bash
git push -u origin paulo-desktop-app
gh pr create --base main --head paulo-desktop-app \
  --title "feat(stack): comandos artisan para arrancar/parar tracker y scheduler" \
  --body "## Summary
- Comandos tracker:start/stop y scheduler:start/stop, wrappers finos sobre TrackerManager/SchedulerManager (idempotentes, fallo con gracia).
- Base reutilizable para la app de escritorio (Tauri) y para control por CLI."
```

> Parar aquí hasta que el usuario confirme merge a main (flujo acordado).

---

# PR2 — App Tauri (`desktop/`) · build + verificación manual

> **No hay tests automatizados de la UI nativa.** Requiere toolchain Rust:
> `rustup` + `cargo` + `cargo install tauri-cli` (o `npm create tauri-app`).
> Se construye y se verifica con la checklist de la Task 9. Las APIs concretas
> de Tauri v2 (tray, eventos de ventana, plugins) **deben verificarse contra la
> documentación de la versión instalada** al implementar; abajo va la estructura
> y la lógica; los nombres exactos de la API pueden variar por versión.

## Task 4: Scaffold de la app Tauri

**Files:**
- Create: `desktop/src-tauri/Cargo.toml`
- Create: `desktop/src-tauri/tauri.conf.json`
- Create: `desktop/src-tauri/src/main.rs`
- Create: `desktop/src-tauri/icons/` (copiar desde `dashboard/public/`)

- [ ] **Step 1: Generar el esqueleto**

Desde la raíz del repo:
```bash
cargo install create-tauri-app --locked   # si no está
cargo create-tauri-app desktop --template vanilla --manager npm
```
(o `npm create tauri-app@latest desktop`). Elegir: sin framework JS (vanilla),
Tauri v2. Esto crea `desktop/src-tauri/`.

- [ ] **Step 2: Configurar `tauri.conf.json`** para cargar el dashboard local

Ajustar la sección de ventana para que apunte al dashboard servido en local y
arranque oculta (la mostramos cuando `serve` responde):

```json
{
  "productName": "trackActivity",
  "identifier": "com.trackactivity.desktop",
  "app": {
    "windows": [
      {
        "title": "trackActivity",
        "url": "http://localhost:8000",
        "width": 1200,
        "height": 800,
        "visible": false
      }
    ]
  },
  "bundle": {
    "active": true,
    "targets": ["appimage", "deb"],
    "icon": ["icons/icon.png"]
  }
}
```

- [ ] **Step 3: Verificar arranque básico**

Run (desde `desktop/`): `cargo tauri dev`
Expected: compila y abre (con `php artisan serve` corriendo aparte, mostraría el
dashboard; aún sin orquestación). Si falla la API, ajustar a la versión de Tauri.

- [ ] **Step 4: Commit**

```bash
git add desktop/
git commit -m "feat(desktop): scaffold de la app Tauri que carga el dashboard local"
```

## Task 5: Orquestación del stack al arrancar

**Files:**
- Modify: `desktop/src-tauri/src/main.rs`
- Modify: `desktop/src-tauri/Cargo.toml` (deps si hacen falta)

- [ ] **Step 1: Implementar el arranque en `setup`**

En `main.rs`, en el hook `setup` de la app: lanzar `php artisan serve` como hijo
gestionado, e invocar `tracker:start` + `scheduler:start`. Esperar a que `serve`
responda y mostrar la ventana. Patrón (ajustar a la API v2 exacta):

```rust
use std::process::{Command, Child};
use std::sync::Mutex;

// Ruta al dashboard (ajustar/parametrizar): asumimos repo junto a la app.
const DASHBOARD_DIR: &str = "../dashboard"; // resolver a ruta absoluta en runtime
const PORT: u16 = 8000;

struct ServeChild(Mutex<Option<Child>>);

fn artisan(args: &[&str]) {
    let _ = Command::new("php").arg("artisan").args(args)
        .current_dir(DASHBOARD_DIR).status();
}

// en setup():
//   let serve = Command::new("php")
//       .args(["artisan", "serve", "--port", &PORT.to_string()])
//       .current_dir(DASHBOARD_DIR).spawn().expect("serve");
//   app.manage(ServeChild(Mutex::new(Some(serve))));
//   artisan(&["tracker:start"]);
//   artisan(&["scheduler:start"]);
//   // esperar a que el puerto responda (poll TcpStream::connect) y luego:
//   window.show().unwrap();
```

Espera a `serve`: hacer polling de `std::net::TcpStream::connect(("127.0.0.1", PORT))`
con timeout (p. ej. 30 intentos × 200ms) antes de `window.show()`.

- [ ] **Step 2: Verificar**

Run: `cargo tauri dev`
Expected: al abrir, arranca el server y el dashboard aparece solo (sin lanzar
`php artisan serve` a mano). Comprobar con `php artisan tracker:doctor` o el badge
del dashboard que el daemon quedó en marcha.

- [ ] **Step 3: Commit**

```bash
git add desktop/src-tauri
git commit -m "feat(desktop): orquesta serve + tracker:start + scheduler:start al abrir"
```

## Task 6: Cerrar → bandeja; Salir → apagar el stack

**Files:**
- Modify: `desktop/src-tauri/src/main.rs`

- [ ] **Step 1: Interceptar el cierre de ventana → ocultar**

Manejar el evento de cierre de la ventana para **prevenir el cierre y ocultarla**
(`api.prevent_close(); window.hide();`). Patrón v2 (ajustar):

```rust
// window.on_window_event(|event| {
//   if let WindowEvent::CloseRequested { api, .. } = event {
//       api.prevent_close();
//       window.hide().unwrap();
//   }
// });
```

- [ ] **Step 2: Teardown al Salir**

Al salir de verdad (item "Salir" del tray, Task 7): parar el stack antes de cerrar:

```rust
// fn shutdown(app: &AppHandle) {
//   artisan(&["tracker:stop"]);
//   artisan(&["scheduler:stop"]);
//   if let Some(state) = app.try_state::<ServeChild>() {
//       if let Some(mut child) = state.0.lock().unwrap().take() { let _ = child.kill(); }
//   }
// }
```
Llamar `shutdown(&app)` también en `RunEvent::ExitRequested`/`Exit` para cubrir
cierres por señal.

- [ ] **Step 3: Verificar (manual)**

`cargo tauri dev`: cerrar la ventana (X) → desaparece pero el dashboard sigue
respondiendo en `localhost:8000` y el daemon sigue (badge activo). Aún no hay
forma de "Salir" hasta la Task 7; matar con Ctrl-C debe disparar el teardown.

- [ ] **Step 4: Commit**

```bash
git add desktop/src-tauri
git commit -m "feat(desktop): cerrar oculta a bandeja; salir apaga el stack"
```

## Task 7: Bandeja (tray) con menú

**Files:**
- Modify: `desktop/src-tauri/src/main.rs`
- Modify: `desktop/src-tauri/Cargo.toml` (`tauri` con feature `tray-icon`)

- [ ] **Step 1: Crear el tray con su menú**

Tray con icono (reusar `icon.png`) y menú: *Mostrar/Ocultar*, *Pausar / Reanudar
tracker*, *Iniciar al encender* (toggle, Task 8), *Salir*. Patrón v2 (ajustar a
`tauri::tray::TrayIconBuilder` y `tauri::menu`):

```rust
// let menu = MenuBuilder::new(app)
//   .text("toggle_window", "Mostrar / Ocultar")
//   .text("toggle_tracker", "Pausar / Reanudar tracker")
//   .check("autostart", "Iniciar al encender")
//   .separator()
//   .text("quit", "Salir")
//   .build()?;
// TrayIconBuilder::new().menu(&menu).on_menu_event(|app, ev| match ev.id().as_ref() {
//   "toggle_window" => { /* show/hide main window */ }
//   "toggle_tracker" => { /* if running → tracker:stop else tracker:start */ }
//   "autostart" => { /* Task 8 */ }
//   "quit" => { shutdown(app); app.exit(0); }
//   _ => {}
// }).build(app)?;
```
Para *Pausar/Reanudar*: leer estado con `php artisan tracker:doctor` no es ideal;
más simple, mantener un flag en estado de la app (Mutex<bool>) sincronizado con
las llamadas start/stop que hace la propia app.

- [ ] **Step 2: Verificar (manual)**

`cargo tauri dev`: aparece el icono en la bandeja; *Mostrar/Ocultar* alterna la
ventana; *Pausar* para el daemon (badge del dashboard pasa a detenido) y *Reanudar*
lo vuelve a arrancar; *Salir* apaga todo y cierra la app.

- [ ] **Step 3: Commit**

```bash
git add desktop/src-tauri
git commit -m "feat(desktop): bandeja con mostrar/ocultar, pausar tracker y salir"
```

## Task 8: Autostart configurable + recordar ventana

**Files:**
- Modify: `desktop/src-tauri/Cargo.toml` (`tauri-plugin-autostart`, `tauri-plugin-window-state`)
- Modify: `desktop/src-tauri/src/main.rs`

- [ ] **Step 1: Plugins**

Añadir a `Cargo.toml` y registrar en el builder:
```rust
// .plugin(tauri_plugin_window_state::Builder::default().build())
// .plugin(tauri_plugin_autostart::init(MacosLauncher::LaunchAgent, None))
```
(En Linux el autostart escribe en `~/.config/autostart`.)

- [ ] **Step 2: Toggle de autostart desde el tray**

El item "Iniciar al encender" refleja y alterna el estado del plugin
(`autostart_manager.enable()/disable()/is_enabled()`), **por defecto desactivado**
(no llamar enable en el primer arranque). Marcar el check del menú según
`is_enabled()`.

- [ ] **Step 3: Verificar (manual)**

`cargo tauri dev` (o build): activar el toggle → aparece la entrada en
`~/.config/autostart`; desactivarlo → desaparece. Mover/redimensionar la ventana,
cerrar y reabrir → recuerda tamaño/posición.

- [ ] **Step 4: Commit**

```bash
git add desktop/src-tauri
git commit -m "feat(desktop): autostart configurable (off por defecto) y estado de ventana"
```

## Task 9: Empaquetado + checklist + PR

**Files:**
- Create: `desktop/README.md`

- [ ] **Step 1: Build de release**

Run (desde `desktop/`): `cargo tauri build`
Expected: genera AppImage y `.deb` en `desktop/src-tauri/target/release/bundle/`.
Si el puerto 8000 está ocupado por un proceso ajeno al abrir, la app debe avisar
(comprobar el caso: ocupar el puerto y abrir → mensaje de error en la ventana, no
fallo silencioso).

- [ ] **Step 2: Escribir `desktop/README.md`** con build + **checklist de verificación manual**:

```markdown
# trackActivity desktop (Tauri)

## Requisitos
rustup + cargo; `cargo install tauri-cli`; PHP/Composer del dashboard.

## Desarrollo
cd desktop && cargo tauri dev

## Build (AppImage + .deb)
cd desktop && cargo tauri build

## Checklist de verificación manual
- [ ] Abrir la app → arranca serve, daemon y scheduler; el dashboard aparece solo.
- [ ] Cerrar la ventana (X) → se oculta a bandeja; dashboard y daemon SIGUEN.
- [ ] Tray: Mostrar/Ocultar alterna la ventana.
- [ ] Tray: Pausar tracker → badge "detenido"; Reanudar → "activo".
- [ ] Tray: Iniciar al encender ON → entrada en ~/.config/autostart; OFF → se borra.
- [ ] Salir → tracker, scheduler y serve parados (comprobar con tracker:doctor / ps).
- [ ] Reabrir → recuerda tamaño/posición de la ventana.
- [ ] Puerto 8000 ocupado por otro proceso → la app avisa en vez de fallar en silencio.
```

- [ ] **Step 3: Commit + PR2**

```bash
git add desktop/
git commit -m "feat(desktop): empaquetado AppImage/.deb y checklist de verificacion"
git push
gh pr create --base main --head paulo-desktop-app \
  --title "feat(desktop): app de escritorio Tauri (control del stack)" \
  --body "## Summary
- App Tauri en desktop/: ventana al dashboard local, bandeja (Mostrar/Pausar/Autostart/Salir).
- Abrir arranca serve+daemon+scheduler; cerrar minimiza a bandeja; Salir apaga todo.
- Autostart configurable (off por defecto), estado de ventana, empaquetado AppImage/.deb.
- Verificacion manual (UI nativa, sin tests automatizados) — ver desktop/README.md."
```

(Si PR1 ya se mergeó, PR2 va en rama nueva desde main; si no, todo en `paulo-desktop-app`.)

---

## Notas de implementación

- **Sin doble fuente de verdad:** la app NO spawnea el daemon directamente; usa
  `tracker:start/stop` que pasan por `TrackerManager` (igual que el botón web).
- **DISPLAY/XAUTHORITY:** Tauri corre en la sesión gráfica → al invocar
  `php artisan tracker:start`, `TrackerManager` hereda el entorno y el daemon
  arranca con X11. No hay que pasar nada especial.
- **Ruta al dashboard:** `DASHBOARD_DIR` debe resolverse a ruta absoluta en
  runtime (relativa al binario o por variable de entorno); en dev `../dashboard`.
- **Tauri v2 API:** los bloques Rust son la lógica; verificar nombres exactos
  (`WindowEvent`, `TrayIconBuilder`, `MenuBuilder`, plugins) contra la doc de la
  versión instalada al compilar.
- **PR2 no tiene tests automatizados** — la red de seguridad es la checklist
  manual del README y los tests de los comandos artisan (PR1).
