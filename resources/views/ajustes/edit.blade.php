<x-layouts.app title="Ajustes">
    <x-page-header title="Ajustes" subtitle="Datos institucionales para constancias y reportes, y escala de calificaciones." />

    <form method="POST" action="{{ route('ajustes.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card">
            <div class="card-header"><h2 class="card-title">Institución</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field label="Nombre (encabezados)" name="institucion_nombre" required class="sm:col-span-2">
                    <input name="institucion_nombre" class="input" required value="{{ old('institucion_nombre', $ajustes['institucion_nombre']) }}">
                </x-field>
                <x-field label="Nombre en textos (constancias)" name="institucion_nombre_texto" class="sm:col-span-2">
                    <input name="institucion_nombre_texto" class="input" value="{{ old('institucion_nombre_texto', $ajustes['institucion_nombre_texto']) }}">
                </x-field>
                <x-field label="Ubicación (encabezado)" name="institucion_ubicacion">
                    <input name="institucion_ubicacion" class="input" value="{{ old('institucion_ubicacion', $ajustes['institucion_ubicacion']) }}">
                </x-field>
                <x-field label="Ciudad (lugar de expedición)" name="institucion_ciudad">
                    <input name="institucion_ciudad" class="input" value="{{ old('institucion_ciudad', $ajustes['institucion_ciudad']) }}">
                </x-field>
                <x-field label="Dirección" name="institucion_direccion" class="sm:col-span-2">
                    <input name="institucion_direccion" class="input" value="{{ old('institucion_direccion', $ajustes['institucion_direccion']) }}">
                </x-field>
                <x-field label="Teléfono" name="institucion_telefono">
                    <input name="institucion_telefono" class="input" value="{{ old('institucion_telefono', $ajustes['institucion_telefono']) }}">
                </x-field>
                <x-field label="Correo" name="institucion_correo">
                    <input type="email" name="institucion_correo" class="input" value="{{ old('institucion_correo', $ajustes['institucion_correo']) }}">
                </x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Firmas</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field label="Director(a)" name="director_nombre">
                    <input name="director_nombre" class="input" value="{{ old('director_nombre', $ajustes['director_nombre']) }}">
                </x-field>
                <x-field label="Resolución / Gaceta del director" name="director_resolucion">
                    <input name="director_resolucion" class="input" value="{{ old('director_resolucion', $ajustes['director_resolucion']) }}">
                </x-field>
                <x-field label="Control de Estudios" name="control_estudios_nombre">
                    <input name="control_estudios_nombre" class="input" value="{{ old('control_estudios_nombre', $ajustes['control_estudios_nombre']) }}">
                </x-field>
                <x-field label="Elaborado por" name="elaborado_por_nombre">
                    <input name="elaborado_por_nombre" class="input" value="{{ old('elaborado_por_nombre', $ajustes['elaborado_por_nombre']) }}">
                </x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Logos de los documentos</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                @foreach (['logo_izquierda' => 'Logo izquierdo', 'logo_centro' => 'Logo central', 'logo_derecha' => 'Logo derecho'] as $clave => $etiqueta)
                    <div class="space-y-2">
                        <div class="label">{{ $etiqueta }}</div>
                        <div class="flex h-20 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50">
                            @if ($url = \App\Models\Ajuste::urlLogo($clave))
                                <img src="{{ $url }}" alt="{{ $etiqueta }}" class="max-h-16 max-w-full object-contain">
                            @else
                                <span class="text-xs text-slate-400">Sin logo</span>
                            @endif
                        </div>
                        <input name="{{ $clave }}" class="input input-sm" value="{{ old($clave, $ajustes[$clave]) }}" placeholder="URL o ruta">
                        <input type="file" name="archivo_{{ $clave }}" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Calificaciones</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <x-field label="Nota máxima" name="nota_maxima" required>
                    <input type="number" step="0.01" name="nota_maxima" class="input" required value="{{ old('nota_maxima', $ajustes['nota_maxima']) }}">
                </x-field>
                <x-field label="Nota mínima aprobatoria" name="nota_minima_aprobatoria" required>
                    <input type="number" step="0.01" name="nota_minima_aprobatoria" class="input" required value="{{ old('nota_minima_aprobatoria', $ajustes['nota_minima_aprobatoria']) }}">
                </x-field>
                <label class="flex items-center gap-2 self-end pb-3 text-sm text-slate-700">
                    <input type="hidden" name="redondear_definitiva" value="0">
                    <input type="checkbox" name="redondear_definitiva" value="1" class="checkbox" @checked(old('redondear_definitiva', $ajustes['redondear_definitiva']))>
                    Redondear la definitiva al entero (9,5 → 10)
                </label>
            </div>
        </section>

        <div class="flex justify-end"><button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar ajustes</button></div>
    </form>
</x-layouts.app>
