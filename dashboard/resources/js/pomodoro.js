/**
 * Pomodoro · timer independiente, 100% client-side.
 *
 * Estado completo en localStorage bajo `pomodoro.state`, sincronizado
 * entre pestañas con el evento `storage`. No hay backend para el timer:
 * sólo /settings/pomodoro decide la duración de las fases.
 *
 * Fases:
 *   idle              → nada corriendo, display = duración del foco
 *   focus / short / long
 *                     → corriendo
 *   awaiting_break    → acaba el foco; espera click "Empezar pausa"
 *   awaiting_focus    → acaba la pausa; espera click "Empezar foco"
 *
 * El módulo:
 *   - tickea cada segundo cuando hay fase corriendo
 *   - pinta dos UIs: la página /pomodoro (`#pomodoro-app`) y el dock
 *     flotante global (`#pomodoro-dock`)
 *   - flashea el <title> y dispara una notification cuando una fase
 *     llega a 0 (siempre que el permiso esté concedido)
 *   - persiste/lee estado entre recargas y entre pestañas
 *
 * Diseño manual: las fases NO se encadenan solas. El usuario decide
 * cuándo arrancar la pausa o el siguiente foco.
 */

const LS_KEY  = 'pomodoro.state';
const TICK_MS = 1000;

// Fase canónica.
const PHASE = {
    IDLE:            'idle',
    FOCUS:           'focus',
    SHORT:           'short',
    LONG:            'long',
    AWAITING_BREAK:  'awaiting_break',
    AWAITING_FOCUS:  'awaiting_focus',
};

const RUNNING  = new Set([PHASE.FOCUS, PHASE.SHORT, PHASE.LONG]);
const AWAITING = new Set([PHASE.AWAITING_BREAK, PHASE.AWAITING_FOCUS]);

/* ── Estado en localStorage ─────────────────────────────────────────── */

function defaultState() {
    return {
        phase:        PHASE.IDLE,
        startedAt:    null,  // ms epoch cuando arrancó la fase actual
        pausedAt:     null,  // ms epoch cuando se pausó (null si corre)
        pausedOffset: 0,     // ms acumulados en pausas previas de esta fase
        cycle:        0,     // focos completados desde el último long break
    };
}

function readState() {
    try {
        const raw = localStorage.getItem(LS_KEY);
        if (! raw) return defaultState();
        const s = JSON.parse(raw);
        return { ...defaultState(), ...s };
    } catch {
        return defaultState();
    }
}

function writeState(s) {
    localStorage.setItem(LS_KEY, JSON.stringify(s));
}

/* ── Cálculo de tiempo restante ─────────────────────────────────────── */

function phaseDurationMs(phase, config) {
    switch (phase) {
        case PHASE.FOCUS: return config.focusMin       * 60_000;
        case PHASE.SHORT: return config.shortBreakMin  * 60_000;
        case PHASE.LONG:  return config.longBreakMin   * 60_000;
        default:          return 0;
    }
}

function remainingMs(state, config) {
    if (! RUNNING.has(state.phase) || state.startedAt == null) return 0;
    const now      = Date.now();
    const pauseAdd = state.pausedAt ? (now - state.pausedAt) : 0;
    const elapsed  = now - state.startedAt - state.pausedOffset - pauseAdd;
    const total    = phaseDurationMs(state.phase, config);
    return Math.max(0, total - elapsed);
}

/* ── Format helpers ─────────────────────────────────────────────────── */

const pad = (n) => String(n).padStart(2, '0');

