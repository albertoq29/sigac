<?php

namespace App\Http\Controllers;

use App\Models\ReaperturaJornada;
use App\Services\RegistroAsistencia;
use App\Support\ContextoAnio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Control de Estudios: reaperturas de asistencia con su motivo y los cambios hechos.
 */
class ReaperturaController extends Controller
{
    public function index(Request $request, RegistroAsistencia $registro): View
    {
        $anio = ContextoAnio::anio();
        $filtro = $request->query('ver', 'pendientes');

        $reaperturas = ReaperturaJornada::query()
            ->whereHas('jornada.catedra', fn ($q) => $q->where('anio_escolar_id', $anio?->id))
            ->when($filtro === 'pendientes', fn ($q) => $q->whereNull('revisada_at'))
            ->with(['jornada.catedra.asignatura', 'jornada.catedra.nivel', 'jornada.catedra.profesor', 'reabiertaPor', 'revisadaPor'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $cambios = $reaperturas->getCollection()->mapWithKeys(fn ($r) => [$r->id => $registro->cambiosDe($r)]);

        $pendientes = ReaperturaJornada::query()
            ->whereHas('jornada.catedra', fn ($q) => $q->where('anio_escolar_id', $anio?->id))
            ->sinRevisar()
            ->count();

        return view('asistencia.reaperturas', compact('anio', 'reaperturas', 'cambios', 'filtro', 'pendientes'));
    }

    public function revisar(Request $request, ReaperturaJornada $reapertura): RedirectResponse
    {
        $reapertura->update(['revisada_at' => now(), 'revisada_por' => $request->user()->id]);

        return back()->with('exito', 'Reapertura marcada como revisada.');
    }
}
