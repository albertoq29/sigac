<x-layouts.app :title="'Reinscripción '.$anio->nombre">
    <x-page-header :title="'Reinscripción para '.$anio->nombre"
                   :subtitle="'Reasigne las materias del nuevo año a los estudiantes de '.($origen?->nombre ?? 'años anteriores').'. '.$yaInscritos.' ya inscritos en '.$anio->nombre.'.'"
                   :back="route('anios.show', $anio)" />

    @if ($totalCatedras === 0)
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icon name="exclamation" class="mt-0.5 size-5 shrink-0" />
            <div>El año {{ $anio->nombre }} todavía no tiene cátedras. <a href="{{ route('anios.show', $anio) }}" class="link">Cópielas de un año anterior</a> o créelas antes de reinscribir.</div>
        </div>
    @endif

    <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
        <x-field label="Estudiantes del año" class="sm:w-56">
            <select name="origen" class="input" onchange="this.form.submit()">
                @foreach ($origenes as $o)
                    <option value="{{ $o->id }}" @selected($origen?->id === $o->id)>{{ $o->nombre }} ({{ mb_strtolower($o->estado->label()) }})</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Buscar" class="flex-1">
            <input type="search" name="q" value="{{ request('q') }}" class="input" placeholder="Cédula o nombre">
        </x-field>
        <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Buscar</button>
    </form>

    <x-aviso-sin-notas :anio="$origen" />

    @if ($origen && ! $origen->estaCerrado())
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            {{ $origen->nombre }} aún no está cerrado: las materias “cursando” se sugieren como aprobadas (nivel siguiente). Revise cada caso o cierre primero ese año.
        </div>
    @endif

    <form method="POST" action="{{ route('reinscripcion.store', $anio) }}" class="card overflow-hidden" x-data="{ todos: false }">
        @csrf
        <div class="card-header">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" class="checkbox" x-model="todos" @change="$root.querySelectorAll('input[name=\'matriculas[]\']').forEach(c => c.checked = todos)">
                Seleccionar todos los de esta página
            </label>
            <button class="btn btn-success" @disabled($totalCatedras === 0)><x-icon name="user-plus" class="size-4" /> Inscribir seleccionados con las materias sugeridas</button>
        </div>

        @if (! $pendientes || $pendientes->isEmpty())
            <x-empty icon="check-circle" title="No hay estudiantes pendientes de reinscripción">
                Todos los estudiantes de {{ $origen?->nombre ?? 'años anteriores' }} ya tienen inscripción en {{ $anio->nombre }} o fueron retirados.
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead>
                        <tr><th class="w-10"></th><th>Estudiante</th><th>Materias en {{ $origen->nombre }}</th><th>Sugeridas para {{ $anio->nombre }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pendientes as $matricula)
                            <tr class="align-top">
                                <td class="pt-4"><input type="checkbox" name="matriculas[]" value="{{ $matricula->id }}" class="checkbox"></td>
                                <td>
                                    <a href="{{ route('estudiantes.show', $matricula->estudiante) }}" class="font-semibold text-slate-900 hover:underline">{{ $matricula->estudiante->apellidos_nombres }}</a>
                                    <div class="text-xs text-slate-500">C.I. {{ $matricula->estudiante->cedula }} @if ($matricula->seccion) · Secc. {{ $matricula->seccion }} @endif</div>
                                    @if ($promovidos[$matricula->id] === true)
                                        <x-badge color="emerald" class="mt-1">Promovido de año</x-badge>
                                    @elseif ($promovidos[$matricula->id] === false)
                                        <x-badge color="rose" class="mt-1">Repite materias del año</x-badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex max-w-md flex-wrap gap-1">
                                        @foreach ($matricula->inscripciones as $inscripcion)
                                            <span class="chip" title="{{ $inscripcion->estado->label() }}">
                                                {{ $inscripcion->catedra->asignatura->nombre }} ({{ $inscripcion->catedra->nivel->nombre }})
                                                <x-badge :color="$inscripcion->estado->color()" class="px-1.5 py-0 text-[10px]">{{ $inscripcion->nota_definitiva !== null ? formato_nota($inscripcion->nota_definitiva) : (! $origen->tieneRegistroNotas() && $inscripcion->estado === \App\Enums\EstadoInscripcion::SinCalificar ? 'Cur' : mb_substr($inscripcion->estado->label(), 0, 3)) }}</x-badge>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <div class="flex max-w-md flex-wrap gap-1">
                                        @forelse ($sugerencias[$matricula->id] as $catedra)
                                            <span class="chip border-violet-200 bg-violet-50 text-violet-800">{{ $catedra->asignatura->nombre }} ({{ $catedra->nivel->nombre }}) {{ $catedra->seccion }}</span>
                                        @empty
                                            <span class="text-xs text-slate-400 italic">{{ $origen->tieneRegistroNotas() ? 'Sin sugerencias' : 'Año sin notas' }}: asigne manualmente</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('matriculas.create', [$matricula->estudiante, 'anio' => $anio->id]) }}" class="btn btn-sm btn-secondary whitespace-nowrap">Personalizar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $pendientes->links() }}</div>
        @endif
    </form>
</x-layouts.app>
