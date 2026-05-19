# 01 · Visión general

## El problema

Un desarrollador trabaja a lo largo del día sobre **múltiples proyectos de software**, alternando entre tareas, repositorios y herramientas. El reporte de horas se hace después, en un sistema de timesheet corporativo, y a menudo con días o semanas de retraso.

Como resultado:

- Es difícil recordar **qué se hizo y cuándo**.
- Las pistas se reconstruyen manualmente revisando: commits de Git, correos enviados, conversaciones en GitHub, ramas creadas, historial del navegador, etc.
- El reporte termina siendo aproximado, inconsistente o incompleto.

## La solución

`trackActivity` reconstruye automáticamente el contexto de trabajo a partir de **señales pasivas** que el sistema ya genera:

- Ventana activa del SO.
- Estado y commits de los repositorios Git locales.
- (Opcional) URLs del navegador y asuntos de Thunderbird.
- Eventos de inactividad.

Estas señales se agrupan en **bloques de tiempo** (por defecto 15 minutos) y un sistema de **scoring ponderado** determina el **proyecto dominante** de cada bloque.

El resultado es un *timeline* limpio:

```
09:00 - 10:30   JASPER     Confianza: Alta
   "Ajustes de permisos y correcciones del dashboard."

10:30 - 11:00   IDLE
11:00 - 12:15   YWL        Confianza: Media
   "Mantenimiento del módulo de notificaciones."
```

## Filosofía

| Principio | Significado práctico |
|-----------|----------------------|
| **Reconstrucción, no medición** | No se mide el tiempo exacto por ventana; se infiere el contexto dominante. |
| **Las ventanas son señales, no tareas** | El usuario hace Alt+Tab constantemente. Cada cambio es solo una pista. |
| **Agregación inteligente** | Múltiples señales pequeñas se combinan para inferir el proyecto. |
| **Corregible siempre** | El usuario puede fusionar, dividir o reasignar bloques desde el dashboard. |
| **Local y privado** | Nunca sale información del equipo. No hay APIs externas obligatorias. |
| **Ligero** | El daemon vive en segundo plano sin afectar la experiencia. |

## Caso de uso típico

> *Lunes, 10:00 AM.* El daemon ya lleva una hora capturando señales. El usuario abre VSCode en `~/Projects/jasper-api`, ejecuta una terminal en la misma carpeta, navega a la issue #123 en GitHub y responde un correo de Thunderbird con el asunto "Re: JASPER permissions".
>
> *Lunes, 18:00.* El usuario abre el dashboard Laravel y ve el día reconstruido en bloques. Confirma que los bloques de 09:00–10:30 y 14:00–16:00 son `JASPER`, corrige uno que el sistema marcó como `YWL` por confusión, y exporta el resumen en Markdown para pegarlo en su timesheet.

## A quién va dirigido

Desarrolladores que:

- Trabajan sobre varios proyectos al día.
- Usan Linux (Ubuntu por defecto en este proyecto).
- Reportan horas a posteriori en un sistema corporativo.
- Valoran la privacidad y la operación local.

## Glosario

| Término | Definición |
|---------|------------|
| **Señal (signal)** | Un evento crudo capturado por un collector (ej. "ventana VSCode con título *X*"). |
| **Bloque de tiempo (time block)** | Intervalo fijo (por defecto 15 min) sobre el que se agregan señales. |
| **Sesión (session)** | Conjunto de bloques contiguos con el mismo proyecto dominante, presentados como una unidad en el timeline. |
| **Proyecto dominante** | Proyecto con mayor puntaje agregado dentro de un bloque. |
| **Confianza (confidence)** | Métrica derivada del scoring: cuán claro es el proyecto dominante frente a los demás. |
| **Collector** | Componente del daemon especializado en capturar un tipo de señal (ventanas, Git, browser, etc.). |
| **Mapping** | Regla que asocia un identificador externo (repo, URL, asunto, carpeta) con un proyecto lógico. |
| **Evidencia (evidence)** | Lista de señales que respaldan la asignación de proyecto en un bloque o sesión. |
| **Resumen (summary)** | Texto generado a partir de la evidencia, listo para pegar en el timesheet. |

## Qué NO es esta aplicación

- **No es un time tracker estricto.** No mide segundos por aplicación.
- **No es una herramienta de vigilancia.** No reporta a terceros, no toma capturas de pantalla, no registra keystrokes.
- **No es un gestor de tareas.** No reemplaza Jira, Linear o GitHub Issues; los lee como evidencia.
- **No es SaaS.** No tiene cuenta, login ni nube.

## Próximos pasos

- Para entender la estructura técnica → [`02-architecture.md`](02-architecture.md).
- Para instalar y probar → [`03-installation.md`](03-installation.md).
- Para entender qué incluye la v1 → [`14-mvp-roadmap.md`](14-mvp-roadmap.md).
