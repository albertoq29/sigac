<x-layouts.app title="Asignaturas">
    <x-page-header title="Asignaturas" subtitle="Catálogo de materias con las que se crean las cátedras de cada año." />

    <form method="POST" action="{{ route('asignaturas.store') }}" class="card grid gap-3 p-4 sm:grid-cols-[1fr_18rem_auto] sm:items-end">
        @csrf
        <x-field label="Nueva asignatura" name="nombre">
            <input name="nombre" class="input" required maxlength="120" value="{{ old('nombre') }}" placeholder="Ej: Clarinete">
        </x-field>
        <x-field label="Categoría" name="categoria">
            <input name="categoria" class="input" list="categorias" maxlength="80" value="{{ old('categoria') }}">
            <datalist id="categorias">
                @foreach (config('sigac.categorias_asignatura') as $categoria) <option value="{{ $categoria }}"> @endforeach
            </datalist>
        </x-field>
        <button class="btn btn-primary"><x-icon name="plus" class="size-4" /> Agregar</button>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($asignaturas as $categoria => $grupo)
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">{{ $categoria }}</h2><span class="text-xs text-slate-500">{{ $grupo->count() }}</span></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($grupo as $asignatura)
                        <li class="px-5 py-2.5" x-data="{ editando: false }">
                            <div x-show="! editando" class="flex items-center gap-3">
                                <span @class(['flex-1 text-sm font-medium', 'text-slate-900' => $asignatura->activo, 'text-slate-400 line-through' => ! $asignatura->activo])>{{ $asignatura->nombre }}</span>
                                <span class="text-xs text-slate-500">{{ $asignatura->catedras_count }} cátedra(s)</span>
                                <button type="button" class="btn btn-sm btn-ghost" @click="editando = true"><x-icon name="pencil" class="size-4" /></button>
                                @if ($asignatura->catedras_count === 0)
                                    <form method="POST" action="{{ route('asignaturas.destroy', $asignatura) }}" data-confirmar="¿Eliminar {{ $asignatura->nombre }}?" data-confirmar-tipo="peligro">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ghost text-rose-600"><x-icon name="trash" class="size-4" /></button>
                                    </form>
                                @endif
                            </div>
                            <form x-cloak x-show="editando" method="POST" action="{{ route('asignaturas.update', $asignatura) }}" class="grid gap-2 sm:grid-cols-[1fr_12rem_auto_auto] sm:items-center">
                                @csrf @method('PUT')
                                <input name="nombre" class="input input-sm" value="{{ $asignatura->nombre }}" required maxlength="120">
                                <input name="categoria" class="input input-sm" list="categorias" value="{{ $asignatura->categoria }}" maxlength="80">
                                <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                    <input type="hidden" name="activo" value="0">
                                    <input type="checkbox" name="activo" value="1" class="checkbox" @checked($asignatura->activo)> Activa
                                </label>
                                <span class="flex gap-1">
                                    <button class="btn btn-sm btn-primary">Guardar</button>
                                    <button type="button" class="btn btn-sm btn-ghost" @click="editando = false"><x-icon name="x" class="size-4" /></button>
                                </span>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-layouts.app>
