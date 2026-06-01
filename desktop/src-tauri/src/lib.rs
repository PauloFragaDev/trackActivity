// App de escritorio trackActivity (Tauri v2).
//
// Cáscara fina: muestra el dashboard local en una ventana, y gobierna el ciclo
// de vida del stack reusando los comandos artisan (sin doble fuente de verdad).
//   - Al abrir: arranca `php artisan serve` (proceso hijo propio) + `tracker:start`
//     + `scheduler:start`; espera al puerto y muestra la ventana.
//   - Cerrar la ventana: se oculta a la bandeja (sigue trackeando).
//   - Salir (bandeja): para tracker + scheduler + serve y cierra.

use std::process::{Child, Command};
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::Mutex;
use tauri::menu::{MenuBuilder, MenuItemBuilder};
use tauri::tray::TrayIconBuilder;
use tauri::{AppHandle, Manager, WindowEvent};

// Puerto poco concurrido a propósito: 8000-8010 los usa el usuario para apps
// de trabajo. Si se cambia, actualizar también la window url de tauri.conf.json
// y APP_URL del dashboard.
const PORT: u16 = 8100;

/// Estado de la app: el proceso `serve` que poseemos y si el tracker está activo.
struct Stack {
    serve: Mutex<Option<Child>>,
    tracker_on: AtomicBool,
}

/// Directorio del dashboard Laravel. Configurable por entorno; fallback al path
/// del usuario (herramienta personal). En instalación, exportar TRACKACTIVITY_DASHBOARD.
fn dashboard_dir() -> String {
    std::env::var("TRACKACTIVITY_DASHBOARD")
        .unwrap_or_else(|_| "/var/www/html/trackActivity/dashboard".to_string())
}

/// Ejecuta `php artisan <args>` en el dashboard (bloqueante, ignora el resultado).
fn artisan(args: &[&str]) {
    let _ = Command::new("php")
        .arg("artisan")
        .args(args)
        .current_dir(dashboard_dir())
        .status();
}

/// Espera a que el puerto del dashboard responda (hasta `tries` × 300ms).
fn wait_for_port(port: u16, tries: u32) -> bool {
    use std::net::TcpStream;
    use std::time::Duration;
    let addr = format!("127.0.0.1:{port}");
    let sock = match addr.parse() {
        Ok(s) => s,
        Err(_) => return false,
    };
    for _ in 0..tries {
        if TcpStream::connect_timeout(&sock, Duration::from_millis(300)).is_ok() {
            return true;
        }
        std::thread::sleep(Duration::from_millis(300));
    }
    false
}

/// ¿Hay algo escuchando ya en el puerto? (para no servir encima de otro proceso).
fn port_in_use(port: u16) -> bool {
    use std::net::TcpStream;
    use std::time::Duration;
    match format!("127.0.0.1:{port}").parse() {
        Ok(sock) => TcpStream::connect_timeout(&sock, Duration::from_millis(200)).is_ok(),
        Err(_) => false,
    }
}

/// Arranca el stack: serve (hijo propio) + tracker + scheduler.
fn start_stack(app: &AppHandle) {
    // Solo levantamos serve si el puerto está libre; si está ocupado, no
    // servimos encima (el aviso se muestra en setup).
    if !port_in_use(PORT) {
        if let Ok(child) = Command::new("php")
            .args(["artisan", "serve", "--port", &PORT.to_string()])
            .current_dir(dashboard_dir())
            .spawn()
        {
            let state = app.state::<Stack>();
            *state.serve.lock().unwrap() = Some(child);
        }
    }
    artisan(&["tracker:start"]);
    artisan(&["scheduler:start"]);
    app.state::<Stack>().tracker_on.store(true, Ordering::SeqCst);
}

/// Para el stack: tracker + scheduler + mata el hijo serve. Idempotente.
fn stop_stack(app: &AppHandle) {
    artisan(&["tracker:stop"]);
    artisan(&["scheduler:stop"]);
    let state = app.state::<Stack>();
    if let Some(mut child) = state.serve.lock().unwrap().take() {
        let _ = child.kill();
    }
    state.tracker_on.store(false, Ordering::SeqCst);
}

