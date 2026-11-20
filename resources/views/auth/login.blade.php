<x-layouts.guest title="Masuk">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center text-white">
            <img src="{{ asset('images/logo-joglo-quran.png') }}" alt="Logo Pesantren Joglo Qur'an" class="mx-auto mb-4 size-24 rounded-full shadow-lg ring-4 ring-gold-300/30">
            <h1 class="text-xl font-bold">Manajemen Rapat</h1>
            <p class="text-sm text-gold-300">{{ config('rapat.institution') }}</p>
        </div>
        <form method="POST" action="{{ route('login') }}" class="card space-y-4 p-6">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password">Kata sandi</label>
                <input id="password" name="password" type="password" required class="input">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600"> Ingat saya
            </label>
            <x-btn type="submit" icon="arrow-right-end-on-rectangle" class="w-full">Masuk</x-btn>
        </form>
        <p class="mt-6 text-center text-xs text-gold-200/70">“Dan urusan mereka diputuskan dengan musyawarah antara mereka.” — QS. Asy-Syura: 38</p>
    </div>
</x-layouts.guest>
