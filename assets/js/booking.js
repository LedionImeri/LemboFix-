/* Booking wizard: services → calendar & slots → details → demo payment. */
(() => {
    'use strict';
    const form = document.getElementById('booking-form');
    if (!form) return;

    const $ = (s, r = form) => r.querySelector(s);
    const $$ = (s, r = form) => Array.from(r.querySelectorAll(s));
    const providerId = form.dataset.providerId;
    const feePercent = parseFloat(form.dataset.feePercent) || 0;
    const money = (v) => '€' + (Math.round(v * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
    const pad = (n) => String(n).padStart(2, '0');
    const isoDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    const state = { step: 1, date: '', time: '', month: null, firstDay: null, lastDay: null, durationLoaded: null };
    const dateInput = $('[data-date-input]');
    const timeInput = $('[data-time-input]');

    // ---------------- Services & summary ----------------
    function selectedServices() {
        return $$('input[name="services[]"]:checked').map((i) => ({
            title: i.dataset.title, price: parseFloat(i.dataset.price), duration: parseInt(i.dataset.duration, 10)
        }));
    }
    function duration() {
        return selectedServices().reduce((sum, s) => sum + s.duration, 0);
    }
    function durationText(min) {
        const h = Math.floor(min / 60), m = min % 60;
        return h && m ? `${h} h ${m} min` : h ? `${h} h` : `${m} min`;
    }
    function updateSummary() {
        const services = selectedServices();
        const subtotal = services.reduce((sum, s) => sum + s.price, 0);
        const fee = Math.round(subtotal * feePercent) / 100;
        $('[data-sum-lines]', document).innerHTML = services.length
            ? services.map((s) => `<div class="summary-line"><span>${window.LF.escapeHtml(s.title)}</span><strong>${money(s.price)}</strong></div>`).join('')
            : '<p class="summary-empty">No services selected yet.</p>';
        $('[data-sum-subtotal]', document).textContent = money(subtotal);
        $('[data-sum-fee]', document).textContent = money(fee);
        $('[data-sum-total]', document).textContent = money(subtotal + fee);
        $('[data-total-btn]').textContent = money(subtotal + fee);
        $('[data-duration-label]').textContent = services.length ? `Estimated duration: ${durationText(duration())}` : '';

        const dateEl = $('[data-sum-date]', document);
        const timeEl = $('[data-sum-time]', document);
        dateEl.textContent = state.date
            ? new Date(state.date + 'T12:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })
            : 'No date selected';
        if (state.time) {
            const [h, m] = state.time.split(':').map(Number);
            const end = h * 60 + m + duration();
            timeEl.textContent = `${state.time} – ${pad(Math.floor(end / 60))}:${pad(end % 60)}`;
        } else {
            timeEl.textContent = 'No time selected';
        }
    }
    form.addEventListener('change', (e) => {
        if (e.target.name === 'services[]') {
            // A different duration changes which slots fit, so the chosen time must be picked again
            state.time = '';
            timeInput.value = '';
            if (state.step >= 2 || state.durationLoaded) loadMonth(state.month);
            updateSummary();
        }
        if (e.target.name === 'payment_method') {
            $('[data-demo-card]').hidden = e.target.value !== 'card';
        }
    });

    // ---------------- Steps ----------------
    const stepper = $$('[data-stepper] li', document);
    function showStep(n) {
        state.step = n;
        $$('[data-step]').forEach((s) => s.classList.toggle('is-active', Number(s.dataset.step) === n));
        stepper.forEach((li, i) => {
            li.classList.toggle('is-active', i + 1 === n);
            li.classList.toggle('is-done', i + 1 < n);
        });
        if (n === 2 && state.durationLoaded !== duration()) loadMonth(state.month);
        $('[data-stepper]', document).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function stepError(n, show) {
        const el = $(`[data-step-error="${n}"]`);
        if (el) el.hidden = !show;
    }
    function validateStep(n) {
        if (n === 1) {
            const ok = selectedServices().length > 0;
            stepError(1, !ok);
            return ok;
        }
        if (n === 2) {
            const ok = Boolean(state.date && state.time);
            stepError(2, !ok);
            return ok;
        }
        if (n === 3) {
            let ok = true;
            $$('[data-step="3"] input, [data-step="3"] textarea').forEach((field) => {
                const wrap = field.closest('.field');
                const valid = field.checkValidity();
                wrap.classList.toggle('is-invalid', !valid);
                let err = wrap.querySelector('.field-error');
                if (!valid) {
                    if (!err) { err = document.createElement('p'); err.className = 'field-error'; wrap.appendChild(err); }
                    err.textContent = field.validity.valueMissing ? 'This field is required.' : field.validationMessage;
                    if (ok) field.focus();
                    ok = false;
                } else {
                    err?.remove();
                }
            });
            return ok;
        }
        return true;
    }
    form.addEventListener('click', (e) => {
        if (e.target.closest('[data-next]')) {
            if (validateStep(state.step)) showStep(state.step + 1);
        }
        if (e.target.closest('[data-prev]')) showStep(state.step - 1);
    });
    // Enter inside a field should advance, never submit early
    form.addEventListener('submit', (e) => {
        if (state.step < 4 || ![1, 2, 3].every(validateStep)) {
            e.preventDefault();
            e.stopPropagation();
            if (validateStep(state.step)) showStep(Math.min(4, state.step + 1));
        }
    });

    // ---------------- Calendar ----------------
    const grid = $('[data-cal-grid]');
    const title = $('[data-cal-title]');
    const prevBtn = $('[data-cal-prev]');
    const nextBtn = $('[data-cal-next]');
    const slotsBox = $('[data-slots]');
    const slotsTitle = $('[data-slots-title]');
    const slotsSub = $('[data-slots-sub]');

    async function loadMonth(month) {
        const today = new Date();
        month = month || `${today.getFullYear()}-${pad(today.getMonth() + 1)}`;
        state.month = month;
        const dur = duration();
        if (!dur) return;
        state.durationLoaded = dur;
        grid.innerHTML = Array.from({ length: 35 }, () => '<span class="calendar-day skeleton" style="aspect-ratio:1"></span>').join('');
        const data = await window.LF.api(`api/availability.php?action=month&provider_id=${providerId}&month=${month}&duration=${dur}`);
        if (!data.ok) {
            grid.innerHTML = `<p class="text-sm text-danger" style="grid-column:1/-1">${window.LF.escapeHtml(data.message)}</p>`;
            return;
        }
        state.firstDay = data.first_day;
        state.lastDay = data.last_day;
        renderMonth(month, data.days);
        // Keep the selected date if it is still available, otherwise clear it
        if (state.date && state.date.startsWith(month)) {
            if (data.days[state.date]?.slots) loadSlots(state.date);
            else clearDate();
        }
    }

    function renderMonth(month, days) {
        const [y, m] = month.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        title.textContent = first.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        const offset = (first.getDay() + 6) % 7; // Monday first
        const daysInMonth = new Date(y, m, 0).getDate();
        const todayIso = isoDate(new Date());
        let html = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => `<span class="calendar-dow">${d}</span>`).join('');
        for (let i = 0; i < offset; i++) html += '<span class="calendar-day is-empty"></span>';
        for (let d = 1; d <= daysInMonth; d++) {
            const iso = `${month}-${pad(d)}`;
            const info = days[iso] || { slots: 0 };
            const cls = ['calendar-day'];
            if (info.slots > 0) cls.push('is-available');
            if (info.reason) cls.push('is-off');
            if (iso === todayIso) cls.push('is-today');
            if (iso === state.date) cls.push('is-selected');
            const label = `${new Date(iso + 'T12:00:00').toDateString()}: ${info.slots ? info.slots + ' free slots' : (info.reason || 'unavailable')}`;
            html += info.slots > 0
                ? `<button type="button" class="${cls.join(' ')}" data-day="${iso}" aria-label="${label}" aria-pressed="${iso === state.date}">${d}</button>`
                : `<span class="${cls.join(' ')}" title="${info.reason || 'Unavailable'}" aria-label="${label}">${d}</span>`;
        }
        grid.innerHTML = html;
        prevBtn.disabled = !state.firstDay || month <= state.firstDay.slice(0, 7);
        nextBtn.disabled = !state.lastDay || month >= state.lastDay.slice(0, 7);
        if (!Object.values(days).some((x) => x.slots > 0)) {
            slotsTitle.textContent = 'No free days this month';
            slotsSub.textContent = 'Try the next month or choose fewer services.';
        }
    }
    function shiftMonth(delta) {
        const [y, m] = state.month.split('-').map(Number);
        const d = new Date(y, m - 1 + delta, 1);
        loadMonth(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
    }
    prevBtn.addEventListener('click', () => shiftMonth(-1));
    nextBtn.addEventListener('click', () => shiftMonth(1));

    function clearDate() {
        state.date = ''; state.time = '';
        dateInput.value = ''; timeInput.value = '';
        slotsTitle.textContent = 'Select a date';
        slotsSub.textContent = 'Days with a green dot have free time slots.';
        slotsBox.innerHTML = '<div class="slots-empty">Choose a day on the calendar to see free times.</div>';
        updateSummary();
    }

    grid.addEventListener('click', (e) => {
        const day = e.target.closest('[data-day]');
        if (!day) return;
        $$('.calendar-day.is-selected', grid).forEach((el) => { el.classList.remove('is-selected'); el.setAttribute('aria-pressed', 'false'); });
        day.classList.add('is-selected');
        day.setAttribute('aria-pressed', 'true');
        state.date = day.dataset.day;
        dateInput.value = state.date;
        state.time = '';
        timeInput.value = '';
        stepError(2, false);
        loadSlots(state.date);
        updateSummary();
    });

    async function loadSlots(date) {
        slotsTitle.textContent = new Date(date + 'T12:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
        slotsSub.textContent = 'Loading free times…';
        slotsBox.innerHTML = '<div class="slots-empty"><span class="spinner"></span></div>';
        const data = await window.LF.api(`api/availability.php?action=slots&provider_id=${providerId}&date=${date}&duration=${duration()}`);
        if (!data.ok || !data.slots.length) {
            slotsSub.textContent = '';
            slotsBox.innerHTML = '<div class="slots-empty">No free times left on this day. Please pick another date.</div>';
            return;
        }
        slotsSub.textContent = `${data.slots.length} free start times · ${durationText(duration())}`;
        const groups = { Morning: [], Afternoon: [], Evening: [] };
        data.slots.forEach((t) => {
            const h = parseInt(t, 10);
            groups[h < 12 ? 'Morning' : h < 17 ? 'Afternoon' : 'Evening'].push(t);
        });
        slotsBox.innerHTML = Object.entries(groups).filter(([, list]) => list.length).map(([name, list]) => `
            <div class="slots-group">
                <div class="slots-group-title">${name}</div>
                <div class="slots">${list.map((t) => `<button type="button" class="slot${t === state.time ? ' is-selected' : ''}" data-slot="${t}">${t}</button>`).join('')}</div>
            </div>`).join('');
    }
    slotsBox.addEventListener('click', (e) => {
        const slot = e.target.closest('[data-slot]');
        if (!slot) return;
        $$('.slot.is-selected', slotsBox).forEach((el) => el.classList.remove('is-selected'));
        slot.classList.add('is-selected');
        state.time = slot.dataset.slot;
        timeInput.value = state.time;
        stepError(2, false);
        updateSummary();
    });

    // ---------------- Server response ----------------
    form.addEventListener('ajax:done', (e) => {
        const res = e.detail;
        if (res.ok) return; // app.js follows res.redirect
        if (res.errors?.time || res.slot_taken) {
            showStep(2);
            state.time = '';
            timeInput.value = '';
            loadMonth(state.month);
        } else if (res.errors?.services) {
            showStep(1);
        } else if (res.errors?.address || res.errors?.city) {
            showStep(3);
        }
    });

    updateSummary();
    if (selectedServices().length) loadMonth();
})();