function formatMmSs(ms) {
    const total = Math.ceil(ms / 1000);
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${pad(m)}:${pad(s)}`;
}

const PHASE_LABEL = {
    [PHASE.IDLE]:            'Listo',
    [PHASE.FOCUS]:           'Foco',
    [PHASE.SHORT]:           'Pausa corta',
    [PHASE.LONG]:            'Pausa larga',
    [PHASE.AWAITING_BREAK]:  '¡Foco completado!',
    [PHASE.AWAITING_FOCUS]:  '¡Pausa terminada!',
};

/* ── State transitions ──────────────────────────────────────────────── */

function configFromRoot(root) {
    return {
        focusMin:        Number(root.dataset.focusMin)        || 25,
        shortBreakMin:   Number(root.dataset.shortBreakMin)   || 5,
        longBreakMin:    Number(root.dataset.longBreakMin)    || 15,
        cyclesUntilLong: Number(root.dataset.cyclesUntilLong) || 4,
    };
}

function configFromDock() {
    // Si entras a una página sin #pomodoro-app, el dock lleva la config
    // como data-attrs para que pueda renderizar sin la página principal.
    const dock = document.getElementById('pomodoro-dock');
    if (! dock) return { focusMin: 25, shortBreakMin: 5, longBreakMin: 15, cyclesUntilLong: 4 };
    return configFromRoot(dock);
}

function nextBreakPhase(state, config) {
    // Tras completar (cycle+1) focos se gana pausa larga; las anteriores son cortas.
    return ((state.cycle + 1) % config.cyclesUntilLong === 0) ? PHASE.LONG : PHASE.SHORT;
}

function startPhase(phase) {
    const s = readState();
    s.phase        = phase;
    s.startedAt    = Date.now();
    s.pausedAt     = null;
    s.pausedOffset = 0;
    writeState(s);
}

function pause() {
    const s = readState();
    if (! RUNNING.has(s.phase) || s.pausedAt) return;
    s.pausedAt = Date.now();
    writeState(s);
}

function resume() {
    const s = readState();
    if (! s.pausedAt) return;
    s.pausedOffset += Date.now() - s.pausedAt;
    s.pausedAt = null;
    writeState(s);
}

function completePhaseTransition(prevPhase) {
    // Se llamó al consumir el tiempo restante: pasamos a estado de espera.
    const s = readState();
    if (prevPhase === PHASE.FOCUS) {
        s.cycle += 1;
        s.phase  = PHASE.AWAITING_BREAK;
    } else {
        s.phase  = PHASE.AWAITING_FOCUS;
    }
    s.startedAt    = null;
    s.pausedAt     = null;
    s.pausedOffset = 0;
    writeState(s);
    onPhaseEnd(prevPhase);
}

function skip() {
    const s = readState();
    if (s.phase === PHASE.IDLE) return;

    if (s.phase === PHASE.FOCUS) {
        // Saltamos en pleno foco: cuenta como ciclo y pasa a awaiting_break.
        s.cycle    += 1;
        s.phase     = PHASE.AWAITING_BREAK;
    } else if (s.phase === PHASE.SHORT || s.phase === PHASE.LONG) {
        s.phase     = PHASE.AWAITING_FOCUS;
    } else if (s.phase === PHASE.AWAITING_BREAK) {
        s.phase     = PHASE.AWAITING_FOCUS;
    } else if (s.phase === PHASE.AWAITING_FOCUS) {
        s.phase     = PHASE.AWAITING_BREAK;
    }
    s.startedAt    = null;
    s.pausedAt     = null;
    s.pausedOffset = 0;
    writeState(s);
}

function reset() {
    writeState(defaultState());
}

/* ── Fin de fase: notificación + flash de título ────────────────────── */

let originalTitle = null;
let flashHandle   = null;

function flashTitle(message) {
    if (originalTitle == null) originalTitle = document.title;
    let toggle = false;
    clearInterval(flashHandle);
    flashHandle = setInterval(() => {
        document.title = toggle ? originalTitle : `🍅 ${message}`;
        toggle = ! toggle;
    }, 1000);
    // Al primer click/focus/visibility devolvemos el título.
    const stop = () => {
        clearInterval(flashHandle);
        flashHandle = null;
        document.title = originalTitle ?? document.title;
        originalTitle = null;
        window.removeEventListener('focus', stop);
        document.removeEventListener('visibilitychange', stop);
        document.removeEventListener('click', stop, true);
    };
    window.addEventListener('focus', stop);
    document.addEventListener('visibilitychange', stop);
    document.addEventListener('click', stop, true);
}

function notify(title, body) {
    if (! ('Notification' in window)) return;
    if (Notification.permission === 'granted') {
        try { new Notification(title, { body, silent: true }); } catch {}
    }
}

function requestNotificationPermissionOnce() {
    if (! ('Notification' in window)) return;
    if (Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }
}

function onPhaseEnd(prevPhase) {
    const wasFocus = prevPhase === PHASE.FOCUS;
    const title    = wasFocus ? 'Foco completado' : 'Pausa terminada';
    const body     = wasFocus ? 'Toca "Empezar pausa" cuando quieras.'
                              : 'Cuando estés listo, arranca el siguiente foco.';
    flashTitle(title);
    notify(title, body);
}

/* ── Render ─────────────────────────────────────────────────────────── */

function buildDisplay(state, config) {
    if (RUNNING.has(state.phase)) return formatMmSs(remainingMs(state, config));
    if (AWAITING.has(state.phase)) return '00:00';
    // idle: mostramos la duración del próximo foco.
    return formatMmSs(phaseDurationMs(PHASE.FOCUS, config));
}

function primaryButtonLabel(state) {
    if (state.phase === PHASE.IDLE)           return 'Empezar foco';
    if (state.phase === PHASE.AWAITING_BREAK) return 'Empezar pausa';
    if (state.phase === PHASE.AWAITING_FOCUS) return 'Empezar foco';
    if (state.pausedAt)                       return 'Reanudar';
    return 'Pausar';
}

function renderMain(state, config) {
    const root = document.getElementById('pomodoro-app');
    if (! root) return;

    root.dataset.phase  = state.phase;
    root.dataset.paused = state.pausedAt ? '1' : '0';

    const phaseEl = root.querySelector('[data-pomodoro-phase-label]');
    if (phaseEl) phaseEl.textContent = (state.pausedAt && RUNNING.has(state.phase))
        ? `${PHASE_LABEL[state.phase]} · pausado`
        : PHASE_LABEL[state.phase];

    const disp = root.querySelector('[data-pomodoro-display]');
    if (disp) disp.textContent = buildDisplay(state, config);

    const cycEl = root.querySelector('[data-pomodoro-cycle-count]');
    if (cycEl) cycEl.textContent = String(state.cycle);

    const primary = root.querySelector('[data-pomodoro-action="primary"]');
    if (primary) primary.textContent = primaryButtonLabel(state);
}

function renderDock(state, config) {
    const dock = document.getElementById('pomodoro-dock');
    if (! dock) return;

    // Visible solo si hay algo que enseñar (cualquier cosa salvo idle).
    const visible = state.phase !== PHASE.IDLE;
    dock.classList.toggle('pomodoro-dock--visible', visible);
    if (! visible) return;

    dock.dataset.phase  = state.phase;
    dock.dataset.paused = state.pausedAt ? '1' : '0';

    const phaseEl = dock.querySelector('[data-pomodoro-dock-phase]');
    if (phaseEl) phaseEl.textContent = PHASE_LABEL[state.phase];

    const tEl = dock.querySelector('[data-pomodoro-dock-time]');
    if (tEl) tEl.textContent = buildDisplay(state, config);

    const pauseBtn = dock.querySelector('[data-pomodoro-dock-pause]');
    if (pauseBtn) {
        const isRunning = RUNNING.has(state.phase) && ! state.pausedAt;
        pauseBtn.textContent = isRunning ? '❚❚' : (state.pausedAt ? '▶' : '·');
        pauseBtn.disabled = ! RUNNING.has(state.phase);
        pauseBtn.title = isRunning ? 'Pausar' : (state.pausedAt ? 'Reanudar' : '');
    }
}

function renderAll() {
    const state  = readState();
    // Si la página tiene la sección principal, su config gana; si no, la del dock.
    const root   = document.getElementById('pomodoro-app');
    const config = root ? configFromRoot(root) : configFromDock();

    // Detectar fin de fase entre ticks.
    if (RUNNING.has(state.phase) && remainingMs(state, config) <= 0) {
        completePhaseTransition(state.phase);
        return renderAll(); // re-render con el nuevo estado
    }

    renderMain(state, config);
    renderDock(state, config);
}

/* ── Wiring ─────────────────────────────────────────────────────────── */

function bindMainActions() {
    const root = document.getElementById('pomodoro-app');
    if (! root) return;

    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-pomodoro-action]');
        if (! btn) return;
        const action = btn.dataset.pomodoroAction;
        const state  = readState();
        const config = configFromRoot(root);

        if (action === 'primary') {
            requestNotificationPermissionOnce();
            if (state.phase === PHASE.IDLE)                startPhase(PHASE.FOCUS);
            else if (state.phase === PHASE.AWAITING_BREAK) startPhase(nextBreakPhase(state, config));
            else if (state.phase === PHASE.AWAITING_FOCUS) startPhase(PHASE.FOCUS);
            else if (RUNNING.has(state.phase) && state.pausedAt) resume();
            else if (RUNNING.has(state.phase))             pause();
        } else if (action === 'skip') {
            skip();
        } else if (action === 'reset') {
            reset();
        }
        renderAll();
    });
}

function bindDockActions() {
    const dock = document.getElementById('pomodoro-dock');
    if (! dock) return;

    dock.querySelector('[data-pomodoro-dock-pause]')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const s = readState();
        if (! RUNNING.has(s.phase)) return;
        if (s.pausedAt) resume(); else pause();
        renderAll();
    });
}

function bindCrossTabSync() {
    window.addEventListener('storage', (e) => {
        if (e.key === LS_KEY) renderAll();
    });
}

/* ── Entry-point ────────────────────────────────────────────────────── */

export function initPomodoro() {
    const hasMain = !! document.getElementById('pomodoro-app');
    const hasDock = !! document.getElementById('pomodoro-dock');
    if (! hasMain && ! hasDock) return;

    bindMainActions();
    bindDockActions();
    bindCrossTabSync();
    renderAll();
    setInterval(renderAll, TICK_MS);
}
