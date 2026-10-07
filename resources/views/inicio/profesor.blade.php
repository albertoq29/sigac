<x-layouts.app title="Mis cátedras">
    <x-page-header :title="'Bienvenido(a), '.auth()->user()->name"
                   :subtitle="$anio ? 'Sus cátedras del año escolar '.$anio->nombre : 'No hay año escolar activo'" />

    @if ($catedras->isNotEmpty())
        <a href="{{ route('offline.index') }}" class="card flex items-center gap-3 p-4 hover:bg-slate-50">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white"><x-icon name="sin-conexion" class="size-5" /></div>
            <div class="min-w-0 flex-1 text-sm">
                <div class="font-semibold text-slate-900">¿Sin internet en el aula?</div>
                <div class="text-slate-500">Descargue la asistencia sin conexión y suba luego el código .txt.</div>
            </div>
            <x-icon name="arrow-right" class="size-4 text-slate-400" />
        </a>
    @endif

    @if ($catedras->isEmpty())
        <div class="card">
            <x-empty icon="music" title="No tiene cátedras asignadas en este año escolar">
                Si cree que es un error, comuníquese con Control de Estudios.
            </x-empty>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($catedras as $catedra)
                <div class="card flex flex-col">
                    <div class="flex-1 space-y-3 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-bold text-slate-900">{{ $catedra->asignatura->nombre }}</div>
                                <div class="text-sm text-slate-500">{{ $catedra->nivel->nombre }} · Sección {{ $catedra->seccion }}</div>
                            </div>
                            @if ($catedra->notasCerradas())
                                <x-badge color="slate"><x-icon name="lock" class="size-3" /> Notas cerradas</x-badge>
                            @else
                                <x-badge color="sky">{{ $catedra->regimen->descripcion($catedra->cantidad_lapsos) }}</x-badge>
                            @endif
                        </div>
                        <dl class="grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-slate-500">Alumnos</dt><dd class="text-lg font-bold text-slate-900">{{ $catedra->alumnos_count }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-slate-500">Jornadas</dt><dd class="text-lg font-bold text-slate-900">{{ $catedra->jornadas_count }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-slate-500">Última</dt><dd class="text-sm font-bold text-slate-900">{{ $catedra->jornadas_max_fecha ? \Illuminate\Support\Carbon::parse($catedra->jornadas_max_fecha)->format('d/m') : '—' }}</dd></div>
                        </dl>
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            @foreach ($catedra->lapsos as $lapso)
                                <span class="chip">{{ $catedra->regimen->nombreLapso($lapso->numero) }}: {{ $lapso->estaCerrado() ? 'cerrado' : 'abierto' }}</span>
                            @endforeach
                            @if ($catedra->horario)
                                <span class="chip"><x-icon name="calendar" class="size-3" /> {{ $catedra->horario }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="grid grid-cols-3 border-t border-slate-100 text-xs font-semibold">
                        <a href="{{ route('asistencia.create', $catedra) }}" class="flex flex-col items-center gap-1 px-2 py-3 text-emerald-700 hover:bg-emerald-50"><x-icon name="clipboard" class="size-5" /> Asistencia</a>
                        <a href="{{ route('notas.index', $catedra) }}" class="flex flex-col items-center gap-1 border-x border-slate-100 px-2 py-3 text-slate-700 hover:bg-slate-50"><x-icon name="academic" class="size-5" /> Notas</a>
                        <a href="{{ route('catedras.show', $catedra) }}" class="flex flex-col items-center gap-1 px-2 py-3 text-slate-700 hover:bg-slate-50"><x-icon name="users" class="size-5" /> Alumnos</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
