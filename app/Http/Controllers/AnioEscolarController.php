<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAnio;
use App\Enums\EstadoMatricula;
use App\Enums\Regimen;
use App\Models\AnioEscolar;
use App\Models\Catedra;
use App\Services\GestionAnioEscolar;
use App\Support\ContextoAnio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnioEscolarController extends Controller
{
    public function index(): View
    {
        $anios = AnioEscolar::query()
            ->recientes()
            ->withCount([
                'catedras',
                'matriculas',
                'matriculas as inscritos_count' => fn ($q) => $q->where('estado', EstadoMatricula::Inscrito->value),
                'matriculas as retirados_count' => fn ($q) => $q->where('estado', EstadoMatricula::Retirado->value),
            ])
            ->get();

        return view('anios.index', compact('anios'));
    }

    public function create(): View
    {
        $ultimo = AnioEscolar::query()->recientes()->first();
        $sugerido = $ultimo && preg_match('/^(\d{4})-(\d{4})$/', $ultimo->nombre, $m)
            ? ($m[1] + 1).'-'.($m[2] + 1)
            : now()->year.'-'.(now()->year + 1);

        return view('anios.form', [
            'anio' => new AnioEscolar([
                'nombre' => $sugerido,
                'regimen_predeterminado' => $ultimo?->regimen_predeterminado ?? Regimen::Trimestral,
                'lapsos_predeterminados' => $ultimo?->lapsos_predeterminados ?? 2,
            ]),
            'anteriores' => AnioEscolar::query()->recientes()->get(),
        ]);
    }

    public function store(Request $request, GestionAnioEscolar $gestion): RedirectResponse
    {
        $datos = $this->validar($request);
        $request->validate(['copiar_de' => ['nullable', Rule::exists('anios_escolares', 'id')]]);

        $anio = DB::transaction(function () use ($datos, $request, $gestion) {
            $anio = AnioEscolar::query()->create($datos + ['estado' => EstadoAnio::Planificacion]);

            if ($request->filled('copiar_de')) {
                $gestion->copiarCatedras(AnioEscolar::query()->findOrFail($request->integer('copiar_de')), $anio);
            }

            return $anio;
        });

        ContextoAnio::seleccionar($anio);

        return redirect()->route('anios.show', $anio)
            ->with('exito', "Año escolar {$anio->nombre} creado en planificación.");
    }

    public function show(AnioEscolar $anio, GestionAnioEscolar $gestion): View
    {
        $pendientes = $gestion->pendientes($anio);

        $catedrasPendientes = $anio->catedras()
            ->whereNull('notas_cerradas_at')
            ->ordenadas()
            ->with(['asignatura', 'nivel', 'profesor', 'lapsos'])
            ->limit(100)
            ->get();

        $otros = AnioEscolar::query()->whereKeyNot($anio->id)->recientes()->get();

        return view('anios.show', [
            'anio' => $anio->load('cerradoPor'),
            'pendientes' => $pendientes,
            'catedrasPendientes' => $catedrasPendientes,
            'otros' => $otros,
            'enCurso' => AnioEscolar::actual(),
            'matriculas' => $anio->matriculas()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
        ]);
    }

    public function edit(AnioEscolar $anio): View
    {
        return view('anios.form', ['anio' => $anio, 'anteriores' => collect()]);
    }

    public function update(Request $request, AnioEscolar $anio, GestionAnioEscolar $gestion): RedirectResponse
    {
        $anio->update($this->validar($request, $anio));

        $redireccion = redirect()->route('anios.show', $anio)->with('exito', 'Año escolar actualizado.');

        if ($request->boolean('aplicar_a_catedras') && ! $anio->estaCerrado()) {
            $resultado = $gestion->aplicarLapsosPredeterminados($anio);
            $mensaje = "Año escolar actualizado. {$resultado['actualizadas']} cátedra(s) ahora tienen "
                .$anio->regimen_predeterminado->descripcion($anio->lapsos_predeterminados).'.';

            $redireccion->with('exito', $mensaje);

            if ($resultado['omitidas']) {
                $redireccion->with('aviso', 'No se modificaron: '.implode('; ', $resultado['omitidas']).'.');
            }
        }

        return $redireccion;
    }

    public function iniciar(AnioEscolar $anio, GestionAnioEscolar $gestion): RedirectResponse
    {
        $gestion->iniciar($anio);
        ContextoAnio::seleccionar($anio);

        return back()->with('exito', "Año escolar {$anio->nombre} iniciado: ya se pueden registrar asistencias y notas.");
    }

    public function cerrar(Request $request, AnioEscolar $anio, GestionAnioEscolar $gestion): RedirectResponse
    {
        if (trim((string) $request->input('confirmacion')) !== $anio->nombre) {
            throw ValidationException::withMessages([
                'confirmacion' => "Escriba exactamente {$anio->nombre} para confirmar el cierre.",
            ]);
        }

        $gestion->cerrar($anio, $request->user());

        return redirect()->route('anios.show', $anio)
            ->with('exito', "Año escolar {$anio->nombre} cerrado. Sus notas y asistencias quedaron guardadas como historial.");
    }

    public function copiarCatedras(Request $request, AnioEscolar $anio, GestionAnioEscolar $gestion): RedirectResponse
    {
        $request->validate(['origen' => ['required', Rule::exists('anios_escolares', 'id'), Rule::notIn([$anio->id])]]);

        $creadas = $gestion->copiarCatedras(AnioEscolar::query()->findOrFail($request->integer('origen')), $anio);

        return back()->with('exito', "{$creadas} cátedra(s) copiadas.");
    }

    public function seleccionar(Request $request): RedirectResponse
    {
        $anio = AnioEscolar::query()->findOrFail($request->integer('anio_escolar_id'));
        ContextoAnio::seleccionar($anio);

        return back();
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?AnioEscolar $anio = null): array
    {
        // La fecha de cierre puede quedar "sin definir".
        if ($request->boolean('fecha_fin_sin_definir')) {
            $request->merge(['fecha_fin' => null]);
        }

        return $request->validate([
            'nombre' => [
                'required', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('anios_escolares', 'nombre')->ignore($anio?->id),
            ],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => array_filter(['nullable', 'date', $request->filled('fecha_inicio') ? 'after:fecha_inicio' : null]),
            'regimen_predeterminado' => ['required', new Enum(Regimen::class)],
            'lapsos_predeterminados' => ['required', 'integer', Rule::in(Catedra::LAPSOS_PERMITIDOS)],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'sin_registro_notas' => ['boolean'],
        ], [
            'nombre.regex' => 'El nombre debe tener el formato 2026-2027.',
        ], [
            'regimen_predeterminado' => 'régimen predeterminado',
            'lapsos_predeterminados' => 'cantidad de lapsos',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de cierre',
        ]);
    }
}
