<x-layouts.guest title="Iniciar sesión">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-flash />

        <x-field label="Usuario" name="usuario">
            <input id="usuario" name="usuario" type="text" class="input" value="{{ old('usuario') }}" required autofocus autocomplete="username" placeholder="ej: everlys.guzman">
        </x-field>

        <x-field label="Contraseña" name="password">
            <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
        </x-field>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="recordar" value="1" class="checkbox"> Mantener la sesión iniciada
        </label>

        <button class="btn btn-primary w-full py-3">
            <x-icon name="lock" class="size-4" /> Iniciar sesión
        </button>

        <p class="text-center text-xs text-slate-500">¿Olvidó su contraseña? Solicite a Control de Estudios que la restablezca.</p>
    </form>
</x-layouts.guest>
