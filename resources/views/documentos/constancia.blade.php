<x-layouts.print :title="'Constancia - '.$estudiante->apellidos_nombres">
    <div class="titulo">Constancia de estudio</div>

    <div class="texto">
        Quien suscribe, Director del {{ $ajustes['institucion_nombre_texto'] }}, hace constar que el (la) alumno(a):
        <b>{{ mb_strtoupper($estudiante->apellidos_nombres) }}</b>, titular de la Cédula de Identidad <b>{{ $estudiante->cedula }}</b>,
        @if ($matricula->estaInscrito())
            está inscrito(a) en esta institución, cursando en el presente año académico <b>{{ str_replace('-', ' – ', $matricula->anioEscolar->nombre) }}</b> las asignaturas siguientes:
        @else
            estuvo inscrito(a) en esta institución durante el año académico <b>{{ str_replace('-', ' – ', $matricula->anioEscolar->nombre) }}</b>, cursando las asignaturas siguientes:
        @endif
    </div>

    <table class="datos">
        <thead>
            <tr>
                <th style="width:15%">Año / Nivel</th>
                <th style="width:27%">Cátedra</th>
                <th style="width:8%">Sec.</th>
                <th style="width:25%">Docente</th>
                <th style="width:25%">Horario</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inscripciones as $inscripcion)
                <tr>
                    <td>{{ $inscripcion->catedra->nivel->nombre }}</td>
                    <td>{{ $inscripcion->catedra->asignatura->nombre }}</td>
                    <td>{{ $inscripcion->catedra->seccion }}</td>
                    <td>{{ $inscripcion->catedra->profesor?->name ?? '—' }}</td>
                    <td>{{ $inscripcion->horario_efectivo ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin asignaturas registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="texto">
        Constancia que se expide a solicitud de parte interesada en {{ $ajustes['institucion_ciudad'] ?: 'Ciudad Bolívar' }},
        a los {{ now()->day }} días del mes de {{ now()->translatedFormat('F') }} de {{ now()->year }}.
    </div>

    <div class="firmas">
        <div style="flex: 0 0 34%; text-align: left;">
            <div class="linea" style="margin: 30px 0 4px; width: 90%;"></div>
            <b>{{ $ajustes['control_estudios_nombre'] }}</b><br>Control de Estudios
            <div class="linea" style="margin: 34px 0 4px; width: 90%;"></div>
            <b>{{ $ajustes['elaborado_por_nombre'] }}</b><br>Elaborado por
        </div>
        <div class="firma">
            <div class="linea"></div>
            <b>{{ $ajustes['director_nombre'] }}</b><br>Director
            @if ($ajustes['director_resolucion'])
                <div class="gaceta">{{ $ajustes['director_resolucion'] }}</div>
            @endif
        </div>
    </div>
</x-layouts.print>
