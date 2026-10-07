<x-layouts.app title="Niveles">
    <x-page-header title="Años y niveles" subtitle="Los AÑOS de estudio se aprueban completos: quien aprueba todas sus materias pasa al año siguiente. Los NIVELES (instrumentos, Nivel I…) se asignan manualmente." />

    <form method="POST" action="{{ route('niveles.store') }}" class="card grid gap-3 p-4 sm:grid-cols-[1fr_7rem_11rem_14rem_auto] sm:items-end">
        @csrf
        <x-field label="Nuevo nivel" name="nombre">
            <input name="nombre" class="input" required maxlength="60" value="{{ old('nombre') }}" placeholder="Ej: Nivel IV">
        </x-field>
        <x-field label="Orden" name="orden">
            <input type="number" name="orden" class="input" required min="0" max="999" value="{{ old('orden', ($niveles->max('orden') ?? 0) + 1) }}">
        </x-field>
        <x-field label="Tipo" name="tipo">
            <select name="tipo" class="input">
                <option value="anio">Año de estudio</option>
                <option value="nivel" selected>Nivel</option>
            </select>
        </x-field>
        <x-field label="Al aprobar pasa a" name="siguiente_nivel_id">
            <select name="siguiente_nivel_id" class="input">
                <option value="">Ninguno (nivel final)</option>
                @foreach ($niveles as $n) <option value="{{ $n->id }}">{{ $n->nombre }}</option> @endforeach
            </select>
        </x-field>
        <button class="btn btn-primary"><x-icon name="plus" class="size-4" /> Agregar</button>
    </form>

    <div class="card overflow-hidden">
        <table class="tabla">
            <thead><tr><th class="w-20">Orden</th><th>Nivel</th><th>Tipo</th><th>Al aprobar pasa a</th><th class="text-center">Cátedras</th><th class="w-48"></th></tr></thead>
            <tbody>
                @foreach ($niveles as $nivel)
                    <tr x-data="{ editando: false }">
                        <td colspan="6" class="p-0">
                            <div x-show="! editando" class="grid grid-cols-[5rem_1fr_9rem_1fr_6rem_12rem] items-center">
                                <span class="px-4 py-3 text-slate-500">{{ $nivel->orden }}</span>
                                <span class="px-4 py-3 font-semibold text-slate-900">{{ $nivel->nombre }}</span>
                                <span class="px-4 py-3"><x-badge :color="$nivel->esAnio() ? 'violet' : 'slate'">{{ $nivel->esAnio() ? 'Año de estudio' : 'Nivel' }}</x-badge></span>
                                <span class="px-4 py-3 text-sm">{{ $nivel->siguiente?->nombre ?? '— (final)' }}</span>
                                <span class="px-4 py-3 text-center">{{ $nivel->catedras_count }}</span>
                                <span class="flex justify-end gap-1 px-4 py-2">
                                    <button type="button" class="btn btn-sm btn-ghost" @click="editando = true"><x-icon name="pencil" class="size-4" /></button>
                                    @if ($nivel->catedras_count === 0)
                                        <form method="POST" action="{{ route('niveles.destroy', $nivel) }}" data-confirmar="¿Eliminar {{ $nivel->nombre }}?" data-confirmar-tipo="peligro">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ghost text-rose-600"><x-icon name="trash" class="size-4" /></button>
                                        </form>
                                    @endif
                                </span>
                            </div>
                            <form x-cloak x-show="editando" method="POST" action="{{ route('niveles.update', $nivel) }}" class="grid grid-cols-[5rem_1fr_9rem_1fr_12rem] items-center gap-2 px-4 py-2">
                                @csrf @method('PUT')
                                <input type="number" name="orden" class="input input-sm" value="{{ $nivel->orden }}" min="0" max="999" required>
                                <input name="nombre" class="input input-sm" value="{{ $nivel->nombre }}" required maxlength="60">
                                <select name="tipo" class="input input-sm">
                                    <option value="anio" @selected($nivel->esAnio())>Año de estudio</option>
                                    <option value="nivel" @selected(! $nivel->esAnio())>Nivel</option>
                                </select>
                                <select name="siguiente_nivel_id" class="input input-sm">
                                    <option value="">Ninguno (nivel final)</option>
                                    @foreach ($niveles->where('id', '!=', $nivel->id) as $n)
                                        <option value="{{ $n->id }}" @selected($nivel->siguiente_nivel_id === $n->id)>{{ $n->nombre }}</option>
                                    @endforeach
                                </select>
                                <span class="flex justify-end gap-1">
                                    <button class="btn btn-sm btn-primary">Guardar</button>
                                    <button type="button" class="btn btn-sm btn-ghost" @click="editando = false"><x-icon name="x" class="size-4" /></button>
                                </span>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
