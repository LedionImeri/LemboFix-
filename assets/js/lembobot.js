/* =====================================================================
   LemboBot chat widget. Talks only to our PHP endpoint (api/chatbot.php);
   the Groq API key stays on the server.
   ===================================================================== */
(() => {
    'use strict';
    const root = document.querySelector('[data-lembobot]');
    if (!root || !window.LF) return;

    const $ = (s) => root.querySelector(s);
    const win = $('.lb-window');
    const launcher = $('.lb-launcher');
    const list = $('[data-lb-messages]');
    const form = $('[data-lb-form]');
    const input = $('[data-lb-text]');
    const sendBtn = $('[data-lb-send]');
    const suggestions = $('[data-lb-suggestions]');
    const storageKey = `lembobot:${root.dataset.user}`;
    const { escapeHtml } = window.LF;
    let busy = false;
    let statusLoaded = false;
    let history = [];

    // ---------------- Persistence (per browser tab session) ----------------
    function load() {
        try { history = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); } catch { history = []; }
        history.forEach((m) => render(m, false));
        if (history.length) suggestions.hidden = true;
    }
    function save() {
        try { sessionStorage.setItem(storageKey, JSON.stringify(history.slice(-30))); } catch { /* storage may be unavailable */ }
    }

    // ---------------- Rendering ----------------
    // Minimal, safe markdown: everything is escaped first, then **bold**, *italic* and lists are added.
    function formatText(text) {
        const lines = escapeHtml(text).split(/\n/);
        let html = '';
        let listType = null;
        const inline = (s) => s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*(?!\s)(.+?)\*/g, '$1<em>$2</em>');
        const closeList = () => { if (listType) { html += `</${listType}>`; listType = null; } };
        for (const raw of lines) {
            const line = raw.trim();
            const bullet = line.match(/^[-•*]\s+(.*)$/);
            const numbered = line.match(/^\d+[.)]\s+(.*)$/);
            if (bullet || numbered) {
                const type = bullet ? 'ul' : 'ol';
                if (listType !== type) { closeList(); html += `<${type}>`; listType = type; }
                html += `<li>${inline((bullet || numbered)[1])}</li>`;
            } else if (line === '') {
                closeList();
            } else {
                closeList();
                html += `<p>${inline(line)}</p>`;
            }
        }
        closeList();
        return html;
    }

    function providerCards(providers) {
        if (!providers?.length) return '';
        const check = '<svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
        return `<div class="lb-cards">${providers.map((p) => `
            <a class="lb-card" href="${escapeHtml(p.url)}">
                <span class="lb-card-avatar">${escapeHtml(p.initials)}</span>
                <span class="lb-card-main">
                    <strong>${escapeHtml(p.name)} ${p.verified ? check : ''}</strong>
                    <span>${escapeHtml(p.category)} · ${escapeHtml(p.city)}</span>
                </span>
                <span class="lb-card-side"><b>from ${escapeHtml(p.price)}</b>${p.reviews ? `★ ${Number(p.rating).toFixed(1)} (${p.reviews})` : 'New'}</span>
            </a>`).join('')}</div>`;
    }

    function linkChips(links) {
        if (!links?.length) return '';
        return `<div class="lb-links">${links.map((l) => `<a class="lb-link" href="${escapeHtml(l.url)}">${escapeHtml(l.label)} →</a>`).join('')}</div>`;
    }

    function render(msg, scroll = true) {
        const el = document.createElement('div');
        el.className = `lb-msg ${msg.role === 'user' ? 'lb-user' : 'lb-bot'}${msg.error ? ' lb-error' : ''}`;
        if (msg.role === 'user') {
            el.innerHTML = `<div class="lb-bubble">${escapeHtml(msg.text).replace(/\n/g, '<br>')}</div>`;
        } else {
            const source = msg.mode ? `<span class="lb-src lb-src-${msg.mode}">${msg.mode === 'ai' ? 'AI' : 'Basic'}</span>` : '';
            el.innerHTML = `<div class="lb-bubble">${formatText(msg.text)}</div>
                ${providerCards(msg.providers)}${linkChips(msg.links)}
                ${msg.notice ? `<div class="lb-meta">${escapeHtml(msg.notice)}</div>` : ''}
                <div class="lb-meta">${source}<span>${escapeHtml(msg.time || '')}</span></div>`;
        }
        list.appendChild(el);
        if (scroll) list.scrollTop = list.scrollHeight;
    }

    function typing(show) {
        let el = list.querySelector('.lb-typing');
        if (show && !el) {
            el = document.createElement('div');
            el.className = 'lb-msg lb-bot lb-typing';
            el.setAttribute('aria-label', 'LemboBot is typing');
            el.innerHTML = '<div class="lb-bubble"><span></span><span></span><span></span></div>';
            list.appendChild(el);
            list.scrollTop = list.scrollHeight;
        } else if (!show && el) {
            el.remove();
        }
    }

    function setStatus(mode) {
        const dot = $('[data-lb-dot]');
        const label = $('[data-lb-status]');
        dot.className = `lb-dot ${mode === 'ai' ? 'is-ai' : mode === 'basic' ? 'is-basic' : ''}`;
        label.textContent = mode === 'ai' ? 'AI assistant · online' : mode === 'basic' ? 'Basic mode · smart search' : 'Offline';
    }

    // ---------------- Open / close ----------------
    async function open() {
        win.hidden = false;
        root.classList.add('is-open');
        launcher.setAttribute('aria-expanded', 'true');
        list.scrollTop = list.scrollHeight;
        setTimeout(() => input.focus(), 50);
        if (!statusLoaded) {
            statusLoaded = true;
            const data = await window.LF.api('api/chatbot.php?action=status');
            setStatus(data.ok ? data.mode : 'offline');
        }
    }
    function close() {
        win.hidden = true;
        root.classList.remove('is-open');
        launcher.setAttribute('aria-expanded', 'false');
    }
    root.addEventListener('click', (e) => {
        if (e.target.closest('[data-lb-toggle]')) (win.hidden ? open() : close());
    });
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-lembobot-open]')) { e.preventDefault(); open(); }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !win.hidden && !document.querySelector('.modal:not([hidden])')) close();
    });

    // ---------------- Sending ----------------
    async function send(text) {
        text = text.trim();
        if (!text || busy) return;
        busy = true;
        sendBtn.disabled = true;
        suggestions.hidden = true;
        const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const userMsg = { role: 'user', text, time: now };
        history.push(userMsg);
        render(userMsg);
        input.value = '';
        autosize();
        typing(true);

        const data = await window.LF.api('api/chatbot.php', { method: 'POST', body: { message: text } });
        typing(false);
        const botMsg = data.ok
            ? { role: 'bot', text: data.reply, providers: data.providers, links: data.links, mode: data.mode, notice: data.notice, time: now }
            : { role: 'bot', text: data.message || 'Sorry, something went wrong. Please try again.', error: true, time: now };
        if (data.ok) setStatus(data.mode === 'ai' ? 'ai' : 'basic');
        history.push(botMsg);
        render(botMsg);
        save();
        busy = false;
        sendBtn.disabled = false;
        input.focus();
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); send(input.value); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); }
    });
    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }
    input.addEventListener('input', autosize);
    suggestions.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-lb-suggest]');
        if (chip) send(chip.textContent);
    });

    $('[data-lb-clear]').addEventListener('click', async () => {
        history = [];
        save();
        list.querySelectorAll('.lb-msg:not([data-lb-welcome])').forEach((el) => el.remove());
        suggestions.hidden = false;
        await window.LF.api('api/chatbot.php', { method: 'POST', body: { action: 'clear' } });
    });

    load();
})();
