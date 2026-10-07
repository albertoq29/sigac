<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de usuarios desactivados y obliga a cambiar la contraseña
 * temporal antes de usar el sistema.
 */
class UsuarioHabilitado
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Su usuario está desactivado. Comuníquese con Control de Estudios.');
        }

        if ($usuario?->debe_cambiar_password && ! $request->routeIs('cuenta.*', 'logout')) {
            return redirect()->route('cuenta.password')
                ->with('aviso', 'Por seguridad, debe cambiar su contraseña temporal antes de continuar.');
        }

        return $next($request);
    }
}
