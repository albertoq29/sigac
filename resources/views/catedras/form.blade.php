<x-layouts.app :title="$catedra->exists ? 'Editar cátedra' : 'Nueva cátedra'">
    <x-page-header :title="$catedra->exists ? 'Editar cátedra' : 'Nueva cátedra'"
                   :subtitle="'Año escolar '.$anio->nombre"
                   :back="$catedra->exists ? route('catedras.show', $catedra) : route('catedras.index')" />

    <form method="POST" action="{{ $catedra->exists ? route('catedras.update', $catedra) : route('catedras.store') }}" class="card max-w-3xl space-y-5 p-6">
        @csrf
        @if ($catedra->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Asignatura" name="asignatura_id" required class="sm:col-span-2">
                <select name="asignatura_id" class="input" required>
                    <option value="">Seleccione…</option>
                    @foreach ($asignaturas as $categoria => $grupo)
                        <optgroup label="{{ $categoria ?: 'Sin categoría' }}">
                            @foreach ($grupo as $asignatura)
                                <option value="{{ $asignatura->id }}" @selected(old('asignatura_id', $catedra->asignatura_id) == $asignatura->id)>{{ $asignatura->nombre }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Año / nivel" name="nivel_id" required>
                <select name="nivel_id" class="input" required>
                    <option value="">Seleccione…</option>
                    @foreach ($niveles as $nivel)
                        <option value="{{ $nivel->id }}" @selected(old('nivel_id', $catedra->nivel_id) == $nivel->id)>{{ $nivel->nombre }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Sección" name="seccion" required help="A, B, C, D, E o U (única).">
                <input name="seccion" class="input uppercase" list="secciones" maxlength="10" required value="{{ old('seccion', $catedra->seccion) }}">
                <datalist id="secciones">
                    @foreach (config('sigac.secciones') as $seccion) <option value="{{ $seccion }}"> @endforeach
                </datalist>
            </x-field>

            <x-field label="Profesor" name="profesor_id">
                <select name="profesor_id" class="input">
                    <option value="">Sin asignar</option>
                    @foreach ($profesores as $profesor)
                        <option value="{{ $profesor->id }}" @selected(old('profesor_id', $catedra->profesor_id) == $profesor->id)>{{ $profesor->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Horario" name="horario">
                <input name="horario" class="input" list="horarios" maxlength="80" value="{{ old('horario', $catedra->horario) }}" placeholder="2:00 pm a 3:30 pm">
                <datalist id="horarios">
                    @foreach (config('sigac.horarios') as $horario) <option value="{{ $horario }}"> @endforeach
                </datalist>
            </x-field>
        </div>

        @php
            $periodos = collect(\App\Enums\Regimen::cases())->mapWithKeys(fn ($r) => [$r->value => [$r->nombreLapso(1), $r->nombreLapso(2), $r->nombreLapso(3)]]);
        @endphp
        <fieldset class="space-y-4 rounded-xl border border-slate-200 p-4"
                  x-data="{ regimen: @js(old('regimen', $catedra->regimen?->value)), cantidad: {{ (int) old('cantidad_lapsos', $catedra->cantidad_lapsos ?? 2) }}, periodos: @js($periodos) }">
            <legend class="px-1 text-sm font-semibold text-slate-900">Régimen de evaluación</legend>
            <p class="text-xs text-slate-500">Control de Estudios define cuántos lapsos tiene la cátedra y si son trimestres o semestres. El profesor decide cuántas evaluaciones y sus porcentajes en cada lapso; la nota final es el promedio de los lapsos.</p>

            <div>
                <div class="label">Tipo de lapso</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Enums\Regimen::cases() as $regimen)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:ring-1 has-[:checked]:ring-slate-900">
                            <input type="radio" name="regimen" value="{{ $regimen->value }}" class="size-4 text-slate-900" x-model="regimen">
                            <span class="text-sm font-semibold text-slate-900">{{ $regimen->label() }} <span class="font-normal text-slate-500">({{ mb_strtolower($regimen->periodo()) }}s)</span></span>
                        </label>
                    @endforeach
                </div>
                @error('regimen') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="label">Cantidad de lapsos</div>
                <div class="grid grid-cols-3 gap-3">
                    @foreach (\App\Models\Catedra::LAPSOS_PERMITIDOS as $cantidad)
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:ring-1 has-[:checked]:ring-slate-900">
                            <input type="radio" name="cantidad_lapsos" value="{{ $cantidad }}" class="size-4 text-slate-900" x-model.number="cantidad">
                            {{ $cantidad }}
                        </label>
                    @endforeach
                </div>
                @error('cantidad_lapsos') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm" x-show="regimen">
                <span class="text-slate-500">La cátedra tendrá:</span>
                <template x-for="n in cantidad" :key="n">
                    <span class="chip bg-white" x-text="periodos[regimen]?.[n - 1]"></span>
                </template>
            </div>
            @if ($catedra->exists)
                <p class="text-xs text-amber-700">Solo se puede reducir la cantidad si los lapsos que se eliminan no tienen evaluaciones ni están cerrados.</p>
            @endif
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ $catedra->exists ? route('catedras.show', $catedra) : route('catedras.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar cátedra</button>
        </div>
    </form>
</x-layouts.app>
