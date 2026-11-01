const test = require('node:test');
const assert = require('node:assert/strict');
const { CURRENCIES, validateSnapshot, formatPrice, createModel } = require('../js/currency.js');

const NOW = Date.parse('2026-01-09T12:00:00Z');
function fixture() {
    return {
        source: 'Frankfurter', base: 'IDR', stale: false,
        fetched_at: '2026-01-09T12:00:00+00:00', expires_at: '2026-01-09T13:00:00+00:00',
        rates: { IDR: 1, USD: 0.00006, EUR: 0.000055, GBP: 0.000048, CAD: 0.000083, JPY: 0.009 },
        dates: { USD: '2026-01-09', EUR: '2026-01-09', GBP: '2026-01-09', CAD: '2026-01-09', JPY: '2026-01-09' },
    };
}
const normalize = text => text.replace(/\s+/g, ' ');
test('supports exactly six currencies and preserves native IDR formatting', () => {
    assert.deepEqual(CURRENCIES, ['IDR', 'USD', 'EUR', 'GBP', 'CAD', 'JPY']);
    assert.equal(formatPrice(120000, 'IDR'), 'Rp. 120.000');
});
test('converts the original IDR amount into all five target currencies', () => {
    const snapshot = validateSnapshot(fixture(), NOW);
    for (const [code, value] of [['USD', '7.20'], ['EUR', '6.60'], ['GBP', '5.76'], ['CAD', '9.96'], ['JPY', '1,080']]) {
        assert.equal(normalize(formatPrice(120000, code, snapshot)), `≈ ${code} ${value}`);
    }
});
test('repeated selections and round trips do not compound conversions', () => {
    const snapshot = validateSnapshot(fixture(), NOW);
    const first = formatPrice(120000, 'USD', snapshot);
    formatPrice(120000, 'EUR', snapshot);
    formatPrice(120000, 'JPY', snapshot);
    assert.equal(formatPrice(120000, 'USD', snapshot), first);
    assert.equal(formatPrice(120000, 'IDR', snapshot), 'Rp. 120.000');
});
test('uses whole yen, two decimals for other targets, and handles zero amounts', () => {
    const snapshot = validateSnapshot(fixture(), NOW);
    assert.equal(normalize(formatPrice(123456, 'JPY', snapshot)), '≈ JPY 1,111');
    assert.equal(normalize(formatPrice(0, 'CAD', snapshot)), '≈ CAD 0.00');
    assert.throws(() => formatPrice(-1, 'USD', snapshot));
    assert.throws(() => formatPrice(Number.MAX_SAFE_INTEGER + 1, 'USD', snapshot));
    assert.throws(() => formatPrice(10, 'BTC', snapshot));
});
test('rejects partial, invalid, future, and expired provider data', () => {
    const changes = [
        data => { delete data.rates.CAD; }, data => { data.rates.USD = 0; },
        data => { data.rates.JPY = Infinity; }, data => { data.rates.EUR = '0.000055'; },
        data => { data.base = 'USD'; }, data => { data.dates.GBP = '2026-02-30'; },
        data => { data.dates.USD = '2026-01-10'; }, data => { data.fetched_at = 'invalid'; },
        data => { data.expires_at = '2026-01-10T12:00:00Z'; },
    ];
    for (const change of changes) { const data = fixture(); change(data); assert.throws(() => validateSnapshot(data, NOW)); }
    assert.throws(() => validateSnapshot(fixture(), NOW + 8 * 86400000));
});
test('changing to IDR during a request cannot be overwritten by its late response', async () => {
    let resolve, requests = 0;
    const model = createModel({ clock: () => NOW, loadRates: () => { requests++; return new Promise(r => { resolve = r; }); } });
    const pending = model.select('USD');
    await Promise.resolve();
    assert.equal(model.state().displayCurrency, 'IDR');
    await model.select('IDR');
    resolve(fixture()); await pending;
    assert.equal(model.state().currency, 'IDR');
    assert.equal(model.state().displayCurrency, 'IDR');
    assert.equal(requests, 1);
});
test('rapid foreign-currency selections share a request and honor the latest choice', async () => {
    let resolve, requests = 0;
    const model = createModel({ clock: () => NOW, loadRates: () => { requests++; return new Promise(r => { resolve = r; }); } });
    const pending = model.select('USD');
    const second = model.select('CAD');
    await Promise.resolve(); resolve(fixture()); await Promise.all([pending, second]);
    assert.equal(model.state().displayCurrency, 'CAD');
    await model.select('EUR');
    assert.equal(requests, 1);
});
test('cold-start failures retain IDR rather than mislabeling base prices', async () => {
    const model = createModel({ clock: () => NOW, loadRates: async () => { throw new Error('offline'); } });
    await model.select('USD');
    assert.equal(model.state().currency, 'USD');
    assert.equal(model.state().displayCurrency, 'IDR');
    assert.equal(model.state().offline, true);
});
test('outages retain recent saved rates with a stale indication and expire them after a week', async () => {
    let now = NOW, fail = false;
    const model = createModel({ clock: () => now, loadRates: async () => { if (fail) throw new Error('offline'); return fixture(); } });
    await model.select('CAD');
    fail = true; now += 7200000; await model.refresh();
    assert.equal(model.state().displayCurrency, 'CAD');
    assert.equal(model.state().stale, true);
    now += 8 * 86400000; await model.refresh();
    assert.equal(model.state().displayCurrency, 'IDR');
});
test('invalid remembered selections fall back to IDR without a network request', async () => {
    let requests = 0;
    const model = createModel({ clock: () => NOW, loadRates: async () => { requests++; return fixture(); } });
    await model.select('BTC');
    assert.equal(model.state().displayCurrency, 'IDR');
    assert.equal(requests, 0);
});
