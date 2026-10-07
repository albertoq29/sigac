<x-layouts.app title="Inicio">
    <x-page-header title="Panel de Control de Estudios"
                   :subtitle="$anio ? 'Año escolar '.$anio->nombre.' · '.$anio->estado->label() : 'Aún no hay años escolares registrados'">
        <x-slot:actions>
            <a href="{{ route('estudiantes.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Nuevo estudiante</a>
            <a href="{{ route('catedras.index') }}" class="btn btn-secondary"><x-icon name="music" class="size-4" /> Cátedras</a>
        </x-slot:actions>
    </x-page-header>

    @unless ($anio)
        <div class="card">
            <x-empty icon="calendar" title="Configure el primer año escolar">
                Cree un año escolar para poder registrar cátedras, inscripciones, asistencias y notas.
                <div class="mt-4"><a href="{{ route('anios.create') }}" class="btn btn-primary">Crear año escolar</a></div>
            </x-empty>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Estudiantes activos" :value="$kpis['activos']" :hint="$kpis['inactivos'].' inactivos'" icon="users" color="emerald" />
            <x-stat label="Inscritos en el año" :value="$kpis['inscritos']" :hint="$kpis['retirados'].' retirados en '.$anio->nombre" icon="academic" color="sky" />
            <x-stat label="Cátedras" :value="$kpis['catedras']" :hint="$kpis['profesores'].' profesores activos'" icon="music" color="violet" />
            <x-stat label="Asistencia promedio" :value="$kpis['asistencia'] !== null ? $kpis['asistencia'].'%' : '—'" :hint="$kpis['jornadas'].' jornadas registradas'" icon="clipboard" color="amber" />
        </div>

        @php $hayAlertas = collect($alertas)->filter()->isNotEmpty(); @endphp
        @if ($hayAlertas)
            <div class="grid gap-3 md:grid-cols-3">
                @if ($alertas['sin_profesor'])
                    <a href="{{ route('catedras.index', ['sin_profesor' => 1]) }}" class="card flex items-center gap-3 border-amber-200 bg-amber-50/60 p-4 hover:bg-amber-50">
                        <x-icon name="exclamation" class="size-6 text-amber-600" />
                        <div class="text-sm"><b>{{ $alertas['sin_profesor'] }}</b> cátedra(s) sin profesor asignado</div>
                    </a>
                @endif
                @if ($alertas['reaperturas'])
                    <a href="{{ route('reaperturas.index') }}" class="card flex items-center gap-3 border-amber-200 bg-amber-50/60 p-4 hover:bg-amber-50">
                        <x-icon name="unlock" class="size-6 text-amber-600" />
                        <div class="text-sm"><b>{{ $alertas['reaperturas'] }}</b> reapertura(s) de asistencia por revisar</div>
                    </a>
                @endif
                @if ($alertas['sin_alumnos'])
                    <a href="{{ route('catedras.index') }}" class="card flex items-center gap-3 p-4 hover:bg-slate-50">
                        <x-icon name="info" class="size-6 text-slate-500" />
                        <div class="text-sm"><b>{{ $alertas['sin_alumnos'] }}</b> cátedra(s) sin estudiantes</div>
                    </a>
                @endif
                @if ($alertas['sin_cierre'])
                    <a href="{{ route('anios.show', $anio) }}" class="card flex items-center gap-3 p-4 hover:bg-slate-50">
                        <x-icon name="lock" class="size-6 text-slate-500" />
                        <div class="text-sm"><b>{{ $alertas['sin_cierre'] }}</b> cátedra(s) con notas sin cerrar</div>
                    </a>
                @endif
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card overflow-hidden lg:col-span-2">
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <span class="relative flex size-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-2.5 rounded-full bg-emerald-500"></span></span>
                        <h2 class="card-title">Actividad docente reciente</h2>
                    </div>
                    <span class="text-xs text-slate-500">Últimos pases de lista</span>
                </div>
                @if ($recientes->isEmpty())
                    <x-empty icon="clipboard" title="Todavía no hay asistencias registradas en este año" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($recientes as $jornada)
                            <li class="flex items-center gap-4 px-5 py-3">
                                <div class="w-14 shrink-0 text-center">
                                    <div class="text-lg leading-none font-bold text-slate-900">{{ $jornada->fecha->format('d') }}</div>
                                    <div class="text-[11px] font-semibold text-slate-500 uppercase">{{ $jornada->fecha->translatedFormat('M') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('asistencia.create', ['catedra' => $jornada->catedra, 'fecha' => $jornada->fecha->toDateString()]) }}" class="block truncate text-sm font-semibold text-slate-900 hover:underline">
                                        {{ $jornada->catedra->nombre }}
                                    </a>
                                    <div class="truncate text-xs text-slate-500">
                                        {{ $jornada->registradoPor?->name ?? $jornada->catedra->profesor?->name ?? 'Sin docente' }}
                                        · registrada {{ $jornada->created_at->diffForHumans() }}
                                        · {{ $jornada->estaCerrada() ? 'cerrada' : 'abierta' }}
                                    </div>
                                </div>
                                <div class="text-right text-xs">
                                    <div class="font-semibold text-slate-900">{{ $jornada->asistencias_count - $jornada->ausentes_count }}/{{ $jornada->asistencias_count }}</div>
                                    <div class="text-slate-500">presentes</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card p-5">
                    <h2 class="card-title mb-3">Accesos rápidos</h2>
                    <div class="grid gap-2 text-sm">
                        <a href="{{ route('estudiantes.index', ['estado' => 'activo']) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 hover:bg-slate-50">
                            <span class="flex items-center gap-2"><x-icon name="users" class="size-4 text-slate-500" /> Estudiantes activos</span>
                            <x-icon name="arrow-right" class="size-4 text-slate-400" />
                        </a>
                        <a href="{{ route('anios.show', $anio) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 hover:bg-slate-50">
                            <span class="flex items-center gap-2"><x-icon name="calendar" class="size-4 text-slate-500" /> Año escolar {{ $anio->nombre }}</span>
                            <x-icon name="arrow-right" class="size-4 text-slate-400" />
                        </a>
                        <a href="{{ route('estadisticas') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 hover:bg-slate-50">
                            <span class="flex items-center gap-2"><x-icon name="chart" class="size-4 text-slate-500" /> Estadísticas</span>
                            <x-icon name="arrow-right" class="size-4 text-slate-400" />
                        </a>
                        <a href="{{ route('usuarios.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 hover:bg-slate-50">
                            <span class="flex items-center gap-2"><x-icon name="user" class="size-4 text-slate-500" /> Profesores</span>
                            <x-icon name="arrow-right" class="size-4 text-slate-400" />
                        </a>
                    </div>
                </div>

                <div class="card p-5 text-sm text-slate-600">
                    <h2 class="card-title mb-2">Ciclo del año escolar</h2>
                    <ol class="list-decimal space-y-1.5 pl-5">
                        <li>Crear el año y sus cátedras (profesor, trimestral o semestral).</li>
                        <li>Inscribir estudiantes (quedan <b>activos</b>) y asignarles cátedras.</li>
                        <li>Profesores pasan asistencia y cargan notas por lapso.</li>
                        <li>Cierre de notas de cada cátedra.</li>
                        <li><b>Cierre del año</b>: todo queda como historial y se reinscribe para el año siguiente.</li>
                    </ol>
                </div>
            </div>
        </div>
    @endunless
</x-layouts.app>
