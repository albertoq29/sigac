{{-- Una reapertura de asistencia: motivo y cambios. Variables: $r (ReaperturaJornada), $cambios --}}
<li class="space-y-2 px-4 py-3 text-sm sm:px-5">
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-semibold text-slate-900">{{ $r->reabiertaPor?->name ?? '—' }}</span>
        <span class="text-xs text-slate-500">reabrió el {{ $r->created_at->format('d/m/Y h:i a') }}</span>
        @if ($r->estaAbierta())
            <x-badge color="amber">Sigue abierta</x-badge>
        @else
            <x-badge color="slate">Cerrada de nuevo {{ $r->recerrada_at->format('d/m/Y h:i a') }}</x-badge>
        @endif
        @if ($r->revisada_at)
            <x-badge color="emerald">Revisada por {{ $r->revisadaPor?->name ?? 'Control de Estudios' }}</x-badge>
        @endif
    </div>
    <div class="rounded-lg bg-slate-50 px-3 py-2 text-slate-700"><span class="text-xs font-semibold text-slate-500 uppercase">Motivo:</span> {{ $r->motivo }}</div>
    @if (empty($cambios))
        <div class="text-xs text-slate-500">{{ $r->estaAbierta() ? 'Aún no se han hecho cambios.' : 'No se hicieron cambios.' }}</div>
    @else
        <div>
            <div class="mb-1 text-xs font-semibold text-slate-500 uppercase">{{ $r->estaAbierta() ? 'Cambios hasta ahora' : 'Cambios realizados' }} ({{ count($cambios) }})</div>
            <ul class="space-y-1">
                @foreach ($cambios as $cambio)
                    @php
                        $antes = \App\Enums\EstadoAsistencia::tryFrom((string) $cambio['antes']);
                        $despues = \App\Enums\EstadoAsistencia::tryFrom((string) $cambio['despues']);
                    @endphp
                    <li class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="font-medium text-slate-800">{{ $cambio['estudiante'] }}:</span>
                        @if ($antes) <x-badge :color="$antes->color()">{{ $antes->label() }}</x-badge> @else <span class="text-slate-400">sin registro</span> @endif
                        <x-icon name="arrow-right" class="size-3.5 text-slate-400" />
                        @if ($despues) <x-badge :color="$despues->color()">{{ $despues->label() }}</x-badge> @else <span class="text-slate-400">sin registro</span> @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</li>
