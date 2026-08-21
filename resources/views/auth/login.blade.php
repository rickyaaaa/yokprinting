<!DOCTYPE html>
<html lang="id" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Login YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Login - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas text-ink">
        @php($yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png'))))

        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
            <section class="w-full max-w-[440px] card p-6 sm:p-8" aria-labelledby="login-heading">
                <div class="flex flex-col items-center gap-4 text-center">
                    <div class="flex h-16 w-32 items-center justify-center rounded-xl border border-line bg-surface-low px-3">
                        <img src="{{ $yokPrintingLogo }}" alt="YokPrinting.ID" class="h-auto w-full object-contain">
                    </div>

                    <div>
                        <h1 id="login-heading" class="text-2xl font-semibold leading-8 text-ink sm:text-[32px] sm:leading-10">Welcome Back</h1>
                        <p class="mt-2 text-sm leading-6 text-muted">Sign in to YokPrinting.ID</p>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm leading-5 text-brand-800" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-5 text-red-800" role="alert" data-testid="login-error-summary">
                        <p class="font-semibold">Login belum berhasil.</p>
                        <p class="mt-1">{{ $errors->first() }}</p>
                    </div>
                @endif

                <form class="mt-6 flex flex-col gap-5" method="POST" action="/login" x-data="{ showPassword: false }" novalidate>
                    @csrf

                    <label class="block">
                        <span class="text-sm font-medium text-ink">Username</span>
                        <span class="relative mt-1.5 block">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true">
                                <i class="iconify tabler--user text-lg"></i>
                            </span>
                            <input
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                autocomplete="username"
                                required
                                autofocus
                                class="form-control pl-10"
                                placeholder="Masukkan username"
                                data-testid="login-username-input"
                            >
                        </span>
                        @error('username')
                            <span class="mt-1.5 block text-sm text-red-700">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-ink">Password</span>
                        </span>
                        <span class="relative mt-1.5 block">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true">
                                <i class="iconify tabler--lock text-lg"></i>
                            </span>
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                autocomplete="current-password"
                                required
                                class="form-control pl-10 pr-24"
                                placeholder="••••••••"
                                data-testid="login-password-input"
                            >
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800"
                                :aria-pressed="showPassword"
                                @click="showPassword = !showPassword"
                                data-testid="toggle-password-visibility"
                            >
                                <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                            </button>
                        </span>
                    </label>

                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm leading-5 text-muted">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line text-brand-700 focus:ring-brand-500">
                        <span>Remember me for 30 days</span>
                    </label>

                    <button type="submit" class="btn btn-primary h-10 w-full" data-testid="login-submit-button">
                        Sign In
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
