<x-layouts.app :title="$estudiante->apellidos_nombres">
    <x-page-header :title="$estudiante->apellidos_nombres" :back="route('estudiantes.index')">
        <x-slot:meta>
            <div class="flex flex-wrap items-center gap-2 pt-1 text-sm text-slate-500">
                <span class="font-semibold text-slate-700">C.I. {{ $estudiante->cedula }}</span>
                <x-badge :color="$estudiante->estado->color()">{{ $estudiante->estado->label() }}</x-badge>
                @if ($vigente)
                    <span>Inscrito en {{ $vigente->anioEscolar->nombre }}</span>
                @endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('estudiantes.edit', $estudiante) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Editar datos</a>
            @if ($vigente?->estaInscrito())
                <a href="{{ route('documentos.constancia', $estudiante) }}" target="_blank" class="btn btn-secondary text-violet-700"><x-icon name="printer" class="size-4" /> Constancia</a>
            @endif
            @if ($matriculas->isNotEmpty())
                <a href="{{ route('documentos.historial', $estudiante) }}" target="_blank" class="btn btn-secondary"><x-icon name="document" class="size-4" /> Historial académico</a>
            @endif
            @if ($aniosAbiertos->isNotEmpty() && ! $vigente?->estaInscrito())
                <a href="{{ route('matriculas.create', [$estudiante, 'anio' => $aniosAbiertos->first()->id]) }}" class="btn btn-success"><x-icon name="user-plus" class="size-4" /> Inscribir</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Datos personales --}}
        <section class="card lg:col-span-1">
            <div class="card-header"><h2 class="card-title">Datos personales</h2></div>
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ([
                    'Cédula' => $estudiante->cedula,
                    'Fecha de nacimiento' => $estudiante->fecha_nacimiento?->format('d/m/Y'),
                    'Edad' => $estudiante->edad !== null ? $estudiante->edad.' años' : null,
                    'Sexo' => $estudiante->sexo_texto,
                    'Teléfono' => $estudiante->telefono,
                    'Correo' => $estudiante->correo,
                    'Observaciones' => $estudiante->observaciones,
                    'Registrado' => $estudiante->created_at?->format('d/m/Y'),
                ] as $etiqueta => $valor)
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">{{ $etiqueta }}</dt>
                        <dd class="text-right font-medium break-all text-slate-900">{{ $valor ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($matriculas->isEmpty())
                <div class="border-t border-slate-100 p-4">
                    <form method="POST" action="{{ route('estudiantes.destroy', $estudiante) }}" data-confirmar="¿Eliminar este registro? Esta acción no se puede deshacer." data-confirmar-tipo="peligro">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger-soft w-full"><x-icon name="trash" class="size-4" /> Eliminar registro</button>
                    </form>
                </div>
            @endif
        </section>

        {{-- Inscripción vigente --}}
        <section class="card lg:col-span-2" x-data="{ url: '', titulo: '' }">
            @if (! $vigente)
                <div class="card-header"><h2 class="card-title">Inscripción actual</h2></div>
                <x-empty icon="user-minus" title="No está inscrito en un año escolar abierto">
                    El estudiante está <b>inactivo</b>.
                    @if ($aniosAbiertos->isNotEmpty())
                        Inscríbalo para asignarle las cátedras del año.
                        <div class="mt-4">
                            @foreach ($aniosAbiertos as $abierto)
                                <a href="{{ route('matriculas.create', [$estudiante, 'anio' => $abierto->id]) }}" class="btn btn-success">Inscribir en {{ $abierto->nombre }}</a>
                            @endforeach
                        </div>
                    @endif
                </x-empty>
            @else
                @php $anioVigente = $vigente->anioEscolar; @endphp
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Año escolar {{ $anioVigente->nombre }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <x-badge :color="$vigente->estado->color()">{{ $vigente->estado->label() }}</x-badge>
                            @if ($vigente->seccion) <span>Sección general: <b class="text-slate-700">{{ $vigente->seccion }}</b></span> @endif
                            @if ($vigente->fecha_inscripcion) <span>Inscrito el {{ $vigente->fecha_inscripcion->format('d/m/Y') }}</span> @endif
                            @if ($vigente->fecha_retiro) <span class="text-rose-600">Retirado el {{ $vigente->fecha_retiro->format('d/m/Y') }}{{ $vigente->motivo_retiro ? ' — '.$vigente->motivo_retiro : '' }}</span> @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($vigente->estaInscrito())
                            <button type="button" class="btn btn-sm btn-primary" @click="$dispatch('abrir-modal', 'agregar-catedras')"><x-icon name="plus" class="size-4" /> Asignar cátedras</button>
                            <button type="button" class="btn btn-sm btn-secondary" @click="$dispatch('abrir-modal', 'editar-matricula')"><x-icon name="pencil" class="size-4" /> Sección</button>
                            <button type="button" class="btn btn-sm btn-danger-soft" @click="$dispatch('abrir-modal', 'retirar-estudiante')"><x-icon name="user-minus" class="size-4" /> Retirar estudiante</button>
                        @elseif ($vigente->estado->value === 'retirado')
                            <form method="POST" action="{{ route('matriculas.reincorporar', $vigente) }}" data-confirmar="¿Reincorporar al estudiante en {{ $anioVigente->nombre }}?">
                                @csrf
                                <button class="btn btn-sm btn-success"><x-icon name="arrow-path" class="size-4" /> Reincorporar</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($vigente->inscripciones->isEmpty())
                    <x-empty icon="music" title="Sin cátedras asignadas">Use “Asignar cátedras” para agregarle las materias del año.</x-empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Cátedra</th>
                                    <th>Profesor / horario</th>
                                    <th class="text-center">Asistencia</th>
                                    <th class="text-center">Nota</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vigente->inscripciones->sortBy(fn ($i) => [$i->estaRetirada(), $i->catedra->asignatura->nombre]) as $inscripcion)
                                    @php $a = $resumenAsistencia[$inscripcion->id] ?? null; @endphp
                                    <tr @class(['opacity-60' => $inscripcion->estaRetirada()])>
                                        <td>
                                            <a href="{{ route('catedras.show', $inscripcion->catedra) }}" class="font-semibold text-slate-900 hover:underline">{{ $inscripcion->catedra->asignatura->nombre }}</a>
                                            <div class="text-xs text-slate-500">{{ $inscripcion->catedra->nivel->nombre }} · Sección {{ $inscripcion->catedra->seccion }}</div>
                                        </td>
                                        <td class="text-xs">
                                            <div class="font-medium text-slate-700">{{ $inscripcion->catedra->profesor?->name ?? 'Sin profesor' }}</div>
                                            <div class="text-slate-500">{{ $inscripcion->horario_efectivo ?? '—' }}</div>
                                        </td>
                                        <td class="text-center text-xs whitespace-nowrap">
                                            @if ($a)
                                                <div class="font-bold text-slate-900">{{ $a['porcentaje'] }}%</div>
                                                <div class="text-slate-500">{{ $a['P'] + $a['R'] }}P · {{ $a['A'] }}A @if ($a['J']) · {{ $a['J'] }}J @endif</div>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center font-bold text-slate-900">
                                            {{ formato_nota($inscripcion->nota_definitiva) }}
                                        </td>
                                        <td>
                                            <x-estado-inscripcion :inscripcion="$inscripcion" :sin-notas="! $inscripcion->catedra->anioEscolar->tieneRegistroNotas()" />
                                            @if ($inscripcion->estaRetirada())
                                                <div class="mt-1 max-w-48 text-[11px] leading-tight text-slate-500">
                                                    {{ $inscripcion->fecha_retiro?->format('d/m/Y') }} {{ $inscripcion->motivo_retiro }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            @if (! $anioVigente->estaCerrado() && ! $inscripcion->catedra->notasCerradas())
                                                <div class="flex justify-end gap-1">
                                                    @if ($inscripcion->estaCursando())
                                                        <button type="button" class="btn btn-sm btn-ghost" title="Cambiar de cátedra / sección"
                                                                @click="url = @js(route('inscripciones.trasladar', $inscripcion)); titulo = @js($inscripcion->catedra->nombre); $dispatch('abrir-modal', 'trasladar')">
                                                            <x-icon name="switch" class="size-4" />
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-ghost text-rose-600" title="Retirar materia"
                                                                @click="url = @js(route('inscripciones.retirar', $inscripcion)); titulo = @js($inscripcion->catedra->nombre); $dispatch('abrir-modal', 'retirar-materia')">
                                                            <x-icon name="user-minus" class="size-4" />
                                                        </button>
                                                    @elseif ($inscripcion->estaRetirada() && $vigente->estaInscrito())
                                                        <form method="POST" action="{{ route('inscripciones.reincorporar', $inscripcion) }}">
                                                            @csrf
                                                            <button class="btn btn-sm btn-ghost text-emerald-700" title="Reincorporar a la materia"><x-icon name="arrow-path" class="size-4" /></button>
                                                        </form>
                                                    @endif
                                                    @if ($inscripcion->estaCursando() && ! $a)
                                                        <form method="POST" action="{{ route('inscripciones.destroy', $inscripcion) }}" data-confirmar="¿Quitar esta cátedra asignada por error?" data-confirmar-tipo="peligro">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-ghost text-slate-400 hover:text-rose-600" title="Quitar (asignada por error)"><x-icon name="trash" class="size-4" /></button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Modales --}}
                <x-modal name="agregar-catedras" title="Asignar cátedras del año {{ $anioVigente->nombre }}" max-width="max-w-3xl">
                    <form method="POST" action="{{ route('inscripciones.store', $vigente) }}" class="space-y-4">
                        @csrf
                        @include('partials.selector-catedras', ['catedras' => $catedrasDisponibles, 'seleccionadas' => [], 'aprobadas' => $aprobadas])
                        <div class="flex justify-end"><button class="btn btn-primary">Asignar seleccionadas</button></div>
                    </form>
                </x-modal>

                <x-modal name="editar-matricula" title="Inscripción {{ $anioVigente->nombre }}">
                    <form method="POST" action="{{ route('matriculas.update', $vigente) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <x-field label="Sección general" name="seccion">
                            <input name="seccion" class="input uppercase" value="{{ $vigente->seccion }}" maxlength="40">
                        </x-field>
                        <x-field label="Fecha de inscripción" name="fecha_inscripcion">
                            <input type="date" name="fecha_inscripcion" class="input" value="{{ $vigente->fecha_inscripcion?->toDateString() }}">
                        </x-field>
                        <div class="flex justify-end"><button class="btn btn-primary">Guardar</button></div>
                    </form>
                </x-modal>

                <x-modal name="retirar-estudiante" title="Retirar estudiante del año {{ $anioVigente->nombre }}">
                    <form method="POST" action="{{ route('matriculas.retirar', $vigente) }}" class="space-y-4">
                        @csrf
                        <p class="text-sm text-slate-600">El estudiante pasará a <b>inactivo</b> y todas las materias que cursa quedarán registradas como <b>retiradas</b>. Sus asistencias y notas se conservan.</p>
                        <x-field label="Fecha de retiro" name="fecha_retiro" required>
                            <input type="date" name="fecha_retiro" class="input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </x-field>
                        <x-field label="Motivo" name="motivo_retiro">
                            <textarea name="motivo_retiro" class="input" rows="3" maxlength="500" placeholder="Ej: cambio de residencia"></textarea>
                        </x-field>
                        <div class="flex justify-end"><button class="btn btn-danger">Confirmar retiro</button></div>
                    </form>
                </x-modal>

                <x-modal name="retirar-materia" title="Retirar materia">
                    <form method="POST" :action="url" class="space-y-4">
                        @csrf
                        <p class="text-sm text-slate-600">Se registrará el retiro de <b x-text="titulo"></b>. El estudiante sigue inscrito en sus demás materias.</p>
                        <x-field label="Fecha de retiro" name="fecha_retiro" required>
                            <input type="date" name="fecha_retiro" class="input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </x-field>
                        <x-field label="Motivo" name="motivo_retiro">
                            <input name="motivo_retiro" class="input" maxlength="255">
                        </x-field>
                        <div class="flex justify-end"><button class="btn btn-danger">Retirar materia</button></div>
                    </form>
                </x-modal>

                <x-modal name="trasladar" title="Cambiar de cátedra o sección">
                    <form method="POST" :action="url" class="space-y-4">
                        @csrf
                        <p class="text-sm text-slate-600"><b x-text="titulo"></b> quedará como retirada (traslado) y el estudiante se inscribirá en la cátedra elegida. Las asistencias anteriores se conservan.</p>
                        <x-field label="Nueva cátedra" name="catedra_id" required>
                            <select name="catedra_id" class="input" required>
                                <option value="">Seleccione…</option>
                                @foreach ($catedrasDisponibles->groupBy(fn ($c) => $c->asignatura->nombre) as $asignatura => $grupo)
                                    <optgroup label="{{ $asignatura }}">
                                        @foreach ($grupo as $c)
                                            <option value="{{ $c->id }}">{{ $c->nivel->nombre }} · Sec. {{ $c->seccion }} — {{ $c->profesor?->name ?? 'Sin profesor' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </x-field>
                        <div class="flex justify-end"><button class="btn btn-primary">Trasladar</button></div>
                    </form>
                </x-modal>
            @endif
        </section>
    </div>

    {{-- Historial por año escolar --}}
    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 class="text-base font-bold text-slate-900">Progreso y notas por año escolar</h2>
            <p class="text-xs text-slate-500">Toque una materia para ver el desglose de sus notas. La línea negra marca la nota mínima aprobatoria.</p>
        </div>
        @forelse ($matriculas as $matricula)
            <div class="card overflow-hidden">
                <div class="card-header bg-slate-50/60">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-slate-900">{{ $matricula->anioEscolar->nombre }}</span>
                        <x-badge :color="$matricula->anioEscolar->estado->color()">Año {{ mb_strtolower($matricula->anioEscolar->estado->label()) }}</x-badge>
                        <x-badge :color="$matricula->estado->color()">{{ $matricula->estado->label() }}</x-badge>
                        @unless ($matricula->anioEscolar->tieneRegistroNotas())
                            <x-badge color="amber">Sin registro de notas</x-badge>
                        @endunless
                        @if ($matricula->seccion) <span class="text-xs text-slate-500">Sección {{ $matricula->seccion }}</span> @endif
                    </div>
                    @if ($matricula->fecha_retiro)
                        <span class="text-xs text-rose-600">Retiro: {{ $matricula->fecha_retiro->format('d/m/Y') }}{{ $matricula->motivo_retiro ? ' — '.$matricula->motivo_retiro : '' }}</span>
                    @endif
                </div>
                @if ($matricula->inscripciones->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500">Sin cátedras en este año.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($matricula->inscripciones->sortBy(fn ($i) => [$i->estaRetirada(), $i->catedra->asignatura->nombre]) as $inscripcion)
                            <x-progreso-materia :inscripcion="$inscripcion"
                                                :progreso="$progresos[$inscripcion->id]"
                                                :asistencia="$resumenAsistencia[$inscripcion->id] ?? null"
                                                :sin-notas="! $matricula->anioEscolar->tieneRegistroNotas()" />
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <div class="card"><x-empty icon="archive" title="Sin historial">El estudiante aún no ha sido inscrito en ningún año escolar.</x-empty></div>
        @endforelse
    </section>
</x-layouts.app>
