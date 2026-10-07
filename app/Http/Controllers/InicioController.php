<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEstudiante;
use App\Enums\EstadoMatricula;
use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Jornada;
use App\Models\ReaperturaJornada;
use App\Models\User;
use App\Services\RegistroAsistencia;
use App\Support\ContextoAnio;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $anio = ContextoAnio::anio();
        $usuario = $request->user();

        if ($usuario->esProfesor()) {
            $catedras = $anio
                ? $usuario->catedras()
                    ->delAnio($anio)
                    ->ordenadas()
                    ->with(['asignatura', 'nivel', 'lapsos'])
                    ->withCount(['inscripcionesVigentes as alumnos_count', 'jornadas'])
                    ->withMax('jornadas', 'fecha')
                    ->get()
                : collect();

            return view('inicio.profesor', compact('anio', 'catedras'));
        }

        $enAnio = fn ($q) => $q->where('anio_escolar_id', $anio?->id);

        $asistencias = Asistencia::query()
            ->whereHas('jornada.catedra', $enAnio)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN estado = 'A' THEN 1 ELSE 0 END) as ausencias")
            ->first();

        $kpis = [
            'activos' => Estudiante::query()->where('estado', EstadoEstudiante::Activo->value)->count(),
            'inactivos' => Estudiante::query()->where('estado', EstadoEstudiante::Inactivo->value)->count(),
            'inscritos' => $anio ? $anio->matriculas()->where('estado', EstadoMatricula::Inscrito->value)->count() : 0,
            'retirados' => $anio ? $anio->matriculas()->where('estado', EstadoMatricula::Retirado->value)->count() : 0,
            'catedras' => $anio ? $anio->catedras()->count() : 0,
            'profesores' => User::query()->profesores()->activos()->count(),
            'jornadas' => Jornada::query()->whereHas('catedra', $enAnio)->count(),
            'asistencia' => RegistroAsistencia::porcentaje((int) $asistencias->total, (int) $asistencias->ausencias),
        ];

        $alertas = $anio ? [
            'sin_profesor' => $anio->catedras()->whereNull('profesor_id')->count(),
            'sin_cierre' => $anio->estaEnCurso() ? $anio->catedras()->whereNull('notas_cerradas_at')->count() : 0,
            'sin_alumnos' => $anio->catedras()->whereDoesntHave('inscripcionesVigentes')->count(),
            'reaperturas' => ReaperturaJornada::query()->sinRevisar()->whereHas('jornada.catedra', $enAnio)->count(),
        ] : [];

        $recientes = Jornada::query()
            ->whereHas('catedra', $enAnio)
            ->with(['catedra.asignatura', 'catedra.nivel', 'catedra.profesor', 'registradoPor'])
            ->withCount([
                'asistencias',
                'asistencias as ausentes_count' => fn ($q) => $q->where('estado', 'A'),
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit(12)
            ->get();

        return view('inicio.control', compact('anio', 'kpis', 'alertas', 'recientes'));
    }
}
