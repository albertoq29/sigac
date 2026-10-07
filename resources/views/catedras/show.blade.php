<x-layouts.app :title="$catedra->nombre">
    @php
        $esControl = auth()->user()->esControl();
        $editable = $esControl && ! $catedra->anioEscolar->estaCerrado() && ! $catedra->notasCerradas();
        $vigentes = $inscripciones->reject->estaRetirada();
    @endphp

    <x-catedra-encabezado :catedra="$catedra">
        @if ($esControl)
            <x-slot:acciones>
                @unless ($catedra->anioEscolar->estaCerrado())
                    <a href="{{ route('catedras.edit', $catedra) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Editar cátedra</a>
                @endunless
                @if ($editable)
                    <a href="{{ route('catedras.estudiantes', $catedra) }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Agregar estudiantes</a>
                @endif
            </x-slot:acciones>
        @endif
    </x-catedra-encabezado>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Alumnos" :value="$vigentes->count()" :hint="($inscripciones->count() - $vigentes->count()).' retirados'" />
        <x-stat label="Jornadas" :value="$catedra->jornadas()->count()" hint="pases de lista registrados" />
        @php
            $totales = collect($resumenAsistencia);
            $registros = $totales->sum('total');
            $ausencias = $totales->sum('A');
        @endphp
        <x-stat label="Asistencia" :value="$registros ? \App\Services\RegistroAsistencia::porcentaje($registros, $ausencias).'%' : '—'" :hint="$ausencias.' inasistencias'" />
        <x-stat label="Lapsos" :value="$catedra->lapsos->filter->estaCerrado()->count().' / '.$catedra->lapsos->count()" hint="cerrados" />
    </div>

    <div class="card overflow-hidden" x-data="{ url: '', titulo: '' }">
        <div class="card-header">
            <h2 class="card-title">Listado de alumnos</h2>
            <a href="{{ route('documentos.acta', $catedra) }}" target="_blank" class="btn btn-sm btn-secondary"><x-icon name="printer" class="size-4" /> Imprimir listado / acta</a>
        </div>
        @if ($inscripciones->isEmpty())
            <x-empty icon="users" title="La cátedra no tiene estudiantes">
                @if ($editable) Use “Agregar estudiantes” para asignar estudiantes inscritos en {{ $catedra->anioEscolar->nombre }}. @endif
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="tabla tabla-movil">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Estudiante</th>
                            <th class="text-center">Asistencia</th>
                            @foreach ($catedra->lapsos as $lapso)
                                <th class="text-center">{{ $lapso->nombre }}</th>
                            @endforeach
                            <th class="text-center">Definitiva</th>
                            <th>Estado</th>
                            @if ($editable) <th></th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inscripciones as $i => $inscripcion)
                            @php
                                $a = $resumenAsistencia[$inscripcion->id] ?? null;
                                $n = $resumenNotas[$inscripcion->id] ?? null;
                            @endphp
                            <tr @class(['opacity-55' => $inscripcion->estaRetirada()])>
                                <td class="ocultar-movil text-xs text-slate-400">{{ $i + 1 }}</td>
                                <td class="celda-titulo">
                                    @if ($esControl)
                                        <a href="{{ route('estudiantes.show', $inscripcion->estudiante_id) }}" class="font-semibold text-slate-900 hover:underline">{{ $inscripcion->estudiante->apellidos_nombres }}</a>
                                    @else
                                        <span class="font-semibold text-slate-900">{{ $inscripcion->estudiante->apellidos_nombres }}</span>
                                    @endif
                                    <div class="text-xs text-slate-500">C.I. {{ $inscripcion->estudiante->cedula }}@if ($inscripcion->horario) · {{ $inscripcion->horario }}@endif</div>
                                </td>
                                <td class="text-center text-xs whitespace-nowrap" data-label="Asistencia">
                                    @if ($a)
                                        <span><span class="font-bold text-slate-900">{{ $a['porcentaje'] }}%</span>
                                        <span class="text-slate-500">({{ $a['A'] }} inasist.)</span></span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                @foreach ($catedra->lapsos as $lapso)
                                    <td class="text-center tabular-nums" data-label="{{ $lapso->nombre }}">{{ formato_nota($n['lapsos'][$lapso->numero] ?? null) }}</td>
                                @endforeach
                                <td class="text-center font-bold tabular-nums" data-label="Definitiva">
                                    {{ formato_nota($inscripcion->nota_definitiva) }}
                                </td>
                                <td data-label="Estado">
                                    <x-estado-inscripcion :inscripcion="$inscripcion" :sin-notas="! $catedra->anioEscolar->tieneRegistroNotas()" />
                                    @if ($inscripcion->estaRetirada() && $inscripcion->motivo_retiro)
                                        <div class="mt-1 max-w-52 text-[11px] leading-tight text-slate-500">{{ $inscripcion->fecha_retiro?->format('d/m/Y') }} {{ $inscripcion->motivo_retiro }}</div>
                                    @endif
                                </td>
                                @if ($editable)
                                    <td class="acciones-movil text-right whitespace-nowrap">
                                        @if ($inscripcion->estaCursando())
                                            <button type="button" class="btn btn-sm btn-ghost" title="Cambiar de cátedra / sección"
                                                    @click="url = @js(route('inscripciones.trasladar', $inscripcion)); titulo = @js($inscripcion->estudiante->apellidos_nombres); $dispatch('abrir-modal', 'trasladar')">
                                                <x-icon name="switch" class="size-4" />
                                            </button>
                                            <button type="button" class="btn btn-sm btn-ghost text-rose-600" title="Retirar de la cátedra"
                                                    @click="url = @js(route('inscripciones.retirar', $inscripcion)); titulo = @js($inscripcion->estudiante->apellidos_nombres); $dispatch('abrir-modal', 'retirar-materia')">
                                                <x-icon name="user-minus" class="size-4" />
                                            </button>
                                        @elseif ($inscripcion->estaRetirada())
                                            <form method="POST" action="{{ route('inscripciones.reincorporar', $inscripcion) }}" class="inline">
                                                @csrf
                                                <button class="btn btn-sm btn-ghost text-emerald-700" title="Reincorporar"><x-icon name="arrow-path" class="size-4" /></button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($editable)
            <x-modal name="retirar-materia" title="Retirar de la cátedra">
                <form method="POST" :action="url" class="space-y-4">
                    @csrf
                    <p class="text-sm text-slate-600">Se registrará el retiro de <b x-text="titulo"></b> en {{ $catedra->nombre }}.</p>
                    <x-field label="Fecha de retiro" required>
                        <input type="date" name="fecha_retiro" class="input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </x-field>
                    <x-field label="Motivo">
                        <input name="motivo_retiro" class="input" maxlength="255">
                    </x-field>
                    <div class="flex justify-end"><button class="btn btn-danger">Retirar</button></div>
                </form>
            </x-modal>

            <x-modal name="trasladar" title="Cambiar de cátedra o sección">
                <form method="POST" :action="url" class="space-y-4">
                    @csrf
                    <p class="text-sm text-slate-600"><b x-text="titulo"></b> quedará retirado de esta cátedra (traslado) e inscrito en la que seleccione.</p>
                    <x-field label="Cátedra de destino" required>
                        <select name="catedra_id" class="input" required>
                            <option value="">Seleccione…</option>
                            @foreach ($otrasCatedras as $otra)
                                <option value="{{ $otra->id }}">{{ $otra->nivel->nombre }} · Sec. {{ $otra->seccion }} — {{ $otra->profesor?->name ?? 'Sin profesor' }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <div class="flex justify-end"><button class="btn btn-primary">Trasladar</button></div>
                </form>
            </x-modal>
        @endif
    </div>

    @if ($esControl && $inscripciones->isEmpty() && ! $catedra->jornadas()->exists())
        <form method="POST" action="{{ route('catedras.destroy', $catedra) }}" data-confirmar="¿Eliminar esta cátedra?" data-confirmar-tipo="peligro" class="text-right">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger-soft"><x-icon name="trash" class="size-4" /> Eliminar cátedra</button>
        </form>
    @endif
</x-layouts.app>
