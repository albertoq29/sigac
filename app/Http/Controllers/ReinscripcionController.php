<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Services\GestionInscripciones;
use App\Services\Reinscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reasignación de materias del año anterior al nuevo año escolar.
 */
class ReinscripcionController extends Controller
{
    public function index(Request $request, AnioEscolar $anio, Reinscripcion $reinscripcion): View
    {
        abort_if($anio->estaCerrado(), 422, 'El año escolar está cerrado.');

        $origenes = AnioEscolar::query()->where('nombre', '<', $anio->nombre)->recientes()->get();
        $origen = $origenes->firstWhere('id', $request->integer('origen')) ?? $origenes->first();

        $catedrasDestino = $anio->catedras()->ordenadas()->with(['asignatura', 'nivel', 'profesor'])->get();

        $pendientes = $origen
            ? $reinscripcion->pendientes($origen, $anio)
                ->whereHas('estudiante', fn ($q) => $q->buscar($request->query('q')))
                ->with(['estudiante', 'inscripciones.catedra.asignatura', 'inscripciones.catedra.nivel'])
                ->join('estudiantes', 'estudiantes.id', '=', 'matriculas.estudiante_id')
                ->orderBy('estudiantes.apellidos_nombres')
                ->select('matriculas.*')
                ->paginate(30)
                ->withQueryString()
            : null;

        $sugerencias = [];
        $promovidos = [];
        foreach ($pendientes ?? [] as $matricula) {
            $sugerencias[$matricula->id] = $reinscripcion->sugerencias($matricula, $anio, $catedrasDestino);
            $promovidos[$matricula->id] = $reinscripcion->promovido($matricula);
        }

        return view('reinscripcion.index', [
            'anio' => $anio,
            'origen' => $origen,
            'origenes' => $origenes,
            'pendientes' => $pendientes,
            'sugerencias' => $sugerencias,
            'promovidos' => $promovidos,
            'totalCatedras' => $catedrasDestino->count(),
            'yaInscritos' => $anio->matriculas()->count(),
        ]);
    }

    /** Inscribe a los seleccionados con las materias sugeridas. */
    public function store(Request $request, AnioEscolar $anio, Reinscripcion $reinscripcion, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'matriculas' => ['required', 'array', 'min:1'],
            'matriculas.*' => ['integer', Rule::exists('matriculas', 'id')],
        ], ['matriculas.required' => 'Seleccione al menos un estudiante.']);

        if ($anio->estaCerrado()) {
            throw new ReglaNegocioException('El año escolar está cerrado.');
        }

        $catedrasDestino = $anio->catedras()->with(['asignatura', 'nivel'])->get();
        $total = 0;

        DB::transaction(function () use ($datos, $anio, $reinscripcion, $gestion, $catedrasDestino, &$total) {
            $matriculas = Matricula::query()
                ->whereIn('id', $datos['matriculas'])
                ->with(['estudiante', 'inscripciones.catedra.nivel'])
                ->get();

            foreach ($matriculas as $anterior) {
                if ($anterior->estudiante->matriculas()->where('anio_escolar_id', $anio->id)->exists()) {
                    continue;
                }

                $catedras = $reinscripcion->sugerencias($anterior, $anio, $catedrasDestino)->pluck('id')->all();
                $gestion->inscribir($anterior->estudiante, $anio, $catedras, $anterior->seccion);
                $total++;
            }
        });

        return back()->with('exito', "{$total} estudiante(s) inscritos en {$anio->nombre} con sus materias sugeridas.");
    }
}
