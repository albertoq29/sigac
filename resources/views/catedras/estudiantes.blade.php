<x-layouts.app title="Agregar estudiantes">
    <x-page-header title="Agregar estudiantes a la cátedra" :subtitle="$catedra->nombre.' · '.$catedra->anioEscolar->nombre" :back="route('catedras.show', $catedra)" />

    <form method="POST" action="{{ route('catedras.agregar-estudiantes', $catedra) }}" class="card overflow-hidden" x-data="selectorConFiltro()">
        @csrf
        <div class="card-header">
            <div class="relative w-full max-w-md">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" x-model="filtro" class="input pl-9" placeholder="Filtrar por nombre, cédula o sección…">
            </div>
            <button class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Agregar seleccionados</button>
        </div>

        @if ($matriculas->isEmpty())
            <x-empty icon="users" title="No hay estudiantes disponibles">
                Solo aparecen estudiantes <b>inscritos</b> (activos) en {{ $catedra->anioEscolar->nombre }} que aún no están en esta cátedra.
            </x-empty>
        @else
            <div class="max-h-[60vh] overflow-y-auto">
                <table class="tabla">
                    <thead class="sticky top-0">
                        <tr><th class="w-10"></th><th>Estudiante</th><th>Sección</th><th>Cátedras actuales</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($matriculas as $matricula)
                            @php $texto = $matricula->estudiante->apellidos_nombres.' '.$matricula->estudiante->cedula.' '.$matricula->seccion; @endphp
                            <tr x-show="coincide(@js($texto))">
                                <td><input type="checkbox" name="matriculas[]" value="{{ $matricula->id }}" class="checkbox" id="m{{ $matricula->id }}"></td>
                                <td>
                                    <label for="m{{ $matricula->id }}" class="cursor-pointer font-semibold text-slate-900">{{ $matricula->estudiante->apellidos_nombres }}</label>
                                    <div class="text-xs text-slate-500">C.I. {{ $matricula->estudiante->cedula }}</div>
                                </td>
                                <td>{{ $matricula->seccion ?? '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($matricula->inscripciones->where('estado', \App\Enums\EstadoInscripcion::Cursando) as $inscripcion)
                                            <span class="chip">{{ $inscripcion->catedra->asignatura->nombre }} ({{ $inscripcion->catedra->nivel->nombre }})</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>
</x-layouts.app>
