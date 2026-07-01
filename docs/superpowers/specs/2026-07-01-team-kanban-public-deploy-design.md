# Despliegue público del Kanban de equipo — Diseño

**Goal:** Desplegar el Kanban de equipo ya existente en `dashboard/` como un
sitio público, en un dominio propio, para que el jefe lo use con interacción
completa (crear/mover tareas, comentar, asignar) — sin duplicar código en un
proyecto nuevo.

**Contexto:** Esto ya se diseñó una vez (`docs/superpowers/specs/2026-06-25-kanban-multiuser-design.md`,
sección "Render como acceso web opcional") y se llegó a implementar contra
Render (`render.yaml`, `dashboard/Dockerfile`, `.github/workflows/keep-alive.yml`).
Se descartó **Render específicamente** (plan gratis que duerme, hack de
keep-alive) — no la idea de fondo. Este documento **sustituye** esa sección:
mismo mecanismo `APP_MODE=team_only`, mismo Dockerfile, pero desplegado en un
VPS propio con login real, sin las piezas específicas de Render.

## Qué no cambia

- El dashboard local (el que usa cada uno en su ordenador, vía `desktop/` o
  `php artisan serve`) sigue exactamente igual: sin login, con el selector de
  identidad actual (modal "¿Quién eres tú?"), conviviendo indefinidamente con
  el sitio público.
- El Kanban personal, notas, timeline, settings, backup/export siguen siendo
  funcionalidad **solo local** — no se exponen en la instalación pública.
- Los modelos `TeamTask`, `TeamProject`, `TeamMember`, etc. y su conexión a
  Supabase no cambian. El sitio público lee/escribe el **mismo** Supabase que
  el dashboard local — un único tablero real, sincronizado por Realtime.

## 1. Arquitectura: un solo codebase, dos despliegues

`dashboard/` se despliega dos veces:
- **Local** (como hoy): `APP_MODE` sin definir, todos los módulos visibles,
  identidad por sesión sin login.
- **Público** (nuevo): `APP_MODE=team_only`, en un contenedor Docker en un VPS
  propio, con login real.

No se crea ningún proyecto ni carpeta nueva. El único código nuevo es: modelo
`User` + login, y un middleware que endurece qué rutas responden en modo
`team_only`.

## 2. Autenticación

- Tabla `users` local (SQLite del contenedor, no Supabase — es solo "quién
  puede entrar", no dato de negocio de equipo).
- Login con email/contraseña. Sin registro público: las 3 cuentas (el
  usuario, su compañero, el jefe) se crean mediante un seeder (reproducible,
  no un paso manual de `tinker` que se olvida al redesplegar).
- **Login = identidad**: `users.team_member_id` (FK a `team_members` en
  Supabase). Al iniciar sesión ya se sabe quién eres — en esta instalación
  desaparece el modal "¿Quién eres tú?" (sigue existiendo tal cual en el
  dashboard local, que no tiene login).
- Middleware `auth` de Laravel envolviendo todas las rutas cuando
  `APP_MODE=team_only`.

## 3. Endurecer la superficie de rutas en modo `team_only`

Hoy `APP_MODE=team_only` **solo** redirige `/tasks` → `/team/tasks` y oculta
un toggle visual — el resto de rutas (`/settings/*`, `/data/backup`,
`/data/export/data`, `/notes`, `/projects`, `/help`, etc.) siguen respondiendo
si se conoce la URL. Para una instalación pública esto no es aceptable
(coincide con el hallazgo de la auditoría de rutas sin protección si se
expone en red).

Nuevo middleware global: cuando `env('APP_MODE') === 'team_only'`, cualquier
request cuya ruta no empiece por `/team/`, `/login` o `/logout` responde 404
directamente — no solo se esconde en la UI, es inalcanzable aunque se sepa la
URL exacta.

La raíz `/` hoy resuelve a `TimelineController::today` (el timeline
**personal**) — no queda cubierta por el redirect existente de `/tasks`.
Se añade `Route::redirect('/', '/team/tasks')` junto al de `/tasks` para que
la raíz del dominio también aterrice en el board del equipo en vez de 404 o
mostrar la vista personal. Los assets estáticos compilados (`public/build/*`)
no pasan por este middleware — los sirve el servidor web directamente, no el
router de Laravel.

## 4. Despliegue

- **Docker Compose** en el VPS: contenedor de la app (reutiliza
  `dashboard/Dockerfile`, ya depurado: PHP 8.4, Node 22.x) + Caddy como reverse
  proxy delante, con TLS automático (Let's Encrypt) para el dominio.
- Variables de entorno del contenedor: las mismas `SUPABASE_DB_*` /
  `SUPABASE_URL` / `SUPABASE_ANON_KEY` que ya usa el dashboard local, más
  `APP_MODE=team_only`.
- Actualizar el sitio público: script `deploy-vps.sh` (mismo patrón que
  `desktop/rebuild.sh`) — `git pull` + rebuild del contenedor Docker —
  ejecutado por SSH cuando toque desplegar cambios. Sin CI/CD automático por
  ahora (equipo de 3 personas, cambios poco frecuentes en este apartado;
  YAGNI).
- Sin necesidad de keep-alive: un VPS no duerme como el free tier de Render.

## 5. Limpieza

Se eliminan, por ser específicos de Render y ya no aplicar:
- `render.yaml`
- `.github/workflows/keep-alive.yml`

Se mantiene `dashboard/Dockerfile` (se reutiliza para el VPS).

## Fuera de alcance de este documento

- Detalles concretos del VPS (proveedor, IP, nombre exacto del subdominio) —
  se deciden al desplegar, no bloquean el diseño.
- Gestión de roles/permisos entre los 3 usuarios (todos tienen interacción
  completa por ahora, según lo acordado) — si en el futuro hiciera falta
  restringir algo al jefe, es una iteración posterior.
- Recuperación de contraseña / rotación de credenciales — con 3 cuentas
  creadas a mano, se resetean a mano si hace falta; no se monta flujo de
  "olvidé mi contraseña" en esta primera versión.
