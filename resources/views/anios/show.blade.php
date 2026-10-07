<x-layouts.app :title="'Año escolar '.$anio->nombre">
    <x-page-header :title="'Año escolar '.$anio->nombre" :back="route('anios.index')">
        <x-slot:meta>
            <div class="flex flex-wrap items-center gap-2 pt-1 text-sm text-slate-500">
                <x-badge :color="$anio->estado->color()">{{ $anio->estado->label() }}</x-badge>
                <span>Régimen predeterminado: {{ $anio->regimen_predeterminado->label() }}</span>
                @if ($anio->fecha_inicio || $anio->fecha_fin)
                    <span>{{ $anio->fecha_inicio?->format('d/m/Y') ?? '…' }} — {{ $anio->fecha_fin?->format('d/m/Y') ?? 'sin definir' }}</span>
                @endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('anios.edit', $anio) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Editar</a>
            @unless ($anio->estaCerrado())
                @if ($otros->contains(fn ($o) => $o->nombre < $anio->nombre))
                    <a href="{{ route('reinscripcion.index', $anio) }}" class="btn btn-secondary"><x-icon name="switch" class="size-4" /> Reinscripción</a>
                @endif
                <button type="button" class="btn btn-secondary" onclick="window.dispatchEvent(new CustomEvent('abrir-modal', { detail: 'copiar' }))"><x-icon name="layers" class="size-4" /> Copiar cátedras</button>
            @endunless
            @if ($anio->enPlanificacion())
                <form method="POST" action="{{ route('anios.iniciar', $anio) }}" data-confirmar="¿Iniciar el año escolar {{ $anio->nombre }}? A partir de ahora se podrán registrar asistencias y notas.">
                    @csrf
                    <button class="btn btn-success" @disabled($enCurso) title="{{ $enCurso ? 'Primero cierre '.$enCurso->nombre : '' }}"><x-icon name="arrow-right" class="size-4" /> Iniciar año escolar</button>
                </form>
            @elseif ($anio->estaEnCurso())
                <button type="button" class="btn btn-danger" onclick="window.dispatchEvent(new CustomEvent('abrir-modal', { detail: 'cerrar-anio' }))"><x-icon name="lock" class="size-4" /> Cerrar año escolar</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-aviso-sin-notas :anio="$anio" />

    @if ($anio->enPlanificacion() && $enCurso)
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Este año está en planificación. Puede crear sus cátedras e inscribir estudiantes; podrá iniciarlo cuando cierre el año en curso ({{ $enCurso->nombre }}).
        </div>
    @endif

    @if ($anio->estaCerrado())
        <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
            <x-icon name="lock" class="mt-0.5 size-5 shrink-0 text-slate-500" />
            <div>
                Año cerrado el {{ $anio->cerrado_at?->format('d/m/Y H:i') }} por {{ $anio->cerradoPor?->name ?? '—' }}.
                Sus cátedras, notas y asistencias quedan guardadas como historial y son de solo lectura.
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Cátedras" :value="$pendientes['catedras']" />
        <x-stat label="Inscritos" :value="$matriculas['inscrito'] ?? 0" hint="estudiantes activos" color="emerald" />
        <x-stat label="Retirados" :value="$matriculas['retirado'] ?? 0" color="rose" />
        <x-stat label="Año finalizado" :value="$matriculas['finalizado'] ?? 0" hint="matrículas cerradas" />
        <x-stat label="Materias cursando" :value="$pendientes['materias_cursando']" />
    </div>

    @unless ($anio->estaCerrado())
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pendientes antes del cierre</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Al cerrar el año, las cátedras sin cierre de notas se cierran automáticamente; quien no tenga notas calculables quedará “sin calificar”.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <x-badge :color="$pendientes['catedras_sin_cierre'] ? 'amber' : 'emerald'">{{ $pendientes['catedras_sin_cierre'] }} sin cierre de notas</x-badge>
                    <x-badge :color="$pendientes['lapsos_abiertos'] ? 'amber' : 'emerald'">{{ $pendientes['lapsos_abiertos'] }} lapsos abiertos</x-badge>
                    <x-badge :color="$pendientes['catedras_sin_profesor'] ? 'amber' : 'emerald'">{{ $pendientes['catedras_sin_profesor'] }} sin profesor</x-badge>
                </div>
            </div>
            @if ($catedrasPendientes->isEmpty())
                <x-empty icon="check-circle" title="Todas las cátedras tienen el cierre de notas realizado" />
            @else
                <div class="max-h-96 overflow-y-auto">
                    <table class="tabla">
                        <thead><tr><th>Cátedra</th><th>Profesor</th><th>Lapsos</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($catedrasPendientes as $catedra)
                                <tr>
                                    <td><span class="font-semibold text-slate-900">{{ $catedra->asignatura->nombre }}</span> <span class="text-xs text-slate-500">{{ $catedra->nivel->nombre }} · Sec. {{ $catedra->seccion }}</span></td>
                                    <td class="text-sm">{{ $catedra->profesor?->name ?? 'Sin profesor' }}</td>
                                    <td>
                                        @foreach ($catedra->lapsos as $lapso)
                                            <x-badge :color="$lapso->estaCerrado() ? 'emerald' : 'slate'">{{ $catedra->regimen->nombreLapso($lapso->numero) }}</x-badge>
                                        @endforeach
                                    </td>
                                    <td class="text-right"><a href="{{ route('notas.index', $catedra) }}" class="btn btn-sm btn-secondary">Ver notas</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endunless

    @if ($anio->observaciones)
        <div class="card p-5 text-sm text-slate-600"><b class="text-slate-900">Observaciones:</b> {{ $anio->observaciones }}</div>
    @endif

    {{-- Copiar cátedras --}}
    <x-modal name="copiar" title="Copiar cátedras a {{ $anio->nombre }}">
        <form method="POST" action="{{ route('anios.copiar-catedras', $anio) }}" class="space-y-4">
            @csrf
            <p class="text-sm text-slate-600">Se copian asignatura, nivel, sección, profesor y horario; el régimen y la cantidad de lapsos serán los predeterminados de {{ $anio->nombre }} ({{ $anio->regimen_predeterminado->descripcion($anio->lapsos_predeterminados) }}). No se copian estudiantes ni notas. Las cátedras que ya existan no se duplican.</p>
            <x-field label="Copiar desde" name="origen" required>
                <select name="origen" class="input" required>
                    @foreach ($otros as $otro)
                        <option value="{{ $otro->id }}">{{ $otro->nombre }} ({{ mb_strtolower($otro->estado->label()) }})</option>
                    @endforeach
                </select>
            </x-field>
            <div class="flex justify-end"><button class="btn btn-primary">Copiar cátedras</button></div>
        </form>
    </x-modal>

    {{-- Cierre del año --}}
    @if ($anio->estaEnCurso())
        <x-modal name="cerrar-anio" title="Cerrar el año escolar {{ $anio->nombre }}" max-width="max-w-xl">
            <form method="POST" action="{{ route('anios.cerrar', $anio) }}" class="space-y-4">
                @csrf
                <div class="space-y-2 text-sm text-slate-600">
                    <p>El cierre del año escolar es realizado por Control de Estudios y <b>no se puede deshacer</b>:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Se realiza el cierre de notas de las {{ $pendientes['catedras_sin_cierre'] }} cátedra(s) pendientes.</li>
                        <li>Las notas y asistencias quedan guardadas como historial del año {{ $anio->nombre }} y en el expediente de cada estudiante.</li>
                        <li>Los {{ $matriculas['inscrito'] ?? 0 }} estudiantes inscritos pasan a <b>inactivos</b> hasta que se les reasignen las materias del próximo año.</li>
                    </ul>
                </div>
                <x-field label="Escriba {{ $anio->nombre }} para confirmar" name="confirmacion" required>
                    <input name="confirmacion" class="input" autocomplete="off" required placeholder="{{ $anio->nombre }}">
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="abierto = false">Cancelar</button>
                    <button class="btn btn-danger"><x-icon name="lock" class="size-4" /> Cerrar año escolar</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
