<x-layouts.app title="Cátedras">
    @php $esControl = auth()->user()->esControl(); @endphp
    <x-page-header :title="$esControl ? 'Cátedras' : 'Mis cátedras'"
                   :subtitle="$anio ? 'Año escolar '.$anio->nombre.' · '.$catedras->count().' cátedra(s)' : 'Sin año escolar'">
        <x-slot:actions>
            @if ($esControl && $anio && ! $anio->estaCerrado())
                <a href="{{ route('catedras.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva cátedra</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($esControl)
        <form method="GET" class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <select name="asignatura" class="input" onchange="this.form.submit()">
                <option value="">Todas las asignaturas</option>
                @foreach ($asignaturas as $asignatura)
                    <option value="{{ $asignatura->id }}" @selected(request('asignatura') == $asignatura->id)>{{ $asignatura->nombre }}</option>
                @endforeach
            </select>
            <select name="nivel" class="input" onchange="this.form.submit()">
                <option value="">Todos los niveles</option>
                @foreach ($niveles as $nivel)
                    <option value="{{ $nivel->id }}" @selected(request('nivel') == $nivel->id)>{{ $nivel->nombre }}</option>
                @endforeach
            </select>
            <select name="profesor" class="input" onchange="this.form.submit()">
                <option value="">Todos los profesores</option>
                @foreach ($profesores as $profesor)
                    <option value="{{ $profesor->id }}" @selected(request('profesor') == $profesor->id)>{{ $profesor->name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="sin_profesor" value="1" class="checkbox" @checked(request('sin_profesor')) onchange="this.form.submit()"> Sin profesor
            </label>
            @if (request()->hasAny(['asignatura', 'nivel', 'profesor', 'sin_profesor']))
                <a href="{{ route('catedras.index') }}" class="btn btn-ghost">Limpiar filtros</a>
            @endif
        </form>
    @endif

    @if ($catedras->isEmpty())
        <div class="card">
            <x-empty icon="music" title="No hay cátedras">
                @if ($esControl && $anio && ! $anio->estaCerrado())
                    Cree las cátedras del año o cópielas de un año anterior desde <a class="link" href="{{ route('anios.show', $anio) }}">Años escolares</a>.
                @endif
            </x-empty>
        </div>
    @else
        @php
            $grupos = $catedras
                ->groupBy(fn ($c) => $c->nivel->grupo())
                ->sortBy(fn ($g, $nombre) => array_search($nombre, \App\Models\Nivel::GRUPOS, true))
                // Años: por año (1er, 2do...). Niveles y preparatorio: cada asignatura junta (Flauta Dulce I, II, III...).
                ->map(fn ($g, $nombre) => $g->sortBy($nombre === 'Años'
                    ? [
                        fn ($a, $b) => $a->nivel->orden <=> $b->nivel->orden,
                        fn ($a, $b) => strcmp($a->asignatura->nombre, $b->asignatura->nombre),
                        fn ($a, $b) => strcmp($a->seccion, $b->seccion),
                    ]
                    : [
                        fn ($a, $b) => strcmp($a->asignatura->nombre, $b->asignatura->nombre),
                        fn ($a, $b) => $a->nivel->orden <=> $b->nivel->orden,
                        fn ($a, $b) => strcmp($a->seccion, $b->seccion),
                    ])->values());
            $descripciones = [
                'Preparatorio' => 'Cátedras del nivel preparatorio',
                'Años' => 'Años de estudio: se aprueban completos para pasar al año siguiente',
                'Niveles' => 'Instrumentos, práctica e IMI por niveles',
            ];
        @endphp

        {{-- Accesos rápidos a cada grupo --}}
        <nav class="flex flex-wrap gap-2 no-print">
            @foreach ($grupos as $nombre => $grupo)
                <a href="#grupo-{{ \Illuminate\Support\Str::slug($nombre) }}" class="btn btn-sm btn-secondary">
                    {{ $nombre }} <span class="rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-600">{{ $grupo->count() }}</span>
                </a>
            @endforeach
        </nav>

        @foreach ($grupos as $nombre => $grupo)
            <section id="grupo-{{ \Illuminate\Support\Str::slug($nombre) }}" class="card scroll-mt-20 overflow-hidden">
                <div class="card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">{{ $nombre }}</h2>
                        <p class="text-xs text-slate-500">{{ $descripciones[$nombre] ?? '' }}</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ $grupo->count() }} cátedra(s) · {{ $grupo->sum('alumnos_count') }} alumnos</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="tabla tabla-movil">
                        <thead>
                            <tr>
                                <th>Cátedra</th>
                                @if ($esControl) <th>Profesor</th> @endif
                                <th>Horario</th>
                                <th class="text-center">Alumnos</th>
                                <th class="text-center">Jornadas</th>
                                <th>Notas</th>
                                <th class="text-right">Ir a</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $porAsignatura = $nombre === 'Niveles' ? $grupo->countBy('asignatura_id') : collect(); @endphp
                            @foreach ($grupo as $i => $catedra)
                                {{-- Subtítulo por asignatura dentro de Niveles --}}
                                @if ($nombre === 'Niveles' && ($i === 0 || $grupo[$i - 1]->asignatura_id !== $catedra->asignatura_id))
                                    <tr class="bg-slate-50">
                                        <td colspan="{{ $esControl ? 7 : 6 }}" class="celda-titulo border-t border-slate-200 bg-slate-50 py-2">
                                            <span class="text-xs font-bold tracking-wide text-slate-700 uppercase">{{ $catedra->asignatura->nombre }}</span>
                                            <span class="ml-1 text-xs text-slate-400">{{ $porAsignatura[$catedra->asignatura_id] }} cátedra(s)</span>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="celda-titulo">
                                        <a href="{{ route('catedras.show', $catedra) }}" class="font-semibold text-slate-900 hover:underline">{{ $catedra->asignatura->nombre }}</a>
                                        <div class="text-xs text-slate-500">{{ $catedra->nivel->nombre }} · Sección {{ $catedra->seccion }} · {{ $catedra->regimen->descripcion($catedra->cantidad_lapsos) }}</div>
                                    </td>
                                    @if ($esControl)
                                    <td data-label="Profesor" class="text-sm">{!! $catedra->profesor ? e($catedra->profesor->name) : '<span class="text-amber-600 font-medium">Sin asignar</span>' !!}</td>
                                    @endif
                                    <td data-label="Horario" class="text-xs whitespace-nowrap text-slate-500">{{ $catedra->horario ?? '—' }}</td>
                                    <td data-label="Alumnos" class="text-center font-semibold">{{ $catedra->alumnos_count }}</td>
                                    <td data-label="Jornadas" class="text-center">{{ $catedra->jornadas_count }}</td>
                                    <td data-label="Lapsos">
                                        @if ($catedra->notasCerradas())
                                            <x-badge color="slate"><x-icon name="lock" class="size-3" /> Cerradas</x-badge>
                                        @else
                                            <div class="flex gap-1">
                                                @foreach ($catedra->lapsos as $lapso)
                                                    <x-badge :color="$lapso->estaCerrado() ? 'emerald' : 'sky'" title="{{ $catedra->regimen->nombreLapso($lapso->numero) }}">{{ ['', 'I', 'II', 'III', 'IV'][$lapso->numero] ?? $lapso->numero }} {{ $lapso->estaCerrado() ? '✓' : '' }}</x-badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="acciones-movil text-right whitespace-nowrap">
                                        <div class="grid w-full grid-cols-2 gap-2 md:flex md:w-auto md:justify-end md:gap-1">
                                            <a href="{{ route('asistencia.create', $catedra) }}" class="btn btn-sm btn-secondary text-emerald-700"><x-icon name="clipboard" class="size-4" /> Asistencia</a>
                                            <a href="{{ route('notas.index', $catedra) }}" class="btn btn-sm btn-secondary"><x-icon name="academic" class="size-4" /> Notas</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</x-layouts.app>
