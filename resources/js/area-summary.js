export function initializeAreaSummary(root = document, fetchRequest = globalThis.fetch) {
    const select = root.querySelector('[data-area-summary-select]');
    const status = root.querySelector('[data-area-summary-status]');
    const retry = root.querySelector('[data-area-summary-retry]');
    if (!select || !status || !retry) return;

    let requestNumber = 0;
    let controller;

    const load = async () => {
        const currentRequest = ++requestNumber;
        controller?.abort();
        const areaId = select.value;
        const url = select.selectedOptions[0]?.dataset.summaryUrl;
        retry.hidden = true;
        status.removeAttribute('aria-busy');
        if (!areaId || !url) {
            status.textContent = 'Pilih wilayah untuk melihat jumlah laporan aktif milik Anda.';
            return;
        }

        controller = new AbortController();
        const activeController = controller;
        const timeout = setTimeout(() => activeController.abort(), 10000);
        status.setAttribute('aria-busy', 'true');
        status.textContent = 'Memuat informasi wilayah…';
        try {
            const response = await fetchRequest(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: activeController.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { data } = await response.json();
            if (!data || String(data.area_id) !== areaId || typeof data.area_name !== 'string'
                || !Number.isSafeInteger(data.active_report_count) || data.active_report_count < 0
                || data.scope !== 'milik_sendiri') {
                throw new Error('Ringkasan wilayah tidak valid');
            }
            if (currentRequest !== requestNumber || select.value !== areaId) return;
            status.textContent = data.active_report_count === 0
                ? `Belum ada laporan aktif milik Anda di ${data.area_name}.`
                : `${data.active_report_count} laporan aktif milik Anda di ${data.area_name}.`;
        } catch (error) {
            if (currentRequest !== requestNumber || select.value !== areaId) return;
            status.textContent = 'Informasi wilayah gagal dimuat. Coba lagi. Anda tetap dapat mengisi dan mengirim laporan.';
            retry.hidden = false;
        } finally {
            clearTimeout(timeout);
            if (currentRequest === requestNumber) status.removeAttribute('aria-busy');
        }
    };

    select.addEventListener('change', load);
    retry.addEventListener('click', load);
    load();
}
