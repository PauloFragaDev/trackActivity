import { createClient } from '@supabase/supabase-js';

let _client = null;

export function getSupabaseClient() {
    if (_client) return _client;
    if (!window.SUPABASE_URL || !window.SUPABASE_ANON_KEY) return null;
    _client = createClient(window.SUPABASE_URL, window.SUPABASE_ANON_KEY);
    return _client;
}
