import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeLocationPicker } from '../../resources/js/location-picker.js';

class Field extends EventTarget {
    value = '';
    textContent = '';
    disabled = false;
    attributes = new Map();
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
}

function setup({ secure = true, supported = true, throws = false } = {}) {
    const button = new Field();
    const status = new Field();
    const latitude = new Field();
    const longitude = new Field();
    latitude.value = '-1';
    longitude.value = '100';
    let success;
    let failure;
    let calls = 0;
    let options;
    const geolocation = supported ? {
        getCurrentPosition(onSuccess, onFailure, settings) {
            calls++;
            if (throws) throw new Error('Geolocation blocked');
            success = onSuccess;
            failure = onFailure;
            options = settings;
        },
    } : undefined;
    const fields = new Map([
        ['[data-use-gps]', button], ['[data-gps-status]', status],
        ['#latitude', latitude], ['#longitude', longitude],
    ]);
    initializeLocationPicker({ querySelector: (selector) => fields.get(selector) }, geolocation ?? null, secure);
    return {
        button, status, latitude, longitude,
        click: () => button.dispatchEvent(new Event('click')),
        success: (coords) => success({ coords }),
        failure: (code) => failure({ code }),
        get calls() { return calls; },
        get options() { return options; },
    };
}

test('location is only requested on click and duplicate clicks are blocked while pending', () => {
    const ui = setup();
    assert.equal(ui.calls, 0);
    ui.click();
    ui.click();
    assert.equal(ui.calls, 1);
    assert.equal(ui.button.disabled, true);
    assert.equal(ui.button.attributes.get('aria-busy'), 'true');
    assert.deepEqual(ui.options, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
});

test('successful GPS fills coordinates to seven decimals, announces accuracy, and remains editable', () => {
    const ui = setup();
    let changes = 0;
    ui.latitude.addEventListener('change', () => changes++);
    ui.longitude.addEventListener('change', () => changes++);
    ui.click();
    ui.success({ latitude: -6.91234567, longitude: 107.61234567, accuracy: 12.4 });
    assert.equal(ui.latitude.value, '-6.9123457');
    assert.equal(ui.longitude.value, '107.6123457');
    assert.match(ui.status.textContent, /±13 meter/);
    assert.match(ui.status.textContent, /lokasi kejadian/);
    assert.equal(ui.button.disabled, false);
    assert.equal(ui.button.attributes.has('aria-busy'), false);
    assert.equal(changes, 2);
    ui.latitude.value = '-7';
    assert.equal(ui.latitude.value, '-7');
});

for (const [code, message] of [[1, /Izin lokasi ditolak/], [2, /belum tersedia/], [3, /terlalu lama/], [99, /gagal diambil/]]) {
    test(`GPS error ${code} preserves manual coordinates and allows retry`, () => {
        const ui = setup();
        ui.click();
        ui.failure(code);
        assert.equal(ui.latitude.value, '-1');
        assert.equal(ui.longitude.value, '100');
        assert.equal(ui.button.disabled, false);
        assert.match(ui.status.textContent, message);
        ui.click();
        assert.equal(ui.calls, 2);
    });
}

for (const options of [{ secure: false }, { supported: false }, { throws: true }]) {
    test(`unavailable browser API fails gracefully: ${JSON.stringify(options)}`, () => {
        const ui = setup(options);
        ui.click();
        assert.equal(ui.button.disabled, false);
        assert.equal(ui.latitude.value, '-1');
        assert.equal(ui.longitude.value, '100');
        assert.match(ui.status.textContent, /manual/);
    });
}

test('late GPS response does not overwrite coordinates edited while waiting', () => {
    const ui = setup();
    ui.click();
    ui.latitude.value = '-8';
    ui.success({ latitude: -6, longitude: 107, accuracy: 10 });
    assert.equal(ui.latitude.value, '-8');
    assert.equal(ui.longitude.value, '100');
    assert.match(ui.status.textContent, /tidak diganti/);
    assert.equal(ui.button.disabled, false);
});

test('invalid GPS coordinates do not replace existing values', () => {
    const ui = setup();
    ui.click();
    ui.success({ latitude: 91, longitude: NaN, accuracy: 10 });
    assert.equal(ui.latitude.value, '-1');
    assert.equal(ui.longitude.value, '100');
    assert.match(ui.status.textContent, /tidak valid/);
});

test('pages without the location form do not request GPS', () => {
    initializeLocationPicker({ querySelector: () => null }, null, true);
});
