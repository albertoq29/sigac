<?php

namespace App\Http\Controllers;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMatricula;
use App\Models\Estudiante;
use App\Services\RegistroAsistencia;
use App\Support\ContextoAnio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EstadisticaController extends Controller
{
    public function __invoke(): View
    {
        $anio = ContextoAnio::anio();
        $anioId = $anio?->id;

        $inscripciones = fn () => DB::table('inscripciones')
            ->join('catedras', 'catedras.id', '=', 'inscripciones.catedra_id')
            ->where('catedras.anio_escolar_id', $anioId);

        $asignaturas = $inscripciones()
            ->join('asignaturas', 'asignaturas.id', '=', 'catedras.asignatura_id')
            ->where('inscripciones.estado', '!=', EstadoInscripcion::Retirada->value)
            ->groupBy('asignaturas.nombre')
            ->selectRaw('asignaturas.nombre as etiqueta, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $niveles = $inscripciones()
            ->join('niveles', 'niveles.id', '=', 'catedras.nivel_id')
            ->where('inscripciones.estado', '!=', EstadoInscripcion::Retirada->value)
            ->groupBy('niveles.nombre', 'niveles.orden')
            ->selectRaw('niveles.nombre as etiqueta, COUNT(*) as total')
            ->orderBy('niveles.orden')
            ->get();

        $resultados = $inscripciones()
            ->groupBy('inscripciones.estado')
            ->selectRaw('inscripciones.estado, COUNT(*) as total')
            ->pluck('total', 'estado');

        // Asistencias por día y estado; se agrupan por mes en PHP (portable entre motores).
        $porDia = DB::table('asistencias')
            ->join('jornadas', 'jornadas.id', '=', 'asistencias.jornada_id')
            ->join('catedras', 'catedras.id', '=', 'jornadas.catedra_id')
            ->where('catedras.anio_escolar_id', $anioId)
            ->groupBy('jornadas.fecha', 'asistencias.estado')
            ->selectRaw('jornadas.fecha, asistencias.estado, COUNT(*) as total')
            ->get();

        $meses = [];
        foreach ($porDia as $fila) {
            $mes = substr((string) $fila->fecha, 0, 7);
            $meses[$mes] ??= ['P' => 0, 'A' => 0, 'R' => 0, 'J' => 0];
            $meses[$mes][$fila->estado] += (int) $fila->total;
        }
        ksort($meses);

        $docentes = DB::table('jornadas')
            ->join('catedras', 'catedras.id', '=', 'jornadas.catedra_id')
            ->join('users', 'users.id', '=', 'jornadas.registrado_por')
            ->where('catedras.anio_escolar_id', $anioId)
            ->groupBy('users.name')
            ->selectRaw('users.name as etiqueta, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $estudiantes = Estudiante::query()
            ->whereHas('matriculas', fn ($q) => $q->where('anio_escolar_id', $anioId))
            ->get(['id', 'sexo', 'fecha_nacimiento']);

        $edades = array_fill_keys(['0-5', '6-10', '11-15', '16-20', '21-30', '31-40', '41-50', '51-60', '61+'], 0);
        foreach ($estudiantes as $estudiante) {
            $edad = $estudiante->edad;
            if ($edad === null) {
                continue;
            }
            $rango = match (true) {
                $edad <= 5 => '0-5',
                $edad <= 10 => '6-10',
                $edad <= 15 => '11-15',
                $edad <= 20 => '16-20',
                $edad <= 30 => '21-30',
                $edad <= 40 => '31-40',
                $edad <= 50 => '41-50',
                $edad <= 60 => '51-60',
                default => '61+',
            };
            $edades[$rango]++;
        }

        $totalAsistencias = array_sum(array_map('array_sum', $meses));
        $ausencias = array_sum(array_column($meses, 'A'));
        $conEdad = $estudiantes->filter(fn ($e) => $e->edad !== null);

        $kpis = [
            'estudiantes' => $estudiantes->count(),
            'inscritos' => $anio?->matriculas()->where('estado', EstadoMatricula::Inscrito->value)->count() ?? 0,
            'retirados' => $anio?->matriculas()->where('estado', EstadoMatricula::Retirado->value)->count() ?? 0,
            'materias' => (int) $inscripciones()->where('inscripciones.estado', '!=', EstadoInscripcion::Retirada->value)->count(),
            'jornadas' => DB::table('jornadas')->join('catedras', 'catedras.id', '=', 'jornadas.catedra_id')->where('catedras.anio_escolar_id', $anioId)->count(),
            'asistencias' => $totalAsistencias,
            'porcentaje' => RegistroAsistencia::porcentaje($totalAsistencias, $ausencias),
            'edad_promedio' => $conEdad->isNotEmpty() ? round($conEdad->avg('edad'), 1) : null,
        ];

        $etiquetasResultados = collect(EstadoInscripcion::cases())
            ->filter(fn ($e) => ($resultados[$e->value] ?? 0) > 0);

        $datos = [
            'asignaturas' => ['labels' => $asignaturas->pluck('etiqueta'), 'data' => $asignaturas->pluck('total')],
            'niveles' => ['labels' => $niveles->pluck('etiqueta'), 'data' => $niveles->pluck('total')],
            'docentes' => ['labels' => $docentes->pluck('etiqueta'), 'data' => $docentes->pluck('total')],
            'meses' => [
                'labels' => array_map(fn ($m) => ucfirst(Carbon::createFromFormat('Y-m-d', $m.'-01')->translatedFormat('M Y')), array_keys($meses)),
                'P' => array_column($meses, 'P'),
                'A' => array_column($meses, 'A'),
                'R' => array_column($meses, 'R'),
                'J' => array_column($meses, 'J'),
            ],
            'sexo' => [
                'labels' => ['Femenino', 'Masculino', 'Sin dato'],
                'data' => [
                    $estudiantes->where('sexo', 'F')->count(),
                    $estudiantes->where('sexo', 'M')->count(),
                    $estudiantes->whereNull('sexo')->count(),
                ],
            ],
            'edades' => ['labels' => array_keys($edades), 'data' => array_values($edades)],
            'resultados' => [
                'labels' => $etiquetasResultados->map->label()->values(),
                'data' => $etiquetasResultados->map(fn ($e) => $resultados[$e->value])->values(),
            ],
        ];

        return view('estadisticas.index', compact('anio', 'kpis', 'datos'));
    }
}
