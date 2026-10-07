<?php

namespace App\Http\Controllers;

use App\Enums\EstadoInscripcion;
use App\Models\Ajuste;
use App\Models\Catedra;
use App\Models\Estudiante;
use App\Services\CalculadoraNotas;
use App\Services\RegistroAsistencia;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Documentos imprimibles: constancia de estudio, historial académico y acta de notas.
 */
class DocumentoController extends Controller
{
    public function constancia(Request $request, Estudiante $estudiante): View
    {
        $matricula = $request->integer('anio')
            ? $estudiante->matriculas()->where('anio_escolar_id', $request->integer('anio'))->first()
            : $estudiante->matriculaVigente();

        abort_unless($matricula, 404, 'El estudiante no tiene una inscripción vigente.');

        $matricula->load(['anioEscolar', 'inscripciones' => fn ($q) => $q
            ->where('estado', '!=', EstadoInscripcion::Retirada->value)
            ->with(['catedra.asignatura', 'catedra.nivel', 'catedra.profesor'])]);

        $inscripciones = $matricula->inscripciones
            ->sortBy(fn ($i) => [$i->catedra->nivel->orden, $i->catedra->asignatura->nombre])
            ->values();

        return view('documentos.constancia', [
            'estudiante' => $estudiante,
            'matricula' => $matricula,
            'inscripciones' => $inscripciones,
            'ajustes' => Ajuste::todos(),
        ]);
    }

    public function historial(Estudiante $estudiante, RegistroAsistencia $asistencia): View
    {
        $matriculas = $estudiante->matriculas()
            ->with(['anioEscolar', 'inscripciones.catedra.asignatura', 'inscripciones.catedra.nivel', 'inscripciones.catedra.profesor'])
            ->get()
            ->sortBy(fn ($m) => $m->anioEscolar->nombre)
            ->values();

        return view('documentos.historial', [
            'estudiante' => $estudiante,
            'matriculas' => $matriculas,
            'resumenAsistencia' => $asistencia->resumen($matriculas->flatMap->inscripciones->pluck('id')),
            'ajustes' => Ajuste::todos(),
        ]);
    }

    public function acta(Catedra $catedra, CalculadoraNotas $calculadora): View
    {
        $this->authorize('view', $catedra);

        $catedra->load(['anioEscolar', 'asignatura', 'nivel', 'profesor', 'lapsos.evaluaciones.notas']);

        $inscripciones = $catedra->inscripciones()
            ->with('estudiante')
            ->get()
            ->sortBy(fn ($i) => $i->estudiante->apellidos_nombres)
            ->values();

        return view('documentos.acta', [
            'catedra' => $catedra,
            'inscripciones' => $inscripciones,
            'resumen' => $calculadora->resumenCatedra($catedra, $inscripciones),
            'ajustes' => Ajuste::todos(),
        ]);
    }
}
