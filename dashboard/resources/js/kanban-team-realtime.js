import { getSupabaseClient } from './supabase-client.js';

let channel = null;

export function initTeamRealtime() {
    if (window.KANBAN_MODE !== 'team') return;

    const supabase = getSupabaseClient();
    if (!supabase) return;

    channel = supabase
        .channel('kanban-team')
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'tasks' },
            () => {
                const ownMutation = window.__taskMutationAt
                    && (Date.now() - window.__taskMutationAt) < 2000;
                if (ownMutation) return;
                window.location.reload();
            }
        )
        .subscribe();
}

export function destroyTeamRealtime() {
    channel?.unsubscribe();
    channel = null;
}
