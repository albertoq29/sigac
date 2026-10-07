<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->firstName().' '.fake()->lastName()),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'rol' => Rol::Profesor,
            'activo' => true,
            'debe_cambiar_password' => false,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function control(): static
    {
        return $this->state(fn () => ['rol' => Rol::Control]);
    }
}
