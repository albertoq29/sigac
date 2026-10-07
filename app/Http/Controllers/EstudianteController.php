<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEstudiante;
use App\Http\Requests\EstudianteRequest;
use App\Models\AnioEscolar;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Services\CalculadoraNotas;
use App\Services\GestionInscripciones;
use App\Services\RegistroAsistencia;
use App\Support\ContextoAnio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstudianteController extends Controller
{
    public function index(Request $request): View
    {
        $anio = ContextoAnio::anio();
        $estado = $request->query('estado');

        $estudiantes = Estudiante::query()
            ->buscar($request->query('q'))
            ->when(in_array($estado, ['activo', 'inactivo'], true), fn ($q) => $q->where('estado', $estado))
            ->when($request->integer('catedra'), fn ($q, $catedraId) => $q->whereHas('inscripciones', fn ($i) => $i->where('catedra_id', $catedraId)))
            ->with(['matriculas' => fn ($q) => $q->where('anio_escolar_id', $anio?->id)
                ->with('inscripciones.catedra.asignatura', 'inscripciones.catedra.nivel')])
            ->orderBy('apellidos_nombres')
            ->paginate(25)
            ->withQueryString();

        $totales = Estudiante::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('estudiantes.index', compact('estudiantes', 'anio', 'totales'));
    }

    public function create(): View
    {
        $anio = $this->anioParaInscribir();

        return view('estudiantes.create', [
            'estudiante' => new Estudiante,
            'anio' => $anio,
            'catedras' => $anio ? $this->catedrasDe($anio) : collect(),
        ]);
    }

    public function store(EstudianteRequest $request, GestionInscripciones $gestion): RedirectResponse
    {
        $anio = $this->anioParaInscribir();

        $request->validate([
            'inscribir' => ['boolean'],
            'seccion' => ['nullable', 'string', 'max:40'],
            'catedras' => ['array'],
            'catedras.*' => ['integer', Rule::exists('catedras', 'id')->where('anio_escolar_id', $anio?->id)],
        ]);

        $estudiante = DB::transaction(function () use ($request, $gestion, $anio) {
            $estudiante = Estudiante::query()->create([
                ...$request->datosEstudiante(),
                'estado' => EstadoEstudiante::Inactivo,
            ]);

            if ($anio && $request->boolean('inscribir')) {
                $gestion->inscribir(
                    $estudiante,
                    $anio,
                    $request->input('catedras', []),
                    $request->input('seccion'),
                );
            }

            return $estudiante;
        });

        return redirect()->route('estudiantes.show', $estudiante)->with('exito', 'Estudiante registrado correctamente.');
    }

    public function show(Estudiante $estudiante, RegistroAsistencia $asistencia, CalculadoraNotas $calculadora): View
    {
        $estudiante->load([
            'matriculas' => fn ($q) => $q->with([
                'anioEscolar',
                'inscripciones.catedra.asignatura',
                'inscripciones.catedra.nivel',
                'inscripciones.catedra.profesor',
                'inscripciones.catedra.anioEscolar',
            ]),
        ]);

        $matriculas = $estudiante->matriculas->sortByDesc(fn ($m) => $m->anioEscolar->nombre)->values();
        $vigente = $matriculas->first(fn ($m) => ! $m->anioEscolar->estaCerrado());

        $inscripcionIds = $matriculas->flatMap->inscripciones->pluck('id');
        $resumenAsistencia = $asistencia->resumen($inscripcionIds);

        // Progreso de aprobación y desglose de notas (solo las notas de este estudiante).
        $todas = Collection::make($matriculas->flatMap->inscripciones->all())->load([
            'catedra.lapsos.evaluaciones.notas' => fn ($q) => $q->whereIn('inscripcion_id', $inscripcionIds),
        ]);
        $progresos = $todas->mapWithKeys(fn ($i) => [$i->id => $calculadora->progreso($i)]);

        $aniosAbiertos = AnioEscolar::query()->abiertos()->recientes()->get()
            ->reject(fn ($a) => $matriculas->contains('anio_escolar_id', $a->id));

        $catedrasDisponibles = $vigente
            ? $this->catedrasDe($vigente->anioEscolar)
                ->reject(fn ($c) => $vigente->inscripciones->contains('catedra_id', $c->id))
            : collect();

        $aprobadas = GestionInscripciones::materiasAprobadas($estudiante->id);

        return view('estudiantes.show', compact(
            'estudiante',
            'matriculas',
            'vigente',
            'resumenAsistencia',
            'aniosAbiertos',
            'catedrasDisponibles',
            'aprobadas',
            'progresos',
        ));
    }

    public function edit(Estudiante $estudiante): View
    {
        return view('estudiantes.edit', compact('estudiante'));
    }

    public function update(EstudianteRequest $request, Estudiante $estudiante): RedirectResponse
    {
        $estudiante->update($request->datosEstudiante());

        return redirect()->route('estudiantes.show', $estudiante)->with('exito', 'Datos actualizados.');
    }

    public function destroy(Estudiante $estudiante): RedirectResponse
    {
        $tieneHistorial = Inscripcion::query()
            ->where('estudiante_id', $estudiante->id)
            ->where(fn ($q) => $q->whereHas('asistencias')->orWhereHas('notas', fn ($n) => $n->whereNotNull('valor')))
            ->exists();

        if ($tieneHistorial) {
            return back()->with('error', 'El estudiante tiene asistencias o notas registradas. Para darlo de baja use "Retirar estudiante".');
        }

        $estudiante->delete();

        return redirect()->route('estudiantes.index')->with('exito', 'Registro eliminado.');
    }

    /** Año en el que se inscribe por defecto: el de trabajo si no está cerrado; si no, uno abierto. */
    private function anioParaInscribir(): ?AnioEscolar
    {
        $anio = ContextoAnio::anio();

        if ($anio && ! $anio->estaCerrado()) {
            return $anio;
        }

        return AnioEscolar::actual() ?? AnioEscolar::query()->abiertos()->recientes()->first();
    }

    /** @return Collection<int, \App\Models\Catedra> */
    private function catedrasDe(AnioEscolar $anio): Collection
    {
        return $anio->catedras()
            ->ordenadas()
            ->with(['asignatura', 'nivel', 'profesor'])
            ->whereNull('notas_cerradas_at')
            ->get();
    }
}