fn build_tray(app: &AppHandle) -> tauri::Result<()> {
    let toggle_window = MenuItemBuilder::with_id("toggle_window", "Mostrar / Ocultar").build(app)?;
    let toggle_tracker =
        MenuItemBuilder::with_id("toggle_tracker", "Pausar / Reanudar tracker").build(app)?;
    let autostart = MenuItemBuilder::with_id("autostart", "Iniciar al encender").build(app)?;
    let quit = MenuItemBuilder::with_id("quit", "Salir").build(app)?;

    let menu = MenuBuilder::new(app)
        .items(&[&toggle_window, &toggle_tracker, &autostart])
        .separator()
        .items(&[&quit])
        .build()?;

    TrayIconBuilder::new()
        .icon(app.default_window_icon().unwrap().clone())
        .menu(&menu)
        .on_menu_event(|app, event| match event.id().as_ref() {
            "toggle_window" => {
                if let Some(w) = app.get_webview_window("main") {
                    if w.is_visible().unwrap_or(false) {
                        let _ = w.hide();
                    } else {
                        let _ = w.show();
                        let _ = w.set_focus();
                    }
                }
            }
            "toggle_tracker" => {
                let state = app.state::<Stack>();
                if state.tracker_on.load(Ordering::SeqCst) {
                    artisan(&["tracker:stop"]);
                    state.tracker_on.store(false, Ordering::SeqCst);
                } else {
                    artisan(&["tracker:start"]);
                    state.tracker_on.store(true, Ordering::SeqCst);
                }
            }
            "autostart" => {
                use tauri_plugin_autostart::ManagerExt;
                let mgr = app.autolaunch();
                if mgr.is_enabled().unwrap_or(false) {
                    let _ = mgr.disable();
                } else {
                    let _ = mgr.enable();
                }
            }
            "quit" => {
                stop_stack(app);
                app.exit(0);
            }
            _ => {}
        })
        .build(app)?;
    Ok(())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_log::Builder::default().build())
        .plugin(tauri_plugin_window_state::Builder::default().build())
        .plugin(tauri_plugin_autostart::init(
            tauri_plugin_autostart::MacosLauncher::LaunchAgent,
            None,
        ))
        .plugin(tauri_plugin_dialog::init())
        .manage(Stack {
            serve: Mutex::new(None),
            tracker_on: AtomicBool::new(false),
        })
        .setup(|app| {
            let handle = app.handle().clone();

            // Si el puerto ya está ocupado por otro proceso, avisar en vez de
            // servir/cargar a ciegas encima de él.
            if port_in_use(PORT) {
                use tauri_plugin_dialog::{DialogExt, MessageDialogKind};
                handle
                    .dialog()
                    .message(format!(
                        "El puerto {PORT} ya está en uso por otro proceso. Cierra ese \
                         servidor y vuelve a abrir trackActivity para que la app gestione \
                         su propio servidor."
                    ))
                    .kind(MessageDialogKind::Warning)
                    .title("trackActivity")
                    .show(|_| {});
            }

            start_stack(&handle);

            // Espera al puerto en un hilo aparte y muestra la ventana cuando responde.
            let show_handle = handle.clone();
            std::thread::spawn(move || {
                if wait_for_port(PORT, 40) {
                    if let Some(w) = show_handle.get_webview_window("main") {
                        let _ = w.show();
                        let _ = w.set_focus();
                    }
                }
            });

            build_tray(&handle)?;

            // Cerrar la ventana la oculta a la bandeja (no cierra la app).
            if let Some(window) = app.get_webview_window("main") {
                let w = window.clone();
                window.on_window_event(move |event| {
                    if let WindowEvent::CloseRequested { api, .. } = event {
                        api.prevent_close();
                        let _ = w.hide();
                    }
                });
            }
            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("error while building tauri application")
        .run(|app_handle, event| {
            if let tauri::RunEvent::Exit = event {
                stop_stack(app_handle);
            }
        });
}
