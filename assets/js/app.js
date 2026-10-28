/* =====================================================================
   LemboFix — core UI behaviour (vanilla JS, no dependencies)
   ===================================================================== */
(() => {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const base = document.body.dataset.base || '';
    const csrf = () => $('meta[name="csrf-token"]')?.content || '';
    const url = (path) => `${base}/${path.replace(/^\//, '')}`;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    // ------------------------------------------------------------------
    //  API helper: JSON in/out, CSRF header, friendly errors
    // ------------------------------------------------------------------
    async function api(path, { method = 'GET', body = null, signal } = {}) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        let payload = body;
        if (method !== 'GET') {
            headers['X-CSRF-Token'] = csrf();
            if (body && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                payload = JSON.stringify(body);
            }
        }
        let response;
        try {
            response = await fetch(path.startsWith('/') || path.startsWith('http') ? path : url(path), {
                method, headers, body: method === 'GET' ? null : payload, credentials: 'same-origin', signal
            });
        } catch (err) {
            if (err.name === 'AbortError') throw err;
            return { ok: false, message: 'Network error — please check your connection and try again.' };
        }
        let data;
        try {
            data = await response.json();
        } catch {
            data = { ok: false, message: 'Unexpected server response. Please try again.' };
        }
        if (response.status === 401 && data.login) {
            window.location.href = `${data.login}`;
        }
        if (typeof data.ok === 'undefined') data.ok = response.ok;
        return data;
    }

    // ------------------------------------------------------------------
    //  Toasts
    // ------------------------------------------------------------------
    const toastIcons = {
        success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
        error: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>'
    };
    function dismissToast(el) {
        if (!el || el.classList.contains('is-leaving')) return;
        el.classList.add('is-leaving');
        setTimeout(() => el.remove(), 250);
    }
    function toast(type, message) {
        if (!message) return;
        const stack = $('[data-toasts]');
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.setAttribute('role', 'status');
        el.innerHTML = `<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${toastIcons[type] || toastIcons.info}</svg>
            <span>${escapeHtml(message)}</span>
            <button type="button" class="toast-close" aria-label="Dismiss" data-toast-close>
              <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>`;
        stack.appendChild(el);
        setTimeout(() => dismissToast(el), type === 'error' ? 7000 : 4500);
    }
    $$('[data-toast]').forEach((el) => setTimeout(() => dismissToast(el), 5500));
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toast-close]');
        if (btn) dismissToast(btn.closest('.toast'));
    });

    // ------------------------------------------------------------------
    //  Sticky header state
    // ------------------------------------------------------------------
    const header = $('#site-header');
    const onScroll = () => header?.classList.toggle('is-scrolled', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // ------------------------------------------------------------------
    //  Mobile navigation drawer
    //  The drawer is position:fixed and a sibling of the header, so it is
    //  always reachable regardless of scroll position or stacking contexts.
    // ------------------------------------------------------------------
    const nav = $('#mobile-nav');
    const navBackdrop = $('.mobile-nav-backdrop');
    const navOpenBtn = $('[data-nav-open]');
    let lastFocus = null;

    function openNav() {
        if (!nav) return;
        lastFocus = document.activeElement;
        navBackdrop.hidden = false;
        nav.removeAttribute('inert');
        nav.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => {
            nav.classList.add('is-open');
            navBackdrop.classList.add('is-visible');
        });
        document.body.classList.add('nav-open');
        navOpenBtn?.setAttribute('aria-expanded', 'true');
        setTimeout(() => $('[data-nav-close]', nav)?.focus({ preventScroll: true }), 50);
    }
    function closeNav() {
        if (!nav || !nav.classList.contains('is-open')) return;
        nav.classList.remove('is-open');
        navBackdrop.classList.remove('is-visible');
        nav.setAttribute('aria-hidden', 'true');
        nav.setAttribute('inert', '');
        document.body.classList.remove('nav-open');
        navOpenBtn?.setAttribute('aria-expanded', 'false');
        setTimeout(() => { navBackdrop.hidden = true; }, 250);
        lastFocus?.focus?.({ preventScroll: true });
    }
    navOpenBtn?.addEventListener('click', openNav);
    $$('[data-nav-close]').forEach((el) => el.addEventListener('click', closeNav));
    nav?.addEventListener('click', (e) => { if (e.target.closest('a')) closeNav(); });
    window.matchMedia('(min-width: 1025px)').addEventListener('change', (mq) => { if (mq.matches) closeNav(); });

    // ------------------------------------------------------------------
    //  Dropdowns
    // ------------------------------------------------------------------
    function closeDropdowns(except) {
        $$('[data-dropdown].is-open').forEach((dd) => {
            if (dd === except) return;
            dd.classList.remove('is-open');
            $('[data-dropdown-toggle]', dd)?.setAttribute('aria-expanded', 'false');
        });
    }
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-dropdown-toggle]');
        if (toggle) {
            const dd = toggle.closest('[data-dropdown]');
            const open = !dd.classList.contains('is-open');
            closeDropdowns(dd);
            dd.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            if (open) dd.dispatchEvent(new CustomEvent('dropdown:open'));
            return;
        }
        if (!e.target.closest('[data-dropdown-menu]')) closeDropdowns();
    });

    // ------------------------------------------------------------------
    //  Notifications
    // ------------------------------------------------------------------
    const notifRoot = $('[data-notifications]');
    const notifIcons = {
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        check: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        x: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        star: '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
        clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
        bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'
    };
    function notifIcon(type) {
        if (type.startsWith('booking_request')) return notifIcons.calendar;
        if (['booking_confirmed', 'booking_completed', 'provider_approved'].includes(type)) return notifIcons.check;
        if (['booking_rejected', 'booking_cancelled'].includes(type)) return notifIcons.x;
        if (type.startsWith('review')) return notifIcons.star;
        if (type === 'booking_reminder') return notifIcons.clock;
        if (type.startsWith('report')) return notifIcons.flag;
        return notifIcons.bell;
    }
    function setUnread(count) {
        $$('[data-notif-count]').forEach((el) => {
            el.textContent = count > 9 ? '9+' : String(count);
            el.classList.toggle('is-hidden', count <= 0);
        });
    }
    async function loadNotifications() {
        const list = $('[data-notif-list]', notifRoot);
        const data = await api('api/notifications.php?action=list');
        if (!data.ok) {
            list.innerHTML = `<div class="notif-empty">${escapeHtml(data.message || 'Could not load notifications.')}</div>`;
            return;
        }
        setUnread(data.unread);
        if (!data.items.length) {
            list.innerHTML = '<div class="notif-empty">You\'re all caught up ✨<br><small>New updates about your bookings will appear here.</small></div>';
            return;
        }
        list.innerHTML = data.items.map((n) => `
            <a role="menuitem" href="${escapeHtml(n.link || url('notifications.php'))}" class="notif-item ${n.is_read ? '' : 'is-unread'}" data-notif-id="${n.id}">
                <span class="notif-ico"><svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${notifIcon(n.type)}</svg></span>
                <span class="notif-text"><strong>${escapeHtml(n.title)}</strong><p>${escapeHtml(n.message)}</p><time>${escapeHtml(n.time_ago)}</time></span>
            </a>`).join('');
    }
    if (notifRoot) {
        notifRoot.addEventListener('dropdown:open', loadNotifications);
        notifRoot.addEventListener('click', async (e) => {
            const item = e.target.closest('[data-notif-id]');
            if (item && item.classList.contains('is-unread')) {
                e.preventDefault();
                await api('api/notifications.php', { method: 'POST', body: { action: 'read', id: Number(item.dataset.notifId) } });
                window.location.href = item.href;
            }
        });
        $('[data-notif-read-all]', notifRoot)?.addEventListener('click', async () => {
            const data = await api('api/notifications.php', { method: 'POST', body: { action: 'read_all' } });
            if (data.ok) {
                setUnread(0);
                $$('.notif-item.is-unread').forEach((el) => el.classList.remove('is-unread'));
            }
        });
        // Light polling for the unread badge while the tab is visible
        setInterval(async () => {
            if (document.visibilityState !== 'visible') return;
            const data = await api('api/notifications.php?action=count');
            if (data.ok) setUnread(data.unread);
        }, 60000);
    }

    // ------------------------------------------------------------------
    //  Modals
    // ------------------------------------------------------------------
    let modalReturnFocus = null;
    function openModal(modal) {
        if (typeof modal === 'string') modal = $(modal);
        if (!modal) return;
        modalReturnFocus = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('modal-open');
        setTimeout(() => $('input:not([type=hidden]), textarea, select, button:not([data-modal-close])', modal)?.focus(), 30);
    }
    function closeModal(modal) {
        modal = modal || $('.modal:not([hidden])');
        if (!modal) return;
        modal.hidden = true;
        if (!$('.modal:not([hidden])')) document.body.classList.remove('modal-open');
        modalReturnFocus?.focus?.({ preventScroll: true });
    }
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            const modal = $(opener.dataset.modalOpen);
            if (!modal) return;
            // Copy data-fill-* attributes into fields of the modal (e.g. booking id for a review)
            Object.entries(opener.dataset).forEach(([key, value]) => {
                if (!key.startsWith('fill')) return;
                const name = key.slice(4).replace(/^./, (c) => c.toLowerCase()).replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`);
                $$(`[name="${name}"]`, modal).forEach((field) => {
                    if (field.type === 'checkbox') field.checked = value === '1';
                    else if (field.type === 'radio') field.checked = field.value === value;
                    else field.value = value;
                });
                $$(`[data-fill-text="${name}"]`, modal).forEach((el) => { el.textContent = value; });
            });
            modal.dispatchEvent(new CustomEvent('modal:open', { detail: opener }));
            openModal(modal);
            return;
        }
        if (e.target.closest('[data-modal-close]')) closeModal(e.target.closest('.modal'));
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if ($('.modal:not([hidden])')) closeModal();
        else if (nav?.classList.contains('is-open')) closeNav();
        else closeDropdowns();
    });

    // Confirmation dialog (returns a Promise<{confirmed, note}>)
    function confirmDialog({ title = 'Are you sure?', text = '', ok = 'Confirm', tone = 'danger', note = false, noteLabel = 'Add a note (optional)', noteRequired = false }) {
        const modal = $('#confirm-modal');
        $('[data-confirm-title]', modal).textContent = title;
        $('[data-confirm-text]', modal).textContent = text;
        const okBtn = $('[data-confirm-ok]', modal);
        okBtn.textContent = ok;
        okBtn.className = `btn btn-${tone === 'brand' ? 'primary' : tone}`;
        $('[data-confirm-icon]', modal).className = `confirm-icon ${tone === 'danger' ? '' : 'tone-' + tone}`;
        const noteWrap = $('[data-confirm-note-wrap]', modal);
        const noteField = $('[data-confirm-note]', modal);
        noteWrap.hidden = !note;
        noteField.value = '';
        noteField.required = noteRequired;
        $('[data-confirm-note-label]', modal).textContent = noteLabel;
        openModal(modal);
        return new Promise((resolve) => {
            const cleanup = (result) => {
                okBtn.removeEventListener('click', onOk);
                modal.removeEventListener('click', onCancel);
                closeModal(modal);
                resolve(result);
            };
            const onOk = () => {
                if (noteRequired && !noteField.value.trim()) { noteField.focus(); return; }
                cleanup({ confirmed: true, note: noteField.value.trim() });
            };
            const onCancel = (e) => { if (e.target.closest('[data-modal-close]')) cleanup({ confirmed: false }); };
            okBtn.addEventListener('click', onOk);
            modal.addEventListener('click', onCancel);
        });
    }

    // ------------------------------------------------------------------
    //  Forms: client-side validation + AJAX submit
    // ------------------------------------------------------------------
    function showFieldError(field, message) {
        const wrap = field.closest('.field') || field.parentElement;
        wrap.classList.toggle('is-invalid', Boolean(message));
        let error = $('.field-error', wrap);
        if (!message) { error?.remove(); return; }
        if (!error) {
            error = document.createElement('p');
            error.className = 'field-error';
            error.setAttribute('role', 'alert');
            wrap.appendChild(error);
        }
        error.textContent = message;
    }
    function validateField(field) {
        if (!field.willValidate || field.type === 'hidden') return true;
        let message = '';
        if (!field.checkValidity()) {
            message = field.validity.valueMissing ? 'This field is required.'
                : field.validity.typeMismatch && field.type === 'email' ? 'Please enter a valid email address.'
                : field.validity.tooShort ? `Please use at least ${field.minLength} characters.`
                : field.validationMessage;
        }
        const match = field.dataset.match && $(`[name="${field.dataset.match}"]`, field.form);
        if (!message && match && field.value !== match.value) message = 'Passwords do not match.';
        showFieldError(field, message);
        return !message;
    }
    function validateForm(form) {
        let valid = true;
        $$('input, select, textarea', form).forEach((field) => {
            if (!validateField(field)) valid = false;
        });
        if (!valid) $('.is-invalid input, .is-invalid select, .is-invalid textarea', form)?.focus();
        return valid;
    }
    document.addEventListener('blur', (e) => {
        const f = e.target;
        if (f.form && f.form.hasAttribute('data-validate') && f.matches('input, select, textarea') && f.value !== '') validateField(f);
    }, true);
    document.addEventListener('input', (e) => {
        const wrap = e.target.closest?.('.field.is-invalid');
        if (wrap) validateField(e.target);
    });

    async function submitAjaxForm(form, extra = {}) {
        const submit = form.querySelector('[type=submit]');
        const data = new FormData(form);
        Object.entries(extra).forEach(([k, v]) => data.set(k, v));
        submit?.classList.add('is-loading');
        const result = await api(form.getAttribute('action') || window.location.pathname, { method: 'POST', body: data });
        submit?.classList.remove('is-loading');

        if (result.errors) {
            Object.entries(result.errors).forEach(([name, msg]) => {
                const field = form.querySelector(`[name="${name}"], [name="${name}[]"]`);
                if (field) showFieldError(field, msg);
            });
        }
        toast(result.ok ? 'success' : 'error', result.message);
        form.dispatchEvent(new CustomEvent('ajax:done', { detail: result }));
        if (!result.ok) return result;

        const modal = form.closest('.modal');
        if (modal && !form.hasAttribute('data-keep-open')) closeModal(modal);
        if (result.redirect) { setTimeout(() => { window.location.href = result.redirect; }, 400); return result; }
        if (form.dataset.onSuccess === 'reload') setTimeout(() => window.location.reload(), 500);
        if (form.dataset.onSuccess === 'reset') form.reset();
        if (form.dataset.onSuccess === 'remove') form.closest('[data-removable]')?.remove();
        return result;
    }

    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (form.hasAttribute('data-validate') && !validateForm(form)) {
            e.preventDefault();
            return;
        }
        // Optional confirmation step, configured with data-confirm-* on the form or submitter
        const submitter = e.submitter;
        const cfgEl = submitter?.dataset.confirm ? submitter : (form.dataset.confirm ? form : null);
        if (cfgEl && !form.dataset.confirmed) {
            e.preventDefault();
            const answer = await confirmDialog({
                title: cfgEl.dataset.confirm,
                text: cfgEl.dataset.confirmText || '',
                ok: cfgEl.dataset.confirmOk || 'Confirm',
                tone: cfgEl.dataset.confirmTone || 'danger',
                note: cfgEl.hasAttribute('data-confirm-note'),
                noteLabel: cfgEl.dataset.confirmNote || undefined,
                noteRequired: cfgEl.hasAttribute('data-confirm-note-required')
            });
            if (!answer.confirmed) return;
            const extra = {};
            if (answer.note) extra.note = answer.note;
            if (submitter?.name) extra[submitter.name] = submitter.value;
            if (form.hasAttribute('data-ajax')) {
                submitAjaxForm(form, extra);
            } else {
                Object.entries(extra).forEach(([k, v]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = k; input.value = v;
                    form.appendChild(input);
                });
                form.dataset.confirmed = '1';
                form.submit();
            }
            return;
        }
        if (form.hasAttribute('data-ajax')) {
            e.preventDefault();
            const extra = submitter?.name ? { [submitter.name]: submitter.value } : {};
            submitAjaxForm(form, extra);
        }
    });

    // ------------------------------------------------------------------
    //  Favorites (heart buttons anywhere on the page)
    // ------------------------------------------------------------------
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-favorite]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (document.body.dataset.role === 'guest') {
            toast('info', 'Sign in as a customer to save favorites.');
            setTimeout(() => { window.location.href = url('login.php'); }, 900);
            return;
        }
        btn.classList.add('is-popping');
        setTimeout(() => btn.classList.remove('is-popping'), 400);
        const data = await api('api/favorites.php', { method: 'POST', body: { provider_id: Number(btn.dataset.favorite) } });
        if (!data.ok) { toast('error', data.message); return; }
        $$(`[data-favorite="${btn.dataset.favorite}"]`).forEach((b) => {
            b.classList.toggle('is-active', data.favorite);
            b.setAttribute('aria-pressed', String(data.favorite));
            const label = $('[data-fav-label]', b);
            if (label) label.textContent = data.favorite ? 'Saved' : 'Save';
        });
        toast('success', data.message);
        if (!data.favorite && btn.hasAttribute('data-remove-on-unfavorite')) {
            const card = btn.closest('[data-removable]');
            card?.remove();
            if (!$('[data-removable]')) window.location.reload();
        }
    });

    // ------------------------------------------------------------------
    //  Small enhancements
    // ------------------------------------------------------------------
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-password-toggle]');
        if (!toggle) return;
        const input = toggle.parentElement.querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.classList.toggle('is-showing', show);
    });

    $$('[data-password-meter]').forEach((meter) => {
        const input = $(`[name="${meter.dataset.passwordMeter}"]`);
        input?.addEventListener('input', () => {
            const v = input.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v) && v.length >= 10) score++;
            meter.dataset.score = v ? String(Math.max(1, score)) : '0';
        });
    });

    $$('[data-image-preview]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const target = $(input.dataset.imagePreview);
            if (!file || !target) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                toast('error', 'Only JPG, PNG or WebP images are allowed.');
                input.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                toast('error', 'Images must be smaller than 2 MB.');
                input.value = '';
                return;
            }
            const img = document.createElement('img');
            img.className = target.className.replace(/avatar-tone-\d/, '');
            img.alt = 'Preview';
            img.src = URL.createObjectURL(file);
            target.replaceWith(img);
            img.id = target.id;
        });
    });

    $$('[data-count]').forEach((field) => {
        const counter = $(field.dataset.count);
        const update = () => { if (counter) counter.textContent = `${field.value.length}/${field.maxLength}`; };
        field.addEventListener('input', update);
        update();
    });

    // Auto-submit filter forms (toolbars on dashboard tables)
    $$('form[data-auto-submit]').forEach((form) => {
        let timer;
        form.addEventListener('change', () => form.requestSubmit());
        $$('input[type=search]', form).forEach((input) => input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.requestSubmit(), 450);
        }));
    });

    // Demo account quick-fill on the login page
    document.addEventListener('click', (e) => {
        const demo = e.target.closest('[data-demo-email]');
        if (!demo) return;
        const form = $('form[data-login]');
        $('[name=email]', form).value = demo.dataset.demoEmail;
        $('[name=password]', form).value = demo.dataset.demoPassword;
        form.requestSubmit();
    });

    // Availability editor: dim a weekday's time inputs when it is switched off
    document.addEventListener('change', (e) => {
        const toggle = e.target.closest('[data-day-toggle]');
        if (toggle) toggle.closest('[data-day-row]')?.classList.toggle('is-off', !toggle.checked);
    });

    // Registration: show provider fields only for the provider role
    const registerForm = $('form[data-register]');
    if (registerForm) {
        const providerFields = $('[data-provider-fields]', registerForm);
        const sync = () => {
            const isProvider = $('[name=role]:checked', registerForm)?.value === 'provider';
            providerFields.hidden = !isProvider;
            providerFields.disabled = !isProvider;
        };
        $$('[name=role]', registerForm).forEach((r) => r.addEventListener('change', sync));
        sync();

        const email = $('[data-email-check]', registerForm);
        email?.addEventListener('change', async () => {
            if (!email.checkValidity()) return;
            const data = await api(`api/auth.php?action=check_email&email=${encodeURIComponent(email.value)}`);
            if (data.ok && !data.available) showFieldError(email, 'An account with this email already exists. Try logging in instead.');
        });
    }

    window.LF = { api, toast, url, escapeHtml, openModal, closeModal, confirm: confirmDialog, submitAjaxForm };
})();
