<x-layouts.app :title="$lapso->nombre.' · '.$catedra->nombre">
    @php
        $peso = $lapso->pesoTotal();
        $completo = abs($peso - 100) < 0.01;
        $pesos = $lapso->evaluaciones->mapWithKeys(fn ($e) => [$e->id => (float) $e->peso]);
    @endphp

    <x-catedra-encabezado :catedra="$catedra" />

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('notas.index', $catedra) }}" class="btn btn-sm btn-secondary"><x-icon name="arrow-left" class="size-4" /> Resumen</a>
        @foreach ($catedra->lapsos as $otro)
            <a href="{{ route('notas.lapso', [$catedra, $otro->numero]) }}"
               @class(['btn btn-sm', 'btn-primary' => $otro->numero === $lapso->numero, 'btn-secondary' => $otro->numero !== $lapso->numero])>
                {{ $catedra->regimen->nombreLapso($otro->numero) }} @if ($otro->estaCerrado()) <x-icon name="lock" class="size-3.5" /> @endif
            </a>
        @endforeach
        @if (! $editable)
            <x-badge color="slate" class="ml-auto"><x-icon name="lock" class="size-3" /> Solo lectura</x-badge>
        @endif
    </div>

    {{-- Evaluaciones y porcentajes --}}
    <section class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">Evaluaciones del {{ $lapso->nombre }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">El profesor define cuántas evaluaciones hay y cuánto vale cada una. Deben sumar 100% para cerrar el lapso.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div @class(['text-lg font-bold tabular-nums', 'text-emerald-600' => $completo, 'text-amber-600' => ! $completo])>{{ formato_nota($peso, '0') }}%</div>
                    <div class="text-[11px] text-slate-500">de 100%</div>
                </div>
                @if ($editable)
                    <form method="POST" action="{{ route('notas.cerrar-lapso', [$catedra, $lapso->numero]) }}"
                          data-confirmar="Al cerrar el {{ $lapso->nombre }} sus notas quedarán bloqueadas. ¿Continuar?">
                        @csrf
                        <button class="btn btn-sm btn-success" @disabled(! $completo) title="{{ $completo ? '' : 'Las evaluaciones deben sumar 100%' }}">
                            <x-icon name="lock" class="size-4" /> Cerrar lapso
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead class="hidden sm:table-header-group">
                    <tr><th class="w-10">#</th><th>Evaluación</th><th class="w-32 text-center">Porcentaje</th><th class="w-40">Fecha</th>@if ($editable)<th class="w-32"></th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($lapso->evaluaciones as $n => $evaluacion)
                        <tr x-data="{ editando: false }">
                            <td class="hidden text-xs text-slate-400 sm:table-cell">{{ $n + 1 }}</td>
                            <td colspan="{{ $editable ? 4 : 3 }}" class="p-0">
                                <div x-show="! editando" class="flex items-center gap-3 px-4 py-3 sm:grid sm:grid-cols-[1fr_8rem_10rem_8rem] sm:gap-0 sm:p-0">
                                    <span class="min-w-0 flex-1 sm:px-4 sm:py-3">
                                        <span class="font-medium text-slate-900">{{ $evaluacion->nombre }}</span>
                                        @if ($evaluacion->fecha)
                                            <span class="block text-xs text-slate-500 sm:hidden">{{ $evaluacion->fecha->format('d/m/Y') }}</span>
                                        @endif
                                    </span>
                                    <span class="font-semibold sm:px-4 sm:py-3 sm:text-center">{{ formato_nota($evaluacion->peso) }}%</span>
                                    <span class="hidden px-4 py-3 text-sm text-slate-500 sm:block">{{ $evaluacion->fecha?->format('d/m/Y') ?? '—' }}</span>
                                    @if ($editable)
                                        <span class="flex justify-end gap-1 sm:px-4 sm:py-2">
                                            <button type="button" class="btn btn-sm btn-ghost" @click="editando = true" title="Editar"><x-icon name="pencil" class="size-4" /></button>
                                            <form method="POST" action="{{ route('evaluaciones.destroy', $evaluacion) }}" data-confirmar="¿Eliminar la evaluación «{{ $evaluacion->nombre }}» y sus notas?" data-confirmar-tipo="peligro">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-ghost text-rose-600" title="Eliminar"><x-icon name="trash" class="size-4" /></button>
                                            </form>
                                        </span>
                                    @endif
                                </div>
                                @if ($editable)
                                    <form x-cloak x-show="editando" method="POST" action="{{ route('evaluaciones.update', $evaluacion) }}" class="grid grid-cols-2 items-center gap-2 px-4 py-3 sm:grid-cols-[1fr_8rem_10rem_8rem] sm:py-2">
                                        @csrf @method('PUT')
                                        <input name="nombre" class="input input-sm col-span-2 sm:col-span-1" value="{{ $evaluacion->nombre }}" required maxlength="120">
                                        <input name="peso" class="input input-sm text-center" value="{{ formato_nota($evaluacion->peso) }}" required inputmode="decimal">
                                        <input type="date" name="fecha" class="input input-sm" value="{{ $evaluacion->fecha?->toDateString() }}">
                                        <span class="col-span-2 flex justify-end gap-1 sm:col-span-1">
                                            <button class="btn btn-sm btn-primary">Guardar</button>
                                            <button type="button" class="btn btn-sm btn-ghost" @click="editando = false"><x-icon name="x" class="size-4" /></button>
                                        </span>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500">Aún no hay evaluaciones en este lapso.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($editable && ! $completo)
            <form method="POST" action="{{ route('evaluaciones.store', [$catedra, $lapso->numero]) }}" class="grid grid-cols-2 gap-3 border-t border-slate-100 bg-slate-50/60 p-4 sm:grid-cols-[1fr_8rem_10rem_auto] sm:items-end">
                @csrf
                <x-field label="Nueva evaluación" name="nombre" class="col-span-2 sm:col-span-1">
                    <input name="nombre" class="input" required maxlength="120" placeholder="Ej: Examen práctico, Lectura a primera vista…" value="{{ old('nombre') }}">
                </x-field>
                <x-field label="Porcentaje" name="peso">
                    <input name="peso" class="input text-center" required inputmode="decimal" placeholder="{{ formato_nota(max(0, 100 - $peso)) }}" value="{{ old('peso') }}">
                </x-field>
                <x-field label="Fecha" name="fecha">
                    <input type="date" name="fecha" class="input" value="{{ old('fecha') }}">
                </x-field>
                <button class="btn btn-primary col-span-2 sm:col-span-1"><x-icon name="plus" class="size-4" /> Agregar</button>
            </form>
        @endif
    </section>

    {{-- Planilla de notas (en el teléfono: una tarjeta por estudiante) --}}
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Planilla de notas · escala 0 a {{ formato_nota($notaMaxima) }}</h2>
            <span class="text-xs text-slate-500">La nota del lapso se calcula automáticamente (Σ nota × %).</span>
        </div>

        @if ($lapso->evaluaciones->isEmpty())
            <x-empty icon="academic" title="Agregue las evaluaciones del lapso para cargar notas" />
        @elseif ($inscripciones->isEmpty())
            <x-empty icon="users" title="La cátedra no tiene estudiantes" />
        @else
            <form method="POST" action="{{ route('notas.guardar', [$catedra, $lapso->numero]) }}"
                  x-data="planillaNotas(@js($pesos), {{ $notaMaxima }})" @input="version++">
                @csrf @method('PUT')
                <div class="overflow-x-auto">
                    <table class="tabla tabla-movil">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Estudiante</th>
                                @foreach ($lapso->evaluaciones as $evaluacion)
                                    <th class="min-w-28 text-center normal-case">
                                        <div class="truncate font-semibold text-slate-700" title="{{ $evaluacion->nombre }}">{{ \Illuminate\Support\Str::limit($evaluacion->nombre, 18) }}</div>
                                        <div class="font-normal text-slate-500">{{ formato_nota($evaluacion->peso) }}%</div>
                                    </th>
                                @endforeach
                                <th class="text-center">Nota del lapso</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inscripciones as $i => $inscripcion)
                                @php $activa = $inscripcion->estaCursando() && $editable; @endphp
                                <tr @class(['opacity-55' => $inscripcion->estaRetirada()])>
                                    <td class="ocultar-movil text-xs text-slate-400">{{ $i + 1 }}</td>
                                    <td class="celda-titulo">
                                        <div class="font-semibold text-slate-900 md:whitespace-nowrap"><span class="text-xs font-normal text-slate-400 md:hidden">{{ $i + 1 }}.</span> {{ $inscripcion->estudiante->apellidos_nombres }}</div>
                                        @if (! $inscripcion->estaCursando())
                                            <x-estado-inscripcion :inscripcion="$inscripcion" :sin-notas="! $catedra->anioEscolar->tieneRegistroNotas()" />
                                        @endif
                                    </td>
                                    @foreach ($lapso->evaluaciones as $evaluacion)
                                        @php $valor = $valores[$inscripcion->id][$evaluacion->id] ?? null; @endphp
                                        <td class="text-center" data-label="{{ \Illuminate\Support\Str::limit($evaluacion->nombre, 26) }} ({{ formato_nota($evaluacion->peso) }}%)">
                                            @if ($activa)
                                                <input name="notas[{{ $inscripcion->id }}][{{ $evaluacion->id }}]"
                                                       value="{{ old("notas.{$inscripcion->id}.{$evaluacion->id}", $valor !== null ? formato_nota($valor) : '') }}"
                                                       data-fila="{{ $inscripcion->id }}" data-evaluacion="{{ $evaluacion->id }}"
                                                       inputmode="decimal" autocomplete="off"
                                                       class="input input-sm w-24 text-center text-base tabular-nums md:mx-auto md:w-20 md:text-xs"
                                                       :class="invalida($el) && 'border-rose-500 ring-2 ring-rose-200'">
                                            @else
                                                <span class="tabular-nums">{{ formato_nota($valor) }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center text-base font-bold text-slate-900 tabular-nums" data-label="Nota del lapso">
                                        @if ($activa)
                                            <span x-text="notaLapso({{ $inscripcion->id }}).replace('.', ',')">{{ formato_nota($notasLapso[$inscripcion->id]['nota'] ?? null) }}</span>
                                        @else
                                            {{ formato_nota($notasLapso[$inscripcion->id]['nota'] ?? null) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($editable)
                    <div class="sticky bottom-0 z-10 flex items-center justify-between gap-3 rounded-b-2xl border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
                        <p class="text-xs text-slate-500">Coma o punto decimal. Casillas vacías cuentan como 0.</p>
                        <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar notas</button>
                    </div>
                @endif
            </form>
        @endif
    </section>
</x-layouts.app>
