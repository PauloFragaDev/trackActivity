/**
 * Pomodoro · pill rica + transiciones + modal de cierre + drag + minimizar.
 *
 * El estado real vive en BBDD (active_timers). Este módulo:
 *   - Tickea el contador descontando offset de pausa.
 *   - Cuando llega a 0 (fin de fase), llama a /timer/advance:
 *       · si era focus → backend crea manual_entry y abre el modal de cierre.
 *       · si era break → vuelve a focus automáticamente.
 *   - Cablea pause/resume/skip/stop, el ▶ de las cards y el CTA del dashboard.
 *   - Drag de la pill (desktop): persistencia en localStorage, clamp al viewport,
 *     umbral de 5 px para no confundir click con drag.
 *   - Minimizar a sidebar: la pill se oculta, aparece un dock con dot+tiempo+
 *     título; click expande. El ticker sigue vivo y pinta lo que esté visible.
 */

import Swal from 'sweetalert2';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const LS_POS  = 'timer-pill-position';     // {left, top} en px, persistido por dispositivo
const LS_MIN  = 'timer-pill-minimized';    // '1' si dock activo
const DRAG_THRESHOLD = 5;                  // px antes de considerar drag
const isDesktop = () => window.matchMedia('(min-width: 768px)').matches;

function postJson(url, payload = {}) {
    const body = new URLSearchParams({ _token: csrf(), ...payload });
    return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body,
    }).then((r) => r.json().catch(() => ({})));
}

function pad(n) {
    return String(n).padStart(2, '0');
}

function formatRemaining(sec) {
    const abs = Math.abs(sec);
    const m = Math.floor(abs / 60);
    const s = abs % 60;
    return `${pad(m)}:${pad(s)}`;
}

/** Segundos restantes en la fase actual, descontando offset de pausa. */
function remainingSeconds(pill) {
    const phaseStart = Date.parse(pill.dataset.phaseStartedAt);
    const offset     = parseInt(pill.dataset.pausedOffsetSeconds || '0', 10);
    const duration   = parseInt(pill.dataset.phaseDurationSeconds || '0', 10);
    const pausedAt   = pill.dataset.pausedAt ? Date.parse(pill.dataset.pausedAt) : null;
    const now        = Date.now();
    let elapsed = Math.floor((now - phaseStart) / 1000) - offset;
    if (pausedAt) {
        elapsed -= Math.max(0, Math.floor((now - pausedAt) / 1000));
    }
    return duration - elapsed;
}

function refreshDot(pill) {
    const dot = pill.querySelector('[data-timer-dot]');
    if (!dot) return;
    const paused = !!pill.dataset.pausedAt;
    const classes = ['bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-ink-400', 'animate-pulse'];
    dot.classList.remove(...classes);
    if (paused) { dot.classList.add('bg-ink-400'); return; }
    dot.classList.add('animate-pulse');
    if (pill.dataset.state === 'focus') dot.classList.add('bg-emerald-500');
    else if (pill.dataset.state === 'short_break') dot.classList.add('bg-amber-500');
    else if (pill.dataset.state === 'long_break') dot.classList.add('bg-sky-500');
    else dot.classList.add('bg-emerald-500');
    // El dot del dock comparte color con el de la pill.
    const dockDot = document.querySelector('[data-timer-dock-dot]');
    if (dockDot) {
        dockDot.classList.remove(...classes);
        dockDot.classList.add(...dot.classList);
    }
}

function setStateLabel(pill) {
    const lbl = pill.querySelector('[data-timer-state-label]');
    if (!lbl) return;
    const map = { focus: 'Foco', short_break: 'Pausa corta', long_break: 'Pausa larga' };
    lbl.textContent = pill.dataset.pausedAt ? 'Pausado' : (map[pill.dataset.state] || 'Foco');
}

