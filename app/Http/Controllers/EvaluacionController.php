<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Catedra;
use App\Models\Evaluacion;
use App\Models\Lapso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Evaluaciones de un lapso: el profesor decide cuántas y qué porcentaje vale cada una.
 */
class EvaluacionController extends Controller
{
    public function store(Request $request, Catedra $catedra, int $numero): RedirectResponse
    {
        $this->authorize('registrar', $catedra);

        $lapso = $catedra->lapsos()->where('numero', $numero)->firstOrFail();
        $lapso->setRelation('catedra', $catedra);
        $this->asegurarEditable($lapso);

        $datos = $this->validar($request, $lapso);

        $lapso->evaluaciones()->create($datos + [
            'orden' => (int) $lapso->evaluaciones()->max('orden') + 1,
        ]);

        return back()->with('exito', 'Evaluación agregada.');
    }

    public function update(Request $request, Evaluacion $evaluacion): RedirectResponse
    {
        $lapso = $evaluacion->lapso;
        $this->authorize('registrar', $lapso->catedra);
        $this->asegurarEditable($lapso);

        $evaluacion->update($this->validar($request, $lapso, $evaluacion));

        return back()->with('exito', 'Evaluación actualizada.');
    }

    public function destroy(Evaluacion $evaluacion): RedirectResponse
    {
        $lapso = $evaluacion->lapso;
        $this->authorize('registrar', $lapso->catedra);
        $this->asegurarEditable($lapso);

        $evaluacion->delete();

        return back()->with('exito', 'Evaluación eliminada junto con sus notas.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, Lapso $lapso, ?Evaluacion $actual = null): array
    {
        $request->merge(['peso' => str_replace(',', '.', (string) $request->input('peso'))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'peso' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fecha' => ['nullable', 'date'],
        ], [], ['peso' => 'porcentaje']);

        $otros = (float) $lapso->evaluaciones()
            ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
            ->sum('peso');

        if ($otros + (float) $datos['peso'] > 100.001) {
            $disponible = max(0, 100 - $otros);
            throw new ReglaNegocioException(
                "Los porcentajes del {$lapso->nombre} no pueden pasar de 100%. Disponible: {$disponible}%."
            );
        }

        return $datos;
    }

    private function asegurarEditable(Lapso $lapso): void
    {
        $catedra = $lapso->catedra;

        if (! $catedra->anioEscolar->permiteRegistros()) {
            throw new ReglaNegocioException("El año escolar {$catedra->anioEscolar->nombre} no está en curso.");
        }

        if ($catedra->notasCerradas() || $lapso->estaCerrado()) {
            throw new ReglaNegocioException("El {$lapso->nombre} está cerrado; no se pueden modificar sus evaluaciones.");
        }
    }
}
