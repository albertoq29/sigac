<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        $usuario = config('sigac.admin.usuario', 'control');

        if (! User::query()->where('username', $usuario)->exists()) {
            $password = config('sigac.admin.password') ?: Str::password(12, symbols: false);

            User::query()->create([
                'name' => 'Control de Estudios',
                'username' => $usuario,
                'rol' => Rol::Control,
                'password' => $password,
                'debe_cambiar_password' => true,
            ]);

            if (! config('sigac.admin.password')) {
                $this->command?->warn("Usuario '{$usuario}' creado con la contraseña temporal: {$password}");
            }
        }
    }
}