function applyTimerData(pill, payload) {
    if (!payload?.running) return;
    pill.dataset.state                 = payload.state || 'focus';
    pill.dataset.phaseStartedAt        = payload.phase_started_at || '';
    pill.dataset.pausedAt              = payload.paused_at || '';
    pill.dataset.pausedOffsetSeconds   = String(payload.paused_offset_seconds ?? 0);
    pill.dataset.phaseDurationSeconds  = String(payload.phase_duration_seconds ?? 0);
    pill.dataset.cycleCount            = String(payload.cycle_count ?? 0);
    if (payload.task_title) {
        pill.dataset.taskTitle = payload.task_title;
        const title = pill.querySelector('[data-timer-task-title]');
        if (title) title.textContent = payload.task_title;
        const dockTitle = document.querySelector('[data-timer-dock-title]');
        if (dockTitle) dockTitle.textContent = payload.task_title;
    }
    const cycles = pill.querySelector('[data-timer-cycles]');
    if (cycles) cycles.textContent = `#${payload.cycle_count ?? 0}`;

    pill.classList.remove('timer-pill--focus', 'timer-pill--short-break', 'timer-pill--long-break', 'timer-pill--paused');
    pill.classList.add(`timer-pill--${(payload.state || 'focus').replace('_', '-')}`);
    if (payload.paused_at) pill.classList.add('timer-pill--paused');

    const iconPause = pill.querySelector('[data-timer-icon-pause]');
    const iconPlay  = pill.querySelector('[data-timer-icon-play]');
    if (iconPause && iconPlay) {
        iconPause.classList.toggle('hidden',      !!payload.paused_at);
        iconPause.classList.toggle('inline-flex', !payload.paused_at);
        iconPlay.classList.toggle('hidden',       !payload.paused_at);
        iconPlay.classList.toggle('inline-flex',  !!payload.paused_at);
    }

    refreshDot(pill);
    setStateLabel(pill);
}

/**
 * Modal de cierre (mood / progress / nota). Devuelve los datos si el usuario
 * envía el form, o null si lo salta.
 */
function openFocusCloseModal(summaryText) {
    const dlg = document.getElementById('focus-close-modal');
    if (!dlg) return Promise.resolve(null);
    const form = dlg.querySelector('#focus-close-form');
    form.querySelector('[data-focus-summary]').textContent = summaryText || '';

    let mood = null;
    let progress = null;
    const moodGroup     = form.querySelector('[data-focus-mood]');
    const progressGroup = form.querySelector('[data-focus-progress]');
    moodGroup.querySelectorAll('[data-mood-value]').forEach((b) => {
        b.classList.remove('ring-2', 'ring-emerald-500');
        b.onclick = () => {
            mood = parseInt(b.dataset.moodValue, 10);
            moodGroup.querySelectorAll('[data-mood-value]').forEach((x) => x.classList.remove('ring-2', 'ring-emerald-500'));
            b.classList.add('ring-2', 'ring-emerald-500');
        };
    });
    progressGroup.querySelectorAll('[data-progress-value]').forEach((b) => {
        b.classList.remove('ring-2', 'ring-emerald-500');
        b.onclick = () => {
            progress = b.dataset.progressValue;
            progressGroup.querySelectorAll('[data-progress-value]').forEach((x) => x.classList.remove('ring-2', 'ring-emerald-500'));
            b.classList.add('ring-2', 'ring-emerald-500');
        };
    });
    form.querySelector('textarea[name="notes"]').value = '';

    return new Promise((resolve) => {
        const onSubmit = (e) => {
            e.preventDefault();
            cleanup();
            const notes = form.querySelector('textarea[name="notes"]').value.trim();
            dlg.close();
            resolve({ mood, progress, notes });
        };
        const onSkip = () => { cleanup(); dlg.close(); resolve(null); };
        const cleanup = () => {
            form.removeEventListener('submit', onSubmit);
            form.querySelector('[data-focus-skip]').removeEventListener('click', onSkip);
        };
        form.addEventListener('submit', onSubmit);
        form.querySelector('[data-focus-skip]').addEventListener('click', onSkip);
        if (typeof dlg.showModal === 'function') dlg.showModal();
    });
}

/**
 * Confirmación previa al stop: si era focus, además se abre luego el modal
 * de cierre breve para enriquecer la entry.
 */
async function confirmStop(pill) {
    const wasFocus = pill.dataset.state === 'focus';
    const rem = remainingSeconds(pill);
    const phaseDur = parseInt(pill.dataset.phaseDurationSeconds || '0', 10);
    const elapsedSec = Math.max(0, phaseDur - rem);
    const elapsedMin = Math.floor(elapsedSec / 60);

    const html = wasFocus
        ? `Llevas <b>${elapsedMin} min</b> de foco. Se guardarán como entrada manual.`
        : 'Vas a salir del Pomodoro durante una pausa. No se guarda nada.';

    const res = await Swal.fire({
        title: '¿Parar el Pomodoro?',
        html,
        customClass: { popup: 'app-swal', confirmButton: 'btn-danger', cancelButton: 'btn-ghost' },
        buttonsStyling: false,
        reverseButtons: true,
        showCancelButton: true,
        confirmButtonText: 'Sí, parar',
        cancelButtonText: 'Seguir',
    });
    return res.isConfirmed;
}

