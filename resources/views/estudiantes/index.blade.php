<x-layouts.app title="Estudiantes">
    <x-page-header title="Estudiantes"
                   :subtitle="($totales['activo'] ?? 0).' activos (inscritos) · '.($totales['inactivo'] ?? 0).' inactivos'">
        <x-slot:actions>
            <a href="{{ route('estudiantes.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Nuevo estudiante</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ request('q') }}" class="input pl-9" placeholder="Buscar por cédula o nombre…" autofocus>
        </div>
        <div class="flex gap-2">
            @foreach (['' => 'Todos', 'activo' => 'Activos', 'inactivo' => 'Inactivos'] as $valor => $texto)
                <button name="estado" value="{{ $valor }}"
                        @class(['btn btn-sm', 'btn-primary' => request('estado', '') === $valor, 'btn-secondary' => request('estado', '') !== $valor])>{{ $texto }}</button>
            @endforeach
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($estudiantes->isEmpty())
            <x-empty icon="users" title="No se encontraron estudiantes">Pruebe con otro término de búsqueda.</x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Estudiante</th>
                            <th>Estado</th>
                            <th>Cátedras {{ $anio?->nombre }}</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($estudiantes as $estudiante)
                            @php $matricula = $estudiante->matriculas->first(); @endphp
                            <tr>
                                <td class="font-semibold whitespace-nowrap text-slate-900">{{ $estudiante->cedula }}</td>
                                <td>
                                    <a href="{{ route('estudiantes.show', $estudiante) }}" class="font-semibold text-slate-900 hover:underline">{{ $estudiante->apellidos_nombres }}</a>
                                    <div class="text-xs text-slate-500">
                                        @if ($estudiante->edad !== null) {{ $estudiante->edad }} años @endif
                                        @if ($matricula?->seccion) · Sección {{ $matricula->seccion }} @endif
                                    </div>
                                </td>
                                <td>
                                    <x-badge :color="$estudiante->estado->color()">{{ $estudiante->estado->label() }}</x-badge>
                                    @if ($matricula && ! $matricula->estaInscrito())
                                        <div class="mt-1"><x-badge :color="$matricula->estado->color()">{{ $matricula->estado->label() }}</x-badge></div>
                                    @endif
                                </td>
                                <td class="max-w-md">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($matricula?->inscripciones ?? [] as $inscripcion)
                                            <span @class(['chip', 'line-through opacity-60' => $inscripcion->estaRetirada()]) title="{{ $inscripcion->estado->label() }}">
                                                {{ $inscripcion->catedra->asignatura->nombre }} ({{ $inscripcion->catedra->nivel->nombre }}) {{ $inscripcion->catedra->seccion }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400 italic">Sin cátedras en este año</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5">
                                        <a href="{{ route('estudiantes.show', $estudiante) }}" class="btn btn-sm btn-secondary" title="Ver expediente"><x-icon name="eye" class="size-4" /> Ver</a>
                                        @if ($estudiante->estado->value === 'activo')
                                            <a href="{{ route('documentos.constancia', $estudiante) }}" target="_blank" class="btn btn-sm btn-secondary text-violet-700" title="Constancia de estudio"><x-icon name="printer" class="size-4" /> Constancia</a>
                                        @else
                                            <a href="{{ route('matriculas.create', $estudiante) }}" class="btn btn-sm btn-secondary text-emerald-700" title="Inscribir"><x-icon name="user-plus" class="size-4" /> Inscribir</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $estudiantes->links() }}</div>
        @endif
    </div>
</x-layouts.app>
