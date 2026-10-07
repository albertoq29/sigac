<x-layouts.app title="Usuarios y profesores">
    <x-page-header title="Usuarios y profesores" subtitle="Cada profesor entra con su usuario para pasar asistencia y cargar notas de sus cátedras.">
        <x-slot:actions>
            <a href="{{ route('usuarios.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Nuevo usuario</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row">
        <input type="search" name="q" value="{{ request('q') }}" class="input flex-1" placeholder="Buscar por nombre, usuario o cédula">
        <select name="rol" class="input sm:w-56" onchange="this.form.submit()">
            <option value="">Todos los roles</option>
            @foreach (\App\Enums\Rol::cases() as $rol)
                <option value="{{ $rol->value }}" @selected(request('rol') === $rol->value)>{{ $rol->label() }}</option>
            @endforeach
        </select>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Contacto</th><th class="text-center">Cátedras {{ $anio?->nombre }}</th><th>Estado</th><th class="text-right">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $usuario)
                        <tr @class(['opacity-60' => ! $usuario->activo])>
                            <td class="font-semibold text-slate-900">{{ $usuario->name }}</td>
                            <td class="font-mono text-xs">{{ $usuario->username }}</td>
                            <td><x-badge :color="$usuario->esControl() ? 'violet' : 'slate'">{{ $usuario->rol->label() }}</x-badge></td>
                            <td class="text-xs text-slate-500">
                                {{ $usuario->cedula ? 'C.I. '.$usuario->cedula : '' }}
                                <div>{{ $usuario->telefono }}</div>
                                <div>{{ $usuario->email }}</div>
                            </td>
                            <td class="text-center">
                                @if ($usuario->esProfesor())
                                    <a href="{{ route('catedras.index', ['profesor' => $usuario->id]) }}" class="font-semibold text-slate-900 hover:underline">{{ $usuario->catedras_count }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <x-badge :color="$usuario->activo ? 'emerald' : 'slate'">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                                @if ($usuario->debe_cambiar_password)
                                    <div class="mt-1 text-[11px] text-amber-600">Contraseña temporal</div>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-sm btn-secondary"><x-icon name="pencil" class="size-4" /> Editar</a>
                                    <form method="POST" action="{{ route('usuarios.restablecer', $usuario) }}" data-confirmar="¿Generar una contraseña temporal nueva para {{ $usuario->username }}?">
                                        @csrf
                                        <button class="btn btn-sm btn-secondary" title="Restablecer contraseña"><x-icon name="key" class="size-4" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty icon="user" title="No hay usuarios" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