/* ─────────────── Drag de la pill (desktop) ─────────────── */

/** Lee la posición persistida y la aplica al pill si existe; valida viewport. */
function applyStoredPosition(pill) {
    if (!isDesktop()) return;
    try {
        const raw = localStorage.getItem(LS_POS);
        if (!raw) return;
        const { left, top } = JSON.parse(raw);
        if (typeof left !== 'number' || typeof top !== 'number') return;
        positionPill(pill, left, top);
    } catch { /* ignore */ }
}

/** Aplica una posición absoluta clampada al viewport. */
function positionPill(pill, left, top) {
    const margin = 8;
    const w = pill.offsetWidth;
    const h = pill.offsetHeight;
    const maxLeft = window.innerWidth  - w - margin;
    const maxTop  = window.innerHeight - h - margin;
    const clampedLeft = Math.min(Math.max(margin, left), Math.max(margin, maxLeft));
    const clampedTop  = Math.min(Math.max(margin, top),  Math.max(margin, maxTop));
    // Quitamos el preset bottom-center y aplicamos top/left absolutos.
    pill.classList.remove('bottom-4', 'left-1/2', '-translate-x-1/2');
    pill.style.left = clampedLeft + 'px';
    pill.style.top  = clampedTop + 'px';
    pill.style.right = 'auto';
    pill.style.bottom = 'auto';
    pill.style.transform = 'none';
}

function initDrag(pill) {
    if (!isDesktop()) return;
    const handle = pill.querySelector('[data-timer-handle]');
    if (!handle) return;

    let startX = 0, startY = 0, originLeft = 0, originTop = 0;
    let dragging = false;
    let pointerId = null;

    const onPointerDown = (e) => {
        // Sólo botón principal o touch.
        if (e.button !== undefined && e.button !== 0) return;
        // Si el evento nace dentro de un botón hijo, dejarlo pasar (no drag).
        if (e.target.closest('button')) return;
        pointerId = e.pointerId;
        handle.setPointerCapture?.(pointerId);

        // Asegúrate de tener una left/top absolutas para hacer delta sobre ellas.
        const rect = pill.getBoundingClientRect();
        originLeft = rect.left;
        originTop  = rect.top;
        startX = e.clientX;
        startY = e.clientY;
        dragging = false; // se confirmará al superar el umbral
    };

    const onPointerMove = (e) => {
        if (pointerId === null || e.pointerId !== pointerId) return;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        if (!dragging) {
            if (Math.hypot(dx, dy) < DRAG_THRESHOLD) return;
            dragging = true;
            pill.classList.add('is-dragging');
            // Antes del primer move real, fijamos las coords actuales.
            positionPill(pill, originLeft, originTop);
        }
        positionPill(pill, originLeft + dx, originTop + dy);
    };

    const onPointerUp = (e) => {
        if (pointerId === null) return;
        if (e.pointerId !== pointerId) return;
        try { handle.releasePointerCapture?.(pointerId); } catch { /* ignore */ }
        pointerId = null;
        if (dragging) {
            pill.classList.remove('is-dragging');
            // Persistir la posición final si supera el umbral.
            const rect = pill.getBoundingClientRect();
            try {
                localStorage.setItem(LS_POS, JSON.stringify({ left: rect.left, top: rect.top }));
            } catch { /* ignore */ }
            // Evita que un click se dispare después de un drag.
            const swallow = (ev) => { ev.stopPropagation(); ev.preventDefault(); window.removeEventListener('click', swallow, true); };
            window.addEventListener('click', swallow, true);
        }
        dragging = false;
    };

    handle.addEventListener('pointerdown', onPointerDown);
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup',   onPointerUp);
    window.addEventListener('pointercancel', onPointerUp);

    // Reclamp al cambiar tamaño de ventana.
    window.addEventListener('resize', () => {
        if (!isDesktop()) return;
        const rect = pill.getBoundingClientRect();
        positionPill(pill, rect.left, rect.top);
    });
}

/* ─────────────── Minimizar a sidebar (desktop) ─────────────── */

function setMinimized(pill, dock, isMin) {
    pill.classList.toggle('hidden', isMin);
    dock?.classList.toggle('timer-dock--visible', isMin);
    try { localStorage.setItem(LS_MIN, isMin ? '1' : '0'); } catch { /* ignore */ }
}

