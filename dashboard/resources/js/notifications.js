import Toastify from 'toastify-js';
import { getSupabaseClient } from './supabase-client.js';

let notifCount  = 0;
let notifItems  = [];  // cached list

// ── Helpers ─────────────────────────────────────────────

function notifText(n) {
    const actor = n.actor?.name ?? n.payload?.actor_name ?? 'Alguien';
    const title = n.task_title ?? n.payload?.task_title ?? '(tarea)';
    if (n.type === 'mention')       return `${actor} te mencionó en <em>${title}</em>`;
    if (n.type === 'assignment')    return `${actor} te asignó la tarea <em>${title}</em>`;
    if (n.type === 'status_change') {
        const to = n.payload?.new_status ?? '';
        return `${actor} movió <em>${title}</em> a ${to}`;
    }
    return `Nueva notificación en <em>${title}</em>`;
}

function avatarHtml(actor, size = '1.75rem') {
    if (!actor) return `<span class="notif-avatar" style="background:#94a3b8;width:${size};height:${size}">?</span>`;
    return `<span class="notif-avatar" style="background:${actor.color};width:${size};height:${size}">${actor.initials}</span>`;
}

function relativeTime(iso) {
    if (!iso) return '';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60)   return 'ahora';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
}

function buildItemHtml(n) {
    return `
        <li class="notif-item" data-notif-id="${n.id}" data-task-id="${n.task_id}">
            ${avatarHtml(n.actor)}
            <span class="notif-text">${notifText(n)}</span>
            <span class="notif-time">${relativeTime(n.created_at)}</span>
        </li>`;
}

// ── Badge ────────────────────────────────────────────────

function updateBadge(count) {
    notifCount = count;
    const badge  = document.getElementById('notif-badge');
    const dot    = document.getElementById('notif-dot');
    const bell   = document.getElementById('notif-bell-collapsed');

    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }
    if (dot)  dot.classList.toggle('hidden', count === 0);

    const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
    if (bell) bell.classList.toggle('hidden', count === 0 || !isCollapsed);
}

// ── List rendering ───────────────────────────────────────

function renderList(items, listEl) {
    if (!listEl) return;
    if (items.length === 0) {
        listEl.innerHTML = '<li class="px-3 py-4 text-sm text-muted text-center" data-empty>Sin notificaciones pendientes</li>';
        return;
    }
    listEl.innerHTML = items.map(buildItemHtml).join('');
    listEl.querySelectorAll('.notif-item').forEach(el => {
        el.addEventListener('click', () => handleRead(el));
    });
}

function refreshLists() {
    renderList(notifItems, document.getElementById('notif-list'));
    renderList(notifItems, document.getElementById('notif-bubble-list'));
}

// ── Read actions ─────────────────────────────────────────

async function handleRead(itemEl) {
    const id     = itemEl.dataset.notifId;
    const taskId = itemEl.dataset.taskId;

    await fetch(`/team/notifications/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });

    notifItems = notifItems.filter(n => String(n.id) !== String(id));
    updateBadge(notifItems.length);
    refreshLists();

    if (taskId) {
        window.location.href = `/team/tasks#task-${taskId}`;
    }
}

async function handleReadAll() {
    await fetch('/team/notifications', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    notifItems = [];
    updateBadge(0);
    refreshLists();
    closeBubble();
    closePanel();
}

// ── Panel (expanded sidebar, inline) ─────────────────────

function closePanel() {
    document.getElementById('notif-panel')?.classList.add('hidden');
}

function togglePanel() {
    const panel = document.getElementById('notif-panel');
    if (!panel) return;
    const opening = panel.classList.toggle('hidden');
    if (!opening) refreshLists();
}

// ── Speech bubble (collapsed sidebar) ────────────────────

function closeBubble() {
    const bubble = document.getElementById('notif-bubble');
    if (bubble) {
        bubble.classList.remove('notif-bubble--visible');
        bubble.classList.add('hidden');
    }
}

function openBubble() {
    const bell   = document.getElementById('notif-bell-collapsed');
    const bubble = document.getElementById('notif-bubble');
    if (!bell || !bubble) return;

    const rect = bell.getBoundingClientRect();
    bubble.style.top  = `${rect.top}px`;
    bubble.style.left = `${rect.right + 8}px`;

    refreshLists();
    bubble.classList.remove('hidden');
    // Trigger animation on next frame
    requestAnimationFrame(() => bubble.classList.add('notif-bubble--visible'));
}

function toggleBubble() {
    const bubble = document.getElementById('notif-bubble');
    if (!bubble) return;
    bubble.classList.contains('hidden') ? openBubble() : closeBubble();
}

// ── Desktop notifications (Web Notifications API) ────────

function requestDesktopPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function showDesktopNotification(n) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    if (document.visibilityState === 'visible') return; // only when page not focused
    const body = notifText(n).replace(/<\/?em>/g, '"').replace(/<[^>]*>/g, '');
    const notif = new Notification('trackActivity', { body, icon: '/favicon.svg' });
    notif.onclick = () => {
        window.focus();
        window.location.href = `/team/tasks#task-${n.task_id}`;
        notif.close();
    };
}

// ── Init ─────────────────────────────────────────────────

export function initNotifications() {
    // UI handlers always — bell must open panel regardless of session/Supabase
    document.getElementById('notif-bell-expanded')?.addEventListener('click', togglePanel);
    document.getElementById('notif-bell-collapsed')?.addEventListener('click', toggleBubble);
    document.getElementById('notif-read-all')?.addEventListener('click', handleReadAll);
    document.getElementById('notif-bubble-read-all')?.addEventListener('click', handleReadAll);

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#notif-bubble') && !e.target.closest('#notif-bell-collapsed')) {
            closeBubble();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeBubble(); }
    });

    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
        const bell        = document.getElementById('notif-bell-collapsed');
        if (bell) bell.classList.toggle('hidden', notifCount === 0 || !isCollapsed);
        closeBubble();
        closePanel();
    });

    // Fetch + realtime require member identity and Supabase
    if (!window.MY_MEMBER_ID || !window.SUPABASE_URL) return;

    requestDesktopPermission();

    // Load initial notifications
    fetch('/team/notifications')
        .then(r => r.ok ? r.json() : [])
        .then(items => {
            notifItems = items;
            updateBadge(items.length);
        })
        .catch(() => {});

    // Realtime subscription
    const supabase = getSupabaseClient();
    if (supabase) {
        supabase
            .channel(`notifications-${window.MY_MEMBER_ID}`)
            .on('postgres_changes', {
                event:  'INSERT',
                schema: 'public',
                table:  'notifications',
                filter: `recipient_id=eq.${window.MY_MEMBER_ID}`,
            }, (payload) => {
                const n = payload.new;
                notifItems.unshift(n);
                updateBadge(notifItems.length);
                refreshLists();
                const text = notifText(n).replace(/<\/?em>/g, '"');
                Toastify({
                    text,
                    duration:  5000,
                    gravity:   'bottom',
                    position:  'center',
                    className: 'toast',
                    onClick:   () => { window.location.href = `/team/tasks#task-${n.task_id}`; },
                }).showToast();
                showDesktopNotification(n);
            })
            .subscribe();
    }
}
