<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoInscripcion;
use App\Models\Catedra;
use App\Models\Jornada;
use App\Services\RegistroAsistencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AsistenciaController extends Controller
{
    public function create(Request $request, Catedra $catedra, RegistroAsistencia $registro): View
    {
        $this->authorize('view', $catedra);

        $catedra->load(['anioEscolar', 'asignatura', 'nivel', 'profesor']);

        $fecha = $this->fecha($request->query('fecha'));
        $jornada = $catedra->jornadas()->whereDate('fecha', $fecha)
            ->with(['asistencias', 'cerradaPor', 'reaperturas.reabiertaPor', 'reaperturas.revisadaPor'])
            ->first();
        $registrados = $jornada?->asistencias->pluck('estado', 'inscripcion_id') ?? collect();

        // Lista: quienes cursan + quienes ya tenían registro ese día (aunque luego se retiraran).
        $inscripciones = $catedra->inscripciones()
            ->with('estudiante')
            ->where(fn ($q) => $q->where('estado', '!=', EstadoInscripcion::Retirada->value)
                ->orWhereIn('id', $registrados->keys()))
            ->get()
            ->sortBy(fn ($i) => $i->estudiante->apellidos_nombres)
            ->values();

        $estados = $inscripciones->mapWithKeys(fn ($i) => [
            $i->id => ($registrados[$i->id] ?? null)?->value ?? EstadoAsistencia::Presente->value,
        ]);

        $jornadas = $catedra->jornadas()
            ->with('registradoPor')
            ->withCount([
                'reaperturas',
                'asistencias',
                'asistencias as ausentes_count' => fn ($q) => $q->where('estado', EstadoAsistencia::Ausente->value),
            ])
            ->orderByDesc('fecha')
            ->limit(40)
            ->get();

        $puedeGestionar = $catedra->anioEscolar->permiteRegistros() && $request->user()->can('registrar', $catedra);
        $puedeRegistrar = $puedeGestionar && ! $jornada?->estaCerrada();

        // Historial de reaperturas del día con los cambios realizados.
        $reaperturas = ($jornada?->reaperturas ?? collect())->map(fn ($r) => [
            'reapertura' => $r->setRelation('jornada', $jornada),
            'cambios' => $registro->cambiosDe($r),
        ]);

        return view('asistencia.create', compact(
            'catedra', 'fecha', 'jornada', 'inscripciones', 'estados', 'jornadas', 'puedeRegistrar', 'puedeGestionar', 'reaperturas',
        ));
    }

    public function store(Request $request, Catedra $catedra, RegistroAsistencia $registro): RedirectResponse
    {
        $this->authorize('registrar', $catedra);

        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'estados' => ['required', 'array'],
            'estados.*' => ['required', Rule::enum(EstadoAsistencia::class)],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'estados.required' => 'La cátedra no tiene estudiantes para pasar lista.',
        ]);

        $fecha = Carbon::parse($datos['fecha']);
        $jornada = $registro->guardar($catedra, $fecha, $datos['estados'], $request->user(), $datos['observacion'] ?? null);
        $cerrar = $request->boolean('cerrar');

        if ($cerrar) {
            $registro->cerrarJornada($jornada, $request->user());
        }

        return redirect()
            ->route('asistencia.create', ['catedra' => $catedra, 'fecha' => $fecha->toDateString()])
            ->with('exito', 'Asistencia del '.$fecha->translatedFormat('d \d\e F').($cerrar ? ' guardada y cerrada.' : ' guardada.'));
    }

    /** Cierra la asistencia del día: ya no se puede modificar sin reabrirla. */
    public function cerrar(Request $request, Jornada $jornada, RegistroAsistencia $registro): RedirectResponse
    {
        $this->authorize('registrar', $jornada->catedra);

        $registro->cerrarJornada($jornada, $request->user());

        return back()->with('exito', 'Asistencia del '.$jornada->fecha->format('d/m/Y').' cerrada.');
    }

    /** Reabre la asistencia del día: el motivo es obligatorio y Control de Estudios lo verá. */
    public function reabrir(Request $request, Jornada $jornada, RegistroAsistencia $registro): RedirectResponse
    {
        $this->authorize('registrar', $jornada->catedra);

        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'motivo.required' => 'Debe explicar por qué se reabre la asistencia.',
                'motivo.min' => 'Explique el motivo con al menos 10 caracteres.',
            ],
        );

        $registro->reabrirJornada($jornada, $request->user(), $datos['motivo']);

        return back()->with('aviso', 'Asistencia reabierta. Los cambios que haga quedarán registrados para Control de Estudios; ciérrela de nuevo al terminar.');
    }

    public function mensual(Request $request, Catedra $catedra, RegistroAsistencia $registro): View
    {
        $this->authorize('view', $catedra);

        $catedra->load(['anioEscolar', 'asignatura', 'nivel', 'profesor']);

        $mes = $request->query('mes');
        if (! is_string($mes) || ! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $ultima = $catedra->jornadas()->max('fecha');
            $mes = $ultima ? Carbon::parse($ultima)->format('Y-m') : now()->format('Y-m');
        }

        [$anio, $numeroMes] = array_map('intval', explode('-', $mes));
        $matriz = $registro->matrizMensual($catedra, $anio, $numeroMes);

        $meses = $catedra->jornadas()
            ->pluck('fecha')
            ->map(fn ($f) => $f->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();

        return view('asistencia.mensual', [
            'catedra' => $catedra,
            'mes' => $mes,
            'inicioMes' => Carbon::create($anio, $numeroMes, 1),
            'matriz' => $matriz,
            'meses' => $meses,
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }

    public function destroy(Jornada $jornada, RegistroAsistencia $registro): RedirectResponse
    {
        $registro->eliminarJornada($jornada);

        return back()->with('exito', 'Jornada de asistencia eliminada.');
    }

    private function fecha(mixed $valor): Carbon
    {
        try {
            $fecha = is_string($valor) && $valor !== '' ? Carbon::parse($valor)->startOfDay() : today();
        } catch (\Throwable) {
            $fecha = today();
        }

        return $fecha->isAfter(today()) ? today() : $fecha;
    }
}
