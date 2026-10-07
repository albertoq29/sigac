<x-layouts.app title="Estadísticas">
    @push('scripts')
        <script type="application/json" id="datos-estadisticas">@json($datos)</script>
        @vite('resources/js/estadisticas.js')
    @endpush

    <x-page-header title="Estadísticas" :subtitle="$anio ? 'Año escolar '.$anio->nombre.' (cambie el año en la barra superior)' : 'Sin año escolar'" />

    <x-aviso-sin-notas :anio="$anio" />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Estudiantes del año" :value="$kpis['estudiantes']" :hint="$kpis['inscritos'].' inscritos · '.$kpis['retirados'].' retirados'" icon="users" color="emerald" />
        <x-stat label="Materias cursadas" :value="$kpis['materias']" hint="inscripciones en cátedras" icon="music" color="violet" />
        <x-stat label="Asistencia global" :value="$kpis['porcentaje'] !== null ? $kpis['porcentaje'].'%' : '—'" :hint="number_format($kpis['asistencias'], 0, ',', '.').' registros en '.$kpis['jornadas'].' jornadas'" icon="clipboard" color="amber" />
        <x-stat label="Edad promedio" :value="$kpis['edad_promedio'] !== null ? formato_nota($kpis['edad_promedio']).' años' : '—'" icon="user" color="sky" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <h2 class="card-title mb-4">Asistencias por mes</h2>
            <div class="h-72"><canvas id="grafico-meses"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="card-title mb-4">Estudiantes por asignatura</h2>
            <div class="h-72"><canvas id="grafico-asignaturas"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="card-title mb-4">Jornadas registradas por docente</h2>
            <div class="h-72"><canvas id="grafico-docentes"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="card-title mb-4">Inscripciones por nivel</h2>
            <div class="h-72"><canvas id="grafico-niveles"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="card-title mb-4">Distribución por sexo</h2>
            <div class="h-64"><canvas id="grafico-sexo"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="card-title mb-4">Distribución por edad</h2>
            <div class="h-64"><canvas id="grafico-edades"></canvas></div>
        </div>
        @if ($anio?->tieneRegistroNotas())
            <div class="card p-5 lg:col-span-2">
                <h2 class="card-title mb-4">Resultado de las materias</h2>
                <div class="h-64"><canvas id="grafico-resultados"></canvas></div>
            </div>
        @endif
    </div>
</x-layouts.app>