function initMinimize(pill, dock) {
    if (!dock) return;
    // Estado inicial: persisted, pero sólo si estamos en desktop (en móvil
    // la pill siempre flota).
    const stored = localStorage.getItem(LS_MIN) === '1';
    if (stored && isDesktop()) setMinimized(pill, dock, true);

    pill.querySelector('[data-timer-minimize]')?.addEventListener('click', () => {
        if (!isDesktop()) return;
        setMinimized(pill, dock, true);
    });
    dock.addEventListener('click', () => setMinimized(pill, dock, false));

    // Al cambiar a móvil, expulsamos del dock (no tiene sentido ahí).
    window.addEventListener('resize', () => {
        if (!isDesktop() && dock.classList.contains('timer-dock--visible')) {
            setMinimized(pill, dock, false);
        }
    });
}

export function initPomodoro() {
    const pill = document.getElementById('timer-pill');
    const dock = document.getElementById('timer-dock');

    let advancing = false;
    if (pill) {
        applyStoredPosition(pill);
        initDrag(pill);
        initMinimize(pill, dock);

        const elapsed     = pill.querySelector('[data-timer-elapsed]');
        const dockElapsed = document.querySelector('[data-timer-dock-elapsed]');

        const tick = async () => {
            if (!pill.isConnected) return;
            const rem = remainingSeconds(pill);
            const paused = !!pill.dataset.pausedAt;
            const text = formatRemaining(rem);
            elapsed.textContent = text;
            if (dockElapsed) dockElapsed.textContent = text;
            elapsed.classList.toggle('text-rose-500', rem < 0 && !paused);
            dockElapsed?.classList.toggle('text-rose-500', rem < 0 && !paused);
            if (!paused && rem <= 0 && !advancing) {
                advancing = true;
                await advancePhase();
                advancing = false;
            }
        };
        tick();
        setInterval(tick, 1000);

        pill.querySelector('[data-timer-toggle-pause]')?.addEventListener('click', async () => {
            const isPaused = !!pill.dataset.pausedAt;
            const payload = await postJson(isPaused ? '/timer/resume' : '/timer/pause');
            applyTimerData(pill, payload);
        });

        pill.querySelector('[data-timer-skip]')?.addEventListener('click', advancePhase);

        pill.querySelector('[data-timer-stop]')?.addEventListener('click', async () => {
            if (! await confirmStop(pill)) return;
            const wasFocus = pill.dataset.state === 'focus';
            const taskTitle = pill.dataset.taskTitle;
            if (wasFocus) {
                const meta = await openFocusCloseModal(`Foco · ${taskTitle}`);
                const payload = meta
                    ? { mood: meta.mood ?? '', progress: meta.progress ?? '', notes: meta.notes ?? '' }
                    : {};
                const res = await postJson('/timer/stop', payload);
                if (typeof window.toast === 'function' && res?.minutes_logged) {
                    window.toast(`Foco guardado · ${res.minutes_logged} min`, 'success');
                }
            } else {
                await postJson('/timer/stop');
            }
            // Limpiamos la posición persistida: al volver, vuelve al default.
            try { localStorage.removeItem(LS_POS); localStorage.removeItem(LS_MIN); } catch { /* ignore */ }
            window.location.reload();
        });
    }

    async function advancePhase() {
        if (!pill) return;
        const wasFocus = pill.dataset.state === 'focus';
        const taskTitle = pill.dataset.taskTitle;
        let meta = null;
        if (wasFocus) {
            meta = await openFocusCloseModal(`Foco · ${taskTitle}`);
        }
        const payload = meta
            ? { mood: meta.mood ?? '', progress: meta.progress ?? '', notes: meta.notes ?? '' }
            : {};
        const res = await postJson('/timer/advance', payload);
        applyTimerData(pill, res);
        if (typeof window.toast === 'function') {
            if (wasFocus && res.minutes_logged) {
                window.toast(`Foco guardado · ${res.minutes_logged} min · toca pausa`, 'success');
            } else if (!wasFocus) {
                window.toast('Vuelta al foco', 'info');
            }
        }
    }

    // ▶ en las cards del Kanban.
    document.querySelectorAll('[data-timer-start]').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await postJson('/timer/start', { task_id: btn.dataset.taskId });
            window.location.reload();
        });
    });

    // CTA "Empezar siguiente" del dashboard.
    document.querySelectorAll('[data-timer-next-cta]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const taskId = btn.dataset.taskId;
            if (!taskId) return;
            await postJson('/timer/start', { task_id: taskId });
            window.location.reload();
        });
    });
}
