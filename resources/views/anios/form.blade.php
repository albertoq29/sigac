<x-layouts.app :title="$anio->exists ? 'Editar año escolar' : 'Nuevo año escolar'">
    <x-page-header :title="$anio->exists ? 'Editar año escolar '.$anio->nombre : 'Nuevo año escolar'"
                   subtitle="El año se crea en planificación: puede preparar cátedras e inscripciones antes de iniciarlo."
                   :back="$anio->exists ? route('anios.show', $anio) : route('anios.index')" />

    <form method="POST" action="{{ $anio->exists ? route('anios.update', $anio) : route('anios.store') }}" class="card max-w-3xl space-y-5 p-6">
        @csrf
        @if ($anio->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="Nombre" name="nombre" required help="Formato: 2026-2027">
                <input name="nombre" class="input" required value="{{ old('nombre', $anio->nombre) }}" placeholder="2026-2027" pattern="\d{4}-\d{4}" @disabled($anio->estaCerrado())>
                @if ($anio->estaCerrado()) <input type="hidden" name="nombre" value="{{ $anio->nombre }}"> @endif
            </x-field>
            <x-field label="Fecha de inicio" name="fecha_inicio">
                <input type="date" name="fecha_inicio" class="input" value="{{ old('fecha_inicio', $anio->fecha_inicio?->toDateString()) }}">
            </x-field>
            <x-field label="Fecha de cierre" name="fecha_fin"
                     x-data="{ sinDefinir: {{ old('fecha_fin_sin_definir', $anio->fecha_fin ? '0' : '1') ? 'true' : 'false' }} }">
                <input type="date" name="fecha_fin" class="input" value="{{ old('fecha_fin', $anio->fecha_fin?->toDateString()) }}"
                       :disabled="sinDefinir" :class="sinDefinir && 'opacity-50'">
                <label class="mt-1.5 flex items-center gap-2 text-xs text-slate-600">
                    <input type="hidden" name="fecha_fin_sin_definir" value="0">
                    <input type="checkbox" name="fecha_fin_sin_definir" value="1" class="checkbox" x-model="sinDefinir">
                    Sin definir
                </label>
            </x-field>
        </div>

        <x-field label="Régimen predeterminado de las cátedras" name="regimen_predeterminado" help="Se propone al crear cada cátedra; Control de Estudios puede cambiarlo por cátedra.">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Enums\Regimen::cases() as $regimen)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 has-[:checked]:border-slate-900 has-[:checked]:ring-1 has-[:checked]:ring-slate-900">
                        <input type="radio" name="regimen_predeterminado" value="{{ $regimen->value }}" @checked(old('regimen_predeterminado', $anio->regimen_predeterminado?->value) === $regimen->value)>
                        <span class="text-sm font-semibold">{{ $regimen->label() }} <span class="font-normal text-slate-500">({{ mb_strtolower($regimen->periodo()) }}s)</span></span>
                    </label>
                @endforeach
            </div>
        </x-field>

        <x-field label="Cantidad de lapsos predeterminada" name="lapsos_predeterminados" help="Se propone al crear cada cátedra (1, 2 o 3); Control de Estudios puede cambiarla por cátedra.">
            <select name="lapsos_predeterminados" class="input sm:w-48">
                @foreach (\App\Models\Catedra::LAPSOS_PERMITIDOS as $cantidad)
                    <option value="{{ $cantidad }}" @selected((int) old('lapsos_predeterminados', $anio->lapsos_predeterminados ?? 2) === $cantidad)>{{ $cantidad }} lapso{{ $cantidad > 1 ? 's' : '' }}</option>
                @endforeach
            </select>
        </x-field>

        @if ($anio->exists && ! $anio->estaCerrado())
            <label class="flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <input type="hidden" name="aplicar_a_catedras" value="0">
                <input type="checkbox" name="aplicar_a_catedras" value="1" class="checkbox mt-0.5" @checked(old('aplicar_a_catedras', true))>
                <span>
                    <b>Aplicar el régimen y la cantidad de lapsos a las cátedras ya creadas de {{ $anio->nombre }}.</b>
                    <span class="block text-xs text-sky-800">No se modifican las que tienen el cierre de notas hecho ni aquellas en las que habría que quitar un lapso que ya tiene evaluaciones.</span>
                </span>
            </label>
        @endif

        @unless ($anio->exists)
            <x-field label="Copiar cátedras de" name="copiar_de" help="Copia asignatura, nivel, sección, profesor y horario, con el régimen y los lapsos de este año (sin estudiantes ni notas).">
                <select name="copiar_de" class="input">
                    <option value="">No copiar</option>
                    @foreach ($anteriores as $anterior)
                        <option value="{{ $anterior->id }}" @selected(old('copiar_de', $anteriores->first()?->id) == $anterior->id)>{{ $anterior->nombre }}</option>
                    @endforeach
                </select>
            </x-field>
        @endunless

        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="hidden" name="sin_registro_notas" value="0">
            <input type="checkbox" name="sin_registro_notas" value="1" class="checkbox mt-0.5" @checked(old('sin_registro_notas', $anio->sin_registro_notas))>
            <span>
                <b>Este año no tiene registro de notas</b>
                <span class="block text-xs text-slate-500">No se toma en cuenta para la promoción ni para las materias aprobadas; sus materias figuran como cursadas.</span>
            </span>
        </label>

        <x-field label="Observaciones" name="observaciones">
            <textarea name="observaciones" class="input" rows="3" maxlength="2000">{{ old('observaciones', $anio->observaciones) }}</textarea>
        </x-field>

        <div class="flex justify-end gap-2">
            <a href="{{ $anio->exists ? route('anios.show', $anio) : route('anios.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar</button>
        </div>
    </form>
</x-layouts.app>
