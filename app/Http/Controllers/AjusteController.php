<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AjusteController extends Controller
{
    private const LOGOS = ['logo_izquierda', 'logo_centro', 'logo_derecha'];

    public function edit(): View
    {
        return view('ajustes.edit', ['ajustes' => Ajuste::todos()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'institucion_nombre' => ['required', 'string', 'max:200'],
            'institucion_nombre_texto' => ['nullable', 'string', 'max:200'],
            'institucion_ubicacion' => ['nullable', 'string', 'max:200'],
            'institucion_ciudad' => ['nullable', 'string', 'max:100'],
            'institucion_direccion' => ['nullable', 'string', 'max:300'],
            'institucion_telefono' => ['nullable', 'string', 'max:60'],
            'institucion_correo' => ['nullable', 'email', 'max:120'],
            'director_nombre' => ['nullable', 'string', 'max:120'],
            'director_resolucion' => ['nullable', 'string', 'max:200'],
            'control_estudios_nombre' => ['nullable', 'string', 'max:120'],
            'elaborado_por_nombre' => ['nullable', 'string', 'max:120'],
            'logo_izquierda' => ['nullable', 'string', 'max:500'],
            'logo_centro' => ['nullable', 'string', 'max:500'],
            'logo_derecha' => ['nullable', 'string', 'max:500'],
            'archivo_logo_izquierda' => ['nullable', 'image', 'max:2048'],
            'archivo_logo_centro' => ['nullable', 'image', 'max:2048'],
            'archivo_logo_derecha' => ['nullable', 'image', 'max:2048'],
            'nota_maxima' => ['required', 'numeric', 'min:1', 'max:100'],
            'nota_minima_aprobatoria' => ['required', 'numeric', 'min:0', 'lte:nota_maxima'],
            'redondear_definitiva' => ['boolean'],
        ], [], [
            'nota_maxima' => 'nota máxima',
            'nota_minima_aprobatoria' => 'nota mínima aprobatoria',
        ]);

        foreach (self::LOGOS as $logo) {
            if ($request->hasFile("archivo_{$logo}")) {
                $ruta = $request->file("archivo_{$logo}")->store('logos', 'public');
                $datos[$logo] = 'storage/'.$ruta;
            }
            unset($datos["archivo_{$logo}"]);
        }

        $datos['redondear_definitiva'] = $request->boolean('redondear_definitiva') ? '1' : '0';

        Ajuste::guardar(array_map(fn ($v) => $v === null ? null : (string) $v, $datos));

        return back()->with('exito', 'Ajustes guardados.');
    }
}
