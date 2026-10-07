<?php

namespace App\Http\Controllers;

use App\Models\Nivel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NivelController extends Controller
{
    public function index(): View
    {
        $niveles = Nivel::query()->ordenados()->with('siguiente')->withCount('catedras')->get();

        return view('catalogos.niveles', compact('niveles'));
    }

    public function store(Request $request): RedirectResponse
    {
        Nivel::query()->create($this->validar($request));

        return back()->with('exito', 'Nivel creado.');
    }

    public function update(Request $request, Nivel $nivel): RedirectResponse
    {
        $nivel->update($this->validar($request, $nivel));

        return back()->with('exito', 'Nivel actualizado.');
    }

    public function destroy(Nivel $nivel): RedirectResponse
    {
        if ($nivel->catedras()->exists()) {
            return back()->with('error', 'El nivel tiene cátedras registradas; no se puede eliminar.');
        }

        $nivel->delete();

        return back()->with('exito', 'Nivel eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Nivel $nivel = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('niveles', 'nombre')->ignore($nivel?->id)],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'tipo' => ['required', Rule::in([Nivel::TIPO_ANIO, Nivel::TIPO_NIVEL])],
            'siguiente_nivel_id' => ['nullable', Rule::exists('niveles', 'id'), Rule::notIn(array_filter([$nivel?->id]))],
        ], [], ['siguiente_nivel_id' => 'nivel siguiente']);
    }
}
