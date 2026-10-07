<x-layouts.app :title="$usuario->exists ? 'Editar usuario' : 'Nuevo usuario'">
    <x-page-header :title="$usuario->exists ? 'Editar usuario' : 'Nuevo usuario'"
                   :subtitle="$usuario->exists ? $usuario->name : 'Se generará una contraseña temporal que el usuario deberá cambiar al entrar.'"
                   :back="route('usuarios.index')" />

    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="card max-w-3xl space-y-5 p-6">
        @csrf
        @if ($usuario->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre completo" name="name" required class="sm:col-span-2">
                <input name="name" class="input uppercase" required maxlength="120" value="{{ old('name', $usuario->name) }}">
            </x-field>
            <x-field label="Usuario (para iniciar sesión)" name="username" required help="Minúsculas sin acentos, números, puntos o guiones. Ej: maria.perez">
                <input name="username" class="input lowercase" required maxlength="60" value="{{ old('username', $usuario->username) }}" placeholder="nombre.apellido">
            </x-field>
            <x-field label="Rol" name="rol" required>
                <select name="rol" class="input">
                    @foreach (\App\Enums\Rol::cases() as $rol)
                        <option value="{{ $rol->value }}" @selected(old('rol', $usuario->rol?->value) === $rol->value)>{{ $rol->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Cédula" name="cedula">
                <input name="cedula" class="input" maxlength="20" value="{{ old('cedula', $usuario->cedula) }}">
            </x-field>
            <x-field label="Teléfono" name="telefono">
                <input name="telefono" class="input" maxlength="60" value="{{ old('telefono', $usuario->telefono) }}">
            </x-field>
            <x-field label="Correo electrónico" name="email" class="sm:col-span-2">
                <input type="email" name="email" class="input" maxlength="120" value="{{ old('email', $usuario->email) }}">
            </x-field>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" class="checkbox" @checked(old('activo', $usuario->activo ?? true))>
            Usuario activo (puede iniciar sesión)
        </label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar</button>
        </div>
    </form>
</x-layouts.app>
