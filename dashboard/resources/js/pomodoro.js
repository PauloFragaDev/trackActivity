/**
 * Pomodoro · pill rica + transiciones + modal de cierre.
 *
 * El estado real vive en BBDD (active_timers). Este módulo:
 *   - Tickea el contador descontando offset de pausa.
 *   - Cuando el contador llega a 0 (fin de fase), llama a /timer/advance:
 *       · si era focus → backend crea manual_entry y abre el modal de cierre.
 *       · si era break → vuelve a focus automáticamente.
 *   - Cablea pause/resume/skip/stop y el botón ▶ de las cards.
 *   - El CTA "Empezar siguiente" del dashboard también pasa por aquí.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

/**
 * Calcula segundos restantes en la fase actual considerando pausas.
 * remaining = phaseDuration - (now - phaseStart) + pausedOffset + (paused ? now - pausedAt : 0)
 */
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
    dot.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-ink-400', 'animate-pulse');
    if (paused) {
        dot.classList.add('bg-ink-400');
        return;
    }
    dot.classList.add('animate-pulse');
    if (pill.dataset.state === 'focus') dot.classList.add('bg-emerald-500');
    else if (pill.dataset.state === 'short_break') dot.classList.add('bg-amber-500');
    else if (pill.dataset.state === 'long_break') dot.classList.add('bg-sky-500');
    else dot.classList.add('bg-emerald-500');
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
    }
    const cycles = pill.querySelector('[data-timer-cycles]');
    if (cycles) cycles.textContent = `#${payload.cycle_count ?? 0}`;

    pill.classList.remove('timer-pill--focus', 'timer-pill--short-break', 'timer-pill--long-break', 'timer-pill--paused');
    pill.classList.add(`timer-pill--${(payload.state || 'focus').replace('_', '-')}`);
    if (payload.paused_at) pill.classList.add('timer-pill--paused');

    const iconPause = pill.querySelector('[data-timer-icon-pause]');
    const iconPlay  = pill.querySelector('[data-timer-icon-play]');
    if (iconPause && iconPlay) {
        iconPause.classList.toggle('hidden', !!payload.paused_at);
        iconPlay.classList.toggle('hidden',  !payload.paused_at);
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

    // Estado local: mood y progress se eligen con botones (radio visual).
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
        const onSkip = () => {
            cleanup();
            dlg.close();
            resolve(null);
        };
        const cleanup = () => {
            form.removeEventListener('submit', onSubmit);
            form.querySelector('[data-focus-skip]').removeEventListener('click', onSkip);
        };
        form.addEventListener('submit', onSubmit);
        form.querySelector('[data-focus-skip]').addEventListener('click', onSkip);
        if (typeof dlg.showModal === 'function') dlg.showModal();
    });
}

export function initPomodoro() {
    const pill = document.getElementById('timer-pill');

    // Tick del pill (incluye cuenta atrás y disparo de advance al llegar a 0).
    let advancing = false;
    if (pill) {
        const elapsed = pill.querySelector('[data-timer-elapsed]');
        const tick = async () => {
            if (!pill.isConnected) return;
            const rem = remainingSeconds(pill);
            const paused = !!pill.dataset.pausedAt;
            elapsed.textContent = formatRemaining(rem);
            elapsed.classList.toggle('text-rose-500', rem < 0 && !paused);
            if (!paused && rem <= 0 && !advancing) {
                advancing = true;
                await advancePhase();
                advancing = false;
            }
        };
        tick();
        setInterval(tick, 1000);

        // Pausa / reanuda.
        pill.querySelector('[data-timer-toggle-pause]')?.addEventListener('click', async () => {
            const isPaused = !!pill.dataset.pausedAt;
            const payload = await postJson(isPaused ? '/timer/resume' : '/timer/pause');
            applyTimerData(pill, payload);
        });

        // Skip a la siguiente fase manualmente.
        pill.querySelector('[data-timer-skip]')?.addEventListener('click', advancePhase);

        // Parar el cronómetro (abre modal si estaba en focus).
        pill.querySelector('[data-timer-stop]')?.addEventListener('click', async () => {
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

    // ▶ en las cards del Kanban: arranca un nuevo focus.
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
