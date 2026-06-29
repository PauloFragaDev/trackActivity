export function initMentionAutocomplete() {
    if (!window.TEAM_MEMBERS?.length) return;

    document.querySelectorAll('textarea[data-mention]').forEach(attach);

    // Also attach to dynamically added textareas (e.g., task modal opened after init)
    const observer = new MutationObserver(() => {
        document.querySelectorAll('textarea[data-mention]:not([data-mention-attached])').forEach(attach);
    });
    observer.observe(document.body, { childList: true, subtree: true });
}

function attach(textarea) {
    textarea.setAttribute('data-mention-attached', '1');

    let dropdown = null;
    let activeIndex = -1;

    function getQuery() {
        const pos  = textarea.selectionStart;
        const text = textarea.value.slice(0, pos);
        const match = text.match(/@(\w*)$/);
        return match ? match[1] : null;
    }

    function getMatches(query) {
        if (query === null) return [];
        const q = query.toLowerCase();
        return window.TEAM_MEMBERS
            .filter(m => m.name.toLowerCase().includes(q))
            .slice(0, 5);
    }

    function removeDropdown() {
        dropdown?.remove();
        dropdown   = null;
        activeIndex = -1;
    }

    function renderDropdown(members) {
        removeDropdown();
        if (!members.length) return;

        dropdown = document.createElement('ul');
        dropdown.className = [
            'z-[300] bg-[var(--paper)] dark:bg-ink-900',
            'border divider rounded shadow-lg py-1 w-48 text-sm',
        ].join(' ');

        members.forEach((m, i) => {
            const li = document.createElement('li');
            li.className = 'flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-ink-100 dark:hover:bg-ink-800';
            li.innerHTML = `
                <span style="background:${m.color}" class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white">${m.initials}</span>
                <span>${m.name}</span>`;
            li.addEventListener('mousedown', (e) => { e.preventDefault(); selectMember(m); });
            dropdown.appendChild(li);
        });

        // Position dropdown below the cursor
        const rect = textarea.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top  = `${rect.bottom + 4}px`;
        dropdown.style.left = `${rect.left}px`;

        document.body.appendChild(dropdown);
        setActive(0);
    }

    function setActive(index) {
        if (!dropdown) return;
        const items = dropdown.querySelectorAll('li');
        items.forEach((li, i) => li.classList.toggle('bg-ink-100', i === index));
        activeIndex = index;
    }

    function selectMember(member) {
        const pos   = textarea.selectionStart;
        const before = textarea.value.slice(0, pos);
        const after  = textarea.value.slice(pos);
        const replaced = before.replace(/@\w*$/, `@${member.name} `);
        textarea.value = replaced + after;
        textarea.selectionStart = textarea.selectionEnd = replaced.length;
        textarea.dispatchEvent(new Event('input'));
        removeDropdown();
        textarea.focus();
    }

    textarea.addEventListener('input', () => {
        const query   = getQuery();
        const matches = getMatches(query);
        if (matches.length) renderDropdown(matches);
        else removeDropdown();
    });

    textarea.addEventListener('keydown', (e) => {
        if (!dropdown) return;
        const items = dropdown.querySelectorAll('li');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            const members = getMatches(getQuery());
            if (members[activeIndex]) selectMember(members[activeIndex]);
        } else if (e.key === 'Escape') {
            removeDropdown();
        }
    });

    textarea.addEventListener('blur', () => {
        // Small delay to allow mousedown on dropdown to fire first
        setTimeout(removeDropdown, 150);
    });
}
