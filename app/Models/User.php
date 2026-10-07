<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'cedula',
        'telefono',
        'rol',
        'activo',
        'debe_cambiar_password',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
        ];
    }

    public function catedras(): HasMany
    {
        return $this->hasMany(Catedra::class, 'profesor_id');
    }

    public function esControl(): bool
    {
        return $this->rol === Rol::Control;
    }

    public function esProfesor(): bool
    {
        return $this->rol === Rol::Profesor;
    }

    public function scopeProfesores(Builder $query): Builder
    {
        return $query->where('rol', Rol::Profesor->value);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
