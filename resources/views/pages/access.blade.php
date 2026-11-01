<x-layouts.public title="Masuk">
    <div class="container-page py-10 sm:py-14">
        <section class="mx-auto max-w-md rounded-lg border border-slate-200 p-6 sm:p-8" aria-labelledby="login-title">
            <h1 id="login-title" class="text-2xl font-semibold">Masuk</h1>
            <p class="muted mt-2">Gunakan akun yang disediakan untuk mengakses Karhutla.</p>

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus class="field @error('email') border-red-600 @enderror" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror>
                    @error('email')
                        <p id="email-error" role="alert" class="mt-2 text-sm leading-6 text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">Kata sandi</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required class="field @error('password') border-red-600 @enderror" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror>
                    @error('password')
                        <p id="password-error" role="alert" class="mt-2 text-sm leading-6 text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">Masuk</button>
            </form>

            <p class="mt-5 text-xs leading-6 text-slate-500">Belum memiliki akun? Hubungi koordinator. Peran pengguna ditentukan dari akun yang digunakan.</p>
            <a href="{{ route('guide') }}" class="mt-5 inline-flex min-h-11 items-center text-sm text-forest-700 underline underline-offset-4">Baca panduan pelaporan</a>
        </section>
    </div>
</x-layouts.public>
