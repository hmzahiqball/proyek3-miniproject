export function initializeLocationPicker(root = document, geolocation = navigator.geolocation, secureContext = window.isSecureContext) {
    const button = root.querySelector('[data-use-gps]');
    const status = root.querySelector('[data-gps-status]');
    const latitude = root.querySelector('#latitude');
    const longitude = root.querySelector('#longitude');

    if (!button || !status || !latitude || !longitude) return;

    button.addEventListener('click', () => {
        if (button.disabled) return;
        if (!secureContext || !geolocation) {
            status.textContent = !secureContext
                ? 'Pengambilan lokasi memerlukan HTTPS atau localhost. Isi koordinat secara manual.'
                : 'Browser ini tidak mendukung pengambilan lokasi. Isi koordinat secara manual.';
            return;
        }

        const previousLatitude = latitude.value;
        const previousLongitude = longitude.value;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Mengambil lokasi…';
        status.textContent = 'Izinkan akses lokasi pada browser. Sedang mencari lokasi perangkat…';

        const finish = () => {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = 'Gunakan GPS';
        };

        const fail = (error) => {
            finish();
            const messages = {
                1: 'Izin lokasi ditolak. Izinkan akses lokasi di browser atau isi koordinat secara manual.',
                2: 'Lokasi perangkat belum tersedia. Coba lagi atau isi koordinat secara manual.',
                3: 'Pengambilan lokasi terlalu lama. Coba lagi atau isi koordinat secara manual.',
            };
            status.textContent = messages[error?.code] ?? 'Lokasi gagal diambil. Coba lagi atau isi koordinat secara manual.';
        };

        try {
            geolocation.getCurrentPosition((position) => {
                finish();
                const { latitude: lat, longitude: lng, accuracy } = position.coords;
                if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
                    status.textContent = 'Koordinat dari perangkat tidak valid. Isi koordinat secara manual.';
                    return;
                }
                if (latitude.value !== previousLatitude || longitude.value !== previousLongitude) {
                    status.textContent = 'Koordinat tidak diganti karena Anda mengubah isian saat lokasi sedang dicari. Klik Gunakan GPS lagi jika ingin menggantinya.';
                    return;
                }

                latitude.value = lat.toFixed(7);
                longitude.value = lng.toFixed(7);
                for (const field of [latitude, longitude]) {
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
                const precision = Number.isFinite(accuracy) && accuracy >= 0 ? ` Perkiraan ketelitian ±${Math.ceil(accuracy)} meter.` : '';
                status.textContent = `Lokasi perangkat berhasil diambil.${precision} Pastikan koordinat sesuai lokasi kejadian.`;
            }, fail, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        } catch {
            fail();
        }
    });
}
