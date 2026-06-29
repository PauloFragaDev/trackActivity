# 03 · Instalación

Guía para dejar `trackActivity` operativo en Linux (Debian/Ubuntu) con sesión X11.

> El dashboard se abre en el **navegador** (`http://localhost:8100`). No hay app de escritorio instalable todavía.

---

## Requisitos

| Software | Versión mínima |
|----------|---------------|
| PHP | 8.4 |
| Composer | 2.x |
| Node.js | 22.x |
| Python | 3.11 |
| SQLite | 3.37 |
| Git | 2.34 |
| X11 | — (Wayland no soportado) |

---

## 1. Dependencias del sistema

```bash
sudo apt update
sudo apt install -y \
    xdotool wmctrl x11-utils libxss1 \
    python3.11 python3.11-venv python3-pip \
    sqlite3 git curl

# PHP 8.4
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-sqlite3 php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-pgsql

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
```

---

## 2. Clonar el repositorio

```bash
git clone https://github.com/PauloFragaDev/trackActivity.git
cd trackActivity
```

---

## 3. Crear la base de datos SQLite

```bash
mkdir -p ~/.local/share/trackActivity
touch ~/.local/share/trackActivity/activity.db
```

---

## 4. Instalar el dashboard (Laravel)

```bash
cd dashboard
composer install
cp .env.example .env
php artisan key:generate
```

Editar `.env` — mínimo necesario:

```env
DB_DATABASE=/home/<tu-usuario>/.local/share/trackActivity/activity.db
```

Ejecutar migraciones y compilar assets:

```bash
php artisan migrate
npm install
npm run build
```

Arrancar el servidor:

```bash
php artisan serve --port=8100
# Abre http://localhost:8100
```

---

## 5. Instalar el daemon Python (tracker)

### bash / zsh
```bash
cd ../tracker
python3.11 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml
```

### fish
```fish
cd ../tracker
python3.11 -m venv .venv
source .venv/bin/activate.fish
pip install --upgrade pip
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml
```

Editar `config.yml` — ajustar la ruta de la BBDD:

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
  idle:
    enabled: true
    threshold_seconds: 180
```

Verificar que captura señales:

```bash
tracker run --foreground --log-level=DEBUG
# Deberías ver "signal stored: window=..." cada 15 segundos. Ctrl+C para parar.
```

---

## 6. Registrar el daemon como servicio (systemd)

```bash
mkdir -p ~/.config/systemd/user
cp tracker/scripts/trackactivity.service ~/.config/systemd/user/
```

Editar el unit file con tus rutas:

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

Logs en tiempo real:

```bash
journalctl --user -u trackactivity.service -f
```

---

## 7. Verificación

1. Trabaja unos minutos normalmente (abre VSCode, terminal, navegador).
2. Abre `http://localhost:8100`.
3. Tras ~15 minutos deberías ver bloques de tiempo acumulándose.

Para acelerar la verificación:

```bash
cd dashboard
php artisan tracker:rebuild-blocks --since="1 hour ago"
```

---

## 8. Kanban de equipo (opcional, requiere Supabase)

El Kanban de equipo usa Supabase (PostgreSQL en la nube). Para activarlo:

1. Crea un proyecto en [supabase.com](https://supabase.com) (gratuito).
2. Añade en `dashboard/.env`:

```env
SUPABASE_DB_HOST=db.<ref>.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=tu-password
SUPABASE_DB_SSLMODE=require
SUPABASE_URL=https://<ref>.supabase.co
SUPABASE_ANON_KEY=eyJ...
SUPABASE_SERVICE_ROLE_KEY=eyJ...
```

   Los valores se sacan de Supabase → Settings → **Database** (host y password) y Settings → **API** (URL y claves).

3. Ejecutar las migraciones del equipo:

```bash
php artisan migrate --database=supabase --path=database/migrations/team
```

4. Reiniciar el servidor. El menú lateral mostrará la sección **Equipo**.

> Todos los compañeros que quieran usar el Kanban de equipo necesitan configurar las mismas variables de Supabase en su `.env`.

---

## Desinstalación

```bash
systemctl --user disable --now trackactivity.service
rm ~/.config/systemd/user/trackactivity.service
rm -rf ~/trackActivity
# Borra todos los datos (irreversible):
rm -rf ~/.local/share/trackActivity
```

---

## Resolución de problemas

| Síntoma | Causa | Solución |
|---------|-------|----------|
| Daemon no captura ventanas | Wayland activo | Iniciar sesión con "Ubuntu on Xorg" |
| `xdotool: Can't open display` | `DISPLAY` no exportada | Verificar `Environment=DISPLAY=:0` en el unit file |
| `database is locked` | WAL no habilitado | `sqlite3 activity.db "PRAGMA journal_mode=WAL;"` |
| Dashboard sin datos | Ruta de BBDD distinta en tracker y dashboard | Comprobar que `config.yml` y `.env` apuntan al mismo archivo |
| Error 500 al arrancar | `APP_KEY` vacío | `php artisan key:generate` |
