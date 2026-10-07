@props(['anio'])
@if ($anio && ! $anio->tieneRegistroNotas())
    <div {{ $attributes->class('flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900') }}>
        <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
        <div>
            <b>El año escolar {{ $anio->nombre }} no tiene registro de notas.</b>
            Sus materias figuran como cursadas (sin calificación) y no se toman en cuenta para la promoción ni para las materias aprobadas.
        </div>
    </div>
@endif
