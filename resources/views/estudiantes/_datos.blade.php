{{-- Datos personales del estudiante (crear / editar) --}}
<div class="grid gap-4 md:grid-cols-4">
    <x-field label="Cédula" name="cedula" required>
        <input id="cedula" name="cedula" class="input" value="{{ old('cedula', $estudiante->cedula) }}" required maxlength="20" placeholder="12345678">
    </x-field>
    <x-field label="Apellidos y nombres" name="apellidos_nombres" class="md:col-span-3" required>
        <input id="apellidos_nombres" name="apellidos_nombres" class="input uppercase" value="{{ old('apellidos_nombres', $estudiante->apellidos_nombres) }}" required maxlength="160" placeholder="APELLIDOS NOMBRES">
    </x-field>
</div>

<div class="grid gap-4 md:grid-cols-4" x-data="{ nacimiento: @js(old('fecha_nacimiento', $estudiante->fecha_nacimiento?->toDateString())) }">
    <x-field label="Fecha de nacimiento" name="fecha_nacimiento">
        <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" class="input" x-model="nacimiento" max="{{ now()->toDateString() }}">
    </x-field>
    <x-field label="Edad">
        <input class="input bg-slate-100" readonly tabindex="-1"
               :value="nacimiento ? Math.floor((Date.now() - new Date(nacimiento + 'T00:00:00')) / 31557600000) + ' años' : ''" placeholder="Automática">
    </x-field>
    <x-field label="Sexo" name="sexo">
        <select id="sexo" name="sexo" class="input">
            <option value="">—</option>
            <option value="F" @selected(old('sexo', $estudiante->sexo) === 'F')>Femenino</option>
            <option value="M" @selected(old('sexo', $estudiante->sexo) === 'M')>Masculino</option>
        </select>
    </x-field>
    <x-field label="Teléfono" name="telefono">
        <input id="telefono" name="telefono" class="input" value="{{ old('telefono', $estudiante->telefono) }}" maxlength="80" placeholder="0414-1234567">
    </x-field>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <x-field label="Correo electrónico" name="correo">
        <input id="correo" name="correo" type="email" class="input" value="{{ old('correo', $estudiante->correo) }}" maxlength="120" placeholder="ejemplo@gmail.com">
    </x-field>
    <x-field label="Observaciones" name="observaciones">
        <input id="observaciones" name="observaciones" class="input" value="{{ old('observaciones', $estudiante->observaciones) }}" maxlength="2000">
    </x-field>
</div>
