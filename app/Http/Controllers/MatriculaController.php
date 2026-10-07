<?php

namespace App\Http\Controllers;

use App\Models\AnioEscolar;
use App\Models\Estudiante;
use App\Models\Matricula;
use App\Services\GestionInscripciones;
use App\Services\Reinscripcion;
use App\Support\ContextoAnio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inscripción anual del estudiante (activo/inactivo) y su retiro.
 */
class MatriculaController extends Controller
{
    public function create(Request $request, Estudiante $estudiante, Reinscripcion $reinscripcion): View|RedirectResponse
    {
        $abiertos = AnioEscolar::query()->abiertos()->recientes()->get();

        if ($abiertos->isEmpty()) {
            return redirect()->route('anios.index')
                ->with('error', 'No hay un año escolar abierto. Cree el nuevo año escolar para poder inscribir.');
        }

        $contexto = ContextoAnio::anio();
        $anio = $abiertos->firstWhere('id', $request->integer('anio'))
            ?? ($contexto && ! $contexto->estaCerrado() ? $abiertos->firstWhere('id', $contexto->id) : null)
            ?? $abiertos->first();

        $existente = $estudiante->matriculas()->where('anio_escolar_id', $anio->id)->first();

        if ($existente?->estaInscrito()) {
            return redirect()->route('estudiantes.show', $estudiante)
                ->with('aviso', "El estudiante ya está inscrito en {$anio->nombre}.");
        }

        $catedras = $anio->catedras()->ordenadas()->with(['asignatura', 'nivel', 'profesor'])->whereNull('notas_cerradas_at')->get();

        // Materias del año anterior y sugerencias para el nuevo año.
        $anterior = $estudiante->matriculas()
            ->with(['anioEscolar', 'inscripciones.catedra.asignatura', 'inscripciones.catedra.nivel'])
            ->whereHas('anioEscolar', fn ($q) => $q->where('nombre', '<', $anio->nombre))
            ->get()
            ->sortByDesc(fn ($m) => $m->anioEscolar->nombre)
            ->first();

        $sugeridas = $anterior ? $reinscripcion->sugerencias($anterior, $anio, $catedras)->pluck('id') : collect();
        $promovido = $anterior ? $reinscripcion->promovido($anterior) : null;
        $aprobadas = GestionInscripciones::materiasAprobadas($estudiante->id);

        return view('matriculas.create', compact(
            'estudiante', 'anio', 'abiertos', 'catedras', 'anterior', 'sugeridas', 'existente', 'promovido', 'aprobadas',
        ));
    }

    public function store(Request $request, Estudiante $estudiante, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'anio_escolar_id' => ['required', Rule::exists('anios_escolares', 'id')->whereNot('estado', 'cerrado')],
            'seccion' => ['nullable', 'string', 'max:40'],
            'fecha_inscripcion' => ['nullable', 'date', 'before_or_equal:today'],
            'catedras' => ['array'],
            'catedras.*' => ['integer', Rule::exists('catedras', 'id')->where('anio_escolar_id', $request->integer('anio_escolar_id'))],
        ], [], ['anio_escolar_id' => 'año escolar']);

        $anio = AnioEscolar::query()->findOrFail($datos['anio_escolar_id']);

        $gestion->inscribir(
            $estudiante,
            $anio,
            $datos['catedras'] ?? [],
            $datos['seccion'] ?? null,
            isset($datos['fecha_inscripcion']) ? Carbon::parse($datos['fecha_inscripcion']) : null,
        );

        return redirect()->route('estudiantes.show', $estudiante)
            ->with('exito', "Estudiante inscrito en el año escolar {$anio->nombre}.");
    }

    public function update(Request $request, Matricula $matricula): RedirectResponse
    {
        $datos = $request->validate([
            'seccion' => ['nullable', 'string', 'max:40'],
            'fecha_inscripcion' => ['nullable', 'date'],
        ]);

        if ($matricula->anioEscolar->estaCerrado()) {
            return back()->with('error', 'El año escolar está cerrado.');
        }

        $matricula->update($datos);

        return back()->with('exito', 'Inscripción actualizada.');
    }

    public function retirar(Request $request, Matricula $matricula, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_retiro' => ['required', 'date', 'before_or_equal:today'],
            'motivo_retiro' => ['nullable', 'string', 'max:500'],
        ], [], ['fecha_retiro' => 'fecha de retiro', 'motivo_retiro' => 'motivo']);

        $gestion->retirarEstudiante($matricula, Carbon::parse($datos['fecha_retiro']), $datos['motivo_retiro'] ?? null);

        return back()->with('exito', 'Estudiante retirado. Sus materias quedaron registradas como retiradas.');
    }

    public function reincorporar(Matricula $matricula, GestionInscripciones $gestion): RedirectResponse
    {
        $gestion->reincorporarEstudiante($matricula);

        return back()->with('exito', 'Estudiante reincorporado.');
    }
}
