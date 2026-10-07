<?php

namespace App\Http\Controllers;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMatricula;
use App\Enums\Regimen;
use App\Exceptions\ReglaNegocioException;
use App\Models\AnioEscolar;
use App\Models\Asignatura;
use App\Models\Catedra;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\User;
use App\Services\CalculadoraNotas;
use App\Services\GestionInscripciones;
use App\Services\RegistroAsistencia;
use App\Support\ContextoAnio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class CatedraController extends Controller
{
    public function index(Request $request): View
    {
        $anio = ContextoAnio::anio();
        $usuario = $request->user();

        $catedras = Catedra::query()
            ->delAnio($anio)
            ->when($usuario->esProfesor(), fn ($q) => $q->where('profesor_id', $usuario->id))
            ->when($request->integer('asignatura'), fn ($q, $id) => $q->where('asignatura_id', $id))
            ->when($request->integer('nivel'), fn ($q, $id) => $q->where('nivel_id', $id))
            ->when($request->integer('profesor'), fn ($q, $id) => $q->where('profesor_id', $id))
            ->when($request->query('sin_profesor'), fn ($q) => $q->whereNull('profesor_id'))
            ->ordenadas()
            ->with(['asignatura', 'nivel', 'profesor', 'lapsos'])
            ->withCount(['inscripcionesVigentes as alumnos_count', 'jornadas'])
            ->get();

        return view('catedras.index', [
            'anio' => $anio,
            'catedras' => $catedras,
            'asignaturas' => Asignatura::query()->orderBy('nombre')->get(),
            'niveles' => Nivel::query()->ordenados()->get(),
            'profesores' => User::query()->profesores()->orderBy('name')->get(),
        ]);
    }

    public function show(Catedra $catedra, RegistroAsistencia $asistencia, CalculadoraNotas $calculadora): View
    {
        $this->authorize('view', $catedra);

        $catedra->load(['anioEscolar', 'asignatura', 'nivel', 'profesor', 'lapsos.evaluaciones.notas']);

        $inscripciones = $catedra->inscripciones()
            ->with('estudiante')
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->estaRetirada() <=> $b->estaRetirada(),
                fn ($a, $b) => strcmp($a->estudiante->apellidos_nombres, $b->estudiante->apellidos_nombres),
            ])
            ->values();

        $resumenAsistencia = $asistencia->resumen($inscripciones->pluck('id'));
        $resumenNotas = $calculadora->resumenCatedra($catedra, $inscripciones);

        $otrasCatedras = request()->user()->esControl()
            ? $catedra->anioEscolar->catedras()
                ->where('asignatura_id', $catedra->asignatura_id)
                ->whereKeyNot($catedra->id)
                ->with(['nivel', 'asignatura', 'profesor'])
                ->get()
            : collect();

        return view('catedras.show', compact('catedra', 'inscripciones', 'resumenAsistencia', 'resumenNotas', 'otrasCatedras'));
    }

    public function create(Request $request): View
    {
        $anio = ContextoAnio::anio();

        abort_if(! $anio || $anio->estaCerrado(), 422, 'Seleccione un año escolar abierto para crear cátedras.');

        $catedra = new Catedra([
            'regimen' => $anio->regimen_predeterminado,
            'cantidad_lapsos' => $anio->lapsos_predeterminados,
            'seccion' => 'A',
            'asignatura_id' => $request->integer('asignatura') ?: null,
        ]);

        return view('catedras.form', $this->datosFormulario($catedra, $anio));
    }

    public function store(Request $request): RedirectResponse
    {
        $anio = ContextoAnio::anio();

        abort_if(! $anio || $anio->estaCerrado(), 422);

        $datos = $this->validar($request);
        $catedra = $anio->catedras()->create($datos);

        return redirect()->route('catedras.show', $catedra)
            ->with('exito', 'Cátedra creada con '.$catedra->regimen->descripcion($catedra->cantidad_lapsos).'.');
    }

    public function edit(Catedra $catedra): View
    {
        return view('catedras.form', $this->datosFormulario($catedra, $catedra->anioEscolar));
    }

    public function update(Request $request, Catedra $catedra): RedirectResponse
    {
        if ($catedra->anioEscolar->estaCerrado()) {
            throw new ReglaNegocioException('El año escolar está cerrado; la cátedra no se puede modificar.');
        }

        $datos = $this->validar($request);

        if ($catedra->notasCerradas() && (int) $datos['cantidad_lapsos'] !== $catedra->cantidad_lapsos) {
            throw new ReglaNegocioException('La cátedra ya tiene el cierre de notas; no se puede cambiar la cantidad de lapsos.');
        }

        DB::transaction(function () use ($catedra, $datos) {
            $catedra->update($datos);
            $catedra->sincronizarLapsos();
        });

        return redirect()->route('catedras.show', $catedra)->with('exito', 'Cátedra actualizada.');
    }

    public function destroy(Catedra $catedra): RedirectResponse
    {
        if ($catedra->inscripciones()->exists() || $catedra->jornadas()->exists()) {
            return back()->with('error', 'La cátedra tiene estudiantes o asistencias; no se puede eliminar.');
        }

        $catedra->delete();

        return redirect()->route('catedras.index')->with('exito', 'Cátedra eliminada.');
    }

    /** Formulario para agregar estudiantes inscritos en el año a la cátedra. */
    public function estudiantes(Catedra $catedra): View
    {
        $matriculas = Matricula::query()
            ->where('anio_escolar_id', $catedra->anio_escolar_id)
            ->where('estado', EstadoMatricula::Inscrito->value)
            ->whereDoesntHave('inscripciones', fn ($q) => $q->where('catedra_id', $catedra->id))
            // Quien ya aprobó esta asignatura en este nivel no puede volver a cursarla.
            ->whereDoesntHave('estudiante.inscripciones', fn ($q) => $q
                ->where('estado', EstadoInscripcion::Aprobada->value)
                ->whereHas('catedra', fn ($c) => $c
                    ->where('asignatura_id', $catedra->asignatura_id)
                    ->where('nivel_id', $catedra->nivel_id)))
            ->with(['estudiante', 'inscripciones.catedra.asignatura', 'inscripciones.catedra.nivel'])
            ->get()
            ->sortBy(fn ($m) => $m->estudiante->apellidos_nombres)
            ->values();

        return view('catedras.estudiantes', compact('catedra', 'matriculas'));
    }

    public function agregarEstudiantes(Request $request, Catedra $catedra, GestionInscripciones $gestion): RedirectResponse
    {
        $datos = $request->validate([
            'matriculas' => ['required', 'array', 'min:1'],
            'matriculas.*' => ['integer', Rule::exists('matriculas', 'id')->where('anio_escolar_id', $catedra->anio_escolar_id)],
        ], ['matriculas.required' => 'Seleccione al menos un estudiante.']);

        DB::transaction(function () use ($datos, $catedra, $gestion) {
            foreach (Matricula::query()->whereIn('id', $datos['matriculas'])->get() as $matricula) {
                $gestion->asignarCatedras($matricula, [$catedra->id]);
            }
        });

        return redirect()->route('catedras.show', $catedra)
            ->with('exito', count($datos['matriculas']).' estudiante(s) agregados a la cátedra.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'asignatura_id' => ['required', Rule::exists('asignaturas', 'id')],
            'nivel_id' => ['required', Rule::exists('niveles', 'id')],
            'seccion' => ['required', 'string', 'max:10'],
            'profesor_id' => ['nullable', Rule::exists('users', 'id')->where('rol', 'profesor')],
            'horario' => ['nullable', 'string', 'max:80'],
            'regimen' => ['required', new Enum(Regimen::class)],
            'cantidad_lapsos' => ['required', 'integer', Rule::in(Catedra::LAPSOS_PERMITIDOS)],
        ], [], [
            'cantidad_lapsos' => 'cantidad de lapsos',
            'asignatura_id' => 'asignatura',
            'nivel_id' => 'nivel',
            'profesor_id' => 'profesor',
            'regimen' => 'régimen',
        ]);

        $datos['seccion'] = mb_strtoupper(trim($datos['seccion']));

        return $datos;
    }

    /** @return array<string, mixed> */
    private function datosFormulario(Catedra $catedra, AnioEscolar $anio): array
    {
        return [
            'catedra' => $catedra,
            'anio' => $anio,
            'asignaturas' => Asignatura::query()->activas()->orderBy('categoria')->orderBy('nombre')->get()->groupBy('categoria'),
            'niveles' => Nivel::query()->ordenados()->get(),
            'profesores' => User::query()->profesores()->activos()->orderBy('name')->get(),
        ];
    }
}
