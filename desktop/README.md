# trackActivity · App de escritorio (Tauri v2)

Cáscara de escritorio para Linux que es el **punto de entrada único** de
trackActivity: muestra el dashboard en una ventana nativa y gobierna el ciclo de
vida del stack (servidor web + daemon del tracker + scheduler).

- **Abrir** → arranca `php artisan serve` + `tracker:start` + `scheduler:start`,
  espera al puerto y muestra la ventana.
- **Cerrar la ventana** → se oculta a la **bandeja** (sigue trackeando).
- **Salir** (menú de bandeja) → para tracker + scheduler + serve y cierra.

No duplica la lógica del daemon: usa los comandos artisan
(`tracker:start/stop`, `scheduler:start/stop`), igual que el botón del dashboard.

## Requisitos

- Rust + cargo (`rustup`).
- `cargo install tauri-cli` (v2).
- Dependencias del sistema de Tauri en Linux (WebKitGTK 4.1, etc.).
- PHP/Composer del dashboard ya instalados.

## Configuración

- Puerto del dashboard: `8100` (constante `PORT` en `src-tauri/src/lib.rs`).
- Ruta del dashboard: variable de entorno **`TRACKACTIVITY_DASHBOARD`**
  (por defecto `/var/www/html/trackActivity/dashboard`).

## Desarrollo

```bash
cd desktop
cargo tauri dev      # o: cargo build  (solo compilar)
```

## Build (AppImage + .deb)

```bash
cd desktop
cargo tauri build
# artefactos en: src-tauri/target/release/bundle/{appimage,deb}/
```

## Checklist de verificación manual

La cáscara nativa (ventana/bandeja) no tiene tests automatizados. Verificar a mano:

- [ ] **Abrir** la app → arrancan serve, daemon y scheduler; el dashboard aparece
      solo cuando el servidor responde.
- [ ] **Cerrar la ventana** (botón X) → la ventana desaparece pero el dashboard
      sigue respondiendo en `localhost:8100` y el daemon sigue activo (badge del
      sidebar en "Tracker activo").
- [ ] **Bandeja → Mostrar / Ocultar** → alterna la visibilidad de la ventana.
- [ ] **Bandeja → Pausar / Reanudar tracker** → el badge del dashboard pasa a
      "detenido" y vuelve a "activo".
- [ ] **Bandeja → Iniciar al encender** → activarlo crea la entrada en
      `~/.config/autostart`; desactivarlo la borra. (Por defecto desactivado.)
- [ ] **Bandeja → Salir** → tracker, scheduler y serve quedan parados
      (comprobar con `php artisan tracker:doctor` o `ps aux | grep -E "serve|tracker"`).
- [ ] **Reabrir** → la ventana recuerda tamaño y posición.
- [ ] **Puerto 8100 ocupado** por otro proceso al abrir → el dashboard que carga
      la ventana no es trackActivity (limitación conocida del MVP: el puerto es
      fijo; documentado).
