<x-layouts.print :title="'Acta - '.$catedra->nombre" :horizontal="true">
    <div class="titulo">Acta de calificaciones</div>

    <table class="datos">
        <tr>
            <td class="izq"><b>Cátedra:</b> {{ $catedra->asignatura->nombre }}</td>
            <td class="izq"><b>Año / Nivel:</b> {{ $catedra->nivel->nombre }}</td>
            <td class="izq"><b>Sección:</b> {{ $catedra->seccion }}</td>
        </tr>
        <tr>
            <td class="izq"><b>Docente:</b> {{ $catedra->profesor?->name ?? '—' }}</td>
            <td class="izq"><b>Año escolar:</b> {{ $catedra->anioEscolar->nombre }} · {{ $catedra->regimen->label() }}</td>
            <td class="izq"><b>Estado:</b> {{ $catedra->notasCerradas() ? 'Notas cerradas el '.$catedra->notas_cerradas_at->format('d/m/Y') : 'Notas abiertas (preliminar)' }}</td>
        </tr>
    </table>

    <table class="datos">
        <thead>
            <tr>
                <th style="width:4%">N°</th>
                <th style="width:12%">Cédula</th>
                <th>Apellidos y nombres</th>
                @foreach ($catedra->lapsos as $lapso)
                    <th>{{ $lapso->nombre }}</th>
                @endforeach
                <th>Nota final</th>
                <th>Definitiva</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inscripciones as $i => $inscripcion)
                @php
                    $r = $resumen[$inscripcion->id];
                    $cerrada = ! $inscripcion->estaCursando();
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $inscripcion->estudiante->cedula }}</td>
                    <td class="izq">{{ $inscripcion->estudiante->apellidos_nombres }}</td>
                    @foreach ($catedra->lapsos as $lapso)
                        <td>{{ formato_nota($r['lapsos'][$lapso->numero] ?? null, '') }}</td>
                    @endforeach
                    <td>{{ formato_nota($cerrada ? $inscripcion->nota_final : $r['final'], '') }}</td>
                    <td><b>{{ formato_nota($cerrada ? $inscripcion->nota_definitiva : $r['definitiva'], '') }}</b></td>
                    <td>{{ $inscripcion->estaCursando() ? '' : (! $catedra->anioEscolar->tieneRegistroNotas() && $inscripcion->estado === \App\Enums\EstadoInscripcion::SinCalificar ? 'Cursada' : $inscripcion->estado->label()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="firmas">
        <div class="firma"><div class="linea"></div><b>{{ $catedra->profesor?->name ?? '' }}</b><br>Docente</div>
        <div class="firma"><div class="linea"></div><b>{{ $ajustes['control_estudios_nombre'] }}</b><br>Control de Estudios</div>
        <div class="firma"><div class="linea"></div><b>{{ $ajustes['director_nombre'] }}</b><br>Director</div>
    </div>
</x-layouts.print>
