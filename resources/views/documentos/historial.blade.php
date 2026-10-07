<x-layouts.print :title="'Historial académico - '.$estudiante->apellidos_nombres">
    <div class="titulo">Historial académico</div>

    <table class="datos">
        <tr>
            <td class="izq" style="width:60%"><b>Estudiante:</b> {{ mb_strtoupper($estudiante->apellidos_nombres) }}</td>
            <td class="izq"><b>C.I.:</b> {{ $estudiante->cedula }}</td>
        </tr>
        <tr>
            <td class="izq"><b>Fecha de nacimiento:</b> {{ $estudiante->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</td>
            <td class="izq"><b>Estado actual:</b> {{ $estudiante->estado->label() }}</td>
        </tr>
    </table>

    @forelse ($matriculas as $matricula)
        <div class="seccion-titulo">
            Año escolar {{ $matricula->anioEscolar->nombre }} — {{ $matricula->estado->label() }}
            @if ($matricula->seccion) · Sección {{ $matricula->seccion }} @endif
            @if ($matricula->fecha_retiro) · Retiro: {{ $matricula->fecha_retiro->format('d/m/Y') }} @endif
            @unless ($matricula->anioEscolar->tieneRegistroNotas()) · <i>Año sin registro de notas</i> @endunless
        </div>
        <table class="datos">
            <thead>
                <tr>
                    <th style="width:30%">Asignatura</th>
                    <th>Nivel</th>
                    <th>Sec.</th>
                    <th style="width:22%">Docente</th>
                    <th>Asistencia</th>
                    <th>Definitiva</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($matricula->inscripciones->sortBy(fn ($i) => $i->catedra->asignatura->nombre) as $inscripcion)
                    @php $a = $resumenAsistencia[$inscripcion->id] ?? null; @endphp
                    <tr>
                        <td class="izq">{{ $inscripcion->catedra->asignatura->nombre }}</td>
                        <td>{{ $inscripcion->catedra->nivel->nombre }}</td>
                        <td>{{ $inscripcion->catedra->seccion }}</td>
                        <td>{{ $inscripcion->catedra->profesor?->name ?? '—' }}</td>
                        <td>{{ $a ? $a['porcentaje'].'%' : '—' }}</td>
                        <td><b>{{ formato_nota($inscripcion->nota_definitiva) }}</b></td>
                        <td>{{ ! $matricula->anioEscolar->tieneRegistroNotas() && $inscripcion->estado === \App\Enums\EstadoInscripcion::SinCalificar ? 'Cursada' : $inscripcion->estado->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="texto">El estudiante no tiene inscripciones registradas.</p>
    @endforelse

    <div class="texto" style="margin-top: 18px; font-size: 12px;">
        Escala de calificaciones de 0 a {{ formato_nota($ajustes['nota_maxima']) }} puntos; nota mínima aprobatoria: {{ formato_nota($ajustes['nota_minima_aprobatoria']) }}.
        Expedido en {{ $ajustes['institucion_ciudad'] ?: 'Ciudad Bolívar' }} el {{ now()->format('d/m/Y') }}.
    </div>

    <div class="firmas">
        <div class="firma"><div class="linea"></div><b>{{ $ajustes['control_estudios_nombre'] }}</b><br>Control de Estudios</div>
        <div class="firma"><div class="linea"></div><b>{{ $ajustes['director_nombre'] }}</b><br>Director</div>
    </div>
</x-layouts.print>
