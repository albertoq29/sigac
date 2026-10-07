{{--
    Lista de cátedras con buscador.
    Variables: $catedras, $seleccionadas (array de ids), $sugeridas (colección de ids, opcional),
    $aprobadas (materias ya aprobadas por el estudiante, de GestionInscripciones::materiasAprobadas; opcional)
--}}
@php
    $seleccionadas = collect($seleccionadas ?? [])->map(fn ($id) => (int) $id);
    $sugeridas = collect($sugeridas ?? []);
    $aprobadas = collect($aprobadas ?? []);
@endphp
<div x-data="selectorConFiltro()" class="space-y-3">
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" x-model="filtro" class="input pl-9" placeholder="Filtrar por asignatura, nivel, sección o profesor…">
    </div>

    @if ($catedras->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">
            No hay cátedras abiertas en este año escolar.
        </p>
    @else
        <div class="max-h-96 space-y-3 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/60 p-3">
            @foreach ($catedras->groupBy(fn ($c) => $c->asignatura->nombre) as $asignatura => $grupo)
                <div x-show="{{ $grupo->map(fn ($c) => 'coincide('.\Illuminate\Support\Js::from($c->nombre.' '.($c->profesor?->name ?? '')).')')->implode(' || ') }}">
                    <div class="mb-1 text-xs font-bold tracking-wide text-slate-500 uppercase">{{ $asignatura }}</div>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        @foreach ($grupo as $catedra)
                            @php
                                $texto = $catedra->nombre.' '.($catedra->profesor?->name ?? '');
                                $aprobada = $aprobadas->get(\App\Services\GestionInscripciones::claveMateria($catedra));
                            @endphp
                            <label x-show="coincide({{ \Illuminate\Support\Js::from($texto) }})"
                                   @class([
                                       'flex items-start gap-2.5 rounded-lg border px-3 py-2 text-sm',
                                       'cursor-not-allowed border-slate-200 bg-slate-100 opacity-60' => $aprobada,
                                       'cursor-pointer border-slate-200 bg-white hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:ring-1 has-[:checked]:ring-slate-900' => ! $aprobada,
                                   ])
                                   @if ($aprobada) title="Ya la aprobó en {{ $aprobada->catedra->anioEscolar->nombre }}: no puede volver a cursarla" @endif>
                                <input type="checkbox" name="catedras[]" value="{{ $catedra->id }}" class="checkbox mt-0.5"
                                       @disabled($aprobada)
                                       @checked(! $aprobada && ($seleccionadas->contains($catedra->id) || (old() === [] && $sugeridas->contains($catedra->id))))>
                                <span class="min-w-0">
                                    <span class="font-semibold text-slate-900">{{ $catedra->nivel->nombre }} · Sec. {{ $catedra->seccion }}</span>
                                    @if ($aprobada)
                                        <x-badge color="emerald" class="ml-1">Aprobada {{ $aprobada->catedra->anioEscolar->nombre }}</x-badge>
                                    @elseif ($sugeridas->contains($catedra->id))
                                        <x-badge color="violet" class="ml-1">Sugerida</x-badge>
                                    @endif
                                    <span class="block truncate text-xs text-slate-500">
                                        {{ $catedra->profesor?->name ?? 'Sin profesor' }}@if ($catedra->horario) · {{ $catedra->horario }}@endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    @error('catedras') <p class="error-text">{{ $message }}</p> @enderror
    @error('catedras.*') <p class="error-text">{{ $message }}</p> @enderror
</div>
