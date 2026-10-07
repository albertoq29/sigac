@props(['catedra'])
{{-- Encabezado común de las páginas de una cátedra --}}
@php
    $pestanas = [
        ['catedras.show', 'Alumnos', 'users'],
        ['asistencia.create', 'Pasar asistencia', 'clipboard'],
        ['asistencia.mensual', 'Reporte mensual', 'table'],
        ['notas.index', 'Notas', 'academic'],
    ];
@endphp
<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <a href="{{ route('catedras.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-900 no-print">
                <x-icon name="arrow-left" class="size-3.5" /> Cátedras
            </a>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $catedra->asignatura->nombre }}</h1>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                <span class="font-semibold text-slate-700">{{ $catedra->nivel->nombre }} · Sección {{ $catedra->seccion }}</span>
                @unless (auth()->id() === $catedra->profesor_id)
                    <span>Prof. {{ $catedra->profesor?->name ?? 'sin asignar' }}</span>
                @endunless
                @if ($catedra->horario) <span>{{ $catedra->horario }}</span> @endif
                <span>{{ $catedra->regimen->descripcion($catedra->cantidad_lapsos) }}</span>
                <x-badge :color="$catedra->anioEscolar->estado->color()">{{ $catedra->anioEscolar->nombre }}</x-badge>
                @if ($catedra->notasCerradas()) <x-badge color="slate"><x-icon name="lock" class="size-3" /> Notas cerradas</x-badge> @endif
            </div>
        </div>
        @isset($acciones)
            <div class="flex flex-wrap gap-2 no-print">{{ $acciones }}</div>
        @endisset
    </div>

    <x-aviso-sin-notas :anio="$catedra->anioEscolar" class="no-print" />

    <nav class="grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-white p-1 text-sm font-semibold sm:flex no-print">
        @foreach ($pestanas as [$ruta, $texto, $icono])
            @php $activa = request()->routeIs($ruta) || ($ruta === 'notas.index' && request()->routeIs('notas.*')); @endphp
            <a href="{{ route($ruta, $catedra) }}"
               @class([
                   'flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 whitespace-nowrap transition sm:justify-start sm:px-3.5 sm:py-2',
                   'bg-slate-900 text-white' => $activa,
                   'text-slate-600 hover:bg-slate-100' => ! $activa,
               ])>
                <x-icon :name="$icono" class="size-4" /> {{ $texto }}
            </a>
        @endforeach
    </nav>
</div>
