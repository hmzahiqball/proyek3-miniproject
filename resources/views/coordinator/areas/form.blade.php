<x-layouts.public :title="$area->exists ? 'Ubah wilayah' : 'Tambah wilayah'">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('koordinator.areas.index') }}" class="text-sm text-forest-900 hover:underline">Kembali ke data wilayah</a>
        <div class="page-heading mt-5">
            <h1 class="section-title">{{ $area->exists ? 'Ubah wilayah' : 'Tambah wilayah' }}</h1>
            <p class="muted mt-2">Kode, nama, dan status wilayah digunakan sebagai acuan pelaporan.</p>
        </div>

        <form method="POST" action="{{ $area->exists ? route('koordinator.areas.update', $area) : route('koordinator.areas.store') }}" class="panel max-w-xl space-y-6">
            @csrf
            @if($area->exists)
                @method('PUT')
            @endif
            <div>
                @if($area->exists)
                    <p class="text-sm font-medium">Kode wilayah</p>
                    <p class="mt-2 break-all text-sm">{{ $area->code }}</p>
                    <p class="mt-2 text-xs text-slate-500">Kode tetap setelah disimpan.</p>
                @else
                    <label for="code" class="block text-sm font-medium">Kode wilayah</label>
                    <input id="code" name="code" value="{{ old('code') }}" required maxlength="20" class="field mt-2" aria-describedby="code-help{{ $errors->has('code') ? ' code-error' : '' }}" @error('code') aria-invalid="true" @enderror>
                    <p id="code-help" class="mt-2 text-xs text-slate-500">Kode unik, maksimal 20 karakter. Contoh: WIL-01. Disimpan dalam huruf kapital.</p>
                @endif
                @error('code') <p id="code-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="name" class="block text-sm font-medium">Nama wilayah</label>
                <input id="name" name="name" value="{{ old('name', $area->name) }}" required maxlength="150" class="field mt-2" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name') <p id="name-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="is_active" class="block text-sm font-medium">Status wilayah</label>
                <select id="is_active" name="is_active" class="field mt-2" aria-describedby="active-help{{ $errors->has('is_active') ? ' active-error' : '' }}" @error('is_active') aria-invalid="true" @enderror>
                    <option value="1" @selected((string) old('is_active', $area->is_active ? '1' : '0') === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active', $area->is_active ? '1' : '0') === '0')>Nonaktif</option>
                </select>
                <p id="active-help" class="mt-2 text-xs leading-6 text-slate-500">Wilayah nonaktif tidak tersedia untuk laporan baru. Riwayat laporan tetap tersimpan.</p>
                @error('is_active') <p id="active-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan wilayah</button>
                <a href="{{ route('koordinator.areas.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.public>
