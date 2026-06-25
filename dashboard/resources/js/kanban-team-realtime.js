// dashboard/resources/js/kanban-team-realtime.js
/**
 * Supabase Realtime para el Kanban del equipo.
 * Se suscribe a cambios en la tabla `tasks` y recarga el board
 * cuando otro cliente modifica algo. No actúa sobre cambios
 * propios (marcados por window.__taskMutationAt).
 */
import { createClient } from '@supabase/supabase-js';

let channel = null;

export function initTeamRealtime() {
    if (window.KANBAN_MODE !== 'team') return;
    if (!window.SUPABASE_URL || !window.SUPABASE_ANON_KEY) return;

    const supabase = createClient(window.SUPABASE_URL, window.SUPABASE_ANON_KEY);

    channel = supabase
        .channel('kanban-team')
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'tasks' },
            (payload) => {
                // Ignorar cambios propios (hechos hace menos de 2 s)
                const ownMutation = window.__taskMutationAt
                    && (Date.now() - window.__taskMutationAt) < 2000;
                if (ownMutation) return;

                // Recarga silenciosa: un reload completo es simple y fiable
                // para sincronizar el estado completo del board.
                window.location.reload();
            }
        )
        .subscribe();
}

export function destroyTeamRealtime() {
    channel?.unsubscribe();
    channel = null;
}
