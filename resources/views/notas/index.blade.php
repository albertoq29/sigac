<x-layouts.app :title="'Notas · '.$catedra->nombre">
    @php
        $esControl = auth()->user()->esControl();
        $lapsosCerrados = $catedra->lapsos->every->estaCerrado();
        $anioAbierto = $catedra->anioEscolar->permiteRegistros();
    @endphp

    <x-catedra-encabezado :catedra="$catedra">
        <x-slot:acciones>
            <a href="{{ route('documentos.acta', $catedra) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" class="size-4" /> Acta de notas</a>
        </x-slot:acciones>
    </x-catedra-encabezado>

    {{-- Lapsos --}}
    <div @class(['grid gap-4', 'md:grid-cols-2' => $catedra->lapsos->count() === 2, 'md:grid-cols-3' => $catedra->lapsos->count() >= 3, 'max-w-xl' => $catedra->lapsos->count() === 1])>
        @foreach ($catedra->lapsos as $lapso)
            @php $peso = $lapso->pesoTotal(); @endphp
            <div class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-base font-bold text-slate-900">{{ $lapso->nombre }}</div>
                        <div class="text-xs text-slate-500">{{ $lapso->evaluaciones->count() }} evaluación(es) · {{ formato_nota($peso, '0') }}% asignado</div>
                    </div>
                    @if ($lapso->estaCerrado())
                        <x-badge color="emerald"><x-icon name="lock" class="size-3" /> Cerrado</x-badge>
                    @else
                        <x-badge color="sky">Abierto</x-badge>
                    @endif
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div @class(['h-full rounded-full', 'bg-emerald-500' => abs($peso - 100) < 0.01, 'bg-amber-400' => abs($peso - 100) >= 0.01]) style="width: {{ min(100, $peso) }}%"></div>
                </div>
                @if ($lapso->estaCerrado())
                    <p class="mt-2 text-xs text-slate-500">Cerrado el {{ $lapso->cerrado_at->format('d/m/Y H:i') }} por {{ $lapso->cerradoPor?->name ?? '—' }}</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('notas.lapso', [$catedra, $lapso->numero]) }}" class="btn btn-sm btn-primary">
                        <x-icon name="{{ $puedeRegistrar && ! $lapso->estaCerrado() && ! $catedra->notasCerradas() ? 'pencil' : 'eye' }}" class="size-4" />
                        {{ $puedeRegistrar && ! $lapso->estaCerrado() && ! $catedra->notasCerradas() ? 'Cargar notas' : 'Ver planilla' }}
                    </a>
                    @if ($esControl && $anioAbierto && $lapso->estaCerrado() && ! $catedra->notasCerradas())
                        <form method="POST" action="{{ route('notas.reabrir-lapso', [$catedra, $lapso->numero]) }}" data-confirmar="¿Reabrir el {{ $lapso->nombre }}?">
                            @csrf
                            <button class="btn btn-sm btn-secondary"><x-icon name="unlock" class="size-4" /> Reabrir</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Resumen y cierre --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">Resumen de notas y cierre</h2>
                <p class="mt-0.5 text-xs text-slate-500">Nota final = promedio de los lapsos. Definitiva {{ \App\Models\Ajuste::redondearDefinitiva() ? 'redondeada al entero' : 'con decimales' }}; aprueba con {{ formato_nota($notaMinima) }} o más.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($catedra->notasCerradas())
                    <span class="text-xs text-slate-500">Cierre: {{ $catedra->notas_cerradas_at->format('d/m/Y H:i') }} · {{ $catedra->notasCerradasPor?->name ?? '—' }}</span>
                    @if ($esControl && $anioAbierto)
                        <form method="POST" action="{{ route('notas.reabrir', $catedra) }}" data-confirmar="¿Reabrir el cierre de notas? Las definitivas se recalcularán al cerrar de nuevo.">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-secondary"><x-icon name="unlock" class="size-4" /> Reabrir cierre</button>
                        </form>
                    @endif
                @elseif ($puedeRegistrar)
                    <form method="POST" action="{{ route('notas.cerrar', $catedra) }}" data-confirmar="Se calcularán las notas definitivas y quedarán bloqueadas. ¿Realizar el cierre de notas?">
                        @csrf
                        <button class="btn btn-sm btn-success" @disabled(! $lapsosCerrados) title="{{ $lapsosCerrados ? '' : 'Primero cierre todos los lapsos' }}">
                            <x-icon name="lock" class="size-4" /> Realizar cierre de notas
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if ($inscripciones->isEmpty())
            <x-empty icon="users" title="Sin estudiantes" />
        @else
            <div class="overflow-x-auto">
                <table class="tabla tabla-movil">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Estudiante</th>
                            @foreach ($catedra->lapsos as $lapso)
                                <th class="text-center">{{ $lapso->nombre }}</th>
                            @endforeach
                            <th class="text-center">Nota final</th>
                            <th class="text-center">Definitiva</th>
                            <th>Resultado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inscripciones as $i => $inscripcion)
                            @php
                                $r = $resumen[$inscripcion->id];
                                $cerrada = $catedra->notasCerradas() || ! $inscripcion->estaCursando();
                                $definitiva = $cerrada ? $inscripcion->nota_definitiva : $r['definitiva'];
                            @endphp
                            <tr @class(['opacity-55' => $inscripcion->estaRetirada()])>
                                <td class="ocultar-movil text-xs text-slate-400">{{ $i + 1 }}</td>
                                <td class="celda-titulo font-semibold text-slate-900">{{ $inscripcion->estudiante->apellidos_nombres }}</td>
                                @foreach ($catedra->lapsos as $lapso)
                                    <td class="text-center tabular-nums" data-label="{{ $lapso->nombre }}">{{ formato_nota($r['lapsos'][$lapso->numero] ?? null) }}</td>
                                @endforeach
                                <td class="text-center tabular-nums" data-label="Nota final">{{ formato_nota($cerrada ? $inscripcion->nota_final : $r['final']) }}</td>
                                <td data-label="Definitiva" @class(['text-center text-base font-bold tabular-nums', 'text-rose-600' => $definitiva !== null && $definitiva < $notaMinima, 'text-slate-900' => $definitiva === null || $definitiva >= $notaMinima])>
                                    {{ formato_nota($definitiva) }}
                                </td>
                                <td data-label="Resultado">
                                    @if ($inscripcion->estaCursando())
                                        <span class="text-xs text-slate-500">{{ $r['completa'] ? 'Preliminar' : 'En curso' }}</span>
                                    @else
                                        <x-estado-inscripcion :inscripcion="$inscripcion" :sin-notas="! $catedra->anioEscolar->tieneRegistroNotas()" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
