<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Pendaftaran akun YokPrinting.ID">

        <title>Daftar Akun - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="min-h-screen bg-canvas px-4 py-6 sm:px-6 lg:grid lg:grid-cols-[minmax(0,34rem)_minmax(0,1fr)] lg:p-0">
            <section class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-lg flex-col justify-center lg:min-h-screen lg:px-10" aria-labelledby="register-heading">
                <div class="mb-8 flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="grid size-10 place-items-center rounded-xl bg-brand-700 text-sm font-bold text-white">IH</a>
                    <div>
                        <p class="text-sm font-semibold leading-tight text-ink">YokPrinting.ID</p>
                        <p class="mt-0.5 text-xs text-muted">Ruang kerja keuangan</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-line bg-white p-5 sm:p-7">
                    <div>
                        <p class="text-sm font-semibold text-brand-700">Mulai workspace baru</p>
                        <h1 id="register-heading" class="mt-2 text-2xl font-semibold tracking-[-0.025em] text-ink">Daftarkan akun perusahaan</h1>
                        <p class="mt-2 text-sm leading-6 text-muted">Buat akun admin awal untuk mengelola invoice, pelanggan, produk, dan laporan bisnis.</p>
                    </div>

                    <div class="mt-5 rounded-xl border border-accent-soft bg-accent-soft/50 p-4 text-sm text-accent" role="status">
                        <p class="font-semibold">Demo frontend</p>
                        <p class="mt-1 leading-5">Backend registrasi dan aktivasi role akan disambungkan pada task berikutnya. Struktur form sudah siap untuk proses pembuatan akun.</p>
                    </div>

                    <form class="mt-6 space-y-4" method="POST" action="/register" x-data="{ showPassword: false, showConfirmation: false }" novalidate>
                        @csrf

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-ink">Nama lengkap</span>
                                <input
                                    name="name"
                                    autocomplete="name"
                                    required
                                    autofocus
                                    class="form-control mt-1.5"
                                    placeholder="Nama admin"
                                    data-testid="register-name-input"
                                >
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-ink">Nama perusahaan</span>
                                <input
                                    name="company_name"
                                    autocomplete="organization"
                                    required
                                    class="form-control mt-1.5"
                                    placeholder="PT / CV / usaha"
                                    data-testid="register-company-input"
                                >
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-sm font-medium text-ink">Username</span>
                            <input
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                autocomplete="username"
                                required
                                maxlength="80"
                                class="form-control mt-1.5"
                                placeholder="username_admin"
                                data-testid="register-username-input"
                            >
                            @error('username')
                                <span class="mt-1.5 block text-sm text-red-700">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-ink">Email kerja</span>
                            <input
                                type="email"
                                name="email"
                                autocomplete="email"
                                inputmode="email"
                                required
                                class="form-control mt-1.5"
                                placeholder="admin@perusahaan.com"
                                data-testid="register-email-input"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-ink">Role awal</span>
                            <select name="role" class="form-control mt-1.5" data-testid="register-role-select">
                                <option value="owner">Owner / Direktur</option>
                                <option value="finance_admin">Admin Finance</option>
                                <option value="operations">Operasional</option>
                            </select>
                            <span class="mt-1 block text-xs leading-5 text-muted">Role ini menentukan akses awal setelah backend permission aktif.</span>
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-ink">Password</span>
                                <span class="relative mt-1.5 block">
                                    <input
                                        :type="showPassword ? 'text' : 'password'"
                                        name="password"
                                        autocomplete="new-password"
                                        required
                                        minlength="8"
                                        class="form-control pr-24"
                                        placeholder="Minimal 8 karakter"
                                        data-testid="register-password-input"
                                    >
                                    <button
                                        type="button"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800"
                                        :aria-pressed="showPassword"
                                        @click="showPassword = !showPassword"
                                        data-testid="toggle-register-password"
                                    >
                                        <span x-text="showPassword ? 'Tutup' : 'Lihat'"></span>
                                    </button>
                                </span>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-ink">Konfirmasi password</span>
                                <span class="relative mt-1.5 block">
                                    <input
                                        :type="showConfirmation ? 'text' : 'password'"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                        required
                                        minlength="8"
                                        class="form-control pr-24"
                                        placeholder="Ulangi password"
                                        data-testid="register-password-confirmation-input"
                                    >
                                    <button
                                        type="button"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800"
                                        :aria-pressed="showConfirmation"
                                        @click="showConfirmation = !showConfirmation"
                                        data-testid="toggle-register-confirmation"
                                    >
                                        <span x-text="showConfirmation ? 'Tutup' : 'Lihat'"></span>
                                    </button>
                                </span>
                            </label>
                        </div>

                        <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas p-3 text-sm text-muted">
                            <input type="checkbox" name="terms" value="1" required class="mt-0.5 size-4 rounded border-line text-brand-700 focus:ring-brand-500">
                            <span>Saya menyetujui pembuatan workspace dan memahami bahwa akses pengguna akan dikelola berdasarkan role perusahaan.</span>
                        </label>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-60" data-testid="register-submit-button">
                            Buat akun dan workspace
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs leading-5 text-muted">
                    Sudah punya akun?
                    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">Masuk ke YokPrinting.ID</a>
                </p>
            </section>

            <section class="relative hidden min-h-screen overflow-hidden bg-brand-900 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between" aria-labelledby="register-brand-heading">
                <div class="absolute inset-0 opacity-30" aria-hidden="true">
                    <div class="absolute right-10 top-20 size-80 rounded-full bg-brand-500 blur-3xl"></div>
                    <div class="absolute -bottom-16 left-10 size-72 rounded-full bg-accent blur-3xl"></div>
                </div>

                <div class="relative ml-auto max-w-2xl">
                    <p class="mb-4 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-100 ring-1 ring-white/10">Workspace finance modern</p>
                    <h2 id="register-brand-heading" class="max-w-xl text-4xl font-semibold tracking-[-0.03em] text-wrap-balance">Siapkan fondasi invoice management yang bisa tumbuh bersama bisnis.</h2>
                    <p class="mt-5 max-w-lg text-sm leading-6 text-brand-100 text-wrap-pretty">Mulai dari satu admin, lalu perluas ke tim finance, operasional, dan manajemen saat role & permission aktif.</p>

                    <ul class="mt-8 grid max-w-xl gap-3">
                        <li class="rounded-xl bg-white/8 p-4 text-sm ring-1 ring-white/10">
                            <span class="font-semibold">Data bisnis terpusat</span>
                            <span class="mt-1 block text-brand-100">Customer, produk, invoice, pembayaran, dan laporan berada dalam alur yang sama.</span>
                        </li>
                        <li class="rounded-xl bg-white/8 p-4 text-sm ring-1 ring-white/10">
                            <span class="font-semibold">Siap role & permission</span>
                            <span class="mt-1 block text-brand-100">Form sudah menyiapkan pilihan role awal untuk task backend keamanan berikutnya.</span>
                        </li>
                        <li class="rounded-xl bg-white/8 p-4 text-sm ring-1 ring-white/10">
                            <span class="font-semibold">Onboarding ringkas</span>
                            <span class="mt-1 block text-brand-100">Akun pertama dapat langsung diarahkan ke dashboard setelah registrasi aktif.</span>
                        </li>
                    </ul>
                </div>

                <p class="relative ml-auto max-w-2xl text-xs leading-5 text-brand-200">Gunakan email kerja yang aktif agar proses verifikasi dan pemulihan akses bisa berjalan aman.</p>
            </section>
        </main>
    </body>
</html>
