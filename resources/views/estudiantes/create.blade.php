<x-layouts.app title="Nuevo estudiante">
    <x-page-header title="Nuevo estudiante" subtitle="Registre los datos personales y, si corresponde, inscríbalo en el año escolar." :back="route('estudiantes.index')" />

    <form method="POST" action="{{ route('estudiantes.store') }}" class="space-y-6" x-data="{ inscribir: @js((bool) old('inscribir', $anio !== null)) }">
        @csrf

        <section class="card">
            <div class="card-header"><h2 class="card-title">Datos personales</h2></div>
            <div class="card-body space-y-4">
                @include('estudiantes._datos')
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Inscripción y cátedras</h2>
                @if ($anio)
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="hidden" name="inscribir" value="0">
                        <input type="checkbox" name="inscribir" value="1" class="checkbox" x-model="inscribir">
                        Inscribir en el año escolar {{ $anio->nombre }}
                    </label>
                @endif
            </div>
            <div class="card-body space-y-4">
                @if (! $anio)
                    <p class="text-sm text-slate-500">No hay un año escolar abierto. El estudiante quedará registrado como <b>inactivo</b>; podrá inscribirlo cuando se cree el año escolar.</p>
                @else
                    <p class="text-sm text-slate-500" x-show="! inscribir">El estudiante quedará registrado como <b>inactivo</b> (no inscrito).</p>
                    <div x-show="inscribir" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-4">
                            <x-field label="Sección general" name="seccion" help="Grupo o sección principal (opcional).">
                                <input id="seccion" name="seccion" class="input uppercase" value="{{ old('seccion') }}" maxlength="40" placeholder="Ej: A">
                            </x-field>
                        </div>
                        <div>
                            <div class="label">Cátedras del año {{ $anio->nombre }}</div>
                            @include('partials.selector-catedras', ['catedras' => $catedras, 'seleccionadas' => old('catedras', [])])
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('estudiantes.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar registro</button>
        </div>
    </form>
</x-layouts.app>
