@props(['inscripcion', 'sinNotas' => false])
{{-- En años sin registro de notas, "sin calificar" se muestra como "Cursada". --}}
@if ($sinNotas && $inscripcion->estado === \App\Enums\EstadoInscripcion::SinCalificar)
    <x-badge color="slate" {{ $attributes }} title="Año sin registro de notas">Cursada</x-badge>
@else
    <x-badge :color="$inscripcion->estado->color()" {{ $attributes }}>{{ $inscripcion->estado->label() }}</x-badge>
@endif
