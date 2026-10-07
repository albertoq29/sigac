<x-layouts.app title="Reaperturas de asistencia">
    <x-page-header title="Reaperturas de asistencia"
                   :subtitle="'Asistencias cerradas que fueron reabiertas, con su motivo y los cambios hechos'.($anio ? ' · '.$anio->nombre : '')" />

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reaperturas.index', ['ver' => 'pendientes']) }}" @class(['btn btn-sm', 'btn-primary' => $filtro === 'pendientes', 'btn-secondary' => $filtro !== 'pendientes'])>
            Sin revisar <span @class(['rounded-full px-1.5 text-[11px]', 'bg-white/20' => $filtro === 'pendientes', 'bg-slate-100' => $filtro !== 'pendientes'])>{{ $pendientes }}</span>
        </a>
        <a href="{{ route('reaperturas.index', ['ver' => 'todas']) }}" @class(['btn btn-sm', 'btn-primary' => $filtro === 'todas', 'btn-secondary' => $filtro !== 'todas'])>Todas</a>
    </div>

    <div class="space-y-4">
        @forelse ($reaperturas as $r)
            @php $catedra = $r->jornada->catedra; @endphp
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div class="min-w-0">
                        <a href="{{ route('asistencia.create', ['catedra' => $catedra, 'fecha' => $r->jornada->fecha->toDateString()]) }}" class="font-semibold text-slate-900 hover:underline">
                            {{ $catedra->nombre }}
                        </a>
                        <div class="text-xs text-slate-500">
                            Asistencia del {{ ucfirst($r->jornada->fecha->translatedFormat('l d/m/Y')) }} · Prof. {{ $catedra->profesor?->name ?? '—' }}
                        </div>
                    </div>
                    @unless ($r->revisada_at)
                        <form method="POST" action="{{ route('reaperturas.revisar', $r) }}">
                            @csrf
                            <button class="btn btn-sm btn-success"><x-icon name="check" class="size-4" /> Marcar como revisada</button>
                        </form>
                    @endunless
                </div>
                <ul>
                    @include('asistencia._reapertura', ['r' => $r, 'cambios' => $cambios[$r->id] ?? []])
                </ul>
            </div>
        @empty
            <div class="card">
                <x-empty icon="check-circle" :title="$filtro === 'pendientes' ? 'No hay reaperturas pendientes de revisar' : 'No hay reaperturas en este año escolar'" />
            </div>
        @endforelse
    </div>

    <div>{{ $reaperturas->links() }}</div>
</x-layouts.app>
