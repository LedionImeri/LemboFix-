/* Live search: filters update results via AJAX and keep the URL shareable. */
(() => {
    'use strict';
    const form = document.querySelector('[data-search-form]');
    if (!form) return;

    const results = form.querySelector('[data-results]');
    const count = form.querySelector('[data-results-count]');
    const pageInput = form.querySelector('[data-page-input]');
    const filters = document.getElementById('filters');
    let controller = null;
    let debounce = null;

    function params() {
        const data = new FormData(form);
        const query = new URLSearchParams();
        for (const [key, value] of data.entries()) {
            if (value !== '' && !(key === 'page' && value === '1') && !(key === 'sort' && value === 'recommended')) {
                query.set(key, value);
            }
        }
        return query;
    }

    async function run({ resetPage = true, scroll = false } = {}) {
        if (resetPage) pageInput.value = '1';
        const query = params();
        controller?.abort();
        controller = new AbortController();
        results.classList.add('is-loading');
        results.setAttribute('aria-busy', 'true');
        try {
            const data = await window.LF.api(`api/search.php?${query}`, { signal: controller.signal });
            if (!data.ok) throw new Error(data.message);
            results.innerHTML = data.html;
            count.innerHTML = `<strong>${data.total}</strong> ${data.label} found`;
            const qs = query.toString();
            history.replaceState(null, '', `${window.location.pathname}${qs ? '?' + qs : ''}`);
            if (scroll) results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            if (err.name === 'AbortError') return;
            results.innerHTML = `<div class="card"><div class="empty-state"><h3>Something went wrong</h3><p>We couldn't load results. Please try again.</p><button type="button" class="btn btn-secondary" data-retry>Try again</button></div></div>`;
        } finally {
            results.classList.remove('is-loading');
            results.setAttribute('aria-busy', 'false');
        }
    }

    form.addEventListener('change', (e) => {
        if (e.target.name === 'q') return;
        run();
    });
    form.addEventListener('input', (e) => {
        if (!['q', 'min_price', 'max_price'].includes(e.target.name)) return;
        clearTimeout(debounce);
        debounce = setTimeout(() => run(), 350);
    });
    form.addEventListener('submit', (e) => { e.preventDefault(); run(); });

    results.addEventListener('click', (e) => {
        const link = e.target.closest('.pagination a[data-page]');
        if (link) {
            e.preventDefault();
            pageInput.value = link.dataset.page;
            run({ resetPage: false, scroll: true });
        }
        if (e.target.closest('[data-retry]')) run({ resetPage: false });
    });

    form.querySelector('[data-clear-filters]')?.addEventListener('click', (e) => {
        e.preventDefault();
        form.querySelectorAll('input[type=search], input[type=number]').forEach((i) => { i.value = ''; });
        form.querySelectorAll('input[type=radio][value=""]').forEach((i) => { i.checked = true; });
        form.querySelector('[name=verified]').checked = false;
        form.querySelector('[name=city]').value = '';
        form.querySelector('[name=sort]').value = 'recommended';
        run();
    });

    // Off-canvas filters on small screens
    const open = () => { filters.classList.add('is-open'); document.body.classList.add('filters-open', 'nav-open'); };
    const close = () => { filters.classList.remove('is-open'); document.body.classList.remove('filters-open', 'nav-open'); };
    document.querySelectorAll('[data-filters-open]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-filters-close]').forEach((b) => b.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
