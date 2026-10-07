<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Catedra;
use App\Models\Inscripcion;
use App\Models\Matricula;
use App\Services\GestionInscripciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Materias (cátedras) que cursa un estudiante dentro de su inscripción anual.
 */
class InscripcionController extends Controller
{
    public function store(Request $request, Matricula $matricula, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'catedras' => ['required', 'array', 'min:1'],
            'catedras.*' => ['integer', Rule::exists('catedras', 'id')->where('anio_escolar_id', $matricula->anio_escolar_id)],
        ], ['catedras.required' => 'Seleccione al menos una cátedra.']);

        $gestion->asignarCatedras($matricula, $datos['catedras']);
        $matricula->estudiante->sincronizarEstado();

        return back()->with('exito', 'Cátedras asignadas.');
    }

    public function update(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        $datos = $request->validate(['horario' => ['nullable', 'string', 'max:80']]);

        $inscripcion->update(['horario' => $datos['horario'] ?: null]);

        return back()->with('exito', 'Horario actualizado.');
    }

    public function retirar(Request $request, Inscripcion $inscripcion, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_retiro' => ['required', 'date', 'before_or_equal:today'],
            'motivo_retiro' => ['nullable', 'string', 'max:255'],
        ], [], ['fecha_retiro' => 'fecha de retiro', 'motivo_retiro' => 'motivo']);

        $gestion->retirarInscripcion($inscripcion, Carbon::parse($datos['fecha_retiro']), $datos['motivo_retiro'] ?? null);

        return back()->with('exito', 'Materia retirada.');
    }

    public function reincorporar(Inscripcion $inscripcion, GestionInscripciones $gestion): RedirectResponse
    {
        $gestion->reincorporarInscripcion($inscripcion);

        return back()->with('exito', 'Materia reincorporada.');
    }

    /** Cambio de sección/cátedra: la anterior queda retirada y se inscribe en la nueva. */
    public function trasladar(Request $request, Inscripcion $inscripcion, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'catedra_id' => ['required', 'integer', Rule::exists('catedras', 'id')
                ->where('anio_escolar_id', $inscripcion->catedra->anio_escolar_id)
                ->whereNot('id', $inscripcion->catedra_id)],
        ], [], ['catedra_id' => 'cátedra de destino']);

        $destino = Catedra::query()->with(['asignatura', 'nivel'])->findOrFail($datos['catedra_id']);

        if ($inscripcion->matricula->estudiante->inscripciones()->where('catedra_id', $destino->id)->where('estado', 'cursando')->exists()) {
            throw new ReglaNegocioException('El estudiante ya cursa esa cátedra.');
        }

        DB::transaction(function () use ($inscripcion, $destino, $gestion) {
            $gestion->retirarInscripcion($inscripcion, today(), "Traslado a {$destino->nombre}");
            $gestion->asignarCatedras($inscripcion->matricula, [$destino->id]);
        });

        return back()->with('exito', "Estudiante trasladado a {$destino->nombre}.");
    }

    public function destroy(Inscripcion $inscripcion, GestionInscripciones $gestion): RedirectResponse
    {
        $gestion->eliminarInscripcion($inscripcion);

        return back()->with('exito', 'Asignación eliminada.');
    }
}
