<x-layouts.app title="Años escolares">
    <x-page-header title="Años escolares" subtitle="Cada año tiene sus propias cátedras, inscripciones, asistencias y notas. Al cerrarlo queda como historial de solo lectura.">
        <x-slot:actions>
            <a href="{{ route('anios.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo año escolar</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        @if ($anios->isEmpty())
            <x-empty icon="calendar" title="No hay años escolares" />
        @else
            <table class="tabla">
                <thead>
                    <tr><th>Año escolar</th><th>Estado</th><th class="text-center">Cátedras</th><th class="text-center">Inscritos</th><th class="text-center">Retirados</th><th class="text-center">Total registros</th><th>Régimen</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($anios as $anio)
                        <tr>
                            <td>
                                <a href="{{ route('anios.show', $anio) }}" class="text-base font-bold text-slate-900 hover:underline">{{ $anio->nombre }}</a>
                                @if ($anio->fecha_inicio || $anio->fecha_fin)
                                    <div class="text-xs text-slate-500">{{ $anio->fecha_inicio?->format('d/m/Y') ?? '…' }} — {{ $anio->fecha_fin?->format('d/m/Y') ?? 'sin definir' }}</div>
                                @endif
                            </td>
                            <td>
                                <x-badge :color="$anio->estado->color()">{{ $anio->estado->label() }}</x-badge>
                                @unless ($anio->tieneRegistroNotas()) <x-badge color="amber">Sin registro de notas</x-badge> @endunless
                                @if ($anio->cerrado_at) <div class="mt-1 text-[11px] text-slate-500">el {{ $anio->cerrado_at->format('d/m/Y') }}</div> @endif
                            </td>
                            <td class="text-center">{{ $anio->catedras_count }}</td>
                            <td class="text-center">{{ $anio->inscritos_count }}</td>
                            <td class="text-center">{{ $anio->retirados_count }}</td>
                            <td class="text-center">{{ $anio->matriculas_count }}</td>
                            <td>{{ $anio->regimen_predeterminado->label() }}</td>
                            <td class="text-right"><a href="{{ route('anios.show', $anio) }}" class="btn btn-sm btn-secondary">Gestionar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.app>
