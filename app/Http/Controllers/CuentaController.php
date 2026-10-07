<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CuentaController extends Controller
{
    public function edit(): View
    {
        return view('cuenta.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:password_actual'],
        ], [], [
            'password_actual' => 'contraseña actual',
            'password' => 'nueva contraseña',
        ]);

        $request->user()->update([
            'password' => $request->input('password'),
            'debe_cambiar_password' => false,
        ]);

        return redirect()->route('inicio')->with('exito', 'Contraseña actualizada correctamente.');
    }
}
