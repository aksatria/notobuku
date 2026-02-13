<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Masuk · NOTOBUKU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center">

<div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-2 bg-white shadow-xl rounded-xl overflow-hidden">

    {{-- LEFT: LOGIN FORM --}}
    <div class="p-8 md:p-10">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900">Masuk ke NOTOBUKU</h1>
            <p class="mt-1 text-sm text-slate-600">Sistem Manajemen Perpustakaan Terpadu</p>
        </div>

        {{-- STATUS --}}
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- ERROR --}}
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="nama@email.com"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input
                    type="password"
                    name="password"
                    required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="••••••••"
                >
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600">
                    <span class="ml-2">Ingat saya</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-700">
                        Lupa password?
                    </a>
                @endif
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition"
            >
                Masuk
            </button>
        </form>
    </div>

    {{-- RIGHT: DEMO ACCOUNTS --}}
    @php
        $showDemoAccounts = app()->environment('local')
            && \Illuminate\Support\Facades\Schema::hasTable('users')
            && \App\Models\User::count() === 0;
    @endphp
    @if($showDemoAccounts)
    <div class="bg-slate-900 text-slate-100 p-8 md:p-10">
        <h2 class="text-lg font-semibold">Akun Demo</h2>
        <p class="mt-1 text-sm text-slate-400">Klik “Isi Form” untuk auto-fill</p>

        <div class="mt-6 space-y-4 text-sm">

            <div class="rounded-lg bg-slate-800 p-4">
                <div class="font-semibold text-indigo-300">Super Admin</div>
                <div class="mt-1 font-mono text-xs">
                    Email: adhe5381@gmail.com<br>
                    Password: 71100907
                </div>
                <button type="button" onclick="fillLogin('adhe5381@gmail.com','71100907')"
                        class="mt-3 inline-flex rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold hover:bg-indigo-700">
                    Isi Form
                </button>
            </div>

            <div class="rounded-lg bg-slate-800 p-4">
                <div class="font-semibold text-emerald-300">Admin · Cabang 1</div>
                <div class="mt-1 font-mono text-xs">
                    Email: admin1@notobuku.test<br>
                    Password: password
                </div>
                <button type="button" onclick="fillLogin('admin1@notobuku.test','password')"
                        class="mt-3 inline-flex rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-700">
                    Isi Form
                </button>
            </div>

            <div class="rounded-lg bg-slate-800 p-4">
                <div class="font-semibold text-emerald-300">Admin · Cabang 2</div>
                <div class="mt-1 font-mono text-xs">
                    Email: admin2@notobuku.test<br>
                    Password: password
                </div>
                <button type="button" onclick="fillLogin('admin2@notobuku.test','password')"
                        class="mt-3 inline-flex rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-700">
                    Isi Form
                </button>
            </div>

            <div class="rounded-lg bg-slate-800 p-4">
                <div class="font-semibold text-sky-300">Member</div>
                <div class="mt-1 font-mono text-xs">
                    Email: member@notobuku.test<br>
                    Password: password
                </div>
                <button type="button" onclick="fillLogin('member@notobuku.test','password')"
                        class="mt-3 inline-flex rounded bg-sky-600 px-3 py-1.5 text-xs font-semibold hover:bg-sky-700">
                    Isi Form
                </button>
            </div>

        </div>

        <p class="mt-6 text-xs text-slate-500">
            ⚠️ Info ini sebaiknya hanya muncul di environment <b>local</b>.
        </p>
    </div>
    @endif
</div>

<script>
    function fillLogin(email, password) {
        document.querySelector('input[name=email]').value = email;
        document.querySelector('input[name=password]').value = password;
    }
</script>

</body>
</html>
