<?php

namespace App\Http\Controllers;

use App\Enums\EstadoInscripcion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Ajuste;
use App\Models\Catedra;
use App\Models\Lapso;
use App\Models\Nota;
use App\Services\CalculadoraNotas;
use App\Services\CierreNotas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotaController extends Controller
{
    /** Resumen: notas por lapso, nota final, definitiva y cierre de notas. */
    public function index(Request $request, Catedra $catedra, CalculadoraNotas $calculadora): View
    {
        $this->authorize('view', $catedra);

        $catedra->load(['anioEscolar', 'asignatura', 'nivel', 'profesor', 'notasCerradasPor', 'lapsos.evaluaciones.notas', 'lapsos.cerradoPor']);

        $inscripciones = $this->listaDeClase($catedra);
        $resumen = $calculadora->resumenCatedra($catedra, $inscripciones);

        return view('notas.index', [
            'catedra' => $catedra,
            'inscripciones' => $inscripciones,
            'resumen' => $resumen,
            'puedeRegistrar' => $request->user()->can('registrar', $catedra) && $catedra->anioEscolar->permiteRegistros(),
            'notaMinima' => Ajuste::notaMinimaAprobatoria(),
        ]);
    }

    /** Planilla de un lapso: evaluaciones (porcentajes) y notas. */
    public function lapso(Request $request, Catedra $catedra, int $numero, CalculadoraNotas $calculadora): View
    {
        $this->authorize('view', $catedra);

        $catedra->load(['anioEscolar', 'asignatura', 'nivel', 'profesor', 'lapsos']);
        $lapso = $this->obtenerLapso($catedra, $numero);
        $lapso->load('evaluaciones.notas');

        $inscripciones = $this->listaDeClase($catedra);
        $notasLapso = $calculadora->notasLapso($lapso, $inscripciones);

        $valores = [];
        foreach ($lapso->evaluaciones as $evaluacion) {
            foreach ($evaluacion->notas as $nota) {
                $valores[$nota->inscripcion_id][$evaluacion->id] = $nota->valor;
            }
        }

        return view('notas.lapso', [
            'catedra' => $catedra,
            'lapso' => $lapso,
            'inscripciones' => $inscripciones,
            'valores' => $valores,
            'notasLapso' => $notasLapso,
            'editable' => $this->editable($request, $catedra, $lapso),
            'notaMaxima' => Ajuste::notaMaxima(),
        ]);
    }

    public function guardar(Request $request, Catedra $catedra, int $numero): RedirectResponse
    {
        $this->authorize('registrar', $catedra);

        $lapso = $this->obtenerLapso($catedra, $numero);
        $this->asegurarEditable($request, $catedra, $lapso);

        $maxima = Ajuste::notaMaxima();

        $request->merge([
            'notas' => collect($request->input('notas', []))
                ->map(fn ($fila) => collect($fila)->map(fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v)->all())
                ->all(),
        ]);

        $request->validate([
            'notas' => ['array'],
            'notas.*' => ['array'],
            'notas.*.*' => ['nullable', 'numeric', 'min:0', "max:{$maxima}"],
        ], [
            'notas.*.*.numeric' => 'Las notas deben ser números.',
            'notas.*.*.max' => "Las notas no pueden ser mayores que {$maxima}.",
            'notas.*.*.min' => 'Las notas no pueden ser negativas.',
        ]);

        $evaluacionIds = $lapso->evaluaciones()->pluck('id')->all();
        $inscripcionIds = $catedra->inscripciones()->where('estado', EstadoInscripcion::Cursando->value)->pluck('id')->all();

        DB::transaction(function () use ($request, $evaluacionIds, $inscripcionIds) {
            foreach ($request->input('notas', []) as $inscripcionId => $fila) {
                if (! in_array((int) $inscripcionId, $inscripcionIds, true)) {
                    continue;
                }

                foreach ($fila as $evaluacionId => $valor) {
                    if (! in_array((int) $evaluacionId, $evaluacionIds, true)) {
                        continue;
                    }

                    Nota::query()->updateOrCreate(
                        ['evaluacion_id' => $evaluacionId, 'inscripcion_id' => $inscripcionId],
                        ['valor' => $valor === '' || $valor === null ? null : round((float) $valor, 2)]
                    );
                }
            }
        });

        return back()->with('exito', 'Notas guardadas.');
    }

    public function cerrarLapso(Request $request, Catedra $catedra, int $numero, CierreNotas $cierre): RedirectResponse
    {
        $this->authorize('registrar', $catedra);

        $lapso = $this->obtenerLapso($catedra, $numero);
        $cierre->cerrarLapso($lapso, $request->user());

        return back()->with('exito', "{$lapso->nombre} cerrado. Sus notas ya no se pueden modificar.");
    }

    public function reabrirLapso(Catedra $catedra, int $numero, CierreNotas $cierre): RedirectResponse
    {
        $lapso = $this->obtenerLapso($catedra, $numero);
        $cierre->reabrirLapso($lapso);

        return back()->with('exito', "{$lapso->nombre} reabierto.");
    }

    public function cerrarCatedra(Request $request, Catedra $catedra, CierreNotas $cierre): RedirectResponse
    {
        $this->authorize('registrar', $catedra);

        $cierre->cerrarNotasCatedra($catedra, $request->user());

        return redirect()->route('notas.index', $catedra)
            ->with('exito', 'Cierre de notas realizado: se calcularon las notas definitivas.');
    }

    public function reabrirCatedra(Catedra $catedra, CierreNotas $cierre): RedirectResponse
    {
        $cierre->reabrirNotasCatedra($catedra);

        return redirect()->route('notas.index', $catedra)->with('exito', 'Cierre de notas reabierto.');
    }

    private function obtenerLapso(Catedra $catedra, int $numero): Lapso
    {
        $lapso = $catedra->lapsos()->where('numero', $numero)->firstOrFail();
        $lapso->setRelation('catedra', $catedra);

        return $lapso;
    }

    /** Estudiantes con nota en la cátedra (los retirados se muestran aparte, sin editar). */
    private function listaDeClase(Catedra $catedra)
    {
        return $catedra->inscripciones()
            ->with('estudiante')
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->estaRetirada() <=> $b->estaRetirada(),
                fn ($a, $b) => strcmp($a->estudiante->apellidos_nombres, $b->estudiante->apellidos_nombres),
            ])
            ->values();
    }

    private function editable(Request $request, Catedra $catedra, Lapso $lapso): bool
    {
        return $request->user()->can('registrar', $catedra)
            && $catedra->anioEscolar->permiteRegistros()
            && ! $catedra->notasCerradas()
            && ! $lapso->estaCerrado();
    }

    private function asegurarEditable(Request $request, Catedra $catedra, Lapso $lapso): void
    {
        if (! $catedra->anioEscolar->permiteRegistros()) {
            throw new ReglaNegocioException("El año escolar {$catedra->anioEscolar->nombre} no está en curso.");
        }

        if ($catedra->notasCerradas()) {
            throw new ReglaNegocioException('Las notas de la cátedra están cerradas.');
        }

        if ($lapso->estaCerrado()) {
            throw new ReglaNegocioException("El {$lapso->nombre} está cerrado.");
        }
    }
}
