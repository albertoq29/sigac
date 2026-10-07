<x-layouts.app title="Inscribir estudiante">
    <x-page-header :title="'Inscribir en '.$anio->nombre" :subtitle="$estudiante->apellidos_nombres.' · C.I. '.$estudiante->cedula" :back="route('estudiantes.show', $estudiante)" />

    @if ($existente && $existente->estado->value === 'retirado')
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            El estudiante fue retirado de {{ $anio->nombre }} el {{ $existente->fecha_retiro?->format('d/m/Y') }}. Al inscribirlo de nuevo se reincorpora con las cátedras que seleccione.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('matriculas.store', $estudiante) }}" class="card space-y-5 p-5 lg:col-span-2">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Año escolar" name="anio_escolar_id" required>
                    <select name="anio_escolar_id" class="input" onchange="window.location = '{{ route('matriculas.create', $estudiante) }}?anio=' + this.value">
                        @foreach ($abiertos as $abierto)
                            <option value="{{ $abierto->id }}" @selected($abierto->id === $anio->id)>{{ $abierto->nombre }} ({{ mb_strtolower($abierto->estado->label()) }})</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Sección general" name="seccion">
                    <input name="seccion" class="input uppercase" maxlength="40" value="{{ old('seccion', $existente?->seccion ?? $anterior?->seccion) }}">
                </x-field>
                <x-field label="Fecha de inscripción" name="fecha_inscripcion">
                    <input type="date" name="fecha_inscripcion" class="input" value="{{ old('fecha_inscripcion', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                </x-field>
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <span class="label mb-0">Cátedras de {{ $anio->nombre }}</span>
                    @if ($sugeridas->isNotEmpty())
                        <span class="text-xs text-violet-700">{{ $sugeridas->count() }} sugerida(s) según el año anterior</span>
                    @endif
                </div>
                @include('partials.selector-catedras', ['catedras' => $catedras, 'seleccionadas' => old('catedras', []), 'sugeridas' => $sugeridas, 'aprobadas' => $aprobadas])
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('estudiantes.show', $estudiante) }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-success"><x-icon name="user-plus" class="size-4" /> Inscribir (queda activo)</button>
            </div>
        </form>

        <aside class="card h-fit">
            <div class="card-header"><h2 class="card-title">Año anterior{{ $anterior ? ': '.$anterior->anioEscolar->nombre : '' }}</h2></div>
            @if (! $anterior)
                <x-empty icon="archive" title="Sin registros anteriores" />
            @else
                @unless ($anterior->anioEscolar->tieneRegistroNotas())
                    <div class="flex items-start gap-2 border-b border-amber-100 bg-amber-50 px-5 py-3 text-sm text-amber-900">
                        <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
                        <span>{{ $anterior->anioEscolar->nombre }} no tiene registro de notas: no se sugieren materias, asígnelas manualmente.</span>
                    </div>
                @endunless
                @if ($promovido !== null)
                    <div @class(['flex items-center gap-2 border-b px-5 py-3 text-sm font-semibold',
                                 'border-emerald-100 bg-emerald-50 text-emerald-800' => $promovido,
                                 'border-rose-100 bg-rose-50 text-rose-800' => ! $promovido])>
                        <x-icon :name="$promovido ? 'check-circle' : 'exclamation'" class="size-5" />
                        {{ $promovido ? 'Promovido: aprobó todas las materias de su año.' : 'No promovido: debe repetir las materias de su año que no aprobó.' }}
                    </div>
                @endif
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($anterior->inscripciones as $inscripcion)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-slate-900">{{ $inscripcion->catedra->asignatura->nombre }}</div>
                                <div class="text-xs text-slate-500">{{ $inscripcion->catedra->nivel->nombre }} · Sec. {{ $inscripcion->catedra->seccion }}</div>
                            </div>
                            <div class="text-right">
                                <x-estado-inscripcion :inscripcion="$inscripcion" :sin-notas="! $anterior->anioEscolar->tieneRegistroNotas()" />
                                @if ($inscripcion->nota_definitiva !== null)
                                    <div class="mt-0.5 text-xs font-bold text-slate-700">{{ formato_nota($inscripcion->nota_definitiva) }} pts</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    Si aprobó todas las materias de su año (Preparatorio, 1er Año…) se sugieren las del año siguiente; si no, solo repite las que no aprobó.
                    Las cátedras por nivel (instrumentos, Nivel I…) se asignan manualmente. Las materias ya aprobadas no se pueden volver a cursar.
                </p>
            @endif
        </aside>
    </div>
</x-layouts.app>
