<x-layouts.app :title="'Asistencia · '.$catedra->nombre">
    <x-catedra-encabezado :catedra="$catedra" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
                <x-field label="Fecha de la jornada" class="w-full sm:w-auto sm:flex-1">
                    <input type="date" name="fecha" class="input" value="{{ $fecha->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()">
                </x-field>
                <div class="pb-2 text-sm text-slate-500">
                    {{ ucfirst($fecha->translatedFormat('l d \d\e F \d\e Y')) }}
                    @if ($jornada?->estaCerrada())
                        <x-badge color="slate" class="ml-1"><x-icon name="lock" class="size-3" /> Cerrada</x-badge>
                    @elseif ($jornada)
                        <x-badge color="emerald" class="ml-1">Registrada · abierta</x-badge>
                    @endif
                </div>
            </form>

            {{-- Estado del día: cerrar / reabrir --}}
            @if ($jornada)
                @php $reaperturaAbierta = $jornada->reaperturas->first(fn ($r) => $r->estaAbierta()); @endphp

                @if ($jornada->estaCerrada())
                    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3 text-sm">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white"><x-icon name="lock" class="size-5" /></div>
                            <div>
                                <div class="font-semibold text-slate-900">Asistencia del día cerrada</div>
                                <div class="text-xs text-slate-500">El {{ $jornada->cerrada_at->format('d/m/Y \a \l\a\s h:i a') }} por {{ $jornada->cerradaPor?->name ?? '—' }}</div>
                            </div>
                        </div>
                        @if ($puedeGestionar)
                            <button type="button" class="btn btn-secondary" @click="$dispatch('abrir-modal', 'reabrir-jornada')">
                                <x-icon name="unlock" class="size-4" /> Reabrir
                            </button>
                        @endif
                    </div>
                @elseif ($puedeRegistrar)
                    @if ($reaperturaAbierta)
                        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <x-icon name="unlock" class="mt-0.5 size-5 shrink-0" />
                            <div>
                                <b>Asistencia reabierta</b> por {{ $reaperturaAbierta->reabiertaPor?->name ?? '—' }} el {{ $reaperturaAbierta->created_at->format('d/m/Y h:i a') }}.
                                <div class="mt-0.5">Motivo: «{{ $reaperturaAbierta->motivo }}»</div>
                                <div class="mt-1 text-xs text-amber-800">Los cambios quedan registrados para Control de Estudios. Ciérrela de nuevo al terminar.</div>
                            </div>
                        </div>
                    @endif
                    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-slate-600">
                            <b class="text-slate-900">Asistencia abierta.</b> Ciérrela cuando termine; para cambiarla después habrá que reabrirla con una justificación.
                        </div>
                        <form method="POST" action="{{ route('jornadas.cerrar', $jornada) }}" data-confirmar="¿Cerrar la asistencia del {{ $jornada->fecha->format('d/m/Y') }}? Para modificarla luego deberá justificar la reapertura.">
                            @csrf
                            <button class="btn btn-primary w-full whitespace-nowrap sm:w-auto"><x-icon name="lock" class="size-4" /> Cerrar asistencia del día</button>
                        </form>
                    </div>
                @endif

                @if ($jornada->estaCerrada() && $puedeGestionar)
                    <x-modal name="reabrir-jornada" title="Reabrir la asistencia del {{ $jornada->fecha->format('d/m/Y') }}">
                        <form method="POST" action="{{ route('jornadas.reabrir', $jornada) }}" class="space-y-4">
                            @csrf
                            <p class="text-sm text-slate-600">
                                Explique por qué necesita modificar esta asistencia. <b>Control de Estudios verá el motivo y los cambios que se hagan</b>.
                            </p>
                            <x-field label="Motivo de la reapertura" name="motivo" required>
                                <textarea name="motivo" class="input" rows="4" minlength="10" maxlength="1000" required
                                          placeholder="Ej: marqué ausente por error a un estudiante que llegó tarde.">{{ old('motivo') }}</textarea>
                            </x-field>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-secondary" @click="abierto = false">Cancelar</button>
                                <button class="btn btn-warning"><x-icon name="unlock" class="size-4" /> Reabrir</button>
                            </div>
                        </form>
                    </x-modal>
                    @error('motivo')
                        <div x-data x-init="$nextTick(() => $dispatch('abrir-modal', 'reabrir-jornada'))"></div>
                    @enderror
                @endif
            @endif

            @if ($inscripciones->isEmpty())
                <div class="card"><x-empty icon="users" title="La cátedra no tiene estudiantes" /></div>
            @elseif ($puedeRegistrar)
                <form method="POST" action="{{ route('asistencia.store', $catedra) }}" class="card"
                      x-data="paseDeLista(@js($estados))">
                    @csrf
                    <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">

                    <div class="space-y-3 border-b border-slate-100 p-4 sm:flex sm:items-center sm:justify-between sm:space-y-0 sm:px-5">
                        <div class="grid grid-cols-4 gap-1.5 text-center text-xs font-semibold sm:flex sm:gap-2">
                            <span class="badge badge-emerald justify-center">P <span x-text="cuenta('P')"></span></span>
                            <span class="badge badge-rose justify-center">A <span x-text="cuenta('A')"></span></span>
                            <span class="badge badge-amber justify-center">R <span x-text="cuenta('R')"></span></span>
                            <span class="badge badge-sky justify-center">J <span x-text="cuenta('J')"></span></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex">
                            <button type="button" class="btn btn-sm btn-secondary" @click="todos('P')">Todos presentes</button>
                            <button type="button" class="btn btn-sm btn-secondary" @click="todos('A')">Todos ausentes</button>
                        </div>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($inscripciones as $i => $inscripcion)
                            <li class="space-y-2.5 px-4 py-3 sm:flex sm:items-center sm:gap-3 sm:space-y-0 sm:px-5">
                                <div class="flex min-w-0 flex-1 items-start gap-2">
                                    <span class="w-6 shrink-0 pt-0.5 text-xs text-slate-400">{{ $i + 1 }}</span>
                                    <div class="min-w-0">
                                        <div class="text-sm leading-snug font-semibold text-slate-900 sm:truncate">{{ $inscripcion->estudiante->apellidos_nombres }}</div>
                                        <div class="text-xs text-slate-500">C.I. {{ $inscripcion->estudiante->cedula }}
                                            @if ($inscripcion->estaRetirada()) · <span class="text-amber-600">retirado(a) {{ $inscripcion->fecha_retiro?->format('d/m/Y') }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-1.5 sm:flex" role="radiogroup" aria-label="Asistencia de {{ $inscripcion->estudiante->apellidos_nombres }}">
                                    @foreach (\App\Enums\EstadoAsistencia::cases() as $estado)
                                        <label class="marca-asistencia h-11 w-full flex-col gap-0 leading-none sm:size-8"
                                               title="{{ $estado->label() }}"
                                               :class="estados[{{ $inscripcion->id }}] === '{{ $estado->value }}' && 'marca-{{ $estado->value }}'">
                                            <input type="radio" class="sr-only" name="estados[{{ $inscripcion->id }}]" value="{{ $estado->value }}"
                                                   x-model="estados[{{ $inscripcion->id }}]">
                                            <span class="text-sm sm:text-xs">{{ $estado->value }}</span>
                                            <span class="mt-0.5 text-[10px] font-medium sm:hidden">{{ $estado === \App\Enums\EstadoAsistencia::Justificada ? 'Justif.' : $estado->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-slate-100 p-4 sm:px-5">
                        <input name="observacion" class="input" maxlength="255" placeholder="Observación de la jornada (opcional)" value="{{ old('observacion', $jornada?->observacion) }}">
                        <p class="mt-2 text-xs text-slate-500">P = presente · A = ausente · R = retraso · J = inasistencia justificada. Solo las ausencias (A) restan en el porcentaje de asistencia.</p>
                    </div>

                    {{-- Barra de guardado: fija abajo en el teléfono --}}
                    <div class="sticky bottom-0 z-10 flex items-center gap-3 rounded-b-2xl border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-5">
                        <div class="hidden flex-1 text-xs text-slate-600 sm:block">
                            <b class="text-slate-900" x-text="cuenta('P') + cuenta('R')"></b> presentes ·
                            <b class="text-rose-600" x-text="cuenta('A')"></b> ausentes
                        </div>
                        <button class="btn btn-secondary flex-1 px-3 sm:flex-none sm:px-4"><x-icon name="check" class="size-4" /> Guardar</button>
                        <button name="cerrar" value="1" class="btn btn-success flex-1 px-3 sm:flex-none sm:px-4"
                                data-confirmar="Se guardará y cerrará la asistencia del día. Para modificarla luego deberá justificar la reapertura. ¿Continuar?">
                            <x-icon name="lock" class="size-4" /> Guardar y cerrar
                        </button>
                    </div>
                </form>
            @else
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <h2 class="card-title">Asistencia del día</h2>
                        <x-badge color="slate"><x-icon name="lock" class="size-3" /> {{ $jornada?->estaCerrada() ? 'Cerrada' : 'Solo lectura' }}</x-badge>
                    </div>
                    @if (! $jornada)
                        <x-empty icon="clipboard" title="No hay asistencia registrada en esta fecha" />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($inscripciones as $inscripcion)
                                @php $estado = \App\Enums\EstadoAsistencia::tryFrom($estados[$inscripcion->id] ?? ''); @endphp
                                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                                    <span class="font-medium text-slate-900">{{ $inscripcion->estudiante->apellidos_nombres }}</span>
                                    @if ($estado) <x-badge :color="$estado->color()">{{ $estado->label() }}</x-badge> @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($reaperturas->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <h2 class="card-title">Historial de reaperturas de este día</h2>
                        <span class="text-xs text-slate-500">{{ $reaperturas->count() }}</span>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($reaperturas as ['reapertura' => $r, 'cambios' => $cambios])
                            @include('asistencia._reapertura', ['r' => $r, 'cambios' => $cambios])
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <aside class="card h-fit overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Jornadas registradas</h2>
                <a href="{{ route('asistencia.mensual', $catedra) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">Reporte mensual →</a>
            </div>
            @if ($jornadas->isEmpty())
                <x-empty icon="calendar" title="Sin jornadas" />
            @else
                <ul class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto">
                    @foreach ($jornadas as $j)
                        <li @class(['flex items-center gap-3 px-4 py-2.5 text-sm', 'bg-slate-50' => $jornada?->id === $j->id])>
                            <a href="{{ route('asistencia.create', ['catedra' => $catedra, 'fecha' => $j->fecha->toDateString()]) }}" class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 font-semibold text-slate-900">
                                    {{ ucfirst($j->fecha->translatedFormat('D d/m/Y')) }}
                                    @if ($j->estaCerrada()) <x-icon name="lock" class="size-3.5 text-slate-400" title="Cerrada" /> @endif
                                    @if ($j->reaperturas_count) <span class="text-[10px] font-semibold text-amber-600" title="Reaperturas">↺{{ $j->reaperturas_count }}</span> @endif
                                </div>
                                <div class="truncate text-xs text-slate-500">{{ $j->registradoPor?->name ?? '—' }}</div>
                            </a>
                            <span class="text-xs font-semibold whitespace-nowrap text-slate-700">{{ $j->asistencias_count - $j->ausentes_count }}/{{ $j->asistencias_count }}</span>
                            @if (auth()->user()->esControl() && $catedra->anioEscolar->permiteRegistros())
                                <form method="POST" action="{{ route('jornadas.destroy', $j) }}" data-confirmar="¿Eliminar la asistencia del {{ $j->fecha->format('d/m/Y') }}? Se actualizarán las estadísticas." data-confirmar-tipo="peligro">
                                    @csrf @method('DELETE')
                                    <button class="text-slate-300 hover:text-rose-600" title="Eliminar jornada"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>
</x-layouts.app>
