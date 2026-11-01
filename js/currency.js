(() => {
    'use strict';
    const CURRENCIES = ['IDR', 'USD', 'EUR', 'GBP', 'CAD', 'JPY'];
    const MAX_AGE = 7 * 24 * 60 * 60 * 1000;
    const STORAGE_KEY = 'mae.display-currency';
    const supported = code => CURRENCIES.includes(code) ? code : 'IDR';

    function validateSnapshot(data, now = Date.now()) {
        if (!data || data.base !== 'IDR' || data.source !== 'Frankfurter' || data.rates?.IDR !== 1 || typeof data.stale !== 'boolean') {
            throw new Error('Invalid exchange-rate response.');
        }
        const fetchedAt = Date.parse(data.fetched_at);
        const expiresAt = Date.parse(data.expires_at);
        if (!Number.isFinite(fetchedAt) || !Number.isFinite(expiresAt) || fetchedAt > now + 300000 || now - fetchedAt > MAX_AGE || expiresAt <= fetchedAt || expiresAt - fetchedAt > 3600000) {
            throw new Error('Invalid exchange-rate timestamp.');
        }
        const rates = { IDR: 1 }, dates = {};
        for (const code of CURRENCIES.slice(1)) {
            const rate = data.rates?.[code], date = data.dates?.[code];
            const timestamp = typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date) ? Date.parse(date + 'T00:00:00Z') : NaN;
            if (typeof rate !== 'number' || !Number.isFinite(rate) || rate <= 0 || !Number.isFinite(timestamp) || new Date(timestamp).toISOString().slice(0, 10) !== date || timestamp > now + 300000 || now - timestamp > MAX_AGE) {
                throw new Error('Incomplete or expired exchange rates.');
            }
            rates[code] = rate;
            dates[code] = date;
        }
        return { rates, dates, fetchedAt, expiresAt, stale: data.stale };
    }

    function formatPrice(idr, currency, snapshot) {
        if (!CURRENCIES.includes(currency) || !Number.isSafeInteger(idr) || idr < 0) throw new Error('Invalid price.');
        if (currency === 'IDR') return 'Rp. ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(idr);
        const amount = idr * snapshot?.rates?.[currency];
        if (!Number.isFinite(amount) || amount > Number.MAX_SAFE_INTEGER) throw new Error('Rate unavailable.');
        const digits = currency === 'JPY' ? 0 : 2;
        return '≈ ' + new Intl.NumberFormat('en-US', {
            style: 'currency', currency, currencyDisplay: 'code', minimumFractionDigits: digits, maximumFractionDigits: digits,
        }).format(amount);
    }

    function createModel({ loadRates, onChange = () => {}, clock = Date.now }) {
        let currency = 'IDR', snapshot = null, pending = null, offline = false;
        function usable() {
            const now = clock();
            return snapshot && now - snapshot.fetchedAt <= MAX_AGE && Object.values(snapshot.dates).every(date => now - Date.parse(date + 'T00:00:00Z') <= MAX_AGE);
        }
        function state() {
            const available = usable();
            return {
                currency, displayCurrency: currency === 'IDR' || !available ? 'IDR' : currency,
                snapshot: available ? snapshot : null, loading: pending !== null, offline,
                stale: Boolean(available && (offline || snapshot.stale || clock() >= snapshot.expiresAt)),
            };
        }
        const publish = () => onChange(state());
        function load(force = false) {
            if (currency === 'IDR' || (!force && usable() && !snapshot.stale && clock() < snapshot.expiresAt)) return Promise.resolve(state());
            if (pending) return pending;
            pending = Promise.resolve().then(loadRates).then(data => {
                snapshot = validateSnapshot(data, clock());
                offline = snapshot.stale;
            }).catch(() => { offline = true; }).finally(() => { pending = null; publish(); });
            publish();
            return pending;
        }
        return {
            select(code) { currency = supported(code); publish(); return load(); },
            refresh() { return load(true); },
            state,
        };
    }

    function attach(document, window) {
        const select = document.querySelector('[data-currency-select]');
        const status = document.querySelector('[data-currency-status]');
        const endpoint = document.querySelector('meta[name="mae-exchange-rates"]')?.content;
        if (!select || !status || !endpoint) return;
        const source = document.querySelector('[data-currency-source]');
        const prices = Array.from(document.querySelectorAll('[data-money-idr]'), element => ({
            element, idr: Number(element.dataset.moneyIdr), original: element.textContent,
        }));
        function render(state) {
            let display = state.displayCurrency;
            let rendered;
            try {
                rendered = prices.map(price => display === 'IDR' ? price.original : formatPrice(price.idr, display, state.snapshot));
            } catch (_) {
                display = 'IDR';
                rendered = prices.map(price => price.original);
            }
            prices.forEach((price, index) => {
                price.element.textContent = rendered[index];
                price.element.dataset.displayCurrency = display;
                if (display === 'IDR') price.element.removeAttribute('title');
                else price.element.title = 'Original price: ' + price.original + ' · Reference rate: ' + state.snapshot.dates[display];
            });
            document.documentElement.dataset.displayCurrency = display;
            if (source) source.hidden = display === 'IDR';
            if (state.currency === 'IDR') status.textContent = 'Prices shown in IDR.';
            else if (display === 'IDR') status.textContent = state.loading
                ? 'Loading ' + state.currency + ' rates… Prices remain in IDR.'
                : state.currency + ' rates unavailable. Prices shown in IDR.';
            else {
                const date = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(state.snapshot.dates[display] + 'T00:00:00Z'));
                status.textContent = (state.stale ? 'Saved reference rate' : 'Reference rate') + ' · ' + date + '. Estimates only; orders use IDR.';
            }
        }
        const model = createModel({
            onChange: render,
            loadRates: async () => {
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 7000);
                try {
                    const response = await window.fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'omit', cache: 'no-store', redirect: 'error', signal: controller.signal });
                    if (!response.ok) throw new Error('Exchange rates unavailable.');
                    return await response.json();
                } finally { window.clearTimeout(timeout); }
            },
        });
        function choose(code, save = false) {
            select.value = supported(code);
            if (save) { try { window.localStorage.setItem(STORAGE_KEY, select.value); } catch (_) { /* Selection still works when storage is disabled. */ } }
            model.select(select.value);
        }
        let remembered = 'IDR';
        try { remembered = supported(window.localStorage.getItem(STORAGE_KEY)); } catch (_) { /* Private browsing may disable storage. */ }
        select.disabled = false;
        select.addEventListener('change', () => choose(select.value, true));
        window.addEventListener('storage', event => { if (event.key === STORAGE_KEY) choose(event.newValue); });
        const refresh = () => { if (document.visibilityState === 'visible') model.refresh(); };
        document.addEventListener('visibilitychange', refresh);
        window.setInterval(refresh, 15 * 60 * 1000);
        choose(remembered);
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { CURRENCIES, validateSnapshot, formatPrice, createModel };
    if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', () => attach(document, window));
})();
