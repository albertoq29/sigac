<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\User;
use App\Support\ContextoAnio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Profesores y usuarios de Control de Estudios.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $anio = ContextoAnio::anio();

        $usuarios = User::query()
            ->when($request->query('rol'), fn ($q, $rol) => $q->where('rol', $rol))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhere('username', 'like', "%{$t}%")
                ->orWhere('cedula', 'like', "%{$t}%")))
            ->withCount(['catedras' => fn ($q) => $q->where('anio_escolar_id', $anio?->id)])
            ->orderBy('rol')
            ->orderBy('name')
            ->get();

        return view('usuarios.index', compact('usuarios', 'anio'));
    }

    public function create(): View
    {
        return view('usuarios.form', ['usuario' => new User(['rol' => Rol::Profesor, 'activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $password = Str::password(10, symbols: false);

        $usuario = User::query()->create($datos + [
            'password' => $password,
            'debe_cambiar_password' => true,
        ]);

        return redirect()->route('usuarios.index')
            ->with('exito', "Usuario {$usuario->username} creado.")
            ->with('credencial', ['usuario' => $usuario->username, 'password' => $password, 'nombre' => $usuario->name]);
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.form', compact('usuario'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        if ($usuario->is($request->user()) && (! $datos['activo'] || $datos['rol'] !== Rol::Control->value)) {
            return back()->with('error', 'No puede desactivar su propio usuario ni quitarse el rol de Control de Estudios.');
        }

        $usuario->update($datos);

        return redirect()->route('usuarios.index')->with('exito', 'Usuario actualizado.');
    }

    /** Genera una contraseña temporal (se muestra una sola vez). */
    public function restablecer(User $usuario): RedirectResponse
    {
        $password = Str::password(10, symbols: false);

        $usuario->update(['password' => $password, 'debe_cambiar_password' => true]);

        return back()
            ->with('exito', "Contraseña de {$usuario->username} restablecida.")
            ->with('credencial', ['usuario' => $usuario->username, 'password' => $password, 'nombre' => $usuario->name]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?User $usuario = null): array
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'name' => mb_strtoupper(trim((string) $request->input('name'))),
        ]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'regex:/^[a-z0-9._-]+$/', 'max:60', Rule::unique('users', 'username')->ignore($usuario?->id)],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'cedula' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'rol' => ['required', new Enum(Rol::class)],
            'activo' => ['boolean'],
        ], [
            'username.regex' => 'El usuario solo puede tener letras minúsculas sin acentos, números, puntos, guiones o guiones bajos.',
        ], ['name' => 'nombre', 'username' => 'usuario', 'email' => 'correo']);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
