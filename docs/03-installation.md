# 03 · Instalación

Guía para dejar `trackActivity` operativo en Ubuntu 22.04+.

> Si trabajas en otra distribución Linux, los comandos `apt` deberán adaptarse al gestor de paquetes correspondiente.

---

## 1. Requisitos del sistema

| Software | Versión mínima | Notas |
|----------|---------------|-------|
| Ubuntu | 22.04 LTS | Probado también en 24.04 |
| Python | 3.11 | Para el daemon |
| PHP | 8.2 | Requisito de Laravel 11 |
| Composer | 2.6 | Gestor de dependencias PHP |
| SQLite | 3.37 | Con soporte WAL |
| Git | 2.34 | Necesario para el collector de Git |
| systemd | — | Para gestión del daemon como servicio de usuario |

### Dependencias del sistema operativo (X11)

El collector de ventana activa usa utilidades X11. **Wayland tiene soporte limitado** (ver sección al final).

```bash
sudo apt update
sudo apt install -y \
    xdotool \
    wmctrl \
    x11-utils \
    libxss1 \
    python3.11 python3.11-venv python3-pip \
    php8.2 php8.2-cli php8.2-sqlite3 php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-intl \
    composer \
    sqlite3 \
    git
```

---

## 2. Clonar el repositorio

```bash
git clone <repo-url> trackActivity
cd trackActivity
```

Estructura esperada tras el clone:

```
trackActivity/
├── README.md
├── docs/
├── tracker/      # daemon Python
└── dashboard/    # app Laravel
```

---

## 3. Preparar la base de datos

La base de datos se crea desde Laravel (las migraciones viven allí).

```bash
mkdir -p ~/.local/share/trackActivity
touch ~/.local/share/trackActivity/activity.db
```

> **Ubicación canónica**: `~/.local/share/trackActivity/activity.db` (sigue la [XDG Base Directory Specification](https://specifications.freedesktop.org/basedir-spec/basedir-spec-latest.html)).

---

## 4. Instalar el dashboard (Laravel)

```bash
cd dashboard
composer install
cp .env.example .env
php artisan key:generate
```

Editar `.env` y apuntar a la BBDD compartida:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/home/<tu-usuario>/.local/share/trackActivity/activity.db
DB_FOREIGN_KEYS=true

APP_URL=http://127.0.0.1:8000
APP_ENV=local
APP_DEBUG=true
```

Ejecutar migraciones y seeders:

```bash
php artisan migrate --seed
```

Esto crea todas las tablas (ver [`05-database-schema.md`](05-database-schema.md)) y carga el catálogo inicial de proyectos y mappings de ejemplo.

Levantar el servidor local:

```bash
php artisan serve
# http://127.0.0.1:8000
```

---

## 5. Instalar el daemon (Python)

```bash
cd ../tracker
python3.11 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
cp config.example.yml config.yml
```

Editar `config.yml` (ver detalle en [`04-configuration.md`](04-configuration.md)). Mínimo:

```yaml
database:
  path: ~/.local/share/trackActivity/activity.db

collectors:
  window:
    enabled: true
    interval_seconds: 15
  git:
    enabled: true
    interval_seconds: 240
    repositories_paths:
      - ~/Projects
  browser:
    enabled: false
  thunderbird:
    enabled: false
  idle:
    enabled: true
    threshold_seconds: 180
```

Verificar que captura señales en primer plano:

```bash
python -m tracker.cli run --foreground --log-level=DEBUG
```

Si ves líneas como `signal stored: window=...` y `signal stored: git=...`, está funcionando. Cortar con `Ctrl+C`.

---

## 6. Registrar el daemon como servicio de usuario (systemd)

Copiar el unit file que provee el repo:

```bash
mkdir -p ~/.config/systemd/user
cp tracker/scripts/trackactivity.service ~/.config/systemd/user/
```

Editar el archivo si tu ruta a `python` o al repo difiere:

```ini
[Unit]
Description=trackActivity background tracker
After=graphical-session.target

[Service]
Type=simple
ExecStart=/home/<usuario>/trackActivity/tracker/.venv/bin/python -m tracker.cli run
Restart=on-failure
RestartSec=10
Environment=DISPLAY=:0
Environment=XAUTHORITY=/home/<usuario>/.Xauthority

[Install]
WantedBy=default.target
```

Activar:

```bash
systemctl --user daemon-reload
systemctl --user enable --now trackactivity.service
systemctl --user status trackactivity.service
```

Para que arranque sin necesidad de sesión gráfica activa:

```bash
sudo loginctl enable-linger $USER
```

Logs en tiempo real:

```bash
journalctl --user -u trackactivity.service -f
```

---

## 7. Verificación post-instalación

1. Trabaja unos minutos con normalidad (abrir VSCode en algún repo, terminal, navegador).
2. Abre el dashboard: `http://127.0.0.1:8000`.
3. Deberías ver señales acumulándose y, tras ~15 minutos, el primer bloque reconstruido.

Si quieres acelerar la verificación, fuerza la agregación manualmente:

```bash
cd dashboard
php artisan tracker:rebuild-blocks --since="1 hour ago"
```

---

## 8. (Opcional) Filament admin

Si activaste Filament en el `.env` (`FILAMENT_ENABLED=true`):

```bash
cd dashboard
php artisan filament:install --panels
php artisan make:filament-user
```

Filament estará disponible en `http://127.0.0.1:8000/admin`.

---

## 9. Desinstalación

```bash
systemctl --user disable --now trackactivity.service
rm ~/.config/systemd/user/trackactivity.service
rm -rf ~/trackActivity
# Opcional, BORRA TODOS LOS DATOS:
rm -rf ~/.local/share/trackActivity
```

---

## Notas sobre Wayland

El collector de ventana activa basado en `xdotool`/`wmctrl` **no funciona bajo Wayland puro**. Alternativas previstas (fuera de v1):

- GNOME shell extension que exponga la ventana activa por DBus.
- Soporte experimental vía `swaymsg` para Sway.

Para v1, se recomienda iniciar sesión con "Ubuntu on Xorg" en el selector de gdm3.

---

## Resolución de problemas

| Síntoma | Causa probable | Solución |
|---------|----------------|----------|
| Daemon corre pero no captura ventanas | Wayland activo | Cambiar a sesión Xorg |
| `xdotool: Can't open display` en logs | `DISPLAY` no exportada al servicio | Verificar `Environment=DISPLAY=:0` en el unit file |
| `database is locked` | Modo WAL no habilitado | Ejecutar `sqlite3 activity.db "PRAGMA journal_mode=WAL;"` |
| Dashboard no muestra datos | Ruta de BBDD distinta | Confirmar que `tracker/config.yml` y `dashboard/.env` apuntan al **mismo archivo** |
| Daemon consume CPU | Intervalos demasiado bajos | Subir `interval_seconds` en `config.yml` |
