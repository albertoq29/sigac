<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        abort_unless($usuario && in_array($usuario->rol->value, $roles, true), 403, 'No tiene permiso para acceder a esta sección.');

        return $next($request);
    }
}
