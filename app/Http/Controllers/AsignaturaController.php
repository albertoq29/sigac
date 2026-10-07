<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AsignaturaController extends Controller
{
    public function index(): View
    {
        $asignaturas = Asignatura::query()
            ->withCount('catedras')
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get()
            ->groupBy(fn ($a) => $a->categoria ?: 'Sin categoría');

        return view('catalogos.asignaturas', compact('asignaturas'));
    }

    public function store(Request $request): RedirectResponse
    {
        Asignatura::query()->create($this->validar($request));

        return back()->with('exito', 'Asignatura creada.');
    }

    public function update(Request $request, Asignatura $asignatura): RedirectResponse
    {
        $asignatura->update($this->validar($request, $asignatura));

        return back()->with('exito', 'Asignatura actualizada.');
    }

    public function destroy(Asignatura $asignatura): RedirectResponse
    {
        if ($asignatura->catedras()->exists()) {
            return back()->with('error', 'La asignatura tiene cátedras registradas; puede desactivarla en lugar de eliminarla.');
        }

        $asignatura->delete();

        return back()->with('exito', 'Asignatura eliminada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Asignatura $asignatura = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('asignaturas', 'nombre')->ignore($asignatura?->id)],
            'categoria' => ['nullable', 'string', 'max:80'],
        ]);

        return $datos + ['activo' => $request->boolean('activo', true)];
    }
}
