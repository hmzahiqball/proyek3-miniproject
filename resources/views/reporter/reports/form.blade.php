<x-layouts.public :title="$report->exists ? 'Perbaiki laporan' : 'Buat laporan'">
    @php
        $oldInput = function (string $field) use ($report): string {
            $default = $field === 'occurred_at' ? $report->occurred_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') : $report->{$field};
            $value = old($field, $default);

            return is_scalar($value) ? (string) $value : '';
        };
    @endphp
    <div class="container-page py-8 sm:py-10">
        <a href="{{ $report->exists ? route('pelapor.reports.show', $report) : route('pelapor.dashboard') }}" class="text-sm text-forest-900 hover:underline">{{ $report->exists ? 'Kembali ke detail laporan' : 'Kembali ke dashboard' }}</a>
        <div class="page-heading mt-5">
            <h1 class="section-title">{{ $report->exists ? 'Perbaiki laporan #'.$report->id : 'Buat laporan' }}</h1>
            <p class="muted mt-2">{{ $report->exists ? 'Perbaiki informasi kejadian dan lokasi. Menyimpan perbaikan tidak mengubah status atau mengirim ulang laporan untuk ditinjau.' : 'Catat indikasi kebakaran berdasarkan kondisi yang Anda amati.' }}</p>
        </div>

        @if($areas->isEmpty())
            <p role="alert" class="mb-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Belum ada wilayah aktif. Hubungi koordinator sebelum mengirim laporan.</p>
        @endif
        @if($errors->any())
            <div role="alert" class="mb-6 max-w-3xl rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">{{ $report->exists ? 'Perbaikan belum disimpan. Periksa isian berikut.' : 'Laporan belum terkirim. Periksa isian berikut.' }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $report->exists ? route('pelapor.reports.update', $report) : route('pelapor.reports.store') }}" class="panel max-w-3xl space-y-8">
            @csrf
            @if($report->exists)
                @method('PATCH')
            @endif
            <fieldset class="min-w-0 space-y-5">
                <legend class="mb-5 text-lg font-semibold">Informasi kejadian</legend>
                <div>
                    <label for="title" class="block text-sm font-medium">Ringkasan kejadian</label>
                    <input id="title" name="title" value="{{ $oldInput('title') }}" required minlength="5" maxlength="150" class="field mt-2" aria-describedby="title-help{{ $errors->has('title') ? ' title-error' : '' }}" @error('title') aria-invalid="true" @enderror>
                    <p id="title-help" class="mt-2 text-xs text-slate-500">5–150 karakter. Contoh: Asap terlihat dari lahan di tepi jalan.</p>
                    @error('title') <p id="title-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium">Kondisi yang diamati</label>
                    <textarea id="description" name="description" rows="5" required minlength="20" maxlength="5000" class="field mt-2 h-auto py-3" aria-describedby="description-help{{ $errors->has('description') ? ' description-error' : '' }}" @error('description') aria-invalid="true" @enderror>{{ $oldInput('description') }}</textarea>
                    <p id="description-help" class="mt-2 text-xs leading-6 text-slate-500">20–5.000 karakter. Jelaskan asap, api, kondisi lahan, atau hal lain yang terlihat tanpa mendekati area berbahaya.</p>
                    @error('description') <p id="description-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="occurred_at" class="block text-sm font-medium">Waktu pengamatan (WIB)</label>
                    <input type="datetime-local" id="occurred_at" name="occurred_at" value="{{ $oldInput('occurred_at') }}" required class="field mt-2 min-w-0" aria-describedby="time-help{{ $errors->has('occurred_at') ? ' time-error' : '' }}" @error('occurred_at') aria-invalid="true" @enderror>
                    <p id="time-help" class="mt-2 text-xs text-slate-500">Gunakan waktu Indonesia Barat. Waktu pengamatan tidak boleh di masa depan.</p>
                    @error('occurred_at') <p id="time-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <fieldset class="min-w-0 space-y-5 border-t border-slate-200 pt-6">
                <legend class="pr-3 text-lg font-semibold">Lokasi kejadian</legend>
                <div>
                    <label for="area_id" class="block text-sm font-medium">Wilayah</label>
                    <select id="area_id" name="area_id" required class="field mt-2" aria-describedby="area-help{{ $errors->has('area_id') ? ' area-error' : '' }}" @error('area_id') aria-invalid="true" @enderror>
                        <option value="">Pilih wilayah</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" @selected($oldInput('area_id') === (string) $area->id)>{{ $area->name }} ({{ $area->code }}){{ ! $area->is_active ? ' — nonaktif, wilayah saat ini' : '' }}</option>
                        @endforeach
                    </select>
                    <p id="area-help" class="mt-2 text-xs leading-6 text-slate-500">{{ $report->exists ? 'Wilayah lama yang nonaktif boleh dipertahankan. Jika mengganti wilayah, pilih wilayah aktif.' : 'Hanya wilayah aktif yang tersedia.' }}</p>
                    @error('area_id') <p id="area-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="location_description" class="block text-sm font-medium">Petunjuk lokasi</label>
                    <textarea id="location_description" name="location_description" rows="3" required minlength="10" maxlength="500" class="field mt-2 h-auto py-3" aria-describedby="location-help{{ $errors->has('location_description') ? ' location-error' : '' }}" @error('location_description') aria-invalid="true" @enderror>{{ $oldInput('location_description') }}</textarea>
                    <p id="location-help" class="mt-2 text-xs text-slate-500">10–500 karakter. Sebutkan jalan, patokan, atau arah menuju lokasi.</p>
                    @error('location_description') <p id="location-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <button type="button" data-use-gps class="btn btn-outline disabled:cursor-wait disabled:opacity-50" aria-describedby="gps-help gps-status">Gunakan GPS</button>
                    <p id="gps-help" class="mt-2 text-xs leading-6 text-slate-500">Mengisi koordinat lokasi perangkat setelah Anda memberi izin. Gunakan jika Anda berada di lokasi kejadian; koordinat tetap bisa diisi manual.</p>
                    <p id="gps-status" data-gps-status role="status" aria-live="polite" aria-atomic="true" class="mt-2 text-sm leading-6 text-slate-600"></p>
                    <noscript><p class="mt-2 text-sm text-slate-600">Aktifkan JavaScript untuk menggunakan GPS, atau isi koordinat secara manual.</p></noscript>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach(['latitude' => ['Lintang', '-90', '90', '-6.9000000'], 'longitude' => ['Bujur', '-180', '180', '107.6000000']] as $field => [$label, $min, $max, $example])
                        <div class="min-w-0">
                            <label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label>
                            <input type="number" step="any" min="{{ $min }}" max="{{ $max }}" id="{{ $field }}" name="{{ $field }}" value="{{ $oldInput($field) }}" required class="field mt-2" aria-describedby="{{ $field }}-help{{ $errors->has($field) ? ' '.$field.'-error' : '' }}" @error($field) aria-invalid="true" @enderror>
                            <p id="{{ $field }}-help" class="mt-2 text-xs leading-6 text-slate-500">{{ $min }} sampai {{ $max }}. Contoh: {{ $example }}. Gunakan titik untuk desimal.</p>
                            @error($field) <p id="{{ $field }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="border-t border-slate-200 pt-6">
                <p class="muted mb-4">Semua isian wajib dilengkapi. Periksa kembali waktu dan lokasi sebelum menyimpan.</p>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" @disabled($areas->isEmpty())>{{ $report->exists ? 'Simpan perbaikan' : 'Kirim laporan' }}</button>
                    <a href="{{ $report->exists ? route('pelapor.reports.show', $report) : route('pelapor.dashboard') }}" class="btn btn-outline">Batal</a>
                </div>
            </div>
        </form>
    </div>
</x-layouts.public>
