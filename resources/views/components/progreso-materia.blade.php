@props(['inscripcion', 'progreso', 'asistencia' => null, 'sinNotas' => false, 'abierto' => false])
@php
    $p = $progreso;
    $catedra = $inscripcion->catedra;
    $ancho = fn ($v) => max(0, min(100, $p['maximo'] > 0 ? $v * 100 / $p['maximo'] : 0));
    $situaciones = [
        'aprobada' => ['emerald', 'bg-emerald-500', 'Aprobada'],
        'asegurada' => ['emerald', 'bg-emerald-500', 'Aprobación asegurada'],
        'reprobada' => ['rose', 'bg-rose-500', 'Reprobada'],
        'imposible' => ['rose', 'bg-rose-500', 'Ya no alcanza la nota mínima'],
        'en_curso' => ['amber', 'bg-amber-400', 'En curso'],
        'sin_evaluaciones' => ['slate', 'bg-slate-300', 'Sin evaluaciones aún'],
    ];
    [$color, $barra, $texto] = $situaciones[$p['situacion']] ?? $situaciones['en_curso'];
    if ($inscripcion->estaRetirada()) {
        [$color, $barra, $texto] = ['amber', 'bg-slate-300', 'Retirada'];
    }
    $conNotas = ! $sinNotas && collect($p['lapsos'])->contains(fn ($l) => count($l['evaluaciones']) > 0);
@endphp
<li x-data="{ abierto: @js($abierto) }" @class(['opacity-60' => $inscripcion->estaRetirada()])>
    <button type="button" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50 sm:px-5" @click="abierto = ! abierto" :aria-expanded="abierto">
        <x-icon name="chevron-down" class="mt-1 size-4 shrink-0 text-slate-400 transition" ::class="abierto && 'rotate-180'" />
        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <div class="min-w-0">
                    <span class="font-semibold text-slate-900">{{ $catedra->asignatura->nombre }}</span>
                    <span class="text-xs text-slate-500">{{ $catedra->nivel->nombre }} · Sec. {{ $catedra->seccion }} · {{ $catedra->profesor?->name ?? 'Sin profesor' }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if ($asistencia)
                        <span class="text-slate-500" title="Asistencia">{{ $asistencia['porcentaje'] }}% asist.</span>
                    @endif
                    @if ($sinNotas)
                        <x-estado-inscripcion :inscripcion="$inscripcion" :sin-notas="true" />
                    @else
                        <x-badge :color="$color">{{ $texto }}</x-badge>
                    @endif
                </div>
            </div>

            @if ($sinNotas)
                <div class="text-xs text-slate-500">Año sin registro de notas.</div>
            @else
                {{-- Barra: puntos acumulados sobre la nota máxima, con la marca de la nota mínima --}}
                <div class="relative h-3 rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $p['maximo'] }}" aria-valuenow="{{ $p['puntos'] }}">
                    <div class="absolute inset-y-0 left-0 rounded-full bg-slate-200" style="width: {{ $ancho($p['maximoPosible']) }}%" title="Máximo que aún puede alcanzar"></div>
                    <div class="absolute inset-y-0 left-0 rounded-full {{ $barra }}" style="width: {{ $ancho($p['puntos']) }}%"></div>
                    <div class="absolute -inset-y-1 w-0.5 rounded bg-slate-900" style="left: {{ $ancho($p['minimo']) }}%" title="Nota mínima aprobatoria: {{ formato_nota($p['minimo']) }}"></div>
                </div>
                <div class="flex flex-wrap justify-between gap-x-3 text-xs text-slate-500">
                    <span>
                        <b class="text-slate-900">{{ formato_nota($p['puntos'], '0') }}</b> / {{ formato_nota($p['maximo']) }} pts
                        @if ($inscripcion->nota_definitiva === null && in_array($p['situacion'], ['en_curso', 'sin_evaluaciones'], true))
                            · faltan {{ formato_nota($p['faltan'], '0') }} para aprobar
                        @endif
                    </span>
                    <span>{{ round($p['evaluado'] * 100) }}% evaluado</span>
                </div>
            @endif
        </div>
    </button>

    {{-- Desglose --}}
    <div x-cloak x-show="abierto" x-transition.opacity class="border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:px-5 sm:pl-12">
        @if (! $conNotas)
            <p class="text-sm text-slate-500">{{ $sinNotas ? 'Este año escolar no tiene registro de notas.' : 'El profesor aún no ha registrado evaluaciones en esta cátedra.' }}</p>
        @else
            <div @class(['grid gap-3', 'lg:grid-cols-2' => count($p['lapsos']) === 2, 'lg:grid-cols-3' => count($p['lapsos']) >= 3])>
                @foreach ($p['lapsos'] as $lapso)
                    <div class="rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                            <span class="text-sm font-semibold text-slate-900">{{ $lapso['nombre'] }}</span>
                            <span class="flex items-center gap-1.5 text-xs">
                                @if ($lapso['cerrado']) <x-icon name="lock" class="size-3.5 text-slate-400" /> @endif
                                <b class="text-slate-900">{{ formato_nota($lapso['nota']) }}</b>
                            </span>
                        </div>
                        @if (empty($lapso['evaluaciones']))
                            <p class="px-3 py-3 text-xs text-slate-500">Sin evaluaciones.</p>
                        @else
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-slate-500">
                                        <th class="px-3 py-1.5 text-left font-medium">Evaluación</th>
                                        <th class="px-2 py-1.5 text-right font-medium">%</th>
                                        <th class="px-2 py-1.5 text-right font-medium">Nota</th>
                                        <th class="px-3 py-1.5 text-right font-medium">Aporta</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($lapso['evaluaciones'] as $e)
                                        <tr>
                                            <td class="px-3 py-1.5 text-slate-700">
                                                {{ $e['nombre'] }}
                                                @if ($e['fecha']) <span class="block text-[10px] text-slate-400">{{ $e['fecha']->format('d/m/Y') }}</span> @endif
                                            </td>
                                            <td class="px-2 py-1.5 text-right text-slate-500">{{ formato_nota($e['peso']) }}%</td>
                                            <td @class(['px-2 py-1.5 text-right font-semibold tabular-nums', 'text-rose-600' => $e['nota'] !== null && $e['nota'] < $p['minimo'], 'text-slate-900' => $e['nota'] === null || $e['nota'] >= $p['minimo']])>
                                                {{ $e['nota'] !== null ? formato_nota($e['nota']) : '—' }}
                                            </td>
                                            <td class="px-3 py-1.5 text-right text-slate-600 tabular-nums">{{ $e['aporte'] !== null ? '+'.formato_nota($e['aporte']) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="flex justify-between border-t border-slate-100 px-3 py-1.5 text-[11px] text-slate-500">
                                <span>Evaluado {{ formato_nota($lapso['peso_evaluado'], '0') }}% de {{ formato_nota($lapso['peso_total'], '0') }}%</span>
                                <span>Nota del lapso: <b class="text-slate-900">{{ formato_nota($lapso['nota']) }}</b></span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
                <span>Nota final (promedio de los lapsos): <b class="text-slate-900">{{ formato_nota($inscripcion->nota_final ?? ($p['evaluado'] >= 0.9999 ? $p['acumulado'] : null)) }}</b></span>
                <span>Definitiva: <b class="text-slate-900">{{ formato_nota($inscripcion->nota_definitiva) }}</b></span>
                <span>Máximo que aún puede alcanzar: <b class="text-slate-900">{{ formato_nota($p['maximoPosible']) }}</b></span>
            </div>
        @endif
    </div>
</li>
