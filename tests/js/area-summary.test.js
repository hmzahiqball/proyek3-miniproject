import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeAreaSummary } from '../../resources/js/area-summary.js';

class Field extends EventTarget {
    value = '';
    textContent = '';
    hidden = true;
    selectedOptions = [];
    attributes = new Map();
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
}

const flush = () => new Promise((resolve) => setImmediate(resolve));
const response = (id, count = 2, name = 'Wilayah Demo') => ({
    ok: true,
    json: async () => ({ data: { area_id: Number(id), area_name: name, active_report_count: count, scope: 'milik_sendiri' } }),
});

function setup(initial = '') {
    const select = new Field();
    const status = new Field();
    const retry = new Field();
    const calls = [];
    const choose = (id) => {
        select.value = id;
        select.selectedOptions = [{ dataset: id ? { summaryUrl: `/wilayah/${id}/ringkasan` } : {} }];
    };
    choose(initial);
    const fields = new Map([
        ['[data-area-summary-select]', select], ['[data-area-summary-status]', status], ['[data-area-summary-retry]', retry],
    ]);
    initializeAreaSummary({ querySelector: (selector) => fields.get(selector) }, (url, options) => new Promise((resolve, reject) => calls.push({ url, options, resolve, reject })));
    return { select, status, retry, calls, change(id) { choose(id); select.dispatchEvent(new Event('change')); } };
}

test('empty choice does not fetch and selected choice loads asynchronously with session and JSON headers', async () => {
    const ui = setup();
    assert.equal(ui.calls.length, 0);
    assert.match(ui.status.textContent, /Pilih wilayah/);
    ui.change('1');
    assert.match(ui.status.textContent, /Memuat/);
    assert.equal(ui.status.attributes.get('aria-busy'), 'true');
    assert.equal(ui.calls[0].url, '/wilayah/1/ringkasan');
    assert.equal(ui.calls[0].options.credentials, 'same-origin');
    assert.equal(ui.calls[0].options.cache, 'no-store');
    assert.equal(ui.calls[0].options.headers.Accept, 'application/json');
    ui.calls[0].resolve(response(1));
    await flush();
    assert.match(ui.status.textContent, /2 laporan aktif milik Anda di Wilayah Demo/);
    assert.equal(ui.status.attributes.has('aria-busy'), false);
    assert.equal(ui.retry.hidden, true);
});

test('existing selection on edit or validation redirect loads without requiring a change', async () => {
    const ui = setup('3');
    assert.equal(ui.calls.length, 1);
    ui.calls[0].resolve(response(3, 0));
    await flush();
    assert.match(ui.status.textContent, /Belum ada laporan aktif milik Anda/);
});

for (const [name, fail] of [
    ['network failure', (call) => call.reject(new Error('offline'))],
    ['HTTP 401', (call) => call.resolve({ ok: false, status: 401 })],
    ['HTTP 404', (call) => call.resolve({ ok: false, status: 404 })],
    ['HTTP 500', (call) => call.resolve({ ok: false, status: 500 })],
    ['HTML login response', (call) => call.resolve({ ok: true, json: async () => { throw new Error('HTML'); } })],
    ['unexpected summary scope', (call) => call.resolve({ ok: true, json: async () => ({ data: { area_id: 1, area_name: 'Wilayah', active_report_count: 10, scope: 'seluruh_laporan' } }) })],
    ['wrong area response', (call) => call.resolve(response(2))],
    ['negative count', (call) => call.resolve(response(1, -1))],
]) {
    test(`${name} shows a retry without blocking the report form`, async () => {
        const ui = setup('1');
        fail(ui.calls[0]);
        await flush();
        assert.match(ui.status.textContent, /gagal dimuat/);
        assert.match(ui.status.textContent, /tetap dapat mengisi dan mengirim laporan/);
        assert.equal(ui.status.attributes.has('aria-busy'), false);
        assert.equal(ui.retry.hidden, false);
        assert.equal(ui.select.value, '1');
        ui.retry.dispatchEvent(new Event('click'));
        assert.equal(ui.calls.length, 2);
        assert.equal(ui.retry.hidden, true);
        ui.calls[1].resolve(response(1));
        await flush();
        assert.match(ui.status.textContent, /2 laporan aktif/);
    });
}

for (const staleFailure of [false, true]) {
    test(`switching area aborts old request and ignores its late ${staleFailure ? 'failure' : 'success'}`, async () => {
        const ui = setup('1');
        ui.change('2');
        assert.equal(ui.calls[0].options.signal.aborted, true);
        ui.calls[1].resolve(response(2, 4, 'Wilayah B'));
        await flush();
        if (staleFailure) ui.calls[0].reject(new Error('Late failure'));
        else ui.calls[0].resolve(response(1, 9, 'Wilayah A'));
        await flush();
        assert.match(ui.status.textContent, /4 laporan aktif milik Anda di Wilayah B/);
        assert.equal(ui.retry.hidden, true);
        assert.equal(ui.status.attributes.has('aria-busy'), false);
    });
}

test('clearing selection cancels pending request and keeps the selection prompt', async () => {
    const ui = setup('1');
    ui.change('');
    assert.equal(ui.calls[0].options.signal.aborted, true);
    ui.calls[0].resolve(response(1));
    await flush();
    assert.match(ui.status.textContent, /Pilih wilayah/);
    assert.equal(ui.retry.hidden, true);
    assert.equal(ui.status.attributes.has('aria-busy'), false);
});

test('server text is assigned as text rather than interpreted as HTML', async () => {
    const ui = setup('1');
    ui.calls[0].resolve(response(1, 1, '<img src=x onerror=alert(1)>'));
    await flush();
    assert.equal(ui.status.textContent, '1 laporan aktif milik Anda di <img src=x onerror=alert(1)>.');
    assert.equal(ui.status.innerHTML, undefined);
});

test('pages without summary controls do not fetch', () => {
    initializeAreaSummary({ querySelector: () => null }, () => { throw new Error('Unexpected fetch'); });
});
