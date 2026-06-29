# 03 · Instalación

Hay dos modalidades de uso:

- **Solo Kanban de equipo** — para compañeros que solo necesitan acceder al tablero compartido. No requiere el tracker ni Python.
- **Instalación completa** — para quien también quiera el tracker de actividad personal (daemon Python + dashboard completo).

---

## Opción A: Solo Kanban de equipo

### Requisitos

| Software | Versión mínima |
|----------|---------------|
| PHP | 8.4 |
| Composer | 2.x |
| Node.js | 22.x |

### 1. Instalar dependencias del sistema

```bash
sudo apt update
sudo apt install -y git curl

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

### 2. Clonar e instalar

```bash
git clone https://github.com/PauloFragaDev/trackActivity.git
cd trackActivity/dashboard
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Configurar el entorno

Editar `dashboard/.env` y dejar estos valores (añadir las credenciales de Supabase que te haya pasado el administrador):

```env
APP_MODE=team_only

DB_CONNECTION=sqlite
DB_DATABASE=/tmp/trackactivity.sqlite

SUPABASE_DB_HOST=db.<ref>.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=<password>
SUPABASE_DB_SSLMODE=require
SUPABASE_URL=https://<ref>.supabase.co
SUPABASE_ANON_KEY=eyJ...
SUPABASE_SERVICE_ROLE_KEY=eyJ...
```

### 4. Migrar y compilar

```bash
php artisan migrate
php artisan migrate --database=supabase --path=database/migrations/team
npm install && npm run build
```

### 5. Arrancar

```bash
php artisan serve --port=8100
```

Abre `http://localhost:8100` — irá directamente al Kanban de equipo.

---

## Opción B: Instalación completa (tracker + dashboard)

### Requisitos

| Software | Versión mínima |
|----------|---------------|
| PHP | 8.4 |
| Composer | 2.x |
| Node.js | 22.x |
| Python | 3.11 |
| SQLite | 3.37 |
| X11 | — (Wayland no soportado) |

### 1. Dependencias del sistema

```bash
sudo apt update
sudo apt install -y xdotool wmctrl x11-utils libxss1 \
    python3.11 python3.11-venv python3-pip sqlite3 git curl

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

### 2. Clonar e instalar el dashboard

```bash
git clone https://github.com/PauloFragaDev/trackActivity.git
cd trackActivity/dashboard
composer install
cp .env.example .env
php artisan key:generate
```

Editar `dashboard/.env`:

```env
DB_DATABASE=/home/<tu-usuario>/.local/share/trackActivity/activity.db
```

Si también usas el Kanban de equipo, añadir las credenciales de Supabase.

```bash
mkdir -p ~/.local/share/trackActivity
touch ~/.local/share/trackActivity/activity.db
php artisan migrate
npm install && npm run build
```

Arrancar:

```bash
php artisan serve --port=8100
# http://localhost:8100
```

### 3. Instalar el daemon Python

```bash
cd ../tracker
python3.11 -m venv .venv
source .venv/bin/activate        # bash/zsh
# source .venv/bin/activate.fish   # fish
pip install --upgrade pip
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml
```

Editar `config.yml`:

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

Verificar:

```bash
tracker run --foreground --log-level=DEBUG
# Deberías ver "signal stored: window=..." cada 15 segundos. Ctrl+C para parar.
```

### 4. Registrar el daemon como servicio

```bash
mkdir -p ~/.config/systemd/user
cp tracker/scripts/trackactivity.service ~/.config/systemd/user/
```

Editar el unit file con tus rutas reales (sustituir `<usuario>`):

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

```bash
systemctl --user daemon-reload
systemctl --user enable --now trackactivity.service
journalctl --user -u trackactivity.service -f
```

---

## Resolución de problemas

| Síntoma | Causa | Solución |
|---------|-------|----------|
| Error 500 al arrancar | `APP_KEY` vacío | `php artisan key:generate` |
| No conecta a Supabase | Credenciales incorrectas o faltantes | Revisar variables `SUPABASE_*` en `.env` |
| Daemon no captura ventanas | Wayland activo | Iniciar sesión con "Ubuntu on Xorg" |
| `xdotool: Can't open display` | `DISPLAY` no exportada | Verificar `Environment=DISPLAY=:0` en el unit file |
| `database is locked` | WAL no habilitado | `sqlite3 activity.db "PRAGMA journal_mode=WAL;"` |
| Dashboard sin datos | Ruta de BBDD distinta en tracker y dashboard | Comprobar que `config.yml` y `.env` apuntan al mismo archivo |
