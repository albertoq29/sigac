<?php

namespace App\Http\Controllers;

use App\Models\AnioEscolar;
use App\Models\User;
use App\Services\AsistenciaOffline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Asistencia sin conexión: descarga del archivo para pasar lista sin internet
 * y carga del código (.txt) que ese archivo genera.
 */
class AsistenciaOfflineController extends Controller
{
    public function index(Request $request): View
    {
        return view('asistencia.offline', [
            'anio' => AnioEscolar::actual(),
            'profesores' => $request->user()->esControl()
                ? User::query()->profesores()->activos()->withCount(['catedras' => fn ($q) => $q->where('anio_escolar_id', AnioEscolar::actual()?->id)])->orderBy('name')->get()
                : collect(),
        ]);
    }

    /** Archivo HTML independiente que funciona sin internet. */
    public function descargar(Request $request, AsistenciaOffline $offline): Response|RedirectResponse
    {
        $anio = AnioEscolar::actual();

        if (! $anio) {
            return back()->with('error', 'No hay un año escolar en curso.');
        }

        $profesor = $request->user();
        if ($profesor->esControl() && $request->filled('profesor')) {
            $profesor = User::query()->profesores()->findOrFail($request->integer('profesor'));
        }

        $datos = $offline->datosParaArchivo($profesor, $anio);

        if ($datos['catedras']->isEmpty()) {
            return back()->with('error', "{$profesor->name} no tiene cátedras en el año escolar {$anio->nombre}.");
        }

        $html = view('asistencia.offline-app', [
            'datos' => $datos,
            'css' => $offline->css(),
            'prefijo' => AsistenciaOffline::PREFIJO,
            'urlCarga' => route('offline.index'),
        ])->render();

        $nombre = 'SIGAC-asistencia-sin-conexion-'.Str::slug($profesor->name).'-'.now()->format('Y-m-d').'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /** Vista previa de lo que se va a registrar. */
    public function revisar(Request $request, AsistenciaOffline $offline): View
    {
        $request->validate([
            'codigo' => ['nullable', 'string', 'max:2000000', 'required_without:archivo'],
            'archivo' => ['nullable', 'file', 'max:2048', 'mimetypes:text/plain', 'required_without:codigo'],
        ], [
            'codigo.required_without' => 'Pegue el código o seleccione el archivo .txt.',
            'archivo.required_without' => 'Pegue el código o seleccione el archivo .txt.',
            'archivo.mimetypes' => 'El archivo debe ser el .txt generado por la asistencia sin conexión.',
        ]);

        $texto = $request->hasFile('archivo')
            ? (string) file_get_contents($request->file('archivo')->getRealPath())
            : (string) $request->input('codigo');

        $carga = $offline->decodificar($texto);

        return view('asistencia.offline-revisar', [
            'carga' => $carga,
            'codigo' => $this->codigoLimpio($texto),
            'items' => $offline->analizar($carga, $request->user()),
            'generadoPorOtro' => (int) ($carga['u'] ?? 0) !== $request->user()->id,
        ]);
    }

    public function importar(Request $request, AsistenciaOffline $offline): RedirectResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'max:2000000']]);

        $carga = $offline->decodificar($request->input('codigo'));
        $resultado = $offline->importar($carga, $request->user(), $request->boolean('cerrar'));

        $mensaje = "{$resultado['importadas']} asistencia(s) registradas"
            .($request->boolean('cerrar') ? ' y cerradas' : '')
            .($resultado['omitidas'] ? "; {$resultado['omitidas']} omitida(s)." : '.');

        return redirect()->route('offline.index')->with($resultado['importadas'] ? 'exito' : 'error', $mensaje);
    }

    private function codigoLimpio(string $texto): string
    {
        preg_match('/'.AsistenciaOffline::PREFIJO.'-[A-Za-z0-9_-]+-[0-9a-f]{8}/', preg_replace('/\s+/', '', $texto), $m);

        return $m[0] ?? '';
    }
}
