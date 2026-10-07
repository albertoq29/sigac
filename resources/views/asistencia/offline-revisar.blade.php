<x-layouts.app title="Revisar asistencias sin conexión">
    @php
        $validos = $items->whereIn('resultado', ['nueva', 'actualiza']);
        $colores = ['nueva' => 'emerald', 'actualiza' => 'sky', 'cerrada' => 'amber', 'error' => 'rose'];
        $textos = ['nueva' => 'Nueva', 'actualiza' => 'Actualiza', 'cerrada' => 'Cerrada: se omite', 'error' => 'No se puede registrar'];
    @endphp

    <x-page-header title="Revisar asistencias" :back="route('offline.index')"
                   :subtitle="'Código de '.($carga['n'] ?? 'usuario desconocido').' · '.$items->count().' día(s) de asistencia · '.$validos->count().' se registrarán'" />

    @if ($generadoPorOtro)
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
            <div>Este código fue generado por <b>{{ $carga['n'] ?? 'otro usuario' }}</b>. Solo se registrarán las cátedras en las que usted tiene permiso.</div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @foreach ($items as $item)
                <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                    <div class="w-24 shrink-0">
                        <div class="text-sm font-bold text-slate-900">{{ $item['fecha']?->format('d/m/Y') ?? '—' }}</div>
                        <div class="text-xs text-slate-500">{{ $item['fecha'] ? ucfirst($item['fecha']->translatedFormat('l')) : '' }}</div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-slate-900">{{ $item['catedra']?->nombre ?? 'Cátedra desconocida' }}</div>
                        <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                            <x-badge color="emerald">P {{ $item['conteo']['P'] }}</x-badge>
                            <x-badge color="rose">A {{ $item['conteo']['A'] }}</x-badge>
                            <x-badge color="amber">R {{ $item['conteo']['R'] }}</x-badge>
                            <x-badge color="sky">J {{ $item['conteo']['J'] }}</x-badge>
                            @if ($item['omitidos'])
                                <span class="text-slate-500">· {{ $item['omitidos'] }} estudiante(s) que ya no están en la cátedra se omiten</span>
                            @endif
                        </div>
                        @if ($item['mensaje'])
                            <div class="mt-1 text-xs text-slate-600">{{ $item['mensaje'] }}
                                @if ($item['resultado'] === 'actualiza') ({{ $item['diferencias'] }} cambio(s) respecto a lo registrado) @endif
                            </div>
                        @endif
                    </div>
                    <x-badge :color="$colores[$item['resultado']]" class="self-start sm:self-center">{{ $textos[$item['resultado']] }}</x-badge>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($validos->isNotEmpty())
        <form method="POST" action="{{ route('offline.importar') }}" class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
              data-confirmar="Se registrarán {{ $validos->count() }} día(s) de asistencia.">
            @csrf
            <input type="hidden" name="codigo" value="{{ $codigo }}">
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="hidden" name="cerrar" value="0">
                <input type="checkbox" name="cerrar" value="1" class="checkbox mt-0.5" checked>
                <span><b>Cerrar las asistencias al registrarlas</b><span class="block text-xs text-slate-500">Para cambiarlas después habrá que reabrirlas con una justificación.</span></span>
            </label>
            <button class="btn btn-success"><x-icon name="check" class="size-4" /> Registrar {{ $validos->count() }} asistencia(s)</button>
        </form>
    @else
        <div class="card"><x-empty icon="exclamation" title="No hay asistencias que se puedan registrar con este código" /></div>
    @endif
</x-layouts.app>
